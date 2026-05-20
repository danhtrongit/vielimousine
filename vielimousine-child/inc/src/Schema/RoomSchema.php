<?php
declare(strict_types=1);

namespace Vie\Schema;

final class RoomSchema
{
    public const VERSION = '1.0.0';

    public static function install(\wpdb $wpdb): void
    {
        $table   = $wpdb->prefix . 'vie_room';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            hotel_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            included_adults TINYINT UNSIGNED NOT NULL,
            max_adults TINYINT UNSIGNED NOT NULL,
            max_children TINYINT UNSIGNED NOT NULL,
            base_price DECIMAL(12,0) NOT NULL,
            extra_adult_price DECIMAL(12,0) NOT NULL,
            free_children_count TINYINT UNSIGNED NOT NULL,
            free_children_max_age TINYINT UNSIGNED NOT NULL DEFAULT 5,
            area SMALLINT UNSIGNED DEFAULT NULL,
            bed_type VARCHAR(50) DEFAULT NULL,
            bed_count TINYINT UNSIGNED DEFAULT NULL,
            view VARCHAR(100) DEFAULT NULL,
            floor VARCHAR(50) DEFAULT NULL,
            amenities LONGTEXT DEFAULT NULL,
            thumbnail_id BIGINT UNSIGNED DEFAULT NULL,
            gallery LONGTEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order SMALLINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_hotel_id (hotel_id),
            KEY idx_is_active (is_active),
            KEY idx_sort_order (sort_order)
        ) ENGINE=InnoDB {$charset};";

        dbDelta($sql);
    }
}
