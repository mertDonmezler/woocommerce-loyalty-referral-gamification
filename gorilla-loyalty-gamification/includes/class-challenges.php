<?php
/**
 * Gorilla Loyalty & Gamification - Challenge/Quest System
 *
 * Daily, weekly, and one-time quests with XP/credit rewards.
 * Challenges stored in gorilla_lr_challenges option.
 * User progress stored in _gorilla_challenges_progress user_meta.
 *
 * @package Gorilla_Loyalty_Gamification
 * @since   3.1.0
 */

if (!defined('ABSPATH')) exit;


// ── Challenge Definitions ────────────────────────────────

function gorilla_challenges_get_all($force_refresh = false) {
    static $cached = null;
    if ($cached !== null && !$force_refresh) return $cached;

    $challenges = get_option('gorilla_lr_challenges', array());
    if (!is_array($challenges)) $challenges = array();

    $cached = $challenges;
    return $cached;
}

function gorilla_challenges_defaults() {
    return array(
        array(
            'id'          => 'weekly_3_orders',
            'title'       => 'Bu Hafta 3 Siparis Ver',
            'description' => 'Bu hafta icerisinde 3 siparis tamamlayin.',
            'type'        => 'orders',
            'target'      => 3,
            'period'      => 'weekly',
            'reward_type' => 'xp',
            'reward_amount' => 200,
            'emoji'       => '',
            'active'      => true,
        ),
        array(
            'id'          => 'weekly_review',
            'title'       => '3 Urun Yorumla',
            'description' => 'Bu hafta 3 urun degerlendirmesi yapin.',
            'type'        => 'reviews',
            'target'      => 3,
            'period'      => 'weekly',
            'reward_type' => 'xp',
            'reward_amount' => 100,
            'emoji'       => '',
            'active'      => true,
        ),
        array(
            'id'          => 'first_referral',
            'title'       => 'Ilk Referansinizi Gonderin',
            'description' => 'Video referans programina ilk basvurunuzu yapin.',
            'type'        => 'referrals',
            'target'      => 1,
            'period'      => 'one_time',
            'reward_type' => 'xp',
            'reward_amount' => 150,
            'emoji'       => '',
            'active'      => true,
        ),
        array(
            'id'          => 'spend_5000',
            'title'       => '5000 TL Harcama Yap',
            'description' => 'Toplam 5000 TL harcama yaparak bu gorevi tamamlayin.',
            'type'        => 'spending',
            'target'      => 5000,
            'period'      => 'one_time',
            'reward_type' => 'credit',
            'reward_amount' => 100,
            'emoji'       => '',
            'active'      => true,
        ),
    );
}


// ── User Progress ────────────────────────────────────────

function gorilla_challenges_get_progress($user_id) {
    $progress = get_user_meta($user_id, '_gorilla_challenges_progress', true);
    if (!is_array($progress)) return array();
    return $progress;
}

function gorilla_challenges_current_value($user_id, $challenge) {
    $progress  = gorilla_challenges_get_progress($user_id);
    $cid       = $challenge['id'] ?? '';
    $period    = $challenge['period'] ?? 'one_time';

    if ($period === 'one_time' && !empty($progress[$cid]['completed'])) {
        return intval($challenge['target'] ?? 0);
    }

    $period_key = gorilla_challenges_period_key($period);
    $stored     = $progress[$cid] ?? array();

    if (($stored['period_key'] ?? '') !== $period_key && $period !== 'one_time') {
        return 0;
    }

    return intval($stored['current'] ?? 0);
}

function gorilla_challenges_period_key($period) {
    if ($period === 'daily') {
        return current_time('Y-m-d');
    }
    if ($period === 'weekly') {
        return current_time('o-\\WW');
    }
    return 'once';
}

