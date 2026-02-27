<?php
/**
 * Plugin Name: Gorilla Loyalty & Gamification
 * Plugin URI: https://www.gorillacustomcards.com
 * Description: XP/level, tier, badges, spin wheel, challenges, leaderboard, milestones, social share, QR, points shop, churn prediction, smart coupon, VIP early access gamification sistemi.
 * Version: 1.0.1
 * Author: Mert Donmezler
 * Author URI: https://www.gorillacustomcards.com
 * Text Domain: gorilla-loyalty
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.5
 * License: GPLv2 or later
 *
 * @package Gorilla_Loyalty_Gamification
 */

if (!defined('ABSPATH')) exit;

define('GORILLA_LG_VERSION', '1.0.1');
define('GORILLA_LG_FILE', __FILE__);
define('GORILLA_LG_PATH', plugin_dir_path(__FILE__));
define('GORILLA_LG_URL', plugin_dir_url(__FILE__));
define('GORILLA_LG_BASENAME', plugin_basename(__FILE__));

// ── Core Dependency Check ────────────────────────────────
function gorilla_lg_check_dependencies() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Gorilla Loyalty & Gamification:</strong> ';
            echo sprintf('Bu eklenti <strong>%s</strong> gerektirir. Lutfen kurup etkinlestirin.', 'WooCommerce');
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

// ── gorilla_lr_table_exists (shared utility) ─────────────
if (!function_exists('gorilla_lr_table_exists')) {
    function gorilla_lr_table_exists($table_name) {
        static $cache = array();
        if (isset($cache[$table_name])) return $cache[$table_name];
        global $wpdb;
        $cache[$table_name] = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name);
        return $cache[$table_name];
    }
}

// ── gorilla_get_tiers (shared utility) ───────────────────
if (!function_exists('gorilla_get_tiers')) {
    function gorilla_get_tiers() {
        return get_option('gorilla_lr_tiers', array(
            'bronze'   => array('label' => 'Bronz',   'min' => 1500,  'discount' => 5,  'color' => '#CD7F32', 'emoji' => "\xF0\x9F\xA5\x89", 'installment' => 0),
            'silver'   => array('label' => 'Gumus',    'min' => 3000,  'discount' => 10, 'color' => '#C0C0C0', 'emoji' => "\xF0\x9F\xA5\x88", 'installment' => 0),
            'gold'     => array('label' => 'Altin',    'min' => 6000,  'discount' => 15, 'color' => '#FFD700', 'emoji' => "\xF0\x9F\xA5\x87", 'installment' => 0),
            'platinum' => array('label' => 'Platin',   'min' => 10000, 'discount' => 20, 'color' => '#E5E4E2', 'emoji' => "\xF0\x9F\x92\x8E", 'installment' => 0),
            'diamond'  => array('label' => 'Elmas',    'min' => 20000, 'discount' => 25, 'color' => '#B9F2FF', 'emoji' => "\xF0\x9F\x91\x91", 'installment' => 3),
        ));
    }
}

// ── Language Files ────────────────────────────────────────
add_action('init', function() {
    load_plugin_textdomain('gorilla-loyalty', false, dirname(GORILLA_LG_BASENAME) . '/languages');
}, 1);

// ── HPOS Compatibility ───────────────────────────────────
add_action('before_woocommerce_init', function() {
    try {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    } catch (\Throwable $e) {}
});

// ── Module Loading ───────────────────────────────────────
function gorilla_lg_load_modules() {
    if (!gorilla_lg_check_dependencies()) return;

    $modules = array(
        'includes/class-store-credit.php',
        'includes/class-coupon-generator.php',
        'includes/class-loyalty.php',
        'includes/class-xp.php',
        'includes/class-challenges.php',
        'includes/class-settings.php',
        'includes/class-admin.php',
        'includes/class-frontend.php',
        'includes/class-emails.php',
        'includes/class-wc-emails.php',
        'includes/class-rest-api.php',
        'includes/class-gdpr.php',
        'includes/class-sms.php',
    );

    foreach ($modules as $module) {
        $file = GORILLA_LG_PATH . $module;
        if (file_exists($file)) {
            try {
                require_once $file;
            } catch (\Throwable $e) {
                error_log('Gorilla LG: Module load error in ' . $module . ' - ' . $e->getMessage());
            }
        }
    }
}
add_action('plugins_loaded', 'gorilla_lg_load_modules', 20);

