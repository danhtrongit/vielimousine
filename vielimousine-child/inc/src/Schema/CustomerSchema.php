<?php
declare(strict_types=1);

namespace Vie\Schema;

final class CustomerSchema
{
    public const VERSION = '1.0.0';

    public static function install(\wpdb $wpdb): void
    {
        $table   = $wpdb->prefix . 'vie_customer';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            phone VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) DEFAULT NULL,
            note TEXT DEFAULT NULL,
            vat_company_name VARCHAR(255) DEFAULT NULL,
            vat_tax_code VARCHAR(50) DEFAULT NULL,
            vat_address VARCHAR(500) DEFAULT NULL,
            vat_email VARCHAR(255) DEFAULT NULL,
            booking_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_phone (phone),
            KEY idx_email (email)
        ) ENGINE=InnoDB {$charset};";

        dbDelta($sql);
    }
}
