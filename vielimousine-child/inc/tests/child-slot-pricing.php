<?php
declare(strict_types=1);

/**
 * Unit tests for child-slot surcharge (child_index_min/max).
 *
 * Scenarios:
 *   A. 2 children same age, slot rules (1,1) vs (2,2) → different fees
 *   B. 3rd child hits unbounded slot (2, NULL)
 *   C. Backward compat: legacy rule (1, NULL) → all payable children match
 */

if (!isset($GLOBALS['wpdb'])) {
    throw new RuntimeException('child-slot-pricing must run inside WP context');
}

$wpdb = $GLOBALS['wpdb'];

$assert = function (string $name, bool $cond, string $detail = '') use (&$pass, &$fail): void {
    if ($cond) {
        echo "  ✓ {$name}\n";
        $pass++;
    } else {
        echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
        $fail++;
    }
};

$surchargeRepo      = \Vie\Container::get(\Vie\Repository\SurchargeRepository::class);
$surchargePriceRepo = \Vie\Container::get(\Vie\Repository\SurchargePriceRepository::class);

$findRoom = function (string $name) use ($wpdb): int {
    return (int) $wpdb->get_var(
        $wpdb->prepare("SELECT id FROM {$wpdb->prefix}vie_room WHERE name = %s LIMIT 1", $name)
    );
};

$roomId = $findRoom('Deluxe Sea View');
if ($roomId === 0) {
    throw new RuntimeException('Seed room "Deluxe Sea View" not found');
}

// Clean surcharge rules for this room before each run (idempotency)
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}vie_surcharge WHERE room_id = %d AND label LIKE %s",
        $roomId,
        'CHILDSLOT_TEST_%'
    )
);

// --- Scenario A: 2 children same age, slot 1 vs slot 2 ---
echo "Scenario A: child #1 → 500K, child #2 → 600K (same age 3)\n";

$surchargeRepo->create([
    'room_id'         => $roomId,
    'guest_type'      => 'child',
    'label'           => 'CHILDSLOT_TEST_slot1',
    'age_from'        => 0,
    'age_to'          => 12,
    'child_index_min' => 1,
    'child_index_max' => 1,
    'amount'          => 500000,
    'is_free'         => 0,
    'is_active'       => 1,
    'sort_order'      => 1,
]);
$surchargeRepo->create([
    'room_id'         => $roomId,
    'guest_type'      => 'child',
    'label'           => 'CHILDSLOT_TEST_slot2',
    'age_from'        => 0,
    'age_to'          => 12,
    'child_index_min' => 2,
    'child_index_max' => 2,
    'amount'          => 600000,
    'is_free'         => 0,
    'is_active'       => 1,
    'sort_order'      => 2,
]);
$surchargeRepo->create([
    'room_id'         => $roomId,
    'guest_type'      => 'child',
    'label'           => 'CHILDSLOT_TEST_slot3plus',
    'age_from'        => 0,
    'age_to'          => 12,
    'child_index_min' => 3,
    'child_index_max' => null,
    'amount'          => 700000,
    'is_free'         => 0,
    'is_active'       => 1,
    'sort_order'      => 3,
]);

$policyA = new \Vie\Service\Pricing\ChildPolicy([3, 3], [], 0);
$nightsA = ['2099-01-01'];
$calcA = new \Vie\Service\Pricing\SurchargeCalculator(
    roomId:             $roomId,
    nights:             $nightsA,
    assessments:        $policyA->assessments(),
    surchargeRepo:      $surchargeRepo,
    surchargePriceRepo: $surchargePriceRepo,
);
$breakdownA = $calcA->nightlyBreakdown();
$itemsA = $breakdownA['2099-01-01'] ?? [];
$assert('A: 2 surcharge items', count($itemsA) === 2, 'got ' . count($itemsA));
$amounts = array_map(static fn($i) => (int) $i['amount'], $itemsA);
sort($amounts);
$assert('A: amounts [500000, 600000]', $amounts === [500000, 600000], 'got ' . json_encode($amounts));

// --- Scenario B: 3rd child triggers (3, NULL) rule ---
echo "\nScenario B: 3 children age 3 → 500K + 600K + 700K\n";
$policyB = new \Vie\Service\Pricing\ChildPolicy([3, 3, 3], [], 0);
$calcB = new \Vie\Service\Pricing\SurchargeCalculator(
    roomId:             $roomId,
    nights:             ['2099-01-01'],
    assessments:        $policyB->assessments(),
    surchargeRepo:      $surchargeRepo,
    surchargePriceRepo: $surchargePriceRepo,
);
$itemsB = $calcB->nightlyBreakdown()['2099-01-01'] ?? [];
$assert('B: 3 surcharge items', count($itemsB) === 3, 'got ' . count($itemsB));
$amountsB = array_map(static fn($i) => (int) $i['amount'], $itemsB);
sort($amountsB);
$assert('B: amounts [500000, 600000, 700000]', $amountsB === [500000, 600000, 700000], 'got ' . json_encode($amountsB));

// --- Scenario C: backward compat — legacy rule (1, NULL) catches all ---
echo "\nScenario C: legacy single rule (1, NULL) catches both children\n";
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}vie_surcharge WHERE room_id = %d AND label LIKE %s",
        $roomId,
        'CHILDSLOT_TEST_%'
    )
);
$surchargeRepo->create([
    'room_id'         => $roomId,
    'guest_type'      => 'child',
    'label'           => 'CHILDSLOT_TEST_legacy',
    'age_from'        => 0,
    'age_to'          => 12,
    'child_index_min' => 1,
    'child_index_max' => null,
    'amount'          => 400000,
    'is_free'         => 0,
    'is_active'       => 1,
    'sort_order'      => 1,
]);
$policyC = new \Vie\Service\Pricing\ChildPolicy([5, 5], [], 0);
$calcC = new \Vie\Service\Pricing\SurchargeCalculator(
    roomId:             $roomId,
    nights:             ['2099-01-01'],
    assessments:        $policyC->assessments(),
    surchargeRepo:      $surchargeRepo,
    surchargePriceRepo: $surchargePriceRepo,
);
$itemsC = $calcC->nightlyBreakdown()['2099-01-01'] ?? [];
$assert('C: 2 surcharge items', count($itemsC) === 2);
$amountsC = array_map(static fn($i) => (int) $i['amount'], $itemsC);
$assert('C: both 400000', $amountsC === [400000, 400000], 'got ' . json_encode($amountsC));

// Cleanup
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}vie_surcharge WHERE room_id = %d AND label LIKE %s",
        $roomId,
        'CHILDSLOT_TEST_%'
    )
);
