<?php
/**
 * Gorilla LR - XP & Level Sistemi
 * Gamification: coklu kaynaktan XP kazanma ve level sistemi
 *
 * @author Mert Donmezler
 * @copyright 2025-2026 Mert Donmezler
 * @version 3.0.0
 */

if (!defined('ABSPATH')) exit;

// ==============================================================
// XP YONETIMI
// ==============================================================

/**
 * Kullaniciya XP ekle - Transaction ile atomik
 */
function gorilla_xp_add($user_id, $amount, $reason = '', $reference_type = null, $reference_id = null) {
    if (!$user_id || $amount <= 0) {
        return gorilla_xp_get_balance($user_id);
    }

    // XP sistemi aktif mi?
    if (get_option('gorilla_lr_enabled_xp', 'yes') !== 'yes') {
        return gorilla_xp_get_balance($user_id);
    }

    // Duplicate kontrolu (ayni reference_type + reference_id)
    if ($reference_type && $reference_id) {
        if (gorilla_xp_has_been_awarded($user_id, $reference_type, $reference_id)) {
            return gorilla_xp_get_balance($user_id);
        }
    }

    // Seasonal bonus carpani uygula
    $multiplier = gorilla_xp_get_bonus_multiplier();
    if ($multiplier > 1) {
        $amount = intval(round($amount * $multiplier));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'gorilla_xp_log';

    // Transaction ile atomik islem
    $wpdb->query('START TRANSACTION');

    try {
        // Row-level lock ile mevcut bakiyeyi oku
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

        // User meta guncelle (direkt SQL)
        $wpdb->update(
            $wpdb->usermeta,
            array('meta_value' => $new_balance),
            array('user_id' => $user_id, 'meta_key' => '_gorilla_total_xp'),
            array('%d'),
            array('%d', '%s')
        );

        // Log kaydet
        if (gorilla_lr_table_exists($table)) {
            $wpdb->insert($table, array(
                'user_id'        => $user_id,
                'amount'         => intval($amount),
                'balance_after'  => $new_balance,
                'reason'         => sanitize_text_field($reason),
                'reference_type' => $reference_type ? sanitize_key($reference_type) : null,
                'reference_id'   => $reference_id ? intval($reference_id) : null,
                'created_at'     => current_time('mysql'),
            ), array('%d', '%d', '%d', '%s', '%s', '%d', '%s'));
        }

        $wpdb->query('COMMIT');

        // WP cache temizle
        wp_cache_delete($user_id, 'user_meta');
        delete_transient('gorilla_xp_' . $user_id);

    } catch (\Throwable $e) {
        $wpdb->query('ROLLBACK');
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla LR xp_add error: ' . $e->getMessage());
        }
        return gorilla_xp_get_balance($user_id);
    }

    // Level degisikligi kontrolu (transaction disinda)
    $old_level = gorilla_xp_calculate_level_from_xp($current);
    $new_level = gorilla_xp_calculate_level_from_xp($new_balance);

    if ($new_level['number'] > $old_level['number']) {
        // Level atladi - hook tetikle
        do_action('gorilla_xp_level_up', $user_id, $old_level, $new_level);
    }

    return $new_balance;
}

/**
 * Seasonal bonus carpanini al
 */
function gorilla_xp_get_bonus_multiplier() {
    if (get_option('gorilla_lr_bonus_enabled', 'no') !== 'yes') {
        return 1;
    }

    $start = get_option('gorilla_lr_bonus_start', '');
    $end = get_option('gorilla_lr_bonus_end', '');

    if (empty($start) || empty($end)) {
        return 1;
    }

    $now = current_time('Y-m-d');

    if ($now >= $start && $now <= $end) {
        return floatval(get_option('gorilla_lr_bonus_multiplier', 1.5));
    }

    return 1;
}

/**
 * Kullanicinin toplam XP'sini al
 */
function gorilla_xp_get_balance($user_id) {
    if (!$user_id) return 0;

    $cached = get_transient('gorilla_xp_' . $user_id);
    if ($cached !== false) {
        return intval($cached);
    }

    $xp = get_user_meta($user_id, '_gorilla_total_xp', true);
    $xp = $xp ? intval($xp) : 0;

    set_transient('gorilla_xp_' . $user_id, $xp, HOUR_IN_SECONDS);

    return $xp;
}