// ── WP-CLI Commands ──────────────────────────────────────
if (defined('WP_CLI') && WP_CLI) {
    add_action('plugins_loaded', function() {
        $cli_file = GORILLA_LG_PATH . 'includes/class-cli.php';
        if (file_exists($cli_file)) {
            require_once $cli_file;
        }
    }, 25);
}

// ── Activation ───────────────────────────────────────────
register_activation_hook(__FILE__, function() {
    // XP log table
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
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

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($xp_sql);

    // Unique index for duplicate XP prevention
    $index_exists = $wpdb->get_var("SHOW INDEX FROM {$xp_table} WHERE Key_name = 'unique_user_ref'");
    if (!$index_exists) {
        $wpdb->query("ALTER TABLE {$xp_table} ADD UNIQUE KEY unique_user_ref (user_id, reference_type, reference_id)");
    }

    // Credit log table
    $credit_table = $wpdb->prefix . 'gorilla_credit_log';
    $credit_sql = "CREATE TABLE IF NOT EXISTS {$credit_table} (
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
    dbDelta($credit_sql);

    // Default options (loyalty + XP + gamification)
    $defaults = array(
        'gorilla_lr_tiers' => array(
            'bronze'   => array('label' => 'Bronz',   'min' => 1500,  'discount' => 5,  'color' => '#CD7F32', 'emoji' => "\xF0\x9F\xA5\x89", 'installment' => 0),
            'silver'   => array('label' => 'Gumus',    'min' => 3000,  'discount' => 10, 'color' => '#C0C0C0', 'emoji' => "\xF0\x9F\xA5\x88", 'installment' => 0),
            'gold'     => array('label' => 'Altin',    'min' => 6000,  'discount' => 15, 'color' => '#FFD700', 'emoji' => "\xF0\x9F\xA5\x87", 'installment' => 0),
            'platinum' => array('label' => 'Platin',   'min' => 10000, 'discount' => 20, 'color' => '#E5E4E2', 'emoji' => "\xF0\x9F\x92\x8E", 'installment' => 0),
            'diamond'  => array('label' => 'Elmas',    'min' => 20000, 'discount' => 25, 'color' => '#B9F2FF', 'emoji' => "\xF0\x9F\x91\x91", 'installment' => 3),
        ),
        'gorilla_lr_period_months'          => 6,
        'gorilla_lr_enabled_loyalty'        => 'yes',
        'gorilla_lr_enabled_xp'             => 'yes',
        'gorilla_lr_xp_per_order_rate'      => 10,
        'gorilla_lr_xp_review'              => 25,
        'gorilla_lr_xp_referral'            => 50,
        'gorilla_lr_xp_affiliate'           => 30,
        'gorilla_lr_xp_first_order'         => 100,
        'gorilla_lr_xp_register'            => 10,
        'gorilla_lr_xp_profile'             => 20,
        'gorilla_lr_bonus_enabled'          => 'no',
        'gorilla_lr_bonus_multiplier'       => 1.5,
        'gorilla_lr_bonus_start'            => '',
        'gorilla_lr_bonus_end'              => '',
        'gorilla_lr_bonus_label'            => '',
        'gorilla_lr_birthday_enabled'       => 'no',
        'gorilla_lr_birthday_xp'            => 50,
        'gorilla_lr_birthday_credit'        => 10,
        'gorilla_lr_streak_enabled'         => 'no',
        'gorilla_lr_streak_daily_xp'        => 5,
        'gorilla_lr_streak_7day_bonus'      => 50,
        'gorilla_lr_streak_30day_bonus'     => 200,
        'gorilla_lr_badges_enabled'         => 'no',
        'gorilla_lr_spin_enabled'           => 'no',
        'gorilla_lr_spin_prizes'            => array(
            array('label' => '10 XP',          'type' => 'xp',           'value' => 10,  'weight' => 30),
            array('label' => '25 XP',          'type' => 'xp',           'value' => 25,  'weight' => 20),
            array('label' => '50 XP',          'type' => 'xp',           'value' => 50,  'weight' => 10),
            array('label' => '5 TL Credit',    'type' => 'credit',       'value' => 5,   'weight' => 15),
            array('label' => '10 TL Credit',   'type' => 'credit',       'value' => 10,  'weight' => 8),
            array('label' => 'Ucretsiz Kargo', 'type' => 'free_shipping','value' => 0,   'weight' => 7),
            array('label' => '%10 Indirim',    'type' => 'coupon',       'value' => 10,  'weight' => 5),
            array('label' => 'Tekrar Dene',    'type' => 'nothing',      'value' => 0,   'weight' => 5),
        ),
        'gorilla_lr_milestones_enabled'     => 'no',
        'gorilla_lr_milestones'             => array(
            array('id' => 'first_order',  'label' => 'Ilk Siparis',    'emoji' => "\xF0\x9F\x9B\x8D\xEF\xB8\x8F", 'description' => 'Ilk siparisini ver',  'type' => 'total_orders',   'target' => 1,     'xp_reward' => 50,  'credit_reward' => 0),
            array('id' => 'orders_10',    'label' => '10 Siparis',     'emoji' => "\xF0\x9F\x8F\x85", 'description' => '10 siparis tamamla',  'type' => 'total_orders',   'target' => 10,    'xp_reward' => 200, 'credit_reward' => 20),
            array('id' => 'spend_5000',   'label' => '5000 TL Harcama','emoji' => "\xF0\x9F\x92\xB0", 'description' => 'Toplam 5000 TL harca','type' => 'total_spending', 'target' => 5000,  'xp_reward' => 500, 'credit_reward' => 50),
        ),
        'gorilla_lr_points_shop_enabled'    => 'no',
        'gorilla_lr_points_shop_rewards'    => array(
            array('id' => 'coupon_5',      'label' => '5 TL Indirim Kuponu',    'xp_cost' => 100,  'type' => 'coupon', 'coupon_type' => 'fixed_cart', 'coupon_amount' => 5),
            array('id' => 'coupon_10',     'label' => '10 TL Indirim Kuponu',   'xp_cost' => 200,  'type' => 'coupon', 'coupon_type' => 'fixed_cart', 'coupon_amount' => 10),
            array('id' => 'coupon_pct_10', 'label' => '%10 Indirim Kuponu',     'xp_cost' => 300,  'type' => 'coupon', 'coupon_type' => 'percent',    'coupon_amount' => 10),
            array('id' => 'free_shipping', 'label' => 'Ucretsiz Kargo Kuponu',  'xp_cost' => 150,  'type' => 'free_shipping', 'coupon_type' => 'fixed_cart', 'coupon_amount' => 0),
        ),
        'gorilla_lr_leaderboard_enabled'    => 'no',
        'gorilla_lr_leaderboard_limit'      => 10,
        'gorilla_lr_leaderboard_anonymize'  => 'no',
        'gorilla_lr_social_share_enabled'   => 'no',
        'gorilla_lr_social_share_xp'        => 10,
        'gorilla_lr_qr_enabled'             => 'yes',
        'gorilla_lr_qr_method'              => 'quickchart',
        'gorilla_lr_levels'                 => array(
            'level_1' => array('label' => 'Caylak',      'min_xp' => 0,    'emoji' => "\xF0\x9F\x8C\xB1", 'color' => '#a3e635'),
            'level_2' => array('label' => 'Kesifci',     'min_xp' => 50,   'emoji' => "\xF0\x9F\x94\x8D", 'color' => '#22d3ee'),
            'level_3' => array('label' => 'Alisverisci', 'min_xp' => 150,  'emoji' => "\xF0\x9F\x9B\x92", 'color' => '#60a5fa'),
            'level_4' => array('label' => 'Sadik',       'min_xp' => 400,  'emoji' => "\xE2\xAD\x90",     'color' => '#facc15'),
            'level_5' => array('label' => 'Uzman',       'min_xp' => 800,  'emoji' => "\xF0\x9F\x8F\x85", 'color' => '#f97316'),
            'level_6' => array('label' => 'VIP',         'min_xp' => 1500, 'emoji' => "\xF0\x9F\x92\x8E", 'color' => '#a855f7'),
            'level_7' => array('label' => 'Efsane',      'min_xp' => 3000, 'emoji' => "\xF0\x9F\x91\x91", 'color' => '#fbbf24'),
        ),
        'gorilla_lr_challenges_enabled'     => 'no',
        'gorilla_lr_credit_min_order'        => 0,
        'gorilla_lr_credit_expiry_days'      => 0,
        'gorilla_lr_credit_expiry_warn_days' => 7,
        'gorilla_lr_coupon_enabled'          => 'yes',
    );

    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }

    update_option('gorilla_lg_version', GORILLA_LG_VERSION);
    update_option('gorilla_lg_flush_needed', 'yes');
});

