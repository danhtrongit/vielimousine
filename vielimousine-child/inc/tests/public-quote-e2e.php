<?php
declare(strict_types=1);
/**
 * Public quote leakage e2e. Self-contained (no DB seed needed).
 * Verifies cost_total (internal cost/margin) is NOT exposed on the public /quote response,
 * while still being retained internally for the order pricing_snapshot.
 */
if (!isset($GLOBALS['wpdb'])) { throw new RuntimeException('Run inside WP context'); }

use Vie\DTO\PriceBreakdown;

$pass = $pass ?? 0;
$fail = $fail ?? 0;
$assert = function (string $name, bool $cond, string $detail = '') use (&$pass, &$fail): void {
    if ($cond) { echo "  ✓ {$name}\n"; $pass++; }
    else { echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; $fail++; }
};

echo "Public quote: cost_total must not leak to the client\n";

$bd = new PriceBreakdown(
    numRooms: 1, nights: 1, effectiveAdults: 2, effectiveChildren: 0,
    extraAdultBeds: 0, seatCount: 0, billableSeats: 0, freeChildSeats: 0,
    nightly: [], childAssessments: [], roomSubtotal: 1000000, extraAdultSubtotal: 0,
    childSurchargeTotal: 0, ticketSubtotal: 0, childTicketSubtotal: 0,
    subtotal: 1000000, discount: 0, total: 1000000, costTotal: 700000,
    requiresQuote: false, roomsExpanded: false, messages: []
);

$full = $bd->toArray();
$assert('toArray (internal snapshot) still keeps cost_total', array_key_exists('cost_total', $full) && $full['cost_total'] === 700000);

$pub = null;
try { $pub = $bd->toPublicArray(); } catch (\Throwable $e) { $pub = null; }
$assert('toPublicArray() exists and returns array', is_array($pub));
$assert('toPublicArray omits cost_total', is_array($pub) && !array_key_exists('cost_total', $pub));
$assert('toPublicArray keeps subtotal/total/discount', is_array($pub) && ($pub['subtotal'] ?? null) === 1000000 && ($pub['total'] ?? null) === 1000000 && ($pub['discount'] ?? null) === 0);
