<?php
/**
 * WP Gamify - Yorum/Degerlendirme Hook Yoneticisi
 *
 * Urun yorumlarinin (review) onaylanmasina baglanarak XP odul verir.
 * Anti-abuse kontrolleri ve minimum karakter siniri uygulanir.
 *
 * @package WP_Gamify
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Urun degerlendirme olaylari icin hook sinifi.
 *
 * Yorum gonderildiginde ve onaylandiginda urun degerlendirmelerini tespit eder,
 * anti-abuse ve minimum karakter kontrolu yapar, XP odul verir.
 *
 * @since 1.0.0
 */
class WPGamify_Review_Hooks {

    /**
     * Yorum meta anahtari: XP verildi isareti.
     *
     * @var string
     */
    private const META_XP_AWARDED = '_wpgamify_xp_awarded';

    /**
     * Constructor.
     *
     * Hook'lari otomatik olarak kaydeder.
     */
    public function __construct() {
        $this->register();
    }

    /**
     * Yorum hook'larini kaydeder.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'comment_post', [ $this, 'on_review_posted' ], 20, 3 );
        add_action( 'wp_set_comment_status', [ $this, 'on_review_approved' ], 20, 2 );
    }

    /**
     * Yeni yorum gonderildiginde cagrilir.
     *
     * Otomatik onaylanan urun degerlendirmeleri icin hemen XP odul verir.
     * Onay bekleyen yorumlar `on_review_approved` ile islenir.
     *
     * @param int        $comment_id       Yorum ID.
     * @param int|string $comment_approved Onay durumu (1, 0, 'spam').
     * @param array      $commentdata      Yorum verileri.
     * @return void
     */
    public function on_review_posted( int $comment_id, int|string $comment_approved, array $commentdata ): void {
        // Sadece otomatik onaylanan yorumlar burada islenir.
        if ( $comment_approved !== 1 && $comment_approved !== '1' ) {
            return;
        }

        $this->process_review( $comment_id );
    }

    /**
     * Yorum durumu degistiginde cagrilir.
     *
     * Sadece onay durumuna gecen urun degerlendirmeleri icin XP odul verir.
     *
     * @param int    $comment_id Yorum ID.
     * @param string $status     Yeni durum ('approve', 'hold', 'spam', 'trash').
     * @return void
     */
    public function on_review_approved( int $comment_id, string $status ): void {
        if ( $status !== 'approve' ) {
            return;
        }

        $this->process_review( $comment_id );
    }

    /**
     * Urun degerlendirmesi icin XP odul islemini gerceklestirir.
     *
     * Kontroller:
     * 1. Yorumun gecerli bir urun degerlendirmesi olup olmadigi
     * 2. XP ozelliginin aktif olup olmadigi
     * 3. Kullanicinin giris yapmis olup olmadigi
     * 4. Idempotency — bu yorum icin daha once XP verilip verilmedigi
     * 5. Anti-abuse — ayni urune daha once yorum yapilip yapilmadigi
     * 6. Minimum karakter siniri
     *
     * @param int $comment_id Yorum ID.
     * @return void
     */
    private function process_review( int $comment_id ): void {
        $comment = get_comment( $comment_id );

        if ( ! $comment instanceof WP_Comment ) {
            return;
        }

        // Urun degerlendirmesi mi kontrol et.
        if ( ! $this->is_product_review( $comment ) ) {
            return;
        }

        // XP ozelligi aktif mi?
        $enabled = WPGamify_Settings::get( 'xp_review_enabled', false );

        if ( ! $enabled ) {
            return;
        }

        // Giris yapmis kullanici gerektir.
        $user_id = (int) $comment->user_id;

        if ( $user_id <= 0 ) {
            return;
        }

        // Idempotency korumasi: bu yorum icin zaten XP verildiyse atla.
        $already_awarded = get_comment_meta( $comment_id, self::META_XP_AWARDED, true );

        if ( $already_awarded !== '' && $already_awarded !== false ) {
            return;
        }

        $product_id = (int) $comment->comment_post_ID;

        // Anti-abuse: ayni urune daha once yorum yapmis mi?
        if ( WPGamify_Anti_Abuse::has_reviewed_product( $user_id, $product_id ) ) {
            return;
        }

        // Minimum karakter siniri kontrolu.
        $min_chars      = (int) WPGamify_Settings::get( 'xp_review_min_chars', 0 );
        $comment_length = mb_strlen( wp_strip_all_tags( $comment->comment_content ) );

        if ( $min_chars > 0 && $comment_length < $min_chars ) {
            return;
        }

        // XP odul miktari
        $amount = (int) WPGamify_Settings::get( 'xp_review_amount', 20 );

        if ( $amount <= 0 ) {
            return;
        }

        /**
         * Degerlendirme XP miktarini filtreler.
         *
         * @param int $amount     Hesaplanan XP miktari.
         * @param int $comment_id Yorum ID.
         * @param int $user_id    Kullanici ID.
         * @param int $product_id Urun ID.
         */
        $amount = (int) apply_filters( 'gamify_review_xp', $amount, $comment_id, $user_id, $product_id );

        if ( $amount <= 0 ) {
            return;
        }

        WPGamify_XP_Engine::award(
            $user_id,
            $amount,
            'review',
            (string) $comment_id,
            'Urun degerlendirmesi — #' . $product_id
        );

        // Idempotency isareti
        update_comment_meta( $comment_id, self::META_XP_AWARDED, 1 );
    }

    /**
     * Yorumun bir WooCommerce urun degerlendirmesi olup olmadigini kontrol eder.
     *
     * WordPress'te urun degerlendirmeleri `comment_type = 'review'` veya
     * `product` post type'ina ait normal yorumlar olabilir.
     *
     * @param WP_Comment $comment Yorum nesnesi.
     * @return bool Urun degerlendirmesi ise true.
     */
    private function is_product_review( WP_Comment $comment ): bool {
        // WooCommerce 3.x+ comment_type 'review' kullanir.
        if ( $comment->comment_type === 'review' ) {
            return true;
        }

        // Eski WooCommerce veya standart yorum: post type kontrolu.
        $post_type = get_post_type( (int) $comment->comment_post_ID );

        return $post_type === 'product';
    }
}
