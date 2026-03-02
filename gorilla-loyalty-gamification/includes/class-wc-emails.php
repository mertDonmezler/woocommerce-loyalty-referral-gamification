<?php
/**
 * Gorilla LG - WooCommerce Email Entegrasyonu
 *
 * Loyalty/gamification email class'larini WooCommerce'e kaydeder.
 * WooCommerce > Ayarlar > Emailler sayfasinda gorunur.
 * Theme template override destegi.
 *
 * @package Gorilla_Loyalty_Gamification
 * @since   3.1.0
 */

if (!defined('ABSPATH')) exit;

// Base class yukle
require_once GORILLA_LG_PATH . 'includes/emails/class-gorilla-email-base.php';

// -- Email class'larini WooCommerce'e kaydet --
if (!function_exists('gorilla_lg_register_wc_emails')) {
    add_filter('woocommerce_email_classes', 'gorilla_lg_register_wc_emails');
    function gorilla_lg_register_wc_emails($email_classes) {
        $email_classes['Gorilla_Email_Tier_Upgrade']          = new Gorilla_Email_Tier_Upgrade();
        $email_classes['Gorilla_Email_Tier_Grace_Warning']    = new Gorilla_Email_Tier_Grace_Warning();
        $email_classes['Gorilla_Email_Tier_Downgrade']        = new Gorilla_Email_Tier_Downgrade();
        $email_classes['Gorilla_Email_Level_Up']              = new Gorilla_Email_Level_Up();
        $email_classes['Gorilla_Email_Credit_Expiry_Warning'] = new Gorilla_Email_Credit_Expiry_Warning();
        $email_classes['Gorilla_Email_Birthday']              = new Gorilla_Email_Birthday();
        $email_classes['Gorilla_Email_Anniversary']           = new Gorilla_Email_Anniversary();
        $email_classes['Gorilla_Email_Churn_Reengagement']    = new Gorilla_Email_Churn_Reengagement();
        $email_classes['Gorilla_Email_XP_Expiry_Warning']     = new Gorilla_Email_XP_Expiry_Warning();
        $email_classes['Gorilla_Email_Milestone_Reached']     = new Gorilla_Email_Milestone_Reached();
        $email_classes['Gorilla_Email_Badge_Earned']          = new Gorilla_Email_Badge_Earned();
        $email_classes['Gorilla_Email_Smart_Coupon']          = new Gorilla_Email_Smart_Coupon();
        return $email_classes;
    }
}


// =====================================================================
// 1. TIER UPGRADE
// =====================================================================
if (!class_exists('Gorilla_Email_Tier_Upgrade')) {
class Gorilla_Email_Tier_Upgrade extends Gorilla_Email_Base {
    public function __construct() {
        $this->id             = 'gorilla_tier_upgrade';
        $this->title          = 'Gorilla - Seviye Yukseltme';
        $this->description    = 'Musteri tier seviyesi yukseldiginde gonderilir.';
        $this->heading        = 'Seviye Yukseltmesi!';
        $this->subject        = 'Tebrikler! Yeni Seviyeye Yukseldiniz! - {site_title}';
        $this->customer_email = true;
        $this->enabled        = 'yes';
        parent::__construct();
    }

    public function trigger($user_id, $old_tier, $new_tier) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->recipient = $user->user_email;
        $account_url = wc_get_account_endpoint_url('gorilla-loyalty');
        $shop_url    = wc_get_page_permalink('shop');

        $this->email_data = array(
            'user_name' => $user->display_name,
            'tier_name' => $new_tier['label'] ?? 'Yeni',
            'tier_emoji' => $new_tier['emoji'] ?? '',
        );

        $benefits = '<ul style="margin:12px 0; padding-left:20px; color:#166534;">';
        $benefits .= '<li>Tum alisverislerinizde <strong>%' . intval($new_tier['discount'] ?? 0) . ' indirim</strong></li>';
        if (($new_tier['installment'] ?? 0) > 0) {
            $benefits .= '<li>Vade farksiz <strong>' . intval($new_tier['installment']) . ' taksit</strong> hakki</li>';
        }
        $benefits .= '</ul>';

        $this->body_content = sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Harika haberler! Alisverisleriniz sayesinde <strong>%s %s</strong> seviyesine yukseldiniz!</p>
            <div style="background:linear-gradient(135deg, %s15, %s30); border:2px solid %s; border-radius:16px; padding:30px; text-align:center; margin:20px 0;">
                <div style="font-size:64px; line-height:1;">%s</div>
                <div style="font-size:28px; font-weight:800; color:#1f2937; margin:8px 0;">%s Uye</div>
                <div style="font-size:18px; color:#4b5563;">%%%d indirim kazandiniz!</div>
            </div>
            <div style="background:#f0fdf4; border-radius:12px; padding:18px; margin:20px 0;">
                <strong style="color:#166534;">Yeni Ayricaliklaariniz:</strong>
                %s
            </div>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#f97316; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Alisverise Basla</a>
            </p>
            <p style="color:#888; font-size:13px; text-align:center;">Seviye detaylarinizi <a href="%s">hesabinizdan</a> gorebilirsiniz.</p>',
            esc_html($user->display_name),
            esc_html($new_tier['emoji'] ?? ''),
            esc_html($new_tier['label'] ?? 'Yeni'),
            esc_attr($new_tier['color'] ?? '#999'),
            esc_attr($new_tier['color'] ?? '#999'),
            esc_attr($new_tier['color'] ?? '#999'),
            esc_html($new_tier['emoji'] ?? ''),
            esc_html($new_tier['label'] ?? 'Yeni'),
            intval($new_tier['discount'] ?? 0),
            $benefits,
            esc_url($shop_url),
            esc_url($account_url)
        );

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
    }
}
} // end class_exists Gorilla_Email_Tier_Upgrade


