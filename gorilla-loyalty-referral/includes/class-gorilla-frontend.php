<?php
/**
 * Gorilla LR - Frontend (Müşteri Tarafı)
 * Hesabım sayfaları, ürün/sepet bilgilendirmeleri
 * v3.0.0 - CSS/JS harici dosyalara taşındı, Affiliate bölümü, Gamification UI
 */

if (!defined('ABSPATH')) exit;

// ── My Account Endpoint'leri ─────────────────────────────
// FIX: EP_ROOT kaldırıldı, sadece EP_PAGES kullan
add_action('init', function() {
    add_rewrite_endpoint('gorilla-loyalty', EP_PAGES);
    add_rewrite_endpoint('gorilla-referral', EP_PAGES);
});

// FIX: WooCommerce query vars filter eklendi - BU KRİTİK!
// Bu olmadan WooCommerce endpoint'leri tanımıyor
add_filter('woocommerce_get_query_vars', function($vars) {
    $vars['gorilla-loyalty'] = 'gorilla-loyalty';
    $vars['gorilla-referral'] = 'gorilla-referral';
    return $vars;
});

// ── My Account Menü ──────────────────────────────────────
add_filter('woocommerce_account_menu_items', function($items) {
    try {
        if (!is_array($items)) return $items;
        
        $new = array();
        foreach ($items as $key => $label) {
            $new[$key] = $label;
            if ($key === 'orders') {
                if (get_option('gorilla_lr_enabled_loyalty') === 'yes') {
                    $new['gorilla-loyalty'] = '🎖️ Sadakat Programı';
                }
                if (get_option('gorilla_lr_enabled_referral') === 'yes') {
                    $new['gorilla-referral'] = '🔗 Referans Programı';
                }
            }
        }
        return $new;
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla LR menu error: ' . $e->getMessage());
        }
        return $items;
    }
});


