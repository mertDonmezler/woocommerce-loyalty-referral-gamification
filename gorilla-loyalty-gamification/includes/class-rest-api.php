<?php
/**
 * Gorilla Loyalty & Gamification - REST API Endpoints
 *
 * Loyalty/gamification REST API: tier, XP, badges, leaderboard, milestones,
 * points shop, streak, QR, social share.
 *
 * @package Gorilla_Loyalty_Gamification
 * @since   3.1.0
 */

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', 'gorilla_lg_register_rest_routes');

function gorilla_lg_register_rest_routes() {
    $namespace = 'gorilla-lg/v1';

    // GET /me - Loyalty user summary
    register_rest_route($namespace, '/me', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_lg_rest_get_me',
        'permission_callback' => 'gorilla_lg_rest_check_auth_self',
    ));

    // GET /tier - User tier info
    register_rest_route($namespace, '/tier', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_lg_rest_get_tier',
        'permission_callback' => 'gorilla_lg_rest_check_auth_self',
    ));

    // GET /tiers - All tiers (public)
    register_rest_route($namespace, '/tiers', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_lg_rest_get_all_tiers',
        'permission_callback' => '__return_true',
    ));

    // GET /badges
    register_rest_route($namespace, '/badges', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_lg_rest_get_badges',
        'permission_callback' => 'gorilla_lg_rest_check_auth_self',
    ));

    // GET /leaderboard
    register_rest_route($namespace, '/leaderboard', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_lg_rest_get_leaderboard',
        'permission_callback' => 'gorilla_lg_rest_check_auth',
        'args'                => array(
            'period' => array(
                'default'           => 'monthly',
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => function($param) {
                    return in_array($param, array('monthly', 'alltime', 'weekly'), true);
                },
            ),
            'limit' => array(
                'default'           => 10,
                'sanitize_callback' => 'absint',
                'validate_callback' => function($param) {
                    return is_numeric($param) && (int)$param > 0 && (int)$param <= 50;
                },
            ),
        ),
    ));

    // GET /milestones
    register_rest_route($namespace, '/milestones', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_lg_rest_get_milestones',
        'permission_callback' => 'gorilla_lg_rest_check_auth_self',
    ));

    // GET /shop
    register_rest_route($namespace, '/shop', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_lg_rest_get_shop',
        'permission_callback' => 'gorilla_lg_rest_check_auth_self',
    ));

    // POST /shop/redeem
    register_rest_route($namespace, '/shop/redeem', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'gorilla_lg_rest_shop_redeem',
        'permission_callback' => 'gorilla_lg_rest_check_auth_self',
        'args'                => array(
            'reward_id' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => function($value) {
                    if (empty($value)) return false;
                    if (!function_exists('gorilla_shop_get_rewards')) return true;
                    $rewards = gorilla_shop_get_rewards();
                    return isset($rewards[sanitize_key($value)]);
                },
            ),
        ),
    ));

    // GET /streak
    register_rest_route($namespace, '/streak', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_lg_rest_get_streak',
        'permission_callback' => 'gorilla_lg_rest_check_auth_self',
    ));

    // GET /qr
    register_rest_route($namespace, '/qr', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_lg_rest_get_qr',
        'permission_callback' => 'gorilla_lg_rest_check_auth_self',
    ));

    // POST /social/share
    register_rest_route($namespace, '/social/share', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'gorilla_lg_rest_social_share',
        'permission_callback' => 'gorilla_lg_rest_check_auth_self',
        'args' => array(
            'platform' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => function($param) {
                    return in_array($param, array('facebook', 'twitter', 'whatsapp', 'instagram', 'tiktok'), true);
                },
            ),
        ),
    ));

    // GET /settings - Loyalty settings (public feature flags, no sensitive data)
    register_rest_route($namespace, '/settings', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_lg_rest_get_settings',
        'permission_callback' => '__return_true',
    ));

    // Admin endpoints
    register_rest_route($namespace, '/admin/stats', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_lg_rest_admin_stats',
        'permission_callback' => function() {
            return current_user_can('manage_woocommerce');
        },
    ));

    register_rest_route($namespace, '/admin/user/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_lg_rest_admin_get_user',
        'permission_callback' => function() {
            return current_user_can('manage_woocommerce');
        },
        'args' => array(
            'id' => array('required' => true, 'sanitize_callback' => 'absint'),
        ),
    ));

    // Credit endpoints (backward compat namespace)
    $lr_namespace = 'gorilla-lr/v1';
    if (!gorilla_lg_route_exists($lr_namespace, '/credit')) {
        register_rest_route($lr_namespace, '/credit', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gorilla_lg_rest_get_credit',
            'permission_callback' => 'gorilla_lg_rest_check_auth_self',
        ));
    }
    if (!gorilla_lg_route_exists($lr_namespace, '/credit/log')) {
        register_rest_route($lr_namespace, '/credit/log', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gorilla_lg_rest_get_credit_log',
            'permission_callback' => 'gorilla_lg_rest_check_auth_self',
            'args' => array(
                'limit' => array('default' => 20, 'sanitize_callback' => 'absint', 'validate_callback' => function($param) { return is_numeric($param) && $param > 0 && $param <= 100; }),
            ),
        ));
    }
}

