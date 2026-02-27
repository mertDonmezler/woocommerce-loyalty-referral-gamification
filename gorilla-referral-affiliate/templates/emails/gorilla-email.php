<?php
/**
 * Gorilla RA - Email HTML Template
 *
 * WooCommerce standart email header/footer wrapper icinde
 * Gorilla email body icerigini render eder.
 *
 * Theme override: yourtheme/woocommerce/emails/gorilla-email.php
 *
 * @var string $email_heading
 * @var string $body_content
 * @var array  $email_data
 * @var object $email
 * @var bool   $sent_to_admin
 * @var bool   $plain_text
 */

if (!defined('ABSPATH')) exit;

do_action('woocommerce_email_header', $email_heading, $email);
?>

<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <?php echo wp_kses_post($body_content); ?>
</div>

<?php
if ($additional_content = $email->get_additional_content()) {
    echo '<div style="padding: 12px 0; color: #888; font-size: 13px;">' . wp_kses_post(wpautop(wptexturize($additional_content))) . '</div>';
}

do_action('woocommerce_email_footer', $email);
