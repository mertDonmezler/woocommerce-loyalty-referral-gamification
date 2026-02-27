<?php
/**
 * Gorilla RA - WooCommerce Email Entegrasyonu
 *
 * Referral & Affiliate email'lerini WC_Email class'lari olarak kaydeder.
 * WooCommerce > Ayarlar > Emailler sayfasinda gorunur.
 * Theme template override destegi.
 *
 * @package Gorilla_Referral_Affiliate
 */

if (!defined('ABSPATH')) exit;

// Base class yukle
require_once GORILLA_RA_PATH . 'includes/emails/class-gorilla-ra-email-base.php';

// ── Email class'larini WooCommerce'e kaydet ──────────────
add_filter('woocommerce_email_classes', 'gorilla_ra_register_wc_emails');
function gorilla_ra_register_wc_emails($email_classes) {
    $email_classes['Gorilla_Email_Referral_Approved']     = new Gorilla_Email_Referral_Approved();
    $email_classes['Gorilla_Email_Referral_Rejected']     = new Gorilla_Email_Referral_Rejected();
    $email_classes['Gorilla_Email_New_Referral']           = new Gorilla_Email_New_Referral();
    $email_classes['Gorilla_Email_Affiliate_Earned']       = new Gorilla_Email_Affiliate_Earned();
    $email_classes['Gorilla_Email_Dual_Referral_Coupon']   = new Gorilla_Email_Dual_Referral_Coupon();
    return $email_classes;
}


// =====================================================================
// 1. REFERRAL APPROVED
// =====================================================================
class Gorilla_Email_Referral_Approved extends Gorilla_RA_Email_Base {
    public function __construct() {
        $this->id             = 'gorilla_referral_approved';
        $this->title          = 'Gorilla - Referans Onaylandi';
        $this->description    = 'Referans basvurusu onaylandiginda musteriye gonderilir.';
        $this->heading        = 'Referans Onaylandi!';
        $this->subject        = 'Referans Basvurunuz Onaylandi! - {site_title}';
        $this->customer_email = true;
        $this->enabled        = 'yes';
        parent::__construct();
    }

    public function trigger($ref_id) {
        $user_id = get_post_meta($ref_id, '_ref_user_id', true);
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->recipient = $user->user_email;
        $credit   = get_post_meta($ref_id, '_ref_credit_amount', true);
        $order_id = get_post_meta($ref_id, '_ref_order_id', true);
        $balance  = function_exists('gorilla_credit_get_balance') ? gorilla_credit_get_balance($user_id) : 0;
        $shop_url = wc_get_page_permalink('shop');
        $account_url = wc_get_account_endpoint_url('gorilla-referral');

        $this->email_data = array(
            'user_name' => $user->display_name,
            'credit'    => wc_price($credit),
            'order_id'  => $order_id,
            'balance'   => wc_price($balance),
        );

        $this->body_content = sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Siparis <strong>#%s</strong> icin gonderdiginiz video referans basvurusu <span style="color:#22c55e; font-weight:700;">onaylandi</span>!</p>
            <div style="background:#dcfce7; border-radius:12px; padding:20px; text-align:center; margin:20px 0;">
                <div style="color:#166534; font-size:14px;">Hesabiniza Eklenen</div>
                <div style="font-size:32px; font-weight:800; color:#15803d;">+%s</div>
                <div style="color:#4ade80; font-size:13px; margin-top:6px;">Guncel Bakiyeniz: %s</div>
            </div>
            <p>Store credit bakiyenizi bir sonraki alisverisinizde odeme sayfasinda kullanabilirsiniz.</p>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#f97316; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Alisverise Basla</a>
            </p>
            <p style="color:#888; font-size:13px;">Referans detaylarinizi <a href="%s">hesabinizdan</a> gorebilirsiniz.</p>',
            esc_html($user->display_name),
            esc_html($order_id),
            wc_price($credit),
            wc_price($balance),
            esc_url($shop_url),
            esc_url($account_url)
        );

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
    }
}


// =====================================================================
// 2. REFERRAL REJECTED
// =====================================================================
class Gorilla_Email_Referral_Rejected extends Gorilla_RA_Email_Base {
    public function __construct() {
        $this->id             = 'gorilla_referral_rejected';
        $this->title          = 'Gorilla - Referans Reddedildi';
        $this->description    = 'Referans basvurusu reddedildiginde musteriye gonderilir.';
        $this->heading        = 'Basvuru Sonucu';
        $this->subject        = 'Referans Basvurunuz Hakkinda - {site_title}';
        $this->customer_email = true;
        $this->enabled        = 'yes';
        parent::__construct();
    }

