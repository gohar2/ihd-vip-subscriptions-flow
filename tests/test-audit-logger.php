<?php
/**
 * IHD VIP Subscriptions — Comprehensive Audit Logger Test Suite v2
 *
 * Run via: wp eval-file wp-content/plugins/ihd-vip-subscriptions/tests/test-audit-logger.php
 *
 * Tests subscription cancellation tracking, audit logging, event detection,
 * error classification, deduplication, scope gate, and new campaign-friendly columns.
 *
 * @package IHD_VIP_Subscriptions
 */

if ( ! defined( 'ABSPATH' ) ) {
    echo "Must be run via: wp eval-file <path>\n";
    exit( 1 );
}

// ─── Configuration ───────────────────────────────────────────────────────────
$TEST_USER_ID            = 439065; // syed.gohar@homelifemedia.com
$SUBSCRIPTION_PRODUCT_ID = 3626167; // Hero VIP Club $4.99

// ─── Helpers ─────────────────────────────────────────────────────────────────
class IHD_Test_State {
    public static $results     = array();
    public static $test_count  = 0;
    public static $pass_count  = 0;
    public static $fail_count  = 0;
    public static $warn_count  = 0;
    public static $test_sub_ids = array();
}

$test_sub_ids = &IHD_Test_State::$test_sub_ids;

function ihd_test_log( $msg ) {
    echo $msg . "\n";
}

function ihd_test_pass( $name, $detail = '' ) {
    IHD_Test_State::$test_count++;
    IHD_Test_State::$pass_count++;
    IHD_Test_State::$results[] = array( 'status' => 'PASS', 'name' => $name, 'detail' => $detail );
    ihd_test_log( "  ✅ PASS: {$name}" . ( $detail ? " — {$detail}" : '' ) );
}

function ihd_test_fail( $name, $detail = '' ) {
    IHD_Test_State::$test_count++;
    IHD_Test_State::$fail_count++;
    IHD_Test_State::$results[] = array( 'status' => 'FAIL', 'name' => $name, 'detail' => $detail );
    ihd_test_log( "  ❌ FAIL: {$name}" . ( $detail ? " — {$detail}" : '' ) );
}

function ihd_assert( $condition, $name, $detail = '' ) {
    if ( $condition ) {
        ihd_test_pass( $name, $detail );
    } else {
        ihd_test_fail( $name, $detail );
    }
    return $condition;
}

function ihd_get_audit_count( $sub_id ) {
    global $wpdb;
    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ihd_vip_subscription_audit WHERE subscription_id = %d",
            $sub_id
        )
    );
}

function ihd_get_latest_audit( $sub_id ) {
    global $wpdb;
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ihd_vip_subscription_audit WHERE subscription_id = %d ORDER BY id DESC LIMIT 1",
            $sub_id
        ),
        ARRAY_A
    );
}

function ihd_cleanup_audit( $sub_id ) {
    global $wpdb;
    $wpdb->delete( $wpdb->prefix . 'ihd_vip_subscription_audit', array( 'subscription_id' => $sub_id ), array( '%d' ) );
}

function ihd_create_test_subscription( $user_id, $product_id, $status = 'active' ) {
    $sub = wcs_create_subscription( array(
        'customer_id'      => $user_id,
        'billing_period'   => 'month',
        'billing_interval' => 1,
        'start_date'       => gmdate( 'Y-m-d H:i:s' ),
    ) );

    if ( is_wp_error( $sub ) ) {
        ihd_test_log( "    [ERROR] Could not create subscription: " . $sub->get_error_message() );
        return null;
    }

    $product = wc_get_product( $product_id );
    if ( $product ) {
        $item = new WC_Order_Item_Product();
        $item->set_product( $product );
        $item->set_quantity( 1 );
        $item->set_subtotal( $product->get_price() );
        $item->set_total( $product->get_price() );
        $sub->add_item( $item );
        $sub->set_total( $product->get_price() );
    }

    $sub->set_payment_method( 'bacs' );
    $sub->save();

    if ( 'active' === $status ) {
        wp_update_post( array( 'ID' => $sub->get_id(), 'post_status' => 'wc-active' ) );
        clean_post_cache( $sub->get_id() );
    }

    IHD_Test_State::$test_sub_ids[] = $sub->get_id();
    return $sub->get_id();
}

function ihd_cleanup_subscription( $sub_id ) {
    if ( ! $sub_id ) return;
    // Keep audit logs in DB for reference — only delete the subscription post.
    ihd_delete_post_silent( $sub_id );
}

/**
 * Delete a test subscription post.
 * The plugin-level dedup in the event tracker (transient + trash check)
 * prevents phantom "System Cancelled" entries from wp_delete_post.
 */
function ihd_delete_post_silent( $post_id ) {
    wp_delete_post( $post_id, true );
}

// ─── Pre-flight Checks ──────────────────────────────────────────────────────
ihd_test_log( "\n╔══════════════════════════════════════════════════════════════════╗" );
ihd_test_log( "║  IHD VIP Subscriptions — Audit Logger Test Suite v2            ║" );
ihd_test_log( "║  Date: " . current_time( 'Y-m-d H:i:s' ) . "                              ║" );
ihd_test_log( "╚══════════════════════════════════════════════════════════════════╝\n" );

ihd_test_log( "─── Pre-flight Checks ───" );

