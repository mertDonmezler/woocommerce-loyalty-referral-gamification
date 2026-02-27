<?php
/**
 * WP Gamify - Musteri Dashboard Sablonu
 *
 * WooCommerce Hesabim > Seviye & XP sayfasinin gorunumu.
 * Modern kart tabanli tasarim, TCG (koleksiyon karti) estetiginde.
 *
 * Bu sablon class-frontend.php tarafindan $data dizisi ile yuklenir.
 *
 * @package    WPGamify
 * @subpackage Frontend\Views
 * @since      1.0.0
 *
 * @var array $data {
 *     Sablon verileri.
 *
 *     @type int        $user_id        Kullanici ID.
 *     @type array      $level_data     Kullanici level verileri.
 *     @type array      $progress       Level ilerleme verileri.
 *     @type array      $benefits       Aktif faydalar.
 *     @type array|null $level_config   Mevcut level konfigurasyonu.
 *     @type array      $streak         Giris serisi verileri.
 *     @type int        $total_xp       Toplam XP.
 *     @type int        $monthly_xp     Bu ayin XP toplami.
 *     @type array      $history        XP gecmisi (son 10).
 *     @type array|null $campaign       Aktif kampanya.
 *     @type array      $all_levels     Tum level konfigurasyonlari.
 *     @type string     $currency_label XP birim etiketi.
 * }
 */

defined( 'ABSPATH' ) || exit;

// Veri ayiklama.
$user_id        = $data['user_id'];
$level_data     = $data['level_data'];
$progress       = $data['progress'];
$benefits       = $data['benefits'];
$level_config   = $data['level_config'];
$streak         = $data['streak'];
$total_xp       = $data['total_xp'];
$monthly_xp     = $data['monthly_xp'];
$history        = $data['history'];
$campaign       = $data['campaign'];
$all_levels     = $data['all_levels'];
$currency_label = $data['currency_label'];

// Hesaplanan degerler.
$current_level_number = (int) ( $level_data['current_level'] ?? 1 );
$current_level_name   = $level_config['name'] ?? 'Bilinmiyor';
$current_level_color  = $level_config['color_hex'] ?? '#6366f1';
$progress_pct         = round( $progress['progress_pct'] ?? 0, 1 );
$next_level           = $progress['next_level'] ?? null;
$next_level_name      = $next_level['name'] ?? null;
$xp_needed            = (int) ( $progress['xp_needed'] ?? 0 );
$current_xp_in_range  = (int) ( $progress['current_xp'] ?? 0 );
$next_level_xp        = $next_level ? (int) $next_level['xp_required'] : 0;

// Streak verileri.
$current_streak = (int) ( $streak['current_streak'] ?? 0 );
$max_streak     = (int) ( $streak['max_streak'] ?? 0 );
$streak_xp      = (int) ( $streak['streak_xp_today'] ?? 0 );

// Fayda etiketleri.
$benefit_labels = [
    'discount'      => 'Indirim',
    'free_shipping' => 'Ucretsiz Kargo',
    'early_access'  => 'Erken Erisim',
    'installment'   => 'Taksit Secenegi',
];
?>