// =====================================================================
// 2. TIER GRACE WARNING
// =====================================================================
if (!class_exists('Gorilla_Email_Tier_Grace_Warning')) {
class Gorilla_Email_Tier_Grace_Warning extends Gorilla_Email_Base {
    public function __construct() {
        $this->id             = 'gorilla_tier_grace_warning';
        $this->title          = 'Gorilla - Seviye Dusme Uyarisi';
        $this->description    = 'Grace period suresinde seviye dusme riski oldiginda gonderilir.';
        $this->heading        = 'Seviye Uyarisi';
        $this->subject        = 'Seviyeniz dusebilir! - {site_title}';
        $this->customer_email = true;
        $this->enabled        = 'yes';
        parent::__construct();
    }

    public function trigger($user_id, $tier, $days_remaining) {
        $user = get_userdata($user_id);
        if (!$user || !$tier) return;

        $this->recipient = $user->user_email;
        $shop_url = wc_get_page_permalink('shop');

        $this->email_data = array(
            'user_name'      => $user->display_name,
            'tier_name'      => $tier['label'] ?? '',
            'days_remaining' => $days_remaining,
        );

        $this->body_content = sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Su anda <strong>%s %s</strong> seviyesindesiniz. Ancak son donem harcamaniz esik degerinin altina dustu.</p>
            <div style="background:#fef2f2; border:2px solid #fca5a5; border-radius:12px; padding:20px; text-align:center; margin:20px 0;">
                <div style="font-size:48px;">%s</div>
                <div style="font-size:20px; font-weight:700; color:#991b1b; margin:8px 0;">%d gun kaldi!</div>
                <div style="font-size:14px; color:#7f1d1d;">Alisveris yaparak seviyenizi koruyabilirsiniz.</div>
            </div>
            <p>Yeni bir alisveris yaparak harcama toplaminizi yukseltin ve <strong>%%%d indirim</strong> avantajinizi kaybetmeyin!</p>
            <p style="text-align:center; margin-top:25px;">
                <a href="%s" style="display:inline-block; padding:14px 36px; background:linear-gradient(135deg, #f59e0b, #d97706); color:#fff; text-decoration:none; border-radius:10px; font-weight:700; font-size:16px;">Alisverise Basla</a>
            </p>',
            esc_html($user->display_name),
            esc_html($tier['emoji'] ?? ''),
            esc_html($tier['label'] ?? ''),
            esc_html($tier['emoji'] ?? ''),
            $days_remaining,
            intval($tier['discount'] ?? 0),
            esc_url($shop_url)
        );

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
    }
}
} // end class_exists Gorilla_Email_Tier_Grace_Warning


