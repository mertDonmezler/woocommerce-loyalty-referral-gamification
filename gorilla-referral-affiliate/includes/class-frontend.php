<?php
/**
 * Gorilla RA - Frontend (My Account Endpoint)
 * Referans programi sayfasi, affiliate link, QR, video basvuru, gecmis
 *
 * @package Gorilla_Referral_Affiliate
 */

if (!defined('ABSPATH')) exit;

// -- Custom Referral Slug AJAX --
add_action('wp_ajax_gorilla_update_ref_slug', function() {
    check_ajax_referer('gorilla_ref_slug', 'nonce');

    $user_id = get_current_user_id();
    if (!$user_id) wp_send_json_error(array('message' => 'Giris yapmaniz gerekiyor.'));

    // Rate limit: max 5 slug changes per hour per user
    $rate_key = 'gorilla_slug_rate_' . $user_id;
    $count = intval(get_transient($rate_key));
    if ($count >= 5) {
        wp_send_json_error(array('message' => 'Cok fazla degisiklik. Lutfen 1 saat bekleyin.'));
        return;
    }
    set_transient($rate_key, $count + 1, HOUR_IN_SECONDS);

    $slug = sanitize_text_field($_POST['slug'] ?? '');
    $slug = strtolower(trim($slug));

    // Validate format: 3-20 chars, alphanumeric + hyphens, no leading/trailing hyphens
    if (empty($slug) || !preg_match('/^[a-z0-9][a-z0-9\-]{1,18}[a-z0-9]$/', $slug)) {
        wp_send_json_error(array('message' => 'Kod 3-20 karakter, sadece kucuk harf, rakam ve tire (-) icermelidir. Tire ile baslayamaz/bitemez.'));
    }

    // Reserved slugs
    $reserved = array('admin', 'shop', 'cart', 'checkout', 'ref', 'affiliate', 'gorilla', 'test', 'api');
    if (in_array($slug, $reserved, true)) {
        wp_send_json_error(array('message' => 'Bu kod kullanilamaz (rezerve edilmis).'));
    }

    // Check uniqueness (case-insensitive)
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_gorilla_affiliate_code' AND LOWER(meta_value) = %s AND user_id != %d LIMIT 1",
        $slug, $user_id
    ));

    if ($exists) {
        wp_send_json_error(array('message' => 'Bu kod baskasi tarafindan kullaniliyor. Farkli bir kod deneyin.'));
    }

    // Update code
    update_user_meta($user_id, '_gorilla_affiliate_code', $slug);
    wp_cache_delete($user_id, 'user_meta');

    // Return new link
    $param = get_option('gorilla_lr_affiliate_url_param', 'ref');
    $link = add_query_arg($param, $slug, home_url('/'));

    wp_send_json_success(array(
        'message' => 'Referans kodunuz guncellendi!',
        'code'    => $slug,
        'link'    => $link,
    ));
});

// -- My Account Endpoint Registration --
add_action('init', function() {
    add_rewrite_endpoint('gorilla-referral', EP_PAGES);
});

// WooCommerce query vars - CRITICAL for endpoint to work
add_filter('woocommerce_get_query_vars', function($vars) {
    $vars['gorilla-referral'] = 'gorilla-referral';
    return $vars;
});

// -- My Account Menu Item --
add_filter('woocommerce_account_menu_items', function($items) {
    try {
        if (!is_array($items)) return $items;

        $new = array();
        foreach ($items as $key => $label) {
            $new[$key] = $label;
            if ($key === 'orders') {
                if (get_option('gorilla_lr_enabled_referral') === 'yes') {
                    $new['gorilla-referral'] = 'Referans Programi';
                }
            }
        }
        return $new;
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla RA menu error: ' . $e->getMessage());
        }
        return $items;
    }
});

// -- Inline Styles Helper --
function gorilla_ra_frontend_styles() {
    // Styles are loaded via referral.css enqueued in main plugin file
    // This function kept for backward compatibility
}

