<?php
/**
 * Gorilla Loyalty & Gamification - Admin Dashboard & Kullanici Yonetimi
 *
 * @package Gorilla_Loyalty_Gamification
 * @since   3.1.0
 */

if (!defined('ABSPATH')) exit;

// ── Ana Menu ─────────────────────────────────────────────
// TUM MENU KAYITLARI BURADA (spec uyumu)
add_action('admin_menu', function() {
    // Ana menu
    add_menu_page(
        __('Gorilla Loyalty', 'gorilla-loyalty'),
        '🦍 ' . __('Gorilla Loyalty', 'gorilla-loyalty'),
        'manage_woocommerce',
        'gorilla-loyalty-admin',
        'gorilla_admin_dashboard_page',
        'dashicons-awards',
        56
    );

    // Dashboard alt menu
    add_submenu_page(
        'gorilla-loyalty-admin',
        __('Dashboard', 'gorilla-loyalty'),
        '📊 ' . __('Dashboard', 'gorilla-loyalty'),
        'manage_woocommerce',
        'gorilla-loyalty-admin',
        'gorilla_admin_dashboard_page'
    );

    // Ayarlar (class-gorilla-settings.php'den tasindi)
    add_submenu_page(
        'gorilla-loyalty-admin',
        __('Ayarlar', 'gorilla-loyalty'),
        '⚙️ ' . __('Ayarlar', 'gorilla-loyalty'),
        'manage_woocommerce',
        'gorilla-loyalty-settings',
        'gorilla_settings_page_render'
    );

    // Credit Yonetimi
    add_submenu_page(
        'gorilla-loyalty-admin',
        __('Credit Yonetimi', 'gorilla-loyalty'),
        "\xF0\x9F\x92\xB3 " . __('Credit Yonetimi', 'gorilla-loyalty'),
        'manage_woocommerce',
        'gorilla-lg-credit',
        'gorilla_lg_admin_credit_page'
    );
});


