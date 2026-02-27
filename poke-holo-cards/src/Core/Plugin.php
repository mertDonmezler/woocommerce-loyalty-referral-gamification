<?php
/**
 * Main plugin orchestrator.
 *
 * @package PokeHoloCards\Core
 * @since   3.0.0
 */

namespace PokeHoloCards\Core;

use PokeHoloCards\Admin\SettingsPage;
use PokeHoloCards\Admin\MetaBox;
use PokeHoloCards\Frontend\Shortcode;
use PokeHoloCards\Frontend\Gallery;
use PokeHoloCards\Frontend\Carousel;
use PokeHoloCards\Frontend\Collection;
use PokeHoloCards\Frontend\Compare;
use PokeHoloCards\Frontend\PackOpening;
use PokeHoloCards\Integration\WooCommerce;
use PokeHoloCards\Integration\ArViewer;
use PokeHoloCards\API\CardController;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin is the singleton entry point that wires all modules together.
 */
class Plugin {

    /** @var self|null Singleton instance. */
    private static $instance = null;

    /**
     * Get or create the singleton instance.
     *
     * @return self
     */
    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor - wire up all module hooks.
     */
    private function __construct() {
        $this->register_hooks();
    }

    /**
     * Initialize all modules.
     */
    private function register_hooks() {
        // Add monthly cron schedule if not already defined.
        add_filter( 'cron_schedules', function ( $schedules ) {
            if ( ! isset( $schedules['monthly'] ) ) {
                $schedules['monthly'] = array(
                    'interval' => 30 * DAY_IN_SECONDS,
                    'display'  => __( 'Once Monthly', 'poke-holo-cards' ),
                );
            }
            return $schedules;
        } );

        // i18n.
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Core.
        Enqueue::init();

        // Frontend.
        Shortcode::init();
        Gallery::init();
        Carousel::init();

        // Admin.
        SettingsPage::init();
        MetaBox::init();

        // API.
        CardController::init();

        // Collection, Compare, Pack Opening, AR (requires WooCommerce).
        if ( class_exists( 'WooCommerce' ) || defined( 'WC_VERSION' ) ) {
            Collection::init();
            Compare::init();
            PackOpening::init();
            ArViewer::init();
        }

        // Integrations.
        WooCommerce::init();
        add_action( 'init', array( $this, 'register_block' ) );
        add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widget' ) );
        add_action( 'init', array( $this, 'register_beaver_builder_module' ), 20 );
        add_action( 'et_builder_ready', array( $this, 'register_divi_module' ) );
        add_action( 'init', array( $this, 'register_bricks_element' ), 20 );
    }

    /**
     * Load plugin text domain.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'poke-holo-cards', false, dirname( plugin_basename( PHC_PLUGIN_DIR . 'poke-holo-cards.php' ) ) . '/languages' );
    }

    /**
     * Register the phc/holo-card Gutenberg block.
     */
    public function register_block() {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }
        $block_dir = PHC_PLUGIN_DIR . 'blocks/holo-card';
        if ( ! file_exists( $block_dir . '/block.json' ) ) {
            return;
        }
        register_block_type( $block_dir );
    }

    /**
     * Register the Holo Card Elementor widget.
     *
     * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
     */
    public function register_elementor_widget( $widgets_manager ) {
        $file = PHC_PLUGIN_DIR . 'includes/class-phc-elementor-widget.php';
        if ( ! file_exists( $file ) ) {
            return;
        }
        require_once $file;
        $widgets_manager->register( new \PHC_Elementor_Widget() );
    }

    /**
     * Register the Beaver Builder module.
     */
    public function register_beaver_builder_module() {
        if ( ! class_exists( 'FLBuilder' ) ) {
            return;
        }
        $file = PHC_PLUGIN_DIR . 'includes/class-phc-beaver-builder-module.php';
        if ( ! file_exists( $file ) ) {
            return;
        }
        require_once $file;
        \PHC_BB_Module::register();
    }

    /**
     * Register the Divi Builder module.
     */
    public function register_divi_module() {
        if ( ! class_exists( 'ET_Builder_Module' ) ) {
            return;
        }
        $file = PHC_PLUGIN_DIR . 'includes/class-phc-divi-module.php';
        if ( ! file_exists( $file ) ) {
            return;
        }
        require_once $file;
        new \PHC_Divi_Module();
    }

    /**
     * Register the Bricks Builder element.
     */
    public function register_bricks_element() {
        if ( ! defined( 'BRICKS_VERSION' ) || ! class_exists( '\Bricks\Elements' ) ) {
            return;
        }
        $file = PHC_PLUGIN_DIR . 'includes/class-phc-bricks-element.php';
        if ( ! file_exists( $file ) ) {
            return;
        }
        require_once $file;
        \Bricks\Elements::register_element( $file );
    }

    /**
     * Plugin activation callback.
     */
    public static function activate() {
        Settings::install_defaults();

        // Register endpoints before flushing so rewrite rules include them.
        \PokeHoloCards\Frontend\Collection::add_endpoint();
        \PokeHoloCards\Frontend\PackOpening::add_endpoint();
        flush_rewrite_rules();

        // Schedule analytics cleanup cron.
        SettingsPage::schedule_cleanup_cron();
    }

    /**
     * Plugin deactivation callback.
     */
    public static function deactivate() {
        SettingsPage::unschedule_cleanup_cron();
    }

    /**
     * Plugin uninstall callback.
     */
    public static function uninstall() {
        SettingsPage::unschedule_cleanup_cron();
        global $wpdb;

        // Remove plugin options.
        foreach ( Settings::option_keys() as $option ) {
            delete_option( $option );
        }
        delete_option( 'phc_analytics_data' );
        delete_option( 'phc_analytics_last_reset' );
        delete_option( 'phc_analytics_retention_days' );
        delete_option( 'phc_presets' );

        // Clean up per-product post meta.
        $meta_keys    = array( '_phc_effect_type', '_phc_glow_color', '_phc_enabled', '_phc_default_effect' );
        $placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders)", $meta_keys ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // Clean up term meta.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE meta_key = %s", '_phc_default_effect' ) );

        // Clean up user meta (Collection + Pack Opening).
        $user_meta_keys = array( '_phc_opened_orders', '_phc_unviewed_orders' );
        $user_placeholders = implode( ',', array_fill( 0, count( $user_meta_keys ), '%s' ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ($user_placeholders)", ...$user_meta_keys ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // Clean up collection transients (value + timeout rows).
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_phc_collection_%',
                '_transient_timeout_phc_collection_%'
            )
        );
    }
}
