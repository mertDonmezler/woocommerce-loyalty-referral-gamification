/**
 * Gorilla Loyalty & Gamification - Frontend Scripts
 * Loyalty-specific logic only. Shared utilities are in gorilla-base.js.
 * Version: 3.1.0
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

    // PHC Cross-Plugin: Holo Level-Up Celebration (6.2)
    function showHoloCelebration(emoji, label) {
        if (typeof window.PHC_CardFactory === 'undefined' && !document.querySelector('.phc-card')) return;

        var overlay = document.createElement('div');
        overlay.className = 'glr-holo-celebration';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);opacity:0;transition:opacity 0.4s;';

        var card = document.createElement('div');
        card.className = 'phc-card phc-effect-cosmos phc-showcase';
        card.style.cssText = 'width:200px;transform:scale(0) rotateY(180deg);transition:transform 0.8s cubic-bezier(0.34,1.56,0.64,1);';
        card.setAttribute('data-phc-effect', 'cosmos');
        card.setAttribute('data-phc-sparkle', 'true');

        card.innerHTML =
            '<div class="phc-card__translater"><div class="phc-card__rotator" style="border-radius:16px;">' +
            '<div class="phc-card__front" style="width:100%;height:260px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:linear-gradient(135deg,#1a1a2e,#16213e);border-radius:16px;">' +
            '<div style="font-size:72px;line-height:1;margin-bottom:12px;">' + (emoji || '\uD83C\uDF89') + '</div>' +
            '<div style="font-size:20px;font-weight:800;color:#fff;text-shadow:0 0 20px rgba(255,255,255,0.5);">' + (label || 'Level Up!') + '</div>' +
            '</div>' +
            '<div class="phc-card__shine"></div>' +
            '<div class="phc-card__glare"></div>' +
            '</div></div>';

        overlay.appendChild(card);
        document.body.appendChild(overlay);

        // Animate in
        requestAnimationFrame(function() {
            overlay.style.opacity = '1';
            card.style.transform = 'scale(1) rotateY(0deg)';
        });

        // Auto dismiss
        overlay.addEventListener('click', function() { dismiss(); });
        setTimeout(function() { dismiss(); }, 4000);

        function dismiss() {
            card.style.transform = 'scale(0) rotateY(-180deg)';
            overlay.style.opacity = '0';
            setTimeout(function() { overlay.remove(); }, 500);
        }
    }

    // PHC Cross-Plugin: Spin Prize Holo Card (6.3)
    function showHoloPrize(prizeLabel) {
        if (!document.querySelector('.phc-card')) return;

        var popup = document.createElement('div');
        popup.className = 'glr-holo-prize';
        popup.style.cssText = 'position:fixed;bottom:100px;left:50%;transform:translateX(-50%) scale(0);z-index:99998;transition:transform 0.6s cubic-bezier(0.34,1.56,0.64,1);';

        popup.innerHTML =
            '<div class="phc-card phc-effect-galaxy" style="width:160px;" data-phc-effect="galaxy" data-phc-sparkle="true">' +
            '<div class="phc-card__translater"><div class="phc-card__rotator" style="border-radius:12px;">' +
            '<div class="phc-card__front" style="width:100%;height:100px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:12px;">' +
            '<div style="font-size:28px;line-height:1;">\uD83C\uDF81</div>' +
            '<div style="font-size:12px;font-weight:800;color:#92400e;margin-top:6px;text-align:center;padding:0 8px;">' + (prizeLabel || '') + '</div>' +
            '</div>' +
            '<div class="phc-card__shine"></div>' +
            '<div class="phc-card__glare"></div>' +
            '</div></div></div>';

        document.body.appendChild(popup);
        requestAnimationFrame(function() { popup.style.transform = 'translateX(-50%) scale(1)'; });
        setTimeout(function() {
            popup.style.transform = 'translateX(-50%) scale(0)';
            setTimeout(function() { popup.remove(); }, 400);
        }, 3000);
    }

    // PHC Cross-Plugin: Holographic QR Code Frame (6.4)
    function initHoloQR() {
        if (!document.querySelector('.phc-card')) return;

        var qrImages = document.querySelectorAll('img[alt="QR Kod"]');
        qrImages.forEach(function(img) {
            var parent = img.parentElement;
            if (!parent || parent.classList.contains('glr-holo-qr-wrapped')) return;

            parent.classList.add('glr-holo-qr-wrapped');
            var wrapper = document.createElement('div');
            wrapper.className = 'phc-card phc-effect-prism glr-holo-qr';
            wrapper.style.cssText = 'width:' + (img.offsetWidth || 200) + 'px;display:inline-block;';
            wrapper.setAttribute('data-phc-effect', 'prism');
            wrapper.setAttribute('data-phc-radius', '12');

            wrapper.innerHTML =
                '<div class="phc-card__translater"><div class="phc-card__rotator" style="border-radius:12px;">' +
                '<div class="phc-card__front" style="width:100%;display:flex;align-items:center;justify-content:center;background:#fff;border-radius:12px;padding:8px;"></div>' +
                '<div class="phc-card__shine"></div>' +
                '<div class="phc-card__glare"></div>' +
                '</div></div>';

            var frontDiv = wrapper.querySelector('.phc-card__front');
            var imgClone = img.cloneNode(true);
            imgClone.style.cssText = 'max-width:100%;border:none;padding:0;border-radius:8px;';
            frontDiv.appendChild(imgClone);
            img.style.display = 'none';
            img.parentNode.insertBefore(wrapper, img);
        });
    }

    // Loyalty Bar Click Handler
    function initLoyaltyBar() {
        var bar = document.getElementById('gorilla-loyalty-bar');
        if (!bar) return;

        bar.addEventListener('click', function() {
            if (typeof gorillaLR !== 'undefined' && gorillaLR.loyalty_url) {
                window.location.href = gorillaLR.loyalty_url;
            }
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

            var ajaxUrl = (typeof gorillaLR !== 'undefined') ? gorillaLR.ajax_url : '';
            var spinNonce = (typeof gorillaLR !== 'undefined') ? gorillaLR.spin_nonce : '';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxUrl, true);
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
                            showHoloPrize(res.data.label || '');
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
            xhr.send('action=gorilla_spin_wheel&nonce=' + spinNonce);
        });
    }

    // Points Shop Redeem
    function initPointsShop() {
        var shopBtns = document.querySelectorAll('.glr-shop-btn');
        if (!shopBtns.length) return;

        var ajaxUrl = (typeof gorillaLR !== 'undefined') ? gorillaLR.ajax_url : '';
        var shopNonce = (typeof gorillaLR !== 'undefined') ? gorillaLR.shop_nonce : '';

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
                xhr.open('POST', ajaxUrl, true);
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
                xhr.send('action=gorilla_shop_redeem&reward_id=' + rewardId + '&nonce=' + shopNonce);
            });
        });
    }

    // Social Share Tracking
    function initSocialShare() {
        var shareBtns = document.querySelectorAll('.glr-share-btn');
        if (!shareBtns.length) return;

        var ajaxUrl = (typeof gorillaLR !== 'undefined') ? gorillaLR.ajax_url : '';
        var shareNonce = (typeof gorillaLR !== 'undefined') ? gorillaLR.share_nonce : '';

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
                xhr.open('POST', ajaxUrl, true);
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
                xhr.send('action=gorilla_track_share&platform=' + platform + '&nonce=' + shareNonce);
            });
        });
    }

    // Initialize on DOM Ready
    function init() {
        initLoyaltyBar();
        initSpinWheel();
        initPointsShop();
        initSocialShare();
        initHoloQR();
    }

    // Expose cross-plugin functions globally for inline script triggers
    window.showHoloCelebration = showHoloCelebration;
    window.showHoloPrize = showHoloPrize;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
