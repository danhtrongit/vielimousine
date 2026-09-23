<?php
declare(strict_types=1);

/**
 * Offline storage integration test for booking-quote payments.
 *
 * Unlike booking-quote-payment-test.php, this suite uses the production quote
 * repository, payment repository, and payment service together. A
 * small wpdb-compatible adapter runs their SQL against in-memory SQLite; no
 * WordPress, MySQL, network, gateway, or persistent database is touched.
 */

namespace {
    const ARRAY_A = 'ARRAY_A';

    if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
        echo "--- Booking quote payment storage: SKIP (pdo_sqlite unavailable) ---\n";
        exit(0);
    }

    /** @var array<string,mixed> */
    $GLOBALS['booking_quote_storage_options'] = [];

    function get_option(string $key, mixed $default = false): mixed
    {
        return $GLOBALS['booking_quote_storage_options'][$key] ?? $default;
    }

    function current_time(string $type): string
    {
        return $type === 'mysql' ? '2026-09-22 12:00:00' : '2026-09-22T12:00:00+07:00';
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

    function remove_accents(string $value): string
    {
        return $value;
    }

    function wp_json_encode(mixed $value, int $flags = 0): string|false
    {
        return json_encode($value, $flags | JSON_UNESCAPED_UNICODE);
    }

    /** Captures the production dbDelta DDL without executing MySQL syntax. */
    class wpdb
    {
        public string $prefix = 'wp_';
        public function get_charset_collate(): string { return 'DEFAULT CHARACTER SET utf8mb4'; }
    }

    function dbDelta(string $sql): void
    {
        $GLOBALS['booking_quote_storage_schema_sql'][] = $sql;
    }

    /** Minimal wpdb surface used by the production repositories in this test. */
    final class BookingQuoteStorageWpdb
    {
        public string $prefix = 'wp_';
        public string $last_error = '';
        public int $insert_id = 0;
        public int $rows_affected = 0;
        public ?string $failNextInsertTable = null;
        public bool $failNextQuoteFinancialUpdate = false;

        public function __construct(public readonly \PDO $pdo)
        {
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        }

        public function prepare(string $sql, mixed ...$values): string
        {
            if (count($values) === 1 && is_array($values[0])) {
                $values = $values[0];
            }
            $index = 0;
            return (string) preg_replace_callback(
                '/%(?:\d+\$)?([dfs])/',
                function (array $match) use (&$index, $values): string {
                    if (!array_key_exists($index, $values)) {
                        throw new \RuntimeException('Missing SQL prepare value');
                    }
                    $value = $values[$index++];
                    return match ($match[1]) {
                        'd' => (string) (int) $value,
                        'f' => (string) (float) $value,
                        default => $value === null ? 'NULL' : $this->pdo->quote((string) $value),
                    };
                },
                $sql,
            );
        }

        public function insert(string $table, array $data, array|string|null $format = null): int|false
        {
            unset($format);
            $this->resetResultState();
            if ($this->failNextInsertTable === $table) {
                $this->failNextInsertTable = null;
                $this->last_error = 'simulated insert failure';
                return false;
            }
            try {
                $columns = array_keys($data);
                $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
                $statement = $this->pdo->prepare(
                    'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
                );
                foreach ($data as $column => $value) {
                    $type = $value === null ? \PDO::PARAM_NULL : (is_int($value) || is_bool($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
                    $statement->bindValue(':' . $column, $value, $type);
                }
                $statement->execute();
                $this->rows_affected = $statement->rowCount();
                $this->insert_id = (int) $this->pdo->lastInsertId();
                return $this->rows_affected;
            } catch (\Throwable $e) {
                $this->last_error = $e->getMessage();
                return false;
            }
        }

        public function update(string $table, array $data, array $where, array|string|null $format = null, array|string|null $whereFormat = null): int|false
        {
            unset($format, $whereFormat);
            $sets = [];
            $predicates = [];
            $values = [];
            foreach ($data as $column => $value) {
                $sets[] = $column . ' = %s';
                $values[] = $value;
            }
            foreach ($where as $column => $value) {
                $predicates[] = $column . ' = %s';
                $values[] = $value;
            }
            return $this->query($this->prepare(
                'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . implode(' AND ', $predicates),
                ...$values,
            ));
        }

        public function query(string $sql): int|false
        {
            $this->resetResultState();
            $normalized = strtoupper(trim($sql));
            try {
                if ($normalized === 'START TRANSACTION') {
                    // SQLite has no row-level SELECT ... FOR UPDATE. BEGIN
                    // IMMEDIATE serializes writers before the locking reads,
                    // which is the closest safe analogue for this harness.
                    return $this->pdo->exec('BEGIN IMMEDIATE');
                }
                if ($normalized === 'COMMIT') {
                    return $this->pdo->commit() ? 0 : false;
                }
                if ($normalized === 'ROLLBACK') {
                    if (!$this->pdo->inTransaction()) {
                        return 0;
                    }
                    return $this->pdo->rollBack() ? 0 : false;
                }
                if ($this->failNextQuoteFinancialUpdate
                    && preg_match('/^UPDATE\s+WP_VIE_BOOKING_QUOTE\s+SET/i', trim($sql))
                    && str_contains(strtolower($sql), 'paid_amount')
                ) {
                    $this->failNextQuoteFinancialUpdate = false;
                    $this->last_error = 'simulated quote financial update failure';
                    return false;
                }
                $sql = preg_replace('/\s+FOR\s+UPDATE\s*$/i', '', trim($sql)) ?? trim($sql);
                $this->rows_affected = $this->pdo->exec($sql);
                return $this->rows_affected;
            } catch (\Throwable $e) {
                $this->last_error = $e->getMessage();
                return false;
            }
        }

        public function get_row(string $sql, string $output = ARRAY_A): ?array
        {
            unset($output);
            $rows = $this->select($sql);
            return $rows[0] ?? null;
        }

        public function get_results(string $sql, string $output = ARRAY_A): array
        {
            unset($output);
            return $this->select($sql);
        }

        public function get_var(string $sql): mixed
        {
            $rows = $this->select($sql);
            if ($rows === []) {
                return null;
            }
            return array_values($rows[0])[0] ?? null;
        }

        public function esc_like(string $value): string
        {
            return addcslashes($value, '_%\\');
        }

        /** @return list<array<string,mixed>> */
        private function select(string $sql): array
        {
            $this->resetResultState();
            $sql = preg_replace('/\s+FOR\s+UPDATE\s*$/i', '', trim($sql)) ?? trim($sql);
            try {
                $statement = $this->pdo->query($sql);
                return $statement === false ? [] : $statement->fetchAll();
            } catch (\Throwable $e) {
                $this->last_error = $e->getMessage();
                return [];
            }
        }

        private function resetResultState(): void
        {
            $this->last_error = '';
            $this->rows_affected = 0;
        }
    }

    function bookingQuoteStorageCreateTables(BookingQuoteStorageWpdb $wpdb): void
    {
        $ddl = [
            "CREATE TABLE wp_vie_booking_quote (
                id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT NOT NULL UNIQUE, code TEXT NOT NULL UNIQUE,
                sales_user_id INTEGER NOT NULL, status TEXT NOT NULL, customer_name TEXT NOT NULL,
                customer_phone TEXT NOT NULL, customer_email TEXT, title TEXT NOT NULL, image_url TEXT,
                greeting TEXT, trip_start TEXT, trip_end TEXT, description TEXT, inclusions TEXT, exclusions TEXT,
                terms TEXT, contact_name TEXT, contact_phone TEXT, contact_zalo TEXT, lines TEXT NOT NULL,
                discount INTEGER NOT NULL, deposit_type TEXT NOT NULL, deposit_value INTEGER NOT NULL,
                subtotal INTEGER NOT NULL, total INTEGER NOT NULL, deposit_amount INTEGER NOT NULL,
                paid_amount INTEGER NOT NULL DEFAULT 0, payment_review INTEGER NOT NULL DEFAULT 0,
                valid_until TEXT, brand TEXT, published_at TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL
            )",
            "CREATE TABLE wp_vie_booking_quote_payment (
                id INTEGER PRIMARY KEY AUTOINCREMENT, quote_id INTEGER NOT NULL, invoice TEXT NOT NULL UNIQUE,
                purpose TEXT NOT NULL, expected_amount INTEGER NOT NULL, currency TEXT NOT NULL,
                status TEXT NOT NULL, transaction_id TEXT UNIQUE, received_amount INTEGER NOT NULL DEFAULT 0,
                paid_at TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL
            )",
            "CREATE TABLE wp_vie_booking_quote_payment_receipt (
                id INTEGER PRIMARY KEY AUTOINCREMENT, quote_id INTEGER NOT NULL, intent_id INTEGER NOT NULL,
                invoice TEXT NOT NULL, transaction_id TEXT NOT NULL UNIQUE, received_amount INTEGER NOT NULL,
                order_amount INTEGER NOT NULL, currency TEXT NOT NULL, outcome TEXT NOT NULL, reason TEXT,
                paid_at TEXT NOT NULL, webhook_event_id INTEGER DEFAULT NULL, created_at TEXT NOT NULL
            )",
            "CREATE TABLE wp_vie_payment_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, gateway TEXT,
                transaction_id TEXT, amount INTEGER NOT NULL, paid_at TEXT, created_at TEXT
            )",
            "CREATE UNIQUE INDEX uniq_order_gateway_tx ON wp_vie_payment_log (gateway, transaction_id)",
        ];
        foreach ($ddl as $sql) {
            if ($wpdb->query($sql) === false) {
                throw new \RuntimeException('SQLite fixture DDL failed: ' . $wpdb->last_error);
            }
        }
    }
}

