<?php
/**
 * Gorilla Loyalty & Gamification - E-posta Bildirimleri
 *
 * Loyalty/gamification email fonksiyonlari.
 * WC_Email class'larina delege eder, fallback olarak legacy gonderim yapar.
 *
 * @package Gorilla_Loyalty_Gamification
 * @since   3.1.0
 */

if (!defined('ABSPATH')) exit;

// ── WC Email class'lari icin helper ────────────────────────
if (!function_exists('gorilla_get_wc_email')) {
    function gorilla_get_wc_email($class_name) {
        if (!function_exists('WC')) return null;
        $emails = WC()->mailer()->get_emails();
        return isset($emails[$class_name]) ? $emails[$class_name] : null;
    }
}

// ── E-posta Template (Legacy fallback) ──────────────────
if (!function_exists('gorilla_email_template')) {
    function gorilla_email_template($title, $body) {
        $logo_url = apply_filters('gorilla_email_logo_url', '');
        if (empty($logo_url)) {
            $custom_logo_id = get_theme_mod('custom_logo');
            $logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'medium') : '';
        }
        if (empty($logo_url)) {
            $logo_url = '';
        }

        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="margin:0; padding:0; background:#f4f4f5; font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">
            <div style="max-width:600px; margin:0 auto; padding:20px;">
                <div style="background:#1f2937; padding:24px; border-radius:12px 12px 0 0; text-align:center;">
                    ' . ($logo_url ? '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr(get_bloginfo('name')) . '" style="height:50px; width:auto;">' : '<span style="color:#fff; font-size:20px; font-weight:700;">' . esc_html(get_bloginfo('name')) . '</span>') . '
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

// ── E-posta Gonder (HTML) - Legacy ───────────────────────
if (!function_exists('gorilla_send_email')) {
    function gorilla_send_email($to, $subject, $message) {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );
        wp_mail($to, $subject, $message, $headers);
    }
}

// ── Seviye Tebrik E-postasi ──────────────────────────────
function gorilla_email_tier_upgrade($user_id, $old_tier, $new_tier) {
    $email = gorilla_get_wc_email('Gorilla_Email_Tier_Upgrade');
    if ($email) {
        $email->trigger($user_id, $old_tier, $new_tier);
        return;
    }
    $user = get_userdata($user_id);
    if (!$user) return;
    $shop_url = wc_get_page_permalink('shop');
    $subject = sprintf('Tebrikler! %s Seviyesine Yukseldiniz! - %s', $new_tier['label'] ?? 'Yeni', get_bloginfo('name'));
    $message = gorilla_email_template('Seviye Yukseltmesi!', sprintf(
        '<p>Merhaba %s,</p><p>%s %s seviyesine yukseldiniz! %%%d indirim kazandiniz.</p><p><a href="%s">Alisverise Basla</a></p>',
        esc_html($user->display_name), $new_tier['emoji'] ?? '', esc_html($new_tier['label'] ?? ''), intval($new_tier['discount'] ?? 0), esc_url($shop_url)
    ));
    gorilla_send_email($user->user_email, $subject, $message);
}

// ── Level-Up E-postasi (XP) ─────────────────────────────
add_action('gorilla_xp_level_up', 'gorilla_email_level_up', 10, 3);
function gorilla_email_level_up($user_id, $old_level, $new_level) {
    $email = gorilla_get_wc_email('Gorilla_Email_Level_Up');
    if ($email) {
        $email->trigger($user_id, $old_level, $new_level);
        return;
    }
    $user = get_userdata($user_id);
    if (!$user) return;
    $account_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('gorilla-loyalty') : home_url();
    $subject = sprintf('Tebrikler! %s Seviyesine Yukseldiniz! - %s', $new_level['label'] ?? 'Yeni', get_bloginfo('name'));
    $message = gorilla_email_template('Level Atladiniz!', sprintf(
        '<p>Merhaba %s,</p><p>%s %s seviyesine yukseldiniz!</p><p><a href="%s">Hesabima Git</a></p>',
        esc_html($user->display_name), $new_level['emoji'] ?? '', esc_html($new_level['label'] ?? ''), esc_url($account_url)
    ));
    gorilla_send_email($user->user_email, $subject, $message);
}

