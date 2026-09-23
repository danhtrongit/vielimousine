<?php
declare(strict_types=1);

namespace Vie\Service\Payment;

use Vie\Container;
use Vie\DTO\PaymentRequest;
use Vie\Repository\ActivityLogRepository;
use Vie\Repository\OrderRepository;
use Vie\Repository\SepayWebhookEventRepository;
use Vie\Service\BookingQuote\BookingQuotePaymentService;
use Vie\Service\Settings\InvoiceSettings;
use Vie\Service\Settings\SepaySettings;

/** Processes SePay bank-account Webhooks with a durable idempotent inbox. */
final class SepayWebhook
{
    public function __construct(
        private readonly SepaySettings $settings,
        private readonly OrderRepository $orderRepo,
        private readonly PaymentLedger $ledger,
        private readonly ActivityLogRepository $activityRepo,
        private readonly ?SepayWebhookSignature $webhookSignature = null,
        private readonly ?SepayWebhookEventRepository $eventRepo = null,
        private readonly ?InvoiceSettings $invoiceSettings = null,
        private readonly ?BookingQuotePaymentService $quotePayment = null,
    ) {
    }

    /** @return array{accepted:bool,reason:string,retryable?:bool} */
    public function handleRaw(string $rawBody, string $signature, string $timestamp, ?string $ip = null): array
    {
        $verifier = $this->webhookSignature ?? new SepayWebhookSignature();
        $verified = $verifier->verify($rawBody, $signature, $timestamp, $this->settings->webhookSecret());
        if (!$verified['valid']) {
            $this->logActivity('sepay_webhook_auth_failed', ['reason' => $verified['reason']], $ip);
            return ['accepted' => false, 'reason' => $verified['reason']];
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['accepted' => false, 'reason' => 'invalid_json'];
        }
        if (!is_array($payload)) {
            return ['accepted' => false, 'reason' => 'invalid_json'];
        }

        $eventId = trim((string) ($payload['id'] ?? ''));
        if ($eventId === '' || strlen($eventId) > 100) {
            return ['accepted' => false, 'reason' => 'missing_id'];
        }

        $events = $this->eventRepo ?? new SepayWebhookEventRepository();
        $stored = $events->create($eventId, $rawBody, $payload);
        $event = $stored['event'];
        if ($stored['duplicate'] && !hash_equals((string) ($event['payload_hash'] ?? ''), hash('sha256', $rawBody))) {
            $events->markReview((int) $event['id'], 'payload_changed_on_replay');
            return ['accepted' => true, 'reason' => 'payload_changed_on_replay'];
        }
        if ($stored['duplicate'] && in_array((string) ($event['status'] ?? ''), ['processed', 'review'], true)) {
            return ['accepted' => true, 'reason' => 'duplicate_ignored'];
        }

        try {
            $result = $this->dispatch($payload, $ip, (int) $event['id']);
            if (($result['retryable'] ?? false) === true) {
                $events->markFailed((int) $event['id'], (string) ($result['reason'] ?? 'processing_error'));
                return $result;
            }
            if ($this->isReviewReason((string) ($result['reason'] ?? ''))) {
                $events->markReview((int) $event['id'], (string) ($result['reason'] ?? 'review_required'));
            } else {
                $events->markProcessed((int) $event['id']);
            }
            return $result;
        } catch (\Throwable $e) {
            try {
                $events->markFailed((int) $event['id'], $e->getMessage());
            } catch (\Throwable) {
                // The original processing error is still retryable.
            }
            return ['accepted' => false, 'reason' => 'storage_error', 'retryable' => true];
        }
    }

    /** Retry events whose request was persisted but processing failed. */
    public function retryPending(int $limit = 50): void
    {
        $events = $this->eventRepo ?? new SepayWebhookEventRepository();
        foreach ($events->pending($limit) as $event) {
            $payload = json_decode((string) ($event['raw_payload'] ?? ''), true);
            if (!is_array($payload)) {
                $events->markFailed((int) $event['id'], 'Invalid persisted payload');
                continue;
            }
            try {
                $result = $this->dispatch($payload, null, (int) $event['id']);
                if (($result['retryable'] ?? false) === true) {
                    $events->markFailed((int) $event['id'], (string) ($result['reason'] ?? 'processing_error'));
                } elseif ($this->isReviewReason((string) ($result['reason'] ?? ''))) {
                    $events->markReview((int) $event['id'], (string) ($result['reason'] ?? 'review_required'));
                } else {
                    $events->markProcessed((int) $event['id']);
                }
            } catch (\Throwable $e) {
                $events->markFailed((int) $event['id'], $e->getMessage());
            }
        }
    }

