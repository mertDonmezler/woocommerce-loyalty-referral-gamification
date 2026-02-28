<?php
/**
 * Gorilla Loyalty & Gamification - Sadakat Sistemi
 * Seviye hesaplama, otomatik indirim, sepet/checkout, badges, spin, shop, social, QR, VIP, transfer
 *
 * @package Gorilla_Loyalty_Gamification
 * @since   3.1.0
 */

if (!defined('ABSPATH')) exit;

// ── Kullanicinin son X aydaki harcamasini hesapla ────────
function gorilla_loyalty_get_spending($user_id) {
    if (!$user_id) return 0;

    try {
        global $wpdb;

        $months = intval(get_option('gorilla_lr_period_months', 6));
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$months} months"));

        $cache_key = 'gorilla_spending_' . $user_id;
        $cached = get_transient($cache_key);
        if ($cached !== false) return floatval($cached);

        $total = 0;

        $use_hpos = false;
        if (class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')) {
            if (method_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil', 'custom_orders_table_enabled')) {
                $use_hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_enabled();
            }
        }

        if ($use_hpos) {
            $table = $wpdb->prefix . 'wc_orders';
            if (function_exists('gorilla_lr_table_exists') && gorilla_lr_table_exists($table)) {
                $total = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(total_amount), 0) FROM {$table}
                     WHERE customer_id = %d AND status IN ('wc-completed','wc-processing') AND date_created_gmt >= %s",
                    $user_id, $date_from
                ));
            }
        } else {
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(pm.meta_value), 0)
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
                 INNER JOIN {$wpdb->postmeta} pc ON p.ID = pc.post_id AND pc.meta_key = '_customer_user'
                 WHERE p.post_type = 'shop_order' AND p.post_status IN ('wc-completed','wc-processing')
                 AND pc.meta_value = %d AND p.post_date >= %s",
                $user_id, $date_from
            ));
        }

        $result = floatval($total);
        set_transient($cache_key, $result, 3600);
        return $result;

    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla LG spending error: ' . $e->getMessage());
        }
        return 0;
    }
}

// ── Seviye hesapla ───────────────────────────────────────
function gorilla_loyalty_calculate_tier($user_id) {
    $default = array('key' => 'none', 'label' => 'Uye', 'discount' => 0, 'emoji' => '', 'color' => '#999', 'installment' => 0, 'spending' => 0);

    static $cache = array();
    if (isset($cache[$user_id])) return $cache[$user_id];

    if (get_option('gorilla_lr_enabled_loyalty') !== 'yes') {
        $cache[$user_id] = $default; return $default;
    }

    if (!function_exists('gorilla_get_tiers')) {
        $cache[$user_id] = $default; return $default;
    }

    try {
        $spending = gorilla_loyalty_get_spending($user_id);
        $tiers = gorilla_get_tiers();

        if (!is_array($tiers) || empty($tiers)) {
            $result = array_merge($default, array('spending' => $spending));
            $cache[$user_id] = $result; return $result;
        }

        $current_key = 'none';
        $current_tier = array('label' => 'Uye', 'discount' => 0, 'emoji' => '', 'color' => '#999999', 'installment' => 0);

        foreach ($tiers as $key => $tier) {
            if (!is_array($tier)) continue;
            if ($spending >= ($tier['min'] ?? 0)) {
                $current_key = $key;
                $current_tier = $tier;
            }
        }

        // Grace period
        $grace_days = intval(get_option('gorilla_lr_tier_grace_days', 0));
        if ($grace_days > 0) {
            $stored_tier_key = get_user_meta($user_id, '_gorilla_last_tier', true);
            if ($stored_tier_key && $stored_tier_key !== 'none' && $stored_tier_key !== $current_key && isset($tiers[$stored_tier_key])) {
                $tier_keys    = array_keys($tiers);
                $stored_index = array_search($stored_tier_key, $tier_keys, true);
                $calc_index   = ($current_key === 'none') ? -1 : array_search($current_key, $tier_keys, true);

                if ($stored_index !== false && $calc_index < $stored_index) {
                    $grace_until = get_user_meta($user_id, '_gorilla_tier_grace_until', true);
                    if ($grace_until && strtotime($grace_until) > time()) {
                        $current_key  = $stored_tier_key;
                        $current_tier = $tiers[$stored_tier_key];
                    }
                }
            }
        }

        $prev = get_user_meta($user_id, '_gorilla_lr_tier_key', true);
        if ($prev !== $current_key) {
            // Detect downgrade: start grace period
            if ($grace_days > 0 && $prev && $prev !== 'none' && isset($tiers[$prev])) {
                $tier_keys = array_keys($tiers);
                $prev_index = array_search($prev, $tier_keys, true);
                $new_index = ($current_key === 'none') ? -1 : array_search($current_key, $tier_keys, true);
                if ($new_index < $prev_index) {
                    // Downgrade detected - set grace period
                    $grace_until = gmdate('Y-m-d H:i:s', strtotime("+{$grace_days} days"));
                    update_user_meta($user_id, '_gorilla_tier_grace_until', $grace_until);
                    update_user_meta($user_id, '_gorilla_last_tier', $prev);
                    // Keep old tier during grace
                    $current_key = $prev;
                    $current_tier = $tiers[$prev];
                } else {
                    // Upgrade - clear grace period
                    delete_user_meta($user_id, '_gorilla_tier_grace_until');
                    update_user_meta($user_id, '_gorilla_last_tier', $current_key);
                    update_user_meta($user_id, '_gorilla_lr_tier_key', $current_key);
                }
            } else {
                update_user_meta($user_id, '_gorilla_lr_tier_key', $current_key);
            }
        }

        $result = array_merge($current_tier, array(
            'key'      => $current_key,
            'spending' => $spending,
        ));
        $cache[$user_id] = $result; return $result;
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla LG tier calc error: ' . $e->getMessage());
        }
        $cache[$user_id] = $default; return $default;
    }
}