// ── Deactivation ─────────────────────────────────────────
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('gorilla_lr_daily_tier_check');
    wp_clear_scheduled_hook('gorilla_sc_daily_check');
    flush_rewrite_rules();
});

// ── Permalink Flush ──────────────────────────────────────
add_action('init', function() {
    if (get_option('gorilla_lg_flush_needed') === 'yes') {
        flush_rewrite_rules();
        update_option('gorilla_lg_flush_needed', 'no');
    }
}, 99);

// ── Frontend CSS/JS Enqueue ─────────────────────────────
add_action('wp_enqueue_scripts', function() {
    if (is_admin()) return;

    wp_enqueue_style('gorilla-lg-frontend', GORILLA_LG_URL . 'assets/css/loyalty.css', array(), GORILLA_LG_VERSION);
    wp_enqueue_script('gorilla-lg-frontend', GORILLA_LG_URL . 'assets/js/loyalty.js', array('jquery'), GORILLA_LG_VERSION, true);

    $loyalty_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('gorilla-loyalty') : '';

    wp_localize_script('gorilla-lg-frontend', 'gorillaLR', array(
        'ajax_url'        => admin_url('admin-ajax.php'),
        'spin_nonce'      => wp_create_nonce('gorilla_spin_nonce'),
        'shop_nonce'      => wp_create_nonce('gorilla_shop_nonce'),
        'share_nonce'     => wp_create_nonce('gorilla_share_nonce'),
        'loyalty_url'     => $loyalty_url,
        'currency_symbol' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : "\xE2\x82\xBA",
    ));

    // Store Credit assets (checkout + account pages only)
    if ((function_exists('is_checkout') && is_checkout()) || (function_exists('is_account_page') && is_account_page())) {
        wp_enqueue_style('gorilla-sc-frontend', GORILLA_LG_URL . 'assets/css/store-credit.css', array(), GORILLA_LG_VERSION);
        wp_enqueue_script('gorilla-sc-frontend', GORILLA_LG_URL . 'assets/js/store-credit.js', array('jquery'), GORILLA_LG_VERSION, true);
        wp_localize_script('gorilla-sc-frontend', 'gorilla_sc', array(
            'ajax_url'        => admin_url('admin-ajax.php'),
            'credit_nonce'    => wp_create_nonce('gorilla_credit_toggle'),
            'currency_symbol' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : "\xE2\x82\xBA",
        ));
    }
});

