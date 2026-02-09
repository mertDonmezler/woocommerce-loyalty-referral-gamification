<?php
/**
 * Gorilla LR - REST API Endpoints
 * v3.0.0 - Kullanıcı seviyesi, credit bakiye, referans bilgileri, affiliate, gamification
 *
 * @author Mert Dönmezler
 * @copyright 2025-2026 Mert Dönmezler
 */

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', 'gorilla_register_rest_routes');

function gorilla_register_rest_routes() {
    $namespace = 'gorilla-lr/v1';

    // GET /gorilla-lr/v1/me - Genel kullanıcı özeti
    register_rest_route($namespace, '/me', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_rest_get_me',
        'permission_callback' => 'gorilla_rest_check_auth',
    ));

    // GET /gorilla-lr/v1/tier - Kullanıcı seviye bilgisi
    register_rest_route($namespace, '/tier', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_rest_get_tier',
        'permission_callback' => 'gorilla_rest_check_auth',
    ));

    // GET /gorilla-lr/v1/credit - Store credit bakiyesi
    register_rest_route($namespace, '/credit', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_rest_get_credit',
        'permission_callback' => 'gorilla_rest_check_auth',
    ));

    // GET /gorilla-lr/v1/credit/log - Credit geçmişi
    register_rest_route($namespace, '/credit/log', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_rest_get_credit_log',
        'permission_callback' => 'gorilla_rest_check_auth',
        'args'                => array(
            'limit' => array(
                'default'           => 20,
                'sanitize_callback' => 'absint',
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0 && $param <= 100;
                },
            ),
        ),
    ));

    // GET /gorilla-lr/v1/referrals - Kullanıcının referans başvuruları
    register_rest_route($namespace, '/referrals', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_rest_get_referrals',
        'permission_callback' => 'gorilla_rest_check_auth',
    ));

    // GET /gorilla-lr/v1/tiers - Tüm seviyeleri listele (public)
    register_rest_route($namespace, '/tiers', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_rest_get_all_tiers',
        'permission_callback' => '__return_true',
    ));

    // GET /gorilla-lr/v1/settings - Genel ayarlar (authenticated)
    register_rest_route($namespace, '/settings', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_rest_get_settings',
        'permission_callback' => 'gorilla_rest_check_auth',
    ));

    // GET /gorilla-lr/v1/affiliate - Kullanıcı affiliate bilgileri
    register_rest_route($namespace, '/affiliate', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_rest_get_affiliate',
        'permission_callback' => 'gorilla_rest_check_auth',
    ));

    // GET /gorilla-lr/v1/affiliate/stats - Detaylı affiliate istatistikleri
    register_rest_route($namespace, '/affiliate/stats', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_rest_get_affiliate_stats',
        'permission_callback' => 'gorilla_rest_check_auth',
    ));

    // Admin endpoints (permission_callback ile yetki kontrolu yapilir)
    // GET /gorilla-lr/v1/admin/stats - Dashboard istatistikleri
    register_rest_route($namespace, '/admin/stats', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_rest_admin_stats',
        'permission_callback' => function() {
            return current_user_can('manage_woocommerce');
        },
    ));

    // GET /gorilla-lr/v1/admin/user/(?P<id>\d+) - Kullanıcı bilgisi (admin)
    register_rest_route($namespace, '/admin/user/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_rest_admin_get_user',
        'permission_callback' => function() {
            return current_user_can('manage_woocommerce');
        },
        'args'                => array(
            'id' => array(
                'required'          => true,
                'sanitize_callback' => 'absint',
            ),
        ),
    ));

    // GET /gorilla-lr/v1/badges
    register_rest_route($namespace, '/badges', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_rest_get_badges',
        'permission_callback' => 'gorilla_rest_check_auth',
    ));

    // GET /gorilla-lr/v1/leaderboard
    register_rest_route($namespace, '/leaderboard', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_rest_get_leaderboard',
        'permission_callback' => 'gorilla_rest_check_auth',
    ));

    // GET /gorilla-lr/v1/milestones
    register_rest_route($namespace, '/milestones', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_rest_get_milestones',
        'permission_callback' => 'gorilla_rest_check_auth',
    ));

    // GET /gorilla-lr/v1/shop
    register_rest_route($namespace, '/shop', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_rest_get_shop',
        'permission_callback' => 'gorilla_rest_check_auth',
    ));

    // POST /gorilla-lr/v1/shop/redeem
    register_rest_route($namespace, '/shop/redeem', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'gorilla_rest_shop_redeem',
        'permission_callback' => 'gorilla_rest_check_auth',
        'args'                => array(
            'reward_id' => array('required' => true, 'sanitize_callback' => 'sanitize_key'),
        ),
    ));

    // GET /gorilla-lr/v1/streak
    register_rest_route($namespace, '/streak', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_rest_get_streak',
        'permission_callback' => 'gorilla_rest_check_auth',
    ));

    // GET /gorilla-lr/v1/qr
    register_rest_route($namespace, '/qr', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_rest_get_qr',
        'permission_callback' => 'gorilla_rest_check_auth',
    ));

    // POST /gorilla-lr/v1/social/share
    register_rest_route($namespace, '/social/share', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'gorilla_rest_social_share',
        'permission_callback' => 'gorilla_rest_check_auth',
        'args' => array(
            'platform' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => function($param) {
                    return in_array($param, array('facebook', 'twitter', 'instagram', 'tiktok'), true);
                },
            ),
        ),
    ));
}

