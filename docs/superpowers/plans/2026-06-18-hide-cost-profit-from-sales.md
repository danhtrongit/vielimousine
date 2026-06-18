# Hide Cost & Profit from Sales — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hide `cost_total` (Giá vốn) and `profit_total` (Lợi nhuận dự kiến) from the `vie_sales` role in both the UI and the REST API (read + write).

**Architecture:** Reuse the existing `vie_view_reports` capability as the gate. Backend: a single `CostVisibility` helper strips the two fields from order/order-item responses (and blocks them on write) unless `current_user_can('vie_view_reports')`, applied at the controller boundary only. Frontend: a `useCostVisibility()` composable drives `v-if` on the columns/card and conditional CSV columns.

**Tech Stack:** PHP 8 (WordPress, namespace `Vie\`, SPL autoload `Vie\`→`inc/src/`); Vue 3.5 + TypeScript 5.7 + Vite 6.3 (admin SPA); new test deps vitest ^3 + @vue/test-utils ^2.4 + jsdom.

## Global Constraints

- Gate capability (reused, no new cap, no migration): `vie_view_reports`. Backend check: `current_user_can('vie_view_reports')`; frontend: `auth.can('vie_view_reports')`.
- Sensitive fields (exact names): `cost_total`, `profit_total`.
- Strip ONLY at the controller/API boundary — never in Repository/Service internals (internal calculations rely on the values).
- Do NOT modify the Reports area or `/reports/*` (already gated by `vie_view_reports`; sales already blocked).
- `MobileCta.vue` and the order-draft feature are unrelated — do not touch.
- Backend tests run inside the `vie_cli` Docker container (WordPress at `/var/www/html`, theme at `/var/www/html/wp-content/themes/vielimousine-child`) via `wp eval` + `require` (NOT `wp eval-file`).
- Frontend build/test run on the host in `vielimousine-child/admin-app`. The project commits the built `dist/`; deploy is `git pull` on cPanel — so `dist` MUST be rebuilt and committed.
- Execute on a dedicated branch (see Task 0), not on `main`.

### Backend test commands (copy verbatim)

Reset SecuritySweep before re-running the full suite (auth/security e2e IP-block `127.0.0.1` on repeats):
```bash
docker exec vie_cli wp --path=/var/www/html --allow-root eval 'global $wpdb;$wpdb->query("DELETE FROM {$wpdb->prefix}vie_activity_log WHERE action=\"login_failed\"");delete_option("vie_blocked_ips");'
```

Focused run of just the new cost-visibility test (fast TDD loop):
```bash
docker exec vie_cli wp --path=/var/www/html --allow-root eval '$pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/cost-visibility-e2e.php"; echo "\n--- cost-visibility: {$pass} passed, {$fail} failed ---\n"; exit($fail===0?0:1);'
```

Full backend suite (NOTE — pre-existing baseline: this aborts inside `auth-e2e.php` at `JwtService.php:60`; the JWT `iss` fix lived on the reverted mobile branch, so `run.php` does NOT reach `=== TOTAL ===` on this branch. That crash is out of scope for this feature — do not try to fix it):
```bash
docker exec vie_cli wp --path=/var/www/html --allow-root eval 'require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/run.php";'
```

**Cost-visibility verification command (USE THIS to verify this feature's backend tests).** It seeds order/item data via `order-e2e`, resets the counters, runs only `cost-visibility-e2e`, and skips the broken auth/security phases:
```bash
docker exec vie_cli wp --path=/var/www/html --allow-root eval '$pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/order-e2e.php"; $pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/cost-visibility-e2e.php"; echo "\n=== COST-VIS: {$pass} passed, {$fail} failed ===\n"; exit($fail===0?0:1);'
```
For the RED step of a TDD task, run the same command but expect the new assertions to FAIL (or a fatal if a class is missing).

---

## Task 0: Create work branch

**Files:** none (git only)

- [ ] **Step 1: Branch off main**

```bash
cd /Users/danhtrongit/vie.local/wp/wp-content/themes
git checkout -b feat/hide-cost-profit-from-sales
```

- [ ] **Step 2: Confirm clean start**

Run: `git status -sb`
Expected: on `feat/hide-cost-profit-from-sales`; only the untracked `docs/` spec/plan files present.

---

## Task 1: `CostVisibility` backend helper (+ unit e2e)

**Files:**
- Create: `vielimousine-child/inc/src/Support/CostVisibility.php`
- Create (test): `vielimousine-child/inc/tests/cost-visibility-e2e.php`
- Modify: `vielimousine-child/inc/tests/run.php` (wire the new test in)

**Interfaces:**
- Produces:
  - `Vie\Support\CostVisibility::canView(): bool`
  - `CostVisibility::stripOrder(array $order): array` — removes `cost_total`/`profit_total` from the order and, if present, each row in `$order['items']`
  - `CostVisibility::stripOrders(array $orders): array`
  - `CostVisibility::stripItemRow(array $row): array`
  - `CostVisibility::stripItemRows(array $rows): array`
  - `CostVisibility::stripWritable(array $data): array`
  - All are no-ops (return input unchanged) when `canView()` is true.

- [ ] **Step 1: Write the failing test**

Create `vielimousine-child/inc/tests/cost-visibility-e2e.php`:

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run:
```bash
docker exec vie_cli wp --path=/var/www/html --allow-root eval '$pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/cost-visibility-e2e.php"; echo "\n--- cost-visibility: {$pass} passed, {$fail} failed ---\n"; exit($fail===0?0:1);'
```
Expected: PHP fatal `Class "Vie\Support\CostVisibility" not found` (the helper does not exist yet).

- [ ] **Step 3: Write the minimal implementation**

Create `vielimousine-child/inc/src/Support/CostVisibility.php`:

```php
<?php
declare(strict_types=1);

namespace Vie\Support;

/**
 * Controls visibility of financial fields (cost_total / profit_total) at the
 * REST boundary. Authorized = current user holds the vie_view_reports capability.
 * Strip helpers are no-ops for authorized users.
 */
final class CostVisibility
{
    /** @var string[] */
    public const FIELDS = ['cost_total', 'profit_total'];

    public static function canView(): bool
    {
        return current_user_can('vie_view_reports');
    }

    /** Strip cost/profit from one order row and its nested items[] (if any). */
    public static function stripOrder(array $order): array
    {
        if (self::canView()) {
            return $order;
        }
        foreach (self::FIELDS as $f) {
            unset($order[$f]);
        }
        if (isset($order['items']) && is_array($order['items'])) {
            $order['items'] = self::stripItemRows($order['items']);
        }
        return $order;
    }

    /** @param array<int,array> $orders */
    public static function stripOrders(array $orders): array
    {
        if (self::canView()) {
            return $orders;
        }
        return array_map([self::class, 'stripOrder'], $orders);
    }

    public static function stripItemRow(array $row): array
    {
        if (self::canView()) {
            return $row;
        }
        foreach (self::FIELDS as $f) {
            unset($row[$f]);
        }
        return $row;
    }

    /** @param array<int,array> $rows */
    public static function stripItemRows(array $rows): array
    {
        if (self::canView()) {
            return $rows;
        }
        return array_map([self::class, 'stripItemRow'], $rows);
    }

    /** Remove cost/profit from a validated write payload unless authorized. */
    public static function stripWritable(array $data): array
    {
        if (self::canView()) {
            return $data;
        }
        foreach (self::FIELDS as $f) {
            unset($data[$f]);
        }
        return $data;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run:
```bash
docker exec vie_cli wp --path=/var/www/html --allow-root eval '$pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/cost-visibility-e2e.php"; echo "\n--- cost-visibility: {$pass} passed, {$fail} failed ---\n"; exit($fail===0?0:1);'
```
Expected: all `✓`, ends `--- cost-visibility: 17 passed, 0 failed ---`, exit 0.

- [ ] **Step 5: Wire the new test into the master runner**

Modify `vielimousine-child/inc/tests/run.php` — add a section immediately after the existing Order E2E `require` (so a real order exists for the integration scenarios added in Tasks 2–3). Find:

```php
echo "\n=== Phase 4 — Order E2E ===\n\n";
require __DIR__ . '/order-e2e.php';
```

Insert directly below it:

```php
echo "\n=== Cost/Profit Visibility E2E ===\n\n";
require __DIR__ . '/cost-visibility-e2e.php';
```

- [ ] **Step 6: Run the full suite to confirm nothing regressed**

```bash
docker exec vie_cli wp --path=/var/www/html --allow-root eval '$pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/order-e2e.php"; $pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/cost-visibility-e2e.php"; echo "\n=== COST-VIS: {$pass} passed, {$fail} failed ===\n"; exit($fail===0?0:1);'
```
Expected: ends `=== COST-VIS: N passed, 0 failed ===`, including the new "Cost/Profit Visibility E2E" section all `✓`.

- [ ] **Step 7: Commit**

```bash
git add vielimousine-child/inc/src/Support/CostVisibility.php vielimousine-child/inc/tests/cost-visibility-e2e.php vielimousine-child/inc/tests/run.php
git commit -m "feat(orders): CostVisibility helper to gate cost/profit by vie_view_reports

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Apply strip + write-block in `OrderController`

**Files:**
- Modify: `vielimousine-child/inc/src/Http/Controllers/OrderController.php` (`index`, `show`, `store`, `update`)
- Modify (test): `vielimousine-child/inc/tests/cost-visibility-e2e.php` (add Scenario C)

**Interfaces:**
- Consumes: `Vie\Support\CostVisibility` (Task 1).

- [ ] **Step 1: Add the failing test (Scenario C)**

Append to `vielimousine-child/inc/tests/cost-visibility-e2e.php`, immediately BEFORE the final `wp_set_current_user(0);` line:

```php
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
```

- [ ] **Step 2: Run the full suite to verify the new Scenario C assertions pass for the authorized path and define behavior**

```bash
docker exec vie_cli wp --path=/var/www/html --allow-root eval '$pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/order-e2e.php"; $pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/cost-visibility-e2e.php"; echo "\n=== COST-VIS: {$pass} passed, {$fail} failed ===\n"; exit($fail===0?0:1);'
```
Expected: Scenario C `✓` (order-e2e ran first, so an order exists). This confirms the admin path returns the fields and the sales transform strips them. (This step has no RED because Scenario C asserts the helper transform + the authorized controller path; the controller wiring in Step 3 is what guarantees the helper is actually invoked in production.)

- [ ] **Step 3: Wire `CostVisibility` into `OrderController`**

In `vielimousine-child/inc/src/Http/Controllers/OrderController.php`, add the import after the existing `use Vie\Support\ResponseEnvelope;` line:

```php
use Vie\Support\CostVisibility;
```

Change `index` (currently lines 43-53) — strip the row array before paginating:

```php
    public static function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo   = Container::get(OrderRepository::class);
        $result = $repo->all($request->get_params());

        return ResponseEnvelope::paginated(CostVisibility::stripOrders($result['data']), $result['pagination'], [
            'sort'            => $result['sort'],
            'filters_applied' => $result['filters_applied'],
            'available_sorts' => $repo->availableSorts(),
        ]);
    }
```

Change the success return in `show` (currently line 70) from `return ResponseEnvelope::success($detail);` to:

```php
            return ResponseEnvelope::success(CostVisibility::stripOrder($detail));
```

Change the success return in `store` (currently line 111) from `return ResponseEnvelope::success($detail, [], 201);` to:

```php
            return ResponseEnvelope::success(CostVisibility::stripOrder($detail), [], 201);
```

Change `update` (currently lines 153-155) to strip the writable payload before persisting AND strip the response:

```php
        $repo = Container::get(OrderRepository::class);
        $row  = $repo->update($id, CostVisibility::stripWritable($v->validated()));
        return ResponseEnvelope::success(CostVisibility::stripOrder($row));
```

- [ ] **Step 4: Re-run the full suite — confirm green and no regression**

```bash
docker exec vie_cli wp --path=/var/www/html --allow-root eval '$pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/order-e2e.php"; $pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/cost-visibility-e2e.php"; echo "\n=== COST-VIS: {$pass} passed, {$fail} failed ===\n"; exit($fail===0?0:1);'
```
Expected: `=== COST-VIS: N passed, 0 failed ===`. The "admin show() keeps cost_total" assertion still passes (admin authorized), confirming we did not over-strip.

- [ ] **Step 5: Commit**

```bash
git add vielimousine-child/inc/src/Http/Controllers/OrderController.php vielimousine-child/inc/tests/cost-visibility-e2e.php
git commit -m "feat(orders): strip cost/profit from order responses + block write unless vie_view_reports

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Apply strip in `OrderItemController`

**Files:**
- Modify: `vielimousine-child/inc/src/Http/Controllers/OrderItemController.php` (`index`, `show`)
- Modify (test): `vielimousine-child/inc/tests/cost-visibility-e2e.php` (add Scenario D)

**Interfaces:**
- Consumes: `Vie\Support\CostVisibility` (Task 1).

`/order-items` index/show require only `vie_view_own_orders` (which sales has) and are NOT user-scoped, so sales can read every item's cost/profit. This is a real leak with a clean controller-level test.

- [ ] **Step 1: Add the failing test (Scenario D)**

Append to `vielimousine-child/inc/tests/cost-visibility-e2e.php`, immediately BEFORE the final `wp_set_current_user(0);` line:

```php
echo "Scenario D: OrderItemController::index strips cost/profit for sales\n";
$itemId = (int) $GLOBALS['wpdb']->get_var("SELECT id FROM {$GLOBALS['wpdb']->prefix}vie_order_item ORDER BY id DESC LIMIT 1");
if ($itemId > 0) {
    wp_set_current_user($salesId);
    $req = new \WP_REST_Request('GET', '/order-items');
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

    // Authorized path unchanged.
    wp_set_current_user($mgrId);
    $mrows = OrderItemController::index($req)->get_data()['data'] ?? [];
    $assert('admin order-items index keeps cost_total', !empty($mrows) && array_key_exists('cost_total', $mrows[0]));
} else {
    echo "  • skip Scenario D — no order_item rows (run full suite for integration)\n";
}
```

- [ ] **Step 2: Run the full suite to verify it fails**

```bash
docker exec vie_cli wp --path=/var/www/html --allow-root eval '$pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/order-e2e.php"; $pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/cost-visibility-e2e.php"; echo "\n=== COST-VIS: {$pass} passed, {$fail} failed ===\n"; exit($fail===0?0:1);'
```
Expected: FAIL on "sales order-items index has no cost_total" / "...show has no cost_total" — the controller still returns the fields to sales.

- [ ] **Step 3: Wire `CostVisibility` into `OrderItemController`**

In `vielimousine-child/inc/src/Http/Controllers/OrderItemController.php`, add the import after `use Vie\Support\ResponseEnvelope;`:

```php
use Vie\Support\CostVisibility;
```

Change `index` (currently lines 19-23) to strip rows:

```php
        return ResponseEnvelope::paginated(CostVisibility::stripItemRows($result['data']), $result['pagination'], [
            'sort'            => $result['sort'],
            'filters_applied' => $result['filters_applied'],
            'available_sorts' => $repo->availableSorts(),
        ]);
```

Change the success return in `show` (currently line 35) from `return ResponseEnvelope::success($row);` to:

```php
        return ResponseEnvelope::success(CostVisibility::stripItemRow($row));
```

- [ ] **Step 4: Re-run the full suite to verify it passes**

```bash
docker exec vie_cli wp --path=/var/www/html --allow-root eval '$pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/order-e2e.php"; $pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/cost-visibility-e2e.php"; echo "\n=== COST-VIS: {$pass} passed, {$fail} failed ===\n"; exit($fail===0?0:1);'
```
Expected: `=== COST-VIS: N passed, 0 failed ===`, Scenario D all `✓`.

- [ ] **Step 5: Commit**

```bash
git add vielimousine-child/inc/src/Http/Controllers/OrderItemController.php vielimousine-child/inc/tests/cost-visibility-e2e.php
git commit -m "feat(order-items): strip cost/profit from order-item responses unless vie_view_reports

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Set up the frontend vitest harness

**Files:**
- Modify: `vielimousine-child/admin-app/package.json` (devDeps + `test` script)
- Modify: `vielimousine-child/admin-app/vite.config.ts` (add `test` block)
- Modify: `vielimousine-child/admin-app/tsconfig.json` (exclude specs from the build typecheck)
- Create (test): `vielimousine-child/admin-app/src/__harness__.spec.ts`

All frontend commands run on the host from `vielimousine-child/admin-app`.

- [ ] **Step 1: Install dev dependencies**

```bash
cd /Users/danhtrongit/vie.local/wp/wp-content/themes/vielimousine-child/admin-app
npm install -D vitest@^3 @vue/test-utils@^2.4 jsdom@^25
```

- [ ] **Step 2: Add the `test` script to `package.json`**

In `vielimousine-child/admin-app/package.json`, change the `scripts` block to:

```json
  "scripts": {
    "dev": "vite",
    "build": "vue-tsc --noEmit && vite build",
    "preview": "vite preview",
    "test": "vitest run"
  },
```

- [ ] **Step 3: Add the vitest `test` block to `vite.config.ts`**

Replace the import line `import { defineConfig } from 'vite';` with:

```ts
/// <reference types="vitest/config" />
import { defineConfig } from 'vitest/config';
```

And add a `test` property to the config object (sibling of `build` / `server`):

```ts
  test: {
    environment: 'jsdom',
    include: ['src/**/*.spec.ts'],
  },
```

- [ ] **Step 4: Keep spec files out of the production typecheck**

In `vielimousine-child/admin-app/tsconfig.json`, add an `exclude` key (sibling of `include`) so `vue-tsc --noEmit` (the build) ignores test files:

```json
  "include": ["src/**/*.ts", "src/**/*.vue", "env.d.ts"],
  "exclude": ["src/**/*.spec.ts"]
```

- [ ] **Step 5: Write a harness sanity spec**

Create `vielimousine-child/admin-app/src/__harness__.spec.ts`:

```ts
import { describe, it, expect } from 'vitest';

describe('vitest harness', () => {
  it('runs', () => {
    expect(1 + 1).toBe(2);
  });

  it('has a jsdom document', () => {
    expect(typeof document).toBe('object');
    expect(document.createElement('div').tagName).toBe('DIV');
  });
});
```

- [ ] **Step 6: Run the tests to verify the harness works**

Run: `npm run test`
Expected: PASS — 1 file, 2 tests passing.

- [ ] **Step 7: Verify the production build still typechecks (specs excluded)**

Run: `npm run build`
Expected: build succeeds (no `vue-tsc` errors from the spec file).

- [ ] **Step 8: Commit (exclude `node_modules`; commit `package-lock.json` if present)**

```bash
git add vielimousine-child/admin-app/package.json vielimousine-child/admin-app/vite.config.ts vielimousine-child/admin-app/tsconfig.json vielimousine-child/admin-app/src/__harness__.spec.ts
git add vielimousine-child/admin-app/package-lock.json 2>/dev/null || true
git commit -m "test(admin): add vitest + @vue/test-utils + jsdom harness

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: `useCostVisibility` composable

**Files:**
- Create: `vielimousine-child/admin-app/src/composables/useCostVisibility.ts`
- Create (test): `vielimousine-child/admin-app/src/composables/useCostVisibility.spec.ts`

**Interfaces:**
- Produces: `useCostVisibility(): { canViewCost: ComputedRef<boolean> }` where `canViewCost.value === auth.can('vie_view_reports')`.

- [ ] **Step 1: Write the failing test**

Create `vielimousine-child/admin-app/src/composables/useCostVisibility.spec.ts`:

```ts
import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '@/stores/auth.store';
import type { AuthUser } from '@/types/auth';
import { useCostVisibility } from './useCostVisibility';

function setCaps(caps: string[]): void {
  useAuthStore().user = { caps } as unknown as AuthUser;
}

describe('useCostVisibility', () => {
  beforeEach(() => setActivePinia(createPinia()));

  it('is true when the user has vie_view_reports', () => {
    setCaps(['vie_view_reports', 'vie_view_own_orders']);
    expect(useCostVisibility().canViewCost.value).toBe(true);
  });

  it('is false for a sales user lacking vie_view_reports', () => {
    setCaps(['vie_view_own_orders', 'vie_create_orders']);
    expect(useCostVisibility().canViewCost.value).toBe(false);
  });

  it('is false when there is no user', () => {
    expect(useCostVisibility().canViewCost.value).toBe(false);
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npm run test -- useCostVisibility`
Expected: FAIL — cannot resolve `./useCostVisibility` (module does not exist).

- [ ] **Step 3: Write the minimal implementation**

Create `vielimousine-child/admin-app/src/composables/useCostVisibility.ts`:

```ts
import { computed, type ComputedRef } from 'vue';
import { useAuthStore } from '@/stores/auth.store';

/**
 * Single source of truth for who may see financial fields (giá vốn / lợi nhuận).
 * Gated by the vie_view_reports capability (sales role lacks it).
 */
export function useCostVisibility(): { canViewCost: ComputedRef<boolean> } {
  const auth = useAuthStore();
  const canViewCost = computed(() => auth.can('vie_view_reports'));
  return { canViewCost };
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `npm run test -- useCostVisibility`
Expected: PASS — 3 tests.

- [ ] **Step 5: Commit**

```bash
git add vielimousine-child/admin-app/src/composables/useCostVisibility.ts vielimousine-child/admin-app/src/composables/useCostVisibility.spec.ts
git commit -m "feat(admin): useCostVisibility composable gated by vie_view_reports

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: Hide cost/profit in `OrderListView` (columns + CSV)

**Files:**
- Create: `vielimousine-child/admin-app/src/views/orders/ordersCsv.ts`
- Create (test): `vielimousine-child/admin-app/src/views/orders/ordersCsv.spec.ts`
- Create (test): `vielimousine-child/admin-app/src/views/orders/OrderListView.spec.ts`
- Modify: `vielimousine-child/admin-app/src/views/orders/OrderListView.vue`

**Interfaces:**
- Consumes: `useCostVisibility` (Task 5).
- Produces: `ordersCsvHeaders(canViewCost: boolean): string[]`, `orderToCsvRow(o: Order, canViewCost: boolean): unknown[]`.

- [ ] **Step 1: Write the failing CSV-helper test**

Create `vielimousine-child/admin-app/src/views/orders/ordersCsv.spec.ts`:

```ts
import { describe, it, expect } from 'vitest';
import type { Order } from '@/types/order';
import { ordersCsvHeaders, orderToCsvRow } from './ordersCsv';

const order = {
  code: 'VIE0001', customer_name: 'A', checkin: '2026-01-01', checkout: '2026-01-02',
  hotel_names: 'H', nights: 1, total: 100, hotel_subtotal: 80, ticket_subtotal: 20,
  cost_total: 60, profit_total: 40, paid_amount: 50, status: 'pending',
  created_at: '2026-01-01T00:00:00Z',
} as unknown as Order;

describe('ordersCsv', () => {
  it('omits cost/profit headers when canViewCost=false', () => {
    const h = ordersCsvHeaders(false);
    expect(h).not.toContain('Tổng giá vốn');
    expect(h).not.toContain('Lợi nhuận dự kiến');
  });

  it('includes cost/profit headers when canViewCost=true', () => {
    const h = ordersCsvHeaders(true);
    expect(h).toContain('Tổng giá vốn');
    expect(h).toContain('Lợi nhuận dự kiến');
  });

  it('row length always matches header length', () => {
    expect(orderToCsvRow(order, false).length).toBe(ordersCsvHeaders(false).length);
    expect(orderToCsvRow(order, true).length).toBe(ordersCsvHeaders(true).length);
  });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `npm run test -- ordersCsv`
Expected: FAIL — cannot resolve `./ordersCsv`.

- [ ] **Step 3: Implement the CSV helper**

Create `vielimousine-child/admin-app/src/views/orders/ordersCsv.ts`:

```ts
import type { Order } from '@/types/order';
import { formatVND, formatDate, formatDateTime } from '@/composables/useFormat';
import { labelOrderStatus } from '@/stores/lookup.store';

/** CSV column headers; cost/profit columns only when the viewer is authorized. */
export function ordersCsvHeaders(canViewCost: boolean): string[] {
  return [
    'Mã đơn', 'Tên khách hàng', 'Check in', 'Check out', 'Tên khách sạn', 'Đêm',
    'Tổng tiền', 'Tổng khách sạn', 'Tổng chi phí vé',
    ...(canViewCost ? ['Tổng giá vốn', 'Lợi nhuận dự kiến'] : []),
    'Đã thanh toán', 'Chưa thanh toán', 'Trạng thái', 'Tạo lúc',
  ];
}

/** One CSV row for an order; cost/profit cells only when authorized (must match headers). */
export function orderToCsvRow(o: Order, canViewCost: boolean): unknown[] {
  const remaining = Math.max(0, Number(o.total ?? 0) - Number(o.paid_amount ?? 0));
  return [
    o.code,
    o.customer_name,
    formatDate(o.checkin),
    formatDate(o.checkout),
    o.hotel_names ?? '',
    o.nights,
    formatVND(o.total),
    formatVND(o.hotel_subtotal),
    formatVND(o.ticket_subtotal),
    ...(canViewCost ? [formatVND(o.cost_total), formatVND(o.profit_total)] : []),
    formatVND(o.paid_amount),
    formatVND(remaining),
    labelOrderStatus(o.status),
    formatDateTime(o.created_at),
  ];
}
```

- [ ] **Step 4: Run to verify the helper test passes**

Run: `npm run test -- ordersCsv`
Expected: PASS — 3 tests.

- [ ] **Step 5: Write the failing component test**

Create `vielimousine-child/admin-app/src/views/orders/OrderListView.spec.ts`:

```ts
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const caps = vi.hoisted(() => ({ value: [] as string[] }));

vi.mock('@/stores/auth.store', () => ({
  useAuthStore: () => ({
    can: (c: string) => caps.value.includes(c),
    canAny: (cs: string[]) => cs.some((c) => caps.value.includes(c)),
  }),
}));
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  useRoute: () => ({ query: {} }),
}));
vi.mock('@/api/orders.api', () => ({
  ordersApi: { list: vi.fn().mockResolvedValue({ data: [] }), deleteDraft: vi.fn() },
}));
vi.mock('@/composables/useNotify', () => ({ useNotify: () => ({ success: vi.fn(), apiError: vi.fn() }) }));
vi.mock('@/composables/useCsvExport', () => ({ useCsvExport: () => ({ downloadCsv: vi.fn() }) }));
vi.mock('@/stores/ui.store', () => ({ useUIStore: () => ({ setBreadcrumb: vi.fn() }) }));
vi.mock('@/stores/lookup.store', () => ({
  ORDER_STATUSES: [], PAYMENT_STATUSES: [], ORDER_SOURCES: [],
  labelOrderStatus: (v: string) => v,
}));

