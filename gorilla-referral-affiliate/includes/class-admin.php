<?php
/**
 * Gorilla RA - Admin Dashboard & Yonetim
 *
 * @package Gorilla_Referral_Affiliate
 */

if (!defined('ABSPATH')) exit;

// -- Admin Menu --
add_action('admin_menu', function() {
    // Check if Loyalty plugin registered the parent menu
    $parent_slug = 'gorilla-loyalty-admin';

    // Fallback: if Loyalty not active, create own menu
    global $menu;
    $parent_exists = false;
    if (is_array($menu)) {
        foreach ($menu as $item) {
            if (isset($item[2]) && $item[2] === $parent_slug) {
                $parent_exists = true;
                break;
            }
        }
    }

    if (!$parent_exists) {
        // Create own top-level menu as fallback
        add_menu_page(
            __('Gorilla Referral', 'gorilla-ra'),
            __('Gorilla Referral', 'gorilla-ra'),
            'manage_woocommerce',
            'gorilla-ra-dashboard',
            'gorilla_ra_admin_dashboard_page',
            'dashicons-megaphone',
            57
        );
        $parent_slug = 'gorilla-ra-dashboard';
    }

    // Referral Dashboard submenu
    add_submenu_page(
        $parent_slug,
        __('Referral Dashboard', 'gorilla-ra'),
        "\xF0\x9F\x93\xA2 " . __('Referral Dashboard', 'gorilla-ra'),
        'manage_woocommerce',
        'gorilla-ra-dashboard',
        'gorilla_ra_admin_dashboard_page'
    );

    // Referral Settings submenu
    add_submenu_page(
        $parent_slug,
        __('Referral Ayarlari', 'gorilla-ra'),
        "\xF0\x9F\x94\xA7 " . __('Referral Ayarlari', 'gorilla-ra'),
        'manage_woocommerce',
        'gorilla-ra-settings',
        'gorilla_ra_settings_page_render'
    );
}, 30);

