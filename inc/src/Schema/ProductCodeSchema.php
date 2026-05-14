<?php
declare(strict_types=1);

namespace Vie\Schema;

final class ProductCodeSchema
{
    public const VERSION = '1.0.0';

    public static function install(\wpdb $wpdb): void
    {
        $table   = $wpdb->prefix . 'vie_product_code';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(50) NOT NULL,
            hotel_id BIGINT UNSIGNED NOT NULL,
            room_id BIGINT UNSIGNED NOT NULL,
            booking_type VARCHAR(10) NOT NULL,
            weekday_pattern VARCHAR(30) DEFAULT NULL,
            display_name VARCHAR(255) NOT NULL,
            unit_label VARCHAR(50) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_code (code),
            KEY idx_hotel_id (hotel_id),
            KEY idx_room_id (room_id),
            KEY idx_booking_type (booking_type)
        ) ENGINE=InnoDB {$charset};";

        dbDelta($sql);
    }
}