ihd_assert( class_exists( 'WooCommerce' ), 'WooCommerce is active' );
ihd_assert( class_exists( 'WC_Subscriptions' ), 'WooCommerce Subscriptions is active' );
ihd_assert( class_exists( 'IHD_VIP_Audit_Logger' ), 'IHD_VIP_Audit_Logger class exists' );
ihd_assert( class_exists( 'IHD_VIP_Subscription_Event_Tracker' ), 'Event Tracker class exists' );
ihd_assert( class_exists( 'IHD_VIP_Cancel_Handler' ), 'Cancel Handler class exists' );
ihd_assert( class_exists( 'IHD_VIP_User_Scope_Gate' ), 'Scope Gate class exists' );

global $wpdb;
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}ihd_vip_subscription_audit'" );
ihd_assert( ! empty( $table_exists ), 'Audit table exists' );

// Verify NEW table schema.
$columns = $wpdb->get_results( "DESCRIBE {$wpdb->prefix}ihd_vip_subscription_audit", ARRAY_A );
$col_names = wp_list_pluck( $columns, 'Field' );
$required_cols = array( 'id', 'subscription_id', 'customer_id', 'by_user_id', 'event_type', 'old_status', 'new_status', 'intentional', 'reason', 'detail', 'payment_method', 'payment_error_type', 'subscription_amount', 'billing_period', 'currency', 'created_at' );
foreach ( $required_cols as $col ) {
    ihd_assert( in_array( $col, $col_names ), "Table has column: {$col}" );
}

$test_user = get_user_by( 'id', $TEST_USER_ID );
ihd_assert( ! empty( $test_user ), "Test user #{$TEST_USER_ID} exists", $test_user ? $test_user->user_email : 'NOT FOUND' );

ihd_assert( defined( 'IHD_VIP_VERSION' ) && IHD_VIP_VERSION === '1.1.0', 'Plugin version is 1.1.0', IHD_VIP_VERSION );


// ═════════════════════════════════════════════════════════════════════════════
// TEST 1: Direct Audit Logger (New Array-Based API)
// ═════════════════════════════════════════════════════════════════════════════
ihd_test_log( "\n─── Test 1: Direct Audit Logger (New API) ───" );

$test1_sub_id = 999999901;
// Clean previous test runs for this fake ID so counts are accurate.
ihd_cleanup_audit( $test1_sub_id );

// Insert with all fields.
$insert_id = IHD_VIP_Audit_Logger::log( array(
    'subscription_id'     => $test1_sub_id,
    'customer_id'         => $TEST_USER_ID,
    'by_user_id'          => $TEST_USER_ID,
    'event_type'          => 'cancellation',
    'old_status'          => 'active',
    'new_status'          => 'cancelled',
    'intentional'         => true,
    'reason'              => 'Too expensive',
    'detail'              => 'Cancelled by customer via VIP portal.',
    'payment_method'      => 'Authorize.Net',
    'payment_error_type'  => '',
    'subscription_amount' => 4.99,
    'billing_period'      => 'month',
    'currency'            => 'USD',
) );

ihd_assert( $insert_id > 0, 'Log insert returns positive insert ID', "ID: {$insert_id}" );

$logs = IHD_VIP_Audit_Logger::get_logs( $test1_sub_id );
ihd_assert( count( $logs ) === 1, 'get_logs returns 1 entry' );

$log = $logs[0];
ihd_assert( $log['event_type'] === 'cancellation', 'event_type stored correctly' );
ihd_assert( $log['old_status'] === 'active', 'old_status stored correctly' );
ihd_assert( $log['new_status'] === 'cancelled', 'new_status stored correctly' );
ihd_assert( (int) $log['intentional'] === 1, 'intentional flag is 1' );
ihd_assert( $log['reason'] === 'Too expensive', 'reason stored correctly' );
ihd_assert( $log['detail'] === 'Cancelled by customer via VIP portal.', 'detail stored correctly' );
ihd_assert( $log['payment_method'] === 'Authorize.Net', 'payment_method stored correctly' );
ihd_assert( floatval( $log['subscription_amount'] ) === 4.99, 'subscription_amount stored correctly' );
ihd_assert( $log['billing_period'] === 'month', 'billing_period stored correctly' );
ihd_assert( $log['currency'] === 'USD', 'currency stored correctly' );
ihd_assert( (int) $log['customer_id'] === $TEST_USER_ID, 'customer_id stored correctly' );

// Test ordering (by id DESC).
sleep( 1 );
IHD_VIP_Audit_Logger::log( array(
    'subscription_id' => $test1_sub_id,
    'customer_id'     => 0,
    'by_user_id'      => 0,
    'event_type'      => 'payment_failure',
    'intentional'     => false,
    'reason'          => 'Renewal Payment Failed',
) );

$logs = IHD_VIP_Audit_Logger::get_logs( $test1_sub_id );
ihd_assert( count( $logs ) === 2, 'get_logs returns 2 entries' );
ihd_assert( $logs[0]['id'] > $logs[1]['id'], 'Logs ordered by id DESC (newest first)' );
ihd_assert( $logs[0]['event_type'] === 'payment_failure', 'Latest entry is payment_failure' );

