<?php
/**
 * Gorilla Loyalty & Gamification - GDPR / KVKK Uyumluluk
 *
 * WordPress Privacy Tools entegrasyonu.
 * XP, tier, badges, streak, gamification verilerini export/erase eder.
 *
 * @package Gorilla_Loyalty_Gamification
 * @since   3.1.0
 */

if (!defined('ABSPATH')) exit;

// ── Privacy Data Exporter ───────────────────────────────
add_filter('wp_privacy_personal_data_exporters', function($exporters) {
    $exporters['gorilla-loyalty-gamification'] = array(
        'exporter_friendly_name' => 'Gorilla Loyalty & Gamification',
        'callback'               => 'gorilla_lg_gdpr_export_data',
    );
    return $exporters;
});

function gorilla_lg_gdpr_export_data($email_address, $page = 1) {
    $user = get_user_by('email', $email_address);
    $export_items = array();

    if (!$user) {
        return array('data' => $export_items, 'done' => true);
    }

    $user_id = $user->ID;
    global $wpdb;

    // XP/Level/Streak export: WP Gamify GDPR handles this via its own exporter.

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

    // Gamification verileri (streak is now in WP Gamify)
    $streak_data = class_exists('WPGamify_Streak_Manager') ? WPGamify_Streak_Manager::get_streak($user_id) : array();
    $login_streak      = intval($streak_data['current_streak'] ?? 0);
    $login_streak_best = intval($streak_data['max_streak'] ?? 0);
    $spin_available    = intval(get_user_meta($user_id, '_gorilla_spin_available', true));
    $login_last_date   = $streak_data['last_activity_date'] ?? '';
    $last_tier         = get_user_meta($user_id, '_gorilla_last_tier', true);

    $spin_history = get_user_meta($user_id, '_gorilla_spin_history', true);
    $spin_history_count = is_array($spin_history) ? count($spin_history) : 0;

    $milestones = get_user_meta($user_id, '_gorilla_milestones', true);
    $milestones_completed = array();
    if (is_array($milestones)) {
        foreach ($milestones as $mv) {
            if (!empty($mv)) {
                $milestones_completed[] = is_array($mv) ? ($mv['id'] ?? $mv) : $mv;
            }
        }
    }

    $social_shares = get_user_meta($user_id, '_gorilla_social_shares', true);
    $social_summary = '';
    if (is_array($social_shares)) {
        $parts = array();
        foreach ($social_shares as $platform => $data) {
            $total = is_array($data) ? intval($data['total'] ?? 0) : intval($data);
            $parts[] = $platform . ': ' . $total;
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

    // SMS preferences
    $sms_phone  = get_user_meta($user_id, '_gorilla_sms_phone', true);
    $sms_optout = get_user_meta($user_id, '_gorilla_sms_optout', true);
    if ($sms_phone || $sms_optout) {
        $export_items[] = array(
            'group_id'    => 'gorilla-sms',
            'group_label' => 'SMS Tercihleri',
            'item_id'     => 'sms-' . $user_id,
            'data'        => array(
                array('name' => 'SMS Telefon',   'value' => $sms_phone ?: 'Belirtilmedi'),
                array('name' => 'SMS Durumu',    'value' => $sms_optout === 'yes' ? 'Devre disi' : 'Aktif'),
            ),
        );
    }

    // Store Credit balance
    $credit_balance = get_user_meta($user_id, '_gorilla_store_credit', true);
    if ($credit_balance) {
        $export_items[] = array(
            'group_id'          => 'gorilla-credit',
            'group_label'       => 'Store Credit Bilgileri',
            'group_description' => 'Store credit bakiye bilgileriniz.',
            'item_id'           => 'credit-balance-' . $user_id,
            'data'              => array(
                array('name' => 'Store Credit Bakiyesi', 'value' => number_format(floatval($credit_balance), 2) . ' TL'),
            ),
        );
    }

    // Credit log
    $credit_table = $wpdb->prefix . 'gorilla_credit_log';
    if (function_exists('gorilla_lr_table_exists') && gorilla_lr_table_exists($credit_table)) {
        $credit_logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$credit_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 100",
            $user_id
        ));
        foreach ($credit_logs as $clog) {
            $export_items[] = array(
                'group_id'    => 'gorilla-credit-log',
                'group_label' => 'Store Credit Islem Gecmisi',
                'item_id'     => 'credit-log-' . $clog->id,
                'data'        => array(
                    array('name' => 'Tarih',    'value' => $clog->created_at),
                    array('name' => 'Tutar',    'value' => number_format(floatval($clog->amount), 2) . ' TL'),
                    array('name' => 'Tur',      'value' => $clog->type),
                    array('name' => 'Aciklama', 'value' => $clog->reason),
                ),
            );
        }
    }

    // Challenge progress
    $challenges = get_user_meta($user_id, '_gorilla_challenges_progress', true);
    if (!empty($challenges) && is_array($challenges)) {
        $export_items[] = array(
            'group_id'    => 'gorilla-loyalty',
            'group_label' => 'Gorilla Loyalty',
            'item_id'     => 'gorilla-challenges',
            'data'        => array(
                array('name' => __('Challenge Progress', 'gorilla-loyalty'), 'value' => wp_json_encode($challenges)),
            ),
        );
    }

    // Notifications
    $notifications = get_user_meta($user_id, '_gorilla_notifications', true);
    if (!empty($notifications) && is_array($notifications)) {
        $export_items[] = array(
            'group_id'    => 'gorilla-loyalty',
            'group_label' => 'Gorilla Loyalty',
            'item_id'     => 'gorilla-notifications',
            'data'        => array(
                array('name' => __('Notifications', 'gorilla-loyalty'), 'value' => wp_json_encode(array_slice($notifications, 0, 50))),
            ),
        );
    }

    // Credit transfer logs
    $transfer_log = get_user_meta($user_id, '_gorilla_transfer_log', true);
    if (!empty($transfer_log)) {
        $export_items[] = array(
            'group_id'    => 'gorilla-loyalty',
            'group_label' => 'Gorilla Loyalty',
            'item_id'     => 'gorilla-transfer-log',
            'data'        => array(
                array('name' => __('Transfer Log (Sent)', 'gorilla-loyalty'), 'value' => wp_json_encode($transfer_log)),
            ),
        );
    }

    $received_log = get_user_meta($user_id, '_gorilla_transfer_received_log', true);
    if (!empty($received_log)) {
        $export_items[] = array(
            'group_id'    => 'gorilla-loyalty',
            'group_label' => 'Gorilla Loyalty',
            'item_id'     => 'gorilla-transfer-received',
            'data'        => array(
                array('name' => __('Transfer Log (Received)', 'gorilla-loyalty'), 'value' => wp_json_encode($received_log)),
            ),
        );
    }

    // Churn data
    $churn_risk = get_user_meta($user_id, '_gorilla_churn_risk', true);
    $churn_last = get_user_meta($user_id, '_gorilla_churn_last_order', true);
    if ($churn_risk !== '' || $churn_last !== '') {
        $export_items[] = array(
            'group_id'    => 'gorilla-loyalty',
            'group_label' => 'Gorilla Loyalty',
            'item_id'     => 'gorilla-churn',
            'data'        => array(
                array('name' => __('Churn Risk Score', 'gorilla-loyalty'), 'value' => $churn_risk ?: '0'),
                array('name' => __('Last Order Date', 'gorilla-loyalty'), 'value' => $churn_last ?: '-'),
            ),
        );
    }

    // Tier tracking
    $tier_key = get_user_meta($user_id, '_gorilla_lr_tier_key', true);
    $grace_until = get_user_meta($user_id, '_gorilla_tier_grace_until', true);
    if ($tier_key || $grace_until) {
        $export_items[] = array(
            'group_id'    => 'gorilla-loyalty',
            'group_label' => 'Gorilla Loyalty',
            'item_id'     => 'gorilla-tier-tracking',
            'data'        => array(
                array('name' => __('Tier Key', 'gorilla-loyalty'), 'value' => $tier_key ?: 'none'),
                array('name' => __('Grace Period Until', 'gorilla-loyalty'), 'value' => $grace_until ?: '-'),
            ),
        );
    }

    return array('data' => $export_items, 'done' => true);
}


