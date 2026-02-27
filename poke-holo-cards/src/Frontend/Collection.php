<?php
/**
 * Card Collection - WooCommerce My Account endpoint.
 *
 * Displays all purchased products as interactive holographic cards
 * with rarity tiers, filtering, and sorting.
 *
 * @package PokeHoloCards\Frontend
 * @since   3.1.0
 */

namespace PokeHoloCards\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Collection {

    const ENDPOINT     = 'phc-collection';
    const TRANSIENT_PX = 'phc_collection_';
    const CACHE_TTL    = 300; // 5 minutes.
    const PER_PAGE     = 20;

    public static function init() {
        add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
        add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'add_query_var' ) );
        add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
        add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render' ) );
        // Invalidate cache on order completion.
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'invalidate_cache' ) );
    }

    /**
     * Register the rewrite endpoint.
     */
    public static function add_endpoint() {
        add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
    }

    /**
     * Add query variable for WooCommerce.
     */
    public static function add_query_var( $vars ) {
        $vars[ self::ENDPOINT ] = self::ENDPOINT;
        return $vars;
    }

    /**
     * Insert "Kart Koleksiyonum" into the My Account menu.
     */
    public static function add_menu_item( $items ) {
        $new = array();
        foreach ( $items as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'orders' ) {
                $new[ self::ENDPOINT ] = __( 'Kart Koleksiyonum', 'poke-holo-cards' );
            }
        }
        return $new;
    }

    /**
     * Build the collection data for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return array
     */
    public static function get_collection( $user_id ) {
        $cache_key = self::TRANSIENT_PX . $user_id;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        $cards = array();
        $orders = wc_get_orders( array(
            'customer' => $user_id,
            'status'   => 'completed',
            'limit'    => 200,
            'return'   => 'ids',
        ) );

        $product_counts = array();
        $product_first  = array();

        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                continue;
            }
            $date_obj = $order->get_date_completed() ?: $order->get_date_created();
            $date     = $date_obj ? $date_obj->date( 'Y-m-d' ) : current_time( 'Y-m-d' );

            foreach ( $order->get_items() as $item ) {
                $product_id = $item->get_product_id();
                $product    = $item->get_product();
                if ( ! $product ) {
                    continue;
                }

                $qty = max( 1, $item->get_quantity() );
                if ( ! isset( $product_counts[ $product_id ] ) ) {
                    $product_counts[ $product_id ] = 0;
                    $product_first[ $product_id ]  = $date;
                }
                $product_counts[ $product_id ] += $qty;

                // Keep earliest date.
                if ( $date < $product_first[ $product_id ] ) {
                    $product_first[ $product_id ] = $date;
                }
            }
        }

        foreach ( $product_counts as $product_id => $count ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }

            $image_id  = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : wc_placeholder_img_src( 'medium' );

            $meta    = function_exists( 'phc_get_product_meta' ) ? phc_get_product_meta( $product_id ) : array();
            $enabled = ! empty( $meta['enabled'] );
            $effect  = $enabled && ! empty( $meta['effect'] ) ? $meta['effect'] : 'none';
            $glow    = $enabled && ! empty( $meta['glow_color'] ) ? $meta['glow_color'] : '';

            $rarity = ( $effect !== 'none' && function_exists( 'phc_get_rarity' ) ) ? phc_get_rarity( $effect ) : 'common';

            $cards[] = array(
                'product_id' => $product_id,
                'name'       => $product->get_name(),
                'image_url'  => $image_url,
                'effect'     => $effect,
                'enabled'    => $enabled,
                'glow'       => $glow,
                'rarity'     => $rarity,
                'count'      => $count,
                'date'       => $product_first[ $product_id ],
                'price'      => $product->get_price(),
            );
        }

        /** Allow filtering of collection data. */
        $cards = apply_filters( 'phc_collection_card_data', $cards, $user_id );

        set_transient( $cache_key, $cards, self::CACHE_TTL );
        return $cards;
    }

    /**
     * Invalidate collection cache when an order completes.
     */
    public static function invalidate_cache( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        $user_id = $order->get_customer_id();
        if ( $user_id ) {
            delete_transient( self::TRANSIENT_PX . $user_id );
        }
    }

    /**
     * Render the collection endpoint.
     */
    public static function render() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            echo '<p>' . esc_html__( 'Koleksiyonunuzu goruntulemek icin giris yapin.', 'poke-holo-cards' ) . '</p>';
            return;
        }

        $all_cards = self::get_collection( $user_id );

        // Pagination
        $per_page    = apply_filters( 'phc_collection_per_page', self::PER_PAGE );
        $total_items = count( $all_cards );
        $total_pages = max( 1, (int) ceil( $total_items / $per_page ) );
        $current_page = isset( $_GET['cpage'] ) ? max( 1, min( $total_pages, intval( $_GET['cpage'] ) ) ) : 1;
        $offset = ( $current_page - 1 ) * $per_page;
        $cards  = array_slice( $all_cards, $offset, $per_page );

        // Rarity stats (calculated from ALL cards, not just current page).
        $rarity_map   = array( 'common' => 0, 'uncommon' => 0, 'rare' => 0, 'epic' => 0, 'legendary' => 0 );
        $rarity_labels = array(
            'common'    => __( 'Common', 'poke-holo-cards' ),
            'uncommon'  => __( 'Uncommon', 'poke-holo-cards' ),
            'rare'      => __( 'Rare', 'poke-holo-cards' ),
            'epic'      => __( 'Epic', 'poke-holo-cards' ),
            'legendary' => __( 'Legendary', 'poke-holo-cards' ),
        );
        $total_cards = 0;
        foreach ( $all_cards as $card ) {
            $r = $card['rarity'];
            if ( isset( $rarity_map[ $r ] ) ) {
                $rarity_map[ $r ] += $card['count'];
            }
            $total_cards += $card['count'];
        }
        $unique_cards = count( $all_cards );

        // Effects for filter dropdown (from all cards).
        $effects_in_collection = array_unique( array_column( $all_cards, 'effect' ) );
        sort( $effects_in_collection );
        ?>

        <div class="phc-collection-wrap">

            <!-- Stats Panel -->
            <div class="phc-collection-stats">
                <div class="phc-stat">
                    <span class="phc-stat-num"><?php echo esc_html( $total_cards ); ?></span>
                    <span class="phc-stat-label"><?php esc_html_e( 'Toplam Kart', 'poke-holo-cards' ); ?></span>
                </div>
                <div class="phc-stat">
                    <span class="phc-stat-num"><?php echo esc_html( $unique_cards ); ?></span>
                    <span class="phc-stat-label"><?php esc_html_e( 'Benzersiz', 'poke-holo-cards' ); ?></span>
                </div>
                <?php foreach ( $rarity_map as $rarity_key => $rcount ) : if ( $rcount === 0 ) continue; ?>
                <div class="phc-stat phc-stat-<?php echo esc_attr( $rarity_key ); ?>">
                    <span class="phc-stat-num"><?php echo esc_html( $rcount ); ?></span>
                    <span class="phc-stat-label"><?php echo esc_html( $rarity_labels[ $rarity_key ] ); ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Filters -->
            <div class="phc-collection-filters">
                <input type="text" class="phc-filter-search" placeholder="<?php esc_attr_e( 'Kart ara...', 'poke-holo-cards' ); ?>" />

                <select class="phc-filter-rarity">
                    <option value=""><?php esc_html_e( 'Tum Nadirllikler', 'poke-holo-cards' ); ?></option>
                    <?php foreach ( $rarity_labels as $rk => $rl ) : ?>
                    <option value="<?php echo esc_attr( $rk ); ?>"><?php echo esc_html( $rl ); ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="phc-filter-effect">
                    <option value=""><?php esc_html_e( 'Tum Efektler', 'poke-holo-cards' ); ?></option>
                    <?php foreach ( $effects_in_collection as $eff ) : ?>
                    <option value="<?php echo esc_attr( $eff ); ?>"><?php echo esc_html( ucfirst( $eff ) ); ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="phc-filter-sort">
                    <option value="date-desc"><?php esc_html_e( 'En Yeni', 'poke-holo-cards' ); ?></option>
                    <option value="date-asc"><?php esc_html_e( 'En Eski', 'poke-holo-cards' ); ?></option>
                    <option value="name-asc"><?php esc_html_e( 'A-Z', 'poke-holo-cards' ); ?></option>
                    <option value="name-desc"><?php esc_html_e( 'Z-A', 'poke-holo-cards' ); ?></option>
                    <option value="rarity-desc"><?php esc_html_e( 'En Nadir', 'poke-holo-cards' ); ?></option>
                    <option value="rarity-asc"><?php esc_html_e( 'En Yaygin', 'poke-holo-cards' ); ?></option>
                </select>

                <button type="button" class="phc-compare-btn" disabled>
                    <?php esc_html_e( 'Karsilastir', 'poke-holo-cards' ); ?> (<span class="phc-compare-count">0</span>/2)
                </button>
            </div>

            <?php if ( empty( $cards ) ) : ?>
                <div class="phc-collection-empty">
                    <p><?php esc_html_e( 'Henuz koleksiyonunuzda kart yok. Siparis vererek kart toplayabilirsiniz!', 'poke-holo-cards' ); ?></p>
                </div>
            <?php else : ?>

            <!-- Card Grid -->
            <div class="phc-collection-grid">
                <?php foreach ( $cards as $card ) :
                    $rarity_score = self::rarity_score( $card['rarity'] );
                    if ( ! empty( $card['enabled'] ) && $card['effect'] !== 'none' ) {
                        $card_html = phc_render_card( $card['image_url'], array(
                            'effect'  => $card['effect'],
                            'width'   => '100%',
                            'sparkle' => ( $rarity_score >= 4 ) ? 'yes' : 'no',
                            'glow'    => $card['glow'],
                            'alt'     => $card['name'],
                            'class'   => 'phc-collection-holo',
                        ) );
                    } else {
                        $card_html = sprintf(
                            '<div class="phc-collection-plain"><img src="%s" alt="%s" loading="lazy" /></div>',
                            esc_url( $card['image_url'] ),
                            esc_attr( $card['name'] )
                        );
                    }
                ?>
                <div class="phc-collection-card"
                     data-product-id="<?php echo esc_attr( $card['product_id'] ); ?>"
                     data-name="<?php echo esc_attr( strtolower( $card['name'] ) ); ?>"
                     data-rarity="<?php echo esc_attr( $card['rarity'] ); ?>"
                     data-rarity-score="<?php echo esc_attr( $rarity_score ); ?>"
                     data-effect="<?php echo esc_attr( $card['effect'] ); ?>"
                     data-date="<?php echo esc_attr( $card['date'] ); ?>"
                     data-count="<?php echo esc_attr( $card['count'] ); ?>">

                    <div class="phc-collection-card-inner">
                        <?php echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>

                    <div class="phc-collection-card-info">
                        <span class="phc-card-name"><?php echo esc_html( $card['name'] ); ?></span>
                        <span class="phc-rarity-badge phc-rarity-<?php echo esc_attr( $card['rarity'] ); ?>">
                            <?php echo esc_html( $rarity_labels[ $card['rarity'] ] ?? $card['rarity'] ); ?>
                        </span>
                        <?php if ( $card['count'] > 1 ) : ?>
                        <span class="phc-card-count">x<?php echo esc_html( $card['count'] ); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="phc-collection-select-overlay">
                        <span class="phc-select-check">&#10003;</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php endif; ?>

            <?php if ( $total_pages > 1 ) : ?>
            <!-- Pagination -->
            <div class="phc-collection-pagination" style="display:flex; justify-content:center; gap:6px; margin-top:24px; flex-wrap:wrap;">
                <?php
                $base_url = wc_get_endpoint_url( self::ENDPOINT, '', wc_get_page_permalink( 'myaccount' ) );
                if ( $current_page > 1 ) :
                    $prev_url = add_query_arg( 'cpage', $current_page - 1, $base_url );
                ?>
                <a href="<?php echo esc_url( $prev_url ); ?>" style="padding:8px 14px; border:1px solid #d1d5db; border-radius:8px; text-decoration:none; color:#374151; font-size:13px;">&laquo; <?php esc_html_e( 'Onceki', 'poke-holo-cards' ); ?></a>
                <?php endif; ?>

                <?php for ( $i = 1; $i <= $total_pages; $i++ ) :
                    $page_url = add_query_arg( 'cpage', $i, $base_url );
                    $is_current = ( $i === $current_page );
                ?>
                <a href="<?php echo esc_url( $page_url ); ?>"
                   style="padding:8px 14px; border:1px solid <?php echo $is_current ? '#3b82f6' : '#d1d5db'; ?>; border-radius:8px; text-decoration:none; font-size:13px; font-weight:<?php echo $is_current ? '700' : '400'; ?>; color:<?php echo $is_current ? '#fff' : '#374151'; ?>; background:<?php echo $is_current ? '#3b82f6' : '#fff'; ?>;">
                    <?php echo esc_html( $i ); ?>
                </a>
                <?php endfor; ?>

                <?php if ( $current_page < $total_pages ) :
                    $next_url = add_query_arg( 'cpage', $current_page + 1, $base_url );
                ?>
                <a href="<?php echo esc_url( $next_url ); ?>" style="padding:8px 14px; border:1px solid #d1d5db; border-radius:8px; text-decoration:none; color:#374151; font-size:13px;"><?php esc_html_e( 'Sonraki', 'poke-holo-cards' ); ?> &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Compare Modal (injected by JS) -->
            <div class="phc-compare-modal" style="display:none"></div>
        </div>
        <?php
    }

    /**
     * Rarity score for sorting (higher = rarer).
     */
    public static function rarity_score( $rarity ) {
        $map = array(
            'common'    => 1,
            'uncommon'  => 2,
            'rare'      => 3,
            'epic'      => 4,
            'legendary' => 5,
        );
        return $map[ $rarity ] ?? 1;
    }
}
