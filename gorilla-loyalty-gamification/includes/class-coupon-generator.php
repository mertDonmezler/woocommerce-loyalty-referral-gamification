<?php
/**
 * Gorilla Store Credit - Coupon Generator
 * WooCommerce coupon creation utility
 *
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Generate a WooCommerce coupon programmatically.
 *
 * @param array $params Coupon parameters
 * @return string|false Coupon code or false on failure
 */
if (!function_exists('gorilla_generate_coupon')) {
    function gorilla_generate_coupon($params = array()) {
        if (!class_exists('WC_Coupon')) return false;

        $defaults = array(
            'type'        => 'percent',
            'amount'      => 10,
            'min_order'   => 0,
            'expiry_days' => 30,
            'user_id'     => 0,
            'reason'      => '',
            'prefix'      => 'GORILLA',
            'usage_limit' => 1,
        );
        $params = wp_parse_args($params, $defaults);

        $code = $params['prefix'] . '-' . strtoupper(wp_generate_password(8, false));

        $coupon = new \WC_Coupon();
        $coupon->set_code($code);
        $coupon->set_discount_type($params['type'] === 'percent' ? 'percent' : 'fixed_cart');
        $coupon->set_amount(floatval($params['amount']));
        $coupon->set_individual_use(true);
        $coupon->set_usage_limit(intval($params['usage_limit']));

        if ($params['type'] === 'free_shipping') {
            $coupon->set_free_shipping(true);
            $coupon->set_discount_type('fixed_cart');
            $coupon->set_amount(0);
        }

        if ($params['min_order'] > 0) {
            $coupon->set_minimum_amount(floatval($params['min_order']));
        }

        if ($params['expiry_days'] > 0) {
            $expiry = gmdate('Y-m-d', strtotime('+' . intval($params['expiry_days']) . ' days'));
            $coupon->set_date_expires($expiry);
        }

        if ($params['user_id']) {
            $user = get_userdata($params['user_id']);
            if ($user) {
                $coupon->set_email_restrictions(array($user->user_email));
            }
        }

        $coupon->save();

        if ($coupon->get_id()) {
            $coupon->update_meta_data('_gorilla_coupon_reason', sanitize_text_field($params['reason']));
            $coupon->save();
        }

        return $code;
    }
}
