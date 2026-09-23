<?php
declare(strict_types=1);

namespace Vie\Service\BookingQuote;

use Vie\Repository\BookingQuotePaymentRepository;
use Vie\Repository\BookingQuoteRepository;
use Vie\Service\Payment\BankTransferInstructions;
use Vie\Service\Settings\InvoiceSettings;

/**
 * Creates immutable quote-payment intents and reconciles authenticated SePay
 * bank-account Webhooks. A paid quotation is still only a quotation: this
 * service deliberately never creates/confirms an order or reserves stock.
 */
final class BookingQuotePaymentService
{
    private const MAX_AMOUNT = 999999999999;

    public function __construct(
        private readonly BankTransferInstructions $transferInstructions,
        private readonly InvoiceSettings $invoiceSettings,
        private readonly BookingQuoteRepository $quoteRepo,
        private readonly BookingQuotePaymentRepository $paymentRepo,
        private readonly BookingQuotePolicy $policy,
    ) {
    }

    /**
     * @return array{amount:int,purpose:string,transfer:array}
     */
    public function checkout(string $publicId): array
    {
        if (!$this->transferInstructions->isConfigured()) {
            throw new BookingQuoteException('Thông tin nhận chuyển khoản chưa được cấu hình.', 'bank_unconfigured', 503);
        }

        global $wpdb;
        try {
            $this->beginTransaction();
            // All financial paths lock the quote before an intent.
            $quote = $this->quoteRepo->lockForUpdateByPublicId($publicId);
            if ($quote === null) {
                throw new BookingQuoteException('Không tìm thấy báo giá.', 'not_found', 404);
            }
            if (!$this->policy->canCheckout($quote)) {
                throw new BookingQuoteException(
                    'Báo giá hiện không thể thanh toán.',
                    'checkout_unavailable',
                    409,
                );
            }

            $amount = $this->policy->dueAmount($quote);
            if ($amount <= 0 || $amount > self::MAX_AMOUNT) {
                throw new BookingQuoteException('Số tiền thanh toán không hợp lệ.', 'invalid_amount', 409);
            }
            $purpose = (int) ($quote['paid_amount'] ?? 0) > 0 ? 'balance' : 'deposit';

            $intent = $this->paymentRepo->findPending((int) $quote['id'], $purpose, $amount);
            if ($intent === null) {
                $intent = $this->createIntent((int) $quote['id'], $purpose, $amount);
            }

            $transfer = $this->transferInstructions->build((int) $intent['expected_amount'], (string) $intent['invoice']);
            $this->commitTransaction();
        } catch (BookingQuoteException $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw new BookingQuoteException(
                'Không thể khởi tạo thanh toán. Vui lòng thử lại.',
                'payment_storage_error',
                500,
                null,
                $e,
            );
        }

        return [
            'amount'   => (int) $intent['expected_amount'],
            'purpose'  => (string) $intent['purpose'],
            'transfer' => $transfer,
        ];
    }

