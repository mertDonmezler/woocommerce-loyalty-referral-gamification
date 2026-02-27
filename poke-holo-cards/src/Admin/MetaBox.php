<?php
/**
 * WooCommerce product meta box for per-product holo effect settings.
 *
 * @package PokeHoloCards\Admin
 * @since   3.0.0
 */

namespace PokeHoloCards\Admin;

use PokeHoloCards\Utils\EffectTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MetaBox adds per-product holo effect controls to the product editor.
 */
class MetaBox {

    /**
     * Register WordPress hooks.
     */
    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
        add_action( 'save_post_product', array( __CLASS__, 'save' ), 10, 2 );

        // Variable product variation fields.
        add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'render_variation_fields' ), 10, 3 );
        add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation' ), 10, 2 );
    }

    /**
     * Register the per-product Holo Card Effect meta box.
     */
    public static function register() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }
        add_meta_box(
            'phc_product_holo_effect',
            __( 'Holo Card Effect', 'poke-holo-cards' ),
            array( __CLASS__, 'render' ),
            'product',
            'side',
            'default'
        );
    }

    /**
     * Render the meta box contents.
     *
     * @param \WP_Post $post Current post object.
     */
    public static function render( $post ) {
        wp_nonce_field( 'phc_save_product_meta', 'phc_product_meta_nonce' );

        $effect_type = get_post_meta( $post->ID, '_phc_effect_type', true );
        $glow_color  = get_post_meta( $post->ID, '_phc_glow_color', true );
        $enabled     = get_post_meta( $post->ID, '_phc_enabled', true );

        if ( empty( $glow_color ) ) {
            $glow_color = get_option( 'phc_glow_color', '#58e0d9' );
        }
        if ( empty( $enabled ) ) {
            $enabled = 'no';
        }
        ?>
        <p>
            <label for="phc_product_enabled"><strong><?php esc_html_e( 'Enable Holo Effect', 'poke-holo-cards' ); ?></strong></label><br />
            <select name="_phc_enabled" id="phc_product_enabled" style="width:100%">
                <option value="yes" <?php selected( $enabled, 'yes' ); ?>><?php esc_html_e( 'Enabled', 'poke-holo-cards' ); ?></option>
                <option value="no" <?php selected( $enabled, 'no' ); ?>><?php esc_html_e( 'Disabled', 'poke-holo-cards' ); ?></option>
            </select>
        </p>
        <p>
            <label for="phc_product_effect_type"><strong><?php esc_html_e( 'Effect Type', 'poke-holo-cards' ); ?></strong></label><br />
            <select name="_phc_effect_type" id="phc_product_effect_type" style="width:100%">
                <option value="global" <?php selected( $effect_type, 'global' ); ?>><?php esc_html_e( 'Use Global Default', 'poke-holo-cards' ); ?></option>
                <?php foreach ( EffectTypes::get_all() as $type ) : ?>
                    <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $effect_type, $type ); ?>><?php echo esc_html( EffectTypes::get_label( $type ) ); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="phc_product_glow_color"><strong><?php esc_html_e( 'Glow Color', 'poke-holo-cards' ); ?></strong></label><br />
            <input type="color" name="_phc_glow_color" id="phc_product_glow_color" value="<?php echo esc_attr( $glow_color ); ?>" style="width:100%;height:36px" />
        </p>
        <?php
    }

    /**
     * Save per-product holo effect meta on product save.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     */
    public static function save( $post_id, $post ) {
        if ( ! isset( $_POST['phc_product_meta_nonce'] ) ||
             ! wp_verify_nonce( $_POST['phc_product_meta_nonce'], 'phc_save_product_meta' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Effect type.
        if ( isset( $_POST['_phc_effect_type'] ) ) {
            $effect          = sanitize_text_field( $_POST['_phc_effect_type'] );
            $allowed_effects = array_merge( array( 'global' ), EffectTypes::get_all() );
            if ( in_array( $effect, $allowed_effects, true ) ) {
                update_post_meta( $post_id, '_phc_effect_type', $effect );
            }
        }

        // Glow color.
        if ( isset( $_POST['_phc_glow_color'] ) ) {
            $color = sanitize_hex_color( $_POST['_phc_glow_color'] );
            if ( $color ) {
                update_post_meta( $post_id, '_phc_glow_color', $color );
            }
        }

        // Enabled.
        if ( isset( $_POST['_phc_enabled'] ) ) {
            $enabled = in_array( $_POST['_phc_enabled'], array( 'yes', 'no' ), true )
                ? $_POST['_phc_enabled']
                : 'yes';
            update_post_meta( $post_id, '_phc_enabled', $enabled );
        }
    }

    /**
     * Render holo effect fields on each product variation.
     *
     * @param int     $loop           Variation loop index.
     * @param array   $variation_data Variation data.
     * @param \WP_Post $variation     Variation post object.
     */
    public static function render_variation_fields( $loop, $variation_data, $variation ) {
        $effect = get_post_meta( $variation->ID, '_phc_effect_type', true );
        $glow   = get_post_meta( $variation->ID, '_phc_glow_color', true );
        ?>
        <div class="phc-variation-fields" style="border-top:1px solid #eee;padding-top:10px;margin-top:10px">
            <p class="form-row form-row-first">
                <label><?php esc_html_e( 'Holo Effect', 'poke-holo-cards' ); ?></label>
                <select name="_phc_variation_effect[<?php echo esc_attr( $loop ); ?>]" style="width:100%">
                    <option value=""><?php esc_html_e( 'Use Product Default', 'poke-holo-cards' ); ?></option>
                    <?php foreach ( EffectTypes::get_all() as $type ) : ?>
                        <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $effect, $type ); ?>><?php echo esc_html( EffectTypes::get_label( $type ) ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p class="form-row form-row-last">
                <label><?php esc_html_e( 'Holo Glow Color', 'poke-holo-cards' ); ?></label>
                <input type="color" name="_phc_variation_glow[<?php echo esc_attr( $loop ); ?>]" value="<?php echo esc_attr( $glow ? $glow : '#58e0d9' ); ?>" style="width:100%;height:30px" />
            </p>
        </div>
        <?php
    }

    /**
     * Save holo effect meta for a product variation.
     *
     * @param int $variation_id Variation post ID.
     * @param int $loop         Variation loop index.
     */
    public static function save_variation( $variation_id, $loop ) {
        // Effect type.
        if ( isset( $_POST['_phc_variation_effect'][ $loop ] ) ) {
            $effect = sanitize_text_field( $_POST['_phc_variation_effect'][ $loop ] );
            if ( empty( $effect ) ) {
                delete_post_meta( $variation_id, '_phc_effect_type' );
            } elseif ( in_array( $effect, EffectTypes::get_all(), true ) ) {
                update_post_meta( $variation_id, '_phc_effect_type', $effect );
            }
        }

        // Glow color.
        if ( isset( $_POST['_phc_variation_glow'][ $loop ] ) ) {
            $color = sanitize_hex_color( $_POST['_phc_variation_glow'][ $loop ] );
            if ( $color ) {
                update_post_meta( $variation_id, '_phc_glow_color', $color );
            } else {
                delete_post_meta( $variation_id, '_phc_glow_color' );
            }
        }
    }
}
