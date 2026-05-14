# 12 — Roles & Permissions

## 12.1. Roles

| Slug | Tên | Mô tả |
|---|---|---|
| `administrator` | Quản trị | Mặc định WP, có tất cả `vie_*` caps |
| `hotel_manager` | Quản lý Khách sạn | Xem/sửa tồn kho & giá thuộc KS được gán |
| `sales` | Nhân viên Sales | Tạo & quản lý đơn của mình |
| `accountant` | Kế toán | Xem báo cáo, ghi nhận thanh toán thủ công |

## 12.2. Capabilities

| Cap | Mô tả |
|---|---|
| `vie_manage_hotels` | CRUD hotel + policy |
| `vie_manage_inventory` | CRUD room, room_price, surcharge, ticket_price |
| `vie_manage_inventory_own_hotel` | Như trên, chỉ với hotel được gán |
| `vie_manage_orders` | Xem & sửa toàn bộ đơn |
| `vie_view_all_orders` | Xem toàn bộ đơn (không sửa) |
| `vie_create_orders` | Tạo đơn từ admin SPA |
| `vie_view_own_orders` | Chỉ thấy đơn `sales_user_id = current_user` |
| `vie_cancel_orders` | Hủy đơn |
| `vie_manage_customers` | CRUD khách hàng |
| `vie_manage_coupons` | CRUD coupon |
| `vie_manage_payments` | Ghi nhận thanh toán / refund |
| `vie_view_reports` | Xem tất cả báo cáo |
| `vie_view_reports_own_hotel` | Chỉ báo cáo của hotel được gán |
| `vie_view_audit` | Xem `vie_activity_log` |
| `vie_use_price_check` | Dùng tool Price Check |
| `vie_print_order` | In đơn |
| `vie_manage_settings` | = `manage_options` |

## 12.3. Map role → caps

```php
final class Capabilities {
    public static function map(): array {
        return [
            'administrator' => [
                'vie_manage_hotels','vie_manage_inventory','vie_manage_orders',
                'vie_view_all_orders','vie_create_orders','vie_cancel_orders',
                'vie_manage_customers','vie_manage_coupons','vie_manage_payments',
                'vie_view_reports','vie_view_audit','vie_use_price_check','vie_print_order',
            ],
            'hotel_manager' => [
                'vie_manage_inventory_own_hotel','vie_view_reports_own_hotel',
                'vie_view_orders_own_hotel',
            ],
            'sales' => [
                'vie_create_orders','vie_view_own_orders','vie_cancel_orders',
                'vie_use_price_check','vie_print_order','read',
            ],
            'accountant' => [
                'vie_view_reports','vie_manage_payments','vie_view_all_orders','read',
            ],
        ];
    }
}
```

## 12.4. Hotel ownership

`wp_usermeta.meta_key = 'vie_managed_hotels'` lưu JSON `[hotel_id]`.

`Repository\OrderRepository::queryScope($user)` thêm điều kiện:

```php
if ($user->has_cap('vie_view_all_orders')) {
    // no scope
} elseif ($user->has_cap('vie_view_orders_own_hotel')) {
    $hotelIds = (array) json_decode(get_user_meta($user->ID, 'vie_managed_hotels', true) ?: '[]', true);
    $where[] = "EXISTS (SELECT 1 FROM {$itemTable} i WHERE i.order_id = o.id AND i.hotel_id IN (...))";
} elseif ($user->has_cap('vie_view_own_orders')) {
    $where[] = $wpdb->prepare("o.sales_user_id = %d", $user->ID);
} else {
    return null; // 403
}
```

## 12.5. RoleInstaller

`RoleInstaller::install()` chạy ở `after_switch_theme` + check version `vie_roles_version`:

```php
foreach (Capabilities::map() as $slug => $caps) {
    $role = get_role($slug);
    if (!$role) {
        $role = add_role($slug, $this->labels[$slug], []);
    }
    foreach ($caps as $cap) $role->add_cap($cap);
}
update_option('vie_roles_version', self::VERSION);
```

`VERSION` tăng khi đổi map → re-apply.

## 12.6. Sales user account

Trang SPA "Tài khoản Sales" (`/users/sales`):

- List user role `sales` + cột "đơn / tháng", "doanh thu / tháng", "trạng thái".
- Tạo mới: form `username, email, display_name, password, source_label` → `POST /users/sales`.
- Action: reset password, disable, gán `vie_managed_hotels`.

Cap: `manage_options`.

## 12.7. Restrict WP backend cho non-admin

`hotel_manager`, `sales`, `accountant` **không được** dùng WP admin UI cũ. Force redirect tới `/vie-admin`:

```php
add_action('admin_init', function () {
    $user = wp_get_current_user();
    if (!$user || $user->ID === 0) return;
    if (current_user_can('manage_options')) return;
    if (array_intersect($user->roles, ['hotel_manager','sales','accountant'])) {
        wp_safe_redirect( home_url('/vie-admin/') );
        exit;
    }
});

add_filter('show_admin_bar', function ($show) {
    if (current_user_can('manage_options')) return $show;
    return false;
});
```

## 12.8. Activity log scope

Bảng `vie_activity_log` ghi mọi hành động state-changing:

- Auth: `login`, `login_fail`, `logout`, `refresh_token_rotate`, `refresh_token_reuse_detected`.
- Order: `create`, `update`, `confirm`, `cancel`, `complete`, `item_cancel`, `recalc_cost`.
- Payment: `payment_log:create`.
- Inventory bulk: `room_price:bulk_update`, `ticket_price:bulk_update`, `surcharge:bulk_update`.
- Coupon: `create`, `update`, `delete`, `usage:create`.
- Settings: `update`.

Service helper: `ActivityLogger::log(string $entity, int $id, string $action, $before, $after)`.

## 12.9. Cap check tại REST

Mỗi route khai báo `permission_callback`:

```php
register_rest_route('vie/v1', '/orders', [
    'methods'  => 'GET',
    'callback' => [OrderController::class, 'index'],
    'permission_callback' => fn() => current_user_can('vie_view_all_orders')
        || current_user_can('vie_view_orders_own_hotel')
        || current_user_can('vie_view_own_orders'),
]);
```

Trong controller: gọi `Repository::queryScope($user)` để thực thi scope.

## 12.10. UI hide vs disable

SPA dùng `<Can>` để **ẩn** tính năng không thuộc role; BE **luôn** check cap để chống bypass. Frontend chỉ là UX hint.
