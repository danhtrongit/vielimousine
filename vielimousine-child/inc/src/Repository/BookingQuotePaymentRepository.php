<?php
declare(strict_types=1);

namespace Vie\Repository;

/**
 * Checked persistence for quotation payment intents and gateway receipts.
 *
 * Callers that mutate financial state must lock the quote first, then use the
 * locking intent reads here. This keeps checkout and webhook lock ordering
 * consistent across the feature.
 */
final class BookingQuotePaymentRepository
{
    private const MAX_AMOUNT = 999999999999;

    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->intentTable()} WHERE id = %d LIMIT 1", $id),
            ARRAY_A,
        );
        $this->assertReadSucceeded('find payment intent');

        return is_array($row) ? $this->castIntent($row) : null;
    }

    public function findByInvoice(string $invoice): ?array
    {
        $invoice = $this->validInvoice($invoice);

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->intentTable()} WHERE invoice = %s LIMIT 1", $invoice),
            ARRAY_A,
        );
        $this->assertReadSucceeded('find payment intent by invoice');

        return is_array($row) ? $this->castIntent($row) : null;
    }

    /**
     * Caller must already hold the corresponding quote row lock.
     */
    public function lockByInvoice(string $invoice): ?array
    {
        $invoice = $this->validInvoice($invoice);

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->intentTable()} WHERE invoice = %s LIMIT 1 FOR UPDATE",
                $invoice,
            ),
            ARRAY_A,
        );
        $this->assertReadSucceeded('lock payment intent by invoice');

        return is_array($row) ? $this->castIntent($row) : null;
    }

    /**
     * Reuses the one pending intent for the frozen quote terms. The quote row
     * must be locked before calling this method.
     */
    public function findPending(int $quoteId, string $purpose, int $amount): ?array
    {
        $this->assertPositiveId($quoteId, 'quote_id');
        $purpose = $this->validPurpose($purpose);
        $amount = $this->validAmount($amount, 'expected_amount', false);

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->intentTable()}
                  WHERE quote_id = %d AND purpose = %s AND expected_amount = %d
                    AND currency = 'VND' AND status = 'pending'
                  ORDER BY id ASC LIMIT 1 FOR UPDATE",
                $quoteId,
                $purpose,
                $amount,
            ),
            ARRAY_A,
        );
        $this->assertReadSucceeded('find pending payment intent');

        return is_array($row) ? $this->castIntent($row) : null;
    }

    public function findByTransaction(string $transactionId): ?array
    {
        $transactionId = $this->validTransactionId($transactionId);

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->intentTable()} WHERE transaction_id = %s LIMIT 1",
                $transactionId,
            ),
            ARRAY_A,
        );
        $this->assertReadSucceeded('find payment intent by transaction');

        return is_array($row) ? $this->castIntent($row) : null;
    }

    public function createIntentChecked(array $data): array
    {
        $quoteId = (int) ($data['quote_id'] ?? 0);
        $this->assertPositiveId($quoteId, 'quote_id');
        $purpose = $this->validPurpose((string) ($data['purpose'] ?? ''));
        $amount = $this->validAmount((int) ($data['expected_amount'] ?? -1), 'expected_amount', false);
        $invoice = $this->validInvoice((string) ($data['invoice'] ?? ''));
        $currency = strtoupper(trim((string) ($data['currency'] ?? 'VND')));
        if ($currency !== 'VND') {
            throw new RepositoryException('Booking quote payment intents only support VND');
        }
        if (($data['status'] ?? 'pending') !== 'pending') {
            throw new RepositoryException('A booking quote payment intent must start pending');
        }

        // Checkout calls this inside a transaction while holding the quote
        // lock. Re-checking here makes accidental duplicate calls idempotent.
        $pending = $this->findPending($quoteId, $purpose, $amount);
        if ($pending !== null) {
            return $pending;
        }

        $now = $this->now();
        global $wpdb;
        $result = $wpdb->insert(
            $this->intentTable(),
            [
                'quote_id'        => $quoteId,
                'invoice'         => $invoice,
                'purpose'         => $purpose,
                'expected_amount' => $amount,
                'currency'        => 'VND',
                'status'          => 'pending',
                'transaction_id'  => null,
                'received_amount' => 0,
                'paid_at'         => null,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s'],
        );
        if ($result !== 1 || (int) $wpdb->insert_id <= 0) {
            throw new RepositoryException('Could not create booking quote payment intent: ' . $wpdb->last_error);
        }

        $created = $this->find((int) $wpdb->insert_id);
        if ($created === null) {
            throw new RepositoryException('Created booking quote payment intent could not be reloaded');
        }

        return $created;
    }

    public function findReceiptByTransaction(string $transactionId): ?array
    {
        $transactionId = $this->validTransactionId($transactionId);

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->receiptTable()} WHERE transaction_id = %s LIMIT 1",
                $transactionId,
            ),
            ARRAY_A,
        );
        $this->assertReadSucceeded('find quote payment receipt by transaction');

        return is_array($row) ? $this->castReceipt($row) : null;
    }

    /**
     * Checks transaction use across quote receipts/intents and the existing
     * order ledger. Receipt rows are canonical for quote webhook replay.
     */
    public function findTransactionUsage(string $transactionId): ?array
    {
        $transactionId = $this->validTransactionId($transactionId);
        $receipt = $this->findReceiptByTransaction($transactionId);
        if ($receipt !== null) {
            return ['source' => 'quote'] + $receipt;
        }

        // Defensive fallback for a legacy/partially migrated paid intent.
        $intent = $this->findByTransaction($transactionId);
        if ($intent !== null) {
            return ['source' => 'quote', 'intent_id' => (int) $intent['id']] + $intent + [
                'order_amount' => (int) $intent['expected_amount'],
            ];
        }

        global $wpdb;
        $orderPaymentTable = $wpdb->prefix . 'vie_payment_log';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, order_id, transaction_id, amount, paid_at, created_at
                   FROM {$orderPaymentTable}
                  WHERE gateway = %s AND transaction_id = %s LIMIT 1",
                'sepay',
                $transactionId,
            ),
            ARRAY_A,
        );
        $this->assertReadSucceeded('find order payment transaction usage');
        if (!is_array($row)) {
            return null;
        }

        return [
            'source'          => 'order',
            'id'              => (int) ($row['id'] ?? 0),
            'order_id'        => (int) ($row['order_id'] ?? 0),
            'transaction_id'  => (string) ($row['transaction_id'] ?? ''),
            'received_amount' => (int) ($row['amount'] ?? 0),
            'paid_at'         => $row['paid_at'] ?? null,
            'created_at'      => $row['created_at'] ?? null,
        ];
    }

    public function createReceiptChecked(array $data): array
    {
        $quoteId = (int) ($data['quote_id'] ?? 0);
        $intentId = (int) ($data['intent_id'] ?? 0);
        $this->assertPositiveId($quoteId, 'quote_id');
        $this->assertPositiveId($intentId, 'intent_id');
        $invoice = $this->validInvoice((string) ($data['invoice'] ?? ''));
        $transactionId = $this->validTransactionId((string) ($data['transaction_id'] ?? ''));
        $receivedAmount = $this->validAmount((int) ($data['received_amount'] ?? -1), 'received_amount', false);
        $orderAmount = $this->validAmount((int) ($data['order_amount'] ?? -1), 'order_amount', false);
        $currency = strtoupper(trim((string) ($data['currency'] ?? '')));
        if (!preg_match('/\A[A-Z]{3}\z/', $currency)) {
            throw new RepositoryException('Receipt currency must be a three-letter code');
        }
        $outcome = (string) ($data['outcome'] ?? '');
        if (!in_array($outcome, ['accepted', 'review'], true)) {
            throw new RepositoryException('Invalid booking quote payment receipt outcome');
        }
        $reason = isset($data['reason']) && $data['reason'] !== '' ? trim((string) $data['reason']) : null;
        if ($outcome === 'review' && $reason === null) {
            throw new RepositoryException('A review receipt requires a reason');
        }
        if ($reason !== null && strlen($reason) > 100) {
            throw new RepositoryException('Receipt reason is too long');
        }
        $paidAt = $this->validDateTime((string) ($data['paid_at'] ?? ''), 'paid_at');

        $existing = $this->findTransactionUsage($transactionId);
        if ($existing !== null) {
            if ($this->sameReceipt($existing, $intentId, $invoice, $receivedAmount, $orderAmount, $currency)) {
                $receipt = $this->findReceiptByTransaction($transactionId);
                if ($receipt !== null) {
                    return $receipt;
                }
            }
            throw new RepositoryException('Transaction ID is already used by another payment');
        }

        global $wpdb;
        $result = $wpdb->insert(
            $this->receiptTable(),
            [
                'quote_id'        => $quoteId,
                'intent_id'       => $intentId,
                'invoice'         => $invoice,
                'transaction_id'  => $transactionId,
                'received_amount' => $receivedAmount,
                'order_amount'    => $orderAmount,
                'currency'        => $currency,
                'outcome'         => $outcome,
                'reason'          => $reason,
                'paid_at'         => $paidAt,
                'created_at'      => $this->now(),
                'webhook_event_id' => isset($data['webhook_event_id']) ? (int) $data['webhook_event_id'] : null,
            ],
            ['%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d'],
        );
        if ($result !== 1 || (int) $wpdb->insert_id <= 0) {
            // Convert a concurrent UNIQUE race into deterministic idempotency
            // only when the stored receipt is byte-for-byte the same event.
            $raced = $this->findReceiptByTransaction($transactionId);
            if ($raced !== null && $this->sameReceipt($raced, $intentId, $invoice, $receivedAmount, $orderAmount, $currency)) {
                return $raced;
            }
            throw new RepositoryException('Could not store booking quote payment receipt: ' . $wpdb->last_error);
        }

        $created = $this->findReceiptByTransaction($transactionId);
        if ($created === null) {
            throw new RepositoryException('Created booking quote payment receipt could not be reloaded');
        }

        return $created;
    }

    public function markPaidChecked(int $id, string $transactionId, int $receivedAmount, string $paidAt): array
    {
        return $this->transitionChecked($id, 'paid', $transactionId, $receivedAmount, $paidAt);
    }

    public function markReviewChecked(
        int $id,
        string $transactionId,
        int $receivedAmount,
        string $paidAt,
        string $reason,
    ): array {
        if (trim($reason) === '') {
            throw new RepositoryException('A review transition requires a reason');
        }

        return $this->transitionChecked($id, 'review', $transactionId, $receivedAmount, $paidAt);
    }

    /**
     * Authoritative paid total: only authenticated, exact receipts accepted by
     * reconciliation are included. Review receipts never affect paid_amount.
     */
    public function sumPaid(int $quoteId): int
    {
        $this->assertPositiveId($quoteId, 'quote_id');

        global $wpdb;
        $sum = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(received_amount), 0)
                   FROM {$this->receiptTable()}
                  WHERE quote_id = %d AND outcome = 'accepted'",
                $quoteId,
            ),
        );
        $this->assertReadSucceeded('sum accepted booking quote payments');

        return max(0, (int) $sum);
    }

    /**
     * Admin-facing intent history. Receipt audit entries are nested so extra
     * and mismatched receipts remain inspectable without fabricating intents.
     *
     * @return array<int,array<string,mixed>>
     */
    public function history(int $quoteId): array
    {
        $this->assertPositiveId($quoteId, 'quote_id');

        global $wpdb;
        $intents = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->intentTable()} WHERE quote_id = %d ORDER BY created_at DESC, id DESC",
                $quoteId,
            ),
            ARRAY_A,
        );
        $this->assertReadSucceeded('load booking quote payment history');
        $receipts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->receiptTable()} WHERE quote_id = %d ORDER BY created_at DESC, id DESC",
                $quoteId,
            ),
            ARRAY_A,
        );
        $this->assertReadSucceeded('load booking quote receipt history');

        $byIntent = [];
        foreach (is_array($receipts) ? $receipts : [] as $receipt) {
            if (!is_array($receipt)) {
                continue;
            }
            $cast = $this->castReceipt($receipt);
            $byIntent[(int) $cast['intent_id']][] = $cast;
        }

        $history = [];
        foreach (is_array($intents) ? $intents : [] as $intent) {
            if (!is_array($intent)) {
                continue;
            }
            $cast = $this->castIntent($intent);
            $cast['receipts'] = $byIntent[(int) $cast['id']] ?? [];
            $history[] = $cast;
        }

        return $history;
    }

    private function transitionChecked(
        int $id,
        string $status,
        string $transactionId,
        int $receivedAmount,
        string $paidAt,
    ): array {
        $this->assertPositiveId($id, 'intent_id');
        $transactionId = $this->validTransactionId($transactionId);
        $receivedAmount = $this->validAmount($receivedAmount, 'received_amount', false);
        $paidAt = $this->validDateTime($paidAt, 'paid_at');

        $usage = $this->findTransactionUsage($transactionId);
        if ($usage !== null && (string) ($usage['source'] ?? '') === 'order') {
            throw new RepositoryException('Transaction ID is already used by an order payment');
        }
        if ($usage !== null && isset($usage['intent_id']) && (int) $usage['intent_id'] !== $id) {
            throw new RepositoryException('Transaction ID is already used by another quote payment');
        }

        global $wpdb;
        if ($status === 'paid') {
            // An accepted intent is exact-value only. Mismatches must take the
            // review transition and remain excluded from the paid sum.
            $sql = $wpdb->prepare(
                "UPDATE {$this->intentTable()}
                    SET status = %s, transaction_id = %s, received_amount = %d,
                        paid_at = %s, updated_at = %s
                  WHERE id = %d AND status = 'pending' AND transaction_id IS NULL
                    AND expected_amount = %d",
                $status,
                $transactionId,
                $receivedAmount,
                $paidAt,
                $this->now(),
                $id,
                $receivedAmount,
            );
        } else {
            $sql = $wpdb->prepare(
                "UPDATE {$this->intentTable()}
                    SET status = %s, transaction_id = %s, received_amount = %d,
                        paid_at = %s, updated_at = %s
                  WHERE id = %d AND status = 'pending' AND transaction_id IS NULL",
                $status,
                $transactionId,
                $receivedAmount,
                $paidAt,
                $this->now(),
                $id,
            );
        }
        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RepositoryException('Could not transition booking quote payment intent: ' . $wpdb->last_error);
        }

        $intent = $this->find($id);
        if ($intent === null) {
            throw new RepositoryException('Booking quote payment intent does not exist');
        }
        if ($result !== 1) {
            $isSameTransition = (string) $intent['status'] === $status
                && (string) ($intent['transaction_id'] ?? '') === $transactionId
                && (int) $intent['received_amount'] === $receivedAmount
                && (string) ($intent['paid_at'] ?? '') === $paidAt;
            if (!$isSameTransition) {
                throw new RepositoryException('Booking quote payment intent is no longer pending');
            }
        }

        return $intent;
    }

    private function sameReceipt(
        array $usage,
        int $intentId,
        string $invoice,
        int $receivedAmount,
        int $orderAmount,
        string $currency,
    ): bool {
        return (string) ($usage['source'] ?? 'quote') === 'quote'
            && (int) ($usage['intent_id'] ?? 0) === $intentId
            && (string) ($usage['invoice'] ?? '') === $invoice
            && (int) ($usage['received_amount'] ?? -1) === $receivedAmount
            && (int) ($usage['order_amount'] ?? -1) === $orderAmount
            && strtoupper((string) ($usage['currency'] ?? '')) === $currency;
    }

    private function castIntent(array $row): array
    {
        foreach (['id', 'quote_id', 'expected_amount', 'received_amount'] as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== null) {
                $row[$field] = (int) $row[$field];
            }
        }

        return $row;
    }

    private function castReceipt(array $row): array
    {
        foreach (['id', 'quote_id', 'intent_id', 'received_amount', 'order_amount', 'webhook_event_id'] as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== null) {
                $row[$field] = (int) $row[$field];
            }
        }

        return $row;
    }

    private function validInvoice(string $invoice): string
    {
        $invoice = trim($invoice);
        if (!preg_match('/\AVQ[A-F0-9]{30}\z/', $invoice)) {
            throw new RepositoryException('Invalid booking quote payment invoice');
        }

        return $invoice;
    }

    private function validPurpose(string $purpose): string
    {
        if (!in_array($purpose, ['deposit', 'balance'], true)) {
            throw new RepositoryException('Invalid booking quote payment purpose');
        }

        return $purpose;
    }

    private function validTransactionId(string $transactionId): string
    {
        $transactionId = trim($transactionId);
        if ($transactionId === '' || strlen($transactionId) > 100) {
            throw new RepositoryException('Invalid payment transaction ID');
        }

        return $transactionId;
    }

    private function validAmount(int $amount, string $field, bool $allowZero): int
    {
        if ($amount < ($allowZero ? 0 : 1) || $amount > self::MAX_AMOUNT) {
            throw new RepositoryException("Invalid {$field}");
        }

        return $amount;
    }

    private function validDateTime(string $value, string $field): string
    {
        $value = trim($value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new RepositoryException("Invalid {$field}");
        }

        return $value;
    }

    private function assertPositiveId(int $id, string $field): void
    {
        if ($id <= 0) {
            throw new RepositoryException("Invalid {$field}");
        }
    }

    private function assertReadSucceeded(string $operation): void
    {
        global $wpdb;
        if ((string) $wpdb->last_error !== '') {
            throw new RepositoryException("Could not {$operation}: {$wpdb->last_error}");
        }
    }

    private function intentTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'vie_booking_quote_payment';
    }

    private function receiptTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'vie_booking_quote_payment_receipt';
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s');
    }
}
