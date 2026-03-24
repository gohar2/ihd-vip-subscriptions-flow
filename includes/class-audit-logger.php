<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IHD_VIP_Audit_Logger {

    private static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'ihd_vip_subscription_audit';
    }

    /**
     * Log a subscription event to the audit table.
     *
     * @param array $data {
     *     @type int    $subscription_id    Required. The WC subscription ID.
     *     @type int    $customer_id        The subscription owner's user ID.
     *     @type int    $by_user_id         Who triggered the action (0 = system/cron).
     *     @type string $event_type         Category: cancellation, payment_failure, expiration, on_hold, pending_cancel.
     *     @type string $old_status         Previous WCS status (without wc- prefix).
     *     @type string $new_status         New WCS status (without wc- prefix).
     *     @type bool   $intentional        Whether the user deliberately triggered this.
     *     @type string $reason             Short human-readable label.
     *     @type string $detail             Full diagnostic context (TEXT).
     *     @type string $payment_method     Gateway title active on the subscription.
     *     @type string $payment_error_type Classified error bucket.
     *     @type float  $subscription_amount Subscription recurring total.
     *     @type string $billing_period     e.g. month, year.
     *     @type string $currency           e.g. USD.
     * }
     * @return int|false Insert ID on success, false on failure.
     */
    public static function log( array $data ) {
        global $wpdb;

        $row = array(
            'subscription_id'     => absint( $data['subscription_id'] ?? 0 ),
            'customer_id'         => absint( $data['customer_id'] ?? 0 ),
            'by_user_id'          => absint( $data['by_user_id'] ?? 0 ),
            'event_type'          => sanitize_text_field( $data['event_type'] ?? '' ),
            'old_status'          => sanitize_text_field( $data['old_status'] ?? '' ),
            'new_status'          => sanitize_text_field( $data['new_status'] ?? '' ),
            'intentional'         => ! empty( $data['intentional'] ) ? 1 : 0,
            'reason'              => sanitize_text_field( $data['reason'] ?? '' ),
            'detail'              => wp_kses_post( $data['detail'] ?? '' ),
            'payment_method'      => sanitize_text_field( $data['payment_method'] ?? '' ),
            'payment_error_type'  => sanitize_text_field( $data['payment_error_type'] ?? '' ),
            'subscription_amount' => floatval( $data['subscription_amount'] ?? 0 ),
            'billing_period'      => sanitize_text_field( $data['billing_period'] ?? '' ),
            'currency'            => sanitize_text_field( $data['currency'] ?? 'USD' ),
            'created_at'          => current_time( 'mysql' ),
        );

        $formats = array( '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s' );

        $inserted = $wpdb->insert( self::table_name(), $row, $formats );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Get audit logs for a specific subscription, ordered by newest first.
     *
     * @param int $subscription_id The subscription ID.
     * @return array
     */
    public static function get_logs( $subscription_id ) {
        global $wpdb;

        $table = self::table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE subscription_id = %d ORDER BY id DESC",
                absint( $subscription_id )
            ),
            ARRAY_A
        );
    }
}