namespace {
    require __DIR__ . '/../../src/Support/QueryBuilder.php';
    require __DIR__ . '/../../src/Repository/RepositoryException.php';
    require __DIR__ . '/../../src/Repository/AbstractRepository.php';
    require __DIR__ . '/../../src/Service/BookingQuote/BookingQuoteException.php';
    require __DIR__ . '/../../src/Validation/Schemas/BookingQuoteValidation.php';
    require __DIR__ . '/../../src/Repository/BookingQuoteRepository.php';
    require __DIR__ . '/../../src/Repository/BookingQuotePaymentRepository.php';
    require __DIR__ . '/../../src/Schema/BookingQuotePaymentSchema.php';
    require __DIR__ . '/../../src/Service/Settings/InvoiceSettings.php';
    require __DIR__ . '/../../src/Service/Payment/BankTransferInstructions.php';
    require __DIR__ . '/../../src/Service/BookingQuote/BookingQuotePolicy.php';
    require __DIR__ . '/../../src/Service/BookingQuote/BookingQuotePaymentService.php';

    use Vie\Repository\BookingQuotePaymentRepository;
    use Vie\Repository\BookingQuoteRepository;
    use Vie\Service\BookingQuote\BookingQuotePaymentService;
    use Vie\Service\BookingQuote\BookingQuotePolicy;
    use Vie\Service\Payment\BankTransferInstructions;
    use Vie\Service\Settings\InvoiceSettings;
    use Vie\Schema\BookingQuotePaymentSchema;