    public function trigger($ref_id) {
        $user_id = get_post_meta($ref_id, '_ref_user_id', true);
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->recipient = $user->user_email;
        $order_id    = get_post_meta($ref_id, '_ref_order_id', true);
        $account_url = wc_get_account_endpoint_url('gorilla-referral');

        $this->email_data = array('user_name' => $user->display_name, 'order_id' => $order_id);

        $this->body_content = sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Siparis <strong>#%s</strong> icin gonderdiginiz video referans basvurusu incelendi ancak maalesef bu sefer onaylanamadi.</p>
            <div style="background:#fef2f2; border-radius:12px; padding:18px; margin:20px 0;">
                <strong style="color:#991b1b;">Olasi sebepler:</strong>
                <ul style="margin:8px 0 0; padding-left:20px; color:#991b1b;">
                    <li>Video icerigi urunu yeterince gostermiyor</li>
                    <li>Video linki calismiyor veya gizli</li>
                    <li>Video cok kisa veya icerik yetersiz</li>
                </ul>
            </div>
            <p>Lutfen video iceriginizi gozden gecirip tekrar basvuru yapabilirsiniz.</p>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#6b7280; color:#fff; padding:12px 35px; border-radius:8px; text-decoration:none; font-weight:600; display:inline-block;">Hesabima Git</a>
            </p>',
            esc_html($user->display_name),
            esc_html($order_id),
            esc_url($account_url)
        );

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
    }
}


// =====================================================================
// 3. NEW REFERRAL (ADMIN)
// =====================================================================
class Gorilla_Email_New_Referral extends Gorilla_RA_Email_Base {
    public function __construct() {
        $this->id          = 'gorilla_new_referral';
        $this->title       = 'Gorilla - Yeni Referans (Admin)';
        $this->description = 'Yeni referans basvurusu yapildiginda admin\'e gonderilir.';
        $this->heading     = 'Yeni Referans Basvurusu';
        $this->subject     = 'Yeni Referans Basvurusu - {site_title}';
        $this->recipient   = $this->get_option('recipient', get_option('admin_email'));
        $this->enabled     = 'yes';
        parent::__construct();
    }

    protected function is_admin_email() {
        return true;
    }

    public function trigger($ref_id) {
        $user_id  = get_post_meta($ref_id, '_ref_user_id', true);
        $user     = get_userdata($user_id);
        $order_id = get_post_meta($ref_id, '_ref_order_id', true);
        $total    = get_post_meta($ref_id, '_ref_order_total', true);
        $credit   = get_post_meta($ref_id, '_ref_credit_amount', true);
        $platform = get_post_meta($ref_id, '_ref_platform', true);
        $video    = get_post_meta($ref_id, '_ref_video_url', true);
        $admin_url = admin_url('edit.php?post_type=gorilla_referral&post_status=pending');

        $this->email_data = array(
            'user_name' => $user ? $user->display_name : '?',
            'order_id'  => $order_id,
        );

        $this->body_content = sprintf(
            '<table style="width:100%%; border-collapse:collapse; margin:16px 0;">
                <tr><td style="padding:8px 12px; background:#f9fafb; font-weight:600; width:140px;">Musteri</td><td style="padding:8px 12px;">%s (%s)</td></tr>
                <tr><td style="padding:8px 12px; background:#f9fafb; font-weight:600;">Siparis</td><td style="padding:8px 12px;">#%s &mdash; %s</td></tr>
                <tr><td style="padding:8px 12px; background:#f9fafb; font-weight:600;">Kazanilacak Credit</td><td style="padding:8px 12px; font-weight:700; color:#22c55e;">%s</td></tr>
                <tr><td style="padding:8px 12px; background:#f9fafb; font-weight:600;">Platform</td><td style="padding:8px 12px;">%s</td></tr>
                <tr><td style="padding:8px 12px; background:#f9fafb; font-weight:600;">Video</td><td style="padding:8px 12px;"><a href="%s">%s</a></td></tr>
            </table>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#f97316; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Basvurulari Incele</a>
            </p>',
            $user ? esc_html($user->display_name) : '?',
            $user ? esc_html($user->user_email) : '?',
            esc_html($order_id),
            wc_price($total),
            wc_price($credit),
            esc_html($platform),
            esc_url($video),
            esc_html($video),
            esc_url($admin_url)
        );

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
    }

    public function init_form_fields() {
        parent::init_form_fields();
        // Admin email'i icin recipient alani ekle
        $this->form_fields = array_merge(
            array(
                'recipient' => array(
                    'title'       => 'Alici(lar)',
                    'type'        => 'text',
                    'desc_tip'    => true,
                    'description' => 'Birden fazla alici icin virgul kullanin.',
                    'placeholder' => get_option('admin_email'),
                    'default'     => '',
                ),
            ),
            $this->form_fields
        );
    }
}


