<?php
declare(strict_types=1);

/**
 * Cost/profit visibility — cost_total/profit_total must be stripped for users
 * WITHOUT vie_view_reports (e.g. vie_sales) and preserved for users WITH it.
 * Mirrors the inc/tests pattern: $assert closure + shared $pass/$fail counters.
 * Runnable standalone (focused eval seeds $pass/$fail) or via run.php.
 */

use Vie\Support\CostVisibility;
use Vie\Service\Auth\RoleInstaller;
use Vie\Http\Controllers\OrderController;
use Vie\Http\Controllers\OrderItemController;
use Vie\Http\Controllers\OrderActionController;
use Vie\Http\Controllers\InvoiceController;

if (!isset($GLOBALS['wpdb'])) {
    throw new RuntimeException('cost-visibility-e2e must run inside WP context');
}

$assert = function (string $name, bool $cond, string $detail = '') use (&$pass, &$fail): void {
    if ($cond) {
        echo "  ✓ {$name}\n";
        $pass++;
    } else {
        echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
        $fail++;
    }
};

// Ensure vie_* caps are present on roles (idempotent).
RoleInstaller::install();

// Idempotent test users.
$salesId = username_exists('cost_vis_sales')
    ?: wp_insert_user(['user_login' => 'cost_vis_sales', 'user_pass' => wp_generate_password(), 'role' => 'vie_sales']);
$salesId = (int) $salesId;

$mgrId = username_exists('cost_vis_admin')
    ?: wp_insert_user(['user_login' => 'cost_vis_admin', 'user_pass' => wp_generate_password(), 'role' => 'administrator']);
$mgrId = (int) $mgrId;

$sample = [
    'id'           => 1,
    'total'        => 100,
    'cost_total'   => 60,
    'profit_total' => 40,
    'items'        => [
        ['id' => 10, 'cost_total' => 30, 'profit_total' => 20, 'qty' => 1],
        ['id' => 11, 'cost_total' => 30, 'profit_total' => 20, 'qty' => 1],
    ],
];

echo "Scenario A: user WITHOUT vie_view_reports (vie_sales)\n";
wp_set_current_user($salesId);
$assert('canView() === false for sales', CostVisibility::canView() === false);

$so = CostVisibility::stripOrder($sample);
$assert('order.cost_total removed', !array_key_exists('cost_total', $so));
$assert('order.profit_total removed', !array_key_exists('profit_total', $so));
$assert('order.total preserved', ($so['total'] ?? null) === 100);
$assert('items[0].cost_total removed', !array_key_exists('cost_total', $so['items'][0]));
$assert('items[0].profit_total removed', !array_key_exists('profit_total', $so['items'][0]));
$assert('items[0].qty preserved', ($so['items'][0]['qty'] ?? null) === 1);

$si = CostVisibility::stripItemRows($sample['items']);
$assert('stripItemRows removes cost_total', !array_key_exists('cost_total', $si[0]));
$assert('stripItemRows removes profit_total', !array_key_exists('profit_total', $si[0]));

$sos = CostVisibility::stripOrders([$sample]);
$assert('stripOrders removes cost_total', !array_key_exists('cost_total', $sos[0]));
$assert('stripOrders strips nested items', !array_key_exists('cost_total', $sos[0]['items'][0]));

$sr = CostVisibility::stripItemRow(['cost_total' => 7, 'profit_total' => 3, 'qty' => 2]);
$assert('stripItemRow removes cost_total', !array_key_exists('cost_total', $sr));
$assert('stripItemRow removes profit_total', !array_key_exists('profit_total', $sr));
$assert('stripItemRow keeps qty', ($sr['qty'] ?? null) === 2);

$sw = CostVisibility::stripWritable(['customer_name' => 'X', 'cost_total' => 5, 'profit_total' => 3]);
$assert('stripWritable removes cost_total', !array_key_exists('cost_total', $sw));
$assert('stripWritable removes profit_total', !array_key_exists('profit_total', $sw));
$assert('stripWritable keeps other fields', ($sw['customer_name'] ?? null) === 'X');

