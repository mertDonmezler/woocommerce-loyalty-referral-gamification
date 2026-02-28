<?php
/**
 * Gorilla Loyalty & Gamification - Ayarlar Modulu
 * Admin panelden tum degerleri degistirebilme
 *
 * @package Gorilla_Loyalty_Gamification
 * @since   3.1.0
 */

if (!defined('ABSPATH')) exit;

// ── Ayar Kaydetme ────────────────────────────────────────
// NOT: Menü kaydı class-gorilla-admin.php'de yapılıyor (spec uyumu)
add_action('admin_init', function() {
    if (!isset($_POST['gorilla_save_settings']) || !current_user_can('manage_woocommerce')) return;
    if (!wp_verify_nonce($_POST['_gorilla_settings_nonce'] ?? '', 'gorilla_save_settings')) return;

    // Yes/No dogrulama yardimcisi
    $validate_yesno = function($val) { return in_array($val, array('yes', 'no'), true) ? $val : 'no'; };

    // Genel ayarlar
    update_option('gorilla_lr_enabled_loyalty', $validate_yesno($_POST['enabled_loyalty'] ?? 'no'));
    update_option('gorilla_lr_period_months', max(1, min(24, intval($_POST['period_months'] ?? 6))));
    update_option('gorilla_lr_tier_grace_days', max(0, min(90, intval($_POST['tier_grace_days'] ?? 0))));

    // XP & Level, XP Expiry, Category XP, Seasonal Bonus, Login Streak:
    // All managed by WP Gamify v2.0.0 now. Settings removed from Gorilla.

    // Gamification: Anniversary (credit only - XP managed by WP Gamify)
    update_option('gorilla_lr_anniversary_enabled', $validate_yesno($_POST['anniversary_enabled'] ?? 'no'));
    update_option('gorilla_lr_anniversary_credit', max(0, min(200, floatval($_POST['anniversary_credit'] ?? 20))));

    // Gamification: Birthday (credit only - XP managed by WP Gamify)
    update_option('gorilla_lr_birthday_enabled', $validate_yesno($_POST['birthday_enabled'] ?? 'no'));
    update_option('gorilla_lr_birthday_credit', max(0, min(100, floatval($_POST['birthday_credit'] ?? 10))));

    // Gamification: Badges
    update_option('gorilla_lr_badges_enabled', $validate_yesno($_POST['badges_enabled'] ?? 'no'));

    // Gamification: Leaderboard
    update_option('gorilla_lr_leaderboard_enabled', $validate_yesno($_POST['leaderboard_enabled'] ?? 'no'));
    update_option('gorilla_lr_leaderboard_anonymize', $validate_yesno($_POST['leaderboard_anonymize'] ?? 'no'));
    update_option('gorilla_lr_leaderboard_limit', max(5, min(50, intval($_POST['leaderboard_limit'] ?? 10))));

    // Gamification: Milestones
    update_option('gorilla_lr_milestones_enabled', $validate_yesno($_POST['milestones_enabled'] ?? 'no'));

    // Milestones Repeater
    if (isset($_POST['milestone_id']) && is_array($_POST['milestone_id'])) {
        $milestones = array();
        foreach ($_POST['milestone_id'] as $i => $mid) {
            $mid = sanitize_key($mid);
            if (empty($mid)) continue;
            $milestones[] = array(
                'id'            => $mid,
                'label'         => sanitize_text_field($_POST['milestone_label'][$i] ?? ''),
                'emoji'         => sanitize_text_field($_POST['milestone_emoji'][$i] ?? ''),
                'description'   => sanitize_text_field($_POST['milestone_desc'][$i] ?? ''),
                'type'          => sanitize_key($_POST['milestone_type'][$i] ?? 'total_orders'),
                'target'        => max(1, floatval($_POST['milestone_target'][$i] ?? 1)),
                'xp_reward'     => max(0, intval($_POST['milestone_xp_reward'][$i] ?? 0)),
                'credit_reward' => max(0, floatval($_POST['milestone_credit_reward'][$i] ?? 0)),
            );
        }
        update_option('gorilla_lr_milestones', $milestones);
    }

    // Gamification: Challenges
    if (function_exists('gorilla_challenges_save_settings')) {
        gorilla_challenges_save_settings();
    }

    // Spin Wheel
    update_option('gorilla_lr_spin_enabled', $validate_yesno($_POST['spin_enabled'] ?? 'no'));

    // Spin Wheel Prizes Repeater
    if (isset($_POST['spin_label']) && is_array($_POST['spin_label'])) {
        $prizes = array();
        foreach ($_POST['spin_label'] as $i => $label) {
            $label = sanitize_text_field($label);
            if (empty($label)) continue;
            $prizes[] = array(
                'label'  => $label,
                'type'   => sanitize_key($_POST['spin_type'][$i] ?? 'nothing'),
                'value'  => max(0, floatval($_POST['spin_value'][$i] ?? 0)),
                'weight' => max(1, intval($_POST['spin_weight'][$i] ?? 1)),
            );
        }
        update_option('gorilla_lr_spin_prizes', $prizes);
    }

    // Points Shop
    update_option('gorilla_lr_points_shop_enabled', $validate_yesno($_POST['points_shop_enabled'] ?? 'no'));

    // Points Shop Rewards Repeater
    if (isset($_POST['shop_id']) && is_array($_POST['shop_id'])) {
        $rewards = array();
        foreach ($_POST['shop_id'] as $i => $rid) {
            $rid = sanitize_key($rid);
            if (empty($rid)) continue;
            $rewards[] = array(
                'id'            => $rid,
                'label'         => sanitize_text_field($_POST['shop_label'][$i] ?? ''),
                'xp_cost'       => max(1, intval($_POST['shop_xp_cost'][$i] ?? 100)),
                'type'          => sanitize_key($_POST['shop_type'][$i] ?? 'coupon'),
                'coupon_type'   => sanitize_key($_POST['shop_coupon_type'][$i] ?? 'fixed_cart'),
                'coupon_amount' => max(0, floatval($_POST['shop_coupon_amount'][$i] ?? 0)),
            );
        }
        update_option('gorilla_lr_points_shop_rewards', $rewards);
    }

    // Social Share
    update_option('gorilla_lr_social_share_enabled', $validate_yesno($_POST['social_share_enabled'] ?? 'no'));
    update_option('gorilla_lr_social_share_xp', max(0, min(100, intval($_POST['social_share_xp'] ?? 10))));

    // QR
    update_option('gorilla_lr_qr_enabled', $validate_yesno($_POST['qr_enabled'] ?? 'no'));

    // Credit Transfer
    update_option('gorilla_lr_transfer_enabled', $validate_yesno($_POST['transfer_enabled'] ?? 'no'));
    update_option('gorilla_lr_transfer_daily_limit', max(0, floatval($_POST['transfer_daily_limit'] ?? 500)));
    update_option('gorilla_lr_transfer_min_amount', max(1, floatval($_POST['transfer_min_amount'] ?? 10)));
    update_option('gorilla_lr_transfer_fee_pct', max(0, min(50, intval($_POST['transfer_fee_pct'] ?? 0))));

    // Churn Prediction
    update_option('gorilla_lr_churn_enabled', $validate_yesno($_POST['churn_enabled'] ?? 'no'));
    update_option('gorilla_lr_churn_months', max(1, min(12, intval($_POST['churn_months'] ?? 3))));
    update_option('gorilla_lr_churn_bonus_credit', max(0, floatval($_POST['churn_bonus_credit'] ?? 25)));
    update_option('gorilla_lr_churn_bonus_xp', max(0, intval($_POST['churn_bonus_xp'] ?? 100)));

    // VIP Early Access
    update_option('gorilla_lr_vip_early_access_enabled', $validate_yesno($_POST['vip_early_access_enabled'] ?? 'no'));

    // Points Transfer
    update_option('gorilla_lr_transfer_enabled', $validate_yesno($_POST['transfer_enabled'] ?? 'no'));
    update_option('gorilla_lr_transfer_daily_limit', max(10, min(100000, floatval($_POST['transfer_daily_limit'] ?? 500))));
    update_option('gorilla_lr_transfer_min_amount', max(1, min(1000, floatval($_POST['transfer_min_amount'] ?? 10))));

    // Smart Coupon
    update_option('gorilla_lr_smart_coupon_enabled', $validate_yesno($_POST['smart_coupon_enabled'] ?? 'no'));
    update_option('gorilla_lr_smart_coupon_inactive_days', max(7, min(90, intval($_POST['smart_coupon_inactive_days'] ?? 21))));
    update_option('gorilla_lr_smart_coupon_discount', max(1, min(50, floatval($_POST['smart_coupon_discount'] ?? 10))));
    update_option('gorilla_lr_smart_coupon_expiry', max(1, min(60, intval($_POST['smart_coupon_expiry'] ?? 14))));

    // Social Proof
    update_option('gorilla_lr_social_proof_enabled', $validate_yesno($_POST['social_proof_enabled'] ?? 'no'));
    update_option('gorilla_lr_social_proof_anonymize', $validate_yesno($_POST['social_proof_anonymize'] ?? 'no'));

    // GA4 & Webhook
    update_option('gorilla_lr_ga4_measurement_id', sanitize_text_field($_POST['ga4_measurement_id'] ?? ''));
    $webhook_url = esc_url_raw($_POST['webhook_url'] ?? '');
    if (!empty($webhook_url) && !wp_http_validate_url($webhook_url)) {
        $webhook_url = '';
    }
    update_option('gorilla_lr_webhook_url', $webhook_url);
    update_option('gorilla_lr_webhook_events', array_map('sanitize_key', $_POST['webhook_events'] ?? array()));

    // SMS / Twilio
    update_option('gorilla_lr_sms_enabled', $validate_yesno($_POST['sms_enabled'] ?? 'no'));
    $twilio_sid = sanitize_text_field($_POST['twilio_sid'] ?? '');
    $twilio_token = sanitize_text_field($_POST['twilio_token'] ?? '');
    update_option('gorilla_lr_twilio_sid', !empty($twilio_sid) && function_exists('gorilla_sms_encrypt') ? gorilla_sms_encrypt($twilio_sid) : $twilio_sid);
    update_option('gorilla_lr_twilio_token', !empty($twilio_token) && function_exists('gorilla_sms_encrypt') ? gorilla_sms_encrypt($twilio_token) : $twilio_token);
    update_option('gorilla_lr_twilio_from', sanitize_text_field($_POST['twilio_from'] ?? ''));
    update_option('gorilla_lr_sms_events', array_map('sanitize_key', $_POST['sms_events'] ?? array()));

    // Store Credit ayarlari
    update_option('gorilla_lr_credit_min_order', max(0, floatval($_POST['credit_min_order'] ?? 0)));
    update_option('gorilla_lr_credit_expiry_days', max(0, intval($_POST['credit_expiry_days'] ?? 0)));
    update_option('gorilla_lr_credit_expiry_warn_days', max(0, min(90, intval($_POST['credit_expiry_warn_days'] ?? 7))));
    update_option('gorilla_lr_coupon_enabled', in_array($_POST['coupon_enabled'] ?? 'no', array('yes', 'no'), true) ? $_POST['coupon_enabled'] : 'no');

    // Level ayarları: WP Gamify v2.0.0 yönetiyor. gorilla_lr_levels artık kullanılmıyor.

    // Seviye ayarları
    $tiers = array();
    $tier_keys = $_POST['tier_key'] ?? array();

    for ($i = 0; $i < count($tier_keys); $i++) {
        $key = sanitize_key($tier_keys[$i]);
        if (empty($key)) continue;

        $tiers[$key] = array(
            'label'         => sanitize_text_field($_POST['tier_label'][$i] ?? ''),
            'min'           => floatval($_POST['tier_min'][$i] ?? 0),
            'discount'      => floatval($_POST['tier_discount'][$i] ?? 0),
            'color'         => sanitize_hex_color($_POST['tier_color'][$i] ?? '#999999'),
            'emoji'         => sanitize_text_field($_POST['tier_emoji'][$i] ?? '🎖️'),
            'installment'   => intval($_POST['tier_installment'][$i] ?? 0),
            'free_shipping' => isset($_POST['tier_free_shipping'][$i]) ? 1 : 0,
        );
    }

    if (!empty($tiers)) {
        // Min harcamaya göre sırala
        uasort($tiers, function($a, $b) { return $a['min'] <=> $b['min']; });
        update_option('gorilla_lr_tiers', $tiers);
    }

    // Cache temizle
    delete_transient('gorilla_lr_tier_stats');

    // Başarılı mesajı
    add_settings_error('gorilla_settings', 'saved', '✅ Ayarlar başarıyla kaydedildi!', 'updated');
});