// ── Dashboard Sayfasi ────────────────────────────────────
function gorilla_admin_dashboard_page() {
    global $wpdb;

    // Istatistikler - cache'li
    $stats = get_transient('gorilla_lr_dashboard_stats');
    if (!$stats) {
        $stats = gorilla_admin_calculate_stats();
        set_transient('gorilla_lr_dashboard_stats', $stats, 3600);
    }

    $total_credit_out = 0;
    if (function_exists('gorilla_credit_get_balance')) {
        $total_credit_out = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(CAST(meta_value AS DECIMAL(10,2))), 0) FROM {$wpdb->usermeta} WHERE meta_key = %s AND CAST(meta_value AS DECIMAL(10,2)) > 0",
                '_gorilla_store_credit'
            )
        );
    }
    $period = get_option('gorilla_lr_period_months', 6);
    $rate = get_option('gorilla_lr_referral_rate', 35);

    // Affiliate istatistikleri
    $affiliate_stats = function_exists('gorilla_affiliate_get_admin_stats') ? gorilla_affiliate_get_admin_stats() : array();
    $affiliate_enabled = get_option('gorilla_lr_enabled_affiliate', 'yes') === 'yes';

    // XP istatistikleri
    $xp_stats = function_exists('gorilla_xp_get_admin_stats') ? gorilla_xp_get_admin_stats() : array();
    $xp_enabled = defined('WPGAMIFY_VERSION');
    ?>
    <div class="wrap">
        <h1 style="display:flex; align-items:center; gap:10px;">
            🦍 Gorilla Loyalty & Gamification Dashboard
            <span style="font-size:12px; background:#e0e7ff; color:#4338ca; padding:4px 12px; border-radius:20px; font-weight:500;">v<?php echo esc_html(GORILLA_LG_VERSION); ?></span>
        </h1>

        <!-- STAT KARTLARI -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px; margin:20px 0;">
            <?php
            $affiliate_card_value = function_exists('gorilla_affiliate_get_admin_stats') ? number_format($affiliate_stats['total_conversions'] ?? 0) : 'N/A';
            $affiliate_card_sub = function_exists('gorilla_affiliate_get_admin_stats')
                ? ($affiliate_enabled ? '<span style="font-size:11px; color:#6b7280;">%' . round($affiliate_stats['conversion_rate'] ?? 0, 1) . ' CVR</span>' : '<span style="font-size:11px; color:#f59e0b;">Kapali</span>')
                : '<span style="font-size:11px; color:#6b7280;">N/A</span>';

            $credit_card_value = function_exists('gorilla_credit_get_balance') ? wc_price($total_credit_out) : 'N/A';
            $credit_card_sub = function_exists('gorilla_credit_get_balance') ? '<span style="font-size:11px; color:#6b7280;">Aktif bakiye</span>' : '<span style="font-size:11px; color:#6b7280;">N/A</span>';

            $cards = array(
                array('🔗', 'Affiliate Satis', $affiliate_card_value, '#8b5cf6', $affiliate_card_sub),
                array('💰', 'Toplam Credit', $credit_card_value, '#3b82f6', $credit_card_sub),
                array('👥', 'Toplam Uye', number_format($stats['total_customers'] ?? 0), '#14b8a6', ''),
                array('🎖️', 'Seviyeli Uye', number_format($stats['tiered_customers'] ?? 0), '#06b6d4', ''),
            );
            foreach ($cards as $c):
            ?>
            <div style="background:#fff; padding:20px; border-radius:14px; border-top:4px solid <?php echo esc_attr($c[3]); ?>; box-shadow:0 1px 6px rgba(0,0,0,0.05);">
                <div style="font-size:24px; margin-bottom:4px;"><?php echo esc_html($c[0]); ?></div>
                <div style="font-size:24px; font-weight:800; color:#1f2937;"><?php echo wp_kses_post($c[2]); ?></div>
                <div style="color:#6b7280; font-size:12px; margin-top:4px;"><?php echo esc_html($c[1]); ?></div>
                <?php if ($c[4]): ?><div style="margin-top:4px;"><?php echo wp_kses_post($c[4]); ?></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ANALITIK BOLUMU -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px;">
            <!-- SEVIYE DAGILIMI -->
            <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05);">
                <h2 style="margin-top:0; font-size:16px;">📊 Seviye Dagilimi</h2>
                <?php
                $tier_dist = $stats['tier_distribution'] ?? array();
                $total_tiered = array_sum($tier_dist);
                $tiers = gorilla_get_tiers();
                ?>
                <?php if ($total_tiered > 0): ?>
                <div style="display:flex; flex-direction:column; gap:8px; margin-top:15px;">
                    <?php foreach ($tiers as $key => $tier):
                        $count = intval($tier_dist[$key] ?? 0);
                        $pct = $total_tiered > 0 ? round(($count / $total_tiered) * 100, 1) : 0;
                    ?>
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
                            <span><?php echo esc_html($tier['emoji'] . ' ' . $tier['label']); ?></span>
                            <span style="font-weight:600;"><?php echo $count; ?> uye (<?php echo $pct; ?>%)</span>
                        </div>
                        <div style="background:#e5e7eb; border-radius:10px; height:8px; overflow:hidden;">
                            <div style="background:<?php echo esc_attr($tier['color']); ?>; height:100%; width:<?php echo $pct; ?>%; transition:width 0.5s;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="color:#888; text-align:center; padding:20px;">Henuz seviye kazanan musteri yok.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- IKINCI SATIR -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px;">
            <!-- SEVIYE TABLOSU -->
            <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05);">
                <h2 style="margin-top:0; font-size:16px;">🎖️ Sadakat Seviyeleri</h2>
                <table class="widefat striped" style="font-size:13px;">
                    <thead><tr><th></th><th>Seviye</th><th>Esik</th><th>Indirim</th><th>Ekstra</th></tr></thead>
                    <tbody>
                    <?php foreach ($tiers as $key => $tier): ?>
                        <tr>
                            <td style="font-size:20px; text-align:center;"><?php echo esc_html($tier['emoji']); ?></td>
                            <td><strong><?php echo esc_html($tier['label']); ?></strong></td>
                            <td><?php echo wc_price($tier['min']); ?></td>
                            <td style="font-weight:700;">%<?php echo esc_html($tier['discount']); ?></td>
                            <td style="font-size:11px;">
                                <?php
                                $extras = array();
                                if (!empty($tier['installment'])) $extras[] = intval($tier['installment']) . ' Taksit';
                                if (!empty($tier['free_shipping'])) $extras[] = 'Ucretsiz Kargo';
                                echo esc_html($extras ? implode(', ', $extras) : '—');
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="text-align:right; margin-top:10px;">
                    <a href="<?php echo admin_url('admin.php?page=gorilla-loyalty-settings'); ?>" class="button">⚙️ Duzenle</a>
                </p>
            </div>
        </div>

        <!-- UCUNCU SATIR: TOP MUSTERILER & SON CREDIT HAREKETLERI -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px;">
            <!-- TOP MUSTERILER -->
            <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05);">
                <h2 style="margin-top:0; font-size:16px;">🏆 En Yuksek Harcayan Musteriler</h2>
                <?php
                $top_customers = $stats['top_customers'] ?? array();
                if (!empty($top_customers)):
                ?>
                <table class="widefat striped" style="font-size:12px;">
                    <thead><tr><th>Musteri</th><th>Harcama</th><th>Seviye</th></tr></thead>
                    <tbody>
                    <?php foreach ($top_customers as $tc): ?>
                        <tr>
                            <td><strong><?php echo esc_html($tc['name']); ?></strong></td>
                            <td><?php echo wc_price($tc['spending']); ?></td>
                            <td><?php echo esc_html($tc['tier_emoji'] . ' ' . $tc['tier_label']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color:#888; text-align:center; padding:20px;">Henuz harcama verisi yok.</p>
                <?php endif; ?>
            </div>

            <!-- SON CREDIT HAREKETLERI -->
            <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05);">
                <h2 style="margin-top:0; font-size:16px;">💳 Son Credit Hareketleri</h2>
                <?php
                $credit_table = $wpdb->prefix . 'gorilla_credit_log';
                $recent_credits = array();
                if (function_exists('gorilla_credit_get_balance') && function_exists('gorilla_lr_table_exists') && gorilla_lr_table_exists($credit_table)) {
                    $recent_credits = $wpdb->get_results($wpdb->prepare(
                        "SELECT cl.*, u.display_name FROM {$credit_table} cl
                         LEFT JOIN {$wpdb->users} u ON cl.user_id = u.ID
                         ORDER BY cl.created_at DESC LIMIT %d", 6
                    ));
                }
                if (!empty($recent_credits)):
                ?>
                <table class="widefat striped" style="font-size:11px;">
                    <thead><tr><th>Musteri</th><th>Tutar</th><th>Tip</th><th>Tarih</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_credits as $rc):
                        $amount = floatval($rc->amount);
                        $color = $amount >= 0 ? '#22c55e' : '#ef4444';
                        $type_labels = array('credit' => '➕', 'debit' => '➖', 'referral' => '🔗', 'manual' => '✏️', 'expired' => '⏰', 'refund' => '↩️');
                    ?>
                        <tr>
                            <td><?php echo esc_html($rc->display_name ?? 'ID:' . $rc->user_id); ?></td>
                            <td style="font-weight:600; color:<?php echo $color; ?>;"><?php echo ($amount >= 0 ? '+' : '') . wc_price($amount); ?></td>
                            <td><?php echo ($type_labels[$rc->type] ?? '') . ' ' . esc_html($rc->type); ?></td>
                            <td><?php echo wp_date('d.m H:i', strtotime($rc->created_at)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color:#888; text-align:center; padding:20px;">Henuz credit hareketi yok.</p>
                <?php endif; ?>
                <p style="text-align:right; margin-top:10px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=gorilla-lg-credit')); ?>" class="button">Credit Yonetimi</a>
                </p>
            </div>
        </div>

        <!-- BESINCI SATIR: XP & LEVEL ISTATISTIKLERI -->
        <?php if ($xp_enabled): ?>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px;">
            <!-- XP GENEL STATS -->
            <div style="background:linear-gradient(135deg, #f0fdf4, #dcfce7); padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05); border:2px solid #22c55e;">
                <h2 style="margin-top:0; font-size:16px; color:#166534;">🎮 XP Sistemi Ozeti</h2>
                <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:15px; margin-top:15px;">
                    <div style="text-align:center; background:#fff; padding:12px; border-radius:10px;">
                        <div style="font-size:24px; font-weight:800; color:#22c55e;"><?php echo number_format_i18n($xp_stats['total_xp'] ?? 0); ?></div>
                        <div style="font-size:11px; color:#6b7280;">Toplam XP</div>
                    </div>
                    <div style="text-align:center; background:#fff; padding:12px; border-radius:10px;">
                        <div style="font-size:24px; font-weight:800; color:#f59e0b;"><?php echo number_format_i18n($xp_stats['avg_xp'] ?? 0); ?></div>
                        <div style="font-size:11px; color:#6b7280;">Ort. XP</div>
                    </div>
                    <div style="text-align:center; background:#fff; padding:12px; border-radius:10px;">
                        <div style="font-size:24px; font-weight:800; color:#8b5cf6;"><?php echo number_format_i18n($xp_stats['users_with_xp'] ?? 0); ?></div>
                        <div style="font-size:11px; color:#6b7280;">XP'li Kullanici</div>
                    </div>
                </div>
            </div>

            <!-- LEVEL DAGILIMI -->
            <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05);">
                <h2 style="margin-top:0; font-size:16px;">🏆 Level Dagilimi</h2>
                <?php
                $level_dist = $xp_stats['level_distribution'] ?? array();
                $total_leveled = array_sum($level_dist);
                $levels = function_exists('gorilla_xp_get_levels') ? gorilla_xp_get_levels() : array();
                ?>
                <?php if ($total_leveled > 0): ?>
                <div style="display:flex; flex-direction:column; gap:6px; margin-top:12px;">
                    <?php foreach ($levels as $lkey => $lvl):
                        $count = intval($level_dist[$lkey] ?? 0);
                        $pct = $total_leveled > 0 ? round(($count / $total_leveled) * 100, 1) : 0;
                        $level_num = intval(str_replace('level_', '', $lkey));
                    ?>
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:3px;">
                            <span><?php echo esc_html($lvl['emoji'] . ' L' . $level_num . ' ' . $lvl['label']); ?></span>
                            <span style="font-weight:600;"><?php echo $count; ?> (<?php echo $pct; ?>%)</span>
                        </div>
                        <div style="background:#e5e7eb; border-radius:8px; height:6px; overflow:hidden;">
                            <div style="background:<?php echo esc_attr($lvl['color']); ?>; height:100%; width:<?php echo $pct; ?>%; transition:width 0.5s;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="color:#888; text-align:center; padding:20px; font-size:13px;">Henuz XP kazanan kullanici yok.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- SON XP AKTIVITELERI -->
        <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05); margin-top:20px;">
            <h2 style="margin-top:0; font-size:16px;">📊 Son XP Aktiviteleri</h2>
            <?php
            $recent_xp = function_exists('gorilla_xp_get_recent_activity') ? gorilla_xp_get_recent_activity(8) : array();
            if (!empty($recent_xp)):
            ?>
            <table class="widefat striped" style="font-size:12px;">
                <thead><tr><th>Kullanici</th><th>XP</th><th>Sebep</th><th>Tarih</th></tr></thead>
                <tbody>
                <?php foreach ($recent_xp as $xp):
                    $xp_amount = intval($xp->amount);
                    $color = $xp_amount >= 0 ? '#22c55e' : '#ef4444';
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($xp->display_name ?? 'ID:' . $xp->user_id); ?></strong></td>
                        <td style="font-weight:600; color:<?php echo $color; ?>;"><?php echo ($xp_amount >= 0 ? '+' : '') . number_format_i18n($xp_amount); ?> XP</td>
                        <td><?php echo esc_html($xp->reason); ?></td>
                        <td><?php echo wp_date('d.m H:i', strtotime($xp->created_at)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color:#888; text-align:center; padding:20px;">Henuz XP aktivitesi yok.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- CSV EXPORT -->
        <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05); margin-top:20px;">
            <h2 style="margin-top:0; font-size:16px;">📥 Veri Disa Aktarma (CSV)</h2>
            <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-top:15px;">
                <div>
                    <label style="font-size:12px; color:#6b7280; display:block; margin-bottom:4px;">Baslangic</label>
                    <input type="date" id="glr-export-from" value="<?php echo esc_attr(wp_date('Y-m-d', strtotime('-3 months'))); ?>" style="width:150px;">
                </div>
                <div>
                    <label style="font-size:12px; color:#6b7280; display:block; margin-bottom:4px;">Bitis</label>
                    <input type="date" id="glr-export-to" value="<?php echo esc_attr(wp_date('Y-m-d')); ?>" style="width:150px;">
                </div>
                <?php if (function_exists('gorilla_lr_table_exists') && gorilla_lr_table_exists($wpdb->prefix . 'gorilla_credit_log')): ?>
                <button type="button" class="button button-primary glr-export-btn" data-type="credit_log">💰 Credit Log</button>
                <?php endif; ?>
                <button type="button" class="button button-primary glr-export-btn" data-type="xp_log">⚡ XP Log</button>
                <?php if (function_exists('gorilla_affiliate_get_admin_stats') && function_exists('gorilla_lr_table_exists') && gorilla_lr_table_exists($wpdb->prefix . 'gorilla_affiliate_clicks')): ?>
                <button type="button" class="button button-primary glr-export-btn" data-type="affiliate_stats">🔗 Affiliate</button>
                <?php endif; ?>
                <button type="button" class="button button-primary glr-export-btn" data-type="leaderboard">🏆 Leaderboard</button>
            </div>
            <p class="description" style="margin-top:10px;">Secili tarih araliginda CSV dosyasi olarak indirilir.</p>
        </div>
        <script>
        (function(){
            document.querySelectorAll('.glr-export-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var from = document.getElementById('glr-export-from').value;
                    var to   = document.getElementById('glr-export-to').value;
                    var type = this.getAttribute('data-type');
                    var url  = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=gorilla_csv_export&type=' + type + '&from=' + from + '&to=' + to + '&_wpnonce=<?php echo wp_create_nonce('gorilla_csv_export'); ?>';
                    window.location.href = url;
                });
            });
        })();
        </script>

        <!-- TIER SIMULATOR -->
        <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05); margin-top:20px;">
            <h2 style="margin-top:0; font-size:16px;">🎛️ Tier Simulasyonu</h2>
            <p class="description">Tier esiklerini degistirmeden once kac kullanicinin etkilenecegini gorun.</p>
            <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; margin-top:15px;">
                <div>
                    <label style="font-size:12px; color:#6b7280; display:block; margin-bottom:4px;">Donem (ay)</label>
                    <input type="number" id="glr-sim-period" value="<?php echo esc_attr(get_option('gorilla_lr_period_months', 6)); ?>" min="1" max="24" style="width:80px;">
                </div>
                <?php
                $sim_tiers = gorilla_get_tiers();
                $ti = 0;
                foreach ($sim_tiers as $tkey => $ttier):
                ?>
                <div>
                    <label style="font-size:12px; color:#6b7280; display:block; margin-bottom:4px;"><?php echo esc_html($ttier['emoji'] . ' ' . $ttier['label']); ?> (₺)</label>
                    <input type="number" class="glr-sim-threshold" data-tier="<?php echo esc_attr($tkey); ?>" value="<?php echo esc_attr($ttier['min_spending'] ?? 0); ?>" min="0" step="100" style="width:110px;">
                </div>
                <?php $ti++; endforeach; ?>
                <button type="button" id="glr-sim-btn" class="button button-primary">📊 Simule Et</button>
            </div>
            <div id="glr-sim-result" style="margin-top:15px; display:none;">
                <div id="glr-sim-bars" style="display:flex; gap:12px; flex-wrap:wrap;"></div>
            </div>
        </div>
        <script>
        (function(){
            var simBtn = document.getElementById('glr-sim-btn');
            if (!simBtn) return;
            simBtn.addEventListener('click', function() {
                var period = document.getElementById('glr-sim-period').value;
                var thresholds = {};
                document.querySelectorAll('.glr-sim-threshold').forEach(function(inp) {
                    thresholds[inp.getAttribute('data-tier')] = inp.value;
                });

                simBtn.disabled = true;
                simBtn.textContent = 'Hesaplaniyor...';

                var xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo esc_url(admin_url('admin-ajax.php')); ?>', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    simBtn.disabled = false;
                    simBtn.textContent = '📊 Simule Et';
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            var container = document.getElementById('glr-sim-bars');
                            container.innerHTML = '';
                            var total = res.data.total || 1;
                            res.data.distribution.forEach(function(d) {
                                var pct = Math.round((d.count / total) * 100);
                                container.innerHTML += '<div style="flex:1; min-width:120px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; text-align:center;">' +
                                    '<div style="font-size:24px;">' + d.emoji + '</div>' +
                                    '<div style="font-weight:700; font-size:14px; margin:4px 0;">' + d.label + '</div>' +
                                    '<div style="font-size:28px; font-weight:800; color:' + d.color + ';">' + d.count + '</div>' +
                                    '<div style="font-size:12px; color:#9ca3af;">' + pct + '%</div>' +
                                    '</div>';
                            });
                            document.getElementById('glr-sim-result').style.display = 'block';
                        }
                    } catch(e) {}
                };
                xhr.send('action=gorilla_tier_simulate&_wpnonce=<?php echo wp_create_nonce('gorilla_tier_simulate'); ?>&period=' + period + '&thresholds=' + encodeURIComponent(JSON.stringify(thresholds)));
            });
        })();
        </script>

        <!-- FOOTER BILGI -->
        <div style="margin-top:25px; padding:15px 20px; background:#f1f5f9; border-radius:12px; font-size:12px; color:#64748b;">
            <strong>📡 REST API:</strong> <code>/wp-json/gorilla-lg/v1/</code> —
            <strong>📊 Son guncelleme:</strong> <?php echo wp_date('d.m.Y H:i'); ?> —
            <a href="#" onclick="location.reload(); return false;">🔄 Yenile</a>
        </div>
    </div>
    <?php
}

