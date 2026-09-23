<?php
declare(strict_types=1);

namespace Vie\Schema;

/**
 * Payment intents and the immutable receipt audit for booking quotations.
 *
 * The intent table contains the amount offered to SePay. Every authenticated
 * successful notification is also retained in the receipt table, including
 * receipts that require manual reconciliation.
 */
final class BookingQuotePaymentSchema
{
    public const VERSION = '1.1.0';

    public static function install(\wpdb $wpdb): void
    {
        $intentTable  = $wpdb->prefix . 'vie_booking_quote_payment';
        $receiptTable = $wpdb->prefix . 'vie_booking_quote_payment_receipt';
        $charset      = $wpdb->get_charset_collate();

        $intentSql = "CREATE TABLE {$intentTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quote_id BIGINT UNSIGNED NOT NULL,
            invoice CHAR(32) NOT NULL,
            purpose VARCHAR(20) NOT NULL,
            expected_amount DECIMAL(12,0) UNSIGNED NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'VND',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            transaction_id VARCHAR(100) DEFAULT NULL,
            received_amount DECIMAL(12,0) UNSIGNED NOT NULL DEFAULT 0,
            paid_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_invoice (invoice),
            UNIQUE KEY uniq_transaction_id (transaction_id),
            KEY idx_quote_status (quote_id, status),
            KEY idx_quote_purpose_amount (quote_id, purpose, expected_amount),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB {$charset};";

        $receiptSql = "CREATE TABLE {$receiptTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quote_id BIGINT UNSIGNED NOT NULL,
            intent_id BIGINT UNSIGNED NOT NULL,
            invoice CHAR(32) NOT NULL,
            transaction_id VARCHAR(100) NOT NULL,
            received_amount DECIMAL(12,0) UNSIGNED NOT NULL,
            order_amount DECIMAL(12,0) UNSIGNED NOT NULL,
            currency CHAR(3) NOT NULL,
            outcome VARCHAR(20) NOT NULL,
            reason VARCHAR(100) DEFAULT NULL,
            paid_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            webhook_event_id BIGINT UNSIGNED DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_transaction_id (transaction_id),
            KEY idx_quote_created (quote_id, created_at),
            KEY idx_intent_id (intent_id),
            KEY idx_invoice (invoice),
            KEY idx_outcome (outcome),
            KEY idx_webhook_event_id (webhook_event_id)
        ) ENGINE=InnoDB {$charset};";

        dbDelta($intentSql);
        dbDelta($receiptSql);
    }
}
