/**
 * WP Gamify Admin JavaScript
 *
 * @package WPGamify
 * @since   1.0.0
 */
(function ($) {
    'use strict';

    const WPGamify = {

        /** Debounce timer for search. */
        searchTimer: null,

        /** WordPress media frame instance. */
        mediaFrame: null,

        /**
         * Initialize all admin modules.
         */
        init() {
            this.initLevels();
            this.initManualXP();
            this.initSettings();
            this.initWizard();
        },

        /* ─── Level Management ─────────────────────────────────────────── */

        /**
         * Initialize level management page.
         */
        initLevels() {
            const $table = $('#wpgamify-levels-table');
            if (!$table.length) return;

            this.initSortable();
            this.initColorPicker();

            // Add level button.
            $(document).on('click', '#wpgamify-add-level, #wpgamify-add-level-inline', () => {
                this.openLevelModal();
            });

            // Edit level button.
            $(document).on('click', '.wpgamify-edit-level', (e) => {
                const data = $(e.currentTarget).data('level');
                this.openLevelModal(data);
            });

            // Delete level button.
            $(document).on('click', '.wpgamify-delete-level', (e) => {
                const $btn = $(e.currentTarget);
                const id = $btn.data('level-id');
                const name = $btn.data('level-name');
                const count = $btn.data('user-count');

                let msg = 'Bu leveli silmek istediginize emin misiniz?';
                if (count > 0) {
                    msg = `"${name}" levelinde ${count} musteri var. Silmek istediginize emin misiniz? Musteriler mevcut XP'lerine gore yeniden hesaplanacak.`;
                }

                this.confirm(msg).then((ok) => {
                    if (ok) this.deleteLevel(id);
                });
            });

            // Modal close.
            $(document).on('click', '.wpgamify-modal-close, .wpgamify-modal-overlay', () => {
                this.closeLevelModal();
            });

            // Level form submit.
            $('#wpgamify-level-form').on('submit', (e) => {
                e.preventDefault();
                this.saveLevel();
            });

            // Media uploader.
            this.initMediaUploader();
        },

        /**
         * Open level modal (create or edit).
         *
         * @param {Object|null} data Level data for editing.
         */
        openLevelModal(data = null) {
            const $modal = $('#wpgamify-level-modal');
            const $title = $('#wpgamify-modal-title');

            if (data) {
                $title.text('Level Duzenle');
                $('#wpgamify-level-id').val(data.id || 0);
                $('#wpgamify-level-name').val(data.name || '');
                $('#wpgamify-level-xp').val(data.min_xp || 0);
                $('#wpgamify-level-discount').val(data.discount || 0);
                $('#wpgamify-level-shipping').prop('checked', !!data.free_shipping);
                $('#wpgamify-level-early').prop('checked', !!data.early_access);
                $('#wpgamify-level-installment').prop('checked', !!data.installment);
                $('#wpgamify-level-color').val(data.color_hex || '#6366f1').trigger('change');
                $('#wpgamify-level-icon-url').val(data.icon_url || '');

                if (data.icon_url) {
                    $('#wpgamify-level-icon-preview').attr('src', data.icon_url).show();
                    $('#wpgamify-level-icon-remove').show();
                } else {
                    $('#wpgamify-level-icon-preview').hide();
                    $('#wpgamify-level-icon-remove').hide();
                }
            } else {
                $title.text('Yeni Level');
                $('#wpgamify-level-form')[0].reset();
                $('#wpgamify-level-id').val(0);
                $('#wpgamify-level-color').val('#6366f1').trigger('change');
                $('#wpgamify-level-icon-preview').hide();
                $('#wpgamify-level-icon-remove').hide();
            }

            // Re-initialize color picker for the modal.
            if ($.fn.wpColorPicker) {
                $('#wpgamify-level-color').wpColorPicker('color', data?.color_hex || '#6366f1');
            }

            $modal.fadeIn(200);
        },

        /**
         * Close level modal.
         */
        closeLevelModal() {
            $('#wpgamify-level-modal').fadeOut(200);
        },

        /**
         * Save level via AJAX.
         */
        saveLevel() {
            const $form = $('#wpgamify-level-form');
            const data = {
                action: 'wpgamify_save_level',
                nonce: wpgamifyAdmin.nonce,
                level_id: $('#wpgamify-level-id').val(),
                name: $('#wpgamify-level-name').val(),
                min_xp: $('#wpgamify-level-xp').val(),
                discount: $('#wpgamify-level-discount').val(),
                free_shipping: $('#wpgamify-level-shipping').is(':checked') ? 1 : 0,
                early_access: $('#wpgamify-level-early').is(':checked') ? 1 : 0,
                installment: $('#wpgamify-level-installment').is(':checked') ? 1 : 0,
                color_hex: $('#wpgamify-level-color').val(),
                icon_url: $('#wpgamify-level-icon-url').val(),
            };

            if (!data.name) {
                this.showToast(wpgamifyAdmin.i18n.required, 'error');
                return;
            }

            $.post(wpgamifyAdmin.ajaxUrl, data, (res) => {
                if (res.success) {
                    this.showToast(res.data.message, 'success');
                    this.closeLevelModal();
                    location.reload();
                } else {
                    this.showToast(res.data.message || wpgamifyAdmin.i18n.error, 'error');
                }
            }).fail(() => {
                this.showToast(wpgamifyAdmin.i18n.error, 'error');
            });
        },

        /**
         * Delete level via AJAX.
         *
         * @param {number} id Level ID.
         */
        deleteLevel(id) {
            $.post(wpgamifyAdmin.ajaxUrl, {
                action: 'wpgamify_delete_level',
                nonce: wpgamifyAdmin.nonce,
                level_id: id,
            }, (res) => {
                if (res.success) {
                    this.showToast(res.data.message, 'success');
                    $(`tr[data-level-id="${id}"]`).fadeOut(300, function () {
                        $(this).remove();
                    });
                } else {
                    this.showToast(res.data.message || wpgamifyAdmin.i18n.error, 'error');
                }
            }).fail(() => {
                this.showToast(wpgamifyAdmin.i18n.error, 'error');
            });
        },

        /**
         * Initialize jQuery UI Sortable for level reordering.
         */
        initSortable() {
            const $tbody = $('#wpgamify-levels-body');
            if (!$tbody.length || !$.fn.sortable) return;

            $tbody.sortable({
                handle: '.wpgamify-sort-handle',
                placeholder: 'ui-sortable-placeholder',
                axis: 'y',
                update: () => {
                    const order = [];
                    $tbody.find('tr[data-level-id]').each(function () {
                        order.push($(this).data('level-id'));
                    });

                    $.post(wpgamifyAdmin.ajaxUrl, {
                        action: 'wpgamify_reorder_levels',
                        nonce: wpgamifyAdmin.nonce,
                        order: order,
                    }, (res) => {
                        if (res.success) {
                            this.showToast(res.data.message, 'success');
                        }
                    }.bind(this));
                },
            });
        },

        /**
         * Initialize WP Color Picker.
         */
        initColorPicker() {
            if (!$.fn.wpColorPicker) return;
            $('.wpgamify-color-field').wpColorPicker();
        },

        /**
         * Initialize WordPress Media Uploader for level icons.
         */
        initMediaUploader() {
            $(document).on('click', '#wpgamify-level-icon-btn', () => {
                if (this.mediaFrame) {
                    this.mediaFrame.open();
                    return;
                }

                this.mediaFrame = wp.media({
                    title: 'Level Gorseli Sec',
                    button: { text: 'Gorseli Kullan' },
                    multiple: false,
                    library: { type: 'image' },
                });

                this.mediaFrame.on('select', () => {
                    const attachment = this.mediaFrame.state().get('selection').first().toJSON();
                    const url = attachment.sizes?.thumbnail?.url || attachment.url;
                    $('#wpgamify-level-icon-url').val(url);
                    $('#wpgamify-level-icon-preview').attr('src', url).show();
                    $('#wpgamify-level-icon-remove').show();
                });

                this.mediaFrame.open();
            });

            $(document).on('click', '#wpgamify-level-icon-remove', () => {
                $('#wpgamify-level-icon-url').val('');
                $('#wpgamify-level-icon-preview').hide();
                $('#wpgamify-level-icon-remove').hide();
            });
        },

        /* ─── Manual XP ────────────────────────────────────────────────── */

        /**
         * Initialize manual XP page.
         */
        initManualXP() {
            const $search = $('#wpgamify-user-search');
            if (!$search.length) return;

            // User search with debounce.
            $search.on('input', () => {
                clearTimeout(this.searchTimer);
                const term = $search.val().trim();

                if (term.length < 3) {
                    $('#wpgamify-user-results').hide();
                    return;
                }

                this.searchTimer = setTimeout(() => {
                    this.searchUsers(term);
                }, 350);
            });

            // Close search results on outside click.
            $(document).on('click', (e) => {
                if (!$(e.target).closest('.wpgamify-search-wrapper').length) {
                    $('#wpgamify-user-results').hide();
                }
            });

            // Select user from results.
            $(document).on('click', '.wpgamify-search-result-item', (e) => {
                const $item = $(e.currentTarget);
                this.loadUserInfo(
                    $item.data('user-id'),
                    $item.data('user-name'),
                    $item.data('user-email'),
                    $item.data('user-xp')
                );
                $('#wpgamify-user-results').hide();
                $search.val($item.data('user-name'));
            });

            // XP form submit.
            $('#wpgamify-xp-form').on('submit', (e) => {
                e.preventDefault();
                this.submitXP();
            });
        },

        /**
         * Search users via AJAX.
         *
         * @param {string} term Search term.
         */
        searchUsers(term) {
            $.post(wpgamifyAdmin.ajaxUrl, {
                action: 'wpgamify_search_users',
                nonce: wpgamifyAdmin.nonce,
                term: term,
            }, (res) => {
                const $results = $('#wpgamify-user-results');
                $results.empty();

                if (!res.success || !res.data.users.length) {
                    $results.html('<div class="wpgamify-search-result-item">Sonuc bulunamadi</div>').show();
                    return;
                }

                res.data.users.forEach((user) => {
                    $results.append(`
                        <div class="wpgamify-search-result-item"
                             data-user-id="${user.id}"
                             data-user-name="${this.escHtml(user.display_name)}"
                             data-user-email="${this.escHtml(user.email)}"
                             data-user-xp="${user.total_xp}">
                            <div>
                                <span class="wpgamify-search-result-name">${this.escHtml(user.display_name)}</span>
                                <br>
                                <span class="wpgamify-search-result-email">${this.escHtml(user.email)}</span>
                            </div>
                            <span class="wpgamify-search-result-xp">${this.formatNumber(user.total_xp)} XP</span>
                        </div>
                    `);
                });

                $results.show();
            });
        },

        /**
         * Load selected user info into the info panel.
         *
         * @param {number} userId   User ID.
         * @param {string} name     Display name.
         * @param {string} email    Email.
         * @param {number} totalXp  Total XP.
         */
        loadUserInfo(userId, name, email, totalXp) {
            $('#wpgamify-user-name').text(name);
            $('#wpgamify-user-email').text(email);
            $('#wpgamify-user-xp').text(this.formatNumber(totalXp) + ' XP');
            $('#wpgamify-user-level').text('-'); // Will be populated if API provides.
            $('#wpgamify-user-streak').text('-');
            $('#wpgamify-xp-user-id').val(userId);

            $('#wpgamify-user-info-panel').slideDown(200);
            $('#wpgamify-xp-action-panel').slideDown(200);
            $('#wpgamify-xp-history-panel').slideDown(200);

            // Load history (simple placeholder; full impl needs a dedicated AJAX endpoint).
            this.loadXPHistory(userId);
        },

        /**
         * Load user XP history.
         *
         * @param {number} userId User ID.
         */
        loadXPHistory(userId) {
            // Reuse search endpoint data or add a dedicated endpoint.
            // For now, show a placeholder message.
            const $body = $('#wpgamify-xp-history-body');
            $body.html('<tr><td colspan="4" class="description">Yukleniyor...</td></tr>');

            // In production, this would call a dedicated AJAX endpoint.
            // For the initial release, we show a note.
            $body.html('<tr><td colspan="4" class="description">Kullanici secildi. XP islemi yapildiginda gecmis burada goruntulenir.</td></tr>');
        },

        /**
         * Submit manual XP via AJAX.
         */
        submitXP() {
            const userId = $('#wpgamify-xp-user-id').val();
            const amount = $('#wpgamify-xp-amount').val();
            const reason = $('#wpgamify-xp-reason').val();
            const action = $('#wpgamify-xp-action').val();

            if (!userId || !amount || !reason) {
                this.showToast(wpgamifyAdmin.i18n.required, 'error');
                return;
            }

            this.confirm(wpgamifyAdmin.i18n.confirmXP).then((ok) => {
                if (!ok) return;

                $.post(wpgamifyAdmin.ajaxUrl, {
                    action: 'wpgamify_manual_xp',
                    nonce: wpgamifyAdmin.nonce,
                    user_id: userId,
                    amount: amount,
                    xp_action: action,
                    reason: reason,
                }, (res) => {
                    if (res.success) {
                        this.showToast(res.data.message, 'success');
                        $('#wpgamify-user-xp').text(this.formatNumber(res.data.new_total) + ' XP');
                        $('#wpgamify-xp-amount').val('');
                        $('#wpgamify-xp-reason').val('');
                    } else {
                        this.showToast(res.data.message || wpgamifyAdmin.i18n.error, 'error');
                    }
                }).fail(() => {
                    this.showToast(wpgamifyAdmin.i18n.error, 'error');
                });
            });
        },

        /* ─── Settings ─────────────────────────────────────────────────── */

        /**
         * Initialize settings page.
         */
        initSettings() {
            // Settings form is handled via standard form POST (non-AJAX).
            // This method is reserved for any JS-enhanced settings behavior.
        },

        /* ─── Setup Wizard ─────────────────────────────────────────────── */

        /**
         * Initialize setup wizard.
         */
        initWizard() {
            // Step 2: Save XP settings.
            $(document).on('click', '#wpgamify-wizard-save-xp', () => {
                const $form = $('#wpgamify-wizard-xp-form');
                const formData = {
                    action: 'wpgamify_save_wizard',
                    nonce: wpgamifyAdmin.nonce,
                    step: 2,
                    order_xp_enabled: $form.find('[name="order_xp_enabled"]').is(':checked') ? 1 : 0,
                    review_xp_enabled: $form.find('[name="review_xp_enabled"]').is(':checked') ? 1 : 0,
                    login_xp_enabled: $form.find('[name="login_xp_enabled"]').is(':checked') ? 1 : 0,
                    streak_enabled: $form.find('[name="streak_enabled"]').is(':checked') ? 1 : 0,
                };

                $.post(wpgamifyAdmin.ajaxUrl, formData, (res) => {
                    if (res.success) {
                        window.location.href = wpgamifyAdmin.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=wp-gamify-wizard&step=3');
                    } else {
                        this.showToast(res.data.message || wpgamifyAdmin.i18n.error, 'error');
                    }
                });
            });

            // Step 3: Confirm levels.
            $(document).on('click', '#wpgamify-wizard-confirm-levels', () => {
                $.post(wpgamifyAdmin.ajaxUrl, {
                    action: 'wpgamify_save_wizard',
                    nonce: wpgamifyAdmin.nonce,
                    step: 3,
                }, (res) => {
                    if (res.success) {
                        window.location.href = wpgamifyAdmin.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=wp-gamify-wizard&step=4');
                    }
                });
            });

            // Step 4: Finish.
            $(document).on('click', '#wpgamify-wizard-finish', () => {
                $.post(wpgamifyAdmin.ajaxUrl, {
                    action: 'wpgamify_save_wizard',
                    nonce: wpgamifyAdmin.nonce,
                    step: 4,
                }, (res) => {
                    if (res.success && res.data.redirect) {
                        window.location.href = res.data.redirect;
                    }
                });
            });
        },

        /* ─── Utilities ────────────────────────────────────────────────── */

        /**
         * Show toast notification.
         *
         * @param {string} message Toast message.
         * @param {string} type    Toast type: success, error, warning.
         */
        showToast(message, type = 'success') {
            // Remove any existing toast.
            $('.wpgamify-toast').remove();

            const $toast = $(`<div class="wpgamify-toast wpgamify-toast-${type}">${this.escHtml(message)}</div>`);
            $('body').append($toast);

            // Trigger show animation.
            requestAnimationFrame(() => {
                $toast.addClass('visible');
            });

            // Auto-hide after 4 seconds.
            setTimeout(() => {
                $toast.removeClass('visible');
                setTimeout(() => $toast.remove(), 300);
            }, 4000);
        },

        /**
         * Promise-based confirm dialog.
         *
         * @param {string} message Confirmation message.
         * @returns {Promise<boolean>}
         */
        confirm(message) {
            return new Promise((resolve) => {
                resolve(window.confirm(message));
            });
        },

        /**
         * Format number with locale separators.
         *
         * @param {number} num Number to format.
         * @returns {string}
         */
        formatNumber(num) {
            return new Intl.NumberFormat('tr-TR').format(num || 0);
        },

        /**
         * Escape HTML to prevent XSS.
         *
         * @param {string} str Raw string.
         * @returns {string}
         */
        escHtml(str) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(str || ''));
            return div.innerHTML;
        },
    };

    $(document).ready(() => WPGamify.init());
})(jQuery);
