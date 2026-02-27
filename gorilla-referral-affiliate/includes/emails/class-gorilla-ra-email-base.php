<?php
/**
 * Gorilla RA - WC_Email Base Class
 * Tum Gorilla RA email'leri bu siniftan turetilir.
 *
 * @package Gorilla_Referral_Affiliate
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('Gorilla_RA_Email_Base')) {
abstract class Gorilla_RA_Email_Base extends WC_Email {

    /**
     * Email body icerigi (HTML).
     * @var string
     */
    public $body_content = '';

    /**
     * Email'e ozgu data (template'e aktarilir).
     * @var array
     */
    public $email_data = array();

    /**
     * Constructor.
     */
    public function __construct() {
        // Template yollari
        $this->template_base  = GORILLA_RA_PATH . 'templates/';
        $this->template_html  = 'emails/gorilla-email.php';
        $this->template_plain = 'emails/plain/gorilla-email.php';

        // Parent constructor
        parent::__construct();
    }

    /**
     * get_content_html - HTML template render.
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            array(
                'email_heading' => $this->get_heading(),
                'body_content'  => $this->body_content,
                'email_data'    => $this->email_data,
                'email'         => $this,
                'sent_to_admin' => $this->is_admin_email(),
                'plain_text'    => false,
            ),
            '',
            $this->template_base
        );
    }

    /**
     * get_content_plain - Plain text template render.
     */
    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            array(
                'email_heading' => $this->get_heading(),
                'body_content'  => $this->body_content,
                'email_data'    => $this->email_data,
                'email'         => $this,
                'sent_to_admin' => $this->is_admin_email(),
                'plain_text'    => true,
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Admin email mi?
     */
    protected function is_admin_email() {
        return false;
    }

    /**
     * Default subject/heading'deki {placeholders} icin degiskenleri doldur.
     */
    public function get_default_additional_content() {
        return '';
    }

    /**
     * Ortak placeholder'lari coz.
     */
    public function format_string($string) {
        $find    = array('{site_title}', '{site_url}');
        $replace = array($this->get_blogname(), home_url());

        // Email data icindeki placeholder'lari ekle
        foreach ($this->email_data as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $find[]    = '{' . $key . '}';
                $replace[] = $value;
            }
        }

        return str_replace($find, $replace, parent::format_string($string));
    }
}
} // end class_exists Gorilla_RA_Email_Base
