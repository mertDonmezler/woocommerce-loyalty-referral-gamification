<?php
/**
 * Gorilla RA - Affiliate Link Sistemi
 * Referans link takibi ve otomatik komisyon
 *
 * @package Gorilla_Referral_Affiliate
 * @author Mert Donmezler
 * @copyright 2025-2026 Mert Donmezler
 */

if (!defined('ABSPATH')) exit;

// ======================================================================
// REFERANS KODU YONETIMI
// ======================================================================

/**
 * Kullanicinin affiliate kodunu al veya olustur
 */
function gorilla_affiliate_get_code($user_id) {
    $code = get_user_meta($user_id, '_gorilla_affiliate_code', true);

    if (empty($code)) {
        $code = gorilla_affiliate_generate_code($user_id);
        update_user_meta($user_id, '_gorilla_affiliate_code', $code);
    }

    return $code;
}

/**
 * Benzersiz affiliate kodu olustur
 * Format: G + base36(user_id) + random(2)
 */
function gorilla_affiliate_generate_code($user_id, $attempt = 0) {
    if ($attempt >= 10) {
        return 'G' . strtoupper(wp_generate_password(8, false));
    }

    $prefix = 'G';
    $user_part = strtoupper(base_convert($user_id, 10, 36));

    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $suffix = '';
    for ($i = 0; $i < 2; $i++) {
        $suffix .= $chars[wp_rand(0, strlen($chars) - 1)];
    }

    $code = $prefix . $user_part . $suffix;

    // Benzersizlik kontrolu
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = '_gorilla_affiliate_code' AND meta_value = %s",
        $code
    ));

    if ($exists > 0) {
        return gorilla_affiliate_generate_code($user_id, $attempt + 1);
    }

    return $code;
}

/**
 * Affiliate kodundan kullanici ID'sini bul
 */
function gorilla_affiliate_get_user_by_code($code) {
    global $wpdb;

    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_gorilla_affiliate_code' AND meta_value = %s LIMIT 1",
        $code
    ));

    return $user_id ? intval($user_id) : null;
}

/**
 * Kullanicinin affiliate linkini olustur
 */
function gorilla_affiliate_get_link($user_id) {
    $code = gorilla_affiliate_get_code($user_id);
    $param = get_option('gorilla_lr_affiliate_url_param', 'ref');

    return add_query_arg($param, $code, home_url('/'));
}


// ======================================================================
// COOKIE TRACKING
// ======================================================================

/**
 * URL'den referans kodunu yakala ve cookie birak
 */
add_action('init', 'gorilla_affiliate_capture_referral', 1);
function gorilla_affiliate_capture_referral() {
    if (get_option('gorilla_lr_enabled_affiliate', 'yes') !== 'yes') {
        return;
    }

    $param = get_option('gorilla_lr_affiliate_url_param', 'ref');
    if (!isset($_GET[$param])) {
        return;
    }

    $ref_code = sanitize_text_field($_GET[$param]);

    if (empty($ref_code) || !preg_match('/^[A-Za-z0-9\-]{3,30}$/', $ref_code)) {
        return;
    }

    $referrer_id_check = gorilla_affiliate_get_user_by_code($ref_code);
    if (!$referrer_id_check) {
        $ref_code = strtoupper($ref_code);
    }

    $referrer_id = gorilla_affiliate_get_user_by_code($ref_code);
    if (!$referrer_id) {
        return;
    }

    // Self-referral kontrolu
    if (is_user_logged_in() && get_current_user_id() == $referrer_id) {
        if (get_option('gorilla_lr_affiliate_allow_self', 'no') !== 'yes') {
            return;
        }
    }

    // Cookie birak
    $cookie_days = intval(get_option('gorilla_lr_affiliate_cookie_days', 30));
    $cookie_name = 'gorilla_ref';

    if (!headers_sent()) {
        setcookie($cookie_name, $ref_code, array(
            'expires'  => time() + ($cookie_days * DAY_IN_SECONDS),
            'path'     => COOKIEPATH,
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly'  => true,
            'samesite' => 'Lax',
        ));

        $_COOKIE[$cookie_name] = $ref_code;
    }

    // WooCommerce session'a da ekle
    if (function_exists('WC') && WC()->session) {
        WC()->session->set('gorilla_affiliate_code', $ref_code);
        WC()->session->set('gorilla_affiliate_referrer_id', $referrer_id);
    }

    // Click logla
    gorilla_affiliate_log_click($referrer_id, $ref_code);
}