import OrderListView from './OrderListView.vue';

function mountView() {
  return mount(OrderListView, {
    global: {
      stubs: {
        DataTablePanel: { template: '<div><slot /></div>' },
        Column: { props: ['header'], template: '<div class="col">{{ header }}</div>' },
        FilterBar: true,
        StatusTag: true,
        PageHeader: { template: '<div><slot /></div>' },
        Can: { template: '<div><slot /></div>' },
        Button: true,
        RouterLink: true,
      },
    },
  });
}

describe('OrderListView cost/profit columns', () => {
  beforeEach(() => { caps.value = []; });

  it('hides the cost & profit columns when lacking vie_view_reports', () => {
    caps.value = ['vie_view_own_orders'];
    const html = mountView().html();
    expect(html).not.toContain('Tổng giá vốn');
    expect(html).not.toContain('Lợi nhuận dự kiến');
  });

  it('shows the cost & profit columns when holding vie_view_reports', () => {
    caps.value = ['vie_view_reports', 'vie_view_own_orders'];
    const html = mountView().html();
    expect(html).toContain('Tổng giá vốn');
    expect(html).toContain('Lợi nhuận dự kiến');
  });
});
```

- [ ] **Step 6: Run to verify it fails**

Run: `npm run test -- OrderListView`
Expected: FAIL — the columns currently render unconditionally, so the "hides" test fails (html contains the headers regardless of caps).

- [ ] **Step 7: Modify `OrderListView.vue`**

(a) In `<script setup>`, add the import after the other `@/composables` imports (e.g. after the `useCsvExport` import on line 14):

```ts
import { useCostVisibility } from '@/composables/useCostVisibility';
import { ordersCsvHeaders, orderToCsvRow } from './ordersCsv';
```

(b) After `const csv = useCsvExport();` (line 23) add:

```ts
const { canViewCost } = useCostVisibility();
```

(c) Replace the body of `exportAll` (lines 63-101) so it uses the helpers — replace the existing `try { ... }` block contents that build `rows` and call `csv.downloadCsv` with:

```ts
async function exportAll() {
  exporting.value = true;
  try {
    const resp = await ordersApi.list({ per_page: 5000 });
    // Nháp là đơn chưa hoàn thiện — không đưa vào CSV xuất.
    const rows = resp.data
      .filter((o) => o.status !== 'draft')
      .map((o) => orderToCsvRow(o, canViewCost.value));
    const today = new Date().toISOString().slice(0, 10);
    csv.downloadCsv(`vie-orders-${today}.csv`, ordersCsvHeaders(canViewCost.value), rows);
    notify.success('Đã xuất CSV', `${rows.length} dòng`);
  } catch (e) {
    notify.apiError(e);
  } finally {
    exporting.value = false;
  }
}
```

(The local `formatDate`/`formatDateTime`/`labelOrderStatus` imports may now be unused in the `<script>` if not referenced elsewhere — they ARE still used by the template column bodies, so keep all existing imports. `noUnusedLocals` only flags truly unused identifiers; verify with the build in Step 9.)

(d) In the `<template>`, add `v-if="canViewCost"` to both money columns (currently lines 151-156):

```html
      <Column v-if="canViewCost" field="cost_total" header="Tổng giá vốn" sortable class="col-money">
        <template #body="{ data }">{{ formatVND(data.cost_total) }}</template>
      </Column>
      <Column v-if="canViewCost" field="profit_total" header="Lợi nhuận dự kiến" sortable class="col-money">
        <template #body="{ data }">{{ formatVND(data.profit_total) }}</template>
      </Column>
