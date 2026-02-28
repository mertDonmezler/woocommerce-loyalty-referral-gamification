<?php
/**
 * Gorilla LG - XP Bridge Layer
 *
 * Backward-compatible wrapper fonksiyonlar.
 * Tum XP islemleri WP Gamify motoruna yonlendirilir.
 *
 * @package Gorilla_Loyalty_Gamification
 * @version 2.0.0
 * @deprecated 2.0.0 Tum XP islemleri WP Gamify uzerinden yapilmalidir.
 */

if (!defined('ABSPATH')) exit;

// ==============================================================
// XP BRIDGE FUNCTIONS (WP Gamify wrapper)
// ==============================================================

/**
 * @deprecated 2.0.0 Use WPGamify_XP_Engine::award() instead.
 */
function gorilla_xp_add($user_id, $amount, $reason = '', $reference_type = null, $reference_id = null) {
    if (!$user_id || $amount <= 0) return gorilla_xp_get_balance($user_id);
    if (!class_exists('WPGamify_XP_Engine')) return gorilla_xp_get_balance($user_id);

    $result = WPGamify_XP_Engine::award(
        (int) $user_id,
        (int) $amount,
        $reference_type ? sanitize_key($reference_type) : 'manual',
        $reference_id ? (string) $reference_id : '',
        sanitize_text_field($reason)
    );

    if ($result === false) {
        return gorilla_xp_get_balance($user_id);
    }

    return gorilla_xp_get_balance($user_id);
}

/**
 * @deprecated 2.0.0 Use WPGamify_XP_Engine::deduct() instead.
 */
function gorilla_xp_deduct($user_id, $amount, $reason = '', $reference_type = null, $reference_id = null) {
    if (!$user_id || $amount <= 0) return false;
    if (!class_exists('WPGamify_XP_Engine')) return false;

    $result = WPGamify_XP_Engine::deduct(
        (int) $user_id,
        (int) $amount,
        $reference_type ? sanitize_key($reference_type) : 'manual',
        $reference_id ? (string) $reference_id : '',
        sanitize_text_field($reason)
    );

    return $result !== false ? gorilla_xp_get_balance($user_id) : false;
}

/**
 * @deprecated 2.0.0 Use WPGamify_XP_Engine::get_total_xp() instead.
 */
function gorilla_xp_get_balance($user_id) {
    if (!$user_id) return 0;
    if (!class_exists('WPGamify_XP_Engine')) return 0;
    return WPGamify_XP_Engine::get_total_xp((int) $user_id);
}

/**
 * @deprecated 2.0.0 Use WPGamify_XP_Engine::get_user_level_info() instead.
 */
function gorilla_xp_calculate_level($user_id) {
    if (!class_exists('WPGamify_XP_Engine')) {
        return array('key' => 'level_1', 'number' => 1, 'label' => '', 'emoji' => '', 'color' => '#999', 'min_xp' => 0, 'xp' => 0);
    }
    $info = WPGamify_XP_Engine::get_user_level_info((int) $user_id);
    return array(
        'key'    => 'level_' . $info['level'],
        'number' => $info['level'],
        'label'  => $info['name'],
        'emoji'  => gorilla_xp_level_emoji($info['level']),
        'color'  => $info['color'] ?? '#6366f1',
        'min_xp' => 0,
        'xp'     => $info['total_xp'],
    );
}

function gorilla_xp_level_emoji($level_number) {
    $emojis = array(1 => "\xF0\x9F\x8C\xB1", 2 => "\xF0\x9F\x8C\xBF", 3 => "\xE2\xAD\x90", 4 => "\xF0\x9F\x94\xA5", 5 => "\xF0\x9F\x92\x8E", 6 => "\xF0\x9F\x91\x91", 7 => "\xF0\x9F\x8F\x86", 8 => "\xF0\x9F\x8C\x9F");
    return $emojis[$level_number] ?? "\xE2\xAD\x90";
}

/**
 * Internal helper - same as gorilla_xp_calculate_level but takes raw XP value.
 * @deprecated 2.0.0
 */
function gorilla_xp_calculate_level_from_xp($xp) {
    if (!class_exists('WPGamify_Level_Manager')) {
        return array('key' => 'level_1', 'number' => 1, 'label' => '', 'emoji' => '', 'color' => '#999', 'min_xp' => 0);
    }
    $level_num = WPGamify_Level_Manager::calculate_level((int) $xp);
    $config = WPGamify_Level_Manager::get_level($level_num);
    return array(
        'key'    => 'level_' . $level_num,
        'number' => $level_num,
        'label'  => $config['name'] ?? 'Level ' . $level_num,
        'emoji'  => gorilla_xp_level_emoji($level_num),
        'color'  => $config['color_hex'] ?? '#6366f1',
        'min_xp' => (int) ($config['xp_required'] ?? 0),
    );
}

