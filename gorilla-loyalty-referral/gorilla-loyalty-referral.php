<?php
/**
 * Plugin Name: Gorilla Loyalty & Referral Program
 * Plugin URI: https://www.gorillacustomcards.com
 * Description: Gorilla Custom Cards için kapsamlı sadakat programı (6 kademe, otomatik indirim) ve video referans sistemi (store credit). Admin panelden tam kontrol.
 * Version: 3.0.1
 * Author: Mert Dönmezler
 * Author URI: https://www.gorillacustomcards.com
 * Text Domain: gorilla-lr
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.5
 * License: GPLv2 or later
 *
 * @package Gorilla_Loyalty_Referral
 * @author Mert Dönmezler
 * @copyright 2025-2026 Mert Dönmezler
 */

if (!defined('ABSPATH')) exit;

// ── Sabitler ──────────────────────────────────────────────
define('GORILLA_LR_VERSION', '3.0.1');
define('GORILLA_LR_FILE', __FILE__);
define('GORILLA_LR_PATH', plugin_dir_path(__FILE__));
define('GORILLA_LR_URL', plugin_dir_url(__FILE__));
define('GORILLA_LR_BASENAME', plugin_basename(__FILE__));

// ── Tablo Varlik Kontrolu (Static Cache) ─────────────────
function gorilla_lr_table_exists($table_name) {
    static $cache = array();
    if (isset($cache[$table_name])) {
        return $cache[$table_name];
    }
    global $wpdb;
    $cache[$table_name] = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name);
    return $cache[$table_name];
}

// ── Dil Dosyaları Yükleme ────────────────────────────────
add_action('init', function() {
    load_plugin_textdomain('gorilla-lr', false, dirname(GORILLA_LR_BASENAME) . '/languages');
}, 1);

// ── WooCommerce HPOS Uyumluluğu ──────────────────────────
add_action('before_woocommerce_init', function() {
    try {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    } catch (\Throwable $e) {
        // Sessizce geç
    }
});

// ── WooCommerce Kontrolü ─────────────────────────────────
function gorilla_lr_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . esc_html__('Gorilla Loyalty & Referral:', 'gorilla-lr') . '</strong> ';
            /* translators: %s: WooCommerce link */
            echo sprintf(
                esc_html__('Bu eklenti %s gerektirir. Lütfen kurup etkinleştirin.', 'gorilla-lr'),
                '<strong>WooCommerce</strong>'
            );
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

// ── Dosya Yükleme ────────────────────────────────────────
function gorilla_lr_load_modules() {
    if (!gorilla_lr_check_woocommerce()) return;
    
    $modules = array(
        'includes/class-gorilla-settings.php',
        'includes/class-gorilla-loyalty.php',
        'includes/class-gorilla-store-credit.php',
        'includes/class-gorilla-referral.php',
        'includes/class-gorilla-admin.php',
        'includes/class-gorilla-frontend.php',
        'includes/class-gorilla-emails.php',
        'includes/class-gorilla-rest-api.php',
        'includes/class-gorilla-affiliate.php',
        'includes/class-gorilla-xp.php',
        'includes/class-gorilla-gdpr.php',
    );
    
    foreach ($modules as $module) {
        $file = GORILLA_LR_PATH . $module;
        if (file_exists($file)) {
            try {
                require_once $file;
            } catch (\Throwable $e) {
                error_log('Gorilla LR: Module load error in ' . $module . ' - ' . $e->getMessage());
                // Admin'de uyarı göster
                add_action('admin_notices', function() use ($module, $e) {
                    echo '<div class="notice notice-error"><p>';
                    echo '<strong>Gorilla LR Hata:</strong> ' . esc_html($module) . ' yüklenemedi: ' . esc_html($e->getMessage());
                    echo '</p></div>';
                });
            }
        }
    }
}
add_action('plugins_loaded', 'gorilla_lr_load_modules', 20);

