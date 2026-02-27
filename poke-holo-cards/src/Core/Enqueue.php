<?php
/**
 * Asset enqueue handler for frontend and admin.
 *
 * @package PokeHoloCards\Core
 * @since   3.0.0
 */

namespace PokeHoloCards\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue manages CSS/JS loading for both frontend and admin contexts.
 */
class Enqueue {

    /**
     * Register WordPress hooks.
     */
    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'frontend' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin' ) );
    }

    /**
     * Build the localize data array from settings.
     *
     * @param array $s       Settings array.
     * @param bool  $gyro    Override gyroscope setting (false for admin).
     * @return array
     */
    private static function build_localize_data( $s, $gyro = null ) {
        $data = array(
            'effectType'      => $s['effect_type'],
            'hoverScale'      => $s['hover_scale'],
            'perspective'     => $s['perspective'],
            'springStiffness' => $s['spring_stiffness'],
            'springDamping'   => $s['spring_damping'],
            'glareOpacity'    => $s['glare_opacity'],
            'shineIntensity'  => $s['shine_intensity'],
            'glowColor'       => $s['glow_color'],
            'borderRadius'    => $s['border_radius'],
            'autoInitClass'   => $s['auto_init_class'],
            'gyroscope'       => $gyro !== null ? $gyro : $s['gyroscope'],
            'springPreset'    => $s['spring_preset'],
        );

        /** This filter is documented in the legacy phc_enqueue_frontend(). */
        return apply_filters( 'phc_script_settings', $data );
    }

    /**
     * Conditionally enqueue front-end CSS and JS assets.
     */
    public static function frontend() {
        $s = Settings::get_all();
        if ( $s['enabled'] !== 'yes' ) {
            return;
        }

        $should_load = false;

        // WooCommerce single product pages.
        if ( $s['woo_enabled'] === 'yes' && function_exists( 'is_product' ) && is_product() ) {
            $should_load = true;
        }

        // WooCommerce archive pages.
        if ( $s['woo_enabled'] === 'yes' && function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product_tag() ) ) {
            if ( $s['woo_target'] === 'archive_thumbs' || $s['woo_target'] === 'both' ) {
                $should_load = true;
            }
        }

        // Post content shortcodes.
        global $post;
        if ( $post && is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'holo_card' ) || has_shortcode( $post->post_content, 'holo_gallery' ) ) ) {
            $should_load = true;
        }

        // Gutenberg block.
        if ( $post && is_a( $post, 'WP_Post' ) && function_exists( 'has_block' ) && has_block( 'phc/holo-card', $post ) ) {
            $should_load = true;
        }

        // AR viewer on product pages (independent of woo_enabled setting).
        if ( function_exists( 'is_product' ) && is_product() ) {
            $should_load = true;
        }

        /**
         * Allow themes and other plugins to force or prevent asset loading.
         *
         * @param bool $should_load Whether assets should be enqueued.
         */
        $should_load = apply_filters( 'phc_should_load_assets', $should_load );

        // WooCommerce My Account endpoints always need assets.
        if ( function_exists( 'is_wc_endpoint_url' ) ) {
            if ( is_wc_endpoint_url( 'phc-collection' ) || is_wc_endpoint_url( 'phc-pack-opening' ) ) {
                $should_load = true;
            }
        }

        if ( ! $should_load ) {
            return;
        }

        self::enqueue_core_assets();
        $data = self::build_localize_data( $s );
        $data['ajaxUrl'] = admin_url( 'admin-ajax.php' );
        wp_localize_script( 'phc-holo-cards', 'phcSettings', $data );

        // Collection page assets.
        if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'phc-collection' ) ) {
            wp_enqueue_style(
                'phc-collection',
                PHC_PLUGIN_URL . 'assets/css/phc-collection.css',
                array( 'phc-holo-cards' ),
                PHC_VERSION
            );
            wp_enqueue_script(
                'phc-collection',
                PHC_PLUGIN_URL . 'assets/js/phc-collection.js',
                array( 'phc-holo-cards' ),
                PHC_VERSION,
                true
            );
            wp_localize_script( 'phc-collection', 'phcCollectionData', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'phc_collection_nonce' ),
                'i18n'    => array(
                    'property' => __( 'Ozellik', 'poke-holo-cards' ),
                    'effect'   => __( 'Efekt', 'poke-holo-cards' ),
                    'rarity'   => __( 'Nadirllik', 'poke-holo-cards' ),
                    'price'    => __( 'Fiyat', 'poke-holo-cards' ),
                ),
            ) );
        }

        // Pack opening assets.
        if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'phc-pack-opening' ) ) {
            wp_enqueue_style(
                'phc-pack-opening',
                PHC_PLUGIN_URL . 'assets/css/phc-pack-opening.css',
                array( 'phc-holo-cards' ),
                PHC_VERSION
            );
            wp_enqueue_script(
                'phc-pack-opening',
                PHC_PLUGIN_URL . 'assets/js/phc-pack-opening.js',
                array( 'phc-holo-cards' ),
                PHC_VERSION,
                true
            );
        }

        // Pack opening CSS on thank-you page too (banner only, no JS needed).
        if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
            wp_enqueue_style(
                'phc-pack-opening',
                PHC_PLUGIN_URL . 'assets/css/phc-pack-opening.css',
                array( 'phc-holo-cards' ),
                PHC_VERSION
            );
        }

        // AR viewer on product pages.
        if ( function_exists( 'is_product' ) && is_product() ) {
            wp_enqueue_style(
                'phc-ar-viewer',
                PHC_PLUGIN_URL . 'assets/css/phc-ar-viewer.css',
                array(),
                PHC_VERSION
            );
            wp_enqueue_script(
                'phc-ar-viewer',
                PHC_PLUGIN_URL . 'assets/js/phc-ar-viewer.js',
                array(),
                PHC_VERSION,
                true
            );
        }
    }

    /**
     * Enqueue CSS, JS, and admin-specific inline styles on the settings page.
     *
     * @param string $hook The current admin page hook suffix.
     */
    public static function admin( $hook ) {
        if ( $hook !== 'settings_page_poke-holo-cards' ) {
            return;
        }

        $s = Settings::get_all();

        wp_enqueue_media(); // For Shortcode Builder media library button.
        self::enqueue_core_assets();
        wp_localize_script( 'phc-holo-cards', 'phcSettings', self::build_localize_data( $s, false ) );
        wp_localize_script( 'phc-holo-cards', 'phcAdmin', array(
            'nonce'   => wp_create_nonce( 'phc_admin_nonce' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ) );

        $admin_css = '
            .phc-admin-preview-wrap {
                background: #1a1a2e;
                padding: 40px;
                border-radius: 8px;
                display: flex;
                justify-content: center;
                align-items: center;
                margin: 20px 0;
                min-height: 320px;
            }
            .phc-admin-preview-wrap .phc-card {
                width: 250px;
                margin: 0 auto;
            }
            .phc-admin-preview-placeholder {
                width: 100%;
                aspect-ratio: 3/4;
                border-radius: inherit;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                font-size: 16px;
                font-weight: 600;
                text-shadow: 0 1px 3px rgba(0,0,0,0.3);
            }
            .phc-tab-content { display: none; }
            .phc-tab-content.phc-tab-active { display: block; }
            .phc-advanced-actions { margin: 20px 0; }
            .phc-advanced-actions .button { margin-right: 10px; }
            #phc-import-export-area { width: 100%; min-height: 120px; font-family: monospace; margin-top: 10px; }
        ';
        wp_add_inline_style( 'phc-holo-cards', $admin_css );
    }

    /**
     * Register and enqueue the core CSS + JS bundle.
     */
    private static function enqueue_core_assets() {
        wp_enqueue_style(
            'phc-holo-cards',
            PHC_PLUGIN_URL . 'assets/css/holo-cards.css',
            array(),
            PHC_VERSION
        );

        wp_enqueue_script(
            'phc-holo-cards',
            PHC_PLUGIN_URL . 'assets/js/holo-cards.js',
            array(),
            PHC_VERSION,
            true
        );
    }
}