/**
 * Daha once bu referans icin XP verilmis mi?
 */
function gorilla_xp_has_been_awarded($user_id, $reference_type, $reference_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'gorilla_xp_log';

    if (!gorilla_lr_table_exists($table)) {
        return false;
    }

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND reference_type = %s AND reference_id = %d",
        $user_id, $reference_type, $reference_id
    ));

    return $exists > 0;
}

/**
 * Kullanicinin XP log'unu al
 */
function gorilla_xp_get_log($user_id, $limit = 20) {
    global $wpdb;
    $table = $wpdb->prefix . 'gorilla_xp_log';

    if (!gorilla_lr_table_exists($table)) {
        return array();
    }

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
        $user_id, $limit
    ), ARRAY_A);
}


// ==============================================================
// LEVEL SISTEMI
// ==============================================================

/**
 * Level tanimlarini al
 */
function gorilla_xp_get_levels() {
    static $cached = null;
    if ($cached !== null) return $cached;

    $levels = get_option('gorilla_lr_levels', array());

    if (empty($levels)) {
        // Varsayilan level'lar
        $levels = array(
            'level_1' => array('label' => 'Caylak',      'min_xp' => 0,    'emoji' => '🌱', 'color' => '#a3e635'),
            'level_2' => array('label' => 'Kesifci',     'min_xp' => 50,   'emoji' => '🔍', 'color' => '#22d3ee'),
            'level_3' => array('label' => 'Alisverisci', 'min_xp' => 150,  'emoji' => '🛒', 'color' => '#60a5fa'),
            'level_4' => array('label' => 'Sadik',       'min_xp' => 400,  'emoji' => '⭐', 'color' => '#facc15'),
            'level_5' => array('label' => 'Uzman',       'min_xp' => 800,  'emoji' => '🏅', 'color' => '#f97316'),
            'level_6' => array('label' => 'VIP',         'min_xp' => 1500, 'emoji' => '💎', 'color' => '#a855f7'),
            'level_7' => array('label' => 'Efsane',      'min_xp' => 3000, 'emoji' => '👑', 'color' => '#fbbf24'),
        );
    }

    // Min XP'ye gore sirala
    uasort($levels, function($a, $b) {
        return ($a['min_xp'] ?? 0) <=> ($b['min_xp'] ?? 0);
    });

    $cached = $levels;
    return $cached;
}

/**
 * XP miktarindan level hesapla
 */
function gorilla_xp_calculate_level_from_xp($xp) {
    $levels = gorilla_xp_get_levels();
    $xp = intval($xp);

    $current_key = 'level_1';
    $current_level = reset($levels);

    foreach ($levels as $key => $level) {
        $min_xp = intval($level['min_xp'] ?? 0);
        if ($xp >= $min_xp) {
            $current_key = $key;
            $current_level = $level;
        }
    }

    $number = intval(str_replace('level_', '', $current_key));

    return array(
        'key'    => $current_key,
        'number' => $number,
        'label'  => $current_level['label'] ?? 'Level ' . $number,
        'emoji'  => $current_level['emoji'] ?? '🎮',
        'color'  => $current_level['color'] ?? '#999',
        'min_xp' => intval($current_level['min_xp'] ?? 0),
    );
}

/**
 * Kullanicinin mevcut level'ini hesapla
 */
function gorilla_xp_calculate_level($user_id) {
    $xp = gorilla_xp_get_balance($user_id);
    $level = gorilla_xp_calculate_level_from_xp($xp);
    $level['xp'] = $xp;

    return $level;
}

/**
 * Sonraki level bilgisini al
 */
function gorilla_xp_get_next_level($user_id) {
    $xp = gorilla_xp_get_balance($user_id);
    $current = gorilla_xp_calculate_level_from_xp($xp);
    $levels = gorilla_xp_get_levels();

    $found_current = false;
    foreach ($levels as $key => $level) {
        if ($found_current) {
            // Sonraki level
            $min_xp = intval($level['min_xp'] ?? 0);
            $remaining = $min_xp - $xp;
            $current_min = intval($current['min_xp'] ?? 0);
            $range = $min_xp - $current_min;
            $progress = $range > 0 ? (($xp - $current_min) / $range) * 100 : 0;

            return array(
                'key'       => $key,
                'number'    => intval(str_replace('level_', '', $key)),
                'label'     => $level['label'] ?? '',
                'emoji'     => $level['emoji'] ?? '',
                'color'     => $level['color'] ?? '#999',
                'min_xp'    => $min_xp,
                'remaining' => max(0, $remaining),
                'progress'  => min(100, max(0, $progress)),
            );
        }

        if ($key === $current['key']) {
            $found_current = true;
        }
    }

    // En ust level'da
    return null;
}


