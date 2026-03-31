<?php
/**
 * IHD VIP Pause Handler
 *
 * Handles subscription pause (on-hold) and resume via AJAX.
 * Stores pause metadata on the subscription so the shortcode can display
 * the correct UI and auto-resume is scheduled via WP Cron.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class IHD_VIP_Pause_Handler {

    const META_PAUSED_BY_VIP   = '_ihd_vip_paused';        // '1' when paused via VIP portal
    const META_PAUSE_DAYS      = '_ihd_vip_pause_days';     // e.g. '30'
    const META_PAUSE_DATE      = '_ihd_vip_pause_date';     // ISO date when paused
    const META_RESUME_DATE     = '_ihd_vip_resume_date';    // ISO date when it should resume
    const CRON_HOOK            = 'ihd_vip_auto_resume_subscription';

    public function __construct() {
        add_action( 'wp_ajax_ihd_vip_pause_subscription',  array( $this, 'handle_pause' ) );
        add_action( 'wp_ajax_ihd_vip_resume_subscription', array( $this, 'handle_resume' ) );
        add_action( self::CRON_HOOK, array( $this, 'auto_resume' ), 10, 1 );
    }

    /* ──────────────────────────────────────────────────────────────────────────
     * AJAX: Pause subscription (put on-hold for N days).
     * ────────────────────────────────────────────────────────────────────────── */
    public function handle_pause() {
        check_ajax_referer( 'ihd_vip_nonce', 'nonce' );

        $user_id         = get_current_user_id();
        $subscription_id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;
        $days            = isset( $_POST['days'] ) ? absint( $_POST['days'] ) : 0;

        if ( ! $subscription_id || ! $days ) {
            wp_send_json_error( array( 'message' => 'Missing required parameters.' ) );
        }

        // Validate days range.
        $allowed_days = array( 30, 60, 90, 180 );
        if ( ! in_array( $days, $allowed_days, true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid pause duration.' ) );
        }

        $subscription = wcs_get_subscription( $subscription_id );
        if ( ! $subscription || (int) $subscription->get_customer_id() !== $user_id ) {
            wp_send_json_error( array( 'message' => 'Invalid subscription.' ) );
        }

        if ( 'active' !== $subscription->get_status() ) {
            wp_send_json_error( array( 'message' => 'Only active subscriptions can be paused.' ) );
        }

        // Put subscription on hold.
        $subscription->update_status( 'on-hold', sprintf(
            'Paused by customer via VIP portal for %d days.',
            $days
        ) );

        // Store pause metadata.
        $now         = current_time( 'mysql', true );
        $resume_time = strtotime( "+{$days} days", strtotime( $now ) );
        $resume_date = gmdate( 'Y-m-d H:i:s', $resume_time );

        $subscription->update_meta_data( self::META_PAUSED_BY_VIP, '1' );
        $subscription->update_meta_data( self::META_PAUSE_DAYS, (string) $days );
        $subscription->update_meta_data( self::META_PAUSE_DATE, $now );
        $subscription->update_meta_data( self::META_RESUME_DATE, $resume_date );
        $subscription->save();

        // Schedule auto-resume via WP Cron.
        wp_schedule_single_event( $resume_time, self::CRON_HOOK, array( $subscription_id ) );

        // Log to audit table if available.
        if ( class_exists( 'IHD_VIP_Audit_Logger' ) ) {
            IHD_VIP_Audit_Logger::log( array(
                'subscription_id' => $subscription_id,
                'customer_id'     => $user_id,
                'event_type'      => 'on_hold',
                'old_status'      => 'active',
                'new_status'      => 'on-hold',
                'intentional'     => 1,
                'reason'          => sprintf( 'Paused for %d days', $days ),
            ) );
        }

        wp_send_json_success( array(
            'message'     => 'Subscription paused.',
            'days'        => $days,
            'resume_date' => date_i18n( 'F j, Y', $resume_time ),
        ) );
    }

    /* ──────────────────────────────────────────────────────────────────────────
     * AJAX: Resume subscription (reactivate from on-hold).
     * ────────────────────────────────────────────────────────────────────────── */
    public function handle_resume() {
        check_ajax_referer( 'ihd_vip_nonce', 'nonce' );

        $user_id         = get_current_user_id();
        $subscription_id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;

        if ( ! $subscription_id ) {
            wp_send_json_error( array( 'message' => 'Missing subscription ID.' ) );
        }

        $subscription = wcs_get_subscription( $subscription_id );
        if ( ! $subscription || (int) $subscription->get_customer_id() !== $user_id ) {
            wp_send_json_error( array( 'message' => 'Invalid subscription.' ) );
        }

        if ( 'on-hold' !== $subscription->get_status() ) {
            wp_send_json_error( array( 'message' => 'Subscription is not paused.' ) );
        }

        // Reactivate.
        $subscription->update_status( 'active', 'Resumed by customer via VIP portal.' );

        // Clean up pause metadata.
        $subscription->delete_meta_data( self::META_PAUSED_BY_VIP );
        $subscription->delete_meta_data( self::META_PAUSE_DAYS );
        $subscription->delete_meta_data( self::META_PAUSE_DATE );
        $subscription->delete_meta_data( self::META_RESUME_DATE );
        $subscription->save();

        // Unschedule the auto-resume cron if it's still pending.
        wp_unschedule_event(
            wp_next_scheduled( self::CRON_HOOK, array( $subscription_id ) ) ?: 0,
            self::CRON_HOOK,
            array( $subscription_id )
        );

        // Log to audit table if available.
        if ( class_exists( 'IHD_VIP_Audit_Logger' ) ) {
            IHD_VIP_Audit_Logger::log( array(
                'subscription_id' => $subscription_id,
                'customer_id'     => $user_id,
                'event_type'      => 'on_hold',
                'old_status'      => 'on-hold',
                'new_status'      => 'active',
                'intentional'     => 1,
                'reason'          => 'Resumed by customer',
            ) );
        }

        wp_send_json_success( array( 'message' => 'Subscription resumed.' ) );
    }

    /* ──────────────────────────────────────────────────────────────────────────
     * WP Cron: Auto-resume a paused subscription after the pause period ends.
     * ────────────────────────────────────────────────────────────────────────── */
    public function auto_resume( $subscription_id ) {
        $subscription = wcs_get_subscription( $subscription_id );
        if ( ! $subscription ) {
            return;
        }

        // Only auto-resume if still on-hold AND was paused via VIP portal.
        if ( 'on-hold' !== $subscription->get_status() ) {
            return;
        }

        if ( '1' !== $subscription->get_meta( self::META_PAUSED_BY_VIP ) ) {
            return;
        }

        $subscription->update_status( 'active', 'Auto-resumed after VIP pause period ended.' );

        // Clean up metadata.
        $subscription->delete_meta_data( self::META_PAUSED_BY_VIP );
        $subscription->delete_meta_data( self::META_PAUSE_DAYS );
        $subscription->delete_meta_data( self::META_PAUSE_DATE );
        $subscription->delete_meta_data( self::META_RESUME_DATE );
        $subscription->save();
    }

    /* ──────────────────────────────────────────────────────────────────────────
     * Helper: Check if a subscription is currently paused via VIP portal.
     * Used by the shortcode to decide which UI to show.
     * ────────────────────────────────────────────────────────────────────────── */
    public static function is_vip_paused( $subscription ) {
        return 'on-hold' === $subscription->get_status()
            && '1' === $subscription->get_meta( self::META_PAUSED_BY_VIP );
    }

    /**
     * Get pause details for a VIP-paused subscription.
     */
    public static function get_pause_details( $subscription ) {
        if ( ! self::is_vip_paused( $subscription ) ) {
            return false;
        }

        $pause_date  = $subscription->get_meta( self::META_PAUSE_DATE );
        $resume_date = $subscription->get_meta( self::META_RESUME_DATE );
        $days        = (int) $subscription->get_meta( self::META_PAUSE_DAYS );

        return array(
            'days'             => $days,
            'pause_date'       => $pause_date,
            'resume_date'      => $resume_date,
            'resume_date_nice' => $resume_date ? date_i18n( 'F j, Y', strtotime( $resume_date ) ) : '',
            'days_remaining'   => $resume_date ? max( 0, (int) ceil( ( strtotime( $resume_date ) - time() ) / DAY_IN_SECONDS ) ) : 0,
        );
    }
}
