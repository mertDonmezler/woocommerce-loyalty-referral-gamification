/**
 * PHC Pack Opening Animation
 *
 * Cinematic card-pack-opening experience with stagger reveals,
 * rarity-based effects, and summary screen.
 *
 * @since 3.1.0
 */
(function () {
    'use strict';

    /* ── Config ──────────────────────────────── */
    var STAGGER_DELAY = 400;  // ms between card reveals
    var OPEN_DURATION = 800;  // ms for pack open animation
    var CARD_FLIP_MS  = 600;  // ms for card flip
    var RARITY_COLORS = {
        common:    'rgba(158, 158, 158, 0.3)',
        uncommon:  'rgba(76, 175, 80, 0.4)',
        rare:      'rgba(33, 150, 243, 0.5)',
        epic:      'rgba(156, 39, 176, 0.6)',
        legendary: 'rgba(255, 152, 0, 0.8)'
    };

    /* ── State ───────────────────────────────── */
    var container   = null;
    var cards       = [];
    var rarityLabels = {};
    var currentCard  = 0;
    var skipped      = false;
    var opened       = false;
    var summaryShown = false;

    /* ── Init ────────────────────────────────── */
    function init() {
        container = document.querySelector('.phc-pack-opening-container');
        if (!container) return;

        // Parse card data
        var dataEl = container.querySelector('.phc-pack-data');
        var labelsEl = container.querySelector('.phc-rarity-labels');
        try {
            cards = JSON.parse(dataEl.textContent);
            rarityLabels = JSON.parse(labelsEl.textContent);
        } catch (e) {
            return;
        }

        if (!cards.length) return;

        // Bind events
        var packBox = container.querySelector('.phc-pack-box');
        if (packBox) packBox.addEventListener('click', openPack);

        var skipBtn = container.querySelector('.phc-skip-btn');
        if (skipBtn) skipBtn.addEventListener('click', skipAll);
    }

    /* ── Pack Open ───────────────────────────── */
    function openPack() {
        if (opened) return;
        opened = true;

        var boxWrap = container.querySelector('.phc-pack-box-wrap');
        if (!boxWrap) return;
        boxWrap.classList.add('phc-pack-opening-anim');

        // Mark as opened via AJAX
        markOpened();

        setTimeout(function () {
            boxWrap.style.display = 'none';
            revealCards();
        }, OPEN_DURATION);
    }

    /* ── Card Reveals ────────────────────────── */
    function revealCards() {
        var area = container.querySelector('.phc-pack-reveal-area');
        area.innerHTML = '';
        currentCard = 0;

        revealNext(area);
    }

    function revealNext(area) {
        if (skipped || currentCard >= cards.length) {
            showSummary();
            return;
        }

        var card = cards[currentCard];
        currentCard++;

        // Create card wrapper
        var wrapper = document.createElement('div');
        wrapper.className = 'phc-pack-card-wrapper phc-pack-card-entering';
        wrapper.setAttribute('data-rarity', card.rarity);

        // Back face (mystery)
        var back = document.createElement('div');
        back.className = 'phc-pack-card-back';
        back.innerHTML = '<span class="phc-pack-card-back-icon">?</span>';
        wrapper.appendChild(back);

        // Front face (actual card)
        var front = document.createElement('div');
        front.className = 'phc-pack-card-front';
        front.innerHTML = card.html;
        wrapper.appendChild(front);

        // Card name
        var nameEl = document.createElement('div');
        nameEl.className = 'phc-pack-card-name';
        nameEl.textContent = card.name;
        wrapper.appendChild(nameEl);

        // Rarity label
        var rarityEl = document.createElement('div');
        rarityEl.className = 'phc-pack-rarity-label phc-rarity-' + card.rarity;
        rarityEl.textContent = rarityLabels[card.rarity] || card.rarity;
        wrapper.appendChild(rarityEl);

        area.innerHTML = '';
        area.appendChild(wrapper);

        // Trigger enter animation
        requestAnimationFrame(function () {
            wrapper.classList.remove('phc-pack-card-entering');
            wrapper.classList.add('phc-pack-card-entered');
        });

        // Flip after brief pause
        setTimeout(function () {
            wrapper.classList.add('phc-pack-card-flipped');
            triggerRarityFlash(card.rarity);

            // Init holo effect
            setTimeout(function () {
                var holoEl = front.querySelector('.phc-card');
                if (holoEl && window.PokeHoloCards) {
                    window.PokeHoloCards.init(holoEl);
                }
            }, CARD_FLIP_MS / 2);
        }, 300);

        // Next card
        setTimeout(function () {
            if (!skipped) revealNext(area);
        }, STAGGER_DELAY + CARD_FLIP_MS);
    }

    /* ── Rarity Flash Effects ────────────────── */
    function triggerRarityFlash(rarity) {
        var overlay = container.querySelector('.phc-pack-overlay');
        if (!overlay) return;

        if (rarity === 'common' || rarity === 'uncommon') return;

        var flash = document.createElement('div');
        flash.className = 'phc-rarity-flash phc-rarity-flash-' + rarity;
        overlay.appendChild(flash);

        // Legendary: screen shake
        if (rarity === 'legendary') {
            overlay.classList.add('phc-pack-shake');
            setTimeout(function () {
                overlay.classList.remove('phc-pack-shake');
            }, 500);
        }

        setTimeout(function () {
            if (flash.parentNode) flash.parentNode.removeChild(flash);
        }, 1000);
    }

    /* ── Skip ────────────────────────────────── */
    function skipAll() {
        skipped = true;
        if (!opened) {
            opened = true;
            markOpened();
            var boxWrap = container.querySelector('.phc-pack-box-wrap');
            if (boxWrap) boxWrap.style.display = 'none';
        }
        showSummary();
    }

    /* ── Summary ─────────────────────────────── */
    function showSummary() {
        if (summaryShown) return;
        summaryShown = true;

        var overlay    = container.querySelector('.phc-pack-overlay');
        var revealArea = container.querySelector('.phc-pack-reveal-area');
        var summary    = container.querySelector('.phc-pack-summary');
        var grid       = container.querySelector('.phc-pack-summary-grid');
        var stats      = container.querySelector('.phc-pack-summary-stats');

        if (!summary || !grid || !stats) return;

        if (revealArea) revealArea.style.display = 'none';
        var skipBtn = container.querySelector('.phc-skip-btn');
        if (skipBtn) skipBtn.style.display = 'none';

        // Render all cards in grid
        grid.innerHTML = '';
        for (var i = 0; i < cards.length; i++) {
            var card = cards[i];
            var item = document.createElement('div');
            item.className = 'phc-pack-summary-card';
            item.setAttribute('data-rarity', card.rarity);
            item.innerHTML = card.html +
                '<div class="phc-pack-summary-card-info">' +
                    '<span class="phc-pack-summary-card-name">' + escHtml(card.name) + '</span>' +
                    '<span class="phc-rarity-badge phc-rarity-' + card.rarity + '">' +
                        escHtml(rarityLabels[card.rarity] || card.rarity) +
                    '</span>' +
                '</div>';
            grid.appendChild(item);
        }

        // Init all holo effects
        var holos = grid.querySelectorAll('.phc-card');
        if (window.PokeHoloCards) {
            for (var j = 0; j < holos.length; j++) {
                window.PokeHoloCards.init(holos[j]);
            }
        }

        // Rarity stats
        var rarityCounts = {};
        for (var k = 0; k < cards.length; k++) {
            var r = cards[k].rarity;
            rarityCounts[r] = (rarityCounts[r] || 0) + 1;
        }
        var statsHtml = '<div class="phc-pack-stats-row">';
        var order = ['legendary', 'epic', 'rare', 'uncommon', 'common'];
        for (var m = 0; m < order.length; m++) {
            var key = order[m];
            if (rarityCounts[key]) {
                statsHtml += '<span class="phc-pack-stat-item phc-rarity-' + key + '">' +
                    escHtml(rarityLabels[key] || key) + ': ' + rarityCounts[key] +
                '</span>';
            }
        }
        statsHtml += '</div>';
        stats.innerHTML = statsHtml;

        summary.style.display = 'block';
        summary.classList.add('phc-pack-summary-enter');
    }

    /* ── AJAX: Mark Opened ───────────────────── */
    function markOpened() {
        var orderId = container.dataset.orderId;
        var nonce   = container.dataset.nonce;
        var ajaxUrl = container.dataset.ajaxUrl;

        var formData = new FormData();
        formData.append('action', 'phc_mark_opened');
        formData.append('nonce', nonce);
        formData.append('order_id', orderId);

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).catch(function () { /* silent */ });
    }

    /* ── Helpers ──────────────────────────────── */
    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    /* ── Boot ─────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.PHCPackOpening = { init: init };
})();