// =====================================================================
// 3. TIER DOWNGRADE
// =====================================================================
if (!class_exists('Gorilla_Email_Tier_Downgrade')) {
class Gorilla_Email_Tier_Downgrade extends Gorilla_Email_Base {
    public function __construct() {
        $this->id             = 'gorilla_tier_downgrade';
        $this->title          = 'Gorilla - Seviye Dususu';
        $this->description    = 'Musteri tier seviyesi dustugunde gonderilir.';
        $this->heading        = 'Seviye Degisikligi';
        $this->subject        = 'Seviyeniz degisti - {site_title}';
        $this->customer_email = true;
        $this->enabled        = 'yes';
        parent::__construct();
    }

    public function trigger($user_id, $old_tier, $new_tier) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->recipient = $user->user_email;
        $shop_url  = wc_get_page_permalink('shop');
        $new_label = $new_tier ? (esc_html($new_tier['emoji']) . ' ' . esc_html($new_tier['label'])) : 'Uye';
        $old_label = $old_tier ? (esc_html($old_tier['emoji']) . ' ' . esc_html($old_tier['label'])) : '';

        $this->email_data = array('user_name' => $user->display_name);

        $this->body_content = sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Grace period suresinde yeterli alisveris yapilmadigi icin seviyeniz <strong>%s</strong> olarak guncellendi.</p>
            <div style="background:#f9fafb; border-radius:12px; padding:20px; text-align:center; margin:20px 0;">
                <span style="font-size:14px; color:#6b7280;">%s</span>
                <span style="font-size:20px; margin:0 10px;">&rarr;</span>
                <span style="font-size:14px; font-weight:700;">%s</span>
            </div>
            <p>Endiselenmeyin! Alisverise devam ederek tekrar yukselinizi yakalayabilirsiniz.</p>
            <p style="text-align:center; margin-top:25px;">
                <a href="%s" style="display:inline-block; padding:14px 36px; background:linear-gradient(135deg, #3b82f6, #2563eb); color:#fff; text-decoration:none; border-radius:10px; font-weight:700; font-size:16px;">Alisverise Basla</a>
            </p>',
            esc_html($user->display_name),
            $new_label,
            $old_label,
            $new_label,
            esc_url($shop_url)
        );

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
    }
}
} // end class_exists Gorilla_Email_Tier_Downgrade


// =====================================================================
// 4. LEVEL UP (XP)
// =====================================================================
if (!class_exists('Gorilla_Email_Level_Up')) {
class Gorilla_Email_Level_Up extends Gorilla_Email_Base {
    public function __construct() {
        $this->id             = 'gorilla_level_up';
        $this->title          = 'Gorilla - XP Level Atlama';
        $this->description    = 'Musteri XP seviyesi atladiginda gonderilir.';
        $this->heading        = 'Level Atladiniz!';
        $this->subject        = 'Tebrikler! Yeni Seviyeye Yukseldiniz! - {site_title}';
        $this->customer_email = true;
        $this->enabled        = 'yes';
        parent::__construct();
    }

    public function trigger($user_id, $old_level, $new_level) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->recipient = $user->user_email;
        $account_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('gorilla-loyalty') : home_url();

        $this->email_data = array(
            'user_name'  => $user->display_name,
            'level_name' => $new_level['label'] ?? 'Yeni',
        );

        $this->body_content = sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Harika haberler! XP puanlariniz sayesinde <strong>%s %s</strong> seviyesine yukseldiniz!</p>
            <div style="background:linear-gradient(135deg, %s15, %s30); border:2px solid %s; border-radius:16px; padding:30px; text-align:center; margin:20px 0;">
                <div style="font-size:64px; line-height:1;">%s</div>
                <div style="font-size:28px; font-weight:800; color:#1f2937; margin:8px 0;">%s</div>
            </div>
            <p>Alisverislerinize devam ederek daha fazla XP kazanabilir ve seviyenizi yukseltebilirsiniz.</p>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#f97316; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Hesabima Git</a>
            </p>',
            esc_html($user->display_name),
            esc_html($new_level['emoji'] ?? ''),
            esc_html($new_level['label'] ?? 'Yeni'),
            esc_attr($new_level['color'] ?? '#999'),
            esc_attr($new_level['color'] ?? '#999'),
            esc_attr($new_level['color'] ?? '#999'),
            esc_html($new_level['emoji'] ?? ''),
            esc_html($new_level['label'] ?? 'Yeni'),
            esc_url($account_url)
        );

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
    }
}
} // end class_exists Gorilla_Email_Level_Up


