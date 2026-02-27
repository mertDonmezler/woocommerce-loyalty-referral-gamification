<?php
/**
 * Gorilla LG - XP & Level Sistemi
 * Gamification: coklu kaynaktan XP kazanma ve level sistemi
 *
 * @package Gorilla_Loyalty_Gamification
 * @version 3.1.0
 */

if (!defined('ABSPATH')) exit;

// ==============================================================
// XP YONETIMI
// ==============================================================

function gorilla_xp_add($user_id, $amount, $reason = '', $reference_type = null, $reference_id = null) {
    if (!$user_id || $amount <= 0) return gorilla_xp_get_balance($user_id);
    if (get_option('gorilla_lr_enabled_xp', 'yes') !== 'yes') return gorilla_xp_get_balance($user_id);

    if ($reference_type && $reference_id) {
        if (gorilla_xp_has_been_awarded($user_id, $reference_type, $reference_id)) {
            return gorilla_xp_get_balance($user_id);
        }
    }

    $multiplier = gorilla_xp_get_bonus_multiplier();
    if ($multiplier > 1) $amount = intval(round($amount * $multiplier));

    global $wpdb;
    $table = $wpdb->prefix . 'gorilla_xp_log';

    $wpdb->query('START TRANSACTION');
    try {
        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = '_gorilla_total_xp' FOR UPDATE",
            $user_id
        ));
        if ($current === null) {
            add_user_meta($user_id, '_gorilla_total_xp', 0, true);
            $current = 0;
        }
        $current = intval($current);
        $new_balance = $current + intval($amount);

        $wpdb->update($wpdb->usermeta, array('meta_value' => $new_balance),
            array('user_id' => $user_id, 'meta_key' => '_gorilla_total_xp'), array('%d'), array('%d', '%s'));

        if (gorilla_lr_table_exists($table)) {
            $wpdb->insert($table, array(
                'user_id' => $user_id, 'amount' => intval($amount), 'balance_after' => $new_balance,
                'reason' => sanitize_text_field($reason),
                'reference_type' => $reference_type ? sanitize_key($reference_type) : null,
                'reference_id' => $reference_id ? intval($reference_id) : null,
                'created_at' => current_time('mysql'),
            ), array('%d', '%d', '%d', '%s', '%s', '%d', '%s'));
        }
        $wpdb->query('COMMIT');
        wp_cache_delete($user_id, 'user_meta');
        delete_transient('gorilla_xp_' . $user_id);
    } catch (\Throwable $e) {
        $wpdb->query('ROLLBACK');
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Gorilla LG xp_add error: ' . $e->getMessage());
        return gorilla_xp_get_balance($user_id);
    }

    $old_level = gorilla_xp_calculate_level_from_xp($current);
    $new_level = gorilla_xp_calculate_level_from_xp($new_balance);
    if ($new_level['number'] > $old_level['number']) {
        do_action('gorilla_xp_level_up', $user_id, $old_level, $new_level);
    }
    do_action('gorilla_xp_added', $user_id, intval($amount), $reason);
    return $new_balance;
}

function gorilla_xp_get_bonus_multiplier() {
    if (get_option('gorilla_lr_bonus_enabled', 'no') !== 'yes') return 1;
    $start = get_option('gorilla_lr_bonus_start', '');
    $end = get_option('gorilla_lr_bonus_end', '');
    if (empty($start) || empty($end)) return 1;
    $now = current_time('Y-m-d');
    if ($now >= $start && $now <= $end) return floatval(get_option('gorilla_lr_bonus_multiplier', 1.5));
    return 1;
}

function gorilla_xp_get_balance($user_id) {
    if (!$user_id) return 0;
    $cached = get_transient('gorilla_xp_' . $user_id);
    if ($cached !== false) return intval($cached);
    $xp = get_user_meta($user_id, '_gorilla_total_xp', true);
    $xp = $xp ? intval($xp) : 0;
    set_transient('gorilla_xp_' . $user_id, $xp, HOUR_IN_SECONDS);
    return $xp;
}

function gorilla_xp_has_been_awarded($user_id, $reference_type, $reference_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'gorilla_xp_log';
    if (!gorilla_lr_table_exists($table)) return false;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND reference_type = %s AND reference_id = %d",
        $user_id, $reference_type, $reference_id
    )) > 0;
}