/**
 * Auth kontrolü - giriş yapmış kullanıcı gerekli
 */
function gorilla_rest_check_auth() {
    return is_user_logged_in();
}

/**
 * /me - Kullanıcı özeti
 */
function gorilla_rest_get_me(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);

    if (!$user) {
        return new WP_Error('user_not_found', __('Kullanıcı bulunamadı.', 'gorilla-lr'), array('status' => 404));
    }

    $tier = function_exists('gorilla_loyalty_calculate_tier') ? gorilla_loyalty_calculate_tier($user_id) : null;
    $credit = function_exists('gorilla_credit_get_balance') ? gorilla_credit_get_balance($user_id) : 0;
    $next = function_exists('gorilla_loyalty_next_tier') ? gorilla_loyalty_next_tier($user_id) : null;

    // Affiliate bilgisi
    $affiliate = null;
    if (function_exists('gorilla_affiliate_get_user_stats') && get_option('gorilla_lr_enabled_affiliate') === 'yes') {
        $aff_stats = gorilla_affiliate_get_user_stats($user_id);
        $affiliate = array(
            'code'     => $aff_stats['code'] ?? '',
            'link'     => $aff_stats['link'] ?? '',
            'earnings' => floatval($aff_stats['earnings'] ?? 0),
        );
    }

    // XP info
    $xp_info = null;
    if (function_exists('gorilla_xp_calculate_level') && get_option('gorilla_lr_enabled_xp') === 'yes') {
        $xp_info = array(
            'balance' => function_exists('gorilla_xp_get_balance') ? gorilla_xp_get_balance($user_id) : 0,
            'level'   => gorilla_xp_calculate_level($user_id),
        );
    }

    return rest_ensure_response(array(
        'user_id'        => $user_id,
        'display_name'   => $user->display_name,
        'tier'           => $tier,
        'next_tier'      => $next,
        'credit'         => array(
            'balance'   => floatval($credit),
            'formatted' => function_exists('wc_price') ? strip_tags(wc_price($credit)) : $credit,
        ),
        'affiliate'      => $affiliate,
        'xp'             => $xp_info,
        'badges'         => (function_exists('gorilla_badge_get_user_badges') && get_option('gorilla_lr_badges_enabled') === 'yes') ? gorilla_badge_get_user_badges($user_id) : null,
        'streak'         => (get_option('gorilla_lr_streak_enabled') === 'yes') ? intval(get_user_meta($user_id, '_gorilla_login_streak', true)) : null,
        'spins_available' => (get_option('gorilla_lr_spin_enabled') === 'yes') ? intval(get_user_meta($user_id, '_gorilla_spin_available', true)) : null,
        'programs'       => array(
            'loyalty_enabled'   => get_option('gorilla_lr_enabled_loyalty') === 'yes',
            'referral_enabled'  => get_option('gorilla_lr_enabled_referral') === 'yes',
            'affiliate_enabled' => get_option('gorilla_lr_enabled_affiliate') === 'yes',
        ),
    ));
}

