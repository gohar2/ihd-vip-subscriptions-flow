<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tracks all subscription cancellations and payment failures automatically.
 *
 * Hooks into WooCommerce Subscriptions to detect:
 * - Status changes to cancelled, on-hold, expired, pending-cancel
 * - Renewal payment failures (card declined, insufficient funds, gateway errors)
 * - Integration errors (INVALID_CARD_DATA, gateway misconfigurations, etc.)
 *
 * Skips logging when the cancellation was already handled by our VIP cancel modal
 * (detected via the _ihd_cancel_logged meta flag).
 */
class IHD_VIP_Subscription_Event_Tracker {

    /**
     * Error patterns that indicate integration/configuration issues
     * rather than genuine customer payment problems.
     */
    private static $integration_error_patterns = array(
        'INVALID_CARD_DATA',
        'INVALID_EXPIRATION',
        'INVALID_ACCOUNT',
        'INVALID_AMOUNT',
        'CONFIGURATION_ERROR',
        'GATEWAY_ERROR',
        'GATEWAY_UNAVAILABLE',
        'INTERNAL_ERROR',
        'SERVICE_UNAVAILABLE',
        'API_ERROR',
        'MERCHANT_ACCOUNT',
        'AUTHENTICATION_ERROR',
        'INVALID_CREDENTIALS',
        'SETUP_ERROR',
        'INTEGRATION_ERROR',
        'UNSUPPORTED_CARD',
        'PROCESSOR_UNAVAILABLE',
        'E00003',   // Authorize.Net - An error occurred while parsing the XML request
        'E00007',   // Authorize.Net - User authentication failed
        'E00040',   // Authorize.Net - The record cannot be found
        'E00044',   // Authorize.Net - Invalid credentials
        'INSTRUMENT_DECLINED',  // PayPal - generic but can be config related
        'PAYER_ACTION_REQUIRED',
        'TRANSACTION_REFUSED',
        'NO_VALID_PAYMENT_SOURCE',
        'INVALID_RESOURCE_ID',
    );

    public function __construct() {
        // Track subscription status changes (cancellation, on-hold, expired).
        add_action( 'woocommerce_subscription_status_updated', array( $this, 'on_status_change' ), 20, 3 );

        // Track renewal payment failures before they potentially trigger status changes.
        add_action( 'woocommerce_subscription_renewal_payment_failed', array( $this, 'on_renewal_payment_failed' ), 10, 2 );

        // Track payment failures logged through WooCommerce payment complete failure.
        add_action( 'woocommerce_subscription_payment_failed', array( $this, 'on_payment_failed' ), 10, 2 );
    }

    /**
     * Fires when a subscription status changes.
     *
     * @param WC_Subscription $subscription The subscription object.
     * @param string          $new_status   New status (without 'wc-' prefix).
     * @param string          $old_status   Old status (without 'wc-' prefix).
     */
    public function on_status_change( $subscription, $new_status, $old_status ) {
        // Only track meaningful negative status transitions.
        $tracked_statuses = array( 'cancelled', 'on-hold', 'expired', 'pending-cancel' );

        if ( ! in_array( $new_status, $tracked_statuses, true ) ) {
            return;
        }

        $subscription_id = $subscription->get_id();

        // Skip if already logged by our cancel modal.
        if ( 'cancelled' === $new_status && 'yes' === $subscription->get_meta( '_ihd_cancel_logged' ) ) {
            // Clear the flag so future status changes are tracked.
            $subscription->delete_meta_data( '_ihd_cancel_logged' );
            $subscription->save_meta_data();
            return;
        }

        // Determine the reason and gather context.
        $reason     = $this->determine_status_change_reason( $subscription, $new_status, $old_status );
        $by_user_id = get_current_user_id(); // 0 for system/cron, admin ID for admin actions.

        IHD_VIP_Audit_Logger::log( $subscription_id, $by_user_id, false, $reason );
    }

    /**
     * Fires when a renewal payment fails.
     *
     * @param WC_Subscription $subscription  The subscription.
     * @param WC_Order        $renewal_order The failed renewal order.
     */
    public function on_renewal_payment_failed( $subscription, $renewal_order ) {
        $subscription_id = $subscription->get_id();
        $order_id        = $renewal_order ? $renewal_order->get_id() : 0;

        // Extract the payment error from the order notes.
        $error_message = $this->get_payment_error_from_order( $renewal_order );
        $gateway       = $renewal_order ? $renewal_order->get_payment_method_title() : 'Unknown';
        $amount        = $renewal_order ? $renewal_order->get_total() : '0';
        $currency      = $renewal_order ? $renewal_order->get_currency() : 'USD';

        // Classify the error.
        $error_type = $this->classify_error( $error_message );

        $reason = 'Renewal Payment Failed';
        if ( 'integration_error' === $error_type ) {
            $reason = 'Integration/Gateway Error';
        }

        IHD_VIP_Audit_Logger::log( $subscription_id, 0, false, $reason );
    }

