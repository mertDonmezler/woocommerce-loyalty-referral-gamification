<?php
/**
 * Gorilla LR - Referans/Affiliate Sistemi
 * Video içerik referans başvuruları, onay/red, store credit
 */

if (!defined('ABSPATH')) exit;

// ── Custom Post Type ─────────────────────────────────────
add_action('init', function() {
    register_post_type('gorilla_referral', array(
        'labels' => array(
            'name'               => 'Referans Başvuruları',
            'singular_name'      => 'Referans Başvurusu',
            'menu_name'          => '🔗 Referanslar',
            'all_items'          => 'Tüm Başvurular',
            'edit_item'          => 'Başvuru Detayı',
            'search_items'       => 'Başvuru Ara',
            'not_found'          => 'Başvuru bulunamadı.',
            'not_found_in_trash' => 'Çöpte başvuru yok.',
        ),
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => 'gorilla-loyalty-admin',
        'supports'           => array(''),
        'capability_type'    => 'post',
        'capabilities'       => array('create_posts' => 'do_not_allow'),
        'map_meta_cap'       => true,
    ));
});

// ── Toplu İşlemler (Bulk Actions) ────────────────────────
add_filter('bulk_actions-edit-gorilla_referral', function($actions) {
    $actions['gorilla_bulk_approve'] = '✅ Toplu Onayla';
    $actions['gorilla_bulk_reject'] = '❌ Toplu Reddet';
    return $actions;
});

add_filter('handle_bulk_actions-edit-gorilla_referral', function($redirect_to, $action, $post_ids) {
    if (!in_array($action, ['gorilla_bulk_approve', 'gorilla_bulk_reject'])) {
        return $redirect_to;
    }

    if (!current_user_can('manage_woocommerce')) {
        return $redirect_to;
    }

    $processed = 0;
    $skipped = 0;

    global $wpdb;

    foreach ($post_ids as $post_id) {
        if ($action === 'gorilla_bulk_approve') {
            // Atomic: sadece pending ise approve et
            $updated = $wpdb->update(
                $wpdb->posts,
                array('post_status' => 'grla_approved'),
                array('ID' => $post_id, 'post_status' => 'pending'),
                array('%s'),
                array('%d', '%s')
            );
            if (!$updated) {
                $skipped++;
                continue;
            }
            clean_post_cache($post_id);

            $user_id = get_post_meta($post_id, '_ref_user_id', true);
            $credit = floatval(get_post_meta($post_id, '_ref_credit_amount', true));

            if ($user_id && $credit > 0 && function_exists('gorilla_credit_adjust')) {
                $expiry_days = intval(get_option('gorilla_lr_credit_expiry_days', 0));
                gorilla_credit_adjust($user_id, $credit, 'referral', sprintf('Referans başvurusu #%d onaylandı', $post_id), $post_id, $expiry_days);
            }

            if (function_exists('gorilla_email_referral_approved')) {
                gorilla_email_referral_approved($post_id);
            }

            if (function_exists('gorilla_xp_on_referral_approved')) {
                gorilla_xp_on_referral_approved($user_id, $post_id);
            }

            // Cift tarafli referans: musteri de kupon kazanir
            if (get_option('gorilla_lr_dual_referral_enabled', 'no') === 'yes') {
                $ref_order_id = get_post_meta($post_id, '_ref_order_id', true);
                $ref_order = wc_get_order($ref_order_id);
                if ($ref_order && function_exists('gorilla_generate_coupon')) {
                    $customer_id = $ref_order->get_customer_id();
                    if ($customer_id) {
                        $coupon_code = gorilla_generate_coupon(array(
                            'type'        => get_option('gorilla_lr_dual_referral_type', 'percent'),
                            'amount'      => floatval(get_option('gorilla_lr_dual_referral_amount', 10)),
                            'min_order'   => floatval(get_option('gorilla_lr_dual_referral_min_order', 0)),
                            'expiry_days' => intval(get_option('gorilla_lr_dual_referral_expiry_days', 30)),
                            'user_id'     => $customer_id,
                            'reason'      => 'referral_dual_sided',
                            'prefix'      => 'REF',
                        ));
                        if ($coupon_code && function_exists('gorilla_email_dual_referral_coupon')) {
                            gorilla_email_dual_referral_coupon($customer_id, $coupon_code);
                        }
                    }
                }
            }

            $processed++;
        } elseif ($action === 'gorilla_bulk_reject') {
            // Atomic: sadece pending ise reject et
            $updated = $wpdb->update(
                $wpdb->posts,
                array('post_status' => 'grla_rejected'),
                array('ID' => $post_id, 'post_status' => 'pending'),
                array('%s'),
                array('%d', '%s')
            );
            if (!$updated) {
                $skipped++;
                continue;
            }
            clean_post_cache($post_id);

            if (function_exists('gorilla_email_referral_rejected')) {
                gorilla_email_referral_rejected($post_id);
            }

            $processed++;
        }
    }

    $redirect_to = add_query_arg(array(
        'gorilla_bulk_processed' => $processed,
        'gorilla_bulk_skipped' => $skipped,
        'gorilla_bulk_action' => $action,
    ), $redirect_to);

    return $redirect_to;
}, 10, 3);