// ── Sonraki seviye bilgisi ───────────────────────────────
function gorilla_loyalty_next_tier($user_id) {
    $spending = gorilla_loyalty_get_spending($user_id);
    $tiers = gorilla_get_tiers();
    $current = gorilla_loyalty_calculate_tier($user_id);

    $found_current = false;
    foreach ($tiers as $key => $tier) {
        if ($found_current) {
            return array(
                'key'       => $key,
                'label'     => $tier['label'],
                'emoji'     => $tier['emoji'],
                'min'       => $tier['min'],
                'discount'  => $tier['discount'],
                'remaining' => max(0, $tier['min'] - $spending),
                'progress'  => $tier['min'] > 0 ? min(100, ($spending / $tier['min']) * 100) : 100,
            );
        }
        if ($key === $current['key']) $found_current = true;
    }

    if ($current['key'] === 'none' && !empty($tiers)) {
        $tier_keys = array_keys($tiers);
        $first_key = $tier_keys[0];
        $first = $tiers[$first_key];
        return array(
            'key'       => $first_key,
            'label'     => $first['label'],
            'emoji'     => $first['emoji'],
            'min'       => $first['min'],
            'discount'  => $first['discount'],
            'remaining' => max(0, $first['min'] - $spending),
            'progress'  => $first['min'] > 0 ? min(100, ($spending / $first['min']) * 100) : 100,
        );
    }

    return null;
}


// ── Sepette Otomatik Indirim ─────────────────────────────
add_action('woocommerce_cart_calculate_fees', 'gorilla_loyalty_apply_cart_discount', 10);
function gorilla_loyalty_apply_cart_discount($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (!is_user_logged_in()) return;
    if (!$cart || !is_object($cart)) return;
    if (get_option('gorilla_lr_enabled_loyalty') !== 'yes') return;

    try {
        $user_id = get_current_user_id();
        $tier = gorilla_loyalty_calculate_tier($user_id);

        if (!is_array($tier) || ($tier['discount'] ?? 0) <= 0) return;

        $subtotal = floatval($cart->get_subtotal());
        $discount_amount = round($subtotal * ($tier['discount'] / 100), 2);

        if ($discount_amount > 0) {
            $label = sprintf('%s %s Uye Indirimi (%%%s)',
                $tier['emoji'] ?? '',
                $tier['label'] ?? 'Uye',
                $tier['discount'] ?? 0
            );
            $cart->add_fee($label, -$discount_amount, true);
        }
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla LG cart discount error: ' . $e->getMessage());
        }
    }
}


// ── Siparis tamamlandiginda cache temizle ve seviye kontrolu ─
add_action('woocommerce_order_status_completed', function($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    $user_id = $order->get_customer_id();
    if (!$user_id) return;

    $old_tier_key = get_user_meta($user_id, '_gorilla_last_tier', true);
    if (!$old_tier_key) $old_tier_key = 'none';

    delete_transient('gorilla_spending_' . $user_id);
    delete_transient('gorilla_lr_bar_' . $user_id);

    $new_tier = gorilla_loyalty_calculate_tier($user_id);
    $new_tier_key = $new_tier['key'] ?? 'none';

    if ($new_tier_key !== 'none' && $new_tier_key !== $old_tier_key) {
        $tiers = gorilla_get_tiers();
        $tier_keys = array_keys($tiers);
        $old_index = array_search($old_tier_key, $tier_keys);
        $new_index = array_search($new_tier_key, $tier_keys);

        if ($old_tier_key === 'none' || ($old_index !== false && $new_index !== false && $new_index > $old_index)) {
            if (function_exists('gorilla_email_tier_upgrade')) {
                $old_tier = ($old_tier_key !== 'none' && isset($tiers[$old_tier_key])) ? $tiers[$old_tier_key] : null;
                gorilla_email_tier_upgrade($user_id, $old_tier, $new_tier);
            }
        }
    }

    update_user_meta($user_id, '_gorilla_last_tier', $new_tier_key);
});

add_action('woocommerce_order_status_processing', function($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    $user_id = $order->get_customer_id();
    if ($user_id) {
        delete_transient('gorilla_spending_' . $user_id);
    }
});


// ── Seviye Bazli Ucretsiz Kargo ──────────────────────────
add_filter('woocommerce_package_rates', 'gorilla_loyalty_free_shipping', 100, 2);
function gorilla_loyalty_free_shipping($rates, $package) {
    if (!is_user_logged_in()) return $rates;
    if (get_option('gorilla_lr_enabled_loyalty') !== 'yes') return $rates;

    try {
        $tier = gorilla_loyalty_calculate_tier(get_current_user_id());
        if (!is_array($tier) || empty($tier['free_shipping'])) return $rates;

        foreach ($rates as $rate_key => $rate) {
            if ($rate->method_id !== 'free_shipping') {
                $rates[$rate_key]->cost = 0;
                $rates[$rate_key]->label = sprintf(
                    '%s %s (%s)',
                    $tier['emoji'] ?? '',
                    __('Ucretsiz Kargo', 'gorilla-loyalty'),
                    $tier['label'] ?? ''
                );
            }
        }
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla LG free shipping error: ' . $e->getMessage());
        }
    }

    return $rates;
}


// ── Siparis notunda seviye bilgisi ───────────────────────
add_action('woocommerce_checkout_order_created', function($order) {
    $user_id = $order->get_customer_id();
    if (!$user_id) return;

    $tier = gorilla_loyalty_calculate_tier($user_id);
    if ($tier['key'] !== 'none') {
        $note = sprintf(
            'Musteri Sadakat Seviyesi: %s %s (%%%s indirim uygulandi)',
            $tier['emoji'], $tier['label'], $tier['discount']
        );
        if ($tier['installment'] > 0) {
            $note .= sprintf(' -- Vade farksiz %d taksit hakki var.', $tier['installment']);
        }
        $order->add_order_note($note);

        $order->update_meta_data('_gorilla_loyalty_tier', $tier['key']);
        $order->update_meta_data('_gorilla_loyalty_discount', $tier['discount']);
        $order->save();
    }
});

// ══════════════════════════════════════════════════════════
// ROZET SISTEMI (F3)
// ══════════════════════════════════════════════════════════

