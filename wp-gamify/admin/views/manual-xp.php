<?php
/**
 * WP Gamify Manual XP Management Page
 *
 * @package WPGamify
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap wpgamify-wrap">
    <h1 class="wp-heading-inline">Manuel XP Yonetimi</h1>
    <hr class="wp-header-end">

    <div class="wpgamify-manual-xp-layout">
        <!-- User Search -->
        <div class="wpgamify-panel">
            <h2 class="wpgamify-panel-title">Kullanici Ara</h2>
            <div class="wpgamify-search-wrapper">
                <input type="text" id="wpgamify-user-search"
                       class="regular-text" placeholder="Ad, soyad veya e-posta ile ara (min. 3 karakter)..."
                       autocomplete="off">
                <div id="wpgamify-user-results" class="wpgamify-search-results" style="display:none;"></div>
            </div>
        </div>

        <!-- Selected User Info -->
        <div id="wpgamify-user-info-panel" class="wpgamify-panel" style="display:none;">
            <h2 class="wpgamify-panel-title">Musteri Bilgileri</h2>
            <div class="wpgamify-user-info-card">
                <div class="wpgamify-user-info-row">
                    <span class="wpgamify-info-label">Ad:</span>
                    <span id="wpgamify-user-name" class="wpgamify-info-value">-</span>
                </div>
                <div class="wpgamify-user-info-row">
                    <span class="wpgamify-info-label">E-posta:</span>
                    <span id="wpgamify-user-email" class="wpgamify-info-value">-</span>
                </div>
                <div class="wpgamify-user-info-row">
                    <span class="wpgamify-info-label">Mevcut Level:</span>
                    <span id="wpgamify-user-level" class="wpgamify-info-value">-</span>
                </div>
                <div class="wpgamify-user-info-row">
                    <span class="wpgamify-info-label">Toplam XP:</span>
                    <span id="wpgamify-user-xp" class="wpgamify-info-value">-</span>
                </div>
                <div class="wpgamify-user-info-row">
                    <span class="wpgamify-info-label">Streak:</span>
                    <span id="wpgamify-user-streak" class="wpgamify-info-value">-</span>
                </div>
            </div>
        </div>

        <!-- XP Action Form -->
        <div id="wpgamify-xp-action-panel" class="wpgamify-panel" style="display:none;">
            <h2 class="wpgamify-panel-title">XP Islemi</h2>
            <form id="wpgamify-xp-form">
                <input type="hidden" id="wpgamify-xp-user-id" name="user_id" value="">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wpgamify-xp-action">Islem</label></th>
                        <td>
                            <select name="xp_action" id="wpgamify-xp-action">
                                <option value="add">XP Ekle</option>
                                <option value="deduct">XP Cikar</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wpgamify-xp-amount">Miktar</label></th>
                        <td>
                            <input type="number" name="amount" id="wpgamify-xp-amount"
                                   min="1" class="small-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wpgamify-xp-reason">Sebep</label></th>
                        <td>
                            <input type="text" name="reason" id="wpgamify-xp-reason"
                                   class="regular-text" required
                                   placeholder="XP islemi sebebini yazin (zorunlu)">
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary" id="wpgamify-xp-submit">
                        Uygula
                    </button>
                </p>
            </form>
        </div>

        <!-- User XP History -->
        <div id="wpgamify-xp-history-panel" class="wpgamify-panel" style="display:none;">
            <h2 class="wpgamify-panel-title">Son XP Islemleri</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Kaynak</th>
                        <th>Miktar</th>
                        <th>Detay</th>
                    </tr>
                </thead>
                <tbody id="wpgamify-xp-history-body">
                    <tr>
                        <td colspan="4" class="description">Kullanici secin.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
