<?php
/**
 * User Scope Gate — Option-Based Toggle
 *
 * Controls which users can see the VIP subscription features.
 * Admin can toggle between Development Mode (scoped) and Production Mode (open)
 * from the IHD VIP Subscriptions admin page.
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
     * Option key storing the scope mode ('development' or 'production').
     */
    const MODE_OPTION_KEY = 'ihd_vip_scope_mode';

    /**
     * Check if the scope gate is in development (scoped) mode.
     *
     * @return bool True if development mode is active.
     */
    public static function is_development_mode() {
        return 'production' !== get_option( self::MODE_OPTION_KEY, 'development' );
    }

    /**
     * Check if the current logged-in user is allowed to see the VIP interface.
     *
     * In production mode, all logged-in users are allowed.
     * In development mode, only users in the scoped list are allowed.
     *
     * @return bool True if user is allowed, false otherwise.
     */
    public static function is_user_allowed() {
        $current_user_id = get_current_user_id();

        // Not logged in — deny.
        if ( ! $current_user_id ) {
            return false;
        }

        // Production mode — allow all logged-in users.
        if ( ! self::is_development_mode() ) {
            return true;
        }

        // Development mode — check the scoped list.
        $allowed_users = get_option( self::OPTION_KEY, array() );

        // If no users have been selected yet, deny all (force admin to configure).
        if ( empty( $allowed_users ) || ! is_array( $allowed_users ) ) {
            return false;
        }

        return in_array( $current_user_id, array_map( 'absint', $allowed_users ), true );
    }
}
