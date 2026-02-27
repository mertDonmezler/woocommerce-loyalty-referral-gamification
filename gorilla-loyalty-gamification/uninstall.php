<?php
/**
 * Gorilla Loyalty & Gamification - Uninstall
 *
 * Cleans up plugin data when uninstalled via WordPress admin.
 *
 * @package Gorilla_Loyalty_Gamification
 * @since   3.1.0
 */

if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

// Remove XP log table
$xp_table = $wpdb->prefix . 'gorilla_xp_log';
$wpdb->query("DROP TABLE IF EXISTS {$xp_table}");

// Drop credit_log table
$credit_table = $wpdb->prefix . 'gorilla_credit_log';
$wpdb->query("DROP TABLE IF EXISTS {$credit_table}");

// Remove all loyalty/gamification options
$options_to_delete = array(
    'gorilla_lr_enabled_loyalty',
    'gorilla_lr_enabled_xp',
    'gorilla_lr_period_months',
    'gorilla_lr_tiers',
    'gorilla_lr_levels',
    'gorilla_lr_tier_grace_days',
    'gorilla_lr_xp_per_order_rate',
    'gorilla_lr_xp_review',
    'gorilla_lr_xp_referral',
    'gorilla_lr_xp_affiliate',
    'gorilla_lr_xp_first_order',
    'gorilla_lr_xp_register',
    'gorilla_lr_xp_profile',
    'gorilla_lr_xp_expiry_enabled',
    'gorilla_lr_xp_expiry_months',
    'gorilla_lr_xp_expiry_warn_days',
    'gorilla_lr_category_xp_multipliers',
    'gorilla_lr_bonus_enabled',
    'gorilla_lr_bonus_multiplier',
    'gorilla_lr_bonus_start',
    'gorilla_lr_bonus_end',
    'gorilla_lr_bonus_label',
    'gorilla_lr_birthday_enabled',
    'gorilla_lr_birthday_xp',
    'gorilla_lr_birthday_credit',
    'gorilla_lr_anniversary_enabled',
    'gorilla_lr_anniversary_xp',
    'gorilla_lr_anniversary_credit',
    'gorilla_lr_streak_enabled',
    'gorilla_lr_streak_daily_xp',
    'gorilla_lr_streak_7day_bonus',
    'gorilla_lr_streak_30day_bonus',
    'gorilla_lr_badges_enabled',
    'gorilla_lr_leaderboard_enabled',
    'gorilla_lr_leaderboard_anonymize',
    'gorilla_lr_leaderboard_limit',
    'gorilla_lr_milestones_enabled',
    'gorilla_lr_milestones',
    'gorilla_lr_challenges_enabled',
    'gorilla_lr_challenges',
    'gorilla_lr_spin_enabled',
    'gorilla_lr_spin_prizes',
    'gorilla_lr_points_shop_enabled',
    'gorilla_lr_points_shop_rewards',
    'gorilla_lr_social_share_enabled',
    'gorilla_lr_social_share_xp',
    'gorilla_lr_qr_enabled',
    'gorilla_lr_transfer_enabled',
    'gorilla_lr_transfer_daily_limit',
    'gorilla_lr_transfer_min_amount',
    'gorilla_lr_transfer_fee_pct',
    'gorilla_lr_churn_enabled',
    'gorilla_lr_churn_months',
    'gorilla_lr_churn_bonus_credit',
    'gorilla_lr_churn_bonus_xp',
    'gorilla_lr_vip_early_access_enabled',
    'gorilla_lr_smart_coupon_enabled',
    'gorilla_lr_smart_coupon_inactive_days',
    'gorilla_lr_smart_coupon_discount',
    'gorilla_lr_smart_coupon_expiry',
    'gorilla_lr_social_proof_enabled',
    'gorilla_lr_social_proof_anonymize',
    'gorilla_lr_ga4_measurement_id',
    'gorilla_lr_webhook_url',
    'gorilla_lr_webhook_events',
    'gorilla_lr_sms_enabled',
    'gorilla_lr_twilio_sid',
    'gorilla_lr_twilio_token',
    'gorilla_lr_twilio_from',
    'gorilla_lr_sms_events',
    'gorilla_lr_credit_min_order',
    'gorilla_lr_credit_expiry_days',
    'gorilla_lr_credit_expiry_warn_days',
    'gorilla_lr_coupon_enabled',
    'gorilla_sc_version',
    'gorilla_lg_version',
    'gorilla_lg_flush_needed',
);

foreach ($options_to_delete as $opt) {
    delete_option($opt);
}

// Remove scheduled cron
wp_clear_scheduled_hook('gorilla_lr_daily_tier_check');
wp_clear_scheduled_hook('gorilla_sc_daily_check');

// Remove all user meta (batch delete for performance)
$meta_patterns = array(
    '_gorilla_total_xp',
    '_gorilla_birthday',
    '_gorilla_login_streak',
    '_gorilla_login_last_date',
    '_gorilla_login_streak_best',
    '_gorilla_badges',
    '_gorilla_spin_available',
    '_gorilla_spin_history',
    '_gorilla_milestones',
    '_gorilla_social_shares',
    '_gorilla_referred_by',
    '_gorilla_last_tier',
    '_gorilla_lr_tier_key',
    '_gorilla_tier_grace_until',
    '_gorilla_tier_grace_from',
    '_gorilla_challenges_progress',
    '_gorilla_notifications',
    '_gorilla_churn_risk',
    '_gorilla_churn_last_order',
    '_gorilla_sms_phone',
    '_gorilla_sms_optout',
);

foreach ($meta_patterns as $mk) {
    $wpdb->delete($wpdb->usermeta, array('meta_key' => $mk), array('%s'));
}

// Store Credit user meta
$wpdb->delete($wpdb->usermeta, array('meta_key' => '_gorilla_store_credit'), array('%s'));
$wpdb->delete($wpdb->usermeta, array('meta_key' => '_gorilla_credit_log'), array('%s'));

// Clean up LIKE-pattern meta keys
$like_patterns = array(
    '_gorilla_birthday_awarded_%',
    '_gorilla_milestone_done_%',
    '_gorilla_grace_warned_%',
    '_gorilla_anniversary_year_%',
    '_gorilla_churn_reengaged_%',
    '_gorilla_xp_expiry_%',
    '_gorilla_xp_warn_%',
    '_gorilla_smart_coupon_%',
    '_gorilla_transfer_total_%',
    '_gorilla_transfer_today_%',
);

foreach ($like_patterns as $pattern) {
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        $pattern
    ));
}

// Store Credit transient cleanup
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
    '_transient_gorilla_sc_expiry_warn_%',
    '_transient_timeout_gorilla_sc_expiry_warn_%'
));

// Flush rewrite rules
flush_rewrite_rules();
