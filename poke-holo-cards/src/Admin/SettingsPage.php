<?php
/**
 * Admin settings page: registration, rendering, and AJAX handlers.
 *
 * @package PokeHoloCards\Admin
 * @since   3.0.0
 */

namespace PokeHoloCards\Admin;

use PokeHoloCards\Core\Settings;
use PokeHoloCards\Utils\Sanitizer;
use PokeHoloCards\Utils\EffectTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SettingsPage handles the admin UI under Settings > Holo Cards.
 */
class SettingsPage {

    /**
     * Register WordPress hooks.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_phc_reset_defaults', array( __CLASS__, 'ajax_reset' ) );
        add_action( 'wp_ajax_phc_export_settings', array( __CLASS__, 'ajax_export' ) );
        add_action( 'wp_ajax_phc_import_settings', array( __CLASS__, 'ajax_import' ) );

        // Analytics auto-cleanup cron.
        add_action( 'phc_analytics_cleanup', array( __CLASS__, 'do_analytics_cleanup' ) );

        // Analytics beacon (public, no auth needed - just increments counters).
        add_action( 'wp_ajax_phc_analytics_beacon', array( __CLASS__, 'ajax_analytics_beacon' ) );
        add_action( 'wp_ajax_nopriv_phc_analytics_beacon', array( __CLASS__, 'ajax_analytics_beacon' ) );
        add_action( 'wp_ajax_phc_reset_analytics', array( __CLASS__, 'ajax_reset_analytics' ) );
        add_action( 'wp_ajax_phc_load_preset', array( __CLASS__, 'ajax_load_preset' ) );
        add_action( 'wp_ajax_phc_save_preset', array( __CLASS__, 'ajax_save_preset' ) );
        add_action( 'wp_ajax_phc_delete_preset', array( __CLASS__, 'ajax_delete_preset' ) );
    }

    /**
     * Register the plugin settings page under Settings.
     */
    public static function add_menu() {
        add_options_page(
            __( 'WooCommerce Holo Cards', 'poke-holo-cards' ),
            __( 'Holo Cards', 'poke-holo-cards' ),
            'manage_options',
            'poke-holo-cards',
            array( __CLASS__, 'render' )
        );
    }

    /**
     * Register all plugin settings with the WordPress Settings API.
     */
    public static function register_settings() {
        // Yes/No fields.
        foreach ( array( 'phc_enabled', 'phc_woo_enabled', 'phc_gyroscope' ) as $field ) {
            register_setting( 'phc_settings_group', $field, array( 'sanitize_callback' => array( Sanitizer::class, 'yes_no' ) ) );
        }

        // Whitelist fields.
        register_setting( 'phc_settings_group', 'phc_woo_target', array( 'sanitize_callback' => array( Sanitizer::class, 'woo_target' ) ) );
        register_setting( 'phc_settings_group', 'phc_effect_type', array( 'sanitize_callback' => array( Sanitizer::class, 'effect_type' ) ) );

        // Float fields with ranges.
        register_setting( 'phc_settings_group', 'phc_hover_scale', array(
            'sanitize_callback' => function ( $v ) { return Sanitizer::float_range( $v, 1, 2 ); },
        ) );
        register_setting( 'phc_settings_group', 'phc_perspective', array(
            'sanitize_callback' => function ( $v ) { return intval( max( 200, min( 2000, $v ) ) ); },
        ) );
        register_setting( 'phc_settings_group', 'phc_spring_stiffness', array(
            'sanitize_callback' => function ( $v ) { return Sanitizer::float_range( $v, 0.01, 1 ); },
        ) );
        register_setting( 'phc_settings_group', 'phc_spring_damping', array(
            'sanitize_callback' => function ( $v ) { return Sanitizer::float_range( $v, 0.01, 1 ); },
        ) );
        register_setting( 'phc_settings_group', 'phc_glare_opacity', array(
            'sanitize_callback' => function ( $v ) { return Sanitizer::float_range( $v, 0, 1 ); },
        ) );
        register_setting( 'phc_settings_group', 'phc_shine_intensity', array(
            'sanitize_callback' => function ( $v ) { return Sanitizer::float_range( $v, 0, 3 ); },
        ) );
        register_setting( 'phc_settings_group', 'phc_border_radius', array(
            'sanitize_callback' => function ( $v ) { return Sanitizer::float_range( $v, 0, 50 ); },
        ) );

        // Color.
        register_setting( 'phc_settings_group', 'phc_glow_color', array( 'sanitize_callback' => 'sanitize_hex_color' ) );

        // CSS class.
        register_setting( 'phc_settings_group', 'phc_auto_init_class', array( 'sanitize_callback' => 'sanitize_html_class' ) );

        // Spring preset.
        register_setting( 'phc_settings_group', 'phc_spring_preset', array( 'sanitize_callback' => 'sanitize_text_field' ) );

        // Analytics retention days (0 = keep forever).
        register_setting( 'phc_settings_group', 'phc_analytics_retention_days', array(
            'sanitize_callback' => function ( $v ) { return max( 0, intval( $v ) ); },
        ) );
    }