// ── Dogum Gunu E-postasi ────────────────────────────────
function gorilla_email_birthday($user_id, $xp_amount, $credit_amount) {
    $email = gorilla_get_wc_email('Gorilla_Email_Birthday');
    if ($email) {
        $email->trigger($user_id, $xp_amount, $credit_amount);
        return;
    }
    $user = get_userdata($user_id);
    if (!$user) return;
    $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url();
    $subject = 'Dogum Gununuz Kutlu Olsun! - ' . get_bloginfo('name');
    $gifts = '';
    if ($xp_amount > 0) $gifts .= intval($xp_amount) . ' XP + ';
    if ($credit_amount > 0) $gifts .= wc_price($credit_amount) . ' credit';
    $message = gorilla_email_template('Dogum Gununuz Kutlu Olsun!', sprintf(
        '<p>Merhaba %s,</p><p>Hediyeleriniz: %s</p><p><a href="%s">Alisverise Basla</a></p>',
        esc_html($user->display_name), $gifts, esc_url($shop_url)
    ));
    gorilla_send_email($user->user_email, $subject, $message);
}

// NOTE: gorilla_email_anniversary() was removed as dead code -- the anniversary
// XP/credit awards in class-xp.php do not send emails (they use WC email classes
// directly via the Gorilla_Email_Anniversary WC email class in class-wc-emails.php).

// ── Churn Re-engagement E-postasi ───────────────────────
function gorilla_email_churn_reengagement($user_id, $bonus_credit, $bonus_xp) {
    $email = gorilla_get_wc_email('Gorilla_Email_Churn_Reengagement');
    if ($email) {
        $email->trigger($user_id, $bonus_credit, $bonus_xp);
        return;
    }
    $user = get_userdata($user_id);
    if (!$user) return;
    $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url();
    $subject = 'Sizi Ozledik! - ' . get_bloginfo('name');
    $message = gorilla_email_template('Sizi Ozledik!', sprintf(
        '<p>Merhaba %s,</p><p>Size ozel hediyeler hazirladik!</p><p><a href="%s">Alisverise Basla</a></p>',
        esc_html($user->display_name), esc_url($shop_url)
    ));
    gorilla_send_email($user->user_email, $subject, $message);
}

// ── XP Expiry Warning E-postasi ─────────────────────────
function gorilla_email_xp_expiry_warning($user_id, $xp_amount, $days_remaining) {
    $email = gorilla_get_wc_email('Gorilla_Email_XP_Expiry_Warning');
    if ($email) {
        $email->trigger($user_id, $xp_amount, $days_remaining);
        return;
    }
    $user = get_userdata($user_id);
    if (!$user) return;
    $account_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('gorilla-loyalty') : home_url();
    $subject = sprintf('XP Puanlariniz %d Gun Icinde Sona Erecek!', $days_remaining);
    $message = gorilla_email_template('XP Puanlariniz Sona Ermek Uzere', sprintf(
        '<p>Merhaba %s,</p><p>%d XP %d gun icinde sona erecek.</p><p><a href="%s">Hesabimi Gor</a></p>',
        esc_html($user->display_name), $xp_amount, $days_remaining, esc_url($account_url)
    ));
    gorilla_send_email($user->user_email, $subject, $message);
}

// ── Milestone Tamamlandi E-postasi ──────────────────────
function gorilla_email_milestone_reached($user_id, $milestone) {
    $email = gorilla_get_wc_email('Gorilla_Email_Milestone_Reached');
    if ($email) {
        $email->trigger($user_id, $milestone);
        return;
    }
    $user = get_userdata($user_id);
    if (!$user) return;
    $account_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('gorilla-loyalty') : home_url();
    $subject = sprintf('Hedef Tamamlandi: %s - %s', $milestone['label'] ?? '', get_bloginfo('name'));
    $message = gorilla_email_template('Hedef Tamamlandi!', sprintf(
        '<p>Merhaba %s,</p><p>%s hedefini tamamladiniz!</p><p><a href="%s">Hesabima Git</a></p>',
        esc_html($user->display_name), esc_html($milestone['label'] ?? ''), esc_url($account_url)
    ));
    gorilla_send_email($user->user_email, $subject, $message);
}

