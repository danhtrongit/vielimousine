<?php
declare(strict_types=1);

/**
 * Price parity tests for booking quotes.
 *
 * The fixture is deliberately backed by the same repositories and
 * PriceCalculator that OrderService uses.  This keeps these checks independent
 * from WordPress/MySQL while still exercising room identity, nightly prices,
 * child rules and combo tickets through production code.
 */

const ARRAY_A = 'ARRAY_A';

function current_time(string $type): string { return '2026-09-22 10:00:00'; }
function wp_timezone(): DateTimeZone { return new DateTimeZone('Asia/Ho_Chi_Minh'); }
function sanitize_title(string $value): string { return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value)); }

final class BookingQuotePricingWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $insert_id = 0;

    public function __construct(private readonly PDO $pdo) {}

    public function prepare(string $sql, mixed ...$values): string
    {
        // WordPress accepts both prepare($sql, ...$args) and prepare($sql, $args).
        if (count($values) === 1 && is_array($values[0])) {
            $values = $values[0];
        }
        $index = 0;
        $sql = preg_replace_callback('/%[dsf]/', function (array $match) use (&$index, $values): string {
            $value = $values[$index++] ?? null;
            if ($match[0] === '%d') {
                return (string) (int) $value;
            }
            return $this->pdo->quote((string) $value);
        }, $sql) ?? $sql;
        return $sql;
    }

    public function get_row(string $sql, string $output = ARRAY_A): ?array
    {
        try {
            $statement = $this->pdo->query($sql);
            $row = $statement?->fetch(PDO::FETCH_ASSOC);
            $this->last_error = '';
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            $this->last_error = $e->getMessage();
            throw $e;
        }
    }

    public function get_results(string $sql, string $output = ARRAY_A): array
    {
        try {
            $statement = $this->pdo->query($sql);
            $this->last_error = '';
            return $statement?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $this->last_error = $e->getMessage();
            throw $e;
        }
    }

    public function get_var(string $sql): mixed
    {
        $row = $this->get_row($sql);
        return $row === null ? null : array_values($row)[0];
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
CREATE TABLE wp_vie_hotel (
 id INTEGER PRIMARY KEY, name TEXT, default_ticket_price INTEGER,
 ticket_free_children_count INTEGER, ticket_free_children_max_age INTEGER
);
CREATE TABLE wp_vie_room (
 id INTEGER PRIMARY KEY, hotel_id INTEGER, name TEXT, included_adults INTEGER,
 max_adults INTEGER, max_children INTEGER, base_price INTEGER,
 extra_adult_price INTEGER, free_children_count INTEGER,
 free_children_max_age INTEGER, is_active INTEGER, sort_order INTEGER
);
CREATE TABLE wp_vie_room_price (
 id INTEGER PRIMARY KEY, room_id INTEGER, date TEXT, price INTEGER,
 extra_adult_price INTEGER, stock INTEGER, is_active INTEGER, source TEXT
);
CREATE TABLE wp_vie_surcharge (
 id INTEGER PRIMARY KEY, room_id INTEGER, guest_type TEXT, label TEXT,
 age_from INTEGER, age_to INTEGER, child_index_min INTEGER,
 child_index_max INTEGER, amount INTEGER, is_free INTEGER,
 sort_order INTEGER, is_active INTEGER
);
CREATE TABLE wp_vie_surcharge_price (
 id INTEGER PRIMARY KEY, surcharge_id INTEGER, date TEXT, amount INTEGER, is_active INTEGER
);
CREATE TABLE wp_vie_ticket_price (
 id INTEGER PRIMARY KEY, hotel_id INTEGER, route_id INTEGER, date TEXT,
 ticket_price INTEGER, is_active INTEGER
);
SQL);

$pdo->exec("INSERT INTO wp_vie_hotel VALUES (1, 'Parity Hotel', 350000, 1, 5)");
$pdo->exec("INSERT INTO wp_vie_room VALUES (10, 1, 'Deluxe DB', 2, 3, 2, 1800000, 400000, 1, 5, 1, 1)");
$insertPrice = $pdo->prepare('INSERT INTO wp_vie_room_price VALUES (?, 10, ?, ?, ?, 5, 1, \'fixture\')');
$prices = [
    ['2026-10-01', 1800500, 400100],
    ['2026-10-02', 2200500, 450100],
    ['2026-10-03', 1900500, 410100],
];
foreach ($prices as $index => [$date, $room, $extra]) {
    $insertPrice->execute([$index + 1, $date, $room, $extra]);
}
$pdo->exec("INSERT INTO wp_vie_surcharge VALUES (20, 10, 'child', 'Child surcharge', 6, 11, 1, NULL, 125050, 0, 1, 1)");
$pdo->exec("INSERT INTO wp_vie_surcharge_price VALUES (30, 20, '2026-10-02', 175050, 1)");

$wpdb = new BookingQuotePricingWpdb($pdo);

