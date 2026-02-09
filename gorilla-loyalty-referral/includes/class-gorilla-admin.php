<?php
/**
 * Gorilla LR - Admin Dashboard & Kullanıcı Yönetimi
 */

if (!defined('ABSPATH')) exit;

// ── Ana Menü ─────────────────────────────────────────────
// TÜM MENÜ KAYITLARI BURADA (spec uyumu)
add_action('admin_menu', function() {
    // Ana menü
    add_menu_page(
        __('Gorilla Loyalty', 'gorilla-lr'),
        '🦍 ' . __('Gorilla Loyalty', 'gorilla-lr'),
        'manage_woocommerce',
        'gorilla-loyalty-admin',
        'gorilla_admin_dashboard_page',
        'dashicons-awards',
        56
    );

    // Dashboard alt menü
    add_submenu_page(
        'gorilla-loyalty-admin',
        __('Dashboard', 'gorilla-lr'),
        '📊 ' . __('Dashboard', 'gorilla-lr'),
        'manage_woocommerce',
        'gorilla-loyalty-admin',
        'gorilla_admin_dashboard_page'
    );

    // Manuel credit yönetimi
    add_submenu_page(
        'gorilla-loyalty-admin',
        __('Credit Yönetimi', 'gorilla-lr'),
        '💰 ' . __('Credit Yönetimi', 'gorilla-lr'),
        'manage_woocommerce',
        'gorilla-credit-manage',
        'gorilla_admin_credit_page'
    );

    // Ayarlar (class-gorilla-settings.php'den taşındı)
    add_submenu_page(
        'gorilla-loyalty-admin',
        __('Ayarlar', 'gorilla-lr'),
        '⚙️ ' . __('Ayarlar', 'gorilla-lr'),
        'manage_woocommerce',
        'gorilla-loyalty-settings',
        'gorilla_settings_page_render'
    );
});