/**
 * Tiklamayi logla
 */
function gorilla_affiliate_log_click($referrer_id, $ref_code) {
    global $wpdb;

    $table = $wpdb->prefix . 'gorilla_affiliate_clicks';

    if (!gorilla_lr_table_exists($table)) {
        return;
    }

    $visitor_ip = gorilla_affiliate_get_visitor_ip();
    $today = gmdate('Y-m-d');

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE referrer_code = %s AND visitor_ip = %s AND DATE(clicked_at) = %s",
        $ref_code, $visitor_ip, $today
    ));

    if ($exists > 0) {
        return;
    }

    $wpdb->insert($table, array(
        'referrer_user_id' => $referrer_id,
        'referrer_code'    => $ref_code,
        'visitor_ip'       => $visitor_ip,
        'clicked_at'       => current_time('mysql'),
        'converted'        => 0,
    ), array('%d', '%s', '%s', '%s', '%d'));
}

/**
 * Ziyaretci IP'sini al (proxy arkasinda da calisir)
 */
function gorilla_affiliate_get_visitor_ip() {
    $trusted_header = apply_filters('gorilla_affiliate_trusted_ip_header', '');

    $ip = '';

    if ($trusted_header && !empty($_SERVER[$trusted_header])) {
        $ips = explode(',', sanitize_text_field($_SERVER[$trusted_header]));
        $ip = trim($ips[0]);
    }

    if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    }

    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '0.0.0.0';
    }

    return $ip;
}


// ======================================================================
// CHECKOUT ENTEGRASYONU
// ======================================================================

/**
 * Siparis olusturuldugunda referrer bilgisini kaydet
 */
add_action('woocommerce_checkout_order_created', 'gorilla_affiliate_save_order_referrer', 10, 1);
function gorilla_affiliate_save_order_referrer($order) {
    if (get_option('gorilla_lr_enabled_affiliate', 'yes') !== 'yes') {
        return;
    }

    $ref_code = null;
    $referrer_id = null;

    // 1. WooCommerce session'dan
    if (function_exists('WC') && WC()->session) {
        $ref_code = WC()->session->get('gorilla_affiliate_code');
        $referrer_id = WC()->session->get('gorilla_affiliate_referrer_id');
    }

    // 2. Cookie'den
    if (!$ref_code && isset($_COOKIE['gorilla_ref'])) {
        $ref_code = sanitize_text_field($_COOKIE['gorilla_ref']);
        $referrer_id = gorilla_affiliate_get_user_by_code($ref_code);
    }

    if (!$referrer_id || !$ref_code) {
        return;
    }

    // Self-referral kontrolu
    $customer_id = $order->get_customer_id();
    if ($customer_id && $customer_id == $referrer_id) {
        if (get_option('gorilla_lr_affiliate_allow_self', 'no') !== 'yes') {
            return;
        }
    }

    // Sadece ilk siparis kontrolu
    if (get_option('gorilla_lr_affiliate_first_only', 'no') === 'yes' && $customer_id) {
        $previous = wc_get_orders(array(
            'customer_id' => $customer_id,
            'status'      => array('completed', 'processing'),
            'limit'       => 1,
            'exclude'     => array($order->get_id()),
        ));

        if (!empty($previous)) {
            return;
        }
    }

    // Order meta kaydet
    $order->update_meta_data('_gorilla_affiliate_referrer_code', $ref_code);
    $order->update_meta_data('_gorilla_affiliate_referrer_id', $referrer_id);
    $order->save();

    // Musteri ilk kez bu affiliate tarafindan yonlendirildi ise kaydet
    if ($customer_id) {
        $existing_referrer = get_user_meta($customer_id, '_gorilla_referred_by', true);
        if (!$existing_referrer) {
            update_user_meta($customer_id, '_gorilla_referred_by', $referrer_id);
        }
    }

    // Click'i converted olarak isaretle
    gorilla_affiliate_mark_converted($ref_code, $order->get_id());

    // Siparis notuna ekle
    $referrer = get_userdata($referrer_id);
    if ($referrer) {
        $order->add_order_note(sprintf(
            'Affiliate: %s (%s) tarafindan getirildi',
            esc_html($referrer->display_name),
            esc_html($ref_code)
        ));
    }
}

/**
 * Click'i converted olarak isaretle
 */
