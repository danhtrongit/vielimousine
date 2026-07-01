<?php
declare(strict_types=1);

/**
 * Phase 6 E2E — JWT, refresh rotation, reuse detection, scoping.
 */

if (!isset($GLOBALS['wpdb'])) {
    throw new RuntimeException('auth-e2e must run inside WP context');
}

$wpdb = $GLOBALS['wpdb'];

$assert = function (string $name, bool $cond, string $detail = '') use (&$pass, &$fail): void {
    if ($cond) {
        echo "  ✓ {$name}\n";
        $pass++;
    } else {
        echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
        $fail++;
    }
};

// Ensure roles + test users
\Vie\Service\Auth\RoleInstaller::install();

$ensureUser = function (string $login, string $role) {
    $user = get_user_by('login', $login);
    if (!$user) {
        $id = wp_create_user($login, 'TestPass123!', $login . '@test.local');
        $user = new WP_User($id);
        $user->set_role($role);
    } else {
        $user->set_role($role);
    }
    return $user->ID;
};

$adminId  = $ensureUser('e2e_admin', 'administrator');
$salesAId = $ensureUser('e2e_sales_a', 'vie_sales');
$salesBId = $ensureUser('e2e_sales_b', 'vie_sales');
$hmId     = $ensureUser('e2e_hm', 'vie_hotel_manager');

$jwt          = \Vie\Container::get(\Vie\Service\Auth\JwtService::class);
$tokenService = \Vie\Container::get(\Vie\Service\Auth\TokenService::class);
$authService  = \Vie\Container::get(\Vie\Service\Auth\AuthService::class);
$orderRepo    = \Vie\Container::get(\Vie\Repository\OrderRepository::class);

// --- Scenario 1: Login success ---
echo "Scenario 1: Login success\n";
$req = new \Vie\DTO\LoginRequest('e2e_admin', 'TestPass123!', false, '127.0.0.1', 'phpunit');
$tokens = $authService->login($req);
$assert('access_token returned', !empty($tokens['access_token']));
$assert('refresh_token_raw returned', !empty($tokens['refresh_token_raw']));
$assert('user.id correct', (int) $tokens['user']['id'] === $adminId);
$assert('user has caps', is_array($tokens['user']['caps']) && count($tokens['user']['caps']) > 0);

// --- Scenario 2: Login fail ---
echo "\nScenario 2: Login fail (wrong password)\n";
$caught = false;
try {
    $authService->login(new \Vie\DTO\LoginRequest('e2e_admin', 'wrong_pass', false, '127.0.0.1', 'phpunit'));
} catch (\Vie\Service\Auth\InvalidCredentialsException $e) {
    $caught = true;
}
$assert('InvalidCredentialsException thrown', $caught);

// --- Scenario 3: JWT decode round-trip ---
echo "\nScenario 3: JWT decode\n";
$payload = ['sub' => '999', 'iat' => time(), 'exp' => time() + 60, 'caps' => ['foo']];
$encoded = $jwt->encode($payload);
$decoded = $jwt->decode($encoded);
$assert('decoded.sub matches', $decoded['sub'] === '999');
$assert('decoded.caps matches', $decoded['caps'] === ['foo']);

// --- Scenario 4: JWT expired ---
echo "\nScenario 4: JWT expired\n";
$expiredToken = $jwt->encode(['sub' => '1', 'iat' => time() - 7200, 'exp' => time() - 3600]);
$caught = false;
try {
    $jwt->decode($expiredToken);
} catch (\Vie\Service\Auth\InvalidTokenException $e) {
    $caught = ($e->reason === 'expired');
}
$assert('expired token → InvalidTokenException(expired)', $caught);

// --- Scenario 5: JWT tampered signature ---
echo "\nScenario 5: JWT tampered signature\n";
$valid = $jwt->encode(['sub' => '1', 'exp' => time() + 60]);
$parts = explode('.', $valid);
$tampered = $parts[0] . '.' . $parts[1] . '.tampered_sig';
$caught = false;
try {
    $jwt->decode($tampered);
} catch (\Vie\Service\Auth\InvalidTokenException $e) {
    $caught = ($e->reason === 'invalid_signature');
}
$assert('tampered sig → InvalidTokenException(invalid_signature)', $caught);

