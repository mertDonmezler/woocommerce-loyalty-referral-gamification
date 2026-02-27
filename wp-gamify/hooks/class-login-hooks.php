<?php
/**
 * WP Gamify - Giris ve Kayit Hook Yoneticisi
 *
 * Kullanici giris ve kayit olaylarina baglanarak streak guncelleme,
 * gunluk giris XP odul ve dogum gunu/yildonumu kontrollerini yonetir.
 *
 * @package WP_Gamify
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Giris ve kayit olaylari icin hook sinifi.
 *
 * Her giriste streak kaydeder, gunluk XP odul verir (transient ile idempotent),
 * dogum gunu ve uyeik yildonumu kontrolu yapar.
 * Kayit sirasinda hosgeldin XP odul verir ve baslangic satirlarini olusturur.
 *
 * @since 1.0.0
 */
class WPGamify_Login_Hooks {

    /**
     * Gunluk giris XP transient oneki.
     *
     * @var string
     */
    private const TRANSIENT_LOGIN_PREFIX = 'wpgamify_login_xp_';

    /**
     * Constructor.
     *
     * Hook'lari otomatik olarak kaydeder.
     */
    public function __construct() {
        $this->register();
    }

    /**
     * WordPress giris ve kayit hook'larini kaydeder.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'wp_login', [ $this, 'on_login' ], 10, 2 );
        add_action( 'user_register', [ $this, 'on_registration' ] );
    }

    /**
     * Kullanici giris yaptiginda cagrilir.
     *
     * Sirasiyla:
     * 1. Streak kaydeder
     * 2. Gunluk giris XP odul verir (gun basina bir kez)
     * 3. Dogum gunu kontrolu
     * 4. Uyelik yildonumu kontrolu
     *
     * @param string  $user_login Kullanici adi.
     * @param WP_User $user       Kullanici nesnesi.
     * @return void
     */
    public function on_login( string $user_login, WP_User $user ): void {
        $user_id = (int) $user->ID;

        if ( $user_id <= 0 ) {
            return;
        }

        // 1. Streak kaydini guncelle
        WPGamify_Streak_Manager::record_activity( $user_id );

        // 2. Gunluk giris XP odul
        $this->maybe_award_login_xp( $user_id );

        // 3. Dogum gunu kontrolu
        WPGamify_Streak_Manager::check_birthday( $user_id );

        // 4. Uyelik yildonumu kontrolu
        WPGamify_Streak_Manager::check_anniversary( $user_id );
    }

    /**
     * Yeni kullanici kayit olduguknda cagrilir.
     *
     * Hosgeldin XP odul verir ve gamification baslangic satirlarini olusturur.
     *
     * @param int $user_id Yeni kullanici ID.
     * @return void
     */
    public function on_registration( int $user_id ): void {
        if ( $user_id <= 0 ) {
            return;
        }

        // Kayit XP odul
        $this->maybe_award_registration_xp( $user_id );

        // Baslangic satirlarini olustur
        $this->initialize_user_records( $user_id );
    }

    /**
     * Gunluk giris XP odul verir.
     *
     * Idempotency: gun bazli transient ile ayni gun icinde tek odul.
     * Transient anahtari: `wpgamify_login_xp_{user_id}_{Y-m-d}`
     *
     * @param int $user_id Kullanici ID.
     * @return void
     */
    private function maybe_award_login_xp( int $user_id ): void {
        $enabled = WPGamify_Settings::get( 'xp_login_enabled', false );

        if ( ! $enabled ) {
            return;
        }

        $amount = (int) WPGamify_Settings::get( 'xp_login_amount', 5 );

        if ( $amount <= 0 ) {
            return;
        }

        // Timezone duyarli tarih
        $today         = wp_date( 'Y-m-d' );
        $transient_key = self::TRANSIENT_LOGIN_PREFIX . $user_id . '_' . $today;

        // Bu gun zaten XP verilmisse atla.
        if ( get_transient( $transient_key ) !== false ) {
            return;
        }

        WPGamify_XP_Engine::award(
            $user_id,
            $amount,
            'login',
            $today,
            'Gunluk giris odulu'
        );

        // 1 gunluk transient — gece yarisi otomatik temizlenir.
        set_transient( $transient_key, 1, DAY_IN_SECONDS );
    }

    /**
     * Kayit XP odul verir.
     *
     * Ayarlardan `xp_registration_enabled` ve `xp_registration_amount` kontrol edilir.
     *
     * @param int $user_id Kullanici ID.
     * @return void
     */
    private function maybe_award_registration_xp( int $user_id ): void {
        $enabled = WPGamify_Settings::get( 'xp_registration_enabled', false );

        if ( ! $enabled ) {
            return;
        }

        $amount = (int) WPGamify_Settings::get( 'xp_registration_amount', 50 );

        if ( $amount <= 0 ) {
            return;
        }

        WPGamify_XP_Engine::award(
            $user_id,
            $amount,
            'registration',
            (string) $user_id,
            'Hosgeldin bonusu — yeni uyelik'
        );
    }

    /**
     * Yeni kullanici icin user_levels ve streaks baslangic satirlarini olusturur.
     *
     * Mevcut kayit varsa INSERT IGNORE ile atlanir.
     *
     * @param int $user_id Kullanici ID.
     * @return void
     */
    private function initialize_user_records( int $user_id ): void {
        global $wpdb;

        $now = wp_date( 'Y-m-d H:i:s' );

        // Kullanici level satiri
        $levels_table = $wpdb->prefix . 'gamify_user_levels';
        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$levels_table}
                    (user_id, current_level, total_xp, rolling_xp, grace_until, last_xp_at, updated_at)
                VALUES (%d, 1, 0, 0, NULL, NULL, %s)",
                $user_id,
                $now
            )
        );

        // Streak satiri
        $streaks_table = $wpdb->prefix . 'gamify_streaks';
        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$streaks_table}
                    (user_id, current_streak, max_streak, last_activity_date, streak_xp_today, updated_at)
                VALUES (%d, 0, 0, NULL, 0, %s)",
                $user_id,
                $now
            )
        );
    }
}