// ── Privacy Data Eraser ─────────────────────────────────
add_filter('wp_privacy_personal_data_erasers', function($erasers) {
    $erasers['gorilla-loyalty-gamification'] = array(
        'eraser_friendly_name' => 'Gorilla Loyalty & Gamification',
        'callback'             => 'gorilla_lg_gdpr_erase_data',
    );
    return $erasers;
});

function gorilla_lg_gdpr_erase_data($email_address, $page = 1) {
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

    // XP/Level/Streak erasure: WP Gamify GDPR handles these via its own eraser.

    // Gamification user meta temizligi (Gorilla-owned meta only)
    $meta_keys = array(
        '_gorilla_birthday',
        '_gorilla_badges', '_gorilla_spin_available', '_gorilla_spin_history',
        '_gorilla_milestones', '_gorilla_social_shares', '_gorilla_referred_by',
        '_gorilla_last_tier', '_gorilla_lr_tier_key',
        '_gorilla_tier_grace_until', '_gorilla_tier_grace_from',
        '_gorilla_challenges_progress',
        '_gorilla_notifications',
        '_gorilla_transfer_today_total',
        '_gorilla_transfer_today_date',
        '_gorilla_transfer_log',
        '_gorilla_transfer_received_log',
        '_gorilla_churn_risk',
        '_gorilla_churn_last_order',
        '_gorilla_sms_phone',
        '_gorilla_sms_optout',
    );
    foreach ($meta_keys as $mk) {
        if (get_user_meta($user_id, $mk, true) !== '') {
            delete_user_meta($user_id, $mk);
            $items_removed++;
        }
    }

    // Birthday guard key'lerini temizle (_gorilla_birthday_awarded_YYYY)
    $birthday_deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE '_gorilla_birthday_awarded_%%'",
        $user_id
    ));
    if ($birthday_deleted) $items_removed += $birthday_deleted;

    // Milestone guard key'lerini temizle
    $milestone_deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE '_gorilla_milestone_done_%%'",
        $user_id
    ));
    if ($milestone_deleted) $items_removed += $milestone_deleted;

    // Grace period warning guard key'lerini temizle
    $grace_warned_deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE '_gorilla_grace_warned_%%'",
        $user_id
    ));
    if ($grace_warned_deleted) $items_removed += $grace_warned_deleted;

    // Anniversary year guard key'lerini temizle
    $anniv_deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE '_gorilla_anniversary_year_%%'",
        $user_id
    ));
    if ($anniv_deleted) $items_removed += $anniv_deleted;

    // Churn re-engagement guard key'lerini temizle
    $churn_deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE '_gorilla_churn_reengaged_%%'",
        $user_id
    ));
    if ($churn_deleted) $items_removed += $churn_deleted;

    // Social share daily guard keys
    $share_deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
        $user_id, $wpdb->esc_like('_gorilla_share_') . '%'
    ));
    if ($share_deleted) $items_removed += $share_deleted;

    // XP expiry guard keys: WP Gamify GDPR handles _wpgamify_xp_expiry_* and _wpgamify_xp_warn_*
    // Fallback cleanup if WP Gamify is inactive
    if (!defined('WPGAMIFY_VERSION')) {
        $xp_expiry_deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
            $user_id, $wpdb->esc_like('_wpgamify_xp_expiry_') . '%'
        ));
        if ($xp_expiry_deleted) $items_removed += $xp_expiry_deleted;

        $xp_warn_deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
            $user_id, $wpdb->esc_like('_wpgamify_xp_warn_') . '%'
        ));
        if ($xp_warn_deleted) $items_removed += $xp_warn_deleted;
    }

    // Smart coupon guard key'lerini temizle
    $smart_deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE '_gorilla_smart_coupon_%%'",
        $user_id
    ));
    if ($smart_deleted) $items_removed += $smart_deleted;

    // Transfer daily total key'lerini temizle
    $transfer_deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE '_gorilla_transfer_total_%%'",
        $user_id
    ));
    if ($transfer_deleted) $items_removed += $transfer_deleted;

    // Store Credit balance
    if (get_user_meta($user_id, '_gorilla_store_credit', true)) {
        delete_user_meta($user_id, '_gorilla_store_credit');
        $items_removed++;
    }

    // Credit log entries
    $credit_table = $wpdb->prefix . 'gorilla_credit_log';
    if (function_exists('gorilla_lr_table_exists') && gorilla_lr_table_exists($credit_table)) {
        $credit_deleted = $wpdb->delete($credit_table, array('user_id' => $user_id), array('%d'));
        if ($credit_deleted) $items_removed += $credit_deleted;
    }

    // Legacy credit log meta
    delete_user_meta($user_id, '_gorilla_credit_log');

    // Kullaniciya ozel transient'leri temizle
    delete_user_meta($user_id, '_gorilla_spending_cache');
    delete_transient('gorilla_lr_bar_' . $user_id);

    return array(
        'items_removed'  => $items_removed,
        'items_retained' => 0,
        'messages'       => array(),
        'done'           => true,
    );
}


