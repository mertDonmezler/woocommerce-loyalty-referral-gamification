<?php
/**
 * WP Gamify - Campaign Manager
 *
 * XP carpan kampanya motoru (Phase 1: basit option-based depolama).
 * Phase 3'te campaigns tablosuna migrate edilecek, bu sinif arayuz olarak korunacak.
 *
 * @package    WPGamify
 * @subpackage Includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPGamify_Campaign_Manager {

    /**
     * Option keys for Phase 1 simple campaign storage.
     */
    private static string $opt_multiplier = 'wpgamify_campaign_multiplier';
    private static string $opt_label      = 'wpgamify_campaign_label';
    private static string $opt_start      = 'wpgamify_campaign_start';
    private static string $opt_end        = 'wpgamify_campaign_end';

    /**
     * Whether the filter has been registered.
     */
    private static bool $filter_registered = false;

    /**
     * Register the XP multiplier filter.
     *
     * Should be called once during plugin bootstrap.
     * Hooks into 'gamify_xp_before_award' to apply campaign multiplier.
     */
    public static function init(): void {
        if ( self::$filter_registered ) {
            return;
        }

        add_filter( 'gamify_xp_before_award', [ self::class, 'apply_multiplier' ], 20, 4 );
        self::$filter_registered = true;
    }

    /**
     * Get the active campaign multiplier.
     *
     * In Phase 1, reads from simple wp_options.
     * In Phase 3, will query the campaigns table.
     *
     * @return float Multiplier value. 1.0 = no campaign active, 2.0 = double XP, etc.
     */
    public static function get_active_multiplier(): float {
        $campaign = self::get_active_campaign();

        if ( $campaign === null ) {
            return 1.0;
        }

        return $campaign['multiplier'];
    }

    /**
     * Check if there's an active campaign right now.
     *
     * Validates start/end dates against current time in wp_timezone.
     *
     * @return array|null Campaign data array or null if no active campaign.
     *                    Keys: multiplier (float), label (string), start (string), end (string)
     */
    public static function get_active_campaign(): ?array {
        $multiplier = (float) get_option( self::$opt_multiplier, 0 );
        $label      = (string) get_option( self::$opt_label, '' );
        $start      = (string) get_option( self::$opt_start, '' );
        $end        = (string) get_option( self::$opt_end, '' );

        // No campaign configured.
        if ( $multiplier <= 0 || empty( $start ) || empty( $end ) ) {
            return null;
        }

        // Validate dates.
        $now = new DateTimeImmutable( 'now', wp_timezone() );

        try {
            $start_dt = new DateTimeImmutable( $start, wp_timezone() );
            $end_dt   = new DateTimeImmutable( $end, wp_timezone() );
        } catch ( \Exception ) {
            return null;
        }

        // Check if current time is within campaign window.
        if ( $now < $start_dt || $now > $end_dt ) {
            return null;
        }

        return [
            'multiplier' => $multiplier,
            'label'      => $label,
            'start'      => $start,
            'end'        => $end,
        ];
    }

    /**
     * Set a simple multiplier campaign (Phase 1 admin feature).
     *
     * Stores campaign data in 4 separate wp_options entries.
     * All dates should be in 'Y-m-d H:i:s' format (wp_timezone context).
     *
     * @param float  $multiplier XP multiplier (e.g., 2.0 for double XP).
     * @param string $label      Campaign label for frontend display (e.g., "2x XP Haftasi!").
     * @param string $start      Start datetime string ('Y-m-d H:i:s').
     * @param string $end        End datetime string ('Y-m-d H:i:s').
     */
    public static function set_simple_campaign( float $multiplier, string $label, string $start, string $end ): void {
        // Validate multiplier is positive.
        $multiplier = max( 0.1, $multiplier );

        // Validate date formats.
        $start_dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $start, wp_timezone() );
        $end_dt   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $end, wp_timezone() );

        if ( ! $start_dt || ! $end_dt ) {
            return;
        }

        // Ensure end is after start.
        if ( $end_dt <= $start_dt ) {
            return;
        }

        update_option( self::$opt_multiplier, $multiplier, false );
        update_option( self::$opt_label, sanitize_text_field( $label ), false );
        update_option( self::$opt_start, $start_dt->format( 'Y-m-d H:i:s' ), false );
        update_option( self::$opt_end, $end_dt->format( 'Y-m-d H:i:s' ), false );

        /**
         * Fires after a campaign is set.
         *
         * @param float  $multiplier XP multiplier.
         * @param string $label      Campaign label.
         * @param string $start      Start datetime.
         * @param string $end        End datetime.
         */
        do_action( 'gamify_campaign_set', $multiplier, $label, $start, $end );
    }

    /**
     * Clear active campaign.
     *
     * Removes all 4 campaign options from the database.
     */
    public static function clear_campaign(): void {
        delete_option( self::$opt_multiplier );
        delete_option( self::$opt_label );
        delete_option( self::$opt_start );
        delete_option( self::$opt_end );

        /**
         * Fires after a campaign is cleared.
         */
        do_action( 'gamify_campaign_cleared' );
    }

    /**
     * Apply campaign multiplier to XP amount.
     *
     * Hooked via filter: gamify_xp_before_award.
     * Multiplies the XP amount by the active campaign multiplier.
     * The campaign_mult value is recorded in xp_transactions for audit.
     *
     * @param int    $xp      Original XP amount.
     * @param string $source  XP source identifier (e.g., 'order', 'review', 'streak').
     * @param int    $user_id WordPress user ID.
     * @param mixed  $context Additional context (source_id, order object, etc.).
     * @return int Modified XP amount after campaign multiplier applied.
     */
    public static function apply_multiplier( int $xp, string $source, int $user_id, mixed $context ): int {
        $multiplier = self::get_active_multiplier();

        if ( $multiplier === 1.0 ) {
            return $xp;
        }

        return (int) round( $xp * $multiplier );
    }
}
