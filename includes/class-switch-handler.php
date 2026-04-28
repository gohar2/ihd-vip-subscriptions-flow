<?php
/**
 * IHD VIP Switch Handler
 *
 * Handles subscription upgrade/downgrade via AJAX.
 * Dynamically resolves sibling variations from the parent product — no hardcoded tier maps.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class IHD_VIP_Switch_Handler {

    public function __construct() {
        add_action( 'wp_ajax_ihd_vip_prepare_switch', array( $this, 'handle_prepare_switch' ) );
        add_action( 'wp_ajax_ihd_vip_checkout_page',  array( $this, 'render_checkout_page' ) );
        add_action( 'wp_ajax_ihd_vip_check_switch_complete', array( $this, 'check_switch_complete' ) );

        // Hook BEFORE WCS (priority 5) to inject switch data directly.
        // WCS 8.3.0+ uses filter_input_array(INPUT_GET) which ignores $_GET modifications.
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'inject_switch_data' ), 5, 3 );
    }

    /**
     * Inject subscription_switch data directly into cart item data.
     *
     * WCS 8.3.0+ uses Request::get_var() which internally calls filter_input_array(INPUT_GET).
     * This reads from the actual HTTP request input stream, NOT from the $_GET superglobal.
     * Therefore, programmatically setting $_GET['switch-subscription'] no longer works.
     *
     * This method hooks at priority 5 (before WCS at priority 10) to inject the switch data
     * directly, bypassing the need for $_GET manipulation.
     */
    public function inject_switch_data( $cart_item_data, $product_id, $variation_id ) {
        $transient_key = 'ihd_vip_switch_' . get_current_user_id();
        $switch_data   = get_transient( $transient_key );

        if ( ! $switch_data || ! is_array( $switch_data ) ) {
            return $cart_item_data;
        }

        if ( (int) $switch_data['variation_id'] !== (int) $variation_id ) {
            return $cart_item_data;
        }

        $cart_item_data['subscription_switch'] = array(
            'subscription_id'        => (int) $switch_data['subscription_id'],
            'item_id'                => (int) $switch_data['item_id'],
            'next_payment_timestamp' => (int) $switch_data['next_payment_timestamp'],
            'upgraded_or_downgraded' => '',
        );

        delete_transient( $transient_key );

        return $cart_item_data;
    }

    /* ──────────────────────────────────────────────────────────────────────────
     * AJAX: Prepare cart with WCS switch item.
     * POST params: nonce, subscription_id, variation_id (the target sibling variation)
     * ────────────────────────────────────────────────────────────────────────── */
    public function handle_prepare_switch() {
        check_ajax_referer( 'ihd_vip_nonce', 'nonce' );

        $user_id              = get_current_user_id();
        $subscription_id      = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;
        $target_variation_id  = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;

        if ( ! $subscription_id || ! $target_variation_id ) {
            wp_send_json_error( array( 'message' => 'Missing required parameters.' ) );
        }

        // Validate subscription ownership.
        $subscription = wcs_get_subscription( $subscription_id );
        if ( ! $subscription || (int) $subscription->get_customer_id() !== $user_id ) {
            wp_send_json_error( array( 'message' => 'Invalid subscription.' ) );
        }

        if ( 'active' !== $subscription->get_status() ) {
            wp_send_json_error( array( 'message' => 'Subscription is not active.' ) );
        }

        // Find the subscription line item that is a variable-subscription product.
        $switch_item_id       = 0;
        $parent_product_id    = 0;
        $current_variation_id = 0;

        foreach ( $subscription->get_items() as $item_id => $item ) {
            $vid = $item->get_variation_id();
            if ( $vid ) {
                $parent = wc_get_product( $item->get_product_id() );
                if ( $parent && $parent->is_type( 'variable-subscription' ) ) {
                    $switch_item_id       = $item_id;
                    $parent_product_id    = $item->get_product_id();
                    $current_variation_id = $vid;
                    break;
                }
            }
        }

        if ( ! $switch_item_id ) {
            wp_send_json_error( array( 'message' => 'No switchable subscription item found.' ) );
        }

        // Verify the target variation is a sibling of the current variation (same parent product).
        $target_variation = wc_get_product( $target_variation_id );
        if ( ! $target_variation || (int) $target_variation->get_parent_id() !== $parent_product_id ) {
            wp_send_json_error( array( 'message' => 'Target variation does not belong to the same product.' ) );
        }

        // Prevent switching to the same variation.
        if ( $target_variation_id === $current_variation_id ) {
            wp_send_json_error( array( 'message' => 'You are already on this plan.' ) );
        }

        // Clear the cart and add the switch item.
        WC()->cart->empty_cart();

        // Calculate next payment timestamp (WCS needs this for proration calculations).
        $next_payment_timestamp = $subscription->get_time( 'next_payment' );
        if ( ! $next_payment_timestamp ) {
            $next_payment_timestamp = $subscription->get_time( 'end' );
        }

        // Store switch data in transient for inject_switch_data() to pick up.
        // WCS 8.3.0+ uses filter_input_array(INPUT_GET) in Request::get_var() which reads
        // from the actual HTTP input stream, not $_GET. Our priority-5 filter injects
        // subscription_switch data into cart_item_data before WCS runs at priority 10.
        $transient_key = 'ihd_vip_switch_' . $user_id;
        set_transient( $transient_key, array(
            'subscription_id'        => $subscription_id,
            'item_id'                => $switch_item_id,
            'variation_id'           => $target_variation_id,
            'next_payment_timestamp' => $next_payment_timestamp,
        ), 5 * MINUTE_IN_SECONDS );

        // Build variation attributes for add_to_cart.
        $attributes = array();
        foreach ( $target_variation->get_variation_attributes() as $attr_key => $attr_val ) {
            $attributes[ $attr_key ] = $attr_val;
        }

        $added = WC()->cart->add_to_cart(
            $parent_product_id,
            1,
            $target_variation_id,
            $attributes
        );

        if ( ! $added ) {
            delete_transient( $transient_key );
            $notices = wc_get_notices( 'error' );
            wc_clear_notices();
            $msg = ! empty( $notices ) ? wp_strip_all_tags( $notices[0]['notice'] ?? $notices[0] ) : 'Could not add switch item to cart.';
            wp_send_json_error( array( 'message' => $msg ) );
        }

        WC()->cart->calculate_totals();

        // Build a summary for the popup header.
        $cart_total = WC()->cart->get_total( 'edit' );
        $period     = get_post_meta( $target_variation_id, '_subscription_period', true );
        $var_name   = self::get_variation_label( $target_variation );

        // Determine switch direction from WCS cart data.
        $switch_direction = 'switch';
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['subscription_switch']['upgraded_or_downgraded'] ) ) {
                $wcs_dir = $cart_item['subscription_switch']['upgraded_or_downgraded'];
                if ( 'upgraded' === $wcs_dir ) {
                    $switch_direction = 'upgrade';
                } elseif ( 'downgraded' === $wcs_dir ) {
                    $switch_direction = 'downgrade';
                } else {
                    // crossgraded — determine by price comparison.
                    $current_total = (float) $subscription->get_total();
                    $target_price  = (float) $target_variation->get_price();
                    if ( $target_price > $current_total ) {
                        $switch_direction = 'upgrade';
                    } elseif ( $target_price < $current_total ) {
                        $switch_direction = 'downgrade';
                    } else {
                        $switch_direction = 'switch';
                    }
                }
                break;
            }
        }

        // Record the current highest switch order ID so the polling endpoint
        // can distinguish a NEW switch completion from a prior one.
        $existing_switch_orders = $subscription->get_related_orders( 'ids', 'switch' );
        $last_switch_order_id   = ! empty( $existing_switch_orders ) ? max( $existing_switch_orders ) : 0;

        wp_send_json_success( array(
            'message'              => 'Cart prepared for switch.',
            'checkout_url'         => wc_get_checkout_url(),
            'plan_label'           => $var_name,
            'price'                => wc_price( $target_variation->get_price() ),
            'period'               => $period ?: 'month',
            'cart_total'           => wc_price( $cart_total ),
            'variation_id'         => $target_variation_id,
            'switch_direction'     => $switch_direction,
            'last_switch_order_id' => $last_switch_order_id,
        ) );
    }

    /* ──────────────────────────────────────────────────────────────────────────
     * AJAX: Render a stripped-down checkout page (no header/footer) for the
     * inline popup iframe.
     * ────────────────────────────────────────────────────────────────────────── */
    public function render_checkout_page() {
        if ( ! is_user_logged_in() ) {
            wp_die( 'Please log in.', 'Login Required', array( 'response' => 403 ) );
        }

        if ( WC()->cart->is_empty() ) {
            wp_die( 'Your cart is empty. Please select a plan first.', 'Empty Cart', array( 'response' => 400 ) );
        }

        show_admin_bar( false );

        // Override the checkout submit button text via WooCommerce filter.
        // Determine switch direction from the cart for button label.
        $switch_direction = 'Switch';
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['subscription_switch']['upgraded_or_downgraded'] ) ) {
                $wcs_dir = $cart_item['subscription_switch']['upgraded_or_downgraded'];
                if ( 'upgraded' === $wcs_dir ) {
                    $switch_direction = 'Upgrade';
                } elseif ( 'downgraded' === $wcs_dir ) {
                    $switch_direction = 'Downgrade';
                } else {
                    // crossgraded — compare prices.
                    $sub_id = $cart_item['subscription_switch']['subscription_id'] ?? 0;
                    $sub_obj = $sub_id ? wcs_get_subscription( $sub_id ) : null;
                    if ( $sub_obj && (float) $cart_item['data']->get_price() < (float) $sub_obj->get_total() ) {
                        $switch_direction = 'Downgrade';
                    } else {
                        $switch_direction = 'Upgrade';
                    }
                }
                break;
            }
        }
        $button_label = $switch_direction . ' Plan Now';

        // Hook into WC's order button text filter at high priority so it
        // overrides WooCommerce Subscriptions' "Switch subscription" label.
        add_filter( 'woocommerce_order_button_text', function () use ( $button_label ) {
            return $button_label;
        }, 999 );

        ob_start();
        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <?php wp_head(); ?>
    <style>
        body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
        #wpadminbar, header, .site-header, .masthead, .top-bar,
        footer, .site-footer, .footer, .bottom-bar,
        .breadcrumb, .breadcrumbs, .woocommerce-breadcrumb,
        nav.main-navigation, .primary-menu, .mobile-menu,
        .sidebar, #secondary, .widget-area { display: none !important; }
        #content, .site-content, .content-area, main, .entry-content {
            max-width: 100% !important; margin: 0 !important; padding: 12px 16px !important;
            float: none !important; width: 100% !important;
        }
        .woocommerce-checkout { max-width: 100% !important; }
        .woocommerce-form-coupon-toggle { display: none !important; }
        .woocommerce-checkout-review-order-table { font-size: 14px; }
        #place_order {
            background: #C84B31 !important; border-color: #C84B31 !important;
            color: #fff !important; font-size: 15px !important; padding: 12px 24px !important;
            border-radius: 8px !important; width: auto !important; min-width: 200px !important;
            max-width: 100% !important; margin: 12px auto 0 !important; display: block !important;
            cursor: pointer !important; transition: background .2s !important;
            font-weight: 600 !important; letter-spacing: .01em !important;
        }
        #place_order:hover { background: #a63d28 !important; }
        .woocommerce-order-received .woocommerce-thankyou-order-received {
            font-size: 18px; font-weight: 600; text-align: center; padding: 40px 20px;
        }
    </style>