    $pass = 0;
    $fail = 0;
    $assert = static function (string $name, bool $condition, string $detail = '') use (&$pass, &$fail): void {
        if ($condition) {
            echo "  ✓ {$name}\n";
            $pass++;
            return;
        }
        echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
        $fail++;
    };

    /** @return array{BookingQuotePaymentService,BookingQuoteRepository,BookingQuotePaymentRepository,BookingQuoteStorageWpdb} */
    $fixture = static function (): array {
        $wpdb = new BookingQuoteStorageWpdb(new \PDO('sqlite::memory:'));
        bookingQuoteStorageCreateTables($wpdb);
        $GLOBALS['wpdb'] = $wpdb;
        $GLOBALS['booking_quote_storage_options'] = [
            'vie_invoice_settings' => json_encode([
                'bank_name' => 'Storage Bank', 'bank_code' => 'VCB',
                'bank_account' => '012345678901', 'bank_holder' => 'VIE STORAGE',
            ]),
        ];
        $wpdb->insert('wp_vie_booking_quote', [
            'public_id' => '0123456789abcdef0123456789abcdef',
            'code' => 'VQ-STORAGE-1',
            'sales_user_id' => 9,
            'status' => 'published',
            'customer_name' => 'Storage Guest',
            'customer_phone' => '0900000000',
            'customer_email' => null,
            'title' => 'Storage integration quote',
            'image_url' => null,
            'greeting' => null,
            'trip_start' => null,
            'trip_end' => null,
            'description' => null,
            'inclusions' => null,
            'exclusions' => null,
            'terms' => null,
            'contact_name' => null,
            'contact_phone' => null,
            'contact_zalo' => null,
            'lines' => '[{"label":"Trip","quantity":1,"unit":"package","unit_price":1000000,"line_total":1000000}]',
            'discount' => 0,
            'deposit_type' => 'fixed',
            'deposit_value' => 300000,
            'subtotal' => 1000000,
            'total' => 1000000,
            'deposit_amount' => 300000,
            'paid_amount' => 0,
            'payment_review' => 0,
            'valid_until' => '2099-12-31 23:59:59',
            'brand' => '{}',
            'published_at' => '2026-09-22 12:00:00',
            'created_at' => '2026-09-22 12:00:00',
            'updated_at' => '2026-09-22 12:00:00',
        ]);

        $quoteRepo = new BookingQuoteRepository();
        $paymentRepo = new BookingQuotePaymentRepository();
        return [
            new BookingQuotePaymentService(
                new BankTransferInstructions(new InvoiceSettings()),
                new InvoiceSettings(),
                $quoteRepo,
                $paymentRepo,
                new BookingQuotePolicy(),
            ),
            $quoteRepo,
            $paymentRepo,
            $wpdb,
        ];
    };

