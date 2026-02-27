<?php
/**
 * Card Comparison AJAX handler.
 *
 * Returns rendered holo card HTML + product metadata for
 * side-by-side comparison in the collection page modal.
 *
 * @package PokeHoloCards\Frontend
 * @since   3.1.0
 */

namespace PokeHoloCards\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Compare {

    public static function init() {
        add_action( 'wp_ajax_phc_compare_cards', array( __CLASS__, 'ajax_compare' ) );
    }

    /**
     * AJAX: Return comparison data for two products.
     */
    public static function ajax_compare() {
        check_ajax_referer( 'phc_collection_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Not logged in.' ) );
        }

        $id1 = isset( $_POST['product_id_1'] ) ? absint( $_POST['product_id_1'] ) : 0;
        $id2 = isset( $_POST['product_id_2'] ) ? absint( $_POST['product_id_2'] ) : 0;

        if ( ! $id1 || ! $id2 ) {
            wp_send_json_error( array( 'message' => 'Invalid product IDs.' ) );
        }

        // Verify user has purchased these products.
        $user_id    = get_current_user_id();
        $user_email = wp_get_current_user()->user_email;
        if ( ! wc_customer_bought_product( $user_email, $user_id, $id1 ) ||
             ! wc_customer_bought_product( $user_email, $user_id, $id2 ) ) {
            wp_send_json_error( array( 'message' => 'Product not in your collection.' ) );
        }

        $card1 = self::build_card_data( $id1 );
        $card2 = self::build_card_data( $id2 );

        if ( ! $card1 || ! $card2 ) {
            wp_send_json_error( array( 'message' => 'Product not found.' ) );
        }

        wp_send_json_success( array(
            'card1' => $card1,
            'card2' => $card2,
        ) );
    }

    /**
     * Build card data array for a product.
     *
     * @param int $product_id WC product ID.
     * @return array|null
     */
    private static function build_card_data( $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return null;
        }

        $image_id  = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : wc_placeholder_img_src( 'medium' );

        $meta   = function_exists( 'phc_get_product_meta' ) ? phc_get_product_meta( $product_id ) : array();
        $effect = ! empty( $meta['effect'] ) ? $meta['effect'] : 'holo';
        $glow   = ! empty( $meta['glow'] ) ? $meta['glow'] : '';
        $rarity = function_exists( 'phc_get_rarity' ) ? phc_get_rarity( $effect ) : 'common';

        $rarity_labels = array(
            'common'    => __( 'Common', 'poke-holo-cards' ),
            'uncommon'  => __( 'Uncommon', 'poke-holo-cards' ),
            'rare'      => __( 'Rare', 'poke-holo-cards' ),
            'epic'      => __( 'Epic', 'poke-holo-cards' ),
            'legendary' => __( 'Legendary', 'poke-holo-cards' ),
        );

        $html = phc_render_card( $image_url, array(
            'effect'  => $effect,
            'width'   => '100%',
            'sparkle' => ( Collection::rarity_score( $rarity ) >= 4 ) ? 'yes' : 'no',
            'glow'    => $glow,
            'alt'     => $product->get_name(),
            'class'   => 'phc-compare-holo',
        ) );

        return array(
            'html'         => $html,
            'name'         => $product->get_name(),
            'price'        => wc_price( $product->get_price() ),
            'effect'       => ucfirst( $effect ),
            'rarity'       => $rarity,
            'rarity_label' => $rarity_labels[ $rarity ] ?? ucfirst( $rarity ),
            'product_id'   => $product_id,
        );
    }
}