</head>
<body <?php body_class( 'ihd-vip-checkout-popup' ); ?>>
    <div id="ihd-checkout-wrap">
        <?php echo do_shortcode( '[woocommerce_checkout]' ); ?>
    </div>
    <?php wp_footer(); ?>
    <script>
    (function(){
        /* Rename the submit button — persist through WC checkout AJAX updates */
        var btnLabel = <?php echo wp_json_encode( $button_label ); ?>;
        function setBtn() {
            var b = document.getElementById('place_order');
            if (b && b.value !== btnLabel) b.value = btnLabel;
        }
        setBtn();
        if (window.jQuery) jQuery(document.body).on('updated_checkout', setBtn);
        new MutationObserver(setBtn).observe(document.body, { childList: true, subtree: true });

        function notifyParent(type, data) {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ source: 'ihd_vip_checkout', type: type, data: data || {} }, '*');
            }
        }
        if (document.body.classList.contains('woocommerce-order-received') ||
            document.querySelector('.woocommerce-order-received') ||
            window.location.href.indexOf('order-received') !== -1) {
            notifyParent('switch_complete');
        }
        var observer = new MutationObserver(function() {
            var errList = document.querySelector('.woocommerce-error');
            if (errList) notifyParent('checkout_error', { message: errList.textContent.trim() });
        });
        var target = document.querySelector('.woocommerce-notices-wrapper') || document.getElementById('ihd-checkout-wrap');
        if (target) observer.observe(target, { childList: true, subtree: true });
        if (window.jQuery) {
            jQuery(document.body).on('checkout_error', function() {
                notifyParent('checkout_error', { message: jQuery('.woocommerce-error').text().trim() });
            });
        }
    })();
    </script>