// ══════════════════════════════════════════════════════════
// SADAKAT PROGRAMI SAYFASI
// ══════════════════════════════════════════════════════════
add_action('woocommerce_account_gorilla-loyalty_endpoint', function() {
    try {
        if (!function_exists('gorilla_loyalty_calculate_tier') || !function_exists('gorilla_get_tiers') || get_option('gorilla_lr_enabled_loyalty', 'yes') !== 'yes') {
            echo '<p>Sadakat programı şu anda kullanılamıyor.</p>';
            return;
        }
        
        $user_id = get_current_user_id();

        // Dogum gunu kaydetme islemi
        if (isset($_POST['gorilla_birthday']) && wp_verify_nonce($_POST['_gorilla_birthday_nonce'] ?? '', 'gorilla_save_birthday')) {
            $birthday = sanitize_text_field($_POST['gorilla_birthday']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
                update_user_meta($user_id, '_gorilla_birthday', $birthday);
                if (function_exists('gorilla_badge_award')) {
                    gorilla_badge_award($user_id, 'birthday_club');
                }
            }
        }

        $tier = gorilla_loyalty_calculate_tier($user_id);
        $next = gorilla_loyalty_next_tier($user_id);
        $spending = $tier['spending'] ?? 0;
        $tiers = gorilla_get_tiers();
        $period = get_option('gorilla_lr_period_months', 6);
        $color = $tier['color'] ?? '#999';
        
        gorilla_frontend_styles();
        ?>
        <div class="glr-wrap">
            <h2 style="font-size:24px; font-weight:800; margin-bottom:20px;">🎖️ Sadakat Programı</h2>
            
            <!-- Mevcut Seviye Kartı -->
            <div class="glr-hero" style="background:linear-gradient(135deg, <?php echo esc_attr($color); ?>15, <?php echo esc_attr($color); ?>30); border:2px solid <?php echo esc_attr($color); ?>;">
                <div style="font-size:56px; line-height:1;"><?php echo esc_html($tier['emoji'] ?? '👤'); ?></div>
                <div style="font-size:28px; font-weight:800; color:#1f2937; margin:8px 0;"><?php echo esc_html($tier['label'] ?? 'Üye'); ?> Üye</div>
                <?php if (($tier['discount'] ?? 0) > 0): ?>
                    <div style="font-size:18px; color:#4b5563;">Tüm alışverişlerde <strong style="color:<?php echo esc_attr($color); ?>;">%<?php echo intval($tier['discount']); ?> indirim</strong></div>
                <?php else: ?>
                    <div style="font-size:16px; color:#9ca3af;">Alışveriş yaparak seviye kazanın!</div>
                <?php endif; ?>
                <?php if (($tier['installment'] ?? 0) > 0): ?>
                    <div style="margin-top:10px;">
                        <span style="background:#22c55e; color:#fff; padding:6px 18px; border-radius:20px; font-size:14px; font-weight:600;">
                            ✨ Vade Farksız <?php echo intval($tier['installment']); ?> Taksit
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Harcama & İlerleme -->
            <div class="glr-card">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div>
                        <div style="color:#6b7280; font-size:13px;">Son <?php echo intval($period); ?> Aydaki Harcamanız</div>
                        <div style="font-size:28px; font-weight:800; color:#1f2937;"><?php echo wc_price($spending); ?></div>
                    </div>
                    <?php if ($next): ?>
                    <div style="text-align:right;">
                        <div style="color:#6b7280; font-size:13px;">Sonraki Seviye: <?php echo esc_html($next['emoji'] ?? ''); ?> <?php echo esc_html($next['label'] ?? ''); ?></div>
                        <div style="font-size:18px; font-weight:700; color:#f59e0b;"><?php echo wc_price($next['remaining'] ?? 0); ?> kaldı</div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($next): ?>
                <div class="glr-progress-track">
                    <div class="glr-progress-bar" style="width:<?php echo min(100, floatval($next['progress'] ?? 0)); ?>%; background:linear-gradient(90deg, <?php echo esc_attr($color); ?>, #f59e0b);">
                        <?php echo round($next['progress'] ?? 0); ?>%
                    </div>
                </div>
                <?php else: ?>
                <div style="text-align:center; padding:15px 0; color:#22c55e; font-weight:700; font-size:16px;">👑 Tebrikler! En üst seviyedesiniz!</div>
                <?php endif; ?>
            </div>
            
            <!-- Tüm Seviyeler Grid -->
            <h3 style="margin-top:30px; font-size:18px;">📊 Tüm Seviyeler</h3>
            <div class="glr-tiers-grid">
                <?php if (is_array($tiers)): foreach ($tiers as $key => $t): 
                    if (!is_array($t)) continue;
                    $active = ($key === ($tier['key'] ?? ''));
                ?>
                <div class="glr-tier-card <?php echo $active ? 'glr-tier-active' : ''; ?>" style="<?php echo $active ? 'border-color:' . esc_attr($t['color'] ?? '#999') . '; box-shadow:0 4px 20px ' . esc_attr($t['color'] ?? '#999') . '33;' : ''; ?>">
                    <div style="font-size:36px;"><?php echo esc_html($t['emoji'] ?? '🎖️'); ?></div>
                    <div style="font-weight:700; font-size:16px; margin:4px 0;"><?php echo esc_html($t['label'] ?? ''); ?></div>
                    <div style="color:#9ca3af; font-size:12px;">min. <?php echo wc_price($t['min'] ?? 0); ?></div>
                    <div style="color:#1f2937; font-weight:800; font-size:20px; margin:6px 0;">%<?php echo intval($t['discount'] ?? 0); ?></div>
                    <?php if (($t['installment'] ?? 0) > 0): ?>
                        <div style="background:#dcfce7; color:#166534; padding:2px 10px; border-radius:10px; font-size:11px; font-weight:600;"><?php echo intval($t['installment']); ?> Taksit</div>
                    <?php endif; ?>
                    <?php if ($active): ?>
                        <div style="color:#22c55e; font-size:11px; font-weight:700; margin-top:6px;">✓ Şu anki</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; endif; ?>
            </div>
            
            <div class="glr-info-box">
                <strong>ℹ️ Nasıl çalışır?</strong>
                <p style="margin:8px 0 0;">Son <?php echo intval($period); ?> ay içindeki toplam harcamanıza göre seviyeniz otomatik belirlenir. Seviyenize uygun indirim, sepetinize otomatik olarak uygulanır. Seviyeniz her sipariş sonrası güncellenir.</p>
            </div>

            <?php
            // ═══ XP & LEVEL BÖLÜMÜ ═══
            if (get_option('gorilla_lr_enabled_xp', 'yes') === 'yes' && function_exists('gorilla_xp_calculate_level')):
                $xp_balance = gorilla_xp_get_balance($user_id);
                $current_level = gorilla_xp_calculate_level($user_id);
                $next_level = gorilla_xp_get_next_level($user_id);
                $xp_log = gorilla_xp_get_log($user_id, 5);
                $levels = gorilla_xp_get_levels();

                // XP ayarları
                $xp_order_rate = intval(get_option('gorilla_lr_xp_per_order_rate', 10));
                $xp_review = intval(get_option('gorilla_lr_xp_review', 25));
                $xp_referral = intval(get_option('gorilla_lr_xp_referral', 50));
                $xp_affiliate = intval(get_option('gorilla_lr_xp_affiliate', 30));

                $level_color = esc_attr($current_level['color'] ?? '#a3e635');
            ?>
            <hr style="border:none; border-top:2px dashed #e5e7eb; margin:35px 0;">

            <h2 style="font-size:24px; font-weight:800; margin-bottom:20px;">🎮 XP & Level Durumunuz</h2>

            <!-- Level Kartı -->
            <div class="glr-hero" style="background:linear-gradient(135deg, <?php echo $level_color; ?>15, <?php echo $level_color; ?>40); border:2px solid <?php echo $level_color; ?>;">
                <div style="font-size:56px; line-height:1;"><?php echo esc_html($current_level['emoji'] ?? '🌱'); ?></div>
                <div style="font-size:14px; color:#6b7280; margin-top:6px;">Level <?php echo intval($current_level['number'] ?? 1); ?></div>
                <div style="font-size:28px; font-weight:800; color:#1f2937; margin:4px 0;"><?php echo esc_html($current_level['label'] ?? 'Çaylak'); ?></div>
                <div style="font-size:20px; color:<?php echo $level_color; ?>; font-weight:700;"><?php echo number_format_i18n($xp_balance); ?> XP</div>

                <?php if ($next_level): ?>
                <div class="glr-progress-track" style="max-width:400px; margin:16px auto 0;">
                    <div class="glr-progress-bar" style="width:<?php echo min(100, floatval($next_level['progress'] ?? 0)); ?>%; background:linear-gradient(90deg, <?php echo $level_color; ?>, #f59e0b);">
                        <?php echo round($next_level['progress'] ?? 0); ?>%
                    </div>
                </div>
                <div style="font-size:14px; color:#6b7280; margin-top:10px;">
                    Sonraki: <?php echo esc_html($next_level['emoji'] ?? ''); ?> <strong><?php echo esc_html($next_level['label'] ?? ''); ?></strong>
                    — <?php echo number_format_i18n($next_level['remaining'] ?? 0); ?> XP kaldı
                </div>
                <?php else: ?>
                <div style="text-align:center; padding:12px 0; color:#22c55e; font-weight:700; font-size:16px;">👑 Tebrikler! En üst level'dasınız!</div>
                <?php endif; ?>
            </div>

            <!-- XP Kazanma Yolları -->
            <div class="glr-card">
                <h3 style="margin-top:0; font-size:16px;">📊 XP Kazanma Yolları</h3>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(100px, 1fr)); gap:12px; text-align:center; margin-top:12px;">
                    <div style="background:#f0fdf4; padding:14px 10px; border-radius:10px;">
                        <div style="font-size:24px;">🛒</div>
                        <div style="font-size:18px; font-weight:800; color:#22c55e;"><?php echo $xp_order_rate > 0 ? '₺/' . $xp_order_rate : '—'; ?></div>
                        <div style="font-size:11px; color:#6b7280;">Her Sipariş</div>
                    </div>
                    <div style="background:#fef3c7; padding:14px 10px; border-radius:10px;">
                        <div style="font-size:24px;">✍️</div>
                        <div style="font-size:18px; font-weight:800; color:#f59e0b;"><?php echo $xp_review; ?> XP</div>
                        <div style="font-size:11px; color:#6b7280;">Ürün Yorumu</div>
                    </div>
                    <div style="background:#fce7f3; padding:14px 10px; border-radius:10px;">
                        <div style="font-size:24px;">🎬</div>
                        <div style="font-size:18px; font-weight:800; color:#ec4899;"><?php echo $xp_referral; ?> XP</div>
                        <div style="font-size:11px; color:#6b7280;">Video Referans</div>
                    </div>
                    <div style="background:#eff6ff; padding:14px 10px; border-radius:10px;">
                        <div style="font-size:24px;">🔗</div>
                        <div style="font-size:18px; font-weight:800; color:#3b82f6;"><?php echo $xp_affiliate; ?> XP</div>
                        <div style="font-size:11px; color:#6b7280;">Affiliate Satış</div>
                    </div>
                </div>
            </div>

            <!-- Tüm Level'lar Grid -->
            <h3 style="margin-top:30px; font-size:18px;">🏆 Tüm Level'lar</h3>
            <div class="glr-tiers-grid">
                <?php if (is_array($levels)): foreach ($levels as $lkey => $lvl):
                    if (!is_array($lvl)) continue;
                    $level_num = intval(str_replace('level_', '', $lkey));
                    $is_current = ($lkey === ($current_level['key'] ?? ''));
                    $lvl_color = esc_attr($lvl['color'] ?? '#999');
                ?>
                <div class="glr-tier-card <?php echo $is_current ? 'glr-tier-active' : ''; ?>" style="<?php echo $is_current ? 'border-color:' . $lvl_color . '; box-shadow:0 4px 20px ' . $lvl_color . '33;' : ''; ?>">
                    <div style="font-size:36px;"><?php echo esc_html($lvl['emoji'] ?? '🏅'); ?></div>
                    <div style="font-weight:700; font-size:14px; margin:4px 0;">Level <?php echo $level_num; ?></div>
                    <div style="font-weight:600; font-size:16px; color:<?php echo $lvl_color; ?>;"><?php echo esc_html($lvl['label'] ?? ''); ?></div>
                    <div style="color:#9ca3af; font-size:11px; margin-top:4px;"><?php echo number_format_i18n($lvl['min_xp'] ?? 0); ?>+ XP</div>
                    <?php if ($is_current): ?>
                        <div style="color:#22c55e; font-size:11px; font-weight:700; margin-top:6px;">✓ Şu anki</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- Son XP Kazanımları -->
            <?php if (!empty($xp_log) && is_array($xp_log)): ?>
            <h3 style="margin-top:30px; font-size:16px;">📜 Son XP Kazanımları</h3>
            <div class="glr-card" style="padding:0; overflow:hidden;">
                <div style="font-size:13px;">
                    <?php foreach ($xp_log as $xp_entry):
                        if (!is_array($xp_entry)) continue;
                        $xp_amt = intval($xp_entry['amount'] ?? 0);
                        $xp_date = isset($xp_entry['created_at']) ? wp_date('d.m.Y', strtotime($xp_entry['created_at'])) : '';
                        $xp_color = $xp_amt >= 0 ? '#22c55e' : '#ef4444';
                        $xp_sign = $xp_amt >= 0 ? '+' : '';
                    ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid #f0f0f0;">
                        <div>
                            <span style="color:#6b7280; font-size:12px;"><?php echo esc_html($xp_date); ?></span>
                            <span style="margin-left:8px;"><?php echo esc_html($xp_entry['reason'] ?? ''); ?></span>
                        </div>
                        <span style="color:<?php echo $xp_color; ?>; font-weight:700; white-space:nowrap;"><?php echo $xp_sign . number_format_i18n(abs($xp_amt)); ?> XP</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; // XP bölümü sonu ?>

            <?php
            // ═══ ROZET VİTRİNİ ═══
            if (get_option('gorilla_lr_badges_enabled', 'no') === 'yes' && function_exists('gorilla_badge_get_user_badges')):
                $user_badges = gorilla_badge_get_user_badges($user_id);
            ?>
            <hr style="border:none; border-top:2px dashed #e5e7eb; margin:35px 0;">
            <h2 style="font-size:24px; font-weight:800; margin-bottom:20px;">🏅 Rozet Vitrini</h2>
            <?php if (!empty($user_badges) && is_array($user_badges)): ?>
            <div class="glr-badge-grid">
                <?php foreach ($user_badges as $badge):
                    if (!is_array($badge)) continue;
                    $is_earned = !empty($badge['earned_at']);
                ?>
                <div class="glr-badge-card <?php echo $is_earned ? 'earned' : 'locked'; ?>">
                    <div class="glr-badge-emoji" style="font-size:40px; line-height:1; margin-bottom:8px;"><?php echo esc_html($badge['emoji'] ?? '🏅'); ?></div>
                    <div style="font-weight:700; font-size:13px; color:#1f2937;"><?php echo esc_html($badge['label'] ?? ''); ?></div>
                    <div style="font-size:11px; color:#6b7280; margin-top:4px;"><?php echo esc_html($badge['description'] ?? ''); ?></div>
                    <?php if ($is_earned): ?>
                        <div style="font-size:11px; color:#22c55e; font-weight:600; margin-top:6px;"><?php echo esc_html(wp_date('d.m.Y', strtotime($badge['earned_at']))); ?></div>
                    <?php else: ?>
                        <div style="font-size:11px; color:#9ca3af; margin-top:6px;">Henuz kazanilmadi</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="glr-card" style="text-align:center; color:#9ca3af;">
                <p>Henuz kazanilmis rozetiniz bulunmuyor.</p>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php
            // ═══ LOGIN STREAK ═══
            if (get_option('gorilla_lr_streak_enabled', 'no') === 'yes'):
                $login_streak = intval(get_user_meta($user_id, '_gorilla_login_streak', true));
                $login_streak_best = intval(get_user_meta($user_id, '_gorilla_login_streak_best', true));
            ?>
            <hr style="border:none; border-top:2px dashed #e5e7eb; margin:35px 0;">
            <h2 style="font-size:24px; font-weight:800; margin-bottom:20px;">🔥 Giris Serisi</h2>
            <div class="glr-streak-card">
                <div class="glr-streak-flame" style="font-size:48px; line-height:1;">🔥</div>
                <div style="font-size:36px; font-weight:800; color:#92400e; margin:8px 0;"><?php echo $login_streak; ?> Gun</div>
                <div style="font-size:14px; color:#78350f;">Mevcut Seri</div>
                <div style="font-size:13px; color:#92400e; margin-top:8px;">En iyi seri: <strong><?php echo $login_streak_best; ?> gun</strong></div>
                <div style="display:flex; justify-content:center; gap:16px; margin-top:16px;">
                    <div style="text-align:center; padding:8px 16px; background:<?php echo $login_streak >= 7 ? '#22c55e' : '#d1d5db'; ?>; border-radius:10px; color:#fff; font-size:12px; font-weight:600;">
                        7 Gun <?php echo $login_streak >= 7 ? '✓' : ''; ?>
                    </div>
                    <div style="text-align:center; padding:8px 16px; background:<?php echo $login_streak >= 30 ? '#22c55e' : '#d1d5db'; ?>; border-radius:10px; color:#fff; font-size:12px; font-weight:600;">
                        30 Gun <?php echo $login_streak >= 30 ? '✓' : ''; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php
            // ═══ DOGUM GUNU GİRİŞİ ═══
            if (get_option('gorilla_lr_birthday_enabled', 'no') === 'yes'):
                $user_birthday = get_user_meta($user_id, '_gorilla_birthday', true);
            ?>
            <hr style="border:none; border-top:2px dashed #e5e7eb; margin:35px 0;">
            <h2 style="font-size:24px; font-weight:800; margin-bottom:20px;">🎂 Dogum Gunu</h2>
            <?php if (!empty($user_birthday)): ?>
            <div class="glr-card" style="text-align:center;">
                <div style="font-size:48px; line-height:1; margin-bottom:10px;">🎂</div>
                <div style="font-size:16px; color:#1f2937;">Dogum gununuz: <strong><?php echo esc_html(wp_date('d.m.Y', strtotime($user_birthday))); ?></strong></div>
            </div>
            <?php else: ?>
            <div class="glr-card">
                <p style="font-size:14px; color:#6b7280; margin-top:0;">Dogum gununuzu girerek ozel surprizlerden yararlanin!</p>
                <form method="post">
                    <?php wp_nonce_field('gorilla_save_birthday', '_gorilla_birthday_nonce'); ?>
                    <div class="glr-form-group">
                        <label>Dogum Tarihiniz</label>
                        <input type="date" name="gorilla_birthday" class="glr-input" required>
                    </div>
                    <button type="submit" class="glr-btn" style="max-width:200px;">💾 Kaydet</button>
                </form>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php
            // ═══ LEADERBOARD ═══
            if (get_option('gorilla_lr_leaderboard_enabled', 'no') === 'yes' && function_exists('gorilla_xp_get_leaderboard')):
                $leaderboard = gorilla_xp_get_leaderboard('monthly', 10);
            ?>
            <hr style="border:none; border-top:2px dashed #e5e7eb; margin:35px 0;">
            <h2 style="font-size:24px; font-weight:800; margin-bottom:20px;">🏆 Bu Ayin Liderleri</h2>
            <?php if (!empty($leaderboard) && is_array($leaderboard)): ?>
            <div class="glr-card" style="padding:0; overflow:hidden;">
                <?php
                $medals = array(1 => '🥇', 2 => '🥈', 3 => '🥉');
                $rank = 1;
                foreach ($leaderboard as $leader):
                    if (!is_array($leader)) continue;
                    $is_current = (intval($leader['user_id'] ?? 0) === $user_id);
                    $medal = isset($medals[$rank]) ? $medals[$rank] : $rank . '.';
                ?>
                <div class="glr-leaderboard-row <?php echo $is_current ? 'current-user' : ''; ?>" data-rank="<?php echo $rank; ?>">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:18px; min-width:30px;"><?php echo $medal; ?></span>
                        <span><?php echo esc_html($leader['display_name'] ?? 'Kullanici'); ?></span>
                        <?php if ($is_current): ?>
                            <span style="font-size:11px; color:#3b82f6;">(Siz)</span>
                        <?php endif; ?>
                    </div>
                    <span style="font-weight:700; color:#f59e0b;"><?php echo number_format_i18n(intval($leader['xp_earned'] ?? 0)); ?> XP</span>
                </div>
                <?php $rank++; endforeach; ?>
            </div>
            <?php else: ?>
            <div class="glr-card" style="text-align:center; color:#9ca3af;">
                <p>Bu ay henuz liderlik tablosu olusturulmadi.</p>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php
            // ═══ MİLESTONE İLERLEME ═══
            if (get_option('gorilla_lr_milestones_enabled', 'no') === 'yes' && function_exists('gorilla_xp_check_milestones')):
                $milestones = get_option('gorilla_lr_milestones', array());
                $completed_milestones = get_user_meta($user_id, '_gorilla_milestones', true);
                if (!is_array($completed_milestones)) $completed_milestones = array();
                $current_xp = function_exists('gorilla_xp_get_balance') ? gorilla_xp_get_balance($user_id) : 0;
            ?>
            <hr style="border:none; border-top:2px dashed #e5e7eb; margin:35px 0;">
            <h2 style="font-size:24px; font-weight:800; margin-bottom:20px;">🎯 Milestone Ilerleme</h2>
            <?php if (!empty($milestones) && is_array($milestones)): ?>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <?php foreach ($milestones as $ms):
                    if (!is_array($ms)) continue;
                    $mid = $ms['id'] ?? '';
                    $ms_target = intval($ms['target'] ?? 0);
                    $ms_completed = in_array($mid, $completed_milestones);
                    $ms_progress = 0;
                    if ($ms_completed) {
                        $ms_progress = 100;
                    } elseif ($ms_target > 0) {
                        $ms_type = $ms['type'] ?? 'total_xp';
                        if ($ms_type === 'total_xp') {
                            $ms_progress = min(100, round(($current_xp / $ms_target) * 100));
                        } elseif ($ms_type === 'total_orders' && function_exists('wc_get_orders')) {
                            $oc_result = wc_get_orders(array('customer_id' => $user_id, 'status' => array('completed','processing'), 'limit' => $ms_target + 1, 'return' => 'ids'));
                            $oc = is_array($oc_result) ? count($oc_result) : 0;
                            $ms_progress = min(100, round(($oc / $ms_target) * 100));
                        } elseif ($ms_type === 'total_spending' && function_exists('gorilla_loyalty_get_spending')) {
                            $sp = gorilla_loyalty_get_spending($user_id);
                            $ms_progress = min(100, round(($sp / $ms_target) * 100));
                        } elseif ($ms_type === 'referral_count') {
                            $rc = count(get_posts(array('post_type' => 'gorilla_referral', 'post_status' => 'grla_approved', 'meta_key' => '_ref_user_id', 'meta_value' => $user_id, 'numberposts' => $ms_target + 1, 'fields' => 'ids')));
                            $ms_progress = min(100, round(($rc / $ms_target) * 100));
                        }
                    }
                ?>
                <div class="glr-card" style="padding:16px 20px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <span style="font-weight:700; font-size:14px;"><?php echo esc_html($ms['label'] ?? ''); ?></span>
                        <?php if ($ms_completed): ?>
                            <span style="background:#dcfce7; color:#22c55e; padding:2px 10px; border-radius:10px; font-size:11px; font-weight:600;">✓ Tamamlandi</span>
                        <?php else: ?>
                            <span style="color:#6b7280; font-size:13px;">%<?php echo $ms_progress; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="glr-progress-track" style="margin:0; height:12px;">
                        <div class="glr-progress-bar" style="width:<?php echo $ms_progress; ?>%; background:<?php echo $ms_completed ? '#22c55e' : 'linear-gradient(90deg, #f59e0b, #f97316)'; ?>; font-size:0;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="glr-card" style="text-align:center; color:#9ca3af;">
                <p>Henuz tanimlanmis milestone bulunmuyor.</p>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php
            // ═══ PUAN DUKKANI ═══
            if (get_option('gorilla_lr_points_shop_enabled', 'no') === 'yes' && function_exists('gorilla_shop_get_rewards')):
                $shop_rewards = gorilla_shop_get_rewards();
                $shop_xp = function_exists('gorilla_xp_get_balance') ? gorilla_xp_get_balance($user_id) : 0;
            ?>
            <hr style="border:none; border-top:2px dashed #e5e7eb; margin:35px 0;">
            <h2 style="font-size:24px; font-weight:800; margin-bottom:20px;">🛍️ Puan Dukkani</h2>
            <div class="glr-card" style="text-align:center; margin-bottom:16px;">
                <div style="font-size:14px; color:#6b7280;">Mevcut XP Bakiyeniz</div>
                <div style="font-size:28px; font-weight:800; color:#8b5cf6;" id="gorilla-shop-xp"><?php echo number_format_i18n($shop_xp); ?></div>
            </div>
            <?php if (!empty($shop_rewards) && is_array($shop_rewards)): ?>
            <div class="glr-shop-grid">
                <?php foreach ($shop_rewards as $reward):
                    if (!is_array($reward)) continue;
                    $reward_cost = intval($reward['xp_cost'] ?? 0);
                ?>
                <div class="glr-shop-item">
                    <div style="font-size:32px; margin-bottom:8px;"><?php echo esc_html($reward['emoji'] ?? '🎁'); ?></div>
                    <div style="font-weight:700; font-size:14px; color:#1f2937;"><?php echo esc_html($reward['label'] ?? ''); ?></div>
                    <div style="font-size:12px; color:#6b7280; margin:4px 0;"><?php echo esc_html($reward['description'] ?? ''); ?></div>
                    <div class="glr-shop-price" style="font-size:16px; font-weight:800; color:#8b5cf6; margin:6px 0;"><?php echo number_format_i18n($reward_cost); ?> XP</div>
                    <button type="button" class="glr-shop-btn" data-reward-id="<?php echo esc_attr($reward['id'] ?? ''); ?>" <?php echo $shop_xp < $reward_cost ? 'disabled' : ''; ?>>
                        Satin Al
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="glr-card" style="text-align:center; color:#9ca3af;">
                <p>Dukkanda henuz odul bulunmuyor.</p>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php
            // ═══ SANS CARKI ═══
            if (get_option('gorilla_lr_spin_enabled', 'no') === 'yes' && function_exists('gorilla_spin_get_prizes')):
                $spin_prizes = gorilla_spin_get_prizes();
                $spin_available = intval(get_user_meta($user_id, '_gorilla_spin_available', true));
                $spin_history = get_user_meta($user_id, '_gorilla_spin_history', true);
                if (!is_array($spin_history)) $spin_history = array();
                $spin_history = array_slice($spin_history, 0, 5);
            ?>
            <hr style="border:none; border-top:2px dashed #e5e7eb; margin:35px 0;">
            <h2 style="font-size:24px; font-weight:800; margin-bottom:20px;">🎰 Sans Carki</h2>
            <div id="gorilla-spin-container">
                <div class="glr-wheel-frame">
                    <div class="glr-wheel-pointer"></div>
                    <canvas id="gorilla-spin-canvas" width="300" height="300" data-prizes='<?php echo esc_attr(wp_json_encode($spin_prizes)); ?>'></canvas>
                    <div class="glr-wheel-center">SPIN</div>
                </div>
                <div style="margin-top:16px;">
                    <button type="button" id="gorilla-spin-btn" <?php echo $spin_available <= 0 ? 'disabled' : ''; ?>>Cevir!</button>
                </div>
                <div style="margin-top:12px; font-size:14px; color:#6b7280;">
                    Kalan hak: <strong id="gorilla-spin-remaining"><?php echo $spin_available; ?></strong>
                </div>
            </div>
            <?php if (!empty($spin_history)): ?>
            <h4 style="margin-top:20px; font-size:14px; color:#6b7280;">Son Cevirme Gecmisi</h4>
            <div class="glr-card" style="padding:0; overflow:hidden;">
                <div style="font-size:13px;">
                    <?php foreach ($spin_history as $spin_entry):
                        if (!is_array($spin_entry)) continue;
                        $spin_date = isset($spin_entry['date']) ? esc_html(wp_date('d.m.Y H:i', strtotime($spin_entry['date']))) : '';
                    ?>
                    <div style="display:flex; justify-content:space-between; padding:10px 16px; border-bottom:1px solid #f0f0f0;">
                        <span style="color:#6b7280;"><?php echo $spin_date; ?></span>
                        <span style="font-weight:600; color:#8b5cf6;"><?php echo esc_html($spin_entry['prize'] ?? ''); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

        </div>
        <?php
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla LR loyalty page error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        }
        echo '<p style="color:#ef4444;">Sadakat sayfası yüklenirken bir hata oluştu. Lütfen yöneticiye bildirin.</p>';
    }
});


