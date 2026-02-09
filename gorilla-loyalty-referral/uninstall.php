<?php
/**
 * Gorilla Loyalty & Referral - Kaldırma
 * Eklenti silindiğinde veritabanını temizler
 */

if (!defined('WP_UNINSTALL_PLUGIN')) exit;

// Ayarları sil
$options = array(
    'gorilla_lr_tiers',
    'gorilla_lr_period_months',
    'gorilla_lr_referral_rate',
    'gorilla_lr_referral_auto',
    'gorilla_lr_credit_min_order',
    'gorilla_lr_enabled_loyalty',
    'gorilla_lr_enabled_referral',
    'gorilla_lr_version',
    'gorilla_lr_flush_needed',
    // Affiliate ayarlari
    'gorilla_lr_enabled_affiliate',
    'gorilla_lr_affiliate_rate',
    'gorilla_lr_affiliate_cookie_days',
    'gorilla_lr_affiliate_min_order',
    'gorilla_lr_affiliate_first_only',
    'gorilla_lr_affiliate_allow_self',
    'gorilla_lr_affiliate_url_param',
    // XP ayarlari
    'gorilla_lr_enabled_xp',
    'gorilla_lr_xp_per_order_rate',
    'gorilla_lr_xp_review',
    'gorilla_lr_xp_referral',
    'gorilla_lr_xp_affiliate',
    'gorilla_lr_xp_first_order',
    'gorilla_lr_xp_register',
    'gorilla_lr_xp_profile',
    'gorilla_lr_levels',
    // Bonus ayarlari
    'gorilla_lr_bonus_enabled',
    'gorilla_lr_bonus_multiplier',
    'gorilla_lr_bonus_start',
    'gorilla_lr_bonus_end',
    'gorilla_lr_bonus_label',
    // Credit expiry
    'gorilla_lr_credit_expiry_days',
    'gorilla_lr_credit_expiry_warn_days',
    // Gamification ayarlari
    'gorilla_lr_birthday_enabled',
    'gorilla_lr_birthday_xp',
    'gorilla_lr_birthday_credit',
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
    // Dual referral
    'gorilla_lr_dual_referral_enabled',
    'gorilla_lr_dual_referral_type',
    'gorilla_lr_dual_referral_amount',
    'gorilla_lr_dual_referral_min_order',
    'gorilla_lr_dual_referral_expiry_days',
    // Tiered affiliate
    'gorilla_lr_tiered_affiliate_enabled',
    'gorilla_lr_affiliate_tiers',
    // Recurring affiliate
    'gorilla_lr_recurring_affiliate_enabled',
    'gorilla_lr_recurring_affiliate_rate',
    'gorilla_lr_recurring_affiliate_months',
    'gorilla_lr_recurring_affiliate_max_orders',
    // Spin
    'gorilla_lr_spin_enabled',
    'gorilla_lr_spin_prizes',
    // Points shop
    'gorilla_lr_points_shop_enabled',
    'gorilla_lr_points_shop_rewards',
    // Social share
    'gorilla_lr_social_share_enabled',
    'gorilla_lr_social_share_xp',
    // QR
    'gorilla_lr_qr_enabled',
    'gorilla_lr_qr_method',
    // Coupon
    'gorilla_lr_coupon_enabled',
    // Legacy key (renamed to leaderboard_limit)
    'gorilla_lr_leaderboard_count',
);

// Zamanlayici temizle
wp_clear_scheduled_hook('gorilla_lr_daily_tier_check');

foreach ($options as $opt) {
    delete_option($opt);
}

// Referans başvurularını sil
$posts = get_posts(array(
    'post_type'   => 'gorilla_referral',
    'post_status' => 'any',
    'numberposts' => -1,
    'fields'      => 'ids',
));
foreach ($posts as $pid) {
    wp_delete_post($pid, true);
}

// User meta temizle
global $wpdb;
$meta_keys = array(
    '_gorilla_store_credit',
    '_gorilla_credit_log',
    '_gorilla_total_xp',
    '_gorilla_affiliate_code',
    '_gorilla_last_tier',
    '_gorilla_birthday',
    '_gorilla_birthday_awarded_year',
    '_gorilla_login_streak',
    '_gorilla_login_last_date',
    '_gorilla_login_streak_best',
    '_gorilla_badges',
    '_gorilla_spin_available',
    '_gorilla_spin_history',
    '_gorilla_milestones',
    '_gorilla_social_shares',
    '_gorilla_referred_by',
);

// Prepare placeholders
$placeholders = implode(', ', array_fill(0, count($meta_keys), '%s'));
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ({$placeholders})",
        ...$meta_keys
    )
);

// Milestone guard keys temizle
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        '_gorilla_milestone_done_%'
    )
);

// Birthday guard keys temizle
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        '_gorilla_birthday_awarded_%'
    )
);

// Veritabani tablolarini sil
$tables = array(
    $wpdb->prefix . 'gorilla_credit_log',
    $wpdb->prefix . 'gorilla_affiliate_clicks',
    $wpdb->prefix . 'gorilla_xp_log',
);
foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
}

// Transient temizle
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
        '_transient_gorilla_spending_%',
        '_transient_gorilla_lr_%',
        '_transient_gorilla_xp_%',
        '_transient_gorilla_ref_%'
    )
);

// Rewrite flush
flush_rewrite_rules();