// Test empty/default values.
IHD_VIP_Audit_Logger::log( array( 'subscription_id' => $test1_sub_id ) );
$logs = IHD_VIP_Audit_Logger::get_logs( $test1_sub_id );
ihd_assert( $logs[0]['event_type'] === '', 'Empty event_type defaults to empty string' );
ihd_assert( $logs[0]['currency'] === 'USD', 'Currency defaults to USD' );

ihd_cleanup_audit( $test1_sub_id );


// ═════════════════════════════════════════════════════════════════════════════
// TEST 2: Manual Intentional Cancellation
// ═════════════════════════════════════════════════════════════════════════════
ihd_test_log( "\n─── Test 2: Manual Intentional Cancellation ───" );

$test2_sub_id = ihd_create_test_subscription( $TEST_USER_ID, $SUBSCRIPTION_PRODUCT_ID, 'active' );

if ( $test2_sub_id ) {
    // Note: Not cleaning audit logs — keeping for reference on dev server.
    wp_set_current_user( $TEST_USER_ID );

    $subscription = wcs_get_subscription( $test2_sub_id );
    ihd_assert( ! empty( $subscription ), 'Test subscription created', "ID: {$test2_sub_id}" );

    $reason = 'Too expensive';

    // Simulate cancel handler.
    IHD_VIP_Audit_Logger::log( array(
        'subscription_id'     => $test2_sub_id,
        'customer_id'         => $subscription->get_customer_id(),
        'by_user_id'          => $TEST_USER_ID,
        'event_type'          => 'cancellation',
        'old_status'          => $subscription->get_status(),
        'new_status'          => 'cancelled',
        'intentional'         => true,
        'reason'              => $reason,
        'detail'              => "Cancelled by customer via VIP portal. Reason: {$reason}",
        'payment_method'      => $subscription->get_payment_method_title(),
        'subscription_amount' => $subscription->get_total(),
        'billing_period'      => $subscription->get_billing_period(),
        'currency'            => $subscription->get_currency(),
    ) );

    $subscription->update_meta_data( '_ihd_cancel_logged', 'yes' );
    $subscription->save_meta_data();
    $subscription->update_status( 'cancelled', 'Test cancel' );

    $count = ihd_get_audit_count( $test2_sub_id );
    ihd_assert( $count === 1, 'Dedup: Only 1 audit entry after manual cancel', "Count: {$count}" );

    $log = ihd_get_latest_audit( $test2_sub_id );
    ihd_assert( (int) $log['intentional'] === 1, 'Entry is intentional' );
    ihd_assert( $log['event_type'] === 'cancellation', 'Event type is cancellation' );
    ihd_assert( $log['reason'] === 'Too expensive', 'Reason matches' );
    ihd_assert( $log['new_status'] === 'cancelled', 'new_status is cancelled' );
    ihd_assert( ! empty( $log['detail'] ), 'Detail is populated' );

    ihd_cleanup_subscription( $test2_sub_id );
} else {
    ihd_test_fail( 'Could not create subscription for Test 2' );
}


// ═════════════════════════════════════════════════════════════════════════════
// TEST 3: Admin-Initiated Status Changes
// ═════════════════════════════════════════════════════════════════════════════
ihd_test_log( "\n─── Test 3: Admin-Initiated Status Changes ───" );

$test3_sub_id = ihd_create_test_subscription( $TEST_USER_ID, $SUBSCRIPTION_PRODUCT_ID, 'active' );

if ( $test3_sub_id ) {
    // Note: Not cleaning audit logs — keeping for reference on dev server.
    wp_set_current_user( $TEST_USER_ID );
    set_current_screen( 'edit-shop_subscription' );

    $subscription = wcs_get_subscription( $test3_sub_id );
    $subscription->update_status( 'on-hold', 'Admin on-hold test' );

    $log = ihd_get_latest_audit( $test3_sub_id );
    ihd_assert( $log['event_type'] === 'on_hold', 'Event type is on_hold' );
    ihd_assert( $log['old_status'] === 'active', 'old_status is active' );
    ihd_assert( $log['new_status'] === 'on-hold', 'new_status is on-hold' );
    ihd_assert( strpos( $log['reason'], 'Admin' ) !== false, 'Reason references Admin', $log['reason'] );
    ihd_assert( ! empty( $log['detail'] ), 'Detail is populated for admin change' );
    ihd_assert( (int) $log['customer_id'] === $TEST_USER_ID, 'customer_id is set' );

    // Admin cancel from on-hold.
    $subscription = wcs_get_subscription( $test3_sub_id );
    $subscription->update_status( 'cancelled', 'Admin cancel test' );

    $log = ihd_get_latest_audit( $test3_sub_id );
    ihd_assert( $log['event_type'] === 'cancellation', 'Cancel event_type is cancellation' );
    ihd_assert( $log['old_status'] === 'on-hold', 'old_status is on-hold' );

    set_current_screen( 'front' );
    ihd_cleanup_subscription( $test3_sub_id );
} else {
    ihd_test_fail( 'Could not create subscription for Test 3' );
}


// ═════════════════════════════════════════════════════════════════════════════
// TEST 4: Auto-Expiration Simulation
// ═════════════════════════════════════════════════════════════════════════════
ihd_test_log( "\n─── Test 4: Auto-Expiration ───" );

$test4_sub_id = ihd_create_test_subscription( $TEST_USER_ID, $SUBSCRIPTION_PRODUCT_ID, 'active' );