// ══════════════════════════════════════════════════════════
// REFERANS PROGRAMI SAYFASI
// ══════════════════════════════════════════════════════════
add_action('woocommerce_account_gorilla-referral_endpoint', function() {
    try {
        if (!function_exists('gorilla_credit_get_balance') || !function_exists('gorilla_referral_process_submission')) {
            echo '<p>Referans programı şu anda kullanılamıyor.</p>';
            return;
        }
        
        $user_id = get_current_user_id();
        $credit = gorilla_credit_get_balance($user_id);
        $rate = intval(get_option('gorilla_lr_referral_rate', 35));
        
        // Form gönderimi
        $result = gorilla_referral_process_submission();
        
        // Mevcut başvurular
        $submissions = function_exists('gorilla_referral_get_user_submissions') ? gorilla_referral_get_user_submissions($user_id) : array();
        $submitted_order_ids = is_array($submissions) ? array_column($submissions, 'order_id') : array();
        
        // Uygun siparişler
        $eligible = array();
        if (function_exists('wc_get_orders')) {
            $orders = wc_get_orders(array(
                'customer_id' => $user_id,
                'status'      => array('completed', 'processing'),
                'limit'       => 50,
                'orderby'     => 'date',
                'order'       => 'DESC',
            ));
            $eligible = array_filter($orders, function($o) use ($submitted_order_ids) {
                return !in_array($o->get_id(), $submitted_order_ids);
            });
        }
        
        gorilla_frontend_styles();
        ?>
        <div class="glr-wrap">
            <h2 style="font-size:24px; font-weight:800; margin-bottom:20px;">🔗 Referans Programı</h2>
            
            <?php if ($result && isset($result['success']) && $result['success']): ?>
                <div class="woocommerce-message" style="border-top-color:#22c55e;">
                    ✅ Başvurunuz alındı! İncelendikten sonra <strong><?php echo wc_price($result['credit_amount'] ?? 0); ?></strong> store credit hesabınıza eklenecektir.
                </div>
            <?php elseif ($result && isset($result['success']) && !$result['success']): ?>
                <?php foreach (($result['errors'] ?? array()) as $err): ?>
                    <div class="woocommerce-error"><?php echo esc_html($err); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Store Credit Bakiye -->
            <div class="glr-hero" style="background:linear-gradient(135deg, #dcfce7, #bbf7d0); border:2px solid #22c55e;">
                <div style="color:#166534; font-size:14px;">Store Credit Bakiyeniz</div>
                <div style="font-size:42px; font-weight:800; color:#15803d;"><?php echo wc_price($credit); ?></div>
                <div style="color:#4ade80; font-size:13px;">Bir sonraki alışverişinizde checkout'ta kullanabilirsiniz</div>
            </div>

            <?php
            // ═══ AFFILIATE LINK BÖLÜMÜ ═══
            if (get_option('gorilla_lr_enabled_affiliate', 'yes') === 'yes' && function_exists('gorilla_affiliate_get_user_stats')):
                $affiliate_stats = gorilla_affiliate_get_user_stats($user_id);
                $affiliate_rate = intval(get_option('gorilla_lr_affiliate_rate', 10));
                $recent_earnings = function_exists('gorilla_affiliate_get_recent_earnings') ? gorilla_affiliate_get_recent_earnings($user_id, 5) : array();
            ?>
            <!-- Affiliate Link Kartı -->
            <div class="glr-card" style="background:linear-gradient(135deg, #eff6ff, #dbeafe); border:2px solid #3b82f6;">
                <h3 style="margin-top:0; font-size:16px; color:#1e40af;">🔗 Affiliate Linkiniz</h3>
                <p style="font-size:13px; color:#3b82f6; margin-bottom:12px;">Bu linki paylaşın, arkadaşlarınız alışveriş yaptığında <strong>%<?php echo $affiliate_rate; ?> komisyon</strong> kazanın!</p>

                <div style="display:flex; gap:10px; margin-bottom:16px;">
                    <input type="text" id="gorilla-affiliate-link" value="<?php echo esc_attr($affiliate_stats['link']); ?>" readonly
                           style="flex:1; padding:12px 14px; border:1px solid #93c5fd; border-radius:8px; font-size:14px; background:#fff; color:#1e3a8a;">
                    <button type="button" id="gorilla-copy-affiliate"
                            style="background:#3b82f6; color:#fff; border:none; padding:12px 20px; border-radius:8px; font-weight:600; cursor:pointer; white-space:nowrap;">
                        📋 Kopyala
                    </button>
                </div>

                <div style="font-size:12px; color:#6b7280;">
                    Referans Kodunuz: <code style="background:#e0e7ff; padding:2px 8px; border-radius:4px; font-weight:600; color:#4338ca;"><?php echo esc_html($affiliate_stats['code']); ?></code>
                </div>
            </div>

            <!-- Affiliate İstatistikleri -->
            <div class="glr-card">
                <h3 style="margin-top:0; font-size:16px;">📊 Affiliate İstatistikleriniz</h3>
                <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; text-align:center;">
                    <div style="background:#f0fdf4; padding:16px; border-radius:10px;">
                        <div style="font-size:28px; font-weight:800; color:#22c55e;"><?php echo number_format_i18n($affiliate_stats['clicks']); ?></div>
                        <div style="font-size:12px; color:#6b7280;">Tıklama</div>
                    </div>
                    <div style="background:#fef3c7; padding:16px; border-radius:10px;">
                        <div style="font-size:28px; font-weight:800; color:#f59e0b;"><?php echo number_format_i18n($affiliate_stats['conversions']); ?></div>
                        <div style="font-size:12px; color:#6b7280;">Satış</div>
                    </div>
                    <div style="background:#eff6ff; padding:16px; border-radius:10px;">
                        <div style="font-size:28px; font-weight:800; color:#3b82f6;"><?php echo wc_price($affiliate_stats['earnings']); ?></div>
                        <div style="font-size:12px; color:#6b7280;">Toplam Kazanç</div>
                    </div>
                </div>

                <?php if (!empty($recent_earnings)): ?>
                <h4 style="margin:20px 0 10px; font-size:14px; color:#6b7280;">Son Kazançlar</h4>
                <div style="font-size:13px;">
                    <?php foreach ($recent_earnings as $earn):
                        $earn_date = isset($earn['created_at']) ? wp_date('d.m.Y', strtotime($earn['created_at'])) : '';
                    ?>
                    <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f0f0f0;">
                        <span style="color:#6b7280;"><?php echo esc_html($earn_date); ?> — <?php echo esc_html($earn['reason'] ?? ''); ?></span>
                        <span style="color:#22c55e; font-weight:600;">+<?php echo wc_price(floatval($earn['amount'] ?? 0)); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php
            // ═══ QR KOD ═══
            if (get_option('gorilla_lr_qr_enabled', 'no') === 'yes' && function_exists('gorilla_qr_get_url')):
                $qr_url = gorilla_qr_get_url($user_id);
                $qr_download_nonce = wp_create_nonce('gorilla_qr_download');
            ?>
            <div class="glr-card" style="text-align:center; margin-top:16px;">
                <h3 style="margin-top:0; font-size:16px;">📱 QR Kodunuz</h3>
                <p style="font-size:13px; color:#6b7280;">Bu QR kodu paylaşarak yeni musteriler kazandirin.</p>
                <?php if (!empty($qr_url)): ?>
                <div style="margin:16px 0;">
                    <img src="<?php echo esc_url($qr_url); ?>" alt="QR Kod" style="max-width:200px; border:2px solid #e5e7eb; border-radius:12px; padding:8px; background:#fff;">
                </div>
                <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=gorilla_download_qr&nonce=' . $qr_download_nonce)); ?>"
                   class="glr-btn" style="display:inline-block; width:auto; padding:10px 24px; font-size:14px;">
                    📥 QR Kodu Indir
                </a>
                <?php else: ?>
                <p style="color:#9ca3af;">QR kod olusturulamadi.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php
            // ═══ AFFİLİATE KADEME GÖSTERİMİ ═══
            if (function_exists('gorilla_affiliate_get_current_tier') && get_option('gorilla_lr_tiered_affiliate_enabled', 'no') === 'yes'):
                $aff_tier = gorilla_affiliate_get_current_tier($user_id);
            ?>
            <?php if (!empty($aff_tier) && is_array($aff_tier)): ?>
            <div class="glr-card" style="margin-top:16px;">
                <h3 style="margin-top:0; font-size:16px;">📈 Affiliate Kademeniz</h3>
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
                    <div>
                        <div style="font-size:14px; color:#6b7280;">Mevcut Komisyon Orani</div>
                        <div style="font-size:28px; font-weight:800; color:#3b82f6;">%<?php echo intval($aff_tier['rate'] ?? 0); ?></div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:14px; color:#6b7280;">Toplam Satis</div>
                        <div style="font-size:28px; font-weight:800; color:#22c55e;"><?php echo number_format_i18n(intval($aff_tier['total_sales'] ?? 0)); ?></div>
                    </div>
                </div>
                <?php if (!empty($aff_tier['next_rate'])): ?>
                <div style="background:#f9fafb; padding:12px 16px; border-radius:10px; margin-top:8px;">
                    <div style="font-size:13px; color:#6b7280; margin-bottom:6px;">
                        Sonraki Kademe: <strong>%<?php echo intval($aff_tier['next_rate']); ?> komisyon</strong>
                    </div>
                    <?php
                    $aff_progress = 0;
                    $aff_current_sales = intval($aff_tier['total_sales'] ?? 0);
                    $aff_next_sales = $aff_current_sales + intval($aff_tier['sales_to_next'] ?? 0);
                    if ($aff_next_sales > 0) {
                        $aff_progress = min(100, round(($aff_current_sales / $aff_next_sales) * 100));
                    }
                    ?>
                    <div class="glr-progress-track" style="margin:0; height:14px;">
                        <div class="glr-progress-bar" style="width:<?php echo $aff_progress; ?>%; background:linear-gradient(90deg, #3b82f6, #8b5cf6);">
                            <?php echo $aff_progress; ?>%
                        </div>
                    </div>
                    <div style="font-size:12px; color:#9ca3af; margin-top:4px;">
                        <?php echo $aff_current_sales; ?> / <?php echo $aff_next_sales; ?> satis
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            <?php endif; ?>

            <hr style="border:none; border-top:2px dashed #e5e7eb; margin:30px 0;">
            <h3 style="font-size:18px; margin-bottom:16px;">🎬 Video İçerik Programı</h3>

            <!-- Nasıl Çalışır -->
            <div class="glr-card" style="background:#fffbeb; border:1px solid #fbbf24;">
                <h3 style="margin-top:0; font-size:16px;">📖 Nasıl Çalışır?</h3>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px; margin-top:12px;">
                    <?php
                    $steps = array(
                        array('📦', 'Sipariş Ver', 'Gorilla\'dan sipariş verin ve ürünlerinizi teslim alın.'),
                        array('🎬', 'Video Çek', 'Ürünlerinizi tanıtan veya açtığınız bir video hazırlayın.'),
                        array('📱', 'Paylaş', 'Videoyu istediğiniz sosyal medya platformuna yükleyin.'),
                        array('🔗', 'Başvur', 'Video linkini aşağıdaki formdan bize gönderin.'),
                        array('💰', 'Kazan!', 'Onaydan sonra sipariş tutarının %' . $rate . '\'i credit olarak hesabınıza eklenir!'),
                    );
                    foreach ($steps as $i => $s):
                    ?>
                    <div style="text-align:center; padding:12px;">
                        <div style="font-size:32px;"><?php echo $s[0]; ?></div>
                        <div style="font-weight:700; margin:4px 0;"><?php echo $s[1]; ?></div>
                        <div style="font-size:12px; color:#92400e;"><?php echo $s[2]; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Başvuru Formu -->
            <?php if (!empty($eligible) && get_option('gorilla_lr_enabled_referral') === 'yes'): ?>
            <div class="glr-card">
                <h3 style="margin-top:0; font-size:16px;">🎬 Yeni Başvuru</h3>
                <form method="post">
                    <?php wp_nonce_field('gorilla_referral_submit', '_gorilla_ref_nonce'); ?>
                    
                    <div class="glr-form-group">
                        <label>Sipariş Seçin *</label>
                        <select name="referral_order_id" required class="glr-input">
                            <option value="">— Sipariş seçin —</option>
                            <?php foreach ($eligible as $o): 
                                $earn = round(floatval($o->get_total()) * ($rate / 100), 2);
                            ?>
                            <option value="<?php echo intval($o->get_id()); ?>">
                                #<?php echo intval($o->get_id()); ?> — <?php echo wc_price($o->get_total()); ?> — Kazanç: <?php echo wc_price($earn); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="glr-form-group">
                        <label>Platform *</label>
                        <select name="referral_platform" required class="glr-input">
                            <option value="">— Platform seçin —</option>
                            <option value="YouTube">🎬 YouTube</option>
                            <option value="Instagram">📸 Instagram (Reels/Post/Story)</option>
                            <option value="TikTok">🎵 TikTok</option>
                            <option value="Twitter/X">🐦 Twitter / X</option>
                            <option value="Facebook">📘 Facebook</option>
                            <option value="Twitch">🎮 Twitch</option>
                            <option value="Diğer">📱 Diğer</option>
                        </select>
                    </div>
                    
                    <div class="glr-form-group">
                        <label>Video Linki *</label>
                        <input type="url" name="referral_video_url" class="glr-input" placeholder="https://www.youtube.com/watch?v=..." required>
                    </div>
                    
                    <div class="glr-form-group">
                        <label>Not <span style="color:#aaa;">(isteğe bağlı)</span></label>
                        <textarea name="referral_note" class="glr-input" rows="3" placeholder="Eklemek istediğiniz bir not varsa..."></textarea>
                    </div>
                    
                    <button type="submit" name="gorilla_submit_referral" value="1" class="glr-btn">🚀 Başvuru Gönder</button>
                </form>
            </div>
            <?php elseif (empty($eligible)): ?>
            <div class="glr-card" style="text-align:center; color:#9ca3af;">
                <div style="font-size:40px;">📦</div>
                <p>Şu anda başvuru yapabileceğiniz sipariş bulunmuyor.</p>
                <p style="font-size:13px;">Yeni sipariş verdikten ve ürünlerinizi aldıktan sonra buradan başvuru yapabilirsiniz.</p>
            </div>
            <?php endif; ?>
            
            <!-- Başvuru Geçmişi -->
            <?php if (!empty($submissions)): ?>
            <h3 style="margin-top:30px; font-size:16px;">📋 Başvuru Geçmişi</h3>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <?php foreach ($submissions as $sub):
                    if (!is_array($sub)) continue;
                    $status_info = array(
                        'pending'       => array('⏳ İnceleniyor', '#f59e0b', '#fef3c7'),
                        'grla_approved' => array('✅ Onaylandı', '#22c55e', '#dcfce7'),
                        'grla_rejected' => array('❌ Reddedildi', '#ef4444', '#fee2e2'),
                    );
                    $si = $status_info[$sub['status'] ?? ''] ?? array(($sub['status'] ?? 'Bilinmiyor'), '#888', '#f0f0f0');
                ?>
                <div class="glr-card" style="padding:14px 18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div>
                        <strong>Sipariş #<?php echo esc_html($sub['order_id'] ?? ''); ?></strong>
                        <span style="color:#9ca3af; font-size:13px;"> — <?php echo esc_html($sub['platform'] ?? ''); ?> — <?php echo esc_html($sub['date'] ?? ''); ?></span>
                        <div style="font-size:13px; color:#6b7280;">Kazanç: <strong><?php echo wc_price($sub['credit'] ?? 0); ?></strong></div>
                    </div>
                    <span style="background:<?php echo esc_attr($si[2]); ?>; color:<?php echo esc_attr($si[1]); ?>; padding:4px 14px; border-radius:20px; font-size:12px; font-weight:600; white-space:nowrap;"><?php echo esc_html($si[0]); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Credit Geçmişi -->
            <?php
            $log = function_exists('gorilla_credit_get_log') ? gorilla_credit_get_log($user_id, 20) : array();
            if (!empty($log) && is_array($log)):
            ?>
            <h3 style="margin-top:30px; font-size:16px;">💳 Credit Geçmişi</h3>
            <div class="glr-card" style="padding:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr style="background:#f9fafb;">
                            <th style="padding:10px 14px; text-align:left;">Tarih</th>
                            <th style="padding:10px 14px; text-align:left;">Açıklama</th>
                            <th style="padding:10px 14px; text-align:right;">Tutar</th>
                            <th style="padding:10px 14px; text-align:right;">Bakiye</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($log as $entry):
                        if (!is_array($entry)) continue;
                        $amt = floatval($entry['amount'] ?? 0);
                        $entry_color = $amt >= 0 ? '#22c55e' : '#ef4444';
                        $sign = $amt >= 0 ? '+' : '';
                        $date = $entry['created_at'] ?? ($entry['date'] ?? '');
                    ?>
                        <tr style="border-top:1px solid #f0f0f0;">
                            <td style="padding:10px 14px;"><?php echo $date ? esc_html(wp_date('d.m.Y H:i', strtotime($date))) : '-'; ?></td>
                            <td style="padding:10px 14px;"><?php echo esc_html($entry['reason'] ?? ''); ?></td>
                            <td style="padding:10px 14px; text-align:right; font-weight:600; color:<?php echo $entry_color; ?>;"><?php echo $sign . wc_price(abs($amt)); ?></td>
                            <td style="padding:10px 14px; text-align:right;"><?php echo wc_price($entry['balance_after'] ?? ($entry['balance'] ?? 0)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla LR referral page error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        }
        echo '<p style="color:#ef4444;">Referans sayfası yüklenirken bir hata oluştu. Lütfen yöneticiye bildirin.</p>';
    }
});


