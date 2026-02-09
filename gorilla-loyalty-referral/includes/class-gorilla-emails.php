<?php
/**
 * Gorilla LR - E-posta Bildirimleri
 * Referans onay/red, admin bildirimi
 */

if (!defined('ABSPATH')) exit;

// ── Referans Onay E-postası ──────────────────────────────
function gorilla_email_referral_approved($ref_id) {
    $user_id = get_post_meta($ref_id, '_ref_user_id', true);
    $credit = get_post_meta($ref_id, '_ref_credit_amount', true);
    $order_id = get_post_meta($ref_id, '_ref_order_id', true);
    
    $user = get_userdata($user_id);
    if (!$user) return;
    
    $balance = gorilla_credit_get_balance($user_id);
    $shop_url = wc_get_page_permalink('shop');
    $account_url = wc_get_account_endpoint_url('gorilla-referral');
    
    $subject = '🎉 Referans Başvurunuz Onaylandı! - Gorilla Custom Cards';
    
    $message = gorilla_email_template(
        '🎉 Referans Onaylandı!',
        sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Sipariş <strong>#%s</strong> için gönderdiğiniz video referans başvurusu <span style="color:#22c55e; font-weight:700;">onaylandı</span>!</p>
            <div style="background:#dcfce7; border-radius:12px; padding:20px; text-align:center; margin:20px 0;">
                <div style="color:#166534; font-size:14px;">Hesabınıza Eklenen</div>
                <div style="font-size:32px; font-weight:800; color:#15803d;">+%s</div>
                <div style="color:#4ade80; font-size:13px; margin-top:6px;">Güncel Bakiyeniz: %s</div>
            </div>
            <p>Store credit bakiyenizi bir sonraki alışverişinizde ödeme sayfasında kullanabilirsiniz.</p>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#f97316; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">🛒 Alışverişe Başla</a>
            </p>
            <p style="color:#888; font-size:13px;">Referans detaylarınızı <a href="%s">hesabınızdan</a> görebilirsiniz.</p>',
            esc_html($user->display_name),
            $order_id,
            wc_price($credit),
            wc_price($balance),
            esc_url($shop_url),
            esc_url($account_url)
        )
    );
    
    gorilla_send_email($user->user_email, $subject, $message);
}

// ── Referans Red E-postası ───────────────────────────────
function gorilla_email_referral_rejected($ref_id) {
    $user_id = get_post_meta($ref_id, '_ref_user_id', true);
    $order_id = get_post_meta($ref_id, '_ref_order_id', true);
    
    $user = get_userdata($user_id);
    if (!$user) return;
    
    $account_url = wc_get_account_endpoint_url('gorilla-referral');
    
    $subject = 'Referans Başvurunuz Hakkında - Gorilla Custom Cards';
    
    $message = gorilla_email_template(
        'Başvuru Sonucu',
        sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Sipariş <strong>#%s</strong> için gönderdiğiniz video referans başvurusu incelendi ancak maalesef bu sefer onaylanamadı.</p>
            <div style="background:#fef2f2; border-radius:12px; padding:18px; margin:20px 0;">
                <strong style="color:#991b1b;">Olası sebepler:</strong>
                <ul style="margin:8px 0 0; padding-left:20px; color:#991b1b;">
                    <li>Video içeriği ürünü yeterince göstermiyor</li>
                    <li>Video linki çalışmıyor veya gizli</li>
                    <li>Video çok kısa veya içerik yetersiz</li>
                </ul>
            </div>
            <p>Lütfen video içeriğinizi gözden geçirip tekrar başvuru yapabilirsiniz. Sorularınız için bize WhatsApp\'tan ulaşabilirsiniz.</p>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#6b7280; color:#fff; padding:12px 35px; border-radius:8px; text-decoration:none; font-weight:600; display:inline-block;">Hesabıma Git</a>
            </p>',
            esc_html($user->display_name),
            $order_id,
            esc_url($account_url)
        )
    );
    
    gorilla_send_email($user->user_email, $subject, $message);
}