// ── Privacy Policy Suggested Text ───────────────────────
add_action('admin_init', function() {
    if (!function_exists('wp_add_privacy_policy_content')) return;

    $content = '
<h3>Gorilla Loyalty & Gamification Programi</h3>

<p>Sitemizde sadakat ve gamification programi kapsaminda asagidaki kisisel verileriniz islenmektedir:</p>

<ul>
<li><strong>XP & Level Bilgileri:</strong> Alisverisleriniz, yorumlariniz ve diger etkinlikleriniz sonucunda kazandiginiz XP puanlari ve seviye bilgileriniz saklanir.</li>
<li><strong>Sadakat Seviyeleri:</strong> Harcamalariniza gore hesaplanan sadakat seviyeniz ve indirim oranlariniz kayit altina alinir.</li>
<li><strong>Gamification Verileri:</strong> Giris serisi, rozetler, kilometre taslari, cark hakki, sosyal paylasimlar gibi oyunlastirma verileri saklanir.</li>
<li><strong>Dogum Tarihi:</strong> Dogum gunu odulleri icin girdiginiz dogum tarihiniz saklanir.</li>
<li><strong>SMS Tercihleri:</strong> SMS bildirimleri icin sakladiginiz telefon numaraniz ve tercihleriniz.</li>
<li><strong>Store Credit:</strong> Hesabinizdaki store credit bakiyesi ve islem gecmisiniz (kazanma, harcama, transfer) saklanir.</li>
<li><strong>Challenge Ilerlemesi:</strong> Tamamladiginiz veya devam eden challenge gorevlerinizin ilerleme verileri saklanir.</li>
<li><strong>Kredi Transfer Kayitlari:</strong> Diger kullanicilara gonderdiginiz veya aldiginiz store credit transfer gecmisiniz kayit altina alinir.</li>
<li><strong>Churn Risk Skorlama:</strong> Alisveris sikligi ve son siparis tarihinize gore otomatik olarak hesaplanan churn risk skorunuz saklanir.</li>
<li><strong>Bildirim Gecmisi:</strong> Hesabinizla ilgili sistem bildirimleri gecici olarak saklanir.</li>
</ul>

<p>Bu veriler, sadakat ve gamification programi hizmetlerinin sunulmasi amaciyla islenmektedir. Kisisel verilerinizin silinmesini veya disa aktarilmasini WordPress gizlilik araclari uzerinden talep edebilirsiniz.</p>
';

    wp_add_privacy_policy_content('Gorilla Loyalty & Gamification', $content);
});
