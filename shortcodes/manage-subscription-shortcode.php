<?php
/**
 * Hero VIP Portal — Manage Subscription Shortcode
 * Version: 3.0.0
 * Matches Vercel mockup: inline flows, no separate modals
 */

if (!defined('ABSPATH')) exit;

final class Hero_VIP_Manage_Subscription_Refined_Shortcode {
  const SHORTCODE = 'hero_vip_manage_subscription_refined';

  public static function init() {
    add_shortcode(self::SHORTCODE, [__CLASS__, 'render_shortcode']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_my_account_interceptors_scripts']);
  }

  /**
   * On the My Account page, rewrite WCS Cancel / Switch button HREFs
   * so they point to our manage-subscription endpoint instead.
   */
  public static function enqueue_my_account_interceptors_scripts() {
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
      return;
    }

    wp_enqueue_script('jquery');

    $inline_js = <<<'JS'
jQuery(function($) {
  if (window.subscriptionId) return;

  var $cancelBtn = $('a.woocommerce-button.button.cancel');
  var $switchBtn = $('a.wcs-switch-link.button');

  try {
    var subscriptionId = null;

    // Try cancel button first: URL has ?subscription_id=…
    var cancelHref = $cancelBtn.attr('href');
    if (cancelHref) {
      var cancelUrl = new URL(cancelHref, window.location.origin);
      subscriptionId = cancelUrl.searchParams.get('subscription_id');
    }

    // Fallback: try the switch/upgrade button: URL has ?switch-subscription=…
    if (!subscriptionId) {
      var switchHref = $switchBtn.first().attr('href');
      if (switchHref) {
        var switchUrl = new URL(switchHref, window.location.origin);
        subscriptionId = switchUrl.searchParams.get('switch-subscription');
      }
    }

    if (!subscriptionId) return;

    var managePageUrl = '/my-account/manage-subscription/?subscription=' + subscriptionId;
    if ($cancelBtn.length) $cancelBtn.attr('href', managePageUrl + '#cancel');
    if ($switchBtn.length) $switchBtn.attr('href', managePageUrl + '#upgrade-downgrade');
    window.subscriptionId = subscriptionId;
  } catch (e) {
    console.error('IFX: Failed to intercept subscription buttons', e);
  }
});
JS;

    wp_add_inline_script('jquery', $inline_js);
  }

  public static function render_shortcode($atts = [], $content = null, $shortcode_tag = '') {
    static $instance = 0; $instance++;
    $uid = 'ifx-hvp-' . $instance;

    $atts = shortcode_atts([
      'current_plan'     => 'Silver Membership',
      'current_price'    => '$9.99',
      'current_interval' => 'Monthly',
      'next_bill'        => 'March 15, 2026',
      'slider_category'  => '',
      'slider_count'     => '4',
    ], $atts, $shortcode_tag);

    $current_plan     = esc_html($atts['current_plan']);
    $current_price    = esc_html($atts['current_price']);
    $current_interval = esc_html($atts['current_interval']);
    $next_bill        = esc_html($atts['next_bill']);

    /* ── Subscription ID from query param ── */
    $subscription_id = isset( $_GET['subscription'] ) ? absint( $_GET['subscription'] ) : 0;

    /* ── Resolve subscription product & sibling variations dynamically ── */
    $sub_product_id       = 0;
    $current_variation_id = 0;
    $current_billing      = 'monthly';
    $sibling_variations   = array();

    if ( $subscription_id && function_exists( 'wcs_get_subscription' ) && class_exists( 'IHD_VIP_Switch_Handler' ) ) {
        $subscription_obj = wcs_get_subscription( $subscription_id );
        if ( $subscription_obj && (int) $subscription_obj->get_customer_id() === get_current_user_id() ) {
            // Pull live data from the subscription.
            $sub_total        = $subscription_obj->get_total();
            $sub_period       = $subscription_obj->get_billing_period();
            $current_interval = ucfirst( $sub_period . 'ly' );
            $current_price    = wc_price( $sub_total );
            $next_payment     = $subscription_obj->get_date( 'next_payment' );
            $next_bill        = $next_payment ? date_i18n( 'F j, Y', strtotime( $next_payment ) ) : 'N/A';

            // Find the switchable line item dynamically.
            $switchable = IHD_VIP_Switch_Handler::get_switchable_item( $subscription_obj );
            if ( $switchable ) {
                $sub_product_id       = $switchable['product_id'];
                $current_variation_id = $switchable['variation_id'];
                $current_var          = wc_get_product( $current_variation_id );

                if ( $current_var ) {
                    $current_plan = IHD_VIP_Switch_Handler::get_variation_label( $current_var ) . ' Membership';
                }

                // Fetch sibling variations matching the same billing period.
                $sibling_variations = IHD_VIP_Switch_Handler::get_sibling_variations(
                    $sub_product_id,
                    $current_variation_id,
                    $sub_period // 'month' or 'year'
                );

                // If the product has only 1 variant for the current period (e.g. a
                // legacy product with just "Monthly"), show the OTHER period's
                // variants instead so the user can switch monthly↔annual.
                if ( count( $sibling_variations ) < 2 ) {
                    $alt_period = ( 'month' === $sub_period ) ? 'year' : 'month';
                    $alt_variations = IHD_VIP_Switch_Handler::get_sibling_variations(
                        $sub_product_id,
                        $current_variation_id,
                        $alt_period
                    );
                    if ( ! empty( $alt_variations ) ) {
                        $sibling_variations = $alt_variations;
                    }
                }
            }
        }
    }

    /* ── Fetch slider posts ── */
    $slider_posts = self::get_slider_posts(
      sanitize_text_field($atts['slider_category']),
      absint($atts['slider_count']) ?: 4
    );

    ob_start();
    ?>
    <div id="<?php echo esc_attr($uid); ?>" class="ifx-hvp">
      <style>
        /* === Root & Reset === */
        #<?php echo esc_attr($uid); ?>{
          --bg: #faf7f5;
          --card: #ffffff;
          --border: #ebe0db;
          --muted: #6b6b6b;
          --text: #1a1a1a;
          --brand-red: #C84B31;
          --brand-red-2: #a63d28;
          --soft-red: #f7ecec;
          --soft-red-border: #e9cfcf;
          --bronze: #b55a1a;
          --bronze-bg: #fef3ec;
          --bronze-border: #e7c4ab;
          --silver: #6b7b8f;
          --silver-bg: #f1f5f9;
          --silver-border: #d8dfe7;
          --gold: #d39200;
          --gold-bg: #fefce8;
          --gold-border: #f1d79a;
          --warn-bg: #fefce8;
          --warn-border: #fde68a;
          --warn-text: #92400e;
          --destructive: #dc2626;
          --destructive-bg: #fef2f2;
          --accent-bg: #f5f5f4;
          --radius: 12px;
          font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
          color: var(--text);
          background: var(--bg);
          border-radius: 16px;
          padding: 32px 0 40px;
          isolation: isolate;
          line-height: 1.5;
        }
        #<?php echo esc_attr($uid); ?>,
        #<?php echo esc_attr($uid); ?> * { box-sizing: border-box; margin: 0; }
        #<?php echo esc_attr($uid); ?> img { max-width: 100%; height: auto; display: block; }

