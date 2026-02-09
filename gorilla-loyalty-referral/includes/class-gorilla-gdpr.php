<?php
/**
 * Gorilla LR - GDPR / KVKK Uyumluluk Modulu
 * WordPress Privacy Tools entegrasyonu
 *
 * @version 3.0.0
 */

if (!defined('ABSPATH')) exit;

// -- Privacy Data Exporter ---
add_filter('wp_privacy_personal_data_exporters', function($exporters) {
    $exporters['gorilla-loyalty-referral'] = array(
        'exporter_friendly_name' => 'Gorilla Loyalty & Referral',
        'callback'               => 'gorilla_gdpr_export_data',
    );
    return $exporters;
});

function gorilla_gdpr_export_data($email_address, $page = 1) {
    $user = get_user_by('email', $email_address);
    $export_items = array();

    if (!$user) {
        return array('data' => $export_items, 'done' => true);
    }

    $user_id = $user->ID;

    // Store Credit bakiye
    $credit_balance = get_user_meta($user_id, '_gorilla_store_credit', true);
    if ($credit_balance) {
        $export_items[] = array(
            'group_id'          => 'gorilla-credit',
            'group_label'       => 'Store Credit Bilgileri',
            'group_description' => 'Gorilla Loyalty programindaki store credit bilgileriniz.',
            'item_id'           => 'credit-balance-' . $user_id,
            'data'              => array(
                array('name' => 'Store Credit Bakiyesi', 'value' => number_format(floatval($credit_balance), 2) . ' TL'),
            ),
        );
    }

    // Credit log
    global $wpdb;
    $credit_table = $wpdb->prefix . 'gorilla_credit_log';
    if (gorilla_lr_table_exists($credit_table)) {
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$credit_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 100",
            $user_id
        ));

        foreach ($logs as $log) {
            $export_items[] = array(
                'group_id'    => 'gorilla-credit-log',
                'group_label' => 'Store Credit Islem Gecmisi',
                'item_id'     => 'credit-log-' . $log->id,
                'data'        => array(
                    array('name' => 'Tarih',  'value' => $log->created_at),
                    array('name' => 'Tutar',  'value' => number_format(floatval($log->amount), 2) . ' TL'),
                    array('name' => 'Tur',    'value' => $log->type),
                    array('name' => 'Aciklama', 'value' => $log->reason),
                ),
            );
        }
    }

    // XP Log Export
    $xp_table = $wpdb->prefix . 'gorilla_xp_log';
    if (gorilla_lr_table_exists($xp_table)) {
        $xp_logs = $wpdb->get_results($wpdb->prepare(
            "SELECT amount, reason, reference_type, created_at FROM {$xp_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 200",
            $user->ID
        ));
        foreach ($xp_logs as $xlog) {
            $export_items[] = array(
                'group_id'    => 'gorilla-xp-log',
                'group_label' => 'Gorilla LR - XP Gecmisi',
                'item_id'     => 'gorilla-xp-log-' . $xlog->created_at,
                'data'        => array(
                    array('name' => 'Miktar', 'value' => $xlog->amount),
                    array('name' => 'Aciklama', 'value' => $xlog->reason),
                    array('name' => 'Tur', 'value' => $xlog->reference_type),
                    array('name' => 'Tarih', 'value' => $xlog->created_at),
                ),
            );
        }
    }

    // XP bilgileri
    $total_xp = get_user_meta($user_id, '_gorilla_total_xp', true);
    if ($total_xp) {
        $level = function_exists('gorilla_xp_calculate_level') ? gorilla_xp_calculate_level($user_id) : null;
        $export_items[] = array(
            'group_id'    => 'gorilla-xp',
            'group_label' => 'XP & Level Bilgileri',
            'item_id'     => 'xp-' . $user_id,
            'data'        => array(
                array('name' => 'Toplam XP', 'value' => intval($total_xp)),
                array('name' => 'Level', 'value' => $level ? ($level['emoji'] . ' ' . $level['label']) : 'Bilinmiyor'),
            ),
        );
    }

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

    // Birthday
    $birthday = get_user_meta($user_id, '_gorilla_birthday', true);
    if ($birthday) {
        $export_items[] = array(
            'group_id'    => 'gorilla-birthday',
            'group_label' => 'Dogum Tarihi Bilgileri',
            'item_id'     => 'birthday-' . $user_id,
            'data'        => array(
                array('name' => 'Dogum Tarihi', 'value' => $birthday),
            ),
        );
    }

    // Gamification verileri (kosulsuz export)
    $login_streak     = intval(get_user_meta($user_id, '_gorilla_login_streak', true));
    $login_streak_best = intval(get_user_meta($user_id, '_gorilla_login_streak_best', true));
    $spin_available   = intval(get_user_meta($user_id, '_gorilla_spin_available', true));
    $login_last_date  = get_user_meta($user_id, '_gorilla_login_last_date', true);
    $last_tier        = get_user_meta($user_id, '_gorilla_last_tier', true);

    $spin_history = get_user_meta($user_id, '_gorilla_spin_history', true);
    $spin_history_count = is_array($spin_history) ? count($spin_history) : 0;

    $milestones = get_user_meta($user_id, '_gorilla_milestones', true);
    $milestones_completed = array();
    if (is_array($milestones)) {
        foreach ($milestones as $mk => $mv) {
            if (!empty($mv)) {
                $milestones_completed[] = $mk;
            }
        }
    }

    $social_shares = get_user_meta($user_id, '_gorilla_social_shares', true);
    $social_summary = '';
    if (is_array($social_shares)) {
        $parts = array();
        foreach ($social_shares as $platform => $count) {
            $parts[] = $platform . ': ' . intval($count);
        }
        $social_summary = implode(', ', $parts);
    }

    $referred_by = get_user_meta($user_id, '_gorilla_referred_by', true);
    $referred_by_name = '';
    if ($referred_by) {
        $referrer_user = get_user_by('id', intval($referred_by));
        $referred_by_name = $referrer_user ? $referrer_user->display_name : ('Kullanici #' . $referred_by);
    }

    $export_items[] = array(
        'group_id'    => 'gorilla-gamification',
        'group_label' => 'Gamification Bilgileri',
        'item_id'     => 'gamification-' . $user_id,
        'data'        => array(
            array('name' => 'Giris Serisi',         'value' => $login_streak),
            array('name' => 'En Iyi Seri',          'value' => $login_streak_best),
            array('name' => 'Cark Hakki',           'value' => $spin_available),
            array('name' => 'Son Giris Tarihi',     'value' => $login_last_date ?: 'Yok'),
            array('name' => 'Cark Gecmisi (adet)',  'value' => $spin_history_count),
            array('name' => 'Tamamlanan Kilometre Taslari', 'value' => !empty($milestones_completed) ? implode(', ', $milestones_completed) : 'Yok'),
            array('name' => 'Sosyal Paylasimlar',   'value' => $social_summary ?: 'Yok'),
            array('name' => 'Referans Eden',         'value' => $referred_by_name ?: 'Yok'),
            array('name' => 'Son Tier',              'value' => $last_tier ?: 'Yok'),
        ),
    );

    // Badges
    $badges = get_user_meta($user_id, '_gorilla_badges', true);
    if (is_array($badges) && !empty($badges)) {
        $badge_names = array();
        $definitions = function_exists('gorilla_badge_get_definitions') ? gorilla_badge_get_definitions() : array();
        foreach ($badges as $bid => $bdata) {
            $badge_names[] = isset($definitions[$bid]) ? $definitions[$bid]['label'] : $bid;
        }
        $export_items[] = array(
            'group_id'    => 'gorilla-badges',
            'group_label' => 'Rozet Bilgileri',
            'item_id'     => 'badges-' . $user_id,
            'data'        => array(
                array('name' => 'Kazanilan Rozetler', 'value' => implode(', ', $badge_names)),
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


// -- Privacy Data Eraser ---
add_filter('wp_privacy_personal_data_erasers', function($erasers) {
    $erasers['gorilla-loyalty-referral'] = array(
        'eraser_friendly_name' => 'Gorilla Loyalty & Referral',
        'callback'             => 'gorilla_gdpr_erase_data',
    );
    return $erasers;
});

function gorilla_gdpr_erase_data($email_address, $page = 1) {
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

    // Store credit bakiyesini sifirla
    if (get_user_meta($user_id, '_gorilla_store_credit', true)) {
        delete_user_meta($user_id, '_gorilla_store_credit');
        $items_removed++;
    }

    // Credit log'larini sil
    $credit_table = $wpdb->prefix . 'gorilla_credit_log';
    if (gorilla_lr_table_exists($credit_table)) {
        $deleted = $wpdb->delete($credit_table, array('user_id' => $user_id), array('%d'));
        if ($deleted) $items_removed += $deleted;
    }

    // XP bilgilerini sil
    if (get_user_meta($user_id, '_gorilla_total_xp', true)) {
        delete_user_meta($user_id, '_gorilla_total_xp');
        $items_removed++;
    }

    // XP log'larini sil
    $xp_table = $wpdb->prefix . 'gorilla_xp_log';
    if (gorilla_lr_table_exists($xp_table)) {
        $deleted = $wpdb->delete($xp_table, array('user_id' => $user_id), array('%d'));
        if ($deleted) $items_removed += $deleted;
    }

    // Affiliate kodunu sil
    if (get_user_meta($user_id, '_gorilla_affiliate_code', true)) {
        delete_user_meta($user_id, '_gorilla_affiliate_code');
        $items_removed++;
    }

    // Affiliate click'leri anonimize et (IP temizle)
    $click_table = $wpdb->prefix . 'gorilla_affiliate_clicks';
    if (gorilla_lr_table_exists($click_table)) {
        $wpdb->update(
            $click_table,
            array('visitor_ip' => '0.0.0.0'),
            array('referrer_user_id' => $user_id),
            array('%s'),
            array('%d')
        );
        $items_removed++;
    }

    // XP transient temizle
    delete_transient('gorilla_xp_' . $user_id);

    // Yeni v3.0 user meta temizligi
    $new_meta_keys = array(
        '_gorilla_birthday', '_gorilla_birthday_awarded_year',
        '_gorilla_login_streak', '_gorilla_login_last_date', '_gorilla_login_streak_best',
        '_gorilla_badges', '_gorilla_spin_available', '_gorilla_spin_history',
        '_gorilla_milestones', '_gorilla_social_shares', '_gorilla_referred_by',
        '_gorilla_last_tier',
    );
    foreach ($new_meta_keys as $mk) {
        if (get_user_meta($user_id, $mk, true) !== '') {
            delete_user_meta($user_id, $mk);
            $items_removed++;
        }
    }

    // Milestone guard key'lerini temizle
    $milestone_deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE '_gorilla_milestone_done_%%'",
        $user_id
    ));
    if ($milestone_deleted) $items_removed += $milestone_deleted;

    // Birthday guard key'lerini temizle
    $birthday_guard_deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE '_gorilla_birthday_awarded_%%'",
        $user_id
    ));
    if ($birthday_guard_deleted) $items_removed += $birthday_guard_deleted;

    // Referral postlarini sil (meta _ref_user_id ile eslestir - export ile tutarli)
    $referral_posts = $wpdb->get_col($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_ref_user_id' AND pm.meta_value = %s WHERE p.post_type = 'gorilla_referral'",
        $user_id
    ));
    foreach ($referral_posts as $pid) {
        wp_delete_post(intval($pid), true);
        $items_removed++;
    }

    // Kullaniciya ozel transient'leri temizle
    delete_transient('gorilla_spending_' . $user_id);
    delete_transient('gorilla_lr_bar_' . $user_id);

    return array(
        'items_removed'  => $items_removed,
        'items_retained' => 0,
        'messages'       => array(),
        'done'           => true,
    );
}


