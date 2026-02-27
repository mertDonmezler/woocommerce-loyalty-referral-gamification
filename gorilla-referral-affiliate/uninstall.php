<?php
/**
 * Gorilla Referral & Affiliate - Uninstall
 *
 * Plugin tamamen silindiginde verileri temizler.
 * Includes cross-plugin safety guards to avoid destroying data
 * that belongs to the Gorilla Loyalty & Gamification (LG) plugin.
 *
 * @package Gorilla_Referral_Affiliate
 */

// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// ── Cross-Plugin Safety: detect if LG plugin is still active ──
// The gorilla_credit_log table is shared between LG and RA. If LG is still
// active, do not drop shared tables -- LG's uninstall will handle them.
$active_plugins = get_option('active_plugins', array());
$lg_active = false;
foreach ($active_plugins as $p) {
    if (strpos($p, 'gorilla-loyalty-gamification') !== false) {
        $lg_active = true;
        break;
    }
}

// 1. Affiliate clicks tablosunu sil (exclusively RA data)
$click_table = $wpdb->prefix . 'gorilla_affiliate_clicks';
$wpdb->query("DROP TABLE IF EXISTS {$click_table}");

// 2. Referral custom post type postlarini sil
$referral_posts = $wpdb->get_col($wpdb->prepare(
    "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
    'gorilla_referral'
));
foreach ($referral_posts as $pid) {
    wp_delete_post(intval($pid), true);
}

// 3. Referral/Affiliate ile ilgili options'lari sil
$options_to_delete = array(
    'gorilla_lr_enabled_referral',
    'gorilla_lr_referral_rate',
    'gorilla_lr_referral_auto',
    'gorilla_lr_enabled_affiliate',
    'gorilla_lr_affiliate_rate',
    'gorilla_lr_affiliate_cookie_days',
    'gorilla_lr_affiliate_min_order',
    'gorilla_lr_affiliate_first_only',
    'gorilla_lr_affiliate_allow_self',
    'gorilla_lr_affiliate_url_param',
    'gorilla_lr_dual_referral_enabled',
    'gorilla_lr_dual_referral_type',
    'gorilla_lr_dual_referral_amount',
    'gorilla_lr_dual_referral_min_order',
    'gorilla_lr_dual_referral_expiry_days',
    'gorilla_lr_tiered_affiliate_enabled',
    'gorilla_lr_affiliate_tiers',
    'gorilla_lr_recurring_affiliate_enabled',
    'gorilla_lr_recurring_affiliate_months',
    'gorilla_lr_recurring_affiliate_rate',
    'gorilla_lr_recurring_affiliate_max_orders',
    'gorilla_lr_fraud_detection_enabled',
    'gorilla_lr_fraud_last_run',
    'gorilla_ra_version',
    'gorilla_ra_flush_needed',
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// 4. Affiliate user meta'larini sil
$wpdb->delete($wpdb->usermeta, array('meta_key' => '_gorilla_affiliate_code'), array('%s'));
$wpdb->delete($wpdb->usermeta, array('meta_key' => '_gorilla_referred_by'), array('%s'));
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
    '_gorilla_affiliate_fraud_%'
));

// 5. Temizle transient'ler
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
    '_transient_gorilla_affiliate_%'
));
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
    '_transient_timeout_gorilla_affiliate_%'
));

// Flush rewrite rules
flush_rewrite_rules();
