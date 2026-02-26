<?php
/**
 * Plugin Name: Hero VIP Portal (Static Shortcode)
 * Description: Static shortcode rendering of "Manage Your Subscription" main content matching the provided layout (accordions, modals, carousel). All CSS inline + scoped.
 * Version: 1.2.0
 * Author: I Fix Ecommerce LLC
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

    // Images hosted on your Vercel deployment (static)
    $slide_1 = 'https://v0-hero-vip-portal.vercel.app/images/testimonial-surgery.jpg';
    $slide_2 = 'https://v0-hero-vip-portal.vercel.app/images/testimonial-flight.jpg';
    $slide_3 = 'https://v0-hero-vip-portal.vercel.app/images/testimonial-rescue.jpg';

    ob_start();
    ?>
    <div id="<?php echo esc_attr($uid); ?>" class="ifx-hvp">
      <style>
        /* ==========================================================
           Scoped styles to match screenshot layout
           ========================================================== */
        #<?php echo esc_attr($uid); ?>{
          --bg: #faf7f6;
          --card: #ffffff;
          --border: #eadfdb;
          --muted: #6b6b6b;
          --text: #1f1f1f;

          --brand-red: #b23a3a;     /* primary accent (title icon / buttons / dots) */
          --brand-red-2: #8e2b2b;   /* darker hover */
          --soft-red: #f7ecec;      /* current plan bar background */
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

          --shadow: 0 1px 0 rgba(0,0,0,.03);
          --radius: 10px;
          --radius-lg: 12px;

          font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji";
          color: var(--text);
          background: var(--bg);
          border-radius: 12px;
          padding: 18px 0;
          isolation: isolate;
        }

        #<?php echo esc_attr($uid); ?>, 
        #<?php echo esc_attr($uid); ?> * { box-sizing: border-box; }

        #<?php echo esc_attr($uid); ?> img { max-width: 100%; height: auto; display:block; }

        /* Container */
        #<?php echo esc_attr($uid); ?> .wrap {
          width: min(980px, calc(100% - 32px));
          margin: 0 auto;
        }

        /* Header */
        #<?php echo esc_attr($uid); ?> .topbar{
          display:flex;
          align-items:center;
          gap: 8px;
          font-weight: 800;
          font-size: 12px;
          letter-spacing: .04em;
          color: var(--brand-red);
          text-transform: uppercase;
          margin-bottom: 6px;
        }
        #<?php echo esc_attr($uid); ?> .topbar .spark{
          width: 14px; height: 14px;
          display:inline-flex;
          align-items:center;
          justify-content:center;
        }
        #<?php echo esc_attr($uid); ?> .h1{
          font-size: 28px;
          font-weight: 900;
          margin: 0;
          line-height: 1.15;
        }
        #<?php echo esc_attr($uid); ?> .lead{
          margin: 8px 0 16px;
          color: var(--muted);
          font-size: 13px;
          line-height: 1.55;
          max-width: 720px;
        }

        /* Current plan bar */
        #<?php echo esc_attr($uid); ?> .currentbar{
          background: var(--soft-red);
          border: 1px solid var(--soft-red-border);
          border-radius: var(--radius);
          padding: 14px;
          display:flex;
          align-items:center;
          gap: 12px;
          box-shadow: var(--shadow);
          margin-bottom: 16px;
        }
        #<?php echo esc_attr($uid); ?> .currentbar .icon{
          width: 34px; height: 34px;
          border-radius: 999px;
          background: var(--brand-red);
          display:flex;
          align-items:center;
          justify-content:center;
          flex: 0 0 auto;
        }
        #<?php echo esc_attr($uid); ?> .currentbar .t1{
          font-weight: 800;
          font-size: 12px;
          margin: 0;
        }
        #<?php echo esc_attr($uid); ?> .currentbar .t2{
          font-size: 11px;
          color: var(--muted);
          margin-top: 2px;
        }

        /* Section card */
        #<?php echo esc_attr($uid); ?> .section{
          background: var(--card);
          border: 1px solid var(--border);
          border-radius: var(--radius-lg);
          box-shadow: var(--shadow);
          margin: 12px 0 16px;
          overflow: hidden;
        }

        /* Accordion header */
        #<?php echo esc_attr($uid); ?> .acc-h{
          width:100%;
          border:0;
          background:#fff;
          padding: 14px 14px;
          display:flex;
          align-items:center;
          justify-content:space-between;
          gap: 12px;
          cursor:pointer;
        }
        #<?php echo esc_attr($uid); ?> .acc-h:focus{ outline:none; box-shadow: 0 0 0 3px rgba(178,58,58,.18) inset; }
        #<?php echo esc_attr($uid); ?> .acc-left{
          display:flex;
          align-items:flex-start;
          gap: 10px;
        }
        #<?php echo esc_attr($uid); ?> .acc-ico{
          width: 26px; height: 26px;
          border-radius: 999px;
          background: #f3ecea;
          display:flex;
          align-items:center;
          justify-content:center;
          flex: 0 0 auto;
        }
        #<?php echo esc_attr($uid); ?> .acc-title{
          font-weight: 900;
          font-size: 13px;
          margin: 0;
          line-height: 1.2;
        }
        #<?php echo esc_attr($uid); ?> .acc-sub{
          font-size: 11px;
          color: var(--muted);
          margin-top: 2px;
          line-height: 1.3;
        }
        #<?php echo esc_attr($uid); ?> .chev{
          width: 18px; height: 18px;
          color: #777;
          transition: transform .16s ease;
          flex: 0 0 auto;
        }
        #<?php echo esc_attr($uid); ?> .section[data-open="1"] .chev{ transform: rotate(180deg); }

        #<?php echo esc_attr($uid); ?> .acc-panel{
          padding: 12px 14px 14px;
          border-top: 1px solid #f0e7e2;
          display:none;
          background: #fff;
        }
        #<?php echo esc_attr($uid); ?> .section[data-open="1"] .acc-panel{ display:block; }

        /* Plans grid */
        #<?php echo esc_attr($uid); ?> .plans{
          display:grid;
          grid-template-columns: 1fr;
          gap: 14px;
          margin-top: 10px;
        }
        @media (min-width: 860px){
          #<?php echo esc_attr($uid); ?> .plans{ grid-template-columns: repeat(3, 1fr); }
        }

        #<?php echo esc_attr($uid); ?> .plan{
          border-radius: 8px;
          background:#fff;
          border: 2px solid #eee;
          padding: 14px;
          position: relative;
          min-height: 260px;
        }
        #<?php echo esc_attr($uid); ?> .plan.bronze{ border-color: var(--bronze-border); }
        #<?php echo esc_attr($uid); ?> .plan.silver{ border-color: var(--silver-border); }
        #<?php echo esc_attr($uid); ?> .plan.gold{ border-color: var(--gold-border); }

        #<?php echo esc_attr($uid); ?> .plan .picon{
          width: 34px; height: 34px;
          border-radius: 999px;
          display:flex; align-items:center; justify-content:center;
          margin-bottom: 10px;
        }
        #<?php echo esc_attr($uid); ?> .plan.bronze .picon{ background: var(--bronze); }
        #<?php echo esc_attr($uid); ?> .plan.silver .picon{ background: var(--silver); }
        #<?php echo esc_attr($uid); ?> .plan.gold .picon{ background: var(--gold); }

        #<?php echo esc_attr($uid); ?> .plan .pname{
          font-weight: 900;
          font-size: 14px;
          margin: 0 0 6px;
        }
        #<?php echo esc_attr($uid); ?> .price{
          font-weight: 900;
          font-size: 18px;
          margin: 0;
          display:flex;
          align-items:baseline;
          gap: 6px;
        }
        #<?php echo esc_attr($uid); ?> .per{
          font-size: 11px;
          font-weight: 700;
          color: var(--muted);
        }
        #<?php echo esc_attr($uid); ?> .ticks{
          list-style:none;
          padding: 10px 0 0;
          margin: 0;
          display:flex;
          flex-direction:column;
          gap: 8px;
          color: #5c5c5c;
          font-size: 11px;
        }
        #<?php echo esc_attr($uid); ?> .tick{
          display:flex; align-items:flex-start; gap: 8px;
          line-height: 1.35;
        }
        #<?php echo esc_attr($uid); ?> .check{
          width: 14px; height: 14px;
          color: var(--brand-red);
          flex: 0 0 auto;
          margin-top: 1px;
        }

        #<?php echo esc_attr($uid); ?> .choose{
          margin-top: 14px;
          width: 100%;
          padding: 10px 12px;
          border-radius: 6px;
          border: 0;
          background: #f3efed;
          color: #3c3c3c;
          font-weight: 800;
          font-size: 11px;
          cursor: pointer;
        }
        #<?php echo esc_attr($uid); ?> .choose:hover{ filter: brightness(.98); }
        #<?php echo esc_attr($uid); ?> .badge{
          position:absolute;
          top: -10px;
          left: 50%;
          transform: translateX(-50%);
          background: var(--brand-red);
          color:#fff;
          font-weight: 900;
          font-size: 10px;
          padding: 4px 10px;
          border-radius: 999px;
          box-shadow: 0 6px 18px rgba(178,58,58,.18);
        }

        /* Pause section */
        #<?php echo esc_attr($uid); ?> .bodytext{
          font-size: 11px;
          color: var(--muted);
          margin: 0 0 10px;
          line-height: 1.5;
        }
        #<?php echo esc_attr($uid); ?> .label{
          font-weight: 900;
          font-size: 11px;
          margin: 8px 0 8px;
          color: #3a3a3a;
        }
        #<?php echo esc_attr($uid); ?> .pause-grid{
          display:grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 10px;
          margin-bottom: 12px;
        }
        @media (min-width: 860px){
          #<?php echo esc_attr($uid); ?> .pause-grid{ grid-template-columns: repeat(4, 1fr); }
        }
        #<?php echo esc_attr($uid); ?> .pill{
          border: 1px solid #eadfdb;
          background: #fff;
          padding: 10px;
          border-radius: 6px;
          font-weight: 900;
          font-size: 11px;
          color: #333;
          cursor: pointer;
          text-align:center;
        }
        #<?php echo esc_attr($uid); ?> .pill[data-active="1"]{
          border-color: rgba(178,58,58,.35);
          box-shadow: 0 0 0 3px rgba(178,58,58,.10);
        }
        #<?php echo esc_attr($uid); ?> .pause-actions{
          display:flex;
          align-items:center;
          justify-content:space-between;
          gap: 12px;
          flex-wrap: wrap;
        }
        #<?php echo esc_attr($uid); ?> .btn{
          display:inline-flex;
          align-items:center;
          justify-content:center;
          padding: 10px 14px;
          border-radius: 6px;
          border: 0;
          cursor:pointer;
          font-weight: 900;
          font-size: 11px;
        }
        #<?php echo esc_attr($uid); ?> .btn-primary{
          background: #d6a2a2;
          color:#fff;
        }
        #<?php echo esc_attr($uid); ?> .btn-primary:hover{ background: #c98f8f; }
        #<?php echo esc_attr($uid); ?> .link{
          font-size: 11px;
          color: var(--muted);
          text-decoration: underline;
          cursor:pointer;
          white-space: nowrap;
        }

        /* Cancel section inner blocks */
        #<?php echo esc_attr($uid); ?> .sadbox{
          margin-top: 6px;
          background: #f6f2f0;
          border: 1px solid #eee3de;
          border-radius: 8px;
          padding: 16px;
          text-align:center;
        }
        #<?php echo esc_attr($uid); ?> .sadbox .heart{
          width: 34px; height: 34px;
          border-radius: 999px;
          margin: 0 auto 8px;
          display:flex; align-items:center; justify-content:center;
          color: var(--brand-red);
          background: #fff;
          border: 1px solid #eadfdb;
        }
        #<?php echo esc_attr($uid); ?> .sadbox .m1{
          font-weight: 900;
          font-size: 11px;
          color:#3a3a3a;
          margin: 0 0 6px;
        }
        #<?php echo esc_attr($uid); ?> .sadbox .m2{
          font-size: 11px;
          color: var(--muted);
          margin: 0;
          line-height: 1.45;
        }
        #<?php echo esc_attr($uid); ?> .warn{
          margin-top: 12px;
          background: var(--warn-bg);
          border: 1px solid var(--warn-border);
          border-radius: 8px;
          padding: 12px;
          display:flex;
          gap: 10px;
          align-items:flex-start;
          color: var(--warn-text);
          font-size: 11px;
          line-height: 1.45;
        }
        #<?php echo esc_attr($uid); ?> .warn .wico{
          width: 18px; height: 18px;
          margin-top: 1px;
          flex: 0 0 auto;
        }
        #<?php echo esc_attr($uid); ?> .warn strong{ display:block; margin-bottom: 4px; color:#8b5a00; }

        #<?php echo esc_attr($uid); ?> .cancel-actions{
          display:flex;
          gap: 10px;
          margin-top: 12px;
          flex-wrap: wrap;
        }
        #<?php echo esc_attr($uid); ?> .btn-outline-red{
          background: #fff;
          border: 1px solid #e5b4b4;
          color: var(--brand-red);
        }
        #<?php echo esc_attr($uid); ?> .btn-outline-red:hover{ background: #fff5f5; }

        #<?php echo esc_attr($uid); ?> .btn-solid-red{
          background: var(--brand-red);
          color:#fff;
        }
        #<?php echo esc_attr($uid); ?> .btn-solid-red:hover{ background: var(--brand-red-2); }

        /* "See the Lives" */
        #<?php echo esc_attr($uid); ?> .h3{
          font-weight: 900;
          font-size: 14px;
          margin: 18px 0 0;
        }
        #<?php echo esc_attr($uid); ?> .h3sub{
          margin: 4px 0 10px;
          font-size: 11px;
          color: var(--muted);
        }

        /* Carousel */
        #<?php echo esc_attr($uid); ?> .carousel{
          background: #000;
          border-radius: 10px;
          overflow:hidden;
          position: relative;
          border: 1px solid #e7dfdb;
        }
        #<?php echo esc_attr($uid); ?> .slide{
          display:none;
          position: relative;
          height: 280px;
        }
        @media (min-width: 860px){
          #<?php echo esc_attr($uid); ?> .slide{ height: 320px; }
        }
        #<?php echo esc_attr($uid); ?> .slide[data-active="1"]{ display:block; }
        #<?php echo esc_attr($uid); ?> .slide img{
          width: 100%;
          height: 100%;
          object-fit: cover;
          opacity: .92;
        }
        #<?php echo esc_attr($uid); ?> .overlay{
          position:absolute;
          left: 0; right: 0; bottom: 0;
          padding: 14px;
          background: linear-gradient(to top, rgba(0,0,0,.65), rgba(0,0,0,0));
          color:#fff;
        }
        #<?php echo esc_attr($uid); ?> .quote{
          font-weight: 900;
          font-size: 12px;
          line-height: 1.35;
          margin: 0 0 10px;
        }
        #<?php echo esc_attr($uid); ?> .who2{
          font-weight: 900;
          font-size: 11px;
          margin: 0;
        }
        #<?php echo esc_attr($uid); ?> .org2{
          font-size: 10px;
          opacity: .85;
          margin-top: 2px;
        }
        #<?php echo esc_attr($uid); ?> .navbtn{
          position:absolute;
          top: 50%;
          transform: translateY(-50%);
          width: 30px; height: 30px;
          border-radius: 999px;
          border: 0;
          background: rgba(0,0,0,.35);
          color:#fff;
          display:flex;
          align-items:center;
          justify-content:center;
          cursor:pointer;
        }
        #<?php echo esc_attr($uid); ?> .navbtn:hover{ background: rgba(0,0,0,.45); }
        #<?php echo esc_attr($uid); ?> .navbtn.prev{ left: 10px; }
        #<?php echo esc_attr($uid); ?> .navbtn.next{ right: 10px; }

        #<?php echo esc_attr($uid); ?> .dots{
          display:flex;
          justify-content:center;
          gap: 8px;
          padding: 10px 0 0;
        }
        #<?php echo esc_attr($uid); ?> .dot{
          width: 22px; height: 6px;
          border-radius: 999px;
          background: #e8dedd;
          border: 0;
          cursor:pointer;
        }
        #<?php echo esc_attr($uid); ?> .dot[data-active="1"]{ background: var(--brand-red); }

        /* Impact bar */
        #<?php echo esc_attr($uid); ?> .impact{
          margin-top: 12px;
          background: #f6f2f0;
          border: 1px solid #eee3de;
          border-radius: 10px;
          padding: 14px;
        }
        #<?php echo esc_attr($uid); ?> .impact-title{
          text-align:center;
          font-weight: 900;
          font-size: 12px;
          margin-bottom: 10px;
          color: #3a3a3a;
        }
        #<?php echo esc_attr($uid); ?> .impact-grid{
          display:grid;
          grid-template-columns: 1fr;
          gap: 10px;
          text-align:center;
        }
        @media (min-width: 860px){
          #<?php echo esc_attr($uid); ?> .impact-grid{ grid-template-columns: repeat(3, 1fr); }
        }
        #<?php echo esc_attr($uid); ?> .impact-num{
          font-weight: 900;
          font-size: 18px;
          color: var(--brand-red);
          margin: 0;
        }
        #<?php echo esc_attr($uid); ?> .impact-lbl{
          font-size: 10px;
          color: var(--muted);
          margin-top: 2px;
        }

        /* Modal (kept minimal; screenshot doesn’t show modals, but you requested popups exist) */
        #<?php echo esc_attr($uid); ?> .modal{
          position: fixed;
          inset: 0;
          display:none;
          align-items:center;
          justify-content:center;
          padding: 18px;
          z-index: 999999;
        }
        #<?php echo esc_attr($uid); ?> .modal[data-open="1"]{ display:flex; }
        #<?php echo esc_attr($uid); ?> .backdrop{
          position:absolute; inset:0;
          background: rgba(0,0,0,.45);
        }
        #<?php echo esc_attr($uid); ?> .mcard{
          position: relative;
          width: min(560px, 100%);
          background:#fff;
          border-radius: 12px;
          border: 1px solid #eadfdb;
          box-shadow: 0 20px 60px rgba(0,0,0,.22);
          overflow:hidden;
        }
        #<?php echo esc_attr($uid); ?> .mhead{
          padding: 12px 14px;
          border-bottom: 1px solid #f0e7e2;
          display:flex;
          align-items:flex-start;
          justify-content:space-between;
          gap: 12px;
        }
        #<?php echo esc_attr($uid); ?> .mtitle{
          font-weight: 900;
          font-size: 13px;
          margin: 0;
        }
        #<?php echo esc_attr($uid); ?> .mdesc{
          margin-top: 4px;
          font-size: 11px;
          color: var(--muted);
          line-height: 1.45;
        }
        #<?php echo esc_attr($uid); ?> .mbody{ padding: 12px 14px; }
        #<?php echo esc_attr($uid); ?> .mfoot{
          padding: 12px 14px;
          border-top: 1px solid #f0e7e2;
          display:flex;
          justify-content:flex-end;
          gap: 10px;
          flex-wrap: wrap;
        }
        #<?php echo esc_attr($uid); ?> .xbtn{
          width: 30px; height: 30px;
          border-radius: 8px;
          border: 1px solid #eadfdb;
          background:#fff;
          cursor:pointer;
          display:flex;
          align-items:center;
          justify-content:center;
        }
        #<?php echo esc_attr($uid); ?> .xbtn:hover{ background:#faf7f6; }

        #<?php echo esc_attr($uid); ?> .field{ margin-top: 10px; }
        #<?php echo esc_attr($uid); ?> .inlabel{ font-size: 11px; font-weight: 900; margin-bottom: 6px; color:#3a3a3a; }
        #<?php echo esc_attr($uid); ?> .input{
          width: 100%;
          border: 1px solid #eadfdb;
          border-radius: 8px;
          padding: 10px 10px;
          font-size: 12px;
        }
      </style>

      <div class="wrap">

        <!-- Header -->
        <div class="topbar">
          <span class="spark" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none">
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
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
              <path d="M12 21s-7-4.5-9.5-9C.8 8.7 3 6 6 6c1.7 0 3.2.9 4 2.2C10.8 6.9 12.3 6 14 6c3 0 5.2 2.7 3.5 6-2.5 4.5-9.5 9-9.5 9Z" stroke="#fff" stroke-width="2" stroke-linejoin="round"/>
            </svg>
          </div>
          <div>
            <p class="t1">Current Plan: <?php echo $current_plan; ?></p>
            <div class="t2"><?php echo $current_price; ?> / <?php echo $current_interval; ?> · Next billing date: <?php echo $next_bill; ?></div>
          </div>
        </div>

        <!-- Accordion: Upgrade/Downgrade -->
        <section class="section" data-acc data-open="1">
          <button class="acc-h" type="button" data-acc-toggle aria-expanded="true">
            <div class="acc-left">
              <div class="acc-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none">
                  <path d="M5 10l7-6 7 6v9a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-9Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                </svg>
              </div>
              <div>
                <div class="acc-title">Upgrade or Downgrade Your Membership</div>
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
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                    <path d="M12 2l7 4v6c0 5-3 9-7 10C8 21 5 17 5 12V6l7-4Z" stroke="#fff" stroke-width="2" stroke-linejoin="round"/>
                  </svg>
                </div>
                <div class="pname" style="color:var(--bronze);">Bronze</div>
                <div class="price">$4.99 <span class="per">/ Monthly</span></div>

                <ul class="ticks">
                  <li class="tick">
                    <svg class="check" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    10% off all purchases
                  </li>
                  <li class="tick">
                    <svg class="check" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Free shipping on orders $25+
                  </li>
                  <li class="tick">
                    <svg class="check" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    5 meals donated monthly
                  </li>
                </ul>

                <button class="choose" type="button" data-open-modal="planModal" data-plan="Bronze" data-price="$4.99 / Monthly">Choose Bronze</button>
              </div>

              <!-- Silver (Most Popular) -->
              <div class="plan silver">
                <div class="badge">Most Popular</div>
                <div class="picon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
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
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
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

        <!-- Accordion: Pause -->
        <section class="section" data-acc data-open="1">
          <button class="acc-h" type="button" data-acc-toggle aria-expanded="true">
            <div class="acc-left">
              <div class="acc-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none">
                  <path d="M8 6v12M16 6v12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
              </div>
              <div>
                <div class="acc-title">Pause My Membership</div>
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

        <!-- Accordion: Cancel -->
        <section class="section" data-acc data-open="1">
          <button class="acc-h" type="button" data-acc-toggle aria-expanded="true">
            <div class="acc-left">
              <div class="acc-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none">
                  <path d="M9 9l6 6M15 9l-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                  <path d="M12 22A10 10 0 1 0 12 2a10 10 0 0 0 0 20Z" stroke="currentColor" stroke-width="2"/>
                </svg>
              </div>
              <div>
                <div class="acc-title">Cancel My Membership</div>
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
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
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
                You'll lose access to exclusive VIP discounts<br>
                Your monthly meal donations will stop<br>
                You can pause your membership instead
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
          <!-- Slide 1 -->
          <div class="slide" data-slide="0" data-active="0">
            <img src="<?php echo esc_url($slide_1); ?>" alt="">
            <div class="overlay">
              <p class="quote">"Donated meals let us redirect funds to life-saving surgeries. Last year, 200+ emergency operations happened because of Hero VIP members."</p>
              <p class="who2">Dr. Sarah Mitchell</p>
              <div class="org2">Second Chance Veterinary Rescue, Austin, TX</div>
            </div>
          </div>

          <!-- Slide 2 (active like your screenshot example) -->
          <div class="slide" data-slide="1" data-active="1">
            <img src="<?php echo esc_url($slide_2); ?>" alt="">
            <div class="overlay">
              <p class="quote">"We fly at-risk shelter dogs to areas with adoption demand. Hero VIP Club funds the flights that give these dogs a second chance at life."</p>
              <p class="who2">Captain Mike Reynolds</p>
              <div class="org2">Wings of Rescue Foundation, Nashville, TN</div>
            </div>
          </div>

          <!-- Slide 3 -->
          <div class="slide" data-slide="2" data-active="0">
            <img src="<?php echo esc_url($slide_3); ?>" alt="">
            <div class="overlay">
              <p class="quote">"Food is our biggest cost with 80+ dogs in care. iHeartDogs donations keep our doors open — without them, we'd have to turn dogs away."</p>
              <p class="who2">Jennifer Ortiz</p>
              <div class="org2">Paws & Hope Animal Shelter, Portland, OR</div>
            </div>
          </div>

          <button class="navbtn prev" type="button" aria-label="Previous" data-prev>
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <button class="navbtn next" type="button" aria-label="Next" data-next>
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
        </div>

        <div class="dots" data-dots>
          <button class="dot" type="button" data-dot="0" data-active="0" aria-label="Slide 1"></button>
          <button class="dot" type="button" data-dot="1" data-active="1" aria-label="Slide 2"></button>
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

      <!-- ==========================================================
           Modals (static but functional)
           ========================================================== -->

      <!-- Plan modal -->
      <div class="modal" data-modal="planModal" role="dialog" aria-modal="true">
        <div class="backdrop" data-close-modal></div>
        <div class="mcard" role="document">
          <div class="mhead">
            <div>
              <p class="mtitle">Confirm Plan Selection</p>
              <div class="mdesc">Static confirmation modal.</div>
            </div>
            <button class="xbtn" type="button" aria-label="Close" data-close-modal>
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
          </div>
          <div class="mbody">
            <div style="font-weight:900;font-size:12px;">Selected: <span data-plan-name>Silver</span></div>
            <div style="color:var(--muted);font-size:11px;margin-top:4px;">Price: <span data-plan-price>$9.99 / Monthly</span></div>
            <div style="margin-top:10px;color:var(--muted);font-size:11px;line-height:1.45;">
              Next billing date: <?php echo $next_bill; ?> (static)
            </div>
          </div>
          <div class="mfoot">
            <button type="button" class="btn btn-outline-red" data-close-modal>Cancel</button>
            <button type="button" class="btn btn-solid-red" data-close-modal>Confirm</button>
          </div>
        </div>
      </div>

      <!-- Pause modal -->
      <div class="modal" data-modal="pauseModal" role="dialog" aria-modal="true">
        <div class="backdrop" data-close-modal></div>
        <div class="mcard" role="document">
          <div class="mhead">
            <div>
              <p class="mtitle">Confirm Pause</p>
              <div class="mdesc">Static UI. Selected duration: <strong><span data-pause-days>30</span> days</strong>.</div>
            </div>
            <button class="xbtn" type="button" aria-label="Close" data-close-modal>
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
          </div>
          <div class="mbody">
            <div class="field">
              <div class="inlabel">Reason (static)</div>
              <input class="input" type="text" value="Taking a short break" readonly>
            </div>
          </div>
          <div class="mfoot">
            <button type="button" class="btn btn-outline-red" data-close-modal>Back</button>
            <button type="button" class="btn btn-solid-red" data-close-modal>Confirm Pause</button>
          </div>
        </div>
      </div>

      <!-- Cancel modal -->
      <div class="modal" data-modal="cancelModal" role="dialog" aria-modal="true">
        <div class="backdrop" data-close-modal></div>
        <div class="mcard" role="document">
          <div class="mhead">
            <div>
              <p class="mtitle">Cancel Membership</p>
              <div class="mdesc">Static modal. This would later call your billing cancellation.</div>
            </div>
            <button class="xbtn" type="button" aria-label="Close" data-close-modal>
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
          </div>
          <div class="mbody">
            <div style="font-size:11px;color:var(--muted);line-height:1.45;">
              You’ll keep benefits until <?php echo $next_bill; ?> (static).
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
              <div class="mdesc">Static confirmation.</div>
            </div>
            <button class="xbtn" type="button" aria-label="Close" data-close-modal>
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
          </div>
          <div class="mbody">
            <div style="font-weight:900;font-size:12px;">Are you sure you want to cancel?</div>
            <div style="font-size:11px;color:var(--muted);margin-top:6px;line-height:1.45;">
              This is static UI. Your membership would end after the current period.
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
              <div class="mdesc">Static modal.</div>
            </div>
            <button class="xbtn" type="button" aria-label="Close" data-close-modal>
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
          </div>
          <div class="mbody">
            <div style="font-size:11px;color:var(--muted);line-height:1.45;">
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

          /* -----------------------------
             Accordions (OPEN by default to match screenshot)
             ----------------------------- */
          root.querySelectorAll('[data-acc]').forEach(sec => {
            const btn = sec.querySelector('[data-acc-toggle]');
            if (!btn) return;

            // Keep as set in HTML (data-open="1")
            btn.setAttribute('aria-expanded', sec.getAttribute('data-open') === '1' ? 'true' : 'false');

            btn.addEventListener('click', () => {
              const open = sec.getAttribute('data-open') === '1';
              sec.setAttribute('data-open', open ? '0' : '1');
              btn.setAttribute('aria-expanded', open ? 'false' : 'true');
            });
          });

          /* -----------------------------
             Pause duration selection
             ----------------------------- */
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

          /* -----------------------------
             Carousel
             ----------------------------- */
          const carousel = root.querySelector('[data-carousel]');
          const slides = carousel ? Array.from(carousel.querySelectorAll('[data-slide]')) : [];
          const dotsWrap = root.querySelector('[data-dots]');
          const dots = dotsWrap ? Array.from(dotsWrap.querySelectorAll('[data-dot]')) : [];

          let idx = slides.findIndex(s => s.getAttribute('data-active') === '1');
          if (idx < 0) idx = 0;

          function setActive(i){
            idx = (i + slides.length) % slides.length;
            slides.forEach((s, n) => s.setAttribute('data-active', n === idx ? '1' : '0'));
            dots.forEach((d, n) => d.setAttribute('data-active', n === idx ? '1' : '0'));
          }

          if (carousel) {
            const prev = carousel.querySelector('[data-prev]');
            const next = carousel.querySelector('[data-next]');
            if (prev) prev.addEventListener('click', () => setActive(idx - 1));
            if (next) next.addEventListener('click', () => setActive(idx + 1));
          }
          if (dotsWrap) {
            dotsWrap.addEventListener('click', (e) => {
              const d = e.target.closest('[data-dot]');
              if (!d) return;
              setActive(parseInt(d.getAttribute('data-dot') || '0', 10));
            });
          }

          /* -----------------------------
             Modals
             ----------------------------- */
          const modals = Array.from(root.querySelectorAll('.modal[data-modal]'));
          let lastFocus = null;

          function openModal(key){
            const m = root.querySelector('.modal[data-modal="'+key+'"]');
            if (!m) return;
            lastFocus = document.activeElement;
            m.setAttribute('data-open','1');
            document.documentElement.style.overflow = 'hidden';
            document.body.style.overflow = 'hidden';

            const focusable = m.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (focusable.length) focusable[0].focus();
          }

          function closeModal(m){
            if (!m) return;
            m.setAttribute('data-open','0');
            document.documentElement.style.overflow = '';
            document.body.style.overflow = '';
            if (lastFocus && typeof lastFocus.focus === 'function') { try { lastFocus.focus(); } catch(e){} }
            lastFocus = null;
          }

          root.addEventListener('click', (e) => {
            const openBtn = e.target.closest('[data-open-modal]');
            if (openBtn) {
              const key = openBtn.getAttribute('data-open-modal');

              // Plan modal content fill
              if (key === 'planModal') {
                const pn = openBtn.getAttribute('data-plan') || 'Silver';
                const pp = openBtn.getAttribute('data-price') || '$9.99 / Monthly';
                const n = root.querySelector('[data-plan-name]');
                const p = root.querySelector('[data-plan-price]');
                if (n) n.textContent = pn;
                if (p) p.textContent = pp;
              }

              // Pause modal content fill
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

Hero_VIP_Manage_Subscription_Refined_Shortcode::init();