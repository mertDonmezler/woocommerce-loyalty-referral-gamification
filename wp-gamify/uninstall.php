<?php
/**
 * WP Gamify Uninstall
 *
 * Veri temizleme islemi. Kullanici tercihine gore tum verileri siler
 * veya sadece eklenti ayarlarini kaldirir.
 *
 * @package WPGamify
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$keep_data = get_option( 'wpgamify_keep_data_on_uninstall', 'no' );

// Also check the JSON settings blob for the preference.
$settings = get_option( 'wpgamify_settings', [] );
if ( is_array( $settings ) && ! empty( $settings['keep_data_on_uninstall'] ) ) {
    $keep_data = 'yes';
}

/* ─── Always remove plugin options ─────────────────────────────────── */

$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
    $wpdb->esc_like( 'wpgamify_' ) . '%'
) );

/* ─── Clear all scheduled hooks ────────────────────────────────────── */

$cron_hooks = [
    'wpgamify_hourly_cache',
    'wpgamify_daily_maintenance',
];

foreach ( $cron_hooks as $hook ) {
    $timestamp = wp_next_scheduled( $hook );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, $hook );
    }
    wp_unschedule_hook( $hook );
}

/* ─── Delete transients ────────────────────────────────────────────── */

$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
    $wpdb->esc_like( '_transient_wpgamify_' ) . '%',
    $wpdb->esc_like( '_transient_timeout_wpgamify_' ) . '%'
) );

/* ─── Always cleanup lock keys (outside keep_data check) ──────────── */

$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
    $wpdb->esc_like( '_wpgamify_order_xp_lock_' ) . '%'
) );

/* ─── Drop tables if not keeping data ──────────────────────────────── */

if ( $keep_data !== 'yes' ) {

    // C10 FIX: If Gorilla Loyalty plugin is still active, do NOT destroy
    // shared data (XP tables, user meta) that Gorilla depends on.
    $gorilla_active = in_array(
        'gorilla-loyalty-gamification/gorilla-loyalty-gamification.php',
        get_option( 'active_plugins', [] ),
        true
    );
    if ( is_multisite() ) {
        $gorilla_active = $gorilla_active || isset(
            get_site_option( 'active_sitewide_plugins', [] )['gorilla-loyalty-gamification/gorilla-loyalty-gamification.php']
        );
    }

    if ( $gorilla_active ) {
        // Gorilla Loyalty is still active - only remove WP Gamify-specific config,
        // keep shared XP/level/streak tables and user meta intact.
        $wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}gamify_levels_config`" );
        $wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}gamify_audit_log`" );
    } else {
        // Safe to remove everything.
        $tables = [
            $wpdb->prefix . 'gamify_xp_transactions',
            $wpdb->prefix . 'gamify_user_levels',
            $wpdb->prefix . 'gamify_streaks',
            $wpdb->prefix . 'gamify_levels_config',
            $wpdb->prefix . 'gamify_audit_log',
        ];

        foreach ( $tables as $table ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
        }

        // Delete all user meta related to the plugin (both _wpgamify_ and wpgamify_ prefixes).
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
            $wpdb->esc_like( '_wpgamify_' ) . '%',
            $wpdb->esc_like( 'wpgamify_' ) . '%'
        ) );
    }
}