function gorilla_xp_get_log($user_id, $limit = 20) {
    global $wpdb;
    $table = $wpdb->prefix . 'gorilla_xp_log';
    if (!gorilla_lr_table_exists($table)) return array();
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d", $user_id, $limit
    ), ARRAY_A);
}

// ==============================================================
// LEVEL SISTEMI
// ==============================================================

function gorilla_xp_get_levels($force_refresh = false) {
    static $cached = null;
    if ($cached !== null && !$force_refresh) return $cached;
    $levels = get_option('gorilla_lr_levels', array());
    if (empty($levels)) {
        $levels = array(
            'level_1' => array('label' => 'Caylak', 'min_xp' => 0, 'emoji' => "\xF0\x9F\x8C\xB1", 'color' => '#a3e635'),
            'level_2' => array('label' => 'Kesifci', 'min_xp' => 50, 'emoji' => "\xF0\x9F\x94\x8D", 'color' => '#22d3ee'),
            'level_3' => array('label' => 'Alisverisci', 'min_xp' => 150, 'emoji' => "\xF0\x9F\x9B\x92", 'color' => '#60a5fa'),
            'level_4' => array('label' => 'Sadik', 'min_xp' => 400, 'emoji' => "\xE2\xAD\x90", 'color' => '#facc15'),
            'level_5' => array('label' => 'Uzman', 'min_xp' => 800, 'emoji' => "\xF0\x9F\x8F\x85", 'color' => '#f97316'),
            'level_6' => array('label' => 'VIP', 'min_xp' => 1500, 'emoji' => "\xF0\x9F\x92\x8E", 'color' => '#a855f7'),
            'level_7' => array('label' => 'Efsane', 'min_xp' => 3000, 'emoji' => "\xF0\x9F\x91\x91", 'color' => '#fbbf24'),
        );
    }
    uasort($levels, function($a, $b) { return ($a['min_xp'] ?? 0) <=> ($b['min_xp'] ?? 0); });
    $cached = $levels;
    return $cached;
}

function gorilla_xp_calculate_level_from_xp($xp) {
    $levels = gorilla_xp_get_levels();
    $xp = intval($xp);
    $current_key = 'level_1';
    $current_level = reset($levels);
    foreach ($levels as $key => $level) {
        if ($xp >= intval($level['min_xp'] ?? 0)) { $current_key = $key; $current_level = $level; }
    }
    $number = intval(str_replace('level_', '', $current_key));
    return array(
        'key' => $current_key, 'number' => $number,
        'label' => $current_level['label'] ?? 'Level ' . $number,
        'emoji' => $current_level['emoji'] ?? "\xF0\x9F\x8E\xAE",
        'color' => $current_level['color'] ?? '#999',
        'min_xp' => intval($current_level['min_xp'] ?? 0),
    );
}

function gorilla_xp_calculate_level($user_id) {
    $xp = gorilla_xp_get_balance($user_id);
    $level = gorilla_xp_calculate_level_from_xp($xp);
    $level['xp'] = $xp;
    return $level;
}

function gorilla_xp_get_next_level($user_id) {
    $xp = gorilla_xp_get_balance($user_id);
    $current = gorilla_xp_calculate_level_from_xp($xp);
    $levels = gorilla_xp_get_levels();
    $found_current = false;
    foreach ($levels as $key => $level) {
        if ($found_current) {
            $min_xp = intval($level['min_xp'] ?? 0);
            $current_min = intval($current['min_xp'] ?? 0);
            $range = $min_xp - $current_min;
            return array(
                'key' => $key, 'number' => intval(str_replace('level_', '', $key)),
                'label' => $level['label'] ?? '', 'emoji' => $level['emoji'] ?? '',
                'color' => $level['color'] ?? '#999', 'min_xp' => $min_xp,
                'remaining' => max(0, $min_xp - $xp),
                'progress' => $range > 0 ? min(100, max(0, (($xp - $current_min) / $range) * 100)) : 0,
            );
        }
        if ($key === $current['key']) $found_current = true;
    }
    return null;
}

// ==============================================================
// XP KAZANMA HOOK'LARI
// ==============================================================

