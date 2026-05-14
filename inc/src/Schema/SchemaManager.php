<?php
declare(strict_types=1);

namespace Vie\Schema;

final class SchemaManager
{
    private static bool $ran = false;

    private const SCHEMAS = [
        'vie_hotel'           => HotelSchema::class,
        'vie_room'            => RoomSchema::class,
        'vie_room_price'      => RoomPriceSchema::class,
        'vie_surcharge'       => SurchargeSchema::class,
        'vie_surcharge_price' => SurchargePriceSchema::class,
        'vie_ticket_price'    => TicketPriceSchema::class,
        'vie_product_code'    => ProductCodeSchema::class,
        'vie_customer'        => CustomerSchema::class,
        'vie_order'           => OrderSchema::class,
        'vie_order_item'      => OrderItemSchema::class,
        'vie_payment_log'     => PaymentLogSchema::class,
        'vie_coupon'          => CouponSchema::class,
        'vie_coupon_usage'    => CouponUsageSchema::class,
        'vie_token'           => TokenSchema::class,
        'vie_activity_log'    => ActivityLogSchema::class,
    ];

    public static function install(): void
    {
        if (self::$ran) {
            return;
        }
        self::$ran = true;

        global $wpdb;

        /** @var array<string, string> $stored */
        $stored  = get_option('vie_schema_versions', []);
        $changed = false;

        foreach (self::SCHEMAS as $table => $class) {
            $version = $class::VERSION;

            if (($stored[$table] ?? '') === $version) {
                continue;
            }

            if (!$changed) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                $changed = true;
            }

            $class::install($wpdb);
            $stored[$table] = $version;
        }

        if ($changed) {
            update_option('vie_schema_versions', $stored, false);
        }
    }
}
