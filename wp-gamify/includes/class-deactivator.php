<?php
/**
 * Plugin Deactivator
 *
 * Eklenti devre disi birakildiginda zamanlanmis gorevleri temizler
 * ve yeniden yazma kurallarini sifirlar.
 *
 * @package WPGamify
 */

defined( 'ABSPATH' ) || exit;

class WPGamify_Deactivator {

    /**
     * Eklenti devre disi birakma islemleri.
     *
     * @return void
     */
    public static function deactivate(): void {
        self::clear_crons();
        flush_rewrite_rules();
    }

    /**
     * Tum zamanlanmis gorevleri temizler.
     *
     * @return void
     */
    private static function clear_crons(): void {
        $hooks = [
            'wpgamify_hourly_cache',
            'wpgamify_daily_maintenance',
        ];

        foreach ( $hooks as $hook ) {
            wp_unschedule_hook( $hook );
        }
    }
}
