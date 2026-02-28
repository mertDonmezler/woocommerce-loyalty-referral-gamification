<?php
/**
 * WP Gamify - XP Expiry
 *
 * Belirli sureden eski XP'lerin otomatik olarak dusurulmesi.
 * Gunluk cron ile calisir.
 *
 * @package    WPGamify
 * @subpackage Includes
 * @since      2.0.0
 */

defined( 'ABSPATH' ) || exit;

class WPGamify_XP_Expiry {

    /**
     * Run expiry check (called from daily cron).
     */
    public static function check(): void {
        if ( ! WPGamify_Settings::get( 'xp_expiry_enabled', false ) ) {
            return;
        }

        $expiry_months = (int) WPGamify_Settings::get( 'xp_expiry_months', 12 );
        if ( $expiry_months <= 0 ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'gamify_xp_transactions';

        $cutoff = ( new \DateTimeImmutable( 'now', wp_timezone() ) )
            ->modify( "-{$expiry_months} months" )
            ->format( 'Y-m-d H:i:s' );

        $current_month = wp_date( 'Y-m', null, wp_timezone() );

        // Find users with positive XP earned before cutoff (excluding already-expired entries).
        $expired_users = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, SUM(amount) as expired_xp
             FROM {$table}
             WHERE created_at <= %s
             AND amount > 0
             AND source != 'xp_expired'
             GROUP BY user_id
             HAVING expired_xp > 0
             LIMIT 500",
            $cutoff
        ) );

        if ( empty( $expired_users ) ) {
            return;
        }

        $warn_days = (int) WPGamify_Settings::get( 'xp_expiry_warn_days', 14 );

        foreach ( $expired_users as $row ) {
            $user_id    = (int) $row->user_id;
            $expired_xp = (int) $row->expired_xp;

            if ( $expired_xp <= 0 ) {
                continue;
            }

            // Monthly guard to prevent double processing.
            $guard_key = '_wpgamify_xp_expiry_' . $current_month;
            if ( get_user_meta( $user_id, $guard_key, true ) ) {
                continue;
            }
            update_user_meta( $user_id, $guard_key, current_time( 'mysql' ) );

            // Deduct expired XP.
            WPGamify_XP_Engine::deduct(
                $user_id,
                $expired_xp,
                'xp_expired',
                '',
                sprintf( '%d aydan eski XP suresi doldu', $expiry_months )
            );
        }
    }

    /**
     * Send warning emails for upcoming expiry.
     * Called from daily cron.
     */
    public static function warn(): void {
        if ( ! WPGamify_Settings::get( 'xp_expiry_enabled', false ) ) {
            return;
        }

        $expiry_months = (int) WPGamify_Settings::get( 'xp_expiry_months', 12 );
        $warn_days     = (int) WPGamify_Settings::get( 'xp_expiry_warn_days', 14 );

        if ( $expiry_months <= 0 || $warn_days <= 0 ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'gamify_xp_transactions';

        // Calculate the warning window: XP that will expire in $warn_days.
        $warn_cutoff = ( new \DateTimeImmutable( 'now', wp_timezone() ) )
            ->modify( "-{$expiry_months} months" )
            ->modify( "+{$warn_days} days" )
            ->format( 'Y-m-d H:i:s' );

        $actual_cutoff = ( new \DateTimeImmutable( 'now', wp_timezone() ) )
            ->modify( "-{$expiry_months} months" )
            ->format( 'Y-m-d H:i:s' );

        $current_month = wp_date( 'Y-m', null, wp_timezone() );

        // Find users with XP that will expire within warning window.
        $at_risk = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, SUM(amount) as expiring_xp
             FROM {$table}
             WHERE created_at <= %s
             AND created_at > %s
             AND amount > 0
             AND source != 'xp_expired'
             GROUP BY user_id
             HAVING expiring_xp > 0
             LIMIT 500",
            $warn_cutoff,
            $actual_cutoff
        ) );

        if ( empty( $at_risk ) ) {
            return;
        }

        $expiry_date = ( new \DateTimeImmutable( 'now', wp_timezone() ) )
            ->modify( "+{$warn_days} days" )
            ->format( 'Y-m-d' );

        foreach ( $at_risk as $row ) {
            $user_id     = (int) $row->user_id;
            $expiring_xp = (int) $row->expiring_xp;

            // Monthly warning guard.
            $warn_key = '_wpgamify_xp_warn_' . $current_month;
            if ( get_user_meta( $user_id, $warn_key, true ) ) {
                continue;
            }
            update_user_meta( $user_id, $warn_key, current_time( 'mysql' ) );

            /**
             * Fires when XP is about to expire for a user.
             *
             * @param int    $user_id     WordPress user ID.
             * @param int    $expiring_xp XP amount that will expire.
             * @param string $expiry_date Date when XP will expire (Y-m-d).
             */
            do_action( 'gamify_xp_expiry_warning', $user_id, $expiring_xp, $expiry_date );
        }
    }
}
