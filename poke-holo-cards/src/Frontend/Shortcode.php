<?php
/**
 * [holo_card] shortcode handler.
 *
 * @package PokeHoloCards\Frontend
 * @since   3.0.0
 */

namespace PokeHoloCards\Frontend;

use PokeHoloCards\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcode renders the [holo_card] shortcode.
 */
class Shortcode {

    /**
     * Register the shortcode.
     */
    public static function init() {
        add_shortcode( 'holo_card', array( __CLASS__, 'render' ) );
    }

    /**
     * Render the [holo_card] shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function render( $atts ) {
        $atts = shortcode_atts( array(
            'img'      => '',
            'alt'      => '',
            'width'    => '300px',
            'effect'   => get_option( 'phc_effect_type', 'holo' ),
            'class'    => '',
            'showcase' => 'no',
            'sparkle'  => 'no',
            'glow'     => '',
            'radius'   => '',
            'back'     => '',
            'back_alt' => '',
            'spring'   => '',
            'url'      => '',
            'target'   => '_self',
        ), $atts, 'holo_card' );

        /** This filter is documented in the legacy phc_shortcode(). */
        $atts = apply_filters( 'phc_shortcode_atts', $atts );

        if ( empty( $atts['img'] ) ) {
            return '<!-- holo_card: img attribute required -->';
        }

        /** Fires before a holo card is rendered. */
        do_action( 'phc_before_render_card', $atts );

        $img_url  = esc_url( $atts['img'] );
        $alt      = esc_attr( $atts['alt'] );
        $width    = esc_attr( Sanitizer::css_length( $atts['width'], '300px' ) );
        $effect   = sanitize_html_class( $atts['effect'] );
        $extra    = $atts['class'] ? ' ' . implode( ' ', array_map( 'sanitize_html_class', explode( ' ', $atts['class'] ) ) ) : '';

        // Showcase attribute.
        $showcase_class = '';
        $showcase_data  = '';
        if ( strtolower( $atts['showcase'] ) === 'yes' ) {
            $showcase_class = ' phc-showcase';
            $showcase_data  = ' data-phc-showcase="true"';
        }

        // Sparkle attribute.
        $sparkle_data = '';
        if ( strtolower( $atts['sparkle'] ) === 'yes' ) {
            $sparkle_data = ' data-phc-sparkle="true"';
        }

        // Per-card glow color.
        $glow_data = '';
        if ( ! empty( $atts['glow'] ) ) {
            $glow_color = sanitize_hex_color( $atts['glow'] );
            if ( $glow_color ) {
                $glow_data = ' data-phc-glow="' . esc_attr( $glow_color ) . '"';
            }
        }

        // Per-card border radius.
        $radius_data = '';
        if ( $atts['radius'] !== '' ) {
            $radius_val  = Sanitizer::float_range( $atts['radius'], 0, 50 );
            $radius_data = ' data-phc-radius="' . esc_attr( $radius_val ) . '"';
        }

        // Back image.
        $back_data = '';
        if ( ! empty( $atts['back'] ) ) {
            $back_data = ' data-phc-back="' . esc_url( $atts['back'] ) . '"';
        }

        // Back alt text.
        $back_alt_data = '';
        if ( ! empty( $atts['back_alt'] ) ) {
            $back_alt_data = ' data-phc-back-alt="' . esc_attr( $atts['back_alt'] ) . '"';
        }

        // Spring preset override.
        $spring_data = '';
        if ( ! empty( $atts['spring'] ) ) {
            $spring_data = ' data-phc-spring="' . esc_attr( sanitize_text_field( $atts['spring'] ) ) . '"';
        }

        // Click-through URL.
        $url_data = '';
        if ( ! empty( $atts['url'] ) ) {
            $url_data = ' data-phc-url="' . esc_url( $atts['url'] ) . '"';
            $allowed_targets = array( '_self', '_blank', '_parent', '_top' );
            $target = in_array( $atts['target'], $allowed_targets, true ) ? $atts['target'] : '_self';
            $url_data .= ' data-phc-target="' . esc_attr( $target ) . '"';
        }

        $html = sprintf(
            '<div class="phc-card phc-effect-%s%s%s" style="width:%s" data-phc-effect="%s"%s%s%s%s%s%s%s%s>' .
                '<div class="phc-card__translater">' .
                    '<div class="phc-card__rotator">' .
                        '<img class="phc-card__front" src="%s" alt="%s" loading="lazy" />' .
                        '<div class="phc-card__shine"></div>' .
                        '<div class="phc-card__glare"></div>' .
                    '</div>' .
                '</div>' .
            '</div>',
            $effect, $extra, $showcase_class, $width, $effect,
            $showcase_data, $sparkle_data, $glow_data, $radius_data,
            $back_data, $back_alt_data, $spring_data, $url_data,
            $img_url, $alt
        );

        /** Filter the final card HTML before output. */
        $html = apply_filters( 'phc_card_html', $html, $atts );

        /** Fires after a holo card has been rendered. */
        do_action( 'phc_after_render_card', $html, $atts );

        return $html;
    }
}
