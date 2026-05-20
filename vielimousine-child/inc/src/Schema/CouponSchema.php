<?php
declare(strict_types=1);

namespace Vie\Schema;

final class CouponSchema
{
    public const VERSION = '1.0.0';

    public static function install(\wpdb $wpdb): void
    {
        $table   = $wpdb->prefix . 'vie_coupon';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(50) NOT NULL,
            description TEXT DEFAULT NULL,
            type VARCHAR(20) NOT NULL,
            value DECIMAL(12,2) NOT NULL,
            min_order DECIMAL(12,0) NOT NULL DEFAULT 0,
            max_discount DECIMAL(12,0) DEFAULT NULL,
            usage_limit INT UNSIGNED DEFAULT NULL,
            usage_limit_per_user INT UNSIGNED DEFAULT NULL,
            used_count INT UNSIGNED NOT NULL DEFAULT 0,
            valid_from DATETIME DEFAULT NULL,
            valid_to DATETIME DEFAULT NULL,
            hotel_ids LONGTEXT DEFAULT NULL,
            room_ids LONGTEXT DEFAULT NULL,
            booking_types LONGTEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sales_only TINYINT(1) NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_code (code),
            KEY idx_is_active (is_active),
            KEY idx_valid_from (valid_from),
            KEY idx_valid_to (valid_to),
            KEY idx_sales_only (sales_only)
        ) ENGINE=InnoDB {$charset};";

        dbDelta($sql);
    }
}
