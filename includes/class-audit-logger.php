<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IHD_VIP_Audit_Logger {

    private static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'ihd_subscription_audit';
    }

    /**
     * Log a subscription event to the audit table.
     *
     * @param int    $subscription_id The subscription ID.
     * @param bool   $intentional     Whether the action was intentional by the user.
     * @param string $reason          The selected reason or detected cause.
     * @param string $text            Additional feedback or error detail.
     * @return int|false
     */
    public static function log( $subscription_id, $intentional, $reason, $text ) {
        global $wpdb;

        return $wpdb->insert(
            self::table_name(),
            array(
                'subscription_id' => absint( $subscription_id ),
                'intentional'     => $intentional ? 1 : 0,
                'reason'          => sanitize_text_field( $reason ),
                'text'            => sanitize_textarea_field( $text ),
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );
    }

    /**
     * Get audit logs for a specific subscription.
     *
     * @param int $subscription_id The subscription ID.
     * @return array
     */
    public static function get_logs( $subscription_id ) {
        global $wpdb;

        $table = self::table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE subscription_id = %d ORDER BY created_at DESC",
                absint( $subscription_id )
            ),
            ARRAY_A
        );
    }
}