add_action('woocommerce_order_status_completed', 'gorilla_xp_on_order_complete', 20, 1);
add_action('woocommerce_order_status_processing', 'gorilla_xp_on_order_complete', 20, 1);
function gorilla_xp_on_order_complete($order_id) {
    if (get_option('gorilla_lr_enabled_xp', 'yes') !== 'yes') return;
    $order = wc_get_order($order_id);
    if (!$order) return;
    $user_id = $order->get_customer_id();
    if (!$user_id) return;
    if ($order->get_meta('_gorilla_xp_awarded') === 'yes') return;
    $order->update_meta_data('_gorilla_xp_awarded', 'yes');
    $order->save();

    $rate = intval(get_option('gorilla_lr_xp_per_order_rate', 10));
    if ($rate <= 0) $rate = 10;

    $xp = 0;
    $cat_multipliers = get_option('gorilla_lr_category_xp_multipliers', array());
    foreach ($order->get_items() as $item) {
        $product_id = intval($item->get_product_id());
        $line_total = floatval($item->get_total());
        $prod_mult = floatval(get_post_meta($product_id, '_gorilla_xp_multiplier', true));
        if ($prod_mult <= 0) $prod_mult = 1.0;
        $cat_mult = 1.0;
        if (!empty($cat_multipliers)) {
            $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
            if (!is_wp_error($terms)) {
                foreach ($terms as $term_id) {
                    if (isset($cat_multipliers[$term_id]) && floatval($cat_multipliers[$term_id]) > $cat_mult) {
                        $cat_mult = floatval($cat_multipliers[$term_id]);
                    }
                }
            }
        }
        $xp += intval(($line_total / $rate) * max($prod_mult, $cat_mult));
    }
    if ($xp > 0) gorilla_xp_add($user_id, $xp, sprintf('Siparis #%d tamamlandi', $order_id), 'order', $order_id);

    $first_order_bonus = intval(get_option('gorilla_lr_xp_first_order', 100));
    if ($first_order_bonus > 0) {
        $previous = wc_get_orders(array('customer_id' => $user_id, 'status' => array('completed', 'processing'), 'limit' => 2, 'exclude' => array($order_id)));
        if (empty($previous) && !gorilla_xp_has_been_awarded($user_id, 'first_order', $user_id)) {
            gorilla_xp_add($user_id, $first_order_bonus, 'Ilk siparis bonusu', 'first_order', $user_id);
        }
    }
}

// Review XP
add_action('comment_unapproved_to_approved', function($comment) {
    if (is_object($comment)) gorilla_xp_on_review_approved($comment->comment_ID);
}, 10, 1);
add_action('wp_insert_comment', function($comment_id, $comment) {
    if ($comment->comment_approved == 1) gorilla_xp_on_review_approved($comment_id);
}, 10, 2);

function gorilla_xp_on_review_approved($comment_id) {
    if (get_option('gorilla_lr_enabled_xp', 'yes') !== 'yes') return;
    static $processed = array();
    if (isset($processed[$comment_id])) return;
    $processed[$comment_id] = true;

    $comment = get_comment($comment_id);
    if (!$comment) return;
    $post = get_post($comment->comment_post_ID);
    if (!$post || $post->post_type !== 'product') return;
    $user_id = intval($comment->user_id);
    if (!$user_id) return;
    $xp = intval(get_option('gorilla_lr_xp_review', 25));
    if ($xp <= 0) return;
    gorilla_xp_add($user_id, $xp, sprintf('Urun yorumu: %s', get_the_title($comment->comment_post_ID)), 'review', $comment_id);
}

// Register XP
add_action('user_register', function($user_id) {
    if (get_option('gorilla_lr_enabled_xp', 'yes') !== 'yes') return;
    $xp = intval(get_option('gorilla_lr_xp_register', 10));
    if ($xp > 0) gorilla_xp_add($user_id, $xp, 'Hosgeldin bonusu', 'register', $user_id);
}, 10, 1);