// ── Aktivasyon ───────────────────────────────────────────
register_activation_hook(__FILE__, function() {
    // Varsayılan ayarları oluştur
    $defaults = array(
        'gorilla_lr_tiers' => array(
            'bronze'   => array('label' => 'Bronz',   'min' => 1500,  'discount' => 5,  'color' => '#CD7F32', 'emoji' => '🥉', 'installment' => 0),
            'silver'   => array('label' => 'Gümüş',   'min' => 3000,  'discount' => 10, 'color' => '#C0C0C0', 'emoji' => '🥈', 'installment' => 0),
            'gold'     => array('label' => 'Altın',    'min' => 6000,  'discount' => 15, 'color' => '#FFD700', 'emoji' => '🥇', 'installment' => 0),
            'platinum' => array('label' => 'Platin',   'min' => 10000, 'discount' => 20, 'color' => '#E5E4E2', 'emoji' => '💎', 'installment' => 0),
            'diamond'  => array('label' => 'Elmas',    'min' => 20000, 'discount' => 25, 'color' => '#B9F2FF', 'emoji' => '👑', 'installment' => 3),
        ),
        'gorilla_lr_period_months'    => 6,
        'gorilla_lr_referral_rate'    => 35,
        'gorilla_lr_referral_auto'    => 'manual',
        'gorilla_lr_credit_min_order' => 0,
        'gorilla_lr_enabled_loyalty'  => 'yes',
        'gorilla_lr_enabled_referral' => 'yes',
        // Affiliate Link Ayarları
        'gorilla_lr_enabled_affiliate'      => 'yes',
        'gorilla_lr_affiliate_rate'         => 10,
        'gorilla_lr_affiliate_cookie_days'  => 30,
        'gorilla_lr_affiliate_min_order'    => 0,
        'gorilla_lr_affiliate_first_only'   => 'no',
        'gorilla_lr_affiliate_allow_self'   => 'no',
        // XP & Level Sistemi
        'gorilla_lr_enabled_xp'         => 'yes',
        'gorilla_lr_xp_per_order_rate'  => 10,
        'gorilla_lr_xp_review'          => 25,
        'gorilla_lr_xp_referral'        => 50,
        'gorilla_lr_xp_affiliate'       => 30,
        'gorilla_lr_xp_first_order'     => 100,
        'gorilla_lr_xp_register'        => 10,
        'gorilla_lr_xp_profile'         => 20,
        // Seasonal Bonus
        'gorilla_lr_bonus_enabled'      => 'no',
        'gorilla_lr_bonus_multiplier'   => 1.5,
        'gorilla_lr_bonus_start'        => '',
        'gorilla_lr_bonus_end'          => '',
        'gorilla_lr_bonus_label'        => '',
        // Credit Expiry Warning
        'gorilla_lr_credit_expiry_warn_days' => 7,
        'gorilla_lr_credit_expiry_days'      => 0,
        // Birthday Rewards
        'gorilla_lr_birthday_enabled'     => 'no',
        'gorilla_lr_birthday_xp'          => 50,
        'gorilla_lr_birthday_credit'      => 10,
        // Daily Login Streaks
        'gorilla_lr_streak_enabled'       => 'no',
        'gorilla_lr_streak_daily_xp'      => 5,
        'gorilla_lr_streak_7day_bonus'    => 50,
        'gorilla_lr_streak_30day_bonus'   => 200,
        // Badges
        'gorilla_lr_badges_enabled'       => 'no',
        // Spin Wheel
        'gorilla_lr_spin_enabled'         => 'no',
        'gorilla_lr_spin_prizes' => array(
            array('label' => '10 XP',          'type' => 'xp',           'value' => 10,  'weight' => 30),
            array('label' => '25 XP',          'type' => 'xp',           'value' => 25,  'weight' => 20),
            array('label' => '50 XP',          'type' => 'xp',           'value' => 50,  'weight' => 10),
            array('label' => '5 TL Credit',    'type' => 'credit',       'value' => 5,   'weight' => 15),
            array('label' => '10 TL Credit',   'type' => 'credit',       'value' => 10,  'weight' => 8),
            array('label' => 'Ucretsiz Kargo', 'type' => 'free_shipping','value' => 0,   'weight' => 7),
            array('label' => '%10 Indirim',    'type' => 'coupon',       'value' => 10,  'weight' => 5),
            array('label' => 'Tekrar Dene',    'type' => 'nothing',      'value' => 0,   'weight' => 5),
        ),
        // Dual-Sided Referral
        'gorilla_lr_dual_referral_enabled'     => 'no',
        'gorilla_lr_dual_referral_type'        => 'percent',
        'gorilla_lr_dual_referral_amount'      => 10,
        'gorilla_lr_dual_referral_min_order'   => 0,
        'gorilla_lr_dual_referral_expiry_days' => 30,
        // Tiered Affiliate Commission
        'gorilla_lr_tiered_affiliate_enabled' => 'no',
        'gorilla_lr_affiliate_tiers' => array(
            array('min_sales' => 0,   'rate' => 10),
            array('min_sales' => 10,  'rate' => 15),
            array('min_sales' => 50,  'rate' => 20),
            array('min_sales' => 100, 'rate' => 25),
        ),
        // Milestones
        'gorilla_lr_milestones_enabled' => 'no',
        'gorilla_lr_milestones' => array(
            array('id' => 'first_order',  'label' => 'Ilk Siparis',    'emoji' => '🛍️', 'description' => 'Ilk siparisini ver',  'type' => 'total_orders',   'target' => 1,     'xp_reward' => 50,  'credit_reward' => 0),
            array('id' => 'orders_10',    'label' => '10 Siparis',     'emoji' => '🏅', 'description' => '10 siparis tamamla',  'type' => 'total_orders',   'target' => 10,    'xp_reward' => 200, 'credit_reward' => 20),
            array('id' => 'spend_5000',   'label' => '5000 TL Harcama','emoji' => '💰', 'description' => 'Toplam 5000 TL harca','type' => 'total_spending', 'target' => 5000,  'xp_reward' => 500, 'credit_reward' => 50),
            array('id' => 'reviews_5',    'label' => '5 Yorum',        'emoji' => '✍️', 'description' => '5 urun yorumu yap',   'type' => 'total_reviews',  'target' => 5,     'xp_reward' => 100, 'credit_reward' => 0),
            array('id' => 'referrals_3',  'label' => '3 Referans',     'emoji' => '🌟', 'description' => '3 referans onayi al', 'type' => 'total_referrals','target' => 3,     'xp_reward' => 300, 'credit_reward' => 30),
        ),
        // Points Shop
        'gorilla_lr_points_shop_enabled' => 'no',
        'gorilla_lr_points_shop_rewards' => array(
            array('id' => 'coupon_5',      'label' => '5 TL Indirim Kuponu',    'xp_cost' => 100,  'type' => 'coupon', 'coupon_type' => 'fixed_cart', 'coupon_amount' => 5),
            array('id' => 'coupon_10',     'label' => '10 TL Indirim Kuponu',   'xp_cost' => 200,  'type' => 'coupon', 'coupon_type' => 'fixed_cart', 'coupon_amount' => 10),
            array('id' => 'coupon_pct_10', 'label' => '%10 Indirim Kuponu',     'xp_cost' => 300,  'type' => 'coupon', 'coupon_type' => 'percent',    'coupon_amount' => 10),
            array('id' => 'free_shipping', 'label' => 'Ucretsiz Kargo Kuponu',  'xp_cost' => 150,  'type' => 'free_shipping', 'coupon_type' => 'fixed_cart', 'coupon_amount' => 0),
        ),
        // Leaderboard
        'gorilla_lr_leaderboard_enabled'   => 'no',
        'gorilla_lr_leaderboard_limit'     => 10,
        'gorilla_lr_leaderboard_anonymize' => 'no',
        // Social Sharing
        'gorilla_lr_social_share_enabled' => 'no',
        'gorilla_lr_social_share_xp'     => 10,
        // Coupon Integration
        'gorilla_lr_coupon_enabled' => 'yes',
        // Recurring Affiliate
        'gorilla_lr_recurring_affiliate_enabled'    => 'no',
        'gorilla_lr_recurring_affiliate_months'     => 6,
        'gorilla_lr_recurring_affiliate_rate'       => 5,
        'gorilla_lr_recurring_affiliate_max_orders' => 0,
        // QR Code
        'gorilla_lr_qr_method' => 'google',
        'gorilla_lr_levels' => array(
            'level_1' => array('label' => 'Çaylak',      'min_xp' => 0,    'emoji' => '🌱', 'color' => '#a3e635'),
            'level_2' => array('label' => 'Keşifçi',     'min_xp' => 50,   'emoji' => '🔍', 'color' => '#22d3ee'),
            'level_3' => array('label' => 'Alışverişçi', 'min_xp' => 150,  'emoji' => '🛒', 'color' => '#60a5fa'),
            'level_4' => array('label' => 'Sadık',       'min_xp' => 400,  'emoji' => '⭐', 'color' => '#facc15'),
            'level_5' => array('label' => 'Uzman',       'min_xp' => 800,  'emoji' => '🏅', 'color' => '#f97316'),
            'level_6' => array('label' => 'VIP',         'min_xp' => 1500, 'emoji' => '💎', 'color' => '#a855f7'),
            'level_7' => array('label' => 'Efsane',      'min_xp' => 3000, 'emoji' => '👑', 'color' => '#fbbf24'),
        ),
        'gorilla_lr_version'          => GORILLA_LR_VERSION,
    );
    
    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }
    
    // Credit log tablosu oluştur
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $table = $wpdb->prefix . 'gorilla_credit_log';
    
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        balance_after DECIMAL(10,2) NOT NULL DEFAULT 0,
        type VARCHAR(20) NOT NULL DEFAULT 'credit',
        reason VARCHAR(500) NOT NULL DEFAULT '',
        reference_id BIGINT(20) UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY type (type),
        KEY created_at (created_at),
        KEY expires_at (expires_at)
    ) {$charset};";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Affiliate clicks tablosu oluştur
    $affiliate_table = $wpdb->prefix . 'gorilla_affiliate_clicks';

    $affiliate_sql = "CREATE TABLE IF NOT EXISTS {$affiliate_table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        referrer_user_id BIGINT(20) UNSIGNED NOT NULL,
        referrer_code VARCHAR(20) NOT NULL,
        visitor_ip VARCHAR(45) NOT NULL,
        clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        converted TINYINT(1) NOT NULL DEFAULT 0,
        order_id BIGINT(20) UNSIGNED DEFAULT NULL,
        converted_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY referrer_user_id (referrer_user_id),
        KEY referrer_code (referrer_code),
        KEY converted (converted),
        KEY clicked_at (clicked_at)
    ) {$charset};";

    dbDelta($affiliate_sql);

    // XP log tablosu oluştur
    $xp_table = $wpdb->prefix . 'gorilla_xp_log';

    $xp_sql = "CREATE TABLE IF NOT EXISTS {$xp_table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        amount INT NOT NULL,
        balance_after INT NOT NULL DEFAULT 0,
        reason VARCHAR(255) NOT NULL DEFAULT '',
        reference_type VARCHAR(20) DEFAULT NULL,
        reference_id BIGINT(20) UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY reference_type (reference_type),
        KEY created_at (created_at)
    ) {$charset};";

    dbDelta($xp_sql);

    // XP log icin unique index (duplicate XP onleme)
    $index_exists = $wpdb->get_var("SHOW INDEX FROM {$xp_table} WHERE Key_name = 'unique_user_ref'");
    if (!$index_exists) {
        $wpdb->query("ALTER TABLE {$xp_table} ADD UNIQUE KEY unique_user_ref (user_id, reference_type, reference_id)");
    }

    // Versiyon guncelle
    update_option('gorilla_lr_version', GORILLA_LR_VERSION);
    
    // Rewrite flush
    update_option('gorilla_lr_flush_needed', 'yes');
});

