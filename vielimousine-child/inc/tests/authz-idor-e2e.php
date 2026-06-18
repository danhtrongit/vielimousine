<?php
declare(strict_types=1);
/**
 * Access-control / IDOR e2e for order-items + invoice (H4, H5).
 * A vie_sales user must NOT read order-items or invoices belonging to another sales user's order.
 */
if (!isset($GLOBALS['wpdb'])) { throw new RuntimeException('Run inside WP context'); }

use Vie\Container;
use Vie\Repository\OrderRepository;
use Vie\Repository\OrderItemRepository;
use Vie\Service\Auth\RoleInstaller;
use Vie\Http\Controllers\OrderItemController;
use Vie\Http\Controllers\InvoiceController;

$pass = $pass ?? 0;
$fail = $fail ?? 0;
$assert = function (string $name, bool $cond, string $detail = '') use (&$pass, &$fail): void {
    if ($cond) { echo "  ✓ {$name}\n"; $pass++; }
    else { echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; $fail++; }
};

RoleInstaller::install();

$mkUser = static function (string $login, string $role): int {
    $id = username_exists($login);
    if (!$id) { $id = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => $role]); }
    return (int) $id;
};
$salesA = $mkUser('idor_sales_a', 'vie_sales');
$salesB = $mkUser('idor_sales_b', 'vie_sales');
$admin  = $mkUser('idor_admin', 'administrator');

$orderRepo = Container::get(OrderRepository::class);
$itemRepo  = Container::get(OrderItemRepository::class);

$order = $orderRepo->create([
    'code' => 'IDOR' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
    'customer_phone' => '0900000000', 'customer_name' => 'IDOR Test', 'source' => 'admin',
    'sales_user_id' => $salesA, 'checkin' => '2026-01-01', 'checkout' => '2026-01-02', 'nights' => 1,
    'adults' => 2, 'children' => 0, 'subtotal' => 1000, 'discount' => 0, 'total' => 1000,
    'currency' => 'VND', 'status' => 'pending', 'payment_status' => 'unpaid',
]);
$orderId = (int) $order['id'];
$item = $itemRepo->create([
    'order_id' => $orderId, 'hotel_id' => 0, 'name' => 'Room', 'booking_type' => 'room',
    'quantity' => 1, 'checkin' => '2026-01-01', 'checkout' => '2026-01-02', 'nights' => 1,
    'adults' => 2, 'children' => 0, 'line_total' => 1000, 'cost_total' => 700,
    'partner_name' => 'SECRET PARTNER',
]);
$itemId = (int) $item['id'];
$assert('setup: order + item created', $orderId > 0 && $itemId > 0);

$showReq = static function (int $id): \WP_REST_Request {
    $r = new \WP_REST_Request('GET', '/order-items/' . $id);
    $r->set_param('id', $id);
    return $r;
};
$indexReq = static function (?int $orderId): \WP_REST_Request {
    $r = new \WP_REST_Request('GET', '/order-items');
    if ($orderId !== null) { $r->set_param('order_id', $orderId); }
    return $r;
};
$invReq = static function (int $orderId): \WP_REST_Request {
    $r = new \WP_REST_Request('GET', '/orders/' . $orderId . '/invoice/data');
    $r->set_param('id', $orderId);
    return $r;
};

echo "H5: OrderItem show ownership\n";
wp_set_current_user($salesB);
$assert('salesB CANNOT show another seller item (403)', OrderItemController::show($showReq($itemId))->get_status() === 403);
wp_set_current_user($salesA);
$assert('owner salesA CAN show own item (200)', OrderItemController::show($showReq($itemId))->get_status() === 200);
wp_set_current_user($admin);
$assert('admin CAN show any item (200)', OrderItemController::show($showReq($itemId))->get_status() === 200);

echo "H5: OrderItem index scoping\n";
wp_set_current_user($salesB);
$assert('salesB index without order_id forbidden (403)', OrderItemController::index($indexReq(null))->get_status() === 403);
$assert('salesB index of foreign order forbidden (403)', OrderItemController::index($indexReq($orderId))->get_status() === 403);
wp_set_current_user($salesA);
$assert('owner index of own order ok (200)', OrderItemController::index($indexReq($orderId))->get_status() === 200);
wp_set_current_user($admin);
$assert('admin index ok (200)', OrderItemController::index($indexReq(null))->get_status() === 200);

echo "H4: Invoice data ownership (checked before settings/config)\n";
wp_set_current_user($salesB);
$assert('salesB CANNOT get invoice data of foreign order (403)', InvoiceController::data($invReq($orderId))->get_status() === 403);
wp_set_current_user($admin);
$assert('admin invoice data NOT forbidden (!=403)', InvoiceController::data($invReq($orderId))->get_status() !== 403);

// cleanup
$itemRepo->delete($itemId);
$orderRepo->delete($orderId);
wp_set_current_user(0);