// ── Daily Cron ───────────────────────────────────────────
add_action('init', function() {
    if (!wp_next_scheduled('gorilla_lr_daily_tier_check')) {
        wp_schedule_event(time(), 'daily', 'gorilla_lr_daily_tier_check');
    }
});

// Store Credit daily cron
add_action('init', function() {
    if (!wp_next_scheduled('gorilla_sc_daily_check')) {
        wp_schedule_event(time(), 'daily', 'gorilla_sc_daily_check');
    }
}, 20);

add_action('gorilla_lr_daily_tier_check', function() {
    delete_transient('gorilla_lr_tier_stats');
});

// ── Churn Prediction ─────────────────────────────────────
add_action('gorilla_lr_daily_tier_check', 'gorilla_churn_weekly_check');
function gorilla_churn_weekly_check() {
    if (get_option('gorilla_lr_churn_enabled', 'no') !== 'yes') return;

    $last_run = get_option('gorilla_lr_churn_last_run', '');
    $now = current_time('Y-W');
    if ($last_run === $now) return;
    update_option('gorilla_lr_churn_last_run', $now);

    global $wpdb;
    $churn_months = intval(get_option('gorilla_lr_churn_months', 3));
    $cutoff_date = gmdate('Y-m-d', strtotime("-{$churn_months} months"));

    $at_risk = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT pm.meta_value as customer_id
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_customer_user'
         WHERE p.post_type IN ('shop_order', 'shop_order_placeholder')
         AND p.post_status IN ('wc-completed', 'wc-processing')
         AND pm.meta_value > 0
         AND pm.meta_value NOT IN (
             SELECT DISTINCT pm2.meta_value
             FROM {$wpdb->posts} p2
             INNER JOIN {$wpdb->postmeta} pm2 ON p2.ID = pm2.post_id AND pm2.meta_key = '_customer_user'
             WHERE p2.post_type IN ('shop_order', 'shop_order_placeholder')
             AND p2.post_status IN ('wc-completed', 'wc-processing')
             AND p2.post_date >= %s
         )",
        $cutoff_date
    ));

    if (empty($at_risk)) return;

    $bonus_credit = floatval(get_option('gorilla_lr_churn_bonus_credit', 25));
    $bonus_xp = intval(get_option('gorilla_lr_churn_bonus_xp', 100));

    foreach ($at_risk as $row) {
        $user_id = intval($row->customer_id);
        if (!$user_id) continue;

        $quarter = current_time('Y') . '-Q' . ceil(intval(current_time('m')) / 3);
        $guard_key = '_gorilla_churn_reengaged_' . $quarter;
        if (get_user_meta($user_id, $guard_key, true)) continue;
        update_user_meta($user_id, $guard_key, current_time('mysql'));

        if ($bonus_credit > 0 && function_exists('gorilla_credit_adjust')) {
            gorilla_credit_adjust($user_id, $bonus_credit, 'churn_bonus', 'Sizi ozledik! Hosgeldin bonusu', 0, 30);
        }
        if ($bonus_xp > 0 && function_exists('gorilla_xp_add')) {
            gorilla_xp_add($user_id, $bonus_xp, 'Sizi ozledik! Hosgeldin XP bonusu', 'churn_bonus', 0);
        }
        if (function_exists('gorilla_email_churn_reengagement')) {
            gorilla_email_churn_reengagement($user_id, $bonus_credit, $bonus_xp);
        }
    }
}

