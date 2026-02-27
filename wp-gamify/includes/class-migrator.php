<?php
/**
 * Database Migrator
 *
 * Veritabani surum yonetimi. Eklenti guncellendikten sonra
 * gerekli sema degisikliklerini otomatik uygular.
 *
 * @package WPGamify
 */

defined( 'ABSPATH' ) || exit;

class WPGamify_Migrator {

    /** @var string Veritabani surum option anahtari */
    private const VERSION_KEY = 'wpgamify_db_version';

    /**
     * Mevcut veritabani surumunu kontrol eder ve gerekirse migrasyon calistirir.
     *
     * admin_init hook'unda cagirilir.
     *
     * @return void
     */
    public static function check(): void {
        $stored_version  = (int) get_option( self::VERSION_KEY, 0 );
        $current_version = WPGAMIFY_DB_VERSION;

        if ( $stored_version >= $current_version ) {
            return;
        }

        // Ilk kurulum: tablolar henuz yoksa activator'u calistir.
        if ( $stored_version === 0 ) {
            require_once WPGAMIFY_PATH . 'includes/class-activator.php';
            WPGamify_Activator::activate();
            return;
        }

        // Artimli migrasyon.
        self::migrate( $stored_version, $current_version );
    }

    /**
     * Belirtilen surum araligindaki migrasyonlari siraliyla calistirir.
     *
     * @param int $from Mevcut DB surumu.
     * @param int $to   Hedef DB surumu.
     * @return void
     */
    public static function migrate( int $from, int $to ): void {
        global $wpdb;

        // Her surum icin migrasyon metodu kontrol et.
        for ( $version = $from + 1; $version <= $to; $version++ ) {
            $method = "migrate_to_v{$version}";

            if ( method_exists( self::class, $method ) ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( sprintf(
                        '[WP Gamify Migrator] Migrasyon calisiyor: v%d -> v%d',
                        $version - 1,
                        $version
                    ) );
                }

                try {
                    self::$method();
                    update_option( self::VERSION_KEY, $version );
                } catch ( \Exception $e ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( sprintf(
                            '[WP Gamify Migrator] Migrasyon hatasi (v%d): %s',
                            $version,
                            $e->getMessage()
                        ) );
                    }
                    // Hatada dur, geri kalan migrasyonlari atlama.
                    return;
                }
            } else {
                // Metod yok ama surum atlandi, sadece surumu guncelle.
                update_option( self::VERSION_KEY, $version );
            }
        }

        // Migrasyondan sonra cron kontrolu.
        if ( ! wp_next_scheduled( 'wpgamify_hourly_cache' ) ) {
            wp_schedule_event( time(), 'wpgamify_hourly', 'wpgamify_hourly_cache' );
        }

        if ( ! wp_next_scheduled( 'wpgamify_daily_maintenance' ) ) {
            $midnight = strtotime( 'tomorrow midnight', current_time( 'timestamp' ) );
            wp_schedule_event( $midnight, 'wpgamify_daily', 'wpgamify_daily_maintenance' );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Gelecek migrasyonlar buraya eklenecek
    |--------------------------------------------------------------------------
    |
    | Her migrasyon private static metod olarak eklenir:
    |   private static function migrate_to_v2(): void { ... }
    |   private static function migrate_to_v3(): void { ... }
    |
    | Ornekler:
    |   - Yeni sutun ekleme: ALTER TABLE ... ADD COLUMN ...
    |   - Yeni tablo olusturma: dbDelta(...)
    |   - Veri donusumu: UPDATE ... SET ...
    |   - Index ekleme: ALTER TABLE ... ADD INDEX ...
    |
    */
}
