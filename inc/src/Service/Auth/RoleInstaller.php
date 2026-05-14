<?php
declare(strict_types=1);

namespace Vie\Service\Auth;

final class RoleInstaller
{
    public const ROLE_MANAGER       = 'vie_manager';
    public const ROLE_SALES         = 'vie_sales';
    public const ROLE_ACCOUNTANT    = 'vie_accountant';
    public const ROLE_HOTEL_MANAGER = 'vie_hotel_manager';

    public const ALL_CAPS = [
        'vie_manage_hotels',
        'vie_manage_inventory',
        'vie_manage_orders',
        'vie_view_all_orders',
        'vie_view_own_orders',
        'vie_view_orders_own_hotel',
        'vie_create_orders',
        'vie_cancel_orders',
        'vie_manage_customers',
        'vie_manage_coupons',
        'vie_manage_payments',
        'vie_view_reports',
        'vie_view_reports_own_hotel',
        'vie_view_audit',
        'vie_use_price_check',
        'vie_print_order',
        'vie_manage_inventory_own_hotel',
    ];

    public static function install(): void
    {
        $admin = get_role('administrator');
        if ($admin !== null) {
            foreach (self::ALL_CAPS as $cap) {
                $admin->add_cap($cap);
            }
        }

        self::ensureRole(self::ROLE_MANAGER, 'Vie Manager', [
            'read'                  => true,
            'vie_manage_hotels'     => true,
            'vie_manage_inventory'  => true,
            'vie_manage_orders'     => true,
            'vie_view_all_orders'   => true,
            'vie_create_orders'     => true,
            'vie_cancel_orders'     => true,
            'vie_manage_customers'  => true,
            'vie_manage_coupons'    => true,
            'vie_view_reports'      => true,
            'vie_use_price_check'   => true,
            'vie_print_order'       => true,
        ]);

        self::ensureRole(self::ROLE_SALES, 'Vie Sales', [
            'read'                => true,
            'vie_create_orders'   => true,
            'vie_view_own_orders' => true,
            'vie_cancel_orders'   => true,
            'vie_use_price_check' => true,
            'vie_print_order'     => true,
        ]);

        self::ensureRole(self::ROLE_ACCOUNTANT, 'Vie Accountant', [
            'read'                  => true,
            'vie_view_all_orders'   => true,
            'vie_view_reports'      => true,
            'vie_manage_payments'   => true,
        ]);

        self::ensureRole(self::ROLE_HOTEL_MANAGER, 'Vie Hotel Manager', [
            'read'                            => true,
            'vie_view_orders_own_hotel'       => true,
            'vie_manage_inventory_own_hotel'  => true,
            'vie_view_reports_own_hotel'      => true,
        ]);
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
    }
}