// ── Smart Coupon ─────────────────────────────────────────
add_action('gorilla_lr_daily_tier_check', 'gorilla_smart_coupon_check');
function gorilla_smart_coupon_check() {
    if (get_option('gorilla_lr_smart_coupon_enabled', 'no') !== 'yes') return;
    if (!function_exists('gorilla_generate_coupon')) return;

    $last_run = get_option('gorilla_lr_smart_coupon_last_run', '');
    $now = current_time('Y-W');
    if ($last_run === $now) return;
    update_option('gorilla_lr_smart_coupon_last_run', $now);

    global $wpdb;
    $inactive_days = intval(get_option('gorilla_lr_smart_coupon_inactive_days', 21));
    $discount_pct  = floatval(get_option('gorilla_lr_smart_coupon_discount', 10));
    $expiry_days   = intval(get_option('gorilla_lr_smart_coupon_expiry', 14));
    $cutoff_date   = gmdate('Y-m-d', strtotime("-{$inactive_days} days"));
    $churn_months  = intval(get_option('gorilla_lr_churn_months', 3));
    $churn_cutoff  = gmdate('Y-m-d', strtotime("-{$churn_months} months"));

    $candidates = $wpdb->get_results($wpdb->prepare(
        "SELECT pm.meta_value as customer_id, MAX(p.post_date) as last_order
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_customer_user'
         WHERE p.post_type IN ('shop_order', 'shop_order_placeholder')
         AND p.post_status IN ('wc-completed', 'wc-processing')
         AND pm.meta_value > 0
         GROUP BY pm.meta_value
         HAVING last_order < %s AND last_order >= %s",
        $cutoff_date, $churn_cutoff
    ));

    if (empty($candidates)) return;

    $processed = 0;
    foreach ($candidates as $row) {
        if ($processed >= 50) break;
        $user_id = intval($row->customer_id);
        if (!$user_id) continue;

        $month_key = '_gorilla_smart_coupon_' . current_time('Y-m');
        if (get_user_meta($user_id, $month_key, true)) continue;
        update_user_meta($user_id, $month_key, current_time('mysql'));

        $fav_cat = gorilla_smart_coupon_fav_category($user_id);
        $coupon_code = gorilla_generate_coupon(array(
            'type' => 'percent', 'amount' => $discount_pct, 'expiry_days' => $expiry_days,
            'user_id' => $user_id, 'reason' => 'smart_coupon', 'prefix' => 'SMART',
        ));

        if ($coupon_code && $fav_cat) {
            $coupon_id = wc_get_coupon_id_by_code($coupon_code);
            if ($coupon_id) {
                $coupon_obj = new \WC_Coupon($coupon_id);
                $coupon_obj->set_product_categories(array($fav_cat['term_id']));
                $coupon_obj->save();
            }
        }

        if ($coupon_code && function_exists('gorilla_email_smart_coupon')) {
            gorilla_email_smart_coupon($user_id, $coupon_code, $discount_pct, $expiry_days, $fav_cat ? $fav_cat['name'] : '');
        }
        $processed++;
    }
}

