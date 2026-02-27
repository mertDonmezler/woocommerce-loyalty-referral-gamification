<?php
/**
 * Gorilla RA - Ayarlar Modulu
 * Referral & Affiliate settings save handler
 *
 * @package Gorilla_Referral_Affiliate
 */

if (!defined('ABSPATH')) exit;

// -- Ayar Kaydetme --
add_action('admin_init', function() {
    if (!isset($_POST['gorilla_ra_save_settings']) || !current_user_can('manage_woocommerce')) return;
    if (!wp_verify_nonce($_POST['_gorilla_ra_settings_nonce'] ?? '', 'gorilla_ra_save_settings')) return;

    $validate_yesno = function($val) { return in_array($val, array('yes', 'no'), true) ? $val : 'no'; };

    // Referral ayarlari
    update_option('gorilla_lr_enabled_referral', $validate_yesno($_POST['enabled_referral'] ?? 'no'));
    update_option('gorilla_lr_referral_rate', max(1, min(100, intval($_POST['referral_rate'] ?? 35))));

    // Affiliate ayarlari
    update_option('gorilla_lr_enabled_affiliate', $validate_yesno($_POST['enabled_affiliate'] ?? 'no'));
    update_option('gorilla_lr_affiliate_rate', max(1, min(100, intval($_POST['affiliate_rate'] ?? 10))));
    update_option('gorilla_lr_affiliate_cookie_days', max(1, min(365, intval($_POST['affiliate_cookie_days'] ?? 30))));
    update_option('gorilla_lr_affiliate_min_order', max(0, floatval($_POST['affiliate_min_order'] ?? 0)));
    update_option('gorilla_lr_affiliate_first_only', $validate_yesno($_POST['affiliate_first_only'] ?? 'no'));
    update_option('gorilla_lr_affiliate_allow_self', $validate_yesno($_POST['affiliate_allow_self'] ?? 'no'));
    update_option('gorilla_lr_fraud_detection_enabled', $validate_yesno($_POST['fraud_detection_enabled'] ?? 'no'));

    // Dual Referral
    update_option('gorilla_lr_dual_referral_enabled', $validate_yesno($_POST['dual_referral_enabled'] ?? 'no'));
    update_option('gorilla_lr_dual_referral_type', in_array($_POST['dual_referral_type'] ?? '', array('percent', 'fixed_cart')) ? $_POST['dual_referral_type'] : 'percent');
    update_option('gorilla_lr_dual_referral_amount', max(0, min(100, floatval($_POST['dual_referral_amount'] ?? 10))));
    update_option('gorilla_lr_dual_referral_min_order', max(0, floatval($_POST['dual_referral_min_order'] ?? 0)));
    update_option('gorilla_lr_dual_referral_expiry_days', max(1, min(365, intval($_POST['dual_referral_expiry_days'] ?? 30))));

    // Tiered Affiliate
    update_option('gorilla_lr_tiered_affiliate_enabled', $validate_yesno($_POST['tiered_affiliate_enabled'] ?? 'no'));

    // Tiered Affiliate Tiers
    $tier_names = $_POST['tier_name'] ?? array();
    $tier_mins  = $_POST['tier_min_sales'] ?? array();
    $tier_rates = $_POST['tier_rate'] ?? array();
    $tiers = array();
    if (is_array($tier_names)) {
        foreach ($tier_names as $i => $name) {
            $name = sanitize_text_field($name);
            $min_sales = max(0, intval($tier_mins[$i] ?? 0));
            $rate = max(1, min(100, floatval($tier_rates[$i] ?? 10)));
            if (!empty($name)) {
                $tiers[] = array('name' => $name, 'min_sales' => $min_sales, 'rate' => $rate);
            }
        }
    }
    // Sort tiers by min_sales ascending
    usort($tiers, function($a, $b) { return $a['min_sales'] - $b['min_sales']; });
    update_option('gorilla_lr_affiliate_tiers', $tiers);

    // Recurring Affiliate
    update_option('gorilla_lr_recurring_affiliate_enabled', $validate_yesno($_POST['recurring_affiliate_enabled'] ?? 'no'));
    update_option('gorilla_lr_recurring_affiliate_rate', max(1, min(50, floatval($_POST['recurring_affiliate_rate'] ?? 5))));
    update_option('gorilla_lr_recurring_affiliate_months', max(1, min(24, intval($_POST['recurring_affiliate_months'] ?? 6))));
    update_option('gorilla_lr_recurring_affiliate_max_orders', max(0, min(100, intval($_POST['recurring_affiliate_max_orders'] ?? 0))));

    add_settings_error('gorilla_ra_settings', 'saved', 'Ayarlar basariyla kaydedildi!', 'updated');
});