// ── Dashboard Sayfası ────────────────────────────────────
function gorilla_admin_dashboard_page() {
    global $wpdb;

    // İstatistikler - cache'li
    $stats = get_transient('gorilla_lr_dashboard_stats');
    if (!$stats) {
        $stats = gorilla_admin_calculate_stats();
        set_transient('gorilla_lr_dashboard_stats', $stats, 3600);
    }

    $ref_counts = wp_count_posts('gorilla_referral');
    $pending = $ref_counts->pending ?? 0;
    $approved = $ref_counts->grla_approved ?? 0;
    $rejected = $ref_counts->grla_rejected ?? 0;

    $total_credit_out = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(CAST(meta_value AS DECIMAL(10,2))), 0) FROM {$wpdb->usermeta} WHERE meta_key = %s AND CAST(meta_value AS DECIMAL(10,2)) > 0",
            '_gorilla_store_credit'
        )
    );
    $period = get_option('gorilla_lr_period_months', 6);
    $rate = get_option('gorilla_lr_referral_rate', 35);

    // Affiliate istatistikleri
    $affiliate_stats = function_exists('gorilla_affiliate_get_admin_stats') ? gorilla_affiliate_get_admin_stats() : array();
    $affiliate_enabled = get_option('gorilla_lr_enabled_affiliate', 'yes') === 'yes';

    // XP istatistikleri
    $xp_stats = function_exists('gorilla_xp_get_admin_stats') ? gorilla_xp_get_admin_stats() : array();
    $xp_enabled = get_option('gorilla_lr_enabled_xp', 'yes') === 'yes';
    ?>
    <div class="wrap">
        <h1 style="display:flex; align-items:center; gap:10px;">
            🦍 Gorilla Loyalty & Referral Dashboard
            <span style="font-size:12px; background:#e0e7ff; color:#4338ca; padding:4px 12px; border-radius:20px; font-weight:500;">v<?php echo esc_html(GORILLA_LR_VERSION); ?></span>
        </h1>

        <!-- STAT KARTLARI -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px; margin:20px 0;">
            <?php
            $cards = array(
                array('⏳', 'Video Bekleyen', $pending, '#f59e0b', $pending > 0 ? '<a href="' . admin_url('edit.php?post_type=gorilla_referral&post_status=pending') . '" style="font-size:12px;">İncele →</a>' : ''),
                array('✅', 'Video Onaylanan', $approved, '#22c55e', ''),
                array('🔗', 'Affiliate Satış', number_format($affiliate_stats['total_conversions'] ?? 0), '#8b5cf6', $affiliate_enabled ? '<span style="font-size:11px; color:#6b7280;">%' . round($affiliate_stats['conversion_rate'] ?? 0, 1) . ' CVR</span>' : '<span style="font-size:11px; color:#f59e0b;">Kapalı</span>'),
                array('💰', 'Toplam Credit', wc_price($total_credit_out), '#3b82f6', '<span style="font-size:11px; color:#6b7280;">Aktif bakiye</span>'),
                array('👥', 'Toplam Üye', number_format($stats['total_customers'] ?? 0), '#14b8a6', ''),
                array('🎖️', 'Seviyeli Üye', number_format($stats['tiered_customers'] ?? 0), '#06b6d4', ''),
            );
            foreach ($cards as $c):
            ?>
            <div style="background:#fff; padding:20px; border-radius:14px; border-top:4px solid <?php echo $c[3]; ?>; box-shadow:0 1px 6px rgba(0,0,0,0.05);">
                <div style="font-size:24px; margin-bottom:4px;"><?php echo $c[0]; ?></div>
                <div style="font-size:24px; font-weight:800; color:#1f2937;"><?php echo $c[2]; ?></div>
                <div style="color:#6b7280; font-size:12px; margin-top:4px;"><?php echo $c[1]; ?></div>
                <?php if ($c[4]): ?><div style="margin-top:4px;"><?php echo $c[4]; ?></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ANALİTİK BÖLÜMÜ -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px;">
            <!-- SEVİYE DAĞILIMI -->
            <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05);">
                <h2 style="margin-top:0; font-size:16px;">📊 Seviye Dağılımı</h2>
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
                            <span style="font-weight:600;"><?php echo $count; ?> üye (<?php echo $pct; ?>%)</span>
                        </div>
                        <div style="background:#e5e7eb; border-radius:10px; height:8px; overflow:hidden;">
                            <div style="background:<?php echo esc_attr($tier['color']); ?>; height:100%; width:<?php echo $pct; ?>%; transition:width 0.5s;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="color:#888; text-align:center; padding:20px;">Henüz seviye kazanan müşteri yok.</p>
                <?php endif; ?>
            </div>

            <!-- AYLIK REFERANS TRENDİ -->
            <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05);">
                <h2 style="margin-top:0; font-size:16px;">📈 Son 6 Ay Referans Trendi</h2>
                <?php
                $monthly = $stats['monthly_referrals'] ?? array();
                $counts = array_column($monthly, 'count');
                $max_val = !empty($counts) ? max($counts) : 1;
                ?>
                <?php if (!empty($monthly)): ?>
                <div style="display:flex; align-items:flex-end; gap:8px; height:120px; margin-top:20px;">
                    <?php foreach ($monthly as $m):
                        $height = ($m['count'] / $max_val) * 100;
                    ?>
                    <div style="flex:1; display:flex; flex-direction:column; align-items:center;">
                        <div style="font-size:11px; font-weight:600; margin-bottom:4px;"><?php echo intval($m['count']); ?></div>
                        <div style="background:linear-gradient(135deg,#22c55e,#16a34a); width:100%; height:<?php echo max(4, $height); ?>px; border-radius:6px 6px 0 0;"></div>
                        <div style="font-size:10px; color:#888; margin-top:6px;"><?php echo esc_html($m['month']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="color:#888; text-align:center; padding:20px;">Henüz referans verisi yok.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- İKİNCİ SATIR -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px;">
            <!-- SEVİYE TABLOSU -->
            <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05);">
                <h2 style="margin-top:0; font-size:16px;">🎖️ Sadakat Seviyeleri</h2>
                <table class="widefat striped" style="font-size:13px;">
                    <thead><tr><th></th><th>Seviye</th><th>Eşik</th><th>İndirim</th><th>Ekstra</th></tr></thead>
                    <tbody>
                    <?php foreach ($tiers as $key => $tier): ?>
                        <tr>
                            <td style="font-size:20px; text-align:center;"><?php echo $tier['emoji']; ?></td>
                            <td><strong><?php echo esc_html($tier['label']); ?></strong></td>
                            <td><?php echo wc_price($tier['min']); ?></td>
                            <td style="font-weight:700;">%<?php echo $tier['discount']; ?></td>
                            <td style="font-size:11px;">
                                <?php
                                $extras = array();
                                if (!empty($tier['installment'])) $extras[] = $tier['installment'] . ' Taksit';
                                if (!empty($tier['free_shipping'])) $extras[] = 'Ücretsiz Kargo';
                                echo $extras ? implode(', ', $extras) : '—';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="text-align:right; margin-top:10px;">
                    <a href="<?php echo admin_url('admin.php?page=gorilla-loyalty-settings'); ?>" class="button">⚙️ Düzenle</a>
                </p>
            </div>

            <!-- SON REFERANSLAR -->
            <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05);">
                <h2 style="margin-top:0; font-size:16px;">🔗 Son Başvurular</h2>
                <?php
                $recent = get_posts(array(
                    'post_type' => 'gorilla_referral',
                    'post_status' => array('pending', 'grla_approved', 'grla_rejected'),
                    'numberposts' => 6,
                    'orderby' => 'date',
                    'order' => 'DESC',
                ));

                if (empty($recent)):
                    echo '<p style="color:#888; text-align:center; padding:30px;">Henüz başvuru yok.</p>';
                else:
                ?>
                <table class="widefat striped" style="font-size:12px;">
                    <thead><tr><th>Müşteri</th><th>Credit</th><th>Durum</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $ref):
                        $uid = get_post_meta($ref->ID, '_ref_user_id', true);
                        $user = get_userdata($uid);
                        $status = get_post_status($ref->ID);
                        $status_map = array('pending' => '⏳', 'grla_approved' => '✅', 'grla_rejected' => '❌');
                    ?>
                        <tr>
                            <td><?php echo $user ? esc_html($user->display_name) : '?'; ?></td>
                            <td><strong><?php echo wc_price(get_post_meta($ref->ID, '_ref_credit_amount', true)); ?></strong></td>
                            <td><?php echo $status_map[$status] ?? $status; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="text-align:right; margin-top:10px;">
                    <a href="<?php echo admin_url('edit.php?post_type=gorilla_referral'); ?>" class="button">Tümünü Gör →</a>
                </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ÜÇÜNCÜ SATIR: TOP MÜŞTERİLER & SON CREDİT HAREKETLERİ -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px;">
            <!-- TOP MÜŞTERİLER -->
            <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05);">
                <h2 style="margin-top:0; font-size:16px;">🏆 En Yüksek Harcayan Müşteriler</h2>
                <?php
                $top_customers = $stats['top_customers'] ?? array();
                if (!empty($top_customers)):
                ?>
                <table class="widefat striped" style="font-size:12px;">
                    <thead><tr><th>Müşteri</th><th>Harcama</th><th>Seviye</th></tr></thead>
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
                <p style="color:#888; text-align:center; padding:20px;">Henüz harcama verisi yok.</p>
                <?php endif; ?>
            </div>

            <!-- SON CREDİT HAREKETLERİ -->
            <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05);">
                <h2 style="margin-top:0; font-size:16px;">💳 Son Credit Hareketleri</h2>
                <?php
                $credit_table = $wpdb->prefix . 'gorilla_credit_log';
                $recent_credits = array();
                if (gorilla_lr_table_exists($credit_table)) {
                    $recent_credits = $wpdb->get_results(
                        "SELECT cl.*, u.display_name FROM {$credit_table} cl
                         LEFT JOIN {$wpdb->users} u ON cl.user_id = u.ID
                         ORDER BY cl.created_at DESC LIMIT 6"
                    );
                }
                if (!empty($recent_credits)):
                ?>
                <table class="widefat striped" style="font-size:11px;">
                    <thead><tr><th>Müşteri</th><th>Tutar</th><th>Tip</th><th>Tarih</th></tr></thead>
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
                <p style="color:#888; text-align:center; padding:20px;">Henüz credit hareketi yok.</p>
                <?php endif; ?>
                <p style="text-align:right; margin-top:10px;">
                    <a href="<?php echo admin_url('admin.php?page=gorilla-credit-manage'); ?>" class="button">💰 Credit Yönetimi</a>
                </p>
            </div>
        </div>

        <!-- DÖRDÜNCÜ SATIR: AFFİLİATE İSTATİSTİKLERİ -->
        <?php if ($affiliate_enabled): ?>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px;">
            <!-- AFFİLİATE GENEL STATS -->
            <div style="background:linear-gradient(135deg, #eff6ff, #dbeafe); padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05); border:2px solid #3b82f6;">
                <h2 style="margin-top:0; font-size:16px; color:#1e40af;">🔗 Affiliate Link Performansı</h2>
                <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:15px; margin-top:15px;">
                    <div style="text-align:center; background:#fff; padding:12px; border-radius:10px;">
                        <div style="font-size:24px; font-weight:800; color:#3b82f6;"><?php echo number_format_i18n($affiliate_stats['total_clicks'] ?? 0); ?></div>
                        <div style="font-size:11px; color:#6b7280;">Tıklama</div>
                    </div>
                    <div style="text-align:center; background:#fff; padding:12px; border-radius:10px;">
                        <div style="font-size:24px; font-weight:800; color:#22c55e;"><?php echo number_format_i18n($affiliate_stats['total_conversions'] ?? 0); ?></div>
                        <div style="font-size:11px; color:#6b7280;">Satış</div>
                    </div>
                    <div style="text-align:center; background:#fff; padding:12px; border-radius:10px;">
                        <div style="font-size:24px; font-weight:800; color:#f59e0b;"><?php echo round($affiliate_stats['conversion_rate'] ?? 0, 1); ?>%</div>
                        <div style="font-size:11px; color:#6b7280;">Dönüşüm</div>
                    </div>
                    <div style="text-align:center; background:#fff; padding:12px; border-radius:10px;">
                        <div style="font-size:24px; font-weight:800; color:#8b5cf6;"><?php echo wc_price($affiliate_stats['total_commission'] ?? 0); ?></div>
                        <div style="font-size:11px; color:#6b7280;">Komisyon</div>
                    </div>
                </div>
                <p style="font-size:12px; color:#6b7280; margin:15px 0 0; text-align:center;">
                    <?php echo number_format_i18n($affiliate_stats['active_affiliates'] ?? 0); ?> aktif affiliate kullanıcı
                </p>
            </div>

            <!-- TOP AFFİLİATELER -->
            <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05);">
                <h2 style="margin-top:0; font-size:16px;">🏅 Top Affiliate Kullanıcılar</h2>
                <?php
                $top_affiliates = function_exists('gorilla_affiliate_get_top_users') ? gorilla_affiliate_get_top_users(5) : array();
                if (!empty($top_affiliates)):
                ?>
                <table class="widefat striped" style="font-size:12px;">
                    <thead><tr><th>Kullanıcı</th><th>Satışlar</th><th style="text-align:right;">Kazanç</th></tr></thead>
                    <tbody>
                    <?php foreach ($top_affiliates as $ta): ?>
                        <tr>
                            <td><strong><?php echo esc_html($ta->display_name ?? 'ID:' . $ta->user_id); ?></strong></td>
                            <td><?php echo number_format_i18n($ta->total_orders); ?></td>
                            <td style="text-align:right; font-weight:600; color:#22c55e;"><?php echo wc_price($ta->total_earnings); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color:#888; text-align:center; padding:20px;">Henüz affiliate kazancı yok.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- BEŞİNCİ SATIR: XP & LEVEL İSTATİSTİKLERİ -->
        <?php if ($xp_enabled): ?>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px;">
            <!-- XP GENEL STATS -->
            <div style="background:linear-gradient(135deg, #f0fdf4, #dcfce7); padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05); border:2px solid #22c55e;">
                <h2 style="margin-top:0; font-size:16px; color:#166534;">🎮 XP Sistemi Özeti</h2>
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
                        <div style="font-size:11px; color:#6b7280;">XP'li Kullanıcı</div>
                    </div>
                </div>
            </div>

            <!-- LEVEL DAĞILIMI -->
            <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05);">
                <h2 style="margin-top:0; font-size:16px;">🏆 Level Dağılımı</h2>
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
                <p style="color:#888; text-align:center; padding:20px; font-size:13px;">Henüz XP kazanan kullanıcı yok.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- SON XP AKTİVİTELERİ -->
        <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05); margin-top:20px;">
            <h2 style="margin-top:0; font-size:16px;">📊 Son XP Aktiviteleri</h2>
            <?php
            $recent_xp = function_exists('gorilla_xp_get_recent_activity') ? gorilla_xp_get_recent_activity(8) : array();
            if (!empty($recent_xp)):
            ?>
            <table class="widefat striped" style="font-size:12px;">
                <thead><tr><th>Kullanıcı</th><th>XP</th><th>Sebep</th><th>Tarih</th></tr></thead>
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
            <p style="color:#888; text-align:center; padding:20px;">Henüz XP aktivitesi yok.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- FOOTER BİLGİ -->
        <div style="margin-top:25px; padding:15px 20px; background:#f1f5f9; border-radius:12px; font-size:12px; color:#64748b;">
            <strong>📡 REST API:</strong> <code>/wp-json/gorilla-lr/v1/</code> —
            <strong>📊 Son güncelleme:</strong> <?php echo wp_date('d.m.Y H:i'); ?> —
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
        'monthly_referrals' => array(),
        'top_customers' => array(),
    );

    // Toplam müşteri sayısı
    $user_counts = count_users();
    $stats['total_customers'] = intval($user_counts['avail_roles']['customer'] ?? 0);

    // Seviye dağılımı hesapla (sampling - performans için)
    $tiers = gorilla_get_tiers();
    $period = intval(get_option('gorilla_lr_period_months', 6));
    $date_from = gmdate('Y-m-d', strtotime("-{$period} months"));

    $tier_counts = array();
    foreach (array_keys($tiers) as $key) {
        $tier_counts[$key] = 0;
    }

    // Sample olarak ilk 500 müşteriyi kontrol et
    $customer_ids = get_users(array('role' => 'customer', 'fields' => 'ID', 'number' => 500));
    foreach ($customer_ids as $cid) {
        if (function_exists('gorilla_loyalty_calculate_tier')) {
            $tier = gorilla_loyalty_calculate_tier($cid);
            $key = $tier['key'] ?? 'none';
            if ($key !== 'none' && isset($tier_counts[$key])) {
                $tier_counts[$key]++;
            }
        }
    }
    $stats['tier_distribution'] = $tier_counts;
    $stats['tiered_customers'] = array_sum($tier_counts);

    // Aylık referans trendi (son 6 ay)
    $monthly = array();
    for ($i = 5; $i >= 0; $i--) {
        $month_start = gmdate('Y-m-01', strtotime("-{$i} months"));
        $month_end = gmdate('Y-m-t', strtotime("-{$i} months"));
        $month_label = wp_date('M', strtotime($month_start));

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('pending','grla_approved','grla_rejected') AND post_date BETWEEN %s AND %s",
            'gorilla_referral', $month_start . ' 00:00:00', $month_end . ' 23:59:59'
        ));

        $monthly[] = array('month' => $month_label, 'count' => intval($count));
    }
    $stats['monthly_referrals'] = $monthly;

    // Top müşteriler (harcamaya göre)
    $top = array();
    if (function_exists('gorilla_loyalty_calculate_tier')) {
        $customers_sample = get_users(array('role' => 'customer', 'fields' => 'ID', 'number' => 100));
        $spending_list = array();

        foreach ($customers_sample as $cid) {
            $tier = gorilla_loyalty_calculate_tier($cid);
            if (($tier['spending'] ?? 0) > 0) {
                $user = get_userdata($cid);
                $spending_list[] = array(
                    'id' => $cid,
                    'name' => $user ? $user->display_name : 'ID:' . $cid,
                    'spending' => floatval($tier['spending']),
                    'tier_label' => $tier['label'] ?? '',
                    'tier_emoji' => $tier['emoji'] ?? '',
                );
            }
        }

        // En yüksek harcayana göre sırala
        usort($spending_list, function($a, $b) {
            return $b['spending'] <=> $a['spending'];
        });

        $top = array_slice($spending_list, 0, 5);
    }
    $stats['top_customers'] = $top;

    return $stats;
}


