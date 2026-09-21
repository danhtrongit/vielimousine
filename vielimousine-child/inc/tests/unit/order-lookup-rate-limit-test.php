<?php
declare(strict_types=1);

/**
 * Standalone regression test for the public order lookup throttle.
 *
 * This intentionally does not bootstrap WordPress.  The production classes
 * are loaded as-is and only the tiny WordPress API surface they touch is
 * stubbed below, which keeps the test deterministic and fast.
 */

namespace {
    final class OrderLookupRateLimitTestClock
    {
        private static int $now = 1_000;

        public static function set(int $now): void
        {
            self::$now = $now;
        }

        public static function now(): int
        {
            return self::$now;
        }
    }

    final class WP_REST_Response
    {
        private array $data;
        private int $status;
        private array $headers = [];

        public function __construct(array $data = [], int $status = 200)
        {
            $this->data   = $data;
            $this->status = $status;
        }

        public function header(string $key, string $value): void
        {
            $this->headers[$key] = $value;
        }

        public function get_data(): array
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        public function get_headers(): array
        {
            return $this->headers;
        }
    }

    final class WP_REST_Request
    {
        private array $params = [];

        public function __construct(string $method = 'GET', string $route = '')
        {
        }

        public function set_param(string $key, mixed $value): void
        {
            $this->params[$key] = $value;
        }

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? null;
        }
    }

    function wp_generate_uuid4(): string
    {
        return 'test-request-id';
    }

    function wp_date(string $format): string
    {
        return '2026-01-01T00:00:00+00:00';
    }

    /** @var array<string,array{value:mixed,expires:int}> */
    $GLOBALS['order_lookup_test_transients'] = [];

    function get_transient(string $key): mixed
    {
        $store = &$GLOBALS['order_lookup_test_transients'];
        if (!isset($store[$key])) {
            return false;
        }
        if ($store[$key]['expires'] > 0 && $store[$key]['expires'] <= OrderLookupRateLimitTestClock::now()) {
            unset($store[$key]);
            return false;
        }
        return $store[$key]['value'];
    }

    function set_transient(string $key, mixed $value, int $expiration = 0): bool
    {
        $GLOBALS['order_lookup_test_transients'][$key] = [
            'value'   => $value,
            'expires' => $expiration > 0
                ? OrderLookupRateLimitTestClock::now() + $expiration
                : 0,
        ];
        return true;
    }

    final class OrderLookupRateLimitTestAuthSettings
    {
        public function trustedProxies(): array
        {
            return [];
        }
    }

    final class OrderLookupRateLimitTestOrderRepository
    {
        public function findByCode(string $code): ?array
        {
            if ($code !== 'VIE-ABC') {
                return null;
            }
            return [
                'id'             => 7,
                'code'           => 'VIE-ABC',
                'status'         => 'confirmed',
                'payment_status' => 'paid',
                'source'         => 'web',
                'checkin'        => '2026-01-10',
                'checkout'       => '2026-01-12',
                'nights'         => 2,
                'adults'         => 2,
                'children'       => 0,
                'subtotal'       => 100,
                'discount'       => 0,
                'total'          => 100,
                'paid_amount'    => 100,
                'currency'       => 'VND',
                'customer_name'  => 'Test Guest',
                'customer_phone' => '0901234567',
                'pickup'         => null,
                'dropoff'        => null,
                'customer_vat'   => null,
            ];
        }
    }

    final class OrderLookupRateLimitTestOrderItemRepository
    {
        public function all(array $params = []): array
        {
            return ['data' => []];
        }
    }

    final class OrderLookupRateLimitTestRoomRepository
    {
        public function find(int $id): ?array
        {
            return ['name' => 'Room'];
        }
    }

    final class OrderLookupRateLimitTestHotelRepository
    {
        public function find(int $id): ?array
        {
            return ['name' => 'Hotel'];
        }
    }

    final class OrderLookupRateLimitTestInvoiceSettings
    {
        public function all(): array
        {
            return [
                'bank_account' => '',
                'bank_name'    => '',
                'bank_holder'  => '',
            ];
        }
    }
}

namespace Vie\Http {
    // Unqualified time() in RateLimiter resolves to this namespaced function.
    function time(): int
    {
        return \OrderLookupRateLimitTestClock::now();
    }
}

namespace {
    require __DIR__ . '/../../src/Container.php';
    require __DIR__ . '/../../src/Support/ResponseEnvelope.php';
    require __DIR__ . '/../../src/Support/ClientIp.php';
    require __DIR__ . '/../../src/Repository/AbstractRepository.php';
    require __DIR__ . '/../../src/Repository/CustomerRepository.php';
    require __DIR__ . '/../../src/Http/RateLimiter.php';
    require __DIR__ . '/../../src/Http/Controllers/OrderLookupController.php';

    use Vie\Container;
    use Vie\Http\Controllers\OrderLookupController;

    $pass = 0;
    $fail = 0;
    $assert = static function (string $name, bool $condition, string $detail = '') use (&$pass, &$fail): void {
        if ($condition) {
            echo "  ✓ {$name}\n";
            $pass++;
            return;
        }
        echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
        $fail++;
    };