// --- Scenario 6: Refresh rotation ---
echo "\nScenario 6: Refresh rotation\n";
$issued = $tokenService->issueRefresh($adminId, '127.0.0.1', 'phpunit');
$family = $issued['family'];
$rotation = $tokenService->rotateRefresh($issued['raw'], '127.0.0.1', 'phpunit');
$assert('rotation same family', $rotation['family'] === $family);
$assert('rotation new hash differs', $rotation['new']['hash'] !== $issued['hash']);

// Verify old hash revoked
$tokenRepo = \Vie\Container::get(\Vie\Repository\TokenRepository::class);
$oldRow = $tokenRepo->findByHash($issued['hash']);
$assert('old refresh row marked revoked', !empty($oldRow['revoked_at']));

// --- Scenario 7: Refresh reuse detection ---
echo "\nScenario 7: Refresh reuse detection — revoke entire family\n";
$issued2 = $tokenService->issueRefresh($salesAId, '127.0.0.1', 'phpunit');
$reusedFamily = $issued2['family'];
$rot2 = $tokenService->rotateRefresh($issued2['raw'], '127.0.0.1', 'phpunit');
$caught = false;
try {
    $tokenService->rotateRefresh($issued2['raw'], '127.0.0.1', 'phpunit');  // reuse old
} catch (\Vie\Service\Auth\TokenReuseException $e) {
    $caught = ($e->context['family'] ?? '') === $reusedFamily;
}
$assert('TokenReuseException on reuse', $caught);

// Verify the new (legit) token now revoked too
$newRow = $tokenRepo->findByHash($rot2['new']['hash']);
$assert('new refresh in revoked family — also revoked', !empty($newRow['revoked_at']));

// --- Scenario 8: Refresh expired ---
echo "\nScenario 8: Refresh expired\n";
$issuedExp = $tokenService->issueRefresh($adminId, '127.0.0.1', 'phpunit');
// Manually set expiry to past
$wpdb->update(
    $wpdb->prefix . 'vie_token',
    ['expires_at' => '2020-01-01 00:00:00'],
    ['hash' => $issuedExp['hash']]
);
$caught = false;
try {
    $tokenService->rotateRefresh($issuedExp['raw'], '127.0.0.1', 'phpunit');
} catch (\Vie\Service\Auth\InvalidTokenException $e) {
    $caught = ($e->reason === 'expired');
}
$assert('expired refresh → InvalidTokenException(expired)', $caught);

// --- Scenario 9: Logout (revoke one) ---
echo "\nScenario 9: Logout revokes single token\n";
$issuedA = $tokenService->issueRefresh($adminId, '127.0.0.1', 'phpunit');
$issuedB = $tokenService->issueRefresh($adminId, '127.0.0.1', 'phpunit');
$authService->logout($issuedA['raw'], $adminId);
$rowA = $tokenRepo->findByHash($issuedA['hash']);
$rowB = $tokenRepo->findByHash($issuedB['hash']);
$assert('logout-ed token revoked', !empty($rowA['revoked_at']));
$assert('other family token still active', empty($rowB['revoked_at']));

// --- Scenario 10: RoleInstaller idempotent ---
echo "\nScenario 10: RoleInstaller idempotent\n";
\Vie\Service\Auth\RoleInstaller::install();  // 2nd call
\Vie\Service\Auth\RoleInstaller::install();  // 3rd call
$role = get_role('vie_sales');
$assert('vie_sales role still exists', $role !== null);
$caps = $role !== null ? array_keys(array_filter($role->capabilities)) : [];
$assert('vie_sales has vie_create_orders cap', in_array('vie_create_orders', $caps, true));

// --- Scenario 11: AuthMiddleware caps check ---
echo "\nScenario 11: current_user_can with role-installed caps\n";
wp_set_current_user($salesAId);
$assert('sales can create_orders', current_user_can('vie_create_orders'));
$assert('sales cannot manage_inventory', !current_user_can('vie_manage_inventory'));
$assert('sales cannot view_all_orders', !current_user_can('vie_view_all_orders'));
$assert('sales cannot manage_pricing', !current_user_can('vie_manage_pricing'));