// Profile complete XP
add_action('profile_update', 'gorilla_xp_on_profile_complete', 10, 2);
add_action('woocommerce_save_account_details', function($user_id) { gorilla_xp_on_profile_complete($user_id); }, 10, 1);
function gorilla_xp_on_profile_complete($user_id, $old_user_data = null) {
    if (get_option('gorilla_lr_enabled_xp', 'yes') !== 'yes') return;
    static $processed = array();
    if (isset($processed[$user_id])) return;
    $processed[$user_id] = true;
    if (gorilla_xp_has_been_awarded($user_id, 'profile', $user_id)) return;

    $first_name = get_user_meta($user_id, 'first_name', true);
    $last_name = get_user_meta($user_id, 'last_name', true);
    $phone = get_user_meta($user_id, 'billing_phone', true);
    $address = get_user_meta($user_id, 'billing_address_1', true);
    $city = get_user_meta($user_id, 'billing_city', true);
    if (empty($first_name) || empty($last_name)) return;
    if (empty($phone) && (empty($address) || empty($city))) return;

    $xp = intval(get_option('gorilla_lr_xp_profile', 20));
    if ($xp > 0) gorilla_xp_add($user_id, $xp, 'Profil tamamlama bonusu', 'profile', $user_id);
}

// ==============================================================
// REFERRAL & AFFILIATE XP (cross-plugin via hooks)
// ==============================================================

function gorilla_xp_on_referral_approved($user_id, $ref_id) {
    if (get_option('gorilla_lr_enabled_xp', 'yes') !== 'yes') return;
    $xp = intval(get_option('gorilla_lr_xp_referral', 50));
    if ($xp > 0) gorilla_xp_add($user_id, $xp, 'Video referans onayi', 'referral', $ref_id);
}

function gorilla_xp_on_affiliate_sale($user_id, $order_id) {
    if (get_option('gorilla_lr_enabled_xp', 'yes') !== 'yes') return;
    $xp = intval(get_option('gorilla_lr_xp_affiliate', 30));
    if ($xp > 0) gorilla_xp_add($user_id, $xp, sprintf('Affiliate satis #%d', $order_id), 'affiliate', $order_id);
}

// ==============================================================
// ADMIN STATS
// ==============================================================

function gorilla_xp_get_admin_stats() {
    global $wpdb;
    $table = $wpdb->prefix . 'gorilla_xp_log';
    $stats = array('total_xp' => 0, 'avg_xp' => 0, 'users_with_xp' => 0, 'level_distribution' => array());
    if (!gorilla_lr_table_exists($table)) return $stats;

    $stats['total_xp'] = intval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE amount > %d", 0)));
    $stats['users_with_xp'] = intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = %s AND CAST(meta_value AS UNSIGNED) > 0", '_gorilla_total_xp'
    )));
    if ($stats['users_with_xp'] > 0) {
        $stats['avg_xp'] = round(floatval($wpdb->get_var($wpdb->prepare(
            "SELECT AVG(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->usermeta} WHERE meta_key = %s AND CAST(meta_value AS UNSIGNED) > 0", '_gorilla_total_xp'
        ))));
    }
    return $stats;
}

function gorilla_xp_get_recent_activity($limit = 10) {
    global $wpdb;
    $table = $wpdb->prefix . 'gorilla_xp_log';
    if (!gorilla_lr_table_exists($table)) return array();
    return $wpdb->get_results($wpdb->prepare(
        "SELECT xl.*, u.display_name FROM {$table} xl LEFT JOIN {$wpdb->users} u ON xl.user_id = u.ID ORDER BY xl.created_at DESC LIMIT %d", $limit
    ));
}