echo "Scenario B: user WITH vie_view_reports (administrator)\n";
wp_set_current_user($mgrId);
$assert('canView() === true for admin', CostVisibility::canView() === true);
$ko = CostVisibility::stripOrder($sample);
$assert('order.cost_total preserved', ($ko['cost_total'] ?? null) === 60);
$assert('order.profit_total preserved', ($ko['profit_total'] ?? null) === 40);
$assert('items[0].cost_total preserved', ($ko['items'][0]['cost_total'] ?? null) === 30);
$kos = CostVisibility::stripOrders([$sample]);
$assert('stripOrders keeps cost_total for admin', ($kos[0]['cost_total'] ?? null) === 60);
$kw = CostVisibility::stripWritable(['cost_total' => 5]);
$assert('stripWritable keeps cost_total for admin', ($kw['cost_total'] ?? null) === 5);

echo "Scenario C: OrderController::show transforms the real order payload\n";
$wpdb    = $GLOBALS['wpdb'];
$orderId = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}vie_order ORDER BY id DESC LIMIT 1");
if ($orderId > 0) {
    // Authorized (admin) path through the actual controller keeps the fields.
    wp_set_current_user($mgrId);
    $req = new \WP_REST_Request('GET', '/orders/' . $orderId);
    $req->set_param('id', $orderId);
    $resp   = OrderController::show($req);
    $detail = $resp->get_data()['data'] ?? [];
    $assert('admin show() keeps cost_total', is_array($detail) && array_key_exists('cost_total', $detail));

    // Re-run the controller as a sales user who owns nothing -> 403 is expected,
    // so instead assert the controller's transform on the SAME real payload: under
    // sales context stripOrder() (the exact call show() makes) removes the fields.
    wp_set_current_user($salesId);
    $salesView = CostVisibility::stripOrder($detail);
    $assert('sales view of real order has no cost_total', !array_key_exists('cost_total', $salesView));
    $assert('sales view of real order has no profit_total', !array_key_exists('profit_total', $salesView));
    if (isset($salesView['items'][0]) && is_array($salesView['items'][0])) {
        $assert('sales view real items stripped', !array_key_exists('cost_total', $salesView['items'][0]));
    }
} else {
    echo "  • skip Scenario C — no order rows (run full suite for integration)\n";
}

echo "Scenario D: OrderItemController index/show strips cost/profit for sales (on OWN order)\n";
$itemRow = $GLOBALS['wpdb']->get_row(
    "SELECT id, order_id FROM {$GLOBALS['wpdb']->prefix}vie_order_item ORDER BY id DESC LIMIT 1",
    ARRAY_A
);
if ($itemRow) {
    $itemId     = (int) $itemRow['id'];
    $ownOrderId = (int) $itemRow['order_id'];
    // Make the sales user the OWNER so they can legitimately read it; then verify stripping.
    // (Reading another seller's order-items is an IDOR — covered by authz-idor-e2e.php.)
    $GLOBALS['wpdb']->update("{$GLOBALS['wpdb']->prefix}vie_order", ['sales_user_id' => $salesId], ['id' => $ownOrderId]);

    wp_set_current_user($salesId);
    $req = new \WP_REST_Request('GET', '/order-items');
    $req->set_param('order_id', $ownOrderId);
    $req->set_param('per_page', 5);
    $rows = OrderItemController::index($req)->get_data()['data'] ?? [];
    $assert('order-items index returns rows', is_array($rows) && count($rows) > 0);
    if (!empty($rows)) {
        $assert('sales order-items index has no cost_total', !array_key_exists('cost_total', $rows[0]));
        $assert('sales order-items index has no profit_total', !array_key_exists('profit_total', $rows[0]));
    }

    $sreq = new \WP_REST_Request('GET', '/order-items/' . $itemId);
    $sreq->set_param('id', $itemId);
    $one = OrderItemController::show($sreq)->get_data()['data'] ?? [];
    $assert('sales order-items show has no cost_total', is_array($one) && !array_key_exists('cost_total', $one));
    $assert('sales order-items show has no profit_total', is_array($one) && !array_key_exists('profit_total', $one));

    // Authorized path unchanged.
    wp_set_current_user($mgrId);
    $mrows = OrderItemController::index($req)->get_data()['data'] ?? [];
    $assert('admin order-items index keeps cost_total', !empty($mrows) && array_key_exists('cost_total', $mrows[0]));
    $mone = OrderItemController::show($sreq)->get_data()['data'] ?? [];
    $assert('admin order-items show keeps cost_total', is_array($mone) && array_key_exists('cost_total', $mone));
} else {
    echo "  • skip Scenario D — no order_item rows (run full suite for integration)\n";
}