function gorilla_smart_coupon_fav_category($user_id) {
    $orders = wc_get_orders(array(
        'customer_id' => $user_id, 'status' => array('completed', 'processing'),
        'limit' => 20, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'ids',
    ));
    if (empty($orders)) return null;

    $cat_counts = array();
    foreach ($orders as $order_id) {
        $order = wc_get_order($order_id);
        if (!$order) continue;
        foreach ($order->get_items() as $item) {
            $cats = wp_get_post_terms($item->get_product_id(), 'product_cat', array('fields' => 'all'));
            if (is_wp_error($cats)) continue;
            foreach ($cats as $cat) {
                if (!isset($cat_counts[$cat->term_id])) {
                    $cat_counts[$cat->term_id] = array('term_id' => $cat->term_id, 'name' => $cat->name, 'count' => 0);
                }
                $cat_counts[$cat->term_id]['count'] += $item->get_quantity();
            }
        }
    }
    if (empty($cat_counts)) return null;
    usort($cat_counts, function($a, $b) { return $b['count'] - $a['count']; });
    return $cat_counts[0];
}

// ── Footer Loyalty Bar ───────────────────────────────────
add_action('wp_footer', function() {
    try {
        if (!is_user_logged_in()) return;
        if (!function_exists('gorilla_loyalty_calculate_tier')) return;
        if (!function_exists('gorilla_lr_get_user_tier')) return;
        if (!function_exists('wc_get_account_endpoint_url')) return;

        $user_id = get_current_user_id();
        $bar_cache_key = 'gorilla_lr_bar_' . $user_id;
        $cached_bar = get_transient($bar_cache_key);
        if ($cached_bar !== false) { echo $cached_bar; return; }

        $tier = gorilla_lr_get_user_tier($user_id);
        if (!is_array($tier) || ($tier['key'] ?? 'none') === 'none' || ($tier['discount'] ?? 0) <= 0) return;

        $color = esc_attr($tier['color'] ?? '#999');
        $emoji = esc_html($tier['emoji'] ?? '');
        $label = esc_html($tier['label'] ?? '');
        $discount = intval($tier['discount'] ?? 0);

        $bar_html = '<div id="gorilla-loyalty-bar" style="border-color:' . $color . ';">';
        $bar_html .= $emoji . ' <strong>' . $label . '</strong> Uye &mdash; %' . $discount . ' indirim';
        $bar_html .= '</div>';

        set_transient($bar_cache_key, $bar_html, 3600);
        echo $bar_html;
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Gorilla LG footer error: ' . $e->getMessage());
    }
}, 99);

// ── Social Proof ─────────────────────────────────────────
function gorilla_social_proof_log($event_text) {
    $events = get_transient('gorilla_social_proof_events');
    if (!is_array($events)) $events = array();
    array_unshift($events, array('text' => sanitize_text_field($event_text), 'time' => time()));
    $cutoff = time() - 7200;
    $events = array_filter($events, function($e) use ($cutoff) { return $e['time'] >= $cutoff; });
    $events = array_slice($events, 0, 20);
    set_transient('gorilla_social_proof_events', $events, 7200);
}

function gorilla_social_proof_anon_name($user_id) {
    $names = array('Bir musteri', 'Bir alisverisci', 'Bir uye', 'Bir kullanici');
    return $names[$user_id % count($names)];
}

add_action('gorilla_xp_level_up', function($user_id, $old_level, $new_level) {
    if (get_option('gorilla_lr_social_proof_enabled', 'no') !== 'yes') return;
    $anonymize = get_option('gorilla_lr_social_proof_anonymize', 'no') === 'yes';
    $name = $anonymize ? gorilla_social_proof_anon_name($user_id) : get_userdata($user_id)->display_name;
    $label = is_array($new_level) ? ($new_level['label'] ?? '') : '';
    gorilla_social_proof_log(sprintf('%s %s seviyesine yukseldi!', $name, $label));
}, 10, 3);

