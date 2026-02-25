<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IHD_VIP_Switch_Handler {

    public function __construct() {
        add_action( 'wp_ajax_ihd_vip_load_switch_options', array( $this, 'load_switch_options' ) );
        add_action( 'wp_ajax_ihd_vip_prepare_switch', array( $this, 'prepare_switch' ) );
    }

    /**
     * AJAX: Load available switch options (variations) for a subscription.
     * Returns upgrades and downgrades separated by tier.
     */
    public function load_switch_options() {
        check_ajax_referer( 'ihd_vip_nonce', 'nonce' );

        $subscription_id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;

        if ( ! $subscription_id ) {
            wp_send_json_error( array( 'message' => 'Invalid subscription ID.' ) );
        }

        $subscription = wcs_get_subscription( $subscription_id );

        if ( ! $subscription || get_current_user_id() !== $subscription->get_user_id() ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $upgrades   = array();
        $downgrades = array();

        foreach ( $subscription->get_items() as $item_id => $item ) {

            // Check if this item can be switched.
            if ( ! class_exists( 'WC_Subscriptions_Switcher' )
                 || ! WC_Subscriptions_Switcher::can_item_be_switched( $item, $subscription ) ) {
                continue;
            }

            $current_variation_id = $item->get_variation_id();
            $product_id           = $item->get_product_id();
            $product              = wc_get_product( $product_id );

            if ( ! $product || ! $product->is_type( 'variable-subscription' ) ) {
                continue;
            }

            $current_product = wc_get_product( $current_variation_id ? $current_variation_id : $product_id );
            $current_price   = (float) $current_product->get_price();

            foreach ( $product->get_children() as $variation_id ) {

                // Skip the current variation.
                if ( $variation_id == $current_variation_id ) {
                    continue;
                }

                $variation = wc_get_product( $variation_id );

                if ( ! $variation || ! $variation->is_purchasable() ) {
                    continue;
                }

                $variation_price = (float) $variation->get_price();

                $option = array(
                    'variation_id' => $variation_id,
                    'name'         => $variation->get_name(),
                    'price'        => $variation_price,
                    'price_html'   => $variation->get_price_html(),
                    'attributes'   => $variation->get_variation_attributes(),
                );

                if ( $variation_price > $current_price ) {
                    $upgrades[] = $option;
                } elseif ( $variation_price < $current_price ) {
                    $downgrades[] = $option;
                }
                // Equal price variations are skipped (same tier).
            }

            // Only process the first switchable item.
            break;
        }

        // Sort upgrades ascending, downgrades descending.
        usort( $upgrades, function( $a, $b ) {
            return $a['price'] <=> $b['price'];
        } );
        usort( $downgrades, function( $a, $b ) {
            return $b['price'] <=> $a['price'];
        } );

        wp_send_json_success( array(
            'upgrades'      => $upgrades,
            'downgrades'    => $downgrades,
            'current_price' => $current_price ?? 0,
        ) );
    }

    /**
     * AJAX: Prepare the switch — clear cart, apply GET trick, add switch item, render inline checkout.
     */
    public function prepare_switch() {
        check_ajax_referer( 'ihd_vip_nonce', 'nonce' );

        $subscription_id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;
        $variation_id    = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;

        if ( ! $subscription_id || ! $variation_id ) {
            wp_send_json_error( array( 'message' => 'Missing parameters.' ) );
        }

        $subscription = wcs_get_subscription( $subscription_id );

        if ( ! $subscription || get_current_user_id() !== $subscription->get_user_id() ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        foreach ( $subscription->get_items() as $item_id => $item ) {

            if ( ! class_exists( 'WC_Subscriptions_Switcher' )
                 || ! WC_Subscriptions_Switcher::can_item_be_switched( $item, $subscription ) ) {
                continue;
            }

            // Clear the cart.
            WC()->cart->empty_cart();

            // Apply the GET switch trick — WooCommerce Subscriptions reads these.
            $_GET['switch-subscription'] = $subscription_id;
            $_GET['item']                = $item_id;

            // Add the new variation to cart.
            $cart_item_key = WC()->cart->add_to_cart(
                $item->get_product_id(),
                1,
                $variation_id
            );

            if ( ! $cart_item_key ) {
                wp_send_json_error( array( 'message' => 'Could not add item to cart.' ) );
            }

            break;
        }

        // Render the checkout form HTML.
        ob_start();
        wc_get_template( 'checkout/form-checkout.php', array(
            'checkout' => WC()->checkout(),
        ) );
        $checkout_html = ob_get_clean();

        wp_send_json_success( array(
            'checkout_html' => $checkout_html,
        ) );
    }
}
