<?php
declare(strict_types=1);

/**
 * Phase 5 E2E — PaymentLedger + SePay webhook scenarios.
 * Phụ thuộc seed data + Phase 4 OrderService.
 */

if (!isset($GLOBALS['wpdb'])) {
    throw new RuntimeException('payment-e2e must run inside WP context');
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
    return (int) $wpdb->get_var(
        $wpdb->prepare("SELECT id FROM {$wpdb->prefix}vie_room WHERE name = %s LIMIT 1", $name)
    );
};

// Reset stock cho idempotency
$wpdb->query("UPDATE {$wpdb->prefix}vie_room_price SET stock = 5 WHERE stock < 5");

// Setup SePay sandbox
update_option('vie_sepay_enabled', 1, false);
update_option('vie_sepay_merchant_id', 'TEST_MERCHANT', false);
update_option('vie_sepay_webhook_secret', 'phase5_e2e_webhook_secret', false);
$invoiceSettings = get_option('vie_invoice_settings', []);
$invoiceSettings = is_array($invoiceSettings) ? $invoiceSettings : (json_decode((string) $invoiceSettings, true) ?: []);
$invoiceSettings['bank_name'] = 'Test Bank';
$invoiceSettings['bank_code'] = 'VCB';
$invoiceSettings['bank_account'] = '0123456789';
$invoiceSettings['bank_holder'] = 'TEST HOLDER';
update_option('vie_invoice_settings', wp_json_encode($invoiceSettings), false);
update_option('vie_sepay_environment', 'sandbox', false);
update_option('vie_sepay_auto_confirm_on_paid', 1, false);

$premierRoom = $findRoom('Deluxe Sea View');
$plus10      = (new DateTimeImmutable('today +10 days', wp_timezone()))->format('Y-m-d');
$plus12      = (new DateTimeImmutable('today +12 days', wp_timezone()))->format('Y-m-d');

$orderSvc    = \Vie\Container::get(\Vie\Service\Order\OrderService::class);
$orderRepo   = \Vie\Container::get(\Vie\Repository\OrderRepository::class);
$paymentRepo = \Vie\Container::get(\Vie\Repository\PaymentLogRepository::class);
$paymentSvc  = \Vie\Container::get(\Vie\Service\Payment\PaymentService::class);
$ledger      = \Vie\Container::get(\Vie\Service\Payment\PaymentLedger::class);
$webhook     = \Vie\Container::get(\Vie\Service\Payment\SepayWebhook::class);

// Helper: tạo 1 order tươi
$makeOrder = function (string $idemKey, string $checkin, string $checkout, int $adults = 2) use ($orderSvc, $premierRoom): array {
    $req = \Vie\DTO\OrderRequest::fromArray([
        'customer' => ['phone' => '0908' . substr(uniqid(), -7), 'name' => 'Phase5 E2E'],
        'source'   => 'website',
        'items'    => [
            ['room_id' => $premierRoom, 'booking_type' => 'room', 'checkin' => $checkin, 'checkout' => $checkout,
             'adults' => $adults, 'user_rooms' => 0],
        ],
    ], $idemKey, '127.0.0.1', 'phpunit');
    return $orderSvc->create($req);
};

// --- Scenario 1: Manual deposit (partial) ---
echo "Scenario 1: Manual deposit — partial payment\n";
$order1 = $makeOrder('ph5-e2e-1-' . uniqid(), $plus10, $plus12);
$depositAmt = (int) ($order1['total'] * 0.5);
$req = new \Vie\DTO\PaymentRequest(
    orderId: (int) $order1['id'], type: 'deposit', amount: $depositAmt,
    method: 'bank_transfer', gateway: null, transactionId: null,
    note: 'E2E deposit', paidAt: current_time('mysql'), createdBy: null, rawPayload: null,
);
$res1 = $paymentSvc->manualEntry($req);
$assert('deposit recorded — paid_amount tăng', $res1['paid_amount'] === $depositAmt);
$assert('payment_status = partial', $res1['payment_status'] === 'partial');
$assert('auto_confirmed = false', $res1['auto_confirmed'] === false);
$assert('order status vẫn = pending', $res1['order']['status'] === 'pending');

