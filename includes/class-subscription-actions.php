<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IHD_VIP_Subscription_Actions {

    public function __construct() {
        // Priority 100 — run AFTER WCS adds its switch actions.
        add_filter( 'wcs_get_all_user_actions_for_subscription', array( $this, 'override_actions' ), 100, 3 );

        // Remove the WCS per-item "Upgrade or Downgrade" link rendered in the subscription items table.
        // WCS renders this via woocommerce_order_item_meta_end, which is a SEPARATE rendering path
        // from wcs_get_all_user_actions_for_subscription. We must suppress it here.
        add_action( 'wp_loaded', array( $this, 'remove_wcs_switch_link' ) );
    }

    /**
     * Remove the default WCS per-item switch link.
     *
     * WCS hooks WC_Subscriptions_Switcher::print_switch_link() at priority 10
     * on woocommerce_order_item_meta_end. This renders the "Upgrade or Downgrade"
     * link inside each line item on the view-subscription page.
     */
    public function remove_wcs_switch_link() {
        if ( ! class_exists( 'WC_Subscriptions_Switcher' ) ) {
            return;
        }

        // Try removing static method hook (WCS 2.x/3.x style).
        remove_action( 'woocommerce_order_item_meta_end', array( 'WC_Subscriptions_Switcher', 'print_switch_link' ), 10 );

        // WCS 4.x+ may use a singleton instance. Try to find and remove it.
        global $wp_filter;
        if ( isset( $wp_filter['woocommerce_order_item_meta_end'] ) ) {
            foreach ( $wp_filter['woocommerce_order_item_meta_end']->callbacks as $priority => $hooks ) {
                foreach ( $hooks as $key => $hook ) {
                    if ( is_array( $hook['function'] ) && is_object( $hook['function'][0] ) ) {
                        if ( $hook['function'][0] instanceof WC_Subscriptions_Switcher
                             || get_class( $hook['function'][0] ) === 'WC_Subscriptions_Switcher' ) {
                            if ( $hook['function'][1] === 'print_switch_link' ) {
                                unset( $wp_filter['woocommerce_order_item_meta_end']->callbacks[ $priority ][ $key ] );
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Override subscription actions on the My Account page.
     *
     * WCS renders action buttons as: <a href="{url}" class="button {key}">{name}</a>
     * The array key becomes the CSS class.
     *
     * @param array           $actions      Existing subscription actions.
     * @param WC_Subscription $subscription The subscription object.
     * @param int             $user_id      The user ID.
     * @return array Modified actions.
     */
    public function override_actions( $actions, $subscription, $user_id ) {

        if ( ! is_user_logged_in() ) {
            return $actions;
        }

        $sub_id = $subscription->get_id();

        // ─── A) Replace Cancel action with modal trigger ───
        if ( isset( $actions['cancel'] ) ) {
            $actions['cancel']['url'] = '#ihd-vip-cancel-' . $sub_id;
        }

        // ─── B) Remove ALL switch-related actions added by WCS ───
        // WCS may use keys like 'switch', 'switch_123', 'upgrade_or_downgrade', etc.
        foreach ( array_keys( $actions ) as $key ) {
            if ( strpos( $key, 'switch' ) !== false ) {
                unset( $actions[ $key ] );
            }
        }

        // ─── C) Inject our Switch button for switchable items ───
        $has_switchable = false;

        foreach ( $subscription->get_items() as $item_id => $item ) {
            if ( class_exists( 'WC_Subscriptions_Switcher' )
                 && WC_Subscriptions_Switcher::can_item_be_switched( $item, $subscription ) ) {
                $has_switchable = true;
                break;
            }
        }

        if ( $has_switchable ) {
            $actions['ihd_vip_switch'] = array(
                'url'  => '#ihd-vip-switch-' . $sub_id,
                'name' => 'Upgrade / Downgrade',
            );
        }

        return $actions;
    }
}