// -- Referral Endpoint Page Rendering --
add_action('woocommerce_account_gorilla-referral_endpoint', function() {
    try {
        if (!function_exists('gorilla_credit_get_balance') || !function_exists('gorilla_referral_process_submission')) {
            echo '<p>Referans programi su anda kullanilamiyor.</p>';
            return;
        }

        $user_id = get_current_user_id();
        $credit = gorilla_credit_get_balance($user_id);
        $rate = intval(get_option('gorilla_lr_referral_rate', 35));

        // Form gonderimi
        $result = gorilla_referral_process_submission();

        // Mevcut basvurular
        $submissions = function_exists('gorilla_referral_get_user_submissions') ? gorilla_referral_get_user_submissions($user_id) : array();
        $submitted_order_ids = is_array($submissions) ? array_column($submissions, 'order_id') : array();

        // Uygun siparisler
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

        gorilla_ra_frontend_styles();
        ?>
        <div class="glr-wrap">
            <h2 style="font-size:24px; font-weight:800; margin-bottom:20px;">Referans Programi</h2>

            <?php if ($result && isset($result['success']) && $result['success']): ?>
                <div class="woocommerce-message" style="border-top-color:#22c55e;">
                    Basvurunuz alindi! Incelendikten sonra <strong><?php echo wc_price($result['credit_amount'] ?? 0); ?></strong> store credit hesabiniza eklenecektir.
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
                <div style="color:#4ade80; font-size:13px;">Bir sonraki alisverisinizde checkout'ta kullanabilirsiniz</div>
            </div>

            <?php
            // === AFFILIATE LINK BOLUMU ===
            if (get_option('gorilla_lr_enabled_affiliate', 'yes') === 'yes' && function_exists('gorilla_affiliate_get_user_stats')):
                $affiliate_stats = gorilla_affiliate_get_user_stats($user_id);
                $affiliate_rate = intval(get_option('gorilla_lr_affiliate_rate', 10));
                $recent_earnings = function_exists('gorilla_affiliate_get_recent_earnings') ? gorilla_affiliate_get_recent_earnings($user_id, 5) : array();
            ?>
            <!-- Affiliate Link Karti -->
            <div class="glr-card" style="background:linear-gradient(135deg, #eff6ff, #dbeafe); border:2px solid #3b82f6;">
                <h3 style="margin-top:0; font-size:16px; color:#1e40af;">Affiliate Linkiniz</h3>
                <p style="font-size:13px; color:#3b82f6; margin-bottom:12px;">Bu linki paylasin, arkadaslariniz alisveris yaptiginda <strong>%<?php echo $affiliate_rate; ?> komisyon</strong> kazanin!</p>

                <div style="display:flex; gap:10px; margin-bottom:16px;">
                    <input type="text" id="gorilla-affiliate-link" value="<?php echo esc_attr($affiliate_stats['link']); ?>" readonly
                           style="flex:1; padding:12px 14px; border:1px solid #93c5fd; border-radius:8px; font-size:14px; background:#fff; color:#1e3a8a;">
                    <button type="button" id="gorilla-copy-affiliate"
                            style="background:#3b82f6; color:#fff; border:none; padding:12px 20px; border-radius:8px; font-weight:600; cursor:pointer; white-space:nowrap;">
                        Kopyala
                    </button>
                </div>

                <div style="font-size:12px; color:#6b7280; margin-bottom:12px;">
                    Referans Kodunuz: <code id="glr-ref-code-display" style="background:#e0e7ff; padding:2px 8px; border-radius:4px; font-weight:600; color:#4338ca;"><?php echo esc_html($affiliate_stats['code']); ?></code>
                </div>

                <!-- Custom Slug -->
                <div style="border-top:1px solid #bfdbfe; padding-top:12px;">
                    <div style="font-size:13px; color:#1e40af; font-weight:600; margin-bottom:6px;">Ozel Referans Kodu Belirle</div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="text" id="glr-ref-slug" placeholder="ornek: mert" value="" maxlength="20" pattern="[a-z0-9\-]+"
                               style="flex:1; max-width:200px; padding:8px 12px; border:1px solid #93c5fd; border-radius:6px; font-size:13px; font-family:monospace;">
                        <button type="button" id="glr-ref-slug-btn" style="background:#4338ca; color:#fff; border:none; padding:8px 16px; border-radius:6px; font-weight:600; cursor:pointer; font-size:13px;">Kaydet</button>
                    </div>
                    <div id="glr-ref-slug-msg" style="margin-top:6px; font-size:12px; display:none;"></div>
                    <p style="margin:4px 0 0; font-size:11px; color:#9ca3af;">3-20 karakter, kucuk harf, rakam ve tire. Ornek: mert, super-ref, kod123</p>
                </div>
                <script>
                (function(){
                    var btn = document.getElementById('glr-ref-slug-btn');
                    if (!btn) return;
                    btn.addEventListener('click', function() {
                        var slug = document.getElementById('glr-ref-slug').value.trim().toLowerCase();
                        var msgEl = document.getElementById('glr-ref-slug-msg');
                        if (!slug) { msgEl.style.display='block'; msgEl.style.color='#dc2626'; msgEl.textContent='Bir kod girin.'; return; }

                        btn.disabled = true; btn.textContent = 'Kaydediliyor...';
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', '<?php echo esc_url(admin_url('admin-ajax.php')); ?>', true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onload = function() {
                            btn.disabled = false; btn.textContent = 'Kaydet';
                            try {
                                var res = JSON.parse(xhr.responseText);
                                msgEl.style.display = 'block';
                                if (res.success) {
                                    msgEl.style.color = '#16a34a';
                                    msgEl.textContent = res.data.message;
                                    document.getElementById('glr-ref-code-display').textContent = res.data.code;
                                    document.getElementById('gorilla-affiliate-link').value = res.data.link;
                                    document.getElementById('glr-ref-slug').value = '';
                                } else {
                                    msgEl.style.color = '#dc2626';
                                    msgEl.textContent = res.data.message || 'Hata olustu.';
                                }
                            } catch(e) { msgEl.style.display='block'; msgEl.style.color='#dc2626'; msgEl.textContent='Bir hata olustu.'; }
                        };
                        xhr.send('action=gorilla_update_ref_slug&nonce=<?php echo wp_create_nonce('gorilla_ref_slug'); ?>&slug=' + encodeURIComponent(slug));
                    });
                })();
                </script>
            </div>

            <!-- Affiliate Istatistikleri -->
            <div class="glr-card">
                <h3 style="margin-top:0; font-size:16px;">Affiliate Istatistikleriniz</h3>
                <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; text-align:center;">
                    <div style="background:#f0fdf4; padding:16px; border-radius:10px;">
                        <div style="font-size:28px; font-weight:800; color:#22c55e;"><?php echo number_format_i18n($affiliate_stats['clicks']); ?></div>
                        <div style="font-size:12px; color:#6b7280;">Tiklama</div>
                    </div>
                    <div style="background:#fef3c7; padding:16px; border-radius:10px;">
                        <div style="font-size:28px; font-weight:800; color:#f59e0b;"><?php echo number_format_i18n($affiliate_stats['conversions']); ?></div>
                        <div style="font-size:12px; color:#6b7280;">Satis</div>
                    </div>
                    <div style="background:#eff6ff; padding:16px; border-radius:10px;">
                        <div style="font-size:28px; font-weight:800; color:#3b82f6;"><?php echo wc_price($affiliate_stats['earnings']); ?></div>
                        <div style="font-size:12px; color:#6b7280;">Toplam Kazanc</div>
                    </div>
                </div>

                <?php if (!empty($recent_earnings)): ?>
                <h4 style="margin:20px 0 10px; font-size:14px; color:#6b7280;">Son Kazanclar</h4>
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
            // === QR KOD ===
            if (get_option('gorilla_lr_qr_enabled', 'no') === 'yes' && function_exists('gorilla_qr_get_url')):
                $qr_url = gorilla_qr_get_url($user_id);
                $qr_download_nonce = wp_create_nonce('gorilla_qr_download');
            ?>
            <div class="glr-card" style="text-align:center; margin-top:16px;">
                <h3 style="margin-top:0; font-size:16px;">QR Kodunuz</h3>
                <p style="font-size:13px; color:#6b7280;">Bu QR kodu paylasarak yeni musteriler kazandirin.</p>
                <?php if (!empty($qr_url)): ?>
                <div style="margin:16px 0;">
                    <img src="<?php echo esc_url($qr_url); ?>" alt="QR Kod" style="max-width:200px; border:2px solid #e5e7eb; border-radius:12px; padding:8px; background:#fff;" onerror="this.style.display='none';this.insertAdjacentHTML('afterend','<p style=\'color:#999;font-size:13px;\'>QR kod yuklenemedi.</p>');">
                </div>
                <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=gorilla_download_qr&nonce=' . $qr_download_nonce)); ?>"
                   class="glr-btn" style="display:inline-block; width:auto; padding:10px 24px; font-size:14px;">
                    QR Kodu Indir
                </a>
                <?php else: ?>
                <p style="color:#9ca3af;">QR kod olusturulamadi.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php
            // === AFFILIATE KADEME GOSTERIMI ===
            if (function_exists('gorilla_affiliate_get_current_tier') && get_option('gorilla_lr_tiered_affiliate_enabled', 'no') === 'yes'):
                $aff_tier = gorilla_affiliate_get_current_tier($user_id);
            ?>
            <?php if (!empty($aff_tier) && is_array($aff_tier)): ?>
            <div class="glr-card" style="margin-top:16px;">
                <h3 style="margin-top:0; font-size:16px;">Affiliate Kademeniz</h3>
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
                    $tier_min = intval($aff_tier['tier_min'] ?? 0);
                    $next_min = intval($aff_tier['next_min'] ?? 0);
                    if ($next_min > $tier_min) {
                        $aff_progress = min(100, max(0, round(($aff_current_sales - $tier_min) / ($next_min - $tier_min) * 100)));
                    }
                    $aff_next_sales = $next_min;
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
            <h3 style="font-size:18px; margin-bottom:16px;">Video Icerik Programi</h3>

            <!-- Nasil Calisir -->
            <div class="glr-card" style="background:#fffbeb; border:1px solid #fbbf24;">
                <h3 style="margin-top:0; font-size:16px;">Nasil Calisir?</h3>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px; margin-top:12px;">
                    <?php
                    $steps = array(
                        array('1.', 'Siparis Ver', 'Gorilla\'dan siparis verin ve urunlerinizi teslim alin.'),
                        array('2.', 'Video Cek', 'Urunlerinizi tanitan veya actiginiz bir video hazirlayin.'),
                        array('3.', 'Paylas', 'Videoyu istediginiz sosyal medya platformuna yukleyin.'),
                        array('4.', 'Basvur', 'Video linkini asagidaki formdan bize gonderin.'),
                        array('5.', 'Kazan!', 'Onaydan sonra siparis tutarinin %' . $rate . '\'i credit olarak hesabiniza eklenir!'),
                    );
                    foreach ($steps as $i => $s):
                    ?>
                    <div style="text-align:center; padding:12px;">
                        <div style="font-size:32px; font-weight:800;"><?php echo $s[0]; ?></div>
                        <div style="font-weight:700; margin:4px 0;"><?php echo $s[1]; ?></div>
                        <div style="font-size:12px; color:#92400e;"><?php echo $s[2]; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Basvuru Formu -->
            <?php if (!empty($eligible) && get_option('gorilla_lr_enabled_referral') === 'yes'): ?>
            <div class="glr-card">
                <h3 style="margin-top:0; font-size:16px;">Yeni Basvuru</h3>
                <form method="post">
                    <?php wp_nonce_field('gorilla_referral_submit', '_gorilla_ref_nonce'); ?>

                    <div class="glr-form-group">
                        <label>Siparis Secin *</label>
                        <select name="referral_order_id" required class="glr-input">
                            <option value="">-- Siparis secin --</option>
                            <?php foreach ($eligible as $o):
                                $earn = round(floatval($o->get_total()) * ($rate / 100), 2);
                            ?>
                            <option value="<?php echo intval($o->get_id()); ?>">
                                #<?php echo intval($o->get_id()); ?> -- <?php echo wc_price($o->get_total()); ?> -- Kazanc: <?php echo wc_price($earn); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="glr-form-group">
                        <label>Platform *</label>
                        <select name="referral_platform" required class="glr-input">
                            <option value="">-- Platform secin --</option>
                            <option value="YouTube">YouTube</option>
                            <option value="Instagram">Instagram (Reels/Post/Story)</option>
                            <option value="TikTok">TikTok</option>
                            <option value="Twitter/X">Twitter / X</option>
                            <option value="Facebook">Facebook</option>
                            <option value="Twitch">Twitch</option>
                            <option value="Diger">Diger</option>
                        </select>
                    </div>

                    <div class="glr-form-group">
                        <label>Video Linki *</label>
                        <input type="url" name="referral_video_url" class="glr-input" placeholder="https://www.youtube.com/watch?v=..." required>
                    </div>

                    <div class="glr-form-group">
                        <label>Not <span style="color:#aaa;">(istege bagli)</span></label>
                        <textarea name="referral_note" class="glr-input" rows="3" placeholder="Eklemek istediginiz bir not varsa..."></textarea>
                    </div>

                    <button type="submit" name="gorilla_submit_referral" value="1" class="glr-btn">Basvuru Gonder</button>
                </form>
            </div>
            <?php elseif (empty($eligible)): ?>
            <div class="glr-card" style="text-align:center; color:#9ca3af;">
                <div style="font-size:40px;">---</div>
                <p>Su anda basvuru yapabileceginiz siparis bulunmuyor.</p>
                <p style="font-size:13px;">Yeni siparis verdikten ve urunlerinizi aldiktan sonra buradan basvuru yapabilirsiniz.</p>
            </div>
            <?php endif; ?>

            <!-- Basvuru Gecmisi -->
            <?php if (!empty($submissions)): ?>
            <h3 style="margin-top:30px; font-size:16px;">Basvuru Gecmisi</h3>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <?php foreach ($submissions as $sub):
                    if (!is_array($sub)) continue;
                    $status_info = array(
                        'pending'       => array('Inceleniyor', '#f59e0b', '#fef3c7'),
                        'grla_approved' => array('Onaylandi', '#22c55e', '#dcfce7'),
                        'grla_rejected' => array('Reddedildi', '#ef4444', '#fee2e2'),
                    );
                    $si = $status_info[$sub['status'] ?? ''] ?? array(($sub['status'] ?? 'Bilinmiyor'), '#888', '#f0f0f0');
                ?>
                <div class="glr-card" style="padding:14px 18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div>
                        <strong>Siparis #<?php echo esc_html($sub['order_id'] ?? ''); ?></strong>
                        <span style="color:#9ca3af; font-size:13px;"> -- <?php echo esc_html($sub['platform'] ?? ''); ?> -- <?php echo esc_html($sub['date'] ?? ''); ?></span>
                        <div style="font-size:13px; color:#6b7280;">Kazanc: <strong><?php echo wc_price($sub['credit'] ?? 0); ?></strong></div>
                    </div>
                    <span style="background:<?php echo esc_attr($si[2]); ?>; color:<?php echo esc_attr($si[1]); ?>; padding:4px 14px; border-radius:20px; font-size:12px; font-weight:600; white-space:nowrap;"><?php echo esc_html($si[0]); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Credit Gecmisi -->
            <?php
            $log = function_exists('gorilla_credit_get_log') ? gorilla_credit_get_log($user_id, 20) : array();
            if (!empty($log) && is_array($log)):
            ?>
            <h3 style="margin-top:30px; font-size:16px;">Credit Gecmisi</h3>
            <div class="glr-card" style="padding:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr style="background:#f9fafb;">
                            <th style="padding:10px 14px; text-align:left;">Tarih</th>
                            <th style="padding:10px 14px; text-align:left;">Aciklama</th>
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
            error_log('Gorilla RA referral page error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        }
        echo '<p style="color:#ef4444;">Referans sayfasi yuklenirken bir hata olustu. Lutfen yoneticiye bildirin.</p>';
    }
});
