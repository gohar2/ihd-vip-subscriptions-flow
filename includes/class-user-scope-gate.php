<?php
/**
 * User Scope Gate — Adaptive / Removable
 *
 * This file controls which users can see the VIP subscription features.
 * Delete this file to make the plugin load for ALL users (production mode).
 *
 * @package IHD_VIP_Subscriptions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IHD_VIP_User_Scope_Gate {

    /**
     * Option key storing the array of allowed user IDs.
     */
    const OPTION_KEY = 'ihd_vip_scoped_users';

    /**
     * Check if the current logged-in user is in the allowed list.
     *
     * @return bool True if user is allowed (or no users are configured), false otherwise.
     */
    public static function is_user_allowed() {
        $current_user_id = get_current_user_id();

        // Not logged in — allow (public pages shouldn't be gated).
        if ( ! $current_user_id ) {
            return false;
        }

        $allowed_users = get_option( self::OPTION_KEY, array() );

        // If no users have been selected yet, deny all (force admin to configure).
        if ( empty( $allowed_users ) || ! is_array( $allowed_users ) ) {
            return false;
        }

        return in_array( $current_user_id, array_map( 'absint', $allowed_users ), true );
    }
}
