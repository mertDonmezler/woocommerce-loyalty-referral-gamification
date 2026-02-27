<?php
/**
 * WP Gamify Level Management Page
 *
 * @package WPGamify
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$levels     = WPGamify_Level_Manager::get_all_levels();
$level_dist = WPGamify_Level_Manager::get_level_distribution();

?>
<div class="wrap wpgamify-wrap">
    <h1 class="wp-heading-inline">
        Level Yonetimi
        <button type="button" class="page-title-action" id="wpgamify-add-level">Yeni Level Ekle</button>
    </h1>
    <hr class="wp-header-end">

    <!-- Level Table -->
    <table class="widefat striped wpgamify-levels-table" id="wpgamify-levels-table">
        <thead>
            <tr>
                <th class="column-sort" style="width:40px;"></th>
                <th>Sira</th>
                <th>Gorsel</th>
                <th>Level Adi</th>
                <th>Gerekli XP</th>
                <th>Faydalar</th>
                <th>Renk</th>
                <th>Uye Sayisi</th>
                <th>Islemler</th>
            </tr>
        </thead>
        <tbody id="wpgamify-levels-body">
            <?php if ( ! empty( $levels ) ) : ?>
                <?php foreach ( $levels as $i => $level ) :
                    $level      = (array) $level;
                    $id         = (int) ( $level['id'] ?? 0 );
                    $user_count = (int) ( $level_dist[ (int) $level['level_number'] ] ?? 0 );
                    $benefits_data = json_decode( $level['benefits'] ?? '{}', true );
                    $benefits      = [];
                    if ( ! empty( $benefits_data['discount'] ) ) {
                        $benefits[] = '%' . $benefits_data['discount'] . ' indirim';
                    }
                    if ( ! empty( $benefits_data['free_shipping'] ) ) {
                        $benefits[] = 'Ucretsiz kargo';
                    }
                    if ( ! empty( $benefits_data['early_access'] ) ) {
                        $benefits[] = 'Erken erisim';
                    }
                    if ( ! empty( $benefits_data['installment'] ) ) {
                        $benefits[] = 'Taksit avantaji';
                    }
                    ?>
                    <tr data-level-id="<?php echo $id; ?>">
                        <td class="column-sort">
                            <span class="dashicons dashicons-menu wpgamify-sort-handle" title="Surukle"></span>
                        </td>
                        <td><?php echo esc_html( $i + 1 ); ?></td>
                        <td>
                            <?php if ( ! empty( $level['icon_url'] ) ) : ?>
                                <img src="<?php echo esc_url( $level['icon_url'] ); ?>"
                                     alt="" class="wpgamify-level-icon">
                            <?php else : ?>
                                <span class="dashicons dashicons-shield" style="color: <?php echo esc_attr( $level['color_hex'] ?? '#6366f1' ); ?>"></span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo esc_html( $level['name'] ?? '' ); ?></strong></td>
                        <td><?php echo number_format_i18n( $level['xp_required'] ?? 0 ); ?></td>
                        <td>
                            <?php if ( ! empty( $benefits ) ) : ?>
                                <?php echo esc_html( implode( ', ', $benefits ) ); ?>
                            <?php else : ?>
                                <span class="description">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="wpgamify-color-swatch"
                                  style="background-color: <?php echo esc_attr( $level['color_hex'] ?? '#6366f1' ); ?>"></span>
                            <?php echo esc_html( $level['color_hex'] ?? '' ); ?>
                        </td>
                        <td><?php echo number_format_i18n( $user_count ); ?></td>
                        <td>
                            <button type="button" class="button button-small wpgamify-edit-level"
                                    data-level='<?php echo esc_attr( wp_json_encode( $level ) ); ?>'>
                                Duzenle
                            </button>
                            <button type="button" class="button button-small button-link-delete wpgamify-delete-level"
                                    data-level-id="<?php echo $id; ?>"
                                    data-user-count="<?php echo $user_count; ?>"
                                    data-level-name="<?php echo esc_attr( $level['name'] ?? '' ); ?>">
                                Sil
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr class="wpgamify-no-levels">
                    <td colspan="9">Henuz level olusturulmamis.
                        <button type="button" class="button button-link" id="wpgamify-add-level-inline">
                            Ilk leveli olusturun.
                        </button>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Level Edit Modal -->
    <div id="wpgamify-level-modal" class="wpgamify-modal" style="display:none;">
        <div class="wpgamify-modal-overlay"></div>
        <div class="wpgamify-modal-content">
            <div class="wpgamify-modal-header">
                <h2 id="wpgamify-modal-title">Yeni Level</h2>
                <button type="button" class="wpgamify-modal-close">&times;</button>
            </div>
            <form id="wpgamify-level-form">
                <input type="hidden" name="level_id" id="wpgamify-level-id" value="0">

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wpgamify-level-name">Level Adi</label></th>
                        <td>
                            <input type="text" name="name" id="wpgamify-level-name" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wpgamify-level-xp">Gerekli XP</label></th>
                        <td>
                            <input type="number" name="min_xp" id="wpgamify-level-xp" min="0" class="small-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wpgamify-level-discount">Indirim %</label></th>
                        <td>
                            <input type="number" name="discount" id="wpgamify-level-discount"
                                   min="0" max="100" step="0.5" class="small-text" value="0">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Faydalar</th>
                        <td>
                            <label>
                                <input type="checkbox" name="free_shipping" id="wpgamify-level-shipping" value="1">
                                Ucretsiz Kargo
                            </label><br>
                            <label>
                                <input type="checkbox" name="early_access" id="wpgamify-level-early" value="1">
                                Erken Erisim
                            </label><br>
                            <label>
                                <input type="checkbox" name="installment" id="wpgamify-level-installment" value="1">
                                Taksit Avantaji
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wpgamify-level-color">Renk</label></th>
                        <td>
                            <input type="text" name="color_hex" id="wpgamify-level-color"
                                   class="wpgamify-color-field" value="#6366f1">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Gorsel</th>
                        <td>
                            <div class="wpgamify-media-field">
                                <input type="hidden" name="icon_url" id="wpgamify-level-icon-url" value="">
                                <img id="wpgamify-level-icon-preview" src="" alt=""
                                     class="wpgamify-level-icon" style="display:none;">
                                <button type="button" class="button" id="wpgamify-level-icon-btn">
                                    Gorsel Sec
                                </button>
                                <button type="button" class="button button-link" id="wpgamify-level-icon-remove"
                                        style="display:none;">
                                    Kaldir
                                </button>
                            </div>
                        </td>
                    </tr>
                </table>

                <div class="wpgamify-modal-footer">
                    <button type="button" class="button wpgamify-modal-close">Iptal</button>
                    <button type="submit" class="button button-primary" id="wpgamify-level-submit">
                        Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