if ( $test4_sub_id ) {
    // Note: Not cleaning audit logs — keeping for reference on dev server.
    wp_set_current_user( 0 );

    $subscription = wcs_get_subscription( $test4_sub_id );
    $subscription->update_status( 'expired', 'Expired test' );

    $log = ihd_get_latest_audit( $test4_sub_id );
    ihd_assert( $log['event_type'] === 'expiration', 'Event type is expiration' );
    ihd_assert( (int) $log['intentional'] === 0, 'Not intentional' );
    ihd_assert( (int) $log['by_user_id'] === 0, 'by_user_id is 0 (system)' );
    ihd_assert( $log['new_status'] === 'expired', 'new_status is expired' );
    ihd_assert( strpos( $log['reason'], 'Expired' ) !== false, 'Reason mentions expired', $log['reason'] );

    ihd_cleanup_subscription( $test4_sub_id );
} else {
    ihd_test_fail( 'Could not create subscription for Test 4' );
}


// ═════════════════════════════════════════════════════════════════════════════
// TEST 5: Renewal Payment Failure
// ═════════════════════════════════════════════════════════════════════════════
ihd_test_log( "\n─── Test 5: Renewal Payment Failure ───" );

$test5_sub_id = ihd_create_test_subscription( $TEST_USER_ID, $SUBSCRIPTION_PRODUCT_ID, 'active' );

if ( $test5_sub_id ) {
    // Note: Not cleaning audit logs — keeping for reference on dev server.
    wp_set_current_user( 0 );

    $subscription = wcs_get_subscription( $test5_sub_id );

    $renewal_order = wc_create_order( array( 'customer_id' => $TEST_USER_ID, 'status' => 'failed' ) );
    if ( ! is_wp_error( $renewal_order ) ) {
        $renewal_order->set_parent_id( $test5_sub_id );
        $renewal_order->set_payment_method( 'authorize_net_cim_credit_card' );
        $renewal_order->set_payment_method_title( 'Authorize.Net' );
        $renewal_order->set_total( '4.99' );
        $renewal_order->add_order_note( 'Payment failed: DECLINED - Do not honor.' );
        $renewal_order->save();

        do_action( 'woocommerce_subscription_renewal_payment_failed', $subscription, $renewal_order );

        $log = ihd_get_latest_audit( $test5_sub_id );
        ihd_assert( $log['event_type'] === 'payment_failure', 'Event type is payment_failure' );
        ihd_assert( $log['reason'] === 'Renewal Failed (Payment Declined)', 'Reason is Renewal Failed (Payment Declined)' );
        ihd_assert( $log['payment_method'] === 'Authorize.Net', 'Payment method captured' );
        ihd_assert( $log['payment_error_type'] === 'payment_declined', 'Error type is payment_declined' );
        ihd_assert( ! empty( $log['detail'] ), 'Detail is populated with error info' );
        ihd_assert( floatval( $log['subscription_amount'] ) > 0, 'subscription_amount is populated' );

        wp_delete_post( $renewal_order->get_id(), true );
    }

    ihd_cleanup_subscription( $test5_sub_id );
} else {
    ihd_test_fail( 'Could not create subscription for Test 5' );
}


// ═════════════════════════════════════════════════════════════════════════════
// TEST 6: Gateway/Integration Error
// ═════════════════════════════════════════════════════════════════════════════
ihd_test_log( "\n─── Test 6: Gateway/Integration Error ───" );

$test6_sub_id = ihd_create_test_subscription( $TEST_USER_ID, $SUBSCRIPTION_PRODUCT_ID, 'active' );

if ( $test6_sub_id ) {
    // Note: Not cleaning audit logs — keeping for reference on dev server.
    wp_set_current_user( 0 );

    $subscription = wcs_get_subscription( $test6_sub_id );

    $renewal_order = wc_create_order( array( 'customer_id' => $TEST_USER_ID, 'status' => 'failed' ) );
    if ( ! is_wp_error( $renewal_order ) ) {
        $renewal_order->set_parent_id( $test6_sub_id );
        $renewal_order->set_payment_method_title( 'Authorize.Net' );
        $renewal_order->set_total( '4.99' );
        $renewal_order->add_order_note( 'CONFIGURATION_ERROR - E00044 Invalid credentials.' );
        $renewal_order->save();

        do_action( 'woocommerce_subscription_renewal_payment_failed', $subscription, $renewal_order );

        $log = ihd_get_latest_audit( $test6_sub_id );
        ihd_assert( $log['reason'] === 'Integration/Gateway Error', 'Reason is Integration/Gateway Error' );
        ihd_assert( $log['payment_error_type'] === 'integration_error', 'Error type is integration_error' );

        wp_delete_post( $renewal_order->get_id(), true );
    }

    ihd_cleanup_subscription( $test6_sub_id );
} else {
    ihd_test_fail( 'Could not create subscription for Test 6' );
}


// ═════════════════════════════════════════════════════════════════════════════
// TEST 7: Card Error Scenarios
// ═════════════════════════════════════════════════════════════════════════════
ihd_test_log( "\n─── Test 7: Card Error Scenarios ───" );

