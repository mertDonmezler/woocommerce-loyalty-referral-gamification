<?php
/**
 * Plugin Name: WP Gamify
 * Plugin URI: https://wpgamify.com
 * Description: Gelismis XP, level, streak, rozet ve gorev sistemi. WooCommerce ile tam entegre gamification altyapisi.
 * Version: 2.0.0
 * Author: Mert Donmezler
 * Author URI: https://mertdonmezler.com
 * Text Domain: wp-gamify
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 * WC tested up to: 9.5
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

/* ─── Constants ─────────────────────────────────────────────────────── */

define( 'WPGAMIFY_VERSION', '2.0.0' );
define( 'WPGAMIFY_FILE', __FILE__ );
define( 'WPGAMIFY_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPGAMIFY_URL', plugin_dir_url( __FILE__ ) );
define( 'WPGAMIFY_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPGAMIFY_DB_VERSION', 2 );

/* ─── Autoloader ────────────────────────────────────────────────────── */

spl_autoload_register( function ( string $class ): void {
    $directories = [
        'includes',
        'interfaces',
        'hooks',
        'api',
        'api/endpoints',
        'admin',
        'frontend',
    ];

    // Validate: only autoload WPGamify_ or Gamify prefixed classes.
    if ( ! str_starts_with( $class, 'WPGamify_' ) && ! str_starts_with( $class, 'Gamify' ) ) {
        return;
    }

    // Validate class name: only allow alphanumeric + underscore.
    if ( ! preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $class ) ) {
        return;
    }

    // Convert class name to file name: WPGamify_XP_Engine -> class-xp-engine.php
    $file_name = 'class-' . str_replace( '_', '-', strtolower(
        preg_replace( '/^WPGamify_/', '', $class )
    ) ) . '.php';

    // Interface files: GamifyRewardSource -> interface-reward-source.php
    if ( str_starts_with( $class, 'Gamify' ) && str_contains( strtolower( $class ), 'interface' ) === false ) {
        $interface_name = 'interface-' . str_replace( '_', '-', strtolower(
            preg_replace( '/^Gamify/', '', $class )
        ) ) . '.php';

        $interface_path = WPGAMIFY_PATH . 'interfaces/' . $interface_name;
        if ( file_exists( $interface_path ) ) {
            require_once $interface_path;
            return;
        }
    }

    foreach ( $directories as $dir ) {
        $path = WPGAMIFY_PATH . $dir . '/' . $file_name;
        if ( file_exists( $path ) ) {
            require_once $path;
            return;
        }
    }
});

/* ─── WooCommerce HPOS Compatibility ────────────────────────────────── */

add_action( 'before_woocommerce_init', function (): void {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

/* ─── WooCommerce Dependency Check ──────────────────────────────────── */

/**
 * Show admin notice if WooCommerce is not active.
 */
function wpgamify_wc_missing_notice(): void {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }
    echo '<div class="notice notice-error"><p>';
    echo '<strong>WP Gamify:</strong> Bu eklenti WooCommerce gerektirir. Lutfen WooCommerce\'i yukleyin ve etkinlestirin.';
    echo '</p></div>';
}

/**
 * Check if WooCommerce is active.
 */
function wpgamify_check_woocommerce(): bool {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'wpgamify_wc_missing_notice' );
        return false;
    }
    return true;
}

/* ─── Activation / Deactivation ─────────────────────────────────────── */

register_activation_hook( __FILE__, function (): void {
    require_once WPGAMIFY_PATH . 'includes/class-activator.php';
    WPGamify_Activator::activate();
});

register_deactivation_hook( __FILE__, function (): void {
    require_once WPGAMIFY_PATH . 'includes/class-deactivator.php';
    WPGamify_Deactivator::deactivate();
});

/* ─── Text Domain ───────────────────────────────────────────────────── */

add_action( 'init', function (): void {
    load_plugin_textdomain( 'wp-gamify', false, dirname( WPGAMIFY_BASENAME ) . '/languages' );
});

/* ─── Cron Schedules ────────────────────────────────────────────────── */

add_filter( 'cron_schedules', function ( array $schedules ): array {
    if ( ! isset( $schedules['wpgamify_hourly'] ) ) {
        $schedules['wpgamify_hourly'] = [
            'interval' => HOUR_IN_SECONDS,
            'display'  => 'WP Gamify - Saatlik',
        ];
    }
    if ( ! isset( $schedules['wpgamify_daily'] ) ) {
        $schedules['wpgamify_daily'] = [
            'interval' => DAY_IN_SECONDS,
            'display'  => 'WP Gamify - Gunluk',
        ];
    }
    return $schedules;
});

/* ─── Plugin Boot ───────────────────────────────────────────────────── */

/**
 * Priority 10: Core classes (Settings, XP Engine, Anti-Abuse, Loader).
 */