/**
 * Permission callback: any authenticated user (used for shared/social endpoints like leaderboard).
 */
function gorilla_lg_rest_check_auth() {
    return is_user_logged_in();
}

/**
 * Permission callback: authenticated user acting on their own data, or a WooCommerce admin.
 * Accepts an optional 'user_id' parameter for admin cross-user lookups.
 *
 * @param WP_REST_Request $request
 * @return bool
 */
function gorilla_lg_rest_check_auth_self( WP_REST_Request $request ) {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    $requested_user_id = $request->get_param('user_id');
    if ( null !== $requested_user_id ) {
        // If a user_id param is supplied, it must match the current user or the caller must be admin.
        return (int) $requested_user_id === get_current_user_id()
            || current_user_can( 'manage_woocommerce' );
    }

    // No user_id param: endpoint operates on current user, which is always safe.
    return true;
}

// ── /me ─────────────────────────────────────────────────
function gorilla_lg_rest_get_me(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error('user_not_found', 'Kullanici bulunamadi.', array('status' => 404));
    }

    $tier = function_exists('gorilla_loyalty_calculate_tier') ? gorilla_loyalty_calculate_tier($user_id) : null;
    $next = function_exists('gorilla_loyalty_next_tier') ? gorilla_loyalty_next_tier($user_id) : null;

    $xp_info = null;
    if (function_exists('gorilla_xp_calculate_level') && defined('WPGAMIFY_VERSION')) {
        $xp_info = array(
            'balance' => function_exists('gorilla_xp_get_balance') ? gorilla_xp_get_balance($user_id) : 0,
            'level'   => gorilla_xp_calculate_level($user_id),
        );
    }

    return new WP_REST_Response(array(
        'user_id'         => $user_id,
        'display_name'    => $user->display_name,
        'tier'            => $tier,
        'next_tier'       => $next,
        'xp'              => $xp_info,
        'badges'          => (function_exists('gorilla_badge_get_user_badges') && get_option('gorilla_lr_badges_enabled') === 'yes') ? gorilla_badge_get_user_badges($user_id) : null,
        'streak'          => (class_exists('WPGamify_Settings') && WPGamify_Settings::get('streak_enabled', true) && class_exists('WPGamify_Streak_Manager')) ? intval(WPGamify_Streak_Manager::get_streak($user_id)['current_streak'] ?? 0) : null,
        'spins_available' => (get_option('gorilla_lr_spin_enabled') === 'yes') ? intval(get_user_meta($user_id, '_gorilla_spin_available', true)) : null,
        'programs'        => array(
            'loyalty_enabled' => get_option('gorilla_lr_enabled_loyalty') === 'yes',
            'xp_enabled'      => defined('WPGAMIFY_VERSION'),
        ),
    ), 200);
}

// ── /tier ───────────────────────────────────────────────
function gorilla_lg_rest_get_tier(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    if (!function_exists('gorilla_loyalty_calculate_tier')) {
        return new WP_Error('not_available', 'Sadakat programi kullanilamiyor.', array('status' => 503));
    }

    $tier = gorilla_loyalty_calculate_tier($user_id);
    $next = function_exists('gorilla_loyalty_next_tier') ? gorilla_loyalty_next_tier($user_id) : null;
    $period = get_option('gorilla_lr_period_months', 6);

    return new WP_REST_Response(array(
        'current' => array(
            'key'          => $tier['key'] ?? 'none',
            'label'        => $tier['label'] ?? 'Uye',
            'emoji'        => $tier['emoji'] ?? '',
            'color'        => $tier['color'] ?? '#999',
            'discount'     => intval($tier['discount'] ?? 0),
            'installment'  => intval($tier['installment'] ?? 0),
            'free_shipping'=> !empty($tier['free_shipping']),
        ),
        'spending' => array(
            'amount'        => floatval($tier['spending'] ?? 0),
            'formatted'     => function_exists('wc_price') ? strip_tags(wc_price($tier['spending'] ?? 0)) : ($tier['spending'] ?? 0),
            'period_months' => intval($period),
        ),
        'next_tier' => $next ? array(
            'key'       => $next['key'] ?? '',
            'label'     => $next['label'] ?? '',
            'emoji'     => $next['emoji'] ?? '',
            'remaining' => floatval($next['remaining'] ?? 0),
            'progress'  => floatval($next['progress'] ?? 0),
        ) : null,
    ), 200);
}

