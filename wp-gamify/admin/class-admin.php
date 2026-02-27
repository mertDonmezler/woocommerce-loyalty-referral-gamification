<?php
/**
 * WP Gamify Admin Panel
 *
 * Admin menu, asset loading, AJAX handlers.
 *
 * @package WPGamify
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class WPGamify_Admin {

    /** @var string Admin nonce action. */
    private const NONCE_ACTION = 'wpgamify_admin_nonce';

    /** @var string Required capability. */
    private const CAPABILITY = 'manage_woocommerce';

    /** @var string Menu slug prefix. */
    private const SLUG = 'wp-gamify';

    /**
     * Constructor - register hooks and sub-modules.
     */
    public function __construct() {
        $this->register();

        // Setup wizard (hidden page, registers its own menu).
        require_once WPGAMIFY_PATH . 'admin/class-setup-wizard.php';
        new WPGamify_Setup_Wizard();
    }

    /**
     * Register all admin hooks.
     */
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init', [ $this, 'handle_form_submissions' ] );
        add_action( 'admin_init', [ $this, 'maybe_show_wizard' ] );

        // AJAX handlers.
        add_action( 'wp_ajax_wpgamify_save_level', [ $this, 'ajax_save_level' ] );
        add_action( 'wp_ajax_wpgamify_delete_level', [ $this, 'ajax_delete_level' ] );
        add_action( 'wp_ajax_wpgamify_manual_xp', [ $this, 'ajax_manual_xp' ] );
        add_action( 'wp_ajax_wpgamify_search_users', [ $this, 'ajax_search_users' ] );
        add_action( 'wp_ajax_wpgamify_save_settings', [ $this, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_wpgamify_save_wizard', [ $this, 'ajax_save_wizard' ] );
        add_action( 'wp_ajax_wpgamify_reorder_levels', [ $this, 'ajax_reorder_levels' ] );
    }

    /**
     * Register admin menu pages.
     */
    public function register_menus(): void {
        add_menu_page(
            'WP Gamify',
            'WP Gamify',
            self::CAPABILITY,
            self::SLUG,
            [ $this, 'render_dashboard' ],
            'dashicons-star-filled',
            56
        );

        add_submenu_page(
            self::SLUG,
            'Dashboard - WP Gamify',
            'Dashboard',
            self::CAPABILITY,
            self::SLUG,
            [ $this, 'render_dashboard' ]
        );

        add_submenu_page(
            self::SLUG,
            'XP Ayarlari - WP Gamify',
            'XP Ayarlari',
            self::CAPABILITY,
            self::SLUG . '-settings',
            [ $this, 'render_settings' ]
        );

        add_submenu_page(
            self::SLUG,
            'Level Yonetimi - WP Gamify',
            'Level Yonetimi',
            self::CAPABILITY,
            self::SLUG . '-levels',
            [ $this, 'render_levels' ]
        );

        add_submenu_page(
            self::SLUG,
            'Manuel XP - WP Gamify',
            'Manuel XP',
            self::CAPABILITY,
            self::SLUG . '-manual-xp',
            [ $this, 'render_manual_xp' ]
        );

        add_submenu_page(
            self::SLUG,
            'Islem Logu - WP Gamify',
            'Islem Logu',
            self::CAPABILITY,
            self::SLUG . '-audit-log',
            [ $this, 'render_audit_log' ]
        );
    }

    /**
     * Enqueue admin CSS and JS only on WP Gamify pages.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( string $hook ): void {
        if ( ! $this->is_gamify_page( $hook ) ) {
            return;
        }

        $version = WPGAMIFY_VERSION;

        // CSS.
        wp_enqueue_style(
            'wpgamify-admin',
            WPGAMIFY_URL . 'admin/assets/admin.css',
            [],
            $version
        );
        wp_enqueue_style( 'wp-color-picker' );

        // JS.
        wp_enqueue_media();
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_script(
            'wpgamify-admin',
            WPGAMIFY_URL . 'admin/assets/admin.js',
            [ 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ],
            $version,
            true
        );

        wp_localize_script( 'wpgamify-admin', 'wpgamifyAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
            'i18n'    => [
                'confirmDelete'  => 'Bu leveli silmek istediginize emin misiniz?',
                'confirmXP'      => 'XP islemini onayliyor musunuz?',
                'saved'          => 'Basariyla kaydedildi.',
                'deleted'        => 'Basariyla silindi.',
                'error'          => 'Bir hata olustu.',
                'required'       => 'Lutfen zorunlu alanlari doldurun.',
                'searchMinChars' => 'En az 3 karakter girin.',
            ],
        ] );
    }

    /**
     * Handle non-AJAX form submissions on admin_init.
     */
    public function handle_form_submissions(): void {
        if ( ! isset( $_POST['wpgamify_form_action'] ) ) {
            return;
        }

        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( 'Yetkiniz yok.' );
        }

        check_admin_referer( self::NONCE_ACTION, '_wpgamify_nonce' );

        $action = sanitize_text_field( wp_unslash( $_POST['wpgamify_form_action'] ) );

        match ( $action ) {
            'save_settings' => $this->process_save_settings(),
            'save_campaign' => $this->process_save_campaign(),
            default         => null,
        };
    }

    /**
     * Redirect to setup wizard if not completed.
     */
    public function maybe_show_wizard(): void {
        if ( get_option( 'wpgamify_setup_complete' ) === 'yes' ) {
            return;
        }

        $screen = $_GET['page'] ?? '';
        if ( str_starts_with( $screen, 'wp-gamify' ) && $screen !== 'wp-gamify-wizard' ) {
            // Show wizard notice on gamify pages.
            add_action( 'admin_notices', function (): void {
                $url = admin_url( 'admin.php?page=wp-gamify-wizard' );
                echo '<div class="notice notice-info is-dismissible"><p>';
                echo '<strong>WP Gamify:</strong> Ilk kurulum sihirbazini tamamlayin. ';
                echo '<a href="' . esc_url( $url ) . '">Sihirbazi Baslat</a>';
                echo '</p></div>';
            } );
        }
    }

    /* ─── View Renderers ───────────────────────────────────────────────── */

    /**
     * Render dashboard page.
     */
    public function render_dashboard(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( 'Yetkiniz yok.' );
        }
        include WPGAMIFY_PATH . 'admin/views/dashboard.php';
    }

    /**
     * Render XP settings page.
     */
    public function render_settings(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( 'Yetkiniz yok.' );
        }
        include WPGAMIFY_PATH . 'admin/views/xp-settings.php';
    }

    /**
     * Render level management page.
     */
    public function render_levels(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( 'Yetkiniz yok.' );
        }
        include WPGAMIFY_PATH . 'admin/views/levels.php';
    }

    /**
     * Render manual XP page.
     */
    public function render_manual_xp(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( 'Yetkiniz yok.' );
        }
        include WPGAMIFY_PATH . 'admin/views/manual-xp.php';
    }

    /**
     * Render audit log page.
     */
    public function render_audit_log(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( 'Yetkiniz yok.' );
        }
        include WPGAMIFY_PATH . 'admin/views/audit-log.php';
    }

    /* ─── AJAX Handlers ────────────────────────────────────────────────── */

    /**
     * AJAX: Save or update a level.
     */
    public function ajax_save_level(): void {
        $this->verify_ajax();

        $id        = (int) ( $_POST['level_id'] ?? 0 );
        $data      = [
            'name'          => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
            'min_xp'        => max( 0, (int) ( $_POST['min_xp'] ?? 0 ) ),
            'color_hex'     => sanitize_hex_color( wp_unslash( $_POST['color_hex'] ?? '#6366f1' ) ),
            'icon_url'      => esc_url_raw( wp_unslash( $_POST['icon_url'] ?? '' ) ),
            'discount'      => min( 100, max( 0, (float) ( $_POST['discount'] ?? 0 ) ) ),
            'free_shipping' => (int) ! empty( $_POST['free_shipping'] ),
            'early_access'  => (int) ! empty( $_POST['early_access'] ),
            'installment'   => (int) ! empty( $_POST['installment'] ),
            'sort_order'    => max( 0, (int) ( $_POST['sort_order'] ?? 0 ) ),
        ];

        if ( empty( $data['name'] ) ) {
            wp_send_json_error( [ 'message' => 'Level adi zorunludur.' ] );
        }

        if ( $id > 0 ) {
            $result = WPGamify_Level_Manager::update_level( $id, $data );
        } else {
            $result = WPGamify_Level_Manager::create_level( $data );
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'message' => $id > 0 ? 'Level guncellendi.' : 'Level olusturuldu.',
            'level'   => $result,
        ] );
    }

    /**
     * AJAX: Delete a level.
     */
    public function ajax_delete_level(): void {
        $this->verify_ajax();

        $id = (int) ( $_POST['level_id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( [ 'message' => 'Gecersiz level ID.' ] );
        }

        $result = WPGamify_Level_Manager::delete_level( $id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => 'Level silindi.' ] );
    }

    /**
     * AJAX: Manual XP award or deduction.
     */
    public function ajax_manual_xp(): void {
        $this->verify_ajax();

        $user_id = (int) ( $_POST['user_id'] ?? 0 );
        $amount  = (int) ( $_POST['amount'] ?? 0 );
        $action  = sanitize_text_field( wp_unslash( $_POST['xp_action'] ?? 'add' ) );
        $reason  = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );

        if ( $user_id <= 0 || $amount <= 0 || empty( $reason ) ) {
            wp_send_json_error( [ 'message' => 'Kullanici, miktar ve sebep zorunludur.' ] );
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            wp_send_json_error( [ 'message' => 'Kullanici bulunamadi.' ] );
        }

        if ( $action === 'deduct' ) {
            $result = WPGamify_XP_Engine::deduct( $user_id, $amount, 'manual_admin', [
                'reason'   => $reason,
                'admin_id' => get_current_user_id(),
            ] );
        } else {
            $result = WPGamify_XP_Engine::award( $user_id, $amount, 'manual_admin', [
                'reason'   => $reason,
                'admin_id' => get_current_user_id(),
            ] );
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        // Log to audit table.
        $this->log_audit( $user_id, $action, $amount, $reason );

        $new_total = WPGamify_XP_Engine::get_total_xp( $user_id );

        wp_send_json_success( [
            'message'   => ( $action === 'deduct' ? 'XP cikarildi.' : 'XP eklendi.' ),
            'new_total' => $new_total,
        ] );
    }

    /**
     * AJAX: Search users by name or email.
     */
    public function ajax_search_users(): void {
        $this->verify_ajax();

        $term = sanitize_text_field( wp_unslash( $_POST['term'] ?? '' ) );
        if ( mb_strlen( $term ) < 3 ) {
            wp_send_json_success( [ 'users' => [] ] );
        }

        $users = get_users( [
            'search'  => '*' . $term . '*',
            'number'  => 10,
            'orderby' => 'display_name',
            'fields'  => [ 'ID', 'display_name', 'user_email' ],
        ] );

        $results = [];
        foreach ( $users as $user ) {
            $total_xp = WPGamify_XP_Engine::get_total_xp( (int) $user->ID );
            $results[] = [
                'id'           => (int) $user->ID,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'total_xp'     => $total_xp,
            ];
        }

        wp_send_json_success( [ 'users' => $results ] );
    }

    /**
     * AJAX: Save all settings.
     */
    public function ajax_save_settings(): void {
        $this->verify_ajax();

        $raw = wp_unslash( $_POST['settings'] ?? '' );
        if ( is_string( $raw ) ) {
            $settings = json_decode( $raw, true );
        } else {
            $settings = (array) $raw;
        }

        if ( empty( $settings ) || ! is_array( $settings ) ) {
            wp_send_json_error( [ 'message' => 'Gecersiz ayar verisi.' ] );
        }

        $sanitized = $this->sanitize_settings( $settings );
        WPGamify_Settings::save( $sanitized );

        wp_send_json_success( [ 'message' => 'Ayarlar kaydedildi.' ] );
    }

    /**
     * AJAX: Save setup wizard data.
     */
    public function ajax_save_wizard(): void {
        $this->verify_ajax();

        $step = (int) ( $_POST['step'] ?? 0 );

        match ( $step ) {
            2 => $this->wizard_save_xp_settings(),
            3 => $this->wizard_save_levels(),
            4 => $this->wizard_complete(),
            default => wp_send_json_error( [ 'message' => 'Gecersiz adim.' ] ),
        };
    }

    /**
     * AJAX: Reorder levels via drag-and-drop.
     */
    public function ajax_reorder_levels(): void {
        $this->verify_ajax();

        $order = array_map( 'intval', (array) ( $_POST['order'] ?? [] ) );
        if ( empty( $order ) ) {
            wp_send_json_error( [ 'message' => 'Gecersiz siralama.' ] );
        }

        foreach ( $order as $position => $level_id ) {
            WPGamify_Level_Manager::update_level( $level_id, [ 'sort_order' => $position ] );
        }

        wp_send_json_success( [ 'message' => 'Siralama guncellendi.' ] );
    }

    /* ─── Private Helpers ──────────────────────────────────────────────── */

    /**
     * Check if current page is a WP Gamify admin page.
     *
     * @param string $hook Admin page hook.
     * @return bool
     */
    private function is_gamify_page( string $hook ): bool {
        $gamify_pages = [
            'toplevel_page_wp-gamify',
            'wp-gamify_page_wp-gamify-settings',
            'wp-gamify_page_wp-gamify-levels',
            'wp-gamify_page_wp-gamify-manual-xp',
            'wp-gamify_page_wp-gamify-audit-log',
            'wp-gamify_page_wp-gamify-wizard',
        ];
        return in_array( $hook, $gamify_pages, true );
    }

    /**
     * Verify AJAX request: nonce + capability.
     */
    private function verify_ajax(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_send_json_error( [ 'message' => 'Yetkiniz yok.' ], 403 );
        }
    }

    /**
     * Sanitize settings array.
     *
     * @param array<string,mixed> $settings Raw settings.
     * @return array<string,mixed> Sanitized settings.
     */
    private function sanitize_settings( array $settings ): array {
        $sanitized = [];
        $int_keys  = [
            'order_xp_base', 'order_xp_per_currency', 'order_first_bonus',
            'review_xp', 'review_min_chars', 'login_xp',
            'birthday_xp', 'anniversary_xp', 'registration_xp',
            'streak_base_xp', 'streak_max_day', 'streak_tolerance',
            'rolling_months', 'grace_days', 'daily_xp_cap',
        ];
        $float_keys = [ 'streak_multiplier', 'campaign_multiplier' ];
        $bool_keys  = [
            'order_xp_enabled', 'review_xp_enabled', 'login_xp_enabled',
            'birthday_enabled', 'anniversary_enabled', 'registration_enabled',
            'streak_enabled', 'streak_cycle_reset', 'duplicate_review_block',
            'keep_data_on_uninstall',
        ];
        $text_keys  = [
            'level_mode', 'currency_label', 'campaign_label',
            'campaign_start', 'campaign_end',
        ];

        foreach ( $int_keys as $key ) {
            if ( isset( $settings[ $key ] ) ) {
                $sanitized[ $key ] = max( 0, (int) $settings[ $key ] );
            }
        }
        foreach ( $float_keys as $key ) {
            if ( isset( $settings[ $key ] ) ) {
                $sanitized[ $key ] = max( 0, (float) $settings[ $key ] );
            }
        }
        foreach ( $bool_keys as $key ) {
            if ( isset( $settings[ $key ] ) ) {
                $sanitized[ $key ] = (bool) $settings[ $key ];
            }
        }
        foreach ( $text_keys as $key ) {
            if ( isset( $settings[ $key ] ) ) {
                $sanitized[ $key ] = sanitize_text_field( wp_unslash( $settings[ $key ] ) );
            }
        }

        return $sanitized;
    }

    /**
     * Log admin action to audit table.
     *
     * @param int    $user_id Target user.
     * @param string $action  Action type.
     * @param int    $amount  XP amount.
     * @param string $reason  Reason.
     */
    private function log_audit( int $user_id, string $action, int $amount, string $reason ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'gamify_audit_log';

        $previous_xp = WPGamify_XP_Engine::get_total_xp( $user_id );

        $wpdb->insert( $table, [
            'admin_id'    => get_current_user_id(),
            'user_id'     => $user_id,
            'action'      => $action,
            'amount'      => $amount,
            'previous_xp' => $previous_xp,
            'new_xp'      => $action === 'deduct' ? $previous_xp - $amount : $previous_xp + $amount,
            'reason'      => $reason,
            'created_at'  => current_time( 'mysql' ),
        ], [ '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s' ] );
    }

    /**
     * Process settings form submission (non-AJAX).
     */
    private function process_save_settings(): void {
        $settings = $_POST['wpgamify'] ?? [];
        if ( ! is_array( $settings ) ) {
            return;
        }
        $sanitized = $this->sanitize_settings( $settings );
        WPGamify_Settings::save( $sanitized );

        add_settings_error( 'wpgamify', 'settings_saved', 'Ayarlar kaydedildi.', 'success' );
    }

    /**
     * Process campaign form submission.
     */
    private function process_save_campaign(): void {
        $multiplier = max( 1, (float) ( $_POST['campaign_multiplier'] ?? 1 ) );
        $label      = sanitize_text_field( wp_unslash( $_POST['campaign_label'] ?? '' ) );
        $start      = sanitize_text_field( wp_unslash( $_POST['campaign_start'] ?? '' ) );
        $end        = sanitize_text_field( wp_unslash( $_POST['campaign_end'] ?? '' ) );

        if ( ! empty( $_POST['clear_campaign'] ) ) {
            WPGamify_Campaign_Manager::clear_campaign();
            add_settings_error( 'wpgamify', 'campaign_cleared', 'Kampanya temizlendi.', 'success' );
            return;
        }

        if ( empty( $label ) || empty( $start ) || empty( $end ) ) {
            add_settings_error( 'wpgamify', 'campaign_error', 'Tum kampanya alanlari zorunludur.', 'error' );
            return;
        }

        WPGamify_Campaign_Manager::set_simple_campaign( $multiplier, $label, $start, $end );
        add_settings_error( 'wpgamify', 'campaign_saved', 'Kampanya kaydedildi.', 'success' );
    }

    /**
     * Wizard step 2: Save XP source toggles.
     */
    private function wizard_save_xp_settings(): void {
        $settings = [
            'order_xp_enabled'  => ! empty( $_POST['order_xp_enabled'] ),
            'review_xp_enabled' => ! empty( $_POST['review_xp_enabled'] ),
            'login_xp_enabled'  => ! empty( $_POST['login_xp_enabled'] ),
            'streak_enabled'    => ! empty( $_POST['streak_enabled'] ),
        ];
        WPGamify_Settings::save( $settings );
        wp_send_json_success( [ 'message' => 'XP ayarlari kaydedildi.' ] );
    }

    /**
     * Wizard step 3: Confirm default levels.
     */
    private function wizard_save_levels(): void {
        // Levels already created by activator; just mark as confirmed.
        wp_send_json_success( [ 'message' => 'Leveller onaylandi.' ] );
    }

    /**
     * Wizard step 4: Mark setup complete.
     */
    private function wizard_complete(): void {
        update_option( 'wpgamify_setup_complete', 'yes' );
        wp_send_json_success( [
            'message'  => 'Kurulum tamamlandi!',
            'redirect' => admin_url( 'admin.php?page=wp-gamify' ),
        ] );
    }
}
