<?php
/**
 * [holo_carousel] shortcode handler.
 *
 * Renders a horizontal carousel of holographic cards with autoplay,
 * dot/arrow navigation, and touch swipe support.
 *
 * @package PokeHoloCards\Frontend
 * @since   3.1.0
 */

namespace PokeHoloCards\Frontend;

use PokeHoloCards\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Carousel renders the [holo_carousel] shortcode.
 */
class Carousel {

    /** @var int Counter for unique carousel IDs. */
    private static $instance_count = 0;

    /**
     * Register the shortcode.
     */
    public static function init() {
        add_shortcode( 'holo_carousel', array( __CLASS__, 'render' ) );
    }

    /**
     * Render the [holo_carousel] shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function render( $atts ) {
        $atts = shortcode_atts( array(
            'ids'      => '',
            'effect'   => get_option( 'phc_effect_type', 'holo' ),
            'width'    => '300px',
            'autoplay' => 'no',
            'speed'    => '4000',
            'glow'     => '',
            'sparkle'  => 'no',
        ), $atts, 'holo_carousel' );

        if ( empty( $atts['ids'] ) ) {
            return '<!-- holo_carousel: ids required -->';
        }

        $ids   = array_filter( array_map( 'intval', explode( ',', $atts['ids'] ) ), function( $id ) { return $id > 0; } );
        $speed = max( 1000, min( 20000, intval( $atts['speed'] ) ) );
        $auto  = strtolower( $atts['autoplay'] ) === 'yes';

        self::$instance_count++;
        $uid = 'phc-carousel-' . self::$instance_count;

        $card_width = esc_attr( Sanitizer::css_length( $atts['width'], '300px' ) );

        // Build card HTML
        $cards_html = '';
        $count      = 0;
        foreach ( $ids as $id ) {
            $url = wp_get_attachment_image_url( $id, 'large' );
            $alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
            if ( empty( $alt ) ) {
                $alt = get_the_title( $id );
            }
            if ( ! $url ) {
                continue;
            }
            $cards_html .= '<div class="phc-carousel-slide">';
            $cards_html .= Shortcode::render( array(
                'img'     => $url,
                'alt'     => $alt,
                'width'   => $atts['width'],
                'effect'  => $atts['effect'],
                'glow'    => $atts['glow'],
                'sparkle' => $atts['sparkle'],
            ) );
            $cards_html .= '</div>';
            $count++;
        }

        if ( $count === 0 ) {
            return '<!-- holo_carousel: no valid images -->';
        }

        // Dots
        $dots_html = '';
        for ( $i = 0; $i < $count; $i++ ) {
            $active_class = $i === 0 ? ' phc-carousel-dot-active' : '';
            $dots_html .= '<button type="button" class="phc-carousel-dot' . $active_class . '" data-index="' . $i . '" aria-label="Slide ' . ( $i + 1 ) . '"></button>';
        }

        $output = '<div id="' . esc_attr( $uid ) . '" class="phc-carousel" role="region" aria-label="Card carousel" data-autoplay="' . ( $auto ? 'true' : 'false' ) . '" data-speed="' . $speed . '">';

        // Nav arrows
        $output .= '<button type="button" class="phc-carousel-arrow phc-carousel-prev" aria-label="Previous slide">&#8249;</button>';
        $output .= '<div class="phc-carousel-track">';
        $output .= $cards_html;
        $output .= '</div>';
        $output .= '<button type="button" class="phc-carousel-arrow phc-carousel-next" aria-label="Next slide">&#8250;</button>';

        // Dots
        $output .= '<div class="phc-carousel-dots">' . $dots_html . '</div>';
        $output .= '</div>';

        // Inline JS for this carousel instance
        $output .= '<script>(function(){var c=document.getElementById("' . esc_js( $uid ) . '");if(!c)return;'
            . 'var track=c.querySelector(".phc-carousel-track"),slides=track.querySelectorAll(".phc-carousel-slide"),dots=c.querySelectorAll(".phc-carousel-dot"),idx=0,total=slides.length,timer=null;'
            . 'function goTo(n){idx=(n+total)%total;track.style.transform="translateX(-"+(idx*100)+\"%)";dots.forEach(function(d,i){d.classList.toggle("phc-carousel-dot-active",i===idx);});}'
            . 'c.querySelector(".phc-carousel-prev").addEventListener("click",function(){goTo(idx-1);resetAuto();});'
            . 'c.querySelector(".phc-carousel-next").addEventListener("click",function(){goTo(idx+1);resetAuto();});'
            . 'dots.forEach(function(d){d.addEventListener("click",function(){goTo(parseInt(this.getAttribute("data-index")));resetAuto();});});'
            . 'var auto=c.getAttribute("data-autoplay")==="true",spd=parseInt(c.getAttribute("data-speed"))||4000;'
            . 'function startAuto(){if(auto&&total>1)timer=setInterval(function(){goTo(idx+1);},spd);}'
            . 'function resetAuto(){clearInterval(timer);startAuto();}'
            // Touch swipe
            . 'var sx=0,st=0;track.addEventListener("touchstart",function(e){if(e.touches.length===1){sx=e.touches[0].clientX;st=Date.now();}},{passive:true});'
            . 'track.addEventListener("touchend",function(e){var dx=e.changedTouches[0].clientX-sx,dt=Date.now()-st;if(Math.abs(dx)>50&&dt<500){if(dx<0)goTo(idx+1);else goTo(idx-1);resetAuto();}},{passive:true});'
            . 'startAuto();'
            . '})();</script>';

        return apply_filters( 'phc_carousel_html', $output, $atts );
    }
}