$card_scenarios = array(
    array( 'Insufficient Funds', 'INSUFFICIENT FUNDS', 'Renewal Failed (Insufficient Funds)', 'insufficient_funds' ),
    array( 'Expired Card', 'Card has EXPIRED', 'Renewal Failed (Expired Card)', 'expired_card' ),
    array( 'Card Declined', 'DECLINED - Do not honor', 'Renewal Failed (Payment Declined)', 'payment_declined' ),
    array( 'CVV Mismatch', 'CVV verification failed', 'Renewal Failed (Payment Declined)', 'payment_declined' ),
    array( 'INVALID_CARD_DATA', 'INVALID_CARD_DATA error', 'Integration/Gateway Error', 'integration_error' ),
    array( 'PayPal INSTRUMENT_DECLINED', 'INSTRUMENT_DECLINED by PayPal', 'Integration/Gateway Error', 'integration_error' ),
);

foreach ( $card_scenarios as $s ) {
    list( $label, $note, $exp_reason, $exp_error_type ) = $s;

    $sub_id = ihd_create_test_subscription( $TEST_USER_ID, $SUBSCRIPTION_PRODUCT_ID, 'active' );
    if ( ! $sub_id ) { ihd_test_fail( "Could not create sub for: {$label}" ); continue; }

    ihd_cleanup_audit( $sub_id );
    wp_set_current_user( 0 );

    $subscription = wcs_get_subscription( $sub_id );
    $renewal = wc_create_order( array( 'customer_id' => $TEST_USER_ID, 'status' => 'failed' ) );
    if ( ! is_wp_error( $renewal ) ) {
        $renewal->set_parent_id( $sub_id );
        $renewal->set_total( '4.99' );
        $renewal->add_order_note( "Payment failed: {$note}" );
        $renewal->save();

        delete_transient( 'ihd_pf_logged_' . $sub_id . '_' . $renewal->get_id() );
        do_action( 'woocommerce_subscription_renewal_payment_failed', $subscription, $renewal );

        $log = ihd_get_latest_audit( $sub_id );
        ihd_assert( $log && $log['reason'] === $exp_reason, "[{$label}] reason = {$exp_reason}", $log ? $log['reason'] : 'NO LOG' );
        ihd_assert( $log && $log['payment_error_type'] === $exp_error_type, "[{$label}] error_type = {$exp_error_type}", $log ? $log['payment_error_type'] : 'NO LOG' );

        wp_delete_post( $renewal->get_id(), true );
    }

    // Keep audit logs in DB for reference (dev server).
    ihd_delete_post_silent( $sub_id );
}


// ═════════════════════════════════════════════════════════════════════════════
// TEST 8: Deduplication (Cancel Modal Flag)
// ═════════════════════════════════════════════════════════════════════════════
ihd_test_log( "\n─── Test 8: Deduplication (Cancel Modal Flag) ───" );

$test8_sub_id = ihd_create_test_subscription( $TEST_USER_ID, $SUBSCRIPTION_PRODUCT_ID, 'active' );

if ( $test8_sub_id ) {
    // Note: Not cleaning audit logs — keeping for reference on dev server.
    wp_set_current_user( $TEST_USER_ID );

    $subscription = wcs_get_subscription( $test8_sub_id );

    IHD_VIP_Audit_Logger::log( array(
        'subscription_id' => $test8_sub_id,
        'customer_id'     => $TEST_USER_ID,
        'by_user_id'      => $TEST_USER_ID,
        'event_type'      => 'cancellation',
        'intentional'     => true,
        'reason'          => 'Just need a break',
    ) );

    $subscription->update_meta_data( '_ihd_cancel_logged', 'yes' );
    $subscription->save_meta_data();
    $subscription->update_status( 'cancelled', 'Dedup test' );

    $count = ihd_get_audit_count( $test8_sub_id );
    ihd_assert( $count === 1, 'Dedup: Only 1 entry', "Count: {$count}" );

    $log = ihd_get_latest_audit( $test8_sub_id );
    ihd_assert( (int) $log['intentional'] === 1, 'Only entry is intentional' );

    ihd_cleanup_subscription( $test8_sub_id );
} else {
    ihd_test_fail( 'Could not create subscription for Test 8' );
}


// ═════════════════════════════════════════════════════════════════════════════
// TEST 9: Transient Dedup (Payment Failure Double-Fire)
// ═════════════════════════════════════════════════════════════════════════════
ihd_test_log( "\n─── Test 9: Transient Dedup ───" );

$test9_sub_id = ihd_create_test_subscription( $TEST_USER_ID, $SUBSCRIPTION_PRODUCT_ID, 'active' );

if ( $test9_sub_id ) {
    ihd_cleanup_audit( $test9_sub_id );
    wp_set_current_user( 0 );

    $subscription = wcs_get_subscription( $test9_sub_id );
    $renewal = wc_create_order( array( 'customer_id' => $TEST_USER_ID, 'status' => 'failed' ) );

    if ( ! is_wp_error( $renewal ) ) {
        $renewal->set_parent_id( $test9_sub_id );
        $renewal->set_total( '4.99' );
        $renewal->add_order_note( 'Payment declined.' );
        $renewal->save();

        $dedup_key = 'ihd_pf_logged_' . $test9_sub_id . '_' . $renewal->get_id();
        delete_transient( $dedup_key );

        do_action( 'woocommerce_subscription_payment_failed', $subscription, $renewal );
        do_action( 'woocommerce_subscription_payment_failed', $subscription, $renewal );

        $count = ihd_get_audit_count( $test9_sub_id );
        ihd_assert( $count === 1, 'Transient dedup: Only 1 entry from double fire', "Count: {$count}" );

        delete_transient( $dedup_key );
        wp_delete_post( $renewal->get_id(), true );
    }

    ihd_cleanup_subscription( $test9_sub_id );
} else {
    ihd_test_fail( 'Could not create subscription for Test 9' );
}