/**
 * Dashboard istatistiklerini hesapla
 */
function gorilla_admin_calculate_stats() {
    global $wpdb;

    $stats = array(
        'calculated_at' => current_time('mysql'),
        'total_customers' => 0,
        'tiered_customers' => 0,
        'tier_distribution' => array(),
        'top_customers' => array(),
    );

    // Toplam musteri sayisi
    $user_counts = count_users();
    $stats['total_customers'] = intval($user_counts['avail_roles']['customer'] ?? 0);

    // Seviye dagilimi ve top musteriler - single batch SQL query (N+1 fix)
    $tiers = gorilla_get_tiers();
    $period = intval(get_option('gorilla_lr_period_months', 6));
    $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$period} months"));

    $tier_counts = array();
    foreach (array_keys($tiers) as $key) {
        $tier_counts[$key] = 0;
    }

    // HPOS-aware single batch query for customer spending
    $use_hpos = false;
    if (class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')) {
        if (method_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil', 'custom_orders_table_usage_is_enabled')) {
            $use_hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
    }

    $spending_rows = [];
    if ($use_hpos) {
        $orders_table = $wpdb->prefix . 'wc_orders';
        $spending_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT customer_id, SUM(total_amount) as total_spent
             FROM {$orders_table}
             WHERE status IN ('wc-completed','wc-processing')
             AND date_created_gmt >= %s AND customer_id > 0
             GROUP BY customer_id ORDER BY total_spent DESC LIMIT 500",
            $date_from
        ));
    } else {
        $spending_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.meta_value AS customer_id, SUM(pm2.meta_value) AS total_spent
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_customer_user'
             INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_order_total'
             WHERE p.post_type = 'shop_order'
             AND p.post_status IN ('wc-completed','wc-processing')
             AND p.post_date_gmt >= %s
             AND pm.meta_value > 0
             GROUP BY pm.meta_value ORDER BY total_spent DESC LIMIT 500",
            $date_from
        ));
    }

    // Compute tier distribution and top customers in-memory
    $spending_list = array();
    $tier_keys_sorted = array_keys($tiers);

    foreach ($spending_rows as $row) {
        $cid = intval($row->customer_id);
        $spent = floatval($row->total_spent);

        // Match spending to tier thresholds (in-memory)
        $matched_key = 'none';
        $matched_tier = array();
        foreach ($tiers as $key => $tier) {
            if (!is_array($tier)) continue;
            if ($spent >= ($tier['min'] ?? 0)) {
                $matched_key = $key;
                $matched_tier = $tier;
            }
        }

        if ($matched_key !== 'none' && isset($tier_counts[$matched_key])) {
            $tier_counts[$matched_key]++;
        }

        if ($spent > 0) {
            $spending_list[] = array(
                'id' => $cid,
                'spending' => $spent,
                'tier_key' => $matched_key,
                'tier_label' => $matched_tier['label'] ?? '',
                'tier_emoji' => $matched_tier['emoji'] ?? '',
            );
        }
    }

    $stats['tier_distribution'] = $tier_counts;
    $stats['tiered_customers'] = array_sum($tier_counts);

    // Top 5 customers (already sorted by total_spent DESC from SQL)
    $top = array();
    foreach (array_slice($spending_list, 0, 5) as $item) {
        $user = get_userdata($item['id']);
        $top[] = array(
            'id' => $item['id'],
            'name' => $user ? $user->display_name : 'ID:' . $item['id'],
            'spending' => $item['spending'],
            'tier_label' => $item['tier_label'],
            'tier_emoji' => $item['tier_emoji'],
        );
    }
    $stats['top_customers'] = $top;

    return $stats;
}