// ── Deaktivasyon ─────────────────────────────────────────
register_deactivation_hook(__FILE__, function() {
    // Cron temizle
    wp_clear_scheduled_hook('gorilla_lr_daily_tier_check');
    flush_rewrite_rules();
});

// ── Permalink Flush ──────────────────────────────────────
add_action('init', function() {
    if (get_option('gorilla_lr_flush_needed') === 'yes') {
        flush_rewrite_rules();
        update_option('gorilla_lr_flush_needed', 'no');
    }
}, 99);

// ── Frontend CSS/JS Enqueue ─────────────────────────────
add_action('wp_enqueue_scripts', function() {
    // Sadece frontend sayfalarında yükle
    if (is_admin()) return;

    // CSS
    wp_enqueue_style(
        'gorilla-lr-frontend',
        GORILLA_LR_URL . 'assets/css/frontend.css',
        array(),
        GORILLA_LR_VERSION
    );

    // JS (footer'da yükle)
    wp_enqueue_script(
        'gorilla-lr-frontend',
        GORILLA_LR_URL . 'assets/js/frontend.js',
        array('jquery'),
        GORILLA_LR_VERSION,
        true
    );

    // JS için değişkenler
    $loyalty_url = '';
    if (function_exists('wc_get_account_endpoint_url')) {
        $loyalty_url = wc_get_account_endpoint_url('gorilla-loyalty');
    }

    wp_localize_script('gorilla-lr-frontend', 'gorilla_lr', array(
        'ajax_url'     => admin_url('admin-ajax.php'),
        'credit_nonce' => wp_create_nonce('gorilla_credit_toggle'),
        'spin_nonce'   => wp_create_nonce('gorilla_spin_nonce'),
        'shop_nonce'   => wp_create_nonce('gorilla_shop_nonce'),
        'share_nonce'  => wp_create_nonce('gorilla_share_nonce'),
        'loyalty_url'  => $loyalty_url,
    ));
});

