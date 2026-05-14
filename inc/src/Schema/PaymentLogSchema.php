<?php
declare(strict_types=1);

namespace Vie\Schema;

final class PaymentLogSchema
{
    public const VERSION = '1.0.0';

    public static function install(\wpdb $wpdb): void
    {
        $table   = $wpdb->prefix . 'vie_payment_log';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(20) NOT NULL,
            amount DECIMAL(12,0) NOT NULL,
            method VARCHAR(30) NOT NULL,
            gateway VARCHAR(30) DEFAULT NULL,
            transaction_id VARCHAR(100) DEFAULT NULL,
            note TEXT DEFAULT NULL,
            paid_at DATETIME DEFAULT NULL,
            created_by BIGINT UNSIGNED DEFAULT NULL,
            raw_payload LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_gateway_txn (gateway, transaction_id),
            KEY idx_order_id (order_id),
            KEY idx_created_at (created_at),
            KEY idx_type (type)
        ) ENGINE=InnoDB {$charset};";

        dbDelta($sql);
    }
}
