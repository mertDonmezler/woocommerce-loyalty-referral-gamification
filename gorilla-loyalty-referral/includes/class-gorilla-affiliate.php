<?php
/**
 * Gorilla LR - Affiliate Link Sistemi
 * Trendyol tarzı referans link takibi ve otomatik komisyon
 *
 * @author Mert Dönmezler
 * @copyright 2025-2026 Mert Dönmezler
 * @version 3.0.0
 */

if (!defined('ABSPATH')) exit;

// ══════════════════════════════════════════════════════════
// REFERANS KODU YÖNETİMİ
// ══════════════════════════════════════════════════════════

/**
 * Kullanıcının affiliate kodunu al veya oluştur
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
 * Benzersiz affiliate kodu oluştur
 * Format: G + base36(user_id) + random(2)
 * Örnek: G5A7X3
 */
function gorilla_affiliate_generate_code($user_id, $attempt = 0) {
    // Sonsuz recursion onleme: 10 denemeden sonra uzun rastgele kod uret
    if ($attempt >= 10) {
        return 'G' . strtoupper(wp_generate_password(8, false));
    }

    $prefix = 'G';
    $user_part = strtoupper(base_convert($user_id, 10, 36));

    // Random suffix (collision önleme)
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Karışıklık yaratabilecek karakterler çıkarıldı (0,O,1,I)
    $suffix = '';
    for ($i = 0; $i < 2; $i++) {
        $suffix .= $chars[wp_rand(0, strlen($chars) - 1)];
    }

    $code = $prefix . $user_part . $suffix;

    // Benzersizlik kontrolü
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = '_gorilla_affiliate_code' AND meta_value = %s",
        $code
    ));

    // Eğer varsa, tekrar üret
    if ($exists > 0) {
        return gorilla_affiliate_generate_code($user_id, $attempt + 1);
    }

    return $code;
}

/**
 * Affiliate kodundan kullanıcı ID'sini bul
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
 * Kullanıcının affiliate linkini oluştur
 */
function gorilla_affiliate_get_link($user_id) {
    $code = gorilla_affiliate_get_code($user_id);
    $param = get_option('gorilla_lr_affiliate_url_param', 'ref');

    return add_query_arg($param, $code, home_url('/'));
}


// ══════════════════════════════════════════════════════════
// COOKIE TRACKING
// ══════════════════════════════════════════════════════════

/**
 * URL'den referans kodunu yakala ve cookie bırak
 */
add_action('init', 'gorilla_affiliate_capture_referral', 1);
function gorilla_affiliate_capture_referral() {
    // Affiliate sistemi aktif mi?
    if (get_option('gorilla_lr_enabled_affiliate', 'yes') !== 'yes') {
        return;
    }

    // URL parametresini al
    $param = get_option('gorilla_lr_affiliate_url_param', 'ref');
    if (!isset($_GET[$param])) {
        return;
    }

    $ref_code = sanitize_text_field($_GET[$param]);

    // Kod validasyonu
    if (empty($ref_code) || !preg_match('/^[A-Z0-9]{4,20}$/i', $ref_code)) {
        return;
    }

    $ref_code = strtoupper($ref_code);

    // Referrer'ı bul
    $referrer_id = gorilla_affiliate_get_user_by_code($ref_code);
    if (!$referrer_id) {
        return;
    }

    // Self-referral kontrolü
    if (is_user_logged_in() && get_current_user_id() == $referrer_id) {
        if (get_option('gorilla_lr_affiliate_allow_self', 'no') !== 'yes') {
            return;
        }
    }

    // Cookie bırak
    $cookie_days = intval(get_option('gorilla_lr_affiliate_cookie_days', 30));
    $cookie_name = 'gorilla_ref';

    // Header gönderilmeden önce cookie set etmeliyiz
    if (!headers_sent()) {
        setcookie($cookie_name, $ref_code, array(
            'expires'  => time() + ($cookie_days * DAY_IN_SECONDS),
            'path'     => COOKIEPATH,
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly'  => true,
            'samesite' => 'Lax',
        ));

        // Hemen kullanılabilmesi için
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
 * Tıklamayı logla
 */
function gorilla_affiliate_log_click($referrer_id, $ref_code) {
    global $wpdb;

    $table = $wpdb->prefix . 'gorilla_affiliate_clicks';

    // Tablo var mı kontrol
    if (!gorilla_lr_table_exists($table)) {
        return;
    }

    // Aynı IP'den bugün tıklama var mı? (spam önleme)
    $visitor_ip = gorilla_affiliate_get_visitor_ip();
    $today = gmdate('Y-m-d');

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE referrer_code = %s AND visitor_ip = %s AND DATE(clicked_at) = %s",
        $ref_code, $visitor_ip, $today
    ));

    if ($exists > 0) {
        return; // Bugün zaten tıklamış
    }

    // Yeni click kaydet
    $wpdb->insert($table, array(
        'referrer_user_id' => $referrer_id,
        'referrer_code'    => $ref_code,
        'visitor_ip'       => $visitor_ip,
        'clicked_at'       => current_time('mysql'),
        'converted'        => 0,
    ), array('%d', '%s', '%s', '%s', '%d'));
}

