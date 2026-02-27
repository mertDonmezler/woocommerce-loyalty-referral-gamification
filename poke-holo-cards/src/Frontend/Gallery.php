<?php
/**
 * [holo_gallery] shortcode handler.
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
 * Gallery renders the [holo_gallery] shortcode.
 */
class Gallery {

    /**
     * Register the shortcode.
     */
    public static function init() {
        add_shortcode( 'holo_gallery', array( __CLASS__, 'render' ) );
    }

    /**
     * Render the [holo_gallery] shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function render( $atts ) {
        $atts = shortcode_atts( array(
            'ids'     => '',
            'columns' => '3',
            'effect'  => get_option( 'phc_effect_type', 'holo' ),
            'width'   => '250px',
            'glow'    => '',
            'sparkle' => 'no',
            'gap'     => '20px',
        ), $atts, 'holo_gallery' );

        if ( empty( $atts['ids'] ) ) {
            return '<!-- holo_gallery: ids required -->';
        }

        $ids  = array_filter( array_map( 'intval', explode( ',', $atts['ids'] ) ), function( $id ) { return $id > 0; } );
        $cols = max( 1, min( 6, intval( $atts['columns'] ) ) );
        $gap  = Sanitizer::css_length( $atts['gap'], '20px' );

        $output = '<div class="phc-gallery" style="display:grid;grid-template-columns:repeat(' . $cols . ',1fr);gap:' . esc_attr( $gap ) . ';">';

        foreach ( $ids as $id ) {
            $url = wp_get_attachment_image_url( $id, 'large' );
            $alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
            if ( empty( $alt ) ) {
                $alt = get_the_title( $id );
            }
            if ( ! $url ) {
                continue;
            }
            $output .= Shortcode::render( array(
                'img'     => $url,
                'alt'     => $alt,
                'width'   => $atts['width'],
                'effect'  => $atts['effect'],
                'glow'    => $atts['glow'],
                'sparkle' => $atts['sparkle'],
            ) );
        }

        $output .= '</div>';

        /** Filter the final gallery HTML. */
        return apply_filters( 'phc_gallery_html', $output, $atts );
    }
}