// ── Ayar Okuma Fonksiyonları ─────────────────────────────
function gorilla_get_setting($key, $default = '') {
    return get_option('gorilla_lr_' . $key, $default);
}

// ── Ayarlar Sayfası Render ───────────────────────────────
function gorilla_settings_page_render() {
    if (!current_user_can('manage_woocommerce')) return;

    $enabled_loyalty  = get_option('gorilla_lr_enabled_loyalty', 'yes');
    $period           = get_option('gorilla_lr_period_months', 6);
    $tiers            = gorilla_get_tiers();

    // XP & Level ayarları artık WP Gamify v2.0.0 tarafından yönetiliyor.

    settings_errors('gorilla_settings');
    ?>
    <div class="wrap">
        <h1>⚙️ Gorilla Loyalty & Gamification - Ayarlar</h1>

        <form method="post" action="">
            <?php wp_nonce_field('gorilla_save_settings', '_gorilla_settings_nonce'); ?>

            <!-- GENEL AYARLAR -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">📋 Genel Ayarlar</h2>
                <table class="form-table">
                    <tr>
                        <th>Sadakat Programı</th>
                        <td>
                            <label><input type="checkbox" name="enabled_loyalty" value="yes" <?php checked($enabled_loyalty, 'yes'); ?>> Aktif</label>
                            <p class="description">Devre dışı bırakırsanız müşterilere seviye indirimi uygulanmaz.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Harcama Dönemi</th>
                        <td>
                            <select name="period_months" style="width:120px;">
                                <?php foreach(array(3,6,9,12) as $m): ?>
                                    <option value="<?php echo $m; ?>" <?php selected($period, $m); ?>><?php echo $m; ?> Ay</option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Müşteri seviyesi bu süre içindeki toplam harcamaya göre hesaplanır.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Seviye Dusme Grace Period</th>
                        <td>
                            <input type="number" name="tier_grace_days" value="<?php echo esc_attr(get_option('gorilla_lr_tier_grace_days', 0)); ?>" min="0" max="90" step="1" style="width:100px;"> gun
                            <p class="description">Musteri harcamasi duserse seviye aninda dusmez, bu sure kadar mevcut seviyeyi korur. 15/7/1 gun kala uyari e-postasi gonderilir. 0 = grace period yok.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- XP & LEVEL AYARLARI - WP Gamify'a taşındı -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">🎮 XP & Level Sistemi</h2>
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:16px 20px; color:#1e40af;">
                    <strong>XP, Level, Streak, XP Suresi ve Kampanya ayarlari artik WP Gamify eklentisi tarafindan yonetilmektedir.</strong>
                    <br><br>
                    <?php
                    $gamify_url = admin_url('admin.php?page=wp-gamify');
                    ?>
                    <a href="<?php echo esc_url($gamify_url); ?>" class="button button-primary" style="margin-top:5px;">WP Gamify Ayarlarina Git &rarr;</a>
                </div>
            </div>

            <!-- ESKi XP AYARLARI BURADAN KALDIRILDI - WP Gamify yonetiyor -->
            <?php /* XP & Level, Category XP, XP Expiry, Level Config, Seasonal Bonus ayarlari
                     artik WP Gamify v2.0.0 eklentisinden yonetiliyor. */ ?>

            <!-- SEVIYE AYARLARI -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">🎖️ Sadakat Seviyeleri</h2>
                <p style="color:#666; margin-bottom:20px;">Seviyeleri, eşikleri ve indirimleri aşağıdan dilediğiniz gibi düzenleyin. Minimum harcamaya göre otomatik sıralanır.</p>

                <table class="widefat" id="gorilla-tiers-table" style="border-collapse:separate; border-spacing:0 8px;">
                    <thead>
                        <tr style="background:#f8f9fa;">
                            <th style="width:40px;">Emoji</th>
                            <th style="width:80px;">Anahtar</th>
                            <th>Seviye Adı</th>
                            <th style="width:130px;">Min. Harcama (₺)</th>
                            <th style="width:100px;">İndirim (%)</th>
                            <th style="width:70px;">Renk</th>
                            <th style="width:100px;">Taksit</th>
                            <th style="width:80px;">Üc. Kargo</th>
                            <th style="width:50px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $loop_index = 0; foreach ($tiers as $key => $tier): ?>
                        <tr class="gorilla-tier-row" style="background:#fff; border:1px solid #eee;">
                            <td><input type="text" name="tier_emoji[]" value="<?php echo esc_attr($tier['emoji']); ?>" style="width:45px; text-align:center; font-size:20px;"></td>
                            <td><input type="text" name="tier_key[]" value="<?php echo esc_attr($key); ?>" style="width:80px; font-family:monospace; font-size:12px;" readonly></td>
                            <td><input type="text" name="tier_label[]" value="<?php echo esc_attr($tier['label']); ?>" style="width:100%; font-weight:600;" required></td>
                            <td><input type="number" name="tier_min[]" value="<?php echo esc_attr($tier['min']); ?>" min="0" step="100" style="width:100%;" required></td>
                            <td><input type="number" name="tier_discount[]" value="<?php echo esc_attr($tier['discount']); ?>" min="0" max="100" step="1" style="width:100%;" required></td>
                            <td><input type="color" name="tier_color[]" value="<?php echo esc_attr($tier['color']); ?>" style="width:45px; height:35px; padding:2px;"></td>
                            <td>
                                <select name="tier_installment[]" style="width:100%;">
                                    <option value="0" <?php selected($tier['installment'], 0); ?>>Yok</option>
                                    <option value="2" <?php selected($tier['installment'], 2); ?>>2 Taksit</option>
                                    <option value="3" <?php selected($tier['installment'], 3); ?>>3 Taksit</option>
                                    <option value="6" <?php selected($tier['installment'], 6); ?>>6 Taksit</option>
                                </select>
                            </td>
                            <td style="text-align:center;">
                                <input type="checkbox" name="tier_free_shipping[<?php echo $loop_index; ?>]" value="1" <?php checked(!empty($tier['free_shipping'])); ?> style="width:20px; height:20px;">
                            </td>
                            <td><button type="button" class="button gorilla-remove-tier" title="Sil" style="color:#dc3545;">✕</button></td>
                        <?php $loop_index++; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <button type="button" id="gorilla-add-tier" class="button" style="margin-top:10px;">➕ Yeni Seviye Ekle</button>

                <script>
                document.getElementById('gorilla-add-tier').addEventListener('click', function() {
                    var tbody = document.querySelector('#gorilla-tiers-table tbody');
                    var count = tbody.querySelectorAll('tr').length;
                    var key = 'tier_' + (count + 1);
                    var row = document.createElement('tr');
                    row.className = 'gorilla-tier-row';
                    row.style.background = '#fff';
                    row.innerHTML = '<td><input type="text" name="tier_emoji[]" value="🎖️" style="width:45px; text-align:center; font-size:20px;"></td>' +
                        '<td><input type="text" name="tier_key[]" value="' + key + '" style="width:80px; font-family:monospace; font-size:12px;"></td>' +
                        '<td><input type="text" name="tier_label[]" value="" style="width:100%; font-weight:600;" required placeholder="Seviye Adı"></td>' +
                        '<td><input type="number" name="tier_min[]" value="0" min="0" step="100" style="width:100%;" required></td>' +
                        '<td><input type="number" name="tier_discount[]" value="0" min="0" max="100" step="1" style="width:100%;" required></td>' +
                        '<td><input type="color" name="tier_color[]" value="#999999" style="width:45px; height:35px; padding:2px;"></td>' +
                        '<td><select name="tier_installment[]" style="width:100%;"><option value="0">Yok</option><option value="2">2 Taksit</option><option value="3">3 Taksit</option><option value="6">6 Taksit</option></select></td>' +
                        '<td style="text-align:center;"><input type="checkbox" name="tier_free_shipping[' + count + ']" value="1" style="width:20px; height:20px;"></td>' +
                        '<td><button type="button" class="button gorilla-remove-tier" title="Sil" style="color:#dc3545;">✕</button></td>';
                    tbody.appendChild(row);
                });
                document.addEventListener('click', function(e) {
                    if (e.target.classList.contains('gorilla-remove-tier')) {
                        if (confirm('Bu seviyeyi silmek istediğinize emin misiniz?')) {
                            e.target.closest('tr').remove();
                        }
                    }
                });
                </script>
            </div>

            <!-- GAMIFICATION AYARLARI -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">🎯 Gamification Ayarlari</h2>
                <p style="color:#666; margin-bottom:20px;">Dogum gunu odulleri, giris serisi, rozetler, liderlik tablosu ve kilometre taslari ile musterilerinizi motive edin.</p>

                <h3 style="margin-top:20px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">🎂 Dogum Gunu Odulleri</h3>
                <table class="form-table">
                    <tr>
                        <th>Dogum Gunu Odulu</th>
                        <td>
                            <label><input type="checkbox" name="birthday_enabled" value="yes" <?php checked(get_option('gorilla_lr_birthday_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Musterilere dogum gunlerinde otomatik XP ve store credit hediye edilir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Dogum Gunu Store Credit</th>
                        <td>
                            <input type="number" name="birthday_credit" value="<?php echo esc_attr(get_option('gorilla_lr_birthday_credit', 10)); ?>" min="0" max="100" step="0.01" style="width:100px;"> ₺
                            <p class="description">Dogum gununde verilecek store credit miktari.</p>
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top:30px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">🎉 Uyelik Yildonumu</h3>
                <table class="form-table">
                    <tr>
                        <th>Yildonumu Odulleri</th>
                        <td>
                            <label><input type="checkbox" name="anniversary_enabled" value="yes" <?php checked(get_option('gorilla_lr_anniversary_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Her yildonumunde (uyelik tarihi bazinda) otomatik odul verilir. XP carpan olarak uygulanir (2. yil = 2x).</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Yildonumu Store Credit</th>
                        <td>
                            <input type="number" name="anniversary_credit" value="<?php echo esc_attr(get_option('gorilla_lr_anniversary_credit', 20)); ?>" min="0" max="200" step="0.01" style="width:100px;"> ₺
                            <p class="description">Her yildonumunde verilecek store credit miktari.</p>
                        </td>
                    </tr>
                </table>


                <h3 style="margin-top:30px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">🏅 Rozetler (Badges)</h3>
                <table class="form-table">
                    <tr>
                        <th>Rozet Sistemi</th>
                        <td>
                            <label><input type="checkbox" name="badges_enabled" value="yes" <?php checked(get_option('gorilla_lr_badges_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Musteriler belirli eylemleri tamamladiginda rozet kazanir. Rozetler hesabimda goruntulenir.</p>
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top:30px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">🏆 Liderlik Tablosu (Leaderboard)</h3>
                <table class="form-table">
                    <tr>
                        <th>Liderlik Tablosu</th>
                        <td>
                            <label><input type="checkbox" name="leaderboard_enabled" value="yes" <?php checked(get_option('gorilla_lr_leaderboard_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">En yuksek XP'ye sahip musterilerin siralamasi goruntulenebilir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Anonim Gosterim</th>
                        <td>
                            <label><input type="checkbox" name="leaderboard_anonymize" value="yes" <?php checked(get_option('gorilla_lr_leaderboard_anonymize', 'no'), 'yes'); ?>> Isimleri gizle</label>
                            <p class="description">Aktif edilirse musteri isimleri kisaltilmis olarak gosterilir (ornek: A***n K.).</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Gosterim Limiti</th>
                        <td>
                            <input type="number" name="leaderboard_limit" value="<?php echo esc_attr(get_option('gorilla_lr_leaderboard_limit', 10)); ?>" min="5" max="50" style="width:80px;"> kisi
                            <p class="description">Liderlik tablosunda kac kisi gosterilecegi.</p>
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top:30px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">🚀 Kilometre Taslari (Milestones)</h3>
                <table class="form-table">
                    <tr>
                        <th>Kilometre Taslari</th>
                        <td>
                            <label><input type="checkbox" name="milestones_enabled" value="yes" <?php checked(get_option('gorilla_lr_milestones_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Musteriler belirli hedefleri tamamlayinca ozel oduller kazanir.</p>
                        </td>
                    </tr>
                </table>
                <?php
                $milestones = function_exists('gorilla_milestones_get_all') ? gorilla_milestones_get_all() : get_option('gorilla_lr_milestones', array());
                $ms_types = array('total_orders' => 'Toplam Siparis', 'total_spending' => 'Toplam Harcama (TL)', 'xp' => 'XP', 'referral_count' => 'Referans Sayisi', 'review_count' => 'Yorum Sayisi');
                ?>
                <div id="gorilla-milestones-list" style="margin-top:10px;">
                <?php if (!empty($milestones)): foreach ($milestones as $mi => $ms): ?>
                    <div class="gorilla-milestone-row" style="background:#f9fafb; padding:12px 16px; border-radius:10px; margin-bottom:10px; border:1px solid #e5e7eb;">
                        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                            <input type="text" name="milestone_id[]" value="<?php echo esc_attr($ms['id'] ?? ''); ?>" style="width:110px;" placeholder="ID (key)">
                            <input type="text" name="milestone_emoji[]" value="<?php echo esc_attr($ms['emoji'] ?? ''); ?>" style="width:40px; text-align:center;" title="Emoji">
                            <input type="text" name="milestone_label[]" value="<?php echo esc_attr($ms['label'] ?? ''); ?>" style="width:150px;" placeholder="Baslik">
                            <select name="milestone_type[]" style="width:140px;">
                                <?php foreach ($ms_types as $mtk => $mtl): ?>
                                    <option value="<?php echo $mtk; ?>" <?php selected($ms['type'] ?? '', $mtk); ?>><?php echo $mtl; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="milestone_target[]" value="<?php echo esc_attr($ms['target'] ?? 1); ?>" min="1" style="width:80px;" title="Hedef">
                            <input type="number" name="milestone_xp_reward[]" value="<?php echo intval($ms['xp_reward'] ?? 0); ?>" min="0" style="width:70px;" title="XP Odul"> <span style="font-size:12px; color:#888;">XP</span>
                            <input type="number" name="milestone_credit_reward[]" value="<?php echo floatval($ms['credit_reward'] ?? 0); ?>" min="0" step="0.01" style="width:70px;" title="Credit Odul"> <span style="font-size:12px; color:#888;">Credit</span>
                            <button type="button" class="button button-small gorilla-milestone-remove" style="color:#d63638;" title="Sil">✕</button>
                        </div>
                        <input type="text" name="milestone_desc[]" value="<?php echo esc_attr($ms['description'] ?? ''); ?>" style="width:100%; margin-top:6px;" placeholder="Aciklama">
                    </div>
                <?php endforeach; endif; ?>
                </div>
                <div style="display:flex; gap:8px; margin-top:8px;">
                    <button type="button" class="button" id="gorilla-milestone-add">+ Kilometre Tasi Ekle</button>
                    <button type="button" class="button" id="gorilla-milestone-reset" style="color:#b45309;">Varsayilanlara Sifirla</button>
                </div>
                <script>
                (function(){
                    var list = document.getElementById('gorilla-milestones-list');
                    var msTypes = <?php echo wp_json_encode($ms_types); ?>;

                    document.getElementById('gorilla-milestone-add').addEventListener('click', function() {
                        var i = list.querySelectorAll('.gorilla-milestone-row').length;
                        var typeOpts = '';
                        for (var k in msTypes) { typeOpts += '<option value="'+k+'">'+msTypes[k]+'</option>'; }
                        var html = '<div class="gorilla-milestone-row" style="background:#f9fafb; padding:12px 16px; border-radius:10px; margin-bottom:10px; border:1px solid #e5e7eb;">' +
                            '<div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">' +
                            '<input type="text" name="milestone_id[]" value="" style="width:110px;" placeholder="ID (key)">' +
                            '<input type="text" name="milestone_emoji[]" value="" style="width:40px; text-align:center;" title="Emoji">' +
                            '<input type="text" name="milestone_label[]" value="" style="width:150px;" placeholder="Baslik">' +
                            '<select name="milestone_type[]" style="width:140px;">' + typeOpts + '</select>' +
                            '<input type="number" name="milestone_target[]" value="1" min="1" style="width:80px;" title="Hedef">' +
                            '<input type="number" name="milestone_xp_reward[]" value="0" min="0" style="width:70px;" title="XP Odul"> <span style="font-size:12px; color:#888;">XP</span>' +
                            '<input type="number" name="milestone_credit_reward[]" value="0" min="0" step="0.01" style="width:70px;" title="Credit Odul"> <span style="font-size:12px; color:#888;">Credit</span>' +
                            '<button type="button" class="button button-small gorilla-milestone-remove" style="color:#d63638;" title="Sil">\u2715</button>' +
                            '</div>' +
                            '<input type="text" name="milestone_desc[]" value="" style="width:100%; margin-top:6px;" placeholder="Aciklama">' +
                            '</div>';
                        list.insertAdjacentHTML('beforeend', html);
                    });

                    document.getElementById('gorilla-milestone-reset').addEventListener('click', function() {
                        if (!confirm('Kilometre taslari varsayilan degerlere sifirlanacak. Emin misiniz?')) return;
                        list.innerHTML = '';
                        var defaults = [
                            {id:'first_order',label:'Ilk Siparis',emoji:'\uD83D\uDECD\uFE0F',desc:'Ilk siparisini ver',type:'total_orders',target:1,xp:50,credit:0},
                            {id:'orders_10',label:'10 Siparis',emoji:'\uD83C\uDFC5',desc:'10 siparis tamamla',type:'total_orders',target:10,xp:200,credit:20},
                            {id:'spend_5000',label:'5000 TL Harcama',emoji:'\uD83D\uDCB0',desc:'Toplam 5000 TL harca',type:'total_spending',target:5000,xp:500,credit:50}
                        ];
                        var typeOpts = '';
                        for (var k in msTypes) { typeOpts += '<option value="'+k+'">'+msTypes[k]+'</option>'; }
                        defaults.forEach(function(d) {
                            var opts = '';
                            for (var k in msTypes) { opts += '<option value="'+k+'"'+(k===d.type?' selected':'')+'>'+msTypes[k]+'</option>'; }
                            var html = '<div class="gorilla-milestone-row" style="background:#f9fafb; padding:12px 16px; border-radius:10px; margin-bottom:10px; border:1px solid #e5e7eb;">' +
                                '<div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">' +
                                '<input type="text" name="milestone_id[]" value="'+d.id+'" style="width:110px;" placeholder="ID (key)">' +
                                '<input type="text" name="milestone_emoji[]" value="'+d.emoji+'" style="width:40px; text-align:center;" title="Emoji">' +
                                '<input type="text" name="milestone_label[]" value="'+d.label+'" style="width:150px;" placeholder="Baslik">' +
                                '<select name="milestone_type[]" style="width:140px;">' + opts + '</select>' +
                                '<input type="number" name="milestone_target[]" value="'+d.target+'" min="1" style="width:80px;" title="Hedef">' +
                                '<input type="number" name="milestone_xp_reward[]" value="'+d.xp+'" min="0" style="width:70px;" title="XP Odul"> <span style="font-size:12px; color:#888;">XP</span>' +
                                '<input type="number" name="milestone_credit_reward[]" value="'+d.credit+'" min="0" step="0.01" style="width:70px;" title="Credit Odul"> <span style="font-size:12px; color:#888;">Credit</span>' +
                                '<button type="button" class="button button-small gorilla-milestone-remove" style="color:#d63638;" title="Sil">\u2715</button>' +
                                '</div>' +
                                '<input type="text" name="milestone_desc[]" value="'+d.desc+'" style="width:100%; margin-top:6px;" placeholder="Aciklama">' +
                                '</div>';
                            list.insertAdjacentHTML('beforeend', html);
                        });
                    });

                    list.addEventListener('click', function(e) {
                        if (e.target.classList.contains('gorilla-milestone-remove')) {
                            e.target.closest('.gorilla-milestone-row').remove();
                        }
                    });
                })();
                </script>

                <h3 style="margin-top:30px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">🎯 Gorevler / Challenges</h3>
                <table class="form-table">
                    <tr>
                        <th>Challenge Sistemi</th>
                        <td>
                            <label><input type="checkbox" name="challenges_enabled" value="yes" <?php checked(get_option('gorilla_lr_challenges_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Gunluk, haftalik ve tek seferlik gorevler ile musterilere XP/credit odul verin.</p>
                        </td>
                    </tr>
                </table>
                <?php
                $challenges = get_option('gorilla_lr_challenges', array());
                if (empty($challenges) && function_exists('gorilla_challenges_defaults')) {
                    $challenges = gorilla_challenges_defaults();
                }
                $ch_types   = array('orders' => 'Siparis', 'reviews' => 'Yorum', 'referrals' => 'Referans', 'spending' => 'Harcama (TL)');
                $ch_periods = array('daily' => 'Gunluk', 'weekly' => 'Haftalik', 'one_time' => 'Tek Seferlik');
                ?>
                <div id="gorilla-challenges-list" style="margin-top:10px;">
                <?php if (!empty($challenges)): foreach ($challenges as $ci => $ch): ?>
                    <div class="gorilla-challenge-row" style="background:#f9fafb; padding:12px 16px; border-radius:10px; margin-bottom:10px; border:1px solid #e5e7eb;">
                        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                            <input type="hidden" name="gorilla_challenges[<?php echo $ci; ?>][id]" value="<?php echo esc_attr($ch['id'] ?? ''); ?>">
                            <label><input type="checkbox" name="gorilla_challenges[<?php echo $ci; ?>][active]" value="1" <?php checked(!empty($ch['active'])); ?>> Aktif</label>
                            <input type="text" name="gorilla_challenges[<?php echo $ci; ?>][emoji]" value="<?php echo esc_attr($ch['emoji'] ?? '🎯'); ?>" style="width:40px; text-align:center;" title="Emoji">
                            <input type="text" name="gorilla_challenges[<?php echo $ci; ?>][title]" value="<?php echo esc_attr($ch['title'] ?? ''); ?>" style="width:180px;" placeholder="Baslik">
                            <select name="gorilla_challenges[<?php echo $ci; ?>][type]" style="width:110px;">
                                <?php foreach ($ch_types as $tk => $tl): ?>
                                    <option value="<?php echo $tk; ?>" <?php selected($ch['type'] ?? '', $tk); ?>><?php echo $tl; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="gorilla_challenges[<?php echo $ci; ?>][target]" value="<?php echo intval($ch['target'] ?? 1); ?>" min="1" style="width:70px;" title="Hedef">
                            <select name="gorilla_challenges[<?php echo $ci; ?>][period]" style="width:100px;">
                                <?php foreach ($ch_periods as $pk => $pl): ?>
                                    <option value="<?php echo $pk; ?>" <?php selected($ch['period'] ?? '', $pk); ?>><?php echo $pl; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="gorilla_challenges[<?php echo $ci; ?>][reward_type]" style="width:80px;">
                                <option value="xp" <?php selected($ch['reward_type'] ?? '', 'xp'); ?>>XP</option>
                                <option value="credit" <?php selected($ch['reward_type'] ?? '', 'credit'); ?>>Credit</option>
                            </select>
                            <input type="number" name="gorilla_challenges[<?php echo $ci; ?>][reward_amount]" value="<?php echo intval($ch['reward_amount'] ?? 50); ?>" min="1" style="width:70px;" title="Odul miktari">
                        </div>
                        <input type="text" name="gorilla_challenges[<?php echo $ci; ?>][description]" value="<?php echo esc_attr($ch['description'] ?? ''); ?>" style="width:100%; margin-top:6px;" placeholder="Aciklama">
                    </div>
                <?php endforeach; endif; ?>
                </div>
                <button type="button" class="button" onclick="gorillaAddChallenge()" style="margin-top:8px;">+ Gorev Ekle</button>
                <script>
                function gorillaAddChallenge() {
                    var list = document.getElementById('gorilla-challenges-list');
                    var i = list.querySelectorAll('.gorilla-challenge-row').length;
                    var html = '<div class="gorilla-challenge-row" style="background:#f9fafb; padding:12px 16px; border-radius:10px; margin-bottom:10px; border:1px solid #e5e7eb;">' +
                        '<div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">' +
                        '<input type="hidden" name="gorilla_challenges['+i+'][id]" value="">' +
                        '<label><input type="checkbox" name="gorilla_challenges['+i+'][active]" value="1" checked> Aktif</label>' +
                        '<input type="text" name="gorilla_challenges['+i+'][emoji]" value="🎯" style="width:40px; text-align:center;">' +
                        '<input type="text" name="gorilla_challenges['+i+'][title]" value="" style="width:180px;" placeholder="Baslik">' +
                        '<select name="gorilla_challenges['+i+'][type]" style="width:110px;"><option value="orders">Siparis</option><option value="reviews">Yorum</option><option value="referrals">Referans</option><option value="spending">Harcama (TL)</option></select>' +
                        '<input type="number" name="gorilla_challenges['+i+'][target]" value="1" min="1" style="width:70px;">' +
                        '<select name="gorilla_challenges['+i+'][period]" style="width:100px;"><option value="weekly">Haftalik</option><option value="daily">Gunluk</option><option value="one_time">Tek Seferlik</option></select>' +
                        '<select name="gorilla_challenges['+i+'][reward_type]" style="width:80px;"><option value="xp">XP</option><option value="credit">Credit</option></select>' +
                        '<input type="number" name="gorilla_challenges['+i+'][reward_amount]" value="50" min="1" style="width:70px;">' +
                        '</div>' +
                        '<input type="text" name="gorilla_challenges['+i+'][description]" value="" style="width:100%; margin-top:6px;" placeholder="Aciklama">' +
                        '</div>';
                    list.insertAdjacentHTML('beforeend', html);
                }
                </script>
            </div>

            <!-- SANS CARKI & PUAN DUKKANI -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">🎰 Sans Carki & Puan Dukkani</h2>
                <p style="color:#666; margin-bottom:20px;">Sans carki ile musterilere surpriz oduller verin, puan dukkani ile XP puanlarini odullere donustursunler.</p>

                <h3 style="margin-top:20px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">🎡 Sans Carki (Spin Wheel)</h3>
                <table class="form-table">
                    <tr>
                        <th>Sans Carki</th>
                        <td>
                            <label><input type="checkbox" name="spin_enabled" value="yes" <?php checked(get_option('gorilla_lr_spin_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Musteriler belirli kosullari karsiladiginda sans carkini cevirebilir.</p>
                        </td>
                    </tr>
                </table>
                <?php
                $spin_prizes = function_exists('gorilla_spin_get_prizes') ? gorilla_spin_get_prizes() : get_option('gorilla_lr_spin_prizes', array());
                $spin_types = array('xp' => 'XP', 'credit' => 'Credit', 'coupon' => 'Indirim Kuponu', 'free_shipping' => 'Ucretsiz Kargo', 'nothing' => 'Bos (Tekrar Dene)');
                ?>
                <p style="margin:10px 0 4px; font-weight:600; color:#374151;">Oduller</p>
                <p class="description" style="margin-bottom:10px;">Her satir bir cark dilimini temsil eder. Agirlik (weight) degeri yukseldikce odul kazanma olasiligi artar.</p>
                <div id="gorilla-spin-list">
                <?php if (!empty($spin_prizes)): foreach ($spin_prizes as $si => $sp): ?>
                    <div class="gorilla-spin-row" style="background:#f9fafb; padding:10px 14px; border-radius:8px; margin-bottom:8px; border:1px solid #e5e7eb; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                        <input type="text" name="spin_label[]" value="<?php echo esc_attr($sp['label'] ?? ''); ?>" style="width:150px;" placeholder="Etiket">
                        <select name="spin_type[]" style="width:140px;">
                            <?php foreach ($spin_types as $stk => $stl): ?>
                                <option value="<?php echo $stk; ?>" <?php selected($sp['type'] ?? '', $stk); ?>><?php echo $stl; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="spin_value[]" value="<?php echo esc_attr($sp['value'] ?? 0); ?>" min="0" step="0.01" style="width:80px;" title="Deger">
                        <span style="font-size:12px; color:#888;">Deger</span>
                        <input type="number" name="spin_weight[]" value="<?php echo intval($sp['weight'] ?? 1); ?>" min="1" max="100" style="width:60px;" title="Agirlik">
                        <span style="font-size:12px; color:#888;">Agirlik</span>
                        <button type="button" class="button button-small gorilla-spin-remove" style="color:#d63638;" title="Sil">&#10005;</button>
                    </div>
                <?php endforeach; endif; ?>
                </div>
                <div style="display:flex; gap:8px; margin-top:8px;">
                    <button type="button" class="button" id="gorilla-spin-add">+ Odul Ekle</button>
                    <button type="button" class="button" id="gorilla-spin-reset" style="color:#b45309;">Varsayilanlara Sifirla</button>
                </div>
                <script>
                (function(){
                    var list = document.getElementById('gorilla-spin-list');
                    var spinTypes = <?php echo wp_json_encode($spin_types); ?>;

                    document.getElementById('gorilla-spin-add').addEventListener('click', function() {
                        var opts = '';
                        for (var k in spinTypes) { opts += '<option value="'+k+'">'+spinTypes[k]+'</option>'; }
                        var html = '<div class="gorilla-spin-row" style="background:#f9fafb; padding:10px 14px; border-radius:8px; margin-bottom:8px; border:1px solid #e5e7eb; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">' +
                            '<input type="text" name="spin_label[]" value="" style="width:150px;" placeholder="Etiket">' +
                            '<select name="spin_type[]" style="width:140px;">' + opts + '</select>' +
                            '<input type="number" name="spin_value[]" value="0" min="0" step="0.01" style="width:80px;" title="Deger">' +
                            '<span style="font-size:12px; color:#888;">Deger</span>' +
                            '<input type="number" name="spin_weight[]" value="10" min="1" max="100" style="width:60px;" title="Agirlik">' +
                            '<span style="font-size:12px; color:#888;">Agirlik</span>' +
                            '<button type="button" class="button button-small gorilla-spin-remove" style="color:#d63638;" title="Sil">\u2715</button>' +
                            '</div>';
                        list.insertAdjacentHTML('beforeend', html);
                    });

                    document.getElementById('gorilla-spin-reset').addEventListener('click', function() {
                        if (!confirm('Sans carki odulleri varsayilan degerlere sifirlanacak. Emin misiniz?')) return;
                        list.innerHTML = '';
                        var defaults = [
                            {label:'10 XP',type:'xp',value:10,weight:30},
                            {label:'25 XP',type:'xp',value:25,weight:20},
                            {label:'50 XP',type:'xp',value:50,weight:10},
                            {label:'5 TL Credit',type:'credit',value:5,weight:15},
                            {label:'10 TL Credit',type:'credit',value:10,weight:8},
                            {label:'Ucretsiz Kargo',type:'free_shipping',value:0,weight:7},
                            {label:'%10 Indirim',type:'coupon',value:10,weight:5},
                            {label:'Tekrar Dene',type:'nothing',value:0,weight:5}
                        ];
                        defaults.forEach(function(d) {
                            var opts = '';
                            for (var k in spinTypes) { opts += '<option value="'+k+'"'+(k===d.type?' selected':'')+'>'+spinTypes[k]+'</option>'; }
                            var html = '<div class="gorilla-spin-row" style="background:#f9fafb; padding:10px 14px; border-radius:8px; margin-bottom:8px; border:1px solid #e5e7eb; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">' +
                                '<input type="text" name="spin_label[]" value="'+d.label+'" style="width:150px;" placeholder="Etiket">' +
                                '<select name="spin_type[]" style="width:140px;">' + opts + '</select>' +
                                '<input type="number" name="spin_value[]" value="'+d.value+'" min="0" step="0.01" style="width:80px;" title="Deger">' +
                                '<span style="font-size:12px; color:#888;">Deger</span>' +
                                '<input type="number" name="spin_weight[]" value="'+d.weight+'" min="1" max="100" style="width:60px;" title="Agirlik">' +
                                '<span style="font-size:12px; color:#888;">Agirlik</span>' +
                                '<button type="button" class="button button-small gorilla-spin-remove" style="color:#d63638;" title="Sil">\u2715</button>' +
                                '</div>';
                            list.insertAdjacentHTML('beforeend', html);
                        });
                    });

                    list.addEventListener('click', function(e) {
                        if (e.target.classList.contains('gorilla-spin-remove')) {
                            e.target.closest('.gorilla-spin-row').remove();
                        }
                    });
                })();
                </script>

                <h3 style="margin-top:30px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">🛍️ Puan Dukkani (Points Shop)</h3>
                <table class="form-table">
                    <tr>
                        <th>Puan Dukkani</th>
                        <td>
                            <label><input type="checkbox" name="points_shop_enabled" value="yes" <?php checked(get_option('gorilla_lr_points_shop_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Musteriler kazandiklari XP puanlarini ozel oduller, indirimler ve hediyelerle takas edebilir.</p>
                        </td>
                    </tr>
                </table>
                <?php
                $shop_rewards = function_exists('gorilla_shop_get_rewards') ? gorilla_shop_get_rewards() : get_option('gorilla_lr_points_shop_rewards', array());
                $shop_types = array('coupon' => 'Indirim Kuponu', 'free_shipping' => 'Ucretsiz Kargo');
                $shop_coupon_types = array('fixed_cart' => 'Sabit Tutar (TL)', 'percent' => 'Yuzde (%)');
                ?>
                <p style="margin:10px 0 4px; font-weight:600; color:#374151;">Oduller</p>
                <p class="description" style="margin-bottom:10px;">Musterilerin XP karsiliginda satin alabilecegi oduller. Her odul icin benzersiz bir ID belirleyin.</p>
                <div id="gorilla-shop-list">
                <?php if (!empty($shop_rewards)): foreach ($shop_rewards as $ri => $rw): ?>
                    <div class="gorilla-shop-row" style="background:#f9fafb; padding:10px 14px; border-radius:8px; margin-bottom:8px; border:1px solid #e5e7eb; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                        <input type="text" name="shop_id[]" value="<?php echo esc_attr($rw['id'] ?? ''); ?>" style="width:110px;" placeholder="ID (key)">
                        <input type="text" name="shop_label[]" value="<?php echo esc_attr($rw['label'] ?? ''); ?>" style="width:180px;" placeholder="Etiket">
                        <input type="number" name="shop_xp_cost[]" value="<?php echo intval($rw['xp_cost'] ?? 100); ?>" min="1" style="width:70px;" title="XP Maliyeti">
                        <span style="font-size:12px; color:#888;">XP</span>
                        <select name="shop_type[]" style="width:130px;">
                            <?php foreach ($shop_types as $rtk => $rtl): ?>
                                <option value="<?php echo $rtk; ?>" <?php selected($rw['type'] ?? '', $rtk); ?>><?php echo $rtl; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="shop_coupon_type[]" style="width:120px;">
                            <?php foreach ($shop_coupon_types as $ctk => $ctl): ?>
                                <option value="<?php echo $ctk; ?>" <?php selected($rw['coupon_type'] ?? '', $ctk); ?>><?php echo $ctl; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="shop_coupon_amount[]" value="<?php echo esc_attr($rw['coupon_amount'] ?? 0); ?>" min="0" step="0.01" style="width:70px;" title="Kupon Tutari">
                        <span style="font-size:12px; color:#888;">Tutar</span>
                        <button type="button" class="button button-small gorilla-shop-remove" style="color:#d63638;" title="Sil">&#10005;</button>
                    </div>
                <?php endforeach; endif; ?>
                </div>
                <div style="display:flex; gap:8px; margin-top:8px;">
                    <button type="button" class="button" id="gorilla-shop-add">+ Odul Ekle</button>
                    <button type="button" class="button" id="gorilla-shop-reset" style="color:#b45309;">Varsayilanlara Sifirla</button>
                </div>
                <script>
                (function(){
                    var list = document.getElementById('gorilla-shop-list');
                    var shopTypes = <?php echo wp_json_encode($shop_types); ?>;
                    var shopCouponTypes = <?php echo wp_json_encode($shop_coupon_types); ?>;

                    document.getElementById('gorilla-shop-add').addEventListener('click', function() {
                        var tOpts = '', cOpts = '';
                        for (var k in shopTypes) { tOpts += '<option value="'+k+'">'+shopTypes[k]+'</option>'; }
                        for (var k in shopCouponTypes) { cOpts += '<option value="'+k+'">'+shopCouponTypes[k]+'</option>'; }
                        var html = '<div class="gorilla-shop-row" style="background:#f9fafb; padding:10px 14px; border-radius:8px; margin-bottom:8px; border:1px solid #e5e7eb; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">' +
                            '<input type="text" name="shop_id[]" value="" style="width:110px;" placeholder="ID (key)">' +
                            '<input type="text" name="shop_label[]" value="" style="width:180px;" placeholder="Etiket">' +
                            '<input type="number" name="shop_xp_cost[]" value="100" min="1" style="width:70px;" title="XP Maliyeti">' +
                            '<span style="font-size:12px; color:#888;">XP</span>' +
                            '<select name="shop_type[]" style="width:130px;">' + tOpts + '</select>' +
                            '<select name="shop_coupon_type[]" style="width:120px;">' + cOpts + '</select>' +
                            '<input type="number" name="shop_coupon_amount[]" value="0" min="0" step="0.01" style="width:70px;" title="Kupon Tutari">' +
                            '<span style="font-size:12px; color:#888;">Tutar</span>' +
                            '<button type="button" class="button button-small gorilla-shop-remove" style="color:#d63638;" title="Sil">\u2715</button>' +
                            '</div>';
                        list.insertAdjacentHTML('beforeend', html);
                    });

                    document.getElementById('gorilla-shop-reset').addEventListener('click', function() {
                        if (!confirm('Puan dukkani odulleri varsayilan degerlere sifirlanacak. Emin misiniz?')) return;
                        list.innerHTML = '';
                        var defaults = [
                            {id:'coupon_5',label:'5 TL Indirim Kuponu',xp_cost:100,type:'coupon',coupon_type:'fixed_cart',coupon_amount:5},
                            {id:'coupon_10',label:'10 TL Indirim Kuponu',xp_cost:200,type:'coupon',coupon_type:'fixed_cart',coupon_amount:10},
                            {id:'coupon_pct_10',label:'%10 Indirim Kuponu',xp_cost:300,type:'coupon',coupon_type:'percent',coupon_amount:10},
                            {id:'free_shipping',label:'Ucretsiz Kargo Kuponu',xp_cost:150,type:'free_shipping',coupon_type:'fixed_cart',coupon_amount:0}
                        ];
                        defaults.forEach(function(d) {
                            var tOpts = '', cOpts = '';
                            for (var k in shopTypes) { tOpts += '<option value="'+k+'"'+(k===d.type?' selected':'')+'>'+shopTypes[k]+'</option>'; }
                            for (var k in shopCouponTypes) { cOpts += '<option value="'+k+'"'+(k===d.coupon_type?' selected':'')+'>'+shopCouponTypes[k]+'</option>'; }
                            var html = '<div class="gorilla-shop-row" style="background:#f9fafb; padding:10px 14px; border-radius:8px; margin-bottom:8px; border:1px solid #e5e7eb; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">' +
                                '<input type="text" name="shop_id[]" value="'+d.id+'" style="width:110px;" placeholder="ID (key)">' +
                                '<input type="text" name="shop_label[]" value="'+d.label+'" style="width:180px;" placeholder="Etiket">' +
                                '<input type="number" name="shop_xp_cost[]" value="'+d.xp_cost+'" min="1" style="width:70px;" title="XP Maliyeti">' +
                                '<span style="font-size:12px; color:#888;">XP</span>' +
                                '<select name="shop_type[]" style="width:130px;">' + tOpts + '</select>' +
                                '<select name="shop_coupon_type[]" style="width:120px;">' + cOpts + '</select>' +
                                '<input type="number" name="shop_coupon_amount[]" value="'+d.coupon_amount+'" min="0" step="0.01" style="width:70px;" title="Kupon Tutari">' +
                                '<span style="font-size:12px; color:#888;">Tutar</span>' +
                                '<button type="button" class="button button-small gorilla-shop-remove" style="color:#d63638;" title="Sil">\u2715</button>' +
                                '</div>';
                            list.insertAdjacentHTML('beforeend', html);
                        });
                    });

                    list.addEventListener('click', function(e) {
                        if (e.target.classList.contains('gorilla-shop-remove')) {
                            e.target.closest('.gorilla-shop-row').remove();
                        }
                    });
                })();
                </script>
            </div>

            <!-- SOSYAL PAYLASIM & QR KOD -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">📱 Sosyal Paylasim & QR Kod</h2>
                <p style="color:#666; margin-bottom:20px;">Musterilerin sosyal medyada paylasim yaparak XP kazanmasini saglayin ve QR kod ile magaza ici etkilesimi artirin.</p>

                <h3 style="margin-top:20px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">📣 Sosyal Paylasim</h3>
                <table class="form-table">
                    <tr>
                        <th>Sosyal Paylasim</th>
                        <td>
                            <label><input type="checkbox" name="social_share_enabled" value="yes" <?php checked(get_option('gorilla_lr_social_share_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Musteriler urunleri sosyal medyada paylasarak XP kazanir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Paylasim Basina XP</th>
                        <td>
                            <input type="number" name="social_share_xp" value="<?php echo esc_attr(get_option('gorilla_lr_social_share_xp', 10)); ?>" min="0" max="100" style="width:80px;"> XP
                            <p class="description">Her basarili sosyal medya paylasimi icin verilecek XP.</p>
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top:30px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">📷 QR Kod</h3>
                <table class="form-table">
                    <tr>
                        <th>QR Kod Sistemi</th>
                        <td>
                            <label><input type="checkbox" name="qr_enabled" value="yes" <?php checked(get_option('gorilla_lr_qr_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Her musteriye ozel QR kod olusturulur. Magaza ici taramalarla referans takibi yapilabilir.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- BAKIYE TRANSFERI -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">💸 Bakiye Transferi</h2>
                <p style="color:#666; margin-bottom:20px;">Musterilerin birbirlerine store credit gonderebilmesini saglayin.</p>
                <table class="form-table">
                    <tr>
                        <th>Bakiye Transferi</th>
                        <td>
                            <label><input type="checkbox" name="transfer_enabled" value="yes" <?php checked(get_option('gorilla_lr_transfer_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Musteriler hesabim sayfasindan diger musterilere bakiye gonderebilir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Gunluk Transfer Limiti</th>
                        <td>
                            <input type="number" name="transfer_daily_limit" value="<?php echo esc_attr(get_option('gorilla_lr_transfer_daily_limit', 500)); ?>" min="0" step="1" style="width:120px;"> ₺
                            <p class="description">Bir musterinin gunluk toplam transfer edebilecegi maksimum miktar. 0 = limitsiz.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Minimum Transfer</th>
                        <td>
                            <input type="number" name="transfer_min_amount" value="<?php echo esc_attr(get_option('gorilla_lr_transfer_min_amount', 10)); ?>" min="1" step="1" style="width:120px;"> ₺
                            <p class="description">Yapilabilecek en dusuk transfer miktari.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Transfer Komisyonu</th>
                        <td>
                            <input type="number" name="transfer_fee_pct" value="<?php echo esc_attr(get_option('gorilla_lr_transfer_fee_pct', 0)); ?>" min="0" max="50" style="width:80px;"> %
                            <p class="description">Transfer uzerinden kesilecek komisyon yuzdesi. 0 = komisyon yok. Ornek: %5 komisyon ile 100₺ transferde aliciya 95₺ ulasir.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ENTEGRASYONLAR -->
            <div class="gorilla-settings-section" style="max-width:900px; background:#fff; padding:30px; border-radius:16px; border:1px solid #e5e7eb; margin-bottom:25px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">🔗 Entegrasyonlar</h2>

                <h3 style="margin-top:20px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">📊 Google Analytics 4</h3>
                <table class="form-table">
                    <tr>
                        <th>GA4 Measurement ID</th>
                        <td>
                            <input type="text" name="ga4_measurement_id" value="<?php echo esc_attr(get_option('gorilla_lr_ga4_measurement_id', '')); ?>" placeholder="G-XXXXXXXXXX" style="width:250px;">
                            <p class="description">GA4 Measurement ID'nizi girin. Sadakat olaylari (XP, tier, badge, spin) otomatik olarak GA4'e gonderilir. Bos birakirsaniz GA4 takibi devre disi kalir.</p>
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top:30px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">🪝 Webhook</h3>
                <table class="form-table">
                    <tr>
                        <th>Webhook URL</th>
                        <td>
                            <input type="url" name="webhook_url" value="<?php echo esc_attr(get_option('gorilla_lr_webhook_url', '')); ?>" placeholder="https://example.com/webhook" class="regular-text">
                            <p class="description">Olaylar HTTP POST olarak bu URL'ye gonderilir. Zapier, Make.com veya kendi sunucunuzu kullanabilirsiniz.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Aktif Olaylar</th>
                        <td>
                            <?php $wh_events = get_option('gorilla_lr_webhook_events', array()); ?>
                            <label style="display:block; margin-bottom:4px;"><input type="checkbox" name="webhook_events[]" value="tier_change" <?php checked(in_array('tier_change', $wh_events)); ?>> Seviye Degisimi</label>
                            <label style="display:block; margin-bottom:4px;"><input type="checkbox" name="webhook_events[]" value="referral_approved" <?php checked(in_array('referral_approved', $wh_events)); ?>> Referans Onayi</label>
                            <label style="display:block; margin-bottom:4px;"><input type="checkbox" name="webhook_events[]" value="milestone_reached" <?php checked(in_array('milestone_reached', $wh_events)); ?>> Milestone</label>
                            <label style="display:block; margin-bottom:4px;"><input type="checkbox" name="webhook_events[]" value="badge_earned" <?php checked(in_array('badge_earned', $wh_events)); ?>> Rozet Kazanimi</label>
                            <label style="display:block; margin-bottom:4px;"><input type="checkbox" name="webhook_events[]" value="spin_win" <?php checked(in_array('spin_win', $wh_events)); ?>> Cark Odulu</label>
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top:30px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">📉 Churn Onleme (Musteri Kaybı)</h3>
                <table class="form-table">
                    <tr>
                        <th>Churn Onleme</th>
                        <td>
                            <label><input type="checkbox" name="churn_enabled" value="yes" <?php checked(get_option('gorilla_lr_churn_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Belirli suredir alisveris yapmayan musterilere otomatik bonus gonderir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Inaktivite Suresi</th>
                        <td>
                            <input type="number" name="churn_months" value="<?php echo esc_attr(get_option('gorilla_lr_churn_months', 3)); ?>" min="1" max="12" style="width:80px;"> ay
                            <p class="description">Bu sureden fazla alisveris yapmayanlar "risk altinda" sayilir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Bonus Credit</th>
                        <td>
                            <input type="number" name="churn_bonus_credit" value="<?php echo esc_attr(get_option('gorilla_lr_churn_bonus_credit', 25)); ?>" min="0" step="5" style="width:100px;"> ₺
                            <p class="description">Geri donme tesvik bonusu (30 gun gecerli).</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Bonus XP</th>
                        <td>
                            <input type="number" name="churn_bonus_xp" value="<?php echo esc_attr(get_option('gorilla_lr_churn_bonus_xp', 100)); ?>" min="0" step="10" style="width:100px;"> XP
                            <p class="description">Geri donme XP bonusu.</p>
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top:30px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">👑 VIP Erken Erisim</h3>
                <table class="form-table">
                    <tr>
                        <th>VIP Erken Erisim</th>
                        <td>
                            <label><input type="checkbox" name="vip_early_access_enabled" value="yes" <?php checked(get_option('gorilla_lr_vip_early_access_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Indirimli urunler belirli tier'lere erken acilir. Urun edit sayfasinda "VIP Erken Erisim" ayarini aktif edin.</p>
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top:30px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">🔄 Puan Transfer</h3>
                <table class="form-table">
                    <tr>
                        <th>Puan Transfer</th>
                        <td>
                            <label><input type="checkbox" name="transfer_enabled" value="yes" <?php checked(get_option('gorilla_lr_transfer_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Kullanicilar arasi credit/XP transferi.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Gunluk Limit</th>
                        <td>
                            <input type="number" name="transfer_daily_limit" value="<?php echo esc_attr(get_option('gorilla_lr_transfer_daily_limit', 500)); ?>" min="10" max="100000" step="10" style="width:120px;">
                            <p class="description">Kullanici basina gunluk maximum transfer miktari.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Minimum Miktar</th>
                        <td>
                            <input type="number" name="transfer_min_amount" value="<?php echo esc_attr(get_option('gorilla_lr_transfer_min_amount', 10)); ?>" min="1" max="1000" step="1" style="width:120px;">
                            <p class="description">Tek seferde minimum transfer miktari.</p>
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top:30px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">🎟️ Akilli Kupon Olusturma</h3>
                <table class="form-table">
                    <tr>
                        <th>Akilli Kupon</th>
                        <td>
                            <label><input type="checkbox" name="smart_coupon_enabled" value="yes" <?php checked(get_option('gorilla_lr_smart_coupon_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Belirli suredir alisveris yapmayan musterilere favori kategorilerine ozel otomatik kupon gonderir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Inaktivite Suresi</th>
                        <td>
                            <input type="number" name="smart_coupon_inactive_days" value="<?php echo esc_attr(get_option('gorilla_lr_smart_coupon_inactive_days', 21)); ?>" min="7" max="90" style="width:80px;"> gun
                            <p class="description">Bu kadar gun alisveris yapmayanlara kupon gonderilir (churn sisteminden once).</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Indirim Orani</th>
                        <td>
                            <input type="number" name="smart_coupon_discount" value="<?php echo esc_attr(get_option('gorilla_lr_smart_coupon_discount', 10)); ?>" min="1" max="50" style="width:80px;"> %
                            <p class="description">Otomatik olusturulan kuponun indirim yuzdesi.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Kupon Gecerlilik</th>
                        <td>
                            <input type="number" name="smart_coupon_expiry" value="<?php echo esc_attr(get_option('gorilla_lr_smart_coupon_expiry', 14)); ?>" min="1" max="60" style="width:80px;"> gun
                            <p class="description">Olusturulan kuponun gecerlilik suresi.</p>
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top:30px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">🔔 Social Proof Popup</h3>
                <table class="form-table">
                    <tr>
                        <th>Social Proof</th>
                        <td>
                            <label><input type="checkbox" name="social_proof_enabled" value="yes" <?php checked(get_option('gorilla_lr_social_proof_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Site genelinde "Ahmet Diamond seviyeye yukseldi!" gibi bildirimler gosterir. Sosyal kanit etkisi ile musteri katilimini artirir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Anonim Mod</th>
                        <td>
                            <label><input type="checkbox" name="social_proof_anonymize" value="yes" <?php checked(get_option('gorilla_lr_social_proof_anonymize', 'no'), 'yes'); ?>> Anonim</label>
                            <p class="description">Aktif olursa gercek isimler yerine "Bir musteri" gibi anonim ifadeler kullanilir.</p>
                        </td>
                    </tr>
                </table>
            </div>

                <h3 style="margin-top:30px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">📱 SMS Bildirimleri (Twilio)</h3>
                <table class="form-table">
                    <tr>
                        <th>SMS Bildirimleri</th>
                        <td>
                            <label><input type="checkbox" name="sms_enabled" value="yes" <?php checked(get_option('gorilla_lr_sms_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Twilio uzerinden SMS bildirimleri gonderir. Musteri hesap sayfasindan opt-in/opt-out yapabilir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Twilio Account SID</th>
                        <td>
                            <input type="text" name="twilio_sid" value="<?php echo esc_attr(function_exists('gorilla_sms_decrypt') ? gorilla_sms_decrypt(get_option('gorilla_lr_twilio_sid', '')) : get_option('gorilla_lr_twilio_sid', '')); ?>" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>Twilio Auth Token</th>
                        <td>
                            <input type="password" name="twilio_token" value="<?php echo esc_attr(function_exists('gorilla_sms_decrypt') ? gorilla_sms_decrypt(get_option('gorilla_lr_twilio_token', '')) : get_option('gorilla_lr_twilio_token', '')); ?>" placeholder="Auth Token" class="regular-text">
                            <p class="description">Twilio Console > Account > API Keys bolumunden alabilirsiniz.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Gonderici Numara</th>
                        <td>
                            <input type="text" name="twilio_from" value="<?php echo esc_attr(get_option('gorilla_lr_twilio_from', '')); ?>" placeholder="+1234567890" style="width:200px;">
                            <p class="description">Twilio'dan aldığınız telefon numarası veya Messaging Service SID.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>SMS Olaylari</th>
                        <td>
                            <?php $sms_events = get_option('gorilla_lr_sms_events', array()); if (!is_array($sms_events)) $sms_events = array(); ?>
                            <label style="display:block; margin-bottom:4px;"><input type="checkbox" name="sms_events[]" value="tier_upgrade" <?php checked(in_array('tier_upgrade', $sms_events)); ?>> Seviye Yukselmesi</label>
                            <label style="display:block; margin-bottom:4px;"><input type="checkbox" name="sms_events[]" value="level_up" <?php checked(in_array('level_up', $sms_events)); ?>> XP Level Up</label>
                            <label style="display:block; margin-bottom:4px;"><input type="checkbox" name="sms_events[]" value="credit_earned" <?php checked(in_array('credit_earned', $sms_events)); ?>> Credit Kazanimi</label>
                            <label style="display:block; margin-bottom:4px;"><input type="checkbox" name="sms_events[]" value="spin_win" <?php checked(in_array('spin_win', $sms_events)); ?>> Cark Odulu</label>
                            <label style="display:block; margin-bottom:4px;"><input type="checkbox" name="sms_events[]" value="badge_earned" <?php checked(in_array('badge_earned', $sms_events)); ?>> Rozet Kazanimi</label>
                        </td>
                    </tr>
                </table>

            <!-- STORE CREDIT AYARLARI -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">💳 Store Credit Ayarlari</h2>
                <table class="form-table">
                    <tr>
                        <th>Minimum Siparis Tutari</th>
                        <td>
                            <input type="number" name="credit_min_order" value="<?php echo esc_attr(get_option('gorilla_lr_credit_min_order', 0)); ?>" min="0" step="0.01" style="width:150px;"> <?php echo function_exists('get_woocommerce_currency_symbol') ? esc_html(get_woocommerce_currency_symbol()) : 'TL'; ?>
                            <p class="description">Store credit kullanmak icin minimum siparis tutari. 0 = sinir yok.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Credit Gecerlilik Suresi (gun)</th>
                        <td>
                            <input type="number" name="credit_expiry_days" value="<?php echo esc_attr(get_option('gorilla_lr_credit_expiry_days', 0)); ?>" min="0" max="365" style="width:100px;"> gun
                            <p class="description">Kazanilan credit'in gecerlilik suresi (gun). 0 = suresi dolmaz.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Expiry Uyari Suresi (gun)</th>
                        <td>
                            <input type="number" name="credit_expiry_warn_days" value="<?php echo esc_attr(get_option('gorilla_lr_credit_expiry_warn_days', 7)); ?>" min="0" max="90" style="width:100px;"> gun once
                            <p class="description">Credit suresi dolmadan kac gun once uyari gonderilsin. 0 = uyari gonderilmez.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Kupon Olusturma</th>
                        <td>
                            <select name="coupon_enabled" style="width:120px;">
                                <option value="no" <?php selected(get_option('gorilla_lr_coupon_enabled', 'no'), 'no'); ?>>Hayir</option>
                                <option value="yes" <?php selected(get_option('gorilla_lr_coupon_enabled', 'no'), 'yes'); ?>>Evet</option>
                            </select>
                            <p class="description">Aktif olursa musteriler credit bakiyelerinden WooCommerce kuponu olusturabilir.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- KAYDET -->
            <p style="max-width:900px;">
                <button type="submit" name="gorilla_save_settings" value="1" class="button button-primary button-hero" style="width:100%; font-size:16px; padding:12px;">
                    💾 Ayarları Kaydet
                </button>
            </p>
        </form>

        <!-- BİLGİ -->
        <div style="background:#fff3cd; padding:20px; border-radius:12px; max-width:900px; margin-top:20px; border-left:5px solid #ffc107;">
            <h3 style="margin-top:0;">⚠️ PayTR Taksit Ayarları Hakkında</h3>
            <p>Bu eklenti, uygun seviyedeki müşterilere "<strong>vade farksız taksit hakkınız var</strong>" bilgisi gösterir. Ancak PayTR ödeme panelinden taksit ayarlarının ayrıca yapılması gerekir:</p>
            <ol>
                <li>PayTR Mağaza Paneline giriş yapın</li>
                <li>Ayarlar → Taksit Ayarları bölümüne gidin</li>
                <li>İlgili banka kartları için taksit seçeneklerini aktif edin</li>
                <li>Vade farkı oranlarını 0 (sıfır) olarak ayarlayın</li>
            </ol>
            <p style="margin-bottom:0;"><strong>Not:</strong> Eklenti, taksit hakkı olan müşterileri sipariş notunda belirtir, böylece taksit oranlarını manuel kontrol edebilirsiniz.</p>
        </div>
    </div>
    <?php
}