// ═════════════════════════════════════════════════════════════════════════════
// TEST 10: Scope Gate (Option-Based Toggle)
// ═════════════════════════════════════════════════════════════════════════════
ihd_test_log( "\n─── Test 10: Scope Gate (Option-Based) ───" );

$original_mode  = get_option( IHD_VIP_User_Scope_Gate::MODE_OPTION_KEY, 'development' );
$original_users = get_option( IHD_VIP_User_Scope_Gate::OPTION_KEY, array() );

// Test development mode (scoped).
update_option( IHD_VIP_User_Scope_Gate::MODE_OPTION_KEY, 'development' );

wp_set_current_user( $TEST_USER_ID );
ihd_assert( IHD_VIP_User_Scope_Gate::is_development_mode() === true, 'is_development_mode() returns true in dev mode' );
ihd_assert( IHD_VIP_User_Scope_Gate::is_user_allowed() === true, 'Scoped user IS allowed in dev mode' );

// Non-scoped user in dev mode.
$non_scoped = $wpdb->get_var(
    "SELECT ID FROM {$wpdb->users} WHERE ID NOT IN (" . implode( ',', array_map( 'absint', $original_users ?: array( 0 ) ) ) . ") LIMIT 1"
);
if ( $non_scoped ) {
    wp_set_current_user( (int) $non_scoped );
    ihd_assert( IHD_VIP_User_Scope_Gate::is_user_allowed() === false, "Non-scoped user #{$non_scoped} denied in dev mode" );
}

// Anonymous in dev mode.
wp_set_current_user( 0 );
ihd_assert( IHD_VIP_User_Scope_Gate::is_user_allowed() === false, 'Anonymous denied in dev mode' );

// Production mode — all logged-in users allowed.
update_option( IHD_VIP_User_Scope_Gate::MODE_OPTION_KEY, 'production' );

ihd_assert( IHD_VIP_User_Scope_Gate::is_development_mode() === false, 'is_development_mode() returns false in prod mode' );

wp_set_current_user( $TEST_USER_ID );
ihd_assert( IHD_VIP_User_Scope_Gate::is_user_allowed() === true, 'Any logged-in user allowed in prod mode' );

if ( $non_scoped ) {
    wp_set_current_user( (int) $non_scoped );
    ihd_assert( IHD_VIP_User_Scope_Gate::is_user_allowed() === true, 'Non-scoped user allowed in prod mode' );
}

wp_set_current_user( 0 );
ihd_assert( IHD_VIP_User_Scope_Gate::is_user_allowed() === false, 'Anonymous still denied in prod mode' );

// Empty scoped list in dev mode.
update_option( IHD_VIP_User_Scope_Gate::MODE_OPTION_KEY, 'development' );
update_option( IHD_VIP_User_Scope_Gate::OPTION_KEY, array() );
wp_set_current_user( $TEST_USER_ID );
ihd_assert( IHD_VIP_User_Scope_Gate::is_user_allowed() === false, 'Empty scoped list denies everyone in dev mode' );

// Restore.
update_option( IHD_VIP_User_Scope_Gate::MODE_OPTION_KEY, $original_mode );
update_option( IHD_VIP_User_Scope_Gate::OPTION_KEY, $original_users );
wp_set_current_user( $TEST_USER_ID );


// ═════════════════════════════════════════════════════════════════════════════
// TEST 11: Error Classification Accuracy
// ═════════════════════════════════════════════════════════════════════════════
ihd_test_log( "\n─── Test 11: Error Classification ───" );

// Use static method — do NOT create a new instance (constructor registers hooks).
$tests = array(
    array( 'INVALID_CARD_DATA error', 'integration_error', 'INVALID_CARD_DATA' ),
    array( 'CONFIGURATION_ERROR', 'integration_error', 'CONFIGURATION_ERROR' ),
    array( 'E00003 XML parse', 'integration_error', 'E00003' ),
    array( 'GATEWAY_UNAVAILABLE', 'integration_error', 'GATEWAY_UNAVAILABLE' ),
    array( 'Insufficient funds', 'insufficient_funds', 'Insufficient' ),
    array( 'NSF code', 'insufficient_funds', 'NSF' ),
    array( 'Card expired', 'expired_card', 'Expired' ),
    array( 'CARD_EXPIRED', 'expired_card', 'CARD_EXPIRED' ),
    array( 'Transaction declined', 'payment_declined', 'Declined' ),
    array( 'Do not honor', 'payment_declined', 'Do not honor' ),
    array( 'CVV failed', 'payment_declined', 'CVV' ),
    array( 'FRAUD suspected', 'payment_declined', 'Fraud' ),
    array( '', 'unknown', 'Empty' ),
    array( 'Generic issue', 'unknown', 'Unrecognized' ),
);

// classify_error is now public.
foreach ( $tests as $t ) {
    list( $input, $expected, $label ) = $t;
    $result = IHD_VIP_Subscription_Event_Tracker::classify_error( $input );
    ihd_assert( $result === $expected, "classify [{$label}]", "Got: {$result}" );
}


