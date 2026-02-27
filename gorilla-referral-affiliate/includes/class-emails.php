<?php
/**
 * Gorilla RA - E-posta Bildirimleri
 * Referral & Affiliate email fonksiyonlari
 *
 * Tum email fonksiyonlari artik WC_Email class'larina delege eder.
 * WooCommerce > Ayarlar > Emailler sayfasindan yonetim saglanir.
 * Geriye uyumluluk icin eski fonksiyon imzalari korunur.
 *
 * @package Gorilla_Referral_Affiliate
 */

if (!defined('ABSPATH')) exit;

// -- WC Email class'lari icin helper --
if (!function_exists('gorilla_get_wc_email')) {
    function gorilla_get_wc_email($class_name) {
        if (!function_exists('WC')) return null;
        $emails = WC()->mailer()->get_emails();
        return isset($emails[$class_name]) ? $emails[$class_name] : null;
    }
}

// -- Referans Onay E-postasi --
if (!function_exists('gorilla_email_referral_approved')) {
    function gorilla_email_referral_approved($ref_id) {
        $email = gorilla_get_wc_email('Gorilla_Email_Referral_Approved');
        if ($email) {
            $email->trigger($ref_id);
            return;
        }
        gorilla_email_referral_approved_legacy($ref_id);
    }
}

if (!function_exists('gorilla_email_referral_approved_legacy')) {
    function gorilla_email_referral_approved_legacy($ref_id) {
        $user_id = get_post_meta($ref_id, '_ref_user_id', true);
        $credit = get_post_meta($ref_id, '_ref_credit_amount', true);
        $order_id = get_post_meta($ref_id, '_ref_order_id', true);
        $user = get_userdata($user_id);
        if (!$user) return;
        $balance = function_exists('gorilla_credit_get_balance') ? gorilla_credit_get_balance($user_id) : 0;
        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url();
        $account_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('gorilla-referral') : home_url();
        $subject = 'Referans Basvurunuz Onaylandi! - ' . get_bloginfo('name');
        $message = gorilla_ra_email_template('Referans Onaylandi!', sprintf(
            '<p>Merhaba <strong>%s</strong>,</p><p>Siparis #%s icin video referans basvurunuz onaylandi!</p><p>Eklenen: +%s | Bakiye: %s</p><p><a href="%s">Alisverise Basla</a></p>',
            esc_html($user->display_name), $order_id, wc_price($credit), wc_price($balance), esc_url($shop_url)
        ));
        gorilla_ra_send_email($user->user_email, $subject, $message);
    }
}

// -- Referans Red E-postasi --
if (!function_exists('gorilla_email_referral_rejected')) {
    function gorilla_email_referral_rejected($ref_id) {
        $email = gorilla_get_wc_email('Gorilla_Email_Referral_Rejected');
        if ($email) {
            $email->trigger($ref_id);
            return;
        }
        gorilla_email_referral_rejected_legacy($ref_id);
    }
}

if (!function_exists('gorilla_email_referral_rejected_legacy')) {
    function gorilla_email_referral_rejected_legacy($ref_id) {
        $user_id = get_post_meta($ref_id, '_ref_user_id', true);
        $order_id = get_post_meta($ref_id, '_ref_order_id', true);
        $user = get_userdata($user_id);
        if (!$user) return;
        $account_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('gorilla-referral') : home_url();
        $subject = 'Referans Basvurunuz Hakkinda - ' . get_bloginfo('name');
        $message = gorilla_ra_email_template('Basvuru Sonucu', sprintf(
            '<p>Merhaba <strong>%s</strong>,</p><p>Siparis #%s icin video referans basvurunuz bu sefer onaylanamadi.</p><p><a href="%s">Hesabima Git</a></p>',
            esc_html($user->display_name), $order_id, esc_url($account_url)
        ));
        gorilla_ra_send_email($user->user_email, $subject, $message);
    }
}

// -- Yeni Basvuru: Admin Bildirimi --
if (!function_exists('gorilla_email_new_referral')) {
    function gorilla_email_new_referral($ref_id) {
        $email = gorilla_get_wc_email('Gorilla_Email_New_Referral');
        if ($email) {
            $email->trigger($ref_id);
            return;
        }
        gorilla_email_new_referral_legacy($ref_id);
    }
}

