<?php
/**
 * WP Gamify - GDPR Compliance
 *
 * WordPress Privacy (GDPR) entegrasyonu.
 * Kisisel veri export ve silme islemlerini yonetir.
 *
 * @package    WPGamify
 * @subpackage Includes
 * @since      2.0.0
 */

defined( 'ABSPATH' ) || exit;

class WPGamify_GDPR {

    /**
     * Register GDPR hooks.
     */
    public static function init(): void {
        add_filter( 'wp_privacy_personal_data_exporters', [ self::class, 'register_exporters' ] );
        add_filter( 'wp_privacy_personal_data_erasers', [ self::class, 'register_erasers' ] );
        add_action( 'admin_init', [ self::class, 'add_privacy_policy' ] );
    }

    /**
     * Register data exporters.
     */
    public static function register_exporters( array $exporters ): array {
        $exporters['wpgamify-xp'] = [
            'exporter_friendly_name' => 'WP Gamify - XP Verileri',
            'callback'               => [ self::class, 'export_xp_data' ],
        ];
        return $exporters;
    }

    /**
     * Register data erasers.
     */
    public static function register_erasers( array $erasers ): array {
        $erasers['wpgamify-xp'] = [
            'eraser_friendly_name' => 'WP Gamify - XP Verileri',
            'callback'             => [ self::class, 'erase_xp_data' ],
        ];
        return $erasers;
    }

    /**
     * Export user's XP data.
     */
    public static function export_xp_data( string $email, int $page = 1 ): array {
        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            return [ 'data' => [], 'done' => true ];
        }

        $user_id = $user->ID;
        $data    = [];

        // XP Level info
        global $wpdb;
        $level_table = $wpdb->prefix . 'gamify_user_levels';
        $level_row   = $wpdb->get_row( $wpdb->prepare(
            "SELECT current_level, total_xp, rolling_xp, grace_until, last_xp_at FROM {$level_table} WHERE user_id = %d",
            $user_id
        ), ARRAY_A );

        if ( $level_row ) {
            $data[] = [
                'group_id'    => 'wpgamify-level',
                'group_label' => 'WP Gamify - Seviye',
                'item_id'     => "gamify-level-{$user_id}",
                'data'        => [
                    [ 'name' => 'Mevcut Seviye', 'value' => $level_row['current_level'] ],
                    [ 'name' => 'Toplam XP', 'value' => $level_row['total_xp'] ],
                    [ 'name' => 'Rolling XP', 'value' => $level_row['rolling_xp'] ],
                    [ 'name' => 'Son XP Tarihi', 'value' => $level_row['last_xp_at'] ?? '-' ],
                ],
            ];
        }

        // Streak info
        $streak_table = $wpdb->prefix . 'gamify_streaks';
        $streak_row   = $wpdb->get_row( $wpdb->prepare(
            "SELECT current_streak, max_streak, last_activity_date FROM {$streak_table} WHERE user_id = %d",
            $user_id
        ), ARRAY_A );

        if ( $streak_row ) {
            $data[] = [
                'group_id'    => 'wpgamify-streak',
                'group_label' => 'WP Gamify - Giris Serisi',
                'item_id'     => "gamify-streak-{$user_id}",
                'data'        => [
                    [ 'name' => 'Mevcut Seri', 'value' => $streak_row['current_streak'] ],
                    [ 'name' => 'En Iyi Seri', 'value' => $streak_row['max_streak'] ],
                    [ 'name' => 'Son Aktivite', 'value' => $streak_row['last_activity_date'] ?? '-' ],
                ],
            ];
        }

        // XP Transactions (paginated)
        $per_page  = 50;
        $offset    = ( $page - 1 ) * $per_page;
        $txn_table = $wpdb->prefix . 'gamify_xp_transactions';

        $transactions = $wpdb->get_results( $wpdb->prepare(
            "SELECT amount, source, source_id, note, created_at FROM {$txn_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id, $per_page, $offset
        ), ARRAY_A );

        foreach ( $transactions as $i => $txn ) {
            $data[] = [
                'group_id'    => 'wpgamify-transactions',
                'group_label' => 'WP Gamify - XP Islemleri',
                'item_id'     => "gamify-txn-{$user_id}-{$offset}-{$i}",
                'data'        => [
                    [ 'name' => 'Miktar', 'value' => $txn['amount'] ],
                    [ 'name' => 'Kaynak', 'value' => $txn['source'] ],
                    [ 'name' => 'Not', 'value' => $txn['note'] ?? '' ],
                    [ 'name' => 'Tarih', 'value' => $txn['created_at'] ],
                ],
            ];
        }

        $done = count( $transactions ) < $per_page;

        return [ 'data' => $data, 'done' => $done ];
    }

    /**
     * Erase user's XP data.
     */
    public static function erase_xp_data( string $email, int $page = 1 ): array {
        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            return [ 'items_removed' => 0, 'items_retained' => 0, 'messages' => [], 'done' => true ];
        }

        $user_id = $user->ID;
        global $wpdb;

        $removed = 0;

        // Delete XP transactions
        $txn_table = $wpdb->prefix . 'gamify_xp_transactions';
        $removed += (int) $wpdb->delete( $txn_table, [ 'user_id' => $user_id ], [ '%d' ] );

        // Delete user level row
        $level_table = $wpdb->prefix . 'gamify_user_levels';
        $removed += (int) $wpdb->delete( $level_table, [ 'user_id' => $user_id ], [ '%d' ] );

        // Delete streak row
        $streak_table = $wpdb->prefix . 'gamify_streaks';
        $removed += (int) $wpdb->delete( $streak_table, [ 'user_id' => $user_id ], [ '%d' ] );

        // Delete all _wpgamify_* user meta
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
            $user_id, '_wpgamify_%'
        ) );

        // Delete audit log entries
        $audit_table = $wpdb->prefix . 'gamify_audit_log';
        $wpdb->delete( $audit_table, [ 'target_user_id' => $user_id ], [ '%d' ] );

        // Clear transients
        delete_transient( "wpgamify_user_level_{$user_id}" );

        return [
            'items_removed'  => $removed,
            'items_retained' => 0,
            'messages'       => [],
            'done'           => true,
        ];
    }

    /**
     * Add privacy policy content.
     */
    public static function add_privacy_policy(): void {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
            return;
        }

        $content = '<h2>WP Gamify - Gamification Verileri</h2>
        <p>Bu eklenti asagidaki kisisel verileri toplar ve isler:</p>
        <ul>
        <li><strong>XP Islemleri:</strong> Kazanilan ve harcanan XP miktarlari, kaynagi ve tarihi.</li>
        <li><strong>Seviye Bilgisi:</strong> Mevcut seviye, toplam XP ve son aktivite tarihi.</li>
        <li><strong>Giris Serisi:</strong> Ardisik giris gunleri ve en iyi seri kaydi.</li>
        <li><strong>Dogum Gunu:</strong> Ay ve gun bilgisi (yalnizca dogum gunu odulu icin).</li>
        </ul>
        <p>Bu veriler GDPR kapsaminda export ve silme talebine tabidir.</p>';

        wp_add_privacy_policy_content( 'WP Gamify', $content );
    }
}
