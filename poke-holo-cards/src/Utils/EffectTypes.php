<?php
/**
 * Centralized effect type registry.
 *
 * @package PokeHoloCards\Utils
 * @since   3.0.0
 */

namespace PokeHoloCards\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * EffectTypes manages the list of available holographic effects.
 */
class EffectTypes {

    /**
     * Return the list of available effect type slugs.
     *
     * Developers can extend the list using the `phc_effect_types` filter.
     *
     * @return string[]
     */
    public static function get_all() {
        $types = array( 'holo', 'rainbow', 'cosmos', 'galaxy', 'prism', 'neon', 'basic', 'vintage', 'aurora', 'glitch' );
        return apply_filters( 'phc_effect_types', $types );
    }

    /**
     * Return a human-readable label for an effect type slug.
     *
     * @param string $slug Effect type slug.
     * @return string Translated label.
     */
    public static function get_label( $slug ) {
        $labels = array(
            'holo'    => __( 'Holographic', 'poke-holo-cards' ),
            'rainbow' => __( 'Rainbow', 'poke-holo-cards' ),
            'cosmos'  => __( 'Cosmos', 'poke-holo-cards' ),
            'galaxy'  => __( 'Galaxy', 'poke-holo-cards' ),
            'prism'   => __( 'Prism', 'poke-holo-cards' ),
            'neon'    => __( 'Neon', 'poke-holo-cards' ),
            'basic'   => __( 'Basic 3D', 'poke-holo-cards' ),
            'vintage' => __( 'Vintage', 'poke-holo-cards' ),
            'aurora'  => __( 'Aurora', 'poke-holo-cards' ),
            'glitch'  => __( 'Glitch', 'poke-holo-cards' ),
        );
        return isset( $labels[ $slug ] ) ? $labels[ $slug ] : ucfirst( $slug );
    }
}