function gorilla_badge_get_definitions() {
    return array(
        'first_purchase'   => array('label' => 'Ilk Alisveris',      'emoji' => '', 'description' => 'Ilk siparisini verdi',          'color' => '#22c55e'),
        '10_orders'        => array('label' => 'Sadik Musteri',       'emoji' => '', 'description' => '10 siparis tamamladi',          'color' => '#f59e0b',
            'tiers' => array('bronze' => 5, 'silver' => 10, 'gold' => 25, 'diamond' => 50)),
        '5_referrals'      => array('label' => 'Referans Yildizi',    'emoji' => '', 'description' => '5 referans onayi aldi',         'color' => '#ec4899',
            'tiers' => array('bronze' => 2, 'silver' => 5, 'gold' => 10, 'diamond' => 25)),
        '10_reviews'       => array('label' => 'Yorum Ustasi',        'emoji' => '', 'description' => '10 urun yorumu yapti',           'color' => '#8b5cf6',
            'tiers' => array('bronze' => 3, 'silver' => 10, 'gold' => 25, 'diamond' => 50)),
        'social_butterfly' => array('label' => 'Sosyal Kelebek',      'emoji' => '', 'description' => '3 platformda paylasim yapti',   'color' => '#06b6d4'),
        'vip_tier'         => array('label' => 'VIP',                  'emoji' => '', 'description' => 'Diamond seviyeye ulasti',       'color' => '#a855f7'),
        'streak_master'    => array('label' => 'Seri Ustasi',          'emoji' => '', 'description' => '30 gunluk giris serisi',        'color' => '#ef4444',
            'tiers' => array('bronze' => 7, 'silver' => 30, 'gold' => 60, 'diamond' => 100)),
        'big_spender'      => array('label' => 'Buyuk Harcamaci',     'emoji' => '', 'description' => '10000 TL ustu harcama',         'color' => '#f97316',
            'tiers' => array('bronze' => 3000, 'silver' => 10000, 'gold' => 25000, 'diamond' => 50000)),
        'birthday_club'    => array('label' => 'Dogum Gunu Kulubu',   'emoji' => '', 'description' => 'Dogum gununu paylasti',         'color' => '#f472b6'),
    );
}

function gorilla_badge_tier_meta($tier_key) {
    $meta = array(
        'bronze'  => array('label' => 'Bronz',   'emoji' => '', 'color' => '#CD7F32'),
        'silver'  => array('label' => 'Gumus',    'emoji' => '', 'color' => '#C0C0C0'),
        'gold'    => array('label' => 'Altin',    'emoji' => '', 'color' => '#FFD700'),
        'diamond' => array('label' => 'Elmas',    'emoji' => '', 'color' => '#B9F2FF'),
    );
    return $meta[$tier_key] ?? array('label' => '', 'emoji' => '', 'color' => '#999');
}

function gorilla_badge_award($user_id, $badge_id, $tier_key = '') {
    if (get_option('gorilla_lr_badges_enabled', 'no') !== 'yes') return false;

    global $wpdb;
    $lock_name = "gorilla_badge_{$user_id}";
    $got_lock = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $lock_name));
    if (!$got_lock) return false;
    try {

    $definitions = gorilla_badge_get_definitions();
    if (!isset($definitions[$badge_id])) return false;

    $badges = get_user_meta($user_id, '_gorilla_badges', true);
    if (!is_array($badges)) $badges = array();

    $existing_tier = $badges[$badge_id]['tier'] ?? '';

    if (empty($tier_key)) {
        if (isset($badges[$badge_id])) return false;
        $badges[$badge_id] = array('earned_at' => current_time('mysql'), 'tier' => '');
    } else {
        $tier_order = array('bronze' => 1, 'silver' => 2, 'gold' => 3, 'diamond' => 4);
        $existing_rank = $tier_order[$existing_tier] ?? 0;
        $new_rank      = $tier_order[$tier_key] ?? 0;
        if ($new_rank <= $existing_rank) return false;

        $badges[$badge_id] = array(
            'earned_at' => $badges[$badge_id]['earned_at'] ?? current_time('mysql'),
            'tier'      => $tier_key,
            'tier_at'   => current_time('mysql'),
        );
    }

    update_user_meta($user_id, '_gorilla_badges', $badges);
    do_action('gorilla_badge_earned', $user_id, $badge_id, $tier_key);
    return true;

    } finally {
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
    }
}

function gorilla_badge_get_user_badges($user_id) {
    $earned = get_user_meta($user_id, '_gorilla_badges', true);
    if (!is_array($earned)) $earned = array();

    $definitions = gorilla_badge_get_definitions();
    $result = array();

    foreach ($definitions as $id => $def) {
        $badge_data = $earned[$id] ?? null;
        $tier       = $badge_data['tier'] ?? '';
        $tier_meta  = $tier ? gorilla_badge_tier_meta($tier) : array();

        $result[$id] = array_merge($def, array(
            'earned'     => isset($earned[$id]),
            'earned_at'  => $badge_data['earned_at'] ?? null,
            'tier'       => $tier,
            'tier_label' => $tier_meta['label'] ?? '',
            'tier_emoji' => $tier_meta['emoji'] ?? '',
            'tier_color' => $tier_meta['color'] ?? '',
        ));
    }

    return $result;
}

function gorilla_badge_check_all($user_id) {
    if (get_option('gorilla_lr_badges_enabled', 'no') !== 'yes') return;
    if (!$user_id) return;

    $earned      = get_user_meta($user_id, '_gorilla_badges', true);
    if (!is_array($earned)) $earned = array();
    $definitions = gorilla_badge_get_definitions();

    $award_tiered = function($badge_id, $value) use ($definitions, $user_id) {
        $def = $definitions[$badge_id] ?? array();
        if (empty($def['tiers'])) return;
        $best = '';
        foreach ($def['tiers'] as $tk => $threshold) {
            if ($value >= $threshold) $best = $tk;
        }
        if ($best) gorilla_badge_award($user_id, $badge_id, $best);
    };

    $order_count = count(wc_get_orders(array('customer_id' => $user_id, 'status' => array('completed', 'processing'), 'limit' => 51, 'return' => 'ids')) ?: array());
    if (!isset($earned['first_purchase']) && $order_count > 0) gorilla_badge_award($user_id, 'first_purchase');
    if ($order_count > 0) $award_tiered('10_orders', $order_count);

    $ref_count = count(get_posts(array('post_type' => 'gorilla_referral', 'post_status' => 'grla_approved', 'meta_key' => '_ref_user_id', 'meta_value' => $user_id, 'numberposts' => 26, 'fields' => 'ids')) ?: array());
    if ($ref_count > 0) $award_tiered('5_referrals', $ref_count);

    global $wpdb;
    $review_count = intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d AND comment_type = 'review' AND comment_approved = '1'",
        $user_id
    )));
    if ($review_count > 0) $award_tiered('10_reviews', $review_count);

    if (!isset($earned['social_butterfly'])) {
        $shares = get_user_meta($user_id, '_gorilla_social_shares', true);
        if (is_array($shares) && count($shares) >= 3) gorilla_badge_award($user_id, 'social_butterfly');
    }

    if (!isset($earned['vip_tier'])) {
        $tier = gorilla_loyalty_calculate_tier($user_id);
        if (in_array($tier['key'] ?? '', array('diamond', 'platinum'))) gorilla_badge_award($user_id, 'vip_tier');
    }

    $spending = gorilla_loyalty_get_spending($user_id);
    if ($spending > 0) $award_tiered('big_spender', $spending);

    if (!isset($earned['birthday_club'])) {
        if (get_user_meta($user_id, '_gorilla_birthday', true)) gorilla_badge_award($user_id, 'birthday_club');
    }

    // Streak badge (data from WP Gamify)
    if (class_exists('WPGamify_Streak_Manager')) {
        $streak_data = WPGamify_Streak_Manager::get_streak($user_id);
        $max_streak = intval($streak_data['max_streak'] ?? 0);
        if ($max_streak > 0) $award_tiered('streak_master', $max_streak);
    }
}