// --- Scenario 2: Full payment → auto-confirm ---
echo "\nScenario 2: Full payment → auto-confirm\n";
$remaining = (int) $order1['total'] - $depositAmt;
$req2 = new \Vie\DTO\PaymentRequest(
    orderId: (int) $order1['id'], type: 'payment', amount: $remaining,
    method: 'manual', gateway: null, transactionId: null,
    note: 'E2E full pay', paidAt: current_time('mysql'), createdBy: null, rawPayload: null,
);
$res2 = $paymentSvc->manualEntry($req2);
$assert('paid_amount = total', $res2['paid_amount'] === (int) $order1['total']);
$assert('payment_status = paid', $res2['payment_status'] === 'paid');
$assert('auto_confirmed = true', $res2['auto_confirmed'] === true);
$assert('order status = confirmed', $res2['order']['status'] === 'confirmed');
$assert('confirmed_at được set', !empty($res2['order']['confirmed_at']));

// --- Scenario 3: Refund ---
echo "\nScenario 3: Refund — payment_status → partial\n";
$refundAmt = (int) ($order1['total'] * 0.3);
$req3 = new \Vie\DTO\PaymentRequest(
    orderId: (int) $order1['id'], type: 'refund', amount: $refundAmt,
    method: 'bank_transfer', gateway: null, transactionId: null,
    note: 'E2E partial refund', paidAt: current_time('mysql'), createdBy: null, rawPayload: null,
);
$res3 = $paymentSvc->manualEntry($req3);
$assert('refund — paid_amount giảm', $res3['paid_amount'] === (int) $order1['total'] - $refundAmt);
$assert('payment_status quay về partial', $res3['payment_status'] === 'partial');

// --- Scenario 4: Void — paid_amount = 0 ---
echo "\nScenario 4: Void total còn lại\n";
$voidAmt = $res3['paid_amount'];
$req4 = new \Vie\DTO\PaymentRequest(
    orderId: (int) $order1['id'], type: 'void', amount: $voidAmt,
    method: 'manual', gateway: null, transactionId: null,
    note: 'E2E void', paidAt: current_time('mysql'), createdBy: null, rawPayload: null,
);
$res4 = $paymentSvc->manualEntry($req4);
$assert('void → paid_amount = 0', $res4['paid_amount'] === 0);
$assert('payment_status = pending', $res4['payment_status'] === 'pending');

// --- Scenario 5: Idempotency (manual SePay txn_id) ---
echo "\nScenario 5: Idempotency — duplicate gateway+transaction_id\n";
$order5 = $makeOrder('ph5-e2e-5-' . uniqid(), $plus10, $plus12);
$txId = 'SPY-E2E-' . uniqid();
$reqA = new \Vie\DTO\PaymentRequest(
    orderId: (int) $order5['id'], type: 'payment', amount: 500000,
    method: 'sepay', gateway: 'sepay', transactionId: $txId,
    note: 'E2E 1st', paidAt: current_time('mysql'), createdBy: null, rawPayload: null,
);
$paymentSvc->manualEntry($reqA);
$caught = false;
try {
    $paymentSvc->manualEntry($reqA);  // duplicate
} catch (\Vie\Service\Payment\IdempotencyConflictException $e) {
    $caught = true;
}
$assert('duplicate transaction_id → IdempotencyConflictException', $caught);