function gorilla_challenges_increment($user_id, $type, $amount = 1) {
    if (!$user_id || $amount <= 0) return;
    if (get_option('gorilla_lr_challenges_enabled', 'no') !== 'yes') return;

    global $wpdb;
    $lock_name = "gorilla_challenges_{$user_id}";
    $got_lock = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $lock_name));
    if (!$got_lock) return;
    try {
        $challenges = gorilla_challenges_get_all();
        if (empty($challenges)) return;

        $progress = gorilla_challenges_get_progress($user_id);
        $changed  = false;

        foreach ($challenges as $challenge) {
            if (!is_array($challenge) || empty($challenge['active'])) continue;
            if (($challenge['type'] ?? '') !== $type) continue;

            $cid        = $challenge['id'] ?? '';
            $period     = $challenge['period'] ?? 'one_time';
            $target     = intval($challenge['target'] ?? 0);
            $period_key = gorilla_challenges_period_key($period);

            if (!$cid || $target <= 0) continue;

            $entry = $progress[$cid] ?? array();

            if ($period === 'one_time' && !empty($entry['completed'])) continue;

            if ($period !== 'one_time' && ($entry['period_key'] ?? '') !== $period_key) {
                $entry = array('current' => 0, 'period_key' => $period_key, 'completed' => false);
            }

            if (!empty($entry['completed'])) continue;

            $entry['current']    = min($target, intval($entry['current'] ?? 0) + $amount);
            $entry['period_key'] = $period_key;

            if ($entry['current'] >= $target && !$entry['completed']) {
                $entry['completed']    = true;
                $entry['completed_at'] = current_time('mysql');
                gorilla_challenges_award_reward($user_id, $challenge);
            }

            $progress[$cid] = $entry;
            $changed = true;
        }

        if ($changed) {
            update_user_meta($user_id, '_gorilla_challenges_progress', $progress);
        }
    } finally {
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
    }
}


// ── Reward ───────────────────────────────────────────────

function gorilla_challenges_award_reward($user_id, $challenge) {
    $reward_type   = $challenge['reward_type'] ?? 'xp';
    $reward_amount = intval($challenge['reward_amount'] ?? 0);
    $title         = $challenge['title'] ?? 'Gorev';

    if ($reward_amount <= 0) return;

    if ($reward_type === 'xp' && function_exists('gorilla_xp_add')) {
        gorilla_xp_add($user_id, $reward_amount, 'Gorev tamamlandi: ' . $title);
    } elseif ($reward_type === 'credit' && function_exists('gorilla_credit_adjust')) {
        gorilla_credit_adjust($user_id, $reward_amount, 'challenge', 'Gorev tamamlandi: ' . $title);
    }

    if (!empty($challenge['reward_badge']) && function_exists('gorilla_badge_award')) {
        gorilla_badge_award($user_id, $challenge['reward_badge']);
    }

    if (function_exists('gorilla_send_email') && function_exists('gorilla_email_template')) {
        $user = get_userdata($user_id);
        if ($user) {
            $reward_label = ($reward_type === 'xp')
                ? number_format_i18n($reward_amount) . ' XP'
                : wc_price($reward_amount) . ' Credit';

            $subject = sprintf('%s Gorev Tamamlandi! - %s', $challenge['emoji'] ?? '', get_bloginfo('name'));
            $message = gorilla_email_template(
                'Gorev Tamamlandi!',
                sprintf(
                    '<p style="font-size:16px;">Merhaba <strong>%s</strong>,</p>
                    <div style="background:#f0fdf4; border:2px solid #22c55e; border-radius:14px; padding:25px; text-align:center; margin:16px 0;">
                        <div style="font-size:48px;">%s</div>
                        <div style="font-size:20px; font-weight:700; margin:8px 0;">%s</div>
                        <div style="font-size:16px; color:#22c55e; font-weight:600;">+%s kazandiniz!</div>
                    </div>
                    <p>Yeni gorevlere goz atin ve odullerinizi toplamaya devam edin!</p>',
                    esc_html($user->display_name),
                    esc_html($challenge['emoji'] ?? ''),
                    esc_html($title),
                    esc_html($reward_label)
                )
            );
            gorilla_send_email($user->user_email, $subject, $message);
        }
    }
}


