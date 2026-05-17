<?php
declare(strict_types=1);

/**
 * Phase 4 E2E scenarios — Order + Coupon + Cancel.
 * Mirror pattern của inc/tests/cases.php — assert via PHP, in PASS/FAIL.
 *
 * Phụ thuộc seed data + Phase 3 PriceCalculator.
 */

if (!isset($GLOBALS['wpdb'])) {
    throw new RuntimeException('order-e2e must run inside WP context');
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

$findRoom = function (string $name) use ($wpdb): int {
    $id = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT id FROM {$wpdb->prefix}vie_room WHERE name = %s LIMIT 1", $name)
    );
    if ($id === 0) {
        throw new RuntimeException("Seed room '{$name}' not found");
    }
    return $id;
};

$premierRoom = $findRoom('Deluxe Sea View');
$familyRoom  = $findRoom('Family Suite');
$today       = (new DateTimeImmutable('today', wp_timezone()))->format('Y-m-d');
$plus7       = (new DateTimeImmutable('today +7 days', wp_timezone()))->format('Y-m-d');
$plus9       = (new DateTimeImmutable('today +9 days', wp_timezone()))->format('Y-m-d');
$plus1       = (new DateTimeImmutable('today +1 day', wp_timezone()))->format('Y-m-d');
$plus2       = (new DateTimeImmutable('today +2 days', wp_timezone()))->format('Y-m-d');

// Reset stock cho tests idempotent
$wpdb->query("UPDATE {$wpdb->prefix}vie_room_price SET stock = 5 WHERE stock < 5");

$orderSvc      = \Vie\Container::get(\Vie\Service\Order\OrderService::class);
$orderRepo     = \Vie\Container::get(\Vie\Repository\OrderRepository::class);
$itemRepo      = \Vie\Container::get(\Vie\Repository\OrderItemRepository::class);
$customerRepo  = \Vie\Container::get(\Vie\Repository\CustomerRepository::class);
$paymentRepo   = \Vie\Container::get(\Vie\Repository\PaymentLogRepository::class);
$couponSvc     = \Vie\Container::get(\Vie\Service\Coupon\CouponService::class);
$couponRepo    = \Vie\Container::get(\Vie\Repository\CouponRepository::class);

// --- Scenario 1: Create order without coupon ---
echo "Scenario 1: Create order room-only without coupon\n";
$req1 = \Vie\DTO\OrderRequest::fromArray([
    'customer'     => ['phone' => '0911111001', 'name' => 'E2E User 1', 'email' => 'e2e1@test.local'],
    'source'       => 'website',
    'items'        => [
        ['room_id' => $premierRoom, 'booking_type' => 'room', 'checkin' => $plus7, 'checkout' => $plus9,
         'adults' => 2, 'child_ages' => [], 'user_rooms' => 0],
    ],
], 'e2e-1-' . uniqid(), '127.0.0.1', 'phpunit');
$order1 = $orderSvc->create($req1);
$assert('order created', isset($order1['id']) && (int) $order1['id'] > 0);
$assert('code format VIEymdNNNN', (bool) preg_match('/^VIE\d{6}\d{4}$/', (string) ($order1['code'] ?? '')));
$assert('status=pending', ($order1['status'] ?? '') === 'pending');
$assert('discount=0', (int) ($order1['discount'] ?? -1) === 0);
$assert('total>0', (int) ($order1['total'] ?? 0) > 0);
$assert('items count=1', is_array($order1['items'] ?? null) && count($order1['items']) === 1);
$snap1 = $order1['items'][0]['pricing_snapshot'] ?? null;
$assert('pricing_snapshot is array', is_array($snap1));
$assert('snapshot.num_rooms=1', is_array($snap1) && ($snap1['num_rooms'] ?? 0) === 1);

