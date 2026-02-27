<?php
/**
 * WooCommerce Holo Cards - Elementor Widget
 *
 * Provides a native Elementor widget for adding holographic card effects.
 *
 * @since   2.0.0
 * @package PokeHoloCards
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PHC_Elementor_Widget class.
 *
 * Extends Elementor\Widget_Base to provide a drag-and-drop holo card widget
 * inside the Elementor page builder.
 *
 * @since 2.0.0
 */
class PHC_Elementor_Widget extends \Elementor\Widget_Base {

    /**
     * Widget slug.
     *
     * @since  2.0.0
     * @return string
     */
    public function get_name() {
        return 'phc_holo_card';
    }

    /**
     * Widget display title.
     *
     * @since  2.0.0
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Holo Card', 'poke-holo-cards' );
    }

    /**
     * Widget icon.
     *
     * @since  2.0.0
     * @return string
     */
    public function get_icon() {
        return 'eicon-image';
    }

    /**
     * Widget categories.
     *
     * @since  2.0.0
     * @return array
     */
    public function get_categories() {
        return array( 'general' );
    }

    /**
     * Widget keywords for search.
     *
     * @since  2.0.0
     * @return array
     */
    public function get_keywords() {
        return array( 'holo', 'card', 'holographic', '3d', 'effect', 'pokemon' );
    }

    /**
     * Widget style dependencies.
     *
     * @since  2.0.0
     * @return array
     */
    public function get_style_depends() {
        return array( 'phc-holo-cards' );
    }

    /**
     * Widget script dependencies.
     *
     * @since  2.0.0
     * @return array
     */
    public function get_script_depends() {
        return array( 'phc-holo-cards' );
    }

    /**
     * Build the list of effect type options.
     *
     * @since  2.0.0
     * @return array
     */
    private function get_effect_options() {
        $options = array();
        $types   = function_exists( 'phc_get_effect_types' ) ? phc_get_effect_types() : array();
        foreach ( $types as $type ) {
            $label = function_exists( 'phc_get_effect_label' ) ? phc_get_effect_label( $type ) : ucfirst( $type );
            $options[ $type ] = $label;
        }
        return $options;
    }