function gorilla_affiliate_mark_converted($ref_code, $order_id) {
    global $wpdb;

    $table = $wpdb->prefix . 'gorilla_affiliate_clicks';

    if (!gorilla_lr_table_exists($table)) {
        return;
    }

    $wpdb->query($wpdb->prepare(
        "UPDATE {$table} SET converted = 1, order_id = %d, converted_at = %s
         WHERE referrer_code = %s AND converted = 0
         ORDER BY clicked_at DESC LIMIT 1",
        $order_id, current_time('mysql'), $ref_code
    ));
}


// ======================================================================
// OTOMATIK KOMISYON
// ======================================================================

/**
 * Siparis tamamlandiginda referrer'a komisyon ver
 */
add_action('woocommerce_order_status_completed', 'gorilla_affiliate_give_credit', 15, 1);
add_action('woocommerce_order_status_processing', 'gorilla_affiliate_give_credit', 15, 1);
function gorilla_affiliate_give_credit($order_id) {
    if (get_option('gorilla_lr_enabled_affiliate', 'yes') !== 'yes') {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    // Idempotency guard
    if ($order->get_meta('_gorilla_affiliate_credited') === 'yes') return;
    $order->update_meta_data('_gorilla_affiliate_credited', 'yes');
    $order->save();

    $referrer_id = $order->get_meta('_gorilla_affiliate_referrer_id');
    if (!$referrer_id) {
        return;
    }

    // Minimum siparis kontrolu
    $min_order = floatval(get_option('gorilla_lr_affiliate_min_order', 0));
    $order_total = floatval($order->get_total());

    if ($min_order > 0 && $order_total < $min_order) {
        $order->add_order_note(sprintf(
            'Affiliate komisyonu verilmedi: Minimum siparis tutari (%s) karsilanmadi.',
            wc_price($min_order)
        ));
        return;
    }

    // Komisyon hesapla
    $rate = gorilla_affiliate_get_effective_rate($referrer_id);
    $commission = round($order_total * ($rate / 100), 2);

    // Seasonal bonus carpani uygula
    if (function_exists('gorilla_xp_get_bonus_multiplier')) {
        $multiplier = gorilla_xp_get_bonus_multiplier();
        if ($multiplier > 1) {
            $commission = round($commission * $multiplier, 2);
        }
    }

    if ($commission <= 0) {
        return;
    }

    // Credit ekle
    if (!function_exists('gorilla_credit_adjust')) {
        return;
    }

    $expiry_days = intval(get_option('gorilla_lr_credit_expiry_days', 0));
    $ref_code = $order->get_meta('_gorilla_affiliate_referrer_code');

    gorilla_credit_adjust(
        $referrer_id,
        $commission,
        'affiliate',
        sprintf('Affiliate siparis #%d komisyonu (%%%s)', $order_id, $rate),
        $order_id,
        $expiry_days
    );

    $order->update_meta_data('_gorilla_affiliate_commission', $commission);
    $order->add_order_note(sprintf(
        'Affiliate komisyonu verildi: %s (Referrer: %s, Oran: %%%s)',
        wc_price($commission),
        $ref_code,
        $rate
    ));
    $order->save();

    // Email gonder
    if (function_exists('gorilla_email_affiliate_earned')) {
        gorilla_email_affiliate_earned($referrer_id, $order_id, $commission);
    }

    // Fire action for XP (loyalty plugin listens)
    do_action('gorilla_affiliate_sale', $referrer_id, $order_id, $commission);
}


// ======================================================================
// SIPARIS IPTALI
// ======================================================================

add_action('woocommerce_order_status_cancelled', 'gorilla_affiliate_revoke_credit', 10, 1);
add_action('woocommerce_order_status_refunded', 'gorilla_affiliate_revoke_credit', 10, 1);
function gorilla_affiliate_revoke_credit($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    if ($order->get_meta('_gorilla_affiliate_credited') !== 'yes') {
        return;
    }

    if ($order->get_meta('_gorilla_affiliate_revoked') === 'yes') {
        return;
    }

    $referrer_id = $order->get_meta('_gorilla_affiliate_referrer_id');
    $commission = floatval($order->get_meta('_gorilla_affiliate_commission'));

    if (!$referrer_id || $commission <= 0) {
        return;
    }

    if (function_exists('gorilla_credit_adjust')) {
        gorilla_credit_adjust(
            $referrer_id,
            -$commission,
            'affiliate_revoke',
            sprintf('Siparis #%d iptali - affiliate komisyonu geri alindi', $order_id),
            $order_id
        );

        $order->update_meta_data('_gorilla_affiliate_revoked', 'yes');
        $order->add_order_note(sprintf(
            'Affiliate komisyonu geri alindi: %s',
            wc_price($commission)
        ));
        $order->save();
    }
}


// ======================================================================
// TESEKKUR SAYFASI
// ======================================================================

add_action('woocommerce_thankyou', 'gorilla_affiliate_thankyou_message', 5, 1);
function gorilla_affiliate_thankyou_message($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $referrer_id = $order->get_meta('_gorilla_affiliate_referrer_id');
    if (!$referrer_id) {
        return;
    }

    $referrer = get_userdata($referrer_id);
    if (!$referrer) {
        return;
    }

    $first_name = $referrer->first_name ?: $referrer->display_name;
    ?>
    <div style="background:#eff6ff; border:1px solid #bfdbfe; padding:16px 20px; border-radius:12px; margin:20px 0;">
        <p style="margin:0; color:#1e40af; font-size:14px;">
            <strong><?php echo esc_html($first_name); ?></strong> tarafindan onerildiniz.
            Siz de arkadaslariniza onerin, komisyon kazanin!
        </p>
    </div>
    <?php
}


// ======================================================================
// ISTATISTIKLER
// ======================================================================

function gorilla_affiliate_get_user_stats($user_id) {
    global $wpdb;

    $table = $wpdb->prefix . 'gorilla_affiliate_clicks';
    $code = gorilla_affiliate_get_code($user_id);

    $stats = array(
        'code'        => $code,
        'link'        => gorilla_affiliate_get_link($user_id),
        'clicks'      => 0,
        'conversions' => 0,
        'earnings'    => 0,
        'pending'     => 0,
    );

    if (!gorilla_lr_table_exists($table)) {
        return $stats;
    }

    $click_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT
            COUNT(*) as clicks,
            SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions
         FROM {$table} WHERE referrer_user_id = %d",
        $user_id
    ));

    if ($click_stats) {
        $stats['clicks'] = intval($click_stats->clicks);
        $stats['conversions'] = intval($click_stats->conversions);
    }

    $credit_table = $wpdb->prefix . 'gorilla_credit_log';
    if (gorilla_lr_table_exists($credit_table)) {
        $earnings = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$credit_table}
             WHERE user_id = %d AND type = 'affiliate' AND amount > 0",
            $user_id
        ));
        $stats['earnings'] = floatval($earnings);
    }

    return $stats;
}

