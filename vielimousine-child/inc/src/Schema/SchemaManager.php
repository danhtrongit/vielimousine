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
        'vie_booking_quote'   => BookingQuoteSchema::class,
        'vie_booking_quote_payment' => BookingQuotePaymentSchema::class,
        'vie_sepay_webhook_event' => SepayWebhookEventSchema::class,
    ];

    /** A schema may create more than the table used as its version key. */
    private const COMPANION_TABLES = [
        'vie_booking_quote_payment' => ['vie_booking_quote_payment_receipt'],
    ];

    public static function install(): void
    {
        if (self::$ran) {
            return;
        }
        global $wpdb;

        /** @var array<string, string> $stored */
        $stored  = get_option('vie_schema_versions', []);
        $stored = is_array($stored) ? $stored : [];
        $original = $stored;
        $failed = false;

        try {
            // One inventory query per request also repairs databases restored
            // with a version option but without all of their feature tables.
            $tables = self::existingTables($wpdb);
        } catch (\Throwable $e) {
            error_log('[vie] Cannot inspect schema tables: ' . $e->getMessage());
            return;
        }

        foreach (self::SCHEMAS as $table => $class) {
            $version = $class::VERSION;
            $required = array_merge([$table], self::COMPANION_TABLES[$table] ?? []);

            if (($stored[$table] ?? '') === $version && self::hasTables($wpdb, $tables, $required)) {
                continue;
            }

            // A failed dbDelta must not print SQL into REST output or be
            // permanently marked as installed. Keep diagnostics in the log.
            $previousSuppression = $wpdb->suppress_errors(true);
            try {
                if (!function_exists('dbDelta')) {
                    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                }

                $class::install($wpdb);
                $installError = (string) $wpdb->last_error;
                $tables = self::existingTables($wpdb);
                if ($installError !== '' || !self::hasTables($wpdb, $tables, $required)) {
                    throw new \RuntimeException($installError !== '' ? $installError : 'Required table is still missing');
                }
                $stored[$table] = $version;
            } catch (\Throwable $e) {
                $failed = true;
                unset($stored[$table]);
                error_log('[vie] Schema installation failed for ' . $table . ': ' . $e->getMessage());
            } finally {
                $wpdb->suppress_errors($previousSuppression);
            }
        }

        if ($stored !== $original) {
            update_option('vie_schema_versions', $stored, false);
        }

        if ($failed) {
            return;
        }

        self::dropProductCode();
        self::backfillCustomerBookingCount();
        self::migrateOrderDraftColumns();
        self::$ran = true;
    }

    /** @return array<string,true> */
    private static function existingTables(\wpdb $wpdb): array
    {
        $names = $wpdb->get_col($wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $wpdb->esc_like($wpdb->prefix . 'vie_') . '%',
        ));
        if (!is_array($names) || (string) $wpdb->last_error !== '') {
            throw new \RuntimeException('Could not read schema table inventory');
        }
        return array_fill_keys($names, true);
    }

    private static function hasTables(\wpdb $wpdb, array $tables, array $required): bool
    {
        foreach ($required as $table) {
            if (!isset($tables[$wpdb->prefix . $table])) {
                return false;
            }
        }
        return true;
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

    /**
     * One-shot migration: thêm cột draft_payload + nới NOT NULL cho các cột
     * phục vụ đơn nháp dở dang (code/customer_id/checkin/checkout/nights).
     * dbDelta không đáng tin khi đổi NULL-ability → dùng ALTER tường minh, idempotent.
     */
    private static function migrateOrderDraftColumns(): void
    {
        if (get_option('vie_order_draft_columns_v1') === 'done') {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'vie_order';

        $hasDraftPayload = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'draft_payload'",
            DB_NAME,
            $table
        ));
        if ($hasDraftPayload === 0) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN draft_payload LONGTEXT DEFAULT NULL AFTER user_agent");
        }

        $wpdb->query("ALTER TABLE {$table} MODIFY code VARCHAR(20) DEFAULT NULL");
        $wpdb->query("ALTER TABLE {$table} MODIFY customer_id BIGINT UNSIGNED DEFAULT NULL");
        $wpdb->query("ALTER TABLE {$table} MODIFY checkin DATE DEFAULT NULL");
        $wpdb->query("ALTER TABLE {$table} MODIFY checkout DATE DEFAULT NULL");
        $wpdb->query("ALTER TABLE {$table} MODIFY nights TINYINT UNSIGNED DEFAULT NULL");

        update_option('vie_order_draft_columns_v1', 'done', false);
    }
}