// =====================================================================
// 4. AFFILIATE EARNED
// =====================================================================
class Gorilla_Email_Affiliate_Earned extends Gorilla_RA_Email_Base {
    public function __construct() {
        $this->id             = 'gorilla_affiliate_earned';
        $this->title          = 'Gorilla - Affiliate Komisyonu';
        $this->description    = 'Affiliate komisyonu kazanildiginda gonderilir.';
        $this->heading        = 'Affiliate Komisyonu!';
        $this->subject        = 'Affiliate Komisyonu Kazandiniz! - {site_title}';
        $this->customer_email = true;
        $this->enabled        = 'yes';
        parent::__construct();
    }

    public function trigger($user_id, $order_id, $commission) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->recipient = $user->user_email;
        $balance     = function_exists('gorilla_credit_get_balance') ? gorilla_credit_get_balance($user_id) : 0;
        $account_url = wc_get_account_endpoint_url('gorilla-referral');
        $shop_url    = wc_get_page_permalink('shop');

        $this->email_data = array(
            'user_name'  => $user->display_name,
            'commission' => wc_price($commission),
            'order_id'   => $order_id,
        );

        $this->body_content = sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Harika haber! Paylastiginiz affiliate linkiniz uzerinden bir siparis tamamlandi ve <strong>komisyon kazandiniz!</strong></p>
            <div style="background:linear-gradient(135deg, #dbeafe, #eff6ff); border:2px solid #3b82f6; border-radius:12px; padding:25px; text-align:center; margin:20px 0;">
                <div style="color:#1e40af; font-size:14px;">Kazandiginiz Komisyon</div>
                <div style="font-size:36px; font-weight:800; color:#1e40af;">+%s</div>
                <div style="color:#6b7280; font-size:12px; margin-top:8px;">Siparis #%d</div>
            </div>
            <div style="background:#dcfce7; border-radius:10px; padding:16px; text-align:center; margin:20px 0;">
                <div style="color:#166534; font-size:14px;">Guncel Store Credit Bakiyeniz</div>
                <div style="font-size:28px; font-weight:800; color:#15803d;">%s</div>
            </div>
            <p>Store credit bakiyenizi bir sonraki alisverisinizde odeme sayfasinda kullanabilirsiniz.</p>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#f97316; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Alisverise Basla</a>
            </p>',
            esc_html($user->display_name),
            wc_price($commission),
            $order_id,
            wc_price($balance),
            esc_url($shop_url)
        );

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
    }
}


// =====================================================================
// 5. DUAL REFERRAL COUPON
// =====================================================================
class Gorilla_Email_Dual_Referral_Coupon extends Gorilla_RA_Email_Base {
    public function __construct() {
        $this->id             = 'gorilla_dual_referral_coupon';
        $this->title          = 'Gorilla - Hosgeldin Kuponu';
        $this->description    = 'Dual referral ile gelen yeni musteriye kupon gonderilir.';
        $this->heading        = 'Hosgeldin Hediyesi!';
        $this->subject        = 'Hosgeldin Hediyeniz Hazir! - {site_title}';
        $this->customer_email = true;
        $this->enabled        = 'yes';
        parent::__construct();
    }

    public function trigger($user_id, $coupon_code) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->recipient = $user->user_email;
        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url();

        $coupon = new WC_Coupon($coupon_code);
        $amount = $coupon->get_amount();
        $type   = $coupon->get_discount_type();
        $type_label = ($type === 'percent') ? '%' . intval($amount) . ' indirim' : wc_price($amount) . ' indirim';

        $this->email_data = array('user_name' => $user->display_name, 'coupon_code' => $coupon_code);

        $this->body_content = sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Bir arkadasiniz sizi yonlendirdi ve size ozel bir hosgeldin hediyesi kazandiniz!</p>
            <div style="background:linear-gradient(135deg, #dbeafe, #eff6ff); border:2px solid #3b82f6; border-radius:16px; padding:30px; text-align:center; margin:20px 0;">
                <div style="font-size:48px; line-height:1;">&#127873;</div>
                <div style="font-size:22px; font-weight:800; color:#1e40af; margin:12px 0;">%s</div>
                <div style="background:#1e40af; color:#fff; padding:12px 24px; border-radius:8px; font-size:20px; font-weight:800; letter-spacing:2px; display:inline-block; margin:12px 0;">%s</div>
                <div style="font-size:13px; color:#6b7280; margin-top:8px;">Kupon kodunuzu kasada kullanabilirsiniz</div>
            </div>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#3b82f6; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Hemen Alisverise Basla</a>
            </p>',
            esc_html($user->display_name),
            esc_html($type_label),
            esc_html($coupon_code),
            esc_url($shop_url)
        );

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
    }
}
