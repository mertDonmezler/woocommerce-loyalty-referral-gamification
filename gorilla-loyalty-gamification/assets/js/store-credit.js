/**
 * Gorilla Store Credit - Frontend Scripts
 * Version: 1.0.0
 * Author: Mert Donmezler
 * (c) 2025-2026 Mert Donmezler
 */

(function() {
    'use strict';

    /**
     * Get the localized config object.
     * Supports both gorilla_sc (standalone) and gorilla_lr (monolith) variable names.
     */
    function getConfig() {
        if (typeof gorilla_sc !== 'undefined') return gorilla_sc;
        if (typeof gorilla_lr !== 'undefined') return gorilla_lr;
        return {};
    }

    /**
     * Show a toast notification.
     */
    function showToast(message, type) {
        type = type || 'success';

        // Remove existing toasts
        var existing = document.querySelectorAll('.gorilla-sc-toast');
        existing.forEach(function(el) { el.remove(); });

        var toast = document.createElement('div');
        toast.className = 'gorilla-sc-toast ' + type;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(120%)';
            setTimeout(function() { toast.remove(); }, 300);
        }, 3500);
    }

    /**
     * Credit Slider Handler for checkout page.
     */
    function initCreditToggle() {
        var slider = document.getElementById('gorilla_credit_slider');
        var display = document.getElementById('gorilla_credit_display');
        if (!slider) return;

        var config = getConfig();
        var debounceTimer = null;

        // Format number as price
        function formatPrice(val) {
            var num = parseFloat(val);
            if (isNaN(num)) return '0';
            var symbol = config.currency_symbol || '\u20BA';
            return num.toFixed(2).replace(/\.00$/, '') + ' ' + symbol;
        }

        slider.addEventListener('input', function() {
            // Instant UI feedback
            if (display) display.textContent = formatPrice(slider.value);

            // Debounce AJAX call (300ms)
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                var amount = parseFloat(slider.value) || 0;
                var nonce = config.credit_nonce || '';
                var ajaxUrl = config.ajax_url || '';

                if (!ajaxUrl || !nonce) return;

                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    // Trigger WooCommerce checkout update
                    if (typeof jQuery !== 'undefined') {
                        jQuery('body').trigger('update_checkout');
                    }
                    if (amount > 0) {
                        showToast('Store credit uygulandi!', 'success');
                    } else {
                        showToast('Store credit kaldirildi.', 'info');
                    }
                };
                xhr.onerror = function() {
                    showToast('Baglanti hatasi. Lutfen tekrar deneyin.', 'error');
                };
                xhr.send('action=gorilla_toggle_credit&amount=' + amount + '&nonce=' + nonce);
            }, 300);
        });
    }

    /**
     * After WooCommerce updates checkout fragments, recalculate slider max
     * so it never exceeds the new cart total.
     */
    function bindCheckoutUpdate() {
        if (typeof jQuery === 'undefined') return;
        jQuery(document.body).on('updated_checkout', function() {
            var slider = document.getElementById('gorilla_credit_slider');
            if (!slider) return;
            var credit = parseFloat(slider.getAttribute('data-credit')) || 0;
            var wrapper = document.getElementById('gorilla-credit-toggle');
            var cartTotal = wrapper ? parseFloat(wrapper.getAttribute('data-cart-total')) || credit : credit;
            // Try to read live cart total from WC order review
            var totalEl = document.querySelector('.order-total .woocommerce-Price-amount bdi');
            if (totalEl) {
                var parsed = parseFloat(totalEl.textContent.replace(/[^\d.,]/g, '').replace(',', '.'));
                if (!isNaN(parsed) && parsed > 0) cartTotal = parsed;
            }
            var newMax = Math.min(credit, cartTotal);
            slider.max = newMax.toFixed(2);
            if (parseFloat(slider.value) > newMax) {
                slider.value = newMax.toFixed(2);
                var display = document.getElementById('gorilla_credit_display');
                if (display) {
                    var config = getConfig();
                    var symbol = config.currency_symbol || '\u20BA';
                    display.textContent = newMax.toFixed(2).replace(/\.00$/, '') + ' ' + symbol;
                }
            }
        });
    }

    /**
     * Initialize all modules on DOM ready.
     */
    function init() {
        initCreditToggle();
        bindCheckoutUpdate();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
