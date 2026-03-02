<?php
/**
 * Gorilla Loyalty & Gamification - SMS / Twilio Integration
 *
 * Sends SMS notifications for loyalty events via Twilio REST API.
 * Events: tier_upgrade, credit_earned, spin_win, level_up, badge_earned.
 *
 * @package Gorilla_Loyalty_Gamification
 * @since   3.1.0
 */

if (!defined('ABSPATH')) exit;

// ── Encryption helpers for Twilio credentials ────────────
function gorilla_sms_encrypt($value) {
    if (empty($value)) return '';
    $key = substr(hash('sha256', wp_salt('auth')), 0, 32);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

function gorilla_sms_decrypt($value) {
    if (empty($value)) return '';
    $key = substr(hash('sha256', wp_salt('auth')), 0, 32);
    $decoded = base64_decode($value);
    if ($decoded === false) return $value; // Not encrypted, return as-is (migration)

    // New format: random IV prepended with '::' separator
    if (strpos($decoded, '::') !== false) {
        list($iv, $encrypted) = explode('::', $decoded, 2);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        if ($decrypted !== false) return $decrypted;
    }

    // Legacy fallback: deterministic IV from salt (for previously encrypted values)
    $legacy_iv = substr(hash('sha256', wp_salt('secure_auth')), 0, 16);
    $decrypted = openssl_decrypt($decoded, 'AES-256-CBC', $key, 0, $legacy_iv);
    return $decrypted !== false ? $decrypted : $value;
}

// ── Send SMS via Twilio REST API ────────────────────────
function gorilla_sms_send($to, $message) {
    $sid   = gorilla_sms_decrypt(get_option('gorilla_lr_twilio_sid', ''));
    $token = gorilla_sms_decrypt(get_option('gorilla_lr_twilio_token', ''));
    $from  = get_option('gorilla_lr_twilio_from', '');

    if (empty($sid) || empty($token) || empty($from) || empty($to)) {
        return false;
    }

    $to = preg_replace('/[^+0-9]/', '', $to);
    if (!preg_match('/^\+[1-9]\d{9,14}$/', $to)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla SMS: Invalid phone number format (must be E.164): ' . $to);
        }
        return false;
    }

    $rate_key = 'gorilla_sms_rate_' . md5($to);
    $rate_count = (int) get_transient($rate_key);
    if ($rate_count >= 10) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla SMS: Rate limit exceeded for ' . $to);
        }
        return false;
    }
    set_transient($rate_key, $rate_count + 1, HOUR_IN_SECONDS);

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . urlencode($sid) . '/Messages.json';

    $response = wp_remote_post($url, array(
        'timeout' => 15,
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($sid . ':' . $token),
        ),
        'body' => array(
            'To'   => $to,
            'From' => $from,
            'Body' => mb_substr($message, 0, 1600),
        ),
    ));

    if (is_wp_error($response)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla SMS error: ' . $response->get_error_message());
        }
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code >= 200 && $code < 300) {
        return true;
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Gorilla SMS HTTP ' . $code . ': ' . wp_remote_retrieve_body($response));
    }
    return false;
}

function gorilla_sms_get_phone($user_id) {
    if (get_user_meta($user_id, '_gorilla_sms_optout', true) === 'yes') {
        return '';
    }

    $phone = get_user_meta($user_id, '_gorilla_sms_phone', true);
    if (!empty($phone)) {
        return $phone;
    }

    $phone = get_user_meta($user_id, 'billing_phone', true);
    return $phone ?: '';
}

function gorilla_sms_notify($user_id, $message) {
    if (get_option('gorilla_lr_sms_enabled', 'no') !== 'yes') {
        return false;
    }

    $phone = gorilla_sms_get_phone($user_id);
    if (empty($phone)) {
        return false;
    }

    return gorilla_sms_send($phone, $message);
}

function gorilla_sms_event_enabled($event) {
    $events = get_option('gorilla_lr_sms_events', array());
    return is_array($events) && in_array($event, $events, true);
}

// ══════════════════════════════════════════════════════════
// EVENT HOOKS
// ══════════════════════════════════════════════════════════

add_action('gorilla_tier_upgraded', function($user_id, $old_tier, $new_tier) {
    if (!gorilla_sms_event_enabled('tier_upgrade')) return;

    $label = $new_tier['label'] ?? '';
    $emoji = $new_tier['emoji'] ?? '';
    $msg = sprintf(
        'Tebrikler! %s %s seviyesine yukseldiniz. Yeni indiriminiz: %%%s',
        $emoji, $label, $new_tier['discount'] ?? 0
    );
    gorilla_sms_notify($user_id, $msg);
}, 10, 3);

add_action('gorilla_xp_level_up', function($user_id, $old_level, $new_level) {
    if (!gorilla_sms_event_enabled('level_up')) return;

    $msg = sprintf(
        '%s Yeni seviye: %s! XP biriktirmeye devam edin.',
        $new_level['emoji'] ?? '', $new_level['label'] ?? ''
    );
    gorilla_sms_notify($user_id, $msg);
}, 10, 3);

