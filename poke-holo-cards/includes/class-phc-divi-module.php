<?php
/**
 * WooCommerce Holo Cards - Divi Builder Module
 *
 * Provides a native Divi Builder module for holographic card effects.
 *
 * @since   3.0.0
 * @package PokeHoloCards
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PHC_Divi_Module class.
 *
 * Extends ET_Builder_Module to provide a drag-and-drop holo card module
 * inside the Divi Builder.
 *
 * @since 3.0.0
 */
class PHC_Divi_Module extends ET_Builder_Module {

    /** @var string Module slug. */
    public $slug       = 'phc_holo_card';

    /** @var string Visual builder support. */
    public $vb_support = 'on';

    /**
     * Initialize the module.
     */
    public function init() {
        $this->name = esc_html__( 'Holo Card', 'poke-holo-cards' );
        $this->icon = 'N'; // Divi icon for image.

        $this->settings_modal_toggles = array(
            'general'  => array(
                'toggles' => array(
                    'main_content' => esc_html__( 'Card Content', 'poke-holo-cards' ),
                    'back_content' => esc_html__( 'Card Back (Flip)', 'poke-holo-cards' ),
                ),
            ),
            'advanced' => array(
                'toggles' => array(
                    'card_style' => esc_html__( 'Card Style', 'poke-holo-cards' ),
                ),
            ),
        );
    }

    /**
     * Build effect type options for field select.
     *
     * @return array
     */
    private function get_effect_options() {
        $options = array();
        $types   = function_exists( 'phc_get_effect_types' ) ? phc_get_effect_types() : array();
        foreach ( $types as $type ) {
            $label           = function_exists( 'phc_get_effect_label' ) ? phc_get_effect_label( $type ) : ucfirst( $type );
            $options[ $type ] = $label;
        }
        return $options;
    }

