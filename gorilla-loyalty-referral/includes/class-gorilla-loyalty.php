<?php
/**
 * Gorilla LR - Sadakat Sistemi
 * Seviye hesaplama, otomatik indirim, sepet/checkout entegrasyonu
 */

if (!defined('ABSPATH')) exit;

// ── Kullanıcının son X aydaki harcamasını hesapla ────────
function gorilla_loyalty_get_spending($user_id) {
    if (!$user_id) return 0;
    
    try {
        global $wpdb;
        
        $months = intval(get_option('gorilla_lr_period_months', 6));
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$months} months"));
        
        // Cache kontrol
        $cache_key = 'gorilla_spending_' . $user_id;
        $cached = get_transient($cache_key);
        if ($cached !== false) return floatval($cached);
        
        $total = 0;
        
        // HPOS uyumlu sorgu (güvenli kontrol)
        $use_hpos = false;
        if (class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')) {
            if (method_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil', 'custom_orders_table_enabled')) {
                $use_hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_enabled();
            }
        }
        
        if ($use_hpos) {
            $table = $wpdb->prefix . 'wc_orders';
            // Tablo var mı kontrol
            if (gorilla_lr_table_exists($table)) {
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
            error_log('Gorilla LR spending error: ' . $e->getMessage());
        }
        return 0;
    }
}

// ── Seviye hesapla ───────────────────────────────────────
function gorilla_loyalty_calculate_tier($user_id) {
    $default = array('key' => 'none', 'label' => 'Üye', 'discount' => 0, 'emoji' => '👤', 'color' => '#999', 'installment' => 0, 'spending' => 0);

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
        $current_tier = array('label' => 'Üye', 'discount' => 0, 'emoji' => '👤', 'color' => '#999999', 'installment' => 0);

        foreach ($tiers as $key => $tier) {
            if (!is_array($tier)) continue;
            if ($spending >= ($tier['min'] ?? 0)) {
                $current_key = $key;
                $current_tier = $tier;
            }
        }

        $result = array_merge($current_tier, array(
            'key'      => $current_key,
            'spending' => $spending,
        ));
        $cache[$user_id] = $result; return $result;
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla LR tier calc error: ' . $e->getMessage());
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
    
    // 'none' ise ilk seviyeyi döndür
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
    
    return null; // En üst seviyede
}


// ── Sepette Otomatik İndirim ─────────────────────────────
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
            error_log('Gorilla LR cart discount error: ' . $e->getMessage());
        }
    }
}


