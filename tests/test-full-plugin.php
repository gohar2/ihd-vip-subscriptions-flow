<?php
/**
 * IHD VIP Subscriptions — Full Plugin Test Suite v3
 *
 * Run: wp eval-file wp-content/plugins/ihd-vip-subscriptions/tests/test-full-plugin.php
 *
 * Logs are NOT cleaned up. Test subscriptions tagged with _ihd_test_run meta.
 */

if ( ! defined( 'ABSPATH' ) ) { echo "Run via: wp eval-file\n"; exit(1); }

$TEST_USER_ID = 439065;
$PRODUCT_ID   = 3626167;
$VARIATION_ID = 3626168;
$RUN_ID       = 'test_' . date('Ymd_His');

$GLOBALS['_t_results'] = []; $GLOBALS['_t_pass'] = 0; $GLOBALS['_t_fail'] = 0; $GLOBALS['_t_warn'] = 0; $GLOBALS['_t_total'] = 0; $sub_ids = [];

function out($m){echo $m."\n";}
function pass($n,$d=''){$GLOBALS['_t_total']++;$GLOBALS['_t_pass']++;$GLOBALS['_t_results'][]=['PASS',$n,$d];out("  PASS: {$n}".($d?" - {$d}":''));}
function fail($n,$d=''){$GLOBALS['_t_total']++;$GLOBALS['_t_fail']++;$GLOBALS['_t_results'][]=['FAIL',$n,$d];out("  FAIL: {$n}".($d?" - {$d}":''));}
function ok($c,$n,$d=''){$c?pass($n,$d):fail($n,$d);return $c;}

