<?php
declare(strict_types=1);

/*
 * Offline integration checks for the booking-quote bootstrap.  This file uses
 * only tiny WordPress-shaped stubs; it must never be run through production WP.
 */
namespace Vie {
    final class Container
    {
        public static object $auth;

        public static function get(string $class): object
        {
            return self::$auth;
        }
    }
}

namespace {
    define('VIE_API_NAMESPACE', 'vie/v1');
    define('VIE_CHILD_PATH', dirname(__DIR__, 3));
    define('VIE_CHILD_URL', 'https://vielimousine.test/wp-content/themes/vie-child');
    define('ABSPATH', __DIR__);

    final class FakeRole
    {
        public function __construct(public string $name, public array $caps = []) {}
        public function add_cap(string $cap): void { $this->caps[$cap] = true; }
        public function remove_cap(string $cap): void { unset($this->caps[$cap]); }
    }

    final class WP_User
    {
        public array $roles = [];
        public function __construct(public int $ID) {}
        public function remove_role(string $role): void { $this->roles = array_values(array_diff($this->roles, [$role])); }
        public function add_role(string $role): void { $this->roles[] = $role; }
    }

    final class WP_REST_Request {}

    $roles = [
        'administrator' => new FakeRole('administrator'),
        'vie_hotel_manager' => new FakeRole('vie_hotel_manager', [
            'vie_manage_inventory' => true,
            'vie_create_booking_quotes' => true,
            'vie_view_all_booking_quotes' => true,
        ]),
        'vie_sales' => new FakeRole('vie_sales'),
    ];
    $routes = [];
    $hooks = [];
    $options = [];
    $rewriteRules = [];
    $flushes = [];
    $currentCaps = [];

