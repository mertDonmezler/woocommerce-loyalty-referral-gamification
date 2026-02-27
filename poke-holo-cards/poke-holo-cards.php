<?php
/**
 * Plugin Name: WooCommerce Holo Cards - 3D Holographic Card Effect
 * Plugin URI:  https://github.com/mertDonmezler/poke-holo-cards
 * Description: Add stunning 3D holographic card effects to any image. Supports WooCommerce product images, shortcodes, Gutenberg blocks, and CSS class triggers. Works standalone or with WooCommerce.
 * Version:     1.0.0
 * Author:      Mert Donmezler
 * Author URI:  https://mertdonmezler.com
 * License:     GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: poke-holo-cards
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PHC_VERSION', '1.0.0' );
define( 'PHC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PHC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* ────────────────────────────────────────
   PSR-4 AUTOLOADER
   ──────────────────────────────────────── */

spl_autoload_register( function ( $class ) {
    $prefix = 'PokeHoloCards\\';
    $len    = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }
    $relative = substr( $class, $len );
    $file     = PHC_PLUGIN_DIR . 'src' . DIRECTORY_SEPARATOR
              . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';
    if ( file_exists( $file ) ) {
        require $file;
    }
} );

/* ────────────────────────────────────────
   LIFECYCLE HOOKS
   ──────────────────────────────────────── */

register_activation_hook( __FILE__, array( 'PokeHoloCards\\Core\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PokeHoloCards\\Core\\Plugin', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'PokeHoloCards\\Core\\Plugin', 'uninstall' ) );

/* ────────────────────────────────────────
   BOOT
   ──────────────────────────────────────── */

\PokeHoloCards\Core\Plugin::instance();

/* ────────────────────────────────────────
   BACKWARD-COMPATIBLE GLOBAL FUNCTIONS
   Preserved so that themes, child plugins,
   and filters referencing the old function
   names continue to work.
   ──────────────────────────────────────── */

function phc_get_effect_types() {
    return \PokeHoloCards\Utils\EffectTypes::get_all();
}

function phc_get_effect_label( $slug ) {
    return \PokeHoloCards\Utils\EffectTypes::get_label( $slug );
}

function phc_sanitize_yes_no( $value ) {
    return \PokeHoloCards\Utils\Sanitizer::yes_no( $value );
}

function phc_sanitize_woo_target( $value ) {
    return \PokeHoloCards\Utils\Sanitizer::woo_target( $value );
}

function phc_sanitize_effect_type( $value ) {
    return \PokeHoloCards\Utils\Sanitizer::effect_type( $value );
}

function phc_sanitize_float_range( $value, $min = 0, $max = 100 ) {
    return \PokeHoloCards\Utils\Sanitizer::float_range( $value, $min, $max );
}

function phc_sanitize_css_length( $value, $default = '300px' ) {
    return \PokeHoloCards\Utils\Sanitizer::css_length( $value, $default );
}

function phc_get_settings( $force_refresh = false ) {
    return \PokeHoloCards\Core\Settings::get_all( $force_refresh );
}

function phc_get_product_meta( $product_id ) {
    return \PokeHoloCards\Integration\WooCommerce::get_product_meta( $product_id );
}

function phc_shortcode( $atts ) {
    return \PokeHoloCards\Frontend\Shortcode::render( $atts );
}

/**
 * Programmatic API: Render a holo card from PHP.
 *
 * Used by external plugins to wrap images/emojis
 * in holographic card effects.
 *
 * @param string $img    Image URL or inline content (emoji, SVG).
 * @param array  $args   Optional. Override shortcode attributes.
 *                       Keys: effect, width, sparkle, glow, radius, class, showcase.
 * @return string HTML output.
 */
function phc_render_card( $img, $args = array() ) {
    $defaults = array(
        'img'      => $img,
        'effect'   => 'holo',
        'width'    => '80px',
        'sparkle'  => 'no',
        'showcase' => 'no',
        'glow'     => '',
        'radius'   => '',
        'class'    => '',
    );
    $atts = wp_parse_args( $args, $defaults );
    return \PokeHoloCards\Frontend\Shortcode::render( $atts );
}

/**
 * Check if WooCommerce Holo Cards is available for programmatic use.
 *
 * @return bool
 */
function phc_is_available() {
    return true;
}

/**
 * Get the rarity tier for a given effect type.
 *
 * @param string $effect Effect slug (holo, cosmos, etc.).
 * @return string Rarity tier: common, uncommon, rare, epic, legendary.
 */
function phc_get_rarity( $effect ) {
    /**
     * Filter the effect-to-rarity mapping.
     *
     * @param array $map Keys are effect slugs, values are rarity tiers.
     */
    $map = apply_filters( 'phc_collection_rarity_map', array(
        'basic'   => 'common',
        'holo'    => 'uncommon',
        'rainbow' => 'uncommon',
        'cosmos'  => 'rare',
        'galaxy'  => 'rare',
        'prism'   => 'rare',
        'neon'    => 'epic',
        'vintage' => 'epic',
        'aurora'  => 'epic',
        'glitch'  => 'legendary',
    ) );
    return isset( $map[ $effect ] ) ? $map[ $effect ] : 'common';
}