// ── Login Streaks ────────────────────────────────────────
add_action('wp_login', 'gorilla_xp_on_login', 10, 2);
function gorilla_xp_on_login($user_login, $user) {
    if (get_option('gorilla_lr_streak_enabled', 'no') !== 'yes') return;
    if (get_option('gorilla_lr_enabled_xp', 'yes') !== 'yes') return;
    $user_id = $user->ID;
    $today = current_time('Y-m-d');
    $yesterday = wp_date('Y-m-d', strtotime('-1 day', strtotime($today)));
    $last_date = get_user_meta($user_id, '_gorilla_login_last_date', true);
    if ($last_date === $today) return;

    $streak = intval(get_user_meta($user_id, '_gorilla_login_streak', true));
    $streak = ($last_date === $yesterday) ? $streak + 1 : 1;

    update_user_meta($user_id, '_gorilla_login_last_date', $today);
    update_user_meta($user_id, '_gorilla_login_streak', $streak);
    $best = intval(get_user_meta($user_id, '_gorilla_login_streak_best', true));
    if ($streak > $best) update_user_meta($user_id, '_gorilla_login_streak_best', $streak);

    $daily_xp = intval(get_option('gorilla_lr_streak_daily_xp', 5));
    if ($daily_xp > 0) gorilla_xp_add($user_id, $daily_xp, sprintf('Gunluk giris serisi (%d. gun)', $streak), 'login', strtotime($today));
    if ($streak === 7) {
        $bonus = intval(get_option('gorilla_lr_streak_7day_bonus', 50));
        if ($bonus > 0) gorilla_xp_add($user_id, $bonus, '7 gunluk giris serisi bonusu!', 'streak_7', strtotime($today) + 7);
    }
    if ($streak === 30) {
        $bonus = intval(get_option('gorilla_lr_streak_30day_bonus', 200));
        if ($bonus > 0) gorilla_xp_add($user_id, $bonus, '30 gunluk giris serisi bonusu!', 'streak_30', strtotime($today) + 30);
    }
    if ($streak >= 30 && function_exists('gorilla_badge_award')) gorilla_badge_award($user_id, 'streak_master');
}

// ── Birthday Rewards ─────────────────────────────────────
add_action('gorilla_lr_daily_tier_check', 'gorilla_xp_check_birthdays');
function gorilla_xp_check_birthdays() {
    if (get_option('gorilla_lr_birthday_enabled', 'no') !== 'yes') return;
    global $wpdb;
    $today_md = current_time('m-d');
    $current_year = current_time('Y');
    $user_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_gorilla_birthday' AND RIGHT(meta_value, 5) = %s", $today_md
    ));
    if (empty($user_ids)) return;

    $xp_amount = intval(get_option('gorilla_lr_birthday_xp', 50));
    $credit_amount = floatval(get_option('gorilla_lr_birthday_credit', 10));

    foreach ($user_ids as $user_id) {
        $guard_key = '_gorilla_birthday_awarded_' . $current_year;
        $marked = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) SELECT %d, %s, '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s)",
            $user_id, $guard_key, $user_id, $guard_key
        ));
        if (!$marked) continue;
        if ($xp_amount > 0) gorilla_xp_add($user_id, $xp_amount, 'Dogum gunu bonusu!', 'birthday', intval($current_year));
        if ($credit_amount > 0 && function_exists('gorilla_credit_adjust')) gorilla_credit_adjust($user_id, $credit_amount, 'birthday', 'Dogum gunu hediyesi!', 0, 30);
        if (function_exists('gorilla_email_birthday')) gorilla_email_birthday($user_id, $xp_amount, $credit_amount);
    }
}

// ── Anniversary Rewards ──────────────────────────────────
add_action('gorilla_lr_daily_tier_check', 'gorilla_xp_check_anniversaries');
function gorilla_xp_check_anniversaries() {
    if (get_option('gorilla_lr_anniversary_enabled', 'no') !== 'yes') return;
    global $wpdb;
    $today_md = current_time('m-d');
    $current_year = intval(current_time('Y'));
    $xp_amount = intval(get_option('gorilla_lr_anniversary_xp', 100));
    $credit_amount = floatval(get_option('gorilla_lr_anniversary_credit', 20));

    $users = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, user_registered FROM {$wpdb->users} WHERE DATE_FORMAT(user_registered, '%%m-%%d') = %s", $today_md
    ));
    if (empty($users)) return;

    foreach ($users as $user) {
        $years_since = $current_year - intval(date('Y', strtotime($user->user_registered)));
        if ($years_since <= 0) continue;
        $guard_key = '_gorilla_anniversary_year_' . $current_year;
        $marked = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) SELECT %d, %s, %s FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s)",
            $user->ID, $guard_key, $years_since, $user->ID, $guard_key
        ));
        if (!$marked) continue;
        $user_id = intval($user->ID);
        if ($xp_amount > 0) gorilla_xp_add($user_id, $xp_amount * $years_since, sprintf('%d. yil donumu bonusu!', $years_since), 'anniversary', $years_since);
        if ($credit_amount > 0 && function_exists('gorilla_credit_adjust')) gorilla_credit_adjust($user_id, $credit_amount, 'anniversary', sprintf('%d. yil donumu hediyesi!', $years_since), 0, 30);
        if (function_exists('gorilla_badge_award')) gorilla_badge_award($user_id, 'anniversary_' . $years_since);
    }
}

