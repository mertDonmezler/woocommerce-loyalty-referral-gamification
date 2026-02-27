<?php
/**
 * WP Gamify Admin Dashboard
 *
 * @package WPGamify
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$xp_table    = $wpdb->prefix . 'gamify_xp_transactions';
$level_table = $wpdb->prefix . 'gamify_user_levels';

$today_start = gmdate( 'Y-m-d 00:00:00' );
$week_start  = gmdate( 'Y-m-d 00:00:00', strtotime( 'monday this week' ) );
$month_ago   = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

// Summary queries.
$today_xp = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM {$xp_table} WHERE created_at >= %s AND amount > 0",
    $today_start
) );

$active_users = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(DISTINCT user_id) FROM {$xp_table} WHERE created_at >= %s",
    $month_ago
) );

$week_xp = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM {$xp_table} WHERE created_at >= %s AND amount > 0",
    $week_start
) );

// Top level info.
$top_level_row = $wpdb->get_row(
    "SELECT current_level, COUNT(*) as cnt FROM {$level_table}
     GROUP BY current_level ORDER BY current_level DESC LIMIT 1"
);
$top_level_name  = $top_level_row->current_level ?? '-';
$top_level_count = (int) ( $top_level_row->cnt ?? 0 );

// Level distribution.
$level_dist = $wpdb->get_results(
    "SELECT current_level, COUNT(*) as cnt FROM {$level_table}
     GROUP BY current_level ORDER BY current_level ASC"
);
$max_level_count = 1;
foreach ( $level_dist as $row ) {
    if ( (int) $row->cnt > $max_level_count ) {
        $max_level_count = (int) $row->cnt;
    }
}

// Recent transactions.
$recent_txns = $wpdb->get_results(
    "SELECT t.*, u.display_name
     FROM {$xp_table} t
     LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
     ORDER BY t.created_at DESC
     LIMIT 10"
);

// Active campaign.
$campaign = class_exists( 'WPGamify_Campaign_Manager' )
    ? WPGamify_Campaign_Manager::get_active_campaign()
    : null;

?>
<div class="wrap wpgamify-wrap">
    <h1 class="wp-heading-inline">WP Gamify Dashboard</h1>
    <hr class="wp-header-end">

    <!-- Summary Cards -->
    <div class="wpgamify-cards-row">
        <div class="wpgamify-card">
            <div class="wpgamify-card-icon" style="color: var(--wpg-primary);">
                <span class="dashicons dashicons-chart-bar"></span>
            </div>
            <div class="wpgamify-card-body">
                <span class="wpgamify-card-label">Bugun XP</span>
                <span class="wpgamify-card-value"><?php echo number_format_i18n( $today_xp ); ?></span>
            </div>
        </div>

        <div class="wpgamify-card">
            <div class="wpgamify-card-icon" style="color: var(--wpg-success);">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="wpgamify-card-body">
                <span class="wpgamify-card-label">Aktif Uye (30 gun)</span>
                <span class="wpgamify-card-value"><?php echo number_format_i18n( $active_users ); ?></span>
            </div>
        </div>

        <div class="wpgamify-card">
            <div class="wpgamify-card-icon" style="color: var(--wpg-warning);">
                <span class="dashicons dashicons-awards"></span>
            </div>
            <div class="wpgamify-card-body">
                <span class="wpgamify-card-label">En Ust Level</span>
                <span class="wpgamify-card-value">
                    <?php echo esc_html( $top_level_name ); ?>
                    <small>(<?php echo number_format_i18n( $top_level_count ); ?> uye)</small>
                </span>
            </div>
        </div>

        <div class="wpgamify-card">
            <div class="wpgamify-card-icon" style="color: var(--wpg-primary);">
                <span class="dashicons dashicons-trending-up"></span>
            </div>
            <div class="wpgamify-card-body">
                <span class="wpgamify-card-label">Bu Hafta XP</span>
                <span class="wpgamify-card-value">+<?php echo number_format_i18n( $week_xp ); ?></span>
            </div>
        </div>
    </div>

    <div class="wpgamify-dashboard-grid">
        <!-- Level Distribution -->
        <div class="wpgamify-panel">
            <h2 class="wpgamify-panel-title">Level Dagilimi</h2>
            <div class="wpgamify-level-bars">
                <?php if ( ! empty( $level_dist ) ) : ?>
                    <?php foreach ( $level_dist as $row ) :
                        $pct = round( ( (int) $row->cnt / $max_level_count ) * 100 );
                        ?>
                        <div class="wpgamify-level-bar-row">
                            <span class="wpgamify-level-bar-label">
                                <?php echo esc_html( $row->current_level ); ?>
                            </span>
                            <div class="wpgamify-level-bar-track">
                                <div class="wpgamify-level-bar-fill" style="width: <?php echo $pct; ?>%"></div>
                            </div>
                            <span class="wpgamify-level-bar-count">
                                <?php echo number_format_i18n( (int) $row->cnt ); ?> uye
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="description">Henuz level verisi yok.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="wpgamify-panel">
            <h2 class="wpgamify-panel-title">Son 10 XP Islemi</h2>
            <?php if ( ! empty( $recent_txns ) ) : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Kullanici</th>
                            <th>Kaynak</th>
                            <th>Miktar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $recent_txns as $txn ) :
                            $source_label = class_exists( 'WPGamify_XP_Engine' )
                                ? WPGamify_XP_Engine::get_source_label( $txn->source ?? '' )
                                : ( $txn->source ?? '-' );
                            $amount_class = ( (int) $txn->amount >= 0 ) ? 'wpgamify-xp-positive' : 'wpgamify-xp-negative';
                            ?>
                            <tr>
                                <td><?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $txn->created_at ) ) ); ?></td>
                                <td><?php echo esc_html( $txn->display_name ?? '#' . $txn->user_id ); ?></td>
                                <td><?php echo esc_html( $source_label ); ?></td>
                                <td class="<?php echo esc_attr( $amount_class ); ?>">
                                    <?php echo ( (int) $txn->amount >= 0 ? '+' : '' ) . number_format_i18n( (int) $txn->amount ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="description">Henuz islem yok.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Active Campaign -->
    <?php if ( $campaign ) : ?>
        <div class="wpgamify-panel wpgamify-campaign-banner">
            <h2 class="wpgamify-panel-title">Aktif Kampanya</h2>
            <div class="wpgamify-campaign-info">
                <span class="wpgamify-campaign-label"><?php echo esc_html( $campaign['label'] ?? '' ); ?></span>
                <span class="wpgamify-campaign-multiplier">
                    x<?php echo esc_html( $campaign['multiplier'] ?? 1 ); ?> XP Carpani
                </span>
                <span class="wpgamify-campaign-dates">
                    <?php echo esc_html( $campaign['start'] ?? '' ); ?> -
                    <?php echo esc_html( $campaign['end'] ?? '' ); ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>