```

- [ ] **Step 8: Run to verify the component + helper tests pass**

Run: `npm run test -- OrderListView ordersCsv`
Expected: PASS — both spec files green.

- [ ] **Step 9: Typecheck the build**

Run: `npm run build`
Expected: succeeds with no `vue-tsc` unused-import/type errors. If `formatDate`/`formatDateTime`/`labelOrderStatus` are now unused in the script, remove only the unused names from the import on line 16-17 and re-run.

- [ ] **Step 10: Commit (source only; dist rebuilt in Task 8)**

```bash
git add vielimousine-child/admin-app/src/views/orders/ordersCsv.ts vielimousine-child/admin-app/src/views/orders/ordersCsv.spec.ts vielimousine-child/admin-app/src/views/orders/OrderListView.spec.ts vielimousine-child/admin-app/src/views/orders/OrderListView.vue
git commit -m "feat(admin): hide cost/profit columns + CSV in order list for non-reports roles

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: Hide the cost/profit card in `OrderDetailView`

**Files:**
- Create (test): `vielimousine-child/admin-app/src/views/orders/OrderDetailView.spec.ts`
- Modify: `vielimousine-child/admin-app/src/views/orders/OrderDetailView.vue`

**Interfaces:**
- Consumes: `useCostVisibility` (Task 5).