function audit_count($sid){global $wpdb;return(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ihd_vip_subscription_audit WHERE subscription_id=%d",$sid));}
function audit_last($sid){global $wpdb;return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ihd_vip_subscription_audit WHERE subscription_id=%d ORDER BY id DESC LIMIT 1",$sid),ARRAY_A);}
function audit_all($sid){global $wpdb;return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ihd_vip_subscription_audit WHERE subscription_id=%d ORDER BY id ASC",$sid),ARRAY_A);}

function make_sub($uid,$pid,$vid=0,$status='active'){
    global $RUN_ID,$sub_ids;
    $sub=wcs_create_subscription(['customer_id'=>$uid,'billing_period'=>'month','billing_interval'=>1,'start_date'=>gmdate('Y-m-d H:i:s')]);
    if(is_wp_error($sub)){out("  [ERR] ".$sub->get_error_message());return null;}
    $p=wc_get_product($vid?:$pid);
    if($p){$item=new WC_Order_Item_Product();$item->set_product($p);$item->set_quantity(1);$item->set_subtotal($p->get_price());$item->set_total($p->get_price());$sub->add_item($item);$sub->set_total($p->get_price());}
    $sub->set_payment_method('bacs');$sub->save();
    update_post_meta($sub->get_id(),'_ihd_test_run',$RUN_ID);
    update_post_meta($sub->get_id(),'_ihd_test_flag','1');
    if('active'===$status){wp_update_post(['ID'=>$sub->get_id(),'post_status'=>'wc-active']);clean_post_cache($sub->get_id());}
    $sub_ids[]=$sub->get_id();return $sub->get_id();
}

function clear_dedup($sid){
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%ihd_%{$sid}%' AND option_name LIKE '%_transient_%'");
}

// =========================================================================
out("\n=== IHD VIP Subscriptions - Full Plugin Test Suite v3 ===");
out("Run ID: {$RUN_ID} | Date: ".current_time('Y-m-d H:i:s')."\n");

// --- Pre-flight ---
out("--- Pre-flight ---");
ok(class_exists('WooCommerce'),'WooCommerce active');
ok(class_exists('WC_Subscriptions'),'WC Subscriptions active');
ok(class_exists('IHD_VIP_Audit_Logger'),'Audit Logger class');
ok(class_exists('IHD_VIP_Subscription_Event_Tracker'),'Event Tracker class');
ok(class_exists('IHD_VIP_Cancel_Handler'),'Cancel Handler class');
ok(class_exists('IHD_VIP_Switch_Handler'),'Switch Handler class');
ok(class_exists('IHD_VIP_User_Scope_Gate'),'Scope Gate class');
ok(class_exists('IHD_VIP_Installer'),'Installer class');
ok(defined('IHD_VIP_VERSION'),'Plugin version defined',IHD_VIP_VERSION??'N/A');

global $wpdb;
$tbl=$wpdb->prefix.'ihd_vip_subscription_audit';
ok(!empty($wpdb->get_var("SHOW TABLES LIKE '{$tbl}'")),'Audit table exists');

$cols=wp_list_pluck($wpdb->get_results("DESCRIBE {$tbl}",ARRAY_A),'Field');
foreach(['id','subscription_id','customer_id','by_user_id','event_type','old_status','new_status','intentional','reason','detail','payment_method','payment_error_type','subscription_amount','billing_period','currency','created_at'] as $c)
    ok(in_array($c,$cols),"Column: {$c}");

ok(!empty(get_user_by('id',$TEST_USER_ID)),'Test user exists');
ok(has_action('wp_ajax_ihd_vip_prepare_switch'),'AJAX: prepare_switch');
ok(has_action('wp_ajax_ihd_vip_checkout_page'),'AJAX: checkout_page');
ok(has_action('wp_ajax_ihd_vip_check_switch_complete'),'AJAX: check_switch_complete');
ok(has_action('wp_ajax_ihd_vip_cancel_subscription'),'AJAX: cancel_subscription');

wp_set_current_user($TEST_USER_ID);

// =========================================================================
// TEST 1: Direct Audit Logger
// =========================================================================
out("\n--- Test 1: Direct Audit Logger ---");
$t1=999999901;
$iid=IHD_VIP_Audit_Logger::log(['subscription_id'=>$t1,'customer_id'=>$TEST_USER_ID,'by_user_id'=>$TEST_USER_ID,'event_type'=>'cancellation','old_status'=>'active','new_status'=>'cancelled','intentional'=>true,'reason'=>'Test direct log','detail'=>'Test suite run '.$RUN_ID,'payment_method'=>'bacs','subscription_amount'=>4.99,'billing_period'=>'month','currency'=>'USD']);
ok($iid>0,'log() returns insert ID',"ID: {$iid}");
$r=audit_last($t1);
ok($r&&$r['event_type']==='cancellation','event_type=cancellation');
ok($r&&(int)$r['intentional']===1,'intentional=1');
ok($r&&$r['reason']==='Test direct log','reason correct');
ok($r&&(float)$r['subscription_amount']==4.99,'amount=4.99');
$logs=IHD_VIP_Audit_Logger::get_logs($t1);
ok(!empty($logs),'get_logs() returns results');

// =========================================================================
// TEST 2: Intentional Cancellation (VIP Modal)
// =========================================================================
out("\n--- Test 2: Intentional Cancellation ---");
$t2=make_sub($TEST_USER_ID,$PRODUCT_ID,$VARIATION_ID);
if($t2){
    $before=audit_count($t2);$sub=wcs_get_subscription($t2);
    IHD_VIP_Audit_Logger::log(['subscription_id'=>$t2,'customer_id'=>$TEST_USER_ID,'by_user_id'=>$TEST_USER_ID,'event_type'=>'cancellation','old_status'=>'active','new_status'=>'cancelled','intentional'=>true,'reason'=>'Too expensive','detail'=>'Cancelled by customer via VIP portal.','payment_method'=>$sub->get_payment_method_title(),'subscription_amount'=>$sub->get_total(),'billing_period'=>'month','currency'=>'USD']);
    $sub->update_meta_data('_ihd_cancel_logged','yes');$sub->update_meta_data('_ihd_cancel_reason','Too expensive');$sub->save_meta_data();
    $sub->update_status('cancelled','Cancelled by customer via VIP portal.');
    $after=audit_count($t2);
    ok($after===$before+1,'Exactly 1 audit entry (no dup)',"before={$before} after={$after}");
    $e=audit_last($t2);
    ok($e&&(int)$e['intentional']===1,'intentional=1');
    ok($e&&$e['reason']==='Too expensive','reason=Too expensive');
    ok($e&&(int)$e['by_user_id']===$TEST_USER_ID,'by_user_id=test user');
}

// =========================================================================
// TEST 3: System Cancellation (unintentional)
// =========================================================================
out("\n--- Test 3: System Cancellation ---");
$t3=make_sub($TEST_USER_ID,$PRODUCT_ID,$VARIATION_ID);
if($t3){
    wp_set_current_user(0);$before=audit_count($t3);
    $sub=wcs_get_subscription($t3);$sub->update_status('cancelled');
    $after=audit_count($t3);
    ok($after===$before+1,'Exactly 1 entry',"before={$before} after={$after}");
    $e=audit_last($t3);
    ok($e&&(int)$e['intentional']===0,'intentional=0');
    ok($e&&$e['reason']==='System Cancelled','reason=System Cancelled');
    ok($e&&(int)$e['by_user_id']===0,'by_user_id=0 (system)');
    wp_set_current_user($TEST_USER_ID);
}

// =========================================================================
// TEST 4: Subscription Expiration
// =========================================================================
out("\n--- Test 4: Expiration ---");
$t4=make_sub($TEST_USER_ID,$PRODUCT_ID,$VARIATION_ID);
if($t4){
    wp_set_current_user(0);$sub=wcs_get_subscription($t4);$sub->update_status('expired');
    $e=audit_last($t4);
    ok($e&&$e['event_type']==='expiration','event_type=expiration');
    ok($e&&$e['reason']==='Subscription Expired','reason correct');
    ok($e&&$e['new_status']==='expired','new_status=expired');
    wp_set_current_user($TEST_USER_ID);
}

// =========================================================================
// TEST 5: On-Hold
// =========================================================================
out("\n--- Test 5: On-Hold ---");
$t5=make_sub($TEST_USER_ID,$PRODUCT_ID,$VARIATION_ID);
if($t5){
    wp_set_current_user(0);$sub=wcs_get_subscription($t5);$sub->update_status('on-hold');
    $e=audit_last($t5);
    ok($e&&$e['event_type']==='on_hold','event_type=on_hold');
    ok($e&&$e['reason']==='Subscription On-Hold','reason correct');
    wp_set_current_user($TEST_USER_ID);
}

// =========================================================================
// TEST 6: Pending Cancellation
// =========================================================================
out("\n--- Test 6: Pending Cancel ---");
$t6=make_sub($TEST_USER_ID,$PRODUCT_ID,$VARIATION_ID);
if($t6){
    wp_set_current_user(0);$sub=wcs_get_subscription($t6);$sub->update_status('pending-cancel');
    $e=audit_last($t6);
    ok($e&&$e['event_type']==='pending_cancel','event_type=pending_cancel');
    ok($e&&$e['reason']==='Pending Cancellation','reason correct');
    wp_set_current_user($TEST_USER_ID);
}

// =========================================================================
// TEST 7: On-Hold -> Cancelled (auto-cancel)
// =========================================================================
out("\n--- Test 7: On-Hold -> Cancelled ---");
$t7=make_sub($TEST_USER_ID,$PRODUCT_ID,$VARIATION_ID);
if($t7){
    wp_set_current_user(0);$sub=wcs_get_subscription($t7);
    $sub->update_status('on-hold');
    clear_dedup($t7);
    $sub->update_status('cancelled');
    $all=audit_all($t7);
    ok(count($all)===2,'2 entries (on-hold + cancel)','got '.count($all));
    $last=end($all);
    ok($last['reason']==='Auto-cancelled (Payment Failed)','reason=Auto-cancelled (Payment Failed)');
    wp_set_current_user($TEST_USER_ID);
}

// =========================================================================
// TEST 8: Max Retries Exceeded
// =========================================================================
out("\n--- Test 8: Max Retries ---");
$t8=make_sub($TEST_USER_ID,$PRODUCT_ID,$VARIATION_ID);
if($t8){
    wp_set_current_user(0);$sub=wcs_get_subscription($t8);
    update_post_meta($t8,'_wcs_retry_count',3);
    $sub->update_status('cancelled');
    $e=audit_last($t8);
    ok($e&&$e['reason']==='Auto-cancelled (Max Retries Exceeded)','reason=Max Retries Exceeded');
    wp_set_current_user($TEST_USER_ID);
}

// =========================================================================
// TEST 9: Payment Failure Scenarios
// =========================================================================
out("\n--- Test 9: Payment Failures ---");
$scenarios=[
    ['INSUFFICIENT FUNDS','insufficient_funds','Renewal Failed (Insufficient Funds)'],
    ['Card has expired','expired_card','Renewal Failed (Expired Card)'],
    ['CARD_DECLINED','payment_declined','Renewal Failed (Payment Declined)'],
    ['CVV verification failed','payment_declined','Renewal Failed (Payment Declined)'],
    ['INVALID_CARD_DATA: bad token','integration_error','Integration/Gateway Error'],
    ['GATEWAY_ERROR: timeout','integration_error','Integration/Gateway Error'],
    ['Some unknown error','unknown','Renewal Payment Failed'],
];

foreach($scenarios as $i=>$sc){
    $sid=make_sub($TEST_USER_ID,$PRODUCT_ID,$VARIATION_ID);
    if(!$sid){fail("Scenario {$i}: create sub");continue;}
    wp_set_current_user(0);$sub=wcs_get_subscription($sid);
    $order=wc_create_order(['customer_id'=>$TEST_USER_ID,'status'=>'failed']);
    $order->set_payment_method('authorize_net');$order->set_payment_method_title('Credit Card');$order->save();
    $order->add_order_note($sc[0]);
    do_action('woocommerce_subscription_renewal_payment_failed',$sub,$order);
    $e=audit_last($sid);
    ok($e&&$e['payment_error_type']===$sc[1],"Scenario {$i}: error_type={$sc[1]}",$e['payment_error_type']??'null');
    ok($e&&$e['reason']===$sc[2],"Scenario {$i}: reason correct",$e['reason']??'null');
    ok($e&&$e['event_type']==='payment_failure',"Scenario {$i}: event_type=payment_failure");
    ok($e&&(int)$e['intentional']===0,"Scenario {$i}: intentional=0");
    wp_set_current_user($TEST_USER_ID);
}

// =========================================================================
// TEST 10: Error Classification (static)
// =========================================================================
out("\n--- Test 10: Error Classification ---");
$cc=[
    ['INVALID_CARD_DATA','integration_error'],['E00003: parse error','integration_error'],
    ['INSTRUMENT_DECLINED','integration_error'],['GATEWAY_UNAVAILABLE','integration_error'],
    ['insufficient funds','insufficient_funds'],['NSF','insufficient_funds'],
    ['card has expired','expired_card'],['transaction declined','payment_declined'],
    ['CVV mismatch','payment_declined'],['DO NOT HONOR','payment_declined'],
    ['SUSPECTED FRAUD','payment_declined'],['random xyz','unknown'],['','unknown'],
];
foreach($cc as $c){
    $r=IHD_VIP_Subscription_Event_Tracker::classify_error($c[0]);
    ok($r===$c[1],"classify('{$c[0]}')={$c[1]}","got:{$r}");
}

// =========================================================================
// TEST 11: Deduplication
// =========================================================================
out("\n--- Test 11: Deduplication ---");
$t11=make_sub($TEST_USER_ID,$PRODUCT_ID,$VARIATION_ID);
if($t11){
    wp_set_current_user(0);$sub=wcs_get_subscription($t11);
    do_action('woocommerce_subscription_status_updated',$sub,'cancelled','active');
    $c1=audit_count($t11);
    do_action('woocommerce_subscription_status_updated',$sub,'cancelled','active');
    $c2=audit_count($t11);
    ok($c1===1,'First fire: 1 entry');
    ok($c2===1,'Second fire deduped: still 1',"got {$c2}");
    wp_set_current_user($TEST_USER_ID);
}

// =========================================================================
// TEST 12: Cancel Modal Dedup (_ihd_cancel_logged)
// =========================================================================
out("\n--- Test 12: Cancel Modal Dedup ---");
$t12=make_sub($TEST_USER_ID,$PRODUCT_ID,$VARIATION_ID);
if($t12){
    $sub=wcs_get_subscription($t12);
    IHD_VIP_Audit_Logger::log(['subscription_id'=>$t12,'customer_id'=>$TEST_USER_ID,'by_user_id'=>$TEST_USER_ID,'event_type'=>'cancellation','old_status'=>'active','new_status'=>'cancelled','intentional'=>true,'reason'=>'Just need a break']);
    $sub->update_meta_data('_ihd_cancel_logged','yes');$sub->save_meta_data();
    $cb=audit_count($t12);
    $sub->update_status('cancelled');
    $ca=audit_count($t12);
    ok($ca===$cb,'Tracker skipped: no duplicate',"before={$cb} after={$ca}");
    $sub=wcs_get_subscription($t12);
    ok($sub->get_meta('_ihd_cancel_logged')!=='yes','Flag cleared after skip');
}

// =========================================================================
// TEST 13: Scope Gate
// =========================================================================
out("\n--- Test 13: Scope Gate ---");
$om=get_option(IHD_VIP_User_Scope_Gate::MODE_OPTION_KEY,'development');
$ou=get_option('ihd_vip_scoped_users',[]);

update_option(IHD_VIP_User_Scope_Gate::MODE_OPTION_KEY,'development');
update_option('ihd_vip_scoped_users',[$TEST_USER_ID]);
wp_set_current_user($TEST_USER_ID);
ok(IHD_VIP_User_Scope_Gate::is_user_allowed()===true,'Dev + in list = allowed');

update_option('ihd_vip_scoped_users',[1]);
ok(IHD_VIP_User_Scope_Gate::is_user_allowed()===false,'Dev + not in list = blocked');

update_option(IHD_VIP_User_Scope_Gate::MODE_OPTION_KEY,'production');
ok(IHD_VIP_User_Scope_Gate::is_user_allowed()===true,'Production = allowed');

wp_set_current_user(0);
ok(IHD_VIP_User_Scope_Gate::is_user_allowed()===false,'Not logged in = blocked');

update_option(IHD_VIP_User_Scope_Gate::MODE_OPTION_KEY,$om);
update_option('ihd_vip_scoped_users',$ou);
wp_set_current_user($TEST_USER_ID);

// =========================================================================
// TEST 14: Switch Handler - Dynamic Sibling Resolution
// =========================================================================
out("\n--- Test 14: Switch Handler - Siblings ---");
$t14=make_sub($TEST_USER_ID,$PRODUCT_ID,$VARIATION_ID);
if($t14){
    $sub=wcs_get_subscription($t14);
    $sw=IHD_VIP_Switch_Handler::get_switchable_item($sub);
    ok($sw!==false,'get_switchable_item() found item');
    ok($sw&&(int)$sw['product_id']===$PRODUCT_ID,'Correct parent product');
    ok($sw&&(int)$sw['variation_id']===$VARIATION_ID,'Correct current variation');
    if($sw){
        $sibs=IHD_VIP_Switch_Handler::get_sibling_variations($sw['product_id'],$sw['variation_id'],'month');
        ok(count($sibs)>=2,'At least 2 siblings','found '.count($sibs));
        $sorted=true;for($i=1;$i<count($sibs);$i++){if($sibs[$i]['price']<$sibs[$i-1]['price'])$sorted=false;}
        ok($sorted,'Sorted by price ascending');
        $cf=false;foreach($sibs as $s)if($s['is_current'])$cf=true;
        ok($cf,'Current variation flagged');
        foreach($sibs as $s)ok(!empty($s['label']),"Sibling #{$s['variation_id']} has label",$s['label']);
    }
}

// =========================================================================
// TEST 15: Switch Handler - Skips non-subscription products
// =========================================================================
out("\n--- Test 15: Switch skips non-subscription products ---");
$rs=wcs_get_subscription(4903172);
if($rs){
    $sw=IHD_VIP_Switch_Handler::get_switchable_item($rs);
    ok($sw!==false,'Found switchable on real sub');
    if($sw){
        $p=wc_get_product($sw['product_id']);
        ok($p&&$p->is_type('variable-subscription'),'Picked variable-subscription',$p?$p->get_type():'');
        ok($p&&$p->get_name()==='Hero VIP Club','Correct product name',$p?$p->get_name():'');
    }
}else{out("  SKIP: Sub #4903172 not found");}

// =========================================================================
// TEST 16: Variation Label & Slug
// =========================================================================
out("\n--- Test 16: Variation Labels ---");
$v=wc_get_product($VARIATION_ID);
if($v){
    $l=IHD_VIP_Switch_Handler::get_variation_label($v);
    $s=IHD_VIP_Switch_Handler::get_variation_slug($v);
    ok(!empty($l),'get_variation_label()',$l);
    ok(!empty($s),'get_variation_slug()',$s);
    ok($s==='hero','Slug=hero',$s);
}

// =========================================================================
// TEST 17: All 5 Intentional Cancel Reasons
// =========================================================================
out("\n--- Test 17: All Cancel Reasons ---");
foreach(['Too expensive','Not using the benefits enough','Found an alternative','Just need a break','Other'] as $reason){
    $sid=make_sub($TEST_USER_ID,$PRODUCT_ID,$VARIATION_ID);
    if(!$sid){fail("Create sub for: {$reason}");continue;}
    $sub=wcs_get_subscription($sid);
    IHD_VIP_Audit_Logger::log(['subscription_id'=>$sid,'customer_id'=>$TEST_USER_ID,'by_user_id'=>$TEST_USER_ID,'event_type'=>'cancellation','old_status'=>'active','new_status'=>'cancelled','intentional'=>true,'reason'=>$reason,'detail'=>"Reason: {$reason}",'payment_method'=>'Direct Bank Transfer','subscription_amount'=>$sub->get_total(),'billing_period'=>'month','currency'=>'USD']);
    $sub->update_meta_data('_ihd_cancel_logged','yes');$sub->save_meta_data();
    $sub->update_status('cancelled');
    $e=audit_last($sid);
    ok($e&&$e['reason']===$reason,"Reason: {$reason}");
    ok($e&&(int)$e['intentional']===1,"Intentional for: {$reason}");
    ok(audit_count($sid)===1,"No dup for: {$reason}");
}

// =========================================================================
// TEST 18: Campaign-Friendly Columns
// =========================================================================
out("\n--- Test 18: Campaign Columns ---");
$t18=make_sub($TEST_USER_ID,$PRODUCT_ID,$VARIATION_ID);
if($t18){
    wp_set_current_user(0);$sub=wcs_get_subscription($t18);$sub->update_status('cancelled');wp_set_current_user($TEST_USER_ID);
    $e=audit_last($t18);
    ok(!empty($e['customer_id']),'customer_id populated',$e['customer_id']);
    ok(!empty($e['event_type']),'event_type populated',$e['event_type']);
    ok(!empty($e['old_status']),'old_status populated',$e['old_status']);
    ok(!empty($e['new_status']),'new_status populated',$e['new_status']);
    ok(!empty($e['reason']),'reason populated',$e['reason']);
    ok(!empty($e['detail']),'detail populated');
    ok((float)$e['subscription_amount']>0,'amount>0',$e['subscription_amount']);
    ok(!empty($e['billing_period']),'billing_period',$e['billing_period']);
    ok(!empty($e['currency']),'currency',$e['currency']);
    ok(!empty($e['created_at']),'created_at',$e['created_at']);
}

// =========================================================================
// TEST 19: wp_delete_post phantom prevention
// =========================================================================
out("\n--- Test 19: Delete Phantom Prevention ---");
$t19=make_sub($TEST_USER_ID,$PRODUCT_ID,$VARIATION_ID);
if($t19){
    $before=audit_count($t19);
    wp_delete_post($t19,true);
    $after=audit_count($t19);
    ok($after===0,'No phantom after delete',"got {$after}");
    $sub_ids=array_diff($sub_ids,[$t19]);
}

// =========================================================================
// TEST 20: Rate Limiting
// =========================================================================
out("\n--- Test 20: Rate Limiting ---");
$t20=make_sub($TEST_USER_ID,$PRODUCT_ID,$VARIATION_ID);
if($t20){
    $rk='ihd_cancel_rate_'.$t20;
    delete_transient($rk);ok(!get_transient($rk),'Rate limiter clear');
    set_transient($rk,1,60);ok(get_transient($rk)==1,'Rate limiter active');
    delete_transient($rk);ok(!get_transient($rk),'Rate limiter cleared');
}

// =========================================================================
// REPORT
// =========================================================================
$total=$GLOBALS['_t_total'];$pass=$GLOBALS['_t_pass'];$fail=$GLOBALS['_t_fail'];$warn=$GLOBALS['_t_warn'];$results=$GLOBALS['_t_results'];
out("\n====================================================");
out("  TEST REPORT");
out("====================================================");
out("  Total: {$total}  |  Pass: {$pass}  |  Fail: {$fail}  |  Warn: {$warn}");
out("  Run ID: {$RUN_ID}");
out("  Test subscriptions: ".count($sub_ids));
out("  Tagged: _ihd_test_run = {$RUN_ID}");
out("----------------------------------------------------");
if($fail===0){out("  ALL TESTS PASSED");}
else{
    out("  FAILURES:");
    foreach($results as $r)if($r[0]==='FAIL')out("    - {$r[1]}".($r[2]?" ({$r[2]})":''));
}
out("====================================================");
out("\nFind test logs: SELECT a.* FROM {$tbl} a INNER JOIN {$wpdb->postmeta} pm ON pm.post_id=a.subscription_id AND pm.meta_key='_ihd_test_run' AND pm.meta_value='{$RUN_ID}';");