add_action('woocommerce_order_status_completed', function($order_id) {
    $order = wc_get_order($order_id);
    if ($order && $order->get_customer_id()) {
        gorilla_badge_check_all($order->get_customer_id());
    }
}, 30);
add_action('woocommerce_order_status_processing', function($order_id) {
    $order = wc_get_order($order_id);
    if ($order && $order->get_customer_id()) {
        gorilla_badge_check_all($order->get_customer_id());
    }
}, 30);

// ══════════════════════════════════════════════════════════
// LEADERBOARD (WP Gamify tablosundan sorgu)
// ══════════════════════════════════════════════════════════

/**
 * Get XP leaderboard from WP Gamify transactions table.
 *
 * @param string $period 'monthly' or 'alltime'.
 * @param int    $limit  Number of entries.
 * @return array Array of ['user_id', 'display_name', 'xp_earned'].
 */
function gorilla_xp_get_leaderboard($period = 'monthly', $limit = 10) {
    global $wpdb;

    $table = $wpdb->prefix . 'gamify_xp_transactions';
    $anonymize = get_option('gorilla_lr_leaderboard_anonymize', 'no') === 'yes';
    $limit = max(5, min(50, intval($limit)));

    $where = 'WHERE t.amount > 0';
    if ($period === 'monthly') {
        $where .= $wpdb->prepare(' AND t.created_at >= %s', gmdate('Y-m-01 00:00:00'));
    }

    $results = $wpdb->get_results(
        "SELECT t.user_id, SUM(t.amount) AS xp_earned, u.display_name
         FROM {$table} t
         LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
         {$where}
         GROUP BY t.user_id
         ORDER BY xp_earned DESC
         LIMIT {$limit}",
        ARRAY_A
    );

    if (empty($results)) return array();

    foreach ($results as &$row) {
        $row['xp_earned'] = intval($row['xp_earned']);
        if ($anonymize && !empty($row['display_name'])) {
            $name = $row['display_name'];
            $parts = explode(' ', $name, 2);
            $first = mb_substr($parts[0], 0, 1) . str_repeat('*', max(1, mb_strlen($parts[0]) - 1));
            $last = isset($parts[1]) ? mb_substr($parts[1], 0, 1) . '.' : '';
            $row['display_name'] = $first . ($last ? ' ' . $last : '');
        }
    }

    return $results;
}

// ══════════════════════════════════════════════════════════
// SANS CARKI (F4)
// ══════════════════════════════════════════════════════════

function gorilla_spin_get_prizes() {
    $default = array(
        array('label' => '10 XP',          'type' => 'xp',           'value' => 10,  'weight' => 30),
        array('label' => '25 XP',          'type' => 'xp',           'value' => 25,  'weight' => 20),
        array('label' => '50 XP',          'type' => 'xp',           'value' => 50,  'weight' => 10),
        array('label' => '5 TL Credit',    'type' => 'credit',       'value' => 5,   'weight' => 15),
        array('label' => '10 TL Credit',   'type' => 'credit',       'value' => 10,  'weight' => 8),
        array('label' => 'Ucretsiz Kargo', 'type' => 'free_shipping','value' => 0,   'weight' => 7),
        array('label' => '%10 Indirim',    'type' => 'coupon',       'value' => 10,  'weight' => 5),
        array('label' => 'Tekrar Dene',    'type' => 'nothing',      'value' => 0,   'weight' => 5),
    );
    $saved = get_option('gorilla_lr_spin_prizes', array());
    return !empty($saved) ? $saved : $default;
}

function gorilla_spin_grant($user_id, $reason = 'level_up') {
    global $wpdb;
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->usermeta} SET meta_value = meta_value + 1 WHERE user_id = %d AND meta_key = '_gorilla_spin_available'",
        $user_id
    ));
    if ($wpdb->rows_affected === 0) {
        update_user_meta($user_id, '_gorilla_spin_available', 1);
    }
    wp_cache_delete($user_id, 'user_meta');
}