// ── Kullanici Listesi Kolonlari ──────────────────────────
add_filter('manage_users_columns', function($cols) {
    $cols['gorilla_tier']   = '🎖️ Seviye';
    $cols['gorilla_credit'] = '💰 Credit';
    $cols['gorilla_xp']     = '🎮 XP/Level';
    return $cols;
});

add_filter('manage_users_custom_column', function($val, $col, $uid) {
    // Pre-fetch all user meta for displayed users in one query (N+1 fix)
    static $meta_primed = false;
    if (!$meta_primed) {
        $meta_primed = true;
        // Get the current user list table query results and prime meta cache
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->id === 'users') {
            global $wp_list_table;
            if (!empty($wp_list_table) && method_exists($wp_list_table, 'get_column_info')) {
                // Access the items property which contains the queried user objects
                $items = isset($wp_list_table->items) ? $wp_list_table->items : array();
                $user_ids = wp_list_pluck($items, 'ID');
                if (!empty($user_ids)) {
                    update_meta_cache('user', $user_ids);
                }
            }
        }
    }

    if ($col === 'gorilla_tier') {
        $tier = gorilla_lr_get_user_tier($uid);
        return $tier['key'] !== 'none' ? esc_html($tier['emoji'] . ' ' . $tier['label']) : '<span style="color:#ccc;">—</span>';
    }
    if ($col === 'gorilla_credit') {
        if (!function_exists('gorilla_credit_get_balance')) {
            return '<span style="color:#ccc;">—</span>';
        }
        $credit = gorilla_credit_get_balance($uid);
        return $credit > 0 ? '<strong style="color:#22c55e;">' . wc_price($credit) . '</strong>' : '<span style="color:#ccc;">—</span>';
    }
    if ($col === 'gorilla_xp') {
        if (!function_exists('gorilla_xp_get_balance') || !function_exists('gorilla_xp_calculate_level')) {
            return '<span style="color:#ccc;">—</span>';
        }
        $xp = gorilla_xp_get_balance($uid);
        if ($xp <= 0) {
            return '<span style="color:#ccc;">—</span>';
        }
        $level = gorilla_xp_calculate_level($uid);
        $level_num = intval($level['number'] ?? 1);
        return '<span style="color:' . esc_attr($level['color'] ?? '#999') . ';">' . esc_html($level['emoji'] ?? '🌱') . ' L' . $level_num . '</span> <span style="color:#6b7280; font-size:11px;">(' . number_format_i18n($xp) . ' XP)</span>';
    }
    return $val;
}, 10, 3);