// ── Yeni Başvuru: Admin Bildirimi ────────────────────────
function gorilla_email_new_referral($ref_id) {
    $admin_email = get_option('admin_email');
    $user_id = get_post_meta($ref_id, '_ref_user_id', true);
    $user = get_userdata($user_id);
    $order_id = get_post_meta($ref_id, '_ref_order_id', true);
    $total = get_post_meta($ref_id, '_ref_order_total', true);
    $credit = get_post_meta($ref_id, '_ref_credit_amount', true);
    $platform = get_post_meta($ref_id, '_ref_platform', true);
    $video = get_post_meta($ref_id, '_ref_video_url', true);
    
    $subject = '🔗 Yeni Referans Başvurusu - ' . ($user ? $user->display_name : 'Müşteri');
    $admin_url = admin_url('edit.php?post_type=gorilla_referral&post_status=pending');
    
    $message = gorilla_email_template(
        '🔗 Yeni Referans Başvurusu',
        sprintf(
            '<table style="width:100%%; border-collapse:collapse; margin:16px 0;">
                <tr><td style="padding:8px 12px; background:#f9fafb; font-weight:600; width:140px;">Müşteri</td><td style="padding:8px 12px;">%s (%s)</td></tr>
                <tr><td style="padding:8px 12px; background:#f9fafb; font-weight:600;">Sipariş</td><td style="padding:8px 12px;">#%s — %s</td></tr>
                <tr><td style="padding:8px 12px; background:#f9fafb; font-weight:600;">Kazanılacak Credit</td><td style="padding:8px 12px; font-weight:700; color:#22c55e;">%s</td></tr>
                <tr><td style="padding:8px 12px; background:#f9fafb; font-weight:600;">Platform</td><td style="padding:8px 12px;">%s</td></tr>
                <tr><td style="padding:8px 12px; background:#f9fafb; font-weight:600;">Video</td><td style="padding:8px 12px;"><a href="%s">%s</a></td></tr>
            </table>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#f97316; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">📋 Başvuruları İncele</a>
            </p>',
            $user ? esc_html($user->display_name) : '?',
            $user ? esc_html($user->user_email) : '?',
            $order_id,
            wc_price($total),
            wc_price($credit),
            esc_html($platform),
            esc_url($video),
            esc_html($video),
            esc_url($admin_url)
        )
    );
    
    gorilla_send_email($admin_email, $subject, $message);
}


// ── Seviye Tebrik E-postası ──────────────────────────────
function gorilla_email_tier_upgrade($user_id, $old_tier, $new_tier) {
    $user = get_userdata($user_id);
    if (!$user) return;

    $account_url = wc_get_account_endpoint_url('gorilla-loyalty');
    $shop_url = wc_get_page_permalink('shop');

    $subject = sprintf('🎉 Tebrikler! %s %s Seviyesine Yükseldiniz! - Gorilla Custom Cards',
        $new_tier['emoji'] ?? '🎖️',
        $new_tier['label'] ?? 'Yeni'
    );

    $benefits = '<ul style="margin:12px 0; padding-left:20px; color:#166534;">';
    $benefits .= '<li>Tüm alışverişlerinizde <strong>%' . intval($new_tier['discount'] ?? 0) . ' indirim</strong></li>';
    if (($new_tier['installment'] ?? 0) > 0) {
        $benefits .= '<li>Vade farksız <strong>' . intval($new_tier['installment']) . ' taksit</strong> hakkı</li>';
    }
    if (!empty($new_tier['free_shipping'])) {
        $benefits .= '<li><strong>Ücretsiz kargo</strong> ayrıcalığı</li>';
    }
    $benefits .= '</ul>';

    $message = gorilla_email_template(
        sprintf('%s Seviye Yükseltmesi!', $new_tier['emoji'] ?? '🎖️'),
        sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Harika haberler! Alışverişleriniz sayesinde <strong>%s %s</strong> seviyesine yükseldiniz!</p>
            <div style="background:linear-gradient(135deg, %s15, %s30); border:2px solid %s; border-radius:16px; padding:30px; text-align:center; margin:20px 0;">
                <div style="font-size:64px; line-height:1;">%s</div>
                <div style="font-size:28px; font-weight:800; color:#1f2937; margin:8px 0;">%s Üye</div>
                <div style="font-size:18px; color:#4b5563;">%%%d indirim kazandınız!</div>
            </div>
            <div style="background:#f0fdf4; border-radius:12px; padding:18px; margin:20px 0;">
                <strong style="color:#166534;">🎁 Yeni Ayrıcalıklarınız:</strong>
                %s
            </div>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#f97316; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">🛒 Hemen Alışverişe Başla</a>
            </p>
            <p style="color:#888; font-size:13px; text-align:center;">Seviye detaylarınızı <a href="%s">hesabınızdan</a> görebilirsiniz.</p>',
            esc_html($user->display_name),
            $new_tier['emoji'] ?? '🎖️',
            esc_html($new_tier['label'] ?? 'Yeni'),
            esc_attr($new_tier['color'] ?? '#999'),
            esc_attr($new_tier['color'] ?? '#999'),
            esc_attr($new_tier['color'] ?? '#999'),
            $new_tier['emoji'] ?? '🎖️',
            esc_html($new_tier['label'] ?? 'Yeni'),
            intval($new_tier['discount'] ?? 0),
            $benefits,
            esc_url($shop_url),
            esc_url($account_url)
        )
    );

    gorilla_send_email($user->user_email, $subject, $message);
}