// ── Sipariş tamamlandığında cache temizle ve seviye kontrolü ─
add_action('woocommerce_order_status_completed', function($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    $user_id = $order->get_customer_id();
    if (!$user_id) return;

    // Önceki seviyeyi al
    $old_tier_key = get_user_meta($user_id, '_gorilla_last_tier', true);
    if (!$old_tier_key) $old_tier_key = 'none';

    // Cache temizle
    delete_transient('gorilla_spending_' . $user_id);
    delete_transient('gorilla_lr_bar_' . $user_id);

    // Yeni seviyeyi hesapla
    $new_tier = gorilla_loyalty_calculate_tier($user_id);
    $new_tier_key = $new_tier['key'] ?? 'none';

    // Seviye yükseldiyse tebrik e-postası gönder
    if ($new_tier_key !== 'none' && $new_tier_key !== $old_tier_key) {
        // Tüm tier'ları al ve sırasını kontrol et
        $tiers = gorilla_get_tiers();
        $tier_keys = array_keys($tiers);
        $old_index = array_search($old_tier_key, $tier_keys);
        $new_index = array_search($new_tier_key, $tier_keys);

        // Yükselme olduysa (index arttıysa veya none'dan tier'a geçtiyse)
        if ($old_tier_key === 'none' || ($old_index !== false && $new_index !== false && $new_index > $old_index)) {
            // E-posta gönder
            if (function_exists('gorilla_email_tier_upgrade')) {
                $old_tier = ($old_tier_key !== 'none' && isset($tiers[$old_tier_key])) ? $tiers[$old_tier_key] : null;
                gorilla_email_tier_upgrade($user_id, $old_tier, $new_tier);
            }
        }
    }

    // Yeni seviyeyi kaydet
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


// ── Seviye Bazlı Ücretsiz Kargo ──────────────────────────
add_filter('woocommerce_package_rates', 'gorilla_loyalty_free_shipping', 100, 2);
function gorilla_loyalty_free_shipping($rates, $package) {
    if (!is_user_logged_in()) return $rates;
    if (get_option('gorilla_lr_enabled_loyalty') !== 'yes') return $rates;

    try {
        $tier = gorilla_loyalty_calculate_tier(get_current_user_id());
        if (!is_array($tier) || empty($tier['free_shipping'])) return $rates;

        // Ücretsiz kargo hakkı var - tüm kargo ücretlerini sıfırla
        foreach ($rates as $rate_key => $rate) {
            if ($rate->method_id !== 'free_shipping') {
                $rates[$rate_key]->cost = 0;
                $rates[$rate_key]->label = sprintf(
                    '%s %s (%s)',
                    $tier['emoji'] ?? '🎖️',
                    __('Ücretsiz Kargo', 'gorilla-lr'),
                    $tier['label'] ?? ''
                );
            }
        }
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla LR free shipping error: ' . $e->getMessage());
        }
    }

    return $rates;
}


// ── Sipariş notunda seviye bilgisi ───────────────────────
add_action('woocommerce_checkout_order_created', function($order) {
    $user_id = $order->get_customer_id();
    if (!$user_id) return;
    
    $tier = gorilla_loyalty_calculate_tier($user_id);
    if ($tier['key'] !== 'none') {
        $note = sprintf(
            '🎖️ Müşteri Sadakat Seviyesi: %s %s (%%%s indirim uygulandı)',
            $tier['emoji'], $tier['label'], $tier['discount']
        );
        if ($tier['installment'] > 0) {
            $note .= sprintf(' — Vade farksız %d taksit hakkı var.', $tier['installment']);
        }
        $order->add_order_note($note);
        
        // Seviye meta olarak kaydet
        $order->update_meta_data('_gorilla_loyalty_tier', $tier['key']);
        $order->update_meta_data('_gorilla_loyalty_discount', $tier['discount']);
        $order->save();
    }
});

// ══════════════════════════════════════════════════════════
// KUPON ENTEGRASYONU (F11)
// ══════════════════════════════════════════════════════════

function gorilla_generate_coupon($params = array()) {
    if (!class_exists('WC_Coupon')) return false;

    $defaults = array(
        'type'        => 'percent',
        'amount'      => 10,
        'min_order'   => 0,
        'expiry_days' => 30,
        'user_id'     => 0,
        'reason'      => '',
        'prefix'      => 'GORILLA',
        'usage_limit' => 1,
    );
    $params = wp_parse_args($params, $defaults);

    $code = $params['prefix'] . '-' . strtoupper(wp_generate_password(8, false));

    $coupon = new \WC_Coupon();
    $coupon->set_code($code);
    $coupon->set_discount_type($params['type'] === 'percent' ? 'percent' : 'fixed_cart');
    $coupon->set_amount(floatval($params['amount']));
    $coupon->set_individual_use(true);
    $coupon->set_usage_limit(intval($params['usage_limit']));

    if ($params['type'] === 'free_shipping') {
        $coupon->set_free_shipping(true);
        $coupon->set_discount_type('fixed_cart');
        $coupon->set_amount(0);
    }

    if ($params['min_order'] > 0) {
        $coupon->set_minimum_amount(floatval($params['min_order']));
    }

    if ($params['expiry_days'] > 0) {
        $expiry = gmdate('Y-m-d', strtotime('+' . intval($params['expiry_days']) . ' days'));
        $coupon->set_date_expires($expiry);
    }

    if ($params['user_id']) {
        $user = get_userdata($params['user_id']);
        if ($user) {
            $coupon->set_email_restrictions(array($user->user_email));
        }
    }

    $coupon->save();

    if ($coupon->get_id()) {
        update_post_meta($coupon->get_id(), '_gorilla_coupon_reason', sanitize_text_field($params['reason']));
    }

    return $code;
}

// ══════════════════════════════════════════════════════════
// ROZET SISTEMI (F3)
// ══════════════════════════════════════════════════════════

function gorilla_badge_get_definitions() {
    return array(
        'first_purchase'   => array('label' => 'Ilk Alisveris',      'emoji' => '🛍️', 'description' => 'Ilk siparisini verdi',          'color' => '#22c55e'),
        '10_orders'        => array('label' => 'Sadik Musteri',       'emoji' => '🏅', 'description' => '10 siparis tamamladi',          'color' => '#f59e0b'),
        '5_referrals'      => array('label' => 'Referans Yildizi',    'emoji' => '🌟', 'description' => '5 referans onayi aldi',         'color' => '#ec4899'),
        '10_reviews'       => array('label' => 'Yorum Ustasi',        'emoji' => '✍️', 'description' => '10 urun yorumu yapti',           'color' => '#8b5cf6'),
        'social_butterfly' => array('label' => 'Sosyal Kelebek',      'emoji' => '🦋', 'description' => '3 platformda paylasim yapti',   'color' => '#06b6d4'),
        'vip_tier'         => array('label' => 'VIP',                  'emoji' => '💎', 'description' => 'Diamond seviyeye ulasti',       'color' => '#a855f7'),
        'streak_master'    => array('label' => 'Seri Ustasi',          'emoji' => '🔥', 'description' => '30 gunluk giris serisi',        'color' => '#ef4444'),
        'big_spender'      => array('label' => 'Buyuk Harcamaci',     'emoji' => '💰', 'description' => '10000 TL ustu harcama',         'color' => '#f97316'),
        'birthday_club'    => array('label' => 'Dogum Gunu Kulubu',   'emoji' => '🎂', 'description' => 'Dogum gununu paylasti',         'color' => '#f472b6'),
    );
}

function gorilla_badge_award($user_id, $badge_id) {
    if (get_option('gorilla_lr_badges_enabled', 'no') !== 'yes') return false;

    $definitions = gorilla_badge_get_definitions();
    if (!isset($definitions[$badge_id])) return false;

    $badges = get_user_meta($user_id, '_gorilla_badges', true);
    if (!is_array($badges)) $badges = array();

    if (isset($badges[$badge_id])) return false;

    $badges[$badge_id] = array('earned_at' => current_time('mysql'));
    update_user_meta($user_id, '_gorilla_badges', $badges);

    do_action('gorilla_badge_earned', $user_id, $badge_id);
    return true;
}

function gorilla_badge_get_user_badges($user_id) {
    $earned = get_user_meta($user_id, '_gorilla_badges', true);
    if (!is_array($earned)) $earned = array();

    $definitions = gorilla_badge_get_definitions();
    $result = array();

    foreach ($definitions as $id => $def) {
        $result[$id] = array_merge($def, array(
            'earned'    => isset($earned[$id]),
            'earned_at' => isset($earned[$id]) ? $earned[$id]['earned_at'] : null,
        ));
    }

    return $result;
}

function gorilla_badge_check_all($user_id) {
    if (get_option('gorilla_lr_badges_enabled', 'no') !== 'yes') return;
    if (!$user_id) return;

    // Onceden kazanilmis rozetleri kontrol et - kazanilmislari atla
    $earned = get_user_meta($user_id, '_gorilla_badges', true);
    if (!is_array($earned)) $earned = array();

    // Siparis bazli rozetler - sadece kazanilmamislari sorgula
    if (!isset($earned['first_purchase']) || !isset($earned['10_orders'])) {
        $orders_check = wc_get_orders(array('customer_id' => $user_id, 'status' => array('completed', 'processing'), 'limit' => 11, 'return' => 'ids'));
        if (!is_array($orders_check)) $orders_check = array();
        if (!isset($earned['first_purchase']) && !empty($orders_check)) gorilla_badge_award($user_id, 'first_purchase');
        if (!isset($earned['10_orders']) && count($orders_check) >= 10) gorilla_badge_award($user_id, '10_orders');
    }

    // Referans rozeti
    if (!isset($earned['5_referrals'])) {
        $refs = get_posts(array('post_type' => 'gorilla_referral', 'post_status' => 'grla_approved', 'meta_key' => '_ref_user_id', 'meta_value' => $user_id, 'numberposts' => 6, 'fields' => 'ids'));
        if (count($refs) >= 5) gorilla_badge_award($user_id, '5_referrals');
    }

    // Yorum rozeti
    if (!isset($earned['10_reviews'])) {
        global $wpdb;
        $review_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d AND comment_type = 'review' AND comment_approved = '1'",
            $user_id
        )));
        if ($review_count >= 10) gorilla_badge_award($user_id, '10_reviews');
    }

    // Sosyal rozet (ucuz - sadece meta okuma)
    if (!isset($earned['social_butterfly'])) {
        $shares = get_user_meta($user_id, '_gorilla_social_shares', true);
        if (is_array($shares) && count($shares) >= 3) gorilla_badge_award($user_id, 'social_butterfly');
    }

    // VIP rozet
    if (!isset($earned['vip_tier']) && function_exists('gorilla_loyalty_calculate_tier')) {
        $tier = gorilla_loyalty_calculate_tier($user_id);
        if (in_array($tier['key'] ?? '', array('diamond', 'platinum'))) gorilla_badge_award($user_id, 'vip_tier');
    }

    // Harcama rozeti
    if (!isset($earned['big_spender']) && function_exists('gorilla_loyalty_get_spending')) {
        $spending = gorilla_loyalty_get_spending($user_id);
        if ($spending >= 10000) gorilla_badge_award($user_id, 'big_spender');
    }

    // Dogum gunu rozeti (ucuz - sadece meta okuma)
    if (!isset($earned['birthday_club'])) {
        if (get_user_meta($user_id, '_gorilla_birthday', true)) gorilla_badge_award($user_id, 'birthday_club');
    }
}

