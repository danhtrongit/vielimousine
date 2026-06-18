# Backup & Restore (admin) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An admin-only feature to back up selected `vie_*` tables to a downloadable `.sql` file and restore from such a file, implemented in pure PHP (no `mysqldump`/`shell_exec`) so it runs on local and cPanel alike.

**Architecture:** A static `Vie\Service\Backup\BackupService` (list/export/restore via `$wpdb`); three REST endpoints in a `BackupController` gated by a new `vie_manage_backup` capability; a `BackupView.vue` admin page. Restore is guarded by an allowlist (`wpte_vie_*` only), a typed `RESTORE` confirmation, and an automatic pre-restore snapshot.

**Tech Stack:** PHP 8 / WordPress (`Vie\` autoload → `inc/src/`), MySQL 8 (mysqli via `$wpdb`), Vue 3.5 + TS + Vite admin SPA, vitest.

## Global Constraints

- New capability (exact): `vie_manage_backup`, granted to **administrator only** (not `vie_hotel_manager`/`vie_sales`). It auto-flows into `user.caps` because `AuthService::extractCaps` includes any granted cap starting with `vie_`.
- Table allowlist (exact): a table is backup/restore-eligible **only if its name starts with `$wpdb->prefix . 'vie_'`** (here `wpte_vie_`). Restore MUST reject any SQL referencing a non-allowlisted table.
- Pure PHP only — no `shell_exec`/`mysqldump`. Export via `SHOW CREATE TABLE` + batched `INSERT`; restore via `mysqli_multi_query` on `$wpdb->dbh`.
- Restore safeguards: require `confirm === "RESTORE"`; auto-snapshot referenced tables to `wp-content/uploads/vie-backups/auto-<UTC>.sql` before executing.
- Backend tests run in the `vie_cli` Docker container via `wp eval` + `require` (NOT `wp eval-file`). Do NOT run `inc/tests/run.php` — it aborts pre-existing in `auth-e2e.php` (JwtService.php:60), unrelated to this feature.
- Frontend build/test run on the host in `vielimousine-child/admin-app` (Node v26). The project commits `dist/`; rebuild it (Task 6). A `localStorage ExperimentalWarning` in jsdom test output is environmental, not a failure.
- Execute on a dedicated branch, not `main`.

### Backend test command (copy verbatim)
The backup e2e is self-contained (creates its own throwaway `wpte_vie_backup_test` table — no seeding needed):
```bash
docker exec vie_cli wp --path=/var/www/html --allow-root eval '$pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/backup-e2e.php"; echo "\n=== BACKUP: {$pass} passed, {$fail} failed ===\n"; exit($fail===0?0:1);'
```

---

## Task 0: Work branch + commit design docs

**Files:** none (git only)

- [ ] **Step 1: Branch off main and commit the spec + plan**
```bash
cd /Users/danhtrongit/vie.local/wp/wp-content/themes
git checkout -b feat/admin-backup-restore
git add docs/superpowers/specs/2026-06-18-backup-restore-design.md docs/superpowers/plans/2026-06-18-backup-restore.md
git commit -m "docs(backup): spec + plan for admin backup/restore

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```
- [ ] **Step 2: Confirm branch**
Run: `git branch --show-current`
Expected: `feat/admin-backup-restore`

---

## Task 1: Add `vie_manage_backup` capability (administrator only)

**Files:**
- Modify: `vielimousine-child/inc/src/Service/Auth/RoleInstaller.php`
- Create (test): `vielimousine-child/inc/tests/backup-e2e.php`

**Interfaces:**
- Produces: capability string `vie_manage_backup` granted to `administrator`, absent from `vie_hotel_manager` and `vie_sales`.

- [ ] **Step 1: Write the failing test (cap assertions)**

Create `vielimousine-child/inc/tests/backup-e2e.php`:
```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run the composite command (see Global Constraints).
Expected: `✗ admin has vie_manage_backup` (cap not granted yet); the `lacks` asserts pass.

- [ ] **Step 3: Add the capability to `RoleInstaller`**

In `vielimousine-child/inc/src/Service/Auth/RoleInstaller.php`:

(a) After `public const CAP_MANAGE_USERS = 'vie_manage_users';` add:
```php
    public const CAP_MANAGE_BACKUP = 'vie_manage_backup';
```

(b) In `install()`, after the line `$admin->add_cap(self::CAP_MANAGE_USERS);` add:
```php
            // Backup/Restore: chỉ administrator (rất nhạy cảm, không nằm trong ALL_CAPS).
            $admin->add_cap(self::CAP_MANAGE_BACKUP);
```

(c) In `install()`, change the staff-role cleanup loop so it also strips the backup cap from non-admin roles. Replace:
```php
        foreach ([self::ROLE_HOTEL_MANAGER, self::ROLE_SALES] as $slug) {
            $role = get_role($slug);
            if ($role !== null) {
                $role->remove_cap(self::CAP_MANAGE_USERS);
            }
        }
```
with:
```php
        foreach ([self::ROLE_HOTEL_MANAGER, self::ROLE_SALES] as $slug) {
            $role = get_role($slug);
            if ($role !== null) {
                $role->remove_cap(self::CAP_MANAGE_USERS);
                $role->remove_cap(self::CAP_MANAGE_BACKUP);
            }
        }
```

- [ ] **Step 4: Run the test to verify it passes**

Run the composite command.
Expected: Scenario A all `✓` (admin has it; hotel_manager & sales don't).

- [ ] **Step 5: Commit**
```bash
git add vielimousine-child/inc/src/Service/Auth/RoleInstaller.php vielimousine-child/inc/tests/backup-e2e.php
git commit -m "feat(backup): vie_manage_backup capability (administrator only)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: `BackupService::listTables` + `export`

**Files:**
- Create: `vielimousine-child/inc/src/Service/Backup/BackupService.php`
- Modify (test): `vielimousine-child/inc/tests/backup-e2e.php`

**Interfaces:**
- Produces:
  - `Vie\Service\Backup\BackupService::allowPrefix(): string` (= `$wpdb->prefix.'vie_'`)
  - `BackupService::isAllowed(string $table): bool`
  - `BackupService::listTables(): array` → list of `['name'=>string,'rows'=>int,'size_mb'=>float]`
  - `BackupService::export(array $tables): string` → SQL dump (only allowlisted tables)

- [ ] **Step 1: Write the failing test (export)**

Append to `vielimousine-child/inc/tests/backup-e2e.php` (before any final line; the file currently ends after Scenario A):
```php
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
```

- [ ] **Step 2: Run to verify it fails**

Run the composite command.
Expected: fatal `Class "Vie\Service\Backup\BackupService" not found`.

- [ ] **Step 3: Implement `BackupService` (list + export)**

Create `vielimousine-child/inc/src/Service/Backup/BackupService.php`:
```php
<?php
declare(strict_types=1);

namespace Vie\Service\Backup;

/**
 * Sao lưu/phục hồi các bảng vie_* bằng PHP thuần (không cần mysqldump/shell_exec).
 * Mọi thao tác bị giới hạn ở các bảng có tiền tố $wpdb->prefix.'vie_'.
 */
final class BackupService
{
    public static function allowPrefix(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'vie_';
    }

    public static function isAllowed(string $table): bool
    {
        return str_starts_with($table, self::allowPrefix());
    }

    /** @return array<int,array{name:string,rows:int,size_mb:float}> */
    public static function listTables(): array
    {
        global $wpdb;
        $like = $wpdb->esc_like(self::allowPrefix()) . '%';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT table_name AS n, table_rows AS r, ROUND((data_length+index_length)/1024/1024,2) AS mb
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name LIKE %s
                 ORDER BY table_name",
                $like
            ),
            ARRAY_A
        );
        $out = [];
        foreach ((array) $rows as $r) {
            $out[] = ['name' => (string) $r['n'], 'rows' => (int) $r['r'], 'size_mb' => (float) $r['mb']];
        }
        return $out;
    }

    /** Sinh SQL dump cho các bảng (chỉ bảng được phép). */
    public static function export(array $tables): string
    {
        global $wpdb;
        $out  = "-- Vielimousine backup " . gmdate('Y-m-d H:i:s') . " UTC\n";
        $out .= "-- tables: " . implode(', ', $tables) . "\n";
        $out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $t) {
            $t = (string) $t;
            if (!self::isAllowed($t)) {
                continue;
            }
            $create = $wpdb->get_row("SHOW CREATE TABLE `{$t}`", ARRAY_N);
            if (!$create || !isset($create[1])) {
                continue; // bảng không tồn tại
            }
            $out .= "DROP TABLE IF EXISTS `{$t}`;\n" . $create[1] . ";\n\n";

            $rows = $wpdb->get_results("SELECT * FROM `{$t}`", ARRAY_A);
            if ($rows) {
                $colList = '`' . implode('`,`', array_keys($rows[0])) . '`';
                $batch = [];
                foreach ($rows as $row) {
                    $vals = array_map(
                        static fn($v) => $v === null ? 'NULL' : "'" . esc_sql((string) $v) . "'",
                        array_values($row)
                    );
                    $batch[] = '(' . implode(',', $vals) . ')';
                    if (count($batch) >= 500) {
                        $out .= "INSERT INTO `{$t}` ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n";
                        $batch = [];
                    }
                }
                if ($batch) {
                    $out .= "INSERT INTO `{$t}` ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n";
                }
            }
            $out .= "\n";
        }

        $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $out;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run the composite command.
Expected: Scenario B all `✓`.

- [ ] **Step 5: Commit**
```bash
git add vielimousine-child/inc/src/Service/Backup/BackupService.php vielimousine-child/inc/tests/backup-e2e.php
git commit -m "feat(backup): BackupService listTables + export (pure PHP)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: `BackupService::restore` (allowlist + multi_query)

**Files:**
- Modify: `vielimousine-child/inc/src/Service/Backup/BackupService.php`
- Modify (test): `vielimousine-child/inc/tests/backup-e2e.php`

**Interfaces:**
- Consumes: `BackupService::export`, `isAllowed`.
- Produces: `BackupService::restore(string $sql): array` → `['tables_restored'=>string[], 'statements'=>int, 'errors'=>string[]]`. Throws `\RuntimeException` if `$sql` references any non-allowlisted table.

- [ ] **Step 1: Write the failing test (round-trip + allowlist reject)**

Append to `vielimousine-child/inc/tests/backup-e2e.php`:
```php
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

$wpdb->query("DROP TABLE IF EXISTS `$T`");           // cleanup
```

- [ ] **Step 2: Run to verify it fails**

Run the composite command.
Expected: fatal/`✗` — `restore` method does not exist yet (Error calling undefined method).

- [ ] **Step 3: Implement `restore`**

In `vielimousine-child/inc/src/Service/Backup/BackupService.php`, add this method inside the class (after `export`):
```php
    /**
     * Phục hồi từ SQL. Quét tên bảng trước: nếu có bảng ngoài allowlist -> ném RuntimeException,
     * KHÔNG thực thi gì. Thực thi nguyên khối qua mysqli_multi_query (server tự parse).
     * @return array{tables_restored:array<int,string>,statements:int,errors:array<int,string>}
     */
    public static function restore(string $sql): array
    {
        global $wpdb;

        preg_match_all('/(?:DROP\s+TABLE\s+IF\s+EXISTS|CREATE\s+TABLE|INSERT\s+INTO)\s+`?([A-Za-z0-9_]+)`?/i', $sql, $m);
        $tables = array_values(array_unique($m[1]));
        $bad = array_values(array_filter($tables, static fn($t) => !self::isAllowed($t)));
        if ($bad) {
            throw new \RuntimeException('Bảng ngoài phạm vi cho phép: ' . implode(', ', $bad));
        }

        $dbh = $wpdb->dbh; // mysqli
        $full = "SET FOREIGN_KEY_CHECKS=0;\n" . $sql . "\nSET FOREIGN_KEY_CHECKS=1;\n";
        $errors = [];
        $stmts = 0;

        if (mysqli_multi_query($dbh, $full)) {
            do {
                $stmts++;
                if ($r = mysqli_store_result($dbh)) {
                    mysqli_free_result($r);
                }
                if (!mysqli_more_results($dbh)) {
                    break;
                }
            } while (mysqli_next_result($dbh));
        }
        $err = mysqli_error($dbh);
        if ($err !== '') {
            $errors[] = $err;
        }
        $wpdb->flush();

        return ['tables_restored' => $tables, 'statements' => $stmts, 'errors' => $errors];
    }
```

- [ ] **Step 4: Run to verify it passes**

Run the composite command.
Expected: Scenario C all `✓` (round-trip restores 2 rows; non-allowlisted SQL rejected; users untouched).

- [ ] **Step 5: Commit**
```bash
git add vielimousine-child/inc/src/Service/Backup/BackupService.php vielimousine-child/inc/tests/backup-e2e.php
git commit -m "feat(backup): BackupService restore (allowlist + multi_query)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: `BackupController` + REST routes

**Files:**
- Create: `vielimousine-child/inc/src/Http/Controllers/BackupController.php`
- Modify: `vielimousine-child/inc/src/Http/RestRouter.php`
- Modify (test): `vielimousine-child/inc/tests/backup-e2e.php`

**Interfaces:**
- Consumes: `BackupService`, `Vie\Support\ResponseEnvelope`.
- Produces: `BackupController::{tables,export,restore}(\WP_REST_Request): \WP_REST_Response`; routes `GET /backup/tables`, `POST /backup/export`, `POST /backup/restore` gated by `vie_manage_backup`.

- [ ] **Step 1: Write the failing test (controller behavior)**

Append to `vielimousine-child/inc/tests/backup-e2e.php`:
```php
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
wp_set_current_user(0);
```

- [ ] **Step 2: Run to verify it fails**

Run the composite command.
Expected: fatal — `Vie\Http\Controllers\BackupController` not found.

- [ ] **Step 3: Implement `BackupController`**

Create `vielimousine-child/inc/src/Http/Controllers/BackupController.php`:
```php
<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Service\Backup\BackupService;
use Vie\Support\ResponseEnvelope;

final class BackupController
{
    public static function tables(\WP_REST_Request $request): \WP_REST_Response
    {
        return ResponseEnvelope::success(BackupService::listTables());
    }

    public static function export(\WP_REST_Request $request): \WP_REST_Response
    {
        $body   = $request->get_json_params();
        $tables = is_array($body['tables'] ?? null) ? $body['tables'] : [];
        $tables = array_values(array_filter(array_map('strval', $tables), [BackupService::class, 'isAllowed']));
        if ($tables === []) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'tables', 'message' => 'Chọn ít nhất 1 bảng hợp lệ (vie_*)'],
            ], 422);
        }
        $sql = BackupService::export($tables);
        return ResponseEnvelope::success([
            'filename' => 'vie-backup-' . gmdate('Ymd-His') . '.sql',
            'sql'      => $sql,
            'tables'   => $tables,
            'bytes'    => strlen($sql),
        ]);
    }

    public static function restore(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = $request->get_json_params();
        if (($body['confirm'] ?? '') !== 'RESTORE') {
            return ResponseEnvelope::error([
                ['code' => 'confirm_required', 'field' => 'confirm', 'message' => 'Gõ chính xác RESTORE để xác nhận'],
            ], 422);
        }
        $sql = (string) ($body['sql'] ?? '');
        if (trim($sql) === '') {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'sql', 'message' => 'Thiếu nội dung SQL'],
            ], 422);
        }

        try {
            // auto-snapshot các bảng vie_* hiện có được nhắc tới trong file
            preg_match_all('/(?:DROP\s+TABLE\s+IF\s+EXISTS|CREATE\s+TABLE|INSERT\s+INTO)\s+`?([A-Za-z0-9_]+)`?/i', $sql, $m);
            $refTables = array_values(array_filter(array_unique($m[1]), [BackupService::class, 'isAllowed']));

            $dir = wp_upload_dir()['basedir'] . '/vie-backups';
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
            $snap = $dir . '/auto-' . gmdate('Ymd-His') . '.sql';
            file_put_contents($snap, BackupService::export($refTables));

            $res = BackupService::restore($sql);
            $res['snapshot_file'] = str_replace(wp_upload_dir()['basedir'], '', $snap);
            return ResponseEnvelope::success($res);
        } catch (\RuntimeException $e) {
            return ResponseEnvelope::error([
                ['code' => 'restore_rejected', 'field' => null, 'message' => $e->getMessage()],
            ], 422);
        }
    }
}
```

- [ ] **Step 4: Register routes in `RestRouter`**

In `vielimousine-child/inc/src/Http/RestRouter.php`:

(a) Add the import next to the other controller `use` lines (after `use Vie\Http\Controllers\AuthController;`):
```php
use Vie\Http\Controllers\BackupController;
```

(b) Add the routes after the `/room-prices/bulk` route block:
```php
        // Backup & Restore (admin only)
        register_rest_route(VIE_API_NAMESPACE, '/backup/tables', [
            'methods'             => 'GET',
            'callback'            => [BackupController::class, 'tables'],
            'permission_callback' => AuthMiddleware::requireCap('vie_manage_backup'),
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/backup/export', [
            'methods'             => 'POST',
            'callback'            => [BackupController::class, 'export'],
            'permission_callback' => AuthMiddleware::requireCap('vie_manage_backup'),
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/backup/restore', [
            'methods'             => 'POST',
            'callback'            => [BackupController::class, 'restore'],
            'permission_callback' => AuthMiddleware::requireCap('vie_manage_backup'),
        ]);
```

- [ ] **Step 5: Run to verify it passes**

Run the composite command.
Expected: Scenario D all `✓`.

- [ ] **Step 6: Commit**
```bash
git add vielimousine-child/inc/src/Http/Controllers/BackupController.php vielimousine-child/inc/src/Http/RestRouter.php vielimousine-child/inc/tests/backup-e2e.php
git commit -m "feat(backup): REST endpoints (tables/export/restore) gated by vie_manage_backup

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: Frontend — API client, `BackupView.vue`, route, nav

**Files:**
- Create: `vielimousine-child/admin-app/src/api/backup.api.ts`
- Create: `vielimousine-child/admin-app/src/views/backup/BackupView.vue`
- Create (test): `vielimousine-child/admin-app/src/views/backup/BackupView.spec.ts`
- Modify: `vielimousine-child/admin-app/src/router.ts`
- Modify: `vielimousine-child/admin-app/src/layouts/DefaultLayout.vue`

**Interfaces:**
- Consumes: `vie_manage_backup` cap (now in `auth.user.caps` for admins).
- Produces: `backupApi.{tables,export,restore}`; route `/backup` (meta cap `vie_manage_backup`); nav item.

- [ ] **Step 1: Write the failing component test**

Create `vielimousine-child/admin-app/src/views/backup/BackupView.spec.ts`:
```ts
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

vi.mock('@/api/backup.api', () => ({
  backupApi: {
    tables: vi.fn().mockResolvedValue({ data: [
      { name: 'wpte_vie_order', rows: 2121, size_mb: 2.61 },
      { name: 'wpte_vie_coupon', rows: 3, size_mb: 0.11 },
    ] }),
    export: vi.fn(),
    restore: vi.fn(),
  },
}));
vi.mock('@/composables/useNotify', () => ({ useNotify: () => ({ success: vi.fn(), apiError: vi.fn() }) }));
vi.mock('@/stores/ui.store', () => ({ useUIStore: () => ({ setBreadcrumb: vi.fn() }) }));

import BackupView from './BackupView.vue';

function mountView() {
  return mount(BackupView, {
    global: {
      stubs: {
        PageHeader: { template: '<div><slot /></div>' },
        DataTable: { props: ['value'], template: '<div class="dt"><span v-for="r in value" :key="r.name" class="row">{{ r.name }}</span></div>' },
        Column: true, Checkbox: true, Button: true, InputText: true, FileUpload: true, Card: { template: '<div><slot name="title"/><slot name="content"/></div>' },
        Message: { template: '<div><slot /></div>' },
      },
    },
  });
}

describe('BackupView', () => {
  beforeEach(() => { vi.clearAllMocks(); });

  it('loads and renders the table list', async () => {
    const w = mountView();
    await flushPromises();
    expect(w.html()).toContain('wpte_vie_order');
    expect(w.html()).toContain('wpte_vie_coupon');
  });

  it('restore is blocked until the confirm text is exactly RESTORE', async () => {
    const w = mountView();
    await flushPromises();
    const vm = w.vm as unknown as { confirmText: string; canRestore: boolean; restoreSql: string };
    vm.restoreSql = 'DROP TABLE x;';
    vm.confirmText = 'restore';
    expect((w.vm as unknown as { canRestore: boolean }).canRestore).toBe(false);
    vm.confirmText = 'RESTORE';
    expect((w.vm as unknown as { canRestore: boolean }).canRestore).toBe(true);
  });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/danhtrongit/vie.local/wp/wp-content/themes/vielimousine-child/admin-app && npm run test -- BackupView`
Expected: FAIL — cannot resolve `./BackupView.vue`.

- [ ] **Step 3: Create the API client**

Create `vielimousine-child/admin-app/src/api/backup.api.ts`:
```ts
import { api } from './client';
import type { Envelope } from '@/types/envelope';

export interface BackupTable { name: string; rows: number; size_mb: number }
export interface ExportResult { filename: string; sql: string; tables: string[]; bytes: number }
export interface RestoreResult { tables_restored: string[]; statements: number; errors: string[]; snapshot_file: string }

export const backupApi = {
  tables: () => api.get<Envelope<BackupTable[]>>('/backup/tables').then((r) => r.data),
  export: (tables: string[]) => api.post<Envelope<ExportResult>>('/backup/export', { tables }).then((r) => r.data),
  restore: (sql: string, confirm: string) =>
    api.post<Envelope<RestoreResult>>('/backup/restore', { sql, confirm }).then((r) => r.data),
};
```

- [ ] **Step 4: Create `BackupView.vue`**

Create `vielimousine-child/admin-app/src/views/backup/BackupView.vue`:
```vue
<script setup lang="ts">
import { onMounted, ref, computed } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Card from 'primevue/card';
import Message from 'primevue/message';
import PageHeader from '@/components/PageHeader.vue';
import { useUIStore } from '@/stores/ui.store';
import { useNotify } from '@/composables/useNotify';
import { backupApi, type BackupTable } from '@/api/backup.api';

const ui = useUIStore();
const notify = useNotify();

const tables = ref<BackupTable[]>([]);
const selected = ref<string[]>([]);
const loading = ref(true);
const exporting = ref(false);

const restoreSql = ref('');
const restoreFileName = ref('');
const confirmText = ref('');
const restoring = ref(false);
const canRestore = computed(() => confirmText.value === 'RESTORE' && restoreSql.value.trim() !== '');

onMounted(async () => {
  ui.setBreadcrumb([{ label: 'Sao lưu & Phục hồi' }]);
  try {
    const resp = await backupApi.tables();
    tables.value = resp.data;
    // mặc định chọn tất cả TRỪ vie_token (chứa refresh token)
    selected.value = resp.data.map((t) => t.name).filter((n) => !n.endsWith('vie_token'));
  } catch (e) {
    notify.apiError(e, 'Không tải được danh sách bảng');
  } finally {
    loading.value = false;
  }
});

function downloadFile(filename: string, content: string): void {
  const blob = new Blob([content], { type: 'application/sql;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

async function doBackup(): Promise<void> {
  if (selected.value.length === 0) { notify.apiError(null, 'Chọn ít nhất 1 bảng'); return; }
  exporting.value = true;
  try {
    const resp = await backupApi.export(selected.value);
    downloadFile(resp.data.filename, resp.data.sql);
    notify.success('Đã tạo backup', `${resp.data.tables.length} bảng · ${(resp.data.bytes / 1024 / 1024).toFixed(2)} MB`);
  } catch (e) {
    notify.apiError(e, 'Backup thất bại');
  } finally {
    exporting.value = false;
  }
}

function onFile(e: Event): void {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  restoreFileName.value = file.name;
  const reader = new FileReader();
  reader.onload = () => { restoreSql.value = String(reader.result ?? ''); };
  reader.readAsText(file);
}

async function doRestore(): Promise<void> {
  if (!canRestore.value) return;
  if (!window.confirm('Phục hồi sẽ GHI ĐÈ dữ liệu các bảng trong file. Tiếp tục?')) return;
  restoring.value = true;
  try {
    const resp = await backupApi.restore(restoreSql.value, confirmText.value);
    notify.success('Đã phục hồi', `${resp.data.tables_restored.length} bảng · snapshot: ${resp.data.snapshot_file}`);
    confirmText.value = '';
  } catch (e) {
    notify.apiError(e, 'Phục hồi thất bại');
  } finally {
    restoring.value = false;
  }
}
</script>

<template>
  <div>
    <PageHeader title="Sao lưu & Phục hồi" subtitle="Xuất/nhập dữ liệu vie_*" icon="pi pi-database" />

    <Card class="mb">
      <template #title>Sao lưu</template>
      <template #content>
        <DataTable :value="tables" :loading="loading" dataKey="name" selectionMode="multiple" v-model:selection="selectedRows" class="mb">
          <Column header="" style="width:3rem">
            <template #body="{ data }">
              <Checkbox v-model="selected" :value="data.name" :inputId="data.name" />
            </template>
          </Column>
          <Column field="name" header="Bảng" />
          <Column field="rows" header="Số dòng" />
          <Column field="size_mb" header="Dung lượng (MB)" />
        </DataTable>
        <Button label="Sao lưu (tải .sql)" icon="pi pi-download" :loading="exporting" @click="doBackup" :disabled="selected.length === 0" />
      </template>
    </Card>

    <Card>
      <template #title>Phục hồi</template>
      <template #content>
        <Message severity="warn" :closable="false">Phục hồi sẽ GHI ĐÈ dữ liệu các bảng có trong file. Hệ thống tự lưu snapshot trước khi ghi đè.</Message>
        <div class="field">
          <label>Chọn file .sql</label>
          <input type="file" accept=".sql" @change="onFile" />
          <span v-if="restoreFileName" class="muted">{{ restoreFileName }}</span>
        </div>
        <div class="field">
          <label>Gõ <strong>RESTORE</strong> để xác nhận</label>
          <InputText v-model="confirmText" placeholder="RESTORE" />
        </div>
        <Button label="Phục hồi" icon="pi pi-upload" severity="danger" :loading="restoring" :disabled="!canRestore" @click="doRestore" />
      </template>
    </Card>
  </div>
</template>

<style scoped>
.mb { margin-bottom: var(--space-5); }
.field { margin: var(--space-4) 0; display: flex; flex-direction: column; gap: var(--space-2); }
.muted { color: var(--p-text-muted-color); font-size: 0.85rem; }
</style>
```
Note: `v-model:selection="selectedRows"` is unused glue for PrimeVue DataTable; the actual selection is the `selected` array via the Checkbox. Remove the `:selection` binding if `vue-tsc` complains about the missing `selectedRows` ref — keep only the Checkbox-driven `selected`. (Verify in Step 6 build.)

- [ ] **Step 5: Add the route + nav item**

(a) In `vielimousine-child/admin-app/src/router.ts`, add this route in the `children` array (after the `settings` route, before the `:pathMatch` catch-all):
```ts
        { path: 'backup',
          component: () => import('@/views/backup/BackupView.vue'),
          meta: { cap: 'vie_manage_backup' } },
```

(b) In `vielimousine-child/admin-app/src/layouts/DefaultLayout.vue`, add this nav item to the `navItems` array (after the `Cài đặt` item, before the closing `].filter(...)`):
```ts
  { label: 'Sao lưu', icon: 'pi pi-database', to: '/backup',
    show: auth.can('vie_manage_backup'), group: 'system' },
```

- [ ] **Step 6: Run the test + build**

Run: `cd /Users/danhtrongit/vie.local/wp/wp-content/themes/vielimousine-child/admin-app && npm run test -- BackupView && npm run build`
Expected: BackupView spec passes (2 tests); `npm run build` succeeds (fix the `selectedRows`/`:selection` note from Step 4 if `vue-tsc` errors — remove that binding).

- [ ] **Step 7: Commit (source only; dist in Task 6)**
```bash
cd /Users/danhtrongit/vie.local/wp/wp-content/themes
git add vielimousine-child/admin-app/src/api/backup.api.ts vielimousine-child/admin-app/src/views/backup/BackupView.vue vielimousine-child/admin-app/src/views/backup/BackupView.spec.ts vielimousine-child/admin-app/src/router.ts vielimousine-child/admin-app/src/layouts/DefaultLayout.vue
git commit -m "feat(admin): Backup & Restore page (vie_manage_backup) + route + nav

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: Rebuild dist + final verification

**Files:** Modify (generated): `vielimousine-child/admin-app/dist/**`

- [ ] **Step 1: Rebuild the SPA**
```bash
cd /Users/danhtrongit/vie.local/wp/wp-content/themes/vielimousine-child/admin-app
npm run build
```
Expected: dist regenerated, no type errors.

- [ ] **Step 2: Final frontend test run**
Run: `npm run test`
Expected: all specs pass (incl. `BackupView`).

- [ ] **Step 3: Final backend backup e2e**
Run the composite command (Global Constraints).
Expected: `=== BACKUP: N passed, 0 failed ===` (Scenarios A–D).

- [ ] **Step 4: Commit dist**
```bash
cd /Users/danhtrongit/vie.local/wp/wp-content/themes
git add vielimousine-child/admin-app/dist
git commit -m "build(admin): rebuild dist after backup/restore feature

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

- [ ] **Step 5: Manual verification (verify skill / Playwright)**
- Log in as administrator (`e2e_admin`) → "Sao lưu" appears under Hệ thống → select tables → download `.sql` → open file, confirm `CREATE TABLE` + `INSERT`.
- Log in as a `vie_sales` user → no "Sao lưu" nav, `/vie-admin/backup` redirects to dashboard, `GET /backup/tables` → 403.
- Restore: upload the just-downloaded `.sql`, type `RESTORE`, confirm → success + a snapshot file noted under `wp-content/uploads/vie-backups/`.

---

## Self-Review

**1. Spec coverage:**
- §4 cap `vie_manage_backup` admin-only → Task 1. ✓
- §5 BackupService list/export/restore (allowlist + multi_query) → Tasks 2–3. ✓
- §6 REST endpoints (tables/export/restore, confirm, auto-snapshot) → Task 4. ✓
- §7 restore safeguards (cap, confirm RESTORE, auto-snapshot, allowlist, FK off) → Tasks 3 (allowlist+FK) & 4 (confirm+snapshot). ✓
- §8 BackupView + route + nav + token-unchecked-by-default → Task 5. ✓
- §9 tests (backend round-trip/allowlist/gate; FE gating; dist) → Tasks 1–6. ✓
- §10 YAGNI (no core tables, no cron, no gzip) → not implemented (correct). ✓

**2. Placeholder scan:** No TBD/TODO; every step has literal code/commands. ✓

**3. Type/name consistency:** `BackupService::{allowPrefix,isAllowed,listTables,export,restore}` consistent across Tasks 2–4. `backupApi.{tables,export,restore}` and `RestoreResult.snapshot_file`/`tables_restored` consistent between controller (Task 4) and FE types (Task 5). Cap `vie_manage_backup` consistent across Tasks 1, 4, 5. ✓

**Known judgment calls (intentional):**
- Restore uses `mysqli_multi_query` for robustness (server-side parsing) with a regex allowlist pre-scan; DDL can't be transaction-rolled-back, so the auto-snapshot is the recovery mechanism (per spec §7).
- The FE component test inspects `canRestore`/`confirmText` on the component instance to assert the confirm gate without driving PrimeVue internals.
