<?php
/**
 * Anti-Abuse Guard
 *
 * Kotuye kullanim onleme sistemi. Gunluk XP limiti,
 * tekrar yorum engeli, kendi kendine referans kontrolu
 * ve suphe kaydi islemlerini yonetir.
 *
 * @package WPGamify
 */

defined( 'ABSPATH' ) || exit;

class WPGamify_Anti_Abuse {

    /**
     * Gunluk XP limitini kontrol eder ve izin verilen miktari dondurur.
     *
     * @param int $user_id      Kullanici ID.
     * @param int $requested_xp Istenen XP miktari.
     * @return int Izin verilen XP miktari (0 = limit asildi).
     */
    public static function check_daily_cap( int $user_id, int $requested_xp ): int {
        $cap = (int) WPGamify_Settings::get( 'daily_xp_cap', 500 );

        // 0 veya negatif = limit yok.
        if ( $cap <= 0 ) {
            return $requested_xp;
        }

        $earned_today = WPGamify_XP_Engine::get_today_xp( $user_id );
        $remaining    = max( 0, $cap - $earned_today );

        if ( $remaining <= 0 ) {
            self::log_suspicious(
                $user_id,
                sprintf( 'Gunluk XP limiti asildi. Cap: %d, Bugun: %d, Istenen: %d', $cap, $earned_today, $requested_xp )
            );
            return 0;
        }

        return min( $requested_xp, $remaining );
    }

    /**
     * Kullanicinin belirli bir urune daha once yorum yapip yapmadigini kontrol eder.
     *
     * @param int $user_id    Kullanici ID.
     * @param int $product_id Urun ID.
     * @return bool Daha once yorum yaptiysa true.
     */
    public static function has_reviewed_product( int $user_id, int $product_id ): bool {
        if ( ! WPGamify_Settings::get( 'duplicate_review_block', true ) ) {
            return false;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'gamify_xp_transactions';
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE user_id = %d AND source = 'review' AND source_id = %s",
                $user_id,
                (string) $product_id
            )
        );

        return $count > 0;
    }

    /**
     * Referans isleminin kendi kendine referans olup olmadigini kontrol eder.
     *
     * @param int $referrer_id Referans eden kullanici ID.
     * @param int $referred_id Referans edilen kullanici ID.
     * @return bool Kendi kendine referans ise true.
     */
    public static function is_self_referral( int $referrer_id, int $referred_id ): bool {
        if ( $referrer_id === $referred_id ) {
            self::log_suspicious(
                $referrer_id,
                'Kendi kendine referans denemesi tespit edildi.'
            );
            return true;
        }

        // Ayni IP kontrolu.
        $referrer_ip = get_user_meta( $referrer_id, 'wpgamify_last_ip', true );
        $referred_ip = get_user_meta( $referred_id, 'wpgamify_last_ip', true );

        if ( ! empty( $referrer_ip ) && $referrer_ip === $referred_ip ) {
            self::log_suspicious(
                $referrer_id,
                sprintf(
                    'Ayni IP ile referans denemesi. Referrer: %d, Referred: %d, IP: %s',
                    $referrer_id,
                    $referred_id,
                    $referrer_ip
                )
            );
            return true;
        }

        // Ayni e-posta alan adi kontrolu.
        $referrer_user = get_userdata( $referrer_id );
        $referred_user = get_userdata( $referred_id );

        if ( $referrer_user && $referred_user ) {
            $referrer_domain = substr( strrchr( $referrer_user->user_email, '@' ), 1 );
            $referred_domain = substr( strrchr( $referred_user->user_email, '@' ), 1 );

            // Genel e-posta saglaycilari haric.
            $generic_domains = [
                'gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com',
                'icloud.com', 'yandex.com', 'mail.com', 'protonmail.com',
            ];

            if (
                ! in_array( $referrer_domain, $generic_domains, true ) &&
                $referrer_domain === $referred_domain
            ) {
                self::log_suspicious(
                    $referrer_id,
                    sprintf(
                        'Ayni e-posta alan adi ile referans. Alan: %s',
                        $referrer_domain
                    )
                );
                // Uyari logla ama engelleme.
            }
        }

        return false;
    }

    /**
     * Supheli aktiviteyi loglar.
     *
     * @param int    $user_id Kullanici ID.
     * @param string $reason  Sebep aciklamasi.
     * @return void
     */
    public static function log_suspicious( int $user_id, string $reason ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                '[WP Gamify Anti-Abuse] User: %d | %s',
                $user_id,
                $reason
            ) );
        }

        // Kullanici meta ile suphe sayaci.
        $count = (int) get_user_meta( $user_id, 'wpgamify_suspicious_count', true );
        update_user_meta( $user_id, 'wpgamify_suspicious_count', $count + 1 );
        update_user_meta( $user_id, 'wpgamify_last_suspicious', current_time( 'mysql' ) );
        update_user_meta( $user_id, 'wpgamify_last_suspicious_reason', sanitize_text_field( $reason ) );

        /**
         * Supheli aktivite tespit edildiginde tetiklenir.
         *
         * @param int    $user_id Kullanici ID.
         * @param string $reason  Sebep.
         * @param int    $count   Toplam suphe sayisi.
         */
        do_action( 'gamify_suspicious_activity', $user_id, $reason, $count + 1 );
    }

    /**
     * Kullanicinin belirli bir kaynak icin bugun XP alip almadigini kontrol eder.
     *
     * @param int    $user_id Kullanici ID.
     * @param string $source  Kaynak kodu.
     * @return bool Bugun XP aldiysa true.
     */
    public static function has_earned_today( int $user_id, string $source ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'gamify_xp_transactions';
        $today = ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' );

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE user_id = %d AND source = %s AND DATE(created_at) = %s",
                $user_id,
                $source,
                $today
            )
        );

        return $count > 0;
    }

    /**
     * Kullanicinin suphe sayisini dondurur.
     *
     * @param int $user_id Kullanici ID.
     * @return int Suphe sayisi.
     */
    public static function get_suspicious_count( int $user_id ): int {
        return (int) get_user_meta( $user_id, 'wpgamify_suspicious_count', true );
    }
}
