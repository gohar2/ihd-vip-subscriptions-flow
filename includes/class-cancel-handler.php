<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IHD_VIP_Cancel_Handler {

    public function __construct() {
        add_action( 'wp_ajax_ihd_vip_cancel_subscription', array( $this, 'handle_cancel' ) );
    }

    /**
     * AJAX handler: Cancel subscription with feedback + audit log.
     */
    public function handle_cancel() {
        check_ajax_referer( 'ihd_vip_nonce', 'nonce' );

        $user_id         = get_current_user_id();
        $subscription_id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;
        $reason          = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';

        if ( ! $subscription_id ) {
            wp_send_json_error( array( 'message' => 'Invalid subscription ID.' ) );
        }

        $subscription = wcs_get_subscription( $subscription_id );

        if ( ! $subscription ) {
            wp_send_json_error( array( 'message' => 'Subscription not found.' ) );
        }

        // Allow subscription owner OR admin with manage_woocommerce cap.
        $is_owner = ( $user_id === (int) $subscription->get_customer_id() );
        $is_admin = current_user_can( 'manage_woocommerce' );

        if ( ! $is_owner && ! $is_admin ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to cancel this subscription.' ) );
        }

        if ( ! $subscription->can_be_updated_to( 'cancelled' ) ) {
            wp_send_json_error( array( 'message' => 'This subscription cannot be cancelled.' ) );
        }

        // Log to audit table (by_user_id = who triggered, intentional = true).
        IHD_VIP_Audit_Logger::log( $subscription_id, $user_id, true, $reason );

        // Mark this cancellation as handled by our modal so the event tracker skips it.
        $subscription->update_meta_data( '_ihd_cancel_reason', $reason );
        $subscription->update_meta_data( '_ihd_cancel_logged', 'yes' );
        $subscription->save_meta_data();

        $actor_label = $is_owner ? 'customer' : 'admin (user #' . $user_id . ')';
        $status_note = sprintf( 'Cancelled by %s via VIP portal. Reason: %s', $actor_label, $reason ?: '(none)' );
        $subscription->update_status( 'cancelled', $status_note );

        wp_send_json_success( array( 'message' => 'Subscription cancelled successfully.' ) );
    }
}
