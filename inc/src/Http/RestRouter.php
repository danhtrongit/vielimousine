<?php
declare(strict_types=1);

namespace Vie\Http;

use Vie\Http\Controllers\ActivityLogController;
use Vie\Http\Controllers\AuthController;
use Vie\Http\Controllers\CouponActionController;
use Vie\Http\Controllers\CouponController;
use Vie\Http\Controllers\CouponUsageController;
use Vie\Http\Controllers\CustomerController;
use Vie\Http\Controllers\HealthController;
use Vie\Http\Controllers\HotelController;
use Vie\Http\Controllers\OrderActionController;
use Vie\Http\Controllers\OrderController;
use Vie\Http\Controllers\OrderItemController;
use Vie\Http\Controllers\OrderLookupController;
use Vie\Http\Controllers\PaymentLogController;
use Vie\Http\Controllers\ProductCodeController;
use Vie\Http\Controllers\PublicOrderController;
use Vie\Http\Controllers\QuoteController;
use Vie\Http\Controllers\RoomController;
use Vie\Http\Controllers\RoomPriceBulkController;
use Vie\Http\Controllers\RoomPriceController;
use Vie\Http\Controllers\SepayWebhookController;
use Vie\Http\Controllers\SurchargeController;
use Vie\Http\Controllers\SurchargePriceController;
use Vie\Http\Controllers\TicketPriceController;
use Vie\Http\Controllers\TokenController;
use Vie\Service\Auth\AuthMiddleware;

