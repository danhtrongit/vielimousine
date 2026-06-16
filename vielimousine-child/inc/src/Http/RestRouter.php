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
use Vie\Http\Controllers\InvoiceController;
use Vie\Http\Controllers\MediaController;
use Vie\Http\Controllers\OrderActionController;
use Vie\Http\Controllers\OrderController;
use Vie\Http\Controllers\OrderItemController;
use Vie\Http\Controllers\OrderLookupController;
use Vie\Http\Controllers\PaymentLogController;
use Vie\Http\Controllers\PublicOrderController;
use Vie\Http\Controllers\QuoteController;
use Vie\Http\Controllers\QuoteInquiryController;
use Vie\Http\Controllers\RoomController;
use Vie\Http\Controllers\SettingsController;
use Vie\Http\Controllers\PricingCellsController;
use Vie\Http\Controllers\PricingMatrixController;
use Vie\Http\Controllers\PublicRoomPriceController;
use Vie\Http\Controllers\ReportsController;
use Vie\Http\Controllers\UserController;
use Vie\Http\Controllers\RoomPriceBulkController;
use Vie\Http\Controllers\RoomPriceController;
use Vie\Http\Controllers\SepayWebhookController;
use Vie\Http\Controllers\SurchargeController;
use Vie\Http\Controllers\SurchargePriceBulkController;
use Vie\Http\Controllers\SurchargePriceController;
use Vie\Http\Controllers\TicketPriceBulkController;
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

        register_rest_route(VIE_API_NAMESPACE, '/public/quote-inquiries', [
            'methods'             => 'POST',
            'callback'            => [QuoteInquiryController::class, 'create'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(VIE_API_NAMESPACE, '/public/room-prices', [
            'methods'             => 'GET',
            'callback'            => [PublicRoomPriceController::class, 'index'],
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

        // Customers
        self::crudWithCaps('customers', CustomerController::class, 'vie_manage_customers', 'vie_manage_customers');

        // Orders — viewable by sales (filtered via queryScope); creatable by sales+
        self::crudWithCaps('orders', OrderController::class, 'vie_create_orders', 'vie_view_own_orders');

        // Order drafts — lưu nháp đơn (status='draft'); cùng cap với tạo đơn.
        // 'draft' không khớp regex \d+ nên không đụng route '/orders/{id}'.
        register_rest_route(VIE_API_NAMESPACE, '/orders/draft', [
            'methods'             => 'POST',
            'callback'            => [OrderController::class, 'storeDraft'],
            'permission_callback' => AuthMiddleware::requireCap('vie_create_orders'),
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/orders/draft/(?P<id>\\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [OrderController::class, 'showDraft'],
                'permission_callback' => AuthMiddleware::requireCap('vie_create_orders'),
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [OrderController::class, 'updateDraft'],
                'permission_callback' => AuthMiddleware::requireCap('vie_create_orders'),
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [OrderController::class, 'destroyDraft'],
                'permission_callback' => AuthMiddleware::requireCap('vie_create_orders'),
            ],
        ]);

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
        register_rest_route(VIE_API_NAMESPACE, '/orders/(?P<id>\\d+)/transition', [
            'methods'             => 'POST',
            'callback'            => [OrderActionController::class, 'transition'],
            'permission_callback' => AuthMiddleware::requireCap('vie_manage_orders'),
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/orders/(?P<id>\\d+)/send-checkin-code', [
            'methods'             => 'POST',
            'callback'            => [OrderActionController::class, 'sendCheckinCode'],
            'permission_callback' => AuthMiddleware::requireCap('vie_manage_orders'),
        ]);

        // Invoice — JSON data for Vue-rendered preview (primary)
        register_rest_route(VIE_API_NAMESPACE, '/orders/(?P<id>\\d+)/invoice/data', [
            'methods'             => 'GET',
            'callback'            => [InvoiceController::class, 'data'],
            'permission_callback' => AuthMiddleware::requireCap('vie_print_order'),
        ]);
        // Invoice — server-rendered PDF stream (fallback/legacy via dompdf)
        register_rest_route(VIE_API_NAMESPACE, '/orders/(?P<id>\\d+)/invoice', [
            'methods'             => 'GET',
            'callback'            => [InvoiceController::class, 'pdf'],
            'permission_callback' => AuthMiddleware::requireCap('vie_print_order'),
        ]);

        // Phase 8: room price bulk upsert
        register_rest_route(VIE_API_NAMESPACE, '/room-prices/bulk', [
            'methods'             => 'POST',
            'callback'            => [RoomPriceBulkController::class, 'bulk'],
            'permission_callback' => AuthMiddleware::requireCap('vie_manage_inventory'),
        ]);

        // Phase 17: unified pricing matrix endpoints
        register_rest_route(VIE_API_NAMESPACE, '/pricing/matrix', [
            'methods'             => 'GET',
            'callback'            => [PricingMatrixController::class, 'index'],
            'permission_callback' => AuthMiddleware::requireCap('vie_manage_inventory'),
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/pricing/cells', [
            'methods'             => 'POST',
            'callback'            => [PricingCellsController::class, 'save'],
            'permission_callback' => AuthMiddleware::requireCap('vie_manage_inventory'),
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/surcharge-prices/bulk', [
            'methods'             => 'POST',
            'callback'            => [SurchargePriceBulkController::class, 'bulk'],
            'permission_callback' => AuthMiddleware::requireCap('vie_manage_inventory'),
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/ticket-prices/bulk', [
            'methods'             => 'POST',
            'callback'            => [TicketPriceBulkController::class, 'bulk'],
            'permission_callback' => AuthMiddleware::requireCap('vie_manage_inventory'),
        ]);

        // Phase 18: reports + users lookup
        register_rest_route(VIE_API_NAMESPACE, '/users', [
            [
                'methods'             => 'GET',
                'callback'            => [UserController::class, 'index'],
                'permission_callback' => AuthMiddleware::requireCap('vie_view_reports'),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [UserController::class, 'store'],
                'permission_callback' => AuthMiddleware::requireCap('vie_manage_users'),
            ],
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/users/(?P<id>\\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [UserController::class, 'show'],
                'permission_callback' => AuthMiddleware::requireCap('vie_manage_users'),
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [UserController::class, 'update'],
                'permission_callback' => AuthMiddleware::requireCap('vie_manage_users'),
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [UserController::class, 'destroy'],
                'permission_callback' => AuthMiddleware::requireCap('vie_manage_users'),
            ],
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/reports/by-hotel', [
            'methods'             => 'GET',
            'callback'            => [ReportsController::class, 'byHotel'],
            'permission_callback' => AuthMiddleware::requireCap('vie_view_reports'),
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/reports/orders-export', [
            'methods'             => 'GET',
            'callback'            => [ReportsController::class, 'ordersDetailedExport'],
            'permission_callback' => AuthMiddleware::requireCap('vie_view_reports'),
        ]);

        // Phase 11: settings
        $manageSettings = AuthMiddleware::requireCap('vie_manage_settings');
        register_rest_route(VIE_API_NAMESPACE, '/settings/general', [
            ['methods' => 'GET', 'callback' => [SettingsController::class, 'getGeneral'],   'permission_callback' => $manageSettings],
            ['methods' => 'PUT', 'callback' => [SettingsController::class, 'updateGeneral'],'permission_callback' => $manageSettings],
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/settings/email', [
            ['methods' => 'GET', 'callback' => [SettingsController::class, 'getEmail'],   'permission_callback' => $manageSettings],
            ['methods' => 'PUT', 'callback' => [SettingsController::class, 'updateEmail'],'permission_callback' => $manageSettings],
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/settings/email/test', [
            'methods'             => 'POST',
            'callback'            => [SettingsController::class, 'testEmail'],
            'permission_callback' => $manageSettings,
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/settings/sepay', [
            ['methods' => 'GET', 'callback' => [SettingsController::class, 'getSepay'],   'permission_callback' => $manageSettings],
            ['methods' => 'PUT', 'callback' => [SettingsController::class, 'updateSepay'],'permission_callback' => $manageSettings],
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/settings/invoice', [
            ['methods' => 'GET', 'callback' => [InvoiceController::class, 'getSettings'],   'permission_callback' => $manageSettings],
            ['methods' => 'PUT', 'callback' => [InvoiceController::class, 'updateSettings'],'permission_callback' => $manageSettings],
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/settings/order-sources', [
            ['methods' => 'GET', 'callback' => [SettingsController::class, 'getSources'],   'permission_callback' => AuthMiddleware::requireLogin()],
            ['methods' => 'PUT', 'callback' => [SettingsController::class, 'updateSources'],'permission_callback' => $manageSettings],
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/settings/hotel-sync/run', [
            'methods'             => 'POST',
            'callback'            => [SettingsController::class, 'runHotelSync'],
            'permission_callback' => $manageSettings,
        ]);

        // Phase 14: media library
        $manageMedia = AuthMiddleware::requireCap('vie_manage_media');
        register_rest_route(VIE_API_NAMESPACE, '/media', [
            ['methods' => 'GET',  'callback' => [MediaController::class, 'index'], 'permission_callback' => $manageMedia],
            ['methods' => 'POST', 'callback' => [MediaController::class, 'store'], 'permission_callback' => $manageMedia],
        ]);
        register_rest_route(VIE_API_NAMESPACE, '/media/(?P<id>\\d+)', [
            ['methods' => 'GET',        'callback' => [MediaController::class, 'show'],    'permission_callback' => $manageMedia],
            ['methods' => 'PUT,PATCH',  'callback' => [MediaController::class, 'update'],  'permission_callback' => $manageMedia],
            ['methods' => 'DELETE',     'callback' => [MediaController::class, 'destroy'], 'permission_callback' => $manageMedia],
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