    /**
     * Alternative hook for payment failures (some gateways use this).
     *
     * @param WC_Subscription $subscription The subscription.
     * @param WC_Order        $order        The order that failed.
     */
    public function on_payment_failed( $subscription, $order ) {
        // Avoid double-logging if renewal_payment_failed already fired.
        // We use a transient as a simple dedup mechanism.
        $sub_id = $subscription->get_id();
        $dedup_key = 'ihd_pf_logged_' . $sub_id . '_' . ( $order ? $order->get_id() : 0 );

        if ( get_transient( $dedup_key ) ) {
            return;
        }
        set_transient( $dedup_key, 1, 300 ); // 5 min dedup window.

        $this->on_renewal_payment_failed( $subscription, $order );
    }

    /**
     * Determine a human-readable reason for the status change.
     */
    private function determine_status_change_reason( $subscription, $new_status, $old_status ) {
        // Check if an admin triggered the change.
        if ( is_admin() && current_user_can( 'manage_woocommerce' ) ) {
            return 'Admin ' . ucfirst( $new_status );
        }

        // Check if coming from a payment-failed state.
        if ( 'on-hold' === $old_status && 'cancelled' === $new_status ) {
            return 'Auto-cancelled (Payment Failed)';
        }

        if ( 'on-hold' === $new_status ) {
            // Check the last renewal order for payment failure clues.
            $last_order = $this->get_last_renewal_order( $subscription );
            if ( $last_order && $last_order->get_status() === 'failed' ) {
                $error = $this->get_payment_error_from_order( $last_order );
                $error_type = $this->classify_error( $error );
                if ( 'integration_error' === $error_type ) {
                    return 'On-Hold (Integration/Gateway Error)';
                }
                return 'On-Hold (Payment Failed)';
            }
            return 'Subscription On-Hold';
        }

        if ( 'expired' === $new_status ) {
            return 'Subscription Expired';
        }

        if ( 'pending-cancel' === $new_status ) {
            return 'Pending Cancellation';
        }

        if ( 'cancelled' === $new_status ) {
            // Check if it was a system/cron cancellation after max failed payments.
            $retry_count = $subscription->get_meta( '_wcs_retry_count' );
            if ( $retry_count ) {
                return 'Auto-cancelled (Max Retries Exceeded)';
            }
            return 'System Cancelled';
        }

        return ucfirst( $new_status );
    }

    /**
     * Build detailed text about the status change.
     */
    private function build_status_change_detail( $subscription, $new_status, $old_status ) {
        $lines = array();
        $lines[] = sprintf( 'Status changed: %s → %s', $old_status, $new_status );

        // Who triggered it.
        $current_user = wp_get_current_user();
        if ( $current_user->ID ) {
            $lines[] = sprintf( 'Changed by: %s (%s) #%d', $current_user->display_name, $current_user->user_email, $current_user->ID );
        } else {
            $lines[] = 'Changed by: System/Cron';
        }

        // Payment method info.
        $gateway = $subscription->get_payment_method_title();
        if ( $gateway ) {
            $lines[] = sprintf( 'Payment method: %s', $gateway );
        }

        // Last renewal order info.
        $last_order = $this->get_last_renewal_order( $subscription );
        if ( $last_order ) {
            $lines[] = sprintf( 'Last renewal order: #%d (status: %s)', $last_order->get_id(), $last_order->get_status() );

            $error = $this->get_payment_error_from_order( $last_order );
            if ( $error ) {
                $error_type = $this->classify_error( $error );
                $lines[] = sprintf( 'Payment error: %s', $error );
                $lines[] = sprintf( 'Error classification: %s', $this->get_error_type_label( $error_type ) );
            }
        }

        // Subscription value context.
        $lines[] = sprintf( 'Subscription total: %s %s / %s',
            $subscription->get_total(),
            $subscription->get_currency(),
            $subscription->get_billing_period()
        );

        return implode( "\n", $lines );
    }

