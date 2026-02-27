<?php
/**
 * WP Gamify - REST API Kullanici Istatistikleri Endpoint'i
 *
 * Giris yapmis kullanicinin XP, level, streak ve fayda
 * bilgilerini JSON olarak dondurur.
 *
 * @package    WPGamify
 * @subpackage API\Endpoints
 * @since      1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Kullanici istatistikleri endpoint sinifi.
 *
 * GET /gamify/v1/user/stats
 *
 * Yanit icerigi:
 * - total_xp: Toplam XP
 * - current_level: Mevcut level numarasi
 * - level_name: Mevcut level adi
 * - progress_pct: Sonraki levele ilerleme yuzdesi
 * - xp_to_next: Sonraki level icin gereken XP
 * - streak: Mevcut giris serisi
 * - max_streak: Rekor giris serisi
 * - benefits: Aktif faydalar
 * - campaign: Aktif kampanya (varsa)
 *
 * @since 1.0.0
 */
class WPGamify_Endpoint_Stats {

    /**
     * Istatistik istegini isler.
     *
     * Mevcut kullanicinin tum gamification verilerini toplar
     * ve birlestirmis bir JSON yaniti dondurur.
     *
     * @param \WP_REST_Request $request REST istegi.
     * @return \WP_REST_Response Kullanici istatistikleri.
     */
    public function handle( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id = get_current_user_id();

        $level_data = WPGamify_Level_Manager::get_user_level_data( $user_id );
        $progress   = WPGamify_Level_Manager::get_progress( $user_id );
        $streak     = WPGamify_Streak_Manager::get_streak( $user_id );
        $benefits   = WPGamify_Level_Manager::get_user_benefits( $user_id );
        $campaign   = WPGamify_Campaign_Manager::get_active_campaign();

        $current_level = $progress['current_level'] ?? null;

        return new \WP_REST_Response( [
            'total_xp'      => (int) $level_data['total_xp'],
            'current_level'  => (int) $level_data['current_level'],
            'level_name'     => $current_level['name'] ?? '',
            'level_color'    => $current_level['color_hex'] ?? '#6366f1',
            'progress_pct'   => round( (float) ( $progress['progress_pct'] ?? 0 ), 1 ),
            'xp_to_next'     => (int) ( $progress['xp_needed'] ?? 0 ),
            'current_xp'     => (int) ( $progress['current_xp'] ?? 0 ),
            'streak'         => (int) ( $streak['current_streak'] ?? 0 ),
            'max_streak'     => (int) ( $streak['max_streak'] ?? 0 ),
            'streak_xp_today' => (int) ( $streak['streak_xp_today'] ?? 0 ),
            'benefits'       => $benefits,
            'campaign'       => $campaign,
        ], 200 );
    }
}
