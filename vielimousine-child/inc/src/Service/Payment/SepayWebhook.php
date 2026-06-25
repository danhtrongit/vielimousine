<?php
declare(strict_types=1);

namespace Vie\Service\Payment;

use Vie\DTO\PaymentRequest;
use Vie\Repository\ActivityLogRepository;
use Vie\Repository\OrderRepository;
use Vie\Repository\PaymentLogRepository;
use Vie\Service\Settings\SepaySettings;

/**
 * Xử lý IPN của SePay Cổng thanh toán (Payment Gateway).
 * Xác thực bằng header X-Secret-Key; payload lồng order{}/transaction{}.
 */
final class SepayWebhook
{
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
     * @param string $authSecret Giá trị header X-Secret-Key SePay gửi.
     * @return array{accepted: bool, reason: ?string}
     */
    public function handle(array $payload, string $authSecret, ?string $ip = null): array
    {
        $secret = trim($this->settings->secretKey());

        // 0. Fail-closed: chưa cấu hình secret → từ chối mọi IPN.
        if ($secret === '') {
            $this->logActivity('sepay_webhook_no_secret', $payload, $ip);
            return ['accepted' => false, 'reason' => 'gateway_not_configured'];
        }

        // 1. Xác thực: header X-Secret-Key phải khớp secret_key (so sánh hằng-thời-gian).
        if ($authSecret === '' || !hash_equals($secret, $authSecret)) {
            $this->logActivity('sepay_webhook_invalid_secret', $payload, $ip);
            return ['accepted' => false, 'reason' => 'invalid_secret'];
        }

        // 2. Parse payload lồng (Cổng thanh toán IPN).
        $orderObj    = is_array($payload['order'] ?? null) ? $payload['order'] : [];
        $txObj       = is_array($payload['transaction'] ?? null) ? $payload['transaction'] : [];
        $notiType    = (string) ($payload['notification_type'] ?? '');
        $invoice     = (string) ($orderObj['order_invoice_number'] ?? '');
        $orderStatus = (string) ($orderObj['order_status'] ?? '');
        $txStatus    = (string) ($txObj['transaction_status'] ?? '');
        $txType      = (string) ($txObj['transaction_type'] ?? 'PAYMENT');
        $txId        = (string) ($txObj['transaction_id'] ?? '');
        $amount      = (int) round((float) ($txObj['transaction_amount'] ?? $orderObj['order_amount'] ?? 0));

        if ($invoice === '' || $txId === '') {
            $this->logActivity('sepay_webhook_missing_field', $payload, $ip);
            return ['accepted' => false, 'reason' => 'missing_field'];
        }

        // 3. Chỉ xử lý thanh toán thành công: ORDER_PAID + CAPTURED + APPROVED + PAYMENT.
        //    Các sự kiện khác (VOID/DECLINED/refund) → trả 200 để SePay không retry, không xử lý.
        $isPaid = $notiType === 'ORDER_PAID'
            && $orderStatus === 'CAPTURED'
            && $txStatus === 'APPROVED'
            && $txType === 'PAYMENT';
        if (!$isPaid) {
            $this->logActivity('sepay_webhook_non_success', $this->maskedPayload($payload, $orderObj, $txObj), $ip);
            return ['accepted' => true, 'reason' => 'ignored_non_paid'];
        }

        if ($amount <= 0) {
            $this->logActivity('sepay_webhook_invalid_amount', $payload, $ip);
            return ['accepted' => false, 'reason' => 'invalid_amount'];
        }

        // 4. Lookup order theo order_invoice_number (= mã đơn).
        $order = $this->orderRepo->findByCode($invoice);
        if ($order === null) {
            $this->logActivity('sepay_webhook_unknown_order', $payload, $ip);
            return ['accepted' => false, 'reason' => 'unknown_order'];
        }

        // 4a. Amount phải khớp tổng đơn (chống tampering số tiền).
        if ($amount !== (int) ($order['total'] ?? -1)) {
            $this->logActivity(
                'sepay_webhook_amount_mismatch',
                array_merge($this->maskedPayload($payload, $orderObj, $txObj), [
                    'order_id'    => (int) $order['id'],
                    'order_total' => (int) ($order['total'] ?? 0),
                ]),
                $ip
            );
            return ['accepted' => false, 'reason' => 'amount_mismatch'];
        }

        // 4b. Đơn đã cancelled — cần hoàn tiền thủ công (trả 200, không retry).
        if ((string) ($order['status'] ?? '') === 'cancelled') {
            $this->logActivity(
                'sepay_webhook_into_cancelled_order',
                array_merge($this->maskedPayload($payload, $orderObj, $txObj), ['order_id' => (int) $order['id']]),
                $ip
            );
            return ['accepted' => true, 'reason' => 'order_cancelled_manual_refund_needed'];
        }

        // 5. Idempotency theo transaction_id.
        if ($this->findExistingPayment($txId) !== null) {
            $this->logActivity('sepay_webhook_duplicate', $this->maskedPayload($payload, $orderObj, $txObj), $ip);
            return ['accepted' => true, 'reason' => 'duplicate_ignored'];
        }

        // 6. Ghi nhận thanh toán qua ledger (tự cập nhật payment_status / confirm theo cấu hình).
        $req = new PaymentRequest(
            orderId:       (int) $order['id'],
            type:          'payment',
            amount:        $amount,
            method:        'sepay',
            gateway:       'sepay',
            transactionId: $txId,
            note:          'SePay IPN' . ($txObj['payment_method'] ?? '' ? ' (' . (string) $txObj['payment_method'] . ')' : ''),
            paidAt:        current_time('mysql'),
            createdBy:     null,
            rawPayload:    $this->maskedPayload($payload, $orderObj, $txObj),
        );

        try {
            $this->ledger->record($req);
        } catch (IdempotencyConflictException $e) {
            $this->logActivity('sepay_webhook_duplicate', $this->maskedPayload($payload, $orderObj, $txObj), $ip);
            return ['accepted' => true, 'reason' => 'duplicate_ignored'];
        } catch (\Throwable $e) {
            $this->logActivity('sepay_webhook_error', ['error' => $e->getMessage(), 'order_id' => (int) $order['id']], $ip);
            return ['accepted' => false, 'reason' => 'ledger_error'];
        }

        $this->logActivity('sepay_webhook_accepted', $this->maskedPayload($payload, $orderObj, $txObj), $ip);
        return ['accepted' => true, 'reason' => null];
    }

    /**
     * Trích các field nghiệp vụ cần audit từ payload lồng (không giữ PII thừa).
     * @return array<string,mixed>
     */
    private function maskedPayload(array $payload, array $orderObj = [], array $txObj = []): array
    {
        return [
            'notification_type'    => (string) ($payload['notification_type'] ?? ''),
            'timestamp'            => $payload['timestamp'] ?? null,
            'order_invoice_number' => (string) ($orderObj['order_invoice_number'] ?? ''),
            'order_status'         => (string) ($orderObj['order_status'] ?? ''),
            'order_amount'         => $orderObj['order_amount'] ?? null,
            'transaction_id'       => (string) ($txObj['transaction_id'] ?? ''),
            'transaction_amount'   => $txObj['transaction_amount'] ?? null,
            'transaction_status'   => (string) ($txObj['transaction_status'] ?? ''),
            'payment_method'       => (string) ($txObj['payment_method'] ?? ''),
        ];
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
