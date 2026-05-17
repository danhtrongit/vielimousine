<?php
declare(strict_types=1);

namespace Vie\Schema;

final class SurchargeSchema
{
    public const VERSION = '1.1.0';

    public static function install(\wpdb $wpdb): void
    {
        $table   = $wpdb->prefix . 'vie_surcharge';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            room_id BIGINT UNSIGNED NOT NULL,
            guest_type VARCHAR(10) NOT NULL,
            label VARCHAR(100) NOT NULL,
            age_from TINYINT UNSIGNED NOT NULL,
            age_to TINYINT UNSIGNED NOT NULL,
            child_index_min TINYINT UNSIGNED NOT NULL DEFAULT 1,
            child_index_max TINYINT UNSIGNED DEFAULT NULL,
            amount DECIMAL(12,0) NOT NULL,
            is_free TINYINT(1) NOT NULL DEFAULT 0,
            sort_order SMALLINT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_room_id (room_id),
            KEY idx_room_guest_active_sort (room_id, guest_type, is_active, sort_order)
        ) ENGINE=InnoDB {$charset};";

        dbDelta($sql);
    }
}
