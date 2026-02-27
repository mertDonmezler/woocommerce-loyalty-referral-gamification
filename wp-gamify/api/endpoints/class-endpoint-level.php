<?php
/**
 * WP Gamify - REST API Level Bilgisi Endpoint'i
 *
 * Giris yapmis kullanicinin level ilerlemesini ve
 * tum levellerin yol haritasini JSON formatinda dondurur.
 *
 * @package    WPGamify
 * @subpackage API\Endpoints
 * @since      1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Level bilgisi endpoint sinifi.
 *
 * GET /gamify/v1/user/level
 *
 * Yanit icerigi:
 * - current_level: Mevcut level konfigurasyonu
 * - next_level: Sonraki level konfigurasyonu (varsa)
 * - progress_pct: Ilerleme yuzdesi
 * - xp_needed: Sonraki level icin gereken XP
 * - roadmap: Tum levellerin yol haritasi dizisi
 *
 * @since 1.0.0
 */
class WPGamify_Endpoint_Level {

    /**
     * Level bilgisi istegini isler.
     *
     * Mevcut kullanicinin level ilerlemesini ve tum level
     * konfigurasyonlarini icerir. Her level icin:
     * is_current ve is_reached durumlari belirlenir.
     *
     * @param \WP_REST_Request $request REST istegi.
     * @return \WP_REST_Response Level ilerleme ve yol haritasi verileri.
     */
    public function handle( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id    = get_current_user_id();
        $progress   = WPGamify_Level_Manager::get_progress( $user_id );
        $all_levels = WPGamify_Level_Manager::get_all_levels();

        $current_level_config = $progress['current_level'] ?? null;
        $current_number       = (int) ( $current_level_config['level_number'] ?? 1 );

        $roadmap = array_map( static function ( array $level ) use ( $current_number ): array {
            $level_num = (int) $level['level_number'];

            return [
                'level_number' => $level_num,
                'name'         => $level['name'],
                'xp_required'  => (int) $level['xp_required'],
                'color_hex'    => $level['color_hex'] ?? '#6366f1',
                'is_current'   => $level_num === $current_number,
                'is_reached'   => $level_num <= $current_number,
            ];
        }, $all_levels );

        return new \WP_REST_Response( [
            'current_level' => $current_level_config,
            'next_level'    => $progress['next_level'] ?? null,
            'progress_pct'  => round( (float) ( $progress['progress_pct'] ?? 0 ), 1 ),
            'xp_needed'     => (int) ( $progress['xp_needed'] ?? 0 ),
            'current_xp'    => (int) ( $progress['current_xp'] ?? 0 ),
            'roadmap'       => $roadmap,
        ], 200 );
    }
}
