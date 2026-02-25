<?php
/**
 * Plugin Name: IHD VIP Subscriptions
 * Description: VIP Subscription Modal Interception & Inline Switch System
 * Version: 1.0.2
 * Author: Syed
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: ihd-vip-subscriptions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'IHD_VIP_VERSION', '1.0.2' );
define( 'IHD_VIP_PATH', plugin_dir_path( __FILE__ ) );
define( 'IHD_VIP_URL', plugin_dir_url( __FILE__ ) );
define( 'IHD_VIP_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Require class files.
 */
require_once IHD_VIP_PATH . 'includes/class-installer.php';
require_once IHD_VIP_PATH . 'includes/class-audit-logger.php';

/**
 * Activation hook — create audit table.
 */
register_activation_hook( __FILE__, array( 'IHD_VIP_Installer', 'activate' ) );

/**
 * Initialize the plugin after all plugins are loaded.
 */
add_action( 'plugins_loaded', 'ihd_vip_init' );

function ihd_vip_init() {

    // Bail if WooCommerce Subscriptions is not active.
    if ( ! class_exists( 'WC_Subscriptions' ) ) {
        return;
    }

    // ─── Layer 1: Admin always loads (regardless of scope gate) ───
    if ( is_admin() ) {
        require_once IHD_VIP_PATH . 'includes/class-admin.php';
        new IHD_VIP_Admin();
    }

    // ─── AJAX handlers register always (they have their own nonce + ownership checks) ───
    // wp_ajax_* hooks only fire inside admin-ajax.php, which WordPress treats as is_admin().
    // If these aren't registered, AJAX calls from the frontend fail silently.
    require_once IHD_VIP_PATH . 'includes/class-cancel-handler.php';
    require_once IHD_VIP_PATH . 'includes/class-switch-handler.php';
    new IHD_VIP_Cancel_Handler();
    new IHD_VIP_Switch_Handler();

    // ─── Layer 2: Adaptive User Scope Gate ───
    // If the gate file exists, use it to restrict frontend UI loading.
    // If the file is deleted, the plugin loads for everyone (production mode).
    $gate_file = IHD_VIP_PATH . 'includes/class-user-scope-gate.php';

    if ( file_exists( $gate_file ) ) {
        require_once $gate_file;

        if ( ! IHD_VIP_User_Scope_Gate::is_user_allowed() ) {
            return; // Current user is not in the allowed list — stop frontend UI.
        }
    }

    // ─── Core: Frontend UI (filter + modals) — gated by scope ───
    require_once IHD_VIP_PATH . 'includes/class-subscription-actions.php';
    require_once IHD_VIP_PATH . 'includes/class-modal-renderer.php';

    new IHD_VIP_Subscription_Actions();
    new IHD_VIP_Modal_Renderer();
}