function gorilla_affiliate_get_recent_earnings($user_id, $limit = 10) {
    global $wpdb;

    $table = $wpdb->prefix . 'gorilla_credit_log';

    if (!gorilla_lr_table_exists($table)) {
        return array();
    }

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table}
         WHERE user_id = %d AND type = 'affiliate' AND amount > 0
         ORDER BY created_at DESC LIMIT %d",
        $user_id, $limit
    ), ARRAY_A);
}


// ======================================================================
// ADMIN ISTATISTIKLERI
// ======================================================================

function gorilla_affiliate_get_admin_stats() {
    global $wpdb;

    $table = $wpdb->prefix . 'gorilla_affiliate_clicks';
    $credit_table = $wpdb->prefix . 'gorilla_credit_log';

    $stats = array(
        'total_clicks'      => 0,
        'total_conversions' => 0,
        'conversion_rate'   => 0,
        'total_commission'  => 0,
        'active_affiliates' => 0,
    );

    if (gorilla_lr_table_exists($table)) {
        $click_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as clicks,
                SUM(CASE WHEN converted = %d THEN 1 ELSE 0 END) as conversions,
                COUNT(DISTINCT referrer_user_id) as affiliates
             FROM {$table}",
            1
        ));

        if ($click_stats) {
            $stats['total_clicks'] = intval($click_stats->clicks);
            $stats['total_conversions'] = intval($click_stats->conversions);
            $stats['active_affiliates'] = intval($click_stats->affiliates);

            if ($stats['total_clicks'] > 0) {
                $stats['conversion_rate'] = round(($stats['total_conversions'] / $stats['total_clicks']) * 100, 1);
            }
        }
    }

    if (gorilla_lr_table_exists($credit_table)) {
        $commission = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$credit_table} WHERE type = %s AND amount > 0",
            'affiliate'
        ));
        $stats['total_commission'] = floatval($commission);
    }

    return $stats;
}