final class RestRouter
{
    public static function register(): void
    {
        // Public routes — no auth required
        register_rest_route(VIE_API_NAMESPACE, '/health', [
            'methods'             => 'GET',
            'callback'            => [HealthController::class, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(VIE_API_NAMESPACE, '/quote', [
            'methods'             => 'POST',
            'callback'            => [QuoteController::class, 'quote'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(VIE_API_NAMESPACE, '/coupons/validate', [
            'methods'             => 'POST',
            'callback'            => [CouponActionController::class, 'validateCoupon'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(VIE_API_NAMESPACE, '/orders/lookup', [
            'methods'             => 'GET',
            'callback'            => [OrderLookupController::class, 'lookup'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(VIE_API_NAMESPACE, '/public/orders', [
            'methods'             => 'POST',
            'callback'            => [PublicOrderController::class, 'create'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(VIE_API_NAMESPACE, '/payments/sepay/webhook', [
            'methods'             => 'POST',
            'callback'            => [SepayWebhookController::class, 'handle'],
            'permission_callback' => '__return_true',  // HMAC verified inside controller
        ]);

        // Auth endpoints — public except /me
        register_rest_route(VIE_API_NAMESPACE, '/auth/login', [
            'methods'             => 'POST',
            'callback'            => [AuthController::class, 'login'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/auth/refresh', [
            'methods'             => 'POST',
            'callback'            => [AuthController::class, 'refresh'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/auth/logout', [
            'methods'             => 'POST',
            'callback'            => [AuthController::class, 'logout'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/auth/me', [
            'methods'             => 'GET',
            'callback'            => [AuthController::class, 'me'],
            'permission_callback' => AuthMiddleware::requireLogin(),
        ]);

        // Inventory — needs vie_manage_inventory
        $inv = 'vie_manage_inventory';
        self::crudWithCaps('hotels', HotelController::class, $inv, $inv);
        self::crudWithCaps('rooms', RoomController::class, $inv, $inv);
        self::crudWithCaps('room-prices', RoomPriceController::class, $inv, $inv);
        self::crudWithCaps('surcharges', SurchargeController::class, $inv, $inv);
        self::crudWithCaps('surcharge-prices', SurchargePriceController::class, $inv, $inv);
        self::crudWithCaps('ticket-prices', TicketPriceController::class, $inv, $inv);
        self::crudWithCaps('product-codes', ProductCodeController::class, $inv, $inv);

        // Customers
        self::crudWithCaps('customers', CustomerController::class, 'vie_manage_customers', 'vie_manage_customers');

        // Orders — viewable by sales (filtered via queryScope); creatable by sales+
        self::crudWithCaps('orders', OrderController::class, 'vie_create_orders', 'vie_view_own_orders');

        // Order items
        self::crudWithCaps('order-items', OrderItemController::class, 'vie_manage_orders', 'vie_view_own_orders');

        // Coupons
        self::crudWithCaps('coupons', CouponController::class, 'vie_manage_coupons', 'vie_manage_coupons');

        // Payments — view all caps, manage caps
        self::readAndCreateWithCaps('payments', PaymentLogController::class, 'vie_manage_payments', 'vie_view_all_orders');

        // Coupon usage
        self::listAndCreateWithCaps('coupon-usage', CouponUsageController::class, 'vie_manage_coupons', 'vie_manage_coupons');

        // Tokens — view audit only
        self::readAndDeleteWithCaps('tokens', TokenController::class, 'vie_view_audit', 'vie_view_audit');

        // Activity log
        self::readOnlyWithCaps('activity-log', ActivityLogController::class, 'vie_view_audit');

        // Order actions
        register_rest_route(VIE_API_NAMESPACE, '/orders/(?P<id>\\d+)/cancel', [
            'methods'             => 'POST',
            'callback'            => [OrderActionController::class, 'cancel'],
            'permission_callback' => AuthMiddleware::requireCap('vie_cancel_orders'),
        ]);

        // Phase 8: room price bulk upsert
        register_rest_route(VIE_API_NAMESPACE, '/room-prices/bulk', [
            'methods'             => 'POST',
            'callback'            => [RoomPriceBulkController::class, 'bulk'],
            'permission_callback' => AuthMiddleware::requireCap('vie_manage_inventory'),
        ]);
    }

    private static function crudWithCaps(string $resource, string $controller, string $manageCap, string $viewCap): void
    {
        register_rest_route(VIE_API_NAMESPACE, "/{$resource}", [
            [
                'methods'             => 'GET',
                'callback'            => [$controller, 'index'],
                'permission_callback' => AuthMiddleware::requireCap($viewCap),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$controller, 'store'],
                'permission_callback' => AuthMiddleware::requireCap($manageCap),
            ],
        ]);

        register_rest_route(VIE_API_NAMESPACE, "/{$resource}/(?P<id>\\d+)", [
            [
                'methods'             => 'GET',
                'callback'            => [$controller, 'show'],
                'permission_callback' => AuthMiddleware::requireCap($viewCap),
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [$controller, 'update'],
                'permission_callback' => AuthMiddleware::requireCap($manageCap),
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$controller, 'destroy'],
                'permission_callback' => AuthMiddleware::requireCap($manageCap),
            ],
        ]);
    }

    private static function readAndCreateWithCaps(string $resource, string $controller, string $manageCap, string $viewCap): void
    {
        register_rest_route(VIE_API_NAMESPACE, "/{$resource}", [
            [
                'methods'             => 'GET',
                'callback'            => [$controller, 'index'],
                'permission_callback' => AuthMiddleware::requireCap($viewCap),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$controller, 'store'],
                'permission_callback' => AuthMiddleware::requireCap($manageCap),
            ],
        ]);
        register_rest_route(VIE_API_NAMESPACE, "/{$resource}/(?P<id>\\d+)", [
            [
                'methods'             => 'GET',
                'callback'            => [$controller, 'show'],
                'permission_callback' => AuthMiddleware::requireCap($viewCap),
            ],
        ]);
    }

    private static function listAndCreateWithCaps(string $resource, string $controller, string $manageCap, string $viewCap): void
    {
        register_rest_route(VIE_API_NAMESPACE, "/{$resource}", [
            [
                'methods'             => 'GET',
                'callback'            => [$controller, 'index'],
                'permission_callback' => AuthMiddleware::requireCap($viewCap),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$controller, 'store'],
                'permission_callback' => AuthMiddleware::requireCap($manageCap),
            ],
        ]);
    }

    private static function readAndDeleteWithCaps(string $resource, string $controller, string $manageCap, string $viewCap): void
    {
        register_rest_route(VIE_API_NAMESPACE, "/{$resource}", [
            [
                'methods'             => 'GET',
                'callback'            => [$controller, 'index'],
                'permission_callback' => AuthMiddleware::requireCap($viewCap),
            ],
        ]);
        register_rest_route(VIE_API_NAMESPACE, "/{$resource}/(?P<id>\\d+)", [
            [
                'methods'             => 'GET',
                'callback'            => [$controller, 'show'],
                'permission_callback' => AuthMiddleware::requireCap($viewCap),
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$controller, 'destroy'],
                'permission_callback' => AuthMiddleware::requireCap($manageCap),
            ],
        ]);
    }

    private static function readOnlyWithCaps(string $resource, string $controller, string $viewCap): void
    {
        register_rest_route(VIE_API_NAMESPACE, "/{$resource}", [
            [
                'methods'             => 'GET',
                'callback'            => [$controller, 'index'],
                'permission_callback' => AuthMiddleware::requireCap($viewCap),
            ],
        ]);
        register_rest_route(VIE_API_NAMESPACE, "/{$resource}/(?P<id>\\d+)", [
            [
                'methods'             => 'GET',
                'callback'            => [$controller, 'show'],
                'permission_callback' => AuthMiddleware::requireCap($viewCap),
            ],
        ]);
    }
}