// ==============================================================
// XP KAZANMA HOOK'LARI
// ==============================================================

/**
 * Siparis tamamlandiginda XP ver
 */
add_action('woocommerce_order_status_completed', 'gorilla_xp_on_order_complete', 20, 1);
add_action('woocommerce_order_status_processing', 'gorilla_xp_on_order_complete', 20, 1);
function gorilla_xp_on_order_complete($order_id) {
    if (get_option('gorilla_lr_enabled_xp', 'yes') !== 'yes') {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) return;

    $user_id = $order->get_customer_id();
    if (!$user_id) return;

    // Atomic check-and-set: esanli islem korumasili
    global $wpdb;
    $meta_table = $wpdb->prefix . 'wc_orders_meta';
    $use_hpos = false;
    if (class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')) {
        if (method_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil', 'custom_orders_table_enabled')) {
            $use_hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_enabled();
        }
    }
    if ($use_hpos && gorilla_lr_table_exists($meta_table)) {
        $locked = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$meta_table} (order_id, meta_key, meta_value) VALUES (%d, '_gorilla_xp_awarded', 'yes')",
            $order_id
        ));
        if (!$locked) return;
    } else {
        if ($order->get_meta('_gorilla_xp_awarded') === 'yes') return;
    }

    $order_total = floatval($order->get_total());
    $rate = intval(get_option('gorilla_lr_xp_per_order_rate', 10));

    if ($rate <= 0) $rate = 10;

    $xp = intval($order_total / $rate);

    if ($xp > 0) {
        gorilla_xp_add($user_id, $xp, sprintf('Siparis #%d tamamlandi', $order_id), 'order', $order_id);
    }

    // Ilk siparis bonusu kontrolu
    $first_order_bonus = intval(get_option('gorilla_lr_xp_first_order', 100));
    if ($first_order_bonus > 0) {
        // Bu kullanicinin ilk siparisi mi?
        $previous_orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'status'      => array('completed', 'processing'),
            'limit'       => 2,
            'exclude'     => array($order_id),
        ));

        if (empty($previous_orders)) {
            // Ilk siparis - bonus XP ver
            if (!gorilla_xp_has_been_awarded($user_id, 'first_order', $user_id)) {
                gorilla_xp_add($user_id, $first_order_bonus, 'Ilk siparis bonusu', 'first_order', $user_id);
            }
        }
    }

    // Isaretli (HPOS olmayan ortamlarda)
    if (!$use_hpos || !gorilla_lr_table_exists($meta_table)) {
        $order->update_meta_data('_gorilla_xp_awarded', 'yes');
        $order->save();
    }
}

/**
 * Yorum onaylandiginda XP ver (WooCommerce urun yorumlari)
 */
add_action('comment_unapproved_to_approved', function($comment, $old_status = '') {
    if (is_object($comment)) {
        gorilla_xp_on_review_approved($comment->comment_ID);
    }
}, 10, 2);
add_action('wp_insert_comment', 'gorilla_xp_on_review_insert', 10, 2);
function gorilla_xp_on_review_insert($comment_id, $comment) {
    // Sadece onaylanmis yorumlar
    if ($comment->comment_approved != 1) {
        return;
    }

    gorilla_xp_on_review_approved($comment_id);
}

function gorilla_xp_on_review_approved($comment_id) {
    if (get_option('gorilla_lr_enabled_xp', 'yes') !== 'yes') {
        return;
    }

    $comment = get_comment($comment_id);
    if (!$comment) return;

    // Sadece product yorumlari
    $post = get_post($comment->comment_post_ID);
    if (!$post || $post->post_type !== 'product') {
        return;
    }

    $user_id = intval($comment->user_id);
    if (!$user_id) return;

    $xp = intval(get_option('gorilla_lr_xp_review', 25));
    if ($xp <= 0) return;

    gorilla_xp_add($user_id, $xp, sprintf('Urun yorumu: %s', get_the_title($comment->comment_post_ID)), 'review', $comment_id);
}