function gorilla_affiliate_get_top_users($limit = 5) {
    global $wpdb;

    $credit_table = $wpdb->prefix . 'gorilla_credit_log';

    if (!gorilla_lr_table_exists($credit_table)) {
        return array();
    }

    return $wpdb->get_results($wpdb->prepare(
        "SELECT
            cl.user_id,
            u.display_name,
            u.user_email,
            SUM(cl.amount) as total_earnings,
            COUNT(*) as total_orders
         FROM {$credit_table} cl
         LEFT JOIN {$wpdb->users} u ON cl.user_id = u.ID
         WHERE cl.type = 'affiliate' AND cl.amount > 0
         GROUP BY cl.user_id
         ORDER BY total_earnings DESC
         LIMIT %d",
        $limit
    ));
}

function gorilla_affiliate_get_recent_orders($limit = 10) {
    global $wpdb;

    $credit_table = $wpdb->prefix . 'gorilla_credit_log';

    if (!gorilla_lr_table_exists($credit_table)) {
        return array();
    }

    return $wpdb->get_results($wpdb->prepare(
        "SELECT
            cl.*,
            u.display_name as referrer_name
         FROM {$credit_table} cl
         LEFT JOIN {$wpdb->users} u ON cl.user_id = u.ID
         WHERE cl.type = 'affiliate' AND cl.amount > 0
         ORDER BY cl.created_at DESC
         LIMIT %d",
        $limit
    ));
}


// -- Kademeli Affiliate Komisyon Orani --
function gorilla_affiliate_get_effective_rate($user_id) {
    if (get_option('gorilla_lr_tiered_affiliate_enabled', 'no') !== 'yes') {
        return floatval(get_option('gorilla_lr_affiliate_rate', 10));
    }

    $stats = gorilla_affiliate_get_user_stats($user_id);
    $total_sales = intval($stats['conversions'] ?? 0);

    $tiers = get_option('gorilla_lr_affiliate_tiers', array());
    if (empty($tiers) || !is_array($tiers)) {
        return floatval(get_option('gorilla_lr_affiliate_rate', 10));
    }

    $rate = floatval(get_option('gorilla_lr_affiliate_rate', 10));
    foreach ($tiers as $tier) {
        if ($total_sales >= intval($tier['min_sales'] ?? 0)) {
            $rate = floatval($tier['rate'] ?? $rate);
        }
    }

    return $rate;
}

function gorilla_affiliate_get_current_tier($user_id) {
    $stats = gorilla_affiliate_get_user_stats($user_id);
    $total_sales = intval($stats['conversions'] ?? 0);
    $tiers = get_option('gorilla_lr_affiliate_tiers', array());

    if (empty($tiers) || !is_array($tiers)) {
        $rate = floatval(get_option('gorilla_lr_affiliate_rate', 10));
        return array('rate' => $rate, 'total_sales' => $total_sales, 'next_rate' => null, 'sales_to_next' => 0, 'tier_min' => 0, 'next_min' => 0);
    }

    $current_rate = floatval(get_option('gorilla_lr_affiliate_rate', 10));
    $next_rate = null;
    $sales_to_next = 0;
    $tier_min = 0;
    $next_min = 0;

    foreach ($tiers as $i => $tier) {
        if ($total_sales >= intval($tier['min_sales'] ?? 0)) {
            $current_rate = floatval($tier['rate'] ?? $current_rate);
            $tier_min = intval($tier['min_sales'] ?? 0);
            if (isset($tiers[$i + 1])) {
                $next_rate = floatval($tiers[$i + 1]['rate']);
                $next_min = intval($tiers[$i + 1]['min_sales']);
                $sales_to_next = $next_min - $total_sales;
            } else {
                $next_rate = null;
                $next_min = 0;
                $sales_to_next = 0;
            }
        }
    }

    return array(
        'rate'          => $current_rate,
        'total_sales'   => $total_sales,
        'next_rate'     => $next_rate,
        'sales_to_next' => max(0, $sales_to_next),
        'tier_min'      => $tier_min,
        'next_min'      => $next_min,
    );
}