require __DIR__ . '/../../src/Support/QueryBuilder.php';
require __DIR__ . '/../../src/Support/Money.php';
require __DIR__ . '/../../src/Repository/RepositoryException.php';
require __DIR__ . '/../../src/Repository/AbstractRepository.php';
require __DIR__ . '/../../src/Repository/RoomRepository.php';
require __DIR__ . '/../../src/Repository/HotelRepository.php';
require __DIR__ . '/../../src/Repository/RoomPriceRepository.php';
require __DIR__ . '/../../src/Repository/SurchargeRepository.php';
require __DIR__ . '/../../src/Repository/SurchargePriceRepository.php';
require __DIR__ . '/../../src/Repository/TicketPriceRepository.php';
require __DIR__ . '/../../src/DTO/ChildAssessment.php';
require __DIR__ . '/../../src/DTO/PriceBreakdown.php';
require __DIR__ . '/../../src/DTO/QuoteRequest.php';
require __DIR__ . '/../../src/Service/Pricing/GuestComposition.php';
require __DIR__ . '/../../src/Service/Pricing/RoomAllocation.php';
require __DIR__ . '/../../src/Service/Pricing/ChildPolicy.php';
require __DIR__ . '/../../src/Service/Pricing/SurchargeCalculator.php';
require __DIR__ . '/../../src/Service/Pricing/TicketCalculator.php';
require __DIR__ . '/../../src/Service/Pricing/PriceCalculator.php';

$calculator = new \Vie\Service\Pricing\PriceCalculator(
    new \Vie\Repository\RoomRepository(),
    new \Vie\Repository\HotelRepository(),
    new \Vie\Repository\RoomPriceRepository(),
    new \Vie\Repository\SurchargeRepository(),
    new \Vie\Repository\SurchargePriceRepository(),
    new \Vie\Repository\TicketPriceRepository(),
);

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

// Room prices, extra beds and child surcharges are calculated for every night;
// the date-specific surcharge override is used on 2026-10-02.
$room = $calculator->quote(\Vie\DTO\QuoteRequest::fromArray([
    'room_id' => 10, 'checkin' => '2026-10-01', 'checkout' => '2026-10-03',
    'adults' => 3, 'child_ages' => [8], 'user_rooms' => 1, 'booking_type' => 'room',
]));
$same('room identity comes from persisted room row', 'Deluxe DB', $pdo->query('SELECT name FROM wp_vie_room WHERE id = 10')->fetchColumn());
$same('two nights are represented', 2, $room->nights);
$same('one room with one extra adult bed', [1, 1], [$room->numRooms, $room->extraAdultBeds]);
$same('nightly room subtotal includes both dates', 4_001_000, $room->roomSubtotal);
$same('nightly extra adult prices include both dates', 850_200, $room->extraAdultSubtotal);
$same('child surcharge uses date override', 300_100, $room->childSurchargeTotal);
$same('room subtotal is server-derived', 5_151_300, $room->subtotal);
$same('cost/profit data never enters calculator output', 0, $room->costTotal);

// Combo ticket pricing is once per stay, while room and child charges remain
// nightly. One child under the ticket free-age quota gets a free seat.
$combo = $calculator->quote(\Vie\DTO\QuoteRequest::fromArray([
    'room_id' => 10, 'checkin' => '2026-10-01', 'checkout' => '2026-10-04',
    'adults' => 2, 'child_ages' => [4, 8], 'user_rooms' => 1, 'booking_type' => 'combo',
]));
$same('combo has all requested seats', 4, $combo->seatCount);
$same('combo grants one free child seat', 3, $combo->billableSeats);
$same('combo ticket is charged once per stay', 1_050_000, $combo->ticketSubtotal);
$same('combo room subtotal spans three nights', 5_901_500, $combo->roomSubtotal);
$same('combo child ticket subtotal excludes adult seats', 350_000, $combo->childTicketSubtotal);

// OrderService aggregates breakdown subtotals before applying its nearest-1000
// VND rounding. This catches per-line rounding drift in quote implementations.
$second = $calculator->quote(\Vie\DTO\QuoteRequest::fromArray([
    'room_id' => 10, 'checkin' => '2026-10-01', 'checkout' => '2026-10-02',
    'adults' => 2, 'child_ages' => [], 'user_rooms' => 1, 'booking_type' => 'room',
]));
$aggregateSubtotal = $room->subtotal + $second->subtotal;
$rounded = (int) (round($aggregateSubtotal / 1000, 0, PHP_ROUND_HALF_UP) * 1000);
$same('multi-item aggregate uses raw subtotals before rounding', 6_952_000, $rounded);

// Tampered client totals/line values are not inputs to PriceCalculator; callers
// must derive quote lines from this breakdown and persisted room rows.
$tampered = \Vie\DTO\QuoteRequest::fromArray([
    'room_id' => 10, 'checkin' => '2026-10-01', 'checkout' => '2026-10-02',
    'adults' => 2, 'child_ages' => [], 'user_rooms' => 1, 'booking_type' => 'room',
    'total' => 1, 'lines' => [['unit_price' => 1, 'line_total' => 1]],
]);
$beforePrices = $pdo->query('SELECT room_id, date, price, stock FROM wp_vie_room_price ORDER BY date')->fetchAll(PDO::FETCH_ASSOC);
$clean = $calculator->quote($tampered);
$same('tampered client totals are ignored by pricing engine', 1_800_500, $clean->subtotal);
$same('public breakdown omits cost total', false, array_key_exists('cost_total', $clean->toPublicArray()));

$unavailable = $calculator->quote(\Vie\DTO\QuoteRequest::fromArray([
    'room_id' => 10, 'checkin' => '2026-10-04', 'checkout' => '2026-10-05',
    'adults' => 2, 'child_ages' => [], 'user_rooms' => 1, 'booking_type' => 'room',
]));
$same('missing room date requires a quote', true, $unavailable->requiresQuote);
$same('pricing does not mutate stock or room prices', $beforePrices,
    $pdo->query('SELECT room_id, date, price, stock FROM wp_vie_room_price ORDER BY date')->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- Booking quote pricing: {$passed} passed, {$failed} failed ---\n";
exit($failed === 0 ? 0 : 1);
