<?php
declare(strict_types=1);

namespace Vie\Schema;

final class RoomPriceSchema
{
    public const VERSION = '1.0.0';

    public static function install(\wpdb $wpdb): void
    {
        $table   = $wpdb->prefix . 'vie_room_price';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            room_id BIGINT UNSIGNED NOT NULL,
            date DATE NOT NULL,
            price DECIMAL(12,0) NOT NULL,
            extra_adult_price DECIMAL(12,0) NOT NULL,
            stock SMALLINT UNSIGNED NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            source VARCHAR(30) NOT NULL DEFAULT 'manual',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_room_date (room_id, date),
            KEY idx_date (date),
            KEY idx_room_date_active (room_id, date, is_active)
        ) ENGINE=InnoDB {$charset};";

        dbDelta($sql);
    }
}
