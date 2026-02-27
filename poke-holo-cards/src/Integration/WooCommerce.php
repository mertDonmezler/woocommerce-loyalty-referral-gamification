<?php
/**
 * WooCommerce integration: product image wrapping and per-product meta.
 *
 * @package PokeHoloCards\Integration
 * @since   3.0.0
 */

namespace PokeHoloCards\Integration;

use PokeHoloCards\Core\Settings;
use PokeHoloCards\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WooCommerce handles product gallery/archive image wrapping.
 */
class WooCommerce {

    /**
     * Attach WooCommerce hooks when appropriate.
     */
    public static function init() {
        add_action( 'plugins_loaded', array( __CLASS__, 'register_hooks' ) );
    }

    /**
     * Conditionally register WooCommerce filters/actions.
     */
    public static function register_hooks() {
        $s = Settings::get_all();
        if ( $s['enabled'] !== 'yes' || $s['woo_enabled'] !== 'yes' ) {
            return;
        }
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        $target = $s['woo_target'];

        if ( $target === 'product_gallery' || $target === 'both' ) {
            add_filter( 'woocommerce_single_product_image_thumbnail_html', array( __CLASS__, 'wrap_product_image' ), 20, 2 );
            add_action( 'woocommerce_before_single_product', array( __CLASS__, 'output_variation_effect_data' ) );
        }
        if ( $target === 'archive_thumbs' || $target === 'both' ) {
            add_action( 'woocommerce_before_shop_loop_item_title', array( __CLASS__, 'archive_thumb_open' ), 9 );
            add_action( 'woocommerce_before_shop_loop_item_title', array( __CLASS__, 'archive_thumb_close' ), 11 );
        }
    }

    /**
     * Retrieve per-product holo card meta, falling back to global settings.
     *
     * @param int $product_id WooCommerce product ID.
     * @return array{ effect: string, glow_color: string, enabled: bool }
     */
    public static function get_product_meta( $product_id ) {
        $global_effect = Sanitizer::effect_type( get_option( 'phc_effect_type', 'holo' ) );
        $global_glow   = sanitize_hex_color( get_option( 'phc_glow_color', '#58e0d9' ) );

        $meta_effect  = get_post_meta( $product_id, '_phc_effect_type', true );
        $meta_glow    = get_post_meta( $product_id, '_phc_glow_color', true );
        $meta_enabled = get_post_meta( $product_id, '_phc_enabled', true );

        $effect = ( ! empty( $meta_effect ) && $meta_effect !== 'global' )
            ? Sanitizer::effect_type( $meta_effect )
            : $global_effect;

        $glow_color = ( ! empty( $meta_glow ) )
            ? sanitize_hex_color( $meta_glow )
            : $global_glow;
        if ( ! $glow_color ) {
            $glow_color = $global_glow;
        }

        $enabled = ( $meta_enabled === 'yes' ) ? true : false;

        $meta = array(
            'effect'     => $effect,
            'glow_color' => $glow_color,
            'enabled'    => $enabled,
        );

        /** Filter the resolved per-product meta before it is used. */
        return apply_filters( 'phc_product_meta', $meta, $product_id );
    }

    /**
     * Replace WooCommerce single-product gallery thumbnail HTML with holo card.
     *
     * @param string $html          Original thumbnail HTML.
     * @param int    $attachment_id Attachment ID.
     * @return string Modified HTML.
     */
    public static function wrap_product_image( $html, $attachment_id ) {
        $img_url = wp_get_attachment_image_url( $attachment_id, 'woocommerce_single' );
        if ( ! $img_url ) {
            return $html;
        }

        $alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
        if ( empty( $alt_text ) ) {
            $alt_text = get_the_title( $attachment_id );
        }

        $product_id   = get_the_ID();
        $product_meta = self::get_product_meta( $product_id );

        if ( ! $product_meta['enabled'] ) {
            return $html;
        }

        $effect     = esc_attr( $product_meta['effect'] );
        $glow_color = esc_attr( $product_meta['glow_color'] );

        return sprintf(
            '<div class="phc-card phc-woo-gallery phc-effect-%1$s" data-phc-effect="%1$s" data-phc-glow="%4$s">' .
                '<div class="phc-card__translater">' .
                    '<div class="phc-card__rotator">' .
                        '<img class="phc-card__front" src="%2$s" alt="%3$s" loading="lazy" />' .
                        '<div class="phc-card__shine"></div>' .
                        '<div class="phc-card__glare"></div>' .
                    '</div>' .
                '</div>' .
            '</div>',
            $effect, esc_url( $img_url ), esc_attr( $alt_text ), $glow_color
        );
    }

    /** @var int Tracks nesting depth of archive wrappers. */
    private static $wrapper_depth = 0;

