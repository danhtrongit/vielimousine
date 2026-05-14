<?php
declare(strict_types=1);

namespace Vie\Schema;

final class CouponUsageSchema
{
    public const VERSION = '1.0.0';

    public static function install(\wpdb $wpdb): void
    {
        $table   = $wpdb->prefix . 'vie_coupon_usage';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            coupon_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            user_email VARCHAR(255) DEFAULT NULL,
            discount DECIMAL(12,0) NOT NULL DEFAULT 0,
            used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_coupon_id (coupon_id),
            KEY idx_order_id (order_id),
            KEY idx_user_email (user_email)
        ) ENGINE=InnoDB {$charset};";

        dbDelta($sql);
    }
}
