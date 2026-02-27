<?php
/**
 * Gorilla RA - Email Plain Text Template
 *
 * Theme override: yourtheme/woocommerce/emails/plain/gorilla-email.php
 *
 * @var string $email_heading
 * @var string $body_content
 * @var array  $email_data
 * @var object $email
 * @var bool   $sent_to_admin
 * @var bool   $plain_text
 */

if (!defined('ABSPATH')) exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading));
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// HTML'den plain text'e cevir
echo wp_strip_all_tags(str_replace(
    array('<br>', '<br/>', '<br />', '</p>', '</li>', '</div>'),
    array("\n", "\n", "\n", "\n\n", "\n", "\n"),
    $body_content
));

echo "\n\n";

if ($additional_content = $email->get_additional_content()) {
    echo "---\n";
    echo wp_strip_all_tags(wptexturize($additional_content));
    echo "\n";
}

echo "\n" . apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'));