// =====================================================================
// 5. CREDIT EXPIRY WARNING
// =====================================================================
if (!class_exists('Gorilla_Email_Credit_Expiry_Warning')) {
class Gorilla_Email_Credit_Expiry_Warning extends Gorilla_Email_Base {
    public function __construct() {
        $this->id             = 'gorilla_credit_expiry_warning';
        $this->title          = 'Gorilla - Credit Sure Uyarisi';
        $this->description    = 'Store credit suresi dolmadan once musteri uyarilir.';
        $this->heading        = 'Store Credit Hatirlatmasi';
        $this->subject        = 'Store Credit Sureniz Doluyor! - {site_title}';
        $this->customer_email = true;
        $this->enabled        = 'yes';
        parent::__construct();
    }

    public function trigger($user_id, $expiring_amount, $expiry_date) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->recipient = $user->user_email;
        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url();

        $this->email_data = array(
            'user_name'       => $user->display_name,
            'expiring_amount' => wc_price($expiring_amount),
        );

        $this->body_content = sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Hesabinizdaki store credit bakiyenizin bir kisminin suresi dolmak uzere!</p>
            <div style="background:#fef3c7; border:2px solid #f59e0b; border-radius:12px; padding:25px; text-align:center; margin:20px 0;">
                <div style="color:#92400e; font-size:14px;">Suresi Dolacak Tutar</div>
                <div style="font-size:32px; font-weight:800; color:#d97706;">%s</div>
                <div style="color:#b45309; font-size:13px; margin-top:6px;">Son Kullanim: %s</div>
            </div>
            <p>Bu tutari kaybetmemek icin son kullanim tarihinden once alisveris yapmanizi oneririz.</p>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#f97316; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Alisverise Basla</a>
            </p>',
            esc_html($user->display_name),
            wp_kses_post(wc_price($expiring_amount)),
            esc_html(date_i18n('d.m.Y', strtotime($expiry_date))),
            esc_url($shop_url)
        );

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
    }
}
} // end class_exists Gorilla_Email_Credit_Expiry_Warning


// =====================================================================
// 6. BIRTHDAY
// =====================================================================
if (!class_exists('Gorilla_Email_Birthday')) {
class Gorilla_Email_Birthday extends Gorilla_Email_Base {
    public function __construct() {
        $this->id             = 'gorilla_birthday';
        $this->title          = 'Gorilla - Dogum Gunu';
        $this->description    = 'Musterinin dogum gununde gonderilir.';
        $this->heading        = 'Dogum Gununuz Kutlu Olsun!';
        $this->subject        = 'Dogum Gununuz Kutlu Olsun! - {site_title}';
        $this->customer_email = true;
        $this->enabled        = 'yes';
        parent::__construct();
    }

    public function trigger($user_id, $xp_amount, $credit_amount) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->recipient = $user->user_email;
        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url();

        $this->email_data = array('user_name' => $user->display_name);

        $gifts = '';
        if ($xp_amount > 0) $gifts .= '<li><strong>' . intval($xp_amount) . ' XP</strong> bonus puani</li>';
        if ($credit_amount > 0) $gifts .= '<li><strong>' . wc_price($credit_amount) . '</strong> store credit hediyesi</li>';

        $this->body_content = sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Bugun dogum gununuz! Size ozel hediyelerimiz var:</p>
            <div style="background:linear-gradient(135deg, #fce7f3, #fbcfe8); border:2px solid #ec4899; border-radius:16px; padding:30px; text-align:center; margin:20px 0;">
                <div style="font-size:64px; line-height:1;">&#127874;</div>
                <div style="font-size:24px; font-weight:800; color:#1f2937; margin:12px 0;">Iyi ki Dogdunuz!</div>
                <ul style="list-style:none; padding:0; margin:16px 0; font-size:16px; color:#831843;">%s</ul>
            </div>
            <p>Hediyeleriniz otomatik olarak hesabiniza eklendi. Iyi gunlerde kullanin!</p>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#ec4899; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Alisverise Basla</a>
            </p>',
            esc_html($user->display_name),
            $gifts,
            esc_url($shop_url)
        );

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
    }
}
} // end class_exists Gorilla_Email_Birthday