// ── /tiers (public) ─────────────────────────────────────
function gorilla_lg_rest_get_all_tiers(WP_REST_Request $request) {
    if (!function_exists('gorilla_get_tiers')) {
        return new WP_Error('not_available', 'Sadakat programi kullanilamiyor.', array('status' => 503));
    }

    $tiers = gorilla_get_tiers();
    $formatted = array();
    foreach ($tiers as $key => $tier) {
        $formatted[] = array(
            'key'          => $key,
            'label'        => $tier['label'] ?? '',
            'emoji'        => $tier['emoji'] ?? '',
            'color'        => $tier['color'] ?? '#999',
            'min_spending' => floatval($tier['min'] ?? 0),
            'discount'     => intval($tier['discount'] ?? 0),
            'installment'  => intval($tier['installment'] ?? 0),
            'free_shipping'=> !empty($tier['free_shipping']),
        );
    }

    return new WP_REST_Response(array(
        'tiers'         => $formatted,
        'period_months' => intval(get_option('gorilla_lr_period_months', 6)),
    ), 200);
}

// ── /badges ─────────────────────────────────────────────
function gorilla_lg_rest_get_badges(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    if (!function_exists('gorilla_badge_get_user_badges')) {
        return new WP_Error('not_available', 'Rozet sistemi kullanilamiyor.', array('status' => 503));
    }
    return new WP_REST_Response(array(
        'badges'  => gorilla_badge_get_user_badges($user_id),
        'enabled' => get_option('gorilla_lr_badges_enabled') === 'yes',
    ), 200);
}

// ── /leaderboard ────────────────────────────────────────
function gorilla_lg_rest_get_leaderboard(WP_REST_Request $request) {
    if (!function_exists('gorilla_xp_get_leaderboard')) {
        return new WP_Error('not_available', 'Leaderboard kullanilamiyor.', array('status' => 503));
    }

    $period = $request->get_param('period') ?: 'monthly';
    $limit  = absint($request->get_param('limit') ?: 10);

    $raw_entries = gorilla_xp_get_leaderboard($period, $limit);
    if (!is_array($raw_entries)) {
        $raw_entries = array();
    }

    // C7 fix: strip user_id — return only safe, public-facing fields.
    $safe_entries = array();
    foreach ($raw_entries as $index => $entry) {
        $safe_entries[] = array(
            'rank'         => $index + 1,
            'display_name' => isset($entry['display_name']) ? sanitize_text_field($entry['display_name']) : __('Anonim', 'gorilla-loyalty'),
            'avatar_url'   => isset($entry['user_id']) ? get_avatar_url((int) $entry['user_id'], array('size' => 48)) : '',
            'xp'           => isset($entry['xp']) ? (int) $entry['xp'] : 0,
        );
        // user_id is intentionally excluded from the response.
    }

    return new WP_REST_Response(array(
        'leaderboard' => $safe_entries,
        'enabled'     => get_option('gorilla_lr_leaderboard_enabled') === 'yes',
    ), 200);
}

// ── /milestones ─────────────────────────────────────────
function gorilla_lg_rest_get_milestones(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $milestones = get_option('gorilla_lr_milestones', array());
    $completed = get_user_meta($user_id, '_gorilla_milestones', true);
    if (!is_array($completed)) $completed = array();

    $result = array();
    foreach ($milestones as $m) {
        $mid = $m['id'] ?? '';
        $progress = function_exists('gorilla_milestone_get_progress') ? gorilla_milestone_get_progress($user_id, $m) : 0;
        $result[] = array_merge($m, array('progress' => $progress, 'completed' => in_array($mid, $completed)));
    }
    return new WP_REST_Response(array(
        'milestones' => $result,
        'enabled'    => get_option('gorilla_lr_milestones_enabled') === 'yes',
    ), 200);
}

