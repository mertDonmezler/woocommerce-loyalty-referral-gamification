<?php
/**
 * Gorilla Loyalty & Gamification - Frontend (Musteri Tarafi)
 * Hesabim sayfalari, gamification UI
 *
 * @package Gorilla_Loyalty_Gamification
 * @since   3.1.0
 */

if (!defined('ABSPATH')) exit;

// ── Notification Helpers ─────────────────────────────────
function gorilla_notification_add($user_id, $type, $message, $icon = '') {
    global $wpdb;
    $lock_name = "gorilla_notify_{$user_id}";
    $got_lock = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $lock_name));
    if (!$got_lock) return;
    try {
    $notifications = get_user_meta($user_id, '_gorilla_notifications', true);
    if (!is_array($notifications)) $notifications = array();

    array_unshift($notifications, array(
        'id'      => uniqid('gn_'),
        'type'    => sanitize_key($type),
        'message' => sanitize_text_field($message),
        'icon'    => $icon ?: gorilla_notification_icon($type),
        'time'    => current_time('mysql'),
        'read'    => false,
    ));

    // Keep max 50 notifications
    $notifications = array_slice($notifications, 0, 50);
    update_user_meta($user_id, '_gorilla_notifications', $notifications);
    } finally {
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
    }
}

function gorilla_notification_icon($type) {
    $icons = array(
        'xp_earned'     => '⚡',
        'badge_earned'  => '🏅',
        'tier_upgrade'  => '🎖️',
        'tier_downgrade'=> '⬇️',
        'credit_earned' => '💰',
        'credit_used'   => '🛒',
        'spin_win'      => '🎰',
        'challenge'     => '🎯',
        'milestone'     => '🏆',
        'anniversary'   => '🎉',
        'birthday'      => '🎂',
    );
    return $icons[$type] ?? '🔔';
}

function gorilla_notification_get_unread_count($user_id) {
    $notifications = get_user_meta($user_id, '_gorilla_notifications', true);
    if (!is_array($notifications)) return 0;
    $count = 0;
    foreach ($notifications as $n) {
        if (empty($n['read'])) $count++;
    }
    return $count;
}

// AJAX: Mark notifications as read
add_action('wp_ajax_gorilla_notifications_read', function() {
    check_ajax_referer('gorilla_notifications', 'nonce');
    $user_id = get_current_user_id();
    if (!$user_id) wp_send_json_error();

    $notifications = get_user_meta($user_id, '_gorilla_notifications', true);
    if (!is_array($notifications)) { wp_send_json_success(); return; }

    foreach ($notifications as &$n) {
        $n['read'] = true;
    }
    unset($n);
    update_user_meta($user_id, '_gorilla_notifications', $notifications);
    wp_send_json_success();
});

// Hook into existing events to auto-add notifications
add_action('gamify_after_xp_awarded', function($user_id, $amount, $source, $source_id = '') {
    if ($amount >= 10) { // Only notify for significant XP gains
        $label = class_exists('WPGamify_XP_Engine') ? WPGamify_XP_Engine::get_source_label($source) : $source;
        gorilla_notification_add($user_id, 'xp_earned', sprintf('+%d XP: %s', $amount, $label));
    }
}, 10, 4);

add_action('gorilla_credit_adjusted', function($user_id, $amount, $description) {
    if ($amount > 0) {
        gorilla_notification_add($user_id, 'credit_earned', sprintf('+%s credit: %s', wc_price($amount), $description));
    }
}, 10, 3);

add_action('gorilla_badge_earned', function($user_id, $badge_key, $tier_key = '') {
    $defs = function_exists('gorilla_badge_get_definitions') ? gorilla_badge_get_definitions() : array();
    $label = isset($defs[$badge_key]) ? $defs[$badge_key]['label'] : $badge_key;
    gorilla_notification_add($user_id, 'badge_earned', sprintf('Yeni rozet: %s', $label));
}, 10, 3);


// ── Credit Transfer AJAX ─────────────────────────────────
// REMOVED: Duplicate non-atomic credit transfer handler (C1/C6).
// The authoritative handler is gorilla_transfer_credit_handler() in class-loyalty.php
// which uses GET_LOCK for atomicity and supports both credit and XP transfers.

// ── My Account Endpoint'leri ─────────────────────────────
// FIX: EP_ROOT kaldırıldı, sadece EP_PAGES kullan
add_action('init', function() {
    add_rewrite_endpoint('gorilla-loyalty', EP_PAGES);
});