    /**
     * Render the plugin settings page with tabbed UI.
     */
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $tabs       = array(
            'general'     => __( 'General', 'poke-holo-cards' ),
            'woocommerce' => __( 'WooCommerce', 'poke-holo-cards' ),
            'animation'   => __( 'Animation', 'poke-holo-cards' ),
            'shortcode'   => __( 'Shortcode Builder', 'poke-holo-cards' ),
            'analytics'   => __( 'Analytics', 'poke-holo-cards' ),
            'advanced'    => __( 'Advanced', 'poke-holo-cards' ),
        );
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
        if ( ! array_key_exists( $active_tab, $tabs ) ) {
            $active_tab = 'general';
        }

        include PHC_PLUGIN_DIR . 'src/Admin/views/settings-page.php';
    }

    /* ─── AJAX Handlers ─── */

    /**
     * AJAX: reset all plugin options to their default values.
     */
    public static function ajax_reset() {
        check_ajax_referer( 'phc_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( -1 );
        }

        foreach ( Settings::option_keys() as $o ) {
            delete_option( $o );
        }

        Settings::install_defaults();
        wp_send_json_success();
    }

    /**
     * AJAX: export current settings as JSON.
     */
    public static function ajax_export() {
        check_ajax_referer( 'phc_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( -1 );
        }

        $data = array(
            'enabled'          => get_option( 'phc_enabled', 'yes' ),
            'woo_enabled'      => get_option( 'phc_woo_enabled', 'yes' ),
            'woo_target'       => get_option( 'phc_woo_target', 'product_gallery' ),
            'effect_type'      => get_option( 'phc_effect_type', 'holo' ),
            'hover_scale'      => get_option( 'phc_hover_scale', '1.05' ),
            'perspective'      => get_option( 'phc_perspective', '600' ),
            'spring_stiffness' => get_option( 'phc_spring_stiffness', '0.066' ),
            'spring_damping'   => get_option( 'phc_spring_damping', '0.25' ),
            'glare_opacity'    => get_option( 'phc_glare_opacity', '0.8' ),
            'shine_intensity'  => get_option( 'phc_shine_intensity', '1' ),
            'glow_color'       => get_option( 'phc_glow_color', '#58e0d9' ),
            'border_radius'    => get_option( 'phc_border_radius', '4.55' ),
            'auto_init_class'  => get_option( 'phc_auto_init_class', 'phc-card' ),
            'gyroscope'        => get_option( 'phc_gyroscope', 'yes' ),
            'spring_preset'    => get_option( 'phc_spring_preset', '' ),
        );
        wp_send_json_success( $data );
    }

    /**
     * AJAX: import settings from a JSON string.
     */
    public static function ajax_import() {
        check_ajax_referer( 'phc_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( -1 );
        }

        $raw  = isset( $_POST['settings'] ) ? stripslashes( $_POST['settings'] ) : '';
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            wp_send_json_error( __( 'Invalid JSON', 'poke-holo-cards' ) );
        }

        $map = array(
            'enabled'          => array( 'option' => 'phc_enabled',          'sanitize' => array( Sanitizer::class, 'yes_no' ) ),
            'woo_enabled'      => array( 'option' => 'phc_woo_enabled',      'sanitize' => array( Sanitizer::class, 'yes_no' ) ),
            'woo_target'       => array( 'option' => 'phc_woo_target',       'sanitize' => array( Sanitizer::class, 'woo_target' ) ),
            'effect_type'      => array( 'option' => 'phc_effect_type',      'sanitize' => array( Sanitizer::class, 'effect_type' ) ),
            'hover_scale'      => array( 'option' => 'phc_hover_scale',      'sanitize' => 'floatval', 'min' => 1, 'max' => 2 ),
            'perspective'      => array( 'option' => 'phc_perspective',       'sanitize' => 'intval',   'min' => 200, 'max' => 2000 ),
            'spring_stiffness' => array( 'option' => 'phc_spring_stiffness', 'sanitize' => 'floatval', 'min' => 0.01, 'max' => 1 ),
            'spring_damping'   => array( 'option' => 'phc_spring_damping',   'sanitize' => 'floatval', 'min' => 0.01, 'max' => 1 ),
            'glare_opacity'    => array( 'option' => 'phc_glare_opacity',    'sanitize' => 'floatval', 'min' => 0, 'max' => 1 ),
            'shine_intensity'  => array( 'option' => 'phc_shine_intensity',  'sanitize' => 'floatval', 'min' => 0, 'max' => 3 ),
            'glow_color'       => array( 'option' => 'phc_glow_color',       'sanitize' => 'sanitize_hex_color' ),
            'border_radius'    => array( 'option' => 'phc_border_radius',    'sanitize' => 'floatval', 'min' => 0, 'max' => 50 ),
            'auto_init_class'  => array( 'option' => 'phc_auto_init_class',  'sanitize' => 'sanitize_html_class' ),
            'gyroscope'        => array( 'option' => 'phc_gyroscope',        'sanitize' => array( Sanitizer::class, 'yes_no' ) ),
            'spring_preset'    => array( 'option' => 'phc_spring_preset',    'sanitize' => 'sanitize_text_field' ),
        );

        foreach ( $map as $key => $config ) {
            if ( isset( $data[ $key ] ) ) {
                $sanitized = call_user_func( $config['sanitize'], $data[ $key ] );
                if ( isset( $config['min'] ) && is_numeric( $sanitized ) ) {
                    $sanitized = max( $config['min'], min( $config['max'], $sanitized ) );
                }
                if ( ( $sanitized !== null && $sanitized !== false && $sanitized !== '' ) || $key === 'spring_preset' ) {
                    update_option( $config['option'], $sanitized );
                }
            }
        }

        wp_send_json_success();
    }

    /**
     * Receive analytics beacon data from the frontend.
     *
     * Stores aggregated interaction stats in a transient (24h).
     * Data: effect type, hover duration (ms), click event.
     */
    public static function ajax_analytics_beacon() {
        // Rate limit: 1 beacon per IP per 5 seconds via transient.
        $ip_hash  = md5( sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        $rate_key = 'phc_beacon_' . $ip_hash;
        if ( get_transient( $rate_key ) ) {
            wp_send_json_success();
            return;
        }
        set_transient( $rate_key, 1, 5 );

        $effect   = sanitize_text_field( $_POST['effect'] ?? '' );
        $hover_ms = max( 0, intval( $_POST['hover_ms'] ?? 0 ) );
        $clicked  = ! empty( $_POST['clicked'] );

        if ( empty( $effect ) ) {
            wp_send_json_success();
            return;
        }

        $stats = get_option( 'phc_analytics_data', array() );
        if ( ! is_array( $stats ) ) {
            $stats = array();
        }

        if ( ! isset( $stats[ $effect ] ) ) {
            $stats[ $effect ] = array(
                'hovers'       => 0,
                'clicks'       => 0,
                'total_ms'     => 0,
            );
        }

        $stats[ $effect ]['hovers']++;
        $stats[ $effect ]['total_ms'] += $hover_ms;
        if ( $clicked ) {
            $stats[ $effect ]['clicks']++;
        }

        update_option( 'phc_analytics_data', $stats, false ); // autoload = false
        wp_send_json_success();
    }

    /**
     * Render the analytics tab content on the admin settings page.
     */
    public static function render_analytics_tab() {
        $stats = get_option( 'phc_analytics_data', array() );
        if ( empty( $stats ) ) {
            echo '<p style="text-align:center;color:#888;padding:40px 0;">No interaction data collected yet.</p>';
            return;
        }

        // Calculate totals.
        $total_hovers = 0;
        $total_clicks = 0;
        foreach ( $stats as $s ) {
            $total_hovers += $s['hovers'];
            $total_clicks += $s['clicks'];
        }

        // Sort by hovers descending.
        arsort( $stats );

        echo '<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:15px; margin-bottom:20px;">';
        echo '<div style="text-align:center; background:#f0f9ff; padding:15px; border-radius:10px;">';
        echo '<div style="font-size:24px; font-weight:800; color:#3b82f6;">' . number_format_i18n( $total_hovers ) . '</div>';
        echo '<div style="font-size:11px; color:#6b7280;">Total Hovers</div></div>';
        echo '<div style="text-align:center; background:#f0fdf4; padding:15px; border-radius:10px;">';
        echo '<div style="font-size:24px; font-weight:800; color:#22c55e;">' . number_format_i18n( $total_clicks ) . '</div>';
        echo '<div style="font-size:11px; color:#6b7280;">Total Clicks</div></div>';
        echo '<div style="text-align:center; background:#fef3c7; padding:15px; border-radius:10px;">';
        $avg_ms = $total_hovers > 0 ? round( array_sum( array_column( $stats, 'total_ms' ) ) / $total_hovers ) : 0;
        echo '<div style="font-size:24px; font-weight:800; color:#f59e0b;">' . number_format( $avg_ms / 1000, 1 ) . 's</div>';
        echo '<div style="font-size:11px; color:#6b7280;">Avg Hover Time</div></div>';
        echo '</div>';

        echo '<table class="widefat striped" style="font-size:13px;">';
        echo '<thead><tr><th>Effect</th><th>Hovers</th><th>Clicks</th><th>Avg Hover</th><th>CTR</th></tr></thead><tbody>';

        foreach ( $stats as $effect => $s ) {
            $avg_hover = $s['hovers'] > 0 ? round( $s['total_ms'] / $s['hovers'] / 1000, 1 ) : 0;
            $ctr       = $s['hovers'] > 0 ? round( ( $s['clicks'] / $s['hovers'] ) * 100, 1 ) : 0;
            $label     = EffectTypes::get_label( $effect );
            echo '<tr>';
            echo '<td><strong>' . esc_html( $label ) . '</strong></td>';
            echo '<td>' . number_format_i18n( $s['hovers'] ) . '</td>';
            echo '<td>' . number_format_i18n( $s['clicks'] ) . '</td>';
            echo '<td>' . $avg_hover . 's</td>';
            echo '<td>' . $ctr . '%</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p style="margin-top:10px;"><button type="button" class="button" id="phc-reset-analytics">Reset Analytics</button></p>';
        echo '<script>document.getElementById("phc-reset-analytics")&&document.getElementById("phc-reset-analytics").addEventListener("click",function(){if(confirm("Reset all interaction analytics data?")){var f=new FormData();f.append("action","phc_reset_analytics");f.append("_ajax_nonce","' . wp_create_nonce( 'phc_admin_nonce' ) . '");fetch(ajaxurl,{method:"POST",body:f}).then(function(){location.reload();})}});</script>';
    }

    /**
     * Reset analytics data.
     */
    public static function ajax_reset_analytics() {
        check_ajax_referer( 'phc_admin_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        delete_option( 'phc_analytics_data' );
        wp_send_json_success();
    }

    /**
     * Schedule the monthly analytics cleanup cron.
     * Called from Plugin::activate().
     */
    public static function schedule_cleanup_cron() {
        if ( ! wp_next_scheduled( 'phc_analytics_cleanup' ) ) {
            wp_schedule_event( time(), 'monthly', 'phc_analytics_cleanup' );
        }
    }

    /**
     * Unschedule the cleanup cron.
     * Called from Plugin::uninstall() or deactivation.
     */
    public static function unschedule_cleanup_cron() {
        $timestamp = wp_next_scheduled( 'phc_analytics_cleanup' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'phc_analytics_cleanup' );
        }
    }

    /**
     * Perform analytics cleanup based on retention period.
     * Resets analytics data if it exceeds the configured retention days.
     */
    public static function do_analytics_cleanup() {
        $retention_days = intval( get_option( 'phc_analytics_retention_days', 0 ) );
        if ( $retention_days <= 0 ) {
            return; // 0 = keep forever
        }

        $last_reset = get_option( 'phc_analytics_last_reset', '' );
        if ( empty( $last_reset ) ) {
            // First time: just record the date, don't wipe yet
            update_option( 'phc_analytics_last_reset', current_time( 'Y-m-d' ), false );
            return;
        }

        $last_reset_time = strtotime( $last_reset );
        $cutoff_time     = strtotime( "-{$retention_days} days" );

        if ( $last_reset_time <= $cutoff_time ) {
            delete_option( 'phc_analytics_data' );
            update_option( 'phc_analytics_last_reset', current_time( 'Y-m-d' ), false );
        }
    }

    /**
     * Built-in preset definitions.
     */
    public static function builtin_presets() {
        return array(
            '__pokemon' => array(
                'effect_type'      => 'holo',
                'hover_scale'      => '1.07',
                'perspective'      => '600',
                'spring_stiffness' => '0.066',
                'spring_damping'   => '0.25',
                'glare_opacity'    => '0.9',
                'shine_intensity'  => '1.2',
                'glow_color'       => '#58e0d9',
                'border_radius'    => '4.55',
                'spring_preset'    => 'bouncy',
            ),
            '__mtg' => array(
                'effect_type'      => 'cosmos',
                'hover_scale'      => '1.04',
                'perspective'      => '800',
                'spring_stiffness' => '0.05',
                'spring_damping'   => '0.3',
                'glare_opacity'    => '0.6',
                'shine_intensity'  => '0.8',
                'glow_color'       => '#9b59b6',
                'border_radius'    => '3',
                'spring_preset'    => 'smooth',
            ),
            '__minimalist' => array(
                'effect_type'      => 'basic',
                'hover_scale'      => '1.02',
                'perspective'      => '1000',
                'spring_stiffness' => '0.04',
                'spring_damping'   => '0.35',
                'glare_opacity'    => '0.3',
                'shine_intensity'  => '0.4',
                'glow_color'       => '#888888',
                'border_radius'    => '8',
                'spring_preset'    => 'stiff',
            ),
            '__neon' => array(
                'effect_type'      => 'neon',
                'hover_scale'      => '1.06',
                'perspective'      => '500',
                'spring_stiffness' => '0.08',
                'spring_damping'   => '0.2',
                'glare_opacity'    => '1',
                'shine_intensity'  => '1.5',
                'glow_color'       => '#00ff88',
                'border_radius'    => '4',
                'spring_preset'    => 'elastic',
            ),
            '__retro' => array(
                'effect_type'      => 'vintage',
                'hover_scale'      => '1.03',
                'perspective'      => '700',
                'spring_stiffness' => '0.05',
                'spring_damping'   => '0.3',
                'glare_opacity'    => '0.5',
                'shine_intensity'  => '0.6',
                'glow_color'       => '#d4a574',
                'border_radius'    => '6',
                'spring_preset'    => 'smooth',
            ),
        );
    }

    /**
     * AJAX: Load a preset and return its settings.
     */
    public static function ajax_load_preset() {
        check_ajax_referer( 'phc_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $name = sanitize_text_field( $_POST['preset'] ?? '' );
        if ( empty( $name ) ) {
            wp_send_json_error( 'No preset selected' );
        }

        $builtins = self::builtin_presets();
        if ( isset( $builtins[ $name ] ) ) {
            wp_send_json_success( $builtins[ $name ] );
            return;
        }

        $custom = get_option( 'phc_presets', array() );
        if ( isset( $custom[ $name ] ) ) {
            wp_send_json_success( $custom[ $name ] );
            return;
        }

        wp_send_json_error( 'Preset not found' );
    }

    /**
     * AJAX: Save current settings as a named preset.
     */
    public static function ajax_save_preset() {
        check_ajax_referer( 'phc_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $name = sanitize_text_field( $_POST['name'] ?? '' );
        if ( empty( $name ) || strlen( $name ) > 50 ) {
            wp_send_json_error( 'Invalid preset name' );
        }

        // Don't allow overwriting builtins.
        if ( strpos( $name, '__' ) === 0 ) {
            wp_send_json_error( 'Reserved preset name' );
        }

        $s = Settings::get_all( true );
        $preset_data = array(
            'effect_type'      => $s['effect_type'],
            'hover_scale'      => (string) $s['hover_scale'],
            'perspective'      => (string) $s['perspective'],
            'spring_stiffness' => (string) $s['spring_stiffness'],
            'spring_damping'   => (string) $s['spring_damping'],
            'glare_opacity'    => (string) $s['glare_opacity'],
            'shine_intensity'  => (string) $s['shine_intensity'],
            'glow_color'       => $s['glow_color'],
            'border_radius'    => (string) $s['border_radius'],
            'spring_preset'    => $s['spring_preset'],
        );

        $presets = get_option( 'phc_presets', array() );
        $presets[ $name ] = $preset_data;
        update_option( 'phc_presets', $presets );

        wp_send_json_success( array( 'name' => $name ) );
    }

    /**
     * AJAX: Delete a custom preset.
     */
    public static function ajax_delete_preset() {
        check_ajax_referer( 'phc_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $name = sanitize_text_field( $_POST['preset'] ?? '' );
        if ( strpos( $name, '__' ) === 0 ) {
            wp_send_json_error( 'Cannot delete built-in presets' );
        }

        $presets = get_option( 'phc_presets', array() );
        if ( ! isset( $presets[ $name ] ) ) {
            wp_send_json_error( 'Preset not found' );
        }

        unset( $presets[ $name ] );
        update_option( 'phc_presets', $presets );

        wp_send_json_success();
    }
}