// --- Scenario 2: Create order WITH coupon 10% ---
echo "\nScenario 2: Create order with coupon SUMMER10\n";
$existingCoupon = $couponRepo->findByCode('E2E_SUMMER10');
if ($existingCoupon === null) {
    $couponRepo->create([
        'code'       => 'E2E_SUMMER10',
        'type'       => 'percentage',
        'value'      => 10,
        'is_active'  => true,
        'valid_from' => '2026-01-01 00:00:00',
        'valid_to'   => '2026-12-31 23:59:59',
    ]);
}
$couponBefore = $couponRepo->findByCode('E2E_SUMMER10');
$req2 = \Vie\DTO\OrderRequest::fromArray([
    'customer'    => ['phone' => '0911111002', 'name' => 'E2E User 2', 'email' => 'e2e2@test.local'],
    'source'      => 'website',
    'items'       => [
        ['room_id' => $premierRoom, 'booking_type' => 'combo', 'checkin' => $plus7, 'checkout' => $plus9,
         'adults' => 2, 'child_ages' => [5], 'user_rooms' => 0],
    ],
    'coupon_code' => 'E2E_SUMMER10',
], 'e2e-2-' . uniqid(), '127.0.0.1', 'phpunit');
$order2 = $orderSvc->create($req2);
$assert('coupon applied — discount>0', (int) ($order2['discount'] ?? 0) > 0);
$assert('coupon_code stored', ($order2['coupon_code'] ?? '') === 'E2E_SUMMER10');
$assert('total = subtotal - discount', (int) $order2['total'] === (int) $order2['subtotal'] - (int) $order2['discount']);
$couponAfter = $couponRepo->findByCode('E2E_SUMMER10');
$assert('coupon.used_count incremented', (int) $couponAfter['used_count'] > (int) ($couponBefore['used_count'] ?? 0));

global $wpdb;
$usageCount = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}vie_coupon_usage WHERE order_id = %d",
    (int) $order2['id']
));
$assert('coupon_usage row recorded', $usageCount === 1);

// --- Scenario 3: Idempotency ---
echo "\nScenario 3: Idempotency check\n";
$idemKey = 'e2e-idem-' . uniqid();
$reqA = \Vie\DTO\OrderRequest::fromArray([
    'customer' => ['phone' => '0911111003', 'name' => 'E2E Idem'],
    'source'   => 'website',
    'items'    => [
        ['room_id' => $premierRoom, 'booking_type' => 'room', 'checkin' => $plus7, 'checkout' => $plus9,
         'adults' => 1, 'user_rooms' => 0],
    ],
], $idemKey, '127.0.0.1', 'phpunit');
$orderA = $orderSvc->create($reqA);
$orderB = $orderSvc->create($reqA);  // same key
$assert('same idempotency key returns same order', (int) $orderA['id'] === (int) $orderB['id']);

// --- Scenario 4: Stock conflict ---
echo "\nScenario 4: Stock conflict (set stock=0 → expect StockUnavailableException)\n";
$rowId = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}vie_room_price WHERE room_id = %d AND date = %s LIMIT 1",
    $familyRoom,
    $plus7
));
$origStock = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT stock FROM {$wpdb->prefix}vie_room_price WHERE id = %d",
    $rowId
));
$wpdb->update($wpdb->prefix . 'vie_room_price', ['stock' => 0], ['id' => $rowId]);

$caught = false;
$unavailable = [];
try {
    $reqStock = \Vie\DTO\OrderRequest::fromArray([
        'customer' => ['phone' => '0911111004', 'name' => 'E2E Stock'],
        'source'   => 'website',
        'items'    => [
            ['room_id' => $familyRoom, 'booking_type' => 'room', 'checkin' => $plus7, 'checkout' => $plus9,
             'adults' => 2, 'user_rooms' => 0],
        ],
    ], 'e2e-stock-' . uniqid(), '127.0.0.1', 'phpunit');
    $orderSvc->create($reqStock);
} catch (\Vie\Service\Order\StockUnavailableException $e) {
    $caught = true;
    $unavailable = $e->unavailableDates;
}
$assert('StockUnavailableException thrown', $caught);
$assert('unavailable_dates contains target date', in_array($plus7, $unavailable, true));

// Restore stock
$wpdb->update($wpdb->prefix . 'vie_room_price', ['stock' => $origStock], ['id' => $rowId]);

