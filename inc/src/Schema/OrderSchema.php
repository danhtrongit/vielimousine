<?php
declare(strict_types=1);

namespace Vie\Schema;

final class OrderSchema
{
    public const VERSION = '1.0.0';

    public static function install(\wpdb $wpdb): void
    {
        $table   = $wpdb->prefix . 'vie_order';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(20) NOT NULL,
            idempotency_key VARCHAR(64) DEFAULT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            customer_phone VARCHAR(50) NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) DEFAULT NULL,
            customer_vat LONGTEXT DEFAULT NULL,
            sales_user_id BIGINT UNSIGNED DEFAULT NULL,
            source VARCHAR(50) NOT NULL,
            checkin DATE NOT NULL,
            checkout DATE NOT NULL,
            nights TINYINT UNSIGNED NOT NULL,
            adults TINYINT UNSIGNED NOT NULL,
            children TINYINT UNSIGNED NOT NULL DEFAULT 0,
            child_ages LONGTEXT DEFAULT NULL,
            customer_note TEXT DEFAULT NULL,
            internal_note TEXT DEFAULT NULL,
            pickup LONGTEXT DEFAULT NULL,
            dropoff LONGTEXT DEFAULT NULL,
            subtotal DECIMAL(12,0) NOT NULL DEFAULT 0,
            discount DECIMAL(12,0) NOT NULL DEFAULT 0,
            tax DECIMAL(12,0) NOT NULL DEFAULT 0,
            total DECIMAL(12,0) NOT NULL DEFAULT 0,
            cost_total DECIMAL(12,0) NOT NULL DEFAULT 0,
            profit_total DECIMAL(12,0) NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT 'VND',
            coupon_id BIGINT UNSIGNED DEFAULT NULL,
            coupon_code VARCHAR(50) DEFAULT NULL,
            payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            paid_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
            paid_at DATETIME DEFAULT NULL,
            partner_payment_status VARCHAR(30) NOT NULL DEFAULT 'not_created',
            invoice_number VARCHAR(100) DEFAULT NULL,
            voucher_code VARCHAR(100) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            confirmed_at DATETIME DEFAULT NULL,
            cancelled_at DATETIME DEFAULT NULL,
            cancel_reason TEXT DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            created_by BIGINT UNSIGNED DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_code (code),
            UNIQUE KEY uniq_idempotency (idempotency_key),
            KEY idx_customer_id (customer_id),
            KEY idx_customer_phone (customer_phone),
            KEY idx_sales_user_id (sales_user_id),
            KEY idx_source (source),
            KEY idx_checkin (checkin),
            KEY idx_created_at (created_at),
            KEY idx_status (status),
            KEY idx_payment_status (payment_status)
        ) ENGINE=InnoDB {$charset};";

        dbDelta($sql);
    }
}
