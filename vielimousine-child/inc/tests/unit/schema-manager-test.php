<?php
declare(strict_types=1);

/** Offline regression coverage for the production SchemaManager and schema DDL. */

$GLOBALS['schema_test_options'] = [];
$GLOBALS['schema_test_updates'] = [];

function get_option(string $name, mixed $default = false): mixed
{
    return array_key_exists($name, $GLOBALS['schema_test_options'])
        ? $GLOBALS['schema_test_options'][$name]
        : $default;
}

function update_option(string $name, mixed $value, bool $autoload = true): bool
{
    $GLOBALS['schema_test_options'][$name] = $value;
    $GLOBALS['schema_test_updates'][] = [$name, $value, $autoload];
    return true;
}

/** The small WordPress database surface used by actual schema installers. */
class wpdb
{
    public string $last_error = '';
    public array $tables = [];
    public array $ddl = [];
    public array $inventoryQueries = [];
    public array $failCreate = [];
    public bool $errorsSuppressed = false;

    public function __construct(public string $prefix = 'wp_') {}

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4';
    }

    public function esc_like(string $text): string
    {
        return addcslashes($text, '_%\\');
    }

    public function prepare(string $sql, mixed ...$arguments): string
    {
        $index = 0;
        return (string) preg_replace_callback('/%s/', static function () use (&$index, $arguments): string {
            if (!array_key_exists($index, $arguments)) {
                throw new RuntimeException('Missing SQL argument');
            }
            return "'" . str_replace("'", "''", (string) $arguments[$index++]) . "'";
        }, $sql);
    }

    public function suppress_errors(bool $suppress = true): bool
    {
        $previous = $this->errorsSuppressed;
        $this->errorsSuppressed = $suppress;
        return $previous;
    }

    public function get_col(string $sql): array
    {
        $this->inventoryQueries[] = $sql;
        if (!preg_match("/^SHOW TABLES LIKE '((?:''|[^'])*)'$/i", trim($sql), $match)) {
            throw new RuntimeException('Unexpected inventory SQL: ' . $sql);
        }
        $like = str_replace("''", "'", $match[1]);
        $regex = '';
        $escaped = false;
        foreach (str_split($like) as $character) {
            if ($escaped) {
                $regex .= preg_quote($character, '/');
                $escaped = false;
            } elseif ($character === '\\') {
                $escaped = true;
            } elseif ($character === '%') {
                $regex .= '.*';
            } elseif ($character === '_') {
                $regex .= '.';
            } else {
                $regex .= preg_quote($character, '/');
            }
        }
        if ($escaped) {
            $regex .= '\\\\';
        }
        $this->last_error = '';
        return array_values(array_filter(array_keys($this->tables), static fn(string $table): bool =>
            preg_match('/^' . $regex . '$/D', $table) === 1
        ));
    }
}

/** Simulates MySQL's result of the real CREATE TABLE statement passed to dbDelta. */
function dbDelta(string $sql): array
{
    $wpdb = $GLOBALS['wpdb'];
    if (!preg_match('/^\s*CREATE TABLE\s+`?([a-zA-Z0-9_]+)`?\s*\(/i', $sql, $match)) {
        throw new RuntimeException('Unexpected dbDelta SQL');
    }
    $table = $match[1];
    $wpdb->ddl[] = $sql;
    $failure = $wpdb->failCreate[$table] ?? null;
    if ($failure === 'silent') {
        $wpdb->last_error = '';
        return [];
    }
    if ($failure === 'error') {
        $wpdb->last_error = 'simulated CREATE TABLE failure';
        return [];
    }
    $wpdb->tables[$table] = true;
    $wpdb->last_error = $failure === 'error_after_create' ? 'simulated CREATE TABLE failure' : '';
    return [$table => 'Created table ' . $table];
}

require __DIR__ . '/../../bootstrap.php';

use Vie\Schema\SchemaManager;

/** @return array<string,string> Versions from production schema classes. */
function schemaTestVersions(): array
{
    $schemas = (new ReflectionClass(SchemaManager::class))->getReflectionConstant('SCHEMAS')->getValue();
    $versions = [];
    foreach ($schemas as $key => $class) {
        $versions[$key] = $class::VERSION;
    }
    return $versions;
}

function schemaTestResetRunFlag(): void
{
    (new ReflectionClass(SchemaManager::class))->getProperty('ran')->setValue(null, false);
}

/** @return wpdb A ready database with all production tables and companion receipt. */
function schemaTestFixture(string $prefix = 'wp_'): wpdb
{
    schemaTestResetRunFlag();
    $wpdb = new wpdb($prefix);
    foreach (array_keys(schemaTestVersions()) as $table) {
        $wpdb->tables[$prefix . $table] = true;
    }
    $wpdb->tables[$prefix . 'vie_booking_quote_payment_receipt'] = true;
    $GLOBALS['wpdb'] = $wpdb;
    $GLOBALS['schema_test_options'] = [
        'vie_schema_versions' => schemaTestVersions(),
        'vie_drop_product_code_v1' => 'done',
        'vie_backfill_customer_booking_count_v1' => 'done',
        'vie_order_draft_columns_v1' => 'done',
    ];
    $GLOBALS['schema_test_updates'] = [];
    return $wpdb;
}

