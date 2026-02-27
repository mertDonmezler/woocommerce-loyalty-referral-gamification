/**
 * PHC AR Viewer
 *
 * Lazy-loads model-viewer and Three.js from CDN to create
 * an AR experience from the product's holo card texture.
 * Falls back to a 3D viewer when AR is not supported.
 *
 * @since 3.1.0
 */
(function () {
    'use strict';

    /* ── CDN URLs ────────────────────────────── */
    var MODEL_VIEWER_URL = 'https://cdn.jsdelivr.net/npm/@google/model-viewer@3/dist/model-viewer.min.js';

    /* ── State ───────────────────────────────── */
    var arSupported    = false;
    var iosDevice      = false;
    var depsLoaded     = false;
    var loadingDeps    = false;
    var AR_ICON = '<svg class="phc-ar-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg> ';

    /* ── Init ────────────────────────────────── */
    function init() {
        detectAR().then(function () {
            showButtons();
        });
    }

    /* ── AR Detection ────────────────────────── */
    function detectAR() {
        // iOS check
        var ua = navigator.userAgent;
        iosDevice = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

        if (iosDevice) {
            // iOS 12+ supports AR Quick Look
            var iosVersionMatch = ua.match(/OS (\d+)/);
            if (iosVersionMatch && parseInt(iosVersionMatch[1]) >= 12) {
                arSupported = true;
            }
            return Promise.resolve();
        }

        // WebXR check (Android Chrome)
        if (navigator.xr && navigator.xr.isSessionSupported) {
            return navigator.xr.isSessionSupported('immersive-ar').then(function (supported) {
                arSupported = supported;
            }).catch(function () {
                arSupported = false;
            });
        }

        return Promise.resolve();
    }

    /* ── Show Buttons ────────────────────────── */
    function showButtons() {
        var buttons = document.querySelectorAll('.phc-ar-btn');
        for (var i = 0; i < buttons.length; i++) {
            // Show button - AR or 3D fallback
            buttons[i].style.display = '';

            if (!arSupported) {
                buttons[i].innerHTML = AR_ICON + '3D ile G\u00f6r';
            }

            buttons[i].addEventListener('click', onButtonClick);
        }
    }

    /* ── Button Click Handler ────────────────── */
    function onButtonClick(e) {
        var btn = e.currentTarget;
        if (btn.classList.contains('phc-ar-loading')) return;

        btn.classList.add('phc-ar-loading');
        var originalHTML = btn.innerHTML;
        btn.innerHTML = AR_ICON + 'Y\u00fckleniyor...';

        var imageUrl = btn.dataset.imageUrl;
        var posterBlobUrl = null;

        loadDependencies().then(function () {
            return createModel(imageUrl);
        }).then(function (modelBlobUrl) {
            posterBlobUrl = modelBlobUrl;
            return launchViewer(btn, modelBlobUrl, imageUrl);
        }).catch(function (err) {
            console.error('PHC AR Error:', err);
            if (posterBlobUrl && posterBlobUrl.indexOf('blob:') === 0) {
                URL.revokeObjectURL(posterBlobUrl);
            }
            btn.innerHTML = AR_ICON + 'Hata olustu';
            setTimeout(function () {
                btn.innerHTML = originalHTML;
                btn.classList.remove('phc-ar-loading');
            }, 2000);
        });
    }

    /* ── Load Dependencies ───────────────────── */
    function loadDependencies() {
        if (depsLoaded) return Promise.resolve();
        if (loadingDeps) {
            return new Promise(function (resolve, reject) {
                var check = setInterval(function () {
                    if (depsLoaded) {
                        clearInterval(check);
                        resolve();
                    } else if (!loadingDeps) {
                        clearInterval(check);
                        reject(new Error('Dependency load failed'));
                    }
                }, 100);
            });
        }

        loadingDeps = true;

        return loadScript(MODEL_VIEWER_URL).then(function () {
            depsLoaded = true;
            loadingDeps = false;
        }).catch(function (err) {
            loadingDeps = false;
            throw err;
        });
    }

    function loadScript(url) {
        return new Promise(function (resolve, reject) {
            // Check if already loaded
            var existing = document.querySelector('script[src="' + url + '"]');
            if (existing) {
                resolve();
                return;
            }

            var script = document.createElement('script');
            script.type = url.includes('.module.') ? 'module' : 'text/javascript';
            script.src = url;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    /* ── Create 3D Model ─────────────────────── */
    function createModel(imageUrl) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () {
                // Create canvas with card texture
                var canvas = document.createElement('canvas');
                var ratio  = img.height / img.width;
                canvas.width  = 512;
                canvas.height = Math.round(512 * ratio);

                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                // Convert to blob URL for model-viewer poster
                canvas.toBlob(function (blob) {
                    if (blob) {
                        resolve(URL.createObjectURL(blob));
                    } else {
                        resolve(imageUrl);
                    }
                }, 'image/png');
            };
            img.onerror = function () {
                resolve(imageUrl);
            };
            img.src = imageUrl;
        });
    }

    /* ── Launch Viewer ───────────────────────── */
    function launchViewer(btn, posterUrl, imageUrl) {
        // Find or create container
        var container = btn.parentNode.querySelector('.phc-ar-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'phc-ar-container';
            btn.parentNode.appendChild(container);
        }

        // Create model-viewer element
        // model-viewer supports AR directly with an image as poster
        var viewer = document.createElement('model-viewer');
        viewer.setAttribute('poster', posterUrl);
        viewer.setAttribute('alt', 'Holographic Card');
        viewer.setAttribute('camera-controls', '');
        viewer.setAttribute('touch-action', 'pan-y');
        viewer.setAttribute('auto-rotate', '');
        viewer.setAttribute('shadow-intensity', '1');
        viewer.style.width = '100%';
        viewer.style.height = '400px';
        viewer.style.backgroundColor = '#1a1a2e';
        viewer.style.borderRadius = '12px';

        if (arSupported) {
            viewer.setAttribute('ar', '');
            viewer.setAttribute('ar-modes', 'scene-viewer webxr quick-look');

            // For iOS Quick Look, use USDZ
            if (iosDevice) {
                viewer.setAttribute('ios-src', '');
            }
        }

        // Create a simple plane GLB inline using a minimal approach
        // Since we can't use Three.js easily without a module bundler,
        // we create a data URI GLB with embedded texture
        createMinimalGLB(imageUrl).then(function (glbUrl) {
            viewer.setAttribute('src', glbUrl);
            container.innerHTML = '';
            container.appendChild(viewer);

            // Close button
            var closeBtn = document.createElement('button');
            closeBtn.className = 'phc-ar-close-btn';
            closeBtn.textContent = '\u00D7';
            closeBtn.addEventListener('click', function () {
                // Revoke blob URLs to prevent memory leaks.
                var mv = container.querySelector('model-viewer');
                if (mv) {
                    var src = mv.getAttribute('src');
                    if (src && src.indexOf('blob:') === 0) URL.revokeObjectURL(src);
                    var poster = mv.getAttribute('poster');
                    if (poster && poster.indexOf('blob:') === 0) URL.revokeObjectURL(poster);
                }
                container.innerHTML = '';
                container.style.display = 'none';
                btn.classList.remove('phc-ar-loading');
                btn.innerHTML = AR_ICON + (arSupported ? 'AR ile G\u00f6r' : '3D ile G\u00f6r');
            });
            container.appendChild(closeBtn);
            container.style.display = 'block';

            btn.classList.remove('phc-ar-loading');
            btn.innerHTML = AR_ICON + (arSupported ? 'AR ile G\u00f6r' : '3D ile G\u00f6r');
        });
    }

    /* ── Create Minimal GLB ──────────────────── */
    function createMinimalGLB(imageUrl) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () {
                var canvas = document.createElement('canvas');
                var ratio  = img.height / img.width;
                canvas.width  = 512;
                canvas.height = Math.round(512 * ratio);

                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                canvas.toBlob(function (blob) {
                    if (!blob) {
                        reject(new Error('Canvas toBlob failed'));
                        return;
                    }

                    var reader = new FileReader();
                    reader.onloadend = function () {
                        var base64 = reader.result.split(',')[1];
                        var binaryString = atob(base64);
                        var imageBytes = new Uint8Array(binaryString.length);
                        for (var i = 0; i < binaryString.length; i++) {
                            imageBytes[i] = binaryString.charCodeAt(i);
                        }

                        var glbBytes = buildGLB(imageBytes, canvas.width, canvas.height);
                        var glbBlob = new Blob([glbBytes], { type: 'model/gltf-binary' });
                        resolve(URL.createObjectURL(glbBlob));
                    };
                    reader.readAsDataURL(blob);
                }, 'image/png');
            };
            img.onerror = function () { reject(new Error('Image load failed: ' + imageUrl)); };
            img.src = imageUrl;
        });
    }

    /**
     * Build a minimal GLB (glTF Binary) with a single textured plane.
     * This avoids the need for Three.js completely.
     */
    function buildGLB(imageBytes, imgWidth, imgHeight) {
        var ratio = imgHeight / imgWidth;
        var halfW = 0.5;
        var halfH = halfW * ratio;

        // Vertices: position (3 floats) + texcoord (2 floats) per vertex
        var positions = new Float32Array([
            -halfW, -halfH, 0,   halfW, -halfH, 0,   halfW, halfH, 0,   -halfW, halfH, 0
        ]);
        var texcoords = new Float32Array([
            0, 1,   1, 1,   1, 0,   0, 0
        ]);
        var indices = new Uint16Array([0, 1, 2, 0, 2, 3]);
        var normals = new Float32Array([
            0, 0, 1,   0, 0, 1,   0, 0, 1,   0, 0, 1
        ]);

        // Build binary buffer
        var posBytes    = new Uint8Array(positions.buffer);
        var texBytes    = new Uint8Array(texcoords.buffer);
        var idxBytes    = new Uint8Array(indices.buffer);
        var normalBytes = new Uint8Array(normals.buffer);

        // Pad image to 4-byte boundary
        var imgPadLen = (4 - (imageBytes.length % 4)) % 4;
        var imgPadded = new Uint8Array(imageBytes.length + imgPadLen);
        imgPadded.set(imageBytes);

        // Buffer layout: indices | positions | normals | texcoords | image
        var binLen = idxBytes.length + posBytes.length + normalBytes.length + texBytes.length + imgPadded.length;
        // Pad geometry sections to 4-byte alignment
        var idxPad = (4 - (idxBytes.length % 4)) % 4;
        binLen += idxPad;

        var bin = new Uint8Array(binLen);
        var offset = 0;
        bin.set(idxBytes, offset); offset += idxBytes.length + idxPad;
        bin.set(posBytes, offset); offset += posBytes.length;
        bin.set(normalBytes, offset); offset += normalBytes.length;
        bin.set(texBytes, offset); offset += texBytes.length;
        bin.set(imgPadded, offset);

        // Offsets for buffer views
        var bvIdxOff  = 0;
        var bvIdxLen  = idxBytes.length;
        var bvPosOff  = idxBytes.length + idxPad;
        var bvPosLen  = posBytes.length;
        var bvNormOff = bvPosOff + bvPosLen;
        var bvNormLen = normalBytes.length;
        var bvTexOff  = bvNormOff + bvNormLen;
        var bvTexLen  = texBytes.length;
        var bvImgOff  = bvTexOff + bvTexLen;
        var bvImgLen  = imageBytes.length;

        // glTF JSON
        var gltf = {
            asset: { version: '2.0', generator: 'PHC-AR-Viewer' },
            scene: 0,
            scenes: [{ nodes: [0] }],
            nodes: [{ mesh: 0 }],
            meshes: [{
                primitives: [{
                    attributes: { POSITION: 1, NORMAL: 2, TEXCOORD_0: 3 },
                    indices: 0,
                    material: 0
                }]
            }],
            materials: [{
                pbrMetallicRoughness: {
                    baseColorTexture: { index: 0 },
                    metallicFactor: 0.1,
                    roughnessFactor: 0.6
                },
                doubleSided: true
            }],
            textures: [{ source: 0 }],
            images: [{ bufferView: 4, mimeType: 'image/png' }],
            accessors: [
                { bufferView: 0, componentType: 5123, count: 6, type: 'SCALAR', max: [3], min: [0] },
                { bufferView: 1, componentType: 5126, count: 4, type: 'VEC3', max: [halfW, halfH, 0], min: [-halfW, -halfH, 0] },
                { bufferView: 2, componentType: 5126, count: 4, type: 'VEC3', max: [0, 0, 1], min: [0, 0, 1] },
                { bufferView: 3, componentType: 5126, count: 4, type: 'VEC2', max: [1, 1], min: [0, 0] }
            ],
            bufferViews: [
                { buffer: 0, byteOffset: bvIdxOff,  byteLength: bvIdxLen,  target: 34963 },
                { buffer: 0, byteOffset: bvPosOff,  byteLength: bvPosLen,  target: 34962 },
                { buffer: 0, byteOffset: bvNormOff, byteLength: bvNormLen, target: 34962 },
                { buffer: 0, byteOffset: bvTexOff,  byteLength: bvTexLen,  target: 34962 },
                { buffer: 0, byteOffset: bvImgOff,  byteLength: bvImgLen }
            ],
            buffers: [{ byteLength: binLen }]
        };

        var jsonStr = JSON.stringify(gltf);
        // Pad JSON to 4-byte boundary
        while (jsonStr.length % 4 !== 0) jsonStr += ' ';

        var jsonBytes = new TextEncoder().encode(jsonStr);

        // GLB header: magic(4) + version(4) + length(4)
        // JSON chunk: length(4) + type(4) + data
        // BIN chunk: length(4) + type(4) + data
        var totalLen = 12 + 8 + jsonBytes.length + 8 + bin.length;

        var glb = new ArrayBuffer(totalLen);
        var view = new DataView(glb);
        var i = 0;

        // Header
        view.setUint32(i, 0x46546C67, true); i += 4;  // magic: glTF
        view.setUint32(i, 2, true); i += 4;             // version
        view.setUint32(i, totalLen, true); i += 4;       // total length

        // JSON chunk
        view.setUint32(i, jsonBytes.length, true); i += 4;
        view.setUint32(i, 0x4E4F534A, true); i += 4;    // JSON
        new Uint8Array(glb, i, jsonBytes.length).set(jsonBytes); i += jsonBytes.length;

        // BIN chunk
        view.setUint32(i, bin.length, true); i += 4;
        view.setUint32(i, 0x004E4942, true); i += 4;    // BIN\0
        new Uint8Array(glb, i, bin.length).set(bin);

        return new Uint8Array(glb);
    }

    /* ── Boot ─────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.PHCArViewer = { init: init };
})();