function gorilla_spin_execute($user_id) {
    global $wpdb;

    $prizes = gorilla_spin_get_prizes();
    if (empty($prizes)) return array('success' => false, 'error' => 'Odul bulunamadi');

    $wpdb->query('START TRANSACTION');
    try {
        $available = intval($wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = '_gorilla_spin_available' FOR UPDATE",
            $user_id
        )));

        if ($available <= 0) {
            $wpdb->query('ROLLBACK');
            return array('success' => false, 'error' => 'Cevirme hakkiniz yok');
        }

        $wpdb->update(
            $wpdb->usermeta,
            array('meta_value' => max(0, $available - 1)),
            array('user_id' => $user_id, 'meta_key' => '_gorilla_spin_available'),
            array('%d'),
            array('%d', '%s')
        );
        $wpdb->query('COMMIT');
        wp_cache_delete($user_id, 'user_meta');
    } catch (\Throwable $e) {
        $wpdb->query('ROLLBACK');
        return array('success' => false, 'error' => 'Sistem hatasi');
    }

    $total_weight = array_sum(array_column($prizes, 'weight'));
    if ($total_weight <= 0) return array('success' => false, 'error' => 'Odul yapilandirma hatasi');
    $random = wp_rand(1, $total_weight);
    $cumulative = 0;
    $won_index = 0;
    $won_prize = $prizes[0];

    foreach ($prizes as $i => $prize) {
        $cumulative += intval($prize['weight'] ?? 1);
        if ($random <= $cumulative) {
            $won_index = $i;
            $won_prize = $prize;
            break;
        }
    }

    $coupon_code = '';
    switch ($won_prize['type']) {
        case 'xp':
            if (function_exists('gorilla_xp_add')) {
                gorilla_xp_add($user_id, intval($won_prize['value']), 'Sans carki odulu', 'spin', absint($user_id . time()));
            }
            break;
        case 'credit':
            if (function_exists('gorilla_credit_adjust')) {
                gorilla_credit_adjust($user_id, floatval($won_prize['value']), 'spin', 'Sans carki odulu', 0, 30);
            }
            break;
        case 'free_shipping':
            if (function_exists('gorilla_generate_coupon')) {
                $coupon_code = gorilla_generate_coupon(array(
                    'type' => 'free_shipping', 'amount' => 0, 'expiry_days' => 7,
                    'user_id' => $user_id, 'reason' => 'spin_wheel', 'prefix' => 'SPIN',
                ));
            }
            break;
        case 'coupon':
            if (function_exists('gorilla_generate_coupon')) {
                $coupon_code = gorilla_generate_coupon(array(
                    'type' => 'percent', 'amount' => floatval($won_prize['value']), 'expiry_days' => 7,
                    'user_id' => $user_id, 'reason' => 'spin_wheel', 'prefix' => 'SPIN',
                ));
            }
            break;
        case 'nothing':
        default:
            break;
    }

    $history = get_user_meta($user_id, '_gorilla_spin_history', true);
    if (!is_array($history)) $history = array();
    $history[] = array('prize' => $won_prize['label'], 'type' => $won_prize['type'], 'date' => current_time('mysql'));
    if (count($history) > 50) $history = array_slice($history, -50);
    update_user_meta($user_id, '_gorilla_spin_history', $history);

    return array(
        'success'       => true,
        'label'         => $won_prize['label'],
        'type'          => $won_prize['type'],
        'value'         => $won_prize['value'],
        'coupon_code'   => $coupon_code,
        'segment_index' => $won_index,
        'remaining'     => max(0, $available - 1),
    );
}

add_action('wp_ajax_gorilla_spin_wheel', function() {
    check_ajax_referer('gorilla_spin_nonce', 'nonce');
    $user_id = get_current_user_id();
    if (!$user_id) wp_send_json_error('Giris yapmaniz gerekiyor');

    $result = gorilla_spin_execute($user_id);
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['error']);
    }
});

add_action('gorilla_xp_level_up', function($user_id) {
    if (get_option('gorilla_lr_spin_enabled', 'no') === 'yes') {
        gorilla_spin_grant($user_id, 'level_up');
    }
}, 10, 1);

// ══════════════════════════════════════════════════════════
// KILOMETRE TASLARI (F7)
// ══════════════════════════════════════════════════════════

function gorilla_milestones_get_all() {
    $default = array(
        array('id' => 'first_order',  'label' => 'Ilk Siparis',    'emoji' => "\xF0\x9F\x9B\x8D\xEF\xB8\x8F", 'description' => 'Ilk siparisini ver',  'type' => 'total_orders',   'target' => 1,     'xp_reward' => 50,  'credit_reward' => 0),
        array('id' => 'orders_10',    'label' => '10 Siparis',     'emoji' => "\xF0\x9F\x8F\x85", 'description' => '10 siparis tamamla',  'type' => 'total_orders',   'target' => 10,    'xp_reward' => 200, 'credit_reward' => 20),
        array('id' => 'spend_5000',   'label' => '5000 TL Harcama','emoji' => "\xF0\x9F\x92\xB0", 'description' => 'Toplam 5000 TL harca','type' => 'total_spending', 'target' => 5000,  'xp_reward' => 500, 'credit_reward' => 50),
    );
    $saved = get_option('gorilla_lr_milestones', array());
    return !empty($saved) ? $saved : $default;
}

// ══════════════════════════════════════════════════════════
// PUAN DUKKANI (F8)
// ══════════════════════════════════════════════════════════

function gorilla_shop_get_rewards() {
    $default = array(
        array('id' => 'coupon_5',      'label' => '5 TL Indirim Kuponu',    'xp_cost' => 100,  'type' => 'coupon', 'coupon_type' => 'fixed_cart', 'coupon_amount' => 5),
        array('id' => 'coupon_10',     'label' => '10 TL Indirim Kuponu',   'xp_cost' => 200,  'type' => 'coupon', 'coupon_type' => 'fixed_cart', 'coupon_amount' => 10),
        array('id' => 'coupon_pct_10', 'label' => '%10 Indirim Kuponu',     'xp_cost' => 300,  'type' => 'coupon', 'coupon_type' => 'percent',    'coupon_amount' => 10),
        array('id' => 'free_shipping', 'label' => 'Ucretsiz Kargo Kuponu',  'xp_cost' => 150,  'type' => 'free_shipping', 'coupon_type' => 'fixed_cart', 'coupon_amount' => 0),
    );
    $saved = get_option('gorilla_lr_points_shop_rewards', array());
    return !empty($saved) ? $saved : $default;
}

function gorilla_shop_redeem($user_id, $reward_id) {
    if (get_option('gorilla_lr_points_shop_enabled', 'no') !== 'yes') {
        return array('success' => false, 'error' => 'Puan dukkani aktif degil');
    }

    $rewards = gorilla_shop_get_rewards();
    $reward = null;
    foreach ($rewards as $r) {
        if (($r['id'] ?? '') === $reward_id) { $reward = $r; break; }
    }
    if (!$reward) return array('success' => false, 'error' => 'Odul bulunamadi');

    $xp_cost = intval($reward['xp_cost'] ?? 0);
    if (!function_exists('gorilla_xp_get_balance')) {
        return array('success' => false, 'error' => 'XP sistemi aktif degil');
    }

    if (!function_exists('gorilla_xp_deduct')) {
        return array('success' => false, 'error' => 'XP sistemi hazir degil');
    }

    $result = gorilla_xp_deduct($user_id, $xp_cost, 'Puan Dukkani: ' . ($reward['label'] ?? ''), 'shop', absint($user_id . time()));
    if ($result === false) {
        $current_xp = gorilla_xp_get_balance($user_id);
        return array('success' => false, 'error' => 'Yetersiz XP (' . $current_xp . '/' . $xp_cost . ')');
    }

    $coupon_code = '';
    if (function_exists('gorilla_generate_coupon')) {
        $coupon_params = array(
            'type'        => $reward['coupon_type'] ?? 'fixed_cart',
            'amount'      => floatval($reward['coupon_amount'] ?? 0),
            'expiry_days' => 30,
            'user_id'     => $user_id,
            'reason'      => 'points_shop',
            'prefix'      => 'SHOP',
        );

        if (($reward['type'] ?? '') === 'free_shipping') {
            $coupon_params['type'] = 'free_shipping';
        }

        $coupon_code = gorilla_generate_coupon($coupon_params);
    }

    return array('success' => true, 'coupon_code' => $coupon_code, 'reward' => $reward, 'new_xp' => $result);
}

