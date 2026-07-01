<?php
declare(strict_types=1);

namespace Vie\Service\Auth;

final class RoleInstaller
{
    public const ROLE_SALES         = 'vie_sales';
    public const ROLE_HOTEL_MANAGER = 'vie_hotel_manager';

    public const CAP_MANAGE_USERS = 'vie_manage_users';
    public const CAP_MANAGE_BACKUP = 'vie_manage_backup';
    public const CAP_MANAGE_PRICING = 'vie_manage_pricing';

    /**
     * "Quản lý khách sạn" = chỉ nội dung: khách sạn + loại phòng + hình ảnh + báo cáo.
     * KHÔNG có quyền giá (vie_manage_pricing), đơn hàng, thanh toán, cài đặt…
     */
    public const ROLE_HOTEL_MANAGER_CAPS = [
        'vie_manage_inventory',
        'vie_manage_media',
        'vie_view_reports',
    ];

    public const OBSOLETE_ROLES = [
        'vie_manager',
        'vie_accountant',
    ];

    public const OBSOLETE_CAPS = [
        'vie_view_orders_own_hotel',
        'vie_view_reports_own_hotel',
        'vie_manage_inventory_own_hotel',
    ];

    public const ALL_CAPS = [
        'vie_manage_hotels',
        'vie_manage_inventory',
        'vie_manage_orders',
        'vie_view_all_orders',
        'vie_view_own_orders',
        'vie_create_orders',
        'vie_cancel_orders',
        'vie_manage_customers',
        'vie_manage_coupons',
        'vie_manage_payments',
        'vie_view_reports',
        'vie_view_audit',
        'vie_use_price_check',
        'vie_print_order',
        'vie_manage_settings',
        'vie_manage_media',
    ];

    public static function install(): void
    {
        $admin = get_role('administrator');
        if ($admin !== null) {
            foreach (self::ALL_CAPS as $cap) {
                $admin->add_cap($cap);
            }
            // Quản lý tài khoản nhân viên: chỉ administrator, không nằm trong ALL_CAPS
            // vì ALL_CAPS được cấp nguyên khối cho vie_hotel_manager.
            $admin->add_cap(self::CAP_MANAGE_USERS);
            // Backup/Restore: chỉ administrator (rất nhạy cảm, không nằm trong ALL_CAPS).
            $admin->add_cap(self::CAP_MANAGE_BACKUP);
            // Sửa giá (bảng giá/phụ thu/vé) + xem giá vốn·lợi nhuận: chỉ administrator.
            $admin->add_cap(self::CAP_MANAGE_PRICING);
            foreach (self::OBSOLETE_CAPS as $cap) {
                $admin->remove_cap($cap);
            }
        }

        self::ensureRole(self::ROLE_HOTEL_MANAGER, 'Quản lý khách sạn', array_merge(
            ['read' => true],
            array_fill_keys(self::ROLE_HOTEL_MANAGER_CAPS, true)
        ));

        self::ensureRole(self::ROLE_SALES, 'Sale', [
            'read'                => true,
            'vie_create_orders'   => true,
            'vie_view_own_orders' => true,
            'vie_cancel_orders'   => true,
            'vie_use_price_check' => true,
            'vie_print_order'     => true,
        ]);

        foreach ([self::ROLE_HOTEL_MANAGER, self::ROLE_SALES] as $slug) {
            $role = get_role($slug);
            if ($role !== null) {
                $role->remove_cap(self::CAP_MANAGE_USERS);
                $role->remove_cap(self::CAP_MANAGE_BACKUP);
                $role->remove_cap(self::CAP_MANAGE_PRICING);
            }
        }

        // ensureRole() chỉ THÊM cap. Site đã deploy từng cấp full ALL_CAPS cho
        // "Quản lý khách sạn" nên phải GỠ tường minh mọi quyền không còn thuộc role
        // này (đơn hàng, thanh toán, khách, coupon, cài đặt, audit, in, price-check…),
        // giữ lại đúng ROLE_HOTEL_MANAGER_CAPS.
        $hm = get_role(self::ROLE_HOTEL_MANAGER);
        if ($hm !== null) {
            foreach (self::ALL_CAPS as $cap) {
                if (!in_array($cap, self::ROLE_HOTEL_MANAGER_CAPS, true)) {
                    $hm->remove_cap($cap);
                }
            }
        }

        self::migrateObsoleteRoles();
    }

    private static function ensureRole(string $slug, string $name, array $caps): void
    {
        $existing = get_role($slug);
        if ($existing === null) {
            add_role($slug, $name, $caps);
            return;
        }
        foreach ($caps as $cap => $grant) {
            if ($grant === true) {
                $existing->add_cap($cap);
            }
        }
        foreach (self::OBSOLETE_CAPS as $cap) {
            $existing->remove_cap($cap);
        }
    }

    private static function migrateObsoleteRoles(): void
    {
        foreach (self::OBSOLETE_ROLES as $oldRole) {
            $users = get_users(['role' => $oldRole, 'fields' => ['ID']]);
            foreach ($users as $u) {
                $wpUser = new \WP_User((int) $u->ID);
                $wpUser->remove_role($oldRole);
                if (!in_array(self::ROLE_HOTEL_MANAGER, (array) $wpUser->roles, true)) {
                    $wpUser->add_role(self::ROLE_HOTEL_MANAGER);
                }
            }
            if (get_role($oldRole) !== null) {
                remove_role($oldRole);
            }
        }
    }
}