// =====================================================================
// 7. ANNIVERSARY
// =====================================================================
if (!class_exists('Gorilla_Email_Anniversary')) {
class Gorilla_Email_Anniversary extends Gorilla_Email_Base {
    public function __construct() {
        $this->id             = 'gorilla_anniversary';
        $this->title          = 'Gorilla - Uyelik Yildonumu';
        $this->description    = 'Uyelik yildonumunde gonderilir.';
        $this->heading        = 'Uyelik Yildonumunuz!';
        $this->subject        = 'Yildonumunuz Kutlu Olsun! - {site_title}';
        $this->customer_email = true;
        $this->enabled        = 'yes';
        parent::__construct();
    }

    public function trigger($user_id, $years, $xp_amount, $credit_amount) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->recipient = $user->user_email;
        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url();

        $this->email_data = array('user_name' => $user->display_name, 'years' => $years);

        $gifts = '';
        if ($xp_amount > 0) $gifts .= '<li><strong>' . intval($xp_amount) . ' XP</strong> bonus puani</li>';
        if ($credit_amount > 0) $gifts .= '<li><strong>' . wc_price($credit_amount) . '</strong> store credit hediyesi</li>';

        $this->body_content = sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Bugun bizimle birlikte <strong>%d. yilinizi</strong> tamamliyorsunuz! Sizi aramizda gormek bizi cok mutlu ediyor.</p>
            <div style="background:linear-gradient(135deg, #dbeafe, #bfdbfe); border:2px solid #3b82f6; border-radius:16px; padding:30px; text-align:center; margin:20px 0;">
                <div style="font-size:64px; line-height:1;">&#127881;</div>
                <div style="font-size:24px; font-weight:800; color:#1f2937; margin:12px 0;">%d. Yil Kutlamasi!</div>
                <ul style="list-style:none; padding:0; margin:16px 0; font-size:16px; color:#1e3a5f;">%s</ul>
            </div>
            <p>Yildonumu hediyeleriniz otomatik olarak hesabiniza eklendi!</p>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#3b82f6; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Alisverise Basla</a>
            </p>',
            esc_html($user->display_name),
            $years,
            $years,
            $gifts,
            esc_url($shop_url)
        );

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
    }
}
} // end class_exists Gorilla_Email_Anniversary


// =====================================================================
// 8. CHURN RE-ENGAGEMENT
// =====================================================================
if (!class_exists('Gorilla_Email_Churn_Reengagement')) {
class Gorilla_Email_Churn_Reengagement extends Gorilla_Email_Base {
    public function __construct() {
        $this->id             = 'gorilla_churn_reengagement';
        $this->title          = 'Gorilla - Kayip Musteri Geri Kazanim';
        $this->description    = 'Uzun suredir alisveris yapmayan musterilere gonderilir.';
        $this->heading        = 'Sizi Ozledik!';
        $this->subject        = 'Sizi Ozledik! Ozel Hediyeleriniz Hazir - {site_title}';
        $this->customer_email = true;
        $this->enabled        = 'yes';
        parent::__construct();
    }

    public function trigger($user_id, $bonus_credit, $bonus_xp) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->recipient = $user->user_email;
        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url();

        $this->email_data = array('user_name' => $user->display_name);

        $gifts = '';
        if ($bonus_credit > 0) $gifts .= '<li><strong>' . wc_price($bonus_credit) . '</strong> store credit hediyesi (30 gun gecerli)</li>';
        if ($bonus_xp > 0) $gifts .= '<li><strong>' . intval($bonus_xp) . ' XP</strong> bonus puani</li>';

        $this->body_content = sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Uzun suredir sizi goremiyoruz! Size ozel hediyeler hazirladik:</p>
            <div style="background:linear-gradient(135deg, #fef3c7, #fde68a); border:2px solid #f59e0b; border-radius:16px; padding:30px; text-align:center; margin:20px 0;">
                <div style="font-size:64px; line-height:1;">&#128155;</div>
                <div style="font-size:24px; font-weight:800; color:#92400e; margin:12px 0;">Hosgeldin Hediyeleriniz!</div>
                <ul style="list-style:none; padding:0; margin:16px 0; font-size:16px; color:#78350f;">%s</ul>
            </div>
            <p>Hediyeleriniz otomatik olarak hesabiniza eklendi. Hemen alisverise baslayin!</p>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#f59e0b; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Alisverise Basla</a>
            </p>',
            esc_html($user->display_name),
            $gifts,
            esc_url($shop_url)
        );

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
    }
}
} // end class_exists Gorilla_Email_Churn_Reengagement


