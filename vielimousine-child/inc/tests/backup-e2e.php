<?php
declare(strict_types=1);
/**
 * Backup/Restore e2e. Self-contained (uses a throwaway wpte_vie_backup_test table).
 * Run via the composite command in the plan's Global Constraints.
 */
if (!isset($GLOBALS['wpdb'])) { throw new RuntimeException('Run inside WP context'); }

use Vie\Service\Auth\RoleInstaller;
use Vie\Service\Backup\BackupService;

$wpdb = $GLOBALS['wpdb'];
$assert = function (string $name, bool $cond, string $detail = '') use (&$pass, &$fail): void {
    if ($cond) { echo "  ✓ {$name}\n"; $pass++; }
    else { echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; $fail++; }
};

RoleInstaller::install();

echo "Scenario A: capability vie_manage_backup\n";
$salesId = username_exists('bk_sales') ?: wp_insert_user(['user_login'=>'bk_sales','user_pass'=>wp_generate_password(),'role'=>'vie_sales']);
$hmId    = username_exists('bk_hm')    ?: wp_insert_user(['user_login'=>'bk_hm','user_pass'=>wp_generate_password(),'role'=>'vie_hotel_manager']);
$admId   = username_exists('bk_admin') ?: wp_insert_user(['user_login'=>'bk_admin','user_pass'=>wp_generate_password(),'role'=>'administrator']);
$assert('admin has vie_manage_backup', user_can((int)$admId, 'vie_manage_backup'));
$assert('hotel_manager lacks vie_manage_backup', !user_can((int)$hmId, 'vie_manage_backup'));
$assert('sales lacks vie_manage_backup', !user_can((int)$salesId, 'vie_manage_backup'));

echo "Scenario B: export\n";
$T = $wpdb->prefix . 'vie_backup_test';
$wpdb->query("DROP TABLE IF EXISTS `$T`");
$wpdb->query("CREATE TABLE `$T` (id INT PRIMARY KEY, label VARCHAR(50) NOT NULL) ENGINE=InnoDB");
$wpdb->query("INSERT INTO `$T` (id,label) VALUES (1,'alpha'),(2,'có dấu')");

$assert('isAllowed true for vie_ table', BackupService::isAllowed($T));
$assert('isAllowed false for users table', !BackupService::isAllowed($wpdb->prefix.'users'));

$sql = BackupService::export([$T]);
$assert('export has DROP TABLE', strpos($sql, "DROP TABLE IF EXISTS `$T`") !== false);
$assert('export has CREATE TABLE', strpos($sql, "CREATE TABLE `$T`") !== false);
$assert('export has INSERT', strpos($sql, "INSERT INTO `$T`") !== false);
$assert('export contains row value', strpos($sql, "alpha") !== false && strpos($sql, "có dấu") !== false);

$tables = BackupService::listTables();
$names = array_column($tables, 'name');
$assert('listTables includes our table', in_array($T, $names, true));
$assert('listTables excludes non-vie tables', count(array_filter($names, fn($n)=>strpos($n, $wpdb->prefix.'vie_')!==0))===0);
