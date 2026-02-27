<?php
/**
 * Gorilla RA - GDPR / KVKK Uyumluluk Modulu
 * WordPress Privacy Tools entegrasyonu
 * Referral & Affiliate verilerini export/erase
 *
 * @package Gorilla_Referral_Affiliate
 */

if (!defined('ABSPATH')) exit;

// -- Privacy Data Exporter --
add_filter('wp_privacy_personal_data_exporters', function($exporters) {
    $exporters['gorilla-referral-affiliate'] = array(
        'exporter_friendly_name' => 'Gorilla Referral & Affiliate',
        'callback'               => 'gorilla_ra_gdpr_export_data',
    );
    return $exporters;
});

function gorilla_ra_gdpr_export_data($email_address, $page = 1) {
    $user = get_user_by('email', $email_address);
    $export_items = array();

    if (!$user) {
        return array('data' => $export_items, 'done' => true);
    }

    $user_id = $user->ID;
    global $wpdb;

    // Affiliate kodu
    $affiliate_code = get_user_meta($user_id, '_gorilla_affiliate_code', true);
    if ($affiliate_code) {
        $export_items[] = array(
            'group_id'    => 'gorilla-affiliate',
            'group_label' => 'Affiliate Bilgileri',
            'item_id'     => 'affiliate-' . $user_id,
            'data'        => array(
                array('name' => 'Affiliate Kodu', 'value' => $affiliate_code),
            ),
        );
    }

    // Affiliate click gecmisi
    $click_table = $wpdb->prefix . 'gorilla_affiliate_clicks';
    if (function_exists('gorilla_lr_table_exists') && gorilla_lr_table_exists($click_table)) {
        $clicks = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$click_table} WHERE referrer_user_id = %d ORDER BY clicked_at DESC LIMIT 100",
            $user_id
        ));

        foreach ($clicks as $click) {
            $export_items[] = array(
                'group_id'    => 'gorilla-affiliate-clicks',
                'group_label' => 'Affiliate Tiklama Gecmisi',
                'item_id'     => 'click-' . $click->id,
                'data'        => array(
                    array('name' => 'Tarih',       'value' => $click->clicked_at),
                    array('name' => 'IP Adresi',   'value' => $click->visitor_ip),
                    array('name' => 'Donusum',     'value' => $click->converted ? 'Evet' : 'Hayir'),
                    array('name' => 'Siparis ID',  'value' => $click->order_id ? '#' . $click->order_id : '-'),
                ),
            );
        }
    }

    // Referred by bilgisi
    $referred_by = get_user_meta($user_id, '_gorilla_referred_by', true);
    if ($referred_by) {
        $referrer_user = get_user_by('id', intval($referred_by));
        $referred_by_name = $referrer_user ? $referrer_user->display_name : ('Kullanici #' . $referred_by);
        $export_items[] = array(
            'group_id'    => 'gorilla-referral-info',
            'group_label' => 'Referans Bilgileri',
            'item_id'     => 'referred-by-' . $user_id,
            'data'        => array(
                array('name' => 'Referans Eden', 'value' => $referred_by_name),
            ),
        );
    }

    // Referans basvurulari
    $referrals = get_posts(array(
        'post_type'   => 'gorilla_referral',
        'post_status' => array('pending', 'grla_approved', 'grla_rejected'),
        'meta_key'    => '_ref_user_id',
        'meta_value'  => $user_id,
        'numberposts' => 100,
    ));

    foreach ($referrals as $ref) {
        $status_map = array('pending' => 'Bekliyor', 'grla_approved' => 'Onaylandi', 'grla_rejected' => 'Reddedildi');
        $export_items[] = array(
            'group_id'    => 'gorilla-referrals',
            'group_label' => 'Referans Basvurulari',
            'item_id'     => 'referral-' . $ref->ID,
            'data'        => array(
                array('name' => 'Basvuru ID', 'value' => $ref->ID),
                array('name' => 'Siparis',    'value' => '#' . get_post_meta($ref->ID, '_ref_order_id', true)),
                array('name' => 'Platform',   'value' => get_post_meta($ref->ID, '_ref_platform', true)),
                array('name' => 'Video URL',  'value' => get_post_meta($ref->ID, '_ref_video_url', true)),
                array('name' => 'Durum',      'value' => $status_map[get_post_status($ref->ID)] ?? get_post_status($ref->ID)),
                array('name' => 'Tarih',      'value' => get_the_date('Y-m-d H:i', $ref->ID)),
            ),
        );
    }

    return array('data' => $export_items, 'done' => true);
}