// ══════════════════════════════════════════════════════════
// ÜRÜN SAYFASI & SEPET BİLGİLENDİRMELERİ
// ══════════════════════════════════════════════════════════

// Ürün sayfasında indirim bilgisi
add_action('woocommerce_single_product_summary', function() {
    try {
        if (get_option('gorilla_lr_enabled_loyalty') !== 'yes') return;
        
        if (!is_user_logged_in()) {
            if (!function_exists('wc_get_page_permalink')) return;
            echo '<div class="glr-product-notice" style="background:#eff6ff; border:1px solid #bfdbfe; padding:10px 14px; border-radius:8px; margin:12px 0; font-size:13px; color:#1e40af;">';
            echo '🎖️ <a href="' . esc_url(wc_get_page_permalink('myaccount')) . '">Giriş yapın</a> ve sadakat indiriminizden faydalanın!';
            echo '</div>';
            return;
        }
        
        if (!function_exists('gorilla_loyalty_calculate_tier')) return;
        
        $tier = gorilla_loyalty_calculate_tier(get_current_user_id());
        if (!is_array($tier) || ($tier['discount'] ?? 0) <= 0) return;
        
        global $product;
        if (!$product || !is_object($product) || !method_exists($product, 'get_price')) return;
        
        $price = floatval($product->get_price());
        if ($price <= 0) return;
        
        $discounted = round($price * (1 - $tier['discount'] / 100), 2);
        $color = esc_attr($tier['color'] ?? '#999');
        
        echo '<div style="background:' . $color . '12; border:1px solid ' . $color . '44; padding:12px 16px; border-radius:8px; margin:12px 0; font-size:13px;">';
        echo esc_html($tier['emoji'] ?? '') . ' <strong>' . esc_html($tier['label'] ?? '') . ' Üye</strong> olarak bu ürünü ';
        echo '<strong style="color:' . $color . ';">' . wc_price($discounted) . '</strong> ödeyeceksiniz ';
        echo '<span style="color:#9ca3af; text-decoration:line-through;">' . wc_price($price) . '</span>';
        echo ' <span style="color:#22c55e; font-weight:600;">(-%' . intval($tier['discount']) . ')</span>';
        echo '</div>';
    } catch (\Throwable $e) {
        // Sessizce geç - ürün sayfası bozulmasın
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla LR product page error: ' . $e->getMessage());
        }
    }
}, 25);