add_action( 'plugins_loaded', function (): void {
    if ( ! wpgamify_check_woocommerce() ) {
        return;
    }

    // Core classes auto-loaded on first use; ensure Settings is primed.
    WPGamify_Settings::get_all();

    // Initialize Campaign Manager filter hook.
    WPGamify_Campaign_Manager::init();

    // DB migration check on admin requests.
    if ( is_admin() ) {
        WPGamify_Migrator::check();
    }

    // GDPR hooks (loads on both admin and frontend for export/erase requests).
    WPGamify_GDPR::init();
}, 10 );

/**
 * Priority 15: Hook classes (Order, Login, Review, Discount).
 */
add_action( 'plugins_loaded', function (): void {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    $hook_classes = [
        'hooks/class-order-hooks.php'    => 'WPGamify_Order_Hooks',
        'hooks/class-login-hooks.php'    => 'WPGamify_Login_Hooks',
        'hooks/class-review-hooks.php'   => 'WPGamify_Review_Hooks',
        'hooks/class-discount-hooks.php' => 'WPGamify_Discount_Hooks',
        'hooks/class-profile-hooks.php' => 'WPGamify_Profile_Hooks',
    ];

    foreach ( $hook_classes as $file => $class ) {
        $path = WPGAMIFY_PATH . $file;
        if ( file_exists( $path ) ) {
            require_once $path;
            if ( class_exists( $class ) ) {
                new $class();
            }
        }
    }
}, 15 );

/**
 * Priority 20: Admin and Frontend classes.
 */
add_action( 'plugins_loaded', function (): void {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    if ( is_admin() ) {
        $admin_path = WPGAMIFY_PATH . 'admin/class-admin.php';
        if ( file_exists( $admin_path ) ) {
            require_once $admin_path;
            if ( class_exists( 'WPGamify_Admin' ) ) {
                new WPGamify_Admin();
            }
        }
    }

    $frontend_path = WPGAMIFY_PATH . 'frontend/class-frontend.php';
    if ( file_exists( $frontend_path ) ) {
        require_once $frontend_path;
        if ( class_exists( 'WPGamify_Frontend' ) ) {
            new WPGamify_Frontend();
        }
    }
}, 20 );

/* ─── REST API ──────────────────────────────────────────────────────── */

add_action( 'plugins_loaded', function (): void {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    $api_path = WPGAMIFY_PATH . 'api/class-api-register.php';
    if ( file_exists( $api_path ) ) {
        require_once $api_path;
        if ( class_exists( 'WPGamify_API_Register' ) ) {
            ( new WPGamify_API_Register() )->register();
        }
    }
}, 25 );

/* ─── Cron Handlers ─────────────────────────────────────────────────── */

add_action( 'wpgamify_hourly_cache', function (): void {
    global $wpdb;
    // Delete expired wpgamify transients (timeout < NOW)
    $wpdb->query( $wpdb->prepare(
        "DELETE a, b FROM {$wpdb->options} a
         INNER JOIN {$wpdb->options} b ON b.option_name = REPLACE(a.option_name, '_transient_timeout_', '_transient_')
         WHERE a.option_name LIKE %s
         AND a.option_value < %d",
        $wpdb->esc_like( '_transient_timeout_wpgamify_' ) . '%',
        time()
    ) );
});

add_action( 'wpgamify_daily_maintenance', function (): void {
    // Daily: reset streak_xp_today for all users
    global $wpdb;
    $table = $wpdb->prefix . 'gamify_streaks';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
        $wpdb->query( "UPDATE `{$table}` SET streak_xp_today = 0 WHERE streak_xp_today > 0" );
    }

    // Run streak daily maintenance (check for broken streaks).
    WPGamify_Streak_Manager::daily_maintenance();

    // XP Expiry: check and deduct expired XP.
    if ( class_exists( 'WPGamify_XP_Expiry' ) ) {
        WPGamify_XP_Expiry::warn();
        WPGamify_XP_Expiry::check();
    }

    // Grace period: process expired grace periods (level downgrades).
    if ( class_exists( 'WPGamify_Level_Manager' ) && method_exists( 'WPGamify_Level_Manager', 'process_grace_expirations' ) ) {
        WPGamify_Level_Manager::process_grace_expirations();
    }

    // Cleanup stale order XP lock keys (older than 7 days).
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %s",
        $wpdb->esc_like( '_wpgamify_order_xp_lock_' ) . '%',
        gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS )
    ) );
});

/* ─── Plugin Action Links ───────────────────────────────────────────── */

add_filter( 'plugin_action_links_' . WPGAMIFY_BASENAME, function ( array $links ): array {
    $settings_url  = admin_url( 'admin.php?page=wp-gamify-settings' );
    $settings_link = '<a href="' . esc_url( $settings_url ) . '">Ayarlar</a>';
    array_unshift( $links, $settings_link );
    return $links;
});