// ── WooCommerce Hooks ────────────────────────────────────

add_action('woocommerce_order_status_completed', 'gorilla_challenges_on_order_complete', 25);
add_action('woocommerce_order_status_processing', 'gorilla_challenges_on_order_complete', 25);
function gorilla_challenges_on_order_complete($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    $user_id = $order->get_customer_id();
    if (!$user_id) return;

    gorilla_challenges_increment($user_id, 'orders', 1);

    $total = floatval($order->get_total());
    if ($total > 0) {
        gorilla_challenges_increment($user_id, 'spending', intval($total));
    }
}

add_action('comment_post', 'gorilla_challenges_on_review', 20, 3);
function gorilla_challenges_on_review($comment_id, $comment_approved, $commentdata) {
    if ($comment_approved !== 1 && $comment_approved !== '1') return;

    $comment = get_comment($comment_id);
    if (!$comment || !$comment->user_id) return;

    if (get_comment_meta($comment_id, 'rating', true) === '') return;

    gorilla_challenges_increment(intval($comment->user_id), 'reviews', 1);
}

add_action('comment_unapproved_to_approved', 'gorilla_challenges_on_review_approved');
function gorilla_challenges_on_review_approved($comment) {
    if (!$comment || !$comment->user_id) return;

    if (get_comment_meta($comment->comment_ID, 'rating', true) === '') return;

    gorilla_challenges_increment(intval($comment->user_id), 'reviews', 1);
}

add_action('save_post_gorilla_referral', 'gorilla_challenges_on_referral', 20, 3);
function gorilla_challenges_on_referral($post_id, $post, $update) {
    if ($update) return;
    $user_id = intval(get_post_meta($post_id, '_ref_user_id', true));
    if (!$user_id) return;

    gorilla_challenges_increment($user_id, 'referrals', 1);
}


// ── Admin: Challenge Settings ────────────────────────────

function gorilla_challenges_save_settings() {
    $enabled = (isset($_POST['challenges_enabled']) && $_POST['challenges_enabled'] === 'yes') ? 'yes' : 'no';
    update_option('gorilla_lr_challenges_enabled', $enabled);

    if (isset($_POST['gorilla_challenges']) && is_array($_POST['gorilla_challenges'])) {
        $raw = $_POST['gorilla_challenges'];
        $clean = array();

        foreach ($raw as $ch) {
            if (!is_array($ch)) continue;
            $id = sanitize_key($ch['id'] ?? '');
            if (empty($id)) $id = 'challenge_' . wp_generate_password(6, false);

            $clean[] = array(
                'id'            => $id,
                'title'         => sanitize_text_field($ch['title'] ?? ''),
                'description'   => sanitize_text_field($ch['description'] ?? ''),
                'type'          => in_array($ch['type'] ?? '', array('orders', 'reviews', 'referrals', 'spending'), true) ? $ch['type'] : 'orders',
                'target'        => max(1, intval($ch['target'] ?? 1)),
                'period'        => in_array($ch['period'] ?? '', array('daily', 'weekly', 'one_time'), true) ? $ch['period'] : 'one_time',
                'reward_type'   => in_array($ch['reward_type'] ?? '', array('xp', 'credit'), true) ? $ch['reward_type'] : 'xp',
                'reward_amount' => max(1, intval($ch['reward_amount'] ?? 50)),
                'emoji'         => sanitize_text_field($ch['emoji'] ?? ''),
                'active'        => !empty($ch['active']),
            );
        }

        update_option('gorilla_lr_challenges', $clean);
    }
}


// ── Frontend: Render Challenges Section ──────────────────

