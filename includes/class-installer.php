<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IHD_VIP_Installer {

    /**
     * Runs on plugin activation.
     * Creates the subscription audit log table.
     */
    public static function activate() {
        self::create_audit_table();
    }

    /**
     * Create the audit log table using dbDelta.
     */
    private static function create_audit_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'ihd_subscription_audit';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subscription_id BIGINT UNSIGNED NOT NULL,
            intentional TINYINT(1) NOT NULL DEFAULT 1,
            reason VARCHAR(255) NOT NULL DEFAULT '',
            text TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY subscription_id (subscription_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