/**
 * Ziyaretçi IP'sini al (proxy arkasında da çalışır)
 */
function gorilla_affiliate_get_visitor_ip() {
    // Guvenilir proxy header'i icin filter kullan (varsayilan: sadece REMOTE_ADDR)
    // Cloudflare kullanicilari icin:
    // add_filter('gorilla_affiliate_trusted_ip_header', function() { return 'HTTP_CF_CONNECTING_IP'; });
    $trusted_header = apply_filters('gorilla_affiliate_trusted_ip_header', '');

    $ip = '';

    if ($trusted_header && !empty($_SERVER[$trusted_header])) {
        $ips = explode(',', sanitize_text_field($_SERVER[$trusted_header]));
        $ip = trim($ips[0]);
    }

    // Trusted header'dan gecerli IP alinamadiysa REMOTE_ADDR kullan
    if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    }

    // IP validasyonu
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '0.0.0.0';
    }

    return $ip;
}


// ══════════════════════════════════════════════════════════
// CHECKOUT ENTEGRASYONU
// ══════════════════════════════════════════════════════════

/**
 * Sipariş oluşturulduğunda referrer bilgisini kaydet
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

    // Self-referral kontrolü
    $customer_id = $order->get_customer_id();
    if ($customer_id && $customer_id == $referrer_id) {
        if (get_option('gorilla_lr_affiliate_allow_self', 'no') !== 'yes') {
            return;
        }
    }

    // Sadece ilk sipariş kontrolü
    if (get_option('gorilla_lr_affiliate_first_only', 'no') === 'yes' && $customer_id) {
        $previous = wc_get_orders(array(
            'customer_id' => $customer_id,
            'status'      => array('completed', 'processing'),
            'limit'       => 1,
            'exclude'     => array($order->get_id()),
        ));

        if (!empty($previous)) {
            return; // Daha önce sipariş vermiş
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

    // Click'i converted olarak işaretle
    gorilla_affiliate_mark_converted($ref_code, $order->get_id());

    // Sipariş notuna ekle
    $referrer = get_userdata($referrer_id);
    if ($referrer) {
        $order->add_order_note(sprintf(
            '🔗 Affiliate: %s (%s) tarafından getirildi',
            esc_html($referrer->display_name),
            esc_html($ref_code)
        ));
    }
}

/**
 * Click'i converted olarak işaretle
 */
function gorilla_affiliate_mark_converted($ref_code, $order_id) {
    global $wpdb;

    $table = $wpdb->prefix . 'gorilla_affiliate_clicks';

    if (!gorilla_lr_table_exists($table)) {
        return;
    }

    // En son unconverted click'i bul ve güncelle
    $wpdb->query($wpdb->prepare(
        "UPDATE {$table} SET converted = 1, order_id = %d, converted_at = %s
         WHERE referrer_code = %s AND converted = 0
         ORDER BY clicked_at DESC LIMIT 1",
        $order_id, current_time('mysql'), $ref_code
    ));
}


// ══════════════════════════════════════════════════════════
// OTOMATİK KOMİSYON
// ══════════════════════════════════════════════════════════