// -- Tekrar Eden Affiliate Komisyonu --
add_action('woocommerce_order_status_completed', 'gorilla_affiliate_give_recurring_credit', 16, 1);
add_action('woocommerce_order_status_processing', 'gorilla_affiliate_give_recurring_credit', 16, 1);
function gorilla_affiliate_give_recurring_credit($order_id) {
    if (get_option('gorilla_lr_recurring_affiliate_enabled', 'no') !== 'yes') return;
    if (get_option('gorilla_lr_enabled_affiliate', 'yes') !== 'yes') return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    // Bu sipariste zaten dogrudan affiliate varsa atla
    if ($order->get_meta('_gorilla_affiliate_referrer_id')) return;

    // Idempotency guard
    if ($order->get_meta('_gorilla_recurring_affiliate_credited') === 'yes') return;
    $order->update_meta_data('_gorilla_recurring_affiliate_credited', 'yes');
    $order->save();

    $customer_id = $order->get_customer_id();
    if (!$customer_id) return;

    $original_referrer = intval(get_user_meta($customer_id, '_gorilla_referred_by', true));
    if (!$original_referrer) return;

    if (!get_userdata($original_referrer)) return;

    $recurring_months = intval(get_option('gorilla_lr_recurring_affiliate_months', 6));

    $first_orders = wc_get_orders(array(
        'customer_id' => $customer_id,
        'status'      => array('completed', 'processing'),
        'limit'       => 1,
        'orderby'     => 'date',
        'order'       => 'ASC',
        'return'      => 'objects',
    ));

    if (empty($first_orders)) return;

    $first_date = $first_orders[0]->get_date_created();
    if (!$first_date) return;

    $cutoff = clone $first_date;
    $cutoff->modify("+{$recurring_months} months");

    $now = new \DateTime(current_time('mysql'));
    if ($now > $cutoff) return;

    $max_orders = intval(get_option('gorilla_lr_recurring_affiliate_max_orders', 0));
    if ($max_orders > 0) {
        $orders_result = wc_get_orders(array(
            'customer_id' => $customer_id,
            'status'      => array('completed', 'processing'),
            'return'      => 'ids',
            'limit'       => $max_orders + 1,
        ));
        $order_count = is_array($orders_result) ? count($orders_result) : 0;
        if ($order_count > $max_orders) return;
    }

    $rate = floatval(get_option('gorilla_lr_recurring_affiliate_rate', 5));
    $order_total = floatval($order->get_total());
    $commission = round($order_total * ($rate / 100), 2);

    if ($commission <= 0) return;

    if (function_exists('gorilla_credit_adjust')) {
        gorilla_credit_adjust(
            $original_referrer,
            $commission,
            'affiliate_recurring',
            sprintf('Tekrar eden affiliate komisyonu - Siparis #%d (%%%s)', $order_id, $rate),
            $order_id,
            0
        );
    }

    $order->update_meta_data('_gorilla_recurring_affiliate_commission', $commission);
    $order->update_meta_data('_gorilla_recurring_affiliate_referrer_id', $original_referrer);
    $order->save();

    // Fire action for XP (loyalty plugin listens)
    do_action('gorilla_affiliate_sale', $original_referrer, $order_id, $commission);
}

// ======================================================================
// AFFILIATE FRAUD DETECTION
// ======================================================================

add_action('gorilla_lr_daily_tier_check', 'gorilla_affiliate_fraud_check');