// -- Privacy Data Eraser --
add_filter('wp_privacy_personal_data_erasers', function($erasers) {
    $erasers['gorilla-referral-affiliate'] = array(
        'eraser_friendly_name' => 'Gorilla Referral & Affiliate',
        'callback'             => 'gorilla_ra_gdpr_erase_data',
    );
    return $erasers;
});

function gorilla_ra_gdpr_erase_data($email_address, $page = 1) {
    $user = get_user_by('email', $email_address);
    $items_removed = 0;

    if (!$user) {
        return array(
            'items_removed'  => 0,
            'items_retained' => 0,
            'messages'       => array(),
            'done'           => true,
        );
    }

    $user_id = $user->ID;
    global $wpdb;

    // Affiliate kodunu sil
    if (get_user_meta($user_id, '_gorilla_affiliate_code', true)) {
        delete_user_meta($user_id, '_gorilla_affiliate_code');
        $items_removed++;
    }

    // Affiliate click'leri anonimize et (IP temizle)
    $click_table = $wpdb->prefix . 'gorilla_affiliate_clicks';
    if (function_exists('gorilla_lr_table_exists') && gorilla_lr_table_exists($click_table)) {
        $wpdb->update(
            $click_table,
            array('visitor_ip' => '0.0.0.0'),
            array('referrer_user_id' => $user_id),
            array('%s'),
            array('%d')
        );
        $items_removed++;
    }

    // Referred by meta
    if (get_user_meta($user_id, '_gorilla_referred_by', true) !== '') {
        delete_user_meta($user_id, '_gorilla_referred_by');
        $items_removed++;
    }

    // Affiliate fraud detection key'lerini temizle
    $fraud_deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE '_gorilla_affiliate_fraud_%%'",
        $user_id
    ));
    if ($fraud_deleted) $items_removed += $fraud_deleted;

    // Referral postlarini sil
    $referral_posts = $wpdb->get_col($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_ref_user_id' AND pm.meta_value = %s WHERE p.post_type = 'gorilla_referral'",
        $user_id
    ));
    foreach ($referral_posts as $pid) {
        wp_delete_post(intval($pid), true);
        $items_removed++;
    }

    return array(
        'items_removed'  => $items_removed,
        'items_retained' => 0,
        'messages'       => array(),
        'done'           => true,
    );
}


// -- Privacy Policy Suggested Text --
add_action('admin_init', function() {
    if (!function_exists('wp_add_privacy_policy_content')) return;

    $content = '
<h3>Gorilla Referral & Affiliate Programi</h3>

<p>Sitemizde referans ve affiliate programi kapsaminda asagidaki kisisel verileriniz islenmektedir:</p>

<ul>
<li><strong>Referans Basvurulari:</strong> Video referans basvurulariniz (video URL, platform bilgisi, siparis detaylari) kayit altina alinir.</li>
<li><strong>Affiliate Bilgileri:</strong> Affiliate kodunuz, link tiklamalari ve komisyon kazanclariniz saklanir.</li>
<li><strong>IP Adresi:</strong> Affiliate link tiklamalarinda spam onleme amaciyla IP adresiniz kaydedilir.</li>
</ul>

<p>Bu veriler, referans ve affiliate programi hizmetlerinin sunulmasi ve programin isletilmesi amaciyla islenmektedir. Kisisel verilerinizin silinmesini veya disa aktarilmasini WordPress gizlilik araclari uzerinden talep edebilirsiniz.</p>
';

    wp_add_privacy_policy_content('Gorilla Referral & Affiliate', $content);
});
