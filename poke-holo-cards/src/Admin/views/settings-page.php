<?php
/**
 * Settings page HTML template.
 *
 * Variables available: $tabs, $active_tab (set by SettingsPage::render()).
 *
 * @package PokeHoloCards\Admin
 * @since   3.0.0
 */

use PokeHoloCards\Utils\EffectTypes;
use PokeHoloCards\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'WooCommerce Holo Cards - Settings', 'poke-holo-cards' ); ?></h1>

    <!-- Nav Tabs -->
    <h2 class="nav-tab-wrapper">
        <?php foreach ( $tabs as $tab_slug => $tab_label ) : ?>
            <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'poke-holo-cards', 'tab' => $tab_slug ), admin_url( 'options-general.php' ) ) ); ?>"
               class="nav-tab <?php echo $active_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html( $tab_label ); ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <form method="post" action="options.php">
        <?php settings_fields( 'phc_settings_group' ); ?>

        <!-- General Tab -->
        <div class="phc-tab-content <?php echo $active_tab === 'general' ? 'phc-tab-active' : ''; ?>">
            <table class="form-table">
                <tr>
                    <th><label for="phc_enabled"><?php esc_html_e( 'Enable Plugin', 'poke-holo-cards' ); ?></label></th>
                    <td>
                        <select name="phc_enabled" id="phc_enabled">
                            <option value="yes" <?php selected( get_option( 'phc_enabled', 'yes' ), 'yes' ); ?>><?php esc_html_e( 'Yes', 'poke-holo-cards' ); ?></option>
                            <option value="no" <?php selected( get_option( 'phc_enabled', 'yes' ), 'no' ); ?>><?php esc_html_e( 'No', 'poke-holo-cards' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="phc_effect_type"><?php esc_html_e( 'Effect Type', 'poke-holo-cards' ); ?></label></th>
                    <td>
                        <select name="phc_effect_type" id="phc_effect_type">
                            <?php foreach ( EffectTypes::get_all() as $type ) : ?>
                                <option value="<?php echo esc_attr( $type ); ?>" <?php selected( get_option( 'phc_effect_type', 'holo' ), $type ); ?>><?php echo esc_html( EffectTypes::get_label( $type ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Choose the holographic effect style.', 'poke-holo-cards' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="phc_glow_color"><?php esc_html_e( 'Glow Color', 'poke-holo-cards' ); ?></label></th>
                    <td>
                        <input type="color" name="phc_glow_color" id="phc_glow_color" value="<?php echo esc_attr( get_option( 'phc_glow_color', '#58e0d9' ) ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="phc_border_radius"><?php esc_html_e( 'Border Radius (%)', 'poke-holo-cards' ); ?></label></th>
                    <td>
                        <input type="number" name="phc_border_radius" id="phc_border_radius" value="<?php echo esc_attr( get_option( 'phc_border_radius', '4.55' ) ); ?>" step="0.1" min="0" max="50" style="width:80px" />
                    </td>
                </tr>
                <tr>
                    <th><label for="phc_auto_init_class"><?php esc_html_e( 'Auto-init CSS Class', 'poke-holo-cards' ); ?></label></th>
                    <td>
                        <input type="text" name="phc_auto_init_class" id="phc_auto_init_class" value="<?php echo esc_attr( get_option( 'phc_auto_init_class', 'phc-card' ) ); ?>" />
                        <p class="description"><?php esc_html_e( 'Elements with this class are automatically initialized.', 'poke-holo-cards' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="phc_spring_preset"><?php esc_html_e( 'Spring Preset', 'poke-holo-cards' ); ?></label></th>
                    <td>
                        <input type="text" name="phc_spring_preset" id="phc_spring_preset" value="<?php echo esc_attr( get_option( 'phc_spring_preset', '' ) ); ?>" />
                        <p class="description"><?php esc_html_e( 'Optional spring physics preset name (leave blank for manual stiffness/damping).', 'poke-holo-cards' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- WooCommerce Tab -->
        <div class="phc-tab-content <?php echo $active_tab === 'woocommerce' ? 'phc-tab-active' : ''; ?>">
            <table class="form-table">
                <tr>
                    <th><label for="phc_woo_enabled"><?php esc_html_e( 'Enable for WooCommerce', 'poke-holo-cards' ); ?></label></th>
                    <td>
                        <select name="phc_woo_enabled" id="phc_woo_enabled">
                            <option value="yes" <?php selected( get_option( 'phc_woo_enabled', 'yes' ), 'yes' ); ?>><?php esc_html_e( 'Yes', 'poke-holo-cards' ); ?></option>
                            <option value="no" <?php selected( get_option( 'phc_woo_enabled', 'yes' ), 'no' ); ?>><?php esc_html_e( 'No', 'poke-holo-cards' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="phc_woo_target"><?php esc_html_e( 'WooCommerce Target', 'poke-holo-cards' ); ?></label></th>
                    <td>
                        <select name="phc_woo_target" id="phc_woo_target">
                            <option value="product_gallery" <?php selected( get_option( 'phc_woo_target', 'product_gallery' ), 'product_gallery' ); ?>><?php esc_html_e( 'Product Gallery Only', 'poke-holo-cards' ); ?></option>
                            <option value="archive_thumbs" <?php selected( get_option( 'phc_woo_target', 'product_gallery' ), 'archive_thumbs' ); ?>><?php esc_html_e( 'Archive Thumbnails Only', 'poke-holo-cards' ); ?></option>
                            <option value="both" <?php selected( get_option( 'phc_woo_target', 'product_gallery' ), 'both' ); ?>><?php esc_html_e( 'Both', 'poke-holo-cards' ); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Animation Tab -->
        <div class="phc-tab-content <?php echo $active_tab === 'animation' ? 'phc-tab-active' : ''; ?>">
            <table class="form-table">
                <tr>
                    <th><label for="phc_hover_scale"><?php esc_html_e( 'Hover Scale', 'poke-holo-cards' ); ?></label></th>
                    <td>
                        <input type="number" name="phc_hover_scale" id="phc_hover_scale" value="<?php echo esc_attr( get_option( 'phc_hover_scale', '1.05' ) ); ?>" step="0.01" min="1" max="2" style="width:80px" />
                    </td>
                </tr>
                <tr>
                    <th><label for="phc_perspective"><?php esc_html_e( '3D Perspective (px)', 'poke-holo-cards' ); ?></label></th>
                    <td>
                        <input type="number" name="phc_perspective" id="phc_perspective" value="<?php echo esc_attr( get_option( 'phc_perspective', '600' ) ); ?>" step="50" min="200" max="2000" style="width:100px" />
                    </td>
                </tr>
                <tr>
                    <th><label for="phc_spring_stiffness"><?php esc_html_e( 'Spring Stiffness', 'poke-holo-cards' ); ?></label></th>
                    <td>
                        <input type="number" name="phc_spring_stiffness" id="phc_spring_stiffness" value="<?php echo esc_attr( get_option( 'phc_spring_stiffness', '0.066' ) ); ?>" step="0.001" min="0.01" max="1" style="width:80px" />
                        <p class="description"><?php esc_html_e( 'Lower = smoother, higher = snappier (0.01 - 1)', 'poke-holo-cards' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="phc_spring_damping"><?php esc_html_e( 'Spring Damping', 'poke-holo-cards' ); ?></label></th>
                    <td>
                        <input type="number" name="phc_spring_damping" id="phc_spring_damping" value="<?php echo esc_attr( get_option( 'phc_spring_damping', '0.25' ) ); ?>" step="0.01" min="0.01" max="1" style="width:80px" />
                        <p class="description"><?php esc_html_e( 'Lower = more bouncy, higher = less bouncy (0.01 - 1)', 'poke-holo-cards' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="phc_glare_opacity"><?php esc_html_e( 'Glare Opacity', 'poke-holo-cards' ); ?></label></th>
                    <td>
                        <input type="number" name="phc_glare_opacity" id="phc_glare_opacity" value="<?php echo esc_attr( get_option( 'phc_glare_opacity', '0.8' ) ); ?>" step="0.1" min="0" max="1" style="width:80px" />
                    </td>
                </tr>
                <tr>
                    <th><label for="phc_shine_intensity"><?php esc_html_e( 'Shine Intensity', 'poke-holo-cards' ); ?></label></th>
                    <td>
                        <input type="number" name="phc_shine_intensity" id="phc_shine_intensity" value="<?php echo esc_attr( get_option( 'phc_shine_intensity', '1' ) ); ?>" step="0.1" min="0" max="3" style="width:80px" />
                    </td>
                </tr>
                <tr>
                    <th><label for="phc_gyroscope"><?php esc_html_e( 'Enable Gyroscope (Mobile)', 'poke-holo-cards' ); ?></label></th>
                    <td>
                        <select name="phc_gyroscope" id="phc_gyroscope">
                            <option value="yes" <?php selected( get_option( 'phc_gyroscope', 'yes' ), 'yes' ); ?>><?php esc_html_e( 'Yes', 'poke-holo-cards' ); ?></option>
                            <option value="no" <?php selected( get_option( 'phc_gyroscope', 'yes' ), 'no' ); ?>><?php esc_html_e( 'No', 'poke-holo-cards' ); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Shortcode Builder Tab -->
        <div class="phc-tab-content <?php echo $active_tab === 'shortcode' ? 'phc-tab-active' : ''; ?>">
            <div style="display:flex;gap:30px;flex-wrap:wrap;margin-top:15px;">
                <!-- Builder Controls -->
                <div style="flex:1;min-width:320px;max-width:480px;">
                    <h3 style="margin-top:0;"><?php esc_html_e( 'Card Settings', 'poke-holo-cards' ); ?></h3>
                    <table class="form-table" style="margin-top:0;">
                        <tr>
                            <th><label for="phc-sb-img"><?php esc_html_e( 'Image URL', 'poke-holo-cards' ); ?></label></th>
                            <td>
                                <input type="url" id="phc-sb-img" class="regular-text phc-sb-input" placeholder="https://example.com/card.png" />
                                <button type="button" class="button button-small" id="phc-sb-media-btn"><?php esc_html_e( 'Media Library', 'poke-holo-cards' ); ?></button>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="phc-sb-alt"><?php esc_html_e( 'Alt Text', 'poke-holo-cards' ); ?></label></th>
                            <td><input type="text" id="phc-sb-alt" class="regular-text phc-sb-input" placeholder="<?php esc_attr_e( 'My Card', 'poke-holo-cards' ); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="phc-sb-effect"><?php esc_html_e( 'Effect', 'poke-holo-cards' ); ?></label></th>
                            <td>
                                <select id="phc-sb-effect" class="phc-sb-input">
                                    <?php foreach ( EffectTypes::get_all() as $type ) : ?>
                                        <option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( EffectTypes::get_label( $type ) ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="phc-sb-width"><?php esc_html_e( 'Width', 'poke-holo-cards' ); ?></label></th>
                            <td><input type="text" id="phc-sb-width" class="phc-sb-input" value="300px" style="width:100px;" /></td>
                        </tr>
                        <tr>
                            <th><label for="phc-sb-showcase"><?php esc_html_e( 'Showcase', 'poke-holo-cards' ); ?></label></th>
                            <td>
                                <select id="phc-sb-showcase" class="phc-sb-input">
                                    <option value="no"><?php esc_html_e( 'No', 'poke-holo-cards' ); ?></option>
                                    <option value="yes"><?php esc_html_e( 'Yes', 'poke-holo-cards' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="phc-sb-sparkle"><?php esc_html_e( 'Sparkle', 'poke-holo-cards' ); ?></label></th>
                            <td>
                                <select id="phc-sb-sparkle" class="phc-sb-input">
                                    <option value="no"><?php esc_html_e( 'No', 'poke-holo-cards' ); ?></option>
                                    <option value="yes"><?php esc_html_e( 'Yes', 'poke-holo-cards' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="phc-sb-glow"><?php esc_html_e( 'Glow Color', 'poke-holo-cards' ); ?></label></th>
                            <td><input type="color" id="phc-sb-glow" class="phc-sb-input" value="" /><label style="margin-left:8px;"><input type="checkbox" id="phc-sb-glow-enable" /> <?php esc_html_e( 'Custom', 'poke-holo-cards' ); ?></label></td>
                        </tr>
                        <tr>
                            <th><label for="phc-sb-radius"><?php esc_html_e( 'Border Radius (%)', 'poke-holo-cards' ); ?></label></th>
                            <td><input type="number" id="phc-sb-radius" class="phc-sb-input" value="" min="0" max="50" step="0.5" style="width:80px;" placeholder="default" /></td>
                        </tr>
                        <tr>
                            <th><label for="phc-sb-spring"><?php esc_html_e( 'Spring Preset', 'poke-holo-cards' ); ?></label></th>
                            <td>
                                <select id="phc-sb-spring" class="phc-sb-input">
                                    <option value=""><?php esc_html_e( 'Default', 'poke-holo-cards' ); ?></option>
                                    <option value="bouncy"><?php esc_html_e( 'Bouncy', 'poke-holo-cards' ); ?></option>
                                    <option value="stiff"><?php esc_html_e( 'Stiff', 'poke-holo-cards' ); ?></option>
                                    <option value="smooth"><?php esc_html_e( 'Smooth', 'poke-holo-cards' ); ?></option>
                                    <option value="elastic"><?php esc_html_e( 'Elastic', 'poke-holo-cards' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="phc-sb-back"><?php esc_html_e( 'Back Image URL', 'poke-holo-cards' ); ?></label></th>
                            <td><input type="url" id="phc-sb-back" class="regular-text phc-sb-input" placeholder="<?php esc_attr_e( 'Optional', 'poke-holo-cards' ); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="phc-sb-url"><?php esc_html_e( 'Click-through URL', 'poke-holo-cards' ); ?></label></th>
                            <td><input type="url" id="phc-sb-url" class="regular-text phc-sb-input" placeholder="<?php esc_attr_e( 'Optional', 'poke-holo-cards' ); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="phc-sb-target"><?php esc_html_e( 'Link Target', 'poke-holo-cards' ); ?></label></th>
                            <td>
                                <select id="phc-sb-target" class="phc-sb-input">
                                    <option value="_self"><?php esc_html_e( 'Same Window', 'poke-holo-cards' ); ?></option>
                                    <option value="_blank"><?php esc_html_e( 'New Tab', 'poke-holo-cards' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="phc-sb-class"><?php esc_html_e( 'Extra CSS Class', 'poke-holo-cards' ); ?></label></th>
                            <td><input type="text" id="phc-sb-class" class="phc-sb-input" placeholder="<?php esc_attr_e( 'Optional', 'poke-holo-cards' ); ?>" /></td>
                        </tr>
                    </table>
                </div>

                <!-- Preview & Output -->
                <div style="flex:1;min-width:320px;max-width:500px;">
                    <h3 style="margin-top:0;"><?php esc_html_e( 'Live Preview', 'poke-holo-cards' ); ?></h3>
                    <div id="phc-sb-preview-wrap" style="min-height:200px;display:flex;align-items:center;justify-content:center;background:#1a1a2e;border-radius:8px;padding:30px;">
                        <p style="color:#888;" id="phc-sb-preview-placeholder"><?php esc_html_e( 'Enter an image URL to see preview', 'poke-holo-cards' ); ?></p>
                    </div>

                    <h3><?php esc_html_e( 'Generated Shortcode', 'poke-holo-cards' ); ?></h3>
                    <div style="position:relative;">
                        <textarea id="phc-sb-output" rows="3" class="large-text code" readonly style="font-family:monospace;font-size:13px;background:#f0f0f1;"></textarea>
                        <button type="button" class="button button-small" id="phc-sb-copy" style="position:absolute;top:4px;right:4px;"><?php esc_html_e( 'Copy', 'poke-holo-cards' ); ?></button>
                    </div>
                    <p class="description" id="phc-sb-copy-msg" style="color:#00a32a;display:none;"><?php esc_html_e( 'Copied to clipboard!', 'poke-holo-cards' ); ?></p>
                </div>
            </div>
        </div>

        <!-- Analytics Tab -->
        <div class="phc-tab-content <?php echo $active_tab === 'analytics' ? 'phc-tab-active' : ''; ?>">
            <h3><?php esc_html_e( 'Card Interaction Analytics', 'poke-holo-cards' ); ?></h3>
            <p class="description" style="margin-bottom:15px;"><?php esc_html_e( 'Aggregated interaction data from frontend card hovers and clicks.', 'poke-holo-cards' ); ?></p>
            <?php \PokeHoloCards\Admin\SettingsPage::render_analytics_tab(); ?>
        </div>

        <!-- Advanced Tab -->
        <div class="phc-tab-content <?php echo $active_tab === 'advanced' ? 'phc-tab-active' : ''; ?>">
            <h3><?php esc_html_e( 'Effect Presets', 'poke-holo-cards' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Load Preset', 'poke-holo-cards' ); ?></th>
                    <td>
                        <select id="phc-preset-select" style="min-width:200px;">
                            <option value=""><?php esc_html_e( '-- Select Preset --', 'poke-holo-cards' ); ?></option>
                            <optgroup label="<?php esc_attr_e( 'Built-in', 'poke-holo-cards' ); ?>">
                                <option value="__pokemon"><?php esc_html_e( 'Pokemon Style', 'poke-holo-cards' ); ?></option>
                                <option value="__mtg"><?php esc_html_e( 'MTG Style', 'poke-holo-cards' ); ?></option>
                                <option value="__minimalist"><?php esc_html_e( 'Minimalist', 'poke-holo-cards' ); ?></option>
                                <option value="__neon"><?php esc_html_e( 'Neon Glow', 'poke-holo-cards' ); ?></option>
                                <option value="__retro"><?php esc_html_e( 'Retro Vintage', 'poke-holo-cards' ); ?></option>
                            </optgroup>
                            <?php
                            $custom_presets = get_option( 'phc_presets', array() );
                            if ( ! empty( $custom_presets ) ) :
                            ?>
                            <optgroup label="<?php esc_attr_e( 'Custom', 'poke-holo-cards' ); ?>">
                                <?php foreach ( $custom_presets as $name => $data ) : ?>
                                    <option value="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $name ); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                        <button type="button" class="button button-primary" id="phc-load-preset"><?php esc_html_e( 'Load', 'poke-holo-cards' ); ?></button>
                        <button type="button" class="button button-link-delete" id="phc-delete-preset" style="margin-left:5px;"><?php esc_html_e( 'Delete', 'poke-holo-cards' ); ?></button>
                        <p class="description"><?php esc_html_e( 'Load a preset to apply its settings. Built-in presets cannot be deleted.', 'poke-holo-cards' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Save as Preset', 'poke-holo-cards' ); ?></th>
                    <td>
                        <input type="text" id="phc-preset-name" placeholder="<?php esc_attr_e( 'Preset name', 'poke-holo-cards' ); ?>" style="width:200px;" />
                        <button type="button" class="button button-secondary" id="phc-save-preset"><?php esc_html_e( 'Save Current', 'poke-holo-cards' ); ?></button>
                        <p class="description"><?php esc_html_e( 'Save your current effect settings as a reusable preset.', 'poke-holo-cards' ); ?></p>
                    </td>
                </tr>
            </table>

            <hr />
            <h3><?php esc_html_e( 'Import / Export', 'poke-holo-cards' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Reset to Defaults', 'poke-holo-cards' ); ?></th>
                    <td>
                        <button type="button" class="button button-secondary" id="phc-reset-defaults"><?php esc_html_e( 'Reset All Settings', 'poke-holo-cards' ); ?></button>
                        <p class="description"><?php esc_html_e( 'This will reset all plugin options to their default values. This action cannot be undone.', 'poke-holo-cards' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Export Settings', 'poke-holo-cards' ); ?></th>
                    <td>
                        <button type="button" class="button button-secondary" id="phc-export-settings"><?php esc_html_e( 'Export', 'poke-holo-cards' ); ?></button>
                        <p class="description"><?php esc_html_e( 'Export current settings as JSON to the textarea below.', 'poke-holo-cards' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Import Settings', 'poke-holo-cards' ); ?></th>
                    <td>
                        <button type="button" class="button button-secondary" id="phc-import-settings"><?php esc_html_e( 'Import', 'poke-holo-cards' ); ?></button>
                        <p class="description"><?php esc_html_e( 'Paste a previously exported JSON string into the textarea below and click Import.', 'poke-holo-cards' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Import / Export Data', 'poke-holo-cards' ); ?></th>
                    <td>
                        <textarea id="phc-import-export-area" rows="8" class="large-text code"></textarea>
                    </td>
                </tr>
            </table>
        </div>

        <?php
        if ( $active_tab !== 'advanced' && $active_tab !== 'shortcode' && $active_tab !== 'analytics' ) {
            submit_button();
        }
        ?>
    </form>

    <!-- Live Preview -->
    <hr />
    <h2><?php esc_html_e( 'Live Preview', 'poke-holo-cards' ); ?></h2>
    <p class="description"><?php esc_html_e( 'Hover over the card below to see the current effect in action. Change the Effect Type dropdown above to preview different effects (updates instantly).', 'poke-holo-cards' ); ?></p>
    <div class="phc-admin-preview-wrap">
        <div class="phc-card phc-effect-<?php echo esc_attr( Sanitizer::effect_type( get_option( 'phc_effect_type', 'holo' ) ) ); ?>" id="phc-admin-preview-card" data-phc-effect="<?php echo esc_attr( Sanitizer::effect_type( get_option( 'phc_effect_type', 'holo' ) ) ); ?>">
            <div class="phc-card__translater">
                <div class="phc-card__rotator">
                    <div class="phc-card__front phc-admin-preview-placeholder"><?php esc_html_e( 'Holo Card Preview', 'poke-holo-cards' ); ?></div>
                    <div class="phc-card__shine"></div>
                    <div class="phc-card__glare"></div>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function(){
        /* ── Live Preview: ALL settings update preview card in real-time ── */
        var previewCard  = document.getElementById('phc-admin-preview-card');
        if ( previewCard ) {
            var translater = previewCard.querySelector('.phc-card__translater');

            // Map of setting inputs -> preview update functions
            var liveInputs = {
                'phc_effect_type': function(val) {
                    previewCard.setAttribute('data-phc-effect', val);
                    var classes = previewCard.className.replace(/phc-effect-\S+/g, '').trim();
                    previewCard.className = classes + ' phc-effect-' + val;
                    if ( window.phcReinit ) window.phcReinit(previewCard);
                },
                'phc_glow_color': function(val) {
                    previewCard.style.setProperty('--phc-card-glow', val);
                    previewCard.setAttribute('data-phc-glow', val);
                },
                'phc_border_radius': function(val) {
                    var br = parseFloat(val) || 4.55;
                    previewCard.style.setProperty('--phc-card-radius', br + '% / ' + (br * 0.769).toFixed(1) + '%');
                },
                'phc_perspective': function(val) {
                    if (translater) translater.style.perspective = (parseInt(val, 10) || 600) + 'px';
                },
                'phc_hover_scale': function(val) {
                    // Update phcSettings so the JS engine picks it up on next interaction
                    if (window.phcSettings) window.phcSettings.hoverScale = parseFloat(val) || 1.05;
                },
                'phc_spring_stiffness': function(val) {
                    if (window.phcSettings) window.phcSettings.springStiffness = parseFloat(val) || 0.066;
                    if (window.phcReinit) window.phcReinit(previewCard);
                },
                'phc_spring_damping': function(val) {
                    if (window.phcSettings) window.phcSettings.springDamping = parseFloat(val) || 0.25;
                    if (window.phcReinit) window.phcReinit(previewCard);
                },
                'phc_glare_opacity': function(val) {
                    if (window.phcSettings) window.phcSettings.glareOpacity = parseFloat(val) || 0.8;
                },
                'phc_shine_intensity': function(val) {
                    if (window.phcSettings) window.phcSettings.shineIntensity = parseFloat(val) || 1;
                }
            };

            // Attach listeners to all mapped inputs
            Object.keys(liveInputs).forEach(function(id) {
                var input = document.getElementById(id);
                if (!input) return;
                var eventType = (input.type === 'color' || input.tagName === 'SELECT') ? 'change' : 'input';
                input.addEventListener(eventType, function() {
                    liveInputs[id](this.value);
                });
            });
        }

        /* Reset defaults */
        var resetBtn = document.getElementById('phc-reset-defaults');
        if ( resetBtn ) {
            resetBtn.addEventListener('click', function(){
                if ( ! confirm( '<?php echo esc_js( __( 'Are you sure you want to reset all settings to their defaults?', 'poke-holo-cards' ) ); ?>' ) ) return;
                var xhr = new XMLHttpRequest();
                xhr.open('POST', phcAdmin.ajaxUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function(){
                    if ( xhr.status === 200 ) {
                        alert( '<?php echo esc_js( __( 'Settings reset to defaults. The page will now reload.', 'poke-holo-cards' ) ); ?>' );
                        location.reload();
                    } else {
                        alert( '<?php echo esc_js( __( 'Error resetting settings.', 'poke-holo-cards' ) ); ?>' );
                    }
                };
                xhr.send('action=phc_reset_defaults&nonce=' + encodeURIComponent(phcAdmin.nonce));
            });
        }

        /* Export settings */
        var exportBtn = document.getElementById('phc-export-settings');
        var textarea  = document.getElementById('phc-import-export-area');
        if ( exportBtn && textarea ) {
            exportBtn.addEventListener('click', function(){
                var xhr = new XMLHttpRequest();
                xhr.open('POST', phcAdmin.ajaxUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function(){
                    if ( xhr.status === 200 ) {
                        var resp = JSON.parse(xhr.responseText);
                        if ( resp.success ) {
                            textarea.value = JSON.stringify(resp.data, null, 2);
                        } else {
                            alert( '<?php echo esc_js( __( 'Export failed.', 'poke-holo-cards' ) ); ?>' );
                        }
                    }
                };
                xhr.send('action=phc_export_settings&nonce=' + encodeURIComponent(phcAdmin.nonce));
            });
        }

        /* Import settings */
        var importBtn = document.getElementById('phc-import-settings');
        if ( importBtn && textarea ) {
            importBtn.addEventListener('click', function(){
                var json = textarea.value.trim();
                if ( ! json ) {
                    alert( '<?php echo esc_js( __( 'Please paste a valid JSON string into the textarea.', 'poke-holo-cards' ) ); ?>' );
                    return;
                }
                if ( ! confirm( '<?php echo esc_js( __( 'Are you sure you want to import these settings? Current settings will be overwritten.', 'poke-holo-cards' ) ); ?>' ) ) return;
                var xhr = new XMLHttpRequest();
                xhr.open('POST', phcAdmin.ajaxUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function(){
                    if ( xhr.status === 200 ) {
                        var resp = JSON.parse(xhr.responseText);
                        if ( resp.success ) {
                            alert( '<?php echo esc_js( __( 'Settings imported successfully. The page will now reload.', 'poke-holo-cards' ) ); ?>' );
                            location.reload();
                        } else {
                            alert( '<?php echo esc_js( __( 'Import failed: ', 'poke-holo-cards' ) ); ?>' + ( resp.data || 'Unknown error' ) );
                        }
                    }
                };
                xhr.send('action=phc_import_settings&nonce=' + encodeURIComponent(phcAdmin.nonce) + '&settings=' + encodeURIComponent(json));
            });
        }

        /* ── Preset Management ── */
        var presetSelect = document.getElementById('phc-preset-select');
        var loadPresetBtn = document.getElementById('phc-load-preset');
        var deletePresetBtn = document.getElementById('phc-delete-preset');
        var savePresetBtn = document.getElementById('phc-save-preset');
        var presetNameInput = document.getElementById('phc-preset-name');

        if (loadPresetBtn && presetSelect) {
            loadPresetBtn.addEventListener('click', function() {
                var val = presetSelect.value;
                if (!val) { alert('Please select a preset.'); return; }
                if (!confirm('Load this preset? Current settings will be overwritten.')) return;
                var xhr = new XMLHttpRequest();
                xhr.open('POST', phcAdmin.ajaxUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success) {
                        // Apply preset via import
                        var xhr2 = new XMLHttpRequest();
                        xhr2.open('POST', phcAdmin.ajaxUrl, true);
                        xhr2.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr2.onload = function() {
                            var r2 = JSON.parse(xhr2.responseText);
                            if (r2.success) { alert('Preset loaded!'); location.reload(); }
                            else { alert('Failed to apply preset.'); }
                        };
                        xhr2.send('action=phc_import_settings&nonce=' + encodeURIComponent(phcAdmin.nonce) + '&settings=' + encodeURIComponent(JSON.stringify(resp.data)));
                    } else {
                        alert('Preset not found: ' + (resp.data || ''));
                    }
                };
                xhr.send('action=phc_load_preset&nonce=' + encodeURIComponent(phcAdmin.nonce) + '&preset=' + encodeURIComponent(val));
            });
        }

        if (deletePresetBtn && presetSelect) {
            deletePresetBtn.addEventListener('click', function() {
                var val = presetSelect.value;
                if (!val || val.indexOf('__') === 0) { alert('Cannot delete built-in presets.'); return; }
                if (!confirm('Delete preset "' + val + '"?')) return;
                var xhr = new XMLHttpRequest();
                xhr.open('POST', phcAdmin.ajaxUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success) { location.reload(); }
                    else { alert('Delete failed: ' + (resp.data || '')); }
                };
                xhr.send('action=phc_delete_preset&nonce=' + encodeURIComponent(phcAdmin.nonce) + '&preset=' + encodeURIComponent(val));
            });
        }

        if (savePresetBtn && presetNameInput) {
            savePresetBtn.addEventListener('click', function() {
                var name = presetNameInput.value.trim();
                if (!name) { alert('Please enter a preset name.'); return; }
                var xhr = new XMLHttpRequest();
                xhr.open('POST', phcAdmin.ajaxUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success) { alert('Preset saved!'); location.reload(); }
                    else { alert('Save failed: ' + (resp.data || '')); }
                };
                xhr.send('action=phc_save_preset&nonce=' + encodeURIComponent(phcAdmin.nonce) + '&name=' + encodeURIComponent(name));
            });
        }

        /* ── Shortcode Builder ── */
        var sbOutput     = document.getElementById('phc-sb-output');
        var sbPreview    = document.getElementById('phc-sb-preview-wrap');
        var sbPlaceholder= document.getElementById('phc-sb-preview-placeholder');
        var sbCopyBtn    = document.getElementById('phc-sb-copy');
        var sbCopyMsg    = document.getElementById('phc-sb-copy-msg');

        if (sbOutput) {
            var sbFields = {
                img:      { el: document.getElementById('phc-sb-img'),      def: '',     attr: 'img' },
                alt:      { el: document.getElementById('phc-sb-alt'),      def: '',     attr: 'alt' },
                effect:   { el: document.getElementById('phc-sb-effect'),   def: 'holo', attr: 'effect' },
                width:    { el: document.getElementById('phc-sb-width'),    def: '300px',attr: 'width' },
                showcase: { el: document.getElementById('phc-sb-showcase'), def: 'no',   attr: 'showcase' },
                sparkle:  { el: document.getElementById('phc-sb-sparkle'),  def: 'no',   attr: 'sparkle' },
                glow:     { el: document.getElementById('phc-sb-glow'),     def: '',     attr: 'glow' },
                radius:   { el: document.getElementById('phc-sb-radius'),   def: '',     attr: 'radius' },
                spring:   { el: document.getElementById('phc-sb-spring'),   def: '',     attr: 'spring' },
                back:     { el: document.getElementById('phc-sb-back'),     def: '',     attr: 'back' },
                url:      { el: document.getElementById('phc-sb-url'),      def: '',     attr: 'url' },
                target:   { el: document.getElementById('phc-sb-target'),   def: '_self',attr: 'target' },
                cls:      { el: document.getElementById('phc-sb-class'),    def: '',     attr: 'class' }
            };
            var sbGlowEnable = document.getElementById('phc-sb-glow-enable');

            function sbBuildShortcode() {
                var img = (sbFields.img.el.value || '').trim();
                if (!img) {
                    sbOutput.value = '';
                    return;
                }
                var parts = ['[holo_card img="' + img + '"'];
                var alt = (sbFields.alt.el.value || '').trim();
                if (alt) parts.push(' alt="' + alt + '"');

                var effect = sbFields.effect.el.value;
                if (effect && effect !== 'holo') parts.push(' effect="' + effect + '"');

                var width = (sbFields.width.el.value || '').trim();
                if (width && width !== '300px') parts.push(' width="' + width + '"');

                if (sbFields.showcase.el.value === 'yes') parts.push(' showcase="yes"');
                if (sbFields.sparkle.el.value === 'yes') parts.push(' sparkle="yes"');

                if (sbGlowEnable && sbGlowEnable.checked && sbFields.glow.el.value) {
                    parts.push(' glow="' + sbFields.glow.el.value + '"');
                }

                var radius = (sbFields.radius.el.value || '').trim();
                if (radius !== '') parts.push(' radius="' + radius + '"');

                var spring = sbFields.spring.el.value;
                if (spring) parts.push(' spring="' + spring + '"');

                var back = (sbFields.back.el.value || '').trim();
                if (back) parts.push(' back="' + back + '"');

                var url = (sbFields.url.el.value || '').trim();
                if (url) {
                    parts.push(' url="' + url + '"');
                    if (sbFields.target.el.value === '_blank') parts.push(' target="_blank"');
                }

                var cls = (sbFields.cls.el.value || '').trim();
                if (cls) parts.push(' class="' + cls + '"');

                parts.push(']');
                sbOutput.value = parts.join('');
            }

            function sbUpdatePreview() {
                var img = (sbFields.img.el.value || '').trim();
                if (!img) {
                    sbPlaceholder.style.display = '';
                    var old = sbPreview.querySelector('.phc-card');
                    if (old) old.remove();
                    return;
                }
                sbPlaceholder.style.display = 'none';

                var effect   = sbFields.effect.el.value || 'holo';
                var width    = (sbFields.width.el.value || '300px').trim();
                var showcase = sbFields.showcase.el.value === 'yes';
                var sparkle  = sbFields.sparkle.el.value === 'yes';
                var glow     = (sbGlowEnable && sbGlowEnable.checked) ? sbFields.glow.el.value : '';
                var radius   = (sbFields.radius.el.value || '').trim();
                var spring   = sbFields.spring.el.value || '';
                var back     = (sbFields.back.el.value || '').trim();

                // Remove old preview card
                var oldCard = sbPreview.querySelector('.phc-card');
                if (oldCard && window.PokeHoloCards) window.PokeHoloCards.destroy(oldCard);
                if (oldCard) oldCard.remove();

                // Build preview card
                var card = document.createElement('div');
                card.className = 'phc-card phc-effect-' + effect + (showcase ? ' phc-showcase' : '');
                card.style.width = width;
                card.setAttribute('data-phc-effect', effect);
                if (showcase) card.setAttribute('data-phc-showcase', 'true');
                if (sparkle) card.setAttribute('data-phc-sparkle', 'true');
                if (glow) card.setAttribute('data-phc-glow', glow);
                if (radius) card.setAttribute('data-phc-radius', radius);
                if (spring) card.setAttribute('data-phc-spring', spring);
                if (back) card.setAttribute('data-phc-back', back);

                card.innerHTML = '<div class="phc-card__translater"><div class="phc-card__rotator">' +
                    '<img class="phc-card__front" src="' + img.replace(/"/g, '&quot;') + '" alt="preview" loading="lazy" />' +
                    '<div class="phc-card__shine"></div><div class="phc-card__glare"></div>' +
                    '</div></div>';

                sbPreview.appendChild(card);

                // Initialize with PHC engine
                if (window.PokeHoloCards) {
                    window.PokeHoloCards.init(card);
                }
            }

            // Attach listeners to all builder inputs
            Object.keys(sbFields).forEach(function(key) {
                var el = sbFields[key].el;
                if (!el) return;
                var evt = (el.tagName === 'SELECT' || el.type === 'color') ? 'change' : 'input';
                el.addEventListener(evt, function() { sbBuildShortcode(); sbUpdatePreview(); });
            });
            if (sbGlowEnable) {
                sbGlowEnable.addEventListener('change', function() { sbBuildShortcode(); sbUpdatePreview(); });
            }

            // Copy to clipboard
            if (sbCopyBtn) {
                sbCopyBtn.addEventListener('click', function() {
                    var text = sbOutput.value;
                    if (!text) return;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function() {
                            sbCopyMsg.style.display = '';
                            setTimeout(function() { sbCopyMsg.style.display = 'none'; }, 2000);
                        });
                    } else {
                        sbOutput.select();
                        document.execCommand('copy');
                        sbCopyMsg.style.display = '';
                        setTimeout(function() { sbCopyMsg.style.display = 'none'; }, 2000);
                    }
                });
            }

            // Media Library button
            var sbMediaBtn = document.getElementById('phc-sb-media-btn');
            if (sbMediaBtn && typeof wp !== 'undefined' && wp.media) {
                sbMediaBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var frame = wp.media({ title: 'Select Card Image', multiple: false, library: { type: 'image' } });
                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        sbFields.img.el.value = attachment.url;
                        if (!sbFields.alt.el.value && attachment.alt) {
                            sbFields.alt.el.value = attachment.alt;
                        }
                        sbBuildShortcode();
                        sbUpdatePreview();
                    });
                    frame.open();
                });
            }
        }
    })();
    </script>

    <hr />
    <h2><?php esc_html_e( 'Shortcode Usage', 'poke-holo-cards' ); ?></h2>
    <pre style="background:#f0f0f1;padding:15px;border-radius:4px;">[holo_card img="https://example.com/card.png" alt="My Card" width="300px" effect="holo"]</pre>
    <p>
        <strong><?php esc_html_e( 'Available effects:', 'poke-holo-cards' ); ?></strong>
        <?php
        $effect_codes = array();
        foreach ( EffectTypes::get_all() as $t ) {
            $effect_codes[] = '<code>' . esc_html( $t ) . '</code>';
        }
        echo implode( ', ', $effect_codes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    </p>

    <h3><?php esc_html_e( 'Additional Attributes', 'poke-holo-cards' ); ?></h3>
    <table class="widefat fixed" style="max-width:700px">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Attribute', 'poke-holo-cards' ); ?></th>
                <th><?php esc_html_e( 'Default', 'poke-holo-cards' ); ?></th>
                <th><?php esc_html_e( 'Description', 'poke-holo-cards' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr><td><code>img</code></td><td><em><?php esc_html_e( '(required)', 'poke-holo-cards' ); ?></em></td><td><?php esc_html_e( 'Image URL for the card front.', 'poke-holo-cards' ); ?></td></tr>
            <tr><td><code>alt</code></td><td><em><?php esc_html_e( 'empty', 'poke-holo-cards' ); ?></em></td><td><?php esc_html_e( 'Alt text for the image.', 'poke-holo-cards' ); ?></td></tr>
            <tr><td><code>width</code></td><td><code>300px</code></td><td><?php esc_html_e( 'Card width (any CSS value).', 'poke-holo-cards' ); ?></td></tr>
            <tr><td><code>effect</code></td><td><em><?php esc_html_e( 'global setting', 'poke-holo-cards' ); ?></em></td><td><?php esc_html_e( 'Effect type override for this card.', 'poke-holo-cards' ); ?></td></tr>
            <tr><td><code>class</code></td><td><em><?php esc_html_e( 'empty', 'poke-holo-cards' ); ?></em></td><td><?php esc_html_e( 'Additional CSS class(es).', 'poke-holo-cards' ); ?></td></tr>
            <tr><td><code>showcase</code></td><td><code>no</code></td><td><?php esc_html_e( 'Set to yes to enable showcase mode (auto-rotate animation).', 'poke-holo-cards' ); ?></td></tr>
            <tr><td><code>sparkle</code></td><td><code>no</code></td><td><?php esc_html_e( 'Set to yes to add sparkle particle overlay.', 'poke-holo-cards' ); ?></td></tr>
            <tr><td><code>glow</code></td><td><em><?php esc_html_e( 'global setting', 'poke-holo-cards' ); ?></em></td><td><?php esc_html_e( 'Per-card glow color (hex, e.g. #ff00ff).', 'poke-holo-cards' ); ?></td></tr>
            <tr><td><code>radius</code></td><td><em><?php esc_html_e( 'global setting', 'poke-holo-cards' ); ?></em></td><td><?php esc_html_e( 'Per-card border radius in % (0-50).', 'poke-holo-cards' ); ?></td></tr>
            <tr><td><code>back</code></td><td><em><?php esc_html_e( 'empty', 'poke-holo-cards' ); ?></em></td><td><?php esc_html_e( 'Image URL for the card back (enables flip).', 'poke-holo-cards' ); ?></td></tr>
            <tr><td><code>back_alt</code></td><td><em><?php esc_html_e( 'empty', 'poke-holo-cards' ); ?></em></td><td><?php esc_html_e( 'Alt text for the back image.', 'poke-holo-cards' ); ?></td></tr>
            <tr><td><code>spring</code></td><td><em><?php esc_html_e( 'global setting', 'poke-holo-cards' ); ?></em></td><td><?php esc_html_e( 'Spring physics preset override for this card.', 'poke-holo-cards' ); ?></td></tr>
        </tbody>
    </table>

    <h3><?php esc_html_e( 'Gallery Shortcode', 'poke-holo-cards' ); ?></h3>
    <pre style="background:#f0f0f1;padding:15px;border-radius:4px;">[holo_gallery ids="10,11,12" columns="3" effect="cosmos" width="250px" gap="20px"]</pre>
    <p class="description"><?php esc_html_e( 'Displays a responsive grid of holo cards from WordPress media library attachment IDs.', 'poke-holo-cards' ); ?></p>

    <h3><?php esc_html_e( 'Examples', 'poke-holo-cards' ); ?></h3>
    <pre style="background:#f0f0f1;padding:15px;border-radius:4px;">[holo_card img="card.png" effect="galaxy" showcase="yes" sparkle="yes"]
[holo_card img="card.png" effect="neon" glow="#ff00ff" radius="12"]
[holo_card img="card.png" effect="prism" width="400px" class="my-custom-class"]
[holo_card img="card.png" back="back.png" back_alt="Card Back" spring="gentle"]</pre>

    <h2><?php esc_html_e( 'CSS Class Usage', 'poke-holo-cards' ); ?></h2>
    <p><?php
        printf(
            esc_html__( 'Add the class %s to any element containing an <img> to auto-initialize:', 'poke-holo-cards' ),
            '<code>phc-card</code>'
        );
    ?></p>
    <pre style="background:#f0f0f1;padding:15px;border-radius:4px;">&lt;div class="phc-card"&gt;
  &lt;img src="card.png" alt="My Card" /&gt;
&lt;/div&gt;</pre>
</div>
