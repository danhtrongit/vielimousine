<?php
declare(strict_types=1);

/**
 * Opt-in MySQL parser check for the booking quote and payment schemas.
 *
 * Run against a disposable MySQL database whose name starts with
 * "vie_schema_test_":
 *
 *   VIE_SCHEMA_TEST_SOCKET=/path/to/mysql.sock \
 *   VIE_SCHEMA_TEST_DB=vie_schema_test_isolated \
 *   php inc/tests/schema-mysql-test.php
 *
 * The test creates only randomly prefixed tables and removes them afterward.
 * It never reads WordPress configuration or connects over TCP.
 */

use Vie\Schema\BookingQuotePaymentSchema;
use Vie\Schema\BookingQuoteSchema;
use Vie\Schema\SepayWebhookEventSchema;

$socket = getenv('VIE_SCHEMA_TEST_SOCKET');
$database = getenv('VIE_SCHEMA_TEST_DB');
$user = getenv('VIE_SCHEMA_TEST_USER') ?: 'root';
$password = getenv('VIE_SCHEMA_TEST_PASSWORD') ?: '';

if (!is_string($socket) || $socket === '' || !file_exists($socket) || filetype($socket) !== 'socket'
    || !is_string($database)
    || !preg_match('/^vie_schema_test_[a-z0-9_]+$/', $database)
) {
    fwrite(STDERR, "Set VIE_SCHEMA_TEST_SOCKET to a Unix socket and VIE_SCHEMA_TEST_DB to an existing vie_schema_test_* database.\n");
    exit(2);
}

if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "pdo_mysql is required.\n");
    exit(2);
}

/** Minimal WordPress surface: retain exactly the SQL passed to dbDelta(). */
class wpdb
{
    public function __construct(public string $prefix) {}

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }
}

/** @var list<string> $schemaSql */
$schemaSql = [];

function dbDelta(string $sql): void
{
    global $schemaSql;
    $schemaSql[] = $sql;
}

require_once __DIR__ . '/../src/Schema/BookingQuoteSchema.php';
require_once __DIR__ . '/../src/Schema/BookingQuotePaymentSchema.php';
require_once __DIR__ . '/../src/Schema/SepayWebhookEventSchema.php';

$prefix = 'vst_' . bin2hex(random_bytes(5)) . '_';
$wpdb = new wpdb($prefix);
BookingQuoteSchema::install($wpdb);
BookingQuotePaymentSchema::install($wpdb);
SepayWebhookEventSchema::install($wpdb);

if (count($schemaSql) !== 4) {
    throw new RuntimeException('Expected four production CREATE TABLE statements.');
}

$tables = array_map(
    static fn (string $suffix): string => $prefix . $suffix,
    ['vie_booking_quote', 'vie_booking_quote_payment', 'vie_booking_quote_payment_receipt', 'vie_sepay_webhook_event'],
);

$pdo = new PDO(
    'mysql:unix_socket=' . $socket . ';dbname=' . $database . ';charset=utf8mb4',
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_CASE => PDO::CASE_LOWER],
);

try {
    $legacySql = str_replace('`lines` LONGTEXT NOT NULL', 'lines LONGTEXT NOT NULL', $schemaSql[0], $replacements);
    if ($replacements !== 1) {
        throw new RuntimeException('The production quote schema does not contain the expected quoted lines column.');
    }

    try {
        $pdo->exec($legacySql);
        throw new RuntimeException('MySQL unexpectedly accepted the former unquoted lines column.');
    } catch (PDOException $error) {
        if ((int) ($error->errorInfo[1] ?? 0) !== 1064) {
            throw $error;
        }
    }

    foreach ($schemaSql as $sql) {
        $pdo->exec($sql);
    }

    $tableCheck = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    foreach ($tables as $table) {
        $tableCheck->execute([$table]);
        if ((int) $tableCheck->fetchColumn() !== 1) {
            throw new RuntimeException("MySQL did not create {$table}.");
        }
    }

    $columnCheck = $pdo->prepare(
        'SELECT data_type, is_nullable FROM information_schema.columns '
        . 'WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $columnCheck->execute([$tables[0], 'lines']);
    $column = $columnCheck->fetch(PDO::FETCH_ASSOC);
    if (($column['data_type'] ?? null) !== 'longtext' || ($column['is_nullable'] ?? null) !== 'NO') {
        throw new RuntimeException('The quote lines column was not created as LONGTEXT NOT NULL.');
    }

    $lines = '[{"item":"transfer","amount":250000}]';
    $insert = $pdo->prepare(
        'INSERT INTO `' . $tables[0] . '` (public_id, code, sales_user_id, `lines`) VALUES (?, ?, ?, ?)'
    );
    $insert->execute([bin2hex(random_bytes(16)), 'SCHEMA-PROBE', 1, $lines]);
    $read = $pdo->query('SELECT `lines` FROM `' . $tables[0] . '` WHERE code = ' . $pdo->quote('SCHEMA-PROBE'));
    if ($read->fetchColumn() !== $lines) {
        throw new RuntimeException('A quote lines value did not round-trip through MySQL.');
    }

    echo "PASS: MySQL rejected the legacy quote DDL (1064), created four production tables, and round-tripped quote lines.\n";
} finally {
    foreach (array_reverse($tables) as $table) {
        $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
    }
}
