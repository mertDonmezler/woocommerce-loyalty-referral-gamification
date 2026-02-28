<?php
/**
 * Gorilla Loyalty & Gamification - WP-CLI Commands
 *
 * wp gorilla-lg tier recalculate-all
 * wp gorilla-lg tier list
 * wp gorilla-lg xp add <user_id> <amount> [--reason=<reason>]
 * wp gorilla-lg xp get <user_id>
 * wp gorilla-lg xp export [--format=<csv|json|table>] [--limit=<n>] [--user_id=<id>]
 *
 * @package Gorilla_Loyalty_Gamification
 * @since   3.1.0
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('WP_CLI')) return;

class Gorilla_LG_CLI {

    /**
     * @subcommand recalculate-all
     */
    public function tier_recalculate_all($args, $assoc_args) {
        $dry_run = WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run', false);

        if (get_option('gorilla_lr_enabled_loyalty') !== 'yes') {
            WP_CLI::error('Sadakat sistemi aktif degil (gorilla_lr_enabled_loyalty != yes).');
            return;
        }

        if (!function_exists('gorilla_loyalty_calculate_tier')) {
            WP_CLI::error('gorilla_loyalty_calculate_tier fonksiyonu bulunamadi.');
            return;
        }

        $user_ids = get_users(array('fields' => 'ID', 'orderby' => 'ID', 'order' => 'ASC'));

        $count   = count($user_ids);
        $changed = 0;

        WP_CLI::log(sprintf('%d kullanici kontrol edilecek...', $count));
        $progress = WP_CLI\Utils\make_progress_bar('Tier hesaplaniyor', $count);

        foreach ($user_ids as $user_id) {
            $old_tier = get_user_meta($user_id, '_gorilla_last_tier', true) ?: 'none';
            $result   = gorilla_loyalty_calculate_tier($user_id);
            $new_tier = $result['key'] ?? 'none';

            if ($old_tier !== $new_tier) {
                $changed++;
                if ($dry_run) {
                    WP_CLI::log(sprintf(
                        '  Kullanici #%d: %s -> %s (harcama: %s)',
                        $user_id, $old_tier, $new_tier, number_format($result['spending'] ?? 0, 2)
                    ));
                } else {
                    update_user_meta($user_id, '_gorilla_last_tier', $new_tier);
                    update_user_meta($user_id, '_gorilla_lr_tier_key', $new_tier);
                }
            }

            $progress->tick();
        }

        $progress->finish();

        if ($dry_run) {
            WP_CLI::success(sprintf('Dry run tamamlandi. %d / %d kullanicida degisiklik tespit edildi.', $changed, $count));
        } else {
            WP_CLI::success(sprintf('Tier yeniden hesaplama tamamlandi. %d / %d kullanici guncellendi.', $changed, $count));
        }
    }

    /**
     * @subcommand add
     */
    public function xp_add($args, $assoc_args) {
        list($user_id, $amount) = $args;
        $user_id = intval($user_id);
        $amount  = intval($amount);
        $reason  = WP_CLI\Utils\get_flag_value($assoc_args, 'reason', 'WP-CLI');

        if (!get_userdata($user_id)) {
            WP_CLI::error(sprintf('Kullanici #%d bulunamadi.', $user_id));
            return;
        }

        if ($amount <= 0) {
            WP_CLI::error('XP miktari 0\'dan buyuk olmali.');
            return;
        }

        if (!function_exists('gorilla_xp_add')) {
            WP_CLI::error('gorilla_xp_add fonksiyonu bulunamadi.');
            return;
        }

        $old_xp = function_exists('gorilla_xp_get_balance') ? gorilla_xp_get_balance($user_id) : 0;
        $new_xp = gorilla_xp_add($user_id, $amount, $reason);

        WP_CLI::success(sprintf('Kullanici #%d: %d XP eklendi. (%d -> %d)', $user_id, $amount, $old_xp, $new_xp));
    }

    /**
     * @subcommand get
     */
    public function xp_get($args, $assoc_args) {
        $user_id = intval($args[0]);

        if (!get_userdata($user_id)) {
            WP_CLI::error(sprintf('Kullanici #%d bulunamadi.', $user_id));
            return;
        }

        if (!function_exists('gorilla_xp_get_balance')) {
            WP_CLI::error('gorilla_xp_get_balance fonksiyonu bulunamadi.');
            return;
        }

        $xp = gorilla_xp_get_balance($user_id);
        WP_CLI::log(sprintf('Kullanici #%d XP: %d', $user_id, $xp));
    }

    /**
     * @subcommand export
     */
    public function xp_export($args, $assoc_args) {
        global $wpdb;

        $format  = WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');
        $limit   = intval(WP_CLI\Utils\get_flag_value($assoc_args, 'limit', 100));
        $user_id = WP_CLI\Utils\get_flag_value($assoc_args, 'user_id', 0);

        $table = $wpdb->prefix . 'gamify_xp_transactions';
        $table_check = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$table_check) {
            WP_CLI::error('gamify_xp_transactions tablosu bulunamadi. WP Gamify aktif mi?');
            return;
        }

        $where = '';
        $params = array();
        if ($user_id) {
            $where = ' WHERE user_id = %d';
            $params[] = intval($user_id);
        }

        $sql = "SELECT * FROM {$table}{$where} ORDER BY created_at DESC LIMIT %d";
        $params[] = max(1, min(10000, $limit));

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        if (empty($rows)) {
            WP_CLI::warning('Kayit bulunamadi.');
            return;
        }

        WP_CLI\Utils\format_items($format, $rows, array_keys($rows[0]));
    }

    /**
     * @subcommand list
     */
    public function tier_list($args, $assoc_args) {
        $format     = WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');
        $filter_tier = WP_CLI\Utils\get_flag_value($assoc_args, 'tier', '');
        $limit      = intval(WP_CLI\Utils\get_flag_value($assoc_args, 'limit', 50));

        $user_args = array(
            'fields'  => 'ID',
            'orderby' => 'ID',
            'order'   => 'ASC',
            'number'  => max(1, min(1000, $limit)),
        );

        if ($filter_tier) {
            $user_args['meta_key']   = '_gorilla_last_tier';
            $user_args['meta_value'] = sanitize_text_field($filter_tier);
        }

        $user_ids = get_users($user_args);

        if (empty($user_ids)) {
            WP_CLI::warning('Kullanici bulunamadi.');
            return;
        }

        $rows = array();
        foreach ($user_ids as $uid) {
            $user = get_userdata($uid);
            $tier = get_user_meta($uid, '_gorilla_last_tier', true) ?: 'none';
            $xp   = function_exists('gorilla_xp_get_balance') ? gorilla_xp_get_balance($uid) : 0;
            $credit = function_exists('gorilla_credit_get_balance') ? gorilla_credit_get_balance($uid) : 0;

            $rows[] = array(
                'user_id'  => $uid,
                'email'    => $user ? $user->user_email : '-',
                'tier'     => $tier,
                'xp'       => $xp,
                'credit'   => number_format($credit, 2),
            );
        }

        WP_CLI\Utils\format_items($format, $rows, array('user_id', 'email', 'tier', 'xp', 'credit'));
    }
}

if (class_exists('WP_CLI')) {
    $cli = new Gorilla_LG_CLI();

    WP_CLI::add_command('gorilla-lg tier recalculate-all', array($cli, 'tier_recalculate_all'));
    WP_CLI::add_command('gorilla-lg tier list', array($cli, 'tier_list'));

    WP_CLI::add_command('gorilla-lg xp add', array($cli, 'xp_add'));
    WP_CLI::add_command('gorilla-lg xp get', array($cli, 'xp_get'));
    WP_CLI::add_command('gorilla-lg xp export', array($cli, 'xp_export'));
}
