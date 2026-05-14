<?php
declare(strict_types=1);

/**
 * Phase 12 — Benchmark p95 cho 3 endpoint chính.
 * Chạy: wp eval-file inc/tests/bench.php
 * Cần seed trước: wp eval-file inc/seed/perf.php
 */

if (!defined('ABSPATH')) {
    exit('Run via: wp eval-file inc/tests/bench.php');
}

global $wpdb;

\Vie\Schema\SchemaManager::install();

/**
 * @param callable():void $fn
 * @return array{p50:float,p95:float,p99:float,ok:bool}
 */
function vie_bench(string $name, callable $fn, int $runs = 100, float $targetMs = INF): array
{
    // Warmup
    for ($i = 0; $i < 5; $i++) { $fn(); }

    $times = [];
    for ($i = 0; $i < $runs; $i++) {
        $t = microtime(true);
        $fn();
        $times[] = (microtime(true) - $t) * 1000;
    }
    sort($times);
    $p50 = $times[(int) max(0, $runs * 0.5 - 1)];
    $p95 = $times[(int) max(0, $runs * 0.95 - 1)];
    $p99 = $times[(int) max(0, $runs * 0.99 - 1)];
    $ok  = $p95 <= $targetMs;
    printf(
        "%-40s p50=%7.1fms  p95=%7.1fms  p99=%7.1fms  target<%4.0fms  %s\n",
        $name, $p50, $p95, $p99, $targetMs, $ok ? '✓' : '✗'
    );
    return ['p50' => $p50, 'p95' => $p95, 'p99' => $p99, 'ok' => $ok];
}

// Auth as admin để bypass cap check
$admin = get_user_by('login', 'e2e_admin') ?: get_users(['role' => 'administrator', 'number' => 1])[0] ?? null;
if (!$admin) {
    echo "✗ Không tìm thấy admin user. Chạy auth-e2e trước hoặc tạo admin thủ công.\n";
    exit(1);
}
wp_set_current_user($admin->ID);

// Lấy 1 room để bench /quote
$roomId = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}vie_room ORDER BY id ASC LIMIT 1");
if ($roomId === 0) {
    echo "✗ Không có room. Chạy `wp eval-file inc/seed/run.php` trước.\n";
    exit(1);
}

$orderCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vie_order");
$payCount   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vie_payment_log");
printf("Dataset: %d orders, %d payments. Bench room_id=%d.\n\n", $orderCount, $payCount, $roomId);

$results = [];

$results['orders'] = vie_bench('GET /orders?per_page=50', function () {
    $req = new \WP_REST_Request('GET', '/vie/v1/orders');
    $req->set_query_params(['per_page' => 50, 'page' => 1]);
    rest_do_request($req);
}, 100, 200);

$results['payments'] = vie_bench('GET /payments?per_page=50', function () {
    $req = new \WP_REST_Request('GET', '/vie/v1/payments');
    $req->set_query_params(['per_page' => 50, 'page' => 1]);
    rest_do_request($req);
}, 100, 300);

$results['quote'] = vie_bench('POST /quote (room)', function () use ($roomId) {
    $req = new \WP_REST_Request('POST', '/vie/v1/quote');
    $req->set_header('Content-Type', 'application/json');
    $req->set_body(json_encode([
        'room_id'      => $roomId,
        'booking_type' => 'room',
        'checkin'      => gmdate('Y-m-d', strtotime('+30 day')),
        'checkout'     => gmdate('Y-m-d', strtotime('+32 day')),
        'adults'       => 2,
    ]));
    rest_do_request($req);
}, 100, 100);

$failed = array_filter($results, static fn($r) => !$r['ok']);
echo "\n";
if ($failed === []) {
    echo "=== BENCH PASS: tất cả endpoint đạt p95 target ===\n";
    exit(0);
}
echo "=== BENCH FAIL: " . count($failed) . " endpoint vượt target ===\n";
exit(1);
