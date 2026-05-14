<?php
declare(strict_types=1);

namespace Vie\Schema;

final class HotelSchema
{
    public const VERSION = '1.0.0';

    public static function install(\wpdb $wpdb): void
    {
        $table   = $wpdb->prefix . 'vie_hotel';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            address VARCHAR(500) DEFAULT NULL,
            city VARCHAR(100) DEFAULT NULL,
            contact_phone VARCHAR(50) DEFAULT NULL,
            contact_email VARCHAR(255) DEFAULT NULL,
            star_rating TINYINT UNSIGNED DEFAULT NULL,
            default_checkin TIME NOT NULL DEFAULT '14:00:00',
            default_checkout TIME NOT NULL DEFAULT '12:00:00',
            default_ticket_price DECIMAL(12,0) NOT NULL DEFAULT 0,
            ticket_free_children_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
            ticket_free_children_max_age TINYINT UNSIGNED NOT NULL DEFAULT 5,
            pricing_policy LONGTEXT DEFAULT NULL,
            cancellation_policy LONGTEXT DEFAULT NULL,
            thumbnail_id BIGINT UNSIGNED DEFAULT NULL,
            gallery LONGTEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order SMALLINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_post_id (post_id),
            KEY idx_slug (slug),
            KEY idx_city (city),
            KEY idx_is_active (is_active),
            KEY idx_sort_order (sort_order)
        ) ENGINE=InnoDB {$charset};";

        dbDelta($sql);
    }
}
