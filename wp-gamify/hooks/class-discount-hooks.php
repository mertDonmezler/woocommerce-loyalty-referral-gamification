<?php
/**
 * WP Gamify - Indirim ve Kargo Hook Yoneticisi
 *
 * Level bazli indirim ve ucretsiz kargo avantajlarini
 * WooCommerce sepet hesaplamarina uygular.
 *
 * @package WP_Gamify
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Level bazli indirim ve kargo avantajlari icin hook sinifi.
 *
 * Kullanicinin mevcut seviyesine gore yuzdelik indirim ve
 * ucretsiz kargo avantajlarini sepet hesaplamasina ekler.
 *
 * @since 1.0.0
 */
class WPGamify_Discount_Hooks {

    /**
     * Sepet ucretleri arasinda level indirimi icin kullanilan benzersiz ID.
     *
     * @var string
     */
    private const FEE_ID = 'wpgamify_level_discount';

    /**
     * Ucretsiz kargo orani icin kullanilan method ID.
     *
     * @var string
     */
    private const FREE_SHIPPING_ID = 'wpgamify_level_free_shipping';

    /**
     * Constructor.
     *
     * Hook'lari otomatik olarak kaydeder.
     */
    public function __construct() {
        $this->register();
    }

    /**
     * WooCommerce sepet ve kargo hook'larini kaydeder.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'woocommerce_cart_calculate_fees', [ $this, 'apply_level_discount' ], 30 );
        add_filter( 'woocommerce_package_rates', [ $this, 'apply_free_shipping' ], 30, 2 );
    }

    /**
     * Sepete level bazli yuzdelik indirim uygular.
     *
     * Kullanicinin seviye avantajlarindan 'discount' degeri kontrol edilir.
     * Indirim, sepet ara toplami uzerinden yuzde olarak hesaplanir
     * ve negatif ucret (fee) olarak eklenir.
     *
     * Idempotency: ayni indirim ucretinin tekrar eklenmesini onler.
     *
     * @param WC_Cart $cart Sepet nesnesi.
     * @return void
     */
    public function apply_level_discount( WC_Cart $cart ): void {
        // Admin panelde ve AJAX disinda atla.
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        // C9 FIX: Prevent double discount when Gorilla Loyalty tier discount
        // is already applied. Both plugins use woocommerce_cart_calculate_fees;
        // the global flag ensures only one percentage discount is applied.
        if ( ! empty( $GLOBALS['gorilla_discount_applied'] ) ) {
            return;
        }

        $user_id = get_current_user_id();

        if ( $user_id <= 0 ) {
            return;
        }

        $benefits = WPGamify_Level_Manager::get_user_benefits( $user_id );

        if ( empty( $benefits ) || ! is_array( $benefits ) ) {
            return;
        }

        $discount_percent = (float) ( $benefits['discount'] ?? 0 );

        if ( $discount_percent <= 0 ) {
            return;
        }

        // Idempotency: daha once eklenmis ayni indirimi kontrol et.
        $existing_fees = $cart->get_fees();

        foreach ( $existing_fees as $fee ) {
            if ( isset( $fee->id ) && $fee->id === self::FEE_ID ) {
                return;
            }
        }

        $subtotal        = (float) $cart->get_subtotal();
        $discount_amount = round( $subtotal * ( $discount_percent / 100 ), 2 );

        if ( $discount_amount <= 0 ) {
            return;
        }

        // Level bilgisi al (etiket icin)
        $level_data   = WPGamify_Level_Manager::get_user_level_data( $user_id );
        $level_config = WPGamify_Level_Manager::get_level( $level_data['current_level'] );
        $level_name   = $level_config['name'] ?? '';

        $label = $level_name !== ''
            ? $level_name . ' Indirimi (%' . $discount_percent . ')'
            : 'Level Indirimi (%' . $discount_percent . ')';

        /**
         * Level indirim miktarini filtreler.
         *
         * @param float $discount_amount  Hesaplanan indirim tutari.
         * @param float $discount_percent Indirim yuzdesi.
         * @param int   $user_id          Kullanici ID.
         * @param array $benefits         Kullanici seviye avantajlari.
         */
        $discount_amount = (float) apply_filters(
            'gamify_level_discount_amount',
            $discount_amount,
            $discount_percent,
            $user_id,
            $benefits
        );

        if ( $discount_amount <= 0 ) {
            return;
        }

        $cart->add_fee( $label, -$discount_amount, true );

        // C9 FIX: Set global flag so Gorilla Loyalty tier discount won't stack.
        $GLOBALS['gorilla_discount_applied'] = true;
    }

    /**
     * Level bazli ucretsiz kargo avantajini uygular.
     *
     * Kullanicinin seviye avantajlarindan 'free_shipping' degeri true ise,
     * mevcut kargo seceneklerine ucretsiz kargo orani ekler.
     *
     * @param array $rates   Mevcut kargo oranlari.
     * @param array $package Kargo paketi verileri.
     * @return array Guncellenmis kargo oranlari.
     */
    public function apply_free_shipping( array $rates, array $package ): array {
        $user_id = get_current_user_id();

        if ( $user_id <= 0 ) {
            return $rates;
        }

        $benefits = WPGamify_Level_Manager::get_user_benefits( $user_id );

        if ( empty( $benefits ) || ! is_array( $benefits ) ) {
            return $rates;
        }

        $has_free_shipping = (bool) ( $benefits['free_shipping'] ?? false );

        if ( ! $has_free_shipping ) {
            return $rates;
        }

        // Zaten ucretsiz kargo orani eklenmisse tekrar ekleme.
        if ( isset( $rates[ self::FREE_SHIPPING_ID ] ) ) {
            return $rates;
        }

        // Level bilgisi al
        $level_data   = WPGamify_Level_Manager::get_user_level_data( $user_id );
        $level_config = WPGamify_Level_Manager::get_level( $level_data['current_level'] );
        $level_name   = $level_config['name'] ?? 'Seviye';

        // Yeni ucretsiz kargo orani olustur.
        $free_rate = new WC_Shipping_Rate(
            self::FREE_SHIPPING_ID,
            $level_name . ' Avantaji — Ucretsiz Kargo',
            0,
            [],
            'free_shipping'
        );

        // Ucretsiz kargo oranini en basa ekle.
        $rates = [ self::FREE_SHIPPING_ID => $free_rate ] + $rates;

        /**
         * Level ucretsiz kargo uygulandiktan sonra kargo oranlarini filtreler.
         *
         * @param array $rates    Guncellenmis kargo oranlari.
         * @param int   $user_id  Kullanici ID.
         * @param array $benefits Kullanici seviye avantajlari.
         */
        $rates = apply_filters( 'gamify_level_shipping_rates', $rates, $user_id, $benefits );

        return $rates;
    }
}
