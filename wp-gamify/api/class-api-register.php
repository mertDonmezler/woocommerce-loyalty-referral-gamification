<?php
/**
 * WP Gamify - REST API Kayit Yoneticisi
 *
 * Tum gamification REST API endpoint'lerini kaydeder.
 * Her endpoint ayri bir sinif tarafindan yonetilir.
 *
 * @package    WPGamify
 * @subpackage API
 * @since      1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST API rota kayit sinifi.
 *
 * `gamify/v1` namespace'i altinda kullanici istatistikleri,
 * XP gecmisi ve level bilgisi endpoint'lerini kaydeder.
 * Tum endpoint'ler giris yapilmis kullanici gerektiren
 * permission_callback'e sahiptir.
 *
 * @since 1.0.0
 */
class WPGamify_API_Register {

    /**
     * REST API namespace.
     *
     * @var string
     */
    private const API_NAMESPACE = 'gamify/v1';

    /**
     * WordPress hook'larini kaydeder.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Tum REST API rotalarini kaydeder.
     *
     * Endpoint siniflarini yukler ve register_rest_route ile kaydeder.
     * Her endpoint icin: HTTP metodu, callback, izin kontrolu ve parametre dogrulamasi.
     *
     * @return void
     */
    public function register_routes(): void {
        $this->load_endpoint_classes();

        // Kullanici istatistikleri.
        register_rest_route( self::API_NAMESPACE, '/user/stats', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ new WPGamify_Endpoint_Stats(), 'handle' ],
            'permission_callback' => [ $this, 'check_logged_in' ],
        ] );

        // XP gecmisi (sayfalanmis).
        register_rest_route( self::API_NAMESPACE, '/user/xp-history', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ new WPGamify_Endpoint_History(), 'handle' ],
            'permission_callback' => [ $this, 'check_logged_in' ],
            'args'                => [
                'page'     => [
                    'type'              => 'integer',
                    'default'           => 1,
                    'minimum'           => 1,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'rest_validate_request_arg',
                ],
                'per_page' => [
                    'type'              => 'integer',
                    'default'           => 20,
                    'minimum'           => 1,
                    'maximum'           => 100,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'rest_validate_request_arg',
                ],
            ],
        ] );

        // Level bilgisi ve yol haritasi.
        register_rest_route( self::API_NAMESPACE, '/user/level', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ new WPGamify_Endpoint_Level(), 'handle' ],
            'permission_callback' => [ $this, 'check_logged_in' ],
        ] );
    }

    /**
     * Endpoint sinif dosyalarini yukler.
     *
     * Autoloader api/endpoints/ alt dizinini tamamayamadigindan
     * dosyalari burada elle dahil eder.
     *
     * @return void
     */
    private function load_endpoint_classes(): void {
        $dir = WPGAMIFY_PATH . 'api/endpoints/';

        $files = [
            'class-endpoint-stats.php',
            'class-endpoint-history.php',
            'class-endpoint-level.php',
        ];

        foreach ( $files as $file ) {
            $path = $dir . $file;
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }
    }

    /**
     * Kullanicinin giris yapip yapmadigini kontrol eder.
     *
     * Giris yapmamis kullanicilar 401 Unauthorized yaniti alir.
     *
     * @return bool Giris yapmis ise true.
     */
    public function check_logged_in(): bool {
        return is_user_logged_in();
    }
}