- [ ] **Step 1: Write the failing component test**

Create `vielimousine-child/admin-app/src/views/orders/OrderDetailView.spec.ts`:

```ts
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const caps = vi.hoisted(() => ({ value: [] as string[] }));

const orderDetail = {
  id: 1, code: 'VIE0001', status: 'pending', payment_status: 'pending',
  subtotal: 100, discount: 0, total: 100, paid_amount: 0,
  cost_total: 60, profit_total: 40,
  customer_name: 'A', customer_phone: '0900000000',
  items: [], payments: [], customer: null,
};

vi.mock('@/stores/auth.store', () => ({
  useAuthStore: () => ({
    can: (c: string) => caps.value.includes(c),
    canAny: (cs: string[]) => cs.some((c) => caps.value.includes(c)),
  }),
}));
vi.mock('vue-router', () => ({ useRoute: () => ({ params: { id: '1' } }) }));
vi.mock('@/api/orders.api', () => ({
  ordersApi: { get: vi.fn().mockResolvedValue({ data: orderDetail }) },
}));
vi.mock('@/api/payments.api', () => ({ paymentsApi: {} }));
vi.mock('@/composables/useNotify', () => ({ useNotify: () => ({ success: vi.fn(), apiError: vi.fn() }) }));
vi.mock('@/stores/ui.store', () => ({ useUIStore: () => ({ setBreadcrumb: vi.fn() }) }));
vi.mock('@/stores/lookup.store', () => ({
  labelBookingType: (v: string) => v, labelPaymentMethod: (v: string) => v,
  labelPaymentType: (v: string) => v, labelGateway: (v: string) => v,
}));

import OrderDetailView from './OrderDetailView.vue';

async function mountView() {
  const wrapper = mount(OrderDetailView, {
    global: {
      stubs: {
        Card: { template: '<div><slot name="title" /><slot name="content" /></div>' },
        Tabs: { template: '<div><slot /></div>' }, TabList: true, Tab: true,
        TabPanels: true, TabPanel: true, DataTable: true, Column: true,
        Button: true, Dialog: true, Textarea: true, InputNumber: true,
        InputText: true, Select: true, ProgressSpinner: true, StatusTag: true,
        Can: { template: '<div><slot /></div>' },
        InvoiceDialog: true,
        PageHeader: { template: '<div><slot /></div>' },
      },
    },
  });
  await flushPromises();
  return wrapper;
}

describe('OrderDetailView cost/profit card', () => {
  beforeEach(() => { caps.value = []; });

  it('hides the "Giá vốn & Lợi nhuận" card when lacking vie_view_reports', async () => {
    caps.value = ['vie_view_own_orders'];
    const html = (await mountView()).html();
    expect(html).not.toContain('Lợi nhuận');
  });

  it('shows the card when holding vie_view_reports', async () => {
    caps.value = ['vie_view_reports'];
    const html = (await mountView()).html();
    expect(html).toContain('Lợi nhuận');
  });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `npm run test -- OrderDetailView`
Expected: FAIL — the card renders unconditionally, so the "hides" test fails (`html` contains "Lợi nhuận" regardless of caps).

- [ ] **Step 3: Modify `OrderDetailView.vue`**

(a) In `<script setup>`, after `const auth = useAuthStore();` (line 35) add:

```ts
import { useCostVisibility } from '@/composables/useCostVisibility';
```

Place that `import` with the other imports at the top (after line 28 `import { useAuthStore } from '@/stores/auth.store';`), and after the `canPrintInvoice` computed (line 37) add:

```ts
const { canViewCost } = useCostVisibility();
```

(b) In the `<template>`, gate the cost/profit card (currently the `<Card>` opening at line 345). Change `<Card>` to:

```html
      <Card v-if="canViewCost">
        <template #title>Giá vốn &amp; Lợi nhuận</template>
