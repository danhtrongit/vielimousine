<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('Run via: wp eval-file inc/seed/run.php');
}

global $wpdb;

\Vie\Schema\SchemaManager::install();

echo "Seeding hotel...\n";
\Vie\Schema\Seeders\HotelSeeder::run($wpdb);

echo "\nSeeding rooms + prices (30 days)...\n";
\Vie\Schema\Seeders\RoomSeeder::run($wpdb);

echo "\nDone.\n";