// ── XP Expiry ────────────────────────────────────────────
add_action('gorilla_lr_daily_tier_check', 'gorilla_xp_check_expiry');
function gorilla_xp_check_expiry() {
    if (get_option('gorilla_lr_xp_expiry_enabled', 'no') !== 'yes') return;
    $expiry_months = intval(get_option('gorilla_lr_xp_expiry_months', 12));
    if ($expiry_months <= 0) return;
    global $wpdb;
    $table = $wpdb->prefix . 'gorilla_xp_log';
    if (!gorilla_lr_table_exists($table)) return;
    $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$expiry_months} months"));

    $expired_users = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, SUM(amount) as expired_xp FROM {$table} WHERE created_at <= %s AND amount > 0 AND reason NOT LIKE '%%expired%%' AND reference_type != 'xp_expired' GROUP BY user_id",
        $cutoff_date
    ));
    if (empty($expired_users)) return;

    foreach ($expired_users as $row) {
        $user_id = intval($row->user_id);
        $expired_xp = intval($row->expired_xp);
        if ($expired_xp <= 0) continue;
        $guard_key = '_gorilla_xp_expiry_' . current_time('Y-m');
        if (get_user_meta($user_id, $guard_key, true)) continue;
        update_user_meta($user_id, $guard_key, current_time('mysql'));
        if (function_exists('gorilla_xp_deduct')) gorilla_xp_deduct($user_id, $expired_xp, sprintf('%d aydan eski XP suresi doldu', $expiry_months), 'xp_expired', 0);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET reason = CONCAT(reason, ' [expired]') WHERE user_id = %d AND created_at <= %s AND amount > 0 AND reason NOT LIKE '%%expired%%' AND reference_type != 'xp_expired'",
            $user_id, $cutoff_date
        ));
    }
}

// ── Leaderboard ──────────────────────────────────────────
function gorilla_xp_get_leaderboard($period = 'monthly', $limit = 10, $anonymize_override = null) {
    if (get_option('gorilla_lr_leaderboard_enabled', 'no') !== 'yes') return array();
    global $wpdb;
    $table = $wpdb->prefix . 'gorilla_xp_log';
    if (!gorilla_lr_table_exists($table)) return array();
    $date_from = current_time('Y-m-01 00:00:00');
    $anonymize = ($anonymize_override !== null) ? $anonymize_override : (get_option('gorilla_lr_leaderboard_anonymize', 'no') === 'yes');

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT xl.user_id, SUM(xl.amount) as xp_earned, u.display_name FROM {$table} xl INNER JOIN {$wpdb->users} u ON xl.user_id = u.ID WHERE xl.created_at >= %s AND xl.amount > 0 GROUP BY xl.user_id ORDER BY xp_earned DESC LIMIT %d",
        $date_from, $limit
    ));

    $leaderboard = array();
    foreach ($results as $i => $row) {
        $name = $row->display_name;
        if ($anonymize) $name = mb_substr($name, 0, 1) . str_repeat('*', max(1, mb_strlen($name) - 1));
        $leaderboard[] = array('rank' => $i + 1, 'user_id' => intval($row->user_id), 'display_name' => $name, 'xp_earned' => intval($row->xp_earned));
    }
    return $leaderboard;
}