/**
 * @deprecated 2.0.0 Use WPGamify_Level_Manager::get_progress() instead.
 */
function gorilla_xp_get_next_level($user_id) {
    if (!class_exists('WPGamify_Level_Manager')) return null;
    $progress = WPGamify_Level_Manager::get_progress((int) $user_id);
    if (!$progress['next_level']) return null;
    return array(
        'key'       => 'level_' . $progress['next_level']['level_number'],
        'number'    => (int) $progress['next_level']['level_number'],
        'label'     => $progress['next_level']['name'] ?? '',
        'emoji'     => gorilla_xp_level_emoji((int) $progress['next_level']['level_number']),
        'color'     => $progress['next_level']['color_hex'] ?? '#999',
        'min_xp'    => (int) $progress['next_level']['xp_required'],
        'remaining' => $progress['xp_needed'],
        'progress'  => $progress['progress_pct'],
    );
}

/**
 * @deprecated 2.0.0 Use WPGamify_XP_Engine::get_history() instead.
 */
function gorilla_xp_get_log($user_id, $limit = 20) {
    if (!class_exists('WPGamify_XP_Engine')) return array();
    $result = WPGamify_XP_Engine::get_history((int) $user_id, 1, $limit);
    return array_map(function($item) {
        return (object) array(
            'id'             => $item['id'],
            'user_id'        => 0,
            'amount'         => $item['amount'],
            'balance_after'  => 0,
            'reason'         => $item['note'] ?? '',
            'reference_type' => $item['source'] ?? '',
            'reference_id'   => $item['source_id'] ?? '',
            'created_at'     => $item['created_at'] ?? '',
        );
    }, $result['items'] ?? []);
}

/**
 * @deprecated 2.0.0 Use WPGamify_Level_Manager::get_all_levels() instead.
 */
function gorilla_xp_get_levels($force_refresh = false) {
    if (!class_exists('WPGamify_Level_Manager')) return array();
    $levels = WPGamify_Level_Manager::get_all_levels();
    $result = array();
    foreach ($levels as $l) {
        $result['level_' . $l['level_number']] = array(
            'label'  => $l['name'],
            'min_xp' => (int) $l['xp_required'],
            'emoji'  => gorilla_xp_level_emoji((int) $l['level_number']),
            'color'  => $l['color_hex'] ?? '#6366f1',
        );
    }
    return $result;
}

/**
 * @deprecated 2.0.0 Use WPGamify_Campaign_Manager::get_active_multiplier() instead.
 */
function gorilla_xp_get_bonus_multiplier() {
    if (!class_exists('WPGamify_Campaign_Manager')) return 1.0;
    return WPGamify_Campaign_Manager::get_active_multiplier();
}

/**
 * Check if XP has already been awarded for a specific reference.
 * Queries the WP Gamify transactions table.
 */
function gorilla_xp_has_been_awarded($user_id, $reference_type, $reference_id) {
    if (!class_exists('WPGamify_XP_Engine')) return false;
    global $wpdb;
    $table = $wpdb->prefix . 'gamify_xp_transactions';
    return (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE user_id = %d AND source = %s AND source_id = %s LIMIT 1",
        (int) $user_id, sanitize_key($reference_type), (string) $reference_id
    ));
}

/**
 * @deprecated 2.0.0 Use WPGamify_XP_Engine::get_history() to get admin stats.
 */
function gorilla_xp_get_admin_stats() {
    if (!class_exists('WPGamify_XP_Engine')) {
        return array('total_xp' => 0, 'avg_xp' => 0, 'users_with_xp' => 0, 'level_distribution' => array());
    }
    global $wpdb;
    $txn_table = $wpdb->prefix . 'gamify_xp_transactions';
    $level_table = $wpdb->prefix . 'gamify_user_levels';

    $stats = array('total_xp' => 0, 'avg_xp' => 0, 'users_with_xp' => 0, 'level_distribution' => array());
    $stats['total_xp'] = (int) $wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM {$txn_table} WHERE amount > 0");
    $stats['users_with_xp'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$level_table} WHERE total_xp > 0");
    if ($stats['users_with_xp'] > 0) {
        $stats['avg_xp'] = (int) round((float) $wpdb->get_var("SELECT AVG(total_xp) FROM {$level_table} WHERE total_xp > 0"));
    }
    return $stats;
}

