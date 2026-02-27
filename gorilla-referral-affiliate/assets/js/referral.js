/**
 * Gorilla Referral & Affiliate - Frontend Scripts
 * Referral-specific logic only. Shared utilities are in gorilla-base.js.
 * Version: 1.0.0
 * Author: Mert Donmezler
 * (c) 2025-2026 Mert Donmezler
 */

(function() {
    'use strict';

    // Shared toast function reference (from gorilla-base.js)
    function showGorillaToast(message, type) {
        if (window.GorillaUI && window.GorillaUI.showToast) {
            window.GorillaUI.showToast(message, type);
        }
    }

    // Affiliate Link Copy Handler
    function initAffiliateCopy() {
        var copyBtn = document.getElementById('gorilla-copy-affiliate');
        var linkInput = document.getElementById('gorilla-affiliate-link');
        if (!copyBtn || !linkInput) return;

        copyBtn.addEventListener('click', function() {
            var originalText = copyBtn.innerHTML;

            // Modern clipboard API
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(linkInput.value).then(function() {
                    copyBtn.innerHTML = 'Kopyalandi!';
                    copyBtn.style.background = '#22c55e';
                    showGorillaToast('Affiliate linkiniz kopyalandi!', 'success');
                    setTimeout(function() {
                        copyBtn.innerHTML = originalText;
                        copyBtn.style.background = '#3b82f6';
                    }, 2000);
                }).catch(function() {
                    fallbackCopy();
                });
            } else {
                fallbackCopy();
            }

            function fallbackCopy() {
                linkInput.select();
                linkInput.setSelectionRange(0, 99999);
                try {
                    document.execCommand('copy');
                    copyBtn.innerHTML = 'Kopyalandi!';
                    copyBtn.style.background = '#22c55e';
                    showGorillaToast('Affiliate linkiniz kopyalandi!', 'success');
                    setTimeout(function() {
                        copyBtn.innerHTML = originalText;
                        copyBtn.style.background = '#3b82f6';
                    }, 2000);
                } catch (e) {
                    copyBtn.innerHTML = 'Kopyalanamadi';
                    showGorillaToast('Kopyalama basarisiz oldu.', 'error');
                    setTimeout(function() {
                        copyBtn.innerHTML = originalText;
                    }, 2000);
                }
            }
        });
    }

    // Initialize on DOM Ready
    function init() {
        initAffiliateCopy();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