/**
 * /tier - Seviye bilgisi
 */
function gorilla_rest_get_tier(WP_REST_Request $request) {
    $user_id = get_current_user_id();

    if (!function_exists('gorilla_loyalty_calculate_tier')) {
        return new WP_Error('not_available', __('Sadakat programı kullanılamıyor.', 'gorilla-lr'), array('status' => 503));
    }

    $tier = gorilla_loyalty_calculate_tier($user_id);
    $next = function_exists('gorilla_loyalty_next_tier') ? gorilla_loyalty_next_tier($user_id) : null;
    $period = get_option('gorilla_lr_period_months', 6);

    return rest_ensure_response(array(
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
            'amount'       => floatval($tier['spending'] ?? 0),
            'formatted'    => function_exists('wc_price') ? strip_tags(wc_price($tier['spending'] ?? 0)) : ($tier['spending'] ?? 0),
            'period_months'=> intval($period),
        ),
        'next_tier' => $next ? array(
            'key'       => $next['key'] ?? '',
            'label'     => $next['label'] ?? '',
            'emoji'     => $next['emoji'] ?? '',
            'remaining' => floatval($next['remaining'] ?? 0),
            'progress'  => floatval($next['progress'] ?? 0),
        ) : null,
    ));
}

/**
 * /credit - Bakiye bilgisi
 */
function gorilla_rest_get_credit(WP_REST_Request $request) {
    $user_id = get_current_user_id();

    if (!function_exists('gorilla_credit_get_balance')) {
        return new WP_Error('not_available', __('Store credit sistemi kullanılamıyor.', 'gorilla-lr'), array('status' => 503));
    }

    $balance = gorilla_credit_get_balance($user_id);
    $min_order = floatval(get_option('gorilla_lr_credit_min_order', 0));

    return rest_ensure_response(array(
        'balance'          => floatval($balance),
        'formatted'        => function_exists('wc_price') ? strip_tags(wc_price($balance)) : $balance,
        'min_order'        => $min_order,
        'min_order_formatted' => function_exists('wc_price') ? strip_tags(wc_price($min_order)) : $min_order,
        'can_use'          => $balance > 0,
    ));
}

/**
 * /credit/log - Credit geçmişi
 */
