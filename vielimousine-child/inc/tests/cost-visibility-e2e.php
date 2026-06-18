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
$kw = CostVisibility::stripWritable(['cost_total' => 5]);
$assert('stripWritable keeps cost_total for admin', ($kw['cost_total'] ?? null) === 5);

wp_set_current_user(0);