wp_set_current_user($adminId);
$assert('admin can manage_inventory', current_user_can('vie_manage_inventory'));
$assert('admin can view_all_orders', current_user_can('vie_view_all_orders'));
$assert('admin can manage_pricing', current_user_can('vie_manage_pricing'));

// Quản lý khách sạn = nội dung (khách sạn/phòng/ảnh/báo cáo) nhưng KHÔNG có giá.
$hmAuthId = username_exists('auth_e2e_hm')
    ?: wp_insert_user(['user_login' => 'auth_e2e_hm', 'user_pass' => wp_generate_password(), 'role' => 'vie_hotel_manager']);
wp_set_current_user((int) $hmAuthId);
$assert('hotel_manager can manage_inventory', current_user_can('vie_manage_inventory'));
$assert('hotel_manager can manage_media', current_user_can('vie_manage_media'));
$assert('hotel_manager can view_reports', current_user_can('vie_view_reports'));
$assert('hotel_manager CANNOT manage_pricing', !current_user_can('vie_manage_pricing'));
$assert('hotel_manager CANNOT manage_orders', !current_user_can('vie_manage_orders'));
$assert('hotel_manager CANNOT manage_settings', !current_user_can('vie_manage_settings'));

// --- Scenario 12 & 13: OrderRepository scope ---
echo "\nScenario 12-13: OrderRepository queryScope\n";

// Reset stock + create orders for salesA and salesB
$wpdb->query("UPDATE {$wpdb->prefix}vie_room_price SET stock = 5 WHERE stock < 5");

$findRoom = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}vie_room WHERE name = 'Deluxe Sea View' LIMIT 1");
$plus15 = (new DateTimeImmutable('today +15 days', wp_timezone()))->format('Y-m-d');
$plus17 = (new DateTimeImmutable('today +17 days', wp_timezone()))->format('Y-m-d');

$orderSvc = \Vie\Container::get(\Vie\Service\Order\OrderService::class);

$reqA = \Vie\DTO\OrderRequest::fromArray([
    'customer' => ['phone' => '0911aa' . substr(uniqid(), -5), 'name' => 'salesA order'],
    'source'   => 'admin', 'sales_user_id' => $salesAId,
    'items'    => [['room_id' => $findRoom, 'booking_type' => 'room', 'checkin' => $plus15, 'checkout' => $plus17, 'adults' => 1, 'user_rooms' => 0]],
], 'auth-e2e-A-' . uniqid(), '127.0.0.1', 'phpunit');
$orderA = $orderSvc->create($reqA);

$reqB = \Vie\DTO\OrderRequest::fromArray([
    'customer' => ['phone' => '0911bb' . substr(uniqid(), -5), 'name' => 'salesB order'],
    'source'   => 'admin', 'sales_user_id' => $salesBId,
    'items'    => [['room_id' => $findRoom, 'booking_type' => 'room', 'checkin' => $plus15, 'checkout' => $plus17, 'adults' => 1, 'user_rooms' => 0]],
], 'auth-e2e-B-' . uniqid(), '127.0.0.1', 'phpunit');
$orderB = $orderSvc->create($reqB);

// salesA scope
wp_set_current_user($salesAId);
$resultA = $orderRepo->all(['per_page' => 100]);
$idsA = array_map(static fn($o) => (int) $o['id'], $resultA['data']);
$assert('sales A sees own order', in_array((int) $orderA['id'], $idsA, true));
$assert('sales A does NOT see B order', !in_array((int) $orderB['id'], $idsA, true));

// salesB scope
wp_set_current_user($salesBId);
$resultB = $orderRepo->all(['per_page' => 100]);
$idsB = array_map(static fn($o) => (int) $o['id'], $resultB['data']);
$assert('sales B sees own order', in_array((int) $orderB['id'], $idsB, true));
$assert('sales B does NOT see A order', !in_array((int) $orderA['id'], $idsB, true));