// --- Scenario 6: SePay webhook — valid HMAC + auto-confirm ---
echo "\nScenario 6: SePay webhook — valid HMAC + auto-confirm\n";
$order6 = $makeOrder('ph5-e2e-6-' . uniqid(), $plus10, $plus12);
$webhookPayload = [
    'id' => 'SPY-WH-' . uniqid(),
    'gateway' => 'VCB',
    'transactionDate' => gmdate('c'),
    'accountNumber' => '0123456789',
    'code' => $order6['code'],
    'content' => $order6['code'],
    'transferType' => 'in',
    'description' => 'test',
    'transferAmount' => (int) $order6['total'],
    'accumulated' => 0,
    'referenceCode' => 'REF-' . uniqid(),
];
$raw = wp_json_encode($webhookPayload);
$ts = (string) time();
$sig = 'sha256=' . hash_hmac('sha256', $ts . '.' . $raw, 'phase5_e2e_webhook_secret');
$result = $webhook->handleRaw($raw, $sig, $ts, '127.0.0.1');
$assert('webhook accepted', ($result['accepted'] ?? false) === true);
$updated6 = $orderRepo->find((int) $order6['id']);
$assert('order paid_amount = total', (int) $updated6['paid_amount'] === (int) $order6['total']);
$assert('order payment_status = paid', $updated6['payment_status'] === 'paid');
$assert('order auto-confirmed', $updated6['status'] === 'confirmed');

// --- Scenario 7: SePay webhook — invalid HMAC ---
echo "\nScenario 7: SePay webhook — invalid HMAC\n";
$order7 = $makeOrder('ph5-e2e-7-' . uniqid(), $plus10, $plus12);
$payload7 = $webhookPayload;
$payload7['id'] = 'SPY-BAD-' . uniqid();
$payload7['code'] = $order7['code'];
$payload7['transferAmount'] = (int) $order7['total'];
$raw7 = wp_json_encode($payload7);
$result7 = $webhook->handleRaw($raw7, 'sha256=' . str_repeat('0', 64), (string) time(), '127.0.0.1');
$assert('invalid sig → accepted=false', ($result7['accepted'] ?? true) === false);
$updated7 = $orderRepo->find((int) $order7['id']);
$assert('order paid_amount vẫn = 0', (int) $updated7['paid_amount'] === 0);

// --- Scenario 8: SePay webhook — duplicate (replay) ---
echo "\nScenario 8: SePay webhook — duplicate (replay protection)\n";
$order8 = $makeOrder('ph5-e2e-8-' . uniqid(), $plus10, $plus12);
$payload8 = $webhookPayload;
$payload8['id'] = 'SPY-DUP-' . uniqid();
$payload8['code'] = $order8['code'];
$payload8['transferAmount'] = (int) $order8['total'];
$raw8 = wp_json_encode($payload8);
$ts8 = (string) time();
$sig8 = 'sha256=' . hash_hmac('sha256', $ts8 . '.' . $raw8, 'phase5_e2e_webhook_secret');
$r1 = $webhook->handleRaw($raw8, $sig8, $ts8, '127.0.0.1');
$r2 = $webhook->handleRaw($raw8, $sig8, $ts8, '127.0.0.1');
$assert('1st webhook accepted', ($r1['accepted'] ?? false) === true);
$assert('replay accepted=true', ($r2['accepted'] ?? false) === true);
$assert('replay reason = duplicate_ignored', ($r2['reason'] ?? '') === 'duplicate_ignored');
$count8 = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}vie_payment_log WHERE order_id = %d AND gateway = %s",
    (int) $order8['id'], 'sepay'
));
$assert('chỉ 1 sepay payment row sau replay', $count8 === 1);

// --- Scenario 9: Webhook unknown order ---
echo "\nScenario 9: SePay webhook — unknown order code\n";
$payload9 = $webhookPayload;
$payload9['id'] = 'SPY-UNKNOWN-' . uniqid();
$payload9['code'] = 'VIENONEXISTENT0000';
$payload9['transferAmount'] = 1000000;
$raw9 = wp_json_encode($payload9);
$ts9 = (string) time();
$sig9 = 'sha256=' . hash_hmac('sha256', $ts9 . '.' . $raw9, 'phase5_e2e_webhook_secret');
$r9 = $webhook->handleRaw($raw9, $sig9, $ts9, '127.0.0.1');
$assert('unknown order → acknowledged for manual review', ($r9['accepted'] ?? false) === true);
$assert('reason = unknown_order', ($r9['reason'] ?? '') === 'unknown_order');

echo "\n";
