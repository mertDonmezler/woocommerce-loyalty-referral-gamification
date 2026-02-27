<?php
/**
 * Settings cache layer.
 *
 * @package PokeHoloCards\Core
 * @since   3.0.0
 */

namespace PokeHoloCards\Core;

use PokeHoloCards\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings provides a cached accessor for all plugin options.
 */
class Settings {

    /** @var array|null Cached settings array. */
    private static $cache = null;

    /**
     * Default option values.
     *
     * @return array
     */
    public static function defaults() {
        return array(
            'phc_enabled'          => 'yes',
            'phc_woo_enabled'      => 'yes',
            'phc_woo_target'       => 'product_gallery',
            'phc_effect_type'      => 'holo',
            'phc_hover_scale'      => '1.05',
            'phc_perspective'      => '600',
            'phc_spring_stiffness' => '0.066',
            'phc_spring_damping'   => '0.25',
            'phc_glare_opacity'    => '0.8',
            'phc_shine_intensity'  => '1',
            'phc_glow_color'       => '#58e0d9',
            'phc_border_radius'    => '4.55',
            'phc_auto_init_class'  => 'phc-card',
            'phc_gyroscope'        => 'yes',
            'phc_spring_preset'    => '',
        );
    }

    /**
     * Return all plugin settings as a cached array.
     *
     * @param bool $force_refresh Force a fresh read from the database.
     * @return array
     */
    public static function get_all( $force_refresh = false ) {
        if ( self::$cache !== null && ! $force_refresh ) {
            return self::$cache;
        }

        self::$cache = array(
            'enabled'          => get_option( 'phc_enabled', 'yes' ),
            'woo_enabled'      => get_option( 'phc_woo_enabled', 'yes' ),
            'woo_target'       => get_option( 'phc_woo_target', 'product_gallery' ),
            'effect_type'      => Sanitizer::effect_type( get_option( 'phc_effect_type', 'holo' ) ),
            'hover_scale'      => floatval( get_option( 'phc_hover_scale', '1.05' ) ),
            'perspective'      => intval( get_option( 'phc_perspective', '600' ) ),
            'spring_stiffness' => floatval( get_option( 'phc_spring_stiffness', '0.066' ) ),
            'spring_damping'   => floatval( get_option( 'phc_spring_damping', '0.25' ) ),
            'glare_opacity'    => floatval( get_option( 'phc_glare_opacity', '0.8' ) ),
            'shine_intensity'  => floatval( get_option( 'phc_shine_intensity', '1' ) ),
            'glow_color'       => sanitize_hex_color( get_option( 'phc_glow_color', '#58e0d9' ) ) ?: '#58e0d9',
            'border_radius'    => floatval( get_option( 'phc_border_radius', '4.55' ) ),
            'auto_init_class'  => sanitize_html_class( get_option( 'phc_auto_init_class', 'phc-card' ) ),
            'gyroscope'        => get_option( 'phc_gyroscope', 'yes' ) === 'yes',
            'spring_preset'    => sanitize_text_field( get_option( 'phc_spring_preset', '' ) ),
        );

        return self::$cache;
    }

    /**
     * Option key list (used for reset/uninstall).
     *
     * @return string[]
     */
    public static function option_keys() {
        return array_keys( self::defaults() );
    }

    /**
     * Install default options (add_option, no overwrite).
     */
    public static function install_defaults() {
        foreach ( self::defaults() as $key => $value ) {
            if ( get_option( $key ) === false ) {
                add_option( $key, $value );
            }
        }
    }
}