/**
 * Kullanici kaydoldugunda hosgeldin XP'si
 */
add_action('user_register', 'gorilla_xp_on_user_register', 10, 1);
function gorilla_xp_on_user_register($user_id) {
    if (get_option('gorilla_lr_enabled_xp', 'yes') !== 'yes') {
        return;
    }

    $xp = intval(get_option('gorilla_lr_xp_register', 10));
    if ($xp <= 0) return;

    gorilla_xp_add($user_id, $xp, 'Hosgeldin bonusu', 'register', $user_id);
}

/**
 * Profil tamamlandiginda XP ver
 */
add_action('profile_update', 'gorilla_xp_on_profile_complete', 10, 2);
add_action('woocommerce_save_account_details', 'gorilla_xp_on_profile_complete_wc', 10, 1);
function gorilla_xp_on_profile_complete($user_id, $old_user_data = null) {
    if (get_option('gorilla_lr_enabled_xp', 'yes') !== 'yes') {
        return;
    }

    // Daha once profil XP'si verilmis mi?
    if (gorilla_xp_has_been_awarded($user_id, 'profile', $user_id)) {
        return;
    }

    // Profil tam mi kontrol et
    $user = get_userdata($user_id);
    if (!$user) return;

    $first_name = get_user_meta($user_id, 'first_name', true);
    $last_name = get_user_meta($user_id, 'last_name', true);
    $billing_phone = get_user_meta($user_id, 'billing_phone', true);
    $billing_address = get_user_meta($user_id, 'billing_address_1', true);
    $billing_city = get_user_meta($user_id, 'billing_city', true);

    // En az isim + telefon veya adres dolu olmali
    if (empty($first_name) || empty($last_name)) {
        return;
    }

    if (empty($billing_phone) && (empty($billing_address) || empty($billing_city))) {
        return;
    }

    // Profil tamamlanmis - XP ver
    $xp = intval(get_option('gorilla_lr_xp_profile', 20));
    if ($xp <= 0) return;

    gorilla_xp_add($user_id, $xp, 'Profil tamamlama bonusu', 'profile', $user_id);
}

function gorilla_xp_on_profile_complete_wc($user_id) {
    gorilla_xp_on_profile_complete($user_id, null);
}


// ==============================================================
// REFERRAL & AFFILIATE XP HOOK'LARI
// ==============================================================

/**
 * Referans onaylandiginda XP ver
 */
function gorilla_xp_on_referral_approved($user_id, $ref_id) {
    if (get_option('gorilla_lr_enabled_xp', 'yes') !== 'yes') {
        return;
    }

    $xp = intval(get_option('gorilla_lr_xp_referral', 50));
    if ($xp <= 0) return;

    gorilla_xp_add($user_id, $xp, 'Video referans onayi', 'referral', $ref_id);
}

/**
 * Affiliate satisi yapildiginda XP ver
 */
function gorilla_xp_on_affiliate_sale($user_id, $order_id) {
    if (get_option('gorilla_lr_enabled_xp', 'yes') !== 'yes') {
        return;
    }

    $xp = intval(get_option('gorilla_lr_xp_affiliate', 30));
    if ($xp <= 0) return;

    gorilla_xp_add($user_id, $xp, sprintf('Affiliate satis #%d', $order_id), 'affiliate', $order_id);
}


// ==============================================================
// ADMIN ISTATISTIKLERI
// ==============================================================

/**
 * Genel XP istatistikleri (admin icin)
 */
