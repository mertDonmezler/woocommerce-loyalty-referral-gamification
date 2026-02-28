<?php
/**
 * Gorilla Loyalty -> WP Gamify Data Migration
 *
 * Gorilla Loyalty v2.0.0 aktive edildiginde, eger:
 * - WP Gamify aktif VE
 * - gorilla_xp_log tablosu mevcut VE
 * - _gorilla_migrated_to_gamify option YOKSA
 *
 * Otomatik migration calisir.
 *
 * @package Gorilla_Loyalty_Gamification
 * @since   2.0.0
 */

if (!defined('ABSPATH')) exit;

class Gorilla_Migration_To_Gamify {

    /**
     * Run the full migration.
     *
     * @return bool True if migration completed, false if skipped or failed.
     */
    public static function run(): bool {
        if (get_option('_gorilla_migrated_to_gamify')) {
            return false;
        }

        if (!class_exists('WPGamify_XP_Engine')) {
            return false;
        }

        global $wpdb;

        // Check if old gorilla_xp_log table exists.
        $old_table = $wpdb->prefix . 'gorilla_xp_log';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $old_table));

        if (!$table_exists) {
            // No old data to migrate, just mark as done.
            update_option('_gorilla_migrated_to_gamify', GORILLA_LG_VERSION);
            return true;
        }

        // Run migration steps.
        self::migrate_settings();
        self::migrate_xp_log();
        self::migrate_streaks();
        self::migrate_birthdays();
        self::sync_user_levels();

        // Mark migration as complete.
        update_option('_gorilla_migrated_to_gamify', GORILLA_LG_VERSION);

        /**
         * Fires after Gorilla -> WP Gamify migration is complete.
         */
        do_action('gorilla_migration_to_gamify_complete');

        return true;
    }

    /**
     * Migrate XP settings to WP Gamify.
     */
    private static function migrate_settings(): void {
        if (!class_exists('WPGamify_Settings')) return;

        $mapping = array(
            'gorilla_lr_xp_per_order_rate'  => 'xp_order_per_currency',
            'gorilla_lr_xp_review'          => 'xp_review_amount',
            'gorilla_lr_xp_first_order'     => 'xp_first_order_bonus',
            'gorilla_lr_xp_register'        => 'xp_registration_amount',
            'gorilla_lr_birthday_xp'        => 'xp_birthday_amount',
            'gorilla_lr_anniversary_xp'     => 'xp_anniversary_amount',
            'gorilla_lr_streak_daily_xp'    => 'streak_base_xp',
            'gorilla_lr_xp_referral'        => 'xp_referral_amount',
            'gorilla_lr_xp_affiliate'       => 'xp_affiliate_amount',
            'gorilla_lr_xp_profile'         => 'xp_profile_amount',
            'gorilla_lr_xp_expiry_enabled'  => 'xp_expiry_enabled',
            'gorilla_lr_xp_expiry_months'   => 'xp_expiry_months',
            'gorilla_lr_xp_expiry_warn_days'=> 'xp_expiry_warn_days',
        );

        foreach ($mapping as $old_key => $new_key) {
            $val = get_option($old_key);
            if ($val !== false) {
                WPGamify_Settings::set($new_key, $val);
            }
        }

        // Migrate bonus campaign to WP Gamify Campaign Manager.
        if (class_exists('WPGamify_Campaign_Manager')) {
            $bonus_enabled = get_option('gorilla_lr_bonus_enabled', 'no');
            if ($bonus_enabled === 'yes') {
                $mult  = floatval(get_option('gorilla_lr_bonus_multiplier', 1.5));
                $label = get_option('gorilla_lr_bonus_label', '');
                $start = get_option('gorilla_lr_bonus_start', '');
                $end   = get_option('gorilla_lr_bonus_end', '');

                if ($mult > 0 && $start && $end) {
                    // Convert date format if needed (Y-m-d -> Y-m-d H:i:s).
                    if (strlen($start) === 10) $start .= ' 00:00:00';
                    if (strlen($end) === 10) $end .= ' 23:59:59';
                    WPGamify_Campaign_Manager::set_simple_campaign($mult, $label, $start, $end);
                }
            }
        }
    }

    /**
     * Migrate XP transaction log to WP Gamify transactions table.
     */
    private static function migrate_xp_log(): void {
        global $wpdb;

        $old_table = $wpdb->prefix . 'gorilla_xp_log';
        $new_table = $wpdb->prefix . 'gamify_xp_transactions';

        // Check if XP log migration already ran (not just if table has data from WP Gamify standalone use).
        $migration_marker = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$new_table} WHERE source = %s LIMIT 1",
            '_gorilla_migrated'
        ));
        if ($migration_marker > 0) {
            return;
        }

        // Batch migrate in chunks of 500.
        $batch_size = 500;
        $offset = 0;

        do {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT user_id, amount, reason, reference_type, reference_id, created_at
                 FROM {$old_table}
                 ORDER BY id ASC
                 LIMIT %d OFFSET %d",
                $batch_size, $offset
            ), ARRAY_A);

            if (empty($rows)) break;

            foreach ($rows as $row) {
                $wpdb->insert($new_table, array(
                    'user_id'       => intval($row['user_id']),
                    'amount'        => intval($row['amount']),
                    'source'        => sanitize_key($row['reference_type'] ?: 'manual'),
                    'source_id'     => (string) ($row['reference_id'] ?: ''),
                    'campaign_mult' => 1.00,
                    'note'          => sanitize_text_field($row['reason'] ?: ''),
                    'created_at'    => $row['created_at'],
                ), array('%d', '%d', '%s', '%s', '%f', '%s', '%s'));
            }

            $offset += $batch_size;
        } while (count($rows) === $batch_size);

        // Mark migration complete for idempotency
        $wpdb->insert($new_table, array(
            'user_id'       => 0,
            'amount'        => 0,
            'source'        => '_gorilla_migrated',
            'source_id'     => '',
            'campaign_mult' => 1.00,
            'note'          => 'XP log migration marker',
            'created_at'    => current_time('mysql'),
        ), array('%d', '%d', '%s', '%s', '%f', '%s', '%s'));
    }

    /**
     * Migrate login streak data from user meta to WP Gamify streaks table.
     */
    private static function migrate_streaks(): void {
        global $wpdb;

        $new_table = $wpdb->prefix . 'gamify_streaks';
        $now = current_time('mysql');

        // Get all users with streak data.
        $users_with_streaks = $wpdb->get_results(
            "SELECT u.ID AS user_id,
                    COALESCE(s.meta_value, '0') AS current_streak,
                    COALESCE(b.meta_value, '0') AS max_streak,
                    d.meta_value AS last_activity_date
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} s ON u.ID = s.user_id AND s.meta_key = '_gorilla_login_streak'
             LEFT JOIN {$wpdb->usermeta} b ON u.ID = b.user_id AND b.meta_key = '_gorilla_login_streak_best'
             LEFT JOIN {$wpdb->usermeta} d ON u.ID = d.user_id AND d.meta_key = '_gorilla_login_last_date'
             WHERE CAST(s.meta_value AS UNSIGNED) > 0",
            ARRAY_A
        );

        if (empty($users_with_streaks)) return;

        foreach ($users_with_streaks as $row) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$new_table} (user_id, current_streak, max_streak, last_activity_date, updated_at)
                 VALUES (%d, %d, %d, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    current_streak = GREATEST(current_streak, VALUES(current_streak)),
                    max_streak = GREATEST(max_streak, VALUES(max_streak)),
                    last_activity_date = COALESCE(VALUES(last_activity_date), last_activity_date)",
                intval($row['user_id']),
                intval($row['current_streak']),
                intval($row['max_streak']),
                $row['last_activity_date'] ?: null,
                $now
            ));
        }
    }

    /**
     * Migrate birthday data from _gorilla_birthday (YYYY-MM-DD) to _wpgamify_birthday (MM-DD).
     */
    private static function migrate_birthdays(): void {
        global $wpdb;

        $users = $wpdb->get_results(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta}
             WHERE meta_key = '_gorilla_birthday' AND meta_value != ''",
            ARRAY_A
        );

        if (empty($users)) return;

        foreach ($users as $row) {
            $bday = $row['meta_value'];
            $parts = explode('-', $bday);
            if (count($parts) >= 3) {
                $mm_dd = $parts[1] . '-' . $parts[2];
                update_user_meta(intval($row['user_id']), '_wpgamify_birthday', $mm_dd);
            }
        }
    }

    /**
     * Sync user levels in WP Gamify based on migrated XP transactions.
     */
    private static function sync_user_levels(): void {
        if (!class_exists('WPGamify_XP_Engine')) return;

        global $wpdb;
        $txn_table = $wpdb->prefix . 'gamify_xp_transactions';
        $level_table = $wpdb->prefix . 'gamify_user_levels';
        $now = current_time('mysql');

        // Get all users with XP transactions.
        $users = $wpdb->get_col(
            "SELECT DISTINCT user_id FROM {$txn_table}"
        );

        if (empty($users)) return;

        foreach ($users as $user_id) {
            $total_xp = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM {$txn_table} WHERE user_id = %d",
                $user_id
            ));

            // Upsert into user_levels table.
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$level_table} (user_id, current_level, total_xp, rolling_xp, updated_at)
                 VALUES (%d, 1, %d, %d, %s)
                 ON DUPLICATE KEY UPDATE total_xp = VALUES(total_xp), rolling_xp = VALUES(rolling_xp), updated_at = VALUES(updated_at)",
                $user_id, $total_xp, $total_xp, $now
            ));

            // Use WP Gamify's own sync to calculate correct level.
            if (method_exists('WPGamify_XP_Engine', 'sync_user_level')) {
                WPGamify_XP_Engine::sync_user_level($user_id);
            }
        }
    }
}