```

(the rest of the card body and its closing `</Card>` at line 383 are unchanged).

- [ ] **Step 4: Run to verify it passes**

Run: `npm run test -- OrderDetailView`
Expected: PASS — both tests.

- [ ] **Step 5: Run the full frontend suite + typecheck**

Run: `npm run test && npm run build`
Expected: all spec files pass; build succeeds.

- [ ] **Step 6: Commit (source only)**

```bash
git add vielimousine-child/admin-app/src/views/orders/OrderDetailView.spec.ts vielimousine-child/admin-app/src/views/orders/OrderDetailView.vue
git commit -m "feat(admin): hide cost/profit card in order detail for non-reports roles

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Rebuild dist + final verification

**Files:**
- Modify (generated): `vielimousine-child/admin-app/dist/**`

- [ ] **Step 1: Rebuild the admin SPA**

```bash
cd /Users/danhtrongit/vie.local/wp/wp-content/themes/vielimousine-child/admin-app
npm run build
```
Expected: `dist/` regenerated, no type errors.

- [ ] **Step 2: Final full frontend test run**

Run: `npm run test`
Expected: all specs pass (`__harness__`, `useCostVisibility`, `ordersCsv`, `OrderListView`, `OrderDetailView`).

- [ ] **Step 3: Final full backend suite**