// ── Admin: Users Sayfasinda Tier Filtresi ────────────────
add_action('restrict_manage_users', function($which) {
    if ($which !== 'top') return;

    $tiers   = gorilla_get_tiers();
    $current = isset($_GET['gorilla_tier']) ? sanitize_key($_GET['gorilla_tier']) : '';

    echo '<select name="gorilla_tier" style="float:none; margin:0 6px;">';
    echo '<option value="">' . esc_html__('Tum Seviyeler', 'gorilla-loyalty') . '</option>';
    echo '<option value="none"' . selected($current, 'none', false) . '>' . esc_html__('Seviyesiz (Uye)', 'gorilla-loyalty') . '</option>';
    foreach ($tiers as $key => $tier) {
        printf(
            '<option value="%s"%s>%s %s</option>',
            esc_attr($key),
            selected($current, $key, false),
            esc_html($tier['emoji']),
            esc_html($tier['label'])
        );
    }
    echo '</select>';
});

add_action('pre_get_users', function($query) {
    if (!is_admin() || !function_exists('get_current_screen')) return;

    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'users') return;

    $tier_filter = isset($_GET['gorilla_tier']) ? sanitize_key($_GET['gorilla_tier']) : '';
    if ($tier_filter === '') return;

    $meta_query = $query->get('meta_query');
    if (!is_array($meta_query)) $meta_query = array();

    $meta_query[] = array(
        'key'   => '_gorilla_lr_tier_key',
        'value' => $tier_filter,
    );

    $query->set('meta_query', $meta_query);
});


