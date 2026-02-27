<?php
/**
 * WP Gamify - Frontend Dashboard
 *
 * WooCommerce Hesabim sayfasina "Seviye & XP" sekmesi ekler,
 * musteri dashboard verilerini toplar ve goruntuleme sablonunu yukler.
 *
 * @package    WPGamify
 * @subpackage Frontend
 * @since      1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Musteri hesap sayfasi gamification paneli.
 *
 * My Account endpoint kaydeder, menu ogesi ekler, CSS/JS yukler
 * ve dashboard sablonunu render eder.
 *
 * @since 1.0.0
 */
class WPGamify_Frontend {

    /**
     * Endpoint slug (My Account URL parcasi).
     *
     * @var string
     */
    private const ENDPOINT = 'gamify';

    /**
     * Menu ogesi etiketi.
     *
     * @var string
     */
    private const MENU_LABEL = 'Seviye & XP';

    /**
     * Constructor.
     *
     * Hook'lari otomatik olarak kaydeder.
     */
    public function __construct() {
        $this->register();
    }

    /**
     * WordPress ve WooCommerce hook'larini kaydeder.
     *
     * - My Account menu ogesi ekleme
     * - Rewrite endpoint kaydedilmesi
     * - Endpoint icerigi render etme
     * - CSS/JS asset'lerin yuklenmesi
     * - Endpoint flush kontrolu
     *
     * @return void
     */
    public function register(): void {
        add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_item' ] );
        add_action( 'init', [ $this, 'add_endpoint' ] );
        add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', [ $this, 'render_dashboard' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'init', [ $this, 'maybe_flush_rewrite_rules' ], 99 );
    }

    /**
     * WooCommerce Hesabim menusune "Seviye & XP" ogesi ekler.
     *
     * "dashboard" ogesinden hemen sonra yerlestirilir.
     * Eger "dashboard" bulunmazsa, listenin basina eklenir.
     *
     * @param array<string, string> $items Mevcut menu ogeleri.
     * @return array<string, string> Guncellenmis menu ogeleri.
     */
    public function add_menu_item( array $items ): array {
        $new_items = [];
        $inserted  = false;

        foreach ( $items as $key => $label ) {
            $new_items[ $key ] = $label;

            if ( $key === 'dashboard' && ! $inserted ) {
                $new_items[ self::ENDPOINT ] = self::MENU_LABEL;
                $inserted = true;
            }
        }

        // Dashboard bulunamadiysa basa ekle.
        if ( ! $inserted ) {
            $new_items = [ self::ENDPOINT => self::MENU_LABEL ] + $new_items;
        }

        return $new_items;
    }

    /**
     * WooCommerce My Account icin rewrite endpoint kaydeder.
     *
     * @return void
     */
    public function add_endpoint(): void {
        add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
    }

    /**
     * Aktivasyon sonrasi rewrite kurallarini flush eder.
     *
     * Sadece `wpgamify_flush_rewrite` transient'i varsa calisir.
     * Transient aktivasyon sirasinda ayarlanir.
     *
     * @return void
     */
    public function maybe_flush_rewrite_rules(): void {
        if ( get_transient( 'wpgamify_flush_rewrite' ) === 'yes' ) {
            flush_rewrite_rules( false );
            delete_transient( 'wpgamify_flush_rewrite' );
        }
    }

    /**
     * Musteri dashboard sablonunu render eder.
     *
     * Tum gerekli verileri toplar ve views/my-account-dashboard.php'ye gonderir.
     * Giris yapmamis kullanicilar icin hicbir sey gostermez.
     *
     * @return void
     */
    public function render_dashboard(): void {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return;
        }

        $level_data = WPGamify_Level_Manager::get_user_level_data( $user_id );
        $progress   = WPGamify_Level_Manager::get_progress( $user_id );
        $all_levels = WPGamify_Level_Manager::get_all_levels();

        // Bu ayin XP toplamini hesapla.
        $monthly_xp = $this->get_monthly_xp( $user_id );

        // Get history result (returns associative array with 'items', 'pages', etc.).
        $history_result = WPGamify_XP_Engine::get_history( $user_id, 1, 10 );

        $data = [
            'user_id'          => $user_id,
            'level_data'       => $level_data,
            'progress'         => $progress,
            'benefits'         => WPGamify_Level_Manager::get_user_benefits( $user_id ),
            'level_config'     => WPGamify_Level_Manager::get_level( $level_data['current_level'] ),
            'streak'           => WPGamify_Streak_Manager::get_streak( $user_id ),
            'total_xp'         => (int) $level_data['total_xp'],
            'monthly_xp'       => $monthly_xp,
            'history'          => $history_result['items'] ?? [],
            'history_has_more' => ( $history_result['pages'] ?? 1 ) > 1,
            'campaign'         => WPGamify_Campaign_Manager::get_active_campaign(),
            'all_levels'       => $all_levels,
            'currency_label'   => WPGamify_Settings::get( 'currency_label', 'XP' ),
        ];

        include WPGAMIFY_PATH . 'frontend/views/my-account-dashboard.php';
    }

    /**
     * Gamify dashboard sayfasinda CSS ve JS asset'lerini yukler.
     *
     * Sadece My Account sayfasindaki gamify endpoint'inde calisir.
     * REST API nonce'u ve ayarlar JS'e localize edilir.
     *
     * @return void
     */
    public function enqueue_assets(): void {
        if ( ! is_account_page() ) {
            return;
        }

        global $wp;

        if ( ! isset( $wp->query_vars[ self::ENDPOINT ] ) ) {
            return;
        }

        wp_enqueue_style(
            'wpgamify-dashboard',
            WPGAMIFY_URL . 'frontend/assets/dashboard.css',
            [],
            WPGAMIFY_VERSION
        );

        wp_enqueue_script(
            'wpgamify-dashboard',
            WPGAMIFY_URL . 'frontend/assets/dashboard.js',
            [],
            WPGAMIFY_VERSION,
            true
        );

        wp_localize_script( 'wpgamify-dashboard', 'wpgamify', [
            'rest_url'       => esc_url_raw( rest_url( 'gamify/v1/' ) ),
            'nonce'          => wp_create_nonce( 'wp_rest' ),
            'currency_label' => WPGamify_Settings::get( 'currency_label', 'XP' ),
        ] );
    }

    /**
     * Bu ayin toplam XP miktarini hesaplar.
     *
     * @param int $user_id Kullanici ID.
     * @return int Bu aydaki toplam XP.
     */
    private function get_monthly_xp( int $user_id ): int {
        global $wpdb;

        $table      = $wpdb->prefix . 'gamify_xp_transactions';
        $month_start = wp_date( 'Y-m-01 00:00:00' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0)
                 FROM {$table}
                 WHERE user_id = %d
                 AND created_at >= %s",
                $user_id,
                $month_start
            )
        );

        return (int) $result;
    }
}
