<?php
/**
 * Badge Evaluator Interface
 *
 * Rozet degerlendirme modulleri tarafindan implement edilir.
 * Kullanicinin rozet ilerleme yuzdesini dondurur.
 *
 * @package WPGamify
 */

defined( 'ABSPATH' ) || exit;

interface GamifyBadgeEvaluator {

    /**
     * Kullanicinin rozet ilerleme yuzdesini degerlendirir.
     *
     * @param int $user_id Kullanici ID.
     * @return int Ilerleme yuzdesi (0-100).
     */
    public function evaluate( int $user_id ): int;
}
