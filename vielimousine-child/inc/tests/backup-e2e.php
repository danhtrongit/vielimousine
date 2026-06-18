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

echo "Scenario C: restore round-trip + allowlist\n";
$sqlBackup = BackupService::export([$T]);            // 2 rows (from Scenario B)
$wpdb->query("DELETE FROM `$T`");                    // simulate data loss
$assert('table emptied before restore', (int)$wpdb->get_var("SELECT COUNT(*) FROM `$T`") === 0);

$res = BackupService::restore($sqlBackup);
$assert('restore reports our table', in_array($T, $res['tables_restored'], true));
$assert('restore no errors', empty($res['errors']), implode('; ', $res['errors']));
$assert('rows restored (2)', (int)$wpdb->get_var("SELECT COUNT(*) FROM `$T`") === 2);
$assert('value restored', $wpdb->get_var("SELECT label FROM `$T` WHERE id=1") === 'alpha');

$rejected = false;
try { BackupService::restore("INSERT INTO `{$wpdb->prefix}users` (ID) VALUES (999);"); }
catch (\RuntimeException $e) { $rejected = true; }
$assert('restore rejects non-allowlisted table', $rejected);
$assert('users table untouched', (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}users` WHERE ID=999") === 0);

$rejDrop = false;
try { BackupService::restore("DROP TABLE `{$wpdb->prefix}users`;"); } catch (\RuntimeException $e) { $rejDrop = true; }
$assert('restore rejects DROP TABLE (no IF EXISTS) on users', $rejDrop);

$rejUpd = false;
try { BackupService::restore("UPDATE `{$wpdb->prefix}options` SET option_value='x' WHERE option_id=1;"); } catch (\RuntimeException $e) { $rejUpd = true; }
$assert('restore rejects UPDATE on options', $rejUpd);

$rejTrunc = false;
try { BackupService::restore("TRUNCATE TABLE `{$wpdb->prefix}users`;"); } catch (\RuntimeException $e) { $rejTrunc = true; }
$assert('restore rejects TRUNCATE on users', $rejTrunc);

$rejDelete = false;
try { BackupService::restore("DELETE FROM `{$wpdb->prefix}users` WHERE ID=999;"); } catch (\RuntimeException $e) { $rejDelete = true; }
$assert('restore rejects DELETE FROM users', $rejDelete);
$assert('users untouched after DELETE attempt', (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}users` WHERE ID=999") === 0);

$rejBacktick = false;
try { BackupService::restore("SOMETHING WEIRD `{$wpdb->prefix}options` blah;"); } catch (\RuntimeException $e) { $rejBacktick = true; }
$assert('restore rejects any backticked non-vie table (verb-independent)', $rejBacktick);

$wpdb->query("DROP TABLE IF EXISTS `$T`");           // cleanup

echo "Scenario D: controller endpoints\n";
wp_set_current_user((int)$admId);

$reqMissing = new \WP_REST_Request('POST', '/backup/restore');
$reqMissing->set_body(json_encode(['sql' => 'x']));
$reqMissing->set_header('Content-Type', 'application/json');
$respMissing = \Vie\Http\Controllers\BackupController::restore($reqMissing);
$assert('restore without confirm -> 422', $respMissing->get_status() === 422);

$reqExp = new \WP_REST_Request('POST', '/backup/export');
$reqExp->set_body(json_encode(['tables' => [$wpdb->prefix.'vie_coupon']]));
$reqExp->set_header('Content-Type', 'application/json');
$dataExp = \Vie\Http\Controllers\BackupController::export($reqExp)->get_data()['data'] ?? [];
$assert('export returns filename', isset($dataExp['filename']) && str_ends_with($dataExp['filename'], '.sql'));
$assert('export returns sql with CREATE', isset($dataExp['sql']) && strpos($dataExp['sql'], 'CREATE TABLE') !== false);

$reqExpBad = new \WP_REST_Request('POST', '/backup/export');
$reqExpBad->set_body(json_encode(['tables' => [$wpdb->prefix.'users']]));
$reqExpBad->set_header('Content-Type', 'application/json');
$assert('export rejects non-allowlisted -> 422', \Vie\Http\Controllers\BackupController::export($reqExpBad)->get_status() === 422);

$tblData = \Vie\Http\Controllers\BackupController::tables(new \WP_REST_Request('GET','/backup/tables'))->get_data()['data'] ?? [];
$assert('tables endpoint returns list', is_array($tblData) && count($tblData) > 0);

// Controller restore success path (round-trip through the HTTP layer)
$Td = $wpdb->prefix . 'vie_backup_test';
$wpdb->query("DROP TABLE IF EXISTS `$Td`");
$wpdb->query("CREATE TABLE `$Td` (id INT PRIMARY KEY, label VARCHAR(50) NOT NULL) ENGINE=InnoDB");
$wpdb->query("INSERT INTO `$Td` (id,label) VALUES (1,'ctrl')");
$sqlD = \Vie\Service\Backup\BackupService::export([$Td]);
$reqRes = new \WP_REST_Request('POST', '/backup/restore');
$reqRes->set_header('Content-Type', 'application/json');
$reqRes->set_body(json_encode(['sql' => $sqlD, 'confirm' => 'RESTORE']));
$respRes = \Vie\Http\Controllers\BackupController::restore($reqRes);
$assert('controller restore success -> 200', $respRes->get_status() === 200);
$dataRes = $respRes->get_data()['data'] ?? [];
$assert('controller restore returns snapshot_file', !empty($dataRes['snapshot_file']));
$assert('controller restore lists the table', in_array($Td, $dataRes['tables_restored'] ?? [], true));
$wpdb->query("DROP TABLE IF EXISTS `$Td`");

wp_set_current_user(0);
