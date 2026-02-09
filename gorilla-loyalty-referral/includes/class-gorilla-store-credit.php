<?php
/**
 * Gorilla LR - Store Credit Sistemi
 * Bakiye yonetimi, sepet entegrasyonu, islem gecmisi
 *
 * @version 3.0.0
 */

if (!defined('ABSPATH')) exit;

// -- Bakiye Oku ---
function gorilla_credit_get_balance($user_id) {
    return floatval(get_user_meta($user_id, '_gorilla_store_credit', true));
}

// -- Bakiye Guncelle (arti veya eksi) - Transaction ile atomik ---
function gorilla_credit_adjust($user_id, $amount, $type = 'credit', $reason = '', $reference_id = null, $expires_days = 0) {
    global $wpdb;

    // Transaction ile atomik islem (race condition onleme)
    $wpdb->query('START TRANSACTION');

    try {
        // Row-level lock ile mevcut bakiyeyi oku
        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = '_gorilla_store_credit' FOR UPDATE",
            $user_id
        ));

        if ($current === null) {
            // Meta yoksa olustur
            add_user_meta($user_id, '_gorilla_store_credit', 0, true);
            $current = 0;
        }

        $current = floatval($current);
        $new_balance = round($current + $amount, 2);

        // Bakiye negatife dusemez
        if ($new_balance < 0) $new_balance = 0;

        // Direkt SQL ile guncelle (meta cache bypass)
        $wpdb->update(
            $wpdb->usermeta,
            array('meta_value' => $new_balance),
            array('user_id' => $user_id, 'meta_key' => '_gorilla_store_credit'),
            array('%f'),
            array('%d', '%s')
        );

        // Expiry hesapla
        $expires_at = null;
        if ($amount > 0 && $expires_days > 0) {
            $expires_at = gmdate('Y-m-d H:i:s', strtotime("+{$expires_days} days"));
        }

        // Log tablosuna yaz
        $table = $wpdb->prefix . 'gorilla_credit_log';
        if (gorilla_lr_table_exists($table)) {
            $wpdb->insert($table, array(
                'user_id'       => $user_id,
                'amount'        => $amount,
                'balance_after' => $new_balance,
                'type'          => $type,
                'reason'        => $reason,
                'reference_id'  => $reference_id,
                'created_at'    => current_time('mysql'),
                'expires_at'    => $expires_at,
            ), array('%d', '%f', '%f', '%s', '%s', '%d', '%s', '%s'));
        }

        $wpdb->query('COMMIT');

        // WP meta cache temizle
        wp_cache_delete($user_id, 'user_meta');

        return $new_balance;

    } catch (\Throwable $e) {
        $wpdb->query('ROLLBACK');
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gorilla LR credit_adjust error: ' . $e->getMessage());
        }
        return gorilla_credit_get_balance($user_id);
    }
}

