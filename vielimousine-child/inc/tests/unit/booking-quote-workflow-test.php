<?php
declare(strict_types=1);

/** End-to-end quote workflow checks using production domain/repository code. */

const ARRAY_A = 'ARRAY_A';

$GLOBALS['quote_workflow_user'] = 0;
$GLOBALS['quote_workflow_caps'] = [
    101 => ['vie_create_booking_quotes', 'vie_view_own_booking_quotes'],
    202 => ['vie_create_booking_quotes', 'vie_view_own_booking_quotes'],
];
$GLOBALS['quote_workflow_options'] = [
    'blogname' => 'Vie Limousine',
    'admin_email' => 'private@example.test',
    'vie_invoice_settings' => [
        'company_name' => 'Vie Limousine',
        'company_phone' => '0901234567',
        'company_email' => 'booking@vielimousine.test',
        'company_address' => 'Ha Noi',
        'company_tax_id' => '0100000000',
        'logo_url' => '/wp-content/uploads/vie-logo.png',
    ],
];

function get_current_user_id(): int { return (int) $GLOBALS['quote_workflow_user']; }
function user_can(int $userId, string $capability): bool
{
    return in_array($capability, $GLOBALS['quote_workflow_caps'][$userId] ?? [], true);
}
function current_time(string $type): string { return '2026-09-22 10:00:00'; }
function wp_date(string $format): string { return (new DateTimeImmutable('2026-09-22'))->format($format); }
function wp_timezone(): DateTimeZone { return new DateTimeZone('Asia/Ho_Chi_Minh'); }
function home_url(string $path = ''): string { return 'https://vielimousine.test/' . ltrim($path, '/'); }
function wp_json_encode(mixed $value, int $flags = 0): string|false { return json_encode($value, $flags); }
function get_option(string $name, mixed $default = false): mixed
{
    return $GLOBALS['quote_workflow_options'][$name] ?? $default;
}
function sanitize_text_field(string $value): string
{
    return trim(preg_replace('/[\r\n\t]+/u', ' ', strip_tags($value)) ?? $value);
}
function sanitize_textarea_field(string $value): string
{
    return trim(strip_tags(str_replace(["\r\n", "\r"], "\n", $value)));
}
function is_email(string $value): string|false { return filter_var($value, FILTER_VALIDATE_EMAIL); }

final class BookingQuoteWorkflowWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $insert_id = 0;
    public int $starts = 0;
    public int $commits = 0;
    public int $rollbacks = 0;
    public bool $failNextUpdateAfterWrite = false;
    public bool $failNextRead = false;
    /** @var array<int,array<string,mixed>> */
    public array $rows = [];
    /** @var null|array<int,array<string,mixed>> */
    private ?array $snapshot = null;

    public function prepare(string $sql, mixed ...$values): string
    {
        return $sql . "\n/*workflow-args:" . base64_encode(serialize($values)) . '*/';
    }

    public function esc_like(string $value): string { return addcslashes($value, '_%\\'); }

    public function insert(string $table, array $data): int|false
    {
        foreach ($this->rows as $row) {
            if (($row['public_id'] ?? null) === ($data['public_id'] ?? null)
                || ($row['code'] ?? null) === ($data['code'] ?? null)) {
                $this->last_error = 'duplicate key';
                return false;
            }
        }
        $id = $this->rows === [] ? 1 : max(array_keys($this->rows)) + 1;
        $this->insert_id = $id;
        $this->last_error = '';
        $this->rows[$id] = ['id' => $id] + $data;
        return 1;
    }

    public function get_row(string $sql, string $output = ARRAY_A): ?array
    {
        if ($this->failNextRead) {
            $this->failNextRead = false;
            $this->last_error = 'simulated read failure';
            return null;
        }
        $this->last_error = '';
        [$raw, $args] = $this->decode($sql);
        if (str_contains($raw, 'WHERE id = %d')) {
            return $this->rows[(int) ($args[0] ?? 0)] ?? null;
        }
        if (str_contains($raw, 'WHERE public_id = %s')) {
            foreach ($this->rows as $row) {
                if (($row['public_id'] ?? '') === ($args[0] ?? null)) {
                    return $row;
                }
            }
            return null;
        }
        throw new RuntimeException('Unexpected row query: ' . $raw);
    }

    public function get_var(string $sql): int
    {
        [$raw, $args] = $this->decode($sql);
        if (!str_contains($raw, 'SELECT COUNT(*)')) {
            throw new RuntimeException('Unexpected scalar query: ' . $raw);
        }
        return count($this->filter($raw, $args));
    }

    /** @return list<array<string,mixed>> */
    public function get_results(string $sql, string $output = ARRAY_A): array
    {
        [$raw, $args] = $this->decode($sql);
        $rows = array_values($this->filter($raw, $args));
        if (preg_match('/ORDER BY ([a-z_]+) (ASC|DESC)/i', $raw, $match)) {
            [$column, $direction] = [$match[1], strtoupper($match[2])];
            usort($rows, static function (array $left, array $right) use ($column, $direction): int {
                $comparison = ($left[$column] ?? null) <=> ($right[$column] ?? null);
                return $direction === 'DESC' ? -$comparison : $comparison;
            });
        }
        if (preg_match('/LIMIT (\d+) OFFSET (\d+)/i', $raw, $match)) {
            $rows = array_slice($rows, (int) $match[2], (int) $match[1]);
        }
        return $rows;
    }

    public function query(string $sql): int|false
    {
        [$raw, $args] = $this->decode($sql);
        $command = strtoupper(trim($raw));
        if ($command === 'START TRANSACTION') {
            $this->starts++;
            $this->snapshot = $this->rows;
            return 0;
        }
        if ($command === 'COMMIT') {
            $this->commits++;
            $this->snapshot = null;
            return 0;
        }
        if ($command === 'ROLLBACK') {
            $this->rollbacks++;
            if ($this->snapshot !== null) {
                $this->rows = $this->snapshot;
            }
            $this->snapshot = null;
            return 0;
        }
        if (!preg_match('/^UPDATE \S+ SET (.+) WHERE id = %d(?: AND status = %s)?$/s', trim($raw), $match)) {
            throw new RuntimeException('Unexpected write query: ' . $raw);
        }

        $hasStatus = str_contains($raw, 'AND status = %s');
        $id = (int) $args[count($args) - ($hasStatus ? 2 : 1)];
        $expectedStatus = $hasStatus ? (string) $args[count($args) - 1] : null;
        if (!isset($this->rows[$id]) || ($hasStatus && $this->rows[$id]['status'] !== $expectedStatus)) {
            return 0;
        }
        $valueIndex = 0;
        foreach (preg_split('/,\s*/', $match[1]) ?: [] as $assignment) {
            if (!preg_match('/^`?([a-z_]+)`? = (NULL|%[ds])$/', trim($assignment), $part)) {
                throw new RuntimeException('Unexpected assignment: ' . $assignment);
            }
            $this->rows[$id][$part[1]] = $part[2] === 'NULL' ? null : $args[$valueIndex++];
        }
        if ($this->failNextUpdateAfterWrite) {
            $this->failNextUpdateAfterWrite = false;
            $this->last_error = 'simulated checked write failure';
            return false;
        }
        $this->last_error = '';
        return 1;
    }

    /** @return array{0:string,1:array} */
    private function decode(string $sql): array
    {
        if (!preg_match('/\n\/\*workflow-args:([A-Za-z0-9+\/=]+)\*\/$/', $sql, $match)) {
            return [$sql, []];
        }
        $raw = substr($sql, 0, -strlen($match[0]));
        $values = unserialize((string) base64_decode($match[1]), ['allowed_classes' => false]);
        return [$raw, is_array($values) ? $values : []];
    }

    /** @return array<int,array<string,mixed>> */
    private function filter(string $sql, array $args): array
    {
        $rows = $this->rows;
        $cursor = 0;
        if (str_contains($sql, '1 = 0')) {
            return [];
        }
        if (str_contains($sql, 'sales_user_id = %d')) {
            $owner = (int) $args[$cursor++];
            $rows = array_filter($rows, static fn(array $row): bool => (int) ($row['sales_user_id'] ?? 0) === $owner);
        }
        if (str_contains($sql, 'code LIKE %s')) {
            $needle = trim((string) $args[$cursor], '%');
            $cursor += 5;
            $rows = array_filter($rows, static function (array $row) use ($needle): bool {
                foreach (['code', 'customer_name', 'customer_phone', 'customer_email', 'title'] as $column) {
                    if (stripos((string) ($row[$column] ?? ''), $needle) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }
        if (str_contains($sql, 'status = %s')) {
            $status = (string) $args[$cursor++];
            $rows = array_filter($rows, static fn(array $row): bool => ($row['status'] ?? '') === $status);
        } elseif (str_contains($sql, "status = 'published' AND paid_amount = 0")) {
            $now = (string) $args[$cursor++];
            $rows = array_filter($rows, static fn(array $row): bool => ($row['status'] ?? '') === 'published'
                && (int) ($row['paid_amount'] ?? 0) === 0 && !empty($row['valid_until']) && $row['valid_until'] <= $now);
        } elseif (str_contains($sql, "status = 'published' AND (paid_amount > 0")) {
            $now = (string) $args[$cursor++];
            $rows = array_filter($rows, static fn(array $row): bool => ($row['status'] ?? '') === 'published'
                && ((int) ($row['paid_amount'] ?? 0) > 0 || empty($row['valid_until']) || $row['valid_until'] > $now));
        }
        return $rows;
    }
}

require __DIR__ . '/../../src/Support/QueryBuilder.php';
require __DIR__ . '/../../src/Repository/RepositoryException.php';
require __DIR__ . '/../../src/Repository/AbstractRepository.php';
require __DIR__ . '/../../src/Service/BookingQuote/BookingQuoteException.php';
require __DIR__ . '/../../src/Validation/Schemas/BookingQuoteValidation.php';
require __DIR__ . '/../../src/Repository/BookingQuoteRepository.php';
require __DIR__ . '/../../src/Service/BookingQuote/BookingQuotePolicy.php';
require __DIR__ . '/../../src/Service/Settings/InvoiceSettings.php';
require __DIR__ . '/../../src/Service/BookingQuote/BookingQuoteService.php';

use Vie\Repository\BookingQuoteRepository;
use Vie\Repository\RepositoryException;
use Vie\Service\BookingQuote\BookingQuoteException;
use Vie\Service\BookingQuote\BookingQuotePolicy;
use Vie\Service\BookingQuote\BookingQuoteService;
use Vie\Service\Settings\InvoiceSettings;

$wpdb = new BookingQuoteWorkflowWpdb();
$repository = new BookingQuoteRepository();
$service = new BookingQuoteService($repository, new BookingQuotePolicy(), new InvoiceSettings());
$passed = 0;
$failed = 0;
$assert = static function (string $name, bool $condition, string $detail = '') use (&$passed, &$failed): void {
    if ($condition) {
        echo "  ✓ {$name}\n";
        $passed++;
        return;
    }
    echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    $failed++;
};
$same = static function (string $name, mixed $expected, mixed $actual) use ($assert): void {
    $assert($name, $expected === $actual, 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
};
$throws = static function (string $name, string $code, callable $callback) use ($assert): void {
    try {
        $callback();
        $assert($name, false, 'expected BookingQuoteException');
    } catch (BookingQuoteException $exception) {
        $assert($name, $exception->errorCode === $code, 'got ' . $exception->errorCode);
    } catch (Throwable $exception) {
        $assert($name, false, 'unexpected ' . $exception::class . ': ' . $exception->getMessage());
    }
};
$payload = static fn(string $customer): array => [
    'customer_name' => $customer,
    'customer_phone' => '0901234567',
    'customer_email' => strtolower(str_replace(' ', '.', $customer)) . '@example.test',
    'title' => 'Chuyến đi Hạ Long',
    'lines' => [['label' => 'Xe limousine', 'quantity' => 2, 'unit' => 'khách', 'unit_price' => 1_000_000]],
    'discount' => 100_000,
    'deposit_type' => 'percent',
    'deposit_value' => 30,
    'valid_until' => '2099-10-01T17:30',
];

// Real create -> publish -> public read workflow, followed by immutable update protection.
$draftA = $service->createDraft($payload('Owner A'), 101);
$same('created quote is a draft owned by A', ['draft', 101], [$draftA['status'], $draftA['sales_user_id']]);
$same('create computes financial fields', [2_000_000, 1_900_000, 570_000], [$draftA['subtotal'], $draftA['total'], $draftA['deposit_amount']]);
$throws('legacy manual draft cannot be published without room items', 'validation_error', static fn() => $service->publish($draftA['id'], 101));
// Keep lifecycle coverage for a historical manual quote. Such rows remain
// readable and immutable after the pricing workflow is enabled.
$wpdb->rows[$draftA['id']]['status'] = 'published';
$wpdb->rows[$draftA['id']]['brand'] = json_encode($GLOBALS['quote_workflow_options']['vie_invoice_settings']);
$wpdb->rows[$draftA['id']]['published_at'] = '2026-09-22 10:00:00';
$publishedA = $service->getAdmin($draftA['id'], 101);
$same('historical published quote preserves brand and state', ['published', 'Vie Limousine'], [$publishedA['status'], $publishedA['brand']['company_name'] ?? null]);
$publicA = $service->getPublic($publishedA['public_id']);
$same('published token resolves publicly', $publishedA['code'], $publicA['code']);
$assert('public projection excludes private contact/state fields', !isset($publicA['customer_phone'], $publicA['customer_email'], $publicA['sales_user_id'], $publicA['status']));
$throws('published quote is immutable', 'quote_immutable', static fn() => $service->updateDraft($publishedA['id'], ['title' => 'Tampered'], 101));
$same('immutable update leaves title unchanged', 'Chuyến đi Hạ Long', $repository->find($publishedA['id'])['title'] ?? null);

// Ownership: B sees only B and cannot read or mutate any A quote.
$draftForDenials = $service->createDraft($payload('Owner A draft'), 101);
$draftB = $service->createDraft($payload('Owner B'), 202);
$GLOBALS['quote_workflow_user'] = 202;
$listedB = $service->list(['per_page' => 100]);
$same('owner B list is scoped to B', [$draftB['id']], array_column($listedB['data'], 'id'));
$throws('owner B cannot read A', 'forbidden', static fn() => $service->getAdmin($publishedA['id'], 202));
$throws('owner B cannot update A', 'forbidden', static fn() => $service->updateDraft($draftForDenials['id'], ['title' => 'No'], 202));
$throws('owner B cannot publish A', 'forbidden', static fn() => $service->publish($draftForDenials['id'], 202));
$throws('owner B cannot revoke A', 'forbidden', static fn() => $service->revoke($publishedA['id'], 202));
$throws('owner B cannot duplicate A', 'forbidden', static fn() => $service->duplicate($publishedA['id'], 202));
$throws('user without create capability cannot create', 'forbidden', static fn() => $service->createDraft($payload('No capability'), 303));

// A storage error during a locking read is distinct from an absent public token.
$wpdb->failNextRead = true;
try {
    $repository->lockForUpdateByPublicId(str_repeat('f', 32));
    $assert('locking read failure does not masquerade as not found', false, 'expected RepositoryException');
} catch (RepositoryException) {
    $assert('locking read failure does not masquerade as not found', true);
}

// Creation has no arbitrary per-user cap.
for ($index = 0; $index < 30; $index++) {
    $service->createDraft($payload('Bulk ' . $index), 101);
}
$GLOBALS['quote_workflow_user'] = 101;
$same('owner can create more than a legacy page-sized cap', 32, $service->list(['per_page' => 100])['pagination']['total']);

// A checked write that fails after touching memory must roll back atomically.
$rollbackDraft = $service->createDraft($payload('Rollback'), 101);
$rollbacksBefore = $wpdb->rollbacks;
$wpdb->failNextUpdateAfterWrite = true;
$throws('failed checked write becomes save_failed', 'save_failed', static fn() => $service->updateDraft($rollbackDraft['id'], ['title' => 'Must roll back'], 101));
$same('failed checked write rolls back stored content', 'Chuyến đi Hạ Long', $repository->find($rollbackDraft['id'])['title'] ?? null);
$same('failed checked write issued rollback', $rollbacksBefore + 1, $wpdb->rollbacks);

// Expiry is an effective projection; the immutable stored status stays published.
$wpdb->rows[$publishedA['id']]['valid_until'] = '2020-01-01 00:00:00';
$expired = $service->getPublic($publishedA['public_id']);
$same('expired public quote is projected without state mutation', ['expired', false, 'published'], [
    $expired['effective_status'], $expired['can_checkout'], $repository->find($publishedA['id'])['status'],
]);

// Duplicate through a view-all operator must be a clean new aggregate.
$repository->updateFinancialStateChecked($publishedA['id'], ['paid_amount' => 570_000, 'payment_review' => true]);
$GLOBALS['quote_workflow_caps'][202][] = 'vie_view_all_booking_quotes';
$copy = $service->duplicate($publishedA['id'], 202);
$source = $repository->find($publishedA['id']);
$assert('duplicate gets a new aggregate id/token/code', $copy['id'] !== $source['id']
    && $copy['public_id'] !== $source['public_id'] && $copy['code'] !== $source['code']);
$same('duplicate resets owner and lifecycle state', [202, 'draft', 0, false, null, null], [
    $copy['sales_user_id'], $copy['status'], $copy['paid_amount'], $copy['payment_review'], $copy['brand'], $copy['published_at'],
]);
$assert('new id implies independent payment history', $copy['id'] !== $source['id']);
$copy = $service->updateDraft($copy['id'], ['valid_until' => '2099-10-01T17:30'], 202);
$throws('duplicated legacy manual draft still requires room items', 'validation_error', static fn() => $service->publish($copy['id'], 202));
$wpdb->rows[$copy['id']]['status'] = 'published';
$wpdb->rows[$copy['id']]['brand'] = json_encode($GLOBALS['quote_workflow_options']['vie_invoice_settings']);
$wpdb->rows[$copy['id']]['published_at'] = '2026-09-22 10:00:00';
$service->revoke($copy['id'], 202);
$throws('revoked quote is no longer public', 'not_found', static fn() => $service->getPublic($copy['public_id']));

echo "\n--- Booking quote workflow: {$passed} passed, {$failed} failed ---\n";
exit($failed === 0 ? 0 : 1);
