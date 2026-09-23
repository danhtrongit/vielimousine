<?php
declare(strict_types=1);

/**
 * Deterministic, offline regression tests for booking quotation payments.
 *
 * The production payment service is loaded unchanged. The
 * repositories and the tiny WordPress/transaction surface are in-memory test
 * doubles so this suite never needs WordPress, MySQL, or a gateway connection.
 */

namespace {
    const ARRAY_A = 'ARRAY_A';

    /** @var array<string,mixed> */
    $GLOBALS['booking_quote_payment_options'] = [];

    function get_option(string $key, mixed $default = false): mixed
    {
        return $GLOBALS['booking_quote_payment_options'][$key] ?? $default;
    }

    function current_time(string $type): string
    {
        return $type === 'mysql' ? '2026-09-22 10:00:00' : '2026-09-22T10:00:00+07:00';
    }

    function wp_timezone(): \DateTimeZone
    {
        return new \DateTimeZone('Asia/Ho_Chi_Minh');
    }

    function home_url(string $path = ''): string
    {
        return 'https://vielimousine.test' . $path;
    }

    function add_query_arg(array $args, string $url): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($args);
    }

    final class BookingQuotePaymentTestWpdb
    {
        /** @var list<object> */
        private array $participants = [];
        public int $starts = 0;
        public int $commits = 0;
        public int $rollbacks = 0;
        public bool $failCommit = false;

        public function register(object $participant): void
        {
            $this->participants[] = $participant;
        }

        public function query(string $sql): int|false
        {
            $command = strtoupper(trim($sql));
            if ($command === 'START TRANSACTION') {
                $this->starts++;
                foreach ($this->participants as $participant) {
                    $participant->beginTestTransaction();
                }
                return 0;
            }
            if ($command === 'COMMIT') {
                if ($this->failCommit) {
                    $this->failCommit = false;
                    return false;
                }
                $this->commits++;
                foreach ($this->participants as $participant) {
                    $participant->commitTestTransaction();
                }
                return 0;
            }
            if ($command === 'ROLLBACK') {
                $this->rollbacks++;
                foreach ($this->participants as $participant) {
                    $participant->rollbackTestTransaction();
                }
                return 0;
            }
            throw new \RuntimeException('Unexpected SQL in offline payment test: ' . $sql);
        }
    }

    final class WP_REST_Request
    {
        public function __construct(private array $payload, private array $headers = []) {}
        public function get_json_params(): array { return $this->payload; }
        public function get_header(string $name): string { return (string) ($this->headers[strtolower($name)] ?? ''); }
    }

    final class WP_REST_Response
    {
        public function __construct(private array $data, private int $status) {}
        public function get_data(): array { return $this->data; }
        public function get_status(): int { return $this->status; }
    }
}

namespace Vie\Repository {
    /** In-memory quote store matching the production service's repository API. */
    final class BookingQuoteRepository
    {
        /** @var array<int,array<string,mixed>> */
        public array $quotes = [];
        public bool $failNextFinancialUpdate = false;
        /** @var null|array<int,array<string,mixed>> */
        private ?array $snapshot = null;

        public function __construct(array $quote)
        {
            $this->quotes[(int) $quote['id']] = $quote;
        }

        public function lockForUpdateByPublicId(string $publicId): ?array
        {
            foreach ($this->quotes as $quote) {
                if (($quote['public_id'] ?? null) === $publicId) {
                    return $quote;
                }
            }
            return null;
        }

        public function lockForUpdate(int $id): ?array
        {
            return $this->quotes[$id] ?? null;
        }

        public function updateFinancialStateChecked(int $id, array $patch): array
        {
            if ($this->failNextFinancialUpdate) {
                $this->failNextFinancialUpdate = false;
                throw new \RuntimeException('Simulated quote update failure');
            }
            if (!isset($this->quotes[$id])) {
                throw new \RuntimeException('Quote not found');
            }
            $this->quotes[$id] = array_merge($this->quotes[$id], $patch);
            return $this->quotes[$id];
        }