    /** Core flat-event handler. The controller must authenticate the raw body before calling. */
    public function processWebhook(array $payload, ?string $ip = null, ?int $webhookEventId = null): array
    {
        unset($ip);

        $eventId = trim((string) ($payload['id'] ?? ''));
        $invoice = strtoupper(trim((string) ($payload['code'] ?? '')));
        if (!preg_match('/^VQ[A-F0-9]{30}$/', $invoice)) {
            return ['accepted' => false, 'reason' => 'invalid_invoice'];
        }
        if ($eventId === '' || strlen($eventId) > 100) {
            return ['accepted' => false, 'reason' => 'invalid_transaction_id'];
        }
        if (strtolower(trim((string) ($payload['transferType'] ?? ''))) !== 'in') {
            return ['accepted' => true, 'reason' => 'ignored_non_incoming'];
        }

        $transactionId = $eventId;
        $receivedAmount = $this->parseVndAmount($payload['transferAmount'] ?? null);
        $orderAmount = $receivedAmount;
        if ($receivedAmount === null || $receivedAmount <= 0) {
            return ['accepted' => false, 'reason' => 'invalid_amount'];
        }
        $transactionCurrency = 'VND';
        $currencyMismatch = false;
        $bank = $this->invoiceSettings->all();
        $account = preg_replace('/\s+/', '', trim((string) ($payload['accountNumber'] ?? '')));
        $configuredAccount = preg_replace('/\s+/', '', (string) $bank['bank_account']);
        if ($account === '' || $configuredAccount === '' || !hash_equals($configuredAccount, $account)) {
            return ['accepted' => true, 'reason' => 'account_mismatch'];
        }

        // Read-only prefetch lets the transaction acquire locks in the global
        // order: quote first, then intent. The row is re-read under lock below.
        $prefetchedIntent = $this->paymentRepo->findByInvoice($invoice);
        if ($prefetchedIntent === null) {
            return ['accepted' => false, 'reason' => 'unknown_invoice'];
        }

        global $wpdb;
        try {
            $this->beginTransaction();
            $quote = $this->quoteRepo->lockForUpdate((int) $prefetchedIntent['quote_id']);
            if ($quote === null) {
                throw new \RuntimeException('Quote for payment intent no longer exists');
            }
            $intent = $this->paymentRepo->lockByInvoice($invoice);
            if ($intent === null || (int) $intent['quote_id'] !== (int) $quote['id']) {
                throw new \RuntimeException('Payment intent changed while acquiring locks');
            }

            $transactionUsage = $this->paymentRepo->findTransactionUsage($transactionId);
            if ($transactionUsage !== null) {
                $this->commitTransaction();
                if ($this->isIdenticalReplay(
                    $transactionUsage,
                    $invoice,
                    $receivedAmount,
                    $orderAmount,
                    'VND',
                )) {
                    return ['accepted' => true, 'reason' => 'duplicate_ignored'];
                }
                return ['accepted' => false, 'reason' => 'transaction_reused'];
            }

            $reviewReason = $this->reviewReason(
                $quote,
                $intent,
                $receivedAmount,
                $orderAmount,
                $currencyMismatch,
            );
            $paidAt = function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s');

            $this->paymentRepo->createReceiptChecked([
                'quote_id'        => (int) $quote['id'],
                'intent_id'       => (int) $intent['id'],
                'invoice'         => $invoice,
                'transaction_id'  => $transactionId,
                'received_amount' => $receivedAmount,
                'order_amount'    => $orderAmount,
                'currency'        => 'VND',
                'outcome'         => $reviewReason === null ? 'accepted' : 'review',
                'reason'          => $reviewReason,
                'paid_at'         => $paidAt,
                'webhook_event_id' => $webhookEventId,
            ]);

            if ($reviewReason !== null) {
                // Preserve an already-paid intent when an extra receipt arrives;
                // its original transaction remains authoritative. The extra is
                // retained in the receipt ledger and the quote is flagged.
                if ((string) $intent['status'] === 'pending') {
                    $this->paymentRepo->markReviewChecked(
                        (int) $intent['id'],
                        $transactionId,
                        $receivedAmount,
                        $paidAt,
                        $reviewReason,
                    );
                }
                $this->quoteRepo->updateFinancialStateChecked((int) $quote['id'], [
                    'paid_amount'    => (int) ($quote['paid_amount'] ?? 0),
                    'payment_review' => true,
                ]);
                $this->commitTransaction();
                return ['accepted' => true, 'reason' => 'review_required'];
            }

            $this->paymentRepo->markPaidChecked(
                (int) $intent['id'],
                $transactionId,
                $receivedAmount,
                $paidAt,
            );
            // Never increment from payload values. Recompute exclusively from
            // authenticated accepted receipts while relevant rows stay locked.
            $paidAmount = $this->paymentRepo->sumPaid((int) $quote['id']);
            $this->quoteRepo->updateFinancialStateChecked((int) $quote['id'], [
                'paid_amount'    => $paidAmount,
                'payment_review' => (bool) ($quote['payment_review'] ?? false),
            ]);
            $this->commitTransaction();

            return ['accepted' => true, 'reason' => null];
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');

            // A concurrent webhook can win the unique transaction constraint.
            // Resolve that race deterministically instead of asking SePay to
            // retry a notification already stored successfully.
            try {
                $transactionUsage = $this->paymentRepo->findTransactionUsage($transactionId);
                if ($transactionUsage !== null) {
                    if ($this->isIdenticalReplay(
                        $transactionUsage,
                        $invoice,
                        $receivedAmount,
                        $orderAmount,
                        'VND',
                    )) {
                        return ['accepted' => true, 'reason' => 'duplicate_ignored'];
                    }
                    return ['accepted' => false, 'reason' => 'transaction_reused'];
                }
            } catch (\Throwable) {
                // The original storage error remains retryable.
            }

            return ['accepted' => false, 'reason' => 'storage_error', 'retryable' => true];
        }
    }

