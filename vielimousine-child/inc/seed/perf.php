<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('Run via: wp eval-file inc/seed/perf.php');
}

global $wpdb;

\Vie\Schema\SchemaManager::install();

echo "Seeding perf dataset (10k orders + 30k items + 50k payments)...\n";
$start = microtime(true);
$stats = \Vie\Schema\Seeders\PerfSeeder::run($wpdb);
$elapsed = microtime(true) - $start;

printf("Done in %.1fs.\n", $elapsed);
printf("  Orders  : %d\n",  $stats['orders']);
printf("  Items   : %d\n",  $stats['items']);
printf("  Payments: %d\n",  $stats['payments']);
