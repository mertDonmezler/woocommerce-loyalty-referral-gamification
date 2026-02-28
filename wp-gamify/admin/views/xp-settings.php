<?php
/**
 * WP Gamify XP Settings Page
 *
 * @package WPGamify
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$settings = WPGamify_Settings::get_all();
$campaign = class_exists( 'WPGamify_Campaign_Manager' )
    ? WPGamify_Campaign_Manager::get_active_campaign()
    : null;

?>
<div class="wrap wpgamify-wrap">
    <h1 class="wp-heading-inline">XP Ayarlari</h1>
    <hr class="wp-header-end">

    <?php settings_errors( 'wpgamify' ); ?>

    <form method="post" action="" id="wpgamify-settings-form">
        <?php wp_nonce_field( 'wpgamify_admin_nonce', '_wpgamify_nonce' ); ?>
        <input type="hidden" name="wpgamify_form_action" value="save_settings">

        <!-- Section: Siparis XP -->
        <div class="wpgamify-settings-section">
            <h2>Siparis XP</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Durum</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpgamify[order_xp_enabled]" value="1"
                                <?php checked( $settings['xp_order_enabled'] ?? true ); ?>>
                            Siparis XP aktif
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Temel XP</th>
                    <td>
                        <input type="number" name="wpgamify[order_xp_base]" min="0"
                               value="<?php echo esc_attr( $settings['xp_order_base'] ?? 10 ); ?>"
                               class="small-text">
                        <p class="description">Her siparise verilen sabit XP miktari.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Para Birimi Basina XP</th>
                    <td>
                        <input type="number" name="wpgamify[order_xp_per_currency]" min="0"
                               value="<?php echo esc_attr( $settings['xp_order_per_currency'] ?? 1 ); ?>"
                               class="small-text">
                        <p class="description">Harcanan her 1 birim para icin ekstra XP.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Ilk Siparis Bonusu</th>
                    <td>
                        <input type="number" name="wpgamify[order_first_bonus]" min="0"
                               value="<?php echo esc_attr( $settings['xp_first_order_bonus'] ?? 50 ); ?>"
                               class="small-text">
                        <p class="description">Musterinin ilk siparisine ek XP bonusu.</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Section: Yorum XP -->
        <div class="wpgamify-settings-section">
            <h2>Yorum XP</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Durum</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpgamify[review_xp_enabled]" value="1"
                                <?php checked( $settings['xp_review_enabled'] ?? true ); ?>>
                            Yorum XP aktif
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Yorum XP Miktari</th>
                    <td>
                        <input type="number" name="wpgamify[review_xp]" min="0"
                               value="<?php echo esc_attr( $settings['xp_review_amount'] ?? 15 ); ?>"
                               class="small-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Minimum Karakter</th>
                    <td>
                        <input type="number" name="wpgamify[review_min_chars]" min="0"
                               value="<?php echo esc_attr( $settings['xp_review_min_chars'] ?? 20 ); ?>"
                               class="small-text">
                        <p class="description">Yorumun XP icin minimum karakter sayisi.</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Section: Giris XP -->
        <div class="wpgamify-settings-section">
            <h2>Giris XP</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Durum</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpgamify[login_xp_enabled]" value="1"
                                <?php checked( $settings['xp_login_enabled'] ?? true ); ?>>
                            Gunluk giris XP aktif
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Giris XP Miktari</th>
                    <td>
                        <input type="number" name="wpgamify[login_xp]" min="0"
                               value="<?php echo esc_attr( $settings['xp_login_amount'] ?? 5 ); ?>"
                               class="small-text">
                    </td>
                </tr>
            </table>
        </div>

        <!-- Section: Ozel Gunler -->
        <div class="wpgamify-settings-section">
            <h2>Ozel Gunler</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Dogum Gunu</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpgamify[birthday_enabled]" value="1"
                                <?php checked( $settings['xp_birthday_enabled'] ?? true ); ?>>
                            Aktif
                        </label>
                        <br>
                        <input type="number" name="wpgamify[birthday_xp]" min="0"
                               value="<?php echo esc_attr( $settings['xp_birthday_amount'] ?? 100 ); ?>"
                               class="small-text"> XP
                    </td>
                </tr>
                <tr>
                    <th scope="row">Uyelik Yildonumu</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpgamify[anniversary_enabled]" value="1"
                                <?php checked( $settings['xp_anniversary_enabled'] ?? true ); ?>>
                            Aktif
                        </label>
                        <br>
                        <input type="number" name="wpgamify[anniversary_xp]" min="0"
                               value="<?php echo esc_attr( $settings['xp_anniversary_amount'] ?? 50 ); ?>"
                               class="small-text"> XP
                    </td>
                </tr>
                <tr>
                    <th scope="row">Ilk Kayit Bonusu</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpgamify[registration_enabled]" value="1"
                                <?php checked( $settings['xp_registration_enabled'] ?? true ); ?>>
                            Aktif
                        </label>
                        <br>
                        <input type="number" name="wpgamify[registration_xp]" min="0"
                               value="<?php echo esc_attr( $settings['xp_registration_amount'] ?? 25 ); ?>"
                               class="small-text"> XP
                    </td>
                </tr>
            </table>
        </div>

        <!-- Section: Giris Serisi (Streak) -->
        <div class="wpgamify-settings-section">
            <h2>Giris Serisi (Streak)</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Durum</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpgamify[streak_enabled]" value="1"
                                <?php checked( $settings['streak_enabled'] ?? true ); ?>>
                            Streak sistemi aktif
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Temel XP</th>
                    <td>
                        <input type="number" name="wpgamify[streak_base_xp]" min="0"
                               value="<?php echo esc_attr( $settings['streak_base_xp'] ?? 5 ); ?>"
                               class="small-text">
                        <p class="description">Streak'in 1. gununde verilen XP.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Carpan</th>
                    <td>
                        <input type="number" name="wpgamify[streak_multiplier]" min="0" step="0.1"
                               value="<?php echo esc_attr( $settings['streak_multiplier'] ?? 1.5 ); ?>"
                               class="small-text">
                        <p class="description">Her gun streak XP'si bu oranda artar.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Maksimum Gun</th>
                    <td>
                        <input type="number" name="wpgamify[streak_max_day]" min="1"
                               value="<?php echo esc_attr( $settings['streak_max_day'] ?? 30 ); ?>"
                               class="small-text">
                        <p class="description">Streak'in maksimum gun sayisi (sonra sabit kalir veya sifirlanir).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Tolerans (saat)</th>
                    <td>
                        <input type="number" name="wpgamify[streak_tolerance]" min="0"
                               value="<?php echo esc_attr( $settings['streak_tolerance'] ?? 36 ); ?>"
                               class="small-text">
                        <p class="description">Son giristen bu kadar saat icinde tekrar giris yapmak streak'i korur.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Dongu Sifirlama</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpgamify[streak_cycle_reset]" value="1"
                                <?php checked( $settings['streak_cycle_reset'] ?? false ); ?>>
                            Maksimum gune ulasinca streak sifirlansin (tekrar 1'den baslasin)
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Section: Level Sistemi -->
        <div class="wpgamify-settings-section">
            <h2>Level Sistemi</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Hesaplama Modu</th>
                    <td>
                        <select name="wpgamify[level_mode]">
                            <option value="alltime" <?php selected( $settings['level_mode'] ?? 'alltime', 'alltime' ); ?>>
                                Tum Zamanlar (toplam XP)
                            </option>
                            <option value="rolling" <?php selected( $settings['level_mode'] ?? 'alltime', 'rolling' ); ?>>
                                Hareketli Donem (son X ay)
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Hareketli Donem (ay)</th>
                    <td>
                        <input type="number" name="wpgamify[rolling_months]" min="1" max="24"
                               value="<?php echo esc_attr( $settings['level_rolling_months'] ?? 6 ); ?>"
                               class="small-text">
                        <p class="description">Sadece "Hareketli Donem" modunda gecerlidir.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Tolerans Gunu</th>
                    <td>
                        <input type="number" name="wpgamify[grace_days]" min="0"
                               value="<?php echo esc_attr( $settings['level_grace_days'] ?? 14 ); ?>"
                               class="small-text">
                        <p class="description">Level dusurulmeden once bekleme suresi (gun).</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Section: Anti-Abuse -->
        <div class="wpgamify-settings-section">
            <h2>Anti-Abuse</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Gunluk XP Limiti</th>
                    <td>
                        <input type="number" name="wpgamify[daily_xp_cap]" min="0"
                               value="<?php echo esc_attr( $settings['daily_xp_cap'] ?? 500 ); ?>"
                               class="small-text">
                        <p class="description">Bir kullanicinin gunde kazanabilecegi maksimum XP (0 = limitsiz).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Tekrar Yorum Engeli</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpgamify[duplicate_review_block]" value="1"
                                <?php checked( $settings['duplicate_review_block'] ?? true ); ?>>
                            Ayni urune birden fazla yorum icin XP verme
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Section: Profil Tamamlama XP -->
        <div class="wpgamify-settings-section">
            <h2>Profil Tamamlama XP</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Durum</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpgamify[profile_xp_enabled]" value="1"
                                <?php checked( $settings['xp_profile_enabled'] ?? true ); ?>>
                            Profil tamamlama XP aktif
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">XP Miktari</th>
                    <td>
                        <input type="number" name="wpgamify[profile_xp]" min="0"
                               value="<?php echo esc_attr( $settings['xp_profile_amount'] ?? 20 ); ?>"
                               class="small-text">
                        <p class="description">Profil ilk kez tamamlandiginda verilen XP.</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Section: XP Suresi (Expiry) -->
        <div class="wpgamify-settings-section">
            <h2>XP Suresi (Expiry)</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Durum</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpgamify[xp_expiry_enabled]" value="1"
                                <?php checked( $settings['xp_expiry_enabled'] ?? false ); ?>>
                            XP sure dolumu aktif
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Sure (ay)</th>
                    <td>
                        <input type="number" name="wpgamify[xp_expiry_months]" min="1" max="120"
                               value="<?php echo esc_attr( $settings['xp_expiry_months'] ?? 12 ); ?>"
                               class="small-text">
                        <p class="description">Bu sureden eski XP otomatik olarak dusurulur.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Uyari Suresi (gun)</th>
                    <td>
                        <input type="number" name="wpgamify[xp_expiry_warn_days]" min="0" max="90"
                               value="<?php echo esc_attr( $settings['xp_expiry_warn_days'] ?? 14 ); ?>"
                               class="small-text">
                        <p class="description">XP dolmadan kac gun once uyari gonderilsin (0 = uyari yok).</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Section: Referral/Affiliate XP -->
        <div class="wpgamify-settings-section">
            <h2>Referral/Affiliate XP</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Referral XP</th>
                    <td>
                        <input type="number" name="wpgamify[referral_xp]" min="0"
                               value="<?php echo esc_attr( $settings['xp_referral_amount'] ?? 50 ); ?>"
                               class="small-text">
                        <p class="description">Video referans onayi icin verilen XP.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Affiliate XP</th>
                    <td>
                        <input type="number" name="wpgamify[affiliate_xp]" min="0"
                               value="<?php echo esc_attr( $settings['xp_affiliate_amount'] ?? 30 ); ?>"
                               class="small-text">
                        <p class="description">Affiliate satis basina verilen XP.</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Section: Genel -->
        <div class="wpgamify-settings-section">
            <h2>Genel</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">XP Etiketi</th>
                    <td>
                        <input type="text" name="wpgamify[currency_label]"
                               value="<?php echo esc_attr( $settings['currency_label'] ?? 'XP' ); ?>"
                               class="regular-text">
                        <p class="description">Frontend'de gosterilecek XP birimi etiketi.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Eklenti Kaldirildiginda</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpgamify[keep_data_on_uninstall]" value="1"
                                <?php checked( $settings['keep_data_on_uninstall'] ?? false ); ?>>
                            Eklenti silindiginde verileri koru (tablolari silme)
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( 'Ayarlari Kaydet' ); ?>
    </form>

    <!-- Section: Kampanya -->
    <div class="wpgamify-settings-section wpgamify-campaign-section">
        <h2>Kampanya</h2>

        <?php if ( $campaign ) : ?>
            <div class="wpgamify-campaign-active notice notice-info inline">
                <p>
                    <strong>Aktif Kampanya:</strong>
                    <?php echo esc_html( $campaign['label'] ?? '' ); ?> -
                    x<?php echo esc_html( $campaign['multiplier'] ?? 1 ); ?> XP Carpani
                    (<?php echo esc_html( $campaign['start'] ?? '' ); ?> - <?php echo esc_html( $campaign['end'] ?? '' ); ?>)
                </p>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'wpgamify_admin_nonce', '_wpgamify_nonce' ); ?>
            <input type="hidden" name="wpgamify_form_action" value="save_campaign">

            <table class="form-table">
                <tr>
                    <th scope="row">Kampanya Adi</th>
                    <td>
                        <input type="text" name="campaign_label" class="regular-text"
                               placeholder="orn: Yaz Kampanyasi">
                    </td>
                </tr>
                <tr>
                    <th scope="row">XP Carpani</th>
                    <td>
                        <input type="number" name="campaign_multiplier" min="1" max="10" step="0.1"
                               value="2" class="small-text">
                        <p class="description">Kampanya suresince tum XP'ler bu carpanla verilir.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Baslangic</th>
                    <td>
                        <input type="date" name="campaign_start">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Bitis</th>
                    <td>
                        <input type="date" name="campaign_end">
                    </td>
                </tr>
            </table>

            <p class="submit">
                <?php submit_button( 'Kampanya Baslat', 'primary', 'submit', false ); ?>
                <?php if ( $campaign ) : ?>
                    &nbsp;
                    <button type="submit" name="clear_campaign" value="1" class="button button-secondary">
                        Kampanyayi Temizle
                    </button>
                <?php endif; ?>
            </p>
        </form>
    </div>
</div>