function gorilla_rest_get_credit_log(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $limit = $request->get_param('limit');

    if (!function_exists('gorilla_credit_get_log')) {
        return new WP_Error('not_available', __('Credit log kullanılamıyor.', 'gorilla-lr'), array('status' => 503));
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

    return rest_ensure_response(array(
        'entries' => $formatted,
        'count'   => count($formatted),
    ));
}

/**
 * /referrals - Kullanıcının referans başvuruları
 */
function gorilla_rest_get_referrals(WP_REST_Request $request) {
    $user_id = get_current_user_id();

    if (!function_exists('gorilla_referral_get_user_submissions')) {
        return new WP_Error('not_available', __('Referans sistemi kullanılamıyor.', 'gorilla-lr'), array('status' => 503));
    }

    $submissions = gorilla_referral_get_user_submissions($user_id);
    $rate = intval(get_option('gorilla_lr_referral_rate', 35));

    $formatted = array();
    $status_labels = array(
        'pending'       => __('Bekliyor', 'gorilla-lr'),
        'grla_approved' => __('Onaylandi', 'gorilla-lr'),
        'grla_rejected' => __('Reddedildi', 'gorilla-lr'),
    );

    foreach ($submissions as $sub) {
        $formatted[] = array(
            'id'           => intval($sub['id'] ?? 0),
            'order_id'     => intval($sub['order_id'] ?? 0),
            'order_total'  => floatval($sub['total'] ?? 0),
            'credit_amount'=> floatval($sub['credit'] ?? 0),
            'platform'     => $sub['platform'] ?? '',
            'video_url'    => $sub['video'] ?? '',
            'status'       => $sub['status'] ?? 'unknown',
            'status_label' => $status_labels[$sub['status']] ?? $sub['status'],
            'date'         => $sub['date'] ?? '',
        );
    }

    return rest_ensure_response(array(
        'submissions'    => $formatted,
        'count'          => count($formatted),
        'referral_rate'  => $rate,
        'program_enabled'=> get_option('gorilla_lr_enabled_referral') === 'yes',
    ));
}

/**
 * /tiers - Tüm seviyeleri listele (public)
 */
function gorilla_rest_get_all_tiers(WP_REST_Request $request) {
    if (!function_exists('gorilla_get_tiers')) {
        return new WP_Error('not_available', __('Sadakat programı kullanılamıyor.', 'gorilla-lr'), array('status' => 503));
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

    return rest_ensure_response(array(
        'tiers'         => $formatted,
        'period_months' => intval(get_option('gorilla_lr_period_months', 6)),
    ));
}

/**
 * /settings - Genel ayarlar (public)
 */
function gorilla_rest_get_settings(WP_REST_Request $request) {
    return rest_ensure_response(array(
        'loyalty_enabled'       => get_option('gorilla_lr_enabled_loyalty') === 'yes',
        'referral_enabled'      => get_option('gorilla_lr_enabled_referral') === 'yes',
        'affiliate_enabled'     => get_option('gorilla_lr_enabled_affiliate') === 'yes',
        'period_months'         => intval(get_option('gorilla_lr_period_months', 6)),
        'referral_rate'         => intval(get_option('gorilla_lr_referral_rate', 35)),
        'affiliate_rate'        => intval(get_option('gorilla_lr_affiliate_rate', 10)),
        'affiliate_cookie_days' => intval(get_option('gorilla_lr_affiliate_cookie_days', 30)),
        'credit_min_order'      => floatval(get_option('gorilla_lr_credit_min_order', 0)),
        'credit_expiry_days'    => intval(get_option('gorilla_lr_credit_expiry_days', 0)),
        'version'               => defined('GORILLA_LR_VERSION') ? GORILLA_LR_VERSION : '3.0.1',
    ));
}

/**
 * /affiliate - Kullanıcı affiliate bilgileri
 */
function gorilla_rest_get_affiliate(WP_REST_Request $request) {
    $user_id = get_current_user_id();

    if (!function_exists('gorilla_affiliate_get_user_stats')) {
        return new WP_Error('not_available', __('Affiliate sistemi kullanılamıyor.', 'gorilla-lr'), array('status' => 503));
    }

    $stats = gorilla_affiliate_get_user_stats($user_id);
    $rate = intval(get_option('gorilla_lr_affiliate_rate', 10));
    $cookie_days = intval(get_option('gorilla_lr_affiliate_cookie_days', 30));

    return rest_ensure_response(array(
        'enabled'      => get_option('gorilla_lr_enabled_affiliate') === 'yes',
        'code'         => $stats['code'] ?? '',
        'link'         => $stats['link'] ?? '',
        'rate'         => $rate,
        'cookie_days'  => $cookie_days,
        'stats'        => array(
            'clicks'      => intval($stats['clicks'] ?? 0),
            'conversions' => intval($stats['conversions'] ?? 0),
            'earnings'    => floatval($stats['earnings'] ?? 0),
            'earnings_formatted' => function_exists('wc_price') ? strip_tags(wc_price($stats['earnings'] ?? 0)) : ($stats['earnings'] ?? 0),
        ),
    ));
}

/**
 * /affiliate/stats - Detaylı affiliate istatistikleri
 */
function gorilla_rest_get_affiliate_stats(WP_REST_Request $request) {
    $user_id = get_current_user_id();

    if (!function_exists('gorilla_affiliate_get_user_stats')) {
        return new WP_Error('not_available', __('Affiliate sistemi kullanılamıyor.', 'gorilla-lr'), array('status' => 503));
    }

    $stats = gorilla_affiliate_get_user_stats($user_id);
    $recent = function_exists('gorilla_affiliate_get_recent_earnings')
        ? gorilla_affiliate_get_recent_earnings($user_id, 10)
        : array();

    $formatted_recent = array();
    foreach ($recent as $earn) {
        $formatted_recent[] = array(
            'amount'     => floatval($earn['amount'] ?? 0),
            'formatted'  => function_exists('wc_price') ? strip_tags(wc_price($earn['amount'] ?? 0)) : ($earn['amount'] ?? 0),
            'reason'     => $earn['reason'] ?? '',
            'order_id'   => intval($earn['reference_id'] ?? 0),
            'date'       => $earn['created_at'] ?? '',
        );
    }

    // Conversion rate hesapla
    $clicks = intval($stats['clicks'] ?? 0);
    $conversions = intval($stats['conversions'] ?? 0);
    $conversion_rate = $clicks > 0 ? round(($conversions / $clicks) * 100, 1) : 0;

    return rest_ensure_response(array(
        'code'            => $stats['code'] ?? '',
        'link'            => $stats['link'] ?? '',
        'clicks'          => $clicks,
        'conversions'     => $conversions,
        'conversion_rate' => $conversion_rate,
        'total_earnings'  => floatval($stats['earnings'] ?? 0),
        'total_earnings_formatted' => function_exists('wc_price') ? strip_tags(wc_price($stats['earnings'] ?? 0)) : ($stats['earnings'] ?? 0),
        'recent_earnings' => $formatted_recent,
    ));
}

/**
 * /admin/stats - Admin dashboard istatistikleri
 */
function gorilla_rest_admin_stats(WP_REST_Request $request) {
    global $wpdb;

    // Referans sayıları
    $ref_counts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_status, COUNT(*) as count FROM {$wpdb->posts} WHERE post_type = %s GROUP BY post_status",
            'gorilla_referral'
        ),
        OBJECT_K
    );

    // Toplam verilen credit
    $credit_table = $wpdb->prefix . 'gorilla_credit_log';
    $total_credit_given = 0;
    $total_credit_used = 0;

    if (gorilla_lr_table_exists($credit_table)) {
        $credit_stats = $wpdb->get_row(
            "SELECT
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as given,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as used
             FROM {$credit_table}"
        );
        $total_credit_given = floatval($credit_stats->given ?? 0);
        $total_credit_used = floatval($credit_stats->used ?? 0);
    }

    // Affiliate istatistikleri
    $affiliate_stats = function_exists('gorilla_affiliate_get_admin_stats')
        ? gorilla_affiliate_get_admin_stats()
        : array();

    return rest_ensure_response(array(
        'referrals' => array(
            'pending'  => intval($ref_counts['pending']->count ?? 0),
            'approved' => intval($ref_counts['grla_approved']->count ?? 0),
            'rejected' => intval($ref_counts['grla_rejected']->count ?? 0),
        ),
        'credits' => array(
            'total_given'     => $total_credit_given,
            'total_used'      => $total_credit_used,
            'net_outstanding' => $total_credit_given - $total_credit_used,
        ),
        'affiliate' => array(
            'total_clicks'      => intval($affiliate_stats['total_clicks'] ?? 0),
            'total_conversions' => intval($affiliate_stats['total_conversions'] ?? 0),
            'conversion_rate'   => floatval($affiliate_stats['conversion_rate'] ?? 0),
            'total_commission'  => floatval($affiliate_stats['total_commission'] ?? 0),
            'active_affiliates' => intval($affiliate_stats['active_affiliates'] ?? 0),
        ),
    ));
}

