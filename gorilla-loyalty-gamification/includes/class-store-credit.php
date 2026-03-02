<?php
/**
 * Gorilla Store Credit - Core Credit System
 * Balance management, cart integration, transaction history
 *
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

// -- Get Balance --
if (!function_exists('gorilla_credit_get_balance')) {
    function gorilla_credit_get_balance($user_id) {
        return floatval(get_user_meta($user_id, '_gorilla_store_credit', true));
    }
}

// -- Adjust Balance (add or subtract) - Atomic with transaction --
if (!function_exists('gorilla_credit_adjust')) {
    function gorilla_credit_adjust($user_id, $amount, $type = 'credit', $reason = '', $reference_id = null, $expires_days = 0) {
        global $wpdb;

        // Atomic operation with transaction (race condition prevention)
        $wpdb->query('START TRANSACTION');

        try {
            // Row-level lock to read current balance
            $current = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = '_gorilla_store_credit' FOR UPDATE",
                $user_id
            ));

            if ($current === null) {
                // Meta does not exist, create it
                add_user_meta($user_id, '_gorilla_store_credit', 0, true);
                $current = 0;
            }

            $current = floatval($current);
            $new_balance = round($current + $amount, 2);

            // Balance cannot go negative
            if ($new_balance < 0) $new_balance = 0;

            // Direct SQL update (bypasses meta cache)
            $wpdb->update(
                $wpdb->usermeta,
                array('meta_value' => $new_balance),
                array('user_id' => $user_id, 'meta_key' => '_gorilla_store_credit'),
                array('%f'),
                array('%d', '%s')
            );

            // Calculate expiry
            $expires_at = null;
            if ($amount > 0 && $expires_days > 0) {
                $expires_at = gmdate('Y-m-d H:i:s', strtotime("+{$expires_days} days"));
            }

            // Write to log table
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

            // Clear WP meta cache
            wp_cache_delete($user_id, 'user_meta');

            // Notification hook
            do_action('gorilla_credit_adjusted', $user_id, $amount, $reason);

            return $new_balance;

        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Gorilla SC credit_adjust error: ' . $e->getMessage());
            }
            return gorilla_credit_get_balance($user_id);
        }
    }
}

// -- Credit Expiry Check (Cron) - Atomic --
if (!function_exists('gorilla_credit_check_expiry')) {
    function gorilla_credit_check_expiry() {
        global $wpdb;
        $table = $wpdb->prefix . 'gorilla_credit_log';

        // Check if table exists
        if (!gorilla_lr_table_exists($table)) return;

        // Check if expires_at column exists (upgrade scenario) - cached per request
        static $has_expires_col = null;
        if ($has_expires_col === null) {
            $columns = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'expires_at'));
            $has_expires_col = !empty($columns);
        }
        if (!$has_expires_col) return;

        // Find records expiring today - paginated to prevent memory issues
        $now = current_time('mysql');
        $batch_size = 100;
        $offset = 0;

        do {
            $expired = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT user_id, SUM(amount) as total_expired
                     FROM {$table}
                     WHERE expires_at IS NOT NULL
                     AND expires_at <= %s
                     AND amount > 0
                     AND type NOT IN ('expired', 'expired_processed')
                     GROUP BY user_id
                     LIMIT %d OFFSET %d",
                    $now,
                    $batch_size,
                    $offset
                )
            );

            if (empty($expired)) break;

            foreach ($expired as $row) {
                $user_id = intval($row->user_id);
                $expire_amount = floatval($row->total_expired);

                if ($expire_amount > 0) {
                    // Atomic operation with transaction
                    $wpdb->query('START TRANSACTION');

                    try {
                        // Lock and read balance
                        $current = $wpdb->get_var($wpdb->prepare(
                            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = '_gorilla_store_credit' FOR UPDATE",
                            $user_id
                        ));
                        $current = floatval($current);
                        $new_balance = max(0, $current - $expire_amount);

                        // Update balance
                        $wpdb->update(
                            $wpdb->usermeta,
                            array('meta_value' => $new_balance),
                            array('user_id' => $user_id, 'meta_key' => '_gorilla_store_credit'),
                            array('%f'),
                            array('%d', '%s')
                        );

                        // Log the expiry
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

                        // Mark old records as processed (prevent re-processing)
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
                            error_log('Gorilla SC credit expiry error for user ' . $user_id . ': ' . $e->getMessage());
                        }
                    }
                }
            }

            $offset += $batch_size;
        } while (count($expired) === $batch_size);
    }
    add_action('gorilla_sc_daily_check', 'gorilla_credit_check_expiry');
}

// -- Credit History --
if (!function_exists('gorilla_credit_get_log')) {
    function gorilla_credit_get_log($user_id, $limit = 50) {
        global $wpdb;
        $table = $wpdb->prefix . 'gorilla_credit_log';

        // Check if table exists
        if (!gorilla_lr_table_exists($table)) {
            // Fallback to user meta (backward compat)
            $meta_log = get_user_meta($user_id, '_gorilla_credit_log', true);
            return is_array($meta_log) ? array_reverse(array_slice($meta_log, -$limit)) : array();
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id, $limit
        ), ARRAY_A);
    }
}


// -- Checkout: Store Credit Slider --
if (!function_exists('gorilla_credit_checkout_ui')) {
    function gorilla_credit_checkout_ui() {
        if (!is_user_logged_in()) return;
        if (!function_exists('WC') || !WC()) return;

        $credit = gorilla_credit_get_balance(get_current_user_id());
        if ($credit <= 0) return;

        $session_amount = 0;
        if (WC()->session) {
            $session_amount = floatval(WC()->session->get('gorilla_credit_amount', 0));
        }
        // Cap slider max to min(credit, cart total) so user can't select more than order value
        $cart_total = WC()->cart ? floatval(WC()->cart->get_total('edit')) : 0;
        $slider_max = ($cart_total > 0) ? min($credit, $cart_total) : $credit;
        // Clamp to available max
        $session_amount = min($session_amount, $slider_max);

        ?>
        <div id="gorilla-credit-toggle" data-cart-total="<?php echo esc_attr($cart_total); ?>">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                <span style="font-weight:600; color:#1f2937;">Store Credit Kullan</span>
                <span style="margin-left:auto; background:#22c55e; color:#fff; padding:4px 14px; border-radius:20px; font-size:14px;">
                    <?php echo wc_price($credit); ?>
                </span>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <input type="range" id="gorilla_credit_slider" min="0" max="<?php echo esc_attr($slider_max); ?>" step="0.01" value="<?php echo esc_attr($session_amount); ?>" data-credit="<?php echo esc_attr($credit); ?>" style="flex:1; accent-color:#22c55e; cursor:pointer;">
                <span id="gorilla_credit_display" style="min-width:70px; text-align:right; font-weight:600; color:#22c55e; font-size:15px;"><?php echo wc_price($session_amount); ?></span>
            </div>
            <p style="margin:6px 0 0 0; font-size:12px; color:#4ade80;">Kullanmak istediginiz miktari secin</p>
        </div>
        <?php
    }
    // Classic Checkout hook.
    add_action('woocommerce_review_order_before_payment', 'gorilla_credit_checkout_ui');
    // Block Checkout: render via woocommerce_checkout_order_review (fires in both classic and block).
    add_action('woocommerce_checkout_order_review', 'gorilla_credit_checkout_ui', 30);
}

// -- Block Checkout Integration --
if (!function_exists('gorilla_credit_block_checkout_init')) {
    function gorilla_credit_block_checkout_init() {
        if (!class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface')) {
            return;
        }

        add_action('woocommerce_blocks_checkout_block_registration', function($integration_registry) {
            // Register a lightweight integration to ensure store credit data is available.
            $integration_registry->register(new class implements \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {
                public function get_name() { return 'gorilla-store-credit'; }
                public function initialize() {}
                public function get_script_handles() { return []; }
                public function get_editor_script_handles() { return []; }
                public function get_script_data() {
                    if (!is_user_logged_in()) return [];
                    $credit = gorilla_credit_get_balance(get_current_user_id());
                    return [
                        'credit_balance' => $credit,
                        'formatted_balance' => strip_tags(wc_price($credit)),
                    ];
                }
            });
        });

        // Ensure store credit fee persists across Block Checkout's store API calls.
        add_action('woocommerce_store_api_checkout_update_order_from_request', function($order) {
            if (!is_user_logged_in() || !function_exists('WC') || !WC() || !WC()->session) return;
            if (!WC()->session->get('gorilla_use_credit')) return;

            $amount = floatval(WC()->session->get('gorilla_credit_amount', 0));
            if ($amount <= 0) return;

            $credit = gorilla_credit_get_balance(get_current_user_id());
            $apply = min($amount, $credit, (float) $order->get_total());
            if ($apply > 0.01) {
                $order->add_meta_data('_gorilla_credit_applied', $apply, true);
            }
        });
    }
    add_action('woocommerce_blocks_loaded', 'gorilla_credit_block_checkout_init');
}

// -- AJAX: Set Credit Amount --
if (!has_action('wp_ajax_gorilla_toggle_credit')) {
    add_action('wp_ajax_gorilla_toggle_credit', function() {
        check_ajax_referer('gorilla_credit_toggle', 'nonce');
        $amount = floatval($_POST['amount'] ?? 0);

        // Clamp to 0, available credit, and cart total
        $max_credit = gorilla_credit_get_balance(get_current_user_id());
        $cart_total = (function_exists('WC') && WC() && WC()->cart) ? floatval(WC()->cart->get_total('edit')) : PHP_FLOAT_MAX;
        $amount = max(0, min($amount, $max_credit, $cart_total));

        if (function_exists('WC') && WC() && WC()->session) {
            WC()->session->set('gorilla_credit_amount', $amount);
            // Backward compat: use_credit = true when amount > 0
            WC()->session->set('gorilla_use_credit', $amount > 0);
        }
        wp_send_json_success(array('amount' => $amount, 'formatted' => strip_tags(wc_price($amount))));
    });
}


// -- Apply Credit to Cart --
if (!function_exists('gorilla_credit_apply_to_cart')) {
    function gorilla_credit_apply_to_cart($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (!is_user_logged_in()) return;
        if (!function_exists('WC') || !WC() || !WC()->session) return;
        if (!WC()->session->get('gorilla_use_credit')) return;

        $user_id = get_current_user_id();
        $credit = gorilla_credit_get_balance($user_id);
        if ($credit <= 0) return;

        // Minimum order check
        $min_order = floatval(get_option('gorilla_lr_credit_min_order', 0));
        if ($min_order > 0 && $cart->get_subtotal() < $min_order) return;

        // Amount selected by user (slider)
        $requested = floatval(WC()->session->get('gorilla_credit_amount', 0));
        if ($requested <= 0) return;

        // Calculate cart total (including loyalty discount)
        $cart_total = floatval($cart->get_subtotal());
        $fees = $cart->get_fees();
        if (is_array($fees)) {
            foreach ($fees as $fee) {
                $fee_amount = is_object($fee) ? floatval($fee->amount ?? 0) : 0;
                $cart_total += $fee_amount;
            }
        }
        $cart_total = max(0, $cart_total);

        // Amount: cannot exceed selected, balance, or cart total
        $apply = min($requested, $credit, $cart_total);

        if ($apply > 0.01) {
            $cart->add_fee('Store Credit', -$apply, false);
        }
    }
    add_action('woocommerce_cart_calculate_fees', 'gorilla_credit_apply_to_cart', 20);
}


// -- After Checkout: Deduct Balance (with GET_LOCK to prevent TOCTOU double-spend) --
if (!function_exists('gorilla_credit_deduct_on_checkout')) {
    function gorilla_credit_deduct_on_checkout($order_id) {
        global $wpdb;

        if (!is_user_logged_in()) return;
        if (!function_exists('WC') || !WC() || !WC()->session) return;
        if (!WC()->session->get('gorilla_use_credit')) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $user_id = $order->get_customer_id();
        if (!$user_id) return;

        // Acquire MySQL advisory lock to serialize credit operations for this user
        $lock_key = 'gorilla_credit_' . $user_id;
        $lock = $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, 5)", $lock_key));
        if (!$lock) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Gorilla SC: could not acquire lock for user ' . $user_id . ' on order ' . $order_id);
            }
            return;
        }

        try {
            // Re-read order meta inside the lock to prevent TOCTOU race
            $order = wc_get_order($order_id);
            if (!$order) return;

            // Idempotency guard - prevent double deduction
            if ($order->get_meta('_gorilla_credit_deducted') === 'yes') return;

            // Verify user still has sufficient balance before deducting
            $current_balance = gorilla_credit_get_balance($user_id);

            foreach ($order->get_fees() as $fee) {
                if (strpos($fee->get_name(), 'Store Credit') !== false) {
                    $deducted = abs(floatval($fee->get_total()));
                    if ($deducted > 0) {
                        // Clamp to actual available balance to prevent negative balance
                        $deducted = min($deducted, $current_balance);
                        if ($deducted <= 0) break;

                        // Mark as deducted BEFORE the actual adjustment (inside lock)
                        $order->update_meta_data('_gorilla_credit_deducted', 'yes');
                        $order->save();

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

            // Clear session
            if (function_exists('WC') && WC() && WC()->session) {
                WC()->session->set('gorilla_use_credit', false);
            }
        } finally {
            $wpdb->query($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_key));
        }
    }
    add_action('woocommerce_checkout_order_processed', 'gorilla_credit_deduct_on_checkout', 10, 1);
}


// -- Order cancel/refund: return credit (with GET_LOCK to prevent TOCTOU double-refund) --
if (!function_exists('gorilla_credit_refund_on_cancel')) {
    function gorilla_credit_refund_on_cancel($order_id) {
        global $wpdb;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $user_id = $order->get_customer_id();
        if (!$user_id) return;

        // Early bail if credit was never deducted for this order
        if ($order->get_meta('_gorilla_credit_deducted') !== 'yes') return;

        // Acquire MySQL advisory lock to serialize credit operations for this user
        $lock_key = 'gorilla_credit_' . $user_id;
        $lock = $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, 5)", $lock_key));
        if (!$lock) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Gorilla SC: could not acquire refund lock for user ' . $user_id . ' on order ' . $order_id);
            }
            return;
        }

        try {
            // Re-read order meta inside the lock to prevent TOCTOU race
            $order = wc_get_order($order_id);
            if (!$order) return;

            // Idempotency guard - check inside lock to prevent double-refund
            if ($order->get_meta('_gorilla_credit_refunded') === 'yes') return;

            // Check if credit was used for this order
            foreach ($order->get_fees() as $fee) {
                if (strpos($fee->get_name(), 'Store Credit') !== false) {
                    $refund = abs(floatval($fee->get_total()));
                    if ($refund > 0) {
                        // Mark as refunded BEFORE the actual adjustment (inside lock)
                        $order->update_meta_data('_gorilla_credit_refunded', 'yes');
                        $order->save();

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
        } finally {
            $wpdb->query($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_key));
        }
    }
    add_action('woocommerce_order_status_cancelled', 'gorilla_credit_refund_on_cancel');
    add_action('woocommerce_order_status_refunded', 'gorilla_credit_refund_on_cancel');
}