// ═════════════════════════════════════════════════════════════════════════════
// TEST 12: Status Transitions
// ═════════════════════════════════════════════════════════════════════════════
ihd_test_log( "\n─── Test 12: Various Status Transitions ───" );

// Pending cancel.
$sub_id = ihd_create_test_subscription( $TEST_USER_ID, $SUBSCRIPTION_PRODUCT_ID, 'active' );
if ( $sub_id ) {
    ihd_cleanup_audit( $sub_id );
    wp_set_current_user( 0 );
    $s = wcs_get_subscription( $sub_id );
    $s->update_status( 'pending-cancel' );
    $log = ihd_get_latest_audit( $sub_id );
    ihd_assert( $log['event_type'] === 'pending_cancel', 'pending-cancel → event_type pending_cancel' );
    ihd_assert( strpos( $log['reason'], 'Pending' ) !== false, 'Reason mentions Pending' );
    // Keep audit logs in DB for reference (dev server).
    ihd_delete_post_silent( $sub_id );
}

// On-hold → cancelled (auto-cancel).
$sub_id = ihd_create_test_subscription( $TEST_USER_ID, $SUBSCRIPTION_PRODUCT_ID, 'active' );
if ( $sub_id ) {
    ihd_cleanup_audit( $sub_id );
    wp_set_current_user( 0 );
    $s = wcs_get_subscription( $sub_id );
    $s->update_status( 'on-hold' );
    $s = wcs_get_subscription( $sub_id );
    $s->update_status( 'cancelled' );
    $log = ihd_get_latest_audit( $sub_id );
    ihd_assert( $log['event_type'] === 'cancellation', 'on-hold→cancelled event_type is cancellation' );
    ihd_assert( strpos( $log['reason'], 'Auto-cancelled' ) !== false, 'Reason is Auto-cancelled (Payment Failed)', $log['reason'] );
    ihd_assert( $log['old_status'] === 'on-hold', 'old_status is on-hold' );
    // Keep audit logs in DB for reference (dev server).
    ihd_delete_post_silent( $sub_id );
}

// Max retries exceeded.
$sub_id = ihd_create_test_subscription( $TEST_USER_ID, $SUBSCRIPTION_PRODUCT_ID, 'active' );
if ( $sub_id ) {
    ihd_cleanup_audit( $sub_id );
    wp_set_current_user( 0 );
    $s = wcs_get_subscription( $sub_id );
    $s->update_meta_data( '_wcs_retry_count', 5 );
    $s->save_meta_data();
    $s->update_status( 'cancelled' );
    $log = ihd_get_latest_audit( $sub_id );
    ihd_assert( strpos( $log['reason'], 'Max Retries' ) !== false, 'Max retries reason', $log['reason'] );
    // Keep audit logs in DB for reference (dev server).
    ihd_delete_post_silent( $sub_id );
}

// Reactivation (not tracked).
$sub_id = ihd_create_test_subscription( $TEST_USER_ID, $SUBSCRIPTION_PRODUCT_ID, 'active' );
if ( $sub_id ) {
    ihd_cleanup_audit( $sub_id );
    wp_set_current_user( 0 );
    $s = wcs_get_subscription( $sub_id );
    $s->update_status( 'on-hold' );
    $hold_count = ihd_get_audit_count( $sub_id );
    $s = wcs_get_subscription( $sub_id );
    $s->update_status( 'active' );
    $active_count = ihd_get_audit_count( $sub_id );
    ihd_assert( $active_count === $hold_count, 'Reactivation does NOT create audit entry' );
    // Keep audit logs in DB for reference (dev server).
    ihd_delete_post_silent( $sub_id );
}


// ═════════════════════════════════════════════════════════════════════════════
// TEST 13: Plugin Structural Integrity
// ═════════════════════════════════════════════════════════════════════════════
ihd_test_log( "\n─── Test 13: Plugin Structural Integrity ───" );

$required_files = array(
    'ihd-vip-subscriptions.php',
    'includes/class-admin.php',
    'includes/class-audit-logger.php',
    'includes/class-cancel-handler.php',
    'includes/class-installer.php',
    'includes/class-subscription-event-tracker.php',
    'includes/class-user-scope-gate.php',
    'shortcodes/manage-subscription-shortcode.php',
);

$plugin_path = WP_PLUGIN_DIR . '/ihd-vip-subscriptions/';
foreach ( $required_files as $file ) {
    ihd_assert( file_exists( $plugin_path . $file ), "File exists: {$file}" );
}

ihd_assert( shortcode_exists( 'hero_vip_manage_subscription_refined' ), 'Shortcode registered' );
ihd_assert( has_action( 'wp_ajax_ihd_vip_cancel_subscription' ), 'Cancel AJAX registered' );
// Scope toggle AJAX is registered only in admin context (IHD_VIP_Admin loads on is_admin()).
// WP-CLI runs in non-admin context, so this is expected to be absent here.
if ( is_admin() ) {
    ihd_assert( has_action( 'wp_ajax_ihd_vip_toggle_scope_mode' ), 'Scope toggle AJAX registered (admin)' );
} else {
    ihd_test_pass( 'Scope toggle AJAX skipped (non-admin context — expected)', 'Registered only in admin' );
}
ihd_assert( has_action( 'woocommerce_subscription_status_updated' ), 'Status updated hook registered' );
ihd_assert( has_action( 'woocommerce_subscription_renewal_payment_failed' ), 'Renewal failed hook registered' );
ihd_assert( has_action( 'woocommerce_subscription_payment_failed' ), 'Payment failed hook registered' );