// -- Privacy Policy Suggested Text ---
add_action('admin_init', function() {
    if (!function_exists('wp_add_privacy_policy_content')) return;

    $content = '
<h3>Gorilla Loyalty & Referral Programi</h3>

<p>Sitemizde sadakat ve referans programi kapsaminda asagidaki kisisel verileriniz islenmektedir:</p>

<ul>
<li><strong>Store Credit Bilgileri:</strong> Hesabinizdaki store credit bakiyesi ve islem gecmisi kayit altina alinir.</li>
<li><strong>XP & Level Bilgileri:</strong> Alisverisleriniz, yorumlariniz ve diger etkinlikleriniz sonucunda kazandiginiz XP puanlari ve seviye bilgileriniz saklanir.</li>
<li><strong>Referans Basvurulari:</strong> Video referans basvurulariniz (video URL, platform bilgisi, siparis detaylari) kayit altina alinir.</li>
<li><strong>Affiliate Bilgileri:</strong> Affiliate kodunuz, link tiklamalari ve komisyon kazanclariniz saklanir.</li>
<li><strong>IP Adresi:</strong> Affiliate link tiklamalarinda spam onleme amaciyla IP adresiniz kaydedilir.</li>
</ul>

<p>Bu veriler, sadakat programi hizmetlerinin sunulmasi ve programin isletilmesi amaciyla islenmektedir. Kisisel verilerinizin silinmesini veya disa aktarilmasini WordPress gizlilik araclari uzerinden talep edebilirsiniz.</p>
';

    wp_add_privacy_policy_content('Gorilla Loyalty & Referral', $content);
});