add_shortcode('gorilla_leaderboard', 'gorilla_leaderboard_shortcode');
function gorilla_leaderboard_shortcode($atts) {
    $atts = shortcode_atts(array('period' => 'monthly', 'limit' => 10, 'anonymize' => '', 'title' => ''), $atts, 'gorilla_leaderboard');
    if (get_option('gorilla_lr_leaderboard_enabled', 'no') !== 'yes') return '';
    $limit = absint($atts['limit']);
    if ($limit < 1 || $limit > 50) $limit = 10;
    $anon_override = $atts['anonymize'] !== '' ? ($atts['anonymize'] === 'yes') : null;
    $leaderboard = gorilla_xp_get_leaderboard($atts['period'], $limit, $anon_override);
    if (empty($leaderboard)) return '<p style="color:#888;text-align:center;padding:20px;">Henuz leaderboard verisi yok.</p>';

    $current_user_id = get_current_user_id();
    $title = $atts['title'] !== '' ? esc_html($atts['title']) : 'Bu Ayin Liderleri';
    $medals = array(1 => "\xF0\x9F\xA5\x87", 2 => "\xF0\x9F\xA5\x88", 3 => "\xF0\x9F\xA5\x89");

    ob_start(); ?>
    <div class="glr-leaderboard-shortcode" style="max-width:520px;margin:0 auto;">
        <h3 style="font-size:20px;font-weight:800;text-align:center;margin-bottom:16px;"><?php echo $title; ?></h3>
        <div class="glr-card" style="padding:0;overflow:hidden;border-radius:14px;box-shadow:0 1px 6px rgba(0,0,0,.08);">
            <?php $rank = 1; foreach ($leaderboard as $leader):
                if (!is_array($leader)) continue;
                $is_current = (intval($leader['user_id'] ?? 0) === $current_user_id);
                $medal = $medals[$rank] ?? $rank . '.';
            ?>
            <div class="glr-leaderboard-row <?php echo $is_current ? 'current-user' : ''; ?>" data-rank="<?php echo $rank; ?>">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="font-size:18px;min-width:30px;"><?php echo $medal; ?></span>
                    <span><?php echo esc_html($leader['display_name'] ?? 'Kullanici'); ?></span>
                    <?php if ($is_current): ?><span style="font-size:11px;color:#3b82f6;">(Siz)</span><?php endif; ?>
                </div>
                <div style="font-weight:700;color:#22c55e;"><?php echo number_format_i18n(intval($leader['xp_earned'] ?? 0)); ?> XP</div>
            </div>
            <?php $rank++; endforeach; ?>
        </div>
    </div>
    <?php return ob_get_clean();
}

// ── Milestones ───────────────────────────────────────────
function gorilla_xp_check_milestones($user_id) {
    if (get_option('gorilla_lr_milestones_enabled', 'no') !== 'yes') return;
    if (!$user_id) return;
    $milestones = get_option('gorilla_lr_milestones', array());
    if (empty($milestones)) return;
    $completed = get_user_meta($user_id, '_gorilla_milestones', true);
    if (!is_array($completed)) $completed = array();

    foreach ($milestones as $m) {
        $mid = $m['id'] ?? '';
        if (!$mid || in_array($mid, $completed)) continue;
        $progress = gorilla_milestone_get_progress($user_id, $m);
        if ($progress < 100) continue;

        global $wpdb;
        $guard_key = '_gorilla_milestone_done_' . sanitize_key($mid);
        $m_marked = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) SELECT %d, %s, '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s)",
            $user_id, $guard_key, $user_id, $guard_key
        ));
        if (!$m_marked) continue;

        $completed[] = $mid;
        update_user_meta($user_id, '_gorilla_milestones', $completed);
        $xp_reward = intval($m['xp_reward'] ?? 0);
        if ($xp_reward > 0) gorilla_xp_add($user_id, $xp_reward, sprintf('Hedef tamamlandi: %s', $m['label'] ?? ''), 'milestone', crc32($mid));
        $credit_reward = floatval($m['credit_reward'] ?? 0);
        if ($credit_reward > 0 && function_exists('gorilla_credit_adjust')) gorilla_credit_adjust($user_id, $credit_reward, 'milestone', sprintf('Hedef odulu: %s', $m['label'] ?? ''), 0, 0);
        if (function_exists('gorilla_email_milestone_reached')) gorilla_email_milestone_reached($user_id, $m);
        if (get_option('gorilla_lr_spin_enabled', 'no') === 'yes' && function_exists('gorilla_spin_grant')) gorilla_spin_grant($user_id, 'milestone');
    }
}

