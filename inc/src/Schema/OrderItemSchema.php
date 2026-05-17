<?php
declare(strict_types=1);

namespace Vie\Schema;

final class OrderItemSchema
{
    public const VERSION = '1.1.0';

    public static function install(\wpdb $wpdb): void
    {
        $table   = $wpdb->prefix . 'vie_order_item';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            hotel_id BIGINT UNSIGNED NOT NULL,
            room_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            booking_type VARCHAR(10) NOT NULL,
            unit_label VARCHAR(50) NOT NULL,
            quantity SMALLINT UNSIGNED NOT NULL,
            checkin DATE NOT NULL,
            checkout DATE NOT NULL,
            nights TINYINT UNSIGNED NOT NULL,
            adults TINYINT UNSIGNED NOT NULL,
            children TINYINT UNSIGNED NOT NULL DEFAULT 0,
            child_ages LONGTEXT DEFAULT NULL,
            room_subtotal DECIMAL(12,0) NOT NULL DEFAULT 0,
            extra_adult_total DECIMAL(12,0) NOT NULL DEFAULT 0,
            child_surcharge_total DECIMAL(12,0) NOT NULL DEFAULT 0,
            ticket_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            ticket_subtotal DECIMAL(12,0) NOT NULL DEFAULT 0,
            line_discount DECIMAL(12,0) NOT NULL DEFAULT 0,
            line_total DECIMAL(12,0) NOT NULL DEFAULT 0,
            cost_total DECIMAL(12,0) NOT NULL DEFAULT 0,
            profit_total DECIMAL(12,0) NOT NULL DEFAULT 0,
            partner_name VARCHAR(255) DEFAULT NULL,
            hotel_area VARCHAR(50) DEFAULT NULL,
            supplier_booking_code VARCHAR(100) DEFAULT NULL,
            pricing_snapshot LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            cancelled_at DATETIME DEFAULT NULL,
            cancel_reason TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_order_id (order_id),
            KEY idx_hotel_id (hotel_id),
            KEY idx_room_id (room_id),
            KEY idx_checkin (checkin)
        ) ENGINE=InnoDB {$charset};";

        dbDelta($sql);
    }
}