// Sepet sayfası bildirimi (giriş yapmış)
add_action('woocommerce_before_cart', function() {
    try {
        if (!is_user_logged_in() || get_option('gorilla_lr_enabled_loyalty') !== 'yes') return;
        if (!function_exists('gorilla_loyalty_calculate_tier')) return;
        
        $tier = gorilla_loyalty_calculate_tier(get_current_user_id());
        if (!is_array($tier) || ($tier['discount'] ?? 0) <= 0) return;
        $color = esc_attr($tier['color'] ?? '#999');
        
        echo '<div style="background:' . $color . '12; border:2px solid ' . $color . '; padding:16px 22px; border-radius:12px; margin-bottom:20px; text-align:center;">';
        echo esc_html($tier['emoji'] ?? '') . ' <strong>' . esc_html($tier['label'] ?? '') . ' Üye İndirimi</strong> — ';
        echo 'Sepetinize otomatik <strong>%' . intval($tier['discount']) . ' indirim</strong> uygulanıyor!';
        if (($tier['installment'] ?? 0) > 0) {
            echo '<br><span style="font-size:13px;">✨ Vade farksız ' . intval($tier['installment']) . ' taksit hakkınız var!</span>';
        }
        echo '</div>';
    } catch (\Throwable $e) {
        error_log('Gorilla LR cart notice error: ' . $e->getMessage());
    }
});