/**
 * /admin/user/{id} - Admin kullanıcı bilgisi
 */
function gorilla_rest_admin_get_user(WP_REST_Request $request) {
    $user_id = $request->get_param('id');
    $user_id = absint($user_id);
    if (!$user_id || !get_userdata($user_id)) {
        return new WP_Error('invalid_user', 'Kullanici bulunamadi', array('status' => 404));
    }
    // Admin rate limiting
    $rate_key = 'gorilla_admin_rate_' . get_current_user_id();
    $count = intval(get_transient($rate_key));
    if ($count > 100) {
        return new WP_Error('rate_limited', 'Cok fazla istek', array('status' => 429));
    }
    set_transient($rate_key, $count + 1, MINUTE_IN_SECONDS);

    $user = get_userdata($user_id);

    if (!$user) {
        return new WP_Error('user_not_found', __('Kullanıcı bulunamadı.', 'gorilla-lr'), array('status' => 404));
    }

    $tier = function_exists('gorilla_loyalty_calculate_tier') ? gorilla_loyalty_calculate_tier($user_id) : null;
    $credit = function_exists('gorilla_credit_get_balance') ? gorilla_credit_get_balance($user_id) : 0;
    $referrals = function_exists('gorilla_referral_get_user_submissions') ? gorilla_referral_get_user_submissions($user_id) : array();
    $credit_log = function_exists('gorilla_credit_get_log') ? gorilla_credit_get_log($user_id, 20) : array();

    return rest_ensure_response(array(
        'user' => array(
            'id'           => $user_id,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
            'registered'   => $user->user_registered,
        ),
        'tier'        => $tier,
        'credit'      => floatval($credit),
        'referrals'   => $referrals,
        'credit_log'  => $credit_log,
    ));
}

