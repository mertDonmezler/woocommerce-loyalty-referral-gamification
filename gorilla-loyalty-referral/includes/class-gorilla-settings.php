<?php
/**
 * Gorilla LR - Ayarlar Modülü
 * Admin panelden tüm değerleri değiştirebilme
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
    update_option('gorilla_lr_enabled_referral', $validate_yesno($_POST['enabled_referral'] ?? 'no'));
    update_option('gorilla_lr_period_months', max(1, min(24, intval($_POST['period_months'] ?? 6))));
    update_option('gorilla_lr_referral_rate', max(1, min(100, intval($_POST['referral_rate'] ?? 35))));
    update_option('gorilla_lr_credit_min_order', max(0, floatval($_POST['credit_min_order'] ?? 0)));
    update_option('gorilla_lr_credit_expiry_days', max(0, intval($_POST['credit_expiry_days'] ?? 0)));
    update_option('gorilla_lr_credit_expiry_warn_days', max(0, min(90, intval($_POST['credit_expiry_warn_days'] ?? 7))));

    // Affiliate ayarlari
    update_option('gorilla_lr_enabled_affiliate', $validate_yesno($_POST['enabled_affiliate'] ?? 'no'));
    update_option('gorilla_lr_affiliate_rate', max(1, min(100, intval($_POST['affiliate_rate'] ?? 10))));
    update_option('gorilla_lr_affiliate_cookie_days', max(1, min(365, intval($_POST['affiliate_cookie_days'] ?? 30))));
    update_option('gorilla_lr_affiliate_min_order', max(0, floatval($_POST['affiliate_min_order'] ?? 0)));
    update_option('gorilla_lr_affiliate_first_only', $validate_yesno($_POST['affiliate_first_only'] ?? 'no'));
    update_option('gorilla_lr_affiliate_allow_self', $validate_yesno($_POST['affiliate_allow_self'] ?? 'no'));

    // XP & Level ayarlari
    update_option('gorilla_lr_enabled_xp', $validate_yesno($_POST['enabled_xp'] ?? 'no'));
    update_option('gorilla_lr_xp_per_order_rate', max(1, min(1000, intval($_POST['xp_per_order_rate'] ?? 10))));
    update_option('gorilla_lr_xp_review', max(0, min(500, intval($_POST['xp_review'] ?? 25))));
    update_option('gorilla_lr_xp_referral', max(0, min(500, intval($_POST['xp_referral'] ?? 50))));
    update_option('gorilla_lr_xp_affiliate', max(0, min(500, intval($_POST['xp_affiliate'] ?? 30))));
    update_option('gorilla_lr_xp_first_order', max(0, min(1000, intval($_POST['xp_first_order'] ?? 100))));
    update_option('gorilla_lr_xp_register', max(0, min(500, intval($_POST['xp_register'] ?? 10))));
    update_option('gorilla_lr_xp_profile', max(0, min(500, intval($_POST['xp_profile'] ?? 20))));

    // Seasonal Bonus ayarlari
    update_option('gorilla_lr_bonus_enabled', $validate_yesno($_POST['bonus_enabled'] ?? 'no'));
    update_option('gorilla_lr_bonus_multiplier', max(1, min(5, floatval($_POST['bonus_multiplier'] ?? 1.5))));
    update_option('gorilla_lr_bonus_start', sanitize_text_field($_POST['bonus_start'] ?? ''));
    update_option('gorilla_lr_bonus_end', sanitize_text_field($_POST['bonus_end'] ?? ''));
    update_option('gorilla_lr_bonus_label', sanitize_text_field($_POST['bonus_label'] ?? ''));

    // Gamification: Birthday
    update_option('gorilla_lr_birthday_enabled', $validate_yesno($_POST['birthday_enabled'] ?? 'no'));
    update_option('gorilla_lr_birthday_xp', max(0, min(1000, intval($_POST['birthday_xp'] ?? 50))));
    update_option('gorilla_lr_birthday_credit', max(0, min(100, floatval($_POST['birthday_credit'] ?? 10))));

    // Gamification: Login Streak
    update_option('gorilla_lr_streak_enabled', $validate_yesno($_POST['streak_enabled'] ?? 'no'));
    update_option('gorilla_lr_streak_daily_xp', max(0, min(100, intval($_POST['streak_daily_xp'] ?? 5))));
    update_option('gorilla_lr_streak_7day_bonus', max(0, min(500, intval($_POST['streak_7day_bonus'] ?? 50))));
    update_option('gorilla_lr_streak_30day_bonus', max(0, min(1000, intval($_POST['streak_30day_bonus'] ?? 200))));

    // Gamification: Badges
    update_option('gorilla_lr_badges_enabled', $validate_yesno($_POST['badges_enabled'] ?? 'no'));

    // Gamification: Leaderboard
    update_option('gorilla_lr_leaderboard_enabled', $validate_yesno($_POST['leaderboard_enabled'] ?? 'no'));
    update_option('gorilla_lr_leaderboard_anonymize', $validate_yesno($_POST['leaderboard_anonymize'] ?? 'no'));
    update_option('gorilla_lr_leaderboard_limit', max(5, min(50, intval($_POST['leaderboard_limit'] ?? 10))));

    // Gamification: Milestones
    update_option('gorilla_lr_milestones_enabled', $validate_yesno($_POST['milestones_enabled'] ?? 'no'));

    // Dual Referral
    update_option('gorilla_lr_dual_referral_enabled', $validate_yesno($_POST['dual_referral_enabled'] ?? 'no'));
    update_option('gorilla_lr_dual_referral_type', in_array($_POST['dual_referral_type'] ?? '', array('percent', 'fixed_cart')) ? $_POST['dual_referral_type'] : 'percent');
    update_option('gorilla_lr_dual_referral_amount', max(0, min(100, floatval($_POST['dual_referral_amount'] ?? 10))));
    update_option('gorilla_lr_dual_referral_min_order', max(0, floatval($_POST['dual_referral_min_order'] ?? 0)));
    update_option('gorilla_lr_dual_referral_expiry_days', max(1, min(365, intval($_POST['dual_referral_expiry_days'] ?? 30))));

    // Tiered Affiliate
    update_option('gorilla_lr_tiered_affiliate_enabled', $validate_yesno($_POST['tiered_affiliate_enabled'] ?? 'no'));

    // Recurring Affiliate
    update_option('gorilla_lr_recurring_affiliate_enabled', $validate_yesno($_POST['recurring_affiliate_enabled'] ?? 'no'));
    update_option('gorilla_lr_recurring_affiliate_rate', max(1, min(50, floatval($_POST['recurring_affiliate_rate'] ?? 5))));
    update_option('gorilla_lr_recurring_affiliate_months', max(1, min(24, intval($_POST['recurring_affiliate_months'] ?? 6))));
    update_option('gorilla_lr_recurring_affiliate_max_orders', max(0, min(100, intval($_POST['recurring_affiliate_max_orders'] ?? 0))));

    // Spin Wheel
    update_option('gorilla_lr_spin_enabled', $validate_yesno($_POST['spin_enabled'] ?? 'no'));

    // Points Shop
    update_option('gorilla_lr_points_shop_enabled', $validate_yesno($_POST['points_shop_enabled'] ?? 'no'));

    // Social Share
    update_option('gorilla_lr_social_share_enabled', $validate_yesno($_POST['social_share_enabled'] ?? 'no'));
    update_option('gorilla_lr_social_share_xp', max(0, min(100, intval($_POST['social_share_xp'] ?? 10))));

    // QR
    update_option('gorilla_lr_qr_enabled', $validate_yesno($_POST['qr_enabled'] ?? 'no'));

    // Level ayarları
    $levels = array();
    $level_keys = $_POST['level_key'] ?? array();

    for ($i = 0; $i < count($level_keys); $i++) {
        $key = sanitize_key($level_keys[$i]);
        if (empty($key)) continue;

        $levels[$key] = array(
            'label'  => sanitize_text_field($_POST['level_label'][$i] ?? ''),
            'min_xp' => intval($_POST['level_min_xp'][$i] ?? 0),
            'emoji'  => sanitize_text_field($_POST['level_emoji'][$i] ?? '🏅'),
            'color'  => sanitize_hex_color($_POST['level_color'][$i] ?? '#999999'),
        );
    }

    if (!empty($levels)) {
        // Min XP'ye göre sırala
        uasort($levels, function($a, $b) { return $a['min_xp'] <=> $b['min_xp']; });
        update_option('gorilla_lr_levels', $levels);
    }

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

function gorilla_get_tiers() {
    static $cached = null;
    if ($cached !== null) return $cached;

    $tiers = get_option('gorilla_lr_tiers', array());
    if (empty($tiers)) {
        // Varsayılan
        $cached = array(
            'bronze'   => array('label' => 'Bronz',   'min' => 1500,  'discount' => 5,  'color' => '#CD7F32', 'emoji' => '🥉', 'installment' => 0),
            'silver'   => array('label' => 'Gümüş',   'min' => 3000,  'discount' => 10, 'color' => '#C0C0C0', 'emoji' => '🥈', 'installment' => 0),
            'gold'     => array('label' => 'Altın',    'min' => 6000,  'discount' => 15, 'color' => '#FFD700', 'emoji' => '🥇', 'installment' => 0),
            'platinum' => array('label' => 'Platin',   'min' => 10000, 'discount' => 20, 'color' => '#E5E4E2', 'emoji' => '💎', 'installment' => 0),
            'diamond'  => array('label' => 'Elmas',    'min' => 20000, 'discount' => 25, 'color' => '#B9F2FF', 'emoji' => '👑', 'installment' => 3),
        );
        return $cached;
    }
    $cached = $tiers;
    return $cached;
}

// ── Ayarlar Sayfası Render ───────────────────────────────
function gorilla_settings_page_render() {
    if (!current_user_can('manage_woocommerce')) return;
    
    $enabled_loyalty  = get_option('gorilla_lr_enabled_loyalty', 'yes');
    $enabled_referral = get_option('gorilla_lr_enabled_referral', 'yes');
    $period           = get_option('gorilla_lr_period_months', 6);
    $ref_rate         = get_option('gorilla_lr_referral_rate', 35);
    $credit_min       = get_option('gorilla_lr_credit_min_order', 0);
    $credit_expiry    = get_option('gorilla_lr_credit_expiry_days', 0);
    $tiers            = gorilla_get_tiers();

    // Affiliate ayarları
    $enabled_affiliate   = get_option('gorilla_lr_enabled_affiliate', 'yes');
    $affiliate_rate      = get_option('gorilla_lr_affiliate_rate', 10);
    $affiliate_cookie    = get_option('gorilla_lr_affiliate_cookie_days', 30);
    $affiliate_min       = get_option('gorilla_lr_affiliate_min_order', 0);
    $affiliate_first     = get_option('gorilla_lr_affiliate_first_only', 'no');
    $affiliate_self      = get_option('gorilla_lr_affiliate_allow_self', 'no');

    // XP & Level ayarları
    $enabled_xp         = get_option('gorilla_lr_enabled_xp', 'yes');
    $xp_order_rate      = get_option('gorilla_lr_xp_per_order_rate', 10);
    $xp_review          = get_option('gorilla_lr_xp_review', 25);
    $xp_referral        = get_option('gorilla_lr_xp_referral', 50);
    $xp_affiliate       = get_option('gorilla_lr_xp_affiliate', 30);
    $xp_first_order     = get_option('gorilla_lr_xp_first_order', 100);
    $xp_register        = get_option('gorilla_lr_xp_register', 10);
    $xp_profile         = get_option('gorilla_lr_xp_profile', 20);
    $levels             = get_option('gorilla_lr_levels', array());

    // Varsayılan level'lar
    if (empty($levels)) {
        $levels = array(
            'level_1' => array('label' => 'Çaylak',      'min_xp' => 0,    'emoji' => '🌱', 'color' => '#a3e635'),
            'level_2' => array('label' => 'Keşifçi',     'min_xp' => 50,   'emoji' => '🔍', 'color' => '#22d3ee'),
            'level_3' => array('label' => 'Alışverişçi', 'min_xp' => 150,  'emoji' => '🛒', 'color' => '#60a5fa'),
            'level_4' => array('label' => 'Sadık',       'min_xp' => 400,  'emoji' => '⭐', 'color' => '#facc15'),
            'level_5' => array('label' => 'Uzman',       'min_xp' => 800,  'emoji' => '🏅', 'color' => '#f97316'),
            'level_6' => array('label' => 'VIP',         'min_xp' => 1500, 'emoji' => '💎', 'color' => '#a855f7'),
            'level_7' => array('label' => 'Efsane',      'min_xp' => 3000, 'emoji' => '👑', 'color' => '#fbbf24'),
        );
    }

    settings_errors('gorilla_settings');
    ?>
    <div class="wrap">
        <h1>⚙️ Gorilla Loyalty & Referral - Ayarlar</h1>
        
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
                        <th>Referans Programı</th>
                        <td>
                            <label><input type="checkbox" name="enabled_referral" value="yes" <?php checked($enabled_referral, 'yes'); ?>> Aktif</label>
                            <p class="description">Devre dışı bırakırsanız müşteriler referans başvurusu yapamaz.</p>
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
                        <th>Referans Komisyon Oranı</th>
                        <td>
                            <input type="number" name="referral_rate" value="<?php echo esc_attr($ref_rate); ?>" min="1" max="100" style="width:80px;"> <strong>%</strong>
                            <p class="description">Müşterinin video başvurusu onaylandığında sipariş tutarının yüzde kaçı store credit olarak verilecek.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Store Credit Minimum Sipariş</th>
                        <td>
                            <input type="number" name="credit_min_order" value="<?php echo esc_attr($credit_min); ?>" min="0" step="0.01" style="width:120px;"> ₺
                            <p class="description">Store credit kullanmak için minimum sepet tutarı. 0 = sınır yok.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Store Credit Gecerlilik Suresi</th>
                        <td>
                            <input type="number" name="credit_expiry_days" value="<?php echo esc_attr($credit_expiry); ?>" min="0" step="1" style="width:100px;"> gun
                            <p class="description">Yeni eklenen store credit'lerin gecerlilik suresi. 0 = suresiz.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Credit Suresi Uyari</th>
                        <td>
                            <input type="number" name="credit_expiry_warn_days" value="<?php echo esc_attr(get_option('gorilla_lr_credit_expiry_warn_days', 7)); ?>" min="0" max="90" step="1" style="width:100px;"> gun once
                            <p class="description">Store credit suresi dolmadan kac gun once musteri e-posta ile uyarilsin. 0 = uyari gonderme.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- AFFİLİATE AYARLARI -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">🔗 Affiliate Link Ayarları</h2>
                <p style="color:#666; margin-bottom:20px;">Müşteriler kendi referans linklerini paylaşarak, linke tıklayan kişilerin alışverişlerinden komisyon kazanır. Video içerik programından farklı olarak admin onayı gerektirmez.</p>
                <table class="form-table">
                    <tr>
                        <th>Affiliate Sistemi</th>
                        <td>
                            <label><input type="checkbox" name="enabled_affiliate" value="yes" <?php checked($enabled_affiliate, 'yes'); ?>> Aktif</label>
                            <p class="description">Devre dışı bırakırsanız müşteriler affiliate linki kullanamaz.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Affiliate Komisyon Oranı</th>
                        <td>
                            <input type="number" name="affiliate_rate" value="<?php echo esc_attr($affiliate_rate); ?>" min="1" max="100" style="width:80px;"> <strong>%</strong>
                            <p class="description">Affiliate link üzerinden gelen siparişlerde referrer'a verilecek komisyon oranı.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Cookie Süresi</th>
                        <td>
                            <input type="number" name="affiliate_cookie_days" value="<?php echo esc_attr($affiliate_cookie); ?>" min="1" max="365" style="width:80px;"> gün
                            <p class="description">Affiliate linke tıklayan ziyaretçi bu süre içinde alışveriş yaparsa komisyon verilir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Minimum Sipariş Tutarı</th>
                        <td>
                            <input type="number" name="affiliate_min_order" value="<?php echo esc_attr($affiliate_min); ?>" min="0" step="0.01" style="width:120px;"> ₺
                            <p class="description">Bu tutarın altındaki siparişlerde affiliate komisyonu verilmez. 0 = sınır yok.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Sadece İlk Sipariş</th>
                        <td>
                            <label><input type="checkbox" name="affiliate_first_only" value="yes" <?php checked($affiliate_first, 'yes'); ?>> Sadece yeni müşteriler için komisyon</label>
                            <p class="description">Aktif edilirse, daha önce sipariş vermiş müşteriler için affiliate komisyonu verilmez.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Self-Referral</th>
                        <td>
                            <label><input type="checkbox" name="affiliate_allow_self" value="yes" <?php checked($affiliate_self, 'yes'); ?>> Kendi linkinden alışverişe izin ver</label>
                            <p class="description">Aktif edilirse, kullanıcılar kendi affiliate linkleri üzerinden alışveriş yaparak komisyon kazanabilir.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- XP & LEVEL AYARLARI -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">🎮 XP & Level Sistemi</h2>
                <p style="color:#666; margin-bottom:20px;">Müşteriler çeşitli eylemlerden XP kazanır ve level atlar. Bu sistem tier indirimlerinden bağımsız çalışır - sadece gamification amaçlıdır.</p>
                <table class="form-table">
                    <tr>
                        <th>XP Sistemi</th>
                        <td>
                            <label><input type="checkbox" name="enabled_xp" value="yes" <?php checked($enabled_xp, 'yes'); ?>> Aktif</label>
                            <p class="description">Devre dışı bırakırsanız XP kazanımı ve level sistemi çalışmaz.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Sipariş XP Oranı</th>
                        <td>
                            Sipariş tutarı / <input type="number" name="xp_per_order_rate" value="<?php echo esc_attr($xp_order_rate); ?>" min="1" max="1000" style="width:80px;"> = XP
                            <p class="description">Örn: 100₺ sipariş / 10 = 10 XP</p>
                        </td>
                    </tr>
                    <tr>
                        <th>İlk Sipariş Bonusu</th>
                        <td>
                            <input type="number" name="xp_first_order" value="<?php echo esc_attr($xp_first_order); ?>" min="0" max="1000" style="width:80px;"> XP
                            <p class="description">Müşterinin ilk siparişinde ekstra XP bonusu.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Ürün Yorumu</th>
                        <td>
                            <input type="number" name="xp_review" value="<?php echo esc_attr($xp_review); ?>" min="0" max="500" style="width:80px;"> XP
                            <p class="description">Onaylanan her ürün yorumu için verilecek XP.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Video Referans Onayı</th>
                        <td>
                            <input type="number" name="xp_referral" value="<?php echo esc_attr($xp_referral); ?>" min="0" max="500" style="width:80px;"> XP
                            <p class="description">Video referans başvurusu onaylandığında verilecek XP.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Affiliate Satış</th>
                        <td>
                            <input type="number" name="xp_affiliate" value="<?php echo esc_attr($xp_affiliate); ?>" min="0" max="500" style="width:80px;"> XP
                            <p class="description">Her başarılı affiliate satışı için verilecek XP.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Kayıt Bonusu</th>
                        <td>
                            <input type="number" name="xp_register" value="<?php echo esc_attr($xp_register); ?>" min="0" max="500" style="width:80px;"> XP
                            <p class="description">Yeni üye kaydında hoşgeldin XP'si.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Profil Tamamlama</th>
                        <td>
                            <input type="number" name="xp_profile" value="<?php echo esc_attr($xp_profile); ?>" min="0" max="500" style="width:80px;"> XP
                            <p class="description">Profil bilgilerini (ad, adres, telefon) tamamlayanlara verilecek XP.</p>
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top:30px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">🏆 Level Tanımları</h3>
                <p style="color:#666; margin-bottom:15px;">Level'ları ve XP eşiklerini özelleştirin. Minimum XP'ye göre otomatik sıralanır.</p>

                <table class="widefat" id="gorilla-levels-table" style="border-collapse:separate; border-spacing:0 8px;">
                    <thead>
                        <tr style="background:#f8f9fa;">
                            <th style="width:50px;">Emoji</th>
                            <th style="width:90px;">Anahtar</th>
                            <th>Level Adı</th>
                            <th style="width:120px;">Min. XP</th>
                            <th style="width:70px;">Renk</th>
                            <th style="width:50px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($levels as $lkey => $lvl): ?>
                        <tr class="gorilla-level-row" style="background:#fff; border:1px solid #eee;">
                            <td><input type="text" name="level_emoji[]" value="<?php echo esc_attr($lvl['emoji']); ?>" style="width:45px; text-align:center; font-size:20px;"></td>
                            <td><input type="text" name="level_key[]" value="<?php echo esc_attr($lkey); ?>" style="width:90px; font-family:monospace; font-size:12px;" readonly></td>
                            <td><input type="text" name="level_label[]" value="<?php echo esc_attr($lvl['label']); ?>" style="width:100%; font-weight:600;" required></td>
                            <td><input type="number" name="level_min_xp[]" value="<?php echo esc_attr($lvl['min_xp']); ?>" min="0" step="10" style="width:100%;" required></td>
                            <td><input type="color" name="level_color[]" value="<?php echo esc_attr($lvl['color']); ?>" style="width:45px; height:35px; padding:2px;"></td>
                            <td><button type="button" class="button gorilla-remove-level" title="Sil" style="color:#dc3545;">✕</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <button type="button" id="gorilla-add-level" class="button" style="margin-top:10px;">➕ Yeni Level Ekle</button>

                <script>
                document.getElementById('gorilla-add-level').addEventListener('click', function() {
                    var tbody = document.querySelector('#gorilla-levels-table tbody');
                    var count = tbody.querySelectorAll('tr').length;
                    var key = 'level_' + (count + 1);
                    var row = document.createElement('tr');
                    row.className = 'gorilla-level-row';
                    row.style.background = '#fff';
                    row.innerHTML = '<td><input type="text" name="level_emoji[]" value="🏅" style="width:45px; text-align:center; font-size:20px;"></td>' +
                        '<td><input type="text" name="level_key[]" value="' + key + '" style="width:90px; font-family:monospace; font-size:12px;"></td>' +
                        '<td><input type="text" name="level_label[]" value="" style="width:100%; font-weight:600;" required placeholder="Level Adı"></td>' +
                        '<td><input type="number" name="level_min_xp[]" value="0" min="0" step="10" style="width:100%;" required></td>' +
                        '<td><input type="color" name="level_color[]" value="#999999" style="width:45px; height:35px; padding:2px;"></td>' +
                        '<td><button type="button" class="button gorilla-remove-level" title="Sil" style="color:#dc3545;">✕</button></td>';
                    tbody.appendChild(row);
                });
                document.addEventListener('click', function(e) {
                    if (e.target.classList.contains('gorilla-remove-level')) {
                        if (confirm('Bu level\'ı silmek istediğinize emin misiniz?')) {
                            e.target.closest('tr').remove();
                        }
                    }
                });
                </script>
            </div>

            <!-- SEASONAL BONUS AYARLARI -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">Seasonal Bonus Carpani</h2>
                <p style="color:#666; margin-bottom:20px;">Belirli tarihlerde XP, referral credit ve affiliate komisyonlarini carpan ile artirabilirsiniz. Kampanya donemi icin idealdir.</p>
                <table class="form-table">
                    <tr>
                        <th>Bonus Sistemi</th>
                        <td>
                            <label><input type="checkbox" name="bonus_enabled" value="yes" <?php checked(get_option('gorilla_lr_bonus_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Aktif edildiginde belirlenen tarih araliginda carpan uygulanir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Carpan</th>
                        <td>
                            <input type="number" name="bonus_multiplier" value="<?php echo esc_attr(get_option('gorilla_lr_bonus_multiplier', 1.5)); ?>" min="1" max="5" step="0.1" style="width:80px;">x
                            <p class="description">Ornek: 1.5 = %50 daha fazla XP/credit/komisyon. 2 = 2 kat.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Baslangic Tarihi</th>
                        <td>
                            <input type="date" name="bonus_start" value="<?php echo esc_attr(get_option('gorilla_lr_bonus_start', '')); ?>" style="width:180px;">
                        </td>
                    </tr>
                    <tr>
                        <th>Bitis Tarihi</th>
                        <td>
                            <input type="date" name="bonus_end" value="<?php echo esc_attr(get_option('gorilla_lr_bonus_end', '')); ?>" style="width:180px;">
                        </td>
                    </tr>
                    <tr>
                        <th>Kampanya Etiketi</th>
                        <td>
                            <input type="text" name="bonus_label" value="<?php echo esc_attr(get_option('gorilla_lr_bonus_label', '')); ?>" style="width:300px;" placeholder="Ornek: Yilbasi Kampanyasi">
                            <p class="description">Opsiyonel: Kampanya ismi (log ve bildirimlerde goruntulenir).</p>
                        </td>
                    </tr>
                </table>
            </div>

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
                        <th>Dogum Gunu XP</th>
                        <td>
                            <input type="number" name="birthday_xp" value="<?php echo esc_attr(get_option('gorilla_lr_birthday_xp', 50)); ?>" min="0" max="1000" style="width:80px;"> XP
                            <p class="description">Dogum gununde verilecek bonus XP miktari.</p>
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

                <h3 style="margin-top:30px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">🔥 Giris Serisi (Login Streak)</h3>
                <table class="form-table">
                    <tr>
                        <th>Giris Serisi</th>
                        <td>
                            <label><input type="checkbox" name="streak_enabled" value="yes" <?php checked(get_option('gorilla_lr_streak_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Musteriler ardisik gunlerde giris yaparak bonus XP kazanir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Gunluk Giris XP</th>
                        <td>
                            <input type="number" name="streak_daily_xp" value="<?php echo esc_attr(get_option('gorilla_lr_streak_daily_xp', 5)); ?>" min="0" max="100" style="width:80px;"> XP
                            <p class="description">Her gun giris yapan musteriye verilecek XP.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>7 Gun Seri Bonusu</th>
                        <td>
                            <input type="number" name="streak_7day_bonus" value="<?php echo esc_attr(get_option('gorilla_lr_streak_7day_bonus', 50)); ?>" min="0" max="500" style="width:80px;"> XP
                            <p class="description">Ardisik 7 gun giris yapildiginda verilen ekstra bonus XP.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>30 Gun Seri Bonusu</th>
                        <td>
                            <input type="number" name="streak_30day_bonus" value="<?php echo esc_attr(get_option('gorilla_lr_streak_30day_bonus', 200)); ?>" min="0" max="1000" style="width:80px;"> XP
                            <p class="description">Ardisik 30 gun giris yapildiginda verilen ekstra bonus XP.</p>
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
                            <p class="description">Musteriler belirli XP esiklerini asmca ozel oduller kazanir (ornek: 500 XP = ozel rozet).</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- REFERANS GELISTIRMELERI -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">🤝 Referans Gelistirmeleri</h2>
                <p style="color:#666; margin-bottom:20px;">Cift tarafli referans sistemi: Hem referans veren hem de davet edilen kisiye odul verilir.</p>

                <h3 style="margin-top:20px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">🎁 Cift Tarafli Referans (Dual Referral)</h3>
                <table class="form-table">
                    <tr>
                        <th>Cift Tarafli Referans</th>
                        <td>
                            <label><input type="checkbox" name="dual_referral_enabled" value="yes" <?php checked(get_option('gorilla_lr_dual_referral_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Aktif edilirse davet edilen kisi de ilk siparisinde indirim kuponu alir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Kupon Tipi</th>
                        <td>
                            <select name="dual_referral_type" style="width:200px;">
                                <option value="percent" <?php selected(get_option('gorilla_lr_dual_referral_type', 'percent'), 'percent'); ?>>Yuzde Indirim (%)</option>
                                <option value="fixed_cart" <?php selected(get_option('gorilla_lr_dual_referral_type', 'percent'), 'fixed_cart'); ?>>Sabit Tutar (₺)</option>
                            </select>
                            <p class="description">Davet edilen kisiye verilecek kuponun tipi.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Kupon Miktari</th>
                        <td>
                            <input type="number" name="dual_referral_amount" value="<?php echo esc_attr(get_option('gorilla_lr_dual_referral_amount', 10)); ?>" min="0" max="100" step="0.01" style="width:100px;">
                            <p class="description">Yuzde secildiyse % degeri, sabit tutar secildiyse ₺ degeri.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Minimum Siparis Tutari</th>
                        <td>
                            <input type="number" name="dual_referral_min_order" value="<?php echo esc_attr(get_option('gorilla_lr_dual_referral_min_order', 0)); ?>" min="0" step="0.01" style="width:120px;"> ₺
                            <p class="description">Kuponun gecerli olmasi icin minimum sepet tutari. 0 = sinir yok.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Kupon Gecerlilik Suresi</th>
                        <td>
                            <input type="number" name="dual_referral_expiry_days" value="<?php echo esc_attr(get_option('gorilla_lr_dual_referral_expiry_days', 30)); ?>" min="1" max="365" style="width:80px;"> gun
                            <p class="description">Olusturulan kuponun kac gun gecerli olacagi.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- GELISMIS AFFILIATE -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">📈 Gelismis Affiliate</h2>
                <p style="color:#666; margin-bottom:20px;">Katmanli komisyon ve tekrarlayan komisyon ile affiliate programinizi guclendirin.</p>

                <h3 style="margin-top:20px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">📊 Katmanli Komisyon (Tiered Commission)</h3>
                <table class="form-table">
                    <tr>
                        <th>Katmanli Komisyon</th>
                        <td>
                            <label><input type="checkbox" name="tiered_affiliate_enabled" value="yes" <?php checked(get_option('gorilla_lr_tiered_affiliate_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Affiliate'ler toplam satislarma gore artan komisyon oranlari kazanir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Katman Bilgisi</th>
                        <td>
                            <div style="background:#f0f9ff; padding:12px 16px; border-radius:8px; border-left:4px solid #0ea5e9;">
                                <strong>Varsayilan Katmanlar:</strong><br>
                                Bronz (0-10 satis): Temel oran | Gumus (11-50 satis): +%2 | Altin (51-100 satis): +%5 | Platin (100+ satis): +%10<br>
                                <em style="color:#666;">Katman yapilandirmasi su an kod uzerinden yonetilmektedir. Ileride admin panelden duzenlenebilir olacak.</em>
                            </div>
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top:30px; border-bottom:1px solid #f0f0f0; padding-bottom:10px;">🔄 Tekrarlayan Komisyon (Recurring Commission)</h3>
                <table class="form-table">
                    <tr>
                        <th>Tekrarlayan Komisyon</th>
                        <td>
                            <label><input type="checkbox" name="recurring_affiliate_enabled" value="yes" <?php checked(get_option('gorilla_lr_recurring_affiliate_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Affiliate'ler yonlendirdikleri musterilerin gelecekteki siparislerinden de komisyon kazanir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Tekrarlayan Komisyon Orani</th>
                        <td>
                            <input type="number" name="recurring_affiliate_rate" value="<?php echo esc_attr(get_option('gorilla_lr_recurring_affiliate_rate', 5)); ?>" min="1" max="50" step="0.1" style="width:80px;"> <strong>%</strong>
                            <p class="description">Ilk siparis sonrasi tekrarlayan siparisler icin uygulanacak komisyon orani.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Gecerlilik Suresi</th>
                        <td>
                            <input type="number" name="recurring_affiliate_months" value="<?php echo esc_attr(get_option('gorilla_lr_recurring_affiliate_months', 6)); ?>" min="1" max="24" style="width:80px;"> ay
                            <p class="description">Ilk siparisten sonra kac ay boyunca tekrarlayan komisyon verilecegi.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Maksimum Siparis Sayisi</th>
                        <td>
                            <input type="number" name="recurring_affiliate_max_orders" value="<?php echo esc_attr(get_option('gorilla_lr_recurring_affiliate_max_orders', 0)); ?>" min="0" max="100" style="width:80px;">
                            <p class="description">Tekrarlayan komisyon icin maksimum siparis sayisi. 0 = sinirsiz (sadece ay siniri gecerli).</p>
                        </td>
                    </tr>
                </table>
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
                    <tr>
                        <th>Odul Bilgisi</th>
                        <td>
                            <div style="background:#f0f9ff; padding:12px 16px; border-radius:8px; border-left:4px solid #0ea5e9;">
                                <em style="color:#666;">Sans carki odulleri su an kod uzerinden yonetilmektedir. Varsayilan oduller: XP bonusu, store credit, indirim kuponu, ucretsiz kargo. Ileride admin panelden odul yapilandirmasi eklenecek.</em>
                            </div>
                        </td>
                    </tr>
                </table>

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