// ── Affiliate Komisyon Kazanıldı E-postası ───────────────
function gorilla_email_affiliate_earned($user_id, $order_id, $commission) {
    $user = get_userdata($user_id);
    if (!$user) return;

    $balance = function_exists('gorilla_credit_get_balance') ? gorilla_credit_get_balance($user_id) : 0;
    $account_url = wc_get_account_endpoint_url('gorilla-referral');
    $shop_url = wc_get_page_permalink('shop');

    $subject = sprintf('🎉 Affiliate Komisyonu Kazandınız: %s - Gorilla Custom Cards', wc_price($commission));

    $message = gorilla_email_template(
        '🔗 Affiliate Komisyonu!',
        sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Harika haber! Paylaştığınız affiliate linkiniz üzerinden bir sipariş tamamlandı ve <strong>komisyon kazandınız!</strong></p>
            <div style="background:linear-gradient(135deg, #dbeafe, #eff6ff); border:2px solid #3b82f6; border-radius:12px; padding:25px; text-align:center; margin:20px 0;">
                <div style="color:#1e40af; font-size:14px;">Kazandığınız Komisyon</div>
                <div style="font-size:36px; font-weight:800; color:#1e40af;">+%s</div>
                <div style="color:#6b7280; font-size:12px; margin-top:8px;">Sipariş #%d</div>
            </div>
            <div style="background:#dcfce7; border-radius:10px; padding:16px; text-align:center; margin:20px 0;">
                <div style="color:#166534; font-size:14px;">Güncel Store Credit Bakiyeniz</div>
                <div style="font-size:28px; font-weight:800; color:#15803d;">%s</div>
            </div>
            <p>Store credit bakiyenizi bir sonraki alışverişinizde ödeme sayfasında kullanabilirsiniz.</p>
            <div style="background:#f8fafc; border-radius:10px; padding:16px; margin:20px 0;">
                <p style="margin:0; font-size:13px; color:#64748b;">
                    💡 <strong>İpucu:</strong> Affiliate linkinizi daha fazla kişiyle paylaşarak komisyon kazanmaya devam edin!
                    Linkinizi <a href="%s">hesabınızdan</a> kopyalayabilirsiniz.
                </p>
            </div>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#f97316; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">🛒 Alışverişe Başla</a>
            </p>',
            esc_html($user->display_name),
            wc_price($commission),
            $order_id,
            wc_price($balance),
            esc_url($account_url),
            esc_url($shop_url)
        )
    );

    gorilla_send_email($user->user_email, $subject, $message);
}


