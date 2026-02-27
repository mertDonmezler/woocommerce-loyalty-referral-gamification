/**
 * WooCommerce Holo Cards - Gutenberg Block
 * Vanilla JS (no build step required)
 *
 * @since 2.0.0
 */
(function (wp) {
    'use strict';

    var registerBlockType  = wp.blocks.registerBlockType;
    var el                 = wp.element.createElement;
    var Fragment           = wp.element.Fragment;
    var useBlockProps       = wp.blockEditor.useBlockProps;
    var InspectorControls  = wp.blockEditor.InspectorControls;
    var MediaUpload        = wp.blockEditor.MediaUpload;
    var MediaUploadCheck   = wp.blockEditor.MediaUploadCheck;
    var PanelBody          = wp.components.PanelBody;
    var SelectControl      = wp.components.SelectControl;
    var ToggleControl      = wp.components.ToggleControl;
    var TextControl        = wp.components.TextControl;
    var RangeControl       = wp.components.RangeControl;
    var Button             = wp.components.Button;
    var ColorPalette       = wp.components.ColorPalette;
    var __                 = wp.i18n.__;

    /* ── Effect options (matching PHP phc_get_effect_types) ── */
    var effectOptions = [
        { value: 'holo',    label: __('Holographic',  'poke-holo-cards') },
        { value: 'rainbow', label: __('Rainbow',      'poke-holo-cards') },
        { value: 'cosmos',  label: __('Cosmos',       'poke-holo-cards') },
        { value: 'galaxy',  label: __('Galaxy',       'poke-holo-cards') },
        { value: 'prism',   label: __('Prism',        'poke-holo-cards') },
        { value: 'neon',    label: __('Neon',         'poke-holo-cards') },
        { value: 'basic',   label: __('Basic 3D',     'poke-holo-cards') },
        { value: 'vintage', label: __('Vintage',      'poke-holo-cards') },
        { value: 'aurora',  label: __('Aurora',       'poke-holo-cards') },
        { value: 'glitch',  label: __('Glitch',       'poke-holo-cards') }
    ];

    var springOptions = [
        { value: '',        label: __('Default',  'poke-holo-cards') },
        { value: 'bouncy',  label: __('Bouncy',   'poke-holo-cards') },
        { value: 'stiff',   label: __('Stiff',    'poke-holo-cards') },
        { value: 'smooth',  label: __('Smooth',   'poke-holo-cards') },
        { value: 'elastic', label: __('Elastic',  'poke-holo-cards') }
    ];

    /* ── Block Registration ── */
    registerBlockType('phc/holo-card', {

        edit: function (props) {
            var a   = props.attributes;
            var set = props.setAttributes;
            var blockProps = useBlockProps();

            /* ── Inspector Sidebar ── */
            var inspector = el(InspectorControls, null,

                /* Card Settings Panel */
                el(PanelBody, { title: __('Card Settings', 'poke-holo-cards'), initialOpen: true },
                    el(SelectControl, {
                        label:    __('Effect Type', 'poke-holo-cards'),
                        value:    a.effect,
                        options:  effectOptions,
                        onChange: function (v) { set({ effect: v }); }
                    }),
                    el(TextControl, {
                        label:    __('Width', 'poke-holo-cards'),
                        value:    a.width,
                        onChange: function (v) { set({ width: v }); },
                        help:     __('Any CSS value (e.g. 300px, 50%, 20vw)', 'poke-holo-cards')
                    }),
                    el(ToggleControl, {
                        label:    __('Showcase Mode', 'poke-holo-cards'),
                        help:     __('Auto-rotate animation when idle.', 'poke-holo-cards'),
                        checked:  a.showcase,
                        onChange: function (v) { set({ showcase: v }); }
                    }),
                    el(ToggleControl, {
                        label:    __('Sparkle Overlay', 'poke-holo-cards'),
                        help:     __('Animated sparkle particles on hover.', 'poke-holo-cards'),
                        checked:  a.sparkle,
                        onChange: function (v) { set({ sparkle: v }); }
                    }),
                    el(SelectControl, {
                        label:    __('Spring Preset', 'poke-holo-cards'),
                        value:    a.springPreset,
                        options:  springOptions,
                        onChange: function (v) { set({ springPreset: v }); }
                    })
                ),

                /* Appearance Panel */
                el(PanelBody, { title: __('Appearance', 'poke-holo-cards'), initialOpen: false },
                    el('p', { style: { marginBottom: 8 } }, __('Glow Color', 'poke-holo-cards')),
                    el(ColorPalette, {
                        value:    a.glowColor,
                        onChange: function (v) { set({ glowColor: v || '' }); }
                    }),
                    el(RangeControl, {
                        label:    __('Border Radius (%)', 'poke-holo-cards'),
                        value:    a.borderRadius,
                        min: 0, max: 50, step: 0.5,
                        onChange: function (v) { set({ borderRadius: v }); }
                    })
                ),

                /* Card Back Panel */
                el(PanelBody, { title: __('Card Back (Flip)', 'poke-holo-cards'), initialOpen: false },
                    el('p', { style: { marginBottom: 8, color: '#666', fontSize: 13 } },
                        __('Optional back image. Double-click or press Space to flip.', 'poke-holo-cards')
                    ),
                    el(MediaUploadCheck, null,
                        el(MediaUpload, {
                            onSelect: function (media) {
                                set({ backImgUrl: media.url, backImgId: media.id });
                            },
                            allowedTypes: ['image'],
                            value: a.backImgId,
                            render: function (obj) {
                                return el(Fragment, null,
                                    a.backImgUrl
                                        ? el('div', { style: { marginBottom: 12 } },
                                            el('img', {
                                                src: a.backImgUrl,
                                                style: { maxWidth: '100%', borderRadius: 4 }
                                            }),
                                            el(Button, {
                                                variant: 'secondary',
                                                isDestructive: true,
                                                onClick: function () { set({ backImgUrl: '', backImgId: 0 }); },
                                                style: { marginTop: 8 }
                                            }, __('Remove Back Image', 'poke-holo-cards'))
                                          )
                                        : null,
                                    el(Button, {
                                        onClick: obj.open,
                                        variant: a.backImgUrl ? 'secondary' : 'primary'
                                    }, a.backImgUrl
                                        ? __('Replace Back Image', 'poke-holo-cards')
                                        : __('Select Back Image', 'poke-holo-cards')
                                    )
                                );
                            }
                        })
                    )
                )
            );

            /* ── Block Content (Editor) ── */
            var content;

            if (a.imgUrl) {
                /* Show card preview */
                content = el('div', {
                    className: 'phc-card phc-effect-' + a.effect,
                    style: { width: a.width, margin: '0 auto' }
                },
                    el('div', { className: 'phc-card__translater' },
                        el('div', { className: 'phc-card__rotator' },
                            el('img', {
                                className: 'phc-card__front',
                                src: a.imgUrl,
                                alt: a.imgAlt,
                                style: { width: '100%', height: 'auto', display: 'block' }
                            }),
                            el('div', { className: 'phc-card__shine' }),
                            el('div', { className: 'phc-card__glare' })
                        )
                    )
                );
            } else {
                /* Show placeholder with upload button */
                content = el('div', { className: 'phc-block-placeholder' },
                    el('span', { className: 'dashicons dashicons-format-image' }),
                    el('p', null, __('Select an image for your holographic card.', 'poke-holo-cards')),
                    el(MediaUploadCheck, null,
                        el(MediaUpload, {
                            onSelect: function (media) {
                                set({
                                    imgUrl: media.url,
                                    imgId:  media.id,
                                    imgAlt: media.alt || ''
                                });
                            },
                            allowedTypes: ['image'],
                            render: function (obj) {
                                return el(Button, {
                                    onClick: obj.open,
                                    variant: 'primary',
                                    icon: 'upload'
                                }, __('Select Card Image', 'poke-holo-cards'));
                            }
                        })
                    )
                );
            }

            return el(Fragment, null,
                inspector,
                el('div', blockProps, content)
            );
        },

        save: function (props) {
            var a = props.attributes;
            if (!a.imgUrl) { return null; }

            var cardClasses = 'phc-card phc-effect-' + a.effect;
            if (a.showcase) { cardClasses += ' phc-showcase'; }

            var cardAttrs = {
                className: cardClasses,
                style: { width: a.width },
                'data-phc-effect': a.effect
            };
            if (a.showcase)     { cardAttrs['data-phc-showcase'] = 'true'; }
            if (a.sparkle)      { cardAttrs['data-phc-sparkle']  = 'true'; }
            if (a.glowColor)    { cardAttrs['data-phc-glow']     = a.glowColor; }
            if (a.borderRadius) { cardAttrs['data-phc-radius']   = String(a.borderRadius); }
            if (a.backImgUrl)   { cardAttrs['data-phc-back']     = a.backImgUrl; }
            if (a.springPreset) { cardAttrs['data-phc-spring']   = a.springPreset; }

            return el('div', cardAttrs,
                el('div', { className: 'phc-card__translater' },
                    el('div', { className: 'phc-card__rotator' },
                        el('img', {
                            className: 'phc-card__front',
                            src: a.imgUrl,
                            alt: a.imgAlt,
                            loading: 'lazy'
                        }),
                        el('div', { className: 'phc-card__shine' }),
                        el('div', { className: 'phc-card__glare' })
                    )
                )
            );
        }
    });

})(window.wp);
