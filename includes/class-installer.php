<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IHD_VIP_Installer {

    /**
     * Runs on plugin activation.
     */
    public static function activate() {
        self::create_audit_table();
    }

    /**
     * Create the subscription audit log table.
     *
     * Column design is optimised for future campaign-analysis queries:
     *
     *  - event_type          Filterable category (cancellation, payment_failure, expiration, on_hold, pending_cancel).
     *  - old_status /        Exact WCS status pair so transitions can be analysed without parsing the reason text.
     *    new_status
     *  - intentional         1 = user chose to leave, 0 = system/automated — drives which campaign tone to use.
     *  - reason              Short, human-readable label (user-selected or auto-generated).
     *  - detail              Full diagnostic context (status change detail, gateway response, error classification).
     *  - customer_id         Direct FK to the subscriber — avoids a JOIN through postmeta for campaign targeting.
     *  - payment_method      Gateway that was active at the time (Authorize.Net, PayPal, etc.).
     *  - payment_error_type  Classified error bucket (integration_error, payment_declined, insufficient_funds,
     *                        expired_card, unknown) — for aggregating "fixable" vs "lost" subscriptions.
     *  - subscription_amount + billing_period + currency  Revenue-impact context for prioritising campaigns.
     */
    private static function create_audit_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'ihd_vip_subscription_audit';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subscription_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            by_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            event_type VARCHAR(50) NOT NULL DEFAULT '',
            old_status VARCHAR(30) NOT NULL DEFAULT '',
            new_status VARCHAR(30) NOT NULL DEFAULT '',
            intentional TINYINT(1) NOT NULL DEFAULT 0,
            reason VARCHAR(255) NOT NULL DEFAULT '',
            detail TEXT,
            payment_method VARCHAR(100) NOT NULL DEFAULT '',
            payment_error_type VARCHAR(50) NOT NULL DEFAULT '',
            subscription_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            billing_period VARCHAR(20) NOT NULL DEFAULT '',
            currency VARCHAR(10) NOT NULL DEFAULT 'USD',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY subscription_id (subscription_id),
            KEY customer_id (customer_id),
            KEY by_user_id (by_user_id),
            KEY event_type (event_type),
            KEY intentional (intentional),
            KEY payment_error_type (payment_error_type),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