    /**
     * Register widget controls.
     *
     * @since 2.0.0
     */
    protected function register_controls() {

        /* ── Content: Card Image ─────────────────────────── */
        $this->start_controls_section( 'section_content', array(
            'label' => esc_html__( 'Card Content', 'poke-holo-cards' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ) );

        $this->add_control( 'image', array(
            'label'   => esc_html__( 'Card Image', 'poke-holo-cards' ),
            'type'    => \Elementor\Controls_Manager::MEDIA,
            'default' => array( 'url' => \Elementor\Utils::get_placeholder_image_src() ),
        ) );

        $this->add_control( 'effect', array(
            'label'   => esc_html__( 'Effect Type', 'poke-holo-cards' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => 'holo',
            'options' => $this->get_effect_options(),
        ) );

        $this->add_control( 'width', array(
            'label'       => esc_html__( 'Width', 'poke-holo-cards' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => '300px',
            'description' => esc_html__( 'Any CSS value (e.g. 300px, 50%, 20vw).', 'poke-holo-cards' ),
        ) );

        $this->add_control( 'showcase', array(
            'label'       => esc_html__( 'Showcase Mode', 'poke-holo-cards' ),
            'type'        => \Elementor\Controls_Manager::SWITCHER,
            'default'     => '',
            'description' => esc_html__( 'Auto-rotate animation when idle.', 'poke-holo-cards' ),
        ) );

        $this->add_control( 'sparkle', array(
            'label'       => esc_html__( 'Sparkle Overlay', 'poke-holo-cards' ),
            'type'        => \Elementor\Controls_Manager::SWITCHER,
            'default'     => '',
            'description' => esc_html__( 'Animated sparkle particles on hover.', 'poke-holo-cards' ),
        ) );

        $this->end_controls_section();

        /* ── Content: Card Back ──────────────────────────── */
        $this->start_controls_section( 'section_back', array(
            'label' => esc_html__( 'Card Back (Flip)', 'poke-holo-cards' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ) );

        $this->add_control( 'back_image', array(
            'label'       => esc_html__( 'Back Image', 'poke-holo-cards' ),
            'type'        => \Elementor\Controls_Manager::MEDIA,
            'description' => esc_html__( 'Optional. Double-click or press Space to flip.', 'poke-holo-cards' ),
        ) );

        $this->end_controls_section();

        /* ── Style: Appearance ───────────────────────────── */
        $this->start_controls_section( 'section_style', array(
            'label' => esc_html__( 'Card Style', 'poke-holo-cards' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'glow_color', array(
            'label'   => esc_html__( 'Glow Color', 'poke-holo-cards' ),
            'type'    => \Elementor\Controls_Manager::COLOR,
            'default' => '#58e0d9',
        ) );

        $this->add_control( 'border_radius', array(
            'label'   => esc_html__( 'Border Radius (%)', 'poke-holo-cards' ),
            'type'    => \Elementor\Controls_Manager::SLIDER,
            'range'   => array(
                'px' => array( 'min' => 0, 'max' => 50, 'step' => 0.5 ),
            ),
            'default' => array( 'size' => 4.55 ),
        ) );

        $this->add_control( 'spring_preset', array(
            'label'   => esc_html__( 'Spring Preset', 'poke-holo-cards' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '',
            'options' => array(
                ''        => esc_html__( 'Default', 'poke-holo-cards' ),
                'bouncy'  => esc_html__( 'Bouncy',  'poke-holo-cards' ),
                'stiff'   => esc_html__( 'Stiff',   'poke-holo-cards' ),
                'smooth'  => esc_html__( 'Smooth',  'poke-holo-cards' ),
                'elastic' => esc_html__( 'Elastic', 'poke-holo-cards' ),
            ),
        ) );

        $this->end_controls_section();
    }

    /**
     * Render the widget on the frontend.
     *
     * @since 2.0.0
     */
    protected function render() {
        $s = $this->get_settings_for_display();

        if ( empty( $s['image']['url'] ) ) {
            return;
        }

        $effect  = esc_attr( $s['effect'] );
        $classes = 'phc-card phc-effect-' . $effect;

        $showcase = ( $s['showcase'] === 'yes' );
        $sparkle  = ( $s['sparkle'] === 'yes' );

        if ( $showcase ) {
            $classes .= ' phc-showcase';
        }

        $data = ' data-phc-effect="' . $effect . '"';

        if ( $showcase ) {
            $data .= ' data-phc-showcase="true"';
        }
        if ( $sparkle ) {
            $data .= ' data-phc-sparkle="true"';
        }
        if ( ! empty( $s['glow_color'] ) ) {
            $data .= ' data-phc-glow="' . esc_attr( $s['glow_color'] ) . '"';
        }
        if ( isset( $s['border_radius']['size'] ) && $s['border_radius']['size'] !== '' ) {
            $data .= ' data-phc-radius="' . esc_attr( $s['border_radius']['size'] ) . '"';
        }
        if ( ! empty( $s['spring_preset'] ) ) {
            $data .= ' data-phc-spring="' . esc_attr( $s['spring_preset'] ) . '"';
        }
        if ( ! empty( $s['back_image']['url'] ) ) {
            $data .= ' data-phc-back="' . esc_url( $s['back_image']['url'] ) . '"';
        }

        $alt = ! empty( $s['image']['alt'] ) ? $s['image']['alt'] : '';

        printf(
            '<div class="%s" style="width:%s"%s>' .
                '<div class="phc-card__translater">' .
                    '<div class="phc-card__rotator">' .
                        '<img class="phc-card__front" src="%s" alt="%s" loading="lazy" />' .
                        '<div class="phc-card__shine"></div>' .
                        '<div class="phc-card__glare"></div>' .
                    '</div>' .
                '</div>' .
            '</div>',
            esc_attr( $classes ),
            esc_attr( function_exists( 'phc_sanitize_css_length' ) ? phc_sanitize_css_length( $s['width'], '300px' ) : $s['width'] ),
            $data,
            esc_url( $s['image']['url'] ),
            esc_attr( $alt )
        );
    }
}
