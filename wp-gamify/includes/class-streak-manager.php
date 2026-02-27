<?php
/**
 * WP Gamify - Streak Manager
 *
 * Gunluk giris streak takibi, katlayan XP oduller,
 * dogum gunu ve yildonumu XP kontrolleri.
 *
 * @package    WPGamify
 * @subpackage Includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPGamify_Streak_Manager {

    /**
     * Record a login activity for streak tracking.
     *
     * Called from login hooks (wp_login).
     * Logic:
     * - Get user's streak row (create if not exists).
     * - Compare last_activity_date with today (wp_timezone).
     * - If same day: do nothing (already counted).
     * - If yesterday (or within tolerance): increment streak.
     * - If older: reset streak to 1.
     * - Award streak XP based on current day.
     * - If cycle_reset enabled and streak >= max_day: reset to 0.
     *
     * @param int $user_id WordPress user ID.
     */
    public static function record_activity( int $user_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'gamify_streaks';

        $today    = wp_date( 'Y-m-d', null, wp_timezone() );
        $now      = current_time( 'mysql', false );
        $settings = self::get_settings();

        // Get or create streak row.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT current_streak, max_streak, last_activity_date, streak_xp_today
                 FROM {$table}
                 WHERE user_id = %d",
                $user_id
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            // First ever login -- create row with streak = 1.
            $streak_xp = self::calculate_streak_xp( 1 );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->insert(
                $table,
                [
                    'user_id'            => $user_id,
                    'current_streak'     => 1,
                    'max_streak'         => 1,
                    'last_activity_date' => $today,
                    'streak_xp_today'    => $streak_xp,
                    'updated_at'         => $now,
                ],
                [ '%d', '%d', '%d', '%s', '%d', '%s' ]
            );

            self::award_streak_xp( $user_id, $streak_xp, 1 );
            self::run_bonus_checks( $user_id );
            return;
        }

        $last_date      = $row['last_activity_date'];
        $current_streak = (int) $row['current_streak'];
        $max_streak     = (int) $row['max_streak'];

        // Same day -- already counted.
        if ( $last_date === $today ) {
            // Still run bonus checks (birthday/anniversary) in case they weren't processed yet.
            self::run_bonus_checks( $user_id );
            return;
        }

        // Calculate day difference.
        $today_dt    = new DateTimeImmutable( $today, wp_timezone() );
        $last_dt     = new DateTimeImmutable( $last_date, wp_timezone() );
        $diff_days   = (int) $today_dt->diff( $last_dt )->days;
        $tolerance   = ! empty( $settings['streak_tolerance'] );
        $max_gap     = $tolerance ? 2 : 1; // 1 = must be consecutive, 2 = allow 1 day gap.

        if ( $diff_days <= $max_gap ) {
            // Continue streak.
            $new_streak = $current_streak + 1;
        } else {
            // Streak broken -- reset to 1.
            $new_streak = 1;
        }

        // Check cycle reset: if enabled and streak >= max_day, reset to 1 (new cycle).
        $cycle_reset = ! empty( $settings['streak_cycle_reset'] );
        $max_day     = (int) ( $settings['streak_max_day'] ?? 7 );

        if ( $cycle_reset && $new_streak > $max_day ) {
            $new_streak = 1;
        }

        $new_max   = max( $max_streak, $new_streak );
        $streak_xp = self::calculate_streak_xp( $new_streak );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->update(
            $table,
            [
                'current_streak'     => $new_streak,
                'max_streak'         => $new_max,
                'last_activity_date' => $today,
                'streak_xp_today'    => $streak_xp,
                'updated_at'         => $now,
            ],
            [ 'user_id' => $user_id ],
            [ '%d', '%d', '%s', '%d', '%s' ],
            [ '%d' ]
        );

        self::award_streak_xp( $user_id, $streak_xp, $new_streak );
        self::run_bonus_checks( $user_id );
    }

    /**
     * Calculate XP for a given streak day.
     *
     * Formula: base_xp * multiplier^(min(day, max_day) - 1)
     * Default: base=2, multiplier=2, max_day=7 => capped at day 7.
     * Day 1: 2, Day 2: 4, Day 3: 8, Day 4: 16, Day 5: 32, Day 6: 64, Day 7: 64 (capped).
     *
     * @param int $day The streak day number (1-based).
     * @return int XP amount for that streak day.
     */
    public static function calculate_streak_xp( int $day ): int {
        if ( $day < 1 ) {
            return 0;
        }

        $settings   = self::get_settings();
        $base_xp    = (int) ( $settings['streak_base_xp'] ?? 2 );
        $multiplier = (float) ( $settings['streak_multiplier'] ?? 2.0 );
        $max_day    = (int) ( $settings['streak_max_day'] ?? 7 );

        // Cap the exponent at max_day.
        $effective_day = min( $day, $max_day );
        $exponent      = $effective_day - 1;

        return (int) round( $base_xp * ( $multiplier ** $exponent ) );
    }

    /**
     * Get user's current streak data.
     *
     * @param int $user_id WordPress user ID.
     * @return array Keys: current_streak, max_streak, last_activity_date, streak_xp_today, updated_at.
     *               Returns defaults if no row exists.
     */
    public static function get_streak( int $user_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'gamify_streaks';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT current_streak, max_streak, last_activity_date, streak_xp_today, updated_at
                 FROM {$table}
                 WHERE user_id = %d",
                $user_id
            ),
            ARRAY_A
        );

        if ( $row ) {
            $row['current_streak']  = (int) $row['current_streak'];
            $row['max_streak']      = (int) $row['max_streak'];
            $row['streak_xp_today'] = (int) $row['streak_xp_today'];
            return $row;
        }

        return [
            'current_streak'     => 0,
            'max_streak'         => 0,
            'last_activity_date' => null,
            'streak_xp_today'    => 0,
            'updated_at'         => null,
        ];
    }

    /**
     * Get streak day count for a user (used by campaign system).
     *
     * @param int $user_id WordPress user ID.
     * @return int Current streak day count (0 if no active streak).
     */
    public static function get_streak_day( int $user_id ): int {
        $streak = self::get_streak( $user_id );
        return $streak['current_streak'];
    }

    /**
     * Reset user's streak (admin action).
     *
     * @param int $user_id WordPress user ID.
     */
    public static function reset_streak( int $user_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'gamify_streaks';
        $now   = current_time( 'mysql', false );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$table} WHERE user_id = %d",
                $user_id
            )
        );

        if ( $exists ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->update(
                $table,
                [
                    'current_streak'     => 0,
                    'last_activity_date' => null,
                    'streak_xp_today'    => 0,
                    'updated_at'         => $now,
                ],
                [ 'user_id' => $user_id ],
                [ '%d', '%s', '%d', '%s' ],
                [ '%d' ]
            );
        }
    }

    /**
     * Daily maintenance: check for broken streaks.
     *
     * Users whose last_activity_date is older than the allowed gap
     * get their current_streak reset to 0.
     * Called by daily cron.
     */
    public static function daily_maintenance(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'gamify_streaks';

        $settings  = self::get_settings();
        $tolerance = ! empty( $settings['streak_tolerance'] );
        $now       = current_time( 'mysql', false );

        // If tolerance is enabled, allow 2-day gap (yesterday + day before).
        // Otherwise, only yesterday is valid.
        $cutoff_days = $tolerance ? 2 : 1;

        $today     = wp_date( 'Y-m-d', null, wp_timezone() );
        $today_dt  = new DateTimeImmutable( $today, wp_timezone() );
        $cutoff_dt = $today_dt->modify( "-{$cutoff_days} days" );
        $cutoff    = $cutoff_dt->format( 'Y-m-d' );

        // Reset streaks for users whose last activity is before the cutoff.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET current_streak = 0,
                     streak_xp_today = 0,
                     updated_at = %s
                 WHERE last_activity_date IS NOT NULL
                 AND last_activity_date < %s
                 AND current_streak > 0",
                $now,
                $cutoff
            )
        );
    }

    /**
     * Check if today is user's birthday and award XP if enabled.
     *
     * Birthday stored in user meta '_wpgamify_birthday' (format: MM-DD).
     * Only awards once per year (guard: '_wpgamify_birthday_awarded_{year}').
     *
     * @param int $user_id WordPress user ID.
     */
    public static function check_birthday( int $user_id ): void {
        if ( ! WPGamify_Settings::get( 'xp_birthday_enabled', true ) ) {
            return;
        }

        $birthday = get_user_meta( $user_id, '_wpgamify_birthday', true );

        // Fallback: sync from WooCommerce billing_birthday if our meta is empty.
        if ( empty( $birthday ) ) {
            $billing_bday = get_user_meta( $user_id, 'billing_birthday', true );
            if ( ! empty( $billing_bday ) ) {
                // billing_birthday is typically YYYY-MM-DD; extract MM-DD.
                $parts = explode( '-', $billing_bday );
                if ( count( $parts ) >= 3 ) {
                    $birthday = $parts[1] . '-' . $parts[2];
                    update_user_meta( $user_id, '_wpgamify_birthday', $birthday );
                }
            }
        }

        if ( empty( $birthday ) ) {
            return;
        }

        $today_mmdd = wp_date( 'm-d', null, wp_timezone() );
        if ( $birthday !== $today_mmdd ) {
            return;
        }

        // Check yearly guard.
        $year     = wp_date( 'Y', null, wp_timezone() );
        $guard_key = "_wpgamify_birthday_awarded_{$year}";

        if ( get_user_meta( $user_id, $guard_key, true ) ) {
            return;
        }

        $xp = (int) WPGamify_Settings::get( 'xp_birthday_amount', 100 );
        if ( $xp <= 0 ) {
            return;
        }

        // Award XP.
        if ( class_exists( 'WPGamify_XP_Engine' ) ) {
            WPGamify_XP_Engine::award( $user_id, $xp, 'birthday', null, 'Dogum gunu XP odulu' );
        }

        // Set guard to prevent duplicate awards this year.
        update_user_meta( $user_id, $guard_key, '1' );

        /**
         * Fires after birthday XP is awarded.
         *
         * @param int $user_id WordPress user ID.
         * @param int $xp      XP amount awarded.
         */
        do_action( 'gamify_birthday_xp_awarded', $user_id, $xp );
    }

    /**
     * Check registration anniversary and award XP.
     *
     * Uses user_registered date from WP users table.
     * Only awards once per year (guard: '_wpgamify_anniversary_{year}').
     *
     * @param int $user_id WordPress user ID.
     */
    public static function check_anniversary( int $user_id ): void {
        if ( ! WPGamify_Settings::get( 'xp_anniversary_enabled', true ) ) {
            return;
        }

        $user = get_userdata( $user_id );
        if ( ! $user || empty( $user->user_registered ) ) {
            return;
        }

        // Compare month-day of registration with today.
        $reg_dt     = new DateTimeImmutable( $user->user_registered, wp_timezone() );
        $today_mmdd = wp_date( 'm-d', null, wp_timezone() );
        $reg_mmdd   = $reg_dt->format( 'm-d' );

        if ( $reg_mmdd !== $today_mmdd ) {
            return;
        }

        // Don't award on the registration day itself (year must differ).
        $reg_year   = (int) $reg_dt->format( 'Y' );
        $this_year  = (int) wp_date( 'Y', null, wp_timezone() );
        if ( $reg_year === $this_year ) {
            return;
        }

        // Check yearly guard.
        $guard_key = "_wpgamify_anniversary_{$this_year}";
        if ( get_user_meta( $user_id, $guard_key, true ) ) {
            return;
        }

        $xp = (int) WPGamify_Settings::get( 'xp_anniversary_amount', 50 );
        if ( $xp <= 0 ) {
            return;
        }

        $years_member = $this_year - $reg_year;

        // Award XP.
        if ( class_exists( 'WPGamify_XP_Engine' ) ) {
            WPGamify_XP_Engine::award(
                $user_id,
                $xp,
                'anniversary',
                null,
                sprintf( '%d. yil uyelik yildonumu XP odulu', $years_member )
            );
        }

        // Set guard.
        update_user_meta( $user_id, $guard_key, '1' );

        /**
         * Fires after anniversary XP is awarded.
         *
         * @param int $user_id      WordPress user ID.
         * @param int $xp           XP amount awarded.
         * @param int $years_member Number of years since registration.
         */
        do_action( 'gamify_anniversary_xp_awarded', $user_id, $xp, $years_member );
    }

    /**
     * Award streak XP via the XP Engine.
     *
     * @param int $user_id   WordPress user ID.
     * @param int $xp        XP amount.
     * @param int $streak_day Current streak day.
     */
    private static function award_streak_xp( int $user_id, int $xp, int $streak_day ): void {
        if ( $xp <= 0 ) {
            return;
        }

        if ( class_exists( 'WPGamify_XP_Engine' ) ) {
            WPGamify_XP_Engine::award(
                $user_id,
                $xp,
                'streak',
                null,
                sprintf( 'Streak Gun %d - %d XP', $streak_day, $xp )
            );
        }
    }

    /**
     * Run birthday and anniversary checks on login.
     *
     * @param int $user_id WordPress user ID.
     */
    private static function run_bonus_checks( int $user_id ): void {
        self::check_birthday( $user_id );
        self::check_anniversary( $user_id );
    }

    /**
     * Get plugin settings from wp_options (single JSON).
     *
     * @return array Settings array with streak-related defaults applied.
     */
    private static function get_settings(): array {
        $settings = get_option( 'wpgamify_settings', [] );

        if ( is_string( $settings ) ) {
            $settings = json_decode( $settings, true );
        }

        $defaults = [
            'streak_base_xp'        => 2,
            'streak_multiplier'     => 2.0,
            'streak_max_day'        => 7,
            'streak_tolerance'      => false,
            'streak_cycle_reset'    => true,
        ];

        return wp_parse_args( $settings ?: [], $defaults );
    }
}