        public function beginTestTransaction(): void
        {
            $this->snapshot = $this->quotes;
        }

        public function commitTestTransaction(): void
        {
            $this->snapshot = null;
        }

        public function rollbackTestTransaction(): void
        {
            if ($this->snapshot !== null) {
                $this->quotes = $this->snapshot;
                $this->snapshot = null;
            }
        }
    }

    /** In-memory intent/receipt ledger matching the production service API. */
    final class BookingQuotePaymentRepository
    {
        /** @var array<int,array<string,mixed>> */
        public array $intents = [];
        /** @var array<int,array<string,mixed>> */
        public array $receipts = [];
        /** @var array<string,array<string,mixed>> */
        public array $externalTransactionUsages = [];
        /** @var null|array{intents:array,receipts:array,next_intent:int,next_receipt:int} */
        private ?array $snapshot = null;
        private int $nextIntentId = 1;
        private int $nextReceiptId = 1;

        public function findPending(int $quoteId, string $purpose, int $amount): ?array
        {
            foreach ($this->intents as $intent) {
                if ((int) $intent['quote_id'] === $quoteId
                    && $intent['purpose'] === $purpose
                    && (int) $intent['expected_amount'] === $amount
                    && $intent['status'] === 'pending') {
                    return $intent;
                }
            }
            return null;
        }

        public function createIntentChecked(array $data): array
        {
            foreach ($this->intents as $intent) {
                if ($intent['invoice'] === $data['invoice']) {
                    throw new \RuntimeException('Duplicate invoice');
                }
            }
            $id = $this->nextIntentId++;
            $this->intents[$id] = array_merge([
                'id' => $id,
                'transaction_id' => null,
                'received_amount' => 0,
                'paid_at' => null,
                'review_reason' => null,
            ], $data);
            return $this->intents[$id];
        }

        public function findByInvoice(string $invoice): ?array
        {
            foreach ($this->intents as $intent) {
                if ($intent['invoice'] === $invoice) {
                    return $intent;
                }
            }
            return null;
        }

        public function lockByInvoice(string $invoice): ?array
        {
            return $this->findByInvoice($invoice);
        }

        public function findReceiptByTransaction(string $transactionId): ?array
        {
            foreach ($this->receipts as $receipt) {
                if ($receipt['transaction_id'] === $transactionId) {
                    return $receipt;
                }
            }
            return null;
        }

        public function findTransactionUsage(string $transactionId): ?array
        {
            if (isset($this->externalTransactionUsages[$transactionId])) {
                return $this->externalTransactionUsages[$transactionId];
            }
            $receipt = $this->findReceiptByTransaction($transactionId);
            if ($receipt !== null) {
                return ['source' => 'quote'] + $receipt;
            }
            foreach ($this->intents as $intent) {
                if (($intent['transaction_id'] ?? null) === $transactionId) {
                    return ['source' => 'quote'] + $intent + [
                        'order_amount' => (int) $intent['expected_amount'],
                    ];
                }
            }
            return null;
        }

        public function createReceiptChecked(array $data): array
        {
            if ($this->findReceiptByTransaction((string) $data['transaction_id']) !== null) {
                throw new \RuntimeException('Duplicate transaction');
            }
            $id = $this->nextReceiptId++;
            $this->receipts[$id] = array_merge(['id' => $id], $data);
            return $this->receipts[$id];
        }

        public function markPaidChecked(int $id, string $transactionId, int $receivedAmount, string $paidAt): void
        {
            if (!isset($this->intents[$id]) || $this->intents[$id]['status'] !== 'pending') {
                throw new \RuntimeException('Intent state changed');
            }
            $this->intents[$id] = array_merge($this->intents[$id], [
                'status' => 'paid',
                'transaction_id' => $transactionId,
                'received_amount' => $receivedAmount,
                'paid_at' => $paidAt,
            ]);
        }