// FIX: WooCommerce query vars filter eklendi - BU KRİTİK!
// Bu olmadan WooCommerce endpoint'leri tanımıyor
add_filter('woocommerce_get_query_vars', function($vars) {
    $vars['gorilla-loyalty'] = 'gorilla-loyalty';
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
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
                <h2 style="font-size:24px; font-weight:800; margin:0;">🎖️ Sadakat Programı</h2>
                <?php
                $notif_count = gorilla_notification_get_unread_count($user_id);
                $notifications = get_user_meta($user_id, '_gorilla_notifications', true);
                if (!is_array($notifications)) $notifications = array();
                $recent = array_slice($notifications, 0, 15);
                ?>
                <div style="position:relative;">
                    <button type="button" id="glr-notif-bell" style="background:none; border:1px solid #e5e7eb; border-radius:50%; width:44px; height:44px; font-size:22px; cursor:pointer; position:relative;">
                        🔔
                        <?php if ($notif_count > 0): ?>
                        <span style="position:absolute; top:-2px; right:-2px; background:#ef4444; color:#fff; font-size:11px; font-weight:700; min-width:18px; height:18px; line-height:18px; border-radius:9px; text-align:center;"><?php echo $notif_count > 9 ? '9+' : $notif_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="glr-notif-panel" style="display:none; position:absolute; right:0; top:50px; width:320px; max-height:400px; overflow-y:auto; background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 8px 30px rgba(0,0,0,0.12); z-index:999;">
                        <div style="padding:14px 16px; border-bottom:1px solid #f0f0f0; font-weight:700; display:flex; justify-content:space-between;">
                            <span>Bildirimler</span>
                            <?php if ($notif_count > 0): ?>
                            <button type="button" id="glr-notif-read-all" style="background:none; border:none; color:#3b82f6; cursor:pointer; font-size:12px;">Tumunu oku</button>
                            <?php endif; ?>
                        </div>
                        <?php if (empty($recent)): ?>
                        <p style="padding:30px 16px; text-align:center; color:#9ca3af; margin:0;">Henuz bildirim yok</p>
                        <?php else: ?>
                        <?php foreach ($recent as $n):
                            $is_unread = empty($n['read']);
                            $time_ago = human_time_diff(strtotime($n['time']), current_time('timestamp'));
                        ?>
                        <div style="padding:10px 16px; border-bottom:1px solid #f8f8f8; <?php echo $is_unread ? 'background:#f0f7ff;' : ''; ?>">
                            <div style="display:flex; gap:8px; align-items:flex-start;">
                                <span style="font-size:18px; flex-shrink:0;"><?php echo esc_html($n['icon'] ?? '🔔'); ?></span>
                                <div>
                                    <div style="font-size:13px; color:#1f2937; <?php echo $is_unread ? 'font-weight:600;' : ''; ?>"><?php echo esc_html($n['message'] ?? ''); ?></div>
                                    <div style="font-size:11px; color:#9ca3af; margin-top:2px;"><?php echo esc_html($time_ago); ?> once</div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <script>
                (function(){
                    var bell = document.getElementById('glr-notif-bell');
                    var panel = document.getElementById('glr-notif-panel');
                    var readAll = document.getElementById('glr-notif-read-all');
                    if (!bell || !panel) return;

                    bell.addEventListener('click', function(e) {
                        e.stopPropagation();
                        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
                    });
                    document.addEventListener('click', function(e) {
                        if (!panel.contains(e.target) && e.target !== bell) panel.style.display = 'none';
                    });

                    if (readAll) {
                        readAll.addEventListener('click', function() {
                            var xhr = new XMLHttpRequest();
                            xhr.open('POST', '<?php echo esc_url(admin_url('admin-ajax.php')); ?>', true);
                            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhr.onload = function() {
                                var badge = bell.querySelector('span');
                                if (badge) badge.remove();
                                panel.querySelectorAll('[style*="f0f7ff"]').forEach(function(el) {
                                    el.style.background = '';
                                });
                                readAll.remove();
                            };
                            xhr.send('action=gorilla_notifications_read&nonce=<?php echo wp_create_nonce('gorilla_notifications'); ?>');
                        });
                    }
                })();
                </script>
            </div>

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
            if (defined('WPGAMIFY_VERSION') && function_exists('gorilla_xp_calculate_level')):
                $xp_balance = gorilla_xp_get_balance($user_id);
                $current_level = gorilla_xp_calculate_level($user_id);
                $next_level = gorilla_xp_get_next_level($user_id);
                $xp_log = gorilla_xp_get_log($user_id, 5);
                $levels = gorilla_xp_get_levels();

                // XP ayarları (WP Gamify settings'ten oku)
                $xp_order_rate = class_exists('WPGamify_Settings') ? intval(WPGamify_Settings::get('xp_order_per_currency', 1)) : 1;
                $xp_review = class_exists('WPGamify_Settings') ? intval(WPGamify_Settings::get('xp_review_amount', 15)) : 15;
                $xp_referral = class_exists('WPGamify_Settings') ? intval(WPGamify_Settings::get('xp_referral_amount', 50)) : 50;
                $xp_affiliate = class_exists('WPGamify_Settings') ? intval(WPGamify_Settings::get('xp_affiliate_amount', 30)) : 30;

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
            <?php
            if (!empty($user_badges) && is_array($user_badges)):
                // PHC cross-plugin: badge tier -> holo effect mapping
                $phc_available = function_exists('phc_is_available') && phc_is_available();
                $phc_tier_effects = array(
                    'bronze'  => 'holo',
                    'silver'  => 'prism',
                    'gold'    => 'galaxy',
                    'diamond' => 'cosmos',
                );
            ?>
            <div class="glr-badge-grid">
                <?php foreach ($user_badges as $badge):
                    if (!is_array($badge)) continue;
                    $is_earned = !empty($badge['earned_at']);
                    $badge_tier = $badge['tier'] ?? '';
                    $use_holo = $phc_available && $is_earned && !empty($badge_tier);
                    $holo_effect = $use_holo ? ($phc_tier_effects[$badge_tier] ?? 'holo') : '';
                ?>
                <?php if ($use_holo): ?>
                <div class="phc-card phc-effect-<?php echo esc_attr($holo_effect); ?> glr-badge-holo" style="width:120px;" data-phc-effect="<?php echo esc_attr($holo_effect); ?>" data-phc-sparkle="<?php echo ($badge_tier === 'diamond' || $badge_tier === 'gold') ? 'true' : 'false'; ?>" data-phc-radius="12">
                    <div class="phc-card__translater"><div class="phc-card__rotator">
                        <div class="phc-card__front glr-badge-card earned" style="border-color:<?php echo esc_attr($badge['tier_color'] ?? '#999'); ?>; width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:12px; box-sizing:border-box; background:#fff;">
                            <div class="glr-badge-emoji" style="font-size:36px; line-height:1; margin-bottom:6px;"><?php echo esc_html($badge['emoji'] ?? '🏅'); ?></div>
                            <div style="font-weight:700; font-size:11px; color:#1f2937; text-align:center;"><?php echo esc_html($badge['label'] ?? ''); ?></div>
                            <?php if (!empty($badge['tier'])): ?>
                            <div style="display:inline-block; background:<?php echo esc_attr($badge['tier_color']); ?>30; color:<?php echo esc_attr($badge['tier_color']); ?>; padding:1px 6px; border-radius:6px; font-size:9px; font-weight:700; margin-top:3px;">
                                <?php echo esc_html($badge['tier_emoji'] . ' ' . $badge['tier_label']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="phc-card__shine"></div>
                        <div class="phc-card__glare"></div>
                    </div></div>
                </div>
                <?php else: ?>
                <div class="glr-badge-card <?php echo $is_earned ? 'earned' : 'locked'; ?>"<?php if ($is_earned && !empty($badge['tier_color'])): ?> style="border-color:<?php echo esc_attr($badge['tier_color']); ?>;"<?php endif; ?>>
                    <div class="glr-badge-emoji" style="font-size:40px; line-height:1; margin-bottom:8px;"><?php echo esc_html($badge['emoji'] ?? '🏅'); ?></div>
                    <div style="font-weight:700; font-size:13px; color:#1f2937;"><?php echo esc_html($badge['label'] ?? ''); ?></div>
                    <?php if ($is_earned && !empty($badge['tier'])): ?>
                        <div style="display:inline-block; background:<?php echo esc_attr($badge['tier_color']); ?>30; color:<?php echo esc_attr($badge['tier_color']); ?>; padding:2px 8px; border-radius:8px; font-size:11px; font-weight:700; margin-top:4px;">
                            <?php echo esc_html($badge['tier_emoji'] . ' ' . $badge['tier_label']); ?>
                        </div>
                    <?php endif; ?>
                    <div style="font-size:11px; color:#6b7280; margin-top:4px;"><?php echo esc_html($badge['description'] ?? ''); ?></div>
                    <?php if ($is_earned): ?>
                        <div style="font-size:11px; color:#22c55e; font-weight:600; margin-top:6px;"><?php echo esc_html(wp_date('d.m.Y', strtotime($badge['earned_at']))); ?></div>
                    <?php else: ?>
                        <div style="font-size:11px; color:#9ca3af; margin-top:6px;">Henuz kazanilmadi</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
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
            if (class_exists('WPGamify_Settings') && WPGamify_Settings::get('streak_enabled', true)):
                $streak_data = class_exists('WPGamify_Streak_Manager') ? WPGamify_Streak_Manager::get_streak($user_id) : array();
                $login_streak = intval($streak_data['current_streak'] ?? 0);
                $login_streak_best = intval($streak_data['max_streak'] ?? 0);
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
            // ═══ GOREVLER / CHALLENGES ═══
            if (function_exists('gorilla_challenges_render')):
                gorilla_challenges_render($user_id);
            endif;
            ?>

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
                        } elseif ($ms_type === 'total_referrals' || $ms_type === 'referral_count') {
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

            <!-- Credit Transfer: REMOVED duplicate non-atomic UI (C1/C6).
                 The unified "Puan Transfer" section below uses gorilla_transfer_credit
                 handler in class-loyalty.php with GET_LOCK atomicity. -->

            <!-- Analytics Dashboard -->
            <?php
            global $wpdb;
            $xp_table     = $wpdb->prefix . 'gamify_xp_transactions';
            $credit_table = $wpdb->prefix . 'gorilla_credit_log';

            // Son 6 ay XP verisi (aylik) - WP Gamify tablosundan
            $xp_monthly = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE_FORMAT(created_at, '%%Y-%%m') as month, SUM(amount) as total_xp FROM {$xp_table} WHERE user_id = %d AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY month ORDER BY month ASC",
                $user_id
            ));

            // Son 6 ay credit kullanim verisi
            $credit_monthly = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE_FORMAT(created_at, '%%Y-%%m') as month, SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as earned, SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as spent FROM {$credit_table} WHERE user_id = %d AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY month ORDER BY month ASC",
                $user_id
            ));

            $xp_labels  = array();
            $xp_values  = array();
            if ($xp_monthly) {
                foreach ($xp_monthly as $row) {
                    $xp_labels[] = wp_date('M Y', strtotime($row->month . '-01'));
                    $xp_values[] = intval($row->total_xp);
                }
            }

            $cr_labels  = array();
            $cr_earned  = array();
            $cr_spent   = array();
            if ($credit_monthly) {
                foreach ($credit_monthly as $row) {
                    $cr_labels[] = wp_date('M Y', strtotime($row->month . '-01'));
                    $cr_earned[] = round(floatval($row->earned), 2);
                    $cr_spent[]  = round(floatval($row->spent), 2);
                }
            }

            // Badge progress
            $badge_count = 0;
            $badge_total = 0;
            if (function_exists('gorilla_badge_get_definitions') && function_exists('gorilla_badge_get_user_badges')) {
                $all_badges  = gorilla_badge_get_definitions();
                $user_badges = gorilla_badge_get_user_badges($user_id);
                $badge_total = count($all_badges);
                $badge_count = count($user_badges);
            }
            $badge_pct = $badge_total > 0 ? round(($badge_count / $badge_total) * 100) : 0;
            ?>
            <?php
            // ═══ PUAN TRANSFER ═══
            if (get_option('gorilla_lr_transfer_enabled', 'no') === 'yes'):
                $tr_daily_limit = floatval(get_option('gorilla_lr_transfer_daily_limit', 500));
                $tr_min_amount  = floatval(get_option('gorilla_lr_transfer_min_amount', 10));
                $tr_today_key   = '_gorilla_transfer_total_' . current_time('Y-m-d');
                $tr_today_total = floatval(get_user_meta($user_id, $tr_today_key, true));
                $tr_remaining   = max(0, $tr_daily_limit - $tr_today_total);
                $tr_log         = get_user_meta($user_id, '_gorilla_transfer_log', true);
                if (!is_array($tr_log)) $tr_log = array();
            ?>
            <hr style="border:none; border-top:2px dashed #e5e7eb; margin:35px 0;">
            <h2 style="font-size:24px; font-weight:800; margin-bottom:20px;">🔄 Puan Transfer</h2>

            <div class="glr-card" style="padding:20px 24px;">
                <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:16px;">
                    <div style="background:#f0fdf4; padding:10px 16px; border-radius:8px; font-size:13px;">
                        Gunluk limit: <strong><?php echo number_format_i18n($tr_daily_limit); ?></strong> | Kalan: <strong><?php echo number_format_i18n($tr_remaining); ?></strong>
                    </div>
                    <div style="background:#eff6ff; padding:10px 16px; border-radius:8px; font-size:13px;">
                        Min. transfer: <strong><?php echo number_format_i18n($tr_min_amount); ?></strong>
                    </div>
                </div>

                <form id="glr-transfer-form" style="display:grid; grid-template-columns:1fr 1fr auto auto; gap:12px; align-items:end;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#6b7280; margin-bottom:4px;">Alici E-posta</label>
                        <input type="email" id="glr-transfer-email" required placeholder="ornek@email.com" style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#6b7280; margin-bottom:4px;">Miktar</label>
                        <input type="number" id="glr-transfer-amount" required min="<?php echo esc_attr($tr_min_amount); ?>" max="<?php echo esc_attr($tr_remaining); ?>" step="1" placeholder="<?php echo esc_attr($tr_min_amount); ?>" style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#6b7280; margin-bottom:4px;">Tip</label>
                        <select id="glr-transfer-type" style="padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px;">
                            <option value="credit">Store Credit (TL)</option>
                            <option value="xp">XP</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="glr-btn" style="background:linear-gradient(135deg,#8b5cf6,#6366f1); color:#fff; border:none; padding:9px 20px; border-radius:8px; font-weight:600; cursor:pointer; white-space:nowrap;">Transfer Et</button>
                    </div>
                </form>
                <div id="glr-transfer-msg" style="margin-top:10px; font-size:13px; display:none;"></div>
            </div>

            <?php if (!empty($tr_log)): ?>
            <div class="glr-card" style="margin-top:16px; padding:16px 20px;">
                <h4 style="margin:0 0 12px 0; font-size:14px; color:#6b7280;">Son Transferler</h4>
                <div style="max-height:200px; overflow-y:auto;">
                    <?php foreach (array_slice($tr_log, 0, 10) as $tl):
                        $tr_user = get_user_by('id', $tl['recipient_id'] ?? 0);
                        $tr_name = $tr_user ? $tr_user->display_name : 'Bilinmeyen';
                    ?>
                    <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6; font-size:13px;">
                        <span>→ <?php echo esc_html($tr_name); ?></span>
                        <span style="color:#ef4444; font-weight:600;">-<?php echo number_format_i18n($tl['amount']); ?> <?php echo $tl['type'] === 'credit' ? 'TL' : 'XP'; ?></span>
                        <span style="color:#9ca3af;"><?php echo esc_html(wp_date('d M Y', strtotime($tl['date']))); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <script>
            (function(){
                var form = document.getElementById('glr-transfer-form');
                if (!form) return;
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var btn = form.querySelector('button[type=submit]');
                    var msg = document.getElementById('glr-transfer-msg');
                    btn.disabled = true;
                    btn.textContent = 'Gonderiliyor...';
                    msg.style.display = 'none';

                    var fd = new FormData();
                    fd.append('action', 'gorilla_transfer_credit');
                    fd.append('_nonce', '<?php echo wp_create_nonce("gorilla_transfer_nonce"); ?>');
                    fd.append('recipient_email', document.getElementById('glr-transfer-email').value);
                    fd.append('amount', document.getElementById('glr-transfer-amount').value);
                    fd.append('transfer_type', document.getElementById('glr-transfer-type').value);

                    fetch('<?php echo esc_url(admin_url("admin-ajax.php")); ?>', { method: 'POST', body: fd })
                        .then(function(r){ return r.json(); })
                        .then(function(data){
                            msg.style.display = 'block';
                            if (data.success) {
                                msg.style.color = '#22c55e';
                                msg.textContent = data.data.message;
                                setTimeout(function(){ location.reload(); }, 1500);
                            } else {
                                msg.style.color = '#ef4444';
                                msg.textContent = data.data.message || 'Bir hata olustu.';
                                btn.disabled = false;
                                btn.textContent = 'Transfer Et';
                            }
                        })
                        .catch(function(){
                            msg.style.display = 'block';
                            msg.style.color = '#ef4444';
                            msg.textContent = 'Baglanti hatasi.';
                            btn.disabled = false;
                            btn.textContent = 'Transfer Et';
                        });
                });
            })();
            </script>
            <?php endif; ?>

            <h3 style="margin-top:30px; border-bottom:2px solid #e5e7eb; padding-bottom:10px; font-size:20px; font-weight:800;">📊 Kişisel Analizler</h3>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px; margin:20px 0;">
                <!-- XP Trend -->
                <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
                    <h4 style="margin:0 0 12px 0; font-size:14px; color:#6b7280;">XP Kazanımı (Son 6 Ay)</h4>
                    <canvas id="glr-xp-chart" width="300" height="180"></canvas>
                </div>

                <!-- Credit Trend -->
                <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
                    <h4 style="margin:0 0 12px 0; font-size:14px; color:#6b7280;">Store Credit (Son 6 Ay)</h4>
                    <canvas id="glr-credit-chart" width="300" height="180"></canvas>
                </div>

                <!-- Badge Progress -->
                <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px; text-align:center;">
                    <h4 style="margin:0 0 12px 0; font-size:14px; color:#6b7280;">Rozet Koleksiyonu</h4>
                    <div style="position:relative; width:120px; height:120px; margin:0 auto;">
                        <svg viewBox="0 0 36 36" style="width:100%; height:100%; transform:rotate(-90deg);">
                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e5e7eb" stroke-width="3"/>
                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#8b5cf6" stroke-width="3" stroke-dasharray="<?php echo $badge_pct; ?>, 100" stroke-linecap="round"/>
                        </svg>
                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); font-size:22px; font-weight:800; color:#1f2937;"><?php echo $badge_pct; ?>%</div>
                    </div>
                    <p style="margin:8px 0 0; font-size:13px; color:#6b7280;"><?php echo $badge_count; ?> / <?php echo $badge_total; ?> rozet</p>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
            <script>
            (function(){
                var xpLabels  = <?php echo wp_json_encode($xp_labels); ?>;
                var xpValues  = <?php echo wp_json_encode($xp_values); ?>;
                var crLabels  = <?php echo wp_json_encode($cr_labels); ?>;
                var crEarned  = <?php echo wp_json_encode($cr_earned); ?>;
                var crSpent   = <?php echo wp_json_encode($cr_spent); ?>;

                var chartOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } };

                // XP Chart
                var xpCtx = document.getElementById('glr-xp-chart');
                if (xpCtx && xpLabels.length > 0) {
                    new Chart(xpCtx.getContext('2d'), {
                        type: 'bar',
                        data: { labels: xpLabels, datasets: [{ label: 'XP', data: xpValues, backgroundColor: 'rgba(99, 102, 241, 0.7)', borderRadius: 6 }] },
                        options: chartOpts
                    });
                } else if (xpCtx) {
                    xpCtx.parentElement.innerHTML += '<p style="text-align:center;color:#9ca3af;margin-top:40px;">Henuz veri yok</p>';
                }

                // Credit Chart
                var crCtx = document.getElementById('glr-credit-chart');
                if (crCtx && crLabels.length > 0) {
                    new Chart(crCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: crLabels,
                            datasets: [
                                { label: 'Kazanilan', data: crEarned, backgroundColor: 'rgba(34, 197, 94, 0.7)', borderRadius: 6 },
                                { label: 'Harcanan', data: crSpent, backgroundColor: 'rgba(239, 68, 68, 0.6)', borderRadius: 6 }
                            ]
                        },
                        options: Object.assign({}, chartOpts, { plugins: { legend: { display: true, position: 'bottom' } } })
                    });
                } else if (crCtx) {
                    crCtx.parentElement.innerHTML += '<p style="text-align:center;color:#9ca3af;margin-top:40px;">Henuz veri yok</p>';
                }
            })();
            </script>

            <?php
            // Extensibility hook: SMS opt-in, custom settings, etc.
            do_action('gorilla_frontend_after_settings');
            ?>

        </div>
        <?php
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla LR loyalty page error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        }
        echo '<p style="color:#ef4444;">Sadakat sayfası yüklenirken bir hata oluştu. Lütfen yöneticiye bildirin.</p>';
    }
});