// Toplu işlem sonuç mesajı
add_action('admin_notices', function() {
    if (!isset($_GET['gorilla_bulk_processed'])) return;

    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'gorilla_referral') return;

    $processed = intval($_GET['gorilla_bulk_processed']);
    $skipped = intval($_GET['gorilla_bulk_skipped'] ?? 0);
    $action = sanitize_key($_GET['gorilla_bulk_action'] ?? '');

    if ($processed > 0) {
        $action_text = ($action === 'gorilla_bulk_approve') ? 'onaylandı' : 'reddedildi';
        $class = ($action === 'gorilla_bulk_approve') ? 'updated' : 'warning';
        echo '<div class="notice notice-' . $class . ' is-dismissible"><p>';
        echo sprintf('✅ <strong>%d</strong> başvuru toplu olarak %s.', $processed, $action_text);
        if ($skipped > 0) {
            echo sprintf(' (%d başvuru zaten işlenmiş olduğu için atlandı.)', $skipped);
        }
        echo '</p></div>';
    }
});


// ── Admin Kolonlar ───────────────────────────────────────
add_filter('manage_gorilla_referral_posts_columns', function($columns) {
    return array(
        'cb'            => '<input type="checkbox" />',
        'ref_customer'  => 'Müşteri',
        'ref_order'     => 'Sipariş',
        'ref_total'     => 'Sipariş Tutarı',
        'ref_credit'    => 'Kazanç',
        'ref_platform'  => 'Platform',
        'ref_video'     => 'Video',
        'ref_status'    => 'Durum',
        'ref_date'      => 'Tarih',
        'ref_actions'   => 'İşlem',
    );
});

add_action('manage_gorilla_referral_posts_custom_column', function($col, $id) {
    $meta = function($key) use ($id) { return get_post_meta($id, $key, true); };
    
    switch($col) {
        case 'ref_customer':
            $uid = $meta('_ref_user_id');
            $user = get_userdata($uid);
            if ($user) {
                echo '<strong>' . esc_html($user->display_name) . '</strong>';
                echo '<br><span style="color:#888; font-size:12px;">' . esc_html($user->user_email) . '</span>';
            } else {
                echo '<em>Bilinmiyor</em>';
            }
            break;
            
        case 'ref_order':
            $oid = $meta('_ref_order_id');
            if ($oid) {
                $edit_url = admin_url("post.php?post={$oid}&action=edit");
                echo '<a href="' . esc_url($edit_url) . '" style="font-weight:600;">#' . intval($oid) . '</a>';
            }
            break;
            
        case 'ref_total':
            echo wc_price($meta('_ref_order_total'));
            break;
            
        case 'ref_credit':
            $c = $meta('_ref_credit_amount');
            $rate = get_option('gorilla_lr_referral_rate', 35);
            echo '<strong style="color:#22c55e;">' . wc_price($c) . '</strong>';
            echo '<br><span style="font-size:11px; color:#888;">(%' . $rate . ')</span>';
            break;
            
        case 'ref_platform':
            $p = $meta('_ref_platform');
            $icons = array('YouTube' => '🎬', 'Instagram' => '📸', 'TikTok' => '🎵', 'Twitter/X' => '🐦', 'Facebook' => '📘', 'Twitch' => '🎮');
            echo ($icons[$p] ?? '📱') . ' ' . esc_html($p);
            break;
            
        case 'ref_video':
            $url = $meta('_ref_video_url');
            if ($url) {
                echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" style="text-decoration:none; background:#eff6ff; padding:4px 12px; border-radius:6px; font-size:13px;">▶ İzle</a>';
            }
            break;
            
        case 'ref_status':
            $s = get_post_status($id);
            $map = array(
                'pending' => array('⏳ Bekliyor', '#f59e0b', '#fef3c7'),
                'grla_approved' => array('✅ Onaylandı', '#22c55e', '#dcfce7'),
                'grla_rejected' => array('❌ Reddedildi', '#ef4444', '#fee2e2'),
            );
            $info = $map[$s] ?? array($s, '#888', '#f0f0f0');
            echo '<span style="background:' . $info[2] . '; color:' . $info[1] . '; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; white-space:nowrap;">' . $info[0] . '</span>';
            break;
            
        case 'ref_date':
            echo get_the_date('d.m.Y', $id);
            echo '<br><span style="color:#888; font-size:12px;">' . get_the_date('H:i', $id) . '</span>';
            break;
            
        case 'ref_actions':
            $s = get_post_status($id);
            if ($s === 'pending') {
                $approve_url = wp_nonce_url(admin_url("admin-post.php?action=gorilla_ref_approve&id={$id}"), 'gorilla_ref_action_' . $id);
                $reject_url  = wp_nonce_url(admin_url("admin-post.php?action=gorilla_ref_reject&id={$id}"), 'gorilla_ref_action_' . $id);
                echo '<a href="' . $approve_url . '" class="button button-small" style="color:#22c55e; border-color:#22c55e; margin-right:4px;" onclick="return confirm(\'Onaylayıp credit verilsin mi?\')">✅ Onayla</a>';
                echo '<a href="' . $reject_url . '" class="button button-small" style="color:#ef4444; border-color:#ef4444;" onclick="return confirm(\'Reddetmek istediğinize emin misiniz?\')">❌ Red</a>';
            }
            break;
    }
}, 10, 2);


// ── Custom Post Statuses ─────────────────────────────────
add_action('init', function() {
    register_post_status('grla_approved', array(
        'label'                     => 'Onaylandı',
        'public'                    => false,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Onaylandı <span class="count">(%s)</span>', 'Onaylandı <span class="count">(%s)</span>'),
    ));
    register_post_status('grla_rejected', array(
        'label'                     => 'Reddedildi',
        'public'                    => false,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Reddedildi <span class="count">(%s)</span>', 'Reddedildi <span class="count">(%s)</span>'),
    ));
});

// ── Status filter for admin list ─────────────────────────
add_filter('views_edit-gorilla_referral', function($views) {
    global $wpdb;

    // Tek sorgu ile tüm status sayılarını al (performans iyileştirmesi)
    $counts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_status, COUNT(*) as count FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN (%s, %s, %s) GROUP BY post_status",
            'gorilla_referral', 'pending', 'grla_approved', 'grla_rejected'
        ),
        OBJECT_K
    );

    $pending = isset($counts['pending']) ? intval($counts['pending']->count) : 0;
    $approved = isset($counts['grla_approved']) ? intval($counts['grla_approved']->count) : 0;
    $rejected = isset($counts['grla_rejected']) ? intval($counts['grla_rejected']->count) : 0;

    $current = sanitize_key($_GET['post_status'] ?? '');
    
    $views['all'] = '<a href="' . admin_url('edit.php?post_type=gorilla_referral') . '" ' . (empty($current) ? 'class="current"' : '') . '>Tümü <span class="count">(' . ($pending + $approved + $rejected) . ')</span></a>';
    $views['pending'] = '<a href="' . admin_url('edit.php?post_type=gorilla_referral&post_status=pending') . '" ' . ($current === 'pending' ? 'class="current"' : '') . '>⏳ Bekliyor <span class="count">(' . $pending . ')</span></a>';
    $views['grla_approved'] = '<a href="' . admin_url('edit.php?post_type=gorilla_referral&post_status=grla_approved') . '" ' . ($current === 'grla_approved' ? 'class="current"' : '') . '>✅ Onaylanan <span class="count">(' . $approved . ')</span></a>';
    $views['grla_rejected'] = '<a href="' . admin_url('edit.php?post_type=gorilla_referral&post_status=grla_rejected') . '" ' . ($current === 'grla_rejected' ? 'class="current"' : '') . '>❌ Reddedilen <span class="count">(' . $rejected . ')</span></a>';
    
    unset($views['publish'], $views['draft'], $views['trash']);
    return $views;
});