function gorilla_xp_get_admin_stats() {
    global $wpdb;

    $table = $wpdb->prefix . 'gorilla_xp_log';

    $stats = array(
        'total_xp'           => 0,
        'avg_xp'             => 0,
        'users_with_xp'      => 0,
        'level_distribution' => array(),
    );

    // XP log tablosu var mi?
    if (!gorilla_lr_table_exists($table)) {
        return $stats;
    }

    // Toplam dagitilan XP
    $total = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE amount > %d", 0));
    $stats['total_xp'] = intval($total);

    // XP'si olan kullanici sayisi
    $user_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = %s AND CAST(meta_value AS UNSIGNED) > 0",
        '_gorilla_total_xp'
    ));
    $stats['users_with_xp'] = intval($user_count);

    // Ortalama XP
    if ($stats['users_with_xp'] > 0) {
        $avg = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->usermeta} WHERE meta_key = %s AND CAST(meta_value AS UNSIGNED) > 0",
            '_gorilla_total_xp'
        ));
        $stats['avg_xp'] = round(floatval($avg), 0);
    }

    // Level dagilimi (sample)
    $levels = gorilla_xp_get_levels();
    $level_counts = array();
    foreach (array_keys($levels) as $key) {
        $level_counts[$key] = 0;
    }

    // Ilk 500 kullaniciyi sample al
    $users = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, meta_value as xp FROM {$wpdb->usermeta}
         WHERE meta_key = %s AND CAST(meta_value AS UNSIGNED) > 0
         LIMIT %d",
        '_gorilla_total_xp', 500
    ));

    foreach ($users as $u) {
        $level = gorilla_xp_calculate_level_from_xp($u->xp);
        if (isset($level_counts[$level['key']])) {
            $level_counts[$level['key']]++;
        }
    }

    $stats['level_distribution'] = $level_counts;

    return $stats;
}

/**
 * Son XP hareketleri (admin icin)
 */
function gorilla_xp_get_recent_activity($limit = 10) {
    global $wpdb;

    $table = $wpdb->prefix . 'gorilla_xp_log';

    if (!gorilla_lr_table_exists($table)) {
        return array();
    }

    return $wpdb->get_results($wpdb->prepare(
        "SELECT xl.*, u.display_name
         FROM {$table} xl
         LEFT JOIN {$wpdb->users} u ON xl.user_id = u.ID
         ORDER BY xl.created_at DESC
         LIMIT %d",
        $limit
    ));
}


// ── Gunluk Giris Serisi (Login Streaks) ──────────────────
add_action('wp_login', 'gorilla_xp_on_login', 10, 2);
function gorilla_xp_on_login($user_login, $user) {
    if (get_option('gorilla_lr_streak_enabled', 'no') !== 'yes') return;
    if (get_option('gorilla_lr_enabled_xp') !== 'yes') return;

    $user_id = $user->ID;
    $today = current_time('Y-m-d');
    $yesterday = wp_date('Y-m-d', strtotime('-1 day', strtotime($today)));

    $last_date = get_user_meta($user_id, '_gorilla_login_last_date', true);

    // Bugun zaten giris yapti
    if ($last_date === $today) return;

    $streak = intval(get_user_meta($user_id, '_gorilla_login_streak', true));

    if ($last_date === $yesterday) {
        // Ardisik giris - streak artir
        $streak++;
    } else {
        // Streak kirildi - sifirla
        $streak = 1;
    }

    update_user_meta($user_id, '_gorilla_login_last_date', $today);
    update_user_meta($user_id, '_gorilla_login_streak', $streak);

    // En iyi streak guncelle
    $best = intval(get_user_meta($user_id, '_gorilla_login_streak_best', true));
    if ($streak > $best) {
        update_user_meta($user_id, '_gorilla_login_streak_best', $streak);
    }

    // Gunluk XP ver
    $daily_xp = intval(get_option('gorilla_lr_streak_daily_xp', 5));
    if ($daily_xp > 0) {
        gorilla_xp_add($user_id, $daily_xp, sprintf('Gunluk giris serisi (%d. gun)', $streak), 'login', crc32($today));
    }

    // 7 gun bonus
    if ($streak === 7) {
        $bonus_7 = intval(get_option('gorilla_lr_streak_7day_bonus', 50));
        if ($bonus_7 > 0) {
            gorilla_xp_add($user_id, $bonus_7, '7 gunluk giris serisi bonusu!', 'streak_7', crc32($today . '_7'));
        }
    }

    // 30 gun bonus
    if ($streak === 30) {
        $bonus_30 = intval(get_option('gorilla_lr_streak_30day_bonus', 200));
        if ($bonus_30 > 0) {
            gorilla_xp_add($user_id, $bonus_30, '30 gunluk giris serisi bonusu!', 'streak_30', crc32($today . '_30'));
        }
    }

    // Badge kontrol: streak_master (30 gunluk)
    if ($streak >= 30 && function_exists('gorilla_badge_award')) {
        gorilla_badge_award($user_id, 'streak_master');
    }
}


