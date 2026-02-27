<?php
/**
 * Pack Opening - Card pack opening animation on order completion.
 *
 * Each completed order becomes a "card pack" that the customer
 * can open with a cinematic reveal animation.
 *
 * @package PokeHoloCards\Frontend
 * @since   3.1.0
 */

namespace PokeHoloCards\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PackOpening {

    const ENDPOINT = 'phc-pack-opening';

    public static function init() {
        // WC My Account endpoint.
        add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
        add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'add_query_var' ) );
        add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
        add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render' ) );

        // Track new orders.
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_order_completed' ) );

        // Thank-you page banner.
        add_action( 'woocommerce_thankyou', array( __CLASS__, 'thankyou_banner' ), 5 );

        // AJAX: Mark pack as opened.
        add_action( 'wp_ajax_phc_mark_opened', array( __CLASS__, 'ajax_mark_opened' ) );
    }

    public static function add_endpoint() {
        add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
    }

    public static function add_query_var( $vars ) {
        $vars[ self::ENDPOINT ] = self::ENDPOINT;
        return $vars;
    }

    public static function add_menu_item( $items ) {
        $new = array();
        foreach ( $items as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'phc-collection' ) {
                $new[ self::ENDPOINT ] = __( 'Paketlerim', 'poke-holo-cards' );
            }
        }
        // If collection endpoint doesn't exist, add after orders.
        if ( ! isset( $new[ self::ENDPOINT ] ) ) {
            $new2 = array();
            foreach ( $items as $key => $label ) {
                $new2[ $key ] = $label;
                if ( $key === 'orders' ) {
                    $new2[ self::ENDPOINT ] = __( 'Paketlerim', 'poke-holo-cards' );
                }
            }
            return $new2;
        }
        return $new;
    }

    /**
     * When an order completes, add it to unviewed packs.
     */
    public static function on_order_completed( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        $user_id = $order->get_customer_id();
        if ( ! $user_id ) {
            return;
        }

        $unviewed = get_user_meta( $user_id, '_phc_unviewed_orders', true );
        if ( ! is_array( $unviewed ) ) {
            $unviewed = array();
        }
        if ( ! in_array( $order_id, $unviewed, true ) ) {
            $unviewed[] = $order_id;
            update_user_meta( $user_id, '_phc_unviewed_orders', $unviewed );
        }
    }

    /**
     * Show "Open your pack!" banner on thank-you page.
     */
    public static function thankyou_banner( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || ! in_array( $order->get_status(), array( 'completed', 'processing' ), true ) ) {
            return;
        }
        $user_id = $order->get_customer_id();
        if ( ! $user_id || $user_id !== get_current_user_id() ) {
            return;
        }

        $pack_url = wc_get_account_endpoint_url( self::ENDPOINT );
        ?>
        <div class="phc-thankyou-banner">
            <div class="phc-thankyou-banner-inner">
                <span class="phc-thankyou-icon">&#127873;</span>
                <div class="phc-thankyou-text">
                    <strong><?php esc_html_e( 'Kart paketiniz hazir!', 'poke-holo-cards' ); ?></strong>
                    <p><?php esc_html_e( 'Siparisinizdeki kartlari holografik olarak kesfetmek icin paketinizi acin.', 'poke-holo-cards' ); ?></p>
                </div>
                <a href="<?php echo esc_url( $pack_url ); ?>" class="phc-thankyou-btn">
                    <?php esc_html_e( 'Paketini Ac!', 'poke-holo-cards' ); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render the pack opening endpoint.
     */
    public static function render( $value = '' ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            echo '<p>' . esc_html__( 'Giris yapmaniz gerekiyor.', 'poke-holo-cards' ) . '</p>';
            return;
        }

        // If a specific order is requested, show pack opening.
        $order_id = absint( $value );
        if ( $order_id ) {
            self::render_single_pack( $user_id, $order_id );
            return;
        }

        // Otherwise, show pack list.
        self::render_pack_list( $user_id );
    }

    /**
     * Show list of available packs.
     */
    private static function render_pack_list( $user_id ) {
        $unviewed = get_user_meta( $user_id, '_phc_unviewed_orders', true );
        if ( ! is_array( $unviewed ) ) {
            $unviewed = array();
        }
        $opened = get_user_meta( $user_id, '_phc_opened_orders', true );
        if ( ! is_array( $opened ) ) {
            $opened = array();
        }

        // Get recent completed orders.
        $orders = wc_get_orders( array(
            'customer' => $user_id,
            'status'   => 'completed',
            'limit'    => 20,
            'orderby'  => 'date',
            'order'    => 'DESC',
        ) );

        $pack_url_base = wc_get_account_endpoint_url( self::ENDPOINT );
        ?>
        <div class="phc-pack-list-wrap">
            <h3><?php esc_html_e( 'Kart Paketleriniz', 'poke-holo-cards' ); ?></h3>

            <?php if ( empty( $orders ) ) : ?>
                <p class="phc-pack-empty"><?php esc_html_e( 'Henuz acilacak paketiniz yok. Siparis vererek paket kazanin!', 'poke-holo-cards' ); ?></p>
            <?php else : ?>
                <div class="phc-pack-grid">
                    <?php foreach ( $orders as $order ) :
                        $oid       = $order->get_id();
                        $is_new    = in_array( $oid, $unviewed, true );
                        $is_opened = in_array( $oid, $opened, true );
                        $item_count = count( $order->get_items() );
                        $date_obj   = $order->get_date_completed() ?: $order->get_date_created();
                        $date       = $date_obj ? $date_obj->date( 'd.m.Y' ) : current_time( 'd.m.Y' );
                    ?>
                    <div class="phc-pack-item <?php echo $is_new ? 'phc-pack-new' : ( $is_opened ? 'phc-pack-opened' : '' ); ?>">
                        <div class="phc-pack-icon">
                            <?php echo $is_opened ? '&#128230;' : '&#127873;'; ?>
                        </div>
                        <div class="phc-pack-info">
                            <strong><?php printf( esc_html__( 'Siparis #%s', 'poke-holo-cards' ), esc_html( $oid ) ); ?></strong>
                            <span class="phc-pack-date"><?php echo esc_html( $date ); ?></span>
                            <span class="phc-pack-count"><?php printf( esc_html__( '%d kart', 'poke-holo-cards' ), $item_count ); ?></span>
                        </div>
                        <?php if ( $is_new ) : ?>
                            <a href="<?php echo esc_url( $pack_url_base . $oid . '/' ); ?>" class="phc-pack-open-btn phc-pack-btn-new">
                                <?php esc_html_e( 'Paketi Ac!', 'poke-holo-cards' ); ?>
                            </a>
                        <?php elseif ( ! $is_opened ) : ?>
                            <a href="<?php echo esc_url( $pack_url_base . $oid . '/' ); ?>" class="phc-pack-open-btn">
                                <?php esc_html_e( 'Ac', 'poke-holo-cards' ); ?>
                            </a>
                        <?php else : ?>
                            <a href="<?php echo esc_url( $pack_url_base . $oid . '/' ); ?>" class="phc-pack-open-btn phc-pack-btn-replay">
                                <?php esc_html_e( 'Tekrar Izle', 'poke-holo-cards' ); ?>
                            </a>
                        <?php endif; ?>
                        <?php if ( $is_new ) : ?>
                            <span class="phc-pack-new-badge"><?php esc_html_e( 'YENI', 'poke-holo-cards' ); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render single pack opening experience.
     */
    private static function render_single_pack( $user_id, $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || (int) $order->get_customer_id() !== $user_id ) {
            echo '<p>' . esc_html__( 'Bu paket bulunamadi.', 'poke-holo-cards' ) . '</p>';
            return;
        }

        // Build card data.
        $cards = array();
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $product ) {
                continue;
            }

            $product_id = $item->get_product_id();
            $image_id   = $product->get_image_id();
            $image_url  = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : wc_placeholder_img_src( 'medium' );

            $meta    = function_exists( 'phc_get_product_meta' ) ? phc_get_product_meta( $product_id ) : array();
            $enabled = ! empty( $meta['enabled'] );
            $effect  = $enabled && ! empty( $meta['effect'] ) ? $meta['effect'] : 'none';
            $glow    = $enabled && ! empty( $meta['glow_color'] ) ? $meta['glow_color'] : '';
            $rarity  = ( $effect !== 'none' && function_exists( 'phc_get_rarity' ) ) ? phc_get_rarity( $effect ) : 'common';

            $qty = max( 1, $item->get_quantity() );
            for ( $i = 0; $i < $qty; $i++ ) {
                $cards[] = array(
                    'name'       => $product->get_name(),
                    'image_url'  => $image_url,
                    'effect'     => $effect,
                    'enabled'    => $enabled,
                    'glow'       => $glow,
                    'rarity'     => $rarity,
                    'product_id' => $product_id,
                );
            }
        }

        // Sort by rarity (common first, legendary last = dramatic reveal).
        usort( $cards, function ( $a, $b ) {
            return Collection::rarity_score( $a['rarity'] ) - Collection::rarity_score( $b['rarity'] );
        } );

        // Pre-render card HTML.
        $rendered = array();
        foreach ( $cards as $card ) {
            if ( ! empty( $card['enabled'] ) && $card['effect'] !== 'none' ) {
                $html = phc_render_card( $card['image_url'], array(
                    'effect'  => $card['effect'],
                    'width'   => '250px',
                    'sparkle' => ( Collection::rarity_score( $card['rarity'] ) >= 4 ) ? 'yes' : 'no',
                    'glow'    => $card['glow'],
                    'alt'     => $card['name'],
                    'class'   => 'phc-pack-reveal-card',
                ) );
            } else {
                $html = sprintf(
                    '<div class="phc-pack-reveal-card phc-pack-plain-card" style="width:250px"><img src="%s" alt="%s" loading="lazy" style="width:100%%;border-radius:12px" /></div>',
                    esc_url( $card['image_url'] ),
                    esc_attr( $card['name'] )
                );
            }
            $rendered[] = array(
                'name'    => $card['name'],
                'rarity'  => $card['rarity'],
                'effect'  => $card['effect'],
                'html'    => $html,
            );
        }

        $rarity_labels = array(
            'common'    => __( 'Common', 'poke-holo-cards' ),
            'uncommon'  => __( 'Uncommon', 'poke-holo-cards' ),
            'rare'      => __( 'Rare', 'poke-holo-cards' ),
            'epic'      => __( 'Epic', 'poke-holo-cards' ),
            'legendary' => __( 'Legendary', 'poke-holo-cards' ),
        );

        $collection_url = wc_get_account_endpoint_url( 'phc-collection' );
        ?>
        <div class="phc-pack-opening-container"
             data-order-id="<?php echo esc_attr( $order_id ); ?>"
             data-nonce="<?php echo esc_attr( wp_create_nonce( 'phc_pack_nonce' ) ); ?>"
             data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>"
             data-collection-url="<?php echo esc_attr( $collection_url ); ?>">

            <!-- Card data as JSON -->
            <script type="application/json" class="phc-pack-data">
                <?php echo wp_json_encode( $rendered ); ?>
            </script>

            <!-- Rarity labels -->
            <script type="application/json" class="phc-rarity-labels">
                <?php echo wp_json_encode( $rarity_labels ); ?>
            </script>

            <!-- Pack opening overlay (JS will control this) -->
            <div class="phc-pack-overlay">
                <button type="button" class="phc-skip-btn"><?php esc_html_e( 'Atla', 'poke-holo-cards' ); ?></button>

                <!-- Phase 1: Pack box -->
                <div class="phc-pack-box-wrap">
                    <div class="phc-pack-box">
                        <div class="phc-pack-box-glow"></div>
                        <span class="phc-pack-box-text"><?php esc_html_e( 'Acmak icin tikla!', 'poke-holo-cards' ); ?></span>
                        <span class="phc-pack-box-count"><?php printf( esc_html__( '%d kart', 'poke-holo-cards' ), count( $rendered ) ); ?></span>
                    </div>
                </div>

                <!-- Phase 2: Card reveals (populated by JS) -->
                <div class="phc-pack-reveal-area"></div>

                <!-- Phase 3: Summary -->
                <div class="phc-pack-summary" style="display:none">
                    <h2><?php esc_html_e( 'Paket Ozeti', 'poke-holo-cards' ); ?></h2>
                    <div class="phc-pack-summary-grid"></div>
                    <div class="phc-pack-summary-stats"></div>
                    <a href="<?php echo esc_url( $collection_url ); ?>" class="phc-pack-collection-btn">
                        <?php esc_html_e( 'Koleksiyona Git', 'poke-holo-cards' ); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Mark a pack as opened.
     */
    public static function ajax_mark_opened() {
        check_ajax_referer( 'phc_pack_nonce', 'nonce' );

        $user_id  = get_current_user_id();
        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

        if ( ! $user_id || ! $order_id ) {
            wp_send_json_error();
        }

        // Verify order belongs to current user.
        $order = wc_get_order( $order_id );
        if ( ! $order || (int) $order->get_customer_id() !== $user_id ) {
            wp_send_json_error();
        }

        // Remove from unviewed.
        $unviewed = get_user_meta( $user_id, '_phc_unviewed_orders', true );
        if ( is_array( $unviewed ) ) {
            $unviewed = array_values( array_diff( $unviewed, array( $order_id ) ) );
            update_user_meta( $user_id, '_phc_unviewed_orders', $unviewed );
        }

        // Add to opened.
        $opened = get_user_meta( $user_id, '_phc_opened_orders', true );
        if ( ! is_array( $opened ) ) {
            $opened = array();
        }
        if ( ! in_array( $order_id, $opened, true ) ) {
            $opened[] = $order_id;
            update_user_meta( $user_id, '_phc_opened_orders', $opened );
        }

        wp_send_json_success();
    }
}
