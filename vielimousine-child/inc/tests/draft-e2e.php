<?php
declare(strict_types=1);

/**
 * Draft order E2E — lưu nháp KHÔNG side-effect, promote (create+delete) hoạt động,
 * nháp bị loại khỏi báo cáo/booking_count. Mirror pattern của order-e2e.php.
 */

if (!isset($GLOBALS['wpdb'])) {
    throw new RuntimeException('draft-e2e must run inside WP context');
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
$plus7 = (new DateTimeImmutable('today +7 days', wp_timezone()))->format('Y-m-d');
$plus9 = (new DateTimeImmutable('today +9 days', wp_timezone()))->format('Y-m-d');
$wpdb->query("UPDATE {$wpdb->prefix}vie_room_price SET stock = 5 WHERE stock < 5");

$draftSvc  = \Vie\Container::get(\Vie\Service\Order\OrderDraftService::class);
$orderSvc  = \Vie\Container::get(\Vie\Service\Order\OrderService::class);
$orderRepo = \Vie\Container::get(\Vie\Repository\OrderRepository::class);

// --- Scenario 1: Lưu nháp dở dang (chưa có phòng/ngày) ---
echo "Scenario 1: Save partial draft — no side effects\n";
$draft = $draftSvc->save([
    'customer_phone' => '0922000001',
    'customer_name'  => 'Draft User 1',
    'source'         => 'admin',
    'draft_payload'  => ['wizard' => ['customer' => ['phone' => '0922000001', 'name' => 'Draft User 1']], 'stepIndex' => 0],
], 0);
$draftId = (int) $draft['id'];
$assert('draft created', $draftId > 0);
$assert('status=draft', ($draft['status'] ?? '') === 'draft');
$assert('code is NULL (no code generated)', ($draft['code'] ?? null) === null);
$itemCount = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}vie_order_item WHERE order_id = %d", $draftId
));
$assert('no order_items created', $itemCount === 0);
$reloaded = $orderRepo->find($draftId);
$assert('draft_payload round-trips', is_array($reloaded['draft_payload'] ?? null)
    && ($reloaded['draft_payload']['stepIndex'] ?? null) === 0);

// --- Scenario 2: Update nháp ---
echo "\nScenario 2: Update draft\n";
$draftSvc->update($draftId, [
    'customer_phone' => '0922000001',
    'customer_name'  => 'Draft User 1 EDITED',
    'source'         => 'admin',
    'draft_payload'  => ['wizard' => ['customer' => ['name' => 'Draft User 1 EDITED']], 'stepIndex' => 1],
], 0);
$after = $orderRepo->find($draftId);
$assert('name updated', ($after['customer_name'] ?? '') === 'Draft User 1 EDITED');
$assert('payload stepIndex updated', ($after['draft_payload']['stepIndex'] ?? null) === 1);
$assert('still status=draft', ($after['status'] ?? '') === 'draft');

// --- Scenario 3: Promote (frontend orchestration = create + delete draft) ---
echo "\nScenario 3: Promote draft → real order, then delete draft\n";
$promoteReq = \Vie\DTO\OrderRequest::fromArray([
    'customer' => ['phone' => '0922000001', 'name' => 'Draft User 1 EDITED'],
    'source'   => 'admin',
    'items'    => [
        ['room_id' => $premierRoom, 'booking_type' => 'room', 'checkin' => $plus7, 'checkout' => $plus9,
         'adults' => 2, 'child_ages' => [], 'user_rooms' => 0],
    ],
], 'draft-promote-' . uniqid(), '127.0.0.1', 'phpunit');
$realOrder = $orderSvc->create($promoteReq);
// OrderCodeGenerator format: VIE{YYMMDD}{NNNN}{XXXX} — NNNN sequence + XXXX 4-hex anti-enumeration suffix.
$assert('real order created with code', (bool) preg_match('/^VIE\d{6}\d{4}[0-9A-F]{4}$/', (string) ($realOrder['code'] ?? '')));
$assert('real order status=pending', ($realOrder['status'] ?? '') === 'pending');
$draftSvc->delete($draftId);
$assert('draft deleted after promote', $orderRepo->find($draftId) === null);

// --- Scenario 4: Delete draft trực tiếp ---
echo "\nScenario 4: Delete draft\n";
$d2 = $draftSvc->save(['customer_phone' => '0922000002', 'customer_name' => 'Draft 2', 'source' => 'admin'], 0);
$d2Id = (int) $d2['id'];
$draftSvc->delete($d2Id);
$assert('draft 2 deleted', $orderRepo->find($d2Id) === null);

// --- Scenario 5: Không xóa được đơn thật qua draft delete ---
echo "\nScenario 5: Cannot delete a real order via draft delete\n";
$caught = false;
try {
    $draftSvc->delete((int) $realOrder['id']);
} catch (\Vie\Service\Order\OrderNotFoundException $e) {
    $caught = true;
}
$assert('draft delete rejects non-draft order', $caught);

// --- Scenario 6: Nháp không lọt vào báo cáo ByHotel ---
echo "\nScenario 6: Draft excluded from ByHotel report\n";
$dRep = $draftSvc->save([
    'customer_phone' => '0922000003', 'customer_name' => 'Draft Rep', 'source' => 'admin',
    'checkin' => $plus7, 'checkout' => $plus9, 'subtotal' => 9999999, 'total' => 9999999,
], 0);
$itemTbl  = $wpdb->prefix . 'vie_order_item';
$orderTbl = $wpdb->prefix . 'vie_order';
$leak = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$orderTbl} o INNER JOIN {$itemTbl} i ON i.order_id = o.id
      WHERE o.id = %d AND o.status NOT IN ('cancelled','draft')",
    (int) $dRep['id']
));
$assert('draft contributes 0 rows to item-joined report', $leak === 0);
$draftSvc->delete((int) $dRep['id']);

echo "\n--- Draft E2E done ---\n";