// ── Admin: Siparis sayfasinda seviye bilgisi ─────────────
add_action('woocommerce_admin_order_data_after_billing_address', function($order) {
    $user_id = $order->get_customer_id();
    if (!$user_id) return;

    $tier = gorilla_lr_get_user_tier($user_id);

    echo '<div style="background:#f8fafc; padding:12px 16px; border-radius:8px; margin-top:12px; border:1px solid #e5e7eb;">';
    echo '<strong>🦍 Gorilla Loyalty</strong><br>';
    echo 'Seviye: ' . esc_html($tier['emoji'] . ' ' . $tier['label']);
    if ($tier['discount'] > 0) echo ' (%' . esc_html($tier['discount']) . ' indirim)';

    if (function_exists('gorilla_credit_get_balance')) {
        $credit = gorilla_credit_get_balance($user_id);
        if ($credit > 0) echo '<br>Store Credit: ' . wc_price($credit);
    }

    // XP/Level bilgisi
    if (function_exists('gorilla_xp_get_balance') && function_exists('gorilla_xp_calculate_level') && defined('WPGAMIFY_VERSION')) {
        $xp = gorilla_xp_get_balance($user_id);
        if ($xp > 0) {
            $level = gorilla_xp_calculate_level($user_id);
            $level_num = intval($level['number'] ?? 1);
            echo '<br>XP: <span style="color:' . esc_attr($level['color'] ?? '#999') . ';">' . esc_html($level['emoji'] ?? '🌱') . ' Level ' . $level_num . ' (' . esc_html($level['label'] ?? '') . ')</span> - ' . number_format_i18n($xp) . ' XP';
        }
    }
    echo '</div>';
});


// ── Bulk User Actions ────────────────────────────────────
add_filter('bulk_actions-users', function($actions) {
    $actions['gorilla_add_xp']     = '🦍 XP Ekle (100)';
    $actions['gorilla_add_xp_500'] = '🦍 XP Ekle (500)';
    $actions['gorilla_add_credit'] = '🦍 Credit Ekle (50₺)';
    return $actions;
});

add_filter('handle_bulk_actions-users', function($redirect_to, $action, $user_ids) {
    $count = 0;

    switch ($action) {
        case 'gorilla_add_xp':
            if (!function_exists('gorilla_xp_add')) break;
            foreach ($user_ids as $uid) {
                gorilla_xp_add(intval($uid), 100, 'Admin toplu XP eklemesi', 'admin_bulk', 0);
                $count++;
            }
            $redirect_to = add_query_arg('gorilla_bulk_msg', urlencode($count . ' kullaniciya 100 XP eklendi.'), $redirect_to);
            break;

        case 'gorilla_add_xp_500':
            if (!function_exists('gorilla_xp_add')) break;
            foreach ($user_ids as $uid) {
                gorilla_xp_add(intval($uid), 500, 'Admin toplu XP eklemesi', 'admin_bulk', 0);
                $count++;
            }
            $redirect_to = add_query_arg('gorilla_bulk_msg', urlencode($count . ' kullaniciya 500 XP eklendi.'), $redirect_to);
            break;

        case 'gorilla_add_credit':
            if (!function_exists('gorilla_credit_adjust')) break;
            foreach ($user_ids as $uid) {
                gorilla_credit_adjust(intval($uid), 50, 'admin_bulk', 'Admin toplu credit eklemesi', 0, 0);
                $count++;
            }
            $redirect_to = add_query_arg('gorilla_bulk_msg', urlencode($count . ' kullaniciya 50₺ credit eklendi.'), $redirect_to);
            break;
    }

    return $redirect_to;
}, 10, 3);

add_action('admin_notices', function() {
    if (!empty($_GET['gorilla_bulk_msg'])) {
        printf('<div class="notice notice-success is-dismissible"><p>🦍 %s</p></div>', esc_html(urldecode($_GET['gorilla_bulk_msg'])));
    }
});


// ── Tier Simulation AJAX Handler ─────────────────────────
add_action('wp_ajax_gorilla_tier_simulate', function() {
    if (!current_user_can('manage_woocommerce')) wp_send_json_error();
    check_ajax_referer('gorilla_tier_simulate');

    $period = max(1, min(24, intval($_POST['period'] ?? 6)));
    $thresholds = json_decode(wp_unslash($_POST['thresholds'] ?? '{}'), true);
    if (!is_array($thresholds)) $thresholds = array();

    // Sanitize thresholds
    foreach ($thresholds as $key => $val) {
        $thresholds[sanitize_key($key)] = max(0, floatval($val));
    }

    // Sort thresholds descending
    arsort($thresholds);

    // Calculate spending per user in the simulated period
    $from_date = gmdate('Y-m-d', strtotime("-{$period} months"));

    $users_spending = array();
    if (function_exists('wc_get_orders')) {
        global $wpdb;

        // Get total spending per customer in the period
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.meta_value as customer_id, SUM(pm2.meta_value) as total_spending
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_customer_user'
             INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_order_total'
             WHERE p.post_type IN ('shop_order', 'shop_order_placeholder')
             AND p.post_status IN ('wc-completed', 'wc-processing')
             AND p.post_date >= %s
             AND pm.meta_value > 0
             GROUP BY pm.meta_value",
            $from_date
        ));

        foreach ($results as $row) {
            $users_spending[intval($row->customer_id)] = floatval($row->total_spending);
        }
    }

    // Count total customers
    $total_users = intval($wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = '{$wpdb->prefix}capabilities' AND meta_value LIKE '%\"customer\"%'"
    ));

    // Distribute users into tiers
    $distribution = array();
    foreach ($thresholds as $tier_key => $min_spending) {
        $distribution[$tier_key] = array('count' => 0, 'min' => $min_spending);
    }
    $distribution['none'] = array('count' => 0, 'min' => 0);

    foreach ($users_spending as $uid => $spending) {
        $matched = false;
        foreach ($thresholds as $tier_key => $min_spending) {
            if ($spending >= $min_spending && $min_spending > 0) {
                $distribution[$tier_key]['count']++;
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            $distribution['none']['count']++;
        }
    }

    // Users without orders
    $no_order_count = max(0, $total_users - count($users_spending));
    $distribution['none']['count'] += $no_order_count;

    // Build response with tier labels/colors
    $tiers = function_exists('gorilla_get_tiers') ? gorilla_get_tiers() : array();
    $result = array();
    foreach ($distribution as $key => $data) {
        $tier_info = $tiers[$key] ?? array();
        $result[] = array(
            'key'   => $key,
            'label' => $tier_info['label'] ?? ($key === 'none' ? 'Seviyesiz' : $key),
            'emoji' => $tier_info['emoji'] ?? '👤',
            'color' => $tier_info['color'] ?? '#9ca3af',
            'count' => $data['count'],
        );
    }

    wp_send_json_success(array(
        'distribution' => $result,
        'total'        => $total_users,
    ));
});