// ── Dogum Gunu Odulleri (Birthday Rewards) ───────────────
add_action('gorilla_lr_daily_tier_check', 'gorilla_xp_check_birthdays');
function gorilla_xp_check_birthdays() {
    if (get_option('gorilla_lr_birthday_enabled', 'no') !== 'yes') return;

    global $wpdb;
    $today_md = current_time('m-d');
    $current_year = current_time('Y');

    // Bugun dogum gunu olan kullanicilar
    $user_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_gorilla_birthday' AND RIGHT(meta_value, 5) = %s",
        $today_md
    ));

    if (empty($user_ids)) return;

    $xp_amount = intval(get_option('gorilla_lr_birthday_xp', 50));
    $credit_amount = floatval(get_option('gorilla_lr_birthday_credit', 10));

    foreach ($user_ids as $user_id) {
        // Atomic year-check guard to prevent double birthday reward
        global $wpdb;
        $guard_key = '_gorilla_birthday_awarded_' . $current_year;
        $marked = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) SELECT %d, %s, '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s)",
            $user_id, $guard_key, $user_id, $guard_key
        ));
        if (!$marked) continue;

        // XP ver
        if ($xp_amount > 0 && function_exists('gorilla_xp_add')) {
            gorilla_xp_add($user_id, $xp_amount, 'Dogum gunu bonusu!', 'birthday', intval($current_year));
        }

        // Credit ver
        if ($credit_amount > 0 && function_exists('gorilla_credit_adjust')) {
            gorilla_credit_adjust($user_id, $credit_amount, 'birthday', 'Dogum gunu hediyesi!', 0, 30);
        }

        // Email gonder
        if (function_exists('gorilla_email_birthday')) {
            gorilla_email_birthday($user_id, $xp_amount, $credit_amount);
        }

        update_user_meta($user_id, '_gorilla_birthday_awarded_year', $current_year);
    }
}


// ── Leaderboard (Siralama Tablosu) ───────────────────────
function gorilla_xp_get_leaderboard($period = 'monthly', $limit = 10) {
    if (get_option('gorilla_lr_leaderboard_enabled', 'no') !== 'yes') return array();

    global $wpdb;
    $table = $wpdb->prefix . 'gorilla_xp_log';
    if (!gorilla_lr_table_exists($table)) return array();

    // Ay baslangicindan itibaren
    $date_from = current_time('Y-m-01 00:00:00');
    $anonymize = (get_option('gorilla_lr_leaderboard_anonymize', 'no') === 'yes');

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT xl.user_id, SUM(xl.amount) as xp_earned, u.display_name
         FROM {$table} xl
         INNER JOIN {$wpdb->users} u ON xl.user_id = u.ID
         WHERE xl.created_at >= %s AND xl.amount > 0
         GROUP BY xl.user_id
         ORDER BY xp_earned DESC
         LIMIT %d",
        $date_from, $limit
    ));

    $leaderboard = array();
    foreach ($results as $i => $row) {
        $name = $row->display_name;
        if ($anonymize) {
            $name = mb_substr($name, 0, 1) . str_repeat('*', max(1, mb_strlen($name) - 1));
        }
        $leaderboard[] = array(
            'rank'         => $i + 1,
            'user_id'      => intval($row->user_id),
            'display_name' => $name,
            'xp_earned'    => intval($row->xp_earned),
        );
    }

    return $leaderboard;
}


// ── Hedef/Milestone Sistemi ──────────────────────────────
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

        // Atomic milestone completion guard
        global $wpdb;
        $guard_key = '_gorilla_milestone_done_' . sanitize_key($mid);
        $m_marked = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) SELECT %d, %s, '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s)",
            $user_id, $guard_key, $user_id, $guard_key
        ));
        if (!$m_marked) continue;

        // Hedef tamamlandi!
        $completed[] = $mid;
        update_user_meta($user_id, '_gorilla_milestones', $completed);

        // XP odul
        $xp_reward = intval($m['xp_reward'] ?? 0);
        if ($xp_reward > 0 && function_exists('gorilla_xp_add')) {
            gorilla_xp_add($user_id, $xp_reward, sprintf('Hedef tamamlandi: %s', $m['label'] ?? ''), 'milestone', crc32($mid));
        }

        // Credit odul
        $credit_reward = floatval($m['credit_reward'] ?? 0);
        if ($credit_reward > 0 && function_exists('gorilla_credit_adjust')) {
            gorilla_credit_adjust($user_id, $credit_reward, 'milestone', sprintf('Hedef odulu: %s', $m['label'] ?? ''), 0, 0);
        }

        // Email
        if (function_exists('gorilla_email_milestone_reached')) {
            gorilla_email_milestone_reached($user_id, $m);
        }

        // Sans carki hakki ver
        if (get_option('gorilla_lr_spin_enabled', 'no') === 'yes' && function_exists('gorilla_spin_grant')) {
            gorilla_spin_grant($user_id, 'milestone');
        }
    }
}