    /**
     * Open holo card wrapper around WooCommerce archive thumbnail.
     */
    public static function archive_thumb_open() {
        global $product;
        $product_id   = ( $product && is_a( $product, 'WC_Product' ) ) ? $product->get_id() : get_the_ID();
        $product_meta = self::get_product_meta( $product_id );

        if ( ! $product_meta['enabled'] ) {
            echo '<div class="phc-woo-archive-passthrough">';
            return;
        }

        self::$wrapper_depth++;
        $effect     = esc_attr( $product_meta['effect'] );
        $glow_color = esc_attr( $product_meta['glow_color'] );

        echo '<div class="phc-card phc-woo-archive phc-effect-' . $effect . '" data-phc-effect="' . $effect . '" data-phc-glow="' . $glow_color . '">';
        echo '<div class="phc-card__translater"><div class="phc-card__rotator">';
    }

    /**
     * Close holo card wrapper around WooCommerce archive thumbnail.
     */
    public static function archive_thumb_close() {
        global $product;

        if ( self::$wrapper_depth <= 0 ) {
            echo '</div>';
            return;
        }

        self::$wrapper_depth--;

        echo '<div class="phc-card__shine"></div><div class="phc-card__glare"></div>';

        // Add-to-cart overlay for archive thumbnails.
        if ( $product && is_a( $product, 'WC_Product' ) && $product->is_purchasable() && $product->is_in_stock() ) {
            $price = $product->get_price_html();
            $type  = $product->get_type();

            echo '<div class="phc-atc-overlay">';
            if ( $price ) {
                echo '<span class="phc-atc-price">' . $price . '</span>';
            }
            if ( $type === 'simple' ) {
                printf(
                    '<a href="%s" data-quantity="1" data-product_id="%d" class="phc-atc-btn button add_to_cart_button ajax_add_to_cart">%s</a>',
                    esc_url( $product->add_to_cart_url() ),
                    esc_attr( $product->get_id() ),
                    esc_html( $product->add_to_cart_text() )
                );
            } else {
                printf(
                    '<a href="%s" class="phc-atc-btn button">%s</a>',
                    esc_url( $product->add_to_cart_url() ),
                    esc_html( $product->add_to_cart_text() )
                );
            }
            echo '</div>';
        }

        echo '</div></div></div>';
    }

    /**
     * Output variation-specific effect data as inline JSON for variable products.
     *
     * This allows the frontend JS to swap holo effects when a variation is selected.
     */
    public static function output_variation_effect_data() {
        global $product;
        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            return;
        }

        $product_meta  = self::get_product_meta( $product->get_id() );
        $variations    = $product->get_available_variations();
        $variation_map = array();

        foreach ( $variations as $variation ) {
            $vid         = $variation['variation_id'];
            $var_effect  = get_post_meta( $vid, '_phc_effect_type', true );
            $var_glow    = get_post_meta( $vid, '_phc_glow_color', true );

            // Only include if variation has its own override.
            if ( ! empty( $var_effect ) || ! empty( $var_glow ) ) {
                $variation_map[ $vid ] = array(
                    'effect' => ! empty( $var_effect ) ? Sanitizer::effect_type( $var_effect ) : $product_meta['effect'],
                    'glow'   => ! empty( $var_glow ) ? sanitize_hex_color( $var_glow ) : $product_meta['glow_color'],
                );
            }
        }

        if ( empty( $variation_map ) ) {
            return;
        }

        // Output as a hidden script tag for JS consumption.
        printf(
            '<script type="application/json" id="phc-variation-effects">%s</script>',
            wp_json_encode( array(
                'default' => array(
                    'effect' => $product_meta['effect'],
                    'glow'   => $product_meta['glow_color'],
                ),
                'variations' => $variation_map,
            ) )
        );

        // Inline JS to handle WooCommerce variation changes.
        ?>
        <script>
        (function(){
            var dataEl = document.getElementById('phc-variation-effects');
            if (!dataEl) return;
            var data = JSON.parse(dataEl.textContent);
            var form = document.querySelector('form.variations_form');
            if (!form) return;

            function applyEffect(effect, glow) {
                var cards = document.querySelectorAll('.phc-woo-gallery');
                cards.forEach(function(card) {
                    // Remove old effect classes.
                    var classes = card.className.split(' ').filter(function(c) {
                        return c.indexOf('phc-effect-') !== 0;
                    });
                    classes.push('phc-effect-' + effect);
                    card.className = classes.join(' ');
                    card.setAttribute('data-phc-effect', effect);
                    card.setAttribute('data-phc-glow', glow);
                    card.style.setProperty('--phc-card-glow', glow);
                });
            }

            // WooCommerce fires this when a variation is selected.
            jQuery(form).on('found_variation', function(e, variation) {
                var vid = variation.variation_id;
                if (data.variations[vid]) {
                    applyEffect(data.variations[vid].effect, data.variations[vid].glow);
                } else {
                    applyEffect(data['default'].effect, data['default'].glow);
                }
            });

            // Reset when variation is cleared.
            jQuery(form).on('reset_data', function() {
                applyEffect(data['default'].effect, data['default'].glow);
            });
        })();
        </script>
        <?php
    }
}
