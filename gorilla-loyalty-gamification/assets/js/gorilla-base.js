/**
 * Gorilla Base - Shared Frontend Utilities
 * Shared JS foundation for Gorilla Loyalty & Gamification + Gorilla Referral & Affiliate.
 * Exposes window.GorillaUI namespace.
 * Version: 1.0.0
 * Author: Mert Donmezler
 * (c) 2025-2026 Mert Donmezler
 */

(function() {
    'use strict';

    // Prevent double-init if loaded by multiple plugins
    if (window.GorillaUI) return;

    /**
     * Toast Notification System
     * @param {string} message - The message to display
     * @param {string} [type='success'] - Toast type: 'success', 'error', or 'info'
     */
    function showGorillaToast(message, type) {
        type = type || 'success';
        var existing = document.querySelectorAll('.gorilla-toast');
        existing.forEach(function(el) { el.remove(); });

        var toast = document.createElement('div');
        toast.className = 'gorilla-toast ' + type;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        var content = document.createElement('div');
        content.className = 'gorilla-toast-content';
        var msgSpan = document.createElement('span');
        msgSpan.textContent = message;
        content.appendChild(msgSpan);
        var closeBtn = document.createElement('button');
        closeBtn.className = 'gorilla-toast-close';
        closeBtn.setAttribute('aria-label', 'Kapat');
        closeBtn.textContent = '\u00D7';
        var progress = document.createElement('div');
        progress.className = 'gorilla-toast-progress';
        toast.appendChild(content);
        toast.appendChild(closeBtn);
        toast.appendChild(progress);
        document.body.appendChild(toast);

        closeBtn.addEventListener('click', function() {
            dismissToast(toast);
        });

        var timeout = setTimeout(function() { dismissToast(toast); }, 4000);

        toast.addEventListener('mouseenter', function() {
            clearTimeout(timeout);
            var p = toast.querySelector('.gorilla-toast-progress');
            if (p) p.style.animationPlayState = 'paused';
        });
        toast.addEventListener('mouseleave', function() {
            var p = toast.querySelector('.gorilla-toast-progress');
            if (p) p.style.animationPlayState = 'running';
            timeout = setTimeout(function() { dismissToast(toast); }, 2000);
        });

        function dismissToast(el) {
            el.style.transform = 'translateX(120%)';
            el.style.opacity = '0';
            setTimeout(function() { el.remove(); }, 300);
        }
    }

    /**
     * Ripple Effect on Buttons
     * Attaches a delegated click listener for buttons with ripple selectors.
     */
    function initRippleEffect() {
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.glr-btn, .glr-shop-btn, #gorilla-spin-btn, .glr-share-btn');
            if (!btn) return;
            var rect = btn.getBoundingClientRect();
            var size = Math.max(rect.width, rect.height);
            var ripple = document.createElement('span');
            ripple.className = 'glr-ripple';
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
            btn.appendChild(ripple);
            setTimeout(function() { ripple.remove(); }, 600);
        });
    }

    /**
     * Animated Progress Bars (IntersectionObserver)
     * Triggers CSS animation on scroll visibility.
     */
    function initProgressAnimations() {
        var bars = document.querySelectorAll('.glr-progress-bar');
        if (!bars.length || !('IntersectionObserver' in window)) return;

        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('glr-progress-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.3 });

        bars.forEach(function(bar) {
            observer.observe(bar);
        });
    }

    // Initialize shared utilities on DOM ready
    function initBase() {
        initRippleEffect();
        initProgressAnimations();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBase);
    } else {
        initBase();
    }

    // Expose public API on window.GorillaUI
    window.GorillaUI = {
        showToast: showGorillaToast,
        initRippleEffect: initRippleEffect,
        initProgressAnimations: initProgressAnimations
    };

    // Backward-compatible global alias
    window.showGorillaToast = showGorillaToast;

})();