// ── /shop ───────────────────────────────────────────────
function gorilla_lg_rest_get_shop(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $rewards = function_exists('gorilla_shop_get_rewards') ? gorilla_shop_get_rewards() : array();
    $xp = function_exists('gorilla_xp_get_balance') ? gorilla_xp_get_balance($user_id) : 0;
    return new WP_REST_Response(array(
        'rewards'    => $rewards,
        'xp_balance' => $xp,
        'enabled'    => get_option('gorilla_lr_points_shop_enabled') === 'yes',
    ), 200);
}

// ── /shop/redeem ────────────────────────────────────────
function gorilla_lg_rest_shop_redeem(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $rate_key = 'gorilla_shop_rate_' . $user_id;
    if (get_transient($rate_key)) {
        return new WP_Error('rate_limited', 'Cok sik istek. Lutfen bekleyin.', array('status' => 429));
    }
    set_transient($rate_key, true, 5);

    $reward_id = $request->get_param('reward_id');
    if (!function_exists('gorilla_shop_redeem')) {
        return new WP_Error('not_available', 'Puan dukkani kullanilamiyor.', array('status' => 503));
    }
    $result = gorilla_shop_redeem($user_id, $reward_id);
    if ($result['success']) {
        return new WP_REST_Response($result, 200);
    }
    return new WP_Error('redeem_failed', $result['error'] ?? 'Bilinmeyen hata', array('status' => 400));
}

// ── /streak ─────────────────────────────────────────────
function gorilla_lg_rest_get_streak(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $streak_data = class_exists('WPGamify_Streak_Manager') ? WPGamify_Streak_Manager::get_streak($user_id) : array();
    return new WP_REST_Response(array(
        'current_streak' => intval($streak_data['current_streak'] ?? 0),
        'best_streak'    => intval($streak_data['max_streak'] ?? 0),
        'last_login'     => $streak_data['last_activity_date'] ?? null,
        'enabled'        => class_exists('WPGamify_Settings') && (bool) WPGamify_Settings::get('streak_enabled', true),
    ), 200);
}

// ── /qr ─────────────────────────────────────────────────
function gorilla_lg_rest_get_qr(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    if (!function_exists('gorilla_qr_get_url')) {
        return new WP_Error('not_available', 'QR sistemi kullanilamiyor.', array('status' => 503));
    }
    return new WP_REST_Response(array(
        'qr_url'  => gorilla_qr_get_url($user_id),
        'enabled' => get_option('gorilla_lr_qr_enabled') === 'yes',
    ), 200);
}

// ── /social/share ───────────────────────────────────────
function gorilla_lg_rest_social_share(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $rate_key = 'gorilla_share_rate_' . $user_id;
    if (get_transient($rate_key)) {
        return new WP_Error('rate_limited', 'Cok sik istek. Lutfen bekleyin.', array('status' => 429));
    }
    set_transient($rate_key, true, 3);

    $platform = $request->get_param('platform');
    if (!function_exists('gorilla_social_track_share')) {
        return new WP_Error('not_available', 'Sosyal paylasim sistemi kullanilamiyor.', array('status' => 503));
    }
    $result = gorilla_social_track_share($user_id, $platform);
    return new WP_REST_Response(array('awarded' => $result), 200);
}

// ── /settings ───────────────────────────────────────────
// Medium fix: removed 'version' field — plugin version must not be exposed publicly
// as it aids fingerprinting and targeted exploit research.
function gorilla_lg_rest_get_settings(WP_REST_Request $request) {
    return new WP_REST_Response(array(
        'loyalty_enabled'    => get_option('gorilla_lr_enabled_loyalty') === 'yes',
        'xp_enabled'         => defined('WPGAMIFY_VERSION'),
        'badges_enabled'     => get_option('gorilla_lr_badges_enabled') === 'yes',
        'leaderboard_enabled'=> get_option('gorilla_lr_leaderboard_enabled') === 'yes',
        'spin_enabled'       => get_option('gorilla_lr_spin_enabled') === 'yes',
        'streak_enabled'     => class_exists('WPGamify_Settings') && (bool) WPGamify_Settings::get('streak_enabled', true),
        'period_months'      => intval(get_option('gorilla_lr_period_months', 6)),
    ), 200);
}