function gorilla_milestone_get_progress($user_id, $milestone) {
    $type = $milestone['type'] ?? '';
    $target = floatval($milestone['target'] ?? 1);
    if ($target <= 0) return 100;
    $current = 0;
    switch ($type) {
        case 'total_orders':
            $r = wc_get_orders(array('customer_id' => $user_id, 'status' => array('completed', 'processing'), 'limit' => intval($target) + 1, 'return' => 'ids'));
            $current = is_array($r) ? count($r) : 0;
            break;
        case 'total_spending':
            $current = function_exists('gorilla_loyalty_get_spending') ? gorilla_loyalty_get_spending($user_id) : 0;
            break;
        case 'total_reviews':
            global $wpdb;
            $current = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d AND comment_type = 'review' AND comment_approved = '1'", $user_id)));
            break;
        case 'total_referrals':
            $current = count(get_posts(array('post_type' => 'gorilla_referral', 'post_status' => 'grla_approved', 'meta_key' => '_ref_user_id', 'meta_value' => $user_id, 'numberposts' => intval($target) + 1, 'fields' => 'ids')) ?: array());
            break;
        case 'total_xp':
            $current = gorilla_xp_get_balance($user_id);
            break;
        case 'account_age':
            $user = get_userdata($user_id);
            if ($user) $current = intval((time() - strtotime($user->user_registered)) / DAY_IN_SECONDS);
            break;
    }
    return min(100, ($current / $target) * 100);
}

add_action('woocommerce_order_status_completed', function($order_id) {
    $order = wc_get_order($order_id);
    if ($order && $order->get_customer_id()) gorilla_xp_check_milestones($order->get_customer_id());
}, 25);
add_action('gorilla_xp_level_up', function($user_id) { gorilla_xp_check_milestones($user_id); }, 10, 1);

// ── XP Deduct ────────────────────────────────────────────
function gorilla_xp_deduct($user_id, $amount, $reason = '', $reference_type = null, $reference_id = null) {
    if (!$user_id || $amount <= 0) return false;
    if (get_option('gorilla_lr_enabled_xp', 'yes') !== 'yes') return false;
    global $wpdb;
    $table = $wpdb->prefix . 'gorilla_xp_log';
    try {
        $wpdb->query('START TRANSACTION');
        $current_xp = intval($wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = '_gorilla_total_xp' FOR UPDATE", $user_id
        )));
        if ($current_xp < $amount) { $wpdb->query('ROLLBACK'); return false; }
        $new_xp = $current_xp - $amount;
        $wpdb->update($wpdb->usermeta, array('meta_value' => $new_xp), array('user_id' => $user_id, 'meta_key' => '_gorilla_total_xp'), array('%d'), array('%d', '%s'));
        if (gorilla_lr_table_exists($table)) {
            $wpdb->insert($table, array('user_id' => $user_id, 'amount' => -$amount, 'balance_after' => $new_xp, 'reason' => $reason, 'reference_type' => $reference_type, 'reference_id' => $reference_id, 'created_at' => current_time('mysql')), array('%d', '%d', '%d', '%s', '%s', '%d', '%s'));
        }
        $wpdb->query('COMMIT');
        wp_cache_delete($user_id, 'user_meta');
        delete_transient('gorilla_xp_' . $user_id);
        return $new_xp;
    } catch (\Throwable $e) {
        $wpdb->query('ROLLBACK');
        return false;
    }
}

// ── Product XP Multiplier Meta Box ───────────────────────
add_action('woocommerce_product_options_general_product_data', function() {
    woocommerce_wp_select(array(
        'id' => '_gorilla_xp_multiplier', 'label' => 'XP Carpani',
        'description' => 'Bu urunun siparislerinde XP carpani.', 'desc_tip' => true,
        'options' => array('1' => '1x (Normal)', '1.5' => '1.5x', '2' => '2x', '3' => '3x'),
    ));
});
add_action('woocommerce_process_product_meta', function($post_id) {
    if (isset($_POST['_gorilla_xp_multiplier'])) {
        $val = floatval($_POST['_gorilla_xp_multiplier']);
        if (!in_array($val, array(1, 1.5, 2, 3), true)) $val = 1;
        update_post_meta($post_id, '_gorilla_xp_multiplier', $val);
    }
});
add_action('woocommerce_single_product_summary', function() {
    global $product;
    if (!$product || get_option('gorilla_lr_enabled_xp', 'yes') !== 'yes') return;
    $multiplier = floatval(get_post_meta($product->get_id(), '_gorilla_xp_multiplier', true));
    if ($multiplier > 1) {
        printf('<div style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#6366f1);color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;margin:8px 0;">%sx XP</div>', esc_html($multiplier));
    }
}, 7);
