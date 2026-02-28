<?php
/**
 * WP Gamify - Siparis Hook Yoneticisi
 *
 * WooCommerce siparis olaylarina baglanarak XP oduller ve
 * iade/iptal islemlerinde XP geri alimlari yonetir.
 *
 * @package WP_Gamify
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Siparis olaylari icin hook sinifi.
 *
 * Tamamlanan siparislerde XP odul, iade ve iptalde XP geri alim islemlerini yonetir.
 * Her islem idempotency korumasina sahiptir — ayni siparis icin XP tekrar verilmez.
 *
 * @since 1.0.0
 */
class WPGamify_Order_Hooks {

    /**
     * Siparis meta anahtari: verilen XP miktari.
     *
     * @var string
     */
    private const META_XP_AWARDED = '_wpgamify_xp_awarded';

    /**
     * Siparis meta anahtari: iade edilen XP miktari.
     *
     * @var string
     */
    private const META_XP_REFUNDED = '_wpgamify_xp_refunded';

    /**
     * Kullanici meta anahtari: ilk siparis bonusu verildi.
     *
     * @var string
     */
    private const META_FIRST_ORDER = '_wpgamify_first_order_awarded';

    /**
     * Constructor.
     *
     * Hook'lari otomatik olarak kaydeder.
     */
    public function __construct() {
        $this->register();
    }

    /**
     * WooCommerce siparis hook'larini kaydeder.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'woocommerce_order_status_completed', [ $this, 'on_order_completed' ], 20 );
        add_action( 'woocommerce_order_status_processing', [ $this, 'on_order_completed' ], 20 );
        add_action( 'woocommerce_order_status_refunded', [ $this, 'on_order_refunded' ], 20 );
        add_action( 'woocommerce_order_status_cancelled', [ $this, 'on_order_cancelled' ], 20 );
    }

    /**
     * Siparis tamamlandiginda veya isleme alindiginda XP odul verir.
     *
     * Hesaplama: (sabit baz XP) + (harcama * birim XP) + (ilk siparis bonusu)
     * Idempotency: siparis meta `_wpgamify_xp_awarded` kontrolu.
     *
     * @param int $order_id Siparis ID.
     * @return void
     */
    public function on_order_completed( int $order_id ): void {
        $order = wc_get_order( $order_id );

        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $user_id = (int) $order->get_customer_id();

        if ( $user_id <= 0 ) {
            return;
        }

        // Idempotency korumasi: bu siparis icin daha once XP verildiyse atla.
        $already_awarded = $order->get_meta( self::META_XP_AWARDED );

        if ( $already_awarded !== '' && $already_awarded !== false ) {
            return;
        }

        // XP hesaplama
        $xp_base         = (int) WPGamify_Settings::get( 'xp_order_base', 10 );
        $xp_per_currency = (float) WPGamify_Settings::get( 'xp_order_per_currency', 1 );
        $order_total     = (float) $order->get_total();
        $spending_xp     = (int) floor( $order_total * $xp_per_currency );
        $total_xp        = $xp_base + $spending_xp;

        /**
         * Siparis XP miktarini filtreler.
         *
         * @param int $total_xp  Hesaplanan toplam XP.
         * @param int $order_id  Siparis ID.
         * @param int $user_id   Kullanici ID.
         */
        $total_xp = (int) apply_filters( 'gamify_order_xp', $total_xp, $order_id, $user_id );

        if ( $total_xp <= 0 ) {
            return;
        }

        // XP odul
        WPGamify_XP_Engine::award(
            $user_id,
            $total_xp,
            'order',
            (string) $order_id,
            'Siparis #' . $order_id
        );

        // Idempotency isareti
        $order->update_meta_data( self::META_XP_AWARDED, $total_xp );
        $order->save();

        // Ilk siparis bonusu
        $this->maybe_award_first_order_bonus( $user_id, $order_id );
    }

    /**
     * Siparis iade edildiginde verilen XP'yi geri alir.
     *
     * Sadece daha once XP verilmis siparisler icin geri alim yapilir.
     * Idempotency: siparis meta `_wpgamify_xp_refunded` kontrolu.
     *
     * @param int $order_id Siparis ID.
     * @return void
     */
    public function on_order_refunded( int $order_id ): void {
        $this->deduct_order_xp( $order_id, 'refund' );
    }

    /**
     * Siparis iptal edildiginde verilen XP'yi geri alir.
     *
     * Iade ile ayni mantik: daha once verilmis XP kadar geri alim.
     *
     * @param int $order_id Siparis ID.
     * @return void
     */
    public function on_order_cancelled( int $order_id ): void {
        $this->deduct_order_xp( $order_id, 'cancellation' );
    }

    /**
     * Siparis XP geri alim islemini gerceklestirir.
     *
     * @param int    $order_id Siparis ID.
     * @param string $reason   Geri alim nedeni ('refund' veya 'cancellation').
     * @return void
     */
    private function deduct_order_xp( int $order_id, string $reason ): void {
        $order = wc_get_order( $order_id );

        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $user_id = (int) $order->get_customer_id();

        if ( $user_id <= 0 ) {
            return;
        }

        // Sadece daha once XP verilmis siparisler icin geri al.
        $awarded_xp = $order->get_meta( self::META_XP_AWARDED );

        if ( $awarded_xp === '' || $awarded_xp === false ) {
            return;
        }

        // Idempotency korumasi: zaten geri alinmissa atla.
        $already_refunded = $order->get_meta( self::META_XP_REFUNDED );

        if ( $already_refunded !== '' && $already_refunded !== false ) {
            return;
        }

        $deduct_amount = absint( $awarded_xp );

        if ( $deduct_amount <= 0 ) {
            return;
        }

        $note = match ( $reason ) {
            'refund'       => 'Iade - Siparis #' . $order_id,
            'cancellation' => 'Iptal - Siparis #' . $order_id,
            default        => 'Geri alim - Siparis #' . $order_id,
        };

        WPGamify_XP_Engine::deduct(
            $user_id,
            $deduct_amount,
            'order_' . $reason,
            (string) $order_id,
            $note
        );

        // Idempotency isareti
        $order->update_meta_data( self::META_XP_REFUNDED, $deduct_amount );
        $order->save();
    }

    /**
     * Ilk siparis bonusunu kontrol eder ve verir.
     *
     * Kullanicinin ilk siparisi ise ve bonus miktari ayarlanmissa,
     * ek XP odul verir. Tek seferlik — kullanici meta ile kontrol edilir.
     *
     * @param int $user_id  Kullanici ID.
     * @param int $order_id Siparis ID.
     * @return void
     */
    private function maybe_award_first_order_bonus( int $user_id, int $order_id ): void {
        $bonus_xp = (int) WPGamify_Settings::get( 'xp_first_order_bonus', 0 );

        if ( $bonus_xp <= 0 ) {
            return;
        }

        // Atomic guard — prevent double first-order bonus via INSERT ... WHERE NOT EXISTS.
        global $wpdb;
        $guard_inserted = $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value)
             SELECT %d, %s, %s FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s)",
            $user_id, self::META_FIRST_ORDER, wp_date( 'Y-m-d H:i:s' ),
            $user_id, self::META_FIRST_ORDER
        ) );
        if ( ! $guard_inserted ) {
            return;
        }
        wp_cache_delete( $user_id, 'user_meta' );

        WPGamify_XP_Engine::award(
            $user_id,
            $bonus_xp,
            'first_order',
            (string) $order_id,
            'Ilk siparis bonusu'
        );
    }
}