// ── /admin/stats ────────────────────────────────────────
function gorilla_lg_rest_admin_stats(WP_REST_Request $request) {
    global $wpdb;

    $xp_table = $wpdb->prefix . 'gamify_xp_transactions';
    $total_xp_given = intval($wpdb->get_var(
        "SELECT COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) FROM `{$xp_table}`"
    ));

    $total_users = intval($wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->users}`"));

    return new WP_REST_Response(array(
        'xp' => array(
            'total_given' => $total_xp_given,
        ),
        'users' => array(
            'total' => $total_users,
        ),
    ), 200);
}

// ── /admin/user/{id} ────────────────────────────────────
function gorilla_lg_rest_admin_get_user(WP_REST_Request $request) {
    $user_id = absint($request->get_param('id'));
    if (!$user_id || !get_userdata($user_id)) {
        return new WP_Error('invalid_user', 'Kullanici bulunamadi', array('status' => 404));
    }

    $rate_key = 'gorilla_admin_rate_' . get_current_user_id();
    $count = intval(get_transient($rate_key));
    if ($count >= 100) {
        return new WP_Error('rate_limited', 'Cok fazla istek', array('status' => 429));
    }
    set_transient($rate_key, $count + 1, MINUTE_IN_SECONDS);

    $user = get_userdata($user_id);
    $tier = function_exists('gorilla_loyalty_calculate_tier') ? gorilla_loyalty_calculate_tier($user_id) : null;
    $xp = function_exists('gorilla_xp_get_balance') ? gorilla_xp_get_balance($user_id) : 0;
    $level = function_exists('gorilla_xp_calculate_level') ? gorilla_xp_calculate_level($user_id) : null;

    $email = $user->user_email;
    $at_pos = strpos($email, '@');
    if ($at_pos !== false && $at_pos > 0) {
        $local = substr($email, 0, min(3, $at_pos));
        $domain = substr($email, $at_pos + 1);
        $masked_email = $local . '***@' . $domain;
    } else {
        $masked_email = '***';
    }

    return new WP_REST_Response(array(
        'user' => array(
            'id'           => $user_id,
            'display_name' => $user->display_name,
            'email'        => $masked_email,
            'registered'   => $user->user_registered,
        ),
        'tier'   => $tier,
        'xp'     => $xp,
        'level'  => $level,
        'streak' => class_exists('WPGamify_Streak_Manager') ? intval(WPGamify_Streak_Manager::get_streak($user_id)['current_streak'] ?? 0) : 0,
        'badges' => function_exists('gorilla_badge_get_user_badges') ? gorilla_badge_get_user_badges($user_id) : array(),
    ), 200);
}

// ── Route existence check ───────────────────────────────
function gorilla_lg_route_exists($namespace, $route) {
    $server = rest_get_server();
    $routes = $server->get_routes();
    return isset($routes['/' . $namespace . $route]);
}

// ── /credit ─────────────────────────────────────────────
function gorilla_lg_rest_get_credit(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    if (!function_exists('gorilla_credit_get_balance')) {
        return new WP_Error('not_available', 'Store credit sistemi kullanilamiyor.', array('status' => 503));
    }
    $balance = gorilla_credit_get_balance($user_id);
    $min_order = floatval(get_option('gorilla_lr_credit_min_order', 0));
    return new WP_REST_Response(array(
        'balance'             => floatval($balance),
        'formatted'           => function_exists('wc_price') ? strip_tags(wc_price($balance)) : $balance,
        'min_order'           => $min_order,
        'min_order_formatted' => function_exists('wc_price') ? strip_tags(wc_price($min_order)) : $min_order,
        'can_use'             => $balance > 0,
    ), 200);
}

// ── /credit/log ─────────────────────────────────────────
function gorilla_lg_rest_get_credit_log(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $limit = $request->get_param('limit');
    if (!function_exists('gorilla_credit_get_log')) {
        return new WP_Error('not_available', 'Credit log kullanilamiyor.', array('status' => 503));
    }
    $log = gorilla_credit_get_log($user_id, $limit);
    $formatted = array();
    foreach ($log as $entry) {
        $formatted[] = array(
            'id'            => intval($entry['id'] ?? 0),
            'amount'        => floatval($entry['amount'] ?? 0),
            'balance_after' => floatval($entry['balance_after'] ?? 0),
            'type'          => $entry['type'] ?? 'unknown',
            'reason'        => $entry['reason'] ?? '',
            'reference_id'  => intval($entry['reference_id'] ?? 0),
            'created_at'    => $entry['created_at'] ?? '',
            'expires_at'    => $entry['expires_at'] ?? null,
        );
    }
    return new WP_REST_Response(array('entries' => $formatted, 'count' => count($formatted)), 200);
}