// -- Ayarlar Sayfasi Render --
function gorilla_ra_settings_page_render() {
    if (!current_user_can('manage_woocommerce')) return;

    $enabled_referral = get_option('gorilla_lr_enabled_referral', 'yes');
    $ref_rate         = get_option('gorilla_lr_referral_rate', 35);

    $enabled_affiliate   = get_option('gorilla_lr_enabled_affiliate', 'yes');
    $affiliate_rate      = get_option('gorilla_lr_affiliate_rate', 10);
    $affiliate_cookie    = get_option('gorilla_lr_affiliate_cookie_days', 30);
    $affiliate_min       = get_option('gorilla_lr_affiliate_min_order', 0);
    $affiliate_first     = get_option('gorilla_lr_affiliate_first_only', 'no');
    $affiliate_self      = get_option('gorilla_lr_affiliate_allow_self', 'no');

    settings_errors('gorilla_ra_settings');
    ?>
    <div class="wrap">
        <h1>Gorilla Referral & Affiliate - Ayarlar</h1>

        <form method="post" action="">
            <?php wp_nonce_field('gorilla_ra_save_settings', '_gorilla_ra_settings_nonce'); ?>

            <!-- REFERRAL AYARLARI -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">Referans Programi</h2>
                <table class="form-table">
                    <tr>
                        <th>Referans Programi</th>
                        <td>
                            <label><input type="checkbox" name="enabled_referral" value="yes" <?php checked($enabled_referral, 'yes'); ?>> Aktif</label>
                            <p class="description">Devre disi birakirsaniz musteriler referans basvurusu yapamaz.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Referans Komisyon Orani</th>
                        <td>
                            <input type="number" name="referral_rate" value="<?php echo esc_attr($ref_rate); ?>" min="1" max="100" style="width:80px;"> <strong>%</strong>
                            <p class="description">Musterinin video basvurusu onaylandiginda siparis tutarinin yuzde kaci store credit olarak verilecek.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- AFFILIATE AYARLARI -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">Affiliate Link Ayarlari</h2>
                <p style="color:#666; margin-bottom:20px;">Musteriler kendi referans linklerini paylasarak, linke tiklayan kisilerin alisverislerinden komisyon kazanir.</p>
                <table class="form-table">
                    <tr>
                        <th>Affiliate Sistemi</th>
                        <td>
                            <label><input type="checkbox" name="enabled_affiliate" value="yes" <?php checked($enabled_affiliate, 'yes'); ?>> Aktif</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Affiliate Komisyon Orani</th>
                        <td>
                            <input type="number" name="affiliate_rate" value="<?php echo esc_attr($affiliate_rate); ?>" min="1" max="100" style="width:80px;"> <strong>%</strong>
                        </td>
                    </tr>
                    <tr>
                        <th>Cookie Suresi</th>
                        <td>
                            <input type="number" name="affiliate_cookie_days" value="<?php echo esc_attr($affiliate_cookie); ?>" min="1" max="365" style="width:80px;"> gun
                        </td>
                    </tr>
                    <tr>
                        <th>Minimum Siparis Tutari</th>
                        <td>
                            <input type="number" name="affiliate_min_order" value="<?php echo esc_attr($affiliate_min); ?>" min="0" step="0.01" style="width:120px;">
                        </td>
                    </tr>
                    <tr>
                        <th>Sadece Ilk Siparis</th>
                        <td>
                            <label><input type="checkbox" name="affiliate_first_only" value="yes" <?php checked($affiliate_first, 'yes'); ?>> Sadece yeni musteriler icin komisyon</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Self-Referral</th>
                        <td>
                            <label><input type="checkbox" name="affiliate_allow_self" value="yes" <?php checked($affiliate_self, 'yes'); ?>> Kendi linkinden alisverise izin ver</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Dolandiricilik Tespiti</th>
                        <td>
                            <label><input type="checkbox" name="fraud_detection_enabled" value="yes" <?php checked(get_option('gorilla_lr_fraud_detection_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Haftalik otomatik tarama ile supheli affiliate aktivitelerini tespit eder.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- DUAL REFERRAL -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">Cift Tarafli Referans</h2>
                <p style="color:#666; margin-bottom:20px;">Referans onaylandiginda hem referans yapan hem de alisveris yapan musteri odul kazanir.</p>
                <table class="form-table">
                    <tr>
                        <th>Cift Tarafli Referans</th>
                        <td>
                            <label><input type="checkbox" name="dual_referral_enabled" value="yes" <?php checked(get_option('gorilla_lr_dual_referral_enabled', 'no'), 'yes'); ?>> Aktif</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Kupon Tipi</th>
                        <td>
                            <select name="dual_referral_type">
                                <option value="percent" <?php selected(get_option('gorilla_lr_dual_referral_type', 'percent'), 'percent'); ?>>Yuzde Indirim</option>
                                <option value="fixed_cart" <?php selected(get_option('gorilla_lr_dual_referral_type', 'percent'), 'fixed_cart'); ?>>Sabit Tutar</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Kupon Degeri</th>
                        <td>
                            <input type="number" name="dual_referral_amount" value="<?php echo esc_attr(get_option('gorilla_lr_dual_referral_amount', 10)); ?>" min="0" max="100" step="0.01" style="width:100px;">
                        </td>
                    </tr>
                    <tr>
                        <th>Minimum Siparis</th>
                        <td>
                            <input type="number" name="dual_referral_min_order" value="<?php echo esc_attr(get_option('gorilla_lr_dual_referral_min_order', 0)); ?>" min="0" step="0.01" style="width:120px;">
                        </td>
                    </tr>
                    <tr>
                        <th>Kupon Gecerlilik</th>
                        <td>
                            <input type="number" name="dual_referral_expiry_days" value="<?php echo esc_attr(get_option('gorilla_lr_dual_referral_expiry_days', 30)); ?>" min="1" max="365" style="width:80px;"> gun
                        </td>
                    </tr>
                </table>
            </div>

            <!-- TIERED AFFILIATE -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">Kademeli Affiliate Komisyonu</h2>
                <table class="form-table">
                    <tr>
                        <th>Kademeli Komisyon</th>
                        <td>
                            <label><input type="checkbox" name="tiered_affiliate_enabled" value="yes" <?php checked(get_option('gorilla_lr_tiered_affiliate_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Affiliate kullanicilari satis sayilarina gore artan komisyon orani kazanir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Kademe Tanimlari</th>
                        <td>
                            <div id="gorilla-tier-rows">
                                <?php
                                $tiers = get_option('gorilla_lr_affiliate_tiers', array());
                                if (!empty($tiers) && is_array($tiers)):
                                    foreach ($tiers as $idx => $tier):
                                ?>
                                <div class="gorilla-tier-row" style="display:flex; gap:10px; align-items:center; margin-bottom:8px;">
                                    <input type="text" name="tier_name[]" value="<?php echo esc_attr($tier['name'] ?? ''); ?>" placeholder="Kademe Adi" style="width:150px;">
                                    <label style="font-size:12px; color:#888;">Min Satis:</label>
                                    <input type="number" name="tier_min_sales[]" value="<?php echo esc_attr($tier['min_sales'] ?? 0); ?>" min="0" style="width:80px;">
                                    <label style="font-size:12px; color:#888;">Komisyon %:</label>
                                    <input type="number" name="tier_rate[]" value="<?php echo esc_attr($tier['rate'] ?? 10); ?>" min="1" max="100" step="0.1" style="width:80px;">
                                    <button type="button" class="button gorilla-remove-tier" style="color:#ef4444;">&times;</button>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                            <button type="button" id="gorilla-add-tier" class="button" style="margin-top:6px;">+ Kademe Ekle</button>
                            <p class="description" style="margin-top:8px;">Her kademe icin bir ad, minimum satis sayisi ve komisyon orani belirleyin. Kademeler min satis sayisina gore otomatik siralanir.</p>
                            <script>
                            (function(){
                                var container = document.getElementById('gorilla-tier-rows');
                                var addBtn = document.getElementById('gorilla-add-tier');
                                if (!addBtn || !container) return;

                                addBtn.addEventListener('click', function() {
                                    var row = document.createElement('div');
                                    row.className = 'gorilla-tier-row';
                                    row.style.cssText = 'display:flex; gap:10px; align-items:center; margin-bottom:8px;';
                                    row.innerHTML = '<input type="text" name="tier_name[]" value="" placeholder="Kademe Adi" style="width:150px;">' +
                                        '<label style="font-size:12px; color:#888;">Min Satis:</label>' +
                                        '<input type="number" name="tier_min_sales[]" value="0" min="0" style="width:80px;">' +
                                        '<label style="font-size:12px; color:#888;">Komisyon %:</label>' +
                                        '<input type="number" name="tier_rate[]" value="10" min="1" max="100" step="0.1" style="width:80px;">' +
                                        '<button type="button" class="button gorilla-remove-tier" style="color:#ef4444;">&times;</button>';
                                    container.appendChild(row);
                                });

                                container.addEventListener('click', function(e) {
                                    if (e.target.classList.contains('gorilla-remove-tier')) {
                                        e.target.closest('.gorilla-tier-row').remove();
                                    }
                                });
                            })();
                            </script>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- RECURRING AFFILIATE -->
            <div style="background:#fff; padding:25px 30px; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); margin:20px 0; max-width:900px;">
                <h2 style="margin-top:0; border-bottom:2px solid #f0f0f0; padding-bottom:12px;">Tekrar Eden Affiliate Komisyonu</h2>
                <table class="form-table">
                    <tr>
                        <th>Tekrar Eden Komisyon</th>
                        <td>
                            <label><input type="checkbox" name="recurring_affiliate_enabled" value="yes" <?php checked(get_option('gorilla_lr_recurring_affiliate_enabled', 'no'), 'yes'); ?>> Aktif</label>
                            <p class="description">Affiliate tarafindan yonlendirilen musteri sonraki siparislerinde de komisyon odemesi yapar.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Komisyon Orani</th>
                        <td>
                            <input type="number" name="recurring_affiliate_rate" value="<?php echo esc_attr(get_option('gorilla_lr_recurring_affiliate_rate', 5)); ?>" min="1" max="50" step="0.1" style="width:80px;"> <strong>%</strong>
                        </td>
                    </tr>
                    <tr>
                        <th>Sure Limiti</th>
                        <td>
                            <input type="number" name="recurring_affiliate_months" value="<?php echo esc_attr(get_option('gorilla_lr_recurring_affiliate_months', 6)); ?>" min="1" max="24" style="width:80px;"> ay
                        </td>
                    </tr>
                    <tr>
                        <th>Maksimum Siparis Sayisi</th>
                        <td>
                            <input type="number" name="recurring_affiliate_max_orders" value="<?php echo esc_attr(get_option('gorilla_lr_recurring_affiliate_max_orders', 0)); ?>" min="0" max="100" style="width:80px;">
                            <p class="description">0 = sinirsiz</p>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit">
                <button type="submit" name="gorilla_ra_save_settings" value="1" class="button button-primary button-large">Ayarlari Kaydet</button>
            </p>
        </form>
    </div>
    <?php
}