echo "Scenario E: cancel + invoice/data strip cost/profit for sales\n";
$wpdb = $GLOBALS['wpdb'];

// --- invoice/data (gated by vie_print_order + order ownership; force sales ownership) ---
$invOrderId = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}vie_order ORDER BY id DESC LIMIT 1");
if ($invOrderId > 0) {
    $wpdb->update("{$wpdb->prefix}vie_order", ['sales_user_id' => $salesId], ['id' => $invOrderId]); // sales owns it
    \Vie\Container::get(\Vie\Service\Settings\InvoiceSettings::class)->update(['company_name' => 'E2E Test Co']);
    $ireq = new \WP_REST_Request('GET', '/orders/' . $invOrderId . '/invoice/data');
    $ireq->set_param('id', $invOrderId);

    wp_set_current_user($salesId);
    $idata = InvoiceController::data($ireq)->get_data()['data'] ?? null;
    $assert('sales invoice/data order has no cost_total', is_array($idata) && isset($idata['order']) && !array_key_exists('cost_total', $idata['order']));
    $assert('sales invoice/data order has no profit_total', is_array($idata) && isset($idata['order']) && !array_key_exists('profit_total', $idata['order']));
    $assert('sales invoice/data items stripped', is_array($idata) && is_array($idata['items'] ?? null) && (empty($idata['items']) || !array_key_exists('cost_total', $idata['items'][0])));

    wp_set_current_user($mgrId);
    $mdata = InvoiceController::data($ireq)->get_data()['data'] ?? null;
    $assert('admin invoice/data order keeps cost_total', is_array($mdata) && isset($mdata['order']) && array_key_exists('cost_total', $mdata['order']));
} else {
    echo "  • skip invoice/data — no order rows\n";
}

// --- cancel (gated by vie_cancel_orders which sales HAS; force sales ownership) ---
$cancelId = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}vie_order WHERE status IN ('pending','confirmed') ORDER BY id DESC LIMIT 1");
if ($cancelId > 0) {
    $wpdb->update("{$wpdb->prefix}vie_order", ['sales_user_id' => $salesId], ['id' => $cancelId]);
    wp_set_current_user($salesId);
    $creq = new \WP_REST_Request('POST', '/orders/' . $cancelId . '/cancel');
    $creq->set_param('id', $cancelId);
    $creq->set_header('Content-Type', 'application/json');
    $creq->set_body(json_encode(['reason' => 'e2e cost-vis cancel', 'refund_amount' => 0]));
    $cdata = OrderActionController::cancel($creq)->get_data()['data'] ?? null;
    $assert('sales cancel response has no cost_total', is_array($cdata) && !array_key_exists('cost_total', $cdata));
    $assert('sales cancel response has no profit_total', is_array($cdata) && !array_key_exists('profit_total', $cdata));
    if (is_array($cdata) && isset($cdata['items'][0])) {
        $assert('sales cancel items stripped', !array_key_exists('cost_total', $cdata['items'][0]));
    }
} else {
    echo "  • skip cancel — no cancellable order\n";
}

echo "Scenario F: draft endpoints strip cost/profit for sales\n";
wp_set_current_user($salesId);
$dreq = new \WP_REST_Request('POST', '/orders/draft');
$dreq->set_header('Content-Type', 'application/json');
$dreq->set_body(json_encode(['customer_name' => 'F2E Draft Test']));
$dresp = \Vie\Http\Controllers\OrderController::storeDraft($dreq)->get_data();
$ddata = $dresp['data'] ?? null;
$assert('sales storeDraft has no cost_total', is_array($ddata) && !array_key_exists('cost_total', $ddata));
$assert('sales storeDraft has no profit_total', is_array($ddata) && !array_key_exists('profit_total', $ddata));

wp_set_current_user(0);
