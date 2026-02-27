<?php
/**
 * Sanitization utility methods.
 *
 * @package PokeHoloCards\Utils
 * @since   3.0.0
 */

namespace PokeHoloCards\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sanitizer provides reusable input validation/sanitization helpers.
 */
class Sanitizer {

    /**
     * Sanitize a yes/no option value.
     *
     * @param string $value Raw value.
     * @return string 'yes' or 'no'.
     */
    public static function yes_no( $value ) {
        return in_array( $value, array( 'yes', 'no' ), true ) ? $value : 'yes';
    }

    /**
     * Sanitize the WooCommerce target option.
     *
     * @param string $value Raw value.
     * @return string One of the allowed target strings.
     */
    public static function woo_target( $value ) {
        $allowed = array( 'product_gallery', 'archive_thumbs', 'both' );
        return in_array( $value, $allowed, true ) ? $value : 'product_gallery';
    }

    /**
     * Sanitize an effect type value against the centralized list.
     *
     * @param string $value Raw value.
     * @return string Sanitized effect type slug.
     */
    public static function effect_type( $value ) {
        $allowed = EffectTypes::get_all();
        return in_array( $value, $allowed, true ) ? $value : 'holo';
    }

    /**
     * Sanitize a float value within a range.
     *
     * @param mixed $value Raw value.
     * @param float $min   Minimum allowed value.
     * @param float $max   Maximum allowed value.
     * @return float Clamped float value.
     */
    public static function float_range( $value, $min = 0, $max = 100 ) {
        $val = floatval( $value );
        return max( $min, min( $max, $val ) );
    }

    /**
     * Validate a CSS length value against safe patterns.
     *
     * @param string $value   Raw CSS value.
     * @param string $default Fallback if invalid.
     * @return string Validated CSS length.
     */
    public static function css_length( $value, $default = '300px' ) {
        $value = trim( $value );
        if ( preg_match( '/^\d+(\.\d+)?\s*(px|em|rem|%|vw|vh|vmin|vmax|ch|ex|cm|mm|in|pt|pc|auto)$/i', $value ) ) {
            return $value;
        }
        return $default;
    }
}
