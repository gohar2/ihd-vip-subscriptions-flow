<?php
/**
 * Plugin Name: IHD VIP Subscriptions
 * Description: VIP Subscription Modal Interception & Inline Switch System
 * Version: 1.2.2
 * Author: Syed
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: ihd-vip-subscriptions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'IHD_VIP_VERSION', '1.1.0' );
define( 'IHD_VIP_PATH', plugin_dir_path( __FILE__ ) );
define( 'IHD_VIP_URL', plugin_dir_url( __FILE__ ) );
define( 'IHD_VIP_BASENAME', plugin_basename( __FILE__ ) );


/**
 * Require class files.
 */
require_once IHD_VIP_PATH . 'includes/class-installer.php';
require_once IHD_VIP_PATH . 'includes/class-audit-logger.php';
require_once IHD_VIP_PATH . 'includes/class-user-scope-gate.php';

/**
 * Activation hook — create audit table.
 */
register_activation_hook( __FILE__, array( 'IHD_VIP_Installer', 'activate' ) );

/**
 * Register the My Account endpoint early (must run before rewrite rules are flushed).
 * This needs to be outside the scope gate so WordPress always knows the endpoint exists.
 */
add_action( 'init', 'ihd_vip_register_endpoint' );

function ihd_vip_register_endpoint() {
    add_rewrite_endpoint( 'manage-subscription', EP_ROOT | EP_PAGES );
}

/**
 * Flush rewrite rules on activation so the endpoint works immediately.
 */
register_activation_hook( __FILE__, 'ihd_vip_flush_rewrites' );

function ihd_vip_flush_rewrites() {
    ihd_vip_register_endpoint();
    flush_rewrite_rules();
}

/**
 * Phase 1: plugins_loaded — dependency check, admin, AJAX handlers.
 * Runs before user auth is available. No scope gate here.
 */
add_action( 'plugins_loaded', 'ihd_vip_init_early' );

function ihd_vip_init_early() {

    // Bail if WooCommerce Subscriptions is not active.
    if ( ! class_exists( 'WC_Subscriptions' ) ) {
        return;
    }

    // ─── Layer 1: Admin always loads (regardless of scope gate) ───
    if ( is_admin() ) {
        require_once IHD_VIP_PATH . 'includes/class-admin.php';
        new IHD_VIP_Admin();
    }

    // ─── Global: Cancel handler (AJAX) — must be available for all allowed users ───
    require_once IHD_VIP_PATH . 'includes/class-cancel-handler.php';
    new IHD_VIP_Cancel_Handler();

    // ─── Global: Switch handler (AJAX) — inline upgrade/downgrade checkout ───
    require_once IHD_VIP_PATH . 'includes/class-switch-handler.php';
    new IHD_VIP_Switch_Handler();

    // ─── Global: Pause handler (AJAX) — pause/resume + auto-resume cron ───
    require_once IHD_VIP_PATH . 'includes/class-pause-handler.php';
    new IHD_VIP_Pause_Handler();

    // ─── Global: Event tracker — monitors ALL subscription cancellations & payment failures ───
    // Runs outside the scope gate so it captures events for every subscription, not just VIP users.
    require_once IHD_VIP_PATH . 'includes/class-subscription-event-tracker.php';
    new IHD_VIP_Subscription_Event_Tracker();

    // ─── Phase 2: Defer scope gate + frontend UI to 'init' where user is authenticated ───
    add_action( 'init', 'ihd_vip_init_frontend' );
}

/**
 * Phase 2: init — scope gate + frontend UI.
 * Runs AFTER WordPress has processed auth cookies, so get_current_user_id() works.
 */
function ihd_vip_init_frontend() {

    // ─── Layer 2: Adaptive User Scope Gate ───
    // In development mode, restrict frontend UI to allowed users only.
    // In production mode, all logged-in users can access.
    if ( ! IHD_VIP_User_Scope_Gate::is_user_allowed() ) {
        return; // Current user is not allowed — stop frontend UI.
    }

    // ─── Shortcode: Manage Subscription page ───
    require_once IHD_VIP_PATH . 'shortcodes/manage-subscription-shortcode.php';
    Hero_VIP_Manage_Subscription_Refined_Shortcode::init();

    // Menu tab removed — the page is accessed via JS-intercepted cancel/switch
    // buttons on the subscription detail page, not via direct navigation.

    // Render shortcode content on the endpoint page.
    add_action( 'woocommerce_account_manage-subscription_endpoint', 'ihd_vip_render_manage_page' );
}

/**
 * Insert "Manage My Subscription" tab into the My Account sidebar menu.
 */
function ihd_vip_add_manage_tab( $items ) {
    // Insert after "My Subscription" if it exists, otherwise after "orders".
    $new_items = array();

    foreach ( $items as $key => $label ) {
        $new_items[ $key ] = $label;

        // Insert right after the subscriptions tab (or orders as fallback).
        if ( 'subscriptions' === $key || ( 'orders' === $key && ! isset( $items['subscriptions'] ) ) ) {
            $new_items['manage-subscription'] = 'Manage My Subscription';
        }
    }

    // Fallback: if neither key was found, just append before logout.
    if ( ! isset( $new_items['manage-subscription'] ) ) {
        $logout = isset( $new_items['customer-logout'] ) ? $new_items['customer-logout'] : null;
        unset( $new_items['customer-logout'] );
        $new_items['manage-subscription'] = 'Manage My Subscription';
        if ( $logout ) {
            $new_items['customer-logout'] = $logout;
        }
    }

    return $new_items;
}

/**
 * Render the Manage Subscription shortcode on the endpoint page.
 */
function ihd_vip_render_manage_page() {
    echo '<main>';
    echo do_shortcode( '[hero_vip_manage_subscription_refined]' );
    echo '</main>';
}
