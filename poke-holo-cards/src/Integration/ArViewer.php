<?php
/**
 * AR Viewer - View holographic cards in augmented reality.
 *
 * Uses Google's model-viewer web component for cross-platform AR
 * (Scene Viewer on Android, AR Quick Look on iOS).
 * Three.js generates GLB/USDZ models client-side from the card texture.
 *
 * @package PokeHoloCards\Integration
 * @since   3.1.0
 */

namespace PokeHoloCards\Integration;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ArViewer {

    public static function init() {
        // Product page button.
        add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_button' ), 35 );

        // Shortcode.
        add_shortcode( 'holo_ar', array( __CLASS__, 'shortcode' ) );
    }

    /**
     * Render the "AR ile Gor" button on product pages.
     */
    public static function render_button() {
        global $product;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        $meta = function_exists( 'phc_get_product_meta' ) ? phc_get_product_meta( $product->get_id() ) : array();
        $enabled = ! empty( $meta['enabled'] );

        // Only show button if PHC is enabled for this product.
        if ( ! $enabled ) {
            return;
        }

        $image_id  = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
        if ( ! $image_url ) {
            return;
        }

        $effect = ! empty( $meta['effect'] ) ? $meta['effect'] : 'holo';
        ?>
        <button type="button"
                class="phc-ar-btn"
                data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
                data-image-url="<?php echo esc_url( $image_url ); ?>"
                data-effect="<?php echo esc_attr( $effect ); ?>"
                style="display:none"
                aria-label="<?php esc_attr_e( 'AR ile Gor', 'poke-holo-cards' ); ?>">
            <svg class="phc-ar-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/>
                <path d="M2 12h20"/>
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            </svg>
            <?php esc_html_e( 'AR ile Gor', 'poke-holo-cards' ); ?>
        </button>
        <?php
    }

    /**
     * Shortcode: [holo_ar product_id="123" width="300"]
     */
    public static function shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'product_id' => 0,
            'width'      => '300px',
        ), $atts, 'holo_ar' );

        $product_id = absint( $atts['product_id'] );
        if ( ! $product_id ) {
            return '<!-- holo_ar: product_id required -->';
        }

        if ( ! function_exists( 'wc_get_product' ) ) {
            return '<!-- holo_ar: WooCommerce required -->';
        }

        // Ensure assets are loaded (shortcode may be on non-product pages).
        wp_enqueue_style( 'phc-ar-viewer', PHC_PLUGIN_URL . 'assets/css/phc-ar-viewer.css', array(), PHC_VERSION );
        wp_enqueue_script( 'phc-ar-viewer', PHC_PLUGIN_URL . 'assets/js/phc-ar-viewer.js', array(), PHC_VERSION, true );

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return '<!-- holo_ar: product not found -->';
        }

        $image_id  = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
        if ( ! $image_url ) {
            return '<!-- holo_ar: no product image -->';
        }

        $meta   = function_exists( 'phc_get_product_meta' ) ? phc_get_product_meta( $product_id ) : array();
        $effect = ! empty( $meta['effect'] ) ? $meta['effect'] : 'holo';

        ob_start();
        ?>
        <div class="phc-ar-shortcode" style="width:<?php echo esc_attr( $atts['width'] ); ?>">
            <button type="button"
                    class="phc-ar-btn phc-ar-btn-inline"
                    data-product-id="<?php echo esc_attr( $product_id ); ?>"
                    data-image-url="<?php echo esc_url( $image_url ); ?>"
                    data-effect="<?php echo esc_attr( $effect ); ?>"
                    style="display:none">
                <svg class="phc-ar-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/>
                    <path d="M2 12h20"/>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                </svg>
                <?php esc_html_e( 'AR ile Gor', 'poke-holo-cards' ); ?>
            </button>
            <div class="phc-ar-container"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
