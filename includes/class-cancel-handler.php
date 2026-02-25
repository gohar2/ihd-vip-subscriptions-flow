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

        $subscription_id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;
        $reason          = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
        $feedback        = isset( $_POST['feedback'] ) ? sanitize_textarea_field( wp_unslash( $_POST['feedback'] ) ) : '';

        if ( ! $subscription_id ) {
            wp_send_json_error( array( 'message' => 'Invalid subscription ID.' ) );
        }

        $subscription = wcs_get_subscription( $subscription_id );

        if ( ! $subscription ) {
            wp_send_json_error( array( 'message' => 'Subscription not found.' ) );
        }

        // Verify ownership.
        if ( get_current_user_id() !== $subscription->get_user_id() ) {
            wp_send_json_error( array( 'message' => 'You do not own this subscription.' ) );
        }

        // Verify the subscription can actually be cancelled.
        if ( ! $subscription->can_be_updated_to( 'cancelled' ) ) {
            wp_send_json_error( array( 'message' => 'This subscription cannot be cancelled.' ) );
        }

        // Log to audit table (intentional = true, user-initiated).
        IHD_VIP_Audit_Logger::log( $subscription_id, true, $reason, $feedback );

        // Store feedback in subscription meta as well for quick access.
        $subscription->update_meta_data( '_ihd_cancel_reason', $reason );
        $subscription->update_meta_data( '_ihd_cancel_feedback', $feedback );
        $subscription->save_meta_data();

        // Cancel the subscription.
        $subscription->update_status( 'cancelled', 'Cancelled via IHD VIP modal.' );

        wp_send_json_success( array( 'message' => 'Subscription cancelled successfully.' ) );
    }
}