// ── Günlük Cron: Seviye güncelleme ──────────────────────
add_action('init', function() {
    if (!wp_next_scheduled('gorilla_lr_daily_tier_check')) {
        wp_schedule_event(time(), 'daily', 'gorilla_lr_daily_tier_check');
    }
});

add_action('gorilla_lr_daily_tier_check', function() {
    // Tüm müşterilerin seviyesini güncelle ve cache temizle
    delete_transient('gorilla_lr_tier_stats');
});

// ── Footer'da loyalty seviyesi göster ────────────────────
// CSS artık assets/css/frontend.css'de, JS assets/js/frontend.js'de
add_action('wp_footer', function() {
    try {
        if (!is_user_logged_in()) return;
        if (!function_exists('gorilla_loyalty_calculate_tier')) return;
        if (!function_exists('gorilla_lr_get_user_tier')) return;
        if (!function_exists('wc_get_account_endpoint_url')) return;

        $user_id = get_current_user_id();
        $bar_cache_key = 'gorilla_lr_bar_' . $user_id;
        $cached_bar = get_transient($bar_cache_key);
        if ($cached_bar !== false) {
            echo $cached_bar;
            return;
        }

        $tier = gorilla_lr_get_user_tier($user_id);
        if (!is_array($tier)) return;
        if (($tier['key'] ?? 'none') === 'none') return;
        if (($tier['discount'] ?? 0) <= 0) return;

        $color = esc_attr($tier['color'] ?? '#999');
        $emoji = esc_html($tier['emoji'] ?? '');
        $label = esc_html($tier['label'] ?? '');
        $discount = intval($tier['discount'] ?? 0);

        // Not: border-color dinamik renk olduğu için inline kalıyor
        $bar_html = '<div id="gorilla-loyalty-bar" style="border-color:' . $color . ';">';
        $bar_html .= $emoji . ' <strong>' . $label . '</strong> Üye — %' . $discount . ' indirim';
        $bar_html .= '</div>';

        set_transient($bar_cache_key, $bar_html, 3600);
        echo $bar_html;
    } catch (\Throwable $e) {
        // Sessizce geç - site çökmemeli
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla LR footer error: ' . $e->getMessage());
        }
    }
}, 99);

// ── Plugin Action Links ──────────────────────────────────
add_filter('plugin_action_links_' . GORILLA_LR_BASENAME, function($links) {
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=gorilla-loyalty-settings')) . '">⚙️ ' . esc_html__('Ayarlar', 'gorilla-lr') . '</a>';
    $dashboard_link = '<a href="' . esc_url(admin_url('admin.php?page=gorilla-loyalty-admin')) . '">📊 ' . esc_html__('Dashboard', 'gorilla-lr') . '</a>';
    array_unshift($links, $dashboard_link, $settings_link);
    return $links;
});

// ── Yardımcı: Kullanıcı seviye bilgisini al ─────────────
function gorilla_lr_get_user_tier($user_id) {
    $default = array('key' => 'none', 'label' => 'Üye', 'discount' => 0, 'emoji' => '👤', 'color' => '#999', 'installment' => 0, 'spending' => 0);
    
    try {
        if (!function_exists('gorilla_loyalty_calculate_tier')) {
            return $default;
        }
        return gorilla_loyalty_calculate_tier($user_id);
    } catch (\Throwable $e) {
        return $default;
    }
}