// -- Credit Expiry Kontrolu (Cron) - Atomik ---
add_action('gorilla_lr_daily_tier_check', 'gorilla_credit_check_expiry');
function gorilla_credit_check_expiry() {
    global $wpdb;
    $table = $wpdb->prefix . 'gorilla_credit_log';

    // Tablo var mi kontrol
    if (!gorilla_lr_table_exists($table)) return;

    // expires_at kolonu var mi kontrol (upgrade durumu icin)
    $columns = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'expires_at'));
    if (empty($columns)) return;

    // Buguen expire olan kayitlari bul
    $now = current_time('mysql');
    $expired = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT user_id, SUM(amount) as total_expired
             FROM {$table}
             WHERE expires_at IS NOT NULL
             AND expires_at <= %s
             AND amount > 0
             AND type NOT IN ('expired', 'expired_processed')
             GROUP BY user_id",
            $now
        )
    );

    foreach ($expired as $row) {
        $user_id = intval($row->user_id);
        $expire_amount = floatval($row->total_expired);

        if ($expire_amount > 0) {
            // Transaction ile atomik islem
            $wpdb->query('START TRANSACTION');

            try {
                // Bakiyeyi kilitli oku
                $current = $wpdb->get_var($wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = '_gorilla_store_credit' FOR UPDATE",
                    $user_id
                ));
                $current = floatval($current);
                $new_balance = max(0, $current - $expire_amount);

                // Bakiye guncelle
                $wpdb->update(
                    $wpdb->usermeta,
                    array('meta_value' => $new_balance),
                    array('user_id' => $user_id, 'meta_key' => '_gorilla_store_credit'),
                    array('%f'),
                    array('%d', '%s')
                );

                // Log kaydet
                $wpdb->insert($table, array(
                    'user_id'       => $user_id,
                    'amount'        => -$expire_amount,
                    'balance_after' => $new_balance,
                    'type'          => 'expired',
                    'reason'        => __('Store credit suresi doldu', 'gorilla-lr'),
                    'reference_id'  => null,
                    'created_at'    => current_time('mysql'),
                    'expires_at'    => null,
                ), array('%d', '%f', '%f', '%s', '%s', '%d', '%s', '%s'));

                // Eski kayitlari expired olarak isaretle (tekrar islememek icin)
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET type = 'expired_processed' WHERE user_id = %d AND expires_at <= %s AND amount > 0 AND type NOT IN ('expired', 'expired_processed')",
                        $user_id, $now
                    )
                );

                $wpdb->query('COMMIT');
                wp_cache_delete($user_id, 'user_meta');

            } catch (\Throwable $e) {
                $wpdb->query('ROLLBACK');
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Gorilla LR credit expiry error for user ' . $user_id . ': ' . $e->getMessage());
                }
            }
        }
    }
}

// -- Credit Gecmisi ---
function gorilla_credit_get_log($user_id, $limit = 50) {
    global $wpdb;
    $table = $wpdb->prefix . 'gorilla_credit_log';

    // Tablo var mi kontrol et
    if (!gorilla_lr_table_exists($table)) {
        // Tablo yoksa user meta'dan oku (geriye uyumluluk)
        $meta_log = get_user_meta($user_id, '_gorilla_credit_log', true);
        return is_array($meta_log) ? array_reverse(array_slice($meta_log, -$limit)) : array();
    }

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
        $user_id, $limit
    ), ARRAY_A);
}


// -- Checkout: Store Credit Toggle ---
add_action('woocommerce_review_order_before_payment', 'gorilla_credit_checkout_ui');
function gorilla_credit_checkout_ui() {
    if (!is_user_logged_in()) return;
    if (!function_exists('WC') || !WC()) return;

    $credit = gorilla_credit_get_balance(get_current_user_id());
    if ($credit <= 0) return;

    $checked = (WC()->session && WC()->session->get('gorilla_use_credit', false));

    // CSS: assets/css/frontend.css, JS: assets/js/frontend.js
    ?>
    <div id="gorilla-credit-toggle">
        <label>
            <input type="checkbox" id="gorilla_use_credit_cb" <?php echo $checked ? 'checked' : ''; ?>>
            <span>Store Credit Kullan</span>
            <span style="margin-left:auto; background:#22c55e; color:#fff; padding:4px 14px; border-radius:20px; font-size:14px;">
                <?php echo wc_price($credit); ?>
            </span>
        </label>
        <p style="margin:8px 0 0 34px; font-size:12px; color:#4ade80;">Sepet tutarinizdan dusulecektir</p>
    </div>
    <?php
}

// -- AJAX: Toggle Credit ---
add_action('wp_ajax_gorilla_toggle_credit', function() {
    check_ajax_referer('gorilla_credit_toggle', 'nonce');
    $use = intval($_POST['use'] ?? 0) === 1;
    if (function_exists('WC') && WC() && WC()->session) {
        WC()->session->set('gorilla_use_credit', $use);
    }
    wp_send_json_success(array('use' => $use));
});


// -- Sepette Credit Uygula ---
add_action('woocommerce_cart_calculate_fees', 'gorilla_credit_apply_to_cart', 20);
function gorilla_credit_apply_to_cart($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (!is_user_logged_in()) return;
    if (!function_exists('WC') || !WC() || !WC()->session) return;
    if (!WC()->session->get('gorilla_use_credit')) return;

    $user_id = get_current_user_id();
    $credit = gorilla_credit_get_balance($user_id);
    if ($credit <= 0) return;

    // Minimum siparis kontrolu
    $min_order = floatval(get_option('gorilla_lr_credit_min_order', 0));
    if ($min_order > 0 && $cart->get_subtotal() < $min_order) return;

    // Sepet toplamini hesapla (loyalty indirimi dahil)
    $cart_total = floatval($cart->get_subtotal());
    $fees = $cart->get_fees();
    if (is_array($fees)) {
        foreach ($fees as $fee) {
            $fee_amount = is_object($fee) ? floatval($fee->amount ?? 0) : 0;
            $cart_total += $fee_amount;
        }
    }
    $cart_total = max(0, $cart_total);

    // Bakiye, sepetten fazla olamaz
    $apply = min($credit, $cart_total);

    if ($apply > 0.01) {
        $cart->add_fee('Store Credit', -$apply, false);
    }
}


// -- Checkout Sonrasi: Bakiyeyi Dus ---
add_action('woocommerce_checkout_order_processed', 'gorilla_credit_deduct_on_checkout', 10, 1);
function gorilla_credit_deduct_on_checkout($order_id) {
    if (!is_user_logged_in()) return;
    if (!function_exists('WC') || !WC() || !WC()->session) return;
    if (!WC()->session->get('gorilla_use_credit')) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $user_id = $order->get_customer_id();
    if (!$user_id) return;

    // Atomic idempotency guard - prevent double deduction
    global $wpdb;
    $already = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_gorilla_credit_deducted' AND meta_value = 'yes'",
        $order_id
    ));
    if ($already) return;
    $marked = $wpdb->query($wpdb->prepare(
        "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) SELECT %d, '_gorilla_credit_deducted', 'yes' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_gorilla_credit_deducted' AND meta_value = 'yes')",
        $order_id, $order_id
    ));
    if (!$marked) return;

    foreach ($order->get_fees() as $fee) {
        if (strpos($fee->get_name(), 'Store Credit') !== false) {
            $deducted = abs(floatval($fee->get_total()));
            if ($deducted > 0) {
                gorilla_credit_adjust(
                    $user_id,
                    -$deducted,
                    'debit',
                    sprintf('Siparis #%d icin kullanildi', $order_id),
                    $order_id
                );
                $order->add_order_note(sprintf('Store Credit: %s kullanildi.', wc_price($deducted)));
            }
            break;
        }
    }

    // Session temizle
    if (function_exists('WC') && WC() && WC()->session) {
        WC()->session->set('gorilla_use_credit', false);
    }
}


// -- Siparis iptal/iade durumunda credit geri ver ---
add_action('woocommerce_order_status_cancelled', 'gorilla_credit_refund_on_cancel');
add_action('woocommerce_order_status_refunded', 'gorilla_credit_refund_on_cancel');
function gorilla_credit_refund_on_cancel($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $user_id = $order->get_customer_id();
    if (!$user_id) return;

    // Bu siparis icin credit kullanilmis mi kontrol et
    foreach ($order->get_fees() as $fee) {
        if (strpos($fee->get_name(), 'Store Credit') !== false) {
            $refund = abs(floatval($fee->get_total()));
            if ($refund > 0) {
                // Atomic idempotency guard
                global $wpdb;
                $already = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_gorilla_credit_refunded' AND meta_value = 'yes'",
                    $order_id
                ));
                if ($already) return;
                $marked = $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) SELECT %d, '_gorilla_credit_refunded', 'yes' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_gorilla_credit_refunded' AND meta_value = 'yes')",
                    $order_id, $order_id
                ));
                if (!$marked) return;

                gorilla_credit_adjust(
                    $user_id,
                    $refund,
                    'refund',
                    sprintf('Siparis #%d iptali - credit iadesi', $order_id),
                    $order_id
                );
                $order->add_order_note(sprintf('Store Credit Iade: %s geri yuklendi.', wc_price($refund)));
            }
            break;
        }
    }
}
