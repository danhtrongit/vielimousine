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
            amount:        (int) $payload['amount'],
            method:        'sepay',
            gateway:       'sepay',
            transactionId: (string) $payload['transaction_id'],
            note:          'SePay IPN',
            paidAt:        isset($payload['paid_at']) ? (string) $payload['paid_at'] : current_time('mysql'),
            createdBy:     null,
            rawPayload:    $payload,
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

        $this->logActivity('sepay_webhook_accepted', $payload, $ip);
        return ['accepted' => true, 'reason' => null];
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
            'user_agent'    => null,
        ]);
    }
}