// ── Rozet Kazanildi E-postasi ───────────────────────────
add_action('gorilla_badge_earned', 'gorilla_email_badge_earned', 10, 2);
function gorilla_email_badge_earned($user_id, $badge_id) {
    $email = gorilla_get_wc_email('Gorilla_Email_Badge_Earned');
    if ($email) {
        $email->trigger($user_id, $badge_id);
        return;
    }
    $user = get_userdata($user_id);
    if (!$user) return;
    $definitions = function_exists('gorilla_badge_get_definitions') ? gorilla_badge_get_definitions() : array();
    $badge = $definitions[$badge_id] ?? null;
    if (!$badge) return;
    $account_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('gorilla-loyalty') : home_url();
    $subject = sprintf('Yeni Rozet Kazandiniz: %s - %s', $badge['label'] ?? '', get_bloginfo('name'));
    $message = gorilla_email_template('Yeni Rozet!', sprintf(
        '<p>Merhaba %s,</p><p>%s %s rozetini kazandiniz!</p><p><a href="%s">Rozetlerimi Gor</a></p>',
        esc_html($user->display_name), $badge['emoji'] ?? '', esc_html($badge['label'] ?? ''), esc_url($account_url)
    ));
    gorilla_send_email($user->user_email, $subject, $message);
}

// ── Tier Grace Period Uyari E-postasi ───────────────────
function gorilla_email_tier_grace_warning($user_id, $tier, $days_remaining) {
    $email = gorilla_get_wc_email('Gorilla_Email_Tier_Grace_Warning');
    if ($email) {
        $email->trigger($user_id, $tier, $days_remaining);
        return;
    }
    $user = get_userdata($user_id);
    if (!$user || !$tier) return;
    $shop_url = wc_get_page_permalink('shop');
    $subject = sprintf('Seviyeniz %d gun icinde dusebilir! - %s', $days_remaining, get_bloginfo('name'));
    $message = gorilla_email_template('Seviye Uyarisi', sprintf(
        '<p>Merhaba %s,</p><p>%s seviyeniz %d gun icinde dusebilir.</p><p><a href="%s">Alisverise Basla</a></p>',
        esc_html($user->display_name), esc_html($tier['label'] ?? ''), $days_remaining, esc_url($shop_url)
    ));
    gorilla_send_email($user->user_email, $subject, $message);
}

// ── Tier Downgrade Bildirim E-postasi ───────────────────
function gorilla_email_tier_downgrade_notice($user_id, $old_tier, $new_tier) {
    $email = gorilla_get_wc_email('Gorilla_Email_Tier_Downgrade');
    if ($email) {
        $email->trigger($user_id, $old_tier, $new_tier);
        return;
    }
    $user = get_userdata($user_id);
    if (!$user) return;
    $shop_url = wc_get_page_permalink('shop');
    $new_label = $new_tier ? ($new_tier['label'] ?? 'Uye') : 'Uye';
    $subject = 'Seviyeniz degisti - ' . get_bloginfo('name');
    $message = gorilla_email_template('Seviye Degisikligi', sprintf(
        '<p>Merhaba %s,</p><p>Seviyeniz %s olarak guncellendi.</p><p><a href="%s">Alisverise Basla</a></p>',
        esc_html($user->display_name), esc_html($new_label), esc_url($shop_url)
    ));
    gorilla_send_email($user->user_email, $subject, $message);
}

// ── Smart Coupon Email ──────────────────────────────────
function gorilla_email_smart_coupon($user_id, $coupon_code, $discount_pct, $expiry_days, $category_name = '') {
    $email = gorilla_get_wc_email('Gorilla_Email_Smart_Coupon');
    if ($email) {
        $email->trigger($user_id, $coupon_code, $discount_pct, $expiry_days, $category_name);
        return;
    }
    $user = get_userdata($user_id);
    if (!$user) return;
    $shop_url = wc_get_page_permalink('shop');
    $subject = sprintf('Size Ozel %%%s Indirim Kuponu - %s', $discount_pct, get_bloginfo('name'));
    $message = gorilla_email_template('Size Ozel Kupon', sprintf(
        '<p>Merhaba %s,</p><p>Kupon: <strong>%s</strong> (%%%s indirim, %d gun gecerli)</p><p><a href="%s">Alisverise Basla</a></p>',
        esc_html($user->display_name), esc_html($coupon_code), esc_html($discount_pct), intval($expiry_days), esc_url($shop_url)
    ));
    gorilla_send_email($user->user_email, $subject, $message);
}