function gorilla_milestone_get_progress($user_id, $milestone) {
    $type = $milestone['type'] ?? '';
    $target = floatval($milestone['target'] ?? 1);
    if ($target <= 0) return 100;

    $current = 0;

    switch ($type) {
        case 'total_orders':
            $orders_result = wc_get_orders(array(
                'customer_id' => $user_id,
                'status'      => array('completed', 'processing'),
                'limit'       => intval($target) + 1,
                'return'      => 'ids',
            ));
            $current = is_array($orders_result) ? count($orders_result) : 0;
            break;

        case 'total_spending':
            if (function_exists('gorilla_loyalty_get_spending')) {
                $current = gorilla_loyalty_get_spending($user_id);
            }
            break;

        case 'total_reviews':
            global $wpdb;
            $current = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d AND comment_type = 'review' AND comment_approved = '1'",
                $user_id
            )));
            break;

        case 'total_referrals':
            $current = count(get_posts(array(
                'post_type'   => 'gorilla_referral',
                'post_status' => 'grla_approved',
                'meta_key'    => '_ref_user_id',
                'meta_value'  => $user_id,
                'numberposts' => intval($target) + 1,
                'fields'      => 'ids',
            )));
            break;

        case 'total_xp':
            if (function_exists('gorilla_xp_get_balance')) {
                $current = gorilla_xp_get_balance($user_id);
            }
            break;

        case 'account_age':
            $user = get_userdata($user_id);
            if ($user) {
                $days = (time() - strtotime($user->user_registered)) / DAY_IN_SECONDS;
                $current = intval($days);
            }
            break;
    }

    return min(100, ($current / $target) * 100);
}

// Milestone kontrolunu onemli aksiyonlarda tetikle
add_action('woocommerce_order_status_completed', function($order_id) {
    $order = wc_get_order($order_id);
    if ($order && $order->get_customer_id()) {
        gorilla_xp_check_milestones($order->get_customer_id());
    }
}, 25);

add_action('gorilla_xp_level_up', function($user_id) {
    gorilla_xp_check_milestones($user_id);
}, 10, 1);


// ── XP Dusurme (Points Shop icin) ────────────────────────
function gorilla_xp_deduct($user_id, $amount, $reason = '', $reference_type = null, $reference_id = null) {
    if (!$user_id || $amount <= 0) return false;
    if (get_option('gorilla_lr_enabled_xp') !== 'yes') return false;

    global $wpdb;
    $table = $wpdb->prefix . 'gorilla_xp_log';

    try {
        $wpdb->query('START TRANSACTION');

        $current_xp = intval($wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = '_gorilla_total_xp' FOR UPDATE",
            $user_id
        )));

        if ($current_xp < $amount) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        $new_xp = $current_xp - $amount;

        $wpdb->update(
            $wpdb->usermeta,
            array('meta_value' => $new_xp),
            array('user_id' => $user_id, 'meta_key' => '_gorilla_total_xp'),
            array('%d'),
            array('%d', '%s')
        );

        if (gorilla_lr_table_exists($table)) {
            $wpdb->insert($table, array(
                'user_id'        => $user_id,
                'amount'         => -$amount,
                'balance_after'  => $new_xp,
                'reason'         => $reason,
                'reference_type' => $reference_type,
                'reference_id'   => $reference_id,
                'created_at'     => current_time('mysql'),
            ), array('%d', '%d', '%d', '%s', '%s', '%d', '%s'));
        }

        $wpdb->query('COMMIT');
        wp_cache_delete($user_id, 'user_meta');
        delete_transient('gorilla_xp_' . $user_id);

        return $new_xp;
    } catch (\Throwable $e) {
        $wpdb->query('ROLLBACK');
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla XP deduct error: ' . $e->getMessage());
        }
        return false;
    }
}