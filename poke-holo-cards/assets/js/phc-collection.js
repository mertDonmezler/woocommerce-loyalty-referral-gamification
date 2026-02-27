/**
 * PHC Collection & Compare Module
 *
 * Handles card filtering, sorting, selection, lazy-init,
 * and the side-by-side comparison modal.
 *
 * @since 3.1.0
 */
(function () {
    'use strict';

    /* ── Constants ──────────────────────────── */
    var RARITY_SCORE = { common: 1, uncommon: 2, rare: 3, epic: 4, legendary: 5 };
    var MAX_SELECT   = 2;

    /* ── State ──────────────────────────────── */
    var selected    = [];  // product IDs
    var gridEl      = null;
    var compareBtn  = null;
    var countSpan   = null;
    var modalEl     = null;

    /* ── Init ───────────────────────────────── */
    function init() {
        gridEl     = document.querySelector('.phc-collection-grid');
        compareBtn = document.querySelector('.phc-compare-btn');
        countSpan  = document.querySelector('.phc-compare-count');
        modalEl    = document.querySelector('.phc-compare-modal');

        if (!gridEl) return;

        // Filters
        var searchInput  = document.querySelector('.phc-filter-search');
        var raritySelect = document.querySelector('.phc-filter-rarity');
        var effectSelect = document.querySelector('.phc-filter-effect');
        var sortSelect   = document.querySelector('.phc-filter-sort');

        if (searchInput)  searchInput.addEventListener('input', applyFilters);
        if (raritySelect) raritySelect.addEventListener('change', applyFilters);
        if (effectSelect) effectSelect.addEventListener('change', applyFilters);
        if (sortSelect)   sortSelect.addEventListener('change', applySort);

        // Card selection
        gridEl.addEventListener('click', onCardClick);

        // Compare button
        if (compareBtn) {
            compareBtn.addEventListener('click', openCompare);
        }

        // Lazy init with IntersectionObserver
        setupLazyInit();

        // Close modal events
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeCompare();
        });
    }

    /* ── Lazy Init ──────────────────────────── */
    function setupLazyInit() {
        if (!('IntersectionObserver' in window)) {
            // Fallback: init all
            initAllCards();
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    var holoEl = entry.target.querySelector('.phc-card');
                    if (holoEl && window.PokeHoloCards) {
                        window.PokeHoloCards.init(holoEl);
                    }
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '200px' });

        var cards = gridEl.querySelectorAll('.phc-collection-card');
        for (var i = 0; i < cards.length; i++) {
            observer.observe(cards[i]);
        }
    }

    function initAllCards() {
        if (!window.PokeHoloCards) return;
        var holos = gridEl.querySelectorAll('.phc-card');
        for (var i = 0; i < holos.length; i++) {
            window.PokeHoloCards.init(holos[i]);
        }
    }

    /* ── Filtering ──────────────────────────── */
    function applyFilters() {
        var search = (document.querySelector('.phc-filter-search')  || {}).value || '';
        var rarity = (document.querySelector('.phc-filter-rarity')  || {}).value || '';
        var effect = (document.querySelector('.phc-filter-effect')  || {}).value || '';

        search = search.toLowerCase().trim();

        var cards = gridEl.querySelectorAll('.phc-collection-card');
        for (var i = 0; i < cards.length; i++) {
            var card = cards[i];
            var show = true;

            if (search && (card.dataset.name || '').indexOf(search) === -1) {
                show = false;
            }
            if (rarity && (card.dataset.rarity || '') !== rarity) {
                show = false;
            }
            if (effect && (card.dataset.effect || '') !== effect) {
                show = false;
            }

            card.style.display = show ? '' : 'none';
        }
    }

    /* ── Sorting ─────────────────────────────── */
    function applySort() {
        var sortVal = (document.querySelector('.phc-filter-sort') || {}).value || 'date-desc';
        var cards   = Array.prototype.slice.call(gridEl.querySelectorAll('.phc-collection-card'));

        cards.sort(function (a, b) {
            switch (sortVal) {
                case 'date-desc':
                    return b.dataset.date.localeCompare(a.dataset.date);
                case 'date-asc':
                    return a.dataset.date.localeCompare(b.dataset.date);
                case 'name-asc':
                    return a.dataset.name.localeCompare(b.dataset.name);
                case 'name-desc':
                    return b.dataset.name.localeCompare(a.dataset.name);
                case 'rarity-desc':
                    return (parseInt(b.dataset.rarityScore) || 0) - (parseInt(a.dataset.rarityScore) || 0);
                case 'rarity-asc':
                    return (parseInt(a.dataset.rarityScore) || 0) - (parseInt(b.dataset.rarityScore) || 0);
                default:
                    return 0;
            }
        });

        // Re-append in sorted order
        for (var i = 0; i < cards.length; i++) {
            gridEl.appendChild(cards[i]);
        }

        // Re-apply filters to maintain visibility state
        applyFilters();
    }

    /* ── Card Selection ──────────────────────── */
    function onCardClick(e) {
        var cardEl = e.target.closest('.phc-collection-card');
        if (!cardEl) return;

        // Don't select if clicking on the holo card interaction area
        if (e.target.closest('.phc-card__rotator')) return;

        var productId = cardEl.dataset.productId;
        var idx = selected.indexOf(productId);

        if (idx !== -1) {
            // Deselect
            selected.splice(idx, 1);
            cardEl.classList.remove('phc-selected');
        } else if (selected.length < MAX_SELECT) {
            // Select
            selected.push(productId);
            cardEl.classList.add('phc-selected');
        }

        updateCompareButton();
    }

    function updateCompareButton() {
        if (countSpan) countSpan.textContent = selected.length;
        if (compareBtn) compareBtn.disabled = (selected.length !== MAX_SELECT);
    }

    /* ── Compare Modal ───────────────────────── */
    function openCompare() {
        if (selected.length !== 2 || !modalEl) return;
        if (typeof phcCollectionData === 'undefined') return;

        modalEl.innerHTML = '<div class="phc-compare-loading"><div class="phc-spinner"></div></div>';
        modalEl.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        var formData = new FormData();
        formData.append('action', 'phc_compare_cards');
        formData.append('nonce', phcCollectionData.nonce);
        formData.append('product_id_1', selected[0]);
        formData.append('product_id_2', selected[1]);

        fetch(phcCollectionData.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success) {
                closeCompare();
                return;
            }
            renderCompareModal(res.data.card1, res.data.card2);
        })
        .catch(function () {
            closeCompare();
        });
    }

    function renderCompareModal(card1, card2) {
        var html = '' +
            '<div class="phc-compare-backdrop"></div>' +
            '<div class="phc-compare-content">' +
                '<button type="button" class="phc-compare-close" aria-label="Kapat">&times;</button>' +
                '<div class="phc-compare-cards">' +
                    '<div class="phc-compare-card-col">' +
                        '<div class="phc-compare-card-wrap">' + card1.html + '</div>' +
                        '<h3 class="phc-compare-card-name">' + escHtml(card1.name) + '</h3>' +
                    '</div>' +
                    '<div class="phc-compare-vs">VS</div>' +
                    '<div class="phc-compare-card-col">' +
                        '<div class="phc-compare-card-wrap">' + card2.html + '</div>' +
                        '<h3 class="phc-compare-card-name">' + escHtml(card2.name) + '</h3>' +
                    '</div>' +
                '</div>' +
                '<table class="phc-compare-table">' +
                    '<thead><tr><th>' + escHtml(phcCollectionData.i18n.property) + '</th><th>' + escHtml(card1.name) + '</th><th>' + escHtml(card2.name) + '</th></tr></thead>' +
                    '<tbody>' +
                        compareRow(phcCollectionData.i18n.effect, card1.effect, card2.effect, false) +
                        compareRow(phcCollectionData.i18n.rarity, card1.rarity_label, card2.rarity_label, false) +
                        compareRow(phcCollectionData.i18n.price, card1.price, card2.price, true) +
                    '</tbody>' +
                '</table>' +
            '</div>';

        modalEl.innerHTML = html;

        // Init holo effects in modal
        var holoCards = modalEl.querySelectorAll('.phc-card');
        if (window.PokeHoloCards) {
            for (var i = 0; i < holoCards.length; i++) {
                window.PokeHoloCards.init(holoCards[i]);
            }
        }

        // Close events
        var backdrop = modalEl.querySelector('.phc-compare-backdrop');
        var closeBtn = modalEl.querySelector('.phc-compare-close');
        if (backdrop) backdrop.addEventListener('click', closeCompare);
        if (closeBtn) closeBtn.addEventListener('click', closeCompare);
    }

    function compareRow(label, val1, val2, rawHtml) {
        var diff = val1 !== val2 ? ' class="phc-compare-diff"' : '';
        var v1 = rawHtml ? val1 : escHtml(val1);
        var v2 = rawHtml ? val2 : escHtml(val2);
        return '<tr' + diff + '><td>' + escHtml(label) + '</td><td>' + v1 + '</td><td>' + v2 + '</td></tr>';
    }

    function closeCompare() {
        if (modalEl) {
            modalEl.style.display = 'none';
            modalEl.innerHTML = '';
        }
        document.body.style.overflow = '';
    }

    /* ── Helpers ──────────────────────────────── */
    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /* ── Boot ─────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.PHCCollection = { init: init, closeCompare: closeCompare };
})();