    /**
     * Extract payment error messages from an order's notes.
     */
    private function get_payment_error_from_order( $order ) {
        if ( ! $order ) {
            return '';
        }

        $notes = wc_get_order_notes( array(
            'order_id' => $order->get_id(),
            'type'     => 'internal',
            'orderby'  => 'date_created',
            'order'    => 'DESC',
            'limit'    => 20,
        ) );

        // Search notes for error messages (payment gateways typically log failures as notes).
        $error_keywords = array(
            'failed', 'declined', 'error', 'rejected', 'denied',
            'refused', 'invalid', 'unable', 'could not', 'unsuccessful',
            'expired card', 'insufficient', 'do not honor',
            'INVALID_CARD_DATA', 'CARD_DECLINED', 'INSTRUMENT_DECLINED',
        );

        foreach ( $notes as $note ) {
            $content = $note->content;
            foreach ( $error_keywords as $keyword ) {
                if ( stripos( $content, $keyword ) !== false ) {
                    // Strip HTML and truncate.
                    $clean = wp_strip_all_tags( $content );
                    return mb_substr( $clean, 0, 500 );
                }
            }
        }

        // Fallback: check order meta for gateway-specific error storage.
        $gateway_error = $order->get_meta( '_transaction_error_message' );
        if ( $gateway_error ) {
            return $gateway_error;
        }

        // Authorize.Net stores errors in specific meta.
        $authnet_error = $order->get_meta( '_wc_authorize_net_cim_credit_card_trans_message' );
        if ( $authnet_error ) {
            return $authnet_error;
        }

        // Square stores transaction errors.
        $square_error = $order->get_meta( '_square_payment_error' );
        if ( $square_error ) {
            return $square_error;
        }

        // PayPal errors.
        $paypal_error = $order->get_meta( '_ppcp_payment_error' );
        if ( $paypal_error ) {
            return $paypal_error;
        }

        return '';
    }

    /**
     * Classify an error message into categories.
     *
     * @return string 'integration_error', 'payment_declined', 'insufficient_funds', 'expired_card', or 'unknown'
     */
    private function classify_error( $error_message ) {
        if ( empty( $error_message ) ) {
            return 'unknown';
        }

        $upper = strtoupper( $error_message );

        // Check for integration/gateway configuration errors first.
        foreach ( self::$integration_error_patterns as $pattern ) {
            if ( strpos( $upper, strtoupper( $pattern ) ) !== false ) {
                return 'integration_error';
            }
        }

        // Insufficient funds.
        $insufficient_patterns = array( 'INSUFFICIENT', 'NSF', 'NOT ENOUGH', 'INADEQUATE FUNDS' );
        foreach ( $insufficient_patterns as $p ) {
            if ( strpos( $upper, $p ) !== false ) {
                return 'insufficient_funds';
            }
        }

        // Expired card.
        $expired_patterns = array( 'EXPIRED', 'PAST DUE', 'CARD_EXPIRED' );
        foreach ( $expired_patterns as $p ) {
            if ( strpos( $upper, $p ) !== false ) {
                return 'expired_card';
            }
        }

        // Generic decline.
        $decline_patterns = array(
            'DECLINE', 'DENIED', 'REFUSED', 'REJECTED', 'DO NOT HONOR',
            'CARD_DECLINED', 'PICK UP CARD', 'LOST CARD', 'STOLEN CARD',
            'RESTRICTED CARD', 'FRAUD', 'SUSPECTED FRAUD', 'CVV', 'CVC',
            'INCORRECT_ZIP', 'AVS_FAILED', 'ZIP_MISMATCH',
        );
        foreach ( $decline_patterns as $p ) {
            if ( strpos( $upper, $p ) !== false ) {
                return 'payment_declined';
            }
        }

        return 'unknown';
    }

    /**
     * Get a human-readable label for the error type.
     */
    private function get_error_type_label( $type ) {
        $labels = array(
            'integration_error'  => 'Integration/Gateway Configuration Error (likely not customer fault)',
            'payment_declined'   => 'Payment Declined by Bank/Issuer',
            'insufficient_funds' => 'Insufficient Funds',
            'expired_card'       => 'Expired Card',
            'unknown'            => 'Unknown/Unclassified',
        );
        return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
    }

    /**
     * Get the last renewal order for a subscription.
     *
     * @param WC_Subscription $subscription
     * @return WC_Order|null
     */
    private function get_last_renewal_order( $subscription ) {
        $renewal_orders = $subscription->get_related_orders( 'ids', 'renewal' );

        if ( empty( $renewal_orders ) ) {
            return null;
        }

        // Get the most recent renewal order.
        $last_order_id = reset( $renewal_orders );
        return wc_get_order( $last_order_id );
    }
}
