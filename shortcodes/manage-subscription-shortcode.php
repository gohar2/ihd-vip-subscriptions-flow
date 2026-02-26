<?php
/**
 * Hero VIP Portal — Manage Subscription Shortcode
 * Version: 2.0.0
 */

if (!defined('ABSPATH')) exit;

final class Hero_VIP_Manage_Subscription_Refined_Shortcode {
  const SHORTCODE = 'hero_vip_manage_subscription_refined';

  public static function init() {
    add_shortcode(self::SHORTCODE, [__CLASS__, 'render_shortcode']);
  }

  public static function render_shortcode($atts = [], $content = null, $shortcode_tag = '') {
    static $instance = 0; $instance++;
    $uid = 'ifx-hvp-' . $instance;

    $atts = shortcode_atts([
      'current_plan' => 'Silver Membership',
      'current_price' => '$9.99',
      'current_interval' => 'Monthly',
      'next_bill' => 'March 15, 2026',
    ], $atts, $shortcode_tag);

    $current_plan = esc_html($atts['current_plan']);
    $current_price = esc_html($atts['current_price']);
    $current_interval = esc_html($atts['current_interval']);
    $next_bill = esc_html($atts['next_bill']);

    $slide_1 = 'https://v0-hero-vip-portal.vercel.app/images/testimonial-surgery.jpg';
    $slide_2 = 'https://v0-hero-vip-portal.vercel.app/images/testimonial-flight.jpg';
    $slide_3 = 'https://v0-hero-vip-portal.vercel.app/images/testimonial-rescue.jpg';

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
          --brand-red: #b23a3a;
          --brand-red-2: #8e2b2b;
          --soft-red: #f7ecec;
          --soft-red-border: #e9cfcf;
          --bronze: #b55a1a;
          --bronze-border: #e7c4ab;
          --silver: #6b7b8f;
          --silver-border: #d8dfe7;
          --gold: #d39200;
          --gold-border: #f1d79a;
          --warn-bg: #fff7d6;
          --warn-border: #f0d37a;
          --warn-text: #9a6b00;
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
          display: flex;
          align-items: center;
          gap: 8px;
          font-weight: 800;
          font-size: 13px;
          letter-spacing: .05em;
          color: var(--brand-red);
          text-transform: uppercase;
          margin-bottom: 8px;
        }
        #<?php echo esc_attr($uid); ?> .topbar .spark {
          width: 18px; height: 18px;
          display: inline-flex; align-items: center; justify-content: center;
        }
        #<?php echo esc_attr($uid); ?> .h1 {
          font-size: 32px;
          font-weight: 900;
          line-height: 1.15;
          color: var(--text);
        }
        #<?php echo esc_attr($uid); ?> .lead {
          margin: 10px 0 24px;
          color: var(--muted);
          font-size: 15px;
          line-height: 1.6;
          max-width: 720px;
        }

        /* === Current Plan Bar === */
        #<?php echo esc_attr($uid); ?> .currentbar {
          background: var(--soft-red);
          border: 1px solid var(--soft-red-border);
          border-radius: var(--radius);
          padding: 18px 20px;
          display: flex;
          align-items: center;
          gap: 14px;
          margin-bottom: 20px;
        }
        #<?php echo esc_attr($uid); ?> .currentbar .icon {
          width: 42px; height: 42px;
          border-radius: 50%;
          background: var(--brand-red);
          display: flex; align-items: center; justify-content: center;
          flex: 0 0 auto;
        }
        #<?php echo esc_attr($uid); ?> .currentbar .t1 {
          font-weight: 800;
          font-size: 15px;
        }
        #<?php echo esc_attr($uid); ?> .currentbar .t2 {
          font-size: 14px;
          color: var(--muted);
          margin-top: 2px;
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
          padding: 20px 22px !important;
          display: flex !important;
          align-items: center !important;
          justify-content: space-between !important;
          gap: 14px !important;
          cursor: pointer !important;
          font-family: inherit !important;
          transition: background .15s ease !important;
        }
        #<?php echo esc_attr($uid); ?> .acc-h:hover { background: #faf7f5 !important; }
        #<?php echo esc_attr($uid); ?> .acc-h:focus { outline: none !important; box-shadow: 0 0 0 3px rgba(178,58,58,.15) inset !important; }
        #<?php echo esc_attr($uid); ?> .acc-left {
          display: flex !important;
          align-items: center !important;
          gap: 14px !important;
        }
        #<?php echo esc_attr($uid); ?> .acc-ico {
          width: 40px !important; height: 40px !important;
          min-width: 40px !important; min-height: 40px !important;
          border-radius: 50% !important;
          background: #f3ecea !important;
          display: flex !important; align-items: center !important; justify-content: center !important;
          flex: 0 0 auto !important;
        }
        #<?php echo esc_attr($uid); ?> .acc-title,
        #<?php echo esc_attr($uid); ?> .section .acc-title,
        #<?php echo esc_attr($uid); ?> .section .acc-h .acc-title,
        #<?php echo esc_attr($uid); ?> h2.acc-title {
          font-weight: 700 !important;
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
          margin-top: 3px !important;
          display: block !important;
          visibility: visible !important;
        }
        #<?php echo esc_attr($uid); ?> .chev {
          width: 22px !important; height: 22px !important;
          color: #888 !important;
          transition: transform .3s ease !important;
          flex: 0 0 auto !important;
        }
        #<?php echo esc_attr($uid); ?> .section[data-open="1"] .chev { transform: rotate(180deg) !important; }

        /* Animated accordion panel */
        #<?php echo esc_attr($uid); ?> .acc-panel {
          padding: 0 22px !important;
          border-top: 1px solid #f0e7e2 !important;
          background: #fff !important;
          max-height: 0 !important;
          overflow: hidden !important;
          opacity: 0 !important;
          transition: max-height .4s cubic-bezier(0.4, 0, 0.2, 1),
                      opacity .3s ease,
                      padding .4s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        #<?php echo esc_attr($uid); ?> .section[data-open="1"] .acc-panel {
          max-height: 2000px !important;
          opacity: 1 !important;
          padding: 20px 22px 24px !important;
        }

        /* === Plans Grid === */
        #<?php echo esc_attr($uid); ?> .plans {
          display: grid;
          grid-template-columns: 1fr;
          gap: 16px;
          margin-top: 8px;
        }
        @media (min-width: 700px) {
          #<?php echo esc_attr($uid); ?> .plans { grid-template-columns: repeat(3, 1fr); }
        }
        #<?php echo esc_attr($uid); ?> .plan {
          border-radius: 10px;
          background: #fff;
          border: 2px solid #eee;
          padding: 22px 20px;
          position: relative;
          display: flex;
          flex-direction: column;
        }
        #<?php echo esc_attr($uid); ?> .plan.bronze { border-color: var(--bronze-border); }
        #<?php echo esc_attr($uid); ?> .plan.silver { border-color: var(--silver-border); }
        #<?php echo esc_attr($uid); ?> .plan.gold { border-color: var(--gold-border); }
        #<?php echo esc_attr($uid); ?> .plan .picon {
          width: 40px; height: 40px;
          border-radius: 50%;
          display: flex; align-items: center; justify-content: center;
          margin-bottom: 14px;
        }
        #<?php echo esc_attr($uid); ?> .plan.bronze .picon { background: var(--bronze); }
        #<?php echo esc_attr($uid); ?> .plan.silver .picon { background: var(--silver); }
        #<?php echo esc_attr($uid); ?> .plan.gold .picon { background: var(--gold); }
        #<?php echo esc_attr($uid); ?> .plan .pname {
          font-weight: 800;
          font-size: 18px;
        }
        #<?php echo esc_attr($uid); ?> .price {
          font-weight: 900;
          font-size: 26px;
          display: flex;
          align-items: baseline;
          gap: 6px;
          margin-top: 2px;
        }
        #<?php echo esc_attr($uid); ?> .per {
          font-size: 14px;
          font-weight: 600;
          color: var(--muted);
        }
        #<?php echo esc_attr($uid); ?> .ticks {
          list-style: none;
          padding: 14px 0 0;
          margin: 0;
          display: flex;
          flex-direction: column;
          gap: 10px;
          font-size: 14px;
          color: #4a4a4a;
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
          margin-top: 18px;
          width: 100%;
          padding: 12px 14px;
          border-radius: 8px;
          border: 1px solid var(--border);
          background: #f5f1ef;
          color: #333;
          font-weight: 700;
          font-size: 14px;
          cursor: pointer;
          font-family: inherit;
          transition: background .15s;
        }
        #<?php echo esc_attr($uid); ?> .choose:hover { background: #ece6e3; }
        #<?php echo esc_attr($uid); ?> .badge {
          position: absolute;
          top: -12px;
          left: 50%;
          transform: translateX(-50%);
          background: var(--brand-red);
          color: #fff;
          font-weight: 800;
          font-size: 12px;
          padding: 4px 14px;
          border-radius: 999px;
          white-space: nowrap;
        }

        /* === Pause Section === */
        #<?php echo esc_attr($uid); ?> .bodytext {
          font-size: 14px;
          color: var(--muted);
          line-height: 1.6;
          margin-bottom: 16px;
        }
        #<?php echo esc_attr($uid); ?> .label {
          font-weight: 800;
          font-size: 14px;
          margin: 0 0 10px;
          color: #333;
        }
        #<?php echo esc_attr($uid); ?> .pause-grid {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 12px;
          margin-bottom: 18px;
        }
        @media (min-width: 700px) {
          #<?php echo esc_attr($uid); ?> .pause-grid { grid-template-columns: repeat(4, 1fr); }
        }
        #<?php echo esc_attr($uid); ?> .pill {
          border: 1px solid var(--border);
          background: #fff;
          padding: 12px;
          border-radius: 8px;
          font-weight: 700;
          font-size: 14px;
          color: #333;
          cursor: pointer;
          text-align: center;
          font-family: inherit;
          transition: border-color .15s, box-shadow .15s;
        }
        #<?php echo esc_attr($uid); ?> .pill:hover { border-color: #ccc; }
        #<?php echo esc_attr($uid); ?> .pill[data-active="1"] {
          border-color: rgba(178,58,58,.4);
          box-shadow: 0 0 0 3px rgba(178,58,58,.1);
        }
        #<?php echo esc_attr($uid); ?> .pause-actions {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 14px;
          flex-wrap: wrap;
        }

        /* === Buttons === */
        #<?php echo esc_attr($uid); ?> .btn {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          padding: 12px 20px;
          border-radius: 8px;
          border: 0;
          cursor: pointer;
          font-weight: 700;
          font-size: 14px;
          font-family: inherit;
          transition: background .15s, border-color .15s;
        }
        #<?php echo esc_attr($uid); ?> .btn-primary {
          background: var(--brand-red);
          color: #fff;
        }
        #<?php echo esc_attr($uid); ?> .btn-primary:hover { background: var(--brand-red-2); }
        #<?php echo esc_attr($uid); ?> .link {
          font-size: 14px;
          color: var(--muted);
          text-decoration: underline;
          cursor: pointer;
          white-space: nowrap;
        }

        /* === Cancel Section === */
        #<?php echo esc_attr($uid); ?> .sadbox {
          background: #f6f2f0;
          border: 1px solid #eee3de;
          border-radius: 10px;
          padding: 28px 24px;
          text-align: center;
        }
        #<?php echo esc_attr($uid); ?> .sadbox .heart {
          width: 44px; height: 44px;
          border-radius: 50%;
          margin: 0 auto 12px;
          display: flex; align-items: center; justify-content: center;
          color: var(--brand-red);
          background: #fff;
          border: 1px solid #ebe0db;
        }
        #<?php echo esc_attr($uid); ?> .sadbox .m1 {
          font-weight: 800;
          font-size: 15px;
          color: #333;
          margin-bottom: 6px;
          line-height: 1.45;
        }
        #<?php echo esc_attr($uid); ?> .sadbox .m2 {
          font-size: 14px;
          color: var(--muted);
          line-height: 1.5;
        }
        #<?php echo esc_attr($uid); ?> .warn {
          margin-top: 16px;
          background: var(--warn-bg);
          border: 1px solid var(--warn-border);
          border-radius: 10px;
          padding: 16px 18px;
          display: flex;
          gap: 12px;
          align-items: flex-start;
          font-size: 14px;
          line-height: 1.55;
        }
        #<?php echo esc_attr($uid); ?> .warn .wico {
          width: 22px; height: 22px;
          margin-top: 1px;
          flex: 0 0 auto;
          color: var(--warn-text);
        }
        #<?php echo esc_attr($uid); ?> .warn strong {
          display: block;
          margin-bottom: 6px;
          color: #8b5a00;
          font-size: 14px;
        }
        #<?php echo esc_attr($uid); ?> .warn-list {
          color: var(--brand-red);
          font-weight: 700;
          font-size: 14px;
          line-height: 1.7;
        }
        #<?php echo esc_attr($uid); ?> .cancel-actions {
          display: flex;
          gap: 12px;
          margin-top: 18px;
          flex-wrap: wrap;
        }
        #<?php echo esc_attr($uid); ?> .btn-outline-red {
          background: #fff;
          border: 1px solid #e5b4b4;
          color: var(--brand-red);
        }
        #<?php echo esc_attr($uid); ?> .btn-outline-red:hover { background: #fff5f5; }
        #<?php echo esc_attr($uid); ?> .btn-solid-red {
          background: var(--brand-red);
          color: #fff;
        }
        #<?php echo esc_attr($uid); ?> .btn-solid-red:hover { background: var(--brand-red-2); }

        /* === Testimonials / Carousel === */
        #<?php echo esc_attr($uid); ?> .h3 {
          font-weight: 900;
          font-size: 20px;
          margin: 28px 0 0;
        }
        #<?php echo esc_attr($uid); ?> .h3sub {
          margin: 4px 0 14px;
          font-size: 14px;
          color: var(--muted);
        }
        #<?php echo esc_attr($uid); ?> .carousel {
          background: #000;
          border-radius: 12px;
          overflow: hidden;
          position: relative;
          border: 1px solid #e7dfdb;
        }
        #<?php echo esc_attr($uid); ?> .slides-track {
          display: flex;
          transition: transform .5s ease-in-out;
        }
        #<?php echo esc_attr($uid); ?> .slide {
          width: 100%;
          flex-shrink: 0;
          position: relative;
          height: 320px;
        }
        @media (min-width: 700px) {
          #<?php echo esc_attr($uid); ?> .slide { height: 380px; }
        }
        #<?php echo esc_attr($uid); ?> .slide img {
          width: 100%; height: 100%;
          object-fit: cover;
          opacity: .9;
        }
        #<?php echo esc_attr($uid); ?> .overlay {
          position: absolute;
          left: 0; right: 0; bottom: 0;
          padding: 24px 22px;
          background: linear-gradient(to top, rgba(0,0,0,.7) 0%, rgba(0,0,0,0) 100%);
          color: #fff;
        }
        #<?php echo esc_attr($uid); ?> .overlay .qmark {
          font-size: 40px;
          font-weight: 900;
          line-height: 1;
          opacity: .4;
          margin-bottom: 6px;
        }
        #<?php echo esc_attr($uid); ?> .quote {
          font-weight: 700;
          font-size: 15px;
          line-height: 1.45;
          margin-bottom: 14px;
        }
        #<?php echo esc_attr($uid); ?> .who2 {
          font-weight: 800;
          font-size: 14px;
        }
        #<?php echo esc_attr($uid); ?> .org2 {
          font-size: 13px;
          opacity: .8;
          margin-top: 2px;
        }
        #<?php echo esc_attr($uid); ?> .navbtn {
          position: absolute;
          top: 50%;
          transform: translateY(-50%);
          width: 36px; height: 36px;
          border-radius: 50%;
          border: 0;
          background: rgba(0,0,0,.4);
          color: #fff;
          display: flex; align-items: center; justify-content: center;
          cursor: pointer;
          transition: background .15s;
        }
        #<?php echo esc_attr($uid); ?> .navbtn:hover { background: rgba(0,0,0,.55); }
        #<?php echo esc_attr($uid); ?> .navbtn.prev { left: 12px; }
        #<?php echo esc_attr($uid); ?> .navbtn.next { right: 12px; }
        #<?php echo esc_attr($uid); ?> .dots {
          display: flex;
          justify-content: center;
          gap: 8px;
          padding: 14px 0 0;
        }
        #<?php echo esc_attr($uid); ?> .dot {
          width: 10px; height: 10px;
          border-radius: 999px;
          background: rgba(0,0,0,.15);
          border: 0;
          cursor: pointer;
          transition: all .3s ease;
        }
        #<?php echo esc_attr($uid); ?> .dot:hover { background: rgba(0,0,0,.35); }
        #<?php echo esc_attr($uid); ?> .dot[data-active="1"] {
          background: var(--brand-red);
          width: 28px;
        }

        /* === Impact Bar === */
        #<?php echo esc_attr($uid); ?> .impact {
          margin-top: 20px;
          background: #f6f2f0;
          border: 1px solid #eee3de;
          border-radius: 12px;
          padding: 24px 20px;
        }
        #<?php echo esc_attr($uid); ?> .impact-title {
          text-align: center;
          font-weight: 900;
          font-size: 17px;
          margin-bottom: 16px;
          color: #333;
        }
        #<?php echo esc_attr($uid); ?> .impact-grid {
          display: grid;
          grid-template-columns: 1fr;
          gap: 12px;
          text-align: center;
        }
        @media (min-width: 700px) {
          #<?php echo esc_attr($uid); ?> .impact-grid { grid-template-columns: repeat(3, 1fr); }
        }
        #<?php echo esc_attr($uid); ?> .impact-num {
          font-weight: 900;
          font-size: 28px;
          color: var(--brand-red);
        }
        #<?php echo esc_attr($uid); ?> .impact-lbl {
          font-size: 13px;
          color: var(--muted);
          margin-top: 2px;
        }

        /* === Modals === */
        #<?php echo esc_attr($uid); ?> .modal {
          position: fixed;
          inset: 0;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 20px;
          z-index: 999999;
          opacity: 0;
          visibility: hidden;
          transition: opacity .25s ease, visibility .25s ease;
        }
        #<?php echo esc_attr($uid); ?> .modal[data-open="1"] {
          opacity: 1;
          visibility: visible;
        }
        #<?php echo esc_attr($uid); ?> .backdrop {
          position: absolute; inset: 0;
          background: rgba(0,0,0,.45);
          backdrop-filter: blur(2px);
          -webkit-backdrop-filter: blur(2px);
        }
        #<?php echo esc_attr($uid); ?> .mcard {
          position: relative;
          width: min(560px, 100%);
          background: #fff;
          border-radius: 14px;
          border: 1px solid #ebe0db;
          box-shadow: 0 20px 60px rgba(0,0,0,.22);
          overflow: hidden;
          transform: scale(.95) translateY(10px);
          transition: transform .3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        #<?php echo esc_attr($uid); ?> .modal[data-open="1"] .mcard {
          transform: scale(1) translateY(0);
        }
        #<?php echo esc_attr($uid); ?> .mhead {
          padding: 18px 20px;
          border-bottom: 1px solid #f0e7e2;
          display: flex;
          align-items: flex-start;
          justify-content: space-between;
          gap: 14px;
        }
        #<?php echo esc_attr($uid); ?> .mtitle {
          font-weight: 800;
          font-size: 16px;
        }
        #<?php echo esc_attr($uid); ?> .mdesc {
          margin-top: 4px;
          font-size: 14px;
          color: var(--muted);
          line-height: 1.5;
        }
        #<?php echo esc_attr($uid); ?> .mbody { padding: 16px 20px; font-size: 14px; }
        #<?php echo esc_attr($uid); ?> .mfoot {
          padding: 14px 20px;
          border-top: 1px solid #f0e7e2;
          display: flex;
          justify-content: flex-end;
          gap: 10px;
          flex-wrap: wrap;
        }
        #<?php echo esc_attr($uid); ?> .xbtn {
          width: 34px; height: 34px;
          border-radius: 8px;
          border: 1px solid #ebe0db;
          background: #fff;
          cursor: pointer;
          display: flex; align-items: center; justify-content: center;
        }
        #<?php echo esc_attr($uid); ?> .xbtn:hover { background: #faf7f5; }
        #<?php echo esc_attr($uid); ?> .field { margin-top: 12px; }
        #<?php echo esc_attr($uid); ?> .inlabel { font-size: 14px; font-weight: 700; margin-bottom: 6px; color: #333; }
        #<?php echo esc_attr($uid); ?> .input {
          width: 100%;
          border: 1px solid #ebe0db;
          border-radius: 8px;
          padding: 10px 12px;
          font-size: 14px;
          font-family: inherit;
        }
      </style>

      <div class="wrap">

        <!-- Header -->
        <div class="topbar">
          <span class="spark" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
              <path d="M12 2l1.2 4.6L18 8l-4.8 1.4L12 14l-1.2-4.6L6 8l4.8-1.4L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
            </svg>
          </span>
          HERO VIP CLUB
        </div>
        <h1 class="h1">Manage Your Subscription</h1>
        <p class="lead">
          Thank you for being a Hero VIP member! Your membership helps provide meals, medical care, and rescue flights for dogs in need across the country.
        </p>

        <!-- Current plan bar -->
        <div class="currentbar">
          <div class="icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none">
              <path d="M12 21s-7-4.5-9.5-9C.8 8.7 3 6 6 6c1.7 0 3.2.9 4 2.2C10.8 6.9 12.3 6 14 6c3 0 5.2 2.7 3.5 6-2.5 4.5-9.5 9-9.5 9Z" stroke="#fff" stroke-width="2" stroke-linejoin="round"/>
            </svg>
          </div>
          <div>
            <p class="t1">Current Plan: <?php echo $current_plan; ?></p>
            <div class="t2"><?php echo $current_price; ?>/<?php echo strtolower($current_interval); ?> · Next billing date: <?php echo $next_bill; ?></div>
          </div>
        </div>

        <!-- Accordion: Upgrade/Downgrade — COLLAPSED by default -->
        <section class="section" data-acc data-open="0">
          <button class="acc-h" type="button" data-acc-toggle aria-expanded="false">
            <div class="acc-left">
              <div class="acc-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                  <path d="M5 10l7-6 7 6v9a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-9Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
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
            <div class="plans">

              <!-- Bronze -->
              <div class="plan bronze">
                <div class="picon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
                    <path d="M12 2l7 4v6c0 5-3 9-7 10C8 21 5 17 5 12V6l7-4Z" stroke="#fff" stroke-width="2" stroke-linejoin="round"/>
                  </svg>
                </div>
                <div class="pname" style="color:var(--bronze);">Bronze</div>
                <div class="price">$4.99 <span class="per">/ Monthly</span></div>
                <ul class="ticks">
                  <li class="tick"><svg class="check" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>10% off all purchases</li>
                  <li class="tick"><svg class="check" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>Free shipping on orders $25+</li>
                  <li class="tick"><svg class="check" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>5 meals donated monthly</li>
                </ul>
                <button class="choose" type="button" data-open-modal="planModal" data-plan="Bronze" data-price="$4.99 / Monthly">Choose Bronze</button>
              </div>

              <!-- Silver -->
              <div class="plan silver">
                <div class="badge">Most Popular</div>
                <div class="picon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
                    <path d="M12 3l2.5 5 5.5.8-4 3.9.9 5.5-4.9-2.6-4.9 2.6.9-5.5-4-3.9 5.5-.8L12 3Z" stroke="#fff" stroke-width="2" stroke-linejoin="round"/>
                  </svg>
                </div>
                <div class="pname" style="color:var(--silver);">Silver</div>
                <div class="price">$9.99 <span class="per">/ Monthly</span></div>
                <ul class="ticks">
                  <li class="tick"><svg class="check" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>15% off all purchases</li>
                  <li class="tick"><svg class="check" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>Free shipping on all orders</li>
                  <li class="tick"><svg class="check" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>15 meals donated monthly</li>
                  <li class="tick"><svg class="check" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>Early access to new products</li>
                </ul>
                <button class="choose" type="button" data-open-modal="planModal" data-plan="Silver" data-price="$9.99 / Monthly">Choose Silver</button>
              </div>

              <!-- Gold -->
              <div class="plan gold">
                <div class="picon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
                    <path d="M5 9l3 3 4-6 4 6 3-3v10H5V9Z" stroke="#fff" stroke-width="2" stroke-linejoin="round"/>
                  </svg>
                </div>
                <div class="pname" style="color:var(--gold);">Gold</div>
                <div class="price">$24.99 <span class="per">/ Monthly</span></div>
                <ul class="ticks">
                  <li class="tick"><svg class="check" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>25% off all purchases</li>
                  <li class="tick"><svg class="check" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>Free shipping on all orders</li>
                  <li class="tick"><svg class="check" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>50 meals donated monthly</li>
                  <li class="tick"><svg class="check" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>Early access to new products</li>
                  <li class="tick"><svg class="check" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>Exclusive VIP-only deals</li>
                  <li class="tick"><svg class="check" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>Priority customer support</li>
                </ul>
                <button class="choose" type="button" data-open-modal="planModal" data-plan="Gold" data-price="$24.99 / Monthly">Choose Gold</button>
              </div>

            </div>
          </div>
        </section>

        <!-- Accordion: Pause — COLLAPSED by default -->
        <section class="section" data-acc data-open="0">
          <button class="acc-h" type="button" data-acc-toggle aria-expanded="false">
            <div class="acc-left">
              <div class="acc-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                  <path d="M8 6v12M16 6v12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
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
            <p class="bodytext">
              Select how long you'd like to pause your membership. Your benefits will resume automatically after the pause period ends.
            </p>
            <div class="label">Pause for:</div>
            <div class="pause-grid" data-pause-grid>
              <button type="button" class="pill" data-pause="30" data-active="1">30 Days</button>
              <button type="button" class="pill" data-pause="60">60 Days</button>
              <button type="button" class="pill" data-pause="90">90 Days</button>
              <button type="button" class="pill" data-pause="180">180 Days</button>
            </div>
            <div class="pause-actions">
              <button type="button" class="btn btn-primary" data-open-modal="pauseModal">Confirm Pause</button>
              <span class="link" data-open-modal="cancelModal">I'd rather permanently cancel</span>
            </div>
          </div>
        </section>

        <!-- Accordion: Cancel — COLLAPSED by default -->
        <section class="section" data-acc data-open="0">
          <button class="acc-h" type="button" data-acc-toggle aria-expanded="false">
            <div class="acc-left">
              <div class="acc-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                  <path d="M9 9l6 6M15 9l-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                  <path d="M12 22A10 10 0 1 0 12 2a10 10 0 0 0 0 20Z" stroke="currentColor" stroke-width="2"/>
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
            <div class="sadbox">
              <div class="heart" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none">
                  <path d="M12 21s-7-4.5-9.5-9C.8 8.7 3 6 6 6c1.7 0 3.2.9 4 2.2C10.8 6.9 12.3 6 14 6c3 0 5.2 2.7 3.5 6-2.5 4.5-9.5 9-9.5 9Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                </svg>
              </div>
              <p class="m1">We're sad to see you go, but we appreciate your partnership and the lives you have helped save!</p>
              <p class="m2">Your support has made a real difference for rescue dogs across the country.</p>
            </div>

            <div class="warn">
              <svg class="wico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 2l10 18H2L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                <path d="M12 9v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M12 17h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
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

            <div class="cancel-actions">
              <button type="button" class="btn btn-outline-red" data-open-modal="cancelConfirmModal">I still want to cancel</button>
              <button type="button" class="btn btn-solid-red" data-open-modal="keepModal">Keep My Membership</button>
            </div>
          </div>
        </section>

        <!-- See the Lives -->
        <div class="h3">See the Lives You Help Save</div>
        <div class="h3sub">Real stories from our rescue partners across the country.</div>

        <div class="carousel" data-carousel>
          <div class="slides-track" data-slides-track>
            <div class="slide" data-slide="0">
              <img src="<?php echo esc_url($slide_1); ?>" alt="">
              <div class="overlay">
                <div class="qmark" aria-hidden="true">&ldquo;</div>
                <p class="quote">"Donated meals let us redirect funds to life-saving surgeries. Last year, 200+ emergency operations happened because of Hero VIP members."</p>
                <p class="who2">Dr. Sarah Mitchell</p>
                <div class="org2">Second Chance Veterinary Rescue, Austin, TX</div>
              </div>
            </div>

            <div class="slide" data-slide="1">
              <img src="<?php echo esc_url($slide_2); ?>" alt="">
              <div class="overlay">
                <div class="qmark" aria-hidden="true">&ldquo;</div>
                <p class="quote">"We fly at-risk shelter dogs to areas with adoption demand. Hero VIP Club funds the flights that give these dogs a second chance at life."</p>
                <p class="who2">Captain Mike Reynolds</p>
                <div class="org2">Wings of Rescue Foundation, Nashville, TN</div>
              </div>
            </div>

            <div class="slide" data-slide="2">
              <img src="<?php echo esc_url($slide_3); ?>" alt="">
              <div class="overlay">
                <div class="qmark" aria-hidden="true">&ldquo;</div>
                <p class="quote">"Food is our biggest cost with 80+ dogs in care. iHeartDogs donations keep our doors open &mdash; without them, we'd have to turn dogs away."</p>
                <p class="who2">Jennifer Ortiz</p>
                <div class="org2">Paws &amp; Hope Animal Shelter, Portland, OR</div>
              </div>
            </div>
          </div>

          <button class="navbtn prev" type="button" aria-label="Previous" data-prev>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <button class="navbtn next" type="button" aria-label="Next" data-next>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
        </div>

        <div class="dots" data-dots>
          <button class="dot" type="button" data-dot="0" data-active="1" aria-label="Slide 1"></button>
          <button class="dot" type="button" data-dot="1" data-active="0" aria-label="Slide 2"></button>
          <button class="dot" type="button" data-dot="2" data-active="0" aria-label="Slide 3"></button>
        </div>

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

      <!-- Modals -->
      <div class="modal" data-modal="planModal" role="dialog" aria-modal="true">
        <div class="backdrop" data-close-modal></div>
        <div class="mcard" role="document">
          <div class="mhead">
            <div>
              <p class="mtitle">Confirm Plan Selection</p>
              <div class="mdesc">You're switching to a new plan.</div>
            </div>
            <button class="xbtn" type="button" aria-label="Close" data-close-modal>
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
          </div>
          <div class="mbody">
            <div style="font-weight:800;font-size:15px;">Selected: <span data-plan-name>Silver</span></div>
            <div style="color:var(--muted);font-size:14px;margin-top:4px;">Price: <span data-plan-price>$9.99 / Monthly</span></div>
            <div style="margin-top:10px;color:var(--muted);font-size:14px;line-height:1.5;">
              Next billing date: <?php echo $next_bill; ?>
            </div>
          </div>
          <div class="mfoot">
            <button type="button" class="btn btn-outline-red" data-close-modal>Cancel</button>
            <button type="button" class="btn btn-solid-red" data-close-modal>Confirm</button>
          </div>
        </div>
      </div>

      <div class="modal" data-modal="pauseModal" role="dialog" aria-modal="true">
        <div class="backdrop" data-close-modal></div>
        <div class="mcard" role="document">
          <div class="mhead">
            <div>
              <p class="mtitle">Confirm Pause</p>
              <div class="mdesc">Pausing for <strong><span data-pause-days>30</span> days</strong>.</div>
            </div>
            <button class="xbtn" type="button" aria-label="Close" data-close-modal>
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
          </div>
          <div class="mbody">
            <div class="field">
              <div class="inlabel">Reason</div>
              <input class="input" type="text" placeholder="Taking a short break" />
            </div>
          </div>
          <div class="mfoot">
            <button type="button" class="btn btn-outline-red" data-close-modal>Back</button>
            <button type="button" class="btn btn-solid-red" data-close-modal>Confirm Pause</button>
          </div>
        </div>
      </div>

      <div class="modal" data-modal="cancelModal" role="dialog" aria-modal="true">
        <div class="backdrop" data-close-modal></div>
        <div class="mcard" role="document">
          <div class="mhead">
            <div>
              <p class="mtitle">Cancel Membership</p>
              <div class="mdesc">Your benefits will continue until the end of your current billing period.</div>
            </div>
            <button class="xbtn" type="button" aria-label="Close" data-close-modal>
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
          </div>
          <div class="mbody">
            <div style="font-size:14px;color:var(--muted);line-height:1.5;">
              You'll keep benefits until <?php echo $next_bill; ?>.
            </div>
          </div>
          <div class="mfoot">
            <button type="button" class="btn btn-outline-red" data-close-modal>Close</button>
            <button type="button" class="btn btn-solid-red" data-close-modal>Proceed</button>
          </div>
        </div>
      </div>

      <div class="modal" data-modal="cancelConfirmModal" role="dialog" aria-modal="true">
        <div class="backdrop" data-close-modal></div>
        <div class="mcard" role="document">
          <div class="mhead">
            <div>
              <p class="mtitle">Final Confirmation</p>
              <div class="mdesc">This action cannot be undone.</div>
            </div>
            <button class="xbtn" type="button" aria-label="Close" data-close-modal>
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
          </div>
          <div class="mbody">
            <div style="font-weight:800;font-size:15px;">Are you sure you want to cancel?</div>
            <div style="font-size:14px;color:var(--muted);margin-top:6px;line-height:1.5;">
              Your membership will end after the current billing period.
            </div>
          </div>
          <div class="mfoot">
            <button type="button" class="btn btn-outline-red" data-close-modal>Go back</button>
            <button type="button" class="btn btn-solid-red" data-close-modal>Confirm cancel</button>
          </div>
        </div>
      </div>

      <div class="modal" data-modal="keepModal" role="dialog" aria-modal="true">
        <div class="backdrop" data-close-modal></div>
        <div class="mcard" role="document">
          <div class="mhead">
            <div>
              <p class="mtitle">Thanks for staying!</p>
              <div class="mdesc">We're glad you're sticking with us.</div>
            </div>
            <button class="xbtn" type="button" aria-label="Close" data-close-modal>
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
          </div>
          <div class="mbody">
            <div style="font-size:14px;color:var(--muted);line-height:1.5;">
              Your support helps rescue dogs across the country.
            </div>
          </div>
          <div class="mfoot">
            <button type="button" class="btn btn-solid-red" data-close-modal>Close</button>
          </div>
        </div>
      </div>

      <script>
        (function(){
          const root = document.getElementById(<?php echo json_encode($uid); ?>);
          if (!root) return;

          /* ── Accordions — animated open/close ── */
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

          /* ── Pause pills ── */
          let pauseDays = 30;
          const pauseGrid = root.querySelector('[data-pause-grid]');
          if (pauseGrid) {
            pauseGrid.addEventListener('click', (e) => {
              const b = e.target.closest('[data-pause]');
              if (!b) return;
              pauseGrid.querySelectorAll('[data-pause]').forEach(x => x.setAttribute('data-active', '0'));
              b.setAttribute('data-active', '1');
              pauseDays = parseInt(b.getAttribute('data-pause') || '30', 10);
              const out = root.querySelector('[data-pause-days]');
              if (out) out.textContent = String(pauseDays);
            });
          }

          /* ── Carousel with translateX sliding + auto-play ── */
          const carousel = root.querySelector('[data-carousel]');
          const track = root.querySelector('[data-slides-track]');
          const slides = track ? Array.from(track.querySelectorAll('[data-slide]')) : [];
          const dotsWrap = root.querySelector('[data-dots]');
          const dots = dotsWrap ? Array.from(dotsWrap.querySelectorAll('[data-dot]')) : [];
          let idx = 0;
          let autoTimer = null;
          const AUTO_INTERVAL = 5000;

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

            /* Pause auto-slide on hover */
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

          /* ── Modals with animation ── */
          const modals = Array.from(root.querySelectorAll('.modal[data-modal]'));
          let lastFocus = null;

          function openModal(key) {
            const m = root.querySelector('.modal[data-modal="' + key + '"]');
            if (!m) return;
            lastFocus = document.activeElement;
            m.setAttribute('data-open', '1');
            document.documentElement.style.overflow = 'hidden';
            document.body.style.overflow = 'hidden';
            const focusable = m.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (focusable.length) focusable[0].focus();
          }

          function closeModal(m) {
            if (!m) return;
            m.setAttribute('data-open', '0');
            document.documentElement.style.overflow = '';
            document.body.style.overflow = '';
            if (lastFocus && typeof lastFocus.focus === 'function') { try { lastFocus.focus(); } catch (e) {} }
            lastFocus = null;
          }

          root.addEventListener('click', (e) => {
            const openBtn = e.target.closest('[data-open-modal]');
            if (openBtn) {
              const key = openBtn.getAttribute('data-open-modal');
              if (key === 'planModal') {
                const pn = openBtn.getAttribute('data-plan') || 'Silver';
                const pp = openBtn.getAttribute('data-price') || '$9.99 / Monthly';
                const n = root.querySelector('[data-plan-name]');
                const p = root.querySelector('[data-plan-price]');
                if (n) n.textContent = pn;
                if (p) p.textContent = pp;
              }
              if (key === 'pauseModal') {
                const out = root.querySelector('[data-pause-days]');
                if (out) out.textContent = String(pauseDays);
              }
              openModal(key);
              return;
            }
            const closeBtn = e.target.closest('[data-close-modal]');
            if (closeBtn) {
              const m = e.target.closest('.modal[data-modal]');
              closeModal(m);
              return;
            }
          });

          document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            const open = modals.find(m => m.getAttribute('data-open') === '1');
            if (open) closeModal(open);
          });
        })();
      </script>
    </div>
    <?php
    return ob_get_clean();
  }
}
