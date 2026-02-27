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

$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpgamify\_%'"
);

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

$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_wpgamify\_%'
        OR option_name LIKE '_transient_timeout_wpgamify\_%'"
);

/* ─── Drop tables if not keeping data ──────────────────────────────── */

if ( $keep_data !== 'yes' ) {
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

    // Delete all user meta related to the plugin.
    $wpdb->query(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wpgamify\_%'"
    );
}
