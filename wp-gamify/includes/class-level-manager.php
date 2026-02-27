<?php
/**
 * WP Gamify - Level Manager
 *
 * Level hesaplama, CRUD, fayda uygulama ve grace period yonetimi.
 * Source of truth: XP + levels_config tablosu.
 * user_levels.current_level sadece cache'dir.
 *
 * @package    WPGamify
 * @subpackage Includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPGamify_Level_Manager {

    /**
     * Static cache for levels config (per-request).
     */
    private static ?array $levels_cache = null;

    /**
     * WP object cache group.
     */
    private static string $cache_group = 'wpgamify';

    /**
     * WP object cache key for levels config.
     */
    private static string $cache_key = 'wpgamify_levels_config';

    /**
     * Cache expiry in seconds (1 hour).
     */
    private static int $cache_expiry = 3600;

    /**
     * Calculate what level a user should be at given their XP.
     *
     * Loops through all levels sorted by xp_required ASC.
     * Returns the highest level where XP >= xp_required.
     * If no levels configured or XP is below the first level, returns 0.
     *
     * @param int $xp Total or rolling XP to evaluate.
     * @return int The calculated level number.
     */
    public static function calculate_level( int $xp ): int {
        $levels  = self::get_all_levels();
        $current = 0;

        foreach ( $levels as $level ) {
            if ( $xp >= (int) $level['xp_required'] ) {
                $current = (int) $level['level_number'];
            } else {
                // Levels are sorted ASC by xp_required, no need to continue.
                break;
            }
        }

        return $current;
    }

    /**
     * Get level config for a specific level number.
     *
     * @param int $level_number The level number to retrieve.
     * @return array|null Level data or null if not found.
     *                    Keys: id, level_number, name, xp_required, benefits, icon_attachment_id, color_hex, sort_order
     */
    public static function get_level( int $level_number ): ?array {
        $levels = self::get_all_levels();

        foreach ( $levels as $level ) {
            if ( (int) $level['level_number'] === $level_number ) {
                return $level;
            }
        }

        return null;
    }

    /**
     * Get all levels config, ordered by xp_required ASC.
     *
     * Uses static variable (per-request) + wp_cache (cross-request).
     *
     * @return array List of level config arrays.
     */
    public static function get_all_levels(): array {
        // Per-request static cache.
        if ( self::$levels_cache !== null ) {
            return self::$levels_cache;
        }

        // WP object cache.
        $cached = wp_cache_get( self::$cache_key, self::$cache_group );
        if ( is_array( $cached ) ) {
            self::$levels_cache = $cached;
            return self::$levels_cache;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'gamify_levels_config';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results(
            "SELECT id, level_number, name, xp_required, benefits, icon_attachment_id, color_hex, sort_order
             FROM {$table}
             ORDER BY xp_required ASC",
            ARRAY_A
        );

        $levels = is_array( $rows ) ? $rows : [];

        // Normalize types.
        foreach ( $levels as &$level ) {
            $level['id']                 = (int) $level['id'];
            $level['level_number']       = (int) $level['level_number'];
            $level['xp_required']        = (int) $level['xp_required'];
            $level['icon_attachment_id'] = $level['icon_attachment_id'] ? (int) $level['icon_attachment_id'] : null;
            $level['sort_order']         = (int) $level['sort_order'];
        }
        unset( $level );

        // Store in both caches.
        wp_cache_set( self::$cache_key, $levels, self::$cache_group, self::$cache_expiry );
        self::$levels_cache = $levels;

        return self::$levels_cache;
    }

    /**
     * Get user's current level data from user_levels cache table.
     *
     * If user has no row, creates one with defaults (level 0, xp 0).
     *
     * @param int $user_id WordPress user ID.
     * @return array Keys: user_id, current_level, total_xp, rolling_xp, grace_until, last_xp_at, updated_at
     */
    public static function get_user_level_data( int $user_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'gamify_user_levels';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id, current_level, total_xp, rolling_xp, grace_until, last_xp_at, updated_at
                 FROM {$table}
                 WHERE user_id = %d",
                $user_id
            ),
            ARRAY_A
        );

        if ( $row ) {
            $row['user_id']       = (int) $row['user_id'];
            $row['current_level'] = (int) $row['current_level'];
            $row['total_xp']      = (int) $row['total_xp'];
            $row['rolling_xp']    = (int) $row['rolling_xp'];
            return $row;
        }

        // Create default row (omit grace_until and last_xp_at to let MySQL default them to NULL).
        $now = current_time( 'mysql', false );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->insert(
            $table,
            [
                'user_id'       => $user_id,
                'current_level' => 0,
                'total_xp'      => 0,
                'rolling_xp'    => 0,
                'updated_at'    => $now,
            ],
            [ '%d', '%d', '%d', '%d', '%s' ]
        );

        return [
            'user_id'       => $user_id,
            'current_level' => 0,
            'total_xp'      => 0,
            'rolling_xp'    => 0,
            'grace_until'   => null,
            'last_xp_at'    => null,
            'updated_at'    => $now,
        ];
    }

    /**
     * Recalculate and sync a user's level.
     *
     * Compares old level with new calculated level.
     * Fires gamify_level_up or gamify_level_down hooks.
     * Handles grace period for level down.
     *
     * @param int $user_id WordPress user ID.
     */
    public static function sync_level( int $user_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'gamify_user_levels';

        $data      = self::get_user_level_data( $user_id );
        $old_level = $data['current_level'];

        // Determine which XP to use based on level mode setting.
        $settings   = self::get_settings();
        $level_mode = $settings['level_mode'] ?? 'alltime';
        $xp         = match ( $level_mode ) {
            'rolling' => $data['rolling_xp'],
            default   => $data['total_xp'],
        };

        $new_level = self::calculate_level( $xp );

        if ( $new_level === $old_level ) {
            return;
        }

        $now = current_time( 'mysql', false );

        if ( $new_level > $old_level ) {
            // Level UP -- clear any grace period and update.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET grace_until = NULL, current_level = %d, updated_at = %s WHERE user_id = %d",
                $new_level,
                $now,
                $user_id
            ) );

            /**
             * Fires when a user's level increases.
             *
             * @param int $user_id  WordPress user ID.
             * @param int $old_level Previous level number.
             * @param int $new_level New level number.
             */
            do_action( 'gamify_level_up', $user_id, $old_level, $new_level );
            return;
        }

        // Level DOWN scenario.
        $has_grace = ! empty( $data['grace_until'] );

        if ( $has_grace ) {
            $now_dt   = new DateTimeImmutable( 'now', wp_timezone() );
            $grace_dt = new DateTimeImmutable( $data['grace_until'], wp_timezone() );

            if ( $now_dt <= $grace_dt ) {
                // Grace still active -- keep current level, do nothing.
                return;
            }

            // Grace expired -- actually downgrade.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET grace_until = NULL, current_level = %d, updated_at = %s WHERE user_id = %d",
                $new_level,
                $now,
                $user_id
            ) );

            /**
             * Fires when a user's level decreases (after grace expired).
             *
             * @param int $user_id  WordPress user ID.
             * @param int $old_level Previous level number.
             * @param int $new_level New level number.
             */
            do_action( 'gamify_level_down', $user_id, $old_level, $new_level );
            return;
        }

        // Not in grace yet -- start grace period instead of immediate downgrade.
        self::start_grace( $user_id, $old_level );
    }

    /**
     * Get the benefits for a specific level.
     *
     * Returns decoded JSON benefits array.
     * Example: ['discount' => 10, 'free_shipping' => true, 'early_access' => true, 'installment' => true]
     *
     * @param int $level_number The level number.
     * @return array Benefits as associative array, empty if level not found.
     */
    public static function get_level_benefits( int $level_number ): array {
        $level = self::get_level( $level_number );

        if ( ! $level ) {
            return [];
        }

        $benefits = json_decode( $level['benefits'] ?? '{}', true );
        return is_array( $benefits ) ? $benefits : [];
    }

    /**
     * Get user's active benefits based on their current level.
     *
     * If user is in grace, returns benefits of their grace-protected level.
     *
     * @param int $user_id WordPress user ID.
     * @return array Active benefits associative array.
     */
    public static function get_user_benefits( int $user_id ): array {
        $data = self::get_user_level_data( $user_id );
        return self::get_level_benefits( $data['current_level'] );
    }

    /**
     * Get XP progress toward next level.
     *
     * @param int $user_id WordPress user ID.
     * @return array Keys: current_level (config array|null), next_level (config array|null),
     *               xp_needed (int), progress_pct (float), current_xp (int)
     */
    public static function get_progress( int $user_id ): array {
        $data     = self::get_user_level_data( $user_id );
        $settings = self::get_settings();

        $level_mode = $settings['level_mode'] ?? 'alltime';
        $current_xp = match ( $level_mode ) {
            'rolling' => $data['rolling_xp'],
            default   => $data['total_xp'],
        };

        $current_level_number = $data['current_level'];
        $current_level_config = self::get_level( $current_level_number );
        $levels               = self::get_all_levels();
        $next_level_config    = null;

        // Find next level (first level whose level_number > current).
        foreach ( $levels as $level ) {
            if ( (int) $level['level_number'] > $current_level_number ) {
                $next_level_config = $level;
                break;
            }
        }

        $current_level_xp = $current_level_config ? (int) $current_level_config['xp_required'] : 0;
        $next_level_xp    = $next_level_config ? (int) $next_level_config['xp_required'] : 0;

        $xp_needed    = 0;
        $progress_pct = 100.0;

        if ( $next_level_config ) {
            $xp_needed  = max( 0, $next_level_xp - $current_xp );
            $range      = $next_level_xp - $current_level_xp;
            $progress   = $current_xp - $current_level_xp;

            $progress_pct = $range > 0
                ? round( min( 100.0, max( 0.0, ( $progress / $range ) * 100 ) ), 2 )
                : 100.0;
        }

        return [
            'current_level' => $current_level_config,
            'next_level'    => $next_level_config,
            'xp_needed'     => $xp_needed,
            'progress_pct'  => $progress_pct,
            'current_xp'    => $current_xp,
        ];
    }

    /**
     * Check if user is in grace period.
     *
     * @param int $user_id WordPress user ID.
     * @return bool True if grace period is active and not yet expired.
     */
    public static function is_in_grace( int $user_id ): bool {
        $data = self::get_user_level_data( $user_id );

        if ( empty( $data['grace_until'] ) ) {
            return false;
        }

        $now   = new DateTimeImmutable( 'now', wp_timezone() );
        $grace = new DateTimeImmutable( $data['grace_until'], wp_timezone() );

        return $now <= $grace;
    }

    /**
     * Start grace period for a user.
     *
     * Called when user's XP drops below their current level threshold.
     * Grace duration from settings: level_grace_days (default 14).
     *
     * @param int $user_id       WordPress user ID.
     * @param int $current_level The level being protected during grace.
     */
    public static function start_grace( int $user_id, int $current_level ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'gamify_user_levels';

        $settings   = self::get_settings();
        $grace_days = (int) ( $settings['level_grace_days'] ?? 14 );

        $now         = new DateTimeImmutable( 'now', wp_timezone() );
        $grace_until = $now->modify( "+{$grace_days} days" );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->update(
            $table,
            [
                'grace_until' => $grace_until->format( 'Y-m-d H:i:s' ),
                'updated_at'  => $now->format( 'Y-m-d H:i:s' ),
            ],
            [ 'user_id' => $user_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        /**
         * Fires when a grace period starts for a user.
         *
         * @param int    $user_id       WordPress user ID.
         * @param int    $current_level The level being protected.
         * @param string $grace_until   Grace expiration datetime.
         */
        do_action( 'gamify_grace_period_started', $user_id, $current_level, $grace_until->format( 'Y-m-d H:i:s' ) );
    }

    /**
     * Process grace period expirations (called by daily cron).
     *
     * For each user whose grace has expired, recalculate their level.
     * If XP is still insufficient, downgrade and fire gamify_level_down hook.
     */
    public static function process_grace_expirations(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'gamify_user_levels';

        $now = current_time( 'mysql', false );

        // Find users with expired grace periods.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $expired_users = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT user_id FROM {$table}
                 WHERE grace_until IS NOT NULL
                 AND grace_until < %s",
                $now
            )
        );

        if ( empty( $expired_users ) ) {
            return;
        }

        $settings   = self::get_settings();
        $level_mode = $settings['level_mode'] ?? 'alltime';

        foreach ( $expired_users as $uid ) {
            $user_id   = (int) $uid;
            $data      = self::get_user_level_data( $user_id );
            $old_level = $data['current_level'];

            $xp = match ( $level_mode ) {
                'rolling' => $data['rolling_xp'],
                default   => $data['total_xp'],
            };

            $new_level = self::calculate_level( $xp );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET grace_until = NULL, current_level = %d, updated_at = %s WHERE user_id = %d",
                $new_level,
                $now,
                $user_id
            ) );

            if ( $new_level < $old_level ) {
                /** This action is documented in self::sync_level() */
                do_action( 'gamify_level_down', $user_id, $old_level, $new_level );
            }
        }
    }

    /**
     * ADMIN CRUD: Create a new level.
     *
     * @param array $data Level data. Required: level_number, name, xp_required.
     *                    Optional: benefits (JSON string), icon_attachment_id, color_hex, sort_order.
     * @return int|false Inserted row ID on success, false on failure.
     */
    public static function create_level( array $data ): int|false {
        global $wpdb;
        $table = $wpdb->prefix . 'gamify_levels_config';

        $now = current_time( 'mysql', false );

        $insert = [
            'level_number'       => (int) ( $data['level_number'] ?? 0 ),
            'name'               => sanitize_text_field( $data['name'] ?? '' ),
            'xp_required'        => (int) ( $data['xp_required'] ?? 0 ),
            'benefits'           => $data['benefits'] ?? '{}',
            'icon_attachment_id' => ! empty( $data['icon_attachment_id'] ) ? (int) $data['icon_attachment_id'] : null,
            'color_hex'          => sanitize_hex_color( $data['color_hex'] ?? '#6366f1' ) ?: '#6366f1',
            'sort_order'         => (int) ( $data['sort_order'] ?? 0 ),
            'created_at'         => $now,
            'updated_at'         => $now,
        ];

        // Validate benefits JSON.
        if ( is_array( $data['benefits'] ?? null ) ) {
            $insert['benefits'] = wp_json_encode( $data['benefits'] );
        }

        // Validate required fields.
        if ( empty( $insert['name'] ) || $insert['level_number'] < 1 || $insert['xp_required'] < 0 ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->insert(
            $table,
            $insert,
            [ '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s' ]
        );

        if ( $result === false ) {
            return false;
        }

        self::invalidate_cache();

        return (int) $wpdb->insert_id;
    }

    /**
     * ADMIN CRUD: Update a level.
     *
     * @param int   $id   The level row ID (primary key).
     * @param array $data Fields to update.
     * @return bool True on success, false on failure.
     */
    public static function update_level( int $id, array $data ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'gamify_levels_config';

        $update  = [];
        $formats = [];

        if ( isset( $data['level_number'] ) ) {
            $update['level_number'] = (int) $data['level_number'];
            $formats[]              = '%d';
        }

        if ( isset( $data['name'] ) ) {
            $update['name'] = sanitize_text_field( $data['name'] );
            $formats[]      = '%s';
        }

        if ( isset( $data['xp_required'] ) ) {
            $update['xp_required'] = (int) $data['xp_required'];
            $formats[]             = '%d';
        }

        if ( isset( $data['benefits'] ) ) {
            $update['benefits'] = is_array( $data['benefits'] )
                ? wp_json_encode( $data['benefits'] )
                : $data['benefits'];
            $formats[] = '%s';
        }

        if ( array_key_exists( 'icon_attachment_id', $data ) ) {
            $update['icon_attachment_id'] = ! empty( $data['icon_attachment_id'] )
                ? (int) $data['icon_attachment_id']
                : null;
            $formats[] = '%d';
        }

        if ( isset( $data['color_hex'] ) ) {
            $update['color_hex'] = sanitize_hex_color( $data['color_hex'] ) ?: '#6366f1';
            $formats[]           = '%s';
        }

        if ( isset( $data['sort_order'] ) ) {
            $update['sort_order'] = (int) $data['sort_order'];
            $formats[]            = '%d';
        }

        if ( empty( $update ) ) {
            return false;
        }

        $update['updated_at'] = current_time( 'mysql', false );
        $formats[]            = '%s';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->update(
            $table,
            $update,
            [ 'id' => $id ],
            $formats,
            [ '%d' ]
        );

        if ( $result === false ) {
            return false;
        }

        self::invalidate_cache();

        return true;
    }

    /**
     * ADMIN CRUD: Delete a level.
     *
     * After deletion, recalculates all affected users' levels.
     * XP is never lost -- users are simply re-evaluated against remaining config.
     *
     * @param int $id The level row ID (primary key).
     * @return bool True on success, false on failure.
     */
    public static function delete_level( int $id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'gamify_levels_config';

        // Get the level data before deleting (for affected-user lookup).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $level = $wpdb->get_row(
            $wpdb->prepare( "SELECT level_number FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );

        if ( ! $level ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

        if ( $result === false ) {
            return false;
        }

        self::invalidate_cache();

        // Recalculate all users currently at the deleted level.
        $user_table = $wpdb->prefix . 'gamify_user_levels';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $affected_user_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT user_id FROM {$user_table} WHERE current_level = %d",
                (int) $level['level_number']
            )
        );

        foreach ( $affected_user_ids as $uid ) {
            self::sync_level( (int) $uid );
        }

        return true;
    }

    /**
     * Get count of users at each level (for dashboard/stats).
     *
     * @return array Associative array: level_number => count.
     */
    public static function get_level_distribution(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'gamify_user_levels';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results(
            "SELECT current_level, COUNT(*) AS user_count
             FROM {$table}
             GROUP BY current_level
             ORDER BY current_level ASC",
            ARRAY_A
        );

        $distribution = [];
        foreach ( $rows as $row ) {
            $distribution[ (int) $row['current_level'] ] = (int) $row['user_count'];
        }

        return $distribution;
    }

    /**
     * Invalidate levels config cache (static + WP object cache).
     *
     * Call this after any CRUD operation on levels_config.
     */
    public static function invalidate_cache(): void {
        self::$levels_cache = null;
        wp_cache_delete( self::$cache_key, self::$cache_group );
    }

    /**
     * Get plugin settings from wp_options (single JSON).
     *
     * @return array Settings array with defaults applied.
     */
    private static function get_settings(): array {
        $settings = get_option( 'wpgamify_settings', [] );

        if ( is_string( $settings ) ) {
            $settings = json_decode( $settings, true );
        }

        $defaults = [
            'level_mode'       => 'alltime',
            'level_grace_days' => 14,
        ];

        return wp_parse_args( $settings ?: [], $defaults );
    }
}