    $setContainer = static function (): void {
        $reflection = new \ReflectionProperty(Container::class, 'instances');
        $reflection->setValue(null, [
            'Vie\\Service\\Settings\\AuthSettings' => new OrderLookupRateLimitTestAuthSettings(),
            'Vie\\Repository\\OrderRepository'       => new OrderLookupRateLimitTestOrderRepository(),
            'Vie\\Repository\\OrderItemRepository'  => new OrderLookupRateLimitTestOrderItemRepository(),
            'Vie\\Repository\\RoomRepository'       => new OrderLookupRateLimitTestRoomRepository(),
            'Vie\\Repository\\HotelRepository'      => new OrderLookupRateLimitTestHotelRepository(),
            'Vie\\Service\\Settings\\InvoiceSettings' => new OrderLookupRateLimitTestInvoiceSettings(),
        ]);
    };

    $reset = static function (string $ip = '203.0.113.10', int $now = 1_000) use ($setContainer): void {
        $GLOBALS['order_lookup_test_transients'] = [];
        $_SERVER['REMOTE_ADDR'] = $ip;
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        OrderLookupRateLimitTestClock::set($now);
        $setContainer();
    };

    $request = static function (string $code = 'VIE-ABC', string $phone = '0901234567'): WP_REST_Request {
        $request = new WP_REST_Request('GET', '/orders/lookup');
        $request->set_param('code', $code);
        $request->set_param('phone', $phone);
        return $request;
    };

    $status = static fn (WP_REST_Response $response): int => $response->get_status();

    // A customer can check immediately and continue polling every eight seconds.
    $reset('203.0.113.10', 1_000);
    $pollStatuses = [];
    for ($poll = 0; $poll <= 15; $poll++) {
        OrderLookupRateLimitTestClock::set(1_000 + ($poll * 8));
        $pollStatuses[] = $status(OrderLookupController::lookup($request()));
    }
    $assert('initial lookup + 15 polls every 8s remain available', $pollStatuses === array_fill(0, 16, 200));

    // Ten requests are allowed in one 15-second window; the eleventh has the
    // standard 429 response and useful client-facing headers.
    $reset('203.0.113.11', 2_000);
    $burstStatuses = [];
    for ($i = 0; $i < 10; $i++) {
        $burstStatuses[] = $status(OrderLookupController::lookup($request()));
    }
    $denied = OrderLookupController::lookup($request());
    $assert('first 10 burst lookups are allowed', $burstStatuses === array_fill(0, 10, 200));
    $assert('11th burst lookup is rate limited', $status($denied) === 429);
    $assert('429 Retry-After is 15s at window start', $denied->get_headers()['Retry-After'] === '15');
    $assert('429 exposes configured limit', $denied->get_headers()['X-RateLimit-Limit'] === '10');
    $assert('429 exposes zero remaining requests', $denied->get_headers()['X-RateLimit-Remaining'] === '0');

    // Rejected requests must not slide the fixed expiry. At exactly t+15 the
    // bucket is usable again, even if another denied call happened at t+5.
    OrderLookupRateLimitTestClock::set(2_005);
    $deniedAtFive = OrderLookupController::lookup($request());
    OrderLookupRateLimitTestClock::set(2_014);
    $deniedAtFourteen = OrderLookupController::lookup($request());
    OrderLookupRateLimitTestClock::set(2_015);
    $allowedAtExpiry = OrderLookupController::lookup($request());
    $assert('rejected requests do not extend the original window', $status($deniedAtFive) === 429 && $status($deniedAtFourteen) === 429);
    $assert('lookup resumes exactly at 15s', $status($allowedAtExpiry) === 200);

    // Deployments that used the old five-minute bucket must not poison the new
    // order_lookup_15s bucket.
    $reset('203.0.113.12', 3_000);
    $oldKey = 'vie_rl_' . md5('order_lookup|203.0.113.12');
    set_transient($oldKey, ['count' => 100, 'reset' => 3_300], 300);
    $assert('legacy 300s bucket does not block lookup', $status(OrderLookupController::lookup($request())) === 200);

    // Counters are isolated per client IP.
    $reset('203.0.113.13', 4_000);
    for ($i = 0; $i < 10; $i++) {
        OrderLookupController::lookup($request());
    }
    $assert('11th request from first IP is denied', $status(OrderLookupController::lookup($request())) === 429);
    $_SERVER['REMOTE_ADDR'] = '203.0.113.14';
    $assert('different IP gets its own lookup allowance', $status(OrderLookupController::lookup($request())) === 200);

    // Rate limiting must not replace the endpoint's code/phone validation.
    $reset('203.0.113.15', 5_000);
    $missingCode = OrderLookupController::lookup($request('', '0901234567'));
    $missingPhone = OrderLookupController::lookup($request('VIE-ABC', ''));
    $wrongPhone = OrderLookupController::lookup($request('VIE-ABC', '0900000000'));
    $assert('missing code remains a 422 validation error', $status($missingCode) === 422);
    $assert('missing phone remains a 422 validation error', $status($missingPhone) === 422);
    $assert('wrong phone remains a 404 not-found response', $status($wrongPhone) === 404);

    echo "\n--- Order lookup rate limit: {$pass} passed, {$fail} failed ---\n";
    exit($fail === 0 ? 0 : 1);
}