    /**
     * Define module fields.
     *
     * @return array
     */
    public function get_fields() {
        return array(
            'photo' => array(
                'label'              => esc_html__( 'Card Image', 'poke-holo-cards' ),
                'type'               => 'upload',
                'upload_button_text' => esc_html__( 'Choose Image', 'poke-holo-cards' ),
                'choose_text'        => esc_html__( 'Choose an Image', 'poke-holo-cards' ),
                'update_text'        => esc_html__( 'Set as Card Image', 'poke-holo-cards' ),
                'option_category'    => 'basic_option',
                'toggle_slug'        => 'main_content',
            ),
            'effect' => array(
                'label'           => esc_html__( 'Effect Type', 'poke-holo-cards' ),
                'type'            => 'select',
                'option_category' => 'basic_option',
                'options'         => $this->get_effect_options(),
                'default'         => 'holo',
                'toggle_slug'     => 'main_content',
            ),
            'card_width' => array(
                'label'           => esc_html__( 'Width', 'poke-holo-cards' ),
                'type'            => 'text',
                'option_category' => 'basic_option',
                'default'         => '300px',
                'description'     => esc_html__( 'CSS value (300px, 50%, 20vw)', 'poke-holo-cards' ),
                'toggle_slug'     => 'main_content',
            ),
            'showcase' => array(
                'label'           => esc_html__( 'Showcase Mode', 'poke-holo-cards' ),
                'type'            => 'yes_no_button',
                'option_category' => 'basic_option',
                'options'         => array(
                    'off' => esc_html__( 'Off', 'poke-holo-cards' ),
                    'on'  => esc_html__( 'On', 'poke-holo-cards' ),
                ),
                'default'     => 'off',
                'description' => esc_html__( 'Auto-rotate animation when idle.', 'poke-holo-cards' ),
                'toggle_slug' => 'main_content',
            ),
            'sparkle' => array(
                'label'           => esc_html__( 'Sparkle Overlay', 'poke-holo-cards' ),
                'type'            => 'yes_no_button',
                'option_category' => 'basic_option',
                'options'         => array(
                    'off' => esc_html__( 'Off', 'poke-holo-cards' ),
                    'on'  => esc_html__( 'On', 'poke-holo-cards' ),
                ),
                'default'     => 'off',
                'description' => esc_html__( 'Animated sparkle particles on hover.', 'poke-holo-cards' ),
                'toggle_slug' => 'main_content',
            ),
            'back_photo' => array(
                'label'              => esc_html__( 'Back Image', 'poke-holo-cards' ),
                'type'               => 'upload',
                'upload_button_text' => esc_html__( 'Choose Image', 'poke-holo-cards' ),
                'choose_text'        => esc_html__( 'Choose an Image', 'poke-holo-cards' ),
                'update_text'        => esc_html__( 'Set as Back Image', 'poke-holo-cards' ),
                'option_category'    => 'basic_option',
                'description'        => esc_html__( 'Optional. Double-click or press Space to flip.', 'poke-holo-cards' ),
                'toggle_slug'        => 'back_content',
            ),
            'glow_color' => array(
                'label'       => esc_html__( 'Glow Color', 'poke-holo-cards' ),
                'type'        => 'color-alpha',
                'default'     => '#58e0d9',
                'tab_slug'    => 'advanced',
                'toggle_slug' => 'card_style',
            ),
            'border_radius' => array(
                'label'       => esc_html__( 'Border Radius (%)', 'poke-holo-cards' ),
                'type'        => 'range',
                'default'     => '4.55%',
                'range_settings' => array(
                    'min'  => '0',
                    'max'  => '50',
                    'step' => '0.5',
                ),
                'tab_slug'    => 'advanced',
                'toggle_slug' => 'card_style',
            ),
            'spring_preset' => array(
                'label'       => esc_html__( 'Spring Preset', 'poke-holo-cards' ),
                'type'        => 'select',
                'options'     => array(
                    ''        => esc_html__( 'Default', 'poke-holo-cards' ),
                    'bouncy'  => esc_html__( 'Bouncy', 'poke-holo-cards' ),
                    'stiff'   => esc_html__( 'Stiff', 'poke-holo-cards' ),
                    'smooth'  => esc_html__( 'Smooth', 'poke-holo-cards' ),
                    'elastic' => esc_html__( 'Elastic', 'poke-holo-cards' ),
                ),
                'default'     => '',
                'tab_slug'    => 'advanced',
                'toggle_slug' => 'card_style',
            ),
        );
    }

    /**
     * Render the module on the frontend.
     *
     * @param array  $attrs       Unprocessed attributes.
     * @param string $content     Module content.
     * @param string $render_slug Module render slug.
     * @return string HTML output.
     */
    public function render( $attrs, $content, $render_slug ) {
        $img = $this->props['photo'];
        if ( empty( $img ) ) {
            return '';
        }

        wp_enqueue_style( 'phc-holo-cards' );
        wp_enqueue_script( 'phc-holo-cards' );

        $effect  = sanitize_html_class( $this->props['effect'] ?? 'holo' ) ?: 'holo';
        $classes = 'phc-card phc-effect-' . $effect;

        $showcase = ( $this->props['showcase'] === 'on' );
        $sparkle  = ( $this->props['sparkle'] === 'on' );

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
        if ( ! empty( $this->props['glow_color'] ) ) {
            $data .= ' data-phc-glow="' . esc_attr( $this->props['glow_color'] ) . '"';
        }

        $radius = $this->props['border_radius'];
        if ( $radius !== '' ) {
            $radius_val = (float) $radius;
            $data .= ' data-phc-radius="' . esc_attr( $radius_val ) . '"';
        }

        if ( ! empty( $this->props['spring_preset'] ) ) {
            $data .= ' data-phc-spring="' . esc_attr( $this->props['spring_preset'] ) . '"';
        }

        if ( ! empty( $this->props['back_photo'] ) ) {
            $data .= ' data-phc-back="' . esc_url( $this->props['back_photo'] ) . '"';
        }

        $width = function_exists( 'phc_sanitize_css_length' )
            ? phc_sanitize_css_length( $this->props['card_width'], '300px' )
            : esc_attr( $this->props['card_width'] );

        return sprintf(
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
