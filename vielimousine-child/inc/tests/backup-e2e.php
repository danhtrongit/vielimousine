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

echo "Scenario C2: statement-type allowlist (reject statements with no allowlisted verb)\n";
// Statements that reference NO table slip past the table-name allowlist; they must be
// rejected by a positive statement-type allowlist (only SET NAMES / SET FOREIGN_KEY_CHECKS /
// DROP TABLE IF EXISTS / CREATE TABLE / INSERT INTO / TRUNCATE TABLE on vie_* are allowed).
$rejSelect = false;
try { BackupService::restore("SELECT 1;"); } catch (\RuntimeException $e) { $rejSelect = true; }
$assert('restore rejects bare SELECT (no allowlisted verb)', $rejSelect);

$rejSetVar = false;
try { BackupService::restore("SET @vie_probe := 1;"); } catch (\RuntimeException $e) { $rejSetVar = true; }
$assert('restore rejects SET of arbitrary variable', $rejSetVar);

$rejOutfile = false;
try { BackupService::restore("SELECT 1 INTO OUTFILE '/tmp/vie_e2e_probe.sql';"); } catch (\RuntimeException $e) { $rejOutfile = true; }
$assert('restore rejects SELECT ... INTO OUTFILE', $rejOutfile);

// A legitimate vie_ dump must still round-trip through the allowlist unharmed.
$Tx = $wpdb->prefix . 'vie_backup_test';
$wpdb->query("DROP TABLE IF EXISTS `$Tx`");
$wpdb->query("CREATE TABLE `$Tx` (id INT PRIMARY KEY, label VARCHAR(50) NOT NULL) ENGINE=InnoDB");
$wpdb->query("INSERT INTO `$Tx` (id,label) VALUES (1,'still works; with semicolon')");
$legitOk = false;
try {
    $r = BackupService::restore(BackupService::export([$Tx]));
    $legitOk = empty($r['errors'])
        && (int) $wpdb->get_var("SELECT COUNT(*) FROM `$Tx`") === 1
        && $wpdb->get_var("SELECT label FROM `$Tx` WHERE id=1") === 'still works; with semicolon';
} catch (\Throwable $e) { $legitOk = false; }
$assert('restore still accepts a legit vie_ dump (incl. value with semicolon)', $legitOk);
$wpdb->query("DROP TABLE IF EXISTS `$Tx`");

// Regression: every real vie_ table has `updated_at ... ON UPDATE CURRENT_TIMESTAMP`.
// The table-name scanner must NOT treat the `UPDATE` inside `ON UPDATE CURRENT_TIMESTAMP`
// as a table-touching verb — otherwise it captures "CURRENT_TIMESTAMP" and rejects a valid
// backup ("Bảng ngoài phạm vi cho phép: CURRENT_TIMESTAMP").
$Tts = $wpdb->prefix . 'vie_backup_test';
$wpdb->query("DROP TABLE IF EXISTS `$Tts`");
$wpdb->query(
    "CREATE TABLE `$Tts` (id INT PRIMARY KEY, label VARCHAR(50) NOT NULL, "
  . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB"
);
$wpdb->query("INSERT INTO `$Tts` (id,label) VALUES (1,'ts')");
$tsOk = false; $tsErr = '';
try {
    $r = BackupService::restore(BackupService::export([$Tts]));
    $tsOk = empty($r['errors']) && (int) $wpdb->get_var("SELECT COUNT(*) FROM `$Tts`") === 1;
} catch (\Throwable $e) { $tsErr = $e->getMessage(); }
$assert('restore accepts dump with ON UPDATE CURRENT_TIMESTAMP column', $tsOk, $tsErr);
$wpdb->query("DROP TABLE IF EXISTS `$Tts`");

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

echo "Scenario E: auto-snapshot directory is not web-accessible\n";
$Te = $wpdb->prefix . 'vie_backup_test';
$wpdb->query("DROP TABLE IF EXISTS `$Te`");
$wpdb->query("CREATE TABLE `$Te` (id INT PRIMARY KEY, label VARCHAR(50) NOT NULL) ENGINE=InnoDB");
$wpdb->query("INSERT INTO `$Te` (id,label) VALUES (1,'snap')");
$reqE = new \WP_REST_Request('POST', '/backup/restore');
$reqE->set_header('Content-Type', 'application/json');
$reqE->set_body(json_encode(['sql' => \Vie\Service\Backup\BackupService::export([$Te]), 'confirm' => 'RESTORE']));
$respE = \Vie\Http\Controllers\BackupController::restore($reqE);
$assert('controller restore success -> 200 (snapshot path)', $respE->get_status() === 200);

$bdir = wp_upload_dir()['basedir'] . '/vie-backups';
$assert('snapshot dir has deny-all .htaccess', is_file($bdir . '/.htaccess') && stripos((string) @file_get_contents($bdir . '/.htaccess'), 'denied') !== false);
$assert('snapshot dir has index.php', is_file($bdir . '/index.php'));

$snapName = basename((string) ($respE->get_data()['data']['snapshot_file'] ?? ''));
$assert('snapshot filename uses high-entropy suffix', (bool) preg_match('/^auto-[0-9]{8}-[0-9]{6}-[0-9a-f]{16}\.sql$/', $snapName), "got: {$snapName}");
$wpdb->query("DROP TABLE IF EXISTS `$Te`");

wp_set_current_user(0);