function gorilla_affiliate_fraud_check() {
    if (get_option('gorilla_lr_fraud_detection_enabled', 'no') !== 'yes') return;

    $last_run = get_option('gorilla_lr_fraud_last_run', '');
    $now = current_time('Y-W');
    if ($last_run === $now) return;
    update_option('gorilla_lr_fraud_last_run', $now);

    global $wpdb;
    $table = $wpdb->prefix . 'gorilla_affiliate_clicks';

    if (!gorilla_lr_table_exists($table)) return;

    $lookback_days = 30;
    $since = gmdate('Y-m-d', strtotime("-{$lookback_days} days"));

    $affiliates = $wpdb->get_results($wpdb->prepare(
        "SELECT referrer_user_id,
                COUNT(*) as total_clicks,
                COUNT(DISTINCT visitor_ip) as unique_ips,
                SUM(converted) as conversions
         FROM {$table}
         WHERE clicked_at >= %s
         GROUP BY referrer_user_id
         HAVING total_clicks >= 10",
        $since
    ));

    if (empty($affiliates)) return;

    $flagged = array();

    foreach ($affiliates as $aff) {
        $user_id  = intval($aff->referrer_user_id);
        $total    = intval($aff->total_clicks);
        $unique   = intval($aff->unique_ips);
        $convs    = intval($aff->conversions);
        $reasons  = array();
        $score    = 0;

        // Check 1: IP concentration
        $top_ip_count = $wpdb->get_var($wpdb->prepare(
            "SELECT cnt FROM (
                SELECT COUNT(*) AS cnt
                FROM {$table}
                WHERE referrer_user_id = %d AND clicked_at >= %s
                GROUP BY visitor_ip
                ORDER BY cnt DESC
                LIMIT 1
            ) AS top_ip",
            $user_id, $since
        ));
        $top_ip_pct = $total > 0 ? ($top_ip_count / $total) * 100 : 0;

        if ($top_ip_pct > 60 && $total >= 15) {
            $reasons[] = sprintf('IP yogunlasma: En populer IP tiklarin %%%d kadarini olusturuyor', round($top_ip_pct));
            $score += 30;
        }

        // Check 2: Very low IP diversity
        $ip_ratio = $unique > 0 ? $total / $unique : $total;
        if ($ip_ratio > 3 && $total >= 20) {
            $reasons[] = sprintf('Dusuk IP cesitliligi: %d tikla sadece %d benzersiz IP', $total, $unique);
            $score += 25;
        }

        // Check 3: Rapid burst clicks
        $burst = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(cnt) FROM (
                SELECT COUNT(*) as cnt
                FROM {$table}
                WHERE referrer_user_id = %d AND clicked_at >= %s
                GROUP BY DATE(clicked_at), HOUR(clicked_at)
            ) sub",
            $user_id, $since
        ));
        if ($burst && intval($burst) > 10) {
            $reasons[] = sprintf('Hizli tiklamalar: Tek saatte %d tiklama tespit edildi', intval($burst));
            $score += 25;
        }

        // Check 4: Zero conversions with high volume
        if ($convs === 0 && $total >= 30) {
            $reasons[] = sprintf('%d tiklama, sifir donusum - sahte trafik suplesi', $total);
            $score += 20;
        }

        if ($score >= 30) {
            $risk_level = 'low';
            if ($score >= 70) $risk_level = 'high';
            elseif ($score >= 50) $risk_level = 'medium';

            update_user_meta($user_id, '_gorilla_affiliate_fraud_score', $score);
            update_user_meta($user_id, '_gorilla_affiliate_fraud_level', $risk_level);
            update_user_meta($user_id, '_gorilla_affiliate_fraud_reasons', $reasons);
            update_user_meta($user_id, '_gorilla_affiliate_fraud_date', current_time('mysql'));

            $flagged[] = array(
                'user_id' => $user_id,
                'score'   => $score,
                'level'   => $risk_level,
                'reasons' => $reasons,
            );
        } else {
            delete_user_meta($user_id, '_gorilla_affiliate_fraud_score');
            delete_user_meta($user_id, '_gorilla_affiliate_fraud_level');
            delete_user_meta($user_id, '_gorilla_affiliate_fraud_reasons');
            delete_user_meta($user_id, '_gorilla_affiliate_fraud_date');
        }
    }

    if (!empty($flagged)) {
        $high_risk = array_filter($flagged, function($f) { return $f['level'] === 'high' || $f['level'] === 'medium'; });
        if (!empty($high_risk)) {
            gorilla_affiliate_fraud_admin_notice($high_risk);
        }
    }
}

function gorilla_affiliate_fraud_admin_notice($flagged) {
    $admin_email = get_option('admin_email');
    $subject     = sprintf('[%s] Affiliate Dolandiricilik Uyarisi - %d supheli hesap', get_bloginfo('name'), count($flagged));

    $body = "Merhaba Admin,\n\n";
    $body .= "Otomatik dolandiricilik taramasi asagidaki supheli affiliate hesaplari tespit etti:\n\n";

    foreach ($flagged as $f) {
        $user = get_userdata($f['user_id']);
        $name = $user ? $user->display_name . ' (' . $user->user_email . ')' : 'ID: ' . $f['user_id'];
        $body .= sprintf("- %s [Risk: %s, Skor: %d]\n", $name, strtoupper($f['level']), $f['score']);
        foreach ($f['reasons'] as $reason) {
            $body .= "    * " . $reason . "\n";
        }
        $body .= "\n";
    }

    $body .= "Bu hesaplari WordPress admin panelinden inceleyebilirsiniz.\n";

    wp_mail($admin_email, $subject, $body);
}