    $payload = static function (string $invoice, string $transactionId, int|string $amount, string $account = '012345678901', string $type = 'in'): array {
        return [
            'id' => $transactionId,
            'code' => $invoice,
            'transferType' => $type,
            'transferAmount' => $amount,
            'accountNumber' => $account,
        ];
    };

    [$service, $quoteRepo, $paymentRepo, $wpdb] = $fixture();

    // Capture the actual production schema and verify the required ledger
    // columns/indexes, then verify the SQLite mirror consumed by repositories.
    $GLOBALS['booking_quote_storage_schema_sql'] = [];
    BookingQuotePaymentSchema::install(new \wpdb());
    $schemaSql = implode("\n", $GLOBALS['booking_quote_storage_schema_sql']);
    $assert('production schema declares intent and receipt ledgers', str_contains($schemaSql, 'vie_booking_quote_payment') && str_contains($schemaSql, 'vie_booking_quote_payment_receipt'));
    $assert('production schema declares exact and review receipt fields', str_contains($schemaSql, 'expected_amount DECIMAL(12,0)') && str_contains($schemaSql, 'order_amount DECIMAL(12,0)') && str_contains($schemaSql, 'outcome VARCHAR(20)') && str_contains($schemaSql, 'webhook_event_id BIGINT UNSIGNED') && substr_count($schemaSql, 'UNIQUE KEY uniq_transaction_id') === 2);

    $columns = static fn(string $table): array => array_column($wpdb->get_results("PRAGMA table_info({$table})"), 'name');
    $assert('intent storage exposes all checked repository columns', array_diff([
        'id', 'quote_id', 'invoice', 'purpose', 'expected_amount', 'currency', 'status',
        'transaction_id', 'received_amount', 'paid_at', 'created_at', 'updated_at',
    ], $columns('wp_vie_booking_quote_payment')) === []);
    $assert('receipt storage exposes immutable reconciliation columns', array_diff([
        'id', 'quote_id', 'intent_id', 'invoice', 'transaction_id', 'received_amount',
        'order_amount', 'currency', 'outcome', 'reason', 'paid_at', 'created_at', 'webhook_event_id',
    ], $columns('wp_vie_booking_quote_payment_receipt')) === []);

    $depositCheckout = $service->checkout('0123456789abcdef0123456789abcdef');
    $depositInvoice = $depositCheckout['transfer']['memo'];
    $assert('actual repositories persist exact deposit intent', $depositCheckout['amount'] === 300000 && $paymentRepo->findByInvoice($depositInvoice)['status'] === 'pending');
    $pendingReplay = $service->checkout('0123456789abcdef0123456789abcdef');
    $assert('actual repositories reuse one pending deposit intent', $pendingReplay['transfer']['memo'] === $depositInvoice && (int) $wpdb->get_var('SELECT COUNT(*) FROM wp_vie_booking_quote_payment') === 1);

    $depositResult = $service->processWebhook($payload($depositInvoice, 'SQLITE-TX-DEPOSIT', 300000), null, 101);
    $quote = $quoteRepo->find(1);
    $depositHistory = $paymentRepo->history(1);
    $assert('actual receipt ledger accepts deposit and updates quote', $depositResult === ['accepted' => true, 'reason' => null] && $quote['paid_amount'] === 300000);
    $assert('actual history nests accepted deposit receipt', count($depositHistory) === 1 && $depositHistory[0]['status'] === 'paid' && ($depositHistory[0]['receipts'][0]['outcome'] ?? null) === 'accepted');
    $assert('receipt stores the webhook event link', (int) ($depositHistory[0]['receipts'][0]['webhook_event_id'] ?? 0) === 101);

