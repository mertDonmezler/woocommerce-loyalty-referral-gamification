<?php
/**
 * WooCommerce Holo Cards - Beaver Builder Module
 *
 * Provides a native Beaver Builder module for holographic card effects.
 *
 * @since   3.0.0
 * @package PokeHoloCards
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PHC_BB_Module class.
 *
 * Extends FLBuilderModule to provide a drag-and-drop holo card module
 * inside Beaver Builder.
 *
 * @since 3.0.0
 */
class PHC_BB_Module extends FLBuilderModule {

    /**
     * Constructor – define module metadata.
     */
    public function __construct() {
        parent::__construct( array(
            'name'            => __( 'Holo Card', 'poke-holo-cards' ),
            'description'     => __( '3D Holographic card effect for any image.', 'poke-holo-cards' ),
            'group'           => __( 'WooCommerce Holo Cards', 'poke-holo-cards' ),
            'category'        => __( 'Media', 'poke-holo-cards' ),
            'dir'             => PHC_PLUGIN_DIR . 'includes/phc-bb-holo-card/',
            'url'             => PHC_PLUGIN_URL . 'includes/phc-bb-holo-card/',
            'icon'            => 'format-image.svg',
            'partial_refresh' => true,
        ) );

        $this->add_css( 'phc-holo-cards' );
        $this->add_js( 'phc-holo-cards' );
    }

    /**
     * Build effect type options for field select.
     *
     * @return array
     */
    private static function get_effect_options() {
        $options = array();
        $types   = function_exists( 'phc_get_effect_types' ) ? phc_get_effect_types() : array();
        foreach ( $types as $type ) {
            $label           = function_exists( 'phc_get_effect_label' ) ? phc_get_effect_label( $type ) : ucfirst( $type );
            $options[ $type ] = $label;
        }
        return $options;
    }