/**
 * Sipariş tamamlandığında referrer'a komisyon ver
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

    // Atomic: esanli islem korumasili
    global $wpdb;
    $already = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_gorilla_affiliate_credited' AND meta_value = 'yes'",
        $order_id
    ));
    if ($already) return;
    // Atomik olarak isaretlele
    $marked = $wpdb->query($wpdb->prepare(
        "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) SELECT %d, '_gorilla_affiliate_credited', 'yes' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_gorilla_affiliate_credited' AND meta_value = 'yes')",
        $order_id, $order_id
    ));
    if (!$marked) return;

    // Referrer bilgisi var mı?
    $referrer_id = $order->get_meta('_gorilla_affiliate_referrer_id');
    if (!$referrer_id) {
        return;
    }

    // Minimum sipariş kontrolü
    $min_order = floatval(get_option('gorilla_lr_affiliate_min_order', 0));
    $order_total = floatval($order->get_total());

    if ($min_order > 0 && $order_total < $min_order) {
        $order->add_order_note(sprintf(
            '🔗 Affiliate komisyonu verilmedi: Minimum sipariş tutarı (%s) karşılanmadı.',
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
        sprintf('Affiliate sipariş #%d komisyonu (%%%s)', $order_id, $rate),
        $order_id,
        $expiry_days
    );

    // Komisyon miktarini kaydet (credited zaten atomik olarak isaretlendi)
    $order->update_meta_data('_gorilla_affiliate_commission', $commission);
    $order->add_order_note(sprintf(
        '🔗 Affiliate komisyonu verildi: %s (Referrer: %s, Oran: %%%s)',
        wc_price($commission),
        $ref_code,
        $rate
    ));
    $order->save();

    // Email gönder
    if (function_exists('gorilla_email_affiliate_earned')) {
        gorilla_email_affiliate_earned($referrer_id, $order_id, $commission);
    }

    // XP ver
    if (function_exists('gorilla_xp_on_affiliate_sale')) {
        gorilla_xp_on_affiliate_sale($referrer_id, $order_id);
    }
}


// ══════════════════════════════════════════════════════════
// SİPARİŞ İPTALİ
// ══════════════════════════════════════════════════════════

/**
 * Sipariş iptal/iade durumunda affiliate komisyonunu geri al
 */
add_action('woocommerce_order_status_cancelled', 'gorilla_affiliate_revoke_credit', 10, 1);
add_action('woocommerce_order_status_refunded', 'gorilla_affiliate_revoke_credit', 10, 1);
function gorilla_affiliate_revoke_credit($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    // Komisyon verilmiş mi?
    if ($order->get_meta('_gorilla_affiliate_credited') !== 'yes') {
        return;
    }

    // Zaten geri alınmış mı?
    if ($order->get_meta('_gorilla_affiliate_revoked') === 'yes') {
        return;
    }

    $referrer_id = $order->get_meta('_gorilla_affiliate_referrer_id');
    $commission = floatval($order->get_meta('_gorilla_affiliate_commission'));

    if (!$referrer_id || $commission <= 0) {
        return;
    }

    // Komisyonu geri al
    if (function_exists('gorilla_credit_adjust')) {
        gorilla_credit_adjust(
            $referrer_id,
            -$commission,
            'affiliate_revoke',
            sprintf('Sipariş #%d iptali - affiliate komisyonu geri alındı', $order_id),
            $order_id
        );

        $order->update_meta_data('_gorilla_affiliate_revoked', 'yes');
        $order->add_order_note(sprintf(
            '🔗 Affiliate komisyonu geri alındı: %s',
            wc_price($commission)
        ));
        $order->save();
    }
}


// ══════════════════════════════════════════════════════════
// TEŞEKKÜR SAYFASI
// ══════════════════════════════════════════════════════════

/**
 * Thank you sayfasında referrer bilgisi göster
 */
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
            🔗 <strong><?php echo esc_html($first_name); ?></strong> tarafından önerildiniz.
            Siz de arkadaşlarınıza önerin, komisyon kazanın!
        </p>
    </div>
    <?php
}


// ══════════════════════════════════════════════════════════
// İSTATİSTİKLER
// ══════════════════════════════════════════════════════════

/**
 * Kullanıcının affiliate istatistiklerini al
 */
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

    // Tablo kontrolü
    if (!gorilla_lr_table_exists($table)) {
        return $stats;
    }

    // Tıklama ve conversion sayısı
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

    // Toplam kazanç (credit log'dan)
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