// ── CSV Export AJAX Handler ──────────────────────────────
add_action('wp_ajax_gorilla_csv_export', 'gorilla_admin_csv_export');
function gorilla_admin_csv_export() {
    if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized');
    check_admin_referer('gorilla_csv_export');

    global $wpdb;
    $type = sanitize_key($_GET['type'] ?? '');
    $from = sanitize_text_field($_GET['from'] ?? '');
    $to   = sanitize_text_field($_GET['to'] ?? '');

    // Validate dates
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = wp_date('Y-m-d', strtotime('-3 months'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = wp_date('Y-m-d');
    $to_end = $to . ' 23:59:59';

    $filename = 'gorilla-' . $type . '-' . $from . '-to-' . $to . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for Excel UTF-8

    switch ($type) {
        case 'credit_log':
            fputcsv($output, array('ID', 'User ID', 'User Name', 'Amount', 'Type', 'Description', 'Date'));
            $table = $wpdb->prefix . 'gorilla_credit_log';
            if (function_exists('gorilla_lr_table_exists') && gorilla_lr_table_exists($table)) {
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table} WHERE created_at BETWEEN %s AND %s ORDER BY created_at DESC LIMIT 10000",
                    $from, $to_end
                ));
                // Batch load all users to avoid N+1
                $user_ids = array_unique(array_map(function($r) { return intval($r->user_id); }, $rows));
                $users_map = array();
                if (!empty($user_ids)) {
                    $batch_users = get_users(array('include' => $user_ids, 'fields' => array('ID', 'display_name', 'user_email')));
                    foreach ($batch_users as $u) {
                        $users_map[$u->ID] = $u;
                    }
                }
                foreach ($rows as $row) {
                    $user = isset($users_map[intval($row->user_id)]) ? $users_map[intval($row->user_id)] : null;
                    fputcsv($output, array(
                        $row->id,
                        $row->user_id,
                        $user ? $user->display_name : 'N/A',
                        $row->amount,
                        $row->type ?? '',
                        $row->description ?? '',
                        $row->created_at
                    ));
                }
            }
            break;

        case 'xp_log':
            fputcsv($output, array('ID', 'User ID', 'User Name', 'XP', 'Source', 'Description', 'Date'));
            $table = $wpdb->prefix . 'gamify_xp_transactions';
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE created_at BETWEEN %s AND %s ORDER BY created_at DESC LIMIT 10000",
                $from, $to_end
            ));
            // Batch load all users to avoid N+1
            $user_ids = array_unique(array_map(function($r) { return intval($r->user_id); }, $rows));
            $users_map = array();
            if (!empty($user_ids)) {
                $batch_users = get_users(array('include' => $user_ids, 'fields' => array('ID', 'display_name', 'user_email')));
                foreach ($batch_users as $u) {
                    $users_map[$u->ID] = $u;
                }
            }
            foreach ($rows as $row) {
                $user = isset($users_map[intval($row->user_id)]) ? $users_map[intval($row->user_id)] : null;
                fputcsv($output, array(
                    $row->id,
                    $row->user_id,
                    $user ? $user->display_name : 'N/A',
                    $row->amount,
                    $row->source ?? '',
                    $row->note ?? '',
                    $row->created_at
                ));
            }
            break;

        case 'affiliate_stats':
            fputcsv($output, array('ID', 'Affiliate User ID', 'Affiliate Name', 'Visitor IP', 'Converted', 'Order ID', 'Commission', 'Date'));
            $table = $wpdb->prefix . 'gorilla_affiliate_clicks';
            if (function_exists('gorilla_lr_table_exists') && gorilla_lr_table_exists($table)) {
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table} WHERE created_at BETWEEN %s AND %s ORDER BY created_at DESC LIMIT 10000",
                    $from, $to_end
                ));
                // Batch load all users to avoid N+1
                $user_ids = array_unique(array_filter(array_map(function($r) { return intval($r->affiliate_id ?? 0); }, $rows)));
                $users_map = array();
                if (!empty($user_ids)) {
                    $batch_users = get_users(array('include' => $user_ids, 'fields' => array('ID', 'display_name', 'user_email')));
                    foreach ($batch_users as $u) {
                        $users_map[$u->ID] = $u;
                    }
                }
                foreach ($rows as $row) {
                    $uid = intval($row->affiliate_id ?? 0);
                    $user = isset($users_map[$uid]) ? $users_map[$uid] : null;
                    fputcsv($output, array(
                        $row->id,
                        $row->affiliate_id ?? '',
                        $user ? $user->display_name : 'N/A',
                        $row->ip ?? '',
                        !empty($row->order_id) ? 'Yes' : 'No',
                        $row->order_id ?? '',
                        $row->commission ?? '',
                        $row->created_at
                    ));
                }
            }
            break;

        case 'leaderboard':
            fputcsv($output, array('Rank', 'User ID', 'User Name', 'Total XP', 'Level', 'Tier'));
            $table = $wpdb->prefix . 'gamify_xp_transactions';
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT user_id, SUM(amount) as total_xp FROM {$table} WHERE created_at BETWEEN %s AND %s GROUP BY user_id ORDER BY total_xp DESC LIMIT 100",
                $from, $to_end
            ));
            // Batch load all users to avoid N+1
            $user_ids = array_unique(array_map(function($r) { return intval($r->user_id); }, $rows));
            $users_map = array();
            if (!empty($user_ids)) {
                $batch_users = get_users(array('include' => $user_ids, 'fields' => array('ID', 'display_name', 'user_email')));
                foreach ($batch_users as $u) {
                    $users_map[$u->ID] = $u;
                }
            }
            $rank = 1;
            foreach ($rows as $row) {
                $user = isset($users_map[intval($row->user_id)]) ? $users_map[intval($row->user_id)] : null;
                $level = function_exists('gorilla_xp_calculate_level') ? gorilla_xp_calculate_level($row->user_id) : array('label' => 'N/A');
                $tier = function_exists('gorilla_lr_get_user_tier') ? gorilla_lr_get_user_tier($row->user_id) : array('label' => 'N/A');
                fputcsv($output, array(
                    $rank++,
                    $row->user_id,
                    $user ? $user->display_name : 'N/A',
                    intval($row->total_xp),
                    $level['label'] ?? 'N/A',
                    $tier['label'] ?? 'N/A'
                ));
            }
            break;

        default:
            fputcsv($output, array('Error: Unknown export type'));
            break;
    }

    fclose($output);
    exit;
}