add_action('wp_ajax_gorilla_shop_redeem', function() {
    check_ajax_referer('gorilla_shop_nonce', 'nonce');
    $user_id = get_current_user_id();
    if (!$user_id) wp_send_json_error('Giris yapmaniz gerekiyor');

    $reward_id = sanitize_key($_POST['reward_id'] ?? '');
    if (!$reward_id) wp_send_json_error('Odul secilmedi');

    $result = gorilla_shop_redeem($user_id, $reward_id);
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['error']);
    }
});

// ══════════════════════════════════════════════════════════
// SOSYAL PAYLASIM BONUSU (F10)
// ══════════════════════════════════════════════════════════

function gorilla_social_track_share($user_id, $platform) {
    if (get_option('gorilla_lr_social_share_enabled', 'no') !== 'yes') return false;

    $allowed = array('facebook', 'twitter', 'whatsapp', 'instagram', 'tiktok');
    if (!in_array($platform, $allowed)) return false;

    // Per-platform daily rate limit guard
    $share_guard_key = '_gorilla_share_' . $platform . '_' . current_time('Y-m-d');
    if (get_user_meta($user_id, $share_guard_key, true)) {
        return false; // Already shared on this platform today
    }
    update_user_meta($user_id, $share_guard_key, current_time('mysql'));

    $shares = get_user_meta($user_id, '_gorilla_social_shares', true);
    if (!is_array($shares)) $shares = array();

    $today = current_time('Y-m-d');

    if (isset($shares[$platform]) && ($shares[$platform]['last_date'] ?? '') === $today) {
        return false;
    }

    $shares[$platform] = array(
        'last_date' => $today,
        'total'     => intval($shares[$platform]['total'] ?? 0) + 1,
    );
    update_user_meta($user_id, '_gorilla_social_shares', $shares);

    $xp = intval(get_option('gorilla_lr_social_share_xp', 10));
    if ($xp > 0 && function_exists('gorilla_xp_add')) {
        gorilla_xp_add($user_id, $xp, sprintf('Sosyal paylasim: %s', ucfirst($platform)), 'share', absint(crc32($user_id . '_' . $platform . '_' . wp_date('Y-m-d'))));
    }

    if (count($shares) >= 3 && function_exists('gorilla_badge_award')) {
        gorilla_badge_award($user_id, 'social_butterfly');
    }

    return true;
}

add_action('wp_ajax_gorilla_track_share', function() {
    check_ajax_referer('gorilla_share_nonce', 'nonce');
    $user_id = get_current_user_id();
    if (!$user_id) wp_send_json_error('Giris yapmaniz gerekiyor');

    $platform = sanitize_key($_POST['platform'] ?? '');
    $result = gorilla_social_track_share($user_id, $platform);
    wp_send_json_success(array('awarded' => $result));
});

// ══════════════════════════════════════════════════════════
// QR KOD REFERANS (F13)
// ══════════════════════════════════════════════════════════

function gorilla_qr_get_url($user_id) {
    if (!function_exists('gorilla_affiliate_get_code')) return '';

    $code = gorilla_affiliate_get_code($user_id);
    if (!$code) return '';

    $param = get_option('gorilla_lr_affiliate_url_param', 'ref');
    $affiliate_link = add_query_arg($param, $code, home_url('/'));

    $method = get_option('gorilla_lr_qr_method', 'google');
    if ($method === 'local' && function_exists('gorilla_qr_generate_svg')) {
        return gorilla_qr_generate_svg($affiliate_link);
    }
    return 'https://quickchart.io/qr?text=' . urlencode($affiliate_link) . '&size=300&margin=2';
}

add_action('wp_ajax_gorilla_download_qr', function() {
    check_ajax_referer('gorilla_qr_download', 'nonce');
    $user_id = get_current_user_id();
    if (!$user_id) wp_die('Yetkisiz');

    $qr_url = gorilla_qr_get_url($user_id);
    if (!$qr_url) wp_die('QR kod olusturulamadi');

    $response = wp_remote_get($qr_url, array('timeout' => 15));

    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="gorilla-qr-code.png"');
        echo wp_remote_retrieve_body($response);
        exit;
    }

    wp_die('QR kod indirilemedi');
});


// ── Tier Downgrade Grace Period Cron ─────────────────────
add_action('gorilla_lr_daily_tier_check', 'gorilla_tier_grace_check');