<div class="wpg-dashboard" role="main" aria-label="Gamification paneli">

    <?php // ── Aktif Kampanya Banner ─────────────────────────────────────── ?>
    <?php if ( ! empty( $campaign ) ) : ?>
        <div class="wpg-campaign-banner" role="alert" aria-live="polite">
            <span class="wpg-campaign-icon" aria-hidden="true">&#128293;</span>
            <span class="wpg-campaign-text">
                <?php echo esc_html( $campaign['label'] ?? 'Ozel Kampanya' ); ?>
                <?php if ( ! empty( $campaign['multiplier'] ) && (float) $campaign['multiplier'] > 1 ) : ?>
                    &mdash; <strong><?php echo esc_html( $campaign['multiplier'] ); ?>x <?php echo esc_html( $currency_label ); ?></strong>
                <?php endif; ?>
                <?php if ( ! empty( $campaign['end'] ) ) : ?>
                    <span class="wpg-campaign-end">
                        (<?php echo esc_html( wp_date( 'j M Y', strtotime( $campaign['end'] ) ) ); ?> tarihine kadar)
                    </span>
                <?php endif; ?>
            </span>
        </div>
    <?php endif; ?>

    <?php // ── Level Karti ───────────────────────────────────────────────── ?>
    <div class="wpg-level-card" style="--wpg-level-color: <?php echo esc_attr( $current_level_color ); ?>;" aria-label="Seviye bilgileri">
        <div class="wpg-level-card__header">
            <?php if ( ! empty( $level_config['icon_attachment_id'] ) ) : ?>
                <img
                    class="wpg-level-card__icon"
                    src="<?php echo esc_url( wp_get_attachment_image_url( $level_config['icon_attachment_id'], 'thumbnail' ) ); ?>"
                    alt="<?php echo esc_attr( $current_level_name ); ?> ikonu"
                    width="48"
                    height="48"
                    loading="lazy"
                />
            <?php else : ?>
                <span class="wpg-level-card__icon-fallback" aria-hidden="true">
                    &#9733;
                </span>
            <?php endif; ?>

            <div class="wpg-level-card__info">
                <h2 class="wpg-level-card__title">
                    Seviye <?php echo esc_html( $current_level_number ); ?>: <?php echo esc_html( $current_level_name ); ?>
                </h2>
                <?php if ( $next_level_name ) : ?>
                    <p class="wpg-level-card__subtitle">
                        Sonraki: <strong><?php echo esc_html( $next_level_name ); ?></strong>
                        &mdash; <?php echo esc_html( number_format_i18n( $xp_needed ) ); ?> <?php echo esc_html( $currency_label ); ?> kaldi
                    </p>
                <?php else : ?>
                    <p class="wpg-level-card__subtitle">
                        Maksimum seviyeye ulastiniz!
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="wpg-progress" role="progressbar" aria-valuenow="<?php echo esc_attr( $progress_pct ); ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Seviye ilerlemesi">
            <div class="wpg-progress__bar">
                <div class="wpg-progress__fill" data-progress="<?php echo esc_attr( $progress_pct ); ?>" style="width: 0%;"></div>
            </div>
            <div class="wpg-progress__label">
                <span><?php echo esc_html( number_format_i18n( $current_xp_in_range ) ); ?></span>
                <span><?php echo esc_html( $progress_pct ); ?>%</span>
                <?php if ( $next_level_xp > 0 ) : ?>
                    <span><?php echo esc_html( number_format_i18n( $next_level_xp ) ); ?> <?php echo esc_html( $currency_label ); ?></span>
                <?php else : ?>
                    <span>MAX</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php // ── Istatistik Kartlari ──────────────────────────────────────── ?>
    <div class="wpg-stats-grid" aria-label="Istatistikler">

        <?php // Toplam XP ?>
        <div class="wpg-stat-card">
            <span class="wpg-stat-card__icon" aria-hidden="true">&#9889;</span>
            <div class="wpg-stat-card__content">
                <span class="wpg-stat-card__value" data-count="<?php echo esc_attr( $total_xp ); ?>">0</span>
                <span class="wpg-stat-card__label">Toplam <?php echo esc_html( $currency_label ); ?></span>
            </div>
        </div>

        <?php // Bu Ay ?>
        <div class="wpg-stat-card">
            <span class="wpg-stat-card__icon" aria-hidden="true">&#128197;</span>
            <div class="wpg-stat-card__content">
                <span class="wpg-stat-card__value" data-count="<?php echo esc_attr( $monthly_xp ); ?>">0</span>
                <span class="wpg-stat-card__label">Bu Ay</span>
            </div>
        </div>

        <?php // Giris Serisi ?>
        <div class="wpg-stat-card wpg-stat-card--streak">
            <span class="wpg-stat-card__icon wpg-streak-fire" aria-hidden="true">&#128293;</span>
            <div class="wpg-stat-card__content">
                <span class="wpg-stat-card__value" data-count="<?php echo esc_attr( $current_streak ); ?>">0</span>
                <span class="wpg-stat-card__label">Gun Seri</span>
                <span class="wpg-stat-card__sub">
                    Rekor: <?php echo esc_html( number_format_i18n( $max_streak ) ); ?> Gun
                    <?php if ( $streak_xp > 0 ) : ?>
                        &middot; Bugun: +<?php echo esc_html( number_format_i18n( $streak_xp ) ); ?> <?php echo esc_html( $currency_label ); ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <?php // Faydalar ?>
        <div class="wpg-stat-card wpg-stat-card--benefits">
            <span class="wpg-stat-card__icon" aria-hidden="true">&#127873;</span>
            <div class="wpg-stat-card__content">
                <span class="wpg-stat-card__label wpg-stat-card__label--title">Faydalar</span>
                <ul class="wpg-benefits-list" aria-label="Aktif faydalar">
                    <?php foreach ( $benefit_labels as $key => $label ) : ?>
                        <?php
                        $is_active = false;
                        $display   = $label;

                        if ( $key === 'discount' && isset( $benefits['discount'] ) && (int) $benefits['discount'] > 0 ) {
                            $is_active = true;
                            $display   = '%' . (int) $benefits['discount'] . ' ' . $label;
                        } elseif ( $key !== 'discount' && ! empty( $benefits[ $key ] ) ) {
                            $is_active = true;
                        }
                        ?>
                        <li class="wpg-benefit <?php echo $is_active ? 'wpg-benefit--active' : 'wpg-benefit--locked'; ?>">
                            <span class="wpg-benefit__icon" aria-hidden="true"><?php echo $is_active ? '&#10003;' : '&#10007;'; ?></span>
                            <span class="wpg-benefit__text"><?php echo esc_html( $display ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

    </div>

    <?php // ── Level Yol Haritasi ────────────────────────────────────────── ?>
    <div class="wpg-level-roadmap" aria-label="Seviye yol haritasi">
        <h3 class="wpg-section-title">Seviye Yol Haritasi</h3>
        <div class="wpg-roadmap-track">
            <?php foreach ( $all_levels as $index => $level ) :
                $level_num = (int) $level['level_number'];
                $is_current = $level_num === $current_level_number;
                $is_reached = $level_num <= $current_level_number;
                $color      = $level['color_hex'] ?? '#6366f1';

                $dot_class = 'wpg-roadmap-dot';
                if ( $is_current ) {
                    $dot_class .= ' wpg-roadmap-dot--current';
                } elseif ( $is_reached ) {
                    $dot_class .= ' wpg-roadmap-dot--reached';
                } else {
                    $dot_class .= ' wpg-roadmap-dot--locked';
                }
            ?>
                <div class="wpg-roadmap-step" aria-label="Seviye <?php echo esc_attr( $level_num ); ?>: <?php echo esc_attr( $level['name'] ); ?><?php echo $is_current ? ' (Mevcut)' : ''; ?>">
                    <div class="<?php echo esc_attr( $dot_class ); ?>" style="--wpg-dot-color: <?php echo esc_attr( $color ); ?>;">
                        <?php if ( $is_current ) : ?>
                            <span class="wpg-roadmap-dot__star" aria-hidden="true">&#9733;</span>
                        <?php elseif ( $is_reached ) : ?>
                            <span class="wpg-roadmap-dot__check" aria-hidden="true">&#10003;</span>
                        <?php else : ?>
                            <span class="wpg-roadmap-dot__num" aria-hidden="true"><?php echo esc_html( $level_num ); ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="wpg-roadmap-label"><?php echo esc_html( $level['name'] ); ?></span>
                    <span class="wpg-roadmap-xp"><?php echo esc_html( number_format_i18n( (int) $level['xp_required'] ) ); ?> <?php echo esc_html( $currency_label ); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php // ── XP Gecmisi ────────────────────────────────────────────────── ?>
    <div class="wpg-history" aria-label="XP gecmisi">
        <h3 class="wpg-section-title"><?php echo esc_html( $currency_label ); ?> Gecmisi</h3>

        <?php if ( ! empty( $history ) ) : ?>
            <div class="wpg-history-table-wrap">
                <table class="wpg-history-table" aria-label="XP islem gecmisi">
                    <thead>
                        <tr>
                            <th scope="col">Tarih</th>
                            <th scope="col">Kaynak</th>
                            <th scope="col">Miktar</th>
                            <th scope="col">Not</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $history as $row ) :
                            $amount       = (int) $row['amount'];
                            $amount_class = $amount >= 0 ? 'wpg-xp-positive' : 'wpg-xp-negative';
                            $sign         = $amount >= 0 ? '+' : '';
                            $source_label = WPGamify_XP_Engine::get_source_label( $row['source'] ?? '' );
                            $date_str     = ! empty( $row['created_at'] )
                                ? wp_date( 'j M Y, H:i', strtotime( $row['created_at'] ) )
                                : '-';
                        ?>
                            <tr>
                                <td data-label="Tarih"><?php echo esc_html( $date_str ); ?></td>
                                <td data-label="Kaynak"><?php echo esc_html( $source_label ); ?></td>
                                <td data-label="Miktar" class="<?php echo esc_attr( $amount_class ); ?>">
                                    <?php echo esc_html( $sign . number_format_i18n( $amount ) . ' ' . $currency_label ); ?>
                                </td>
                                <td data-label="Not"><?php echo esc_html( $row['note'] ?? '-' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="wpg-history-footer">
                <button
                    type="button"
                    class="wpg-load-more"
                    aria-label="Daha fazla XP gecmisi yukle"
                >
                    Daha Fazla Goster
                </button>
            </div>
        <?php else : ?>
            <div class="wpg-history-empty" role="status">
                <p>Henuz <?php echo esc_html( $currency_label ); ?> isleminiz bulunmuyor.</p>
            </div>
        <?php endif; ?>
    </div>

</div>