        public function markReviewChecked(
            int $id,
            string $transactionId,
            int $receivedAmount,
            string $paidAt,
            string $reason,
        ): void {
            if (!isset($this->intents[$id]) || $this->intents[$id]['status'] !== 'pending') {
                throw new \RuntimeException('Intent state changed');
            }
            $this->intents[$id] = array_merge($this->intents[$id], [
                'status' => 'review',
                'transaction_id' => $transactionId,
                'received_amount' => $receivedAmount,
                'paid_at' => $paidAt,
                'review_reason' => $reason,
            ]);
        }

        public function sumPaid(int $quoteId): int
        {
            $total = 0;
            foreach ($this->receipts as $receipt) {
                if ((int) $receipt['quote_id'] === $quoteId && $receipt['outcome'] === 'accepted') {
                    $total += (int) $receipt['received_amount'];
                }
            }
            return $total;
        }

        public function history(int $quoteId): array
        {
            return array_values(array_filter(
                $this->intents,
                static fn(array $intent): bool => (int) $intent['quote_id'] === $quoteId,
            ));
        }

        public function beginTestTransaction(): void
        {
            $this->snapshot = [
                'intents' => $this->intents,
                'receipts' => $this->receipts,
                'next_intent' => $this->nextIntentId,
                'next_receipt' => $this->nextReceiptId,
            ];
        }

        public function commitTestTransaction(): void
        {
            $this->snapshot = null;
        }

        public function rollbackTestTransaction(): void
        {
            if ($this->snapshot !== null) {
                $this->intents = $this->snapshot['intents'];
                $this->receipts = $this->snapshot['receipts'];
                $this->nextIntentId = $this->snapshot['next_intent'];
                $this->nextReceiptId = $this->snapshot['next_receipt'];
                $this->snapshot = null;
            }
        }
    }
}

namespace {
    require __DIR__ . '/../../src/Service/Settings/InvoiceSettings.php';
    require __DIR__ . '/../../src/Service/Payment/BankTransferInstructions.php';
    require __DIR__ . '/../../src/Service/BookingQuote/BookingQuoteException.php';
    require __DIR__ . '/../../src/Service/BookingQuote/BookingQuotePolicy.php';
    require __DIR__ . '/../../src/Service/BookingQuote/BookingQuotePaymentService.php';

    use Vie\Repository\BookingQuotePaymentRepository;
    use Vie\Repository\BookingQuoteRepository;
    use Vie\Service\BookingQuote\BookingQuoteException;
    use Vie\Service\BookingQuote\BookingQuotePaymentService;
    use Vie\Service\BookingQuote\BookingQuotePolicy;
    use Vie\Service\Payment\BankTransferInstructions;
    use Vie\Service\Settings\InvoiceSettings;

    $pass = 0;
    $fail = 0;
    $assert = static function (string $name, bool $condition, string $detail = '') use (&$pass, &$fail): void {
        if ($condition) { echo "  ✓ {$name}\n"; $pass++; return; }
        echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; $fail++;
    };

    /** @return array{0:BookingQuotePaymentService,1:BookingQuoteRepository,2:BookingQuotePaymentRepository,3:BookingQuotePaymentTestWpdb} */
    $fixture = static function (array $override = []): array {
        $GLOBALS['booking_quote_payment_options'] = [
            'vie_invoice_settings' => json_encode([
                'bank_name' => 'Test Bank', 'bank_code' => 'VCB',
                'bank_account' => '012345678901', 'bank_holder' => 'VIE TEST',
            ]),
        ];
        $quote = array_merge([
            'id' => 7, 'public_id' => '0123456789abcdef0123456789abcdef',
            'code' => 'VQ-2026-0001', 'status' => 'published',
            'title' => 'Ha Long package', 'total' => 1_000_000,
            'deposit_amount' => 300_000, 'paid_amount' => 0,
            'payment_review' => false, 'valid_until' => '2099-12-31 23:59:59',
        ], $override);
        $quoteRepo = new BookingQuoteRepository($quote);
        $paymentRepo = new BookingQuotePaymentRepository();
        $wpdb = new BookingQuotePaymentTestWpdb();
        $wpdb->register($quoteRepo); $wpdb->register($paymentRepo); $GLOBALS['wpdb'] = $wpdb;
        $service = new BookingQuotePaymentService(
            new BankTransferInstructions(new InvoiceSettings()),
            new InvoiceSettings(),
            $quoteRepo,
            $paymentRepo,
            new BookingQuotePolicy(),
        );
        return [$service, $quoteRepo, $paymentRepo, $wpdb];
    };

