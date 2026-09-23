<?php
declare(strict_types=1);

namespace Vie\Schema;

final class BookingQuoteSchema
{
    public const VERSION = '1.0.1';

    public static function install(\wpdb $wpdb): void
    {
        $table   = $wpdb->prefix . 'vie_booking_quote';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id CHAR(32) NOT NULL,
            code VARCHAR(32) NOT NULL,
            sales_user_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            customer_name VARCHAR(255) NOT NULL DEFAULT '',
            customer_phone VARCHAR(50) NOT NULL DEFAULT '',
            customer_email VARCHAR(255) DEFAULT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            image_url TEXT DEFAULT NULL,
            greeting TEXT DEFAULT NULL,
            trip_start DATE DEFAULT NULL,
            trip_end DATE DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            inclusions LONGTEXT DEFAULT NULL,
            exclusions LONGTEXT DEFAULT NULL,
            terms LONGTEXT DEFAULT NULL,
            contact_name VARCHAR(255) DEFAULT NULL,
            contact_phone VARCHAR(50) DEFAULT NULL,
            contact_zalo VARCHAR(50) DEFAULT NULL,
            `lines` LONGTEXT NOT NULL,
            discount DECIMAL(12,0) NOT NULL DEFAULT 0,
            deposit_type VARCHAR(20) NOT NULL DEFAULT 'percent',
            deposit_value DECIMAL(12,0) NOT NULL DEFAULT 0,
            subtotal DECIMAL(12,0) NOT NULL DEFAULT 0,
            total DECIMAL(12,0) NOT NULL DEFAULT 0,
            deposit_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
            paid_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
            payment_review TINYINT(1) NOT NULL DEFAULT 0,
            valid_until DATETIME DEFAULT NULL,
            brand LONGTEXT DEFAULT NULL,
            published_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_public_id (public_id),
            UNIQUE KEY uniq_code (code),
            KEY idx_sales_user_id (sales_user_id),
            KEY idx_status (status),
            KEY idx_valid_until (valid_until),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB {$charset};";

        dbDelta($sql);
    }
}
