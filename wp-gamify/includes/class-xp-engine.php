<?php
/**
 * XP Engine
 *
 * Temel XP motoru. XP verme, dusme, gecmis sorgulama ve
 * seviye senkronizasyonu islemlerini yonetir.
 * Tum XP islemleri bu sinif uzerinden yapilir.
 *
 * @package WPGamify
 */

defined( 'ABSPATH' ) || exit;

class WPGamify_XP_Engine {

    /**
     * Kullaniciya XP verir.
     *
     * Ana giris noktasi. Gunluk limit kontrolu, kampanya carpani,
     * islem kaydi ve seviye senkronizasyonu yapar.
     *
     * @param int    $user_id   Kullanici ID.
     * @param int    $amount    XP miktari.
     * @param string $source    Kaynak kodu (order, review, login vb.).
     * @param string $source_id Kaynak ID (siparis no, yorum ID vb.).
     * @param string $note      Not.
     * @return bool|int Basarili ise kaydedilen XP, basarisiz ise false.
     */
    public static function award( int $user_id, int $amount, string $source, string $source_id = '', string $note = '' ): bool|int {
        if ( $amount <= 0 || $user_id <= 0 ) {
            return false;
        }

        // Gunluk limit kontrolu.
        $amount = WPGamify_Anti_Abuse::check_daily_cap( $user_id, $amount );
        if ( $amount <= 0 ) {
            return false;
        }

        // Kampanya carpani filtresi.
        $campaign_mult = (float) apply_filters( 'gamify_xp_campaign_multiplier', 1.0, $source, $user_id );
        $campaign_mult = max( 0.01, min( 99.99, $campaign_mult ) );

        // XP filtreleme (3. parti muedahale noktasi).
        $final_amount = (int) apply_filters(
            'gamify_xp_before_award',
            (int) round( $amount * $campaign_mult ),
            $source,
            $user_id,
            [
                'original_amount' => $amount,
                'campaign_mult'   => $campaign_mult,
                'source_id'       => $source_id,
            ]
        );

        if ( $final_amount <= 0 ) {
            return false;
        }

        global $wpdb;
        $now = current_time( 'mysql' );

        $wpdb->query( 'START TRANSACTION' );

        try {
            // XP islem kaydi.
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'gamify_xp_transactions',
                [
                    'user_id'       => $user_id,
                    'amount'        => $final_amount,
                    'source'        => sanitize_key( $source ),
                    'source_id'     => sanitize_text_field( $source_id ),
                    'campaign_mult' => $campaign_mult,
                    'note'          => sanitize_text_field( $note ),
                    'created_at'    => $now,
                ],
                [ '%d', '%d', '%s', '%s', '%f', '%s', '%s' ]
            );

            if ( $inserted === false ) {
                $wpdb->query( 'ROLLBACK' );
                return false;
            }

            // Kullanici seviye tablosunu guncelle.
            self::sync_user_level( $user_id );

            $wpdb->query( 'COMMIT' );
        } catch ( \Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'WP Gamify XP award hatasi: ' . $e->getMessage() );
            }
            return false;
        }

        /**
         * XP verildikten sonra tetiklenir.
         *
         * @param int    $user_id      Kullanici ID.
         * @param int    $final_amount Verilen XP.
         * @param string $source       Kaynak.
         * @param string $source_id    Kaynak ID.
         */
        do_action( 'gamify_after_xp_awarded', $user_id, $final_amount, $source, $source_id );

        return $final_amount;
    }

    /**
     * Kullanicidan XP duser (iade, ceza vb.).
     *
     * Negatif islem kaydi olusturur.
     *
     * @param int    $user_id   Kullanici ID.
     * @param int    $amount    Dusulecek XP (pozitif sayi).
     * @param string $source    Kaynak kodu.
     * @param string $source_id Kaynak ID.
     * @param string $note      Not.
     * @return bool|int Basarili ise dusulen miktar, basarisiz ise false.
     */
    public static function deduct( int $user_id, int $amount, string $source, string $source_id = '', string $note = '' ): bool|int {
        if ( $amount <= 0 || $user_id <= 0 ) {
            return false;
        }

        global $wpdb;
        $now = current_time( 'mysql' );

        $wpdb->query( 'START TRANSACTION' );

        try {
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'gamify_xp_transactions',
                [
                    'user_id'       => $user_id,
                    'amount'        => -$amount,
                    'source'        => sanitize_key( $source ),
                    'source_id'     => sanitize_text_field( $source_id ),
                    'campaign_mult' => 1.00,
                    'note'          => sanitize_text_field( $note ),
                    'created_at'    => $now,
                ],
                [ '%d', '%d', '%s', '%s', '%f', '%s', '%s' ]
            );

            if ( $inserted === false ) {
                $wpdb->query( 'ROLLBACK' );
                return false;
            }

            self::sync_user_level( $user_id );

            $wpdb->query( 'COMMIT' );
        } catch ( \Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'WP Gamify XP deduct hatasi: ' . $e->getMessage() );
            }
            return false;
        }

        /**
         * XP dusuldukten sonra tetiklenir.
         *
         * @param int    $user_id   Kullanici ID.
         * @param int    $amount    Dusulen XP.
         * @param string $source    Kaynak.
         * @param string $source_id Kaynak ID.
         */
        do_action( 'gamify_after_xp_deducted', $user_id, $amount, $source, $source_id );

        return $amount;
    }

    /**
     * Kullanicinin toplam XP degerini dondurur (tum zamanlar).
     *
     * @param int $user_id Kullanici ID.
     * @return int Toplam XP.
     */
    public static function get_total_xp( int $user_id ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'gamify_user_levels';
        $xp    = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT total_xp FROM {$table} WHERE user_id = %d",
                $user_id
            )
        );

        return (int) ( $xp ?? 0 );
    }

    /**
     * Kullanicinin belirli ay penceresindeki XP degerini dondurur.
     *
     * @param int $user_id Kullanici ID.
     * @param int $months  Ay sayisi (varsayilan: 6).
     * @return int Pencere icindeki XP.
     */
    public static function get_rolling_xp( int $user_id, int $months = 6 ): int {
        global $wpdb;

        $table     = $wpdb->prefix . 'gamify_xp_transactions';
        $since     = ( new \DateTimeImmutable( 'now', wp_timezone() ) )
            ->modify( "-{$months} months" )
            ->format( 'Y-m-d H:i:s' );

        $rolling = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE user_id = %d AND created_at >= %s",
                $user_id,
                $since
            )
        );

        return max( 0, (int) $rolling );
    }

    /**
     * Kullanicinin bugun kazandigi toplam XP degerini dondurur.
     *
     * @param int $user_id Kullanici ID.
     * @return int Bugunun XP toplami.
     */
    public static function get_today_xp( int $user_id ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'gamify_xp_transactions';
        $today = ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' );

        $xp = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM {$table}
                 WHERE user_id = %d AND amount > 0 AND DATE(created_at) = %s",
                $user_id,
                $today
            )
        );

        return (int) ( $xp ?? 0 );
    }

    /**
     * Gunluk limite gore izin verilen XP miktarini hesaplar.
     *
     * @param int $user_id Kullanici ID.
     * @param int $amount  Istenen XP miktari.
     * @return int Izin verilen XP (0 ise limit asildi).
     */
    public static function check_daily_cap( int $user_id, int $amount ): int {
        $cap = (int) WPGamify_Settings::get( 'daily_xp_cap', 500 );

        if ( $cap <= 0 ) {
            return $amount; // Limit yok.
        }

        $earned_today = self::get_today_xp( $user_id );
        $remaining    = max( 0, $cap - $earned_today );

        return min( $amount, $remaining );
    }

    /**
     * Kullanicinin XP gecmisini sayfalanmis olarak dondurur.
     *
     * @param int $user_id  Kullanici ID.
     * @param int $page     Sayfa numarasi (1'den baslar).
     * @param int $per_page Sayfa basi kayit sayisi.
     * @return array{items: array, total: int, pages: int, page: int}
     */
    public static function get_history( int $user_id, int $page = 1, int $per_page = 20 ): array {
        global $wpdb;

        $table    = $wpdb->prefix . 'gamify_xp_transactions';
        $page     = max( 1, $page );
        $per_page = max( 1, min( 100, $per_page ) );
        $offset   = ( $page - 1 ) * $per_page;

        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
                $user_id
            )
        );

        $items = [];
        if ( $total > 0 ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, amount, source, source_id, campaign_mult, note, created_at
                     FROM {$table}
                     WHERE user_id = %d
                     ORDER BY created_at DESC
                     LIMIT %d OFFSET %d",
                    $user_id,
                    $per_page,
                    $offset
                )
            );

            foreach ( $rows as $row ) {
                $items[] = [
                    'id'            => (int) $row->id,
                    'amount'        => (int) $row->amount,
                    'source'        => $row->source,
                    'source_label'  => self::get_source_label( $row->source ),
                    'source_id'     => $row->source_id,
                    'campaign_mult' => (float) $row->campaign_mult,
                    'note'          => $row->note,
                    'created_at'    => $row->created_at,
                ];
            }
        }

        return [
            'items' => $items,
            'total' => $total,
            'pages' => (int) ceil( $total / $per_page ),
            'page'  => $page,
        ];
    }

    /**
     * Kullanicinin seviye kaydini XP islemlerine gore gunceller.
     *
     * @param int $user_id Kullanici ID.
     * @return void
     */
    private static function sync_user_level( int $user_id ): void {
        global $wpdb;

        $txn_table   = $wpdb->prefix . 'gamify_xp_transactions';
        $level_table = $wpdb->prefix . 'gamify_user_levels';
        $conf_table  = $wpdb->prefix . 'gamify_levels_config';
        $now         = current_time( 'mysql' );

        // Toplam XP hesapla.
        $total_xp = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM {$txn_table} WHERE user_id = %d",
                $user_id
            )
        );
        $total_xp = max( 0, $total_xp );

        // Seviye moduna gore kullanilacak XP.
        $mode       = WPGamify_Settings::get( 'level_mode', 'alltime' );
        $rolling_xp = 0;
        $effective_xp = $total_xp;

        if ( $mode === 'rolling' ) {
            $months     = (int) WPGamify_Settings::get( 'level_rolling_months', 6 );
            $rolling_xp = self::get_rolling_xp( $user_id, $months );
            $effective_xp = $rolling_xp;
        }

        // Seviye belirle (en yuksek esigi gecen seviye).
        $current_level = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT level_number FROM {$conf_table}
                 WHERE xp_required <= %d
                 ORDER BY xp_required DESC
                 LIMIT 1",
                $effective_xp
            )
        );
        $current_level = max( 1, $current_level );

        // Grace period kontrolu (rolling modda seviye dusmesi icin).
        $grace_until = null;
        if ( $mode === 'rolling' ) {
            $existing = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT current_level, grace_until FROM {$level_table} WHERE user_id = %d",
                    $user_id
                )
            );

            if ( $existing && (int) $existing->current_level > $current_level ) {
                $grace_days = (int) WPGamify_Settings::get( 'level_grace_days', 14 );

                if ( $existing->grace_until === null ) {
                    // Grace baslangiclandi.
                    $grace_until   = ( new \DateTimeImmutable( 'now', wp_timezone() ) )
                        ->modify( "+{$grace_days} days" )
                        ->format( 'Y-m-d H:i:s' );
                    $current_level = (int) $existing->current_level; // Seviye korunuyor.
                } elseif ( strtotime( $existing->grace_until ) > time() ) {
                    // Grace hala gecerli.
                    $grace_until   = $existing->grace_until;
                    $current_level = (int) $existing->current_level;
                }
                // else: Grace suresi doldu, seviye duser.
            }
        }

        // Upsert: user_levels tablosu.
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$level_table} WHERE user_id = %d",
                $user_id
            )
        );

        if ( $exists ) {
            $wpdb->update(
                $level_table,
                [
                    'current_level' => $current_level,
                    'total_xp'      => $total_xp,
                    'rolling_xp'    => $rolling_xp,
                    'grace_until'   => $grace_until,
                    'last_xp_at'    => $now,
                    'updated_at'    => $now,
                ],
                [ 'user_id' => $user_id ],
                [ '%d', '%d', '%d', '%s', '%s', '%s' ],
                [ '%d' ]
            );
        } else {
            $wpdb->insert(
                $level_table,
                [
                    'user_id'       => $user_id,
                    'current_level' => $current_level,
                    'total_xp'      => $total_xp,
                    'rolling_xp'    => $rolling_xp,
                    'grace_until'   => $grace_until,
                    'last_xp_at'    => $now,
                    'updated_at'    => $now,
                ],
                [ '%d', '%d', '%d', '%d', '%s', '%s', '%s' ]
            );
        }

        // Transient onbellegini temizle.
        delete_transient( "wpgamify_user_level_{$user_id}" );
    }

    /**
     * Kaynak kodunu Turkce etikete cevirir.
     *
     * @param string $source Kaynak kodu.
     * @return string Turkce etiket.
     */
    public static function get_source_label( string $source ): string {
        $labels = [
            'order'        => 'Siparis',
            'review'       => 'Yorum',
            'login'        => 'Giris',
            'streak'       => 'Giris Serisi',
            'birthday'     => 'Dogum Gunu',
            'anniversary'  => 'Yildonumu',
            'registration' => 'Kayit',
            'manual'       => 'Manuel',
            'refund'       => 'Iade',
            'campaign'     => 'Kampanya',
            'first_order'  => 'Ilk Siparis',
        ];

        return $labels[ $source ] ?? ucfirst( $source );
    }

    /**
     * Kullanicinin mevcut seviye bilgilerini dondurur.
     *
     * @param int $user_id Kullanici ID.
     * @return array{level: int, name: string, total_xp: int, rolling_xp: int, next_level_xp: int, progress: float, color: string, benefits: array}
     */
    public static function get_user_level_info( int $user_id ): array {
        $transient_key = "wpgamify_user_level_{$user_id}";
        $cached        = get_transient( $transient_key );

        if ( $cached !== false ) {
            return $cached;
        }

        global $wpdb;

        $level_table = $wpdb->prefix . 'gamify_user_levels';
        $conf_table  = $wpdb->prefix . 'gamify_levels_config';

        $user_level = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT current_level, total_xp, rolling_xp FROM {$level_table} WHERE user_id = %d",
                $user_id
            )
        );

        $current_level = (int) ( $user_level->current_level ?? 1 );
        $total_xp      = (int) ( $user_level->total_xp ?? 0 );
        $rolling_xp    = (int) ( $user_level->rolling_xp ?? 0 );

        // Mevcut seviye bilgisi.
        $level_config = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT name, xp_required, benefits, color_hex FROM {$conf_table} WHERE level_number = %d",
                $current_level
            )
        );

        // Sonraki seviye bilgisi.
        $next_config = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT xp_required FROM {$conf_table} WHERE level_number = %d",
                $current_level + 1
            )
        );

        $mode          = WPGamify_Settings::get( 'level_mode', 'alltime' );
        $effective_xp  = $mode === 'rolling' ? $rolling_xp : $total_xp;
        $current_req   = (int) ( $level_config->xp_required ?? 0 );
        $next_req      = $next_config ? (int) $next_config->xp_required : 0;

        // Ilerleme yuzdesi.
        $progress = 100.0;
        if ( $next_req > $current_req ) {
            $progress = min( 100.0, ( ( $effective_xp - $current_req ) / ( $next_req - $current_req ) ) * 100 );
        }

        $benefits = [];
        if ( ! empty( $level_config->benefits ) ) {
            $decoded = json_decode( $level_config->benefits, true );
            if ( is_array( $decoded ) ) {
                $benefits = $decoded;
            }
        }

        $info = [
            'level'         => $current_level,
            'name'          => $level_config->name ?? 'Caylak',
            'total_xp'      => $total_xp,
            'rolling_xp'    => $rolling_xp,
            'next_level_xp' => $next_req,
            'progress'      => round( $progress, 1 ),
            'color'         => $level_config->color_hex ?? '#6366f1',
            'benefits'      => $benefits,
        ];

        set_transient( $transient_key, $info, HOUR_IN_SECONDS );

        return $info;
    }
}