    $payload = static function (string $invoice, string $transactionId, int|string $amount = 300_000, string $account = '012345678901', string $type = 'in'): array {
        return [
            'id' => $transactionId,
            'code' => $invoice,
            'transferType' => $type,
            'transferAmount' => $amount,
            'accountNumber' => $account,
        ];
    };

    // Checkout is now a bank-transfer instruction, with server-derived amount and memo.
    [$service, $quoteRepo, $paymentRepo, $wpdb] = $fixture();
    $checkout = $service->checkout('0123456789abcdef0123456789abcdef');
    $assert('checkout computes the deposit server-side', $checkout['amount'] === 300_000 && $checkout['purpose'] === 'deposit');
    $assert('checkout returns configured transfer instructions', $checkout['transfer']['bank_account'] === '012345678901' && $checkout['transfer']['currency'] === 'VND' && $checkout['transfer']['amount'] === 300_000);
    $invoice = (string) $checkout['transfer']['memo'];
    $assert('checkout memo is the isolated VQ invoice', (bool) preg_match('/^VQ[A-F0-9]{30}$/', $invoice));
    $again = $service->checkout('0123456789abcdef0123456789abcdef');
    $assert('repeated checkout reuses pending intent', count($paymentRepo->intents) === 1 && $again['transfer']['memo'] === $invoice);

    [$balanceService] = $fixture(['paid_amount' => 300_000]);
    $balance = $balanceService->checkout('0123456789abcdef0123456789abcdef');
    $assert('checkout computes outstanding balance after deposit', $balance['amount'] === 700_000 && $balance['purpose'] === 'balance');

    // Direct flat webhook processing: authentication belongs to the controller/HMAC layer.
    [$service, $quoteRepo, $paymentRepo] = $fixture();
    $invoice = (string) $service->checkout('0123456789abcdef0123456789abcdef')['transfer']['memo'];
    $bad = $service->processWebhook($payload($invoice, 'TX-BAD', '300000.5'), '127.0.0.1', 41);
    $assert('non-integer VND amount is rejected', $bad === ['accepted' => false, 'reason' => 'invalid_amount'] && $paymentRepo->receipts === []);
    $wrongAccount = $service->processWebhook($payload($invoice, 'TX-ACCOUNT', 300_000, '999999999999'), '127.0.0.1', 42);
    $assert('wrong receiving account is ignored without mutation', $wrongAccount === ['accepted' => true, 'reason' => 'account_mismatch'] && $paymentRepo->receipts === []);
    $ignored = $service->processWebhook($payload($invoice, 'TX-OUT', 300_000, '012345678901', 'out'), null, 43);
    $assert('outgoing transfer is ignored', $ignored === ['accepted' => true, 'reason' => 'ignored_non_incoming']);

    // Exact amount, receipt event link, and idempotent replay.
    $paid = $service->processWebhook($payload($invoice, 'TX-PAID-1'), '127.0.0.1', 44);
    $assert('exact flat payment is accepted', $paid === ['accepted' => true, 'reason' => null], json_encode($paid));
    $assert('paid amount is recomputed from accepted receipts', $quoteRepo->quotes[7]['paid_amount'] === 300_000 && ($paymentRepo->receipts[1]['webhook_event_id'] ?? null) === 44);
    $duplicate = $service->processWebhook($payload($invoice, 'TX-PAID-1'), null, 45);
    $assert('duplicate transaction is acknowledged without double credit', $duplicate === ['accepted' => true, 'reason' => 'duplicate_ignored'] && count($paymentRepo->receipts) === 1 && $quoteRepo->quotes[7]['paid_amount'] === 300_000);
    $changedReplay = $service->processWebhook($payload($invoice, 'TX-PAID-1', 299_999), null, 46);
    $assert('same transaction with changed amount is rejected as reuse', $changedReplay === ['accepted' => false, 'reason' => 'transaction_reused']);

