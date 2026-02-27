<?php
/**
 * Quest Checker Interface
 *
 * Gorev kontrol modulleri tarafindan implement edilir.
 * Belirli bir donem icindeki gorev ilerleme yuzdesini dondurur.
 *
 * @package WPGamify
 */

defined( 'ABSPATH' ) || exit;

interface GamifyQuestChecker {

    /**
     * Kullanicinin belirli donem icindeki gorev ilerlemesini kontrol eder.
     *
     * @param int    $user_id    Kullanici ID.
     * @param string $period_key Donem anahtari (orn: '2026-W09', '2026-02').
     * @return int Ilerleme yuzdesi (0-100).
     */
    public function check( int $user_id, string $period_key ): int;
}