    /** @return array{accepted:bool,reason:string,retryable?:bool} */
    private function dispatch(array $payload, ?string $ip, ?int $eventId = null): array
    {
        $code = strtoupper(trim((string) ($payload['code'] ?? '')));
        $direction = strtolower(trim((string) ($payload['transferType'] ?? '')));
        $amount = $this->parseAmount($payload['transferAmount'] ?? null);

        if ($direction !== 'in') {
            $this->logActivity('sepay_webhook_ignored_outgoing', ['id' => $payload['id'] ?? null, 'code' => $code], $ip);
            return ['accepted' => true, 'reason' => 'ignored_non_incoming'];
        }
        if ($amount === null || $amount <= 0) {
            return ['accepted' => true, 'reason' => 'invalid_amount'];
        }
        if ($code === '' || (!str_starts_with($code, 'VIE') && !str_starts_with($code, 'VQ'))) {
            $this->logActivity('sepay_webhook_unknown_code', ['id' => $payload['id'] ?? null, 'code' => $code, 'amount' => $amount], $ip);
            return ['accepted' => true, 'reason' => 'unknown_code'];
        }

        if (str_starts_with($code, 'VQ')) {
            $service = $this->quotePayment ?? Container::get(BookingQuotePaymentService::class);
            $result = $service->processWebhook($payload, $ip, $eventId);
            if (($result['retryable'] ?? false) === true || ($result['reason'] ?? '') === 'storage_error') {
                $result['retryable'] = true;
            }
            return $result + ['accepted' => false, 'reason' => 'quote_review'];
        }

        return $this->processOrder($payload, $code, $amount, $ip, $eventId);
    }

    /** @return array{accepted:bool,reason:string,retryable?:bool} */
    private function processOrder(array $payload, string $code, int $amount, ?string $ip, ?int $eventId = null): array
    {
        $settings = $this->invoiceSettings ?? Container::get(InvoiceSettings::class);
        $bank = $settings->all();
        $account = preg_replace('/\s+/', '', trim((string) ($payload['accountNumber'] ?? '')));
        $configured = preg_replace('/\s+/', '', (string) ($bank['bank_account'] ?? ''));
        if ($account === '' || $configured === '' || !hash_equals($configured, $account)) {
            return ['accepted' => true, 'reason' => 'account_mismatch'];
        }

        $order = $this->orderRepo->findByCode($code);
        if ($order === null) {
            return ['accepted' => true, 'reason' => 'unknown_order'];
        }
        if ((string) ($order['status'] ?? '') === 'cancelled') {
            return ['accepted' => true, 'reason' => 'order_cancelled_manual_review'];
        }

        $transactionId = trim((string) ($payload['id'] ?? ''));
        if ($transactionId === '') {
            return ['accepted' => true, 'reason' => 'missing_id'];
        }
        if ($this->findExistingPayment($transactionId) !== null) {
            return ['accepted' => true, 'reason' => 'duplicate_ignored'];
        }

        $remaining = max(0, (int) ($order['total'] ?? 0) - (int) ($order['paid_amount'] ?? 0));
        if ($remaining <= 0 || $amount > $remaining) {
            $this->logActivity('sepay_webhook_order_amount_review', [
                'id' => $transactionId, 'code' => $code, 'amount' => $amount, 'remaining' => $remaining,
            ], $ip);
            return ['accepted' => true, 'reason' => 'amount_mismatch'];
        }

        $request = new PaymentRequest(
            orderId: (int) $order['id'],
            type: 'payment',
            amount: $amount,
            method: 'bank_transfer',
            gateway: 'sepay',
            transactionId: $transactionId,
            note: 'SePay Webhook' . (($payload['content'] ?? '') !== '' ? ' (' . sanitize_text_field((string) $payload['content']) . ')' : ''),
            paidAt: function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
            createdBy: null,
            rawPayload: $payload,
            webhookEventId: $eventId,
        );
        try {
            $this->ledger->record($request);
        } catch (IdempotencyConflictException) {
            return ['accepted' => true, 'reason' => 'duplicate_ignored'];
        } catch (\Throwable) {
            $this->logActivity('sepay_webhook_order_error', ['id' => $transactionId, 'code' => $code], $ip);
            return ['accepted' => false, 'reason' => 'storage_error', 'retryable' => true];
        }
        $this->logActivity('sepay_webhook_order_accepted', ['id' => $transactionId, 'code' => $code, 'amount' => $amount], $ip);
        return ['accepted' => true, 'reason' => 'accepted'];
    }

    private function parseAmount(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 && $value <= 999999999999 ? $value : null;
        }
        $raw = trim((string) $value);
        if (!preg_match('/^(0|[1-9][0-9]*)(?:\.0+)?$/', $raw)) {
            return null;
        }
        $integer = explode('.', $raw, 2)[0];
        if (strlen($integer) > 12) {
            return null;
        }
        return (int) $integer;
    }

    private function findExistingPayment(string $transactionId): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_payment_log';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE gateway = %s AND transaction_id = %s LIMIT 1",
            'sepay', $transactionId
        ), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    private function isReviewReason(string $reason): bool
    {
        return in_array($reason, [
            'review_required', 'account_mismatch', 'unknown_order', 'unknown_invoice',
            'invalid_invoice', 'invalid_amount', 'amount_mismatch', 'transaction_reused',
            'quote_expired', 'quote_revoked', 'quote_unavailable', 'current_due_mismatch',
            'extra_receipt', 'existing_review', 'currency_mismatch', 'order_cancelled_manual_review',
            'missing_id', 'unknown_code', 'payload_changed_on_replay',
        ], true);
    }

    private function logActivity(string $action, array $payload, ?string $ip): void
    {
        try {
            $this->activityRepo->create([
                'actor_user_id' => 0,
                'entity_type' => 'sepay_webhook',
                'entity_id' => 0,
                'action' => $action,
                'before_json' => null,
                'after_json' => $payload,
                'ip' => $ip,
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null,
            ]);
        } catch (\Throwable) {
            // Audit logging must never turn a valid Webhook into a retry storm.
        }
    }
}