// -- Level-Up E-postasi (XP level atlayinca) ---
add_action('gorilla_xp_level_up', 'gorilla_email_level_up', 10, 3);
function gorilla_email_level_up($user_id, $old_level, $new_level) {
    $user = get_userdata($user_id);
    if (!$user) return;

    $account_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('gorilla-loyalty') : home_url();

    $subject = sprintf('Tebrikler! %s %s Seviyesine Yukseldiniz! - Gorilla Custom Cards',
        $new_level['emoji'] ?? '',
        $new_level['label'] ?? 'Yeni'
    );

    $message = gorilla_email_template(
        sprintf('%s Level Atladin!', $new_level['emoji'] ?? ''),
        sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Harika haberler! XP puanlariniz sayesinde <strong>%s %s</strong> seviyesine yukseldiniz!</p>
            <div style="background:linear-gradient(135deg, %s15, %s30); border:2px solid %s; border-radius:16px; padding:30px; text-align:center; margin:20px 0;">
                <div style="font-size:64px; line-height:1;">%s</div>
                <div style="font-size:28px; font-weight:800; color:#1f2937; margin:8px 0;">%s</div>
            </div>
            <p>Alisverislerinize devam ederek daha fazla XP kazanabilir ve seviyenizi yukseltebilirsiniz.</p>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#f97316; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Hesabima Git</a>
            </p>',
            esc_html($user->display_name),
            $new_level['emoji'] ?? '',
            esc_html($new_level['label'] ?? 'Yeni'),
            esc_attr($new_level['color'] ?? '#999'),
            esc_attr($new_level['color'] ?? '#999'),
            esc_attr($new_level['color'] ?? '#999'),
            $new_level['emoji'] ?? '',
            esc_html($new_level['label'] ?? 'Yeni'),
            esc_url($account_url)
        )
    );

    gorilla_send_email($user->user_email, $subject, $message);
}


// -- Credit Expiry Uyari E-postasi ---
function gorilla_email_credit_expiry_warning($user_id, $expiring_amount, $expiry_date) {
    $user = get_userdata($user_id);
    if (!$user) return;

    $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url();

    $subject = 'Store Credit Sureniz Doluyor! - Gorilla Custom Cards';

    $message = gorilla_email_template(
        'Store Credit Hatirlatmasi',
        sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Hesabinizdaki store credit bakiyenizin bir kisminin suresi dolmak uzere!</p>
            <div style="background:#fef3c7; border:2px solid #f59e0b; border-radius:12px; padding:25px; text-align:center; margin:20px 0;">
                <div style="color:#92400e; font-size:14px;">Suresi Dolacak Tutar</div>
                <div style="font-size:32px; font-weight:800; color:#d97706;">%s</div>
                <div style="color:#b45309; font-size:13px; margin-top:6px;">Son Kullanim: %s</div>
            </div>
            <p>Bu tutari kaybetmemek icin son kullanim tarihinden once alisveris yapmanizi oneririz.</p>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#f97316; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Alisverise Basla</a>
            </p>',
            esc_html($user->display_name),
            wc_price($expiring_amount),
            esc_html(date_i18n('d.m.Y', strtotime($expiry_date))),
            esc_url($shop_url)
        )
    );

    gorilla_send_email($user->user_email, $subject, $message);
}


// -- Credit Expiry Uyari Cron ---
add_action('gorilla_lr_daily_tier_check', 'gorilla_email_check_expiry_warnings');
function gorilla_email_check_expiry_warnings() {
    $warn_days = intval(get_option('gorilla_lr_credit_expiry_warn_days', 7));
    if ($warn_days <= 0) return;

    global $wpdb;
    $table = $wpdb->prefix . 'gorilla_credit_log';

    if (!gorilla_lr_table_exists($table)) return;

    // expires_at kolonu var mi?
    $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'expires_at'");
    if (empty($columns)) return;

    $warn_date = gmdate('Y-m-d H:i:s', strtotime("+{$warn_days} days"));
    $now = current_time('mysql');

    $expiring = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT user_id, SUM(amount) as total_expiring, MIN(expires_at) as earliest_expiry
             FROM {$table}
             WHERE expires_at IS NOT NULL
             AND expires_at > %s
             AND expires_at <= %s
             AND amount > 0
             AND type NOT IN ('expired', 'expired_processed')
             GROUP BY user_id",
            $now, $warn_date
        )
    );

    foreach ($expiring as $row) {
        $user_id = intval($row->user_id);
        $warn_sent_key = 'gorilla_expiry_warn_' . $user_id;

        // Bu periyotta uyari gonderilmis mi?
        if (get_transient($warn_sent_key)) continue;

        gorilla_email_credit_expiry_warning($user_id, floatval($row->total_expiring), $row->earliest_expiry);

        // 3 gun boyunca tekrar gonderme
        set_transient($warn_sent_key, true, 3 * DAY_IN_SECONDS);
    }
}


