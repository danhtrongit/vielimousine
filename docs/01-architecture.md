# 01 — Kiến trúc

## 1.1. Mục tiêu

- **MVC layered hiện đại**: Domain → Service → Repository → Persistence.
- **Stateless REST**: tất cả nghiệp vụ expose qua `vie/v1/*`. AJAX `admin-ajax.php` chỉ là bridge cho legacy.
- **Admin SPA tách rời**: Vue 3 + PrimeVue, build tĩnh, gọi REST có JWT — **không phụ thuộc WP admin UI**.
- **Một nguồn sự thật**: mỗi entity có 1 Repository duy nhất; không có `$wpdb` rải rác ngoài Repository.
- **Schema versioned**: mỗi bảng có version riêng, migration idempotent qua `admin_init` + `after_switch_theme`.
- **PSR-4 autoload thủ công** (không cần Composer): `Vie\` → `inc/src/`.

## 1.2. Phân lớp

```
┌────────────────────────────────────────────────────────────────────┐
│ Presentation                                                       │
│   • Public shortcodes (PHP + vanilla JS)                          │
│   • Admin SPA (Vue 3 + PrimeVue, build artifact ở /admin-app/dist) │
├────────────────────────────────────────────────────────────────────┤
│ HTTP / Controller        REST routes  vie/v1/*  +  AJAX bridge    │
├────────────────────────────────────────────────────────────────────┤
│ Application              Services + Domain entities (DTOs)        │
├────────────────────────────────────────────────────────────────────┤
│ Infrastructure           Repositories + Schema installers + Email │
├────────────────────────────────────────────────────────────────────┤
│ Persistence              wpdb / wp_options / wp_user / posts      │
└────────────────────────────────────────────────────────────────────┘
```

## 1.3. Cấu trúc thư mục

```
vielimousine-child/
├── style.css
├── functions.php                ← bootstrap thuần (~10 dòng)
├── single-hotel.php             ← template (giữ ngoài MVC, chỉ render)
│
├── inc/
│   ├── bootstrap.php            ← autoloader + container + register hooks
│   │
│   ├── src/
│   │   ├── Plugin.php           ← entrypoint
│   │   ├── Container.php        ← service container đơn giản
│   │   │
│   │   ├── Support/
│   │   │   ├── Arr.php
│   │   │   ├── Money.php        ← format VND, làm tròn 1.000
│   │   │   ├── DateRange.php
│   │   │   ├── ChildAges.php    ← parse/serialize "5,7,9"
│   │   │   ├── ResponseEnvelope.php  ← chuẩn hoá envelope REST
│   │   │   └── Logger.php
│   │   │
│   │   ├── Schema/
│   │   │   ├── SchemaManager.php
│   │   │   ├── HotelSchema.php
│   │   │   ├── RoomSchema.php
│   │   │   ├── RoomPriceSchema.php
│   │   │   ├── SurchargeSchema.php
│   │   │   ├── SurchargePriceSchema.php
│   │   │   ├── TicketPriceSchema.php
│   │   │   ├── ProductCodeSchema.php
│   │   │   ├── CustomerSchema.php
│   │   │   ├── OrderSchema.php
│   │   │   ├── OrderItemSchema.php
│   │   │   ├── CouponSchema.php
│   │   │   ├── CouponUsageSchema.php
│   │   │   ├── PaymentLogSchema.php
│   │   │   ├── TokenSchema.php
│   │   │   └── ActivityLogSchema.php
│   │   │
│   │   ├── Repository/          ← 1 file / 1 bảng
│   │   │
│   │   ├── Pricing/
│   │   │   ├── GuestComposition.php
│   │   │   ├── RoomAllocation.php
│   │   │   ├── ChildPolicy.php
│   │   │   ├── PriceCalculator.php
│   │   │   ├── TicketCalculator.php
│   │   │   ├── PriceBreakdown.php
│   │   │   └── QuoteRequest.php
│   │   │
│   │   ├── Order/
│   │   │   ├── OrderCodeGenerator.php
│   │   │   ├── OrderService.php
│   │   │   ├── OrderDescription.php
│   │   │   └── OrderStateMachine.php
│   │   │
│   │   ├── Payment/
│   │   │   ├── PaymentLedger.php          ← write vie_payment_log
│   │   │   ├── SepayClient.php
│   │   │   ├── SepayCheckout.php
│   │   │   ├── SepayWebhook.php
│   │   │   └── PaymentService.php
│   │   │
│   │   ├── Email/
│   │   │   ├── MailService.php
│   │   │   ├── OrderMailer.php
│   │   │   ├── AdminOrderMailer.php       ← chứa seat_count
│   │   │   └── Templates/
│   │   │
│   │   ├── Coupon/
│   │   ├── Reporting/
│   │   │
│   │   ├── Auth/
│   │   │   ├── JwtService.php
│   │   │   ├── TokenRepository.php        ← vie_token
│   │   │   ├── LoginController.php
│   │   │   ├── RefreshController.php
│   │   │   └── AuthMiddleware.php
│   │   │
│   │   ├── Role/
│   │   │   ├── RoleInstaller.php
│   │   │   └── Capabilities.php
│   │   │
│   │   ├── Activity/
│   │   │   └── ActivityLogger.php
│   │   │
│   │   ├── Http/
│   │   │   ├── RestRouter.php
│   │   │   ├── Controllers/
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── HotelController.php
│   │   │   │   ├── RoomController.php
│   │   │   │   ├── PriceController.php
│   │   │   │   ├── QuoteController.php
│   │   │   │   ├── OrderController.php
│   │   │   │   ├── PaymentController.php
│   │   │   │   ├── CouponController.php
│   │   │   │   ├── CustomerController.php
│   │   │   │   ├── ReportController.php
│   │   │   │   ├── ProductCodeController.php
│   │   │   │   └── ActivityLogController.php
│   │   │   └── AjaxBridge.php
│   │   │
│   │   ├── Frontend/
│   │   │   ├── Shortcode/
│   │   │   │   ├── HotelSearchShortcode.php
│   │   │   │   ├── HotelRoomsShortcode.php
│   │   │   │   ├── CheckoutShortcode.php
│   │   │   │   └── OrderSuccessShortcode.php
│   │   │   ├── Assets.php
│   │   │   └── AdminAppLoader.php        ← serve SPA tại /vie-admin
│   │   │
│   │   └── Validation/
│   │       └── Schemas/                  ← class validate input REST
│   │
│   ├── templates/
│   │   └── frontend/
│   │       ├── search-form.php
│   │       ├── room-card.php
│   │       ├── checkout.php
│   │       └── success.php
│   │
│   └── assets/
│       ├── css/
│       │   └── frontend-*.css
│       └── js/
│           └── frontend-*.js
│
├── admin-app/                              ← Vue 3 + PrimeVue source
│   ├── package.json
│   ├── vite.config.ts
│   ├── tsconfig.json
│   ├── index.html
│   ├── src/
│   │   ├── main.ts
│   │   ├── router.ts
│   │   ├── api/
│   │   ├── stores/
│   │   ├── layouts/
│   │   ├── views/
│   │   ├── components/
│   │   ├── composables/
│   │   └── assets/
│   └── dist/                               ← build artifact
│
└── languages/
```

## 1.4. Bootstrap

`functions.php` chỉ 3 việc:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( version_compare( PHP_VERSION, '8.2', '<' ) ) return;

define( 'VIE_CHILD_VERSION', '2.0.0' );
define( 'VIE_CHILD_PATH', __DIR__ );
define( 'VIE_CHILD_URL', get_stylesheet_directory_uri() );
define( 'VIE_API_NAMESPACE', 'vie/v1' );

require_once __DIR__ . '/inc/bootstrap.php';
\Vie\Plugin::boot();
```

`inc/bootstrap.php`:

```php
<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Vie\\')) return;
    $rel  = substr($class, 4);
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($file)) require_once $file;
});
```

`Plugin::boot()`:

```php
namespace Vie;

final class Plugin {
    public static function boot(): void {
        Schema\SchemaManager::install();
        Role\RoleInstaller::install();

        add_action('rest_api_init', [Http\RestRouter::class, 'register']);
        add_action('init', [Frontend\Shortcode\HotelSearchShortcode::class, 'register']);
        add_action('init', [Frontend\Shortcode\HotelRoomsShortcode::class, 'register']);
        add_action('init', [Frontend\Shortcode\CheckoutShortcode::class, 'register']);
        add_action('init', [Frontend\Shortcode\OrderSuccessShortcode::class, 'register']);
        add_action('init', [Frontend\AdminAppLoader::class, 'register']);
        add_action('wp_enqueue_scripts', [Frontend\Assets::class, 'register']);

        Http\AjaxBridge::register();
    }
}
```

`SchemaManager::install()` iterate qua mảng `[table => SchemaClass::class]`, đọc option `vie_schema_versions` (assoc array), gọi `dbDelta()` nếu version khác.

## 1.5. Service container

Đơn giản, không bind interface — chỉ resolve singleton.

```php
namespace Vie;

final class Container {
    private static array $instances = [];

    public static function get(string $class): object {
        return self::$instances[$class] ??= new $class();
    }
}
```

Controller:

```php
$repo = Container::get(Repository\OrderRepository::class);
```

## 1.6. Quy ước code

- `declare(strict_types=1);` ở mọi file.
- DTO = `final readonly class` (PHP 8.2).
- Repository expose array hoặc DTO; **không** trả `stdClass` ra ngoài.
- REST controllers chỉ: validate → service → response envelope. Không truy vấn DB.
- Service không động vào superglobals (`$_REQUEST`, `$_POST`).
- Tiền tệ làm tròn về 1.000đ tại Service trước khi persist.
- Log lỗi qua `Logger::error()`, không log payload nhạy cảm (token, password).
- Cấm `eval`, `extract`, `create_function`, `assert()` với string.
- Tên class English (PascalCase), comment & UI label tiếng Việt.

## 1.7. Vòng đời migration

- Mỗi `*Schema` class có `const VERSION = '1.0.0';` + method `static install(\wpdb $wpdb): void`.
- `SchemaManager` chạy:
    1. `after_switch_theme`: install tất cả.
    2. `admin_init` (giới hạn 1 lần / request bằng static flag): check version, install nếu khác.
    3. Sau khi install xong, ghi `update_option('vie_schema_versions', $map)`.
- Không bao giờ `DROP TABLE` trong code. Cleanup làm thủ công.
