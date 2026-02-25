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
     * - Replace Cancel action URL with modal trigger.
     * - Inject Upgrade/Downgrade button for switchable subscriptions.
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

        // ─── A) Replace Cancel action with modal trigger ───
        if ( isset( $actions['cancel'] ) ) {
            $actions['cancel']['url'] = '#ihd-vip-cancel';
            $actions['cancel']['class'] = isset( $actions['cancel']['class'] )
                ? $actions['cancel']['class'] . ' ihd-vip-cancel-trigger'
                : 'ihd-vip-cancel-trigger';
        }

        // ─── B) Inject Upgrade/Downgrade button for switchable items ───
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
                'url'   => '#ihd-vip-switch',
                'name'  => 'Upgrade / Downgrade',
                'class' => 'ihd-vip-switch-trigger button',
            );
        }

        return $actions;
    }
}