// ── Admin Post Aksiyonları: Onayla / Reddet ──────────────
add_action('admin_post_gorilla_ref_approve', function() {
    global $wpdb;

    $id = intval($_GET['id'] ?? 0);
    if (!$id || !current_user_can('manage_woocommerce')) wp_die('Yetki hatası.');
    check_admin_referer('gorilla_ref_action_' . $id);

    // Atomic status update: only approve if still pending (race condition prevention)
    $updated = $wpdb->update(
        $wpdb->posts,
        array('post_status' => 'grla_approved'),
        array('ID' => $id, 'post_status' => 'pending'),
        array('%s'),
        array('%d', '%s')
    );
    if (!$updated) {
        wp_redirect(admin_url('edit.php?post_type=gorilla_referral&gorilla_msg=already_processed'));
        exit;
    }
    clean_post_cache($id);

    $user_id = get_post_meta($id, '_ref_user_id', true);
    $credit = floatval(get_post_meta($id, '_ref_credit_amount', true));
    
    // Credit ver
    if ($user_id && $credit > 0 && function_exists('gorilla_credit_adjust')) {
        $expiry_days = intval(get_option('gorilla_lr_credit_expiry_days', 0));
        gorilla_credit_adjust($user_id, $credit, 'referral', sprintf('Referans başvurusu #%d onaylandı', $id), $id, $expiry_days);
    }

    // E-posta gönder
    if (function_exists('gorilla_email_referral_approved')) {
        gorilla_email_referral_approved($id);
    }

    // XP ver
    if (function_exists('gorilla_xp_on_referral_approved')) {
        gorilla_xp_on_referral_approved($user_id, $id);
    }

    // Cift tarafli referans: musteri de kupon kazanir
    if (get_option('gorilla_lr_dual_referral_enabled', 'no') === 'yes') {
        $ref_order_id = get_post_meta($id, '_ref_order_id', true);
        $ref_order = wc_get_order($ref_order_id);
        if ($ref_order && function_exists('gorilla_generate_coupon')) {
            $customer_id = $ref_order->get_customer_id();
            if ($customer_id) {
                $coupon_code = gorilla_generate_coupon(array(
                    'type'        => get_option('gorilla_lr_dual_referral_type', 'percent'),
                    'amount'      => floatval(get_option('gorilla_lr_dual_referral_amount', 10)),
                    'min_order'   => floatval(get_option('gorilla_lr_dual_referral_min_order', 0)),
                    'expiry_days' => intval(get_option('gorilla_lr_dual_referral_expiry_days', 30)),
                    'user_id'     => $customer_id,
                    'reason'      => 'referral_dual_sided',
                    'prefix'      => 'REF',
                ));
                if ($coupon_code && function_exists('gorilla_email_dual_referral_coupon')) {
                    gorilla_email_dual_referral_coupon($customer_id, $coupon_code);
                }
            }
        }
    }

    wp_redirect(admin_url('edit.php?post_type=gorilla_referral&gorilla_msg=approved'));
    exit;
});