// -- Dashboard Sayfasi --
function gorilla_ra_admin_dashboard_page() {
    global $wpdb;

    $ref_counts = wp_count_posts('gorilla_referral');
    $pending = $ref_counts->pending ?? 0;
    $approved = $ref_counts->grla_approved ?? 0;
    $rejected = $ref_counts->grla_rejected ?? 0;

    $rate = get_option('gorilla_lr_referral_rate', 35);

    // Affiliate istatistikleri
    $affiliate_stats = function_exists('gorilla_affiliate_get_admin_stats') ? gorilla_affiliate_get_admin_stats() : array();
    $top_affiliates = function_exists('gorilla_affiliate_get_top_users') ? gorilla_affiliate_get_top_users(5) : array();
    $recent_affiliate_orders = function_exists('gorilla_affiliate_get_recent_orders') ? gorilla_affiliate_get_recent_orders(10) : array();

    ?>
    <div class="wrap">
        <h1>Gorilla Referral & Affiliate - Dashboard</h1>

        <!-- Referans Ozeti -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin:20px 0;">
            <div style="background:#fff; padding:24px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); text-align:center;">
                <div style="font-size:36px; font-weight:800; color:#f59e0b;"><?php echo intval($pending); ?></div>
                <div style="font-size:14px; color:#6b7280;">Bekleyen Basvuru</div>
            </div>
            <div style="background:#fff; padding:24px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); text-align:center;">
                <div style="font-size:36px; font-weight:800; color:#22c55e;"><?php echo intval($approved); ?></div>
                <div style="font-size:14px; color:#6b7280;">Onaylanan</div>
            </div>
            <div style="background:#fff; padding:24px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); text-align:center;">
                <div style="font-size:36px; font-weight:800; color:#ef4444;"><?php echo intval($rejected); ?></div>
                <div style="font-size:14px; color:#6b7280;">Reddedilen</div>
            </div>
            <div style="background:#fff; padding:24px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); text-align:center;">
                <div style="font-size:36px; font-weight:800; color:#3b82f6;">%<?php echo intval($rate); ?></div>
                <div style="font-size:14px; color:#6b7280;">Referans Orani</div>
            </div>
        </div>

        <?php if ($pending > 0): ?>
        <div style="background:#fef3c7; border:1px solid #f59e0b; padding:16px 20px; border-radius:12px; margin:20px 0;">
            <strong><?php echo intval($pending); ?></strong> adet bekleyen referans basvurusu var.
            <a href="<?php echo admin_url('edit.php?post_type=gorilla_referral&post_status=pending'); ?>" style="font-weight:700;">Basvurulari Incele &rarr;</a>
        </div>
        <?php endif; ?>

        <!-- Affiliate Istatistikleri -->
        <?php if (get_option('gorilla_lr_enabled_affiliate', 'yes') === 'yes'): ?>
        <h2 style="margin-top:30px;">Affiliate Istatistikleri</h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px; margin:20px 0;">
            <div style="background:#fff; padding:24px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); text-align:center;">
                <div style="font-size:36px; font-weight:800; color:#3b82f6;"><?php echo number_format_i18n(intval($affiliate_stats['total_clicks'] ?? 0)); ?></div>
                <div style="font-size:14px; color:#6b7280;">Toplam Tiklama</div>
            </div>
            <div style="background:#fff; padding:24px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); text-align:center;">
                <div style="font-size:36px; font-weight:800; color:#22c55e;"><?php echo number_format_i18n(intval($affiliate_stats['total_conversions'] ?? 0)); ?></div>
                <div style="font-size:14px; color:#6b7280;">Donusum</div>
            </div>
            <div style="background:#fff; padding:24px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); text-align:center;">
                <div style="font-size:36px; font-weight:800; color:#8b5cf6;">%<?php echo floatval($affiliate_stats['conversion_rate'] ?? 0); ?></div>
                <div style="font-size:14px; color:#6b7280;">Donusum Orani</div>
            </div>
            <div style="background:#fff; padding:24px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); text-align:center;">
                <div style="font-size:36px; font-weight:800; color:#f59e0b;"><?php echo wc_price(floatval($affiliate_stats['total_commission'] ?? 0)); ?></div>
                <div style="font-size:14px; color:#6b7280;">Toplam Komisyon</div>
            </div>
            <div style="background:#fff; padding:24px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); text-align:center;">
                <div style="font-size:36px; font-weight:800; color:#06b6d4;"><?php echo number_format_i18n(intval($affiliate_stats['active_affiliates'] ?? 0)); ?></div>
                <div style="font-size:14px; color:#6b7280;">Aktif Affiliate</div>
            </div>
        </div>

        <!-- Top Affiliates -->
        <?php if (!empty($top_affiliates)): ?>
        <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
            <h3 style="margin-top:0;">En Basarili Affiliate Kullanicilar</h3>
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="background:#f9fafb;">
                        <th style="padding:10px 14px; text-align:left;">#</th>
                        <th style="padding:10px 14px; text-align:left;">Kullanici</th>
                        <th style="padding:10px 14px; text-align:right;">Siparis</th>
                        <th style="padding:10px 14px; text-align:right;">Toplam Kazanc</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($top_affiliates as $i => $aff): ?>
                    <tr style="border-top:1px solid #f0f0f0;">
                        <td style="padding:10px 14px;"><?php echo $i + 1; ?></td>
                        <td style="padding:10px 14px;">
                            <strong><?php echo esc_html($aff->display_name ?? ''); ?></strong>
                            <br><span style="color:#888; font-size:12px;"><?php echo esc_html($aff->user_email ?? ''); ?></span>
                        </td>
                        <td style="padding:10px 14px; text-align:right;"><?php echo intval($aff->total_orders ?? 0); ?></td>
                        <td style="padding:10px 14px; text-align:right; font-weight:600; color:#22c55e;"><?php echo wc_price(floatval($aff->total_earnings ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Recent Affiliate Orders -->
        <?php if (!empty($recent_affiliate_orders)): ?>
        <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
            <h3 style="margin-top:0;">Son Affiliate Siparisleri</h3>
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="background:#f9fafb;">
                        <th style="padding:10px 14px; text-align:left;">Tarih</th>
                        <th style="padding:10px 14px; text-align:left;">Affiliate</th>
                        <th style="padding:10px 14px; text-align:left;">Aciklama</th>
                        <th style="padding:10px 14px; text-align:right;">Komisyon</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent_affiliate_orders as $entry): ?>
                    <tr style="border-top:1px solid #f0f0f0;">
                        <td style="padding:10px 14px;"><?php echo esc_html(wp_date('d.m.Y H:i', strtotime($entry->created_at))); ?></td>
                        <td style="padding:10px 14px;"><?php echo esc_html($entry->referrer_name ?? ''); ?></td>
                        <td style="padding:10px 14px;"><?php echo esc_html($entry->reason ?? ''); ?></td>
                        <td style="padding:10px 14px; text-align:right; font-weight:600; color:#22c55e;"><?php echo wc_price(floatval($entry->amount ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
