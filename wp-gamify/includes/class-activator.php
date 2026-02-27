<?php
/**
 * Plugin Activator
 *
 * Eklenti etkinlestirildiginde veritabani tablolarini olusturur,
 * varsayilan seviyeleri ekler ve zamanlanmis gorevleri ayarlar.
 *
 * @package WPGamify
 */

defined( 'ABSPATH' ) || exit;

class WPGamify_Activator {

    /**
     * Eklenti etkinlestirme islemleri.
     *
     * @return void
     */
    public static function activate(): void {
        self::create_tables();
        self::seed_levels();
        self::schedule_crons();

        update_option( 'wpgamify_db_version', WPGAMIFY_DB_VERSION );
        update_option( 'wpgamify_installed_at', current_time( 'mysql' ) );

        // Flush rewrite rules on next page load.
        set_transient( 'wpgamify_flush_rewrite', 'yes', 60 );
    }

    /**
     * Phase 1 veritabani tablolarini olusturur.
     *
     * @return void
     */
    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = [];

        // XP Transactions
        $table_xp = $wpdb->prefix . 'gamify_xp_transactions';
        $sql[]    = "CREATE TABLE {$table_xp} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            amount INT NOT NULL,
            source VARCHAR(50) NOT NULL,
            source_id VARCHAR(100) DEFAULT NULL,
            campaign_mult DECIMAL(4,2) DEFAULT 1.00,
            note VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_created (user_id, created_at),
            KEY source_created (source, created_at)
        ) {$charset_collate};";

        // User Levels
        $table_levels = $wpdb->prefix . 'gamify_user_levels';
        $sql[]        = "CREATE TABLE {$table_levels} (
            user_id BIGINT UNSIGNED NOT NULL,
            current_level INT NOT NULL DEFAULT 1,
            total_xp BIGINT DEFAULT 0,
            rolling_xp BIGINT DEFAULT 0,
            grace_until DATETIME DEFAULT NULL,
            last_xp_at DATETIME DEFAULT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (user_id)
        ) {$charset_collate};";

        // Streaks
        $table_streaks = $wpdb->prefix . 'gamify_streaks';
        $sql[]         = "CREATE TABLE {$table_streaks} (
            user_id BIGINT UNSIGNED NOT NULL,
            current_streak INT DEFAULT 0,
            max_streak INT DEFAULT 0,
            last_activity_date DATE DEFAULT NULL,
            streak_xp_today INT DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (user_id)
        ) {$charset_collate};";

        // Levels Config
        $table_config = $wpdb->prefix . 'gamify_levels_config';
        $sql[]        = "CREATE TABLE {$table_config} (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            level_number INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            xp_required BIGINT NOT NULL,
            benefits JSON NOT NULL,
            icon_attachment_id BIGINT UNSIGNED DEFAULT NULL,
            color_hex VARCHAR(7) DEFAULT '#6366f1',
            sort_order INT DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY level_number (level_number)
        ) {$charset_collate};";

        // Audit Log
        $table_audit = $wpdb->prefix . 'gamify_audit_log';
        $sql[]       = "CREATE TABLE {$table_audit} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_id BIGINT UNSIGNED NOT NULL,
            target_user_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(50) NOT NULL,
            amount INT DEFAULT NULL,
            before_value VARCHAR(255) DEFAULT NULL,
            after_value VARCHAR(255) DEFAULT NULL,
            reason TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY target_created (target_user_id, created_at),
            KEY admin_created (admin_id, created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ( $sql as $query ) {
            dbDelta( $query );
        }
    }

    /**
     * Varsayilan 8 seviyeyi ekler (sadece tablo bossa).
     *
     * @return void
     */
    private static function seed_levels(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'gamify_levels_config';

        // Tablo zaten dolu mu kontrol et.
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        if ( $count > 0 ) {
            return;
        }

        $now    = current_time( 'mysql' );
        $levels = [
            [
                'level_number' => 1,
                'name'         => 'Caylak',
                'xp_required'  => 0,
                'benefits'     => '{"discount":0}',
                'color_hex'    => '#94a3b8',
            ],
            [
                'level_number' => 2,
                'name'         => 'Kesifci',
                'xp_required'  => 100,
                'benefits'     => '{"discount":3}',
                'color_hex'    => '#22c55e',
            ],
            [
                'level_number' => 3,
                'name'         => 'Koleksiyoncu',
                'xp_required'  => 500,
                'benefits'     => '{"discount":5}',
                'color_hex'    => '#3b82f6',
            ],
            [
                'level_number' => 4,
                'name'         => 'Uzman',
                'xp_required'  => 1500,
                'benefits'     => '{"discount":7,"free_shipping":true}',
                'color_hex'    => '#8b5cf6',
            ],
            [
                'level_number' => 5,
                'name'         => 'Usta',
                'xp_required'  => 3500,
                'benefits'     => '{"discount":10,"free_shipping":true}',
                'color_hex'    => '#f59e0b',
            ],
            [
                'level_number' => 6,
                'name'         => 'Efsane',
                'xp_required'  => 7000,
                'benefits'     => '{"discount":12,"free_shipping":true,"early_access":true}',
                'color_hex'    => '#ef4444',
            ],
            [
                'level_number' => 7,
                'name'         => 'Sampiyon',
                'xp_required'  => 12000,
                'benefits'     => '{"discount":15,"free_shipping":true,"early_access":true,"installment":true}',
                'color_hex'    => '#ec4899',
            ],
            [
                'level_number' => 8,
                'name'         => 'Efsanevi',
                'xp_required'  => 20000,
                'benefits'     => '{"discount":20,"free_shipping":true,"early_access":true,"installment":true}',
                'color_hex'    => '#f97316',
            ],
        ];

        foreach ( $levels as $index => $level ) {
            $wpdb->insert(
                $table,
                [
                    'level_number' => $level['level_number'],
                    'name'         => $level['name'],
                    'xp_required'  => $level['xp_required'],
                    'benefits'     => $level['benefits'],
                    'color_hex'    => $level['color_hex'],
                    'sort_order'   => $index,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ],
                [ '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s' ]
            );
        }
    }

    /**
     * Zamanlanmis gorevleri ayarlar.
     *
     * @return void
     */
    private static function schedule_crons(): void {
        if ( ! wp_next_scheduled( 'wpgamify_hourly_cache' ) ) {
            wp_schedule_event( time(), 'wpgamify_hourly', 'wpgamify_hourly_cache' );
        }

        if ( ! wp_next_scheduled( 'wpgamify_daily_maintenance' ) ) {
            // Gece yarisi (site saat dilimine gore).
            $midnight = strtotime( 'tomorrow midnight', current_time( 'timestamp' ) );
            wp_schedule_event( $midnight, 'wpgamify_daily', 'wpgamify_daily_maintenance' );
        }
    }
}