add_action('admin_post_gorilla_ref_reject', function() {
    global $wpdb;

    $id = intval($_GET['id'] ?? 0);
    if (!$id || !current_user_can('manage_woocommerce')) wp_die('Yetki hatası.');
    check_admin_referer('gorilla_ref_action_' . $id);

    // Atomic status update: only reject if still pending
    $updated = $wpdb->update(
        $wpdb->posts,
        array('post_status' => 'grla_rejected'),
        array('ID' => $id, 'post_status' => 'pending'),
        array('%s'),
        array('%d', '%s')
    );
    if (!$updated) {
        wp_redirect(admin_url('edit.php?post_type=gorilla_referral&gorilla_msg=already_processed'));
        exit;
    }
    clean_post_cache($id);
    if (function_exists('gorilla_email_referral_rejected')) {
        gorilla_email_referral_rejected($id);
    }

    wp_redirect(admin_url('edit.php?post_type=gorilla_referral&gorilla_msg=rejected'));
    exit;
});


// ── Admin bildiri mesajları ──────────────────────────────
add_action('admin_notices', function() {
    if (!isset($_GET['gorilla_msg'])) return;
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'gorilla_referral') return;
    
    $messages = array(
        'approved'          => array('updated',  '✅ Referans onaylandı ve store credit müşteriye eklendi!'),
        'rejected'          => array('warning',  '❌ Referans başvurusu reddedildi.'),
        'already_processed' => array('error',    '⚠️ Bu başvuru zaten işlenmiş.'),
    );
    
    $msg_key = sanitize_key($_GET['gorilla_msg']);
    $msg = $messages[$msg_key] ?? null;
    if ($msg) {
        echo '<div class="notice notice-' . esc_attr($msg[0]) . ' is-dismissible"><p>' . wp_kses_post($msg[1]) . '</p></div>';
    }
});


