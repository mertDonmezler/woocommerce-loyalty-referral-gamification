<?php
/**
 * WooCommerce Holo Cards - Bricks Builder Element
 *
 * Provides a native Bricks Builder element for holographic card effects.
 *
 * @since   3.0.0
 * @package PokeHoloCards
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PHC_Bricks_Element class.
 *
 * Extends \Bricks\Element to provide a drag-and-drop holo card element
 * inside Bricks Builder.
 *
 * @since 3.0.0
 */
class PHC_Bricks_Element extends \Bricks\Element {

    /** @var string Element category. */
    public $category = 'media';

    /** @var string Element name/slug. */
    public $name = 'phc-holo-card';

    /** @var string Element icon CSS class. */
    public $icon = 'ti-image';

    /** @var array CSS script/style dependencies. */
    public $scripts = array( 'phc-holo-cards' );

    /** @var array CSS style dependencies. */
    public $css = array( 'phc-holo-cards' );

    /**
     * Return element label.
     *
     * @return string
     */
    public function get_label() {
        return esc_html__( 'Holo Card', 'poke-holo-cards' );
    }

    /**
     * Return element keywords for search.
     *
     * @return array
     */
    public function get_keywords() {
        return array( 'holo', 'card', 'holographic', '3d', 'effect', 'pokemon' );
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
     * Define element controls (fields).
     */
    public function set_controls() {

        /* ── Card Image ─────────────────────────────────── */
        $this->controls['photo'] = array(
            'tab'   => 'content',
            'label' => esc_html__( 'Card Image', 'poke-holo-cards' ),
            'type'  => 'image',
        );

        $this->controls['effect'] = array(
            'tab'     => 'content',
            'label'   => esc_html__( 'Effect Type', 'poke-holo-cards' ),
            'type'    => 'select',
            'options' => $this->get_effect_options(),
            'default' => 'holo',
        );

        $this->controls['card_width'] = array(
            'tab'         => 'content',
            'label'       => esc_html__( 'Width', 'poke-holo-cards' ),
            'type'        => 'text',
            'default'     => '300px',
            'description' => esc_html__( 'CSS value (300px, 50%, 20vw)', 'poke-holo-cards' ),
        );

        $this->controls['showcase'] = array(
            'tab'         => 'content',
            'label'       => esc_html__( 'Showcase Mode', 'poke-holo-cards' ),
            'type'        => 'checkbox',
            'default'     => false,
            'description' => esc_html__( 'Auto-rotate animation when idle.', 'poke-holo-cards' ),
        );

        $this->controls['sparkle'] = array(
            'tab'         => 'content',
            'label'       => esc_html__( 'Sparkle Overlay', 'poke-holo-cards' ),
            'type'        => 'checkbox',
            'default'     => false,
            'description' => esc_html__( 'Animated sparkle particles on hover.', 'poke-holo-cards' ),
        );

        /* ── Card Back ──────────────────────────────────── */
        $this->controls['backSeparator'] = array(
            'tab'   => 'content',
            'type'  => 'separator',
            'label' => esc_html__( 'Card Back (Flip)', 'poke-holo-cards' ),
        );

        $this->controls['back_photo'] = array(
            'tab'         => 'content',
            'label'       => esc_html__( 'Back Image', 'poke-holo-cards' ),
            'type'        => 'image',
            'description' => esc_html__( 'Optional. Double-click or press Space to flip.', 'poke-holo-cards' ),
        );

        /* ── Style Controls ─────────────────────────────── */
        $this->controls['glow_color'] = array(
            'tab'     => 'style',
            'label'   => esc_html__( 'Glow Color', 'poke-holo-cards' ),
            'type'    => 'color',
            'default' => array( 'hex' => '#58e0d9' ),
        );

        $this->controls['border_radius'] = array(
            'tab'     => 'style',
            'label'   => esc_html__( 'Border Radius (%)', 'poke-holo-cards' ),
            'type'    => 'number',
            'min'     => 0,
            'max'     => 50,
            'step'    => 0.5,
            'default' => 4.55,
        );

        $this->controls['spring_preset'] = array(
            'tab'     => 'style',
            'label'   => esc_html__( 'Spring Preset', 'poke-holo-cards' ),
            'type'    => 'select',
            'options' => array(
                ''        => esc_html__( 'Default', 'poke-holo-cards' ),
                'bouncy'  => esc_html__( 'Bouncy', 'poke-holo-cards' ),
                'stiff'   => esc_html__( 'Stiff', 'poke-holo-cards' ),
                'smooth'  => esc_html__( 'Smooth', 'poke-holo-cards' ),
                'elastic' => esc_html__( 'Elastic', 'poke-holo-cards' ),
            ),
            'default' => '',
        );
    }

    /**
     * Render element on the frontend.
     */
    public function render() {
        $settings = $this->settings;

        // Get image URL from Bricks image control.
        $img = '';
        if ( ! empty( $settings['photo']['url'] ) ) {
            $img = $settings['photo']['url'];
        } elseif ( ! empty( $settings['photo']['id'] ) ) {
            $img = wp_get_attachment_url( $settings['photo']['id'] );
        }

        if ( empty( $img ) ) {
            return;
        }

        $effect  = sanitize_html_class( $settings['effect'] ?? 'holo' );
        $classes = 'phc-card phc-effect-' . $effect;

        $showcase = ! empty( $settings['showcase'] );
        $sparkle  = ! empty( $settings['sparkle'] );

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

        // Glow color.
        $glow = '';
        if ( ! empty( $settings['glow_color'] ) && is_array( $settings['glow_color'] ) && ! empty( $settings['glow_color']['hex'] ) ) {
            $glow = $settings['glow_color']['hex'];
        } elseif ( ! empty( $settings['glow_color'] ) && is_string( $settings['glow_color'] ) ) {
            $glow = $settings['glow_color'];
        }
        if ( $glow ) {
            $data .= ' data-phc-glow="' . esc_attr( $glow ) . '"';
        }

        // Border radius.
        if ( isset( $settings['border_radius'] ) && $settings['border_radius'] !== '' ) {
            $data .= ' data-phc-radius="' . esc_attr( (float) $settings['border_radius'] ) . '"';
        }

        // Spring preset.
        if ( ! empty( $settings['spring_preset'] ) ) {
            $data .= ' data-phc-spring="' . esc_attr( $settings['spring_preset'] ) . '"';
        }

        // Back image.
        $back_url = '';
        if ( ! empty( $settings['back_photo']['url'] ) ) {
            $back_url = $settings['back_photo']['url'];
        } elseif ( ! empty( $settings['back_photo']['id'] ) ) {
            $back_url = wp_get_attachment_url( $settings['back_photo']['id'] );
        }
        if ( $back_url ) {
            $data .= ' data-phc-back="' . esc_url( $back_url ) . '"';
        }

        $width = function_exists( 'phc_sanitize_css_length' )
            ? phc_sanitize_css_length( $settings['card_width'] ?? '300px', '300px' )
            : esc_attr( $settings['card_width'] ?? '300px' );

        echo "<div {$this->render_attributes( '_root' )}>";

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

        echo '</div>';
    }
}