    /**
     * Caller must already have passed the quote ownership/capability guard.
     */
    public function history(int $quoteId): array
    {
        return $this->paymentRepo->history($quoteId);
    }

    private function createIntent(int $quoteId, string $purpose, int $amount): array
    {
        return $this->paymentRepo->createIntentChecked([
            'quote_id'        => $quoteId,
            'invoice'         => 'VQ' . strtoupper(bin2hex(random_bytes(15))),
            'purpose'         => $purpose,
            'expected_amount' => $amount,
            'currency'        => 'VND',
            'status'          => 'pending',
        ]);
    }

    private function isIdenticalReplay(
        array $usage,
        string $invoice,
        int $receivedAmount,
        int $orderAmount,
        string $currency,
    ): bool {
        return (string) ($usage['source'] ?? '') === 'quote'
            && (string) ($usage['invoice'] ?? '') === $invoice
            && (int) ($usage['received_amount'] ?? -1) === $receivedAmount
            && (int) ($usage['order_amount'] ?? -1) === $orderAmount
            && strtoupper((string) ($usage['currency'] ?? '')) === strtoupper($currency);
    }

    private function reviewReason(
        array $quote,
        array $intent,
        int $receivedAmount,
        int $orderAmount,
        bool $currencyMismatch,
    ): ?string {
        if ((string) ($intent['status'] ?? '') !== 'pending') {
            return 'extra_receipt';
        }
        if ((string) ($quote['status'] ?? '') === 'revoked') {
            return 'quote_revoked';
        }
        if ($this->policy->effectiveStatus($quote) === 'expired') {
            return 'quote_expired';
        }
        if ((string) ($quote['status'] ?? '') !== 'published') {
            return 'quote_unavailable';
        }
        if (!empty($quote['payment_review'])) {
            return 'existing_review';
        }
        if ($currencyMismatch) {
            return 'currency_mismatch';
        }
        if ($receivedAmount !== $orderAmount || $receivedAmount !== (int) $intent['expected_amount']) {
            return 'amount_mismatch';
        }
        if ($this->policy->dueAmount($quote) !== (int) $intent['expected_amount']) {
            return 'current_due_mismatch';
        }
        return null;
    }

    private function parseVndAmount(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 && $value <= self::MAX_AMOUNT ? $value : null;
        }
        if (!is_string($value)) {
            return null;
        }
        $raw = trim((string) $value);
        if (!preg_match('/^(0|[1-9][0-9]*)(?:\.0+)?$/', $raw)) {
            return null;
        }
        $integer = explode('.', $raw, 2)[0];
        if (strlen($integer) > 12) {
            return null;
        }
        $amount = (int) $integer;
        return $amount <= self::MAX_AMOUNT ? $amount : null;
    }

    private function beginTransaction(): void
    {
        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new \RuntimeException('Could not start quote payment transaction');
        }
    }

    private function commitTransaction(): void
    {
        global $wpdb;
        if ($wpdb->query('COMMIT') === false) {
            throw new \RuntimeException('Could not commit quote payment transaction');
        }
    }
}