if (!function_exists('gorilla_email_new_referral_legacy')) {
    function gorilla_email_new_referral_legacy($ref_id) {
        $admin_email = get_option('admin_email');
        $user_id = get_post_meta($ref_id, '_ref_user_id', true);
        $user = get_userdata($user_id);
        $order_id = get_post_meta($ref_id, '_ref_order_id', true);
        $subject = 'Yeni Referans Basvurusu - ' . ($user ? $user->display_name : 'Musteri');
        $admin_url = admin_url('edit.php?post_type=gorilla_referral&post_status=pending');
        $message = gorilla_ra_email_template('Yeni Referans Basvurusu', sprintf(
            '<p>Musteri: %s | Siparis: #%s</p><p><a href="%s">Basvurulari Incele</a></p>',
            $user ? esc_html($user->display_name) : '?', $order_id, esc_url($admin_url)
        ));
        gorilla_ra_send_email($admin_email, $subject, $message);
    }
}

// -- Affiliate Komisyon E-postasi --
if (!function_exists('gorilla_email_affiliate_earned')) {
    function gorilla_email_affiliate_earned($user_id, $order_id, $commission) {
        $email = gorilla_get_wc_email('Gorilla_Email_Affiliate_Earned');
        if ($email) {
            $email->trigger($user_id, $order_id, $commission);
            return;
        }
        $user = get_userdata($user_id);
        if (!$user) return;
        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url();
        $subject = sprintf('Affiliate Komisyonu Kazandiniz: %s - %s', wc_price($commission), get_bloginfo('name'));
        $message = gorilla_ra_email_template('Affiliate Komisyonu!', sprintf(
            '<p>Merhaba %s,</p><p>Siparis #%d uzerinden +%s komisyon kazandiniz!</p><p><a href="%s">Alisverise Basla</a></p>',
            esc_html($user->display_name), $order_id, wc_price($commission), esc_url($shop_url)
        ));
        gorilla_ra_send_email($user->user_email, $subject, $message);
    }
}

// -- Dual Referral Kupon E-postasi --
if (!function_exists('gorilla_email_dual_referral_coupon')) {
    function gorilla_email_dual_referral_coupon($user_id, $coupon_code) {
        $email = gorilla_get_wc_email('Gorilla_Email_Dual_Referral_Coupon');
        if ($email) {
            $email->trigger($user_id, $coupon_code);
            return;
        }
        $user = get_userdata($user_id);
        if (!$user) return;
        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url();
        $subject = 'Hosgeldin Hediyeniz Hazir! - ' . get_bloginfo('name');
        $message = gorilla_ra_email_template('Hosgeldin Hediyesi!', sprintf(
            '<p>Merhaba %s,</p><p>Kupon kodunuz: <strong>%s</strong></p><p><a href="%s">Alisverise Basla</a></p>',
            esc_html($user->display_name), esc_html($coupon_code), esc_url($shop_url)
        ));
        gorilla_ra_send_email($user->user_email, $subject, $message);
    }
}

// -- E-posta Template (Legacy fallback) --
if (!function_exists('gorilla_ra_email_template')) {
    function gorilla_ra_email_template($title, $body) {
        $logo_url = apply_filters('gorilla_email_logo_url', '');
        if (empty($logo_url)) {
            $custom_logo_id = get_theme_mod('custom_logo');
            $logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'medium') : '';
        }
        if (empty($logo_url)) {
            $logo_url = '';
        }

        $logo_html = '';
        if (!empty($logo_url)) {
            $logo_html = '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr(get_bloginfo('name')) . '" style="height:50px; width:auto;">';
        } else {
            $logo_html = '<span style="color:#fff; font-size:22px; font-weight:700;">' . esc_html(get_bloginfo('name')) . '</span>';
        }

        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="margin:0; padding:0; background:#f4f4f5; font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">
            <div style="max-width:600px; margin:0 auto; padding:20px;">
                <div style="background:#1f2937; padding:24px; border-radius:12px 12px 0 0; text-align:center;">
                    ' . $logo_html . '
                </div>
                <div style="background:#fff; padding:30px; border-bottom:1px solid #e5e7eb;">
                    <h1 style="margin:0; font-size:22px; color:#1f2937; text-align:center;">' . esc_html($title) . '</h1>
                </div>
                <div style="background:#fff; padding:30px 30px 35px; border-radius:0 0 12px 12px;">
                    ' . $body . '
                </div>
                <div style="text-align:center; padding:20px; color:#9ca3af; font-size:12px;">
                    <p>' . esc_html(get_bloginfo('name')) . ' &copy; ' . wp_date('Y') . '</p>
                </div>
            </div>
        </body>
        </html>';
    }
}

// -- E-posta Gonder (HTML) - Legacy --
if (!function_exists('gorilla_ra_send_email')) {
    function gorilla_ra_send_email($to, $subject, $message) {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );
        wp_mail($to, $subject, $message, $headers);
    }
}