add_action('gorilla_credit_adjusted', function($user_id, $amount, $reason) {
    if (!gorilla_sms_event_enabled('credit_earned')) return;
    if ($amount <= 0) return; // Don't notify on deductions

    $msg = sprintf(
        'Hesabiniza %s TL store credit eklendi! Sebep: %s',
        number_format($amount, 2, ',', '.'), mb_substr($reason, 0, 100)
    );
    gorilla_sms_notify($user_id, $msg);
}, 10, 3);

add_action('gorilla_spin_win', function($user_id, $prize_label) {
    if (!gorilla_sms_event_enabled('spin_win')) return;

    $msg = sprintf('Cark cevirme odulinuz: %s! Tebrikler!', $prize_label);
    gorilla_sms_notify($user_id, $msg);
}, 10, 2);

add_action('gorilla_badge_earned', function($user_id, $badge_key, $tier_key) {
    if (!gorilla_sms_event_enabled('badge_earned')) return;

    $definitions = function_exists('gorilla_badge_get_definitions') ? gorilla_badge_get_definitions() : array();
    $badge = $definitions[$badge_key] ?? array();
    $label = $badge['label'] ?? $badge_key;
    $emoji = $badge['emoji'] ?? '';
    $tier_label = '';
    if ($tier_key && function_exists('gorilla_badge_tier_meta')) {
        $tier_meta = gorilla_badge_tier_meta($tier_key);
        $tier_label = ' (' . ($tier_meta['label'] ?? $tier_key) . ')';
    }

    $msg = sprintf(
        '%s "%s"%s rozetini kazandiniz! Rozet koleksiyonunuzu kontrol edin.',
        $emoji, $label, $tier_label
    );
    gorilla_sms_notify($user_id, $msg);
}, 10, 3);

// ══════════════════════════════════════════════════════════
// FRONTEND: OPT-IN / OPT-OUT UI
// ══════════════════════════════════════════════════════════

add_action('gorilla_frontend_after_settings', function() {
    if (get_option('gorilla_lr_sms_enabled', 'no') !== 'yes') return;

    $user_id = get_current_user_id();
    if (!$user_id) return;

    $phone   = get_user_meta($user_id, '_gorilla_sms_phone', true);
    $optout  = get_user_meta($user_id, '_gorilla_sms_optout', true) === 'yes';
    $nonce   = wp_create_nonce('gorilla_sms_prefs');
    ?>
    <hr style="border:none; border-top:2px dashed #e5e7eb; margin:35px 0;">
    <h2 style="font-size:24px; font-weight:800; margin-bottom:20px;">SMS Bildirimleri</h2>
    <div class="glr-card" style="padding:20px;">
        <p style="margin-bottom:10px;">
            <label>
                <input type="checkbox" id="gorilla_sms_optin" <?php checked(!$optout); ?>>
                SMS bildirimlerini almak istiyorum
            </label>
        </p>
        <p style="margin-bottom:5px;">
            <input type="tel" id="gorilla_sms_phone" value="<?php echo esc_attr($phone); ?>"
                   placeholder="+90 5XX XXX XX XX" style="width:200px; padding:6px 10px; border:1px solid #d1d5db; border-radius:8px;">
            <button type="button" id="gorilla_sms_save" class="button" style="margin-left:5px;">Kaydet</button>
        </p>
        <p class="description" style="color:#6b7280; font-size:12px;">Telefon numaraniz sadece sadakat bildirimleri icin kullanilir.</p>
    </div>
    <script>
    (function(){
        var btn = document.getElementById('gorilla_sms_save');
        if (!btn) return;
        var nonce = <?php echo wp_json_encode($nonce); ?>;
        btn.addEventListener('click', function(){
            var phone = document.getElementById('gorilla_sms_phone').value;
            var optin = document.getElementById('gorilla_sms_optin').checked;
            var fd = new FormData();
            fd.append('action', 'gorilla_sms_save_prefs');
            fd.append('phone', phone);
            fd.append('optin', optin ? '1' : '0');
            fd.append('_wpnonce', nonce);
            fetch(gorillaLR.ajax_url, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){return r.json()})
                .then(function(d){btn.textContent=d.success?'Kaydedildi':'Hata'; setTimeout(function(){btn.textContent='Kaydet'},2000)});
        });
    })();
    </script>
    <?php
});

add_action('wp_ajax_gorilla_sms_save_prefs', function() {
    check_ajax_referer('gorilla_sms_prefs');
    $user_id = get_current_user_id();
    if (!$user_id) wp_send_json_error();

    if (!current_user_can('read')) {
        wp_send_json_error(array('message' => 'Yetkiniz yok.'));
    }

    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $phone = preg_replace('/[^+0-9]/', '', $phone);

    if (!empty($phone) && !preg_match('/^\+[1-9]\d{9,14}$/', $phone)) {
        wp_send_json_error(array('message' => 'Gecersiz telefon numarasi. +90XXXXXXXXXX formatinda girin.'));
    }

    update_user_meta($user_id, '_gorilla_sms_phone', $phone);

    $optout = ($_POST['optin'] ?? '0') === '0' ? 'yes' : 'no';
    update_user_meta($user_id, '_gorilla_sms_optout', $optout);

    wp_send_json_success();
});