function gorilla_tier_grace_check() {
    $grace_days = intval(get_option('gorilla_lr_tier_grace_days', 0));
    if ($grace_days <= 0) return;

    global $wpdb;
    $tiers    = gorilla_get_tiers();
    $tier_keys = array_keys($tiers);

    $user_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != 'none' LIMIT 500",
        '_gorilla_last_tier'
    ));

    foreach ($user_ids as $user_id) {
        $user_id    = intval($user_id);
        $stored_key = get_user_meta($user_id, '_gorilla_last_tier', true);
        if (!$stored_key || $stored_key === 'none' || !isset($tiers[$stored_key])) continue;

        $spending = gorilla_loyalty_get_spending($user_id);
        $raw_key  = 'none';
        foreach ($tiers as $key => $tier) {
            if (!is_array($tier)) continue;
            if ($spending >= ($tier['min'] ?? 0)) $raw_key = $key;
        }

        $stored_index = array_search($stored_key, $tier_keys, true);
        $raw_index    = ($raw_key === 'none') ? -1 : array_search($raw_key, $tier_keys, true);

        if ($raw_index >= $stored_index) {
            delete_user_meta($user_id, '_gorilla_tier_grace_until');
            delete_user_meta($user_id, '_gorilla_tier_grace_from');
            continue;
        }

        $grace_until = get_user_meta($user_id, '_gorilla_tier_grace_until', true);

        if (empty($grace_until)) {
            $grace_date = gmdate('Y-m-d', strtotime("+{$grace_days} days"));
            update_user_meta($user_id, '_gorilla_tier_grace_until', $grace_date);
            update_user_meta($user_id, '_gorilla_tier_grace_from', $stored_key);
            continue;
        }

        $remaining = (strtotime($grace_until) - time()) / DAY_IN_SECONDS;

        if ($remaining <= 0) {
            update_user_meta($user_id, '_gorilla_last_tier', $raw_key);
            update_user_meta($user_id, '_gorilla_lr_tier_key', $raw_key);
            delete_user_meta($user_id, '_gorilla_tier_grace_until');
            delete_user_meta($user_id, '_gorilla_tier_grace_from');

            if (function_exists('gorilla_email_tier_downgrade_notice')) {
                $old_tier = $tiers[$stored_key] ?? null;
                $new_tier = ($raw_key !== 'none' && isset($tiers[$raw_key])) ? $tiers[$raw_key] : null;
                gorilla_email_tier_downgrade_notice($user_id, $old_tier, $new_tier);
            }
        } else {
            $remaining_int = intval(ceil($remaining));
            $warned_key    = '_gorilla_grace_warned_' . $remaining_int;

            if (in_array($remaining_int, array(15, 7, 1), true) && !get_user_meta($user_id, $warned_key, true)) {
                update_user_meta($user_id, $warned_key, '1');
                if (function_exists('gorilla_email_tier_grace_warning')) {
                    gorilla_email_tier_grace_warning($user_id, $tiers[$stored_key] ?? null, $remaining_int);
                }
            }
        }
    }
}

// ══════════════════════════════════════════════════════════
// VIP EARLY ACCESS
// ══════════════════════════════════════════════════════════

add_action('woocommerce_product_options_general_product_data', function() {
    if (get_option('gorilla_lr_vip_early_access_enabled', 'no') !== 'yes') return;

    echo '<div class="options_group">';

    woocommerce_wp_checkbox(array(
        'id'          => '_gorilla_vip_early_access',
        'label'       => 'VIP Erken Erisim',
        'description' => 'Bu urun indirimde oldugunda ust tier musterilere erken acilir.',
    ));

    woocommerce_wp_text_input(array(
        'id'          => '_gorilla_vip_hours',
        'label'       => 'Erken Erisim (saat)',
        'type'        => 'number',
        'desc_tip'    => true,
        'description' => 'VIP musteriler indirimi bu kadar saat once gorur.',
        'custom_attributes' => array('min' => 1, 'max' => 72, 'step' => 1),
    ));

    woocommerce_wp_text_input(array(
        'id'          => '_gorilla_vip_min_tier',
        'label'       => 'Minimum Tier Key',
        'description' => 'Erken erisen minimum tier (orn: gold, platinum). Bos = tum tier\'ler.',
    ));

    echo '</div>';
});

add_action('woocommerce_process_product_meta', function($post_id) {
    if (get_option('gorilla_lr_vip_early_access_enabled', 'no') !== 'yes') return;

    $early = isset($_POST['_gorilla_vip_early_access']) ? 'yes' : 'no';
    update_post_meta($post_id, '_gorilla_vip_early_access', $early);

    $hours = max(1, min(72, intval($_POST['_gorilla_vip_hours'] ?? 24)));
    update_post_meta($post_id, '_gorilla_vip_hours', $hours);

    $min_tier = sanitize_key($_POST['_gorilla_vip_min_tier'] ?? '');
    update_post_meta($post_id, '_gorilla_vip_min_tier', $min_tier);
});

add_filter('woocommerce_product_get_sale_price', 'gorilla_vip_filter_sale_price', 10, 2);
add_filter('woocommerce_product_get_date_on_sale_from', 'gorilla_vip_filter_sale_date', 10, 2);

function gorilla_vip_filter_sale_price($sale_price, $product) {
    if (get_option('gorilla_lr_vip_early_access_enabled', 'no') !== 'yes') return $sale_price;
    if (empty($sale_price)) return $sale_price;
    if (is_admin()) return $sale_price;

    $product_id = $product->get_id();
    if (get_post_meta($product_id, '_gorilla_vip_early_access', true) !== 'yes') return $sale_price;

    $sale_from = $product->get_date_on_sale_from('edit');
    if (!$sale_from) return $sale_price;

    $hours = intval(get_post_meta($product_id, '_gorilla_vip_hours', true)) ?: 24;
    $early_start = strtotime("-{$hours} hours", $sale_from->getTimestamp());
    $now = time();

    if ($now < $early_start) return '';
    if ($now >= $sale_from->getTimestamp()) return $sale_price;

    if (!is_user_logged_in()) return '';

    $tier = gorilla_loyalty_calculate_tier(get_current_user_id());
    $tier_key = $tier['key'] ?? 'none';
    if ($tier_key === 'none') return '';

    $min_tier = get_post_meta($product_id, '_gorilla_vip_min_tier', true);
    if (!empty($min_tier)) {
        $tiers = function_exists('gorilla_get_tiers') ? gorilla_get_tiers() : array();
        $tier_keys = array_keys($tiers);
        $user_index = array_search($tier_key, $tier_keys, true);
        $min_index = array_search($min_tier, $tier_keys, true);
        if ($user_index === false || $min_index === false || $user_index < $min_index) {
            return '';
        }
    }

    return $sale_price;
}

function gorilla_vip_filter_sale_date($date, $product) {
    if (get_option('gorilla_lr_vip_early_access_enabled', 'no') !== 'yes') return $date;
    if (!$date) return $date;
    if (is_admin()) return $date;

    $product_id = $product->get_id();
    if (get_post_meta($product_id, '_gorilla_vip_early_access', true) !== 'yes') return $date;

    if (!is_user_logged_in()) return $date;

    $tier = gorilla_loyalty_calculate_tier(get_current_user_id());
    if (($tier['key'] ?? 'none') === 'none') return $date;

    $hours = intval(get_post_meta($product_id, '_gorilla_vip_hours', true)) ?: 24;

    $min_tier = get_post_meta($product_id, '_gorilla_vip_min_tier', true);
    if (!empty($min_tier)) {
        $tiers = function_exists('gorilla_get_tiers') ? gorilla_get_tiers() : array();
        $tier_keys = array_keys($tiers);
        $user_index = array_search($tier['key'], $tier_keys, true);
        $min_index = array_search($min_tier, $tier_keys, true);
        if ($user_index === false || $min_index === false || $user_index < $min_index) {
            return $date;
        }
    }

    $early = clone $date;
    $early->modify("-{$hours} hours");
    return $early;
}