function gorilla_challenges_render($user_id) {
    if (get_option('gorilla_lr_challenges_enabled', 'no') !== 'yes') return;

    $challenges = gorilla_challenges_get_all();
    if (empty($challenges)) return;

    $progress = gorilla_challenges_get_progress($user_id);
    $period_labels = array('daily' => 'Gunluk', 'weekly' => 'Haftalik', 'one_time' => 'Tek Seferlik');
    $type_labels   = array('orders' => 'Siparis', 'reviews' => 'Yorum', 'referrals' => 'Referans', 'spending' => 'Harcama');
    ?>
    <hr style="border:none; border-top:2px dashed #e5e7eb; margin:35px 0;">
    <h2 style="font-size:24px; font-weight:800; margin-bottom:20px;">Gorevler</h2>
    <div style="display:flex; flex-direction:column; gap:12px;">
    <?php
    foreach ($challenges as $challenge):
        if (!is_array($challenge) || empty($challenge['active'])) continue;

        $cid        = $challenge['id'] ?? '';
        $target     = intval($challenge['target'] ?? 0);
        $period     = $challenge['period'] ?? 'one_time';
        $period_key = gorilla_challenges_period_key($period);
        $entry      = $progress[$cid] ?? array();

        $is_completed = false;
        $current      = 0;

        if ($period === 'one_time') {
            $is_completed = !empty($entry['completed']);
            $current      = $is_completed ? $target : intval($entry['current'] ?? 0);
        } else {
            if (($entry['period_key'] ?? '') === $period_key) {
                $is_completed = !empty($entry['completed']);
                $current      = intval($entry['current'] ?? 0);
            }
        }

        $pct = ($target > 0) ? min(100, round(($current / $target) * 100)) : 0;

        $reward_label = ($challenge['reward_type'] ?? 'xp') === 'xp'
            ? number_format_i18n(intval($challenge['reward_amount'] ?? 0)) . ' XP'
            : wc_price(intval($challenge['reward_amount'] ?? 0)) . ' Credit';

        $period_label = $period_labels[$period] ?? '';
        $type_label   = $type_labels[$challenge['type'] ?? ''] ?? '';
    ?>
        <div class="glr-card" style="padding:16px 20px; <?php echo $is_completed ? 'border-color:#22c55e; background:linear-gradient(180deg,#f0fdf4,#fff);' : ''; ?>">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:28px;"><?php echo esc_html($challenge['emoji'] ?? ''); ?></span>
                    <div>
                        <div style="font-weight:700; font-size:14px;"><?php echo esc_html($challenge['title'] ?? ''); ?></div>
                        <div style="font-size:12px; color:#6b7280;"><?php echo esc_html($challenge['description'] ?? ''); ?></div>
                    </div>
                </div>
                <div style="text-align:right; flex-shrink:0;">
                    <?php if ($is_completed): ?>
                        <span style="background:#dcfce7; color:#16a34a; padding:3px 10px; border-radius:10px; font-size:11px; font-weight:700;">&#10003; Tamamlandi</span>
                    <?php else: ?>
                        <span style="background:#f3f4f6; color:#6b7280; padding:3px 10px; border-radius:10px; font-size:11px;"><?php echo esc_html($period_label); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <div class="glr-progress-track" style="flex:1; margin:0; height:14px;">
                    <div class="glr-progress-bar" style="width:<?php echo $pct; ?>%; background:<?php echo $is_completed ? '#22c55e' : 'linear-gradient(90deg, #8b5cf6, #6366f1)'; ?>;">
                        <?php echo $pct; ?>%
                    </div>
                </div>
                <span style="font-size:12px; color:#6b7280; white-space:nowrap;"><?php echo $current; ?>/<?php echo $target; ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-top:8px; font-size:11px; color:#9ca3af;">
                <span><?php echo esc_html($type_label); ?></span>
                <span style="font-weight:600; color:#8b5cf6;"><?php echo $reward_label; ?></span>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php
}
