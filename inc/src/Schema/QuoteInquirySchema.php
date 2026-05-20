<?php
declare(strict_types=1);

namespace Vie\Schema;

final class QuoteInquirySchema
{
    public const VERSION = '1.0.0';

    public static function install(\wpdb $wpdb): void
    {
        $table   = $wpdb->prefix . 'vie_quote_inquiry';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            room_id BIGINT UNSIGNED NOT NULL,
            hotel_id BIGINT UNSIGNED NOT NULL,
            booking_type VARCHAR(20) NOT NULL DEFAULT 'room',
            checkin DATE NOT NULL,
            checkout DATE NOT NULL,
            adults TINYINT UNSIGNED NOT NULL DEFAULT 1,
            children TINYINT UNSIGNED NOT NULL DEFAULT 0,
            child_ages LONGTEXT DEFAULT NULL,
            user_rooms TINYINT UNSIGNED NOT NULL DEFAULT 1,
            customer_name VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50) NOT NULL,
            customer_email VARCHAR(255) DEFAULT NULL,
            note TEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            assigned_user_id BIGINT UNSIGNED DEFAULT NULL,
            admin_note TEXT DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_status (status),
            KEY idx_hotel_id (hotel_id),
            KEY idx_room_id (room_id),
            KEY idx_customer_phone (customer_phone),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB {$charset};";

        dbDelta($sql);
    }
}
