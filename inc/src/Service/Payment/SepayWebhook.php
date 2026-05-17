<?php
declare(strict_types=1);

namespace Vie\Service\Payment;

use Vie\DTO\PaymentRequest;
use Vie\Repository\ActivityLogRepository;
use Vie\Repository\OrderRepository;
use Vie\Repository\PaymentLogRepository;
use Vie\Service\Settings\SepaySettings;

final class SepayWebhook
{
    /** Cửa sổ thời gian chấp nhận webhook so với `timestamp` trong payload. */
    private const TIMESTAMP_WINDOW_SECONDS = 300;

    /** Chỉ field này được persist vào raw_payload. Field ngoài whitelist bị loại bỏ. */
    private const RAW_PAYLOAD_WHITELIST = [
        'order_invoice_number', 'transaction_id', 'amount', 'status',
        'paid_at', 'payment_method', 'currency', 'timestamp',
    ];

    public function __construct(
        private readonly SepaySettings $settings,
        private readonly SepaySignature $signature,
        private readonly OrderRepository $orderRepo,
        private readonly PaymentLogRepository $paymentRepo,
        private readonly PaymentLedger $ledger,
        private readonly ActivityLogRepository $activityRepo,
    ) {
    }

    /**
     * @return array{accepted: bool, reason: ?string}
     */
    public function handle(array $payload, string $signature, ?string $ip = null): array
    {
        // 0. Fail-closed: nếu secret_key rỗng (chưa cấu hình), refuse mọi IPN.
        // Trước đây verify với secret='' luôn ra signature giống nhau → endpoint
        // không xác thực — nguy hiểm nếu admin quên config.
        if (trim($this->settings->secretKey()) === '') {
            $this->logActivity('sepay_webhook_no_secret', $payload, $ip);
            return ['accepted' => false, 'reason' => 'gateway_not_configured'];
        }

        // 1. Verify signature
        if (!$this->signature->verifyWebhook($payload, $signature)) {
            $this->logActivity('sepay_webhook_invalid_sig', $payload, $ip);
            return ['accepted' => false, 'reason' => 'invalid_signature'];
        }

        // 2. Validate payload structure
        $required = ['order_invoice_number', 'transaction_id', 'amount', 'status'];
        foreach ($required as $field) {
            if (!isset($payload[$field]) || $payload[$field] === '') {
                $this->logActivity('sepay_webhook_missing_field', $payload, $ip);
                return ['accepted' => false, 'reason' => 'missing_field:' . $field];
            }
        }

        // 2b. Replay-protection bằng timestamp (nếu SePay gửi `timestamp`).
        // Khi SePay chưa hỗ trợ, payload thiếu field này → skip check (giữ
        // tương thích). Khi có, reject nếu lệch > 5 phút.
        if (isset($payload['timestamp']) && $payload['timestamp'] !== '') {
            $ts = (int) $payload['timestamp'];
            if ($ts <= 0 || abs(time() - $ts) > self::TIMESTAMP_WINDOW_SECONDS) {
                $this->logActivity('sepay_webhook_stale_timestamp', $payload, $ip);
                return ['accepted' => false, 'reason' => 'stale_timestamp'];
            }
        }

        // 2c. Amount sanity — không bao giờ chấp nhận amount âm/zero qua webhook
        // (refund đi qua endpoint admin thủ công, không qua IPN).
        $amount = (int) $payload['amount'];
        if ($amount <= 0) {
            $this->logActivity('sepay_webhook_invalid_amount', $payload, $ip);
            return ['accepted' => false, 'reason' => 'invalid_amount'];
        }

        // 3. Only process success
        if ((string) $payload['status'] !== 'success') {
            $this->logActivity('sepay_webhook_non_success', $payload, $ip);
            return ['accepted' => false, 'reason' => 'non_success_status'];
        }

        // 4. Lookup order
        $order = $this->orderRepo->findByCode((string) $payload['order_invoice_number']);
        if ($order === null) {
            $this->logActivity('sepay_webhook_unknown_order', $payload, $ip);
            return ['accepted' => false, 'reason' => 'unknown_order'];
        }

        // 4b. Order đã cancelled — không auto-confirm, ghi activity để operator
        // hoàn tiền thủ công (return 200 để SePay không retry).
        if ((string) ($order['status'] ?? '') === 'cancelled') {
            $this->logActivity('sepay_webhook_into_cancelled_order',
                array_merge($payload, ['order_id' => (int) $order['id']]), $ip);
            return ['accepted' => true, 'reason' => 'order_cancelled_manual_refund_needed'];
        }

        // 5. Idempotency
        $existing = $this->findExistingPayment((string) $payload['transaction_id']);
        if ($existing !== null) {
            $this->logActivity('sepay_webhook_duplicate', $payload, $ip);
            return ['accepted' => true, 'reason' => 'duplicate_ignored'];
        }

        // 6. Build PaymentRequest + record via ledger
        $req = new PaymentRequest(
            orderId:       (int) $order['id'],
            type:          'payment',
            amount:        $amount,
            method:        'sepay',
            gateway:       'sepay',
            transactionId: (string) $payload['transaction_id'],
            note:          'SePay IPN',
            paidAt:        isset($payload['paid_at']) ? (string) $payload['paid_at'] : current_time('mysql'),
            createdBy:     null,
            rawPayload:    $this->maskedPayload($payload),
        );

        try {
            $this->ledger->record($req);
        } catch (IdempotencyConflictException $e) {
            // Race condition — đã được xử lý ở pre-check, ở đây phòng hờ
            $this->logActivity('sepay_webhook_duplicate', $payload, $ip);
            return ['accepted' => true, 'reason' => 'duplicate_ignored'];
        } catch (\Throwable $e) {
            $this->logActivity('sepay_webhook_error', array_merge($payload, ['error' => $e->getMessage()]), $ip);
            return ['accepted' => false, 'reason' => 'ledger_error'];
        }

        $this->logActivity('sepay_webhook_accepted', $this->maskedPayload($payload), $ip);
        return ['accepted' => true, 'reason' => null];
    }

    /**
     * Lọc payload chỉ giữ các field nghiệp vụ cần audit, loại bỏ PII không cần
     * (số tài khoản đầy đủ, BIN, etc.). Field `signature` không bao giờ giữ.
     */
    private function maskedPayload(array $payload): array
    {
        $safe = [];
        foreach (self::RAW_PAYLOAD_WHITELIST as $key) {
            if (array_key_exists($key, $payload)) {
                $safe[$key] = $payload[$key];
            }
        }
        // Mask số tài khoản nếu SePay gửi (giữ 4 ký tự cuối để đối soát).
        if (isset($payload['account_number']) && is_string($payload['account_number'])) {
            $acc = $payload['account_number'];
            $safe['account_number_tail'] = strlen($acc) > 4 ? substr($acc, -4) : $acc;
        }
        return $safe;
    }

    private function findExistingPayment(string $txId): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_payment_log';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE gateway = %s AND transaction_id = %s LIMIT 1",
                'sepay',
                $txId
            ),
            ARRAY_A
        );
        return $row !== null ? $row : null;
    }

    private function logActivity(string $action, array $payload, ?string $ip): void
    {
        // Mask secret-like fields nếu có
        $safe = $payload;
        unset($safe['signature']);

        $this->activityRepo->create([
            'actor_user_id' => 0,
            'entity_type'   => 'sepay_webhook',
            'entity_id'     => 0,
            'action'        => $action,
            'before_json'   => null,
            'after_json'    => $safe,
            'ip'            => $ip,
            'user_agent'    => isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null,
        ]);
    }
}
