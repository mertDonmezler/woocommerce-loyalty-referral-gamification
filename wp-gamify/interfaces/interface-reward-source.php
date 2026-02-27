<?php
/**
 * Reward Source Interface
 *
 * XP kaynagi modulleri tarafindan implement edilir.
 * Her kaynak kendi XP hesaplama mantigini tasir.
 *
 * @package WPGamify
 */

defined( 'ABSPATH' ) || exit;

interface GamifyRewardSource {

    /**
     * Kaynak benzersiz kimligini dondurur.
     *
     * @return string Kaynak kimligi (orn: 'order', 'review', 'login').
     */
    public function getId(): string;

    /**
     * Kullanici ve baglam icin XP miktarini hesaplar.
     *
     * @param int   $user_id Kullanici ID.
     * @param mixed $context Baglam verisi (siparis, yorum vb.).
     * @return int Hesaplanan XP miktari.
     */
    public function calculate( int $user_id, mixed $context ): int;

    /**
     * Bu kaynagin aktif olup olmadigini kontrol eder.
     *
     * @return bool Aktifse true.
     */
    public function isEnabled(): bool;

    /**
     * Bu kaynak icin gunluk XP limitini dondurur.
     *
     * @return int Gunluk limit (0 = limitsiz).
     */
    public function getDailyLimit(): int;
}