add_action('gorilla_badge_earned', function($user_id, $badge_key) {
    if (get_option('gorilla_lr_social_proof_enabled', 'no') !== 'yes') return;
    $anonymize = get_option('gorilla_lr_social_proof_anonymize', 'no') === 'yes';
    $name = $anonymize ? gorilla_social_proof_anon_name($user_id) : get_userdata($user_id)->display_name;
    $defs = function_exists('gorilla_badge_get_definitions') ? gorilla_badge_get_definitions() : array();
    gorilla_social_proof_log(sprintf('%s "%s" rozetini kazandi!', $name, $defs[$badge_key]['label'] ?? $badge_key));
}, 10, 2);

add_action('wp_footer', function() {
    if (is_admin() || get_option('gorilla_lr_social_proof_enabled', 'no') !== 'yes') return;
    $events = get_transient('gorilla_social_proof_events');
    if (!is_array($events) || empty($events)) return;
    $show = array_slice($events, 0, 5);
    $json_events = wp_json_encode(array_map(function($e) {
        return array('text' => $e['text'], 'ago' => human_time_diff($e['time'], time()) . ' once');
    }, $show));
    ?>
    <style>#gorilla-social-proof{position:fixed;bottom:20px;left:20px;z-index:9998;pointer-events:none}#gorilla-social-proof .gsp-toast{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.12);padding:12px 18px;max-width:320px;font-size:13px;color:#1f2937;opacity:0;transform:translateY(20px);transition:all .4s ease;pointer-events:auto}#gorilla-social-proof .gsp-toast.gsp-show{opacity:1;transform:translateY(0)}#gorilla-social-proof .gsp-time{font-size:11px;color:#9ca3af;margin-top:2px}</style>
    <div id="gorilla-social-proof"></div>
    <script>!function(){var e=<?php echo $json_events; ?>;if(e&&e.length){var c=document.getElementById("gorilla-social-proof");if(c){var i=0;function n(){if(i>=e.length)i=0;var t=e[i++],o=document.createElement("div");o.className="gsp-toast";o.innerHTML='<div>\xF0\x9F\x94\x94 '+t.text+'</div><div class="gsp-time">'+t.ago+"</div>";c.appendChild(o);o.offsetHeight;o.classList.add("gsp-show");setTimeout(function(){o.classList.remove("gsp-show");setTimeout(function(){o.remove()},400)},5e3)}setTimeout(function(){n();setInterval(n,8e3)},3e3)}}}();</script>
    <?php
}, 100);

// ── Helper: gorilla_lr_get_user_tier ─────────────────────
if (!function_exists('gorilla_lr_get_user_tier')) {
    function gorilla_lr_get_user_tier($user_id) {
        $default = array('key' => 'none', 'label' => 'Uye', 'discount' => 0, 'emoji' => "\xF0\x9F\x91\xA4", 'color' => '#999', 'installment' => 0, 'spending' => 0);
        try {
            if (!function_exists('gorilla_loyalty_calculate_tier')) return $default;
            return gorilla_loyalty_calculate_tier($user_id);
        } catch (\Throwable $e) { return $default; }
    }
}

// ── Cross-Plugin Hook Listeners ──────────────────────────
// Listen for referral approval -> award XP
add_action('gorilla_referral_approved', function($user_id, $ref_id) {
    if (function_exists('gorilla_xp_on_referral_approved')) {
        gorilla_xp_on_referral_approved($user_id, $ref_id);
    }
}, 10, 2);

// Listen for affiliate sale -> award XP
add_action('gorilla_affiliate_sale', function($user_id, $order_id) {
    if (function_exists('gorilla_xp_on_affiliate_sale')) {
        gorilla_xp_on_affiliate_sale($user_id, $order_id);
    }
}, 10, 2);

// ── Plugin Action Links ──────────────────────────────────
add_filter('plugin_action_links_' . GORILLA_LG_BASENAME, function($links) {
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=gorilla-loyalty-settings')) . '">' . esc_html__('Ayarlar', 'gorilla-loyalty') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});