/**
 * @deprecated 2.0.0
 */
function gorilla_xp_get_recent_activity($limit = 10) {
    if (!class_exists('WPGamify_XP_Engine')) return array();
    global $wpdb;
    $table = $wpdb->prefix . 'gamify_xp_transactions';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT t.*, u.display_name FROM {$table} t LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID ORDER BY t.created_at DESC LIMIT %d",
        $limit
    ));
}

/**
 * Milestone check - delegates to existing function.
 * Kept for backward compat with hooks that call this.
 */
function gorilla_xp_check_milestones($user_id) {
    if (get_option('gorilla_lr_milestones_enabled', 'no') !== 'yes') return;
    if (!$user_id) return;
    $milestones = get_option('gorilla_lr_milestones', array());
    if (empty($milestones)) return;
    $completed = get_user_meta($user_id, '_gorilla_milestones', true);
    if (!is_array($completed)) $completed = array();

    foreach ($milestones as $m) {
        $mid = $m['id'] ?? '';
        if (!$mid || in_array($mid, $completed)) continue;
        $progress = gorilla_milestone_get_progress($user_id, $m);
        if ($progress < 100) continue;

        global $wpdb;
        $guard_key = '_gorilla_milestone_done_' . sanitize_key($mid);
        $m_marked = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) SELECT %d, %s, '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s)",
            $user_id, $guard_key, $user_id, $guard_key
        ));
        if (!$m_marked) continue;

        // Re-read to avoid lost-update from concurrent completions
        $completed = get_user_meta($user_id, '_gorilla_milestones', true);
        if (!is_array($completed)) $completed = array();
        if (!in_array($mid, $completed)) {
            $completed[] = $mid;
            update_user_meta($user_id, '_gorilla_milestones', $completed);
        }
        $xp_reward = intval($m['xp_reward'] ?? 0);
        if ($xp_reward > 0) gorilla_xp_add($user_id, $xp_reward, sprintf('Hedef tamamlandi: %s', $m['label'] ?? ''), 'milestone', crc32($mid));
        $credit_reward = floatval($m['credit_reward'] ?? 0);
        if ($credit_reward > 0 && function_exists('gorilla_credit_adjust')) gorilla_credit_adjust($user_id, $credit_reward, 'milestone', sprintf('Hedef odulu: %s', $m['label'] ?? ''), 0, 0);
        if (function_exists('gorilla_email_milestone_reached')) gorilla_email_milestone_reached($user_id, $m);
        if (get_option('gorilla_lr_spin_enabled', 'no') === 'yes' && function_exists('gorilla_spin_grant')) gorilla_spin_grant($user_id, 'milestone');
    }
}

function gorilla_milestone_get_progress($user_id, $milestone) {
    $type = $milestone['type'] ?? '';
    $target = floatval($milestone['target'] ?? 1);
    if ($target <= 0) return 100;
    $current = 0;
    switch ($type) {
        case 'total_orders':
            $r = wc_get_orders(array('customer_id' => $user_id, 'status' => array('completed', 'processing'), 'limit' => intval($target) + 1, 'return' => 'ids'));
            $current = is_array($r) ? count($r) : 0;
            break;
        case 'total_spending':
            $current = function_exists('gorilla_loyalty_get_spending') ? gorilla_loyalty_get_spending($user_id) : 0;
            break;
        case 'total_reviews':
            global $wpdb;
            $current = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d AND comment_type = 'review' AND comment_approved = '1'", $user_id)));
            break;
        case 'total_referrals':
            $current = count(get_posts(array('post_type' => 'gorilla_referral', 'post_status' => 'grla_approved', 'meta_key' => '_ref_user_id', 'meta_value' => $user_id, 'numberposts' => intval($target) + 1, 'fields' => 'ids')) ?: array());
            break;
        case 'total_xp':
            $current = gorilla_xp_get_balance($user_id);
            break;
        case 'account_age':
            $user = get_userdata($user_id);
            if ($user) $current = intval((time() - strtotime($user->user_registered)) / DAY_IN_SECONDS);
            break;
    }
    return min(100, ($current / $target) * 100);
}

// Milestone check on order completion (kept - this is Gorilla's own trigger)
add_action('woocommerce_order_status_completed', function($order_id) {
    $order = wc_get_order($order_id);
    if ($order && $order->get_customer_id()) gorilla_xp_check_milestones($order->get_customer_id());
}, 25);