```bash
docker exec vie_cli wp --path=/var/www/html --allow-root eval '$pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/order-e2e.php"; $pass=0;$fail=0; require "/var/www/html/wp-content/themes/vielimousine-child/inc/tests/cost-visibility-e2e.php"; echo "\n=== COST-VIS: {$pass} passed, {$fail} failed ===\n"; exit($fail===0?0:1);'
```
Expected: `=== COST-VIS: N passed, 0 failed ===`.

- [ ] **Step 4: Commit the rebuilt dist**

```bash
cd /Users/danhtrongit/vie.local/wp/wp-content/themes
git add vielimousine-child/admin-app/dist
git commit -m "build(admin): rebuild dist after hiding cost/profit from sales

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

- [ ] **Step 5: Manual verification (verify skill / Playwright)**

- Log in as a `vie_sales` user → Orders list: no "Tổng giá vốn" / "Lợi nhuận dự kiến" columns; CSV export has no cost/profit columns; Order detail: no "Giá vốn & Lợi nhuận" card.
- Confirm via DevTools/Network (or curl with a sales token) that `GET /orders`, `GET /orders/{id}`, `GET /order-items` responses contain NO `cost_total`/`profit_total`.
- Log in as administrator / `vie_hotel_manager` → all cost/profit columns, card, CSV columns, and API fields still present.

---

## Self-Review

**1. Spec coverage:**
- §4.1 gate → Global Constraints + Tasks 1/5. ✓
- §4.2 read-strip (index/show/store/update + order-items index/show) → Tasks 2 & 3. ✓
- §4.3 write-block (PUT /orders) → Task 2 Step 3 (`stripWritable` in `update`). ✓
- §4.4 UI (OrderListView columns + CSV; OrderDetailView card) → Tasks 6 & 7. ✓
- §4.5 reports untouched → not modified (verified by not appearing in any task). ✓
- §5 testing (backend e2e; FE harness + composable + helper + view tests) → Tasks 1–7. ✓
- §5.3 rebuild dist → Task 8. ✓

**2. Placeholder scan:** No TBD/TODO; every code/command step contains literal content. ✓

**3. Type/name consistency:** `CostVisibility::{canView,stripOrder,stripOrders,stripItemRow,stripItemRows,stripWritable}` used consistently across Tasks 1–3. `useCostVisibility().canViewCost` consistent across Tasks 5–7. `ordersCsvHeaders`/`orderToCsvRow` consistent across Task 6. ✓

**Known judgment calls (intentional):**
- Order-level controller strip-for-sales is guaranteed by the unit-tested `stripOrder` + the one-line wiring; the e2e asserts the authorized (admin) controller path and the transform on a real payload (Scenario C). The order-item path has a direct sales-context controller assertion (Scenario D) because `/order-items` is not user-scoped.
- Write-block is silent (fields dropped, no 403) — sales has no UI to send them; avoids breaking existing clients.