// ── Credit Yonetimi Sayfasi ─────────────────────────────
function gorilla_lg_admin_credit_page() {
    if (!current_user_can('manage_woocommerce')) return;

    // Process credit adjustment form
    if (isset($_POST['gorilla_lg_manual_credit']) && wp_verify_nonce($_POST['_gorilla_lg_credit_nonce'] ?? '', 'gorilla_lg_manual_credit')) {
        $target_user = intval($_POST['credit_user_id'] ?? 0);
        $amount = floatval($_POST['credit_amount'] ?? 0);
        $action = sanitize_text_field($_POST['credit_action'] ?? 'add');
        $reason = sanitize_text_field($_POST['credit_reason'] ?? 'Manuel duzenleme');

        if ($target_user && $amount > 0 && function_exists('gorilla_credit_adjust')) {
            $actual_amount = ($action === 'remove') ? -$amount : $amount;
            $new_balance = gorilla_credit_adjust($target_user, $actual_amount, 'manual', $reason);
            $user = get_userdata($target_user);
            $name = $user ? $user->display_name : '#' . $target_user;
            echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html($name) . '</strong> - ' . ($action === 'add' ? 'Eklendi' : 'Cikarildi') . ': ' . wc_price($amount) . '. Yeni bakiye: ' . wc_price($new_balance) . '</p></div>';
        }
    }

    global $wpdb;
    $users_with_credit = $wpdb->get_results($wpdb->prepare(
        "SELECT u.ID, u.display_name, u.user_email, um.meta_value as credit
         FROM {$wpdb->users} u
         INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = %s
         WHERE CAST(um.meta_value AS DECIMAL(10,2)) > 0
         ORDER BY CAST(um.meta_value AS DECIMAL(10,2)) DESC
         LIMIT %d",
        '_gorilla_store_credit', 100
    ));
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Store Credit Yonetimi', 'gorilla-loyalty'); ?></h1>

        <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05); margin:20px 0; max-width:700px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Manuel Credit Islemi', 'gorilla-loyalty'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('gorilla_lg_manual_credit', '_gorilla_lg_credit_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Musteri', 'gorilla-loyalty'); ?></th>
                        <td><?php wp_dropdown_users(array('name' => 'credit_user_id', 'show_option_none' => '-- Musteri Secin --', 'role__in' => array('customer', 'subscriber', 'administrator'), 'orderby' => 'display_name')); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Islem', 'gorilla-loyalty'); ?></th>
                        <td>
                            <label style="margin-right:20px;"><input type="radio" name="credit_action" value="add" checked> <?php esc_html_e('Credit Ekle', 'gorilla-loyalty'); ?></label>
                            <label><input type="radio" name="credit_action" value="remove"> <?php esc_html_e('Credit Cikar', 'gorilla-loyalty'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Tutar', 'gorilla-loyalty'); ?></th>
                        <td><input type="number" name="credit_amount" min="0.01" step="0.01" style="width:150px;" required> <?php echo function_exists('get_woocommerce_currency_symbol') ? esc_html(get_woocommerce_currency_symbol()) : 'TL'; ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Sebep', 'gorilla-loyalty'); ?></th>
                        <td><input type="text" name="credit_reason" value="Manuel duzenleme" style="width:100%;"></td>
                    </tr>
                </table>
                <p><button type="submit" name="gorilla_lg_manual_credit" value="1" class="button button-primary"><?php esc_html_e('Uygula', 'gorilla-loyalty'); ?></button></p>
            </form>
        </div>

        <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05); max-width:700px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Aktif Store Credit Bakiyeleri', 'gorilla-loyalty'); ?></h2>
            <?php if (empty($users_with_credit)): ?>
                <p style="color:#888; text-align:center; padding:20px;"><?php esc_html_e('Henuz store credit bakiyesi olan musteri yok.', 'gorilla-loyalty'); ?></p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Musteri', 'gorilla-loyalty'); ?></th><th><?php esc_html_e('E-posta', 'gorilla-loyalty'); ?></th><th style="text-align:right;"><?php esc_html_e('Bakiye', 'gorilla-loyalty'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($users_with_credit as $uc): ?>
                        <tr>
                            <td><strong><?php echo esc_html($uc->display_name); ?></strong></td>
                            <td style="color:#888;"><?php echo esc_html($uc->user_email); ?></td>
                            <td style="text-align:right; font-weight:700; color:#22c55e; font-size:15px;"><?php echo wc_price($uc->credit); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}


