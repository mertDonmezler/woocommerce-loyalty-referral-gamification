/**
 * Gorilla Loyalty & Referral - Frontend Scripts
 * Version: 3.0.0
 * Author: Mert Donmezler
 * (c) 2025-2026 Mert Donmezler
 */

(function() {
    'use strict';

    // Toast Notification System
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

    // Confetti Celebration
    function showCelebration() {
        var container = document.createElement('div');
        container.className = 'glr-confetti-container';
        container.setAttribute('aria-hidden', 'true');
        document.body.appendChild(container);
        var colors = ['#f97316', '#3b82f6', '#22c55e', '#8b5cf6', '#f59e0b', '#ef4444', '#ec4899'];
        for (var i = 0; i < 50; i++) {
            var c = document.createElement('div');
            c.className = 'glr-confetti-piece';
            c.style.setProperty('--x', (Math.random() * 100) + 'vw');
            c.style.setProperty('--rotation', (Math.random() * 720 - 360) + 'deg');
            c.style.setProperty('--delay', (Math.random() * 0.5) + 's');
            c.style.setProperty('--duration', (Math.random() * 1 + 1.5) + 's');
            c.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            if (Math.random() > 0.5) { c.style.width = '8px'; c.style.height = '8px'; c.style.borderRadius = '50%'; }
            else { c.style.width = '6px'; c.style.height = '14px'; c.style.borderRadius = '2px'; }
            container.appendChild(c);
        }
        setTimeout(function() { container.remove(); }, 3000);
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

    // Credit Toggle Handler
    function initCreditToggle() {
        var cb = document.getElementById('gorilla_use_credit_cb');
        if (!cb) return;

        cb.addEventListener('change', function() {
            var isChecked = cb.checked;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', gorilla_lr.ajax_url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (typeof jQuery !== 'undefined') {
                    jQuery('body').trigger('update_checkout');
                }
                if (isChecked) {
                    showGorillaToast('Store credit uygulandi!', 'success');
                } else {
                    showGorillaToast('Store credit kaldirildi.', 'info');
                }
            };
            xhr.onerror = function() {
                showGorillaToast('Baglanti hatasi. Lutfen tekrar deneyin.', 'error');
            };
            xhr.send('action=gorilla_toggle_credit&use=' + (isChecked ? '1' : '0') + '&nonce=' + gorilla_lr.credit_nonce);
        });
    }

    // Loyalty Bar Click Handler
    function initLoyaltyBar() {
        var bar = document.getElementById('gorilla-loyalty-bar');
        if (!bar) return;

        bar.addEventListener('click', function() {
            if (gorilla_lr.loyalty_url) {
                window.location.href = gorilla_lr.loyalty_url;
            }
        });
    }

    // Animated Progress Bars (IntersectionObserver) - triggers CSS animation on scroll visibility
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

    // Spin Wheel
    function initSpinWheel() {
        var canvas = document.getElementById('gorilla-spin-canvas');
        var btn = document.getElementById('gorilla-spin-btn');
        if (!canvas || !btn) return;

        var ctx = canvas.getContext('2d');
        var prizes = [];
        try {
            prizes = JSON.parse(canvas.getAttribute('data-prizes') || '[]');
        } catch(e) { return; }

        if (!prizes.length) return;

        var colors = ['#f97316','#3b82f6','#22c55e','#8b5cf6','#f59e0b','#ef4444','#06b6d4','#ec4899'];
        var spinning = false;
        var angle = 0;

        function draw(rotation) {
            var segments = prizes.length;
            var arc = (2 * Math.PI) / segments;
            ctx.clearRect(0, 0, 300, 300);
            ctx.save();
            ctx.translate(150, 150);
            ctx.rotate(rotation);

            for (var i = 0; i < segments; i++) {
                ctx.beginPath();
                ctx.moveTo(0, 0);
                ctx.arc(0, 0, 140, i * arc, (i + 1) * arc);
                ctx.fillStyle = colors[i % colors.length];
                ctx.fill();
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 2;
                ctx.stroke();
                // Label
                ctx.save();
                ctx.rotate(i * arc + arc / 2);
                ctx.fillStyle = '#fff';
                ctx.font = 'bold 11px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(prizes[i].label || '', 80, 4);
                ctx.restore();
            }
            ctx.restore();

            // Arrow
            ctx.beginPath();
            ctx.moveTo(150, 8);
            ctx.lineTo(142, 28);
            ctx.lineTo(158, 28);
            ctx.closePath();
            ctx.fillStyle = '#ef4444';
            ctx.fill();
        }

        draw(0);

        btn.addEventListener('click', function() {
            if (spinning) return;
            spinning = true;
            btn.disabled = true;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', gorilla_lr.ajax_url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                var res;
                try { res = JSON.parse(xhr.responseText); } catch(e) { spinning = false; btn.disabled = false; return; }

                if (res.success && res.data) {
                    var target = res.data.segment_index || 0;
                    var segments = prizes.length;
                    var arc = 360 / segments;
                    var targetAngle = 360 * 5 + (360 - target * arc - arc / 2);
                    var start = angle;
                    var duration = 4000;
                    var startTime = null;

                    function animate(time) {
                        if (!startTime) startTime = time;
                        var elapsed = time - startTime;
                        var progress = Math.min(elapsed / duration, 1);
                        var ease = 1 - Math.pow(1 - progress, 3);
                        var current = start + (targetAngle - start) * ease;
                        draw(current * Math.PI / 180);
                        if (progress < 1) {
                            requestAnimationFrame(animate);
                        } else {
                            angle = current % 360;
                            spinning = false;
                            showGorillaToast('Kazandiniz: ' + (res.data.label || ''), 'success');
                            showCelebration();
                            // Update remaining count
                            var remaining = document.getElementById('gorilla-spin-remaining');
                            if (remaining) remaining.textContent = res.data.remaining || 0;
                            if (res.data.remaining > 0) btn.disabled = false;
                        }
                    }
                    requestAnimationFrame(animate);
                } else {
                    showGorillaToast(res.data || 'Bir hata olustu', 'error');
                    spinning = false;
                    btn.disabled = false;
                }
            };
            xhr.onerror = function() {
                showGorillaToast('Baglanti hatasi. Lutfen tekrar deneyin.', 'error');
                spinning = false;
                btn.disabled = false;
            };
            xhr.send('action=gorilla_spin_wheel&nonce=' + gorilla_lr.spin_nonce);
        });
    }

    // Points Shop Redeem
    function initPointsShop() {
        var shopBtns = document.querySelectorAll('.glr-shop-btn');
        if (!shopBtns.length) return;

        shopBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var rewardId = btn.getAttribute('data-reward-id');
                if (!rewardId || btn.disabled) return;

                if (!confirm('Bu odulu satin almak istediginize emin misiniz?')) return;

                btn.disabled = true;
                var originalBtnText = btn.textContent;
                btn.textContent = '';
                btn.classList.add('glr-btn-loading');

                var xhr = new XMLHttpRequest();
                xhr.open('POST', gorilla_lr.ajax_url, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    var res;
                    try { res = JSON.parse(xhr.responseText); } catch(e) { btn.disabled = false; btn.textContent = 'Satin Al'; return; }

                    if (res.success && res.data) {
                        showGorillaToast('Odul alindi! Kupon: ' + (res.data.coupon_code || ''), 'success');
                        var xpDisplay = document.getElementById('gorilla-shop-xp');
                        if (xpDisplay) xpDisplay.textContent = res.data.new_xp;
                        btn.classList.remove('glr-btn-loading');
                        btn.textContent = 'Alindi!';
                        btn.style.background = '#22c55e';
                    } else {
                        showGorillaToast(res.data || 'Satin alma basarisiz', 'error');
                        btn.disabled = false;
                        btn.classList.remove('glr-btn-loading');
                        btn.textContent = 'Satin Al';
                    }
                };
                xhr.onerror = function() {
                    showGorillaToast('Baglanti hatasi. Lutfen tekrar deneyin.', 'error');
                    btn.disabled = false;
                    btn.classList.remove('glr-btn-loading');
                    btn.textContent = 'Satin Al';
                };
                xhr.send('action=gorilla_shop_redeem&reward_id=' + rewardId + '&nonce=' + gorilla_lr.shop_nonce);
            });
        });
    }

    // Social Share Tracking
    function initSocialShare() {
        var shareBtns = document.querySelectorAll('.glr-share-btn');
        if (!shareBtns.length) return;

        shareBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var platform = btn.getAttribute('data-platform');
                var shareUrl = btn.getAttribute('data-url') || window.location.href;

                // Open share window
                var shareWindowUrl = '';
                switch (platform) {
                    case 'facebook': shareWindowUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl); break;
                    case 'twitter': shareWindowUrl = 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(shareUrl); break;
                    case 'whatsapp': shareWindowUrl = 'https://wa.me/?text=' + encodeURIComponent(shareUrl); break;
                }
                if (shareWindowUrl) window.open(shareWindowUrl, '_blank', 'width=600,height=400');

                // Track share
                var xhr = new XMLHttpRequest();
                xhr.open('POST', gorilla_lr.ajax_url, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    var res;
                    try { res = JSON.parse(xhr.responseText); } catch(e) { return; }
                    if (res.success && res.data && res.data.awarded) {
                        showGorillaToast('Paylasim icin XP kazandiniz!', 'success');
                    }
                };
                xhr.onerror = function() {
                    showGorillaToast('Baglanti hatasi. Lutfen tekrar deneyin.', 'error');
                };
                xhr.send('action=gorilla_track_share&platform=' + platform + '&nonce=' + gorilla_lr.share_nonce);
            });
        });
    }

    // Ripple Effect on Buttons
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

    // Initialize on DOM Ready
    function init() {
        initAffiliateCopy();
        initCreditToggle();
        initLoyaltyBar();
        initProgressAnimations();
        initSpinWheel();
        initPointsShop();
        initSocialShare();
        initRippleEffect();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