// ── Müşteri Başvurusu İşleme (Frontend) ──────────────────
function gorilla_referral_process_submission() {
    if (!isset($_POST['gorilla_submit_referral'])) return false;
    if (!is_user_logged_in()) return false;
    if (!wp_verify_nonce($_POST['_gorilla_ref_nonce'] ?? '', 'gorilla_referral_submit')) return false;
    if (get_option('gorilla_lr_enabled_referral') !== 'yes') return false;
    
    $user_id   = get_current_user_id();

    // Rate limiting: 5 dakika cooldown
    $rate_key = 'gorilla_ref_rate_' . $user_id;
    if (get_transient($rate_key)) {
        return array('success' => false, 'errors' => array('Cok sik basvuru gonderiyorsunuz. Lutfen 5 dakika bekleyin.'));
    }

    $order_id  = intval($_POST['referral_order_id'] ?? 0);
    $video_url = esc_url_raw($_POST['referral_video_url'] ?? '');
    $platform  = sanitize_text_field($_POST['referral_platform'] ?? '');
    $note      = sanitize_textarea_field($_POST['referral_note'] ?? '');

    // Validasyon
    $errors = array();
    if (!$order_id) $errors[] = 'Lutfen bir siparis secin.';
    if (empty($video_url) || !filter_var($video_url, FILTER_VALIDATE_URL)) $errors[] = 'Gecerli bir video linki girin.';
    if (empty($platform)) $errors[] = 'Lutfen platform secin.';

    // Video URL domain validasyonu
    if (!empty($video_url) && filter_var($video_url, FILTER_VALIDATE_URL)) {
        $allowed_domains = array('youtube.com', 'youtu.be', 'instagram.com', 'tiktok.com', 'twitter.com', 'x.com', 'facebook.com', 'fb.watch', 'twitch.tv', 'vimeo.com');
        $parsed = wp_parse_url($video_url);
        $host = strtolower($parsed['host'] ?? '');
        // www. prefix'ini kaldir
        $host = preg_replace('/^www\./', '', $host);
        $valid_domain = false;
        foreach ($allowed_domains as $domain) {
            if ($host === $domain || substr($host, -strlen('.' . $domain)) === '.' . $domain) {
                $valid_domain = true;
                break;
            }
        }
        if (!$valid_domain) {
            $errors[] = 'Video linki gecerli bir platformdan olmalidir (YouTube, Instagram, TikTok, Twitter/X, Facebook, Twitch, Vimeo).';
        }
    }
    
    // Sipariş doğrula
    $order = wc_get_order($order_id);
    if (!$order || $order->get_customer_id() != $user_id) {
        $errors[] = 'Geçersiz sipariş.';
    }
    
    // Daha önce başvurulmuş mu?
    if ($order) {
        $existing = get_posts(array(
            'post_type'   => 'gorilla_referral',
            'post_status' => array('pending', 'grla_approved'),
            'meta_query'  => array(
                array('key' => '_ref_order_id', 'value' => $order_id),
                array('key' => '_ref_user_id', 'value' => $user_id),
            ),
            'numberposts' => 1,
        ));
        if (!empty($existing)) $errors[] = 'Bu sipariş için zaten bir başvurunuz var.';
    }
    
    if (!empty($errors)) {
        return array('success' => false, 'errors' => $errors);
    }

    // Race condition lock: ayni user+order icin esanli basvuruyu engelle
    $lock_key = 'gorilla_ref_lock_' . $user_id . '_' . $order_id;
    if (get_transient($lock_key)) {
        return array('success' => false, 'errors' => array('Bu başvuru zaten işleniyor. Lütfen birkaç saniye bekleyin.'));
    }
    set_transient($lock_key, true, 30);

    // Kilit aldiktan sonra tekrar kontrol et (double-check)
    $existing_recheck = get_posts(array(
        'post_type'   => 'gorilla_referral',
        'post_status' => array('pending', 'grla_approved'),
        'meta_query'  => array(
            array('key' => '_ref_order_id', 'value' => $order_id),
            array('key' => '_ref_user_id', 'value' => $user_id),
        ),
        'numberposts' => 1,
    ));
    if (!empty($existing_recheck)) {
        delete_transient($lock_key);
        return array('success' => false, 'errors' => array('Bu sipariş için zaten bir başvurunuz var.'));
    }

    // Credit miktari hesapla
    $rate = intval(get_option('gorilla_lr_referral_rate', 35));
    $order_total = floatval($order->get_total());
    $credit_amount = round($order_total * ($rate / 100), 2);

    // Seasonal bonus carpani uygula
    if (function_exists('gorilla_xp_get_bonus_multiplier')) {
        $multiplier = gorilla_xp_get_bonus_multiplier();
        if ($multiplier > 1) {
            $credit_amount = round($credit_amount * $multiplier, 2);
        }
    }
    
    // Başvuru oluştur
    $ref_id = wp_insert_post(array(
        'post_type'   => 'gorilla_referral',
        'post_title'  => (($u = get_userdata($user_id)) ? $u->display_name : 'User #' . $user_id) . ' — #' . $order_id,
        'post_status' => 'pending',
    ));
    
    if (is_wp_error($ref_id)) {
        delete_transient($lock_key);
        return array('success' => false, 'errors' => array('Bir hata oluştu. Lütfen tekrar deneyin.'));
    }
    
    // Meta kaydet
    update_post_meta($ref_id, '_ref_user_id', $user_id);
    update_post_meta($ref_id, '_ref_order_id', $order_id);
    update_post_meta($ref_id, '_ref_order_total', $order_total);
    update_post_meta($ref_id, '_ref_credit_amount', $credit_amount);
    update_post_meta($ref_id, '_ref_video_url', $video_url);
    update_post_meta($ref_id, '_ref_platform', $platform);
    update_post_meta($ref_id, '_ref_note', $note);
    
    // Admin'e bildirim e-postasi
    if (function_exists('gorilla_email_new_referral')) {
        gorilla_email_new_referral($ref_id);
    }

    // Rate limit set et (5 dakika)
    set_transient($rate_key, true, 5 * MINUTE_IN_SECONDS);

    return array('success' => true, 'credit_amount' => $credit_amount, 'ref_id' => $ref_id);
}

// ── Video Embed Preview Meta Box ─────────────────────────
add_action('add_meta_boxes', function() {
    add_meta_box(
        'gorilla_referral_details',
        '🎬 Başvuru Detayları & Video Önizleme',
        'gorilla_referral_metabox_render',
        'gorilla_referral',
        'normal',
        'high'
    );
});

