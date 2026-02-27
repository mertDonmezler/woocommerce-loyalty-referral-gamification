/**
 * WP Gamify - Musteri Dashboard JavaScript
 *
 * Progress bar animasyonu, XP sayac animasyonu ve
 * REST API uzerinden "Daha Fazla Goster" islevseligi.
 *
 * Bagimlilik: Yok (vanilla JS). jQuery kullanmaz.
 * wpgamify global degiskeni wp_localize_script ile saglanir.
 *
 * @package    WPGamify
 * @subpackage Frontend\Assets
 * @since      1.0.0
 */

(function () {
    'use strict';

    /**
     * Azaltilmis hareket tercihini kontrol eder.
     *
     * @returns {boolean} Kullanici azaltilmis hareket istiyorsa true.
     */
    function prefersReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    /**
     * Dashboard islevsellik modulu.
     */
    var WPGamifyDashboard = {

        /** Mevcut gecmis sayfa numarasi. */
        page: 1,

        /**
         * Tum dashboard islevlerini baslatir.
         */
        init: function () {
            this.animateProgressBar();
            this.animateCounters();
            this.bindLoadMore();
        },

        /**
         * Progress bar genisligini sayfa yuklendiginde animasyonla doldurur.
         *
         * data-progress attribute'undan hedef yuzdeyi okur
         * ve CSS transition ile genisligi ayarlar.
         * Azaltilmis hareket tercihinde animasyonsuz ayarlar.
         */
        animateProgressBar: function () {
            var bar = document.querySelector('.wpg-progress__fill');
            if (!bar) return;

            var target = parseFloat(bar.dataset.progress) || 0;

            if (prefersReducedMotion()) {
                bar.style.width = target + '%';
                return;
            }

            requestAnimationFrame(function () {
                bar.style.width = target + '%';
            });
        },

        /**
         * XP sayaclarini sifirdan hedef degere animasyonla saydirir.
         *
         * data-count attribute'u olan tum elementleri bulur
         * ve easeOutCubic easing ile 1 saniyede hedefe ulasir.
         * Turkce sayi formatini (nokta ayirici) kullanir.
         * Azaltilmis hareket tercihinde animasyonsuz gosterir.
         */
        animateCounters: function () {
            var elements = document.querySelectorAll('[data-count]');
            if (!elements.length) return;

            elements.forEach(function (el) {
                var target = parseInt(el.dataset.count, 10);

                if (isNaN(target)) {
                    return;
                }

                // Azaltilmis hareket: animasyonsuz goster.
                if (prefersReducedMotion()) {
                    el.textContent = target.toLocaleString('tr-TR');
                    return;
                }

                var duration = 1000;
                var startTime = null;

                /**
                 * Animasyon frame callback.
                 *
                 * @param {number} now Zaman damgasi (ms).
                 */
                function tick(now) {
                    if (startTime === null) startTime = now;

                    var elapsed = now - startTime;
                    var progress = Math.min(elapsed / duration, 1);

                    // easeOutCubic: 1 - (1 - t)^3
                    var eased = 1 - Math.pow(1 - progress, 3);
                    var current = Math.floor(target * eased);

                    el.textContent = current.toLocaleString('tr-TR');

                    if (progress < 1) {
                        requestAnimationFrame(tick);
                    } else {
                        // Tam deger ile bitir.
                        el.textContent = target.toLocaleString('tr-TR');
                    }
                }

                requestAnimationFrame(tick);
            });
        },

        /**
         * "Daha Fazla Goster" butonuna tiklama olayi baglar.
         *
         * Her tiklamada bir sonraki sayfa REST API'den yuklenir.
         * Sonuc yoksa veya has_more false ise buton kaldirilir.
         * Hata durumunda "Tekrar Dene" metni gosterilir.
         */
        bindLoadMore: function () {
            var btn = document.querySelector('.wpg-load-more');
            if (!btn) return;

            var self = this;

            btn.addEventListener('click', function () {
                self.page++;
                btn.disabled = true;
                btn.setAttribute('aria-busy', 'true');
                btn.textContent = 'Yukleniyor...';

                self.fetchHistory(self.page)
                    .then(function (data) {
                        if (data.items && data.items.length > 0) {
                            self.appendHistory(data.items);
                            btn.disabled = false;
                            btn.setAttribute('aria-busy', 'false');
                            btn.textContent = 'Daha Fazla Goster';

                            if (!data.has_more) {
                                btn.remove();
                            }
                        } else {
                            btn.remove();
                        }
                    })
                    .catch(function () {
                        self.page--; // Basarisiz sayfayi geri al.
                        btn.disabled = false;
                        btn.setAttribute('aria-busy', 'false');
                        btn.textContent = 'Tekrar Dene';
                    });
            });
        },

        /**
         * REST API'den XP gecmisi sayfasini yukler.
         *
         * @param {number} page Sayfa numarasi.
         * @returns {Promise<Object>} API yaniti (items, has_more, page).
         */
        fetchHistory: function (page) {
            var url = wpgamify.rest_url + 'user/xp-history?page=' + encodeURIComponent(page);

            return fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': wpgamify.nonce,
                    'Content-Type': 'application/json'
                }
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                });
        },

        /**
         * Yuklenen XP gecmisi satirlarini tabloya ekler.
         *
         * Her satir icin yeni bir <tr> olusturur ve
         * mevcut tbody'ye ekler.
         *
         * @param {Array<Object>} items XP gecmis ogeleri.
         */
        appendHistory: function (items) {
            var tbody = document.querySelector('.wpg-history-table tbody');
            if (!tbody) return;

            var currencyLabel = wpgamify.currency_label || 'XP';

            items.forEach(function (item) {
                var tr = document.createElement('tr');
                var amount = parseInt(item.amount, 10);
                var amountClass = amount >= 0 ? 'wpg-xp-positive' : 'wpg-xp-negative';
                var sign = amount >= 0 ? '+' : '';
                var formattedAmount = sign + amount.toLocaleString('tr-TR') + ' ' + currencyLabel;

                // data-label attribute'lari mobil gorunum icin.
                tr.innerHTML =
                    '<td data-label="Tarih">' + escapeHtml(item.date || '-') + '</td>' +
                    '<td data-label="Kaynak">' + escapeHtml(item.source_label || '-') + '</td>' +
                    '<td data-label="Miktar" class="' + amountClass + '">' + escapeHtml(formattedAmount) + '</td>' +
                    '<td data-label="Not">' + escapeHtml(item.note || '-') + '</td>';

                tbody.appendChild(tr);
            });
        }
    };

    /**
     * HTML ozel karakterlerini escape eder (XSS korunmasi).
     *
     * @param {string} str Escape edilecek metin.
     * @returns {string} Guvenli HTML metni.
     */
    function escapeHtml(str) {
        if (typeof str !== 'string') return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return str.replace(/[&<>"']/g, function (m) {
            return map[m];
        });
    }

    /**
     * DOM hazir oldugunda dashboard'u baslatir.
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            WPGamifyDashboard.init();
        });
    } else {
        WPGamifyDashboard.init();
    }
})();
