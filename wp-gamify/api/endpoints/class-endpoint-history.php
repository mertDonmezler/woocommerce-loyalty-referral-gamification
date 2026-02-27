<?php
/**
 * WP Gamify - REST API XP Gecmisi Endpoint'i
 *
 * Giris yapmis kullanicinin XP islem gecmisini
 * sayfalanmis olarak JSON formatinda dondurur.
 *
 * @package    WPGamify
 * @subpackage API\Endpoints
 * @since      1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * XP gecmisi endpoint sinifi.
 *
 * GET /gamify/v1/user/xp-history
 *
 * Parametreler:
 * - page: Sayfa numarasi (varsayilan: 1, min: 1)
 * - per_page: Sayfa basina kayit (varsayilan: 20, min: 1, max: 100)
 *
 * Yanit icerigi:
 * - items: XP islem dizisi (id, amount, source, source_label, note, date)
 * - has_more: Daha fazla kayit var mi
 * - page: Mevcut sayfa numarasi
 *
 * @since 1.0.0
 */
class WPGamify_Endpoint_History {

    /**
     * XP gecmisi istegini isler.
     *
     * per_page + 1 kayit cekerek has_more kontrolu yapar.
     * Tarihleri wp_date ile formatlar, kaynak etiketlerini cevirir.
     *
     * @param \WP_REST_Request $request REST istegi.
     * @return \WP_REST_Response Sayfalanmis XP gecmisi.
     */
    public function handle( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id  = get_current_user_id();
        $page     = (int) ( $request->get_param( 'page' ) ?? 1 );
        $per_page = (int) ( $request->get_param( 'per_page' ) ?? 20 );

        // per_page + 1 cekerek sonraki sayfada veri olup olmadigini kontrol et.
        $history  = WPGamify_XP_Engine::get_history( $user_id, $page, $per_page + 1 );
        $has_more = count( $history ) > $per_page;

        if ( $has_more ) {
            array_pop( $history );
        }

        $items = array_map( static function ( array $row ): array {
            return [
                'id'           => (int) ( $row['id'] ?? 0 ),
                'amount'       => (int) ( $row['amount'] ?? 0 ),
                'source'       => $row['source'] ?? '',
                'source_label' => WPGamify_XP_Engine::get_source_label( $row['source'] ?? '' ),
                'note'         => $row['note'] ?? '',
                'date'         => ! empty( $row['created_at'] )
                    ? wp_date( 'j M Y, H:i', strtotime( $row['created_at'] ) )
                    : '',
            ];
        }, $history );

        return new \WP_REST_Response( [
            'items'    => $items,
            'has_more' => $has_more,
            'page'     => $page,
        ], 200 );
    }
}
