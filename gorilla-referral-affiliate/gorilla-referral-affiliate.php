<?php
/**
 * Plugin Name: Gorilla Referral & Affiliate
 * Plugin URI: https://www.gorillacustomcards.com
 * Description: Video referral sistemi ve affiliate link tracking. Gorilla Loyalty & Gamification ve WooCommerce gerektirir.
 * Version: 1.2.0
 * Author: Mert Donmezler
 * Author URI: https://www.gorillacustomcards.com
 * Text Domain: gorilla-ra
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.5
 * License: GPLv2 or later
 *
 * @package Gorilla_Referral_Affiliate
 * @author Mert Donmezler
 * @copyright 2025-2026 Mert Donmezler
 */

if (!defined('ABSPATH')) exit;

// -- Sabitler --
define('GORILLA_RA_VERSION', '1.2.0');
define('GORILLA_RA_FILE', __FILE__);
define('GORILLA_RA_PATH', plugin_dir_path(__FILE__));
define('GORILLA_RA_URL', plugin_dir_url(__FILE__));
define('GORILLA_RA_BASENAME', plugin_basename(__FILE__));

// -- Tablo Varlik Kontrolu (Static Cache) --
if (!function_exists('gorilla_lr_table_exists')) {
    function gorilla_lr_table_exists($table_name) {
        static $cache = array();
        if (isset($cache[$table_name])) {
            return $cache[$table_name];
        }
        global $wpdb;
        $cache[$table_name] = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name);
        return $cache[$table_name];
    }
}

// -- Dil Dosyalari Yukleme --
add_action('init', function() {
    load_plugin_textdomain('gorilla-ra', false, dirname(GORILLA_RA_BASENAME) . '/languages');
}, 1);

// -- WooCommerce HPOS Uyumlulugu --
add_action('before_woocommerce_init', function() {
    try {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    } catch (\Throwable $e) {
        // Sessizce gec
    }
});

// -- Dependency Check: Gorilla Loyalty & Gamification + WooCommerce gerekli --
function gorilla_ra_check_dependencies() {
    $missing = array();
    if (!class_exists('WooCommerce')) {
        $missing[] = 'WooCommerce';
    }
    if (!defined('GORILLA_LG_VERSION')) {
        $missing[] = 'Gorilla Loyalty & Gamification';
    }
    if (!empty($missing)) {
        add_action('admin_notices', function() use ($missing) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Gorilla Referral & Affiliate:</strong> ';
            echo sprintf('Bu eklenti su bagimliliklari gerektirir: <strong>%s</strong>. Lutfen kurup etkinlestirin.', implode(', ', $missing));
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

// -- Dependency check on plugins_loaded priority 15 --
add_action('plugins_loaded', function() {
    if (!gorilla_ra_check_dependencies()) {
        return;
    }
}, 15);

// -- Module Loading on plugins_loaded priority 20 --
add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) return;
    if (!defined('GORILLA_LG_VERSION')) return;

    $modules = array(
        'includes/class-referral.php',
        'includes/class-affiliate.php',
        'includes/class-settings.php',
        'includes/class-admin.php',
        'includes/class-frontend.php',
        'includes/class-emails.php',
        'includes/class-wc-emails.php',
        'includes/class-rest-api.php',
        'includes/class-gdpr.php',
    );

    foreach ($modules as $module) {
        $file = GORILLA_RA_PATH . $module;
        if (file_exists($file)) {
            try {
                require_once $file;
            } catch (\Throwable $e) {
                error_log('Gorilla RA: Module load error in ' . $module . ' - ' . $e->getMessage());
                add_action('admin_notices', function() use ($module, $e) {
                    echo '<div class="notice notice-error"><p>';
                    echo '<strong>Gorilla RA Hata:</strong> ' . esc_html($module) . ' yuklenemedi: ' . esc_html($e->getMessage());
                    echo '</p></div>';
                });
            }
        }
    }
}, 20);