// -- E-posta Template ---
function gorilla_email_template($title, $body) {
    $logo_url = 'https://www.gorillacustomcards.com/wp-content/uploads/2022/09/gorilla-logo.png';
    
    return '
    <!DOCTYPE html>
    <html>
    <head><meta charset="utf-8"></head>
    <body style="margin:0; padding:0; background:#f4f4f5; font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">
        <div style="max-width:600px; margin:0 auto; padding:20px;">
            <!-- Header -->
            <div style="background:#1f2937; padding:24px; border-radius:12px 12px 0 0; text-align:center;">
                <img src="' . $logo_url . '" alt="Gorilla Custom Cards" style="height:50px; width:auto;">
            </div>
            
            <!-- Title -->
            <div style="background:#fff; padding:30px; border-bottom:1px solid #e5e7eb;">
                <h1 style="margin:0; font-size:22px; color:#1f2937; text-align:center;">' . $title . '</h1>
            </div>
            
            <!-- Body -->
            <div style="background:#fff; padding:30px 30px 35px; border-radius:0 0 12px 12px;">
                ' . $body . '
            </div>
            
            <!-- Footer -->
            <div style="text-align:center; padding:20px; color:#9ca3af; font-size:12px;">
                <p>Gorilla Custom Cards © ' . wp_date('Y') . '</p>
                <p>Türkiye\'nin 1. Kart Konsept Mağazası</p>
            </div>
        </div>
    </body>
    </html>';
}

// ── E-posta Gönder (HTML) ────────────────────────────────
function gorilla_send_email($to, $subject, $message) {
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Gorilla Custom Cards <' . get_option('admin_email') . '>',
    );

    wp_mail($to, $subject, $message, $headers);
}


// -- Dogum Gunu E-postasi ---
function gorilla_email_birthday($user_id, $xp_amount, $credit_amount) {
    $user = get_userdata($user_id);
    if (!$user) return;

    $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url();

    $subject = 'Dogum Gununuz Kutlu Olsun! - Gorilla Custom Cards';

    $gifts = '';
    if ($xp_amount > 0) $gifts .= '<li><strong>' . intval($xp_amount) . ' XP</strong> bonus puani</li>';
    if ($credit_amount > 0) $gifts .= '<li><strong>' . wc_price($credit_amount) . '</strong> store credit hediyesi</li>';

    $message = gorilla_email_template(
        'Dogum Gununuz Kutlu Olsun!',
        sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Bugun dogum gununuz! Size ozel hediyelerimiz var:</p>
            <div style="background:linear-gradient(135deg, #fce7f3, #fbcfe8); border:2px solid #ec4899; border-radius:16px; padding:30px; text-align:center; margin:20px 0;">
                <div style="font-size:64px; line-height:1;">🎂</div>
                <div style="font-size:24px; font-weight:800; color:#1f2937; margin:12px 0;">Iyi ki Dogdunuz!</div>
                <ul style="list-style:none; padding:0; margin:16px 0; font-size:16px; color:#831843;">%s</ul>
            </div>
            <p>Hediyeleriniz otomatik olarak hesabiniza eklendi. Iyi gunlerde kullanin!</p>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#ec4899; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Alisverise Basla</a>
            </p>',
            esc_html($user->display_name),
            $gifts,
            esc_url($shop_url)
        )
    );

    gorilla_send_email($user->user_email, $subject, $message);
}


// -- Dual Referral Kupon E-postasi ---
function gorilla_email_dual_referral_coupon($user_id, $coupon_code) {
    $user = get_userdata($user_id);
    if (!$user) return;

    $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url();
    $coupon = new WC_Coupon($coupon_code);
    $amount = $coupon->get_amount();
    $type = $coupon->get_discount_type();
    $type_label = ($type === 'percent') ? '%' . intval($amount) . ' indirim' : wc_price($amount) . ' indirim';

    $subject = 'Hosgeldin Hediyeniz Hazir! - Gorilla Custom Cards';

    $message = gorilla_email_template(
        'Hosgeldin Hediyesi!',
        sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Bir arkadasiniz sizi Gorilla Custom Cards\'a yonlendirdi ve size ozel bir hosgeldin hediyesi kazandiniz!</p>
            <div style="background:linear-gradient(135deg, #dbeafe, #eff6ff); border:2px solid #3b82f6; border-radius:16px; padding:30px; text-align:center; margin:20px 0;">
                <div style="font-size:48px; line-height:1;">🎁</div>
                <div style="font-size:22px; font-weight:800; color:#1e40af; margin:12px 0;">%s</div>
                <div style="background:#1e40af; color:#fff; padding:12px 24px; border-radius:8px; font-size:20px; font-weight:800; letter-spacing:2px; display:inline-block; margin:12px 0;">%s</div>
                <div style="font-size:13px; color:#6b7280; margin-top:8px;">Kupon kodunuzu kasada kullanabilirsiniz</div>
            </div>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#3b82f6; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Hemen Alisverise Basla</a>
            </p>',
            esc_html($user->display_name),
            esc_html($type_label),
            esc_html($coupon_code),
            esc_url($shop_url)
        )
    );

    gorilla_send_email($user->user_email, $subject, $message);
}