    /**
     * Register the module with Beaver Builder.
     */
    public static function register() {
        FLBuilder::register_module( __CLASS__, array(

            'phc-content' => array(
                'title'    => __( 'Card Content', 'poke-holo-cards' ),
                'sections' => array(
                    'image' => array(
                        'title'  => __( 'Image', 'poke-holo-cards' ),
                        'fields' => array(
                            'photo' => array(
                                'type'  => 'photo',
                                'label' => __( 'Card Image', 'poke-holo-cards' ),
                                'show_remove' => true,
                            ),
                            'effect' => array(
                                'type'    => 'select',
                                'label'   => __( 'Effect Type', 'poke-holo-cards' ),
                                'default' => 'holo',
                                'options' => self::get_effect_options(),
                            ),
                            'width' => array(
                                'type'        => 'text',
                                'label'       => __( 'Width', 'poke-holo-cards' ),
                                'default'     => '300px',
                                'description' => __( 'CSS value (300px, 50%, 20vw)', 'poke-holo-cards' ),
                                'size'        => 10,
                            ),
                            'showcase' => array(
                                'type'    => 'select',
                                'label'   => __( 'Showcase Mode', 'poke-holo-cards' ),
                                'default' => 'no',
                                'options' => array(
                                    'no'  => __( 'Off', 'poke-holo-cards' ),
                                    'yes' => __( 'On', 'poke-holo-cards' ),
                                ),
                                'help' => __( 'Auto-rotate animation when idle.', 'poke-holo-cards' ),
                            ),
                            'sparkle' => array(
                                'type'    => 'select',
                                'label'   => __( 'Sparkle Overlay', 'poke-holo-cards' ),
                                'default' => 'no',
                                'options' => array(
                                    'no'  => __( 'Off', 'poke-holo-cards' ),
                                    'yes' => __( 'On', 'poke-holo-cards' ),
                                ),
                                'help' => __( 'Animated sparkle particles on hover.', 'poke-holo-cards' ),
                            ),
                        ),
                    ),
                    'back' => array(
                        'title'  => __( 'Card Back (Flip)', 'poke-holo-cards' ),
                        'fields' => array(
                            'back_photo' => array(
                                'type'  => 'photo',
                                'label' => __( 'Back Image', 'poke-holo-cards' ),
                                'help'  => __( 'Optional. Double-click or press Space to flip.', 'poke-holo-cards' ),
                                'show_remove' => true,
                            ),
                        ),
                    ),
                ),
            ),

            'phc-style' => array(
                'title'    => __( 'Card Style', 'poke-holo-cards' ),
                'sections' => array(
                    'appearance' => array(
                        'title'  => __( 'Appearance', 'poke-holo-cards' ),
                        'fields' => array(
                            'glow_color' => array(
                                'type'       => 'color',
                                'label'      => __( 'Glow Color', 'poke-holo-cards' ),
                                'default'    => '58e0d9',
                                'show_alpha' => false,
                                'show_reset' => true,
                            ),
                            'border_radius' => array(
                                'type'    => 'unit',
                                'label'   => __( 'Border Radius (%)', 'poke-holo-cards' ),
                                'default' => '4.55',
                                'units'   => array( '%' ),
                                'slider'  => array(
                                    '%' => array( 'min' => 0, 'max' => 50, 'step' => 0.5 ),
                                ),
                            ),
                            'spring_preset' => array(
                                'type'    => 'select',
                                'label'   => __( 'Spring Preset', 'poke-holo-cards' ),
                                'default' => '',
                                'options' => array(
                                    ''        => __( 'Default', 'poke-holo-cards' ),
                                    'bouncy'  => __( 'Bouncy', 'poke-holo-cards' ),
                                    'stiff'   => __( 'Stiff', 'poke-holo-cards' ),
                                    'smooth'  => __( 'Smooth', 'poke-holo-cards' ),
                                    'elastic' => __( 'Elastic', 'poke-holo-cards' ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),

        ) );
    }
}

/**
 * Render callback used by the module template.
 */
if ( ! function_exists( 'phc_bb_render_card' ) ) {
    /**
     * Render the holo card HTML from BB module settings.
     *
     * @param object $settings Module settings object.
     */
    function phc_bb_render_card( $settings ) {
        $img = '';
        if ( ! empty( $settings->photo_src ) ) {
            $img = $settings->photo_src;
        } elseif ( ! empty( $settings->photo ) ) {
            $img = wp_get_attachment_url( $settings->photo );
        }

        if ( empty( $img ) ) {
            return;
        }

        $effect  = sanitize_html_class( $settings->effect ?? 'holo' ) ?: 'holo';
        $classes = 'phc-card phc-effect-' . $effect;

        $showcase = ( $settings->showcase === 'yes' );
        $sparkle  = ( $settings->sparkle === 'yes' );

        if ( $showcase ) {
            $classes .= ' phc-showcase';
        }

        $data = ' data-phc-effect="' . esc_attr( $effect ) . '"';

        if ( $showcase ) {
            $data .= ' data-phc-showcase="true"';
        }
        if ( $sparkle ) {
            $data .= ' data-phc-sparkle="true"';
        }
        if ( ! empty( $settings->glow_color ) ) {
            $data .= ' data-phc-glow="#' . esc_attr( ltrim( $settings->glow_color, '#' ) ) . '"';
        }
        if ( isset( $settings->border_radius ) && $settings->border_radius !== '' ) {
            $data .= ' data-phc-radius="' . esc_attr( $settings->border_radius ) . '"';
        }
        if ( ! empty( $settings->spring_preset ) ) {
            $data .= ' data-phc-spring="' . esc_attr( $settings->spring_preset ) . '"';
        }

        $back_url = '';
        if ( ! empty( $settings->back_photo_src ) ) {
            $back_url = $settings->back_photo_src;
        } elseif ( ! empty( $settings->back_photo ) ) {
            $back_url = wp_get_attachment_url( $settings->back_photo );
        }
        if ( $back_url ) {
            $data .= ' data-phc-back="' . esc_url( $back_url ) . '"';
        }

        $width = function_exists( 'phc_sanitize_css_length' )
            ? phc_sanitize_css_length( $settings->width, '300px' )
            : esc_attr( $settings->width );

        printf(
            '<div class="%s" style="width:%s"%s>' .
                '<div class="phc-card__translater">' .
                    '<div class="phc-card__rotator">' .
                        '<img class="phc-card__front" src="%s" alt="" loading="lazy" />' .
                        '<div class="phc-card__shine"></div>' .
                        '<div class="phc-card__glare"></div>' .
                    '</div>' .
                '</div>' .
            '</div>',
            esc_attr( $classes ),
            esc_attr( $width ),
            $data,
            esc_url( $img )
        );
    }
}
