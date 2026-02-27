<?php
/**
 * WP Gamify Setup Wizard
 *
 * Ilk kurulum sihirbazi - 4 adimli basit form.
 *
 * @package WPGamify
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class WPGamify_Setup_Wizard {

    /** @var int Current step (1-4). */
    private int $current_step;

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
    }

    /**
     * Register hidden admin page for wizard.
     */
    public function register_page(): void {
        add_submenu_page(
            null, // Hidden from menu.
            'Kurulum Sihirbazi - WP Gamify',
            'Kurulum Sihirbazi',
            'manage_woocommerce',
            'wp-gamify-wizard',
            [ $this, 'render' ]
        );
    }

    /**
     * Render the setup wizard.
     */
    public function render(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Yetkiniz yok.' );
        }

        $this->current_step = max( 1, min( 4, (int) ( $_GET['step'] ?? 1 ) ) );
        $settings            = WPGamify_Settings::get_all();
        $levels              = WPGamify_Level_Manager::get_all_levels();

        ?>
        <div class="wrap wpgamify-wizard">
            <h1>WP Gamify Kurulum Sihirbazi</h1>

            <div class="wpgamify-wizard-steps">
                <?php $this->render_step_indicators(); ?>
            </div>

            <div class="wpgamify-wizard-content">
                <?php
                match ( $this->current_step ) {
                    1 => $this->render_step_welcome(),
                    2 => $this->render_step_xp( $settings ),
                    3 => $this->render_step_levels( $levels ),
                    4 => $this->render_step_complete(),
                };
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render step indicator bar.
     */
    private function render_step_indicators(): void {
        $steps = [
            1 => 'Hos Geldiniz',
            2 => 'XP Ayarlari',
            3 => 'Level Sistemi',
            4 => 'Hazir!',
        ];

        echo '<ol class="wpgamify-wizard-step-list">';
        foreach ( $steps as $num => $label ) {
            $class = 'wpgamify-wizard-step';
            if ( $num < $this->current_step ) {
                $class .= ' completed';
            } elseif ( $num === $this->current_step ) {
                $class .= ' active';
            }
            printf(
                '<li class="%s"><span class="step-number">%d</span><span class="step-label">%s</span></li>',
                esc_attr( $class ),
                $num,
                esc_html( $label )
            );
        }
        echo '</ol>';
    }

    /**
     * Step 1: Welcome.
     */
    private function render_step_welcome(): void {
        ?>
        <div class="wpgamify-wizard-panel">
            <div class="wpgamify-wizard-icon">
                <span class="dashicons dashicons-star-filled"></span>
            </div>
            <h2>WP Gamify'a Hos Geldiniz!</h2>
            <p>
                WP Gamify, WooCommerce magazaniz icin gelismis bir gamification altyapisi sunar.
                Musterileriniz alisveris yaptikca, yorum yazdikca ve giris yaptikca XP kazanir,
                seviye atlar ve ozel avantajlar elde eder.
            </p>
            <ul class="wpgamify-feature-list">
                <li><span class="dashicons dashicons-yes-alt"></span> Siparis, yorum ve giris bazli XP sistemi</li>
                <li><span class="dashicons dashicons-yes-alt"></span> Ozellestirilabilir level sistemi</li>
                <li><span class="dashicons dashicons-yes-alt"></span> Giris serisi (streak) ile artan oduller</li>
                <li><span class="dashicons dashicons-yes-alt"></span> Dogum gunu ve yildonumu XP bonuslari</li>
                <li><span class="dashicons dashicons-yes-alt"></span> Anti-abuse korumasi</li>
            </ul>
            <p class="wpgamify-wizard-note">
                Bu sihirbaz birka&ccedil; dakika icerisinde temel ayarlari yapmanizi saglayacak.
                Daha sonra tum ayarlari detayli olarak duzenleyebilirsiniz.
            </p>
            <div class="wpgamify-wizard-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-gamify-wizard&step=2' ) ); ?>"
                   class="button button-primary button-hero">Baslayalim</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-gamify' ) ); ?>"
                   class="button button-link">Sihirbazi Atla</a>
            </div>
        </div>
        <?php
    }

    /**
     * Step 2: XP source toggles.
     *
     * @param array<string,mixed> $settings Current settings.
     */
    private function render_step_xp( array $settings ): void {
        ?>
        <div class="wpgamify-wizard-panel">
            <h2>XP Kaynaklari</h2>
            <p>Hangi aksiyonlarin XP kazandirmasini istiyorsunuz? (Daha sonra detayli ayarlayabilirsiniz)</p>

            <form id="wpgamify-wizard-xp-form" class="wpgamify-wizard-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">Siparis XP</th>
                        <td>
                            <label>
                                <input type="checkbox" name="order_xp_enabled" value="1"
                                    <?php checked( $settings['order_xp_enabled'] ?? true ); ?>>
                                Musteriler siparis verdiklerinde XP kazansin
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Yorum XP</th>
                        <td>
                            <label>
                                <input type="checkbox" name="review_xp_enabled" value="1"
                                    <?php checked( $settings['review_xp_enabled'] ?? true ); ?>>
                                Urun yorumu yazan musteriler XP kazansin
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Giris XP</th>
                        <td>
                            <label>
                                <input type="checkbox" name="login_xp_enabled" value="1"
                                    <?php checked( $settings['login_xp_enabled'] ?? true ); ?>>
                                Gunluk giris yapan musteriler XP kazansin
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Giris Serisi</th>
                        <td>
                            <label>
                                <input type="checkbox" name="streak_enabled" value="1"
                                    <?php checked( $settings['streak_enabled'] ?? true ); ?>>
                                Ust uste giris yapanlara artan XP bonusu verilsin
                            </label>
                        </td>
                    </tr>
                </table>

                <div class="wpgamify-wizard-actions">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-gamify-wizard&step=1' ) ); ?>"
                       class="button">Geri</a>
                    <button type="button" class="button button-primary" id="wpgamify-wizard-save-xp">
                        Devam Et
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Step 3: Confirm or edit default levels.
     *
     * @param array<int,object|array> $levels Current levels.
     */
    private function render_step_levels( array $levels ): void {
        ?>
        <div class="wpgamify-wizard-panel">
            <h2>Level Sistemi</h2>
            <p>Asagidaki varsayilan leveller olusturuldu. Bunlari daha sonra detayli olarak duzenleyebilirsiniz.</p>

            <?php if ( ! empty( $levels ) ) : ?>
                <table class="widefat striped wpgamify-wizard-levels-table">
                    <thead>
                        <tr>
                            <th>Sira</th>
                            <th>Level Adi</th>
                            <th>Gerekli XP</th>
                            <th>Indirim %</th>
                            <th>Renk</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $levels as $i => $level ) :
                            $level = (array) $level;
                            ?>
                            <tr>
                                <td><?php echo esc_html( $i + 1 ); ?></td>
                                <td><?php echo esc_html( $level['name'] ?? '' ); ?></td>
                                <td><?php echo number_format_i18n( $level['min_xp'] ?? 0 ); ?></td>
                                <td><?php echo esc_html( $level['discount'] ?? 0 ); ?>%</td>
                                <td>
                                    <span class="wpgamify-color-swatch"
                                          style="background-color: <?php echo esc_attr( $level['color_hex'] ?? '#6366f1' ); ?>">
                                    </span>
                                    <?php echo esc_html( $level['color_hex'] ?? '#6366f1' ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="description">Henuz level olusturulmamis. Varsayilan leveller aktivasyon sirasinda olusturulur.</p>
            <?php endif; ?>

            <div class="wpgamify-wizard-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-gamify-wizard&step=2' ) ); ?>"
                   class="button">Geri</a>
                <button type="button" class="button button-primary" id="wpgamify-wizard-confirm-levels">
                    Onayla ve Devam Et
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Step 4: Complete.
     */
    private function render_step_complete(): void {
        ?>
        <div class="wpgamify-wizard-panel wpgamify-wizard-complete">
            <div class="wpgamify-wizard-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <h2>Her Sey Hazir!</h2>
            <p>WP Gamify basariyla yapilandirildi. Artik musterileriniz XP kazanmaya baslayabilir.</p>

            <div class="wpgamify-wizard-actions">
                <button type="button" class="button button-primary button-hero" id="wpgamify-wizard-finish">
                    Dashboard'a Git
                </button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-gamify-settings' ) ); ?>"
                   class="button button-link">Detayli Ayarlar</a>
            </div>
        </div>
        <?php
    }
}
