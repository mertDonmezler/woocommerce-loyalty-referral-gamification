<?php
/**
 * WP Gamify Audit Log Viewer
 *
 * @package WPGamify
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$table = $wpdb->prefix . 'gamify_audit_log';
$per_page = 50;
$current_page = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
$offset = ( $current_page - 1 ) * $per_page;

// Filters.
$filter_date_from = sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) );
$filter_date_to   = sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) );
$filter_admin     = (int) ( $_GET['admin_id'] ?? 0 );
$filter_action    = sanitize_text_field( wp_unslash( $_GET['action_type'] ?? '' ) );

// Build WHERE clauses.
$where   = [];
$values  = [];

if ( $filter_date_from ) {
    $where[]  = 'a.created_at >= %s';
    $values[] = $filter_date_from . ' 00:00:00';
}
if ( $filter_date_to ) {
    $where[]  = 'a.created_at <= %s';
    $values[] = $filter_date_to . ' 23:59:59';
}
if ( $filter_admin > 0 ) {
    $where[]  = 'a.admin_id = %d';
    $values[] = $filter_admin;
}
if ( $filter_action ) {
    $where[]  = 'a.action = %s';
    $values[] = $filter_action;
}

$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

// Total count.
$count_query = "SELECT COUNT(*) FROM {$table} a {$where_sql}";
if ( ! empty( $values ) ) {
    $count_query = $wpdb->prepare( $count_query, ...$values );
}
$total_items = (int) $wpdb->get_var( $count_query );
$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );

// Fetch rows.
$query = "SELECT a.*, admin_u.display_name as admin_name, user_u.display_name as user_name
          FROM {$table} a
          LEFT JOIN {$wpdb->users} admin_u ON a.admin_id = admin_u.ID
          LEFT JOIN {$wpdb->users} user_u ON a.target_user_id = user_u.ID
          {$where_sql}
          ORDER BY a.created_at DESC
          LIMIT %d OFFSET %d";

$query_values = array_merge( $values, [ $per_page, $offset ] );
$rows = $wpdb->get_results( $wpdb->prepare( $query, ...$query_values ) );

// Admin users for filter dropdown.
$admin_users = get_users( [
    'role__in' => [ 'administrator', 'shop_manager' ],
    'fields'   => [ 'ID', 'display_name' ],
    'orderby'  => 'display_name',
] );

?>
<div class="wrap wpgamify-wrap">
    <h1 class="wp-heading-inline">Islem Logu</h1>
    <hr class="wp-header-end">

    <!-- Filters -->
    <div class="wpgamify-panel wpgamify-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="wp-gamify-audit-log">
            <div class="wpgamify-filter-row">
                <label>
                    Baslangic:
                    <input type="date" name="date_from" value="<?php echo esc_attr( $filter_date_from ); ?>">
                </label>
                <label>
                    Bitis:
                    <input type="date" name="date_to" value="<?php echo esc_attr( $filter_date_to ); ?>">
                </label>
                <label>
                    Admin:
                    <select name="admin_id">
                        <option value="">Tumu</option>
                        <?php foreach ( $admin_users as $admin ) : ?>
                            <option value="<?php echo (int) $admin->ID; ?>"
                                <?php selected( $filter_admin, (int) $admin->ID ); ?>>
                                <?php echo esc_html( $admin->display_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Islem:
                    <select name="action_type">
                        <option value="">Tumu</option>
                        <option value="add" <?php selected( $filter_action, 'add' ); ?>>XP Ekle</option>
                        <option value="deduct" <?php selected( $filter_action, 'deduct' ); ?>>XP Cikar</option>
                    </select>
                </label>
                <button type="submit" class="button">Filtrele</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-gamify-audit-log' ) ); ?>"
                   class="button button-link">Sifirla</a>
            </div>
        </form>
    </div>

    <!-- Results -->
    <div class="wpgamify-panel">
        <p class="wpgamify-result-count">
            Toplam <strong><?php echo number_format_i18n( $total_items ); ?></strong> kayit bulundu.
        </p>

        <table class="widefat striped wpgamify-audit-table">
            <thead>
                <tr>
                    <th>Tarih</th>
                    <th>Admin</th>
                    <th>Musteri</th>
                    <th>Islem</th>
                    <th>Miktar</th>
                    <th>Onceki XP</th>
                    <th>Sonraki XP</th>
                    <th>Sebep</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $rows ) ) : ?>
                    <?php foreach ( $rows as $row ) :
                        $action_label = $row->action === 'deduct' ? 'XP Cikar' : 'XP Ekle';
                        $action_class = $row->action === 'deduct' ? 'wpgamify-xp-negative' : 'wpgamify-xp-positive';
                        ?>
                        <tr>
                            <td><?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $row->created_at ) ) ); ?></td>
                            <td><?php echo esc_html( $row->admin_name ?? '#' . $row->admin_id ); ?></td>
                            <td><?php echo esc_html( $row->user_name ?? '#' . $row->target_user_id ); ?></td>
                            <td><?php echo esc_html( $action_label ); ?></td>
                            <td class="<?php echo esc_attr( $action_class ); ?>">
                                <?php echo number_format_i18n( (int) $row->amount ); ?>
                            </td>
                            <td><?php echo number_format_i18n( (int) $row->before_value ); ?></td>
                            <td><?php echo number_format_i18n( (int) $row->after_value ); ?></td>
                            <td><?php echo esc_html( $row->reason ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="8">Kayit bulunamadi.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php echo number_format_i18n( $total_items ); ?> kayit
                    </span>
                    <span class="pagination-links">
                        <?php
                        $base_url = admin_url( 'admin.php?page=wp-gamify-audit-log' );
                        $params   = array_filter( [
                            'date_from'   => $filter_date_from,
                            'date_to'     => $filter_date_to,
                            'admin_id'    => $filter_admin ?: '',
                            'action_type' => $filter_action,
                        ] );
                        $base_url = add_query_arg( $params, $base_url );

                        if ( $current_page > 1 ) :
                            ?>
                            <a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ); ?>">
                                &lsaquo;
                            </a>
                        <?php else : ?>
                            <span class="tablenav-pages-navspan button disabled">&lsaquo;</span>
                        <?php endif; ?>

                        <span class="paging-input">
                            <?php echo $current_page; ?> / <?php echo $total_pages; ?>
                        </span>

                        <?php if ( $current_page < $total_pages ) : ?>
                            <a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ); ?>">
                                &rsaquo;
                            </a>
                        <?php else : ?>
                            <span class="tablenav-pages-navspan button disabled">&rsaquo;</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
