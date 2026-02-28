<?php
/**
 * Settings Manager
 *
 * Tek bir JSON blob olarak wp_options tablosunda saklanan
 * eklenti ayarlarini yonetir. Statik API ile erisim saglar.
 *
 * @package WPGamify
 */

defined( 'ABSPATH' ) || exit;

class WPGamify_Settings {

    /** @var string wp_options anahtari */
    private const OPTION_KEY = 'wpgamify_settings';

    /** @var array<string, mixed>|null Onbellek */
    private static ?array $cache = null;

    /**
     * Tum ayarlari varsayilanlarla birlestirerek dondurur.
     *
     * @return array<string, mixed>
     */
    public static function get_all(): array {
        if ( self::$cache !== null ) {
            return self::$cache;
        }

        $stored = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $stored ) ) {
            $stored = [];
        }

        self::$cache = array_merge( self::get_defaults(), $stored );

        return self::$cache;
    }

    /**
     * Tek bir ayar degerini dondurur.
     *
     * @param string $key     Ayar anahtari.
     * @param mixed  $default Varsayilan deger (null ise defaults'tan alinir).
     * @return mixed
     */
    public static function get( string $key, mixed $default = null ): mixed {
        $all = self::get_all();

        if ( array_key_exists( $key, $all ) ) {
            return $all[ $key ];
        }

        return $default;
    }

    /**
     * Tek bir ayar degerini gunceller.
     *
     * @param string $key   Ayar anahtari.
     * @param mixed  $value Yeni deger.
     * @return void
     */
    public static function set( string $key, mixed $value ): void {
        $all         = self::get_all();
        $all[ $key ] = $value;
        self::save( $all );
    }

    /**
     * Tum ayarlari toplu olarak kaydeder.
     *
     * @param array<string, mixed> $settings Kaydedilecek ayarlar.
     * @return void
     */
    public static function save( array $settings ): void {
        // Sadece bilinen anahtarlari kabul et.
        $defaults   = self::get_defaults();
        $sanitized  = [];

        foreach ( $defaults as $key => $default_value ) {
            if ( array_key_exists( $key, $settings ) ) {
                $sanitized[ $key ] = self::sanitize_value( $key, $settings[ $key ], $default_value );
            } else {
                $sanitized[ $key ] = $default_value;
            }
        }

        update_option( self::OPTION_KEY, $sanitized );
        self::$cache = $sanitized;
    }

    /**
     * Varsayilan ayarlari dondurur.
     *
     * @return array<string, mixed>
     */
    public static function get_defaults(): array {
        return [
            // XP Kaynaklari
            'xp_order_enabled'       => true,
            'xp_order_base'          => 10,
            'xp_order_per_currency'  => 1,
            'xp_first_order_bonus'   => 50,
            'xp_review_enabled'      => true,
            'xp_review_amount'       => 15,
            'xp_review_min_chars'    => 20,
            'xp_login_enabled'       => true,
            'xp_login_amount'        => 5,
            'xp_birthday_enabled'    => true,
            'xp_birthday_amount'     => 100,
            'xp_anniversary_enabled' => true,
            'xp_anniversary_amount'  => 50,
            'xp_registration_enabled' => true,
            'xp_registration_amount' => 25,

            // Streak (Giris Serisi)
            'streak_enabled'         => true,
            'streak_base_xp'         => 2,
            'streak_multiplier'      => 2.0,
            'streak_max_day'         => 7,
            'streak_tolerance'       => false,
            'streak_cycle_reset'     => true,

            // Seviye
            'level_mode'             => 'alltime',
            'level_rolling_months'   => 6,
            'level_grace_days'       => 14,

            // Kotuye Kullanim Engelleme
            'daily_xp_cap'           => 500,
            'duplicate_review_block' => true,

            // Genel
            'currency_label'         => 'XP',
            'keep_data_on_uninstall' => false,

            // Profile Completion
            'xp_profile_enabled'  => true,
            'xp_profile_amount'   => 20,

            // XP Expiry
            'xp_expiry_enabled'   => false,
            'xp_expiry_months'    => 12,
            'xp_expiry_warn_days' => 14,

            // Referral/Affiliate (Gorilla integration)
            'xp_referral_amount'  => 50,
            'xp_affiliate_amount' => 30,
        ];
    }

    /**
     * Ayar degerini tipine gore sanitize eder.
     *
     * @param string $key           Ayar anahtari.
     * @param mixed  $value         Gelen deger.
     * @param mixed  $default_value Varsayilan deger (tip referansi).
     * @return mixed Sanitize edilmis deger.
     */
    private static function sanitize_value( string $key, mixed $value, mixed $default_value ): mixed {
        return match ( true ) {
            is_bool( $default_value )   => filter_var( $value, FILTER_VALIDATE_BOOLEAN ),
            is_int( $default_value )    => (int) $value,
            is_float( $default_value )  => (float) $value,
            is_string( $default_value ) => sanitize_text_field( (string) $value ),
            default                     => $value,
        };
    }

    /**
     * Onbellegi temizler (test amacli).
     *
     * @return void
     */
    public static function flush_cache(): void {
        self::$cache = null;
    }
}