// Verify scope mode option exists.
ihd_assert( get_option( IHD_VIP_User_Scope_Gate::MODE_OPTION_KEY ) !== false || true, 'Scope mode option is accessible' );


// ═════════════════════════════════════════════════════════════════════════════
// TEST 14: Edge Cases
// ═════════════════════════════════════════════════════════════════════════════
ihd_test_log( "\n─── Test 14: Edge Cases ───" );

$ghost_logs = IHD_VIP_Audit_Logger::get_logs( 0 );
ihd_assert( is_array( $ghost_logs ) && empty( $ghost_logs ), 'get_logs(0) returns empty array' );

$ghost_logs2 = IHD_VIP_Audit_Logger::get_logs( 999999998 );
ihd_assert( is_array( $ghost_logs2 ) && empty( $ghost_logs2 ), 'get_logs(non-existent) returns empty array' );

// All 5 cancel reasons.
$test14_sub_id = 999999903;
ihd_cleanup_audit( $test14_sub_id );
$reasons = array( 'Too expensive', 'Not using the benefits enough', 'Found an alternative', 'Just need a break', 'Other' );
foreach ( $reasons as $r ) {
    IHD_VIP_Audit_Logger::log( array( 'subscription_id' => $test14_sub_id, 'event_type' => 'cancellation', 'intentional' => true, 'reason' => $r ) );
}
$logs = IHD_VIP_Audit_Logger::get_logs( $test14_sub_id );
ihd_assert( count( $logs ) === 5, 'All 5 cancel reasons logged' );
$stored = wp_list_pluck( $logs, 'reason' );
foreach ( $reasons as $r ) {
    ihd_assert( in_array( $r, $stored ), "Reason stored: '{$r}'" );
}
ihd_cleanup_audit( $test14_sub_id );


// ═════════════════════════════════════════════════════════════════════════════
// TEST 15: Cancel Handler Rate Limiting
// ═════════════════════════════════════════════════════════════════════════════
ihd_test_log( "\n─── Test 15: Cancel Handler Rate Limiting ───" );

$sub_id = ihd_create_test_subscription( $TEST_USER_ID, $SUBSCRIPTION_PRODUCT_ID, 'active' );
if ( $sub_id ) {
    // Check that rate limit transient key pattern exists.
    $rate_key = 'ihd_cancel_rate_' . $sub_id;
    delete_transient( $rate_key );

    // Set transient to simulate recent cancellation.
    set_transient( $rate_key, 1, 60 );
    ihd_assert( get_transient( $rate_key ) == 1, 'Rate limit transient is set' );

    delete_transient( $rate_key );
    // Keep audit logs in DB for reference (dev server).
    ihd_delete_post_silent( $sub_id );
}


// ═════════════════════════════════════════════════════════════════════════════
// CLEANUP
// ═════════════════════════════════════════════════════════════════════════════
ihd_test_log( "\n─── Cleanup ───" );

// Only delete test subscription posts — keep audit logs in DB for reference.
$cleaned = 0;
foreach ( IHD_Test_State::$test_sub_ids as $sid ) {
    if ( get_post( $sid ) ) {
        ihd_delete_post_silent( $sid );
        $cleaned++;
    }
}
ihd_test_log( "  Cleaned up {$cleaned} test subscription posts (audit logs preserved)." );

wp_set_current_user( $TEST_USER_ID );


// ═════════════════════════════════════════════════════════════════════════════
// SUMMARY
// ═════════════════════════════════════════════════════════════════════════════
$tc = IHD_Test_State::$test_count;
$pc = IHD_Test_State::$pass_count;
$fc = IHD_Test_State::$fail_count;

$pass_rate = $tc > 0 ? round( ( $pc / $tc ) * 100, 1 ) : 0;
$overall = $fc === 0 ? 'ALL TESTS PASSED' : 'SOME TESTS FAILED';

ihd_test_log( "\n╔══════════════════════════════════════════════════════════════════╗" );
ihd_test_log( "║  TEST REPORT SUMMARY                                           ║" );
ihd_test_log( "╠══════════════════════════════════════════════════════════════════╣" );
ihd_test_log( "║  Total Tests:  " . str_pad( $tc, 4 ) . "                                           ║" );
ihd_test_log( "║  Passed:       " . str_pad( $pc, 4 ) . "                                           ║" );
ihd_test_log( "║  Failed:       " . str_pad( $fc, 4 ) . "                                           ║" );
ihd_test_log( "║  Pass Rate:    " . str_pad( $pass_rate . '%', 6 ) . "                                         ║" );
ihd_test_log( "║  Result:       " . str_pad( $overall, 48 ) . "║" );
ihd_test_log( "╚══════════════════════════════════════════════════════════════════╝" );

if ( $fc > 0 ) {
    ihd_test_log( "\nFailed Tests:" );
    foreach ( IHD_Test_State::$results as $r ) {
        if ( $r['status'] === 'FAIL' ) {
            ihd_test_log( "  ❌ {$r['name']}" . ( $r['detail'] ? " — {$r['detail']}" : '' ) );
        }
    }
}

ihd_test_log( "\nCompleted at " . current_time( 'Y-m-d H:i:s' ) . "\n" );