function schemaTestDdlContains(wpdb $wpdb, string $table): bool
{
    foreach ($wpdb->ddl as $sql) {
        if (preg_match('/^\s*CREATE TABLE\s+`?' . preg_quote($table, '/') . '`?\s*\(/i', $sql)) {
            return true;
        }
    }
    return false;
}

$passed = 0;
$failed = 0;
$assert = static function (string $label, bool $condition) use (&$passed, &$failed): void {
    echo '  ' . ($condition ? '✓ ' : '✗ ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$versions = schemaTestVersions();
$wpdb = schemaTestFixture();
SchemaManager::install();
$assert('matching versions with all tables perform no DDL', $wpdb->ddl === []);
$assert('matching versions leave version option untouched', $GLOBALS['schema_test_updates'] === []);
$assert('one escaped-prefix inventory query checks a healthy installation',
    count($wpdb->inventoryQueries) === 1 && str_contains($wpdb->inventoryQueries[0], "wp\\_vie\\_%"));

$wpdb = schemaTestFixture();
unset($wpdb->tables['wp_vie_booking_quote']);
SchemaManager::install();
$assert('missing quote table is recreated despite matching version',
    isset($wpdb->tables['wp_vie_booking_quote']) && schemaTestDdlContains($wpdb, 'wp_vie_booking_quote'));
$assert('recreated quote keeps its current version',
    get_option('vie_schema_versions')['vie_booking_quote'] === $versions['vie_booking_quote']);

$wpdb = schemaTestFixture();
unset($wpdb->tables['wp_vie_booking_quote_payment_receipt']);
SchemaManager::install();
$assert('missing payment receipt is recreated despite matching intent version',
    isset($wpdb->tables['wp_vie_booking_quote_payment_receipt'])
    && schemaTestDdlContains($wpdb, 'wp_vie_booking_quote_payment_receipt'));
$assert('payment version remains current only after both ledgers exist',
    get_option('vie_schema_versions')['vie_booking_quote_payment'] === $versions['vie_booking_quote_payment']);

$wpdb = schemaTestFixture();
unset($wpdb->tables['wp_vie_booking_quote']);
$wpdb->failCreate['wp_vie_booking_quote'] = 'silent';
SchemaManager::install();
$assert('silent failed create clears stale quote version',
    !isset(get_option('vie_schema_versions')['vie_booking_quote']) && !isset($wpdb->tables['wp_vie_booking_quote']));
$assert('failed create restores error-suppression state', $wpdb->errorsSuppressed === false);
unset($wpdb->failCreate['wp_vie_booking_quote']);
SchemaManager::install();
$assert('same-process retry installs quote and restores version',
    isset($wpdb->tables['wp_vie_booking_quote'])
    && get_option('vie_schema_versions')['vie_booking_quote'] === $versions['vie_booking_quote']
    && count($wpdb->ddl) === 2);

$wpdb = schemaTestFixture();
unset($wpdb->tables['wp_vie_booking_quote_payment_receipt']);
$wpdb->failCreate['wp_vie_booking_quote_payment_receipt'] = 'error';
SchemaManager::install();
$assert('receipt create error clears payment version even when intent exists',
    !isset(get_option('vie_schema_versions')['vie_booking_quote_payment'])
    && isset($wpdb->tables['wp_vie_booking_quote_payment'])
    && !isset($wpdb->tables['wp_vie_booking_quote_payment_receipt']));
unset($wpdb->failCreate['wp_vie_booking_quote_payment_receipt']);
SchemaManager::install();
$assert('retry restores both ledgers and payment version',
    isset($wpdb->tables['wp_vie_booking_quote_payment_receipt'])
    && get_option('vie_schema_versions')['vie_booking_quote_payment'] === $versions['vie_booking_quote_payment']);

$wpdb = schemaTestFixture();
unset($wpdb->tables['wp_vie_booking_quote']);
$wpdb->failCreate['wp_vie_booking_quote'] = 'error_after_create';
SchemaManager::install();
$assert('database error prevents version stamp even if table appeared',
    isset($wpdb->tables['wp_vie_booking_quote'])
    && !isset(get_option('vie_schema_versions')['vie_booking_quote']));

$wpdb = schemaTestFixture('tenant_7_');
unset($wpdb->tables['tenant_7_vie_hotel']);
$wpdb->tables['tenantX7_vie_hotel'] = true;
SchemaManager::install();
$assert('alternate prefix repairs its own table despite LIKE wildcard decoy',
    isset($wpdb->tables['tenant_7_vie_hotel'])
    && schemaTestDdlContains($wpdb, 'tenant_7_vie_hotel')
    && count($wpdb->ddl) === 1);
$assert('alternate prefix inventory escapes underscores',
    str_contains($wpdb->inventoryQueries[0], 'tenant\\_7\\_vie\\_%'));

$wpdb = schemaTestFixture();
$GLOBALS['schema_test_options']['vie_schema_versions'] = 'corrupt-version-map';
SchemaManager::install();
$stored = get_option('vie_schema_versions');
$assert('malformed version option is repaired from production schemas',
    is_array($stored) && $stored === $versions && count($wpdb->ddl) >= count($versions));

echo "\n--- Schema manager: {$passed} passed, {$failed} failed ---\n";
exit($failed === 0 ? 0 : 1);
