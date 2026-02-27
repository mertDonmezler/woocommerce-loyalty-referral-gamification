/**
 * WooCommerce Holo Cards - Vanilla JS Interaction Engine
 * Adapted from pokemon-cards-css by Simon Goellner (GPL-3.0)
 * Converts Svelte spring animations to vanilla JS
 *
 * Features:
 *   - Spring physics for smooth 3D card tilting
 *   - Spring presets (bouncy, stiff, smooth, elastic)
 *   - Showcase auto-rotation mode
 *   - IntersectionObserver viewport culling
 *   - Sparkle canvas overlay
 *   - Debounced resize rect invalidation
 *   - Per-card data-attribute overrides
 *   - Keyboard accessibility (arrow keys, space, escape)
 *   - WCAG 2.1 AA: ARIA roles, focus trap, reduced-motion, screen reader support
 *   - Back-face support with flip animation
 *   - Parallax depth (separate shine/glare layers)
 *   - Pointer angle CSS variable
 *   - Custom phc:* events
 *   - Performance monitoring for low-end devices
 *   - requestIdleCallback deferred initialization
 *   - Mobile touch gestures: swipe nav, pinch-to-zoom, double-tap zoom
 *   - Gallery horizontal swipe with scroll-snap on mobile
 */
(function () {
    'use strict';

    /* ===================================================================
       SPRING PHYSICS ENGINE
       =================================================================== */

    /**
     * Critically-damped spring that works on scalars or plain objects.
     * @param {number|Object} initial  - Starting value
     * @param {number}        stiffness
     * @param {number}        damping
     */
    function Spring(initial, stiffness, damping) {
        this.target = typeof initial === 'object' ? assign({}, initial) : initial;
        this.current = typeof initial === 'object' ? assign({}, initial) : initial;
        this.velocity = typeof initial === 'object'
            ? Object.keys(initial).reduce(function (a, k) { a[k] = 0; return a; }, {})
            : 0;
        this.stiffness = stiffness || 0.066;
        this.damping = damping || 0.25;
        this.precision = 0.01;
    }

    /** @private Shallow copy own properties from source to target. */
    function assign(target, source) {
        if (!source || typeof source !== 'object') return target;
        for (var k in source) {
            if (source.hasOwnProperty(k)) target[k] = source[k];
        }
        return target;
    }

    /**
     * Set a new target for the spring.
     * @param {number|Object} target
     * @param {Object}        [opts]       - { hard: true } to snap immediately
     */
    Spring.prototype.set = function (target, opts) {
        if (opts && opts.hard) {
            if (typeof target === 'object') {
                this.current = assign({}, target);
                this.target = assign({}, target);
                var v = this.velocity;
                Object.keys(v).forEach(function (k) { v[k] = 0; });
            } else {
                this.current = target;
                this.target = target;
                this.velocity = 0;
            }
            return;
        }
        if (typeof target === 'object') {
            this.target = assign({}, target);
        } else {
            this.target = target;
        }
    };

    /**
     * Advance one simulation step.
     * @returns {boolean} true when the spring has settled
     */
    Spring.prototype.tick = function () {
        var settled = true;
        if (typeof this.target === 'object') {
            var keys = Object.keys(this.target);
            for (var i = 0; i < keys.length; i++) {
                var k = keys[i];
                var displacement = this.current[k] - this.target[k];
                var springForce = -this.stiffness * displacement;
                var dampingForce = -this.damping * this.velocity[k];
                this.velocity[k] += springForce + dampingForce;
                this.current[k] += this.velocity[k];
                if (Math.abs(this.velocity[k]) > this.precision || Math.abs(displacement) > this.precision) {
                    settled = false;
                }
            }
        } else {
            var disp = this.current - this.target;
            var sf = -this.stiffness * disp;
            var df = -this.damping * this.velocity;
            this.velocity += sf + df;
            this.current += this.velocity;
            if (Math.abs(this.velocity) > this.precision || Math.abs(disp) > this.precision) {
                settled = false;
            }
        }
        return settled;
    };

    /** @returns {number|Object} current interpolated value */
    Spring.prototype.value = function () {
        return this.current;
    };

    /* ===================================================================
       SPRING PRESETS
       =================================================================== */

    /** @type {Object.<string, {stiffness: number, damping: number}>} */
    var SPRING_PRESETS = {
        bouncy:  { stiffness: 0.04,  damping: 0.12 },
        stiff:   { stiffness: 0.15,  damping: 0.35 },
        smooth:  { stiffness: 0.066, damping: 0.25 },
        elastic: { stiffness: 0.03,  damping: 0.08 }
    };

    /* ===================================================================
       HELPER FUNCTIONS
       =================================================================== */

    /** Clamp a number between min (default 0) and max (default 100). */
    function clamp(val, min, max) {
        if (min === undefined) min = 0;
        if (max === undefined) max = 100;
        val = Number(val);
        if (isNaN(val)) return min;
        return Math.min(Math.max(val, min), max);
    }

    /** Round to `dec` decimal places (default 2). */
    function round(val, dec) {
        if (dec === undefined) dec = 2;
        return Math.round(val * Math.pow(10, dec)) / Math.pow(10, dec);
    }

    /** Linear interpolation from one range to another. */
    function adjust(val, fromMin, fromMax, toMin, toMax) {
        var range = fromMax - fromMin;
        if (range === 0) return (toMin + toMax) / 2;
        return toMin + ((val - fromMin) / range) * (toMax - toMin);
    }

    /**
     * Dispatch a namespaced custom event on an element.
     * @param {HTMLElement} el    - The element to dispatch from
     * @param {string}      name  - Event name (e.g. 'phc:enter')
     * @param {Object}     [detail] - Optional detail payload
     */
    function dispatchPhcEvent(el, name, detail) {
        if (typeof CustomEvent !== 'undefined') {
            el.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: detail || {} }));
        }
    }

    /* ===================================================================
       PERFORMANCE MONITOR
       Measures initial FPS for 1 second and flags low-end devices.
       =================================================================== */

    var PerformanceMonitor = {
        _fps: 60,
        _checked: false,
        _lowEnd: false,
        start: function () {
            if (this._checked) return;
            var self = this;
            var count = 0;
            var start = performance.now();
            function tick() {
                count++;
                if (performance.now() - start < 1000) {
                    requestAnimationFrame(tick);
                } else {
                    self._fps = count;
                    self._lowEnd = count < 40;
                    self._checked = true;
                }
            }
            requestAnimationFrame(tick);
        },
        isLowEnd: function () { return this._lowEnd; }
    };
    // Defer performance monitoring to avoid blocking initial parse
    if (typeof requestIdleCallback !== 'undefined') {
        requestIdleCallback(function () { PerformanceMonitor.start(); }, { timeout: 3000 });
    } else {
        setTimeout(function () { PerformanceMonitor.start(); }, 100);
    }

    /* ===================================================================
       GLOBAL DEVICE ORIENTATION HANDLER
       (single listener, dispatches to all active cards)
       =================================================================== */
    var orientationCards = [];
    var orientationListenerAdded = false;

    function globalOrientationHandler(e) {
        for (var i = 0; i < orientationCards.length; i++) {
            orientationCards[i]._onOrientation(e);
        }
    }

    function registerOrientation(card) {
        orientationCards.push(card);
        if (!orientationListenerAdded) {
            orientationListenerAdded = true;
            window.addEventListener('deviceorientation', globalOrientationHandler);
        }
    }

    function unregisterOrientation(card) {
        var idx = orientationCards.indexOf(card);
        if (idx !== -1) orientationCards.splice(idx, 1);
        if (orientationCards.length === 0 && orientationListenerAdded) {
            window.removeEventListener('deviceorientation', globalOrientationHandler);
            orientationListenerAdded = false;
        }
    }

    /* ===================================================================
       SETTINGS (with safe defaults)
       =================================================================== */
    var settings = (typeof phcSettings !== 'undefined') ? phcSettings : {
        effectType: 'holo',
        hoverScale: 1.05,
        perspective: 600,
        springStiffness: 0.066,
        springDamping: 0.25,
        glareOpacity: 0.8,
        shineIntensity: 1,
        glowColor: '#58e0d9',
        borderRadius: 4.55,
        autoInitClass: 'phc-card',
        gyroscope: true
    };

    /* ===================================================================
       REDUCED MOTION DETECTION
       Skip spring animations when user prefers reduced motion.
       =================================================================== */
    var reducedMotionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    var prefersReducedMotion = reducedMotionQuery ? reducedMotionQuery.matches : false;

    // Listen for dynamic changes (e.g. user toggles OS setting while page is open)
    if (reducedMotionQuery && reducedMotionQuery.addEventListener) {
        reducedMotionQuery.addEventListener('change', function (e) {
            prefersReducedMotion = e.matches;
            // When reduced motion is enabled, stop all running sparkles and showcases
            if (prefersReducedMotion) {
                for (var i = 0; i < allLiveCards.length; i++) {
                    var c = allLiveCards[i];
                    if (c._sparkle) c._sparkle.stop();
                    if (c._isShowcase) c._stopShowcase();
                }
            }
        });
    }

    // Override Spring.set to snap immediately when reduced motion is preferred
    var _originalSpringSet = Spring.prototype.set;
    Spring.prototype.set = function (target, opts) {
        if (prefersReducedMotion) {
            opts = opts || {};
            opts.hard = true; // snap to target, no animation
        }
        return _originalSpringSet.call(this, target, opts);
    };

    /* ===================================================================
       GLOBAL RESIZE HANDLER  (Feature 4)
       Single debounced listener that invalidates every card's cached rect.
       =================================================================== */
    var resizeTimer = null;
    var allLiveCards = [];   // plain array of HoloCard instances for resize pass

    function onWindowResize() {
        if (resizeTimer) clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            resizeTimer = null;
            for (var i = 0; i < allLiveCards.length; i++) {
                allLiveCards[i].cachedRect = null;
            }
        }, 200);
    }
    window.addEventListener('resize', onWindowResize);

    /* ===================================================================
       INTERSECTION OBSERVER  (Feature 2)
       Pauses/resumes animation loops for off-screen cards.
       =================================================================== */
    var viewportObserver = null;

    if (typeof IntersectionObserver !== 'undefined') {
        viewportObserver = new IntersectionObserver(function (entries) {
            for (var i = 0; i < entries.length; i++) {
                var entry = entries[i];
                var card = cardInstances.get(entry.target);
                if (!card) continue;

                if (entry.isIntersecting) {
                    card._inViewport = true;
                    // If the card was waiting to animate, kick it off
                    if (card._wantsAnimation && !card.animating) {
                        card._startAnimation();
                    }
                    // If showcase was running before going off-screen, resume it
                    if (card._isShowcase && !card._showcaseInterval && !card.interacting) {
                        card._beginShowcaseAfterDelay(0);
                    }
                } else {
                    card._inViewport = false;
                    // Pause animation loop -- it will naturally stop next rAF tick
                    card.animating = false;
                    // Pause showcase interval to save CPU
                    card._stopShowcase();
                }
            }
        }, { rootMargin: '50px' });
    }

    /* ===================================================================
       SPARKLE CANVAS  (Feature 3 - helper)
       =================================================================== */

    /**
     * Manages a canvas overlay that draws shimmering dots on top of a card.
     * @param {HTMLElement} container - The .phc-card element to overlay
     */
    function SparkleCanvas(container) {
        this.container = container;
        this.canvas = document.createElement('canvas');
        this.ctx = this.canvas.getContext('2d');
        this.particles = [];
        this.running = false;
        this._rafId = null;

        // Style the canvas so it sits on top of the card content
        var s = this.canvas.style;
        s.position = 'absolute';
        s.top = '0';
        s.left = '0';
        s.width = '100%';
        s.height = '100%';
        s.pointerEvents = 'none';
        s.mixBlendMode = 'screen';
        s.zIndex = '5';

        var computedPos = window.getComputedStyle(container).position;
        if (!computedPos || computedPos === 'static') {
            container.style.position = 'relative';
        }
        container.appendChild(this.canvas);

        this._resize();
        this._initParticles();
    }

    SparkleCanvas.prototype._resize = function () {
        var rect = this.container.getBoundingClientRect();
        var dpr = window.devicePixelRatio || 1;
        this.canvas.width = Math.round(rect.width * dpr);
        this.canvas.height = Math.round(rect.height * dpr);
        this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        this._w = rect.width;
        this._h = rect.height;
    };

    SparkleCanvas.prototype._initParticles = function () {
        var count = 15 + Math.floor(Math.random() * 11); // 15-25
        this.particles = [];
        for (var i = 0; i < count; i++) {
            this.particles.push(this._makeParticle());
        }
    };

    SparkleCanvas.prototype._makeParticle = function () {
        return {
            x: Math.random() * (this._w || 100),
            y: Math.random() * (this._h || 100),
            r: 1 + Math.random() * 1.5,          // radius 1-2.5 (rendered as 2-5px diameter)
            life: Math.random(),                   // 0..1 phase
            speed: 0.005 + Math.random() * 0.015,  // phase increment per frame
            maxAlpha: 0.5 + Math.random() * 0.5
        };
    };

    SparkleCanvas.prototype.start = function () {
        if (this.running) return;
        this.running = true;
        this._resize();
        this._loop();
    };

    SparkleCanvas.prototype.stop = function () {
        this.running = false;
        if (this._rafId) {
            cancelAnimationFrame(this._rafId);
            this._rafId = null;
        }
        // Clear canvas
        this.ctx.clearRect(0, 0, this._w, this._h);
    };

    SparkleCanvas.prototype._loop = function () {
        if (!this.running) return;
        var ctx = this.ctx;
        ctx.clearRect(0, 0, this._w, this._h);

        for (var i = 0; i < this.particles.length; i++) {
            var p = this.particles[i];
            p.life += p.speed;
            if (p.life > 1) {
                // Respawn at new position
                this.particles[i] = this._makeParticle();
                this.particles[i].life = 0;
                p = this.particles[i];
            }

            // Fade in then fade out using a sine curve over the lifetime
            var alpha = Math.sin(p.life * Math.PI) * p.maxAlpha;
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(255,255,255,' + round(alpha, 3) + ')';
            ctx.fill();
        }

        var self = this;
        this._rafId = requestAnimationFrame(function () { self._loop(); });
    };

    SparkleCanvas.prototype.destroy = function () {
        this.stop();
        if (this.canvas.parentNode) {
            this.canvas.parentNode.removeChild(this.canvas);
        }
    };

    /* ===================================================================
       HOLO CARD CLASS
       =================================================================== */

    /**
     * Core interactive card controller.
     * @param {HTMLElement} el - The root `.phc-card` element
     */
    function HoloCard(el) {
        this.el = el;
        this.interacting = false;
        this.active = false;
        this.animating = false;
        this.pendingUpdate = null;
        this.cachedRect = null;

        // Viewport tracking (Feature 2)
        this._inViewport = true;
        this._wantsAnimation = false;

        // Timeout IDs for cleanup
        this._leaveTimer1 = null;
        this._leaveTimer2 = null;

        // Showcase state (Feature 1)
        this._isShowcase = false;
        this._showcaseInterval = null;
        this._showcaseTimeout = null;
        this._showcaseAngle = 0;
        this._showcaseResumeTimeout = null;

        // Sparkle overlay (Feature 3)
        this._sparkle = null;

        // Flip state
        this._flipped = false;

        // Keyboard state
        this._kbX = 50;
        this._kbY = 50;
        this._kbIdleTimer = null;

        // Pointer angle tracking
        this._lastAngle = 0;

        // Low-end device flag
        this._reducedEffects = false;

        var stiff = parseFloat(settings.springStiffness) || 0.066;
        var damp = parseFloat(settings.springDamping) || 0.25;

        // Check for spring preset from data attribute or global settings
        var presetName = el.getAttribute('data-phc-spring') || (settings.springPreset || '');
        if (presetName && SPRING_PRESETS[presetName]) {
            stiff = SPRING_PRESETS[presetName].stiffness;
            damp = SPRING_PRESETS[presetName].damping;
        }

        // Store original spring settings for restoration
        this._origStiff = stiff;
        this._origDamp = damp;

        this.springRotate = new Spring({ x: 0, y: 0 }, stiff, damp);
        this.springGlare = new Spring({ x: 50, y: 50, o: 0 }, stiff, damp);
        this.springBackground = new Spring({ x: 50, y: 50 }, stiff, damp);
        this.springScale = new Spring(1, 0.033, 0.45);

        // Bound event handlers (for removal)
        this._boundPointerEnter = this._onPointerEnter.bind(this);
        this._boundPointerMove = this._onPointerMove.bind(this);
        this._boundPointerLeave = this._onPointerLeave.bind(this);
        this._boundTouchMove = this._onTouchMove.bind(this);
        this._boundTouchEnd = this._onPointerLeave.bind(this);
        this._boundKeyDown = this._onKeyDown.bind(this);
        this._boundDblClick = this._onDblClick.bind(this);

        this._init();
    }

    /* -------------------------------------------------------------------
       INIT  (reads per-card data attributes - Feature 5)
       ------------------------------------------------------------------- */
    HoloCard.prototype._init = function () {
        var rotator = this.el.querySelector('.phc-card__rotator');
        if (!rotator) {
            rotator = this.el;
        }
        this.rotator = rotator;

        /* -- Per-card overrides (Feature 5) -------------------------------- */

        // Glow colour
        var glow = this.el.getAttribute('data-phc-glow') || settings.glowColor;
        if (glow) {
            this.el.style.setProperty('--phc-card-glow', glow);
        }

        // Border radius
        var brAttr = this.el.getAttribute('data-phc-radius');
        var br = brAttr !== null ? parseFloat(brAttr) : (parseFloat(settings.borderRadius) || 4.55);
        this.el.style.setProperty('--phc-card-radius', br + '% / ' + round(br * 0.769, 1) + '%');

        // Perspective
        var perspAttr = this.el.getAttribute('data-phc-perspective');
        var perspective = perspAttr !== null ? parseInt(perspAttr, 10) : (parseInt(settings.perspective, 10) || 600);
        var translater = this.el.querySelector('.phc-card__translater');
        if (translater) {
            translater.style.perspective = perspective + 'px';
        }

        /* -- Keyboard accessibility ---------------------------------------- */
        this.el.setAttribute('tabindex', '0');
        this.el.setAttribute('role', 'img');
        this.el.setAttribute('aria-roledescription', 'holographic card');

        // Set aria-label from the front image alt text
        var frontImg = this.el.querySelector('.phc-card__front');
        if (frontImg && frontImg.alt) {
            this.el.setAttribute('aria-label', frontImg.alt);
        } else {
            this.el.setAttribute('aria-label', 'Holographic card');
        }

        // Add aria-describedby for keyboard instructions
        if (!document.getElementById('phc-kb-instructions')) {
            var instrEl = document.createElement('div');
            instrEl.id = 'phc-kb-instructions';
            instrEl.className = 'phc-sr-only';
            instrEl.textContent = 'Use arrow keys to tilt the card. Press Space or Enter to flip. Press Escape to reset.';
            document.body.appendChild(instrEl);
        }
        this.el.setAttribute('aria-describedby', 'phc-kb-instructions');

        /* -- Event listeners ------------------------------------------------ */
        rotator.addEventListener('pointerenter', this._boundPointerEnter);
        rotator.addEventListener('pointermove', this._boundPointerMove);
        rotator.addEventListener('pointerleave', this._boundPointerLeave);
        rotator.addEventListener('touchmove', this._boundTouchMove, { passive: true });
        rotator.addEventListener('touchend', this._boundTouchEnd);
        this.el.addEventListener('keydown', this._boundKeyDown);

        // Double-click to flip (only if card has a back face)
        if (this.el.getAttribute('data-phc-back')) {
            rotator.addEventListener('dblclick', this._boundDblClick);
        }

        // Device orientation (single global listener) - skip when reduced motion
        if (settings.gyroscope && window.DeviceOrientationEvent && !prefersReducedMotion) {
            this._baseOrientation = null;
            registerOrientation(this);
        }

        this.el.classList.add('phc-interactive');

        // Register in resize list
        allLiveCards.push(this);

        // Observe visibility (Feature 2)
        if (viewportObserver) {
            viewportObserver.observe(this.el);
        }

        /* -- Low-end device detection -------------------------------------- */
        if (PerformanceMonitor.isLowEnd()) {
            this._reducedEffects = true;
            this.springRotate.precision = 0.1;
            // Skip sparkle init even if data-phc-sparkle is set on low-end devices
        }

        /* -- Sparkle canvas (Feature 3) ------------------------------------ */
        if (this.el.getAttribute('data-phc-sparkle') === 'true' && !this._reducedEffects && !prefersReducedMotion) {
            this._sparkle = new SparkleCanvas(this.rotator || this.el);
        }

        /* -- Showcase mode (Feature 1) ------------------------------------- */
        if (
            (this.el.getAttribute('data-phc-showcase') === 'true' ||
            this.el.classList.contains('phc-showcase')) &&
            !prefersReducedMotion
        ) {
            this._isShowcase = true;
            this._beginShowcaseAfterDelay(1000);
        }

        /* -- Flip state ARIA ------------------------------------------------ */
        if (this.el.getAttribute('data-phc-back')) {
            this.el.setAttribute('aria-pressed', 'false');
        }
    };

    /* -------------------------------------------------------------------
       DESTROY  (cleans up all features)
       ------------------------------------------------------------------- */
    HoloCard.prototype.destroy = function () {
        // Clear pending timers
        if (this._leaveTimer1) clearTimeout(this._leaveTimer1);
        if (this._leaveTimer2) clearTimeout(this._leaveTimer2);
        if (this._kbIdleTimer) clearTimeout(this._kbIdleTimer);

        // Showcase cleanup (Feature 1)
        this._stopShowcase();
        if (this._showcaseResumeTimeout) {
            clearTimeout(this._showcaseResumeTimeout);
            this._showcaseResumeTimeout = null;
        }

        // Sparkle cleanup (Feature 3)
        if (this._sparkle) {
            this._sparkle.destroy();
            this._sparkle = null;
        }

        // IntersectionObserver cleanup (Feature 2)
        if (viewportObserver) {
            viewportObserver.unobserve(this.el);
        }

        // Remove from resize list (Feature 4)
        var idx = allLiveCards.indexOf(this);
        if (idx !== -1) allLiveCards.splice(idx, 1);

        // Remove event listeners
        this.rotator.removeEventListener('pointerenter', this._boundPointerEnter);
        this.rotator.removeEventListener('pointermove', this._boundPointerMove);
        this.rotator.removeEventListener('pointerleave', this._boundPointerLeave);
        this.rotator.removeEventListener('touchmove', this._boundTouchMove);
        this.rotator.removeEventListener('touchend', this._boundTouchEnd);
        this.rotator.removeEventListener('dblclick', this._boundDblClick);
        this.el.removeEventListener('keydown', this._boundKeyDown);

        // Unregister orientation
        unregisterOrientation(this);

        // Remove classes
        this.el.classList.remove('phc-interactive', 'phc-interacting', 'phc-active', 'phc-flipped');

        // Remove accessibility attributes
        this.el.removeAttribute('tabindex');
        this.el.removeAttribute('role');
        this.el.removeAttribute('aria-roledescription');
        this.el.removeAttribute('aria-label');
        this.el.removeAttribute('aria-describedby');
        this.el.removeAttribute('aria-pressed');

        // Stop animation
        this.animating = false;
        this._wantsAnimation = false;
    };

    /* -------------------------------------------------------------------
       SHOWCASE MODE  (Feature 1)
       Automatic circular rotation demo that yields to user interaction.
       ------------------------------------------------------------------- */

    /**
     * Begin the showcase auto-rotation after a delay.
     * @param {number} delayMs  Milliseconds to wait before starting
     */
    HoloCard.prototype._beginShowcaseAfterDelay = function (delayMs) {
        var self = this;
        this._stopShowcase(); // clear any existing interval/timeout first

        this._showcaseTimeout = setTimeout(function () {
            self._showcaseTimeout = null;
            self._startShowcase();
        }, delayMs);
    };

    /** Kick off the showcase rotation interval. */
    HoloCard.prototype._startShowcase = function () {
        if (this._showcaseInterval) return; // already running
        if (!this._inViewport) return;      // don't start if off-screen

        var self = this;

        // Use slower spring settings for silky showcase motion
        this.springRotate.stiffness = 0.02;
        this.springRotate.damping = 0.5;
        this.springGlare.stiffness = 0.02;
        this.springGlare.damping = 0.5;
        this.springBackground.stiffness = 0.02;
        this.springBackground.damping = 0.5;

        this.active = true;
        this.el.classList.add('phc-active');

        this._showcaseInterval = setInterval(function () {
            self._showcaseAngle += 0.03;
            var r = self._showcaseAngle;

            self.springRotate.set({ x: Math.sin(r) * 20, y: Math.cos(r) * 20 });
            self.springGlare.set({ x: 55 + Math.sin(r) * 45, y: 55 + Math.cos(r) * 45, o: 0.8 });
            self.springBackground.set({ x: 30 + Math.sin(r) * 20, y: 30 + Math.cos(r) * 20 });

            self._startAnimation();
        }, 20);

        // Start sparkle during showcase if enabled
        if (self._sparkle) {
            self._sparkle.start();
        }

        dispatchPhcEvent(this.el, 'phc:showcase:start');

        this._startAnimation();
    };

    /** Stop the showcase interval and pending start timeout. */
    HoloCard.prototype._stopShowcase = function () {
        if (this._showcaseInterval) {
            clearInterval(this._showcaseInterval);
            this._showcaseInterval = null;
            dispatchPhcEvent(this.el, 'phc:showcase:stop');
        }
        if (this._showcaseTimeout) {
            clearTimeout(this._showcaseTimeout);
            this._showcaseTimeout = null;
        }
    };

    /* -------------------------------------------------------------------
       BACK-FACE FLIP
       ------------------------------------------------------------------- */

    /**
     * Toggle the card between front and back face.
     */
    HoloCard.prototype._toggleFlip = function () {
        this._flipped = !this._flipped;
        this.el.classList.toggle('phc-flipped', this._flipped);
        this.el.setAttribute('aria-pressed', this._flipped ? 'true' : 'false');
        dispatchPhcEvent(this.el, 'phc:flip', { flipped: this._flipped });
    };

    /** @private Handle double-click on rotator to flip. */
    HoloCard.prototype._onDblClick = function () {
        this._toggleFlip();
    };

    /* -------------------------------------------------------------------
       KEYBOARD ACCESSIBILITY
       ------------------------------------------------------------------- */

    /**
     * Handle keyboard interaction for arrow-key tilt, space/enter toggle,
     * and escape reset.
     * @param {KeyboardEvent} e
     */
    HoloCard.prototype._onKeyDown = function (e) {
        var key = e.key;
        var isArrow = (key === 'ArrowLeft' || key === 'ArrowRight' ||
                       key === 'ArrowUp' || key === 'ArrowDown');
        var isAction = (key === ' ' || key === 'Enter');
        var isEscape = (key === 'Escape');

        if (!isArrow && !isAction && !isEscape) return;

        e.preventDefault();

        var self = this;

        if (isArrow) {
            // Update keyboard-driven pointer position
            if (key === 'ArrowLeft')  this._kbX = clamp(this._kbX - 5, 0, 100);
            if (key === 'ArrowRight') this._kbX = clamp(this._kbX + 5, 0, 100);
            if (key === 'ArrowUp')    this._kbY = clamp(this._kbY - 5, 0, 100);
            if (key === 'ArrowDown')  this._kbY = clamp(this._kbY + 5, 0, 100);

            // Activate the card
            if (!this.active) {
                this.active = true;
                this.el.classList.add('phc-active');
            }
            this.el.classList.add('phc-interacting');

            // Compute the same pendingUpdate as _onPointerMove would
            var percent = { x: this._kbX, y: this._kbY };
            var center = { x: percent.x - 50, y: percent.y - 50 };

            var angle = Math.atan2(center.y, center.x) * (180 / Math.PI) + 180;
            this._lastAngle = angle;

            this.pendingUpdate = {
                background: {
                    x: adjust(percent.x, 0, 100, 37, 63),
                    y: adjust(percent.y, 0, 100, 33, 67)
                },
                rotate: {
                    x: round(-(center.x / 3.5)),
                    y: round(center.y / 3.5)
                },
                glare: {
                    x: round(percent.x),
                    y: round(percent.y),
                    o: 1
                },
                angle: angle
            };

            this._startAnimation();

            // Start sparkle while keyboard-interacting - skip when reduced motion
            if (this._sparkle && !this._sparkle.running && !prefersReducedMotion) {
                this._sparkle.start();
            }

            // Auto-deactivate after 2 seconds of no keypress
            if (this._kbIdleTimer) clearTimeout(this._kbIdleTimer);
            this._kbIdleTimer = setTimeout(function () {
                self._kbIdleTimer = null;
                self.el.classList.remove('phc-interacting');
                // Trigger the same leave logic: ease springs back to center
                self._onPointerLeave();
            }, 2000);

            return;
        }

        if (isAction) {
            // If card has a back face, flip it; otherwise toggle showcase
            if (this.el.getAttribute('data-phc-back')) {
                this._toggleFlip();
            } else if (this._isShowcase) {
                if (this._showcaseInterval) {
                    this._stopShowcase();
                } else {
                    this._beginShowcaseAfterDelay(0);
                }
            }
            return;
        }

        if (isEscape) {
            // Reset keyboard position to center
            this._kbX = 50;
            this._kbY = 50;

            // Stop showcase if running
            if (this._showcaseInterval) {
                this._stopShowcase();
            }

            // Ease springs back to neutral
            this.springRotate.set({ x: 0, y: 0 });
            this.springGlare.set({ x: 50, y: 50, o: 0 });
            this.springBackground.set({ x: 50, y: 50 });
            this.springScale.set(1);

            this.el.classList.remove('phc-interacting');
            this.active = false;
            this.el.classList.remove('phc-active');

            // Stop sparkle
            if (this._sparkle) {
                this._sparkle.stop();
            }

            this._startAnimation();
            return;
        }
    };

    /* -------------------------------------------------------------------
       EVENT HANDLERS
       ------------------------------------------------------------------- */

    HoloCard.prototype._onPointerEnter = function () {
        // Cancel any pending leave timers
        if (this._leaveTimer1) { clearTimeout(this._leaveTimer1); this._leaveTimer1 = null; }
        if (this._leaveTimer2) { clearTimeout(this._leaveTimer2); this._leaveTimer2 = null; }

        // Showcase: stop auto-rotation so user takes over
        if (this._isShowcase) {
            this._stopShowcase();
            if (this._showcaseResumeTimeout) {
                clearTimeout(this._showcaseResumeTimeout);
                this._showcaseResumeTimeout = null;
            }
        }

        this.interacting = true;
        this.active = true;

        // Restore original spring settings for snappy interaction feel
        this.springRotate.stiffness = this._origStiff;
        this.springRotate.damping = this._origDamp;
        this.springGlare.stiffness = this._origStiff;
        this.springGlare.damping = this._origDamp;
        this.springBackground.stiffness = this._origStiff;
        this.springBackground.damping = this._origDamp;

        // Cache rect for this interaction session
        this.cachedRect = this.rotator.getBoundingClientRect();

        this.el.classList.add('phc-interacting', 'phc-active');

        // Start sparkle (Feature 3) - skip when reduced motion
        if (this._sparkle && !prefersReducedMotion) {
            this._sparkle.start();
        }

        dispatchPhcEvent(this.el, 'phc:enter');

        this._startAnimation();
    };

    HoloCard.prototype._onPointerMove = function (e) {
        if (!this.interacting) {
            this._onPointerEnter();
        }

        var rect = this.cachedRect || this.rotator.getBoundingClientRect();
        var absolute = {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };
        var percent = {
            x: clamp(round((100 / rect.width) * absolute.x)),
            y: clamp(round((100 / rect.height) * absolute.y))
        };
        var center = {
            x: percent.x - 50,
            y: percent.y - 50
        };

        // Compute pointer angle for holographic sheen direction
        var angle = Math.atan2(center.y, center.x) * (180 / Math.PI) + 180;
        this._lastAngle = angle;

        this.pendingUpdate = {
            background: {
                x: adjust(percent.x, 0, 100, 37, 63),
                y: adjust(percent.y, 0, 100, 33, 67)
            },
            rotate: {
                x: round(-(center.x / 3.5)),
                y: round(center.y / 3.5)
            },
            glare: {
                x: round(percent.x),
                y: round(percent.y),
                o: 1
            },
            angle: angle
        };
    };

    HoloCard.prototype._onTouchMove = function (e) {
        if (e.touches && e.touches[0]) {
            this._onPointerMove({
                clientX: e.touches[0].clientX,
                clientY: e.touches[0].clientY
            });
        }
    };

    HoloCard.prototype._onPointerLeave = function () {
        var self = this;
        this.interacting = false;
        this.cachedRect = null;

        // Cancel any existing timers first
        if (this._leaveTimer1) clearTimeout(this._leaveTimer1);
        if (this._leaveTimer2) clearTimeout(this._leaveTimer2);

        // Stop sparkle on leave (Feature 3)
        if (this._sparkle) {
            this._sparkle.stop();
        }

        dispatchPhcEvent(this.el, 'phc:leave');

        this._leaveTimer1 = setTimeout(function () {
            self._leaveTimer1 = null;

            // Only proceed if still not interacting
            if (self.interacting) return;

            var snapStiff = 0.01;
            var snapDamp = 0.06;

            self.springRotate.stiffness = snapStiff;
            self.springRotate.damping = snapDamp;
            self.springRotate.set({ x: 0, y: 0 });

            self.springGlare.stiffness = snapStiff;
            self.springGlare.damping = snapDamp;
            self.springGlare.set({ x: 50, y: 50, o: 0 });

            self.springBackground.stiffness = snapStiff;
            self.springBackground.damping = snapDamp;
            self.springBackground.set({ x: 50, y: 50 });

            self.springScale.set(1);

            self.el.classList.remove('phc-interacting');
            self._startAnimation();
        }, 300);

        this._leaveTimer2 = setTimeout(function () {
            self._leaveTimer2 = null;
            if (!self.interacting) {
                // If showcase, schedule a resume instead of going fully inactive
                if (self._isShowcase) {
                    if (self._showcaseResumeTimeout) clearTimeout(self._showcaseResumeTimeout);
                    self._showcaseResumeTimeout = setTimeout(function () {
                        self._showcaseResumeTimeout = null;
                        if (!self.interacting) {
                            self._beginShowcaseAfterDelay(0);
                        }
                    }, 3000);
                } else {
                    self.active = false;
                    self.el.classList.remove('phc-active');
                }
            }
        }, 800);
    };

    HoloCard.prototype._onOrientation = function (e) {
        if (!this.active) return;

        if (!this._baseOrientation) {
            this._baseOrientation = { gamma: e.gamma || 0, beta: e.beta || 0 };
        }

        var gamma = (e.gamma || 0) - this._baseOrientation.gamma;
        var beta = (e.beta || 0) - this._baseOrientation.beta;
        var limit = { x: 16, y: 18 };
        var degrees = {
            x: clamp(gamma, -limit.x, limit.x),
            y: clamp(beta, -limit.y, limit.y)
        };

        this.pendingUpdate = {
            background: {
                x: adjust(degrees.x, -limit.x, limit.x, 37, 63),
                y: adjust(degrees.y, -limit.y, limit.y, 33, 67)
            },
            rotate: {
                x: round(degrees.x * -1),
                y: round(degrees.y)
            },
            glare: {
                x: adjust(degrees.x, -limit.x, limit.x, 0, 100),
                y: adjust(degrees.y, -limit.y, limit.y, 0, 100),
                o: 1
            }
        };

        this._startAnimation();
    };

    /* -------------------------------------------------------------------
       ANIMATION LOOP
       ------------------------------------------------------------------- */

    HoloCard.prototype._startAnimation = function () {
        // If not in viewport, remember the intent but do not spin up a rAF loop
        if (!this._inViewport) {
            this._wantsAnimation = true;
            return;
        }

        if (this.animating) return;
        this.animating = true;
        this._wantsAnimation = false;
        this._animate();
    };

    HoloCard.prototype._animate = function () {
        var self = this;

        // Bail out immediately if paused by IntersectionObserver
        if (!this._inViewport) {
            this.animating = false;
            this._wantsAnimation = true;
            return;
        }

        // Apply pending spring targets and capture angle
        var pendingAngle = null;
        if (this.pendingUpdate) {
            this.springBackground.set(this.pendingUpdate.background);
            this.springRotate.set(this.pendingUpdate.rotate);
            this.springGlare.set(this.pendingUpdate.glare);
            if (this.pendingUpdate.angle !== undefined) {
                pendingAngle = this.pendingUpdate.angle;
            }
            this.pendingUpdate = null;
        }

        // Tick all springs
        var s1 = this.springRotate.tick();
        var s2 = this.springGlare.tick();
        var s3 = this.springBackground.tick();
        var s4 = this.springScale.tick();

        // Apply CSS custom properties
        var r = this.springRotate.value();
        var g = this.springGlare.value();
        var b = this.springBackground.value();
        var s = this.springScale.value();

        var fromCenter = clamp(
            Math.sqrt(
                (g.y - 50) * (g.y - 50) + (g.x - 50) * (g.x - 50)
            ) / 50,
            0, 1
        );

        var style = this.el.style;
        style.setProperty('--pointer-x', g.x + '%');
        style.setProperty('--pointer-y', g.y + '%');
        style.setProperty('--pointer-from-center', round(fromCenter, 3));
        style.setProperty('--pointer-from-top', round(g.y / 100, 3));
        style.setProperty('--pointer-from-left', round(g.x / 100, 3));
        style.setProperty('--card-opacity', round(g.o, 3));
        style.setProperty('--rotate-x', round(r.x, 2) + 'deg');
        style.setProperty('--rotate-y', round(r.y, 2) + 'deg');
        style.setProperty('--background-x', round(b.x, 2) + '%');
        style.setProperty('--background-y', round(b.y, 2) + '%');
        style.setProperty('--card-scale', round(s, 4));

        // Pointer angle (from mouse/keyboard input)
        var angle = pendingAngle !== null ? pendingAngle : this._lastAngle;
        this._lastAngle = angle || this._lastAngle || 0;
        style.setProperty('--pointer-angle', round(this._lastAngle, 1) + 'deg');

        // Parallax depth: separate shine and glare layers at different depths
        var shineX = round(b.x, 2);
        var shineY = round(b.y, 2);
        var glareX = round(50 + (b.x - 50) * 0.6, 2);
        var glareY = round(50 + (b.y - 50) * 0.6, 2);
        style.setProperty('--shine-x', shineX + '%');
        style.setProperty('--shine-y', shineY + '%');
        style.setProperty('--glare-x', glareX + '%');
        style.setProperty('--glare-y', glareY + '%');

        // Continue or stop
        var allSettled = s1 && s2 && s3 && s4 && !this.pendingUpdate;

        if (allSettled && !this.interacting) {
            this.animating = false;
            this._wantsAnimation = false;
            return;
        }

        requestAnimationFrame(function () {
            if (self.animating) {
                self._animate();
            }
        });
    };

    /* ===================================================================
       AUTO-INIT & MUTATION OBSERVER
       =================================================================== */
    var cardInstances = new WeakMap();
    var className = settings.autoInitClass || 'phc-card';

    /**
     * Build card DOM structure if needed and instantiate a HoloCard.
     * @param {HTMLElement} el
     * @returns {HoloCard|null}
     */
    function initCard(el) {
        if (cardInstances.has(el)) return cardInstances.get(el);

        // Build card structure if needed (for CSS-class-only usage)
        var hasStructure = el.querySelector('.phc-card__rotator');
        if (!hasStructure) {
            var img = el.querySelector('img');
            if (!img) return null;

            var translater = document.createElement('div');
            translater.className = 'phc-card__translater';
            var rotator = document.createElement('div');
            rotator.className = 'phc-card__rotator';

            img.classList.add('phc-card__front');
            rotator.appendChild(img);

            var shine = document.createElement('div');
            shine.className = 'phc-card__shine';
            rotator.appendChild(shine);

            var glare = document.createElement('div');
            glare.className = 'phc-card__glare';
            rotator.appendChild(glare);

            translater.appendChild(rotator);

            while (el.firstChild) {
                el.removeChild(el.firstChild);
            }
            el.appendChild(translater);

            // Back-face support: add back image if data-phc-back is set
            var backSrc = el.getAttribute('data-phc-back');
            if (backSrc) {
                var backDiv = document.createElement('div');
                backDiv.className = 'phc-card__back';
                var backImg = document.createElement('img');
                backImg.src = backSrc;
                backImg.alt = el.getAttribute('data-phc-back-alt') || 'Card back';
                backImg.loading = 'lazy';
                backDiv.appendChild(backImg);
                rotator.appendChild(backDiv);
            }
        }

        // Determine effect type (Feature 5 - data-phc-effect already existed)
        var effect = el.getAttribute('data-phc-effect') || settings.effectType || 'holo';
        if (!el.classList.contains('phc-effect-' + effect)) {
            el.classList.add('phc-effect-' + effect);
        }

        var card = new HoloCard(el);
        cardInstances.set(el, card);
        return card;
    }

    /**
     * Tear down a card instance and remove it from the registry.
     * @param {HTMLElement} el
     */
    function destroyCard(el) {
        var card = cardInstances.get(el);
        if (card) {
            card.destroy();
            cardInstances.delete(el);
        }
    }

    /** Initialize all cards matching the configured class name. */
    function initAll() {
        var cards = document.querySelectorAll('.' + className);
        for (var i = 0; i < cards.length; i++) {
            initCard(cards[i]);
        }
        initGallerySwipe();
    }

    /* -------------------------------------------------------------------
       GALLERY SWIPE - Horizontal touch swipe on .phc-gallery containers
       ------------------------------------------------------------------- */
    function initGallerySwipe() {
        var galleries = document.querySelectorAll('.phc-gallery');
        for (var i = 0; i < galleries.length; i++) {
            var g = galleries[i];

            // ARIA: mark gallery as a group
            if (!g.getAttribute('role')) {
                g.setAttribute('role', 'group');
                g.setAttribute('aria-label', 'Holographic card gallery');
            }

            if (g._phcSwipe) continue; // already initialized
            _attachGallerySwipe(g);
        }
    }

    function _attachGallerySwipe(gallery) {
        var startX = 0, startY = 0, startTime = 0, scrollLeft0 = 0;

        gallery.style.overflowX = 'auto';
        gallery.style.WebkitOverflowScrolling = 'touch';
        gallery.style.scrollSnapType = 'x mandatory';
        gallery.style.scrollBehavior = 'smooth';

        // Apply scroll-snap to children
        var cards = gallery.querySelectorAll('.' + className);
        for (var i = 0; i < cards.length; i++) {
            cards[i].style.scrollSnapAlign = 'center';
        }

        gallery.addEventListener('touchstart', function (e) {
            if (e.touches.length !== 1) return;
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            startTime = Date.now();
            scrollLeft0 = gallery.scrollLeft;
            gallery.style.scrollBehavior = 'auto'; // disable smooth during drag
        }, { passive: true });

        gallery.addEventListener('touchend', function () {
            gallery.style.scrollBehavior = 'smooth'; // re-enable after drag
        }, { passive: true });

        gallery._phcSwipe = true;
    }

    // Deferred initialization using requestIdleCallback when available
    function deferredInitAll() {
        if (typeof requestIdleCallback !== 'undefined') {
            requestIdleCallback(function () { initAll(); }, { timeout: 2000 });
        } else {
            setTimeout(initAll, 1);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', deferredInitAll);
    } else {
        deferredInitAll();
    }

    // Watch for dynamically added/removed cards
    if (typeof MutationObserver !== 'undefined') {
        var selector = '.' + className;
        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var mut = mutations[i];

                // Handle added nodes
                var added = mut.addedNodes;
                for (var j = 0; j < added.length; j++) {
                    var node = added[j];
                    if (node.nodeType !== 1) continue;
                    if (node.classList && node.classList.contains(className)) {
                        initCard(node);
                    }
                    if (node.querySelectorAll) {
                        var nested = node.querySelectorAll(selector);
                        for (var k = 0; k < nested.length; k++) {
                            initCard(nested[k]);
                        }
                    }
                }

                // Handle removed nodes (cleanup)
                var removed = mut.removedNodes;
                for (var j2 = 0; j2 < removed.length; j2++) {
                    var rnode = removed[j2];
                    if (rnode.nodeType !== 1) continue;
                    if (rnode.classList && rnode.classList.contains(className)) {
                        destroyCard(rnode);
                    }
                    if (rnode.querySelectorAll) {
                        var rnested = rnode.querySelectorAll(selector);
                        for (var k2 = 0; k2 < rnested.length; k2++) {
                            destroyCard(rnested[k2]);
                        }
                    }
                }
            }
        });

        // Wait for body to be available
        function startObserver() {
            if (document.body) {
                observer.observe(document.body, { childList: true, subtree: true });
            } else {
                document.addEventListener('DOMContentLoaded', function () {
                    observer.observe(document.body, { childList: true, subtree: true });
                });
            }
        }
        startObserver();
    }

    /* ===================================================================
       LIGHTBOX MODULE
       Fullscreen lightbox with zoom, gallery navigation, ESC close.
       =================================================================== */

    var lightbox = {
        overlay: null,
        contentWrap: null,
        currentCards: [],
        currentIndex: 0,
        isOpen: false,
        isZoomed: false,

        /** Build the lightbox DOM (once). */
        _build: function () {
            if (this.overlay) return;

            var self = this;

            this.overlay = document.createElement('div');
            this.overlay.className = 'phc-lightbox-overlay';
            this.overlay.setAttribute('role', 'dialog');
            this.overlay.setAttribute('aria-modal', 'true');
            this.overlay.setAttribute('aria-label', 'Card lightbox');

            this.contentWrap = document.createElement('div');
            this.contentWrap.className = 'phc-lightbox-content';

            this.cardSlot = document.createElement('div');
            this.cardSlot.className = 'phc-lightbox-card-slot';
            this.contentWrap.appendChild(this.cardSlot);

            // Close button
            var closeBtn = document.createElement('button');
            closeBtn.className = 'phc-lightbox-close';
            closeBtn.innerHTML = '&times;';
            closeBtn.setAttribute('aria-label', 'Close lightbox');
            closeBtn.addEventListener('click', function () { self.close(); });
            this.contentWrap.appendChild(closeBtn);

            // Nav buttons
            this.prevBtn = document.createElement('button');
            this.prevBtn.className = 'phc-lightbox-nav phc-lightbox-prev';
            this.prevBtn.innerHTML = '&#8249;';
            this.prevBtn.setAttribute('aria-label', 'Previous card');
            this.prevBtn.addEventListener('click', function (e) { e.stopPropagation(); self.prev(); });
            this.contentWrap.appendChild(this.prevBtn);

            this.nextBtn = document.createElement('button');
            this.nextBtn.className = 'phc-lightbox-nav phc-lightbox-next';
            this.nextBtn.innerHTML = '&#8250;';
            this.nextBtn.setAttribute('aria-label', 'Next card');
            this.nextBtn.addEventListener('click', function (e) { e.stopPropagation(); self.next(); });
            this.contentWrap.appendChild(this.nextBtn);

            // Counter (live region for screen readers)
            this.counter = document.createElement('div');
            this.counter.className = 'phc-lightbox-counter';
            this.counter.setAttribute('aria-live', 'polite');
            this.counter.setAttribute('aria-atomic', 'true');
            this.contentWrap.appendChild(this.counter);

            // Focus trap: cycle Tab through close, prev, next buttons
            this._focusableEls = [closeBtn, this.prevBtn, this.nextBtn];
            this.overlay.addEventListener('keydown', function (e) {
                if (e.key !== 'Tab' || !self.isOpen) return;
                var focusable = self._focusableEls.filter(function(el) { return el.style.display !== 'none'; });
                if (focusable.length === 0) return;
                var first = focusable[0];
                var last = focusable[focusable.length - 1];
                if (e.shiftKey) {
                    if (document.activeElement === first) { e.preventDefault(); last.focus(); }
                } else {
                    if (document.activeElement === last) { e.preventDefault(); first.focus(); }
                }
            });

            this.overlay.appendChild(this.contentWrap);
            document.body.appendChild(this.overlay);

            // Click on overlay backdrop to close
            this.overlay.addEventListener('click', function (e) {
                if (e.target === self.overlay) self.close();
            });

            // Keyboard navigation
            this._boundKeyHandler = function (e) { self._onKey(e); };

            // Touch swipe navigation
            this._touchStartX = 0;
            this._touchStartY = 0;
            this._touchStartTime = 0;
            this._pinchStartDist = 0;
            this._lastTapTime = 0;

            this.overlay.addEventListener('touchstart', function (e) {
                if (!self.isOpen) return;

                // Pinch-to-zoom: track initial distance between 2 fingers
                if (e.touches.length === 2) {
                    var dx = e.touches[0].clientX - e.touches[1].clientX;
                    var dy = e.touches[0].clientY - e.touches[1].clientY;
                    self._pinchStartDist = Math.sqrt(dx * dx + dy * dy);
                    return;
                }

                if (e.touches.length !== 1) return;
                self._touchStartX = e.touches[0].clientX;
                self._touchStartY = e.touches[0].clientY;
                self._touchStartTime = Date.now();
            }, { passive: true });

            this.overlay.addEventListener('touchend', function (e) {
                if (!self.isOpen) return;

                // Reset pinch state when fewer than 2 fingers remain
                if (e.touches.length < 2) {
                    var wasPinching = self._pinchStartDist > 0;
                    self._pinchStartDist = 0;
                    if (wasPinching) return; // skip swipe if was mid-pinch
                }

                var touch = e.changedTouches[0];
                if (!touch) return;

                var dx = touch.clientX - self._touchStartX;
                var dy = touch.clientY - self._touchStartY;
                var dt = Date.now() - self._touchStartTime;

                // Double-tap to toggle zoom
                if (dt < 300 && Math.abs(dx) < 10 && Math.abs(dy) < 10) {
                    var now = Date.now();
                    if (now - self._lastTapTime < 350) {
                        self.isZoomed = !self.isZoomed;
                        if (self.isZoomed) {
                            self.contentWrap.classList.add('phc-lightbox-zoomed');
                        } else {
                            self.contentWrap.classList.remove('phc-lightbox-zoomed');
                        }
                        self._lastTapTime = 0;
                        return;
                    }
                    self._lastTapTime = now;
                }

                // Don't swipe while zoomed
                if (self.isZoomed) return;

                // Minimum 50px and max 500ms for a swipe
                if (dt > 500) return;
                var absDx = Math.abs(dx);
                var absDy = Math.abs(dy);

                // Horizontal swipe (prev/next)
                if (absDx > 50 && absDx > absDy * 1.5) {
                    if (dx < 0) { self.next(); } else { self.prev(); }
                    e.preventDefault();
                    return;
                }

                // Vertical swipe down (close)
                if (absDy > 80 && dy > 0 && absDy > absDx * 1.5) {
                    self.close();
                    e.preventDefault();
                }
            });

            // Pinch-to-zoom gesture
            this.overlay.addEventListener('touchmove', function (e) {
                if (!self.isOpen || e.touches.length !== 2 || self._pinchStartDist <= 0) return;

                var dx = e.touches[0].clientX - e.touches[1].clientX;
                var dy = e.touches[0].clientY - e.touches[1].clientY;
                var dist = Math.sqrt(dx * dx + dy * dy);
                var ratio = dist / self._pinchStartDist;

                // Pinch out to zoom in, pinch in to zoom out
                if (ratio > 1.3 && !self.isZoomed) {
                    self.isZoomed = true;
                    self.contentWrap.classList.add('phc-lightbox-zoomed');
                    self._pinchStartDist = 0;
                    e.preventDefault();
                } else if (ratio < 0.7 && self.isZoomed) {
                    self.isZoomed = false;
                    self.contentWrap.classList.remove('phc-lightbox-zoomed');
                    self._pinchStartDist = 0;
                    e.preventDefault();
                }
            }, { passive: false });
        },

        /**
         * Open lightbox for a card.
         * @param {HTMLElement} cardEl  - The .phc-card element clicked.
         */
        open: function (cardEl) {
            this._build();
            var self = this;

            // Detect gallery context: sibling cards inside .phc-gallery
            var gallery = cardEl.closest('.phc-gallery');
            if (gallery) {
                this.currentCards = Array.prototype.slice.call(gallery.querySelectorAll('.phc-card'));
                this.currentIndex = this.currentCards.indexOf(cardEl);
                if (this.currentIndex < 0) this.currentIndex = 0;
            } else {
                this.currentCards = [cardEl];
                this.currentIndex = 0;
            }

            this._showCard(this.currentIndex);
            this._updateNav();

            // Save trigger element for focus restoration
            this._triggerElement = document.activeElement;

            // Show overlay with transition
            this.overlay.style.display = 'flex';
            requestAnimationFrame(function () {
                self.overlay.classList.add('phc-lightbox-active');
                // Focus close button for keyboard users
                var closeEl = self.contentWrap.querySelector('.phc-lightbox-close');
                if (closeEl) closeEl.focus();
            });

            this.isOpen = true;
            document.body.style.overflow = 'hidden';
            document.addEventListener('keydown', this._boundKeyHandler);
        },

        /** Close the lightbox. */
        close: function () {
            if (!this.isOpen) return;
            var self = this;
            this.overlay.classList.remove('phc-lightbox-active');
            this.isZoomed = false;
            this._lastTapTime = 0;
            this._pinchStartDist = 0;
            this.contentWrap.classList.remove('phc-lightbox-zoomed');
            setTimeout(function () {
                self.overlay.style.display = 'none';
                // Destroy lightbox card clone
                while (self.cardSlot.firstChild) self.cardSlot.removeChild(self.cardSlot.firstChild);
            }, 300);
            this.isOpen = false;
            document.body.style.overflow = '';
            document.removeEventListener('keydown', this._boundKeyHandler);

            // Restore focus to the element that opened the lightbox
            if (this._triggerElement && typeof this._triggerElement.focus === 'function') {
                var trigger = this._triggerElement;
                setTimeout(function() { trigger.focus(); }, 310);
                this._triggerElement = null;
            }
        },

        /** Navigate to previous card. */
        prev: function () {
            if (this.currentCards.length <= 1) return;
            this.currentIndex = (this.currentIndex - 1 + this.currentCards.length) % this.currentCards.length;
            this._showCard(this.currentIndex);
            this._updateNav();
        },

        /** Navigate to next card. */
        next: function () {
            if (this.currentCards.length <= 1) return;
            this.currentIndex = (this.currentIndex + 1) % this.currentCards.length;
            this._showCard(this.currentIndex);
            this._updateNav();
        },

        /** @private Clone the card into the lightbox slot. */
        _showCard: function (idx) {
            // Clear previous
            while (this.cardSlot.firstChild) this.cardSlot.removeChild(this.cardSlot.firstChild);

            var sourceCard = this.currentCards[idx];
            if (!sourceCard) return;

            // Clone the card element
            var clone = sourceCard.cloneNode(true);
            clone.classList.remove('phc-interactive');
            clone.removeAttribute('tabindex');

            this.cardSlot.appendChild(clone);

            // Initialize the clone as a holo card
            initCard(clone);
        },

        /** @private Show/hide nav buttons and counter. */
        _updateNav: function () {
            var multi = this.currentCards.length > 1;
            this.prevBtn.style.display = multi ? '' : 'none';
            this.nextBtn.style.display = multi ? '' : 'none';
            this.counter.style.display = multi ? '' : 'none';
            if (multi) {
                this.counter.textContent = (this.currentIndex + 1) + ' / ' + this.currentCards.length;
            }
        },

        /** @private Handle keyboard events. */
        _onKey: function (e) {
            if (e.key === 'Escape') { this.close(); e.preventDefault(); }
            if (e.key === 'ArrowLeft') { this.prev(); e.preventDefault(); }
            if (e.key === 'ArrowRight') { this.next(); e.preventDefault(); }
        }
    };

    // Attach click handlers on all phc-card elements
    document.addEventListener('click', function (e) {
        var card = e.target.closest('.' + className);
        if (!card) return;

        // Don't trigger on double-click flip
        if (e.detail > 1) return;

        // Don't trigger in admin context
        if (document.body.classList.contains('wp-admin')) return;

        // Don't trigger on WooCommerce product images (they have their own zoom)
        if (card.classList.contains('phc-woo-gallery') || card.classList.contains('phc-woo-archive')) return;

        // Click-through URL: navigate instead of lightbox
        var url = card.getAttribute('data-phc-url');
        if (url) {
            var target = card.getAttribute('data-phc-target') || '_self';
            if (target === '_blank') {
                window.open(url, '_blank', 'noopener,noreferrer');
            } else {
                window.location.href = url;
            }
            return;
        }

        // Otherwise open lightbox
        lightbox.open(card);
    });

    /* ===================================================================
       PUBLIC API
       =================================================================== */
    /** Destroy then re-create a card (useful after class/attribute changes). */
    function reinitCard(el) {
        destroyCard(el);
        return initCard(el);
    }

    window.PokeHoloCards = {
        init: initCard,
        destroy: destroyCard,
        reinit: reinitCard,
        initAll: initAll,
        lightbox: lightbox
    };

    // Alias for admin preview compatibility
    window.phcReinit = reinitCard;

    /* ===================================================================
       INTERACTION ANALYTICS BEACON
       =================================================================== */
    (function() {
        if (!settings.ajaxUrl) return;
        var beaconQueue = [];
        var beaconTimer = null;

        function flushBeacons() {
            if (!beaconQueue.length) return;
            var batch = beaconQueue.splice(0, 10);
            batch.forEach(function(b) {
                var fd = new FormData();
                fd.append('action', 'phc_analytics_beacon');
                fd.append('effect', b.effect);
                fd.append('hover_ms', b.hover_ms);
                if (b.clicked) fd.append('clicked', '1');
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(settings.ajaxUrl, fd);
                } else {
                    fetch(settings.ajaxUrl, { method: 'POST', body: fd, keepalive: true });
                }
            });
        }

        function scheduleFlush() {
            if (beaconTimer) return;
            beaconTimer = setTimeout(function() {
                beaconTimer = null;
                flushBeacons();
            }, 5000);
        }

        // Track hovers on all phc-card elements
        document.addEventListener('pointerenter', function(e) {
            var card = e.target.closest('.phc-card');
            if (!card) return;
            card._phcHoverStart = Date.now();
        }, true);

        document.addEventListener('pointerleave', function(e) {
            var card = e.target.closest('.phc-card');
            if (!card || !card._phcHoverStart) return;
            var duration = Date.now() - card._phcHoverStart;
            card._phcHoverStart = null;
            if (duration < 200) return; // Ignore accidental hovers
            var effect = card.getAttribute('data-phc-effect') || settings.effectType || 'holo';
            beaconQueue.push({ effect: effect, hover_ms: duration, clicked: false });
            scheduleFlush();
        }, true);

        document.addEventListener('click', function(e) {
            var card = e.target.closest('.phc-card');
            if (!card) return;
            var effect = card.getAttribute('data-phc-effect') || settings.effectType || 'holo';
            beaconQueue.push({ effect: effect, hover_ms: 0, clicked: true });
            scheduleFlush();
        }, true);

        // Flush on page unload
        window.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') flushBeacons();
        });
    })();

})();