    // Deposit then balance must derive the second intent from locked quote state.
    [$service, $quoteRepo, $paymentRepo] = $fixture();
    $depositInvoice = (string) $service->checkout('0123456789abcdef0123456789abcdef')['transfer']['memo'];
    $depositPaid = $service->processWebhook($payload($depositInvoice, 'TX-DEPOSIT'), null, 50);
    $balanceCheckout = $service->checkout('0123456789abcdef0123456789abcdef');
    $balanceInvoice = (string) $balanceCheckout['transfer']['memo'];
    $balancePaid = $service->processWebhook($payload($balanceInvoice, 'TX-BALANCE', 700_000), null, 51);
    $assert('deposit then balance reaches exact total', $depositPaid['accepted'] && $balanceCheckout['purpose'] === 'balance' && $balancePaid['accepted'] && $quoteRepo->quotes[7]['paid_amount'] === 1_000_000 && count($paymentRepo->receipts) === 2);

    // Commercially unsafe but authenticated receipts are retained for review.
    foreach ([
        ['revoked', ['status' => 'revoked'], 'quote_revoked'],
        ['expired', ['valid_until' => '2020-01-01 00:00:00'], 'quote_expired'],
        ['amount mismatch', [], 'amount_mismatch'],
    ] as [$label, $override, $reason]) {
        [$svc, $repo, $payments] = $fixture();
        $inv = (string) $svc->checkout('0123456789abcdef0123456789abcdef')['transfer']['memo'];
        foreach ($override as $key => $value) { $repo->quotes[7][$key] = $value; }
        $amt = $label === 'amount mismatch' ? 299_999 : 300_000;
        $result = $svc->processWebhook($payload($inv, 'TX-' . strtoupper(str_replace(' ', '-', $label)), $amt), null, 60);
        $assert($label . ' receipt is retained for review', $result === ['accepted' => true, 'reason' => 'review_required'] && ($payments->receipts[1]['reason'] ?? null) === $reason && $repo->quotes[7]['paid_amount'] === 0 && $repo->quotes[7]['payment_review'] === true, json_encode($result));
    }

    [$service, $quoteRepo, $paymentRepo] = $fixture();
    $invoice = (string) $service->checkout('0123456789abcdef0123456789abcdef')['transfer']['memo'];
    $extra = $service->processWebhook($payload($invoice, 'TX-EXTRA'), null, 70);
    $service->processWebhook($payload($invoice, 'TX-EXTRA-2'), null, 71);
    $assert('review receipt does not credit quote', $extra === ['accepted' => true, 'reason' => null] && $quoteRepo->quotes[7]['paid_amount'] === 300_000);

    // A storage failure after receipt creation must roll back receipt, intent and quote state.
    [$service, $quoteRepo, $paymentRepo, $wpdb] = $fixture();
    $invoice = (string) $service->checkout('0123456789abcdef0123456789abcdef')['transfer']['memo'];
    $quoteRepo->failNextFinancialUpdate = true;
    $storageError = $service->processWebhook($payload($invoice, 'TX-ROLLBACK'), null, 80);
    $intent = array_values($paymentRepo->intents)[0];
    $assert('failed financial transaction is retryable and atomic', $storageError === ['accepted' => false, 'reason' => 'storage_error', 'retryable' => true] && $wpdb->rollbacks === 1 && $paymentRepo->receipts === [] && $intent['status'] === 'pending' && $quoteRepo->quotes[7]['paid_amount'] === 0);

    echo "\n--- Booking quote payments: {$pass} passed, {$fail} failed ---\n";
    exit($fail === 0 ? 0 : 1);
}