// --- Scenario 5: Cancel with full refund (>72h) ---
echo "\nScenario 5: Cancel >72h before checkin — refund 100%\n";
// Mark order1 as paid
$orderRepo->update((int) $order1['id'], [
    'paid_amount'    => (int) $order1['total'],
    'payment_status' => 'paid',
]);
$paymentRepo->create([
    'order_id' => (int) $order1['id'],
    'type'     => 'payment',
    'amount'   => (int) $order1['total'],
    'method'   => 'bank_transfer',
    'paid_at'  => current_time('mysql'),
]);

$stockBefore = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT stock FROM {$wpdb->prefix}vie_room_price WHERE room_id = %d AND date = %s",
    $premierRoom,
    $plus7
));
$cancelled1 = $orderSvc->cancel((int) $order1['id'], 'E2E test full refund', 0, (int) $order1['total']);
$assert('order status=cancelled', $cancelled1['status'] === 'cancelled');
$assert('refund_amount = total', (int) $cancelled1['refund']['refund_amount'] === (int) $order1['total']);

$stockAfter = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT stock FROM {$wpdb->prefix}vie_room_price WHERE room_id = %d AND date = %s",
    $premierRoom,
    $plus7
));
$assert('stock restored (+1)', $stockAfter === $stockBefore + 1);

$refundCount = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}vie_payment_log WHERE order_id = %d AND type = 'refund'",
    (int) $order1['id']
));
$assert('PaymentLog refund row created', $refundCount === 1);

// --- Scenario 6: Cancel with partial refund (<24h) ---
echo "\nScenario 6: Cancel <24h before checkin — 100% penalty, no refund\n";
$req6 = \Vie\DTO\OrderRequest::fromArray([
    'customer' => ['phone' => '0911111006', 'name' => 'E2E Close'],
    'source'   => 'website',
    'items'    => [
        ['room_id' => $premierRoom, 'booking_type' => 'room', 'checkin' => $plus1, 'checkout' => $plus2,
         'adults' => 2, 'user_rooms' => 0],
    ],
], 'e2e-6-' . uniqid(), '127.0.0.1', 'phpunit');
$order6 = $orderSvc->create($req6);
$orderRepo->update((int) $order6['id'], [
    'paid_amount'    => (int) $order6['total'],
    'payment_status' => 'paid',
]);
$cancelled6 = $orderSvc->cancel((int) $order6['id'], 'E2E close-checkin', 0, 0);
$assert('refund_amount=0 (manual, none entered)', (int) $cancelled6['refund']['refund_amount'] === 0);
$assert('paid_amount preserved', (int) $cancelled6['refund']['paid_amount'] === (int) $order6['total']);

// --- Scenario 7: Coupon validate endpoint logic ---
echo "\nScenario 7: CouponService::validate behavior\n";
$validRes  = $couponSvc->validate('E2E_SUMMER10', 3000000, null, null, null, null);
$assert('valid coupon returns valid=true', ($validRes['valid'] ?? false) === true);
$assert('valid coupon discount=300000', (int) ($validRes['discount'] ?? 0) === 300000);

$missRes = $couponSvc->validate('NONEXISTENT_XYZ', 1000000, null, null, null, null);
$assert('non-existent → valid=false', ($missRes['valid'] ?? true) === false);

// Create expired coupon (idempotent)
if ($couponRepo->findByCode('E2E_EXPIRED') === null) {
    $couponRepo->create([
        'code'       => 'E2E_EXPIRED',
        'type'       => 'fixed',
        'value'      => 50000,
        'is_active'  => true,
        'valid_from' => '2020-01-01 00:00:00',
        'valid_to'   => '2020-12-31 23:59:59',
    ]);
}
$expiredRes = $couponSvc->validate('E2E_EXPIRED', 1000000, null, null, null, null);
$assert('expired coupon → valid=false', ($expiredRes['valid'] ?? true) === false);

// --- Scenario 8: Public lookup safe view ---
echo "\nScenario 8: Public order lookup (safe view)\n";
$order2Reloaded = $orderRepo->findByCode((string) $order2['code']);
$assert('findByCode works', $order2Reloaded !== null);
$assert('phone normalized in stored order', $order2Reloaded['customer_phone'] === '0911111002');

echo "\n";