// Badge kontrolunu onemli aksiyonlarda tetikle
add_action('woocommerce_order_status_completed', function($order_id) {
    $order = wc_get_order($order_id);
    if ($order && $order->get_customer_id()) {
        gorilla_badge_check_all($order->get_customer_id());
    }
}, 30);

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
    return get_option('gorilla_lr_spin_prizes', $default);
}

function gorilla_spin_grant($user_id, $reason = 'level_up') {
    $available = intval(get_user_meta($user_id, '_gorilla_spin_available', true));
    update_user_meta($user_id, '_gorilla_spin_available', $available + 1);
}

function gorilla_spin_execute($user_id) {
    global $wpdb;

    $prizes = gorilla_spin_get_prizes();
    if (empty($prizes)) return array('success' => false, 'error' => 'Odul bulunamadi');

    // Atomic spin hak kontrolu ve dusurme (race condition onleme)
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

    // Agirlikli rastgele secim
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

    // Odulu ver
    $coupon_code = '';
    switch ($won_prize['type']) {
        case 'xp':
            if (function_exists('gorilla_xp_add')) {
                gorilla_xp_add($user_id, intval($won_prize['value']), 'Sans carki odulu', 'spin', absint(wp_rand(100000, 999999999)));
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

    // Gecmise kaydet
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

// AJAX handler
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

// Seviye atladiginda spin hakki ver
add_action('gorilla_xp_level_up', function($user_id) {
    if (get_option('gorilla_lr_spin_enabled', 'no') === 'yes') {
        gorilla_spin_grant($user_id, 'level_up');
    }
}, 10, 1);

// ══════════════════════════════════════════════════════════
// PUAN DUKKANI (F8)
// ══════════════════════════════════════════════════════════

function gorilla_shop_get_rewards() {
    return get_option('gorilla_lr_points_shop_rewards', array());
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

    // Yetersizlik kontrolu atomic gorilla_xp_deduct icinde yapilir

    // XP dus
    if (!function_exists('gorilla_xp_deduct')) {
        return array('success' => false, 'error' => 'XP sistemi hazir degil');
    }

    $result = gorilla_xp_deduct($user_id, $xp_cost, 'Puan Dukkani: ' . ($reward['label'] ?? ''), 'shop', absint(wp_rand(100000, 999999999)));
    if ($result === false) {
        $current_xp = gorilla_xp_get_balance($user_id);
        return array('success' => false, 'error' => 'Yetersiz XP (' . $current_xp . '/' . $xp_cost . ')');
    }

    // Kupon uret
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

// AJAX handler
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

    $shares = get_user_meta($user_id, '_gorilla_social_shares', true);
    if (!is_array($shares)) $shares = array();

    $today = current_time('Y-m-d');

    // Gunluk limit: platform basina 1
    if (isset($shares[$platform]) && ($shares[$platform]['last_date'] ?? '') === $today) {
        return false;
    }

    $shares[$platform] = array(
        'last_date' => $today,
        'total'     => intval($shares[$platform]['total'] ?? 0) + 1,
    );
    update_user_meta($user_id, '_gorilla_social_shares', $shares);

    // XP ver
    $xp = intval(get_option('gorilla_lr_social_share_xp', 10));
    if ($xp > 0 && function_exists('gorilla_xp_add')) {
        gorilla_xp_add($user_id, $xp, sprintf('Sosyal paylasim: %s', ucfirst($platform)), 'share', absint(crc32($user_id . '_' . $platform . '_' . wp_date('Y-m-d'))));
    }

    // Badge kontrol
    if (count($shares) >= 3 && function_exists('gorilla_badge_award')) {
        gorilla_badge_award($user_id, 'social_butterfly');
    }

    return true;
}

// AJAX handler
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

    return 'https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=' . urlencode($affiliate_link) . '&choe=UTF-8';
}

// AJAX handler: QR kod indirme
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
