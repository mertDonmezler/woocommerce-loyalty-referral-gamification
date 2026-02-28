<?php
/**
 * WP Gamify - Profile Hooks
 *
 * Profil tamamlama XP odulu.
 * Kullanici profilini ilk kez doldurdugunda tek seferlik XP verir.
 *
 * @package    WPGamify
 * @subpackage Hooks
 * @since      2.0.0
 */

defined( 'ABSPATH' ) || exit;

class WPGamify_Profile_Hooks {

    public function __construct() {
        add_action( 'profile_update', [ $this, 'check_profile_completion' ], 10, 2 );
        add_action( 'woocommerce_save_account_details', [ $this, 'check_profile_completion_wc' ], 10, 1 );
    }

    /**
     * WordPress profile_update hook handler.
     */
    public function check_profile_completion( int $user_id, $old_user_data = null ): void {
        $this->maybe_award_profile_xp( $user_id );
    }

    /**
     * WooCommerce account details save handler.
     */
    public function check_profile_completion_wc( int $user_id ): void {
        $this->maybe_award_profile_xp( $user_id );
    }

    /**
     * Check if profile is complete and award XP once.
     */
    private function maybe_award_profile_xp( int $user_id ): void {
        if ( ! WPGamify_Settings::get( 'xp_profile_enabled', true ) ) {
            return;
        }

        // Static guard for same-request duplicate calls.
        static $processed = [];
        if ( isset( $processed[ $user_id ] ) ) {
            return;
        }
        $processed[ $user_id ] = true;

        // One-time guard via user meta.
        if ( get_user_meta( $user_id, '_wpgamify_profile_xp_awarded', true ) ) {
            return;
        }

        // Check profile completeness.
        $first_name = get_user_meta( $user_id, 'first_name', true );
        $last_name  = get_user_meta( $user_id, 'last_name', true );
        $phone      = get_user_meta( $user_id, 'billing_phone', true );
        $address    = get_user_meta( $user_id, 'billing_address_1', true );
        $city       = get_user_meta( $user_id, 'billing_city', true );

        if ( empty( $first_name ) || empty( $last_name ) ) {
            return;
        }
        if ( empty( $phone ) && ( empty( $address ) || empty( $city ) ) ) {
            return;
        }

        $xp = (int) WPGamify_Settings::get( 'xp_profile_amount', 20 );
        if ( $xp <= 0 ) {
            return;
        }

        WPGamify_XP_Engine::award( $user_id, $xp, 'profile', (string) $user_id, 'Profil tamamlama bonusu' );
        update_user_meta( $user_id, '_wpgamify_profile_xp_awarded', '1' );
    }
}
