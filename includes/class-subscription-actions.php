<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IHD_VIP_Subscription_Actions {

    public function __construct() {
        add_filter( 'wcs_get_all_user_actions_for_subscription', array( $this, 'override_actions' ), 10, 3 );
    }

    /**
     * Override subscription actions on the My Account page.
     *
     * WCS renders actions as: <a href="{url}" class="button {key}">{name}</a>
     * The array key becomes the CSS class — NOT any 'class' key in the array.
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
        // URL becomes #ihd-vip-cancel-{ID} so JS can extract the subscription ID.
        if ( isset( $actions['cancel'] ) ) {
            $actions['cancel']['url'] = '#ihd-vip-cancel-' . $sub_id;
        }

        // ─── B) Remove default WCS switch action ───
        // WCS adds 'switch' key which links to the product page — we replace it entirely.
        unset( $actions['switch'] );

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