    function get_role(string $name): ?FakeRole { global $roles; return $roles[$name] ?? null; }
    function add_role(string $name, string $label, array $caps): FakeRole
    { global $roles; return $roles[$name] = new FakeRole($label, $caps); }
    function remove_role(string $name): void { global $roles; unset($roles[$name]); }
    function get_users(array $args = []): array { return []; }
    function register_rest_route(string $namespace, string $route, array $args): void
    { global $routes; $routes[$namespace . $route] = $args; }
    function add_filter(string $tag, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    { global $hooks; $hooks['filter'][$tag][] = $callback; }
    function add_action(string $tag, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    { global $hooks; $hooks['action'][$tag][] = $callback; }
    function current_user_can(string $cap): bool { global $currentCaps; return in_array($cap, $currentCaps, true); }
    function __return_true(): bool { return true; }
    function get_option(string $name, mixed $default = false): mixed { global $options; return $options[$name] ?? $default; }
    function update_option(string $name, mixed $value, bool $autoload = true): bool { global $options; $options[$name] = $value; return true; }
    function flush_rewrite_rules(bool $hard = true): void { global $flushes; $flushes[] = $hard; }
    function add_rewrite_rule(string $regex, string $query, string $position = 'bottom'): void
    { global $rewriteRules; $rewriteRules[] = [$regex, $query, $position]; }
    function get_query_var(string $name, mixed $default = ''): mixed { return $default; }
    function home_url(string $path = ''): string { return 'https://vielimousine.test/' . ltrim($path, '/'); }
    function rest_url(string $path = ''): string { return 'https://vielimousine.test/wp-json/' . ltrim($path, '/'); }
    function esc_url(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    function esc_url_raw(string $value): string { return $value; }
    function esc_attr(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    function wp_json_encode(mixed $value, int $flags = 0): string { return (string) json_encode($value, $flags | JSON_UNESCAPED_UNICODE); }

    final class FakeAuth { public function authenticate(object $request): bool { return true; } }
    \Vie\Container::$auth = new FakeAuth();

    require dirname(__DIR__, 2) . '/src/Service/Auth/AuthMiddleware.php';
    require dirname(__DIR__, 2) . '/src/Http/RestRouter.php';
    require dirname(__DIR__, 2) . '/src/Service/Auth/RoleInstaller.php';
    require dirname(__DIR__, 2) . '/src/Frontend/PublicAssets.php';
    require dirname(__DIR__, 2) . '/src/Frontend/BookingQuotePage.php';

    $pass = 0; $fail = 0;
    $assert = static function (string $name, bool $condition, string $detail = '') use (&$pass, &$fail): void {
        if ($condition) { echo "  ✓ {$name}\n"; $pass++; return; }
        echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; $fail++;
    };
    $same = static function (string $name, mixed $expected, mixed $actual) use ($assert): void {
        $assert($name, $expected === $actual, 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    };

    // Invoke the production registration method directly, so this checks route
    // shape and callbacks rather than a copied list of expected endpoints.
    $routes = [];
    $method = new ReflectionMethod(\Vie\Http\RestRouter::class, 'registerBookingQuotes');
    $method->invoke(null);
    $route = static fn(string $suffix): array => $routes[VIE_API_NAMESPACE . $suffix] ?? [];
    $same('admin collection has GET and POST', ['GET', 'POST'], array_map(fn($r) => $r['methods'], $route('/booking-quotes')));
    $same('admin collection callbacks', [
        [\Vie\Http\Controllers\BookingQuoteController::class, 'index'],
        [\Vie\Http\Controllers\BookingQuoteController::class, 'store'],
    ], array_map(fn($r) => $r['callback'], $route('/booking-quotes')));
    $same('admin object has GET and PUT,PATCH', ['GET', 'PUT,PATCH'], array_map(fn($r) => $r['methods'], $route('/booking-quotes/(?P<id>\\d+)')));
    $same('admin object callbacks', [
        [\Vie\Http\Controllers\BookingQuoteController::class, 'show'],
        [\Vie\Http\Controllers\BookingQuoteController::class, 'update'],
    ], array_map(fn($r) => $r['callback'], $route('/booking-quotes/(?P<id>\\d+)')));
    foreach (['publish', 'revoke', 'duplicate'] as $action) {
        $registered = $route('/booking-quotes/(?P<id>\\d+)/' . $action);
        $same("{$action} callback", [\Vie\Http\Controllers\BookingQuoteController::class, $action], $registered['callback'] ?? null);
        $same("{$action} uses POST", 'POST', $registered['methods'] ?? null);
    }
    $public = $route('/public/booking-quotes/(?P<public_id>[a-f0-9]{32})');
    $checkout = $route('/public/booking-quotes/(?P<public_id>[a-f0-9]{32})/checkout');
    $same('public show callback', [\Vie\Http\Controllers\PublicBookingQuoteController::class, 'show'], $public['callback'] ?? null);
    $same('public checkout callback', [\Vie\Http\Controllers\PublicBookingQuoteController::class, 'checkout'], $checkout['callback'] ?? null);
    $same('public show is GET', 'GET', $public['methods'] ?? null);
    $same('public checkout is POST', 'POST', $checkout['methods'] ?? null);
    $same('public show needs no login', '__return_true', $public['permission_callback'] ?? null);
    $same('public checkout needs no login', '__return_true', $checkout['permission_callback'] ?? null);
    $currentCaps = ['vie_view_own_booking_quotes'];
    $assert('read permission accepts own capability', ($route('/booking-quotes')[0]['permission_callback'])(new WP_REST_Request()));
    $currentCaps = ['vie_view_all_booking_quotes'];
    $assert('read permission accepts all capability', ($route('/booking-quotes')[0]['permission_callback'])(new WP_REST_Request()));
    $currentCaps = [];
    $assert('read permission rejects missing capabilities', !(($route('/booking-quotes')[0]['permission_callback'])(new WP_REST_Request())));
    $currentCaps = ['vie_create_booking_quotes'];
    $assert('write permission requires create capability', ($route('/booking-quotes')[1]['permission_callback'])(new WP_REST_Request()));
    $currentCaps = ['vie_view_own_booking_quotes'];
    $assert('write permission rejects view-only capability', !(($route('/booking-quotes')[1]['permission_callback'])(new WP_REST_Request())));

    // RoleInstaller is exercised against mutable role stubs, including removal
    // of stale quote permissions from the hotel-manager role.
    \Vie\Service\Auth\RoleInstaller::install();
    $sales = get_role(\Vie\Service\Auth\RoleInstaller::ROLE_SALES);
    $admin = get_role('administrator');
    $hotel = get_role(\Vie\Service\Auth\RoleInstaller::ROLE_HOTEL_MANAGER);
    $assert('sales can create and view own quotes', isset($sales->caps['vie_create_booking_quotes'], $sales->caps['vie_view_own_booking_quotes']));
    $assert('sales cannot view all quotes', !isset($sales->caps['vie_view_all_booking_quotes']));
    $assert('administrator has all quote capabilities', isset($admin->caps['vie_create_booking_quotes'], $admin->caps['vie_view_own_booking_quotes'], $admin->caps['vie_view_all_booking_quotes']));
    $assert('hotel manager has no quote capabilities', !isset($hotel->caps['vie_create_booking_quotes'], $hotel->caps['vie_view_own_booking_quotes'], $hotel->caps['vie_view_all_booking_quotes']));

    // Rewrite setup must expose /booking/<token> and flush only once per version.
    $hooks = []; $rewriteRules = []; $options = []; $flushes = [];
    \Vie\Frontend\BookingQuotePage::register();
    $queryVarFilter = $hooks['filter']['query_vars'][0] ?? null;
    $same('booking query var registered', ['existing', \Vie\Frontend\BookingQuotePage::QUERY_VAR], $queryVarFilter(['existing']));
    $same('booking rewrite rule registered', ['^booking/([^/]+)/?$', 'index.php?vie_booking_quote=$matches[1]', 'top'], $rewriteRules[0] ?? null);
    \Vie\Frontend\BookingQuotePage::maybeFlushRewrites();
    \Vie\Frontend\BookingQuotePage::maybeFlushRewrites();
    $same('rewrite flush occurs once and is soft', [false], $flushes);
    $same('rewrite version persisted', '1', $options['vie_booking_quote_rewrite_version'] ?? null);

    // Use the tracked Vite build when present; every emitted asset must remain
    // under the local assets directory, while an unknown entry safely returns null.
    $manifest = json_decode((string) file_get_contents(VIE_CHILD_PATH . '/public-app/dist/.vite/manifest.json'), true);
    $builtEntry = isset($manifest['src/entries/booking.ts']) ? 'src/entries/booking.ts' : 'src/entries/hotel.ts';
    $assets = \Vie\Frontend\PublicAssets::standaloneEntry($builtEntry);
    $assert('standalone entry resolves existing build', is_array($assets) && str_starts_with($assets['entry'], VIE_CHILD_URL . '/public-app/dist/assets/'));
    $assert('standalone imports include local assets only', is_array($assets) && !array_filter(array_merge($assets['css'], $assets['imports']), fn($url) => !str_starts_with($url, VIE_CHILD_URL . '/public-app/dist/assets/')));
    $same('unknown standalone entry is unavailable', null, \Vie\Frontend\PublicAssets::standaloneEntry('src/entries/not-built.ts'));

    // The private template must escape the token and contain no third-party WP
    // head/footer hooks that could leak a bearer URL to analytics.
    $assets = ['entry' => 'https://vielimousine.test/app.js', 'css' => [], 'imports' => []];
    $publicId = '" onmouseover="alert(1)';
    $boot = ['root' => 'https://vielimousine.test/wp-json/vie/v1/'];
    $cspNonce = 'nonce';
    ob_start();
    require dirname(__DIR__, 2) . '/templates/frontend/booking-quote-page.php';
    $html = (string) ob_get_clean();
    $assert('private template escapes public id', str_contains($html, 'data-public-id="&quot; onmouseover=&quot;alert(1)"') && !str_contains($html, 'data-public-id="" onmouseover="'));
    $assert('private template has no wp head/footer hooks', !str_contains($html, 'wp_head') && !str_contains($html, 'wp_footer'));

    echo "\n--- Booking quote integration: {$pass} passed, {$fail} failed ---\n";
    exit($fail === 0 ? 0 : 1);
}
