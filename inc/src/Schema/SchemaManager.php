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
        'vie_customer'        => CustomerSchema::class,
        'vie_order'           => OrderSchema::class,
        'vie_order_item'      => OrderItemSchema::class,
        'vie_payment_log'     => PaymentLogSchema::class,
        'vie_coupon'          => CouponSchema::class,
        'vie_coupon_usage'    => CouponUsageSchema::class,
        'vie_token'           => TokenSchema::class,
        'vie_activity_log'    => ActivityLogSchema::class,
        'vie_quote_inquiry'   => QuoteInquirySchema::class,
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

        self::dropProductCode();
        self::backfillCustomerBookingCount();
    }

    /**
     * One-shot backfill: recompute booking_count cho mọi customer từ vie_order.
     * Sau khi vá bug counter (Phase 14), data cũ vẫn = 0 nên cần chạy lại.
     */
    private static function backfillCustomerBookingCount(): void
    {
        if (get_option('vie_backfill_customer_booking_count_v1') === 'done') {
            return;
        }
        try {
            \Vie\Container::get(\Vie\Repository\CustomerRepository::class)
                ->recomputeAllBookingCounts();
            update_option('vie_backfill_customer_booking_count_v1', 'done', false);
        } catch (\Throwable $e) {
            error_log('[vie] backfill booking_count failed: ' . $e->getMessage());
        }
    }

    /**
     * One-shot migration: xóa hoàn toàn dấu vết ProductCode khỏi DB.
     */
    private static function dropProductCode(): void
    {
        if (get_option('vie_drop_product_code_v1') === 'done') {
            return;
        }
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}vie_product_code");

        $orderItem = $wpdb->prefix . 'vie_order_item';
        $cols = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
                    AND COLUMN_NAME IN ('product_code','product_code_id')",
                DB_NAME,
                $orderItem
            )
        );
        foreach ($cols as $col) {
            $wpdb->query("ALTER TABLE {$orderItem} DROP COLUMN `{$col}`");
        }

        $idx = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT INDEX_NAME FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
                    AND INDEX_NAME = 'idx_product_code' LIMIT 1",
                DB_NAME,
                $orderItem
            )
        );
        if ($idx) {
            $wpdb->query("ALTER TABLE {$orderItem} DROP INDEX idx_product_code");
        }

        // Cũng xóa khỏi version-map nếu còn lưu
        $versions = get_option('vie_schema_versions', []);
        if (is_array($versions) && isset($versions['vie_product_code'])) {
            unset($versions['vie_product_code']);
            update_option('vie_schema_versions', $versions, false);
        }

        update_option('vie_drop_product_code_v1', 'done', false);
    }
}