// =====================================================================
// 9. XP EXPIRY WARNING
// =====================================================================
if (!class_exists('Gorilla_Email_XP_Expiry_Warning')) {
class Gorilla_Email_XP_Expiry_Warning extends Gorilla_Email_Base {
    public function __construct() {
        $this->id             = 'gorilla_xp_expiry_warning';
        $this->title          = 'Gorilla - XP Sure Uyarisi';
        $this->description    = 'XP puanlari sona ermeden once gonderilir.';
        $this->heading        = 'XP Puanlariniz Sona Ermek Uzere';
        $this->subject        = 'XP Puanlariniz Sona Erecek! - {site_title}';
        $this->customer_email = true;
        $this->enabled        = 'yes';
        parent::__construct();
    }

    public function trigger($user_id, $xp_amount, $days_remaining) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->recipient = $user->user_email;
        $account_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('gorilla-loyalty') : home_url();

        $this->email_data = array(
            'user_name'      => $user->display_name,
            'xp_amount'      => $xp_amount,
            'days_remaining' => $days_remaining,
        );

        $this->body_content = sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p><strong>%d XP</strong> puaninizin suresi <strong>%d gun</strong> icinde dolacak!</p>
            <div style="background:linear-gradient(135deg, #fef3c7, #fde68a); border:2px solid #f59e0b; border-radius:16px; padding:30px; text-align:center; margin:20px 0;">
                <div style="font-size:64px; line-height:1;">&#9203;</div>
                <div style="font-size:24px; font-weight:800; color:#92400e; margin:12px 0;">%d XP Sona Eriyor</div>
                <p style="color:#78350f; font-size:14px;">Alisveris yaparak yeni XP kazanin ve seviyenizi koruyun!</p>
            </div>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#f59e0b; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Hesabimi Gor</a>
            </p>',
            esc_html($user->display_name),
            $xp_amount,
            $days_remaining,
            $xp_amount,
            esc_url($account_url)
        );

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
    }
}
} // end class_exists Gorilla_Email_XP_Expiry_Warning


// =====================================================================
// 10. MILESTONE REACHED
// =====================================================================
if (!class_exists('Gorilla_Email_Milestone_Reached')) {
class Gorilla_Email_Milestone_Reached extends Gorilla_Email_Base {
    public function __construct() {
        $this->id             = 'gorilla_milestone_reached';
        $this->title          = 'Gorilla - Hedef Tamamlandi';
        $this->description    = 'Musteri bir milestone hedefini tamamladiginda gonderilir.';
        $this->heading        = 'Hedef Tamamlandi!';
        $this->subject        = 'Hedef Tamamlandi! - {site_title}';
        $this->customer_email = true;
        $this->enabled        = 'yes';
        parent::__construct();
    }

    public function trigger($user_id, $milestone) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->recipient = $user->user_email;
        $account_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('gorilla-loyalty') : home_url();

        $this->email_data = array(
            'user_name'      => $user->display_name,
            'milestone_name' => $milestone['label'] ?? '',
        );

        $rewards = '';
        if (($milestone['xp_reward'] ?? 0) > 0) $rewards .= '<li><strong>' . intval($milestone['xp_reward']) . ' XP</strong></li>';
        if (($milestone['credit_reward'] ?? 0) > 0) $rewards .= '<li><strong>' . wc_price($milestone['credit_reward']) . '</strong> store credit</li>';

        $this->body_content = sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Tebrikler! Bir hedefinizi tamamladiniz:</p>
            <div style="background:linear-gradient(135deg, #dcfce7, #bbf7d0); border:2px solid #22c55e; border-radius:16px; padding:25px; text-align:center; margin:20px 0;">
                <div style="font-size:48px;">&#127942;</div>
                <div style="font-size:22px; font-weight:800; color:#166534; margin:8px 0;">%s</div>
                %s
            </div>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#22c55e; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Hesabima Git</a>
            </p>',
            esc_html($user->display_name),
            esc_html($milestone['label'] ?? ''),
            $rewards ? '<ul style="list-style:none; padding:0; margin:12px 0; color:#166534;">' . $rewards . '</ul>' : '',
            esc_url($account_url)
        );

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
    }
}
} // end class_exists Gorilla_Email_Milestone_Reached