        /* === Container === */
        #<?php echo esc_attr($uid); ?> .wrap {
          width: min(920px, calc(100% - 40px));
          margin: 0 auto;
        }

        /* === Header === */
        #<?php echo esc_attr($uid); ?> .topbar {
          display: flex !important;
          align-items: center !important;
          gap: 8px !important;
          font-weight: 600 !important;
          font-size: 14px !important;
          letter-spacing: .05em !important;
          color: var(--brand-red) !important;
          text-transform: uppercase !important;
          margin-bottom: 6px !important;
        }
        #<?php echo esc_attr($uid); ?> .topbar .spark {
          width: 20px; height: 20px;
          display: inline-flex; align-items: center; justify-content: center;
        }
        #<?php echo esc_attr($uid); ?> .h1 {
          font-size: 28px !important;
          font-weight: 700 !important;
          line-height: 1.2 !important;
          color: var(--text) !important;
        }
        @media (min-width: 700px) {
          #<?php echo esc_attr($uid); ?> .h1 { font-size: 32px !important; }
        }
        #<?php echo esc_attr($uid); ?> .lead {
          margin: 8px 0 24px !important;
          color: var(--muted) !important;
          font-size: 14px !important;
          line-height: 1.6 !important;
          max-width: 720px !important;
        }

        /* === Current Plan Bar === */
        #<?php echo esc_attr($uid); ?> .currentbar {
          background: rgba(200,75,49,.05) !important;
          border: 1px solid rgba(200,75,49,.2) !important;
          border-radius: var(--radius) !important;
          padding: 16px 18px !important;
          display: flex !important;
          align-items: center !important;
          gap: 12px !important;
          margin-bottom: 24px !important;
        }
        #<?php echo esc_attr($uid); ?> .currentbar .icon {
          width: 40px !important; height: 40px !important;
          border-radius: 50% !important;
          background: var(--brand-red) !important;
          display: flex !important; align-items: center !important; justify-content: center !important;
          flex: 0 0 auto !important;
        }
        #<?php echo esc_attr($uid); ?> .currentbar .t1 {
          font-weight: 700 !important;
          font-size: 14px !important;
          color: var(--text) !important;
        }
        #<?php echo esc_attr($uid); ?> .currentbar .t2 {
          font-size: 12px !important;
          color: var(--muted) !important;
          margin-top: 2px !important;
        }

        /* === Section / Accordion === */
        #<?php echo esc_attr($uid); ?> .section {
          background: var(--card) !important;
          border: 1px solid var(--border) !important;
          border-radius: var(--radius) !important;
          margin: 0 0 16px !important;
          overflow: hidden !important;
        }
        #<?php echo esc_attr($uid); ?> .acc-h {
          width: 100% !important;
          border: 0 !important;
          background: #fff !important;
          padding: 20px !important;
          display: flex !important;
          align-items: center !important;
          justify-content: space-between !important;
          gap: 14px !important;
          cursor: pointer !important;
          font-family: inherit !important;
          transition: background .15s ease !important;
        }
        #<?php echo esc_attr($uid); ?> .acc-h:hover { background: rgba(0,0,0,.02) !important; }
        #<?php echo esc_attr($uid); ?> .acc-h:focus { outline: none !important; box-shadow: 0 0 0 3px rgba(200,75,49,.12) inset !important; }
        #<?php echo esc_attr($uid); ?> .acc-left {
          display: flex !important;
          align-items: center !important;
          gap: 12px !important;
        }
        /* Accordion icons — each section gets a unique color */
        #<?php echo esc_attr($uid); ?> .acc-ico {
          width: 40px !important; height: 40px !important;
          min-width: 40px !important; min-height: 40px !important;
          border-radius: 50% !important;
          display: flex !important; align-items: center !important; justify-content: center !important;
          flex: 0 0 auto !important;
        }
        #<?php echo esc_attr($uid); ?> .acc-ico.ico-upgrade {
          background: rgba(200,75,49,.1) !important;
          color: var(--brand-red) !important;
        }
        #<?php echo esc_attr($uid); ?> .acc-ico.ico-pause {
          background: var(--accent-bg) !important;
          color: #57534e !important;
        }
        #<?php echo esc_attr($uid); ?> .acc-ico.ico-cancel {
          background: var(--destructive-bg) !important;
          color: var(--destructive) !important;
        }
        #<?php echo esc_attr($uid); ?> .acc-title,
        #<?php echo esc_attr($uid); ?> .section .acc-title,
        #<?php echo esc_attr($uid); ?> .section .acc-h .acc-title,
        #<?php echo esc_attr($uid); ?> h2.acc-title {
          font-weight: 600 !important;
          font-size: 18px !important;
          line-height: 1.3 !important;
          color: var(--text) !important;
          display: block !important;
          visibility: visible !important;
          opacity: 1 !important;
          margin: 0 !important;
          padding: 0 !important;
          text-transform: none !important;
          letter-spacing: normal !important;
        }
        #<?php echo esc_attr($uid); ?> .acc-sub {
          font-size: 14px !important;
          color: var(--muted) !important;
          margin-top: 2px !important;
          display: block !important;
          visibility: visible !important;
        }
        #<?php echo esc_attr($uid); ?> .chev {
          width: 20px !important; height: 20px !important;
          color: var(--muted) !important;
          transition: transform .3s ease !important;
          flex: 0 0 auto !important;
        }
        #<?php echo esc_attr($uid); ?> .section[data-open="1"] .chev { transform: rotate(180deg) !important; }

        /* Animated accordion panel */
        #<?php echo esc_attr($uid); ?> .acc-panel {
          border-top: 1px solid var(--border) !important;
          background: #fff !important;
          max-height: 0 !important;
          overflow: hidden !important;
          opacity: 0 !important;
          transition: max-height .4s cubic-bezier(0.4, 0, 0.2, 1),
                      opacity .3s ease !important;
        }
        #<?php echo esc_attr($uid); ?> .section[data-open="1"] .acc-panel {
          max-height: 3000px !important;
          opacity: 1 !important;
        }
        #<?php echo esc_attr($uid); ?> .acc-inner {
          padding: 20px !important;
        }

        /* === Plans Grid === */
        #<?php echo esc_attr($uid); ?> .plans {
          display: grid;
          grid-template-columns: 1fr;
          gap: 16px;
        }
        @media (min-width: 700px) {
          #<?php echo esc_attr($uid); ?> .plans { grid-template-columns: repeat(3, 1fr); }
        }
        #<?php echo esc_attr($uid); ?> .plan {
          border-radius: 10px;
          background: #fff;
          border: 2px solid #eee;
          padding: 20px;
          position: relative;
          display: flex;
          flex-direction: column;
          cursor: pointer;
          transition: all .2s ease;
        }
        #<?php echo esc_attr($uid); ?> .plan:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
        #<?php echo esc_attr($uid); ?> .plan[data-selected="1"] {
          border-color: var(--brand-red) !important;
          box-shadow: 0 0 0 1px rgba(200,75,49,.2), 0 4px 12px rgba(0,0,0,.08) !important;
        }
        #<?php echo esc_attr($uid); ?> .plan .picon {
          width: 48px; height: 48px;
          border-radius: 50%;
          display: flex; align-items: center; justify-content: center;
          margin-bottom: 12px;
        }
        #<?php echo esc_attr($uid); ?> .plan.bronze .picon { background: linear-gradient(135deg, #b55a1a, #92400e); }
        #<?php echo esc_attr($uid); ?> .plan.silver .picon { background: linear-gradient(135deg, #94a3b8, #64748b); }
        #<?php echo esc_attr($uid); ?> .plan.gold .picon { background: linear-gradient(135deg, #eab308, #d97706); }
        #<?php echo esc_attr($uid); ?> .plan .pname {
          font-weight: 700;
          font-size: 20px;
        }
        #<?php echo esc_attr($uid); ?> .plan.bronze .pname { color: var(--bronze); }
        #<?php echo esc_attr($uid); ?> .plan.silver .pname { color: var(--silver); }
        #<?php echo esc_attr($uid); ?> .plan.gold .pname { color: var(--gold); }
        #<?php echo esc_attr($uid); ?> .price {
          font-weight: 700;
          font-size: 24px;
          display: flex;
          align-items: baseline;
          gap: 4px;
          margin-top: 4px;
          margin-bottom: 16px;
        }
        #<?php echo esc_attr($uid); ?> .per {
          font-size: 14px;
          font-weight: 400;
          color: var(--muted);
        }
        #<?php echo esc_attr($uid); ?> .ticks {
          list-style: none;
          padding: 0;
          margin: 0 0 16px;
          display: flex;
          flex-direction: column;
          gap: 8px;
          font-size: 14px;
          color: rgba(0,0,0,.7);
          flex: 1;
        }
        #<?php echo esc_attr($uid); ?> .tick {
          display: flex; align-items: flex-start; gap: 8px;
          line-height: 1.4;
        }
        #<?php echo esc_attr($uid); ?> .check {
          width: 16px; height: 16px;
          color: var(--brand-red);
          flex: 0 0 auto;
          margin-top: 2px;
        }
        #<?php echo esc_attr($uid); ?> .choose {
          width: 100%;
          padding: 10px 14px;
          border-radius: 8px;
          border: 0;
          background: #f5f5f4;
          color: #44403c;
          font-weight: 600;
          font-size: 14px;
          cursor: pointer;
          font-family: inherit;
          transition: all .15s ease;
        }
        #<?php echo esc_attr($uid); ?> .choose:hover { background: var(--brand-red); color: #fff; }
        #<?php echo esc_attr($uid); ?> .plan[data-selected="1"] .choose {
          background: var(--brand-red) !important;
          color: #fff !important;
        }
        #<?php echo esc_attr($uid); ?> .badge {
          position: absolute;
          top: -12px;
          left: 50%;
          transform: translateX(-50%);
          background: var(--brand-red);
          color: #fff;
          font-weight: 600;
          font-size: 12px;
          padding: 4px 12px;
          border-radius: 999px;
          white-space: nowrap;
        }
        /* Confirm bar that appears when plan is selected */
        #<?php echo esc_attr($uid); ?> .confirm-bar {
          margin-top: 20px;
          background: #f5f5f4;
          border-radius: 10px;
          padding: 16px 20px;
          display: none;
          align-items: center;
          justify-content: space-between;
          gap: 12px;
          flex-wrap: wrap;
        }
        #<?php echo esc_attr($uid); ?> .confirm-bar.show { display: flex; }
        #<?php echo esc_attr($uid); ?> .confirm-bar p {
          font-size: 14px;
          color: var(--text);
        }
        #<?php echo esc_attr($uid); ?> .confirm-bar strong { font-weight: 700; }

        /* Current plan card styling */
        #<?php echo esc_attr($uid); ?> .plan.plan-current {
          opacity: .65; pointer-events: none; position: relative;
        }
        #<?php echo esc_attr($uid); ?> .plan.plan-current .choose {
          background: #e5e5e5 !important; color: #999 !important; cursor: default;
        }

        /* === Inline Checkout Popup === */
        #<?php echo esc_attr($uid); ?> .ihd-checkout-overlay {
          position: fixed; inset: 0; z-index: 999999;
          background: rgba(0,0,0,.55); backdrop-filter: blur(2px);
          display: flex; align-items: center; justify-content: center;
          padding: 16px;
        }
        #<?php echo esc_attr($uid); ?> .ihd-checkout-popup {
          background: #fff; border-radius: 16px; width: min(680px, 100%);
          max-height: 92vh; display: flex; flex-direction: column;
          box-shadow: 0 20px 60px rgba(0,0,0,.25);
          overflow: hidden;
        }
        #<?php echo esc_attr($uid); ?> .ihd-checkout-popup-header {
          padding: 18px 20px; border-bottom: 1px solid #ebe0db;
          display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
          position: relative;
        }
        #<?php echo esc_attr($uid); ?> .ihd-checkout-popup-title {
          display: flex; align-items: center; gap: 8px;
          font-weight: 700; font-size: 16px; color: var(--text);
        }
        #<?php echo esc_attr($uid); ?> .ihd-checkout-popup-title svg { color: var(--brand-red); }
        #<?php echo esc_attr($uid); ?> .ihd-checkout-popup-summary {
          font-size: 13px; color: var(--muted); margin-left: auto; margin-right: 32px;
        }
        #<?php echo esc_attr($uid); ?> .ihd-checkout-popup-close {
          position: absolute; top: 14px; right: 14px;
          width: 32px; height: 32px; border-radius: 50%;
          border: 0; background: #f5f5f4; color: #44403c;
          font-size: 20px; line-height: 1; cursor: pointer;
          display: flex; align-items: center; justify-content: center;
          transition: background .15s;
        }
        #<?php echo esc_attr($uid); ?> .ihd-checkout-popup-close:hover { background: #e7e5e4; }
        #<?php echo esc_attr($uid); ?> .ihd-checkout-popup-body {
          flex: 1; overflow: hidden; position: relative; min-height: 300px; padding: 25px;
        }
        #<?php echo esc_attr($uid); ?> .ihd-checkout-iframe {
          width: 100%; height: 100%; min-height: 500px; border: 0;
        }
        #<?php echo esc_attr($uid); ?> .ihd-checkout-loading {
          position: absolute; inset: 0; display: flex; flex-direction: column;
          align-items: center; justify-content: center; gap: 12px;
          color: var(--muted); font-size: 14px; background: #fff;
        }
        #<?php echo esc_attr($uid); ?> .ihd-spinner {
          width: 36px; height: 36px; border: 3px solid #ebe0db;
          border-top-color: var(--brand-red); border-radius: 50%;
          animation: ihd-spin .7s linear infinite;
        }
        @keyframes ihd-spin { to { transform: rotate(360deg); } }

        /* Switch success message (replaces plan cards after completion) */
        #<?php echo esc_attr($uid); ?> .switch-success {
          text-align: center; padding: 40px 20px;
        }
        #<?php echo esc_attr($uid); ?> .switch-success .success-icon {
          width: 56px; height: 56px; border-radius: 50%;
          background: #dcfce7; color: #16a34a; margin: 0 auto 16px;
          display: flex; align-items: center; justify-content: center;
        }
        #<?php echo esc_attr($uid); ?> .switch-success h3 {
          font-size: 20px; font-weight: 700; margin-bottom: 8px;
        }
        #<?php echo esc_attr($uid); ?> .switch-success p { color: var(--muted); font-size: 14px; }

        /* === Pause Section === */
        #<?php echo esc_attr($uid); ?> .bodytext {
          font-size: 14px;
          color: var(--muted);
          line-height: 1.6;
          margin-bottom: 16px;
        }
        #<?php echo esc_attr($uid); ?> .label {
          font-weight: 500;
          font-size: 14px;
          margin: 0 0 12px;
          color: var(--text);
        }
        #<?php echo esc_attr($uid); ?> .pause-grid {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 12px;
          margin-bottom: 16px;
        }
        @media (min-width: 700px) {
          #<?php echo esc_attr($uid); ?> .pause-grid { grid-template-columns: repeat(4, 1fr); }
        }
        #<?php echo esc_attr($uid); ?> .pill {
          border: 2px solid var(--border);
          background: #fff;
          padding: 12px;
          border-radius: 10px;
          font-weight: 500;
          font-size: 14px;
          color: var(--text);
          cursor: pointer;
          text-align: center;
          font-family: inherit;
          transition: all .15s ease;
        }
        #<?php echo esc_attr($uid); ?> .pill:hover { border-color: rgba(200,75,49,.4); }
        #<?php echo esc_attr($uid); ?> .pill[data-active="1"] {
          border-color: var(--brand-red);
          background: rgba(200,75,49,.03);
          color: var(--brand-red);
        }
        /* Pause info bar */
        #<?php echo esc_attr($uid); ?> .pause-info {
          background: #f5f5f4;
          border-radius: 10px;
          padding: 16px;
          margin-bottom: 16px;
          font-size: 14px;
          color: var(--text);
          line-height: 1.6;
          display: none;
        }
        #<?php echo esc_attr($uid); ?> .pause-info.show { display: block; }
        /* Pause success */
        #<?php echo esc_attr($uid); ?> .pause-success {
          display: none;
          background: #f5f5f4;
          border-radius: 10px;
          padding: 32px 20px;
          text-align: center;
        }
        #<?php echo esc_attr($uid); ?> .pause-success.show { display: block; }
        #<?php echo esc_attr($uid); ?> .pause-success .sico {
          width: 40px; height: 40px;
          color: var(--brand-red);
          margin: 0 auto 12px;
        }
        #<?php echo esc_attr($uid); ?> .pause-success h3 {
          font-size: 18px; font-weight: 600; margin-bottom: 4px;
        }
        #<?php echo esc_attr($uid); ?> .pause-success p {
          font-size: 14px; color: var(--muted);
        }
        #<?php echo esc_attr($uid); ?> .pause-actions {
          display: flex;
          align-items: center;
          gap: 16px;
          flex-wrap: wrap;
        }

        /* === Buttons === */
        #<?php echo esc_attr($uid); ?> .btn {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          padding: 10px 24px;
          border-radius: 8px;
          border: 0;
          cursor: pointer;
          font-weight: 600;
          font-size: 14px;
          font-family: inherit;
          transition: all .15s ease;
        }
        #<?php echo esc_attr($uid); ?> .btn-primary {
          background: var(--brand-red);
          color: #fff;
        }
        #<?php echo esc_attr($uid); ?> .btn-primary:hover { background: var(--brand-red-2); }
        #<?php echo esc_attr($uid); ?> .btn-primary:disabled { opacity: .5; cursor: not-allowed; }
        #<?php echo esc_attr($uid); ?> .link-cancel {
          font-size: 14px;
          color: var(--muted);
          text-decoration: underline;
          text-underline-offset: 2px;
          cursor: pointer;
          white-space: nowrap;
          background: none;
          border: 0;
          font-family: inherit;
          padding: 0;
          transition: color .15s;
        }
        #<?php echo esc_attr($uid); ?> .link-cancel:hover { color: var(--text); }

        /* === Cancel Section — multi-step inline === */
        #<?php echo esc_attr($uid); ?> .cancel-step { display: none; }
        #<?php echo esc_attr($uid); ?> .cancel-step.active { display: block; }
        #<?php echo esc_attr($uid); ?> .sadbox {
          background: #f5f5f4;
          border-radius: 10px;
          padding: 32px 24px;
          text-align: center;
          margin-bottom: 20px;
        }
        #<?php echo esc_attr($uid); ?> .sadbox .heart {
          width: 40px; height: 40px;
          color: var(--brand-red);
          margin: 0 auto 12px;
        }
        #<?php echo esc_attr($uid); ?> .sadbox .m1 {
          font-weight: 500;
          font-size: 16px;
          color: var(--text);
          margin-bottom: 4px;
          line-height: 1.5;
        }
        #<?php echo esc_attr($uid); ?> .sadbox .m2 {
          font-size: 14px;
          color: var(--muted);
          line-height: 1.5;
        }
        #<?php echo esc_attr($uid); ?> .warn {
          margin-bottom: 20px;
          background: var(--warn-bg);
          border: 1px solid var(--warn-border);
          border-radius: 10px;
          padding: 16px;
          display: flex;
          gap: 12px;
          align-items: flex-start;
          font-size: 14px;
          line-height: 1.5;
        }
        #<?php echo esc_attr($uid); ?> .warn .wico {
          width: 20px; height: 20px;
          margin-top: 1px;
          flex: 0 0 auto;
          color: #d97706;
        }
        #<?php echo esc_attr($uid); ?> .warn strong {
          display: block;
          margin-bottom: 8px;
          color: #92400e;
          font-size: 14px;
          font-weight: 500;
        }
        #<?php echo esc_attr($uid); ?> .warn-list {
          color: #b45309;
          font-size: 14px;
          line-height: 1.8;
        }
        #<?php echo esc_attr($uid); ?> .cancel-btns {
          display: flex;
          gap: 12px;
          flex-wrap: wrap;
        }
        #<?php echo esc_attr($uid); ?> .btn-cancel-outline {
          background: #fff;
          border: 1px solid var(--destructive);
          color: var(--destructive);
          transition: all .15s;
        }
        #<?php echo esc_attr($uid); ?> .btn-cancel-outline:hover {
          background: var(--destructive);
          color: #fff;
        }
        /* Reason step */
        #<?php echo esc_attr($uid); ?> .reason-label {
          font-size: 14px;
          font-weight: 500;
          color: var(--text);
          margin-bottom: 12px;
        }
        #<?php echo esc_attr($uid); ?> .reason-list {
          display: flex;
          flex-direction: column;
          gap: 8px;
          margin-bottom: 20px;
        }
        #<?php echo esc_attr($uid); ?> .reason-opt {
          display: flex;
          align-items: center;
          gap: 12px;
          border: 2px solid var(--border);
          border-radius: 10px;
          padding: 12px 16px;
          cursor: pointer;
          transition: all .15s;
          font-size: 14px;
          color: var(--text);
        }
        #<?php echo esc_attr($uid); ?> .reason-opt:hover { border-color: rgba(200,75,49,.4); }
        #<?php echo esc_attr($uid); ?> .reason-opt[data-active="1"] {
          border-color: var(--brand-red);
          background: rgba(200,75,49,.03);
        }
        #<?php echo esc_attr($uid); ?> .reason-opt input { display: none; }
        #<?php echo esc_attr($uid); ?> .rdot {
          width: 16px; height: 16px;
          border-radius: 50%;
          border: 2px solid var(--muted);
          flex: 0 0 auto;
          position: relative;
          transition: all .15s;
        }
        #<?php echo esc_attr($uid); ?> .reason-opt[data-active="1"] .rdot {
          border-color: var(--brand-red);
          background: var(--brand-red);
          box-shadow: inset 0 0 0 3px #fff;
        }
        #<?php echo esc_attr($uid); ?> .btn-destructive {
          background: var(--destructive);
          color: #fff;
        }
        #<?php echo esc_attr($uid); ?> .btn-destructive:hover { background: #b91c1c; }
        #<?php echo esc_attr($uid); ?> .btn-destructive:disabled { opacity: .5; cursor: not-allowed; }
        /* Done step */
        #<?php echo esc_attr($uid); ?> .cancel-done {
          background: #f5f5f4;
          border-radius: 10px;
          padding: 32px 20px;
          text-align: center;
        }
        #<?php echo esc_attr($uid); ?> .cancel-done .dico {
          width: 40px; height: 40px;
          color: var(--brand-red);
          margin: 0 auto 12px;
        }
        #<?php echo esc_attr($uid); ?> .cancel-done h3 {
          font-size: 18px; font-weight: 600; margin-bottom: 4px;
        }
        #<?php echo esc_attr($uid); ?> .cancel-done p {
          font-size: 14px; color: var(--muted); margin-bottom: 4px; line-height: 1.5;
        }
        #<?php echo esc_attr($uid); ?> .cancel-done .thanks {
          color: var(--brand-red); font-weight: 500;
        }

        /* === Testimonials / Carousel === */
        #<?php echo esc_attr($uid); ?> .h3 {
          font-weight: 700;
          font-size: 18px;
          margin: 40px 0 4px;
        }
        #<?php echo esc_attr($uid); ?> .h3sub {
          margin: 0 0 20px;
          font-size: 14px;
          color: var(--muted);
        }
        #<?php echo esc_attr($uid); ?> .carousel {
          border-radius: 12px;
          overflow: hidden;
          position: relative;
        }
        #<?php echo esc_attr($uid); ?> .slides-track {
          display: flex;
          transition: transform .5s ease-in-out;
        }
        #<?php echo esc_attr($uid); ?> .slide {
          width: 100%;
          flex-shrink: 0;
          position: relative;
          aspect-ratio: 16/9;
          min-height: 280px;
        }
        #<?php echo esc_attr($uid); ?> .slide img {
          position: absolute;
          width: 100%; height: 100%;
          object-fit: cover;
          object-position: center;
        }
        #<?php echo esc_attr($uid); ?> .overlay {
          position: absolute;
          inset: 0;
          background: linear-gradient(to top, rgba(0,0,0,.85) 0%, rgba(0,0,0,.5) 50%, rgba(0,0,0,.2) 100%);
          display: flex;
          flex-direction: column;
          justify-content: flex-end;
          padding: 24px;
          color: #fff;
          z-index: 2;
          pointer-events: none;
        }
        @media (min-width: 700px) {
          #<?php echo esc_attr($uid); ?> .overlay { padding: 32px; }
        }
        #<?php echo esc_attr($uid); ?> .slide-link {
          position: absolute;
          inset: 0;
          z-index: 1;
          text-decoration: none;
        }
        #<?php echo esc_attr($uid); ?> .overlay .qmark {
          width: 32px; height: 32px;
          color: rgba(255,255,255,.6);
          margin-bottom: 12px;
        }
        #<?php echo esc_attr($uid); ?> .quote {
          font-weight: 600;
          font-size: 18px;
          line-height: 1.5;
          margin-bottom: 16px;
        }
        @media (min-width: 700px) {
          #<?php echo esc_attr($uid); ?> .quote { font-size: 20px; }
        }
        #<?php echo esc_attr($uid); ?> .who-sep {
          border-top: 1px solid rgba(255,255,255,.3);
          padding-top: 12px;
        }
        #<?php echo esc_attr($uid); ?> .who2 {
          font-weight: 700;
          font-size: 16px;
        }
        #<?php echo esc_attr($uid); ?> .org2 {
          font-size: 14px;
          color: rgba(255,255,255,.7);
          font-weight: 500;
          margin-top: 2px;
        }
        #<?php echo esc_attr($uid); ?> .navbtn {
          position: absolute !important;
          top: 50% !important;
          transform: translateY(-50%) !important;
          width: 40px !important; height: 40px !important;
          min-width: 40px !important; max-width: 40px !important;
          min-height: 40px !important; max-height: 40px !important;
          border-radius: 50% !important;
          border: 0 !important;
          background: rgba(0,0,0,.4) !important;
          backdrop-filter: blur(4px) !important;
          -webkit-backdrop-filter: blur(4px) !important;
          color: #fff !important;
          display: flex !important; align-items: center !important; justify-content: center !important;
          cursor: pointer !important;
          transition: background .15s !important;
          z-index: 3 !important;
          padding: 0 !important;
          margin: 0 !important;
          box-shadow: none !important;
          outline: none !important;
        }
        #<?php echo esc_attr($uid); ?> .navbtn:hover { background: rgba(0,0,0,.6) !important; }
        #<?php echo esc_attr($uid); ?> .navbtn.prev { left: 8px !important; }
        #<?php echo esc_attr($uid); ?> .navbtn.next { right: 8px !important; }
        #<?php echo esc_attr($uid); ?> .dots {
          display: flex !important;
          justify-content: center !important;
          gap: 8px !important;
          padding: 16px 0 0 !important;
          list-style: none !important;
        }
        #<?php echo esc_attr($uid); ?> .dot {
          height: 10px !important;
          border-radius: 999px !important;
          border: 0 !important;
          cursor: pointer !important;
          transition: all .3s ease !important;
          width: 10px !important;
          min-width: 10px !important;
          max-width: 10px !important;
          background: rgba(0,0,0,.15) !important;
          padding: 0 !important;
          margin: 0 !important;
          outline: none !important;
          box-shadow: none !important;
        }
        #<?php echo esc_attr($uid); ?> .dot:hover { background: rgba(0,0,0,.35) !important; }
        #<?php echo esc_attr($uid); ?> .dot[data-active="1"] {
          background: var(--brand-red) !important;
          width: 28px !important;
          min-width: 28px !important;
          max-width: 28px !important;
        }

        /* === Impact Bar === */
        #<?php echo esc_attr($uid); ?> .impact {
          margin-top: 32px;
          background: rgba(200,75,49,.04);
          border: 1px solid rgba(200,75,49,.1);
          border-radius: 12px;
          padding: 24px 20px;
        }
        #<?php echo esc_attr($uid); ?> .impact-title {
          text-align: center;
          font-weight: 700;
          font-size: 18px;
          margin-bottom: 16px;
          color: var(--text);
        }
        #<?php echo esc_attr($uid); ?> .impact-grid {
          display: grid;
          grid-template-columns: 1fr;
          gap: 16px;
          text-align: center;
        }
        @media (min-width: 700px) {
          #<?php echo esc_attr($uid); ?> .impact-grid { grid-template-columns: repeat(3, 1fr); }
        }
        #<?php echo esc_attr($uid); ?> .impact-num {
          font-weight: 700;
          font-size: 30px;
          color: var(--brand-red);
        }
        #<?php echo esc_attr($uid); ?> .impact-lbl {
          font-size: 14px;
          color: var(--muted);
          margin-top: 2px;
        }

        /* Fullscreen loading overlay */
        .ihd-vip-overlay {
          position: fixed; inset: 0; z-index: 99999;
          display: flex; align-items: center; justify-content: center;
          background: rgba(255,255,255,.7);
        }
        .ihd-vip-overlay .ihd-spinner {
          width: 40px; height: 40px;
          border: 4px solid #e8e0db;
          border-top-color: var(--brand-red, #c84b31);
          border-radius: 50%;
          animation: ihd-spin .6s linear infinite;
        }
        @keyframes ihd-spin { to { transform: rotate(360deg); } }
      </style>

      <div class="wrap">

        <!-- Header -->
        <div class="topbar">
          <span class="spark" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"/>
              <path d="M20 2v4"/><path d="M22 4h-4"/><circle cx="4" cy="20" r="2"/>
            </svg>
          </span>
          Hero VIP Club
        </div>
        <h1 class="h1">Manage Your Subscription</h1>
        <p class="lead">
          Thank you for being a Hero VIP member! Your membership helps provide meals, medical care, and rescue flights for dogs in need across the country.
        </p>

        <!-- Current plan bar -->
        <div class="currentbar">
          <div class="icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"/>
            </svg>
          </div>
          <div>
            <p class="t1">Current Plan: <?php echo $current_plan; ?></p>
            <div class="t2"><?php echo $current_price; ?>/<?php echo strtolower($current_interval); ?> · Next billing date: <?php echo $next_bill; ?></div>
          </div>
        </div>

        <!-- ═══ Accordion: Upgrade/Downgrade ═══ -->
        <section class="section" data-acc data-acc-id="upgrade-downgrade" data-open="0">
          <button class="acc-h" type="button" data-acc-toggle aria-expanded="false">
            <div class="acc-left">
              <div class="acc-ico ico-upgrade" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M11.562 3.266a.5.5 0 0 1 .876 0L15.39 8.87a1 1 0 0 0 1.516.294L21.183 5.5a.5.5 0 0 1 .798.519l-2.834 10.246a1 1 0 0 1-.956.734H5.81a1 1 0 0 1-.957-.734L2.02 6.02a.5.5 0 0 1 .798-.519l4.276 3.664a1 1 0 0 0 1.516-.294z"/>
                  <path d="M5 21h14"/>
                </svg>
              </div>
              <div>
                <h2 class="acc-title">Upgrade or Downgrade Your Membership</h2>
                <div class="acc-sub">Choose the plan that works best for you</div>
              </div>
            </div>
            <svg class="chev" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>

          <div class="acc-panel">
            <div class="acc-inner">
              <?php
              /*
               * ── Tier benefit descriptions ──
               * Keyed by the membership-level attribute slug.
               * Falls back gracefully if a slug has no entry.
               */
              $tier_benefits = array(
                  'hero'       => array( '10% off all purchases', 'Free shipping on orders $25+', '5 meals donated monthly' ),
                  'super-hero' => array( '15% off all purchases', 'Free shipping on all orders', '15 meals donated monthly', 'Early access to new products' ),
                  'saint'      => array( '25% off all purchases', 'Free shipping on all orders', '50 meals donated monthly', 'Early access to new products', 'Exclusive VIP-only deals', 'Priority customer support' ),
              );
              $tier_icons = array(
                  'hero'       => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#fff" stroke-width="2" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>',
                  'super-hero' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#fff" stroke-width="2" stroke-linejoin="round"><path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/></svg>',
                  'saint'      => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#fff" stroke-width="2" stroke-linejoin="round"><path d="M11.562 3.266a.5.5 0 0 1 .876 0L15.39 8.87a1 1 0 0 0 1.516.294L21.183 5.5a.5.5 0 0 1 .798.519l-2.834 10.246a1 1 0 0 1-.956.734H5.81a1 1 0 0 1-.957-.734L2.02 6.02a.5.5 0 0 1 .798-.519l4.276 3.664a1 1 0 0 0 1.516-.294z"/><path d="M5 21h14"/></svg>',
              );
              /* CSS class mapping for plan card colour theming */
              $tier_css = array( 'hero' => 'bronze', 'super-hero' => 'silver', 'saint' => 'gold' );
              /* Default icon for tiers not in the icon map */
              $default_icon = '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#fff" stroke-width="2" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>';
              $check_svg = '<svg class="check" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

              if ( ! empty( $sibling_variations ) ) : ?>
              <div class="plans" data-plans>
                <?php foreach ( $sibling_variations as $idx => $sib ) :
                    $slug        = $sib['slug'];
                    $css_class   = $tier_css[ $slug ] ?? 'bronze';
                    $benefits    = $tier_benefits[ $slug ] ?? array();
                    $icon        = $tier_icons[ $slug ] ?? $default_icon;
                    $is_current  = $sib['is_current'];
                    $period_label = ( 'year' === $sib['period'] ) ? 'Annual' : 'Monthly';
                ?>
                <div class="plan <?php echo esc_attr( $css_class ); ?><?php echo $is_current ? ' plan-current' : ''; ?>"
                     data-plan="<?php echo esc_attr( $sib['label'] ); ?>"
                     data-variation-id="<?php echo esc_attr( $sib['variation_id'] ); ?>"
                     data-plan-price="$<?php echo esc_attr( $sib['price'] ); ?> / <?php echo esc_attr( $period_label ); ?>"
                     data-selected="0">
                  <?php if ( $is_current ) : ?>
                    <div class="badge" style="background:var(--brand-red);">Current Plan</div>
                  <?php elseif ( count( $sibling_variations ) > 1 && $idx === (int) floor( count( $sibling_variations ) / 2 ) && ! $sibling_variations[ $idx ]['is_current'] ) : ?>
                    <div class="badge">Most Popular</div>
                  <?php endif; ?>
                  <div class="picon" aria-hidden="true"><?php echo $icon; ?></div>
                  <div class="pname"><?php echo esc_html( $sib['label'] ); ?></div>
                  <div class="price">$<?php echo esc_html( number_format( $sib['price'], 2 ) ); ?> <span class="per">/ <?php echo esc_html( $period_label ); ?></span></div>
                  <?php if ( ! empty( $benefits ) ) : ?>
                  <ul class="ticks">
                    <?php foreach ( $benefits as $b ) : ?>
                      <li class="tick"><?php echo $check_svg; ?><?php echo esc_html( $b ); ?></li>
                    <?php endforeach; ?>
                  </ul>
                  <?php endif; ?>
                  <?php if ( $is_current ) : ?>
                    <button class="choose" type="button" disabled>Current Plan</button>
                  <?php else : ?>
                    <button class="choose" type="button">Choose <?php echo esc_html( $sib['label'] ); ?></button>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
              <?php else : ?>
              <div class="plans" data-plans>
                <p style="padding:20px; text-align:center; color:var(--muted);">No switchable plans available. Please contact support.</p>
              </div>
              <?php endif; ?>

              <!-- Confirm bar (appears when a plan is selected) -->
              <div class="confirm-bar" data-confirm-bar>
                <p>Ready to switch to <strong data-confirm-name>—</strong>?</p>
                <button type="button" class="btn btn-primary" data-confirm-change>Confirm &amp; Checkout</button>
              </div>
            </div>
          </div>

          <!-- ═══ Inline Checkout Popup ═══ -->
          <div class="ihd-checkout-overlay" data-checkout-overlay style="display:none;">
            <div class="ihd-checkout-popup">
              <div class="ihd-checkout-popup-header">
                <div class="ihd-checkout-popup-title">
                  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11.562 3.266a.5.5 0 0 1 .876 0L15.39 8.87a1 1 0 0 0 1.516.294L21.183 5.5a.5.5 0 0 1 .798.519l-2.834 10.246a1 1 0 0 1-.956.734H5.81a1 1 0 0 1-.957-.734L2.02 6.02a.5.5 0 0 1 .798-.519l4.276 3.664a1 1 0 0 0 1.516-.294z"/><path d="M5 21h14"/></svg>
                  <span>Complete Your Plan Switch</span>
                </div>
                <div class="ihd-checkout-popup-summary" data-checkout-summary></div>
                <button type="button" class="ihd-checkout-popup-close" data-checkout-close aria-label="Close">&times;</button>
              </div>
              <div class="ihd-checkout-popup-body">
                <div class="ihd-checkout-loading" data-checkout-loading>
                  <div class="ihd-spinner"></div>
                  <p>Loading checkout&hellip;</p>
                </div>
                <iframe data-checkout-iframe class="ihd-checkout-iframe" style="display:none;" title="Checkout"></iframe>
              </div>
            </div>
          </div>
        </section>

        <!-- ═══ Accordion: Pause ═══ -->
        <section class="section" data-acc data-acc-id="pause" data-open="0">
          <button class="acc-h" type="button" data-acc-toggle aria-expanded="false">
            <div class="acc-left">
              <div class="acc-ico ico-pause" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/><line x1="10" x2="10" y1="15" y2="9"/><line x1="14" x2="14" y1="15" y2="9"/>
                </svg>
              </div>
              <div>
                <h2 class="acc-title">Pause My Membership</h2>
                <div class="acc-sub">Take a break</div>
              </div>
            </div>
            <svg class="chev" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>

          <div class="acc-panel">
            <div class="acc-inner">
              <!-- Pause form -->
              <div data-pause-form>
                <p class="bodytext">
                  Select how long you'd like to pause your membership. Your benefits will resume automatically after the pause period ends.
                </p>
                <div class="label">Pause for:</div>
                <div class="pause-grid" data-pause-grid>
                  <button type="button" class="pill" data-pause="30" data-active="0">30 Days</button>
                  <button type="button" class="pill" data-pause="60" data-active="0">60 Days</button>
                  <button type="button" class="pill" data-pause="90" data-active="0">90 Days</button>
                  <button type="button" class="pill" data-pause="180" data-active="0">180 Days</button>
                </div>
                <div class="pause-info" data-pause-info>
                  Your membership will be paused for <strong data-pause-days-info>30</strong> days. You will not be charged during this time, and your benefits will resume automatically.
                </div>
                <div class="pause-actions">
                  <button type="button" class="btn btn-primary" data-pause-confirm disabled>Confirm Pause</button>
                  <button type="button" class="link-cancel" data-open-cancel>I'd rather permanently cancel</button>
                </div>
              </div>
              <!-- Pause success -->
              <div class="pause-success" data-pause-success>
                <svg class="sico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 6v6l4 2"/><circle cx="12" cy="12" r="10"/>
                </svg>
                <h3>Membership Paused</h3>
                <p>Your membership has been paused for <span data-pause-days-done>30</span> days. We'll be here when you're ready to come back!</p>
              </div>
            </div>
          </div>
        </section>

        <!-- ═══ Accordion: Cancel — inline multi-step ═══ -->
        <section class="section" data-acc data-acc-id="cancel" data-open="0" data-cancel-section>
          <button class="acc-h" type="button" data-acc-toggle aria-expanded="false">
            <div class="acc-left">
              <div class="acc-ico ico-cancel" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>
                </svg>
              </div>
              <div>
                <h2 class="acc-title">Cancel My Membership</h2>
                <div class="acc-sub">We'd hate to see you go</div>
              </div>
            </div>
            <svg class="chev" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>

          <div class="acc-panel">
            <div class="acc-inner">

              <!-- Step 1: Confirm -->
              <div class="cancel-step active" data-cancel-step="confirm">
                <div class="sadbox">
                  <svg class="heart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"/>
                  </svg>
                  <p class="m1">We're sad to see you go, but we appreciate your partnership and the lives you have helped save!</p>
                  <p class="m2">Your support has made a real difference for rescue dogs across the country.</p>
                </div>

                <div class="warn">
                  <svg class="wico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/>
                    <path d="M12 9v4"/><path d="M12 17h.01"/>
                  </svg>
                  <div>
                    <strong>Before you cancel, consider:</strong>
                    <div class="warn-list">
                      You'll lose access to exclusive VIP discounts<br>
                      Your monthly meal donations will stop<br>
                      You can pause your membership instead
                    </div>
                  </div>
                </div>

                <div class="cancel-btns">
                  <button type="button" class="btn btn-cancel-outline" data-goto-step="reason">I still want to cancel</button>
                  <button type="button" class="btn btn-primary" data-cancel-keep>Keep My Membership</button>
                </div>
              </div>

              <!-- Step 2: Reason -->
              <div class="cancel-step" data-cancel-step="reason">
                <p class="reason-label">Could you tell us why you're leaving? This helps us improve.</p>
                <div class="reason-list" data-reason-list>
                  <label class="reason-opt" data-reason="Too expensive" data-active="0">
                    <input type="radio" name="<?php echo esc_attr($uid); ?>_reason" value="Too expensive">
                    <span class="rdot"></span>
                    Too expensive
                  </label>
                  <label class="reason-opt" data-reason="Not using the benefits enough" data-active="0">
                    <input type="radio" name="<?php echo esc_attr($uid); ?>_reason" value="Not using the benefits enough">
                    <span class="rdot"></span>
                    Not using the benefits enough
                  </label>
                  <label class="reason-opt" data-reason="Found an alternative" data-active="0">
                    <input type="radio" name="<?php echo esc_attr($uid); ?>_reason" value="Found an alternative">
                    <span class="rdot"></span>
                    Found an alternative
                  </label>
                  <label class="reason-opt" data-reason="Just need a break" data-active="0">
                    <input type="radio" name="<?php echo esc_attr($uid); ?>_reason" value="Just need a break">
                    <span class="rdot"></span>
                    Just need a break
                  </label>
                  <label class="reason-opt" data-reason="Other" data-active="0">
                    <input type="radio" name="<?php echo esc_attr($uid); ?>_reason" value="Other">
                    <span class="rdot"></span>
                    Other
                  </label>
                </div>
                <button type="button" class="btn btn-destructive" data-do-cancel disabled>Confirm Cancellation</button>
              </div>

              <!-- Step 3: Done -->
              <div class="cancel-step" data-cancel-step="done">
                <div class="cancel-done">
                  <svg class="dico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"/>
                  </svg>
                  <h3>Membership Cancelled</h3>
                  <p>Your membership has been cancelled. You'll retain access to your benefits until the end of your current billing period.</p>
                  <p class="thanks">Thank you for every meal you helped provide. The dogs will always remember your kindness.</p>
                </div>
              </div>

            </div>
          </div>
        </section>

        <!-- Blog Post Slider -->
        <?php if ( ! empty( $slider_posts ) ) : $placeholder = self::get_placeholder_image(); ?>
        <div class="h3">See the Lives You Help Save</div>
        <div class="h3sub">Real stories from our rescue partners across the country.</div>

        <div class="carousel" data-carousel>
          <div class="slides-track" data-slides-track>
            <?php foreach ( $slider_posts as $i => $slide_post ) :
              $img = ! empty( $slide_post['image'] ) ? esc_url( $slide_post['image'] ) : $placeholder;
            ?>
            <div class="slide" data-slide="<?php echo (int) $i; ?>">
              <a href="<?php echo esc_url( $slide_post['permalink'] ); ?>" class="slide-link" aria-label="<?php echo esc_attr( $slide_post['title'] ); ?>"></a>
              <img src="<?php echo $img; ?>" alt="<?php echo esc_attr( $slide_post['title'] ); ?>">
              <div class="overlay">
                <svg class="qmark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M16 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z"/>
                  <path d="M5 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z"/>
                </svg>
                <p class="quote"><?php echo esc_html( $slide_post['excerpt'] ); ?></p>
                <div class="who-sep">
                  <p class="who2"><?php echo esc_html( $slide_post['title'] ); ?></p>
                  <div class="org2">By <?php echo esc_html( $slide_post['author'] ); ?> · <?php echo esc_html( $slide_post['date'] ); ?></div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <?php if ( count( $slider_posts ) > 1 ) : ?>
          <button class="navbtn prev" type="button" aria-label="Previous" data-prev>
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <button class="navbtn next" type="button" aria-label="Next" data-next>
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <?php endif; ?>
        </div>

        <?php if ( count( $slider_posts ) > 1 ) : ?>
        <div class="dots" data-dots>
          <?php foreach ( $slider_posts as $i => $slide_post ) : ?>
          <button class="dot" type="button" data-dot="<?php echo (int) $i; ?>" data-active="<?php echo $i === 0 ? '1' : '0'; ?>" aria-label="Slide <?php echo (int) $i + 1; ?>"></button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <div class="impact">
          <div class="impact-title">Your Impact as a Hero VIP Member</div>
          <div class="impact-grid">
            <div>
              <p class="impact-num">2.5M+</p>
              <div class="impact-lbl">Meals Donated</div>
            </div>
            <div>
              <p class="impact-num">15,000+</p>
              <div class="impact-lbl">Dogs Rescued</div>
            </div>
            <div>
              <p class="impact-num">500+</p>
              <div class="impact-lbl">Rescue Partners</div>
            </div>
          </div>
        </div>

      </div><!-- /wrap -->

      <script>
        (function(){
          const root = document.getElementById(<?php echo json_encode($uid); ?>);
          if (!root) return;

          const cfg = {
            ajaxUrl: <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
            nonce:   <?php echo json_encode( wp_create_nonce( 'ihd_vip_nonce' ) ); ?>,
            subId:   <?php echo (int) $subscription_id; ?>,
          };

          /* ── Screen blocker helpers ── */
          function showOverlay() {
            if (window.jQuery && typeof jQuery.fn.block === 'function') {
              jQuery(document.body).block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });
            } else {
              const o = document.createElement('div');
              o.className = 'ihd-vip-overlay';
              o.id = 'ihd-vip-overlay';
              o.innerHTML = '<div class="ihd-spinner"></div>';
              document.body.appendChild(o);
            }
          }
          function hideOverlay() {
            if (window.jQuery && typeof jQuery.fn.unblock === 'function') jQuery(document.body).unblock();
            const o = document.getElementById('ihd-vip-overlay');
            if (o) o.remove();
          }

          /* ── Accordions ── */
          root.querySelectorAll('[data-acc]').forEach(sec => {
            const btn = sec.querySelector('[data-acc-toggle]');
            if (!btn) return;
            btn.setAttribute('aria-expanded', sec.getAttribute('data-open') === '1' ? 'true' : 'false');
            btn.addEventListener('click', () => {
              const isOpen = sec.getAttribute('data-open') === '1';
              sec.setAttribute('data-open', isOpen ? '0' : '1');
              btn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            });
          });

          /* ── Hash → open accordion on load and hash change ── */
          function openAccordionByHash() {
            const hash = window.location.hash.replace('#', '').trim();
            if (!hash) return;
            const target = root.querySelector('[data-acc-id="' + CSS.escape(hash) + '"]');
            if (!target) return;
            target.setAttribute('data-open', '1');
            const btn = target.querySelector('[data-acc-toggle]');
            if (btn) btn.setAttribute('aria-expanded', 'true');
            setTimeout(() => target.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
          }
          openAccordionByHash();
          window.addEventListener('hashchange', openAccordionByHash);

          /* ── Plan Selection & Inline Checkout ── */
          const plansWrap = root.querySelector('[data-plans]');
          const confirmBar = root.querySelector('[data-confirm-bar]');
          const confirmName = root.querySelector('[data-confirm-name]');
          const confirmBtn = root.querySelector('[data-confirm-change]');
          let selectedPlan = null;
          let selectedVariationId = null;
          let lastSwitchOrderId = 0;

          /* Checkout popup elements */
          const checkoutOverlay = root.querySelector('[data-checkout-overlay]');
          const checkoutIframe  = root.querySelector('[data-checkout-iframe]');
          const checkoutLoading = root.querySelector('[data-checkout-loading]');
          const checkoutSummary = root.querySelector('[data-checkout-summary]');
          const checkoutClose   = root.querySelector('[data-checkout-close]');

          if (plansWrap) {
            plansWrap.addEventListener('click', (e) => {
              const card = e.target.closest('[data-plan]');
              if (!card || card.classList.contains('plan-current')) return;
              const name = card.getAttribute('data-plan');
              const varId = card.getAttribute('data-variation-id');
              plansWrap.querySelectorAll('[data-plan]').forEach(p => {
                if (p.classList.contains('plan-current')) return;
                const isSel = p.getAttribute('data-plan') === name;
                p.setAttribute('data-selected', isSel ? '1' : '0');
                const btn = p.querySelector('.choose');
                if (btn) btn.textContent = isSel ? 'Selected' : 'Choose ' + p.getAttribute('data-plan');
              });
              selectedPlan = name;
              selectedVariationId = varId;
              if (confirmBar) {
                confirmBar.classList.add('show');
                if (confirmName) confirmName.textContent = name;
              }
            });
          }

          /* ── Confirm Change → Prepare Cart → Open Checkout Popup ── */
          if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
              if (!selectedVariationId || !cfg.subId) return;
              confirmBtn.disabled = true;
              confirmBtn.textContent = 'Preparing\u2026';

              fetch(cfg.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                  action: 'ihd_vip_prepare_switch',
                  nonce: cfg.nonce,
                  subscription_id: cfg.subId,
                  variation_id: selectedVariationId,
                }),
              })
                .then(r => r.json())
                .then(data => {
                  confirmBtn.disabled = false;
                  confirmBtn.textContent = 'Confirm & Checkout';

                  if (!data.success) {
                    alert(data.data && data.data.message ? data.data.message : 'Could not prepare switch.');
                    return;
                  }

                  /* Show the checkout popup */
                  openCheckoutPopup(data.data);
                })
                .catch(() => {
                  confirmBtn.disabled = false;
                  confirmBtn.textContent = 'Confirm & Checkout';
                  alert('Network error. Please try again.');
                });
            });
          }

          function openCheckoutPopup(switchData) {
            if (!checkoutOverlay || !checkoutIframe) return;

            /* Track the last switch order so polling ignores prior completions */
            lastSwitchOrderId = switchData.last_switch_order_id || 0;

            /* Descriptive heading based on switch direction */
            var dirLabel = 'Switch';
            if (switchData.switch_direction === 'upgrade') dirLabel = 'Upgrade';
            else if (switchData.switch_direction === 'downgrade') dirLabel = 'Downgrade';
            var popupTitle = root.querySelector('.ihd-checkout-popup-title span');
            if (popupTitle) popupTitle.textContent = 'Complete Your Plan ' + dirLabel;

            /* Summary line */
            if (checkoutSummary) {
              checkoutSummary.innerHTML = dirLabel + ' to <strong>' + switchData.plan_label + '</strong> &mdash; ' + switchData.price + '/' + switchData.period;
            }

            /* Reset state */
            checkoutIframe.style.display = 'none';
            if (checkoutLoading) checkoutLoading.style.display = 'flex';
            checkoutOverlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';

            /* Load checkout into iframe via the stripped endpoint */
            var iframeSrc = cfg.ajaxUrl + '?action=ihd_vip_checkout_page&t=' + Date.now();
            checkoutIframe.src = iframeSrc;

            checkoutIframe.onload = function() {
              if (checkoutLoading) checkoutLoading.style.display = 'none';
              checkoutIframe.style.display = 'block';

              /* Check if the iframe landed on order-received (thank you) page */
              try {
                var iframeUrl = checkoutIframe.contentWindow.location.href;
                if (iframeUrl.indexOf('order-received') !== -1) {
                  onSwitchComplete(switchData.tier_label);
                }
              } catch(e) { /* cross-origin safety */ }
            };
          }

          /* Listen for postMessage from the checkout iframe */
          window.addEventListener('message', function(e) {
            if (!e.data || e.data.source !== 'ihd_vip_checkout') return;
            if (e.data.type === 'switch_complete') {
              onSwitchComplete(selectedPlan || 'your new plan');
            }
          });

          function closeCheckoutPopup() {
            if (checkoutOverlay) checkoutOverlay.style.display = 'none';
            if (checkoutIframe) { checkoutIframe.src = 'about:blank'; }
            document.body.style.overflow = '';
          }

          function onSwitchComplete(planName) {
            closeCheckoutPopup();

            /* Replace the upgrade accordion content with a success message */
            var accInner = root.querySelector('[data-acc-id="upgrade-downgrade"] .acc-inner');
            if (accInner) {
              accInner.innerHTML =
                '<div class="switch-success">' +
                  '<div class="success-icon"><svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg></div>' +
                  '<h3>Plan Switched Successfully!</h3>' +
                  '<p>You have been switched to <strong>' + planName + '</strong>. Your new billing will begin on your next renewal date.</p>' +
                  '<p style="margin-top:16px;"><a href="/my-account/subscriptions/" style="color:var(--brand-red);font-weight:600;text-decoration:none;">View My Subscriptions &rarr;</a></p>' +
                '</div>';
            }
          }

          if (checkoutClose) {
            checkoutClose.addEventListener('click', closeCheckoutPopup);
          }
          if (checkoutOverlay) {
            checkoutOverlay.addEventListener('click', function(e) {
              if (e.target === checkoutOverlay) closeCheckoutPopup();
            });
          }
          document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && checkoutOverlay && checkoutOverlay.style.display !== 'none') {
              closeCheckoutPopup();
            }
          });

          /* Fallback: poll for switch completion every 5s while popup is open */
          var switchPollTimer = null;
          function startSwitchPoll() {
            stopSwitchPoll();
            switchPollTimer = setInterval(function() {
              if (!checkoutOverlay || checkoutOverlay.style.display === 'none') { stopSwitchPoll(); return; }
              fetch(cfg.ajaxUrl + '?action=ihd_vip_check_switch_complete&nonce=' + cfg.nonce + '&subscription_id=' + cfg.subId + '&since_order_id=' + lastSwitchOrderId)
                .then(r => r.json())
                .then(d => { if (d.success && d.data.switched) onSwitchComplete(selectedPlan || 'your new plan'); });
            }, 5000);
          }
          function stopSwitchPoll() { if (switchPollTimer) { clearInterval(switchPollTimer); switchPollTimer = null; } }

          /* Start polling when popup opens, stop when it closes */
          var origOpen = openCheckoutPopup;
          openCheckoutPopup = function(sd) { origOpen(sd); startSwitchPoll(); };
          var origClose = closeCheckoutPopup;
          closeCheckoutPopup = function() { stopSwitchPoll(); origClose(); };

          /* ── Pause (inline confirm) ── */
          let pauseDays = null;
          const pauseGrid = root.querySelector('[data-pause-grid]');
          const pauseInfo = root.querySelector('[data-pause-info]');
          const pauseConfirmBtn = root.querySelector('[data-pause-confirm]');
          const pauseForm = root.querySelector('[data-pause-form]');
          const pauseSuccess = root.querySelector('[data-pause-success]');
          const pauseDaysInfo = root.querySelector('[data-pause-days-info]');
          const pauseDaysDone = root.querySelector('[data-pause-days-done]');

          if (pauseGrid) {
            pauseGrid.addEventListener('click', (e) => {
              const b = e.target.closest('[data-pause]');
              if (!b) return;
              pauseGrid.querySelectorAll('[data-pause]').forEach(x => x.setAttribute('data-active', '0'));
              b.setAttribute('data-active', '1');
              pauseDays = parseInt(b.getAttribute('data-pause') || '30', 10);
              if (pauseDaysInfo) pauseDaysInfo.textContent = pauseDays;
              if (pauseInfo) pauseInfo.classList.add('show');
              if (pauseConfirmBtn) pauseConfirmBtn.disabled = false;
            });
          }

          if (pauseConfirmBtn) {
            pauseConfirmBtn.addEventListener('click', () => {
              if (pauseDaysDone) pauseDaysDone.textContent = pauseDays;
              if (pauseForm) pauseForm.style.display = 'none';
              if (pauseSuccess) pauseSuccess.classList.add('show');
            });
          }

          /* "I'd rather permanently cancel" link in pause section */
          const openCancelBtn = root.querySelector('[data-open-cancel]');
          if (openCancelBtn) {
            openCancelBtn.addEventListener('click', () => {
              const cancelSec = root.querySelector('[data-cancel-section]');
              if (cancelSec) {
                cancelSec.setAttribute('data-open', '1');
                const toggle = cancelSec.querySelector('[data-acc-toggle]');
                if (toggle) toggle.setAttribute('aria-expanded', 'true');
                setTimeout(() => cancelSec.scrollIntoView({ behavior: 'smooth' }), 100);
              }
            });
          }

          /* ── Cancel — multi-step inline ── */
          function showCancelStep(step) {
            root.querySelectorAll('[data-cancel-step]').forEach(s => {
              s.classList.toggle('active', s.getAttribute('data-cancel-step') === step);
            });
          }

          root.querySelectorAll('[data-goto-step]').forEach(btn => {
            btn.addEventListener('click', () => showCancelStep(btn.getAttribute('data-goto-step')));
          });

          /* Keep membership — collapse the cancel accordion */
          const keepBtn = root.querySelector('[data-cancel-keep]');
          if (keepBtn) {
            keepBtn.addEventListener('click', () => {
              const cancelSec = root.querySelector('[data-cancel-section]');
              if (cancelSec) {
                cancelSec.setAttribute('data-open', '0');
                const toggle = cancelSec.querySelector('[data-acc-toggle]');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
              }
            });
          }

          /* Reason selection */
          const reasonList = root.querySelector('[data-reason-list]');
          const doCancelBtn = root.querySelector('[data-do-cancel]');
          let selectedReason = null;

          if (reasonList) {
            reasonList.addEventListener('click', (e) => {
              const opt = e.target.closest('[data-reason]');
              if (!opt) return;
              reasonList.querySelectorAll('[data-reason]').forEach(o => o.setAttribute('data-active', '0'));
              opt.setAttribute('data-active', '1');
              selectedReason = opt.getAttribute('data-reason');
              if (doCancelBtn) doCancelBtn.disabled = false;
            });
          }

          if (doCancelBtn) {
            doCancelBtn.addEventListener('click', () => {
              if (!selectedReason || !cfg.subId) return;

              showOverlay();
              doCancelBtn.disabled = true;
              doCancelBtn.textContent = 'Cancelling\u2026';

              fetch(cfg.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                  action: 'ihd_vip_cancel_subscription',
                  nonce: cfg.nonce,
                  subscription_id: cfg.subId,
                  reason: selectedReason,
                  feedback: '',
                }),
              })
                .then(r => r.json())
                .then(data => {
                  hideOverlay();
                  if (data.success) {
                    showCancelStep('done');
                  } else {
                    doCancelBtn.disabled = false;
                    doCancelBtn.textContent = 'Confirm Cancellation';
                    alert(data.data && data.data.message ? data.data.message : 'Something went wrong. Please try again.');
                  }
                })
                .catch(() => {
                  hideOverlay();
                  doCancelBtn.disabled = false;
                  doCancelBtn.textContent = 'Confirm Cancellation';
                  alert('Network error. Please try again.');
                });
            });
          }

          /* ── Carousel with translateX + auto-play ── */
          const carousel = root.querySelector('[data-carousel]');
          const track = root.querySelector('[data-slides-track]');
          const slides = track ? Array.from(track.querySelectorAll('[data-slide]')) : [];
          const dotsWrap = root.querySelector('[data-dots]');
          const dots = dotsWrap ? Array.from(dotsWrap.querySelectorAll('[data-dot]')) : [];
          let idx = 0;
          let autoTimer = null;
          const AUTO_INTERVAL = 6000;

          function goToSlide(i) {
            idx = ((i % slides.length) + slides.length) % slides.length;
            if (track) track.style.transform = 'translateX(-' + (idx * 100) + '%)';
            dots.forEach((d, n) => d.setAttribute('data-active', n === idx ? '1' : '0'));
          }

          function startAuto() {
            stopAuto();
            autoTimer = setInterval(() => goToSlide(idx + 1), AUTO_INTERVAL);
          }

          function stopAuto() {
            if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
          }

          if (carousel && slides.length > 1) {
            const prev = carousel.querySelector('[data-prev]');
            const next = carousel.querySelector('[data-next]');
            if (prev) prev.addEventListener('click', () => { goToSlide(idx - 1); startAuto(); });
            if (next) next.addEventListener('click', () => { goToSlide(idx + 1); startAuto(); });
            carousel.addEventListener('mouseenter', stopAuto);
            carousel.addEventListener('mouseleave', startAuto);
            goToSlide(0);
            startAuto();
          }

          if (dotsWrap) {
            dotsWrap.addEventListener('click', (e) => {
              const d = e.target.closest('[data-dot]');
              if (!d) return;
              goToSlide(parseInt(d.getAttribute('data-dot') || '0', 10));
              startAuto();
            });
          }

        })();
      </script>
    </div>
    <?php
    return ob_get_clean();
  }

  /**
   * Fetch slider posts from a specific category or random.
   *
   * @param string $category  Category slug or ID. Empty = random posts.
   * @param int    $count     Number of posts to fetch.
   * @return array Array of post data arrays with keys: title, excerpt, image, author, permalink.
   */
  private static function get_slider_posts( $category = '', $count = 4 ) {
    $args = [
      'post_type'      => 'post',
      'post_status'    => 'publish',
      'posts_per_page' => $count,
      'no_found_rows'  => true,
    ];

    if ( ! empty( $category ) ) {
      // Support both slug and numeric ID.
      if ( is_numeric( $category ) ) {
        $args['cat'] = absint( $category );
      } else {
        $args['category_name'] = $category;
      }
    } else {
      $args['orderby'] = 'rand';
    }

    $query = new \WP_Query( $args );
    $posts = [];

    if ( $query->have_posts() ) {
      while ( $query->have_posts() ) {
        $query->the_post();
        $post_id   = get_the_ID();
        $thumb_url = '';

        if ( has_post_thumbnail( $post_id ) ) {
          $thumb_url = get_the_post_thumbnail_url( $post_id, 'large' );
        }

        $excerpt = get_the_excerpt();
        if ( empty( $excerpt ) ) {
          $excerpt = wp_trim_words( get_the_content(), 18, '…' );
        } else {
          $excerpt = wp_trim_words( $excerpt, 18, '…' );
        }
        // Strip any remaining HTML and decode entities.
        $excerpt = html_entity_decode( wp_strip_all_tags( $excerpt ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        $posts[] = [
          'title'     => html_entity_decode( get_the_title(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
          'excerpt'   => $excerpt,
          'image'     => $thumb_url,
          'author'    => get_the_author(),
          'date'      => get_the_date(),
          'permalink' => get_permalink(),
        ];
      }
      wp_reset_postdata();
    }

    return $posts;
  }

  /**
   * Return a placehold.co URL for posts without featured images.
   *
   * @return string Placeholder image URL.
   */
  private static function get_placeholder_image() {
    return 'https://placehold.co/800x450/e8e0db/c4b5ab?text=No+Image';
  }
}