    $balanceCheckout = $service->checkout('0123456789abcdef0123456789abcdef');
    $balanceInvoice = $balanceCheckout['transfer']['memo'];
    $balanceResult = $service->processWebhook($payload($balanceInvoice, 'SQLITE-TX-BALANCE', 700000), null, 101);
    $quote = $quoteRepo->find(1);
    $assert('actual repository flow collects server-derived balance', $balanceCheckout['purpose'] === 'balance' && $balanceCheckout['amount'] === 700000 && $balanceResult['accepted'] === true && $quote['paid_amount'] === 1000000);
    $assert('sumPaid and history derive from two accepted receipts', $paymentRepo->sumPaid(1) === 1000000 && count($paymentRepo->history(1)) === 2);

    $duplicate = $service->processWebhook($payload($balanceInvoice, 'SQLITE-TX-BALANCE', 700000), null, 101);
    $receiptCount = (int) $wpdb->get_var('SELECT COUNT(*) FROM wp_vie_booking_quote_payment_receipt');
    $assert('actual unique transaction makes exact replay idempotent', $duplicate === ['accepted' => true, 'reason' => 'duplicate_ignored'] && $receiptCount === 2);

    $extra = $service->processWebhook($payload($balanceInvoice, 'SQLITE-TX-EXTRA', 700000), null, 101);
    $quote = $quoteRepo->find(1);
    $history = $paymentRepo->history(1);
    $balanceEntry = array_values(array_filter($history, static fn(array $intent): bool => $intent['purpose'] === 'balance'))[0];
    $assert('extra authentic receipt is retained as review without extra credit', $extra === ['accepted' => true, 'reason' => 'review_required'] && $quote['paid_amount'] === 1000000 && $quote['payment_review'] === true && count($balanceEntry['receipts']) === 2);

    // Receipt insert failure must leave both intent and quote unchanged.
    [$service, $quoteRepo, $paymentRepo, $wpdb] = $fixture();
    $invoice = $service->checkout('0123456789abcdef0123456789abcdef')['transfer']['memo'];
    $wpdb->failNextInsertTable = 'wp_vie_booking_quote_payment_receipt';
    $receiptFailure = $service->processWebhook($payload($invoice, 'SQLITE-TX-RECEIPT-FAIL', 300000), null, 101);
    $intent = $paymentRepo->findByInvoice($invoice);
    $assert('failed real receipt insert rolls back atomically', $receiptFailure === ['accepted' => false, 'reason' => 'storage_error', 'retryable' => true] && $quoteRepo->find(1)['paid_amount'] === 0 && $intent['status'] === 'pending' && (int) $wpdb->get_var('SELECT COUNT(*) FROM wp_vie_booking_quote_payment_receipt') === 0);

    // Failure after receipt + intent writes must roll both back too.
    [$service, $quoteRepo, $paymentRepo, $wpdb] = $fixture();
    $invoice = $service->checkout('0123456789abcdef0123456789abcdef')['transfer']['memo'];
    $wpdb->failNextQuoteFinancialUpdate = true;
    $quoteFailure = $service->processWebhook($payload($invoice, 'SQLITE-TX-QUOTE-FAIL', 300000), null, 101);
    $intent = $paymentRepo->findByInvoice($invoice);
    $assert('failed real quote write rolls receipt and intent back', $quoteFailure === ['accepted' => false, 'reason' => 'storage_error', 'retryable' => true] && $quoteRepo->find(1)['paid_amount'] === 0 && $intent['status'] === 'pending' && (int) $wpdb->get_var('SELECT COUNT(*) FROM wp_vie_booking_quote_payment_receipt') === 0);

    // The quote ledger must reject a SePay transaction already held by the
    // existing order ledger.
    [$service, $quoteRepo, $paymentRepo, $wpdb] = $fixture();
    $invoice = $service->checkout('0123456789abcdef0123456789abcdef')['transfer']['memo'];
    $wpdb->insert('wp_vie_payment_log', [
        'order_id' => 88,
        'gateway' => 'sepay',
        'transaction_id' => 'SQLITE-TX-ORDER-USED',
        'amount' => 300000,
        'paid_at' => '2026-09-22 12:00:00',
        'created_at' => '2026-09-22 12:00:00',
    ]);
    $orderReuse = $service->processWebhook($payload($invoice, 'SQLITE-TX-ORDER-USED', 300000), null, 101);
    $assert('actual cross-ledger lookup rejects order transaction reuse', $orderReuse === ['accepted' => false, 'reason' => 'transaction_reused'] && $quoteRepo->find(1)['paid_amount'] === 0 && (int) $wpdb->get_var('SELECT COUNT(*) FROM wp_vie_booking_quote_payment_receipt') === 0);

    echo "\n--- Booking quote payment storage: {$pass} passed, {$fail} failed ---\n";
    exit($fail === 0 ? 0 : 1);
}