// -- Milestone Tamamlandi E-postasi ---
function gorilla_email_milestone_reached($user_id, $milestone) {
    $user = get_userdata($user_id);
    if (!$user) return;

    $account_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('gorilla-loyalty') : home_url();

    $subject = sprintf('Hedef Tamamlandi: %s - Gorilla Custom Cards', $milestone['label'] ?? '');

    $rewards = '';
    if (($milestone['xp_reward'] ?? 0) > 0) $rewards .= '<li><strong>' . intval($milestone['xp_reward']) . ' XP</strong></li>';
    if (($milestone['credit_reward'] ?? 0) > 0) $rewards .= '<li><strong>' . wc_price($milestone['credit_reward']) . '</strong> store credit</li>';

    $message = gorilla_email_template(
        'Hedef Tamamlandi!',
        sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Tebrikler! Bir hedefinizi tamamladiniz:</p>
            <div style="background:linear-gradient(135deg, #dcfce7, #bbf7d0); border:2px solid #22c55e; border-radius:16px; padding:25px; text-align:center; margin:20px 0;">
                <div style="font-size:48px;">🏆</div>
                <div style="font-size:22px; font-weight:800; color:#166534; margin:8px 0;">%s</div>
                %s
            </div>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:#22c55e; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Hesabima Git</a>
            </p>',
            esc_html($user->display_name),
            esc_html($milestone['label'] ?? ''),
            $rewards ? '<ul style="list-style:none; padding:0; margin:12px 0; color:#166534;">' . $rewards . '</ul>' : '',
            esc_url($account_url)
        )
    );

    gorilla_send_email($user->user_email, $subject, $message);
}


// -- Rozet Kazanildi E-postasi ---
add_action('gorilla_badge_earned', 'gorilla_email_badge_earned', 10, 2);
function gorilla_email_badge_earned($user_id, $badge_id) {
    $user = get_userdata($user_id);
    if (!$user) return;

    $definitions = function_exists('gorilla_badge_get_definitions') ? gorilla_badge_get_definitions() : array();
    $badge = $definitions[$badge_id] ?? null;
    if (!$badge) return;

    $account_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('gorilla-loyalty') : home_url();

    $subject = sprintf('Yeni Rozet Kazandiniz: %s %s - Gorilla Custom Cards', $badge['emoji'] ?? '', $badge['label'] ?? '');

    $message = gorilla_email_template(
        'Yeni Rozet!',
        sprintf(
            '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
            <p>Tebrikler! Yeni bir rozet kazandiniz:</p>
            <div style="background:linear-gradient(135deg, %s15, %s30); border:2px solid %s; border-radius:16px; padding:30px; text-align:center; margin:20px 0;">
                <div style="font-size:64px; line-height:1;">%s</div>
                <div style="font-size:24px; font-weight:800; color:#1f2937; margin:8px 0;">%s</div>
                <div style="font-size:14px; color:#6b7280;">%s</div>
            </div>
            <p style="text-align:center; margin:25px 0;">
                <a href="%s" style="background:%s; color:#fff; padding:14px 40px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; display:inline-block;">Rozetlerimi Gor</a>
            </p>',
            esc_html($user->display_name),
            esc_attr($badge['color'] ?? '#999'),
            esc_attr($badge['color'] ?? '#999'),
            esc_attr($badge['color'] ?? '#999'),
            $badge['emoji'] ?? '',
            esc_html($badge['label'] ?? ''),
            esc_html($badge['description'] ?? ''),
            esc_url($account_url),
            esc_attr($badge['color'] ?? '#999')
        )
    );

    gorilla_send_email($user->user_email, $subject, $message);
}