/**
 * Kullanıcının son affiliate kazançlarını al
 */
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


// ══════════════════════════════════════════════════════════
// ADMIN İSTATİSTİKLERİ
// ══════════════════════════════════════════════════════════

/**
 * Genel affiliate istatistikleri (admin için)
 */
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

    // Click tablosu varsa
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

    // Toplam komisyon
    if (gorilla_lr_table_exists($credit_table)) {
        $commission = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$credit_table} WHERE type = %s AND amount > 0",
            'affiliate'
        ));
        $stats['total_commission'] = floatval($commission);
    }

    return $stats;
}

/**
 * Top affiliate kullanıcıları (admin için)
 */
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

/**
 * Son affiliate siparişleri (admin için)
 */
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


// ── Kademeli Affiliate Komisyon Orani ────────────────────
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
        return array('rate' => $rate, 'total_sales' => $total_sales, 'next_rate' => null, 'sales_to_next' => 0);
    }

    $current_rate = floatval(get_option('gorilla_lr_affiliate_rate', 10));
    $next_rate = null;
    $sales_to_next = 0;

    foreach ($tiers as $i => $tier) {
        if ($total_sales >= intval($tier['min_sales'] ?? 0)) {
            $current_rate = floatval($tier['rate'] ?? $current_rate);
            // Sonraki tier var mi?
            if (isset($tiers[$i + 1])) {
                $next_rate = floatval($tiers[$i + 1]['rate']);
                $sales_to_next = intval($tiers[$i + 1]['min_sales']) - $total_sales;
            } else {
                $next_rate = null;
                $sales_to_next = 0;
            }
        }
    }

    return array(
        'rate'          => $current_rate,
        'total_sales'   => $total_sales,
        'next_rate'     => $next_rate,
        'sales_to_next' => max(0, $sales_to_next),
    );
}


// ── Tekrar Eden Affiliate Komisyonu ──────────────────────
add_action('woocommerce_order_status_completed', 'gorilla_affiliate_give_recurring_credit', 16, 1);
add_action('woocommerce_order_status_processing', 'gorilla_affiliate_give_recurring_credit', 16, 1);
function gorilla_affiliate_give_recurring_credit($order_id) {
    if (get_option('gorilla_lr_recurring_affiliate_enabled', 'no') !== 'yes') return;
    if (get_option('gorilla_lr_enabled_affiliate') !== 'yes') return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    // Bu sipariste zaten dogrudan affiliate varsa atla (normal komisyon verilecek)
    if ($order->get_meta('_gorilla_affiliate_referrer_id')) return;

    // Atomic: esanli islem korumasili (recurring)
    global $wpdb;
    $already_recurring = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_gorilla_recurring_affiliate_credited' AND meta_value = 'yes'",
        $order_id
    ));
    if ($already_recurring) return;
    $marked_recurring = $wpdb->query($wpdb->prepare(
        "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) SELECT %d, '_gorilla_recurring_affiliate_credited', 'yes' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_gorilla_recurring_affiliate_credited' AND meta_value = 'yes')",
        $order_id, $order_id
    ));
    if (!$marked_recurring) return;

    $customer_id = $order->get_customer_id();
    if (!$customer_id) return;

    // Musteri daha once bir affiliate tarafindan yonlendirildi mi?
    $original_referrer = intval(get_user_meta($customer_id, '_gorilla_referred_by', true));
    if (!$original_referrer) return;

    // Referrer hala var mi?
    if (!get_userdata($original_referrer)) return;

    // Tekrar eden komisyon suresi icinde mi?
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

    // Max siparis limiti
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

    // Komisyon hesapla
    $rate = floatval(get_option('gorilla_lr_recurring_affiliate_rate', 5));
    $order_total = floatval($order->get_total());
    $commission = round($order_total * ($rate / 100), 2);

    if ($commission <= 0) return;

    // Credit ver
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

    // credited zaten atomik olarak isaretlendi
    $order->update_meta_data('_gorilla_recurring_affiliate_commission', $commission);
    $order->update_meta_data('_gorilla_recurring_affiliate_referrer_id', $original_referrer);
    $order->save();

    // XP ver
    if (function_exists('gorilla_xp_on_affiliate_sale')) {
        gorilla_xp_on_affiliate_sale($original_referrer, $order_id);
    }
}