// =====================================================================
// 11. BADGE EARNED
// =====================================================================
if (!class_exists('Gorilla_Email_Badge_Earned')) {
class Gorilla_Email_Badge_Earned extends Gorilla_Email_Base {
    public function __construct() {
        $this->id             = 'gorilla_badge_earned';
        $this->title          = 'Gorilla - Rozet Kazanildi';
        $this->description    = 'Yeni bir rozet kazanildiginda gonderilir.';
        $this->heading        = 'Yeni Rozet!';
        $this->subject        = 'Yeni Rozet Kazandiniz! - {site_title}';
        $this->customer_email = true;
        $this->enabled        = 'yes';
        parent::__construct();
    }

    public function trigger($user_id, $badge_id) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $definitions = function_exists('gorilla_badge_get_definitions') ? gorilla_badge_get_definitions() : array();
        $badge = $definitions[$badge_id] ?? null;
        if (!$badge) return;

        $this->recipient = $user->user_email;
        $account_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('gorilla-loyalty') : home_url();

        $this->email_data = array(
            'user_name'  => $user->display_name,
            'badge_name' => $badge['label'] ?? '',
        );

        $this->body_content = sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Tebrikler! Yeni bir rozet kazandiniz:</p>
            <div style="background:linear-gradient(135deg, %s15, %s30); border:2px solid %s; border-radius:16px; padding:30px; text-align:center; margin:20px 0;">
                <div style="font-size:64px; line-height:1;">%s</div>
                <div style="font-size:24px; font-weight:800; color:#1f2937; margin:8px 0;">%s</div>
                <div style="font-size:14px; color:#6b7280;">%s</div>
            </div>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:%s; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Rozetlerimi Gor</a>
            </p>',
            esc_html($user->display_name),
            esc_attr($badge['color'] ?? '#999'),
            esc_attr($badge['color'] ?? '#999'),
            esc_attr($badge['color'] ?? '#999'),
            esc_html($badge['emoji'] ?? ''),
            esc_html($badge['label'] ?? ''),
            esc_html($badge['description'] ?? ''),
            esc_url($account_url),
            esc_attr($badge['color'] ?? '#999')
        );

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
    }
}
} // end class_exists Gorilla_Email_Badge_Earned


// =====================================================================
// 12. SMART COUPON
// =====================================================================
if (!class_exists('Gorilla_Email_Smart_Coupon')) {
class Gorilla_Email_Smart_Coupon extends Gorilla_Email_Base {
    public function __construct() {
        $this->id             = 'gorilla_smart_coupon';
        $this->title          = 'Gorilla - Akilli Kupon';
        $this->description    = 'Otomatik olusturulan akilli kupon gonderilir.';
        $this->heading        = 'Size Ozel Kupon';
        $this->subject        = 'Size Ozel Indirim Kuponu - {site_title}';
        $this->customer_email = true;
        $this->enabled        = 'yes';
        parent::__construct();
    }

    public function trigger($user_id, $coupon_code, $discount_pct, $expiry_days, $category_name = '') {
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->recipient = $user->user_email;
        $shop_url = wc_get_page_permalink('shop');

        $this->email_data = array(
            'user_name'   => $user->display_name,
            'coupon_code' => $coupon_code,
            'discount'    => $discount_pct,
        );

        $cat_text = $category_name
            ? sprintf('Favori kategoriniz <strong>%s</strong> icin ozel bir indirim kuponu hazirladik!', esc_html($category_name))
            : 'Size ozel bir indirim kuponu hazirladik!';

        $this->body_content = sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>%s</p>
            <div style="background:linear-gradient(135deg, #f0fdf4, #dcfce7); border:2px dashed #22c55e; border-radius:12px; padding:24px; text-align:center; margin:20px 0;">
                <div style="font-size:12px; color:#6b7280; margin-bottom:8px;">KUPON KODUNUZ</div>
                <div style="font-size:28px; font-weight:800; color:#16a34a; letter-spacing:3px;">%s</div>
                <div style="font-size:14px; color:#4b5563; margin-top:8px;">%%%s indirim &bull; %d gun gecerli</div>
            </div>
            <p style="text-align:center; margin-top:25px;">
                <a href="%s" style="display:inline-block; padding:14px 36px; background:linear-gradient(135deg, #22c55e, #16a34a); color:#fff; text-decoration:none; border-radius:10px; font-weight:700; font-size:16px;">Alisverise Basla</a>
            </p>
            <p style="font-size:12px; color:#9ca3af; text-align:center;">Bu kupon %d gun sonra sona erecektir.</p>',
            esc_html($user->display_name),
            $cat_text,
            esc_html($coupon_code),
            esc_html($discount_pct),
            intval($expiry_days),
            esc_url($shop_url),
            intval($expiry_days)
        );

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
    }
}
} // end class_exists Gorilla_Email_Smart_Coupon