function gorilla_rest_get_badges(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    if (!function_exists('gorilla_badge_get_user_badges')) {
        return new WP_Error('not_available', 'Rozet sistemi kulanilamiyor.', array('status' => 503));
    }
    return rest_ensure_response(array('badges' => gorilla_badge_get_user_badges($user_id), 'enabled' => get_option('gorilla_lr_badges_enabled') === 'yes'));
}

function gorilla_rest_get_leaderboard(WP_REST_Request $request) {
    if (!function_exists('gorilla_xp_get_leaderboard')) {
        return new WP_Error('not_available', 'Leaderboard kulanilamiyor.', array('status' => 503));
    }
    return rest_ensure_response(array('leaderboard' => gorilla_xp_get_leaderboard('monthly', 10), 'enabled' => get_option('gorilla_lr_leaderboard_enabled') === 'yes'));
}

function gorilla_rest_get_milestones(WP_REST_Request $request) {
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
    return rest_ensure_response(array('milestones' => $result, 'enabled' => get_option('gorilla_lr_milestones_enabled') === 'yes'));
}

function gorilla_rest_get_shop(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $rewards = function_exists('gorilla_shop_get_rewards') ? gorilla_shop_get_rewards() : array();
    $xp = function_exists('gorilla_xp_get_balance') ? gorilla_xp_get_balance($user_id) : 0;
    return rest_ensure_response(array('rewards' => $rewards, 'xp_balance' => $xp, 'enabled' => get_option('gorilla_lr_points_shop_enabled') === 'yes'));
}

function gorilla_rest_shop_redeem(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    // Rate limiting
    $rate_key = 'gorilla_shop_rate_' . $user_id;
    if (get_transient($rate_key)) {
        return new WP_Error('rate_limited', 'Cok sik istek. Lutfen bekleyin.', array('status' => 429));
    }
    set_transient($rate_key, true, 5);

    $reward_id = $request->get_param('reward_id');
    if (!function_exists('gorilla_shop_redeem')) {
        return new WP_Error('not_available', 'Puan dukkani kulanilamiyor.', array('status' => 503));
    }
    $result = gorilla_shop_redeem($user_id, $reward_id);
    if ($result['success']) {
        return rest_ensure_response($result);
    }
    return new WP_Error('redeem_failed', $result['error'] ?? 'Bilinmeyen hata', array('status' => 400));
}

function gorilla_rest_get_streak(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    return rest_ensure_response(array(
        'current_streak' => intval(get_user_meta($user_id, '_gorilla_login_streak', true)),
        'best_streak'    => intval(get_user_meta($user_id, '_gorilla_login_streak_best', true)),
        'last_login'     => get_user_meta($user_id, '_gorilla_login_last_date', true) ?: null,
        'enabled'        => get_option('gorilla_lr_streak_enabled') === 'yes',
    ));
}

function gorilla_rest_get_qr(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    if (!function_exists('gorilla_qr_get_url')) {
        return new WP_Error('not_available', 'QR sistemi kulanilamiyor.', array('status' => 503));
    }
    return rest_ensure_response(array('qr_url' => gorilla_qr_get_url($user_id), 'enabled' => get_option('gorilla_lr_qr_enabled') === 'yes'));
}

function gorilla_rest_social_share(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    // Rate limiting
    $rate_key = 'gorilla_share_rate_' . $user_id;
    if (get_transient($rate_key)) {
        return new WP_Error('rate_limited', 'Cok sik istek. Lutfen bekleyin.', array('status' => 429));
    }
    set_transient($rate_key, true, 3);

    $platform = $request->get_param('platform');
    if (!function_exists('gorilla_social_track_share')) {
        return new WP_Error('not_available', 'Sosyal paylasim sistemi kulanilamiyor.', array('status' => 503));
    }
    $result = gorilla_social_track_share($user_id, $platform);
    return rest_ensure_response(array('awarded' => $result));
}