// admin scope
wp_set_current_user($adminId);
$resultAdmin = $orderRepo->all(['per_page' => 100]);
$idsAdmin = array_map(static fn($o) => (int) $o['id'], $resultAdmin['data']);
$assert('admin sees A order', in_array((int) $orderA['id'], $idsAdmin, true));
$assert('admin sees B order', in_array((int) $orderB['id'], $idsAdmin, true));

// canUserViewOrder
$assert('canUserViewOrder admin → true (A)', $orderRepo->canUserViewOrder($adminId, $orderA));
$assert('canUserViewOrder salesA → true (own A)', $orderRepo->canUserViewOrder($salesAId, $orderA));
$assert('canUserViewOrder salesA → false (B)', !$orderRepo->canUserViewOrder($salesAId, $orderB));

// --- Scenario 14: Security regression — sensitive fields stripped from REST input ---
echo "\nScenario 14: Security regression — sensitive fields stripped from REST input\n";

wp_set_current_user($adminId);

// 14a: POST /orders với idempotency_key trong body → bị strip (chỉ header set value)
$headerKey = 'sec-header-key-' . uniqid();
$bodyKey   = 'attacker-supplied-body-key';
$reqMal = \Vie\DTO\OrderRequest::fromArray([
    'customer' => ['phone' => '0913aa' . substr(uniqid(), -5), 'name' => 'Sec test'],
    'source'   => 'admin', 'sales_user_id' => $adminId,
    'items'    => [['room_id' => $findRoom, 'booking_type' => 'room',
                    'checkin' => $plus15, 'checkout' => $plus17,
                    'adults' => 1, 'user_rooms' => 0]],
    'idempotency_key' => $bodyKey,  // attacker thử inject
], $headerKey, '127.0.0.1', 'phpunit');
$orderSec = $orderSvc->create($reqMal);
$assert('idempotency_key từ header (không phải body)',
    (string) $orderSec['idempotency_key'] === $headerKey
    && (string) $orderSec['idempotency_key'] !== $bodyKey);

// 14b: PUT /orders/{id} với body chứa code/idempotency_key → bị Validator strip
$putValid = \Vie\Support\Validator::validate([
    'code'            => 'HACKED1234',
    'idempotency_key' => 'hacked-key',
    'customer_name'   => 'updated name',
], \Vie\Validation\Schemas\OrderValidation::updateRules((int) $orderSec['id']));
$cleaned = $putValid->validated();
$assert('PUT validator strips code',            !isset($cleaned['code']));
$assert('PUT validator strips idempotency_key', !isset($cleaned['idempotency_key']));
$assert('PUT validator giữ legit field',        ($cleaned['customer_name'] ?? null) === 'updated name');

// 14c: PaymentEntryValidation strips raw_payload
$payValid = \Vie\Support\Validator::validate([
    'order_id'    => (int) $orderSec['id'],
    'type'        => 'payment', 'amount' => 100000, 'method' => 'cash',
    'raw_payload' => ['attacker' => 'data'],
], \Vie\Validation\Schemas\PaymentEntryValidation::rules());
$payClean = $payValid->validated();
$assert('PaymentEntry validator strips raw_payload', !isset($payClean['raw_payload']));

// 14d: OrderValidation::createRules() KHÔNG còn idempotency_key / code (dead-path đóng)
$rules = \Vie\Validation\Schemas\OrderValidation::createRules();
$assert('OrderValidation::createRules() không có idempotency_key', !isset($rules['idempotency_key']));
$assert('OrderValidation::createRules() không có code',            !isset($rules['code']));

// 14e: Token routes — POST/PUT không tồn tại
$routes = rest_get_server()->get_routes(VIE_API_NAMESPACE);
$tokenRoute = $routes['/' . VIE_API_NAMESPACE . '/tokens'] ?? [];
$methods = [];
foreach ($tokenRoute as $r) {
    $methods = array_merge($methods, array_keys($r['methods'] ?? []));
}
$assert('Token /tokens KHÔNG có POST', !in_array('POST', $methods, true));
$assert('Token /tokens KHÔNG có PUT',  !in_array('PUT',  $methods, true));

echo "\n";