function gorilla_referral_metabox_render($post) {
    $video_url  = get_post_meta($post->ID, '_ref_video_url', true);
    $platform   = get_post_meta($post->ID, '_ref_platform', true);
    $order_id   = get_post_meta($post->ID, '_ref_order_id', true);
    $user_id    = get_post_meta($post->ID, '_ref_user_id', true);
    $note       = get_post_meta($post->ID, '_ref_note', true);
    $credit     = get_post_meta($post->ID, '_ref_credit_amount', true);
    $order_total = get_post_meta($post->ID, '_ref_order_total', true);

    $user = get_userdata($user_id);
    $status = get_post_status($post->ID);
    ?>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:30px;">
        <!-- Sol: Video Embed -->
        <div>
            <h3 style="margin-top:0;">📺 Video Önizleme</h3>
            <?php
            if ($video_url) {
                $embed_html = gorilla_get_video_embed($video_url);
                if ($embed_html) {
                    echo '<div style="border-radius:12px; overflow:hidden; background:#000;">';
                    echo $embed_html;
                    echo '</div>';
                } else {
                    echo '<div style="background:#f9fafb; padding:30px; text-align:center; border-radius:12px; border:1px dashed #d1d5db;">';
                    echo '<p style="color:#6b7280; margin:0 0 12px;">Embed önizlemesi bu platform için desteklenmiyor.</p>';
                    echo '<a href="' . esc_url($video_url) . '" target="_blank" rel="noopener" class="button button-primary">▶ Videoyu Aç</a>';
                    echo '</div>';
                }
            } else {
                echo '<p style="color:#ef4444;">Video URL bulunamadı.</p>';
            }
            ?>
            <p style="margin-top:10px;">
                <strong>Platform:</strong> <?php echo esc_html($platform); ?><br>
                <strong>URL:</strong> <a href="<?php echo esc_url($video_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($video_url); ?></a>
            </p>
        </div>

        <!-- Sağ: Başvuru Bilgileri -->
        <div>
            <h3 style="margin-top:0;">📋 Başvuru Bilgileri</h3>
            <table class="form-table" style="margin:0;">
                <tr>
                    <th style="padding:8px 0; width:120px;">Müşteri</th>
                    <td style="padding:8px 0;">
                        <?php if ($user): ?>
                            <strong><?php echo esc_html($user->display_name); ?></strong><br>
                            <a href="mailto:<?php echo esc_attr($user->user_email); ?>"><?php echo esc_html($user->user_email); ?></a>
                        <?php else: ?>
                            <em>Bilinmiyor</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th style="padding:8px 0;">Sipariş</th>
                    <td style="padding:8px 0;">
                        <a href="<?php echo esc_url(admin_url("post.php?post={$order_id}&action=edit")); ?>" class="button button-small">#<?php echo intval($order_id); ?></a>
                        — Tutar: <strong><?php echo wc_price($order_total); ?></strong>
                    </td>
                </tr>
                <tr>
                    <th style="padding:8px 0;">Kazanılacak Credit</th>
                    <td style="padding:8px 0;">
                        <span style="background:#dcfce7; color:#166534; padding:6px 14px; border-radius:20px; font-weight:700;">
                            <?php echo wc_price($credit); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th style="padding:8px 0;">Durum</th>
                    <td style="padding:8px 0;">
                        <?php
                        $status_map = array(
                            'pending' => array('⏳ Bekliyor', '#f59e0b', '#fef3c7'),
                            'grla_approved' => array('✅ Onaylandı', '#22c55e', '#dcfce7'),
                            'grla_rejected' => array('❌ Reddedildi', '#ef4444', '#fee2e2'),
                        );
                        $si = $status_map[$status] ?? array($status, '#888', '#f0f0f0');
                        echo '<span style="background:' . esc_attr($si[2]) . '; color:' . esc_attr($si[1]) . '; padding:6px 14px; border-radius:20px; font-weight:600;">' . esc_html($si[0]) . '</span>';
                        ?>
                    </td>
                </tr>
                <tr>
                    <th style="padding:8px 0;">Başvuru Tarihi</th>
                    <td style="padding:8px 0;"><?php echo get_the_date('d.m.Y H:i', $post->ID); ?></td>
                </tr>
                <?php if ($note): ?>
                <tr>
                    <th style="padding:8px 0;">Müşteri Notu</th>
                    <td style="padding:8px 0;">
                        <div style="background:#f9fafb; padding:12px 16px; border-radius:8px; font-style:italic; color:#4b5563;">
                            <?php echo esc_html($note); ?>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <?php if ($status === 'pending'): ?>
            <div style="margin-top:20px; padding-top:20px; border-top:1px solid #e5e7eb;">
                <h4 style="margin:0 0 12px;">İşlem</h4>
                <?php
                $approve_url = wp_nonce_url(admin_url("admin-post.php?action=gorilla_ref_approve&id={$post->ID}"), 'gorilla_ref_action_' . $post->ID);
                $reject_url  = wp_nonce_url(admin_url("admin-post.php?action=gorilla_ref_reject&id={$post->ID}"), 'gorilla_ref_action_' . $post->ID);
                ?>
                <a href="<?php echo esc_url($approve_url); ?>" class="button button-primary button-large" onclick="return confirm('Başvuruyu onaylayıp <?php echo esc_attr(wc_price($credit)); ?> store credit verilsin mi?')">✅ Onayla & Credit Ver</a>
                <a href="<?php echo esc_url($reject_url); ?>" class="button button-large" style="color:#ef4444;" onclick="return confirm('Başvuruyu reddetmek istediğinize emin misiniz?')">❌ Reddet</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Video URL'sinden embed kodu oluşturur
 * YouTube, Vimeo, TikTok, Instagram destekler
 */
function gorilla_get_video_embed($url) {
    if (empty($url)) return '';

    // YouTube
    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $m)) {
        $video_id = $m[1];
        return '<iframe width="100%" height="315" src="https://www.youtube.com/embed/' . esc_attr($video_id) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
    }

    // Vimeo
    if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
        $video_id = $m[1];
        return '<iframe width="100%" height="315" src="https://player.vimeo.com/video/' . esc_attr($video_id) . '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
    }

    // TikTok
    if (preg_match('/tiktok\.com\/@[^\/]+\/video\/(\d+)/', $url, $m)) {
        $video_id = $m[1];
        return '<iframe width="100%" height="500" src="https://www.tiktok.com/embed/' . esc_attr($video_id) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
    }

    // Instagram (Reels/Post)
    if (preg_match('/instagram\.com\/(p|reel|reels)\/([A-Za-z0-9_-]+)/', $url, $m)) {
        $post_id = $m[2];
        return '<iframe width="100%" height="500" src="https://www.instagram.com/p/' . esc_attr($post_id) . '/embed" frameborder="0" scrolling="no" allowtransparency="true"></iframe>';
    }

    // Twitter/X - Video link'i olarak göster
    if (preg_match('/(?:twitter|x)\.com\/[^\/]+\/status\/(\d+)/', $url, $m)) {
        // X/Twitter embed API daha karmaşık, basit link göster
        return '';
    }

    // WordPress oEmbed ile dene (fallback)
    $embed = wp_oembed_get($url);
    if ($embed) {
        return $embed;
    }

    return '';
}

// ── Kullanıcının mevcut başvurularını al ─────────────────
function gorilla_referral_get_user_submissions($user_id) {
    $posts = get_posts(array(
        'post_type'   => 'gorilla_referral',
        'post_status' => array('pending', 'grla_approved', 'grla_rejected'),
        'meta_key'    => '_ref_user_id',
        'meta_value'  => $user_id,
        'numberposts' => 100,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ));
    
    $submissions = array();
    foreach ($posts as $p) {
        $submissions[] = array(
            'id'       => $p->ID,
            'order_id' => get_post_meta($p->ID, '_ref_order_id', true),
            'total'    => get_post_meta($p->ID, '_ref_order_total', true),
            'credit'   => get_post_meta($p->ID, '_ref_credit_amount', true),
            'platform' => get_post_meta($p->ID, '_ref_platform', true),
            'video'    => get_post_meta($p->ID, '_ref_video_url', true),
            'status'   => get_post_status($p->ID),
            'date'     => get_the_date('d.m.Y H:i', $p->ID),
        );
    }
    return $submissions;
}