// ══════════════════════════════════════════════════════════
// POINTS / CREDIT TRANSFER
// ══════════════════════════════════════════════════════════

add_action('wp_ajax_gorilla_transfer_credit', 'gorilla_transfer_credit_handler');
function gorilla_transfer_credit_handler() {
    check_ajax_referer('gorilla_transfer_nonce', '_nonce');

    if (get_option('gorilla_lr_transfer_enabled', 'no') !== 'yes') {
        wp_send_json_error(array('message' => 'Transfer ozelligi aktif degil.'));
    }

    $sender_id = get_current_user_id();
    if (!$sender_id) {
        wp_send_json_error(array('message' => 'Oturum acmaniz gerekiyor.'));
    }

    $recipient_email = sanitize_email(wp_unslash($_POST['recipient_email'] ?? ''));
    $amount          = floatval($_POST['amount'] ?? 0);
    $transfer_type   = sanitize_text_field($_POST['transfer_type'] ?? 'credit');

    if (empty($recipient_email) || !is_email($recipient_email)) {
        wp_send_json_error(array('message' => 'Gecerli bir e-posta adresi girin.'));
    }

    if ($amount <= 0) {
        wp_send_json_error(array('message' => 'Transfer miktari 0\'dan buyuk olmalidir.'));
    }

    $recipient = get_user_by('email', $recipient_email);
    if (!$recipient || $recipient->ID === $sender_id) {
        wp_send_json_error(array('message' => 'Alici bulunamadi veya kendinize transfer yapamazsiniz.'));
    }

    $daily_limit = floatval(get_option('gorilla_lr_transfer_daily_limit', 500));
    $today_key   = '_gorilla_transfer_total_' . current_time('Y-m-d');
    $today_total = floatval(get_user_meta($sender_id, $today_key, true));

    if (($today_total + $amount) > $daily_limit) {
        wp_send_json_error(array('message' => sprintf('Gunluk transfer limitiniz %s. Bugun %s transfer ettiniz.', number_format_i18n($daily_limit), number_format_i18n($today_total))));
    }

    $min_amount = floatval(get_option('gorilla_lr_transfer_min_amount', 10));
    if ($amount < $min_amount) {
        wp_send_json_error(array('message' => sprintf('Minimum transfer miktari: %s', number_format_i18n($min_amount))));
    }

    if ($transfer_type === 'credit') {
        if (!function_exists('gorilla_credit_get_balance') || !function_exists('gorilla_credit_adjust')) {
            wp_send_json_error(array('message' => 'Kredi sistemi kulanilamiyor.'));
        }

        // Atomic credit transfer: check balance + deduct + add in a single transaction
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            $sender_balance = floatval($wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = '_gorilla_store_credit' FOR UPDATE",
                $sender_id
            )));
            if ($sender_balance < $amount) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error(array('message' => sprintf('Yetersiz bakiye. Mevcut: %s TL', number_format_i18n($sender_balance, 2))));
            }
            gorilla_credit_adjust($sender_id, -$amount, 'transfer_out', sprintf('Transfer -> %s', $recipient->display_name), $recipient->ID);
            gorilla_credit_adjust($recipient->ID, $amount, 'transfer_in', sprintf('Transfer <- %s', wp_get_current_user()->display_name), $sender_id);
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => 'Transfer sirasinda hata olustu. Lutfen tekrar deneyin.'));
        }
    } elseif ($transfer_type === 'xp') {
        if (!function_exists('gorilla_xp_get_balance') || !function_exists('gorilla_xp_deduct') || !function_exists('gorilla_xp_add')) {
            wp_send_json_error(array('message' => 'XP sistemi kulanilamiyor.'));
        }

        // Atomic XP transfer: check balance + deduct + add in a single transaction
        global $wpdb;
        $level_table = $wpdb->prefix . 'gamify_user_levels';
        $wpdb->query('START TRANSACTION');
        try {
            $sender_xp = intval($wpdb->get_var($wpdb->prepare(
                "SELECT total_xp FROM {$level_table} WHERE user_id = %d FOR UPDATE",
                $sender_id
            )));
            if ($sender_xp < $amount) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error(array('message' => sprintf('Yetersiz XP. Mevcut: %s', number_format_i18n($sender_xp))));
            }
            gorilla_xp_deduct($sender_id, intval($amount), sprintf('Transfer -> %s', $recipient->display_name));
            gorilla_xp_add($recipient->ID, intval($amount), sprintf('Transfer <- %s', wp_get_current_user()->display_name));
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => 'XP transferi sirasinda hata olustu. Lutfen tekrar deneyin.'));
        }
    } else {
        wp_send_json_error(array('message' => 'Gecersiz transfer tipi.'));
    }

    update_user_meta($sender_id, $today_key, $today_total + $amount);

    gorilla_log_transfer($sender_id, $recipient->ID, $amount, $transfer_type);

    wp_send_json_success(array(
        'message' => sprintf('%s basariyla %s\'e transfer edildi!', number_format_i18n($amount) . ($transfer_type === 'credit' ? ' TL' : ' XP'), esc_html($recipient->display_name)),
    ));
}

function gorilla_log_transfer($sender_id, $recipient_id, $amount, $type) {
    $log_entry = array(
        'recipient_id' => $recipient_id,
        'amount'       => $amount,
        'type'         => $type,
        'date'         => current_time('mysql'),
    );
    $log = get_user_meta($sender_id, '_gorilla_transfer_log', true);
    if (!is_array($log)) $log = array();
    array_unshift($log, $log_entry);
    $log = array_slice($log, 0, 50);
    update_user_meta($sender_id, '_gorilla_transfer_log', $log);

    $recv_entry = array(
        'sender_id' => $sender_id,
        'amount'    => $amount,
        'type'      => $type,
        'date'      => current_time('mysql'),
    );
    $recv_log = get_user_meta($recipient_id, '_gorilla_transfer_received_log', true);
    if (!is_array($recv_log)) $recv_log = array();
    array_unshift($recv_log, $recv_entry);
    $recv_log = array_slice($recv_log, 0, 50);
    update_user_meta($recipient_id, '_gorilla_transfer_received_log', $recv_log);
}