// ── Manuel Credit Yönetimi Sayfası ───────────────────────
function gorilla_admin_credit_page() {
    if (!current_user_can('manage_woocommerce')) return;
    
    // Credit ekleme/çıkarma işlemi
    if (isset($_POST['gorilla_manual_credit']) && wp_verify_nonce($_POST['_gorilla_credit_nonce'] ?? '', 'gorilla_manual_credit')) {
        $target_user = intval($_POST['credit_user_id'] ?? 0);
        $amount = floatval($_POST['credit_amount'] ?? 0);
        $action = sanitize_text_field($_POST['credit_action'] ?? 'add');
        $reason = sanitize_text_field($_POST['credit_reason'] ?? 'Manuel düzenleme');
        
        if ($target_user && $amount > 0) {
            $actual_amount = ($action === 'remove') ? -$amount : $amount;
            $new_balance = gorilla_credit_adjust($target_user, $actual_amount, 'manual', $reason);
            
            $user = get_userdata($target_user);
            $name = $user ? $user->display_name : '#' . $target_user;
            echo '<div class="notice notice-success is-dismissible"><p>✅ <strong>' . esc_html($name) . '</strong> - ' . ($action === 'add' ? 'Eklendi' : 'Çıkarıldı') . ': ' . wc_price($amount) . '. Yeni bakiye: ' . wc_price($new_balance) . '</p></div>';
        }
    }
    
    // Tüm credit sahibi kullanıcıları listele
    global $wpdb;
    $users_with_credit = $wpdb->get_results(
        "SELECT u.ID, u.display_name, u.user_email, um.meta_value as credit 
         FROM {$wpdb->users} u 
         INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = '_gorilla_store_credit' 
         WHERE CAST(um.meta_value AS DECIMAL(10,2)) > 0 
         ORDER BY CAST(um.meta_value AS DECIMAL(10,2)) DESC 
         LIMIT 100"
    );
    ?>
    <div class="wrap">
        <h1>💰 Store Credit Yönetimi</h1>
        
        <!-- Manuel credit formu -->
        <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05); margin:20px 0; max-width:700px;">
            <h2 style="margin-top:0;">➕ Manuel Credit İşlemi</h2>
            <form method="post">
                <?php wp_nonce_field('gorilla_manual_credit', '_gorilla_credit_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th>Müşteri</th>
                        <td>
                            <?php
                            wp_dropdown_users(array(
                                'name'             => 'credit_user_id',
                                'show_option_none' => '— Müşteri Seçin —',
                                'role__in'         => array('customer', 'subscriber', 'administrator'),
                                'orderby'          => 'display_name',
                            ));
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>İşlem</th>
                        <td>
                            <label style="margin-right:20px;"><input type="radio" name="credit_action" value="add" checked> ➕ Credit Ekle</label>
                            <label><input type="radio" name="credit_action" value="remove"> ➖ Credit Çıkar</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Tutar (₺)</th>
                        <td><input type="number" name="credit_amount" min="0.01" step="0.01" style="width:150px;" required> ₺</td>
                    </tr>
                    <tr>
                        <th>Sebep</th>
                        <td><input type="text" name="credit_reason" value="Manuel düzenleme" style="width:100%;" placeholder="İşlem sebebi"></td>
                    </tr>
                </table>
                <p><button type="submit" name="gorilla_manual_credit" value="1" class="button button-primary">💾 Uygula</button></p>
            </form>
        </div>
        
        <!-- Mevcut bakiyeler -->
        <div style="background:#fff; padding:25px; border-radius:14px; box-shadow:0 1px 6px rgba(0,0,0,0.05); max-width:700px;">
            <h2 style="margin-top:0;">👥 Aktif Store Credit Bakiyeleri</h2>
            <?php if (empty($users_with_credit)): ?>
                <p style="color:#888; text-align:center; padding:20px;">Henüz store credit bakiyesi olan müşteri yok.</p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead><tr><th>Müşteri</th><th>E-posta</th><th style="text-align:right;">Bakiye</th></tr></thead>
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


// ── Kullanıcı Listesi Kolonları ──────────────────────────
add_filter('manage_users_columns', function($cols) {
    $cols['gorilla_tier']   = '🎖️ Seviye';
    $cols['gorilla_credit'] = '💰 Credit';
    $cols['gorilla_xp']     = '🎮 XP/Level';
    return $cols;
});

add_filter('manage_users_custom_column', function($val, $col, $uid) {
    if ($col === 'gorilla_tier') {
        $tier = gorilla_lr_get_user_tier($uid);
        return $tier['key'] !== 'none' ? $tier['emoji'] . ' ' . $tier['label'] : '<span style="color:#ccc;">—</span>';
    }
    if ($col === 'gorilla_credit') {
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


// ── Admin: Sipariş sayfasında seviye bilgisi ─────────────
add_action('woocommerce_admin_order_data_after_billing_address', function($order) {
    $user_id = $order->get_customer_id();
    if (!$user_id) return;

    $tier = gorilla_lr_get_user_tier($user_id);
    $credit = gorilla_credit_get_balance($user_id);

    echo '<div style="background:#f8fafc; padding:12px 16px; border-radius:8px; margin-top:12px; border:1px solid #e5e7eb;">';
    echo '<strong>🦍 Gorilla Loyalty</strong><br>';
    echo 'Seviye: ' . $tier['emoji'] . ' ' . $tier['label'];
    if ($tier['discount'] > 0) echo ' (%' . $tier['discount'] . ' indirim)';
    if ($credit > 0) echo '<br>Store Credit: ' . wc_price($credit);

    // XP/Level bilgisi
    if (function_exists('gorilla_xp_get_balance') && function_exists('gorilla_xp_calculate_level') && get_option('gorilla_lr_enabled_xp', 'yes') === 'yes') {
        $xp = gorilla_xp_get_balance($user_id);
        if ($xp > 0) {
            $level = gorilla_xp_calculate_level($user_id);
            $level_num = intval($level['number'] ?? 1);
            echo '<br>XP: <span style="color:' . esc_attr($level['color'] ?? '#999') . ';">' . esc_html($level['emoji'] ?? '🌱') . ' Level ' . $level_num . ' (' . esc_html($level['label'] ?? '') . ')</span> - ' . number_format_i18n($xp) . ' XP';
        }
    }
    echo '</div>';
});
