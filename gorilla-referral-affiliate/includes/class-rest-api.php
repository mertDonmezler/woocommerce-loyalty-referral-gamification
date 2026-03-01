<?php
/**
 * Gorilla RA - REST API Endpoints
 * Referral & Affiliate REST API
 *
 * @package Gorilla_Referral_Affiliate
 */

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', 'gorilla_ra_register_rest_routes');

function gorilla_ra_register_rest_routes() {
    $namespace = 'gorilla-lr/v1';

    // GET /gorilla-lr/v1/referrals - Kullanicinin referans basvurulari
    register_rest_route($namespace, '/referrals', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_ra_rest_get_referrals',
        'permission_callback' => 'gorilla_ra_rest_check_auth',
    ));

    // GET /gorilla-lr/v1/affiliate - Kullanici affiliate bilgileri
    register_rest_route($namespace, '/affiliate', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_ra_rest_get_affiliate',
        'permission_callback' => 'gorilla_ra_rest_check_auth',
    ));

    // GET /gorilla-lr/v1/affiliate/stats - Detayli affiliate istatistikleri
    register_rest_route($namespace, '/affiliate/stats', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'gorilla_ra_rest_get_affiliate_stats',
        'permission_callback' => 'gorilla_ra_rest_check_auth',
    ));
}

/**
 * Auth kontrolu - giris yapmis kullanici gerekli
 */
function gorilla_ra_rest_check_auth() {
    return is_user_logged_in();
}

/**
 * /referrals - Kullanicinin referans basvurulari
 */
function gorilla_ra_rest_get_referrals(WP_REST_Request $request) {
    $user_id = get_current_user_id();

    if (!function_exists('gorilla_referral_get_user_submissions')) {
        return new WP_Error('not_available', 'Referans sistemi kullanilamiyor.', array('status' => 501));
    }

    $submissions = gorilla_referral_get_user_submissions($user_id);
    $rate = intval(get_option('gorilla_lr_referral_rate', 35));

    $formatted = array();
    $status_labels = array(
        'pending'       => 'Bekliyor',
        'grla_approved' => 'Onaylandi',
        'grla_rejected' => 'Reddedildi',
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

    return new WP_REST_Response(array(
        'submissions'    => $formatted,
        'count'          => count($formatted),
        'referral_rate'  => $rate,
        'program_enabled'=> get_option('gorilla_lr_enabled_referral') === 'yes',
    ), 200);
}

/**
 * /affiliate - Kullanici affiliate bilgileri
 */
function gorilla_ra_rest_get_affiliate(WP_REST_Request $request) {
    $user_id = get_current_user_id();

    if (!function_exists('gorilla_affiliate_get_user_stats')) {
        return new WP_Error('not_available', 'Affiliate sistemi kullanilamiyor.', array('status' => 501));
    }

    $stats = gorilla_affiliate_get_user_stats($user_id);
    $rate = intval(get_option('gorilla_lr_affiliate_rate', 10));
    $cookie_days = intval(get_option('gorilla_lr_affiliate_cookie_days', 30));

    return new WP_REST_Response(array(
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
    ), 200);
}

/**
 * /affiliate/stats - Detayli affiliate istatistikleri
 */
function gorilla_ra_rest_get_affiliate_stats(WP_REST_Request $request) {
    $user_id = get_current_user_id();

    if (!function_exists('gorilla_affiliate_get_user_stats')) {
        return new WP_Error('not_available', 'Affiliate sistemi kullanilamiyor.', array('status' => 501));
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

    return new WP_REST_Response(array(
        'code'            => $stats['code'] ?? '',
        'link'            => $stats['link'] ?? '',
        'clicks'          => $clicks,
        'conversions'     => $conversions,
        'conversion_rate' => $conversion_rate,
        'total_earnings'  => floatval($stats['earnings'] ?? 0),
        'total_earnings_formatted' => function_exists('wc_price') ? strip_tags(wc_price($stats['earnings'] ?? 0)) : ($stats['earnings'] ?? 0),
        'recent_earnings' => $formatted_recent,
    ), 200);
}