// Giriş yapmamış kullanıcılar için sepet bildirimi
add_action('woocommerce_before_cart', function() {
    try {
        if (is_user_logged_in()) return;
        if (!function_exists('wc_get_page_permalink')) return;
        
        echo '<div style="background:#eff6ff; border:1px solid #bfdbfe; padding:12px 18px; border-radius:10px; margin-bottom:16px; text-align:center; font-size:14px; color:#1e40af;">';
        echo '🎖️ <a href="' . esc_url(wc_get_page_permalink('myaccount')) . '" style="font-weight:700;">Giriş yapın</a> ve sadakat indiriminizden faydalanın!';
        echo '</div>';
    } catch (\Throwable $e) {
        // Sessizce geç
    }
}, 5);

// Teşekkür sayfası: Sonraki seviye hatırlatması
add_action('woocommerce_thankyou', function($order_id) {
    try {
        if (!is_user_logged_in()) return;
        if (!function_exists('gorilla_loyalty_calculate_tier')) return;
        if (!function_exists('wc_get_order')) return;
        
        $order = wc_get_order($order_id);
        if (!$order || !is_object($order)) return;
        
        $user_id = $order->get_customer_id();
        if (!$user_id) return;
        
        // Cache temizle ki yeni seviye hesaplansın
        delete_transient('gorilla_spending_' . $user_id);
        
        $tier = gorilla_loyalty_calculate_tier($user_id);
        $next = function_exists('gorilla_loyalty_next_tier') ? gorilla_loyalty_next_tier($user_id) : null;
        
        echo '<div style="background:#f0fdf4; border:2px solid #22c55e; padding:20px; border-radius:12px; margin:20px 0; text-align:center;">';
        echo '<div style="font-size:14px; color:#166534;">🎖️ Mevcut Sadakat Seviyeniz</div>';
        echo '<div style="font-size:24px; font-weight:800; margin:6px 0;">' . esc_html($tier['emoji'] ?? '👤') . ' ' . esc_html($tier['label'] ?? 'Üye') . '</div>';
        if ($next && is_array($next)) {
            echo '<div style="font-size:13px; color:#4ade80;">🎯 ' . esc_html($next['emoji'] ?? '') . ' ' . esc_html($next['label'] ?? '') . ' seviyesine ' . wc_price($next['remaining'] ?? 0) . ' kaldı!</div>';
        }
        
        // Referans hatırlatması
        if (get_option('gorilla_lr_enabled_referral') === 'yes' && function_exists('wc_get_account_endpoint_url')) {
            $rate = intval(get_option('gorilla_lr_referral_rate', 35));
            echo '<hr style="border:none; border-top:1px solid #bbf7d0; margin:15px 0;">';
            echo '<div style="font-size:13px; color:#166534;">';
            echo '🎬 Bu siparişi sosyal medyada paylaşın, <strong>%' . $rate . ' store credit</strong> kazanın! ';
            echo '<a href="' . esc_url(wc_get_account_endpoint_url('gorilla-referral')) . '" style="font-weight:700;">Başvuru Yap →</a>';
            echo '</div>';
        }

        // Sosyal Paylasim Butonlari
        if (get_option('gorilla_lr_social_share_enabled', 'no') === 'yes') {
            $share_url = esc_url(get_permalink());
            echo '<hr style="border:none; border-top:1px solid #bbf7d0; margin:15px 0;">';
            echo '<div style="font-size:14px; color:#166534; font-weight:700; margin-bottom:8px;">Sosyal Medyada Paylasin</div>';
            echo '<div class="glr-share-buttons">';
            echo '<button type="button" class="glr-share-btn facebook" data-platform="facebook" data-url="' . $share_url . '">📘 Facebook</button>';
            echo '<button type="button" class="glr-share-btn twitter" data-platform="twitter" data-url="' . $share_url . '">🐦 Twitter</button>';
            echo '<button type="button" class="glr-share-btn whatsapp" data-platform="whatsapp" data-url="' . $share_url . '">💬 WhatsApp</button>';
            echo '</div>';
        }

        echo '</div>';
    } catch (\Throwable $e) {
        error_log('Gorilla LR thankyou error: ' . $e->getMessage());
    }
});


// ══════════════════════════════════════════════════════════
// CSS - Artık assets/css/frontend.css olarak yükleniyor
// ══════════════════════════════════════════════════════════
function gorilla_frontend_styles() {
    // CSS artık gorilla-loyalty-referral.php'de wp_enqueue_style ile yükleniyor
    // Bu fonksiyon geriye uyumluluk için korunuyor
    return;
}
