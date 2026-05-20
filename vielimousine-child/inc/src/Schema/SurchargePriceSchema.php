<?php
declare(strict_types=1);

namespace Vie\Schema;

final class SurchargePriceSchema
{
    public const VERSION = '1.0.0';

    public static function install(\wpdb $wpdb): void
    {
        $table   = $wpdb->prefix . 'vie_surcharge_price';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            surcharge_id BIGINT UNSIGNED NOT NULL,
            date DATE NOT NULL,
            amount DECIMAL(12,0) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_surcharge_date (surcharge_id, date),
            KEY idx_date (date)
        ) ENGINE=InnoDB {$charset};";

        dbDelta($sql);
    }
}