// -- Aktivasyon --
register_activation_hook(__FILE__, function() {
    // Varsayilan ayarlari olustur
    $defaults = array(
        'gorilla_lr_enabled_referral'               => 'yes',
        'gorilla_lr_referral_rate'                   => 35,
        'gorilla_lr_enabled_affiliate'               => 'yes',
        'gorilla_lr_affiliate_rate'                  => 10,
        'gorilla_lr_affiliate_cookie_days'           => 30,
        'gorilla_lr_affiliate_min_order'             => 0,
        'gorilla_lr_affiliate_first_only'            => 'no',
        'gorilla_lr_affiliate_allow_self'            => 'no',
        'gorilla_lr_affiliate_url_param'             => 'ref',
        'gorilla_lr_dual_referral_enabled'           => 'no',
        'gorilla_lr_dual_referral_type'              => 'percent',
        'gorilla_lr_dual_referral_amount'            => 10,
        'gorilla_lr_dual_referral_min_order'         => 0,
        'gorilla_lr_dual_referral_expiry_days'       => 30,
        'gorilla_lr_tiered_affiliate_enabled'        => 'no',
        'gorilla_lr_affiliate_tiers'                 => array(
            array('min_sales' => 0,   'rate' => 10),
            array('min_sales' => 10,  'rate' => 15),
            array('min_sales' => 50,  'rate' => 20),
            array('min_sales' => 100, 'rate' => 25),
        ),
        'gorilla_lr_recurring_affiliate_enabled'     => 'no',
        'gorilla_lr_recurring_affiliate_months'      => 6,
        'gorilla_lr_recurring_affiliate_rate'        => 5,
        'gorilla_lr_recurring_affiliate_max_orders'  => 0,
        'gorilla_lr_fraud_detection_enabled'         => 'no',
    );

    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }

    // Affiliate clicks tablosu olustur
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
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

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($affiliate_sql);

    // Versiyon guncelle
    update_option('gorilla_ra_version', GORILLA_RA_VERSION);

    // Rewrite flush
    update_option('gorilla_ra_flush_needed', 'yes');
});

// -- Deaktivasyon --
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

// -- Permalink Flush --
add_action('init', function() {
    if (get_option('gorilla_ra_flush_needed') === 'yes') {
        flush_rewrite_rules();
        update_option('gorilla_ra_flush_needed', 'no');
    }
}, 99);

// -- Frontend CSS/JS Enqueue --
add_action('wp_enqueue_scripts', function() {
    if (is_admin()) return;

    // Sadece My Account sayfalarinda yukle
    if (!function_exists('is_account_page') || !is_account_page()) return;

    // Shared base styles & scripts from LG plugin (RA requires LG, so GORILLA_LG_URL is always available)
    if (defined('GORILLA_LG_URL') && defined('GORILLA_LG_VERSION')) {
        if (!wp_style_is('gorilla-base', 'enqueued') && !wp_style_is('gorilla-base', 'registered')) {
            wp_enqueue_style('gorilla-base', GORILLA_LG_URL . 'assets/css/gorilla-base.css', array(), GORILLA_LG_VERSION);
        } elseif (!wp_style_is('gorilla-base', 'enqueued')) {
            wp_enqueue_style('gorilla-base');
        }
        if (!wp_script_is('gorilla-base', 'enqueued') && !wp_script_is('gorilla-base', 'registered')) {
            wp_enqueue_script('gorilla-base', GORILLA_LG_URL . 'assets/js/gorilla-base.js', array(), GORILLA_LG_VERSION, true);
        } elseif (!wp_script_is('gorilla-base', 'enqueued')) {
            wp_enqueue_script('gorilla-base');
        }
    }

    // CSS (depends on base)
    wp_enqueue_style(
        'gorilla-ra-frontend',
        GORILLA_RA_URL . 'assets/css/referral.css',
        array('gorilla-base'),
        GORILLA_RA_VERSION
    );

    // JS (footer'da yukle, depends on base)
    wp_enqueue_script(
        'gorilla-ra-frontend',
        GORILLA_RA_URL . 'assets/js/referral.js',
        array('jquery', 'gorilla-base'),
        GORILLA_RA_VERSION,
        true
    );

    // JS icin degiskenler
    wp_localize_script('gorilla-ra-frontend', 'gorilla_ra', array(
        'ajax_url'        => admin_url('admin-ajax.php'),
        'ref_slug_nonce'  => wp_create_nonce('gorilla_ref_slug'),
        'currency_symbol' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '&#8378;',
    ));
});

// -- Plugin Action Links --
add_filter('plugin_action_links_' . GORILLA_RA_BASENAME, function($links) {
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=gorilla-ra-settings')) . '">' . esc_html__('Ayarlar', 'gorilla-ra') . '</a>';
    $dashboard_link = '<a href="' . esc_url(admin_url('admin.php?page=gorilla-ra-dashboard')) . '">' . esc_html__('Dashboard', 'gorilla-ra') . '</a>';
    array_unshift($links, $dashboard_link, $settings_link);
    return $links;
});
