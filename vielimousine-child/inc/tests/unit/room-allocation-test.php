<?php
declare(strict_types=1);

// Pure unit test — no WordPress needed.
require __DIR__ . '/../../src/Service/Pricing/GuestComposition.php';
require __DIR__ . '/../../src/Service/Pricing/RoomAllocation.php';

use Vie\Service\Pricing\GuestComposition;
use Vie\Service\Pricing\RoomAllocation;

$pass = 0; $fail = 0;
$assert = function (string $name, bool $cond, string $detail = '') use (&$pass, &$fail): void {
    if ($cond) { echo "  ✓ {$name}\n"; $pass++; }
    else { echo "  ✗ {$name}" . ($detail ? " — {$detail}" : '') . "\n"; $fail++; }
};

// Premier-style room: included 2, max_adults 3, max_children 2.
$premier = ['included_adults' => 2, 'max_adults' => 3, 'max_children' => 2];

// A) 4 adults, user asks 1 room → auto-expand to 2, priced (not quote).
$a = new RoomAllocation($premier, new GuestComposition(4, [], 12), 1);
$assert('A: numRooms auto-expands 1→2', $a->numRooms() === 2, 'got ' . $a->numRooms());
$assert('A: roomsExpanded flag true', $a->roomsExpanded() === true);
$assert('A: not requiresQuote', $a->requiresQuote() === false);

// B) fits in requested rooms → no expansion.
$b = new RoomAllocation($premier, new GuestComposition(2, [5], 12), 1);
$assert('B: numRooms stays 1', $b->numRooms() === 1, 'got ' . $b->numRooms());
$assert('B: not expanded', $b->roomsExpanded() === false);

// C) room rejects children (max_children 0) + has children → requiresQuote.
$noKids = ['included_adults' => 2, 'max_adults' => 2, 'max_children' => 0];
$c = new RoomAllocation($noKids, new GuestComposition(2, [5], 12), 1);
$assert('C: requiresQuote when no child capacity', $c->requiresQuote() === true);

// D) user asks 3 rooms for 2 adults → honor minimum 3.
$d = new RoomAllocation($premier, new GuestComposition(2, [], 12), 3);
$assert('D: userRooms is a minimum (3)', $d->numRooms() === 3, 'got ' . $d->numRooms());
$assert('D: not flagged expanded (user chose 3)', $d->roomsExpanded() === false);

echo "\n--- RoomAllocation: {$pass} passed, {$fail} failed ---\n";
exit($fail === 0 ? 0 : 1);