</body>
</html>
        <?php
        $html = ob_get_clean();
        echo $html;
        exit;
    }

    /* ──────────────────────────────────────────────────────────────────────────
     * AJAX: Check if a recent switch order completed for this subscription.
     * Fallback polling when postMessage detection doesn't fire.
     * ────────────────────────────────────────────────────────────────────────── */
    public function check_switch_complete() {
        check_ajax_referer( 'ihd_vip_nonce', 'nonce' );

        $subscription_id = isset( $_GET['subscription_id'] ) ? absint( $_GET['subscription_id'] ) : 0;
        if ( ! $subscription_id ) {
            wp_send_json_error();
        }

        $subscription = wcs_get_subscription( $subscription_id );
        if ( ! $subscription || (int) $subscription->get_customer_id() !== get_current_user_id() ) {
            wp_send_json_error();
        }

        // Only consider switch orders created AFTER the one that existed when
        // the cart was prepared. This prevents the polling from matching a
        // previous switch order that is still within the time window.
        $since_order_id = isset( $_GET['since_order_id'] ) ? absint( $_GET['since_order_id'] ) : 0;

        $related_orders = $subscription->get_related_orders( 'ids', 'switch' );
        $recent_switch  = false;

        if ( ! empty( $related_orders ) ) {
            // Filter to only orders newer than the reference point.
            $new_orders = array_filter( $related_orders, function ( $oid ) use ( $since_order_id ) {
                return $oid > $since_order_id;
            } );

            if ( ! empty( $new_orders ) ) {
                $latest_order_id = max( $new_orders );
                $order           = wc_get_order( $latest_order_id );
                if ( $order ) {
                    $status = $order->get_status();
                    if ( in_array( $status, array( 'completed', 'processing' ), true ) ) {
                        $recent_switch = true;
                    }
                }
            }
        }

        wp_send_json_success( array( 'switched' => $recent_switch ) );
    }

    /* ──────────────────────────────────────────────────────────────────────────
     * Static helpers — used by the shortcode to build dynamic plan cards.
     * ────────────────────────────────────────────────────────────────────────── */

    /**
     * Given a subscription object, find the switchable line item and return
     * the current variation ID, parent product ID, and line item ID.
     *
     * @param  WC_Subscription $subscription
     * @return array|false     { item_id, product_id, variation_id } or false.
     */
    public static function get_switchable_item( $subscription ) {
        foreach ( $subscription->get_items() as $item_id => $item ) {
            $vid = $item->get_variation_id();
            if ( ! $vid ) {
                continue;
            }
            $parent = wc_get_product( $item->get_product_id() );
            // Only match variable-subscription products — skip regular variable products
            // (e.g. donation products that may also be on the subscription).
            if ( $parent && $parent->is_type( 'variable-subscription' ) ) {
                return array(
                    'item_id'      => $item_id,
                    'product_id'   => $item->get_product_id(),
                    'variation_id' => $vid,
                );
            }
        }
        return false;
    }

    /**
     * Get all purchasable sibling variations of a given parent product,
     * optionally filtered to match a specific billing period (month/year).
     *
     * Each returned entry includes:
     *   variation_id, label, slug, price, period, interval, attributes, is_current
     *
     * @param  int    $parent_product_id  The variable-subscription product ID.
     * @param  int    $current_variation  The variation the customer is currently on.
     * @param  string $match_period       Optional: 'month', 'year', etc. Empty = all.
     * @return array  Sorted by price ascending.
     */
    public static function get_sibling_variations( $parent_product_id, $current_variation_id = 0, $match_period = '' ) {
        $parent = wc_get_product( $parent_product_id );
        if ( ! $parent || ! $parent->is_type( 'variable-subscription' ) ) {
            return array();
        }

        $siblings = array();

        // Use get_children() instead of get_available_variations() to bypass
        // WCS_Limiter purchasability checks. When a subscription product has the
        // "limit: one active subscription" setting, WCS marks all variations as
        // non-purchasable for users who already own a subscription — which is every
        // user viewing this shortcode. We only need the variations for display;
        // actual switch purchasability is validated in handle_prepare_switch().
        $child_ids = $parent->get_children();

        foreach ( $child_ids as $var_id ) {
            $variation = wc_get_product( $var_id );
            if ( ! $variation || 'publish' !== get_post_status( $var_id ) ) {
                continue;
            }

            // Skip variations without a price.
            if ( '' === $variation->get_price() ) {
                continue;
            }

            $period   = get_post_meta( $var_id, '_subscription_period', true );
            $interval = get_post_meta( $var_id, '_subscription_period_interval', true );

            // If caller wants to match a specific billing period, skip non-matching.
            if ( $match_period && $period !== $match_period ) {
                continue;
            }

            $siblings[] = array(
                'variation_id' => $var_id,
                'label'        => self::get_variation_label( $variation ),
                'slug'         => self::get_variation_slug( $variation ),
                'price'        => (float) $variation->get_price(),
                'period'       => $period ?: 'month',
                'interval'     => $interval ?: '1',
                'attributes'   => $variation->get_variation_attributes(),
                'benefits'     => self::get_variation_benefits( $var_id ),
                'is_current'   => ( $var_id === (int) $current_variation_id ),
            );
        }

        // Sort by price ascending so plan cards display Bronze → Silver → Gold.
        usort( $siblings, function ( $a, $b ) {
            return $a['price'] <=> $b['price'];
        } );

        return $siblings;
    }

    /**
     * Build a human-readable label for a variation.
     * Uses the "membership-level" attribute term name if present, otherwise falls
     * back to the variation's formatted name.
     */
    public static function get_variation_label( $variation ) {
        $attrs = $variation->get_variation_attributes();

        // Prioritise the membership-level attribute (common across both products).
        // Resolve the attribute slug to the taxonomy term name so renames flow
        // through automatically (e.g. "hero" → "Bronze").
        $level = $attrs['attribute_pa_membership-level'] ?? '';
        if ( $level ) {
            $term = get_term_by( 'slug', $level, 'pa_membership-level' );
            if ( $term && ! is_wp_error( $term ) ) {
                return $term->name;
            }
            return ucwords( str_replace( '-', ' ', $level ) );
        }

        // Fallback: strip the parent name prefix.
        $name = $variation->get_name();
        $parent = wc_get_product( $variation->get_parent_id() );
        if ( $parent ) {
            $name = trim( str_replace( $parent->get_name() . ' -', '', $name ), ' -' );
        }
        return $name ?: 'Plan #' . $variation->get_id();
    }

    /**
     * Parse the `_subscription_details` meta into an array of benefit lines.
     * The field is free-text with one benefit per line.
     */
    public static function get_variation_benefits( $variation_id ) {
        $raw = get_post_meta( $variation_id, '_subscription_details', true );
        if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
            return array();
        }

        $lines = preg_split( '/\r\n|\r|\n/', $raw );
        $lines = array_map( 'trim', $lines );
        return array_values( array_filter( $lines, 'strlen' ) );
    }

    /**
     * Build a URL-safe slug from the variation's membership-level attribute.
     */
    public static function get_variation_slug( $variation ) {
        $attrs = $variation->get_variation_attributes();
        $level = $attrs['attribute_pa_membership-level'] ?? '';
        return $level ?: sanitize_title( self::get_variation_label( $variation ) );
    }
}
