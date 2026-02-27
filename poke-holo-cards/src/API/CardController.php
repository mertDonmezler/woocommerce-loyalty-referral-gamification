<?php
/**
 * REST API controller for headless card rendering.
 *
 * @package PokeHoloCards\API
 * @since   3.0.0
 */

namespace PokeHoloCards\API;

use PokeHoloCards\Core\Settings;
use PokeHoloCards\Utils\EffectTypes;
use PokeHoloCards\Utils\Sanitizer;
use PokeHoloCards\Frontend\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CardController registers REST API routes under the phc/v1 namespace.
 */
class CardController {

    /** @var string REST namespace. */
    const NAMESPACE = 'phc/v1';

    /**
     * Register WordPress hooks.
     */
    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    /**
     * Register all REST routes.
     */
    public static function register_routes() {
        // GET /phc/v1/settings - Read current plugin settings (public).
        register_rest_route( self::NAMESPACE, '/settings', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'get_settings' ),
            'permission_callback' => '__return_true',
        ) );

        // GET /phc/v1/effects - List available effect types (public).
        register_rest_route( self::NAMESPACE, '/effects', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'get_effects' ),
            'permission_callback' => '__return_true',
        ) );

        // POST /phc/v1/render - Render a card from attributes (public).
        register_rest_route( self::NAMESPACE, '/render', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'render_card' ),
            'permission_callback' => '__return_true',
            'args'                => self::render_args(),
        ) );

        // GET /phc/v1/presets - List available presets (admin only).
        register_rest_route( self::NAMESPACE, '/presets', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'get_presets' ),
            'permission_callback' => array( __CLASS__, 'admin_permission' ),
        ) );

        // POST /phc/v1/settings - Update plugin settings (admin only).
        register_rest_route( self::NAMESPACE, '/settings', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'update_settings' ),
            'permission_callback' => array( __CLASS__, 'admin_permission' ),
        ) );
    }

    /**
     * Admin-level permission check.
     *
     * @return bool
     */
    public static function admin_permission() {
        return current_user_can( 'manage_options' );
    }

    /**
     * GET /phc/v1/settings
     *
     * @return \WP_REST_Response
     */
    public static function get_settings() {
        $s = Settings::get_all( true );
        return rest_ensure_response( $s );
    }

    /**
     * POST /phc/v1/settings
     *
     * @param \WP_REST_Request $request Full request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public static function update_settings( $request ) {
        $body     = $request->get_json_params();
        $defaults = Settings::defaults();
        $updated  = array();

        // Map of short key -> option key + sanitizer.
        $field_map = array(
            'enabled'          => array( 'key' => 'phc_enabled',          'sanitize' => array( Sanitizer::class, 'yes_no' ) ),
            'woo_enabled'      => array( 'key' => 'phc_woo_enabled',      'sanitize' => array( Sanitizer::class, 'yes_no' ) ),
            'woo_target'       => array( 'key' => 'phc_woo_target',       'sanitize' => array( Sanitizer::class, 'woo_target' ) ),
            'effect_type'      => array( 'key' => 'phc_effect_type',      'sanitize' => array( Sanitizer::class, 'effect_type' ) ),
            'hover_scale'      => array( 'key' => 'phc_hover_scale',      'sanitize' => 'floatval' ),
            'perspective'      => array( 'key' => 'phc_perspective',      'sanitize' => 'intval' ),
            'spring_stiffness' => array( 'key' => 'phc_spring_stiffness', 'sanitize' => 'floatval' ),
            'spring_damping'   => array( 'key' => 'phc_spring_damping',   'sanitize' => 'floatval' ),
            'glare_opacity'    => array( 'key' => 'phc_glare_opacity',    'sanitize' => 'floatval' ),
            'shine_intensity'  => array( 'key' => 'phc_shine_intensity',  'sanitize' => 'floatval' ),
            'glow_color'       => array( 'key' => 'phc_glow_color',       'sanitize' => 'sanitize_hex_color' ),
            'border_radius'    => array( 'key' => 'phc_border_radius',    'sanitize' => 'floatval' ),
            'auto_init_class'  => array( 'key' => 'phc_auto_init_class',  'sanitize' => 'sanitize_html_class' ),
            'gyroscope'        => array( 'key' => 'phc_gyroscope',        'sanitize' => array( Sanitizer::class, 'yes_no' ) ),
            'spring_preset'    => array( 'key' => 'phc_spring_preset',    'sanitize' => 'sanitize_text_field' ),
        );

        foreach ( $body as $short_key => $value ) {
            if ( ! isset( $field_map[ $short_key ] ) ) {
                continue;
            }
            $map   = $field_map[ $short_key ];
            $clean = call_user_func( $map['sanitize'], $value );
            update_option( $map['key'], $clean );
            $updated[ $short_key ] = $clean;
        }

        if ( empty( $updated ) ) {
            return new \WP_Error( 'phc_no_fields', __( 'No valid settings fields provided.', 'poke-holo-cards' ), array( 'status' => 400 ) );
        }

        // Invalidate cached settings.
        Settings::get_all( true );

        return rest_ensure_response( array(
            'updated'  => $updated,
            'settings' => Settings::get_all( true ),
        ) );
    }

    /**
     * GET /phc/v1/effects
     *
     * @return \WP_REST_Response
     */
    public static function get_effects() {
        $types  = EffectTypes::get_all();
        $result = array();
        foreach ( $types as $slug ) {
            $result[] = array(
                'slug'  => $slug,
                'label' => EffectTypes::get_label( $slug ),
            );
        }
        return rest_ensure_response( $result );
    }

    /**
     * POST /phc/v1/render
     *
     * @param \WP_REST_Request $request Full request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public static function render_card( $request ) {
        $img = $request->get_param( 'img' );
        if ( empty( $img ) ) {
            return new \WP_Error(
                'phc_missing_img',
                __( 'The img parameter is required.', 'poke-holo-cards' ),
                array( 'status' => 400 )
            );
        }

        $atts = array(
            'img'      => sanitize_url( $img ),
            'alt'      => sanitize_text_field( $request->get_param( 'alt' ) ?: '' ),
            'width'    => Sanitizer::css_length( $request->get_param( 'width' ) ?: '300px', '300px' ),
            'effect'   => Sanitizer::effect_type( $request->get_param( 'effect' ) ?: 'holo' ),
            'class'    => sanitize_text_field( $request->get_param( 'class' ) ?: '' ),
            'showcase' => Sanitizer::yes_no( $request->get_param( 'showcase' ) ?: 'no' ),
            'sparkle'  => Sanitizer::yes_no( $request->get_param( 'sparkle' ) ?: 'no' ),
            'glow'     => sanitize_hex_color( $request->get_param( 'glow' ) ?: '' ) ?: '',
            'radius'   => $request->get_param( 'radius' ) !== null ? Sanitizer::float_range( $request->get_param( 'radius' ), 0, 50 ) : '',
            'back'     => sanitize_url( $request->get_param( 'back' ) ?: '' ),
            'back_alt' => sanitize_text_field( $request->get_param( 'back_alt' ) ?: '' ),
            'spring'   => sanitize_text_field( $request->get_param( 'spring' ) ?: '' ),
            'url'      => sanitize_url( $request->get_param( 'url' ) ?: '' ),
            'target'   => sanitize_text_field( $request->get_param( 'target' ) ?: '_self' ),
        );

        $html = Shortcode::render( $atts );

        return rest_ensure_response( array(
            'html'       => $html,
            'attributes' => $atts,
        ) );
    }

    /**
     * GET /phc/v1/presets
     *
     * @return \WP_REST_Response
     */
    public static function get_presets() {
        $builtin = \PokeHoloCards\Admin\SettingsPage::builtin_presets();
        $custom  = get_option( 'phc_presets', array() );

        $result = array(
            'builtin' => array(),
            'custom'  => array(),
        );

        foreach ( $builtin as $name => $values ) {
            $result['builtin'][] = array(
                'name'   => $name,
                'values' => $values,
            );
        }

        foreach ( $custom as $name => $values ) {
            $result['custom'][] = array(
                'name'   => $name,
                'values' => $values,
            );
        }

        return rest_ensure_response( $result );
    }

    /**
     * Argument schema for the render endpoint.
     *
     * @return array
     */
    private static function render_args() {
        return array(
            'img' => array(
                'required'          => true,
                'type'              => 'string',
                'format'            => 'uri',
                'description'       => __( 'Image URL for the card front.', 'poke-holo-cards' ),
                'sanitize_callback' => 'sanitize_url',
            ),
            'alt' => array(
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'width' => array(
                'type'    => 'string',
                'default' => '300px',
            ),
            'effect' => array(
                'type'    => 'string',
                'default' => 'holo',
                'enum'    => EffectTypes::get_all(),
            ),
            'class' => array(
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'showcase' => array(
                'type'    => 'string',
                'default' => 'no',
                'enum'    => array( 'yes', 'no' ),
            ),
            'sparkle' => array(
                'type'    => 'string',
                'default' => 'no',
                'enum'    => array( 'yes', 'no' ),
            ),
            'glow' => array(
                'type'    => 'string',
                'default' => '',
            ),
            'radius' => array(
                'type' => 'number',
            ),
            'back' => array(
                'type'              => 'string',
                'format'            => 'uri',
                'default'           => '',
                'sanitize_callback' => 'sanitize_url',
            ),
            'back_alt' => array(
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'spring' => array(
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'url' => array(
                'type'              => 'string',
                'format'            => 'uri',
                'default'           => '',
                'sanitize_callback' => 'sanitize_url',
            ),
            'target' => array(
                'type'    => 'string',
                'default' => '_self',
                'enum'    => array( '_self', '_blank', '_parent', '_top' ),
            ),
        );
    }
}
