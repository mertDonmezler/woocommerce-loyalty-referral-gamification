# Gorilla Loyalty & Gamification Ecosystem - Kapsamli Teknik Rapor

**Tarih:** 2 Mart 2026
**Versiyon:** v2.0.0 (tum pluginler)
**Hazirlayan:** Mert Donmezler
**Durum:** Production-Ready

---

## Icindekiler

1. [Genel Bakis](#1-genel-bakis)
2. [Plugin 1: WP Gamify (XP Motoru)](#2-wp-gamify-xp-motoru)
3. [Plugin 2: Gorilla Loyalty & Gamification](#3-gorilla-loyalty--gamification)
4. [Plugin 3: Gorilla Referral & Affiliate](#4-gorilla-referral--affiliate)
5. [Pluginler Arasi Entegrasyon](#5-pluginler-arasi-entegrasyon)
6. [Veritabani Semasi (Tum Tablolar)](#6-veritabani-semasi)
7. [REST API Referansi](#7-rest-api-referansi)
8. [Guvenlik Ozeti](#8-guvenlik-ozeti)
9. [GDPR/KVKK Uyumlulugu](#9-gdprkvkk-uyumlulugu)
10. [Performans Optimizasyonlari](#10-performans-optimizasyonlari)
11. [Cron Gorevleri](#11-cron-gorevleri)
12. [E-posta Bildirimleri](#12-e-posta-bildirimleri)
13. [SMS Bildirimleri](#13-sms-bildirimleri)
14. [Admin Paneli Ozellikleri](#14-admin-paneli-ozellikleri)
15. [Musteri On Yuzu (Frontend)](#15-musteri-on-yuzu-frontend)
16. [WP-CLI Komutlari](#16-wp-cli-komutlari)
17. [Eksikler ve Gelecek Planlar](#17-eksikler-ve-gelecek-planlar)
18. [Dosya Yapisi](#18-dosya-yapisi)
19. [Kurulum ve Aktivasyon](#19-kurulum-ve-aktivasyon)
20. [Ozet Istatistikler](#20-ozet-istatistikler)

---

## 1. Genel Bakis

### Ekosistem Mimarisi

Sistem 3 ayri WordPress eklentisinden olusur. Her biri bagimsiz olarak calisabilir, ancak birlikte kullanildiginda tam bir gamification + sadakat + referans platformu sunar.

```
┌─────────────────────────────────────────────────────────┐
│                    WooCommerce                          │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────┐  ┌──────────────┐  ┌────────────────┐ │
│  │  WP Gamify   │  │ Gorilla LG   │  │ Gorilla R&A    │ │
│  │  (XP Motor)  │←─│ (Sadakat)    │──│ (Referans)     │ │
│  │              │  │              │  │                │ │
│  │ • XP Engine  │  │ • Tier'ler   │  │ • Video Ref.   │ │
│  │ • Seviyeler  │  │ • Badge'ler  │  │ • Affiliate    │ │
│  │ • Streak     │  │ • Spin Wheel │  │ • Komisyon     │ │
│  │ • Kampanya   │  │ • Store Cred.│  │ • Fraud Det.   │ │
│  │ • Anti-Abuse │  │ • Challenges │  │ • Dual Reward  │ │
│  │ • Grace Per. │  │ • Milestone  │  │ • QR Code      │ │
│  │ • XP Expiry  │  │ • Leaderboard│  │                │ │
│  │              │  │ • Points Shop│  │                │ │
│  │              │  │ • SMS/Email  │  │                │ │
│  └─────────────┘  └──────────────┘  └────────────────┘ │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### Teknik Gereksinimler

| Gereksinim | Minimum |
|------------|---------|
| WordPress | 6.0+ |
| PHP | 7.4+ (WP Gamify: 8.0+) |
| WooCommerce | 7.0+ (Test: 9.5) |
| MySQL | 5.7+ (InnoDB) |
| HPOS | Tam uyumlu |

### Bagimlilk Zinciri

```
Gorilla Referral & Affiliate
    └── Gorilla Loyalty & Gamification (zorunlu)
            └── WP Gamify (zorunlu)
                    └── WooCommerce (zorunlu)
```

---

## 2. WP Gamify (XP Motoru)

### Genel Bilgi

| Ozellik | Deger |
|---------|-------|
| Versiyon | 2.0.0 |
| Text Domain | wp-gamify |
| PHP Dosya Sayisi | 35 |
| Veritabani Tablosu | 5 |
| REST API Endpoint | 3 |
| Hook Sayisi | 15+ action, 5+ filter |

### Temel Ozellikler

#### 2.1 XP Motoru (class-xp-engine.php)

Tum XP islemlerinin merkezi. Atomik islemler ve eslenik (concurrent) erisim guvenligiyle tasarlanmistir.

**XP Kaynakları:**

| Kaynak | Varsayilan XP | Ayarlanabilir |
|--------|---------------|---------------|
| Siparis (baz) | 10 | Evet |
| Siparis (para basina) | 1 XP / birim | Evet |
| Ilk siparis bonusu | 50 | Evet |
| Urun incelemesi | 15 | Evet (min karakter: 20) |
| Giris | 5 | Evet |
| Dogum gunu | 100 | Evet |
| Yildonumu | 50 | Evet |
| Kayit | 25 | Evet |
| Profil tamamlama | 20 | Evet |
| Streak (serisi) | 2 * 2^(gun-1) | Evet |
| Referans | 50 | Evet |
| Affiliate | 30 | Evet |

**Guvenlik Mekanizmalari:**
- MySQL `GET_LOCK` / `RELEASE_LOCK` ile eslenik erisim korunmasi (3sn timeout)
- `INSERT IGNORE` ile siparis kilit atomikligi
- Transaction bloklari (`START TRANSACTION` / `COMMIT` / `ROLLBACK`)
- Gunluk XP limiti (varsayilan: 500)
- Tekrar inceleme engellemesi
- Kendi referansini engelleme (ID, IP, email domain)

#### 2.2 Seviye Sistemi (class-level-manager.php)

**8 Varsayilan Seviye:**

| Seviye | Ad | Gereken XP | Indirim | Ucretsiz Kargo | Erken Erisim |
|--------|----|------------|---------|-----------------|--------------|
| 1 | Caylak | 0 | %0 | - | - |
| 2 | Kesifci | 100 | %3 | - | - |
| 3 | Koleksiyoncu | 500 | %5 | - | - |
| 4 | Uzman | 1,500 | %7 | Evet | - |
| 5 | Usta | 3,500 | %10 | Evet | - |
| 6 | Efsane | 7,000 | %12 | Evet | Evet |
| 7 | Sampiyon | 12,000 | %15 | Evet | Evet |
| 8 | Efsanevi | 20,000 | %20 | Evet | Evet |

**Seviye Hesaplama Modlari:**
- `alltime`: Tum zamanlarin toplam XP'si
- `rolling`: Son 6 aydaki XP (ayarlanabilir)

**Grace Period (Koruma Suresi):**
- Kullanici XP duserse hemen seviye dusmez
- 14 gun koruma suresi baslar (ayarlanabilir)
- Sure icinde XP toplanirsa seviye korunur
- Sure dolarsa seviye duser, `gamify_level_down` hook tetiklenir

#### 2.3 Streak (Seri) Sistemi (class-streak-manager.php)

- Gunluk giris takibi
- Ustel XP odulu: `base * multiplier^(gun-1)` (varsayilan: 2 * 2^(gun-1))
- Maksimum seri gunu: 7 (ayarlanabilir)
- Seri sifirlandiginda dongu yeniden baslar
- Dogum gunu ve yildonumu XP kontrolu

#### 2.4 Kampanya Sistemi (class-campaign-manager.php)

**Faz 1 (Mevcut):**
- Basit option-based depolama (4 wp_options)
- Tek aktif kampanya
- Tarih araligiyla aktiflesme
- XP carpan (ornek: 2.0 = cift XP)
- Her XP verilmesinde otomatik uygulama (`gamify_xp_before_award` filtresi)
- Carpan degeri islem kaydinda saklanir (audit trail)

#### 2.5 XP Son Kullanma (class-xp-expiry.php)

- Ayarlanabilir sure (varsayilan: 12 ay)
- Uyari e-postasi (sureden 14 gun once)
- Aylik islem guardi (INSERT IGNORE ile TOCTOU korunmasi)
- Toplu isleme (LIMIT 500 kullanici/calisma)
- Kaynak tipi: `xp_expired`

#### 2.6 Anti-Abuse (class-anti-abuse.php)

| Koruma | Aciklama |
|--------|----------|
| Gunluk XP limiti | Kullanici basina gunluk 500 XP (ayarlanabilir) |
| Tekrar inceleme | Ayni urun icin tekrar XP yok |
| Kendi referansi | Ayni ID, IP veya email domain engellenir |
| Supheliler | Sayac + zaman damgasi ile izleme |
| IP takibi | Islem bazli IP kaydı |

#### 2.7 Hook ve Filtre Sistemi

**Action Hook'lari:**
```
gamify_after_activation
gamify_after_xp_awarded($user_id, $amount, $source, $source_id)
gamify_after_xp_deducted($user_id, $amount, $source, $source_id)
gamify_level_up($user_id, $old_level, $new_level)
gamify_level_down($user_id, $old_level, $new_level)
gamify_grace_period_started($user_id, $level, $until)
gamify_birthday_xp_awarded($user_id, $xp)
gamify_anniversary_xp_awarded($user_id, $xp, $years)
gamify_suspicious_activity($user_id, $reason, $count)
gamify_xp_expiry_warning($user_id, $expiring_xp, $expiry_date)
gamify_campaign_set($multiplier, $label, $start, $end)
gamify_campaign_cleared()
```

**Filtre Hook'lari:**
```
gamify_xp_before_award($xp, $source, $user_id, $source_id)
gamify_order_xp($total_xp, $order_id, $user_id)
gamify_level_discount_amount($discount, $percent, $user_id, $benefits)
gamify_level_shipping_rates($rates)
gamify_source_labels($labels)
```

---

## 3. Gorilla Loyalty & Gamification

### Genel Bilgi

| Ozellik | Deger |
|---------|-------|
| Versiyon | 2.0.0 |
| Text Domain | gorilla-loyalty |
| PHP Dosya Sayisi | 15 |
| PHP Toplam Satir | ~8,700 |
| CSS/JS Satir | ~1,470 |
| Veritabani Tablosu | 1 (+ legacy) |
| REST API Endpoint | 17+ |
| E-posta Sinifi | 12 WC_Email |
| Admin Ayar | 40+ |

### 13+ Temel Ozellik

#### 3.1 Sadakat Tier Sistemi

**5 Varsayilan Tier:**

| Tier | Minimum Harcama | Indirim | Ucretsiz Kargo | Taksit |
|------|----------------|---------|-----------------|--------|
| Bronze | 0 TL | %0 | - | - |
| Silver | 500 TL | %3 | - | - |
| Gold | 2,000 TL | %5 | Evet | - |
| Platinum | 5,000 TL | %10 | Evet | Evet |
| Diamond | 15,000 TL | %15 | Evet | Evet |

- Harcama bazli hesaplama (son N ay, ayarlanabilir)
- Grace period (dusme korunmasi)
- Otomatik hesaplama (siparis tamamlaninca)
- VIP erken erisim (Diamond tier)

#### 3.2 Store Credit (Magaza Kredisi)

- Atomik islemler (MySQL row lock + GET_LOCK)
- Son kullanma tarihi yonetimi
- Checkout entegrasyonu (sepette kredi kullanimi)
- Otomatik iade (siparis iptalinde)
- Admin kredi ayarlamalari
- Tam islem kaydi (audit trail)
- TOCTOU korunmasi (eslenik harcama/iade guvenli)

#### 3.3 Badge (Rozet) Sistemi

**20+ Varsayilan Rozet:**
- Tier bazli: Her tier icin rozet
- Harcama bazli: Ilk Alisveris, Buyuk Harcamaci
- Davranis bazli: Power Reviewer, Affiliate Master
- Ozel: Seri Rekortmeni, Sosyal Paylasimci

Otomatik kontrol: Siparis, inceleme, referans, affiliate olaylarinda tetiklenir.

#### 3.4 Spin Wheel (Cark)

- 8+ varsayilan odul
- Olasilik agirlik sistemi (configurable)
- Donme hakki: Seviye atlama, milestone, challenge'lardan kazanilir
- Confetti animasyonu
- Odul tipleri: XP, kredi, ucretsiz kargo, indirim kuponu, bos

#### 3.5 Milestone (Kilometre Tasi)

| Milestone | Kosul | Odul |
|-----------|-------|------|
| Ilk Siparis | 1 siparis | 50 XP + 10 TL |
| 10 Siparis | 10 siparis | 200 XP + 50 TL |
| 5000 TL Harcama | 5000 TL toplam | 500 XP + 100 TL |
| Hesap Yasi | 365 gun | 100 XP + 25 TL |

Atomik guard ile cift odul onlenir.

#### 3.6 Points Shop (Puan Dukkan)

| Odul | XP Maliyeti | Tip |
|------|-------------|-----|
| 5 TL Indirim | 200 XP | Kupon |
| 10 TL Indirim | 400 XP | Kupon |
| Ucretsiz Kargo | 150 XP | Kupon |
| Ozel Odul | 500 XP | Ozel |

REST API ile harcama: `POST /gorilla-lg/v1/shop/redeem`

#### 3.7 Leaderboard (Siralama)

- Periyot: Haftalik, aylik, tum zamanlar
- Anonim mod (isimleri gizleme)
- Limit: Top 10-50 (ayarlanabilir)
- 5 dakikalik transient cache
- REST API: `GET /gorilla-lg/v1/leaderboard`

#### 3.8 Sosyal Paylasim

- Platformlar: Facebook, Twitter, WhatsApp, Instagram, TikTok
- XP odulu: Paylasim basina 10 XP (ayarlanabilir)
- Gunluk guard: Kullanici/platform/gun basina 1 paylasim
- Deterministic ref_id ile tekrar kontrol

#### 3.9 QR Kod

- QuickChart API ile olusturma
- Kullaniciya ozel referans QR kodu
- PNG indirme destegi
- Offline paylasim icin (basili, sosyal)

#### 3.10 Challenge (Gorev) Sistemi

- Tipler: Siparis, inceleme, harcama, referans
- Periyot: Haftalik, tek seferlik
- Gercek zamanli ilerleme cubugu
- Haftalik gorevler Pazartesi sifirlanir
- XP + kredi odulleri

#### 3.11 Churn Prediction (Kayip Onleme)

- Aktif olmayan kullanicilari tespit (N ay)
- Otomatik bonus kredi + XP + e-posta
- Ceyreklik frekans (kullanici basina 3 ayda 1)
- Haftalik toplu isleme

#### 3.12 Smart Coupon (Akilli Kupon)

- Tetik: 21+ gun aktif olmayan kullanicilar
- Mantik: Favori urun kategorisini hedefler
- Indirim: %10 (ayarlanabilir)
- Sure: 14 gun (ayarlanabilir)
- E-posta: Kisisellestirilmis teklif
- Haftalik batch (maks 50/hafta)

#### 3.13 Ek Ozellikler

| Ozellik | Aciklama |
|---------|----------|
| Dogum gunu kampanyasi | Dogum gunu ayinda kredi |
| Yildonumu kredisi | Hesap yildonumunde kredi |
| Kredi transferi | Kullanicilar arasi transfer (ucretli) |
| Urun bazli XP | Admin urun bazinda ozel XP atayabilir |
| Kategori bonusu | Kategori bazli XP carpanlari |
| Sosyal kanit toastlari | Alt bilgi barinda basarimlar (anonim) |
| Footer loyalty bar | 2px renkli tier cubugu |
| Bildirimler | Uygulama ici bildirimler (maks 50) |

---

## 4. Gorilla Referral & Affiliate

### Genel Bilgi

| Ozellik | Deger |
|---------|-------|
| Versiyon | 2.0.0 |
| Text Domain | gorilla-ra |
| PHP Dosya Sayisi | 12 |
| PHP Toplam Satir | ~4,088 |
| Veritabani Tablosu | 1 |
| REST API Endpoint | 3 |
| E-posta Tipi | 5 |
| Admin Ayar | 20+ |

### Temel Ozellikler

#### 4.1 Video Referans Sistemi

**Akis:**
1. Musteri video gonderir (YouTube, Instagram, TikTok, Twitter/X, Facebook, Twitch, Vimeo)
2. Admin metabox'ta gomulu onizleme ile inceler
3. Admin onaylar/reddeder (toplu islem destegi)
4. Onayda: Magaza kredisi verilir (siparis toplaminin %'si), musteri bilgilendirilir
5. Redde: Musteri bilgilendirilir

**Custom Post Type:** `gorilla_referral` (private)
**Durumlar:** `pending`, `grla_approved`, `grla_rejected`

**Guvenlik:**
- 5 dakika bekleme suresi (kullanici basina)
- Race condition korunmasi (transient lock)
- Cift onay kilidi

#### 4.2 Affiliate Sistemi

- Affiliate kodu: `G + base36(user_id) + random(2 karakter)`
- Link: `home_url/?ref=[kod]` (URL parametresi ayarlanabilir)
- Cookie tabanli takip + WooCommerce session
- Cookie suresi: 1-365 gun (varsayilan: 30)
- Komisyon tetikleme: Siparis `completed` veya `processing`
- Idempotency guard (cift kredi onleme)
- Kendi referansini kontrol (izin verilebilir)
- Sadece yeni musteri filtresi
- Minimum siparis tutari

#### 4.3 Kademeli Affiliate Komisyon

| Tier | Min. Satis | Komisyon |
|------|-----------|----------|
| Bronze | 0 | %10 |
| Silver | 10 | %15 |
| Gold | 50 | %20 |
| Platinum | 100 | %25 |

Dinamik oranlar: Kumulatif satis sayisina gore artar.

#### 4.4 Tekrar Eden Affiliate Komisyon

- Zaman penceresi: 1-24 ay (varsayilan: 6)
- Mekanizma: Orijinal referansci sonraki alimlardan komisyon kazanir
- Limit: Maks siparis sayisi (0 = sinirsiz)
- Ayri oran: Varsayilan %5 (ilk komisyondan daha dusuk)
- Direkt affiliate varsa uygulanmaz

#### 4.5 Cift Tarafli Referans

- Referansci: Magaza kredisi (%siparis toplami)
- Musteri: Indirim kuponu (% veya sabit tutar, sureli)
- Bagimsiz acma/kapama
- Kupon tipi: Yuzde veya sabit sepet indirimi
- Gorilla Loyalty `gorilla_generate_coupon()` fonksiyonunu kullanir

#### 4.6 Affiliate Dolandiricilik Tespiti

**Haftalik otomatik tarama:**

| Tespit | Esik | Puan |
|--------|------|------|
| IP yogunlasmasi | >%60 tek IP (15+ tik) | +30 |
| Dusuk IP cesitliligi | Dusuk benzersiz IP orani (20+ tik) | +20 |
| Ani tiklama | >10 tik/saat | +25 |
| Sifir donusum | 30+ tik, %0 donusum | +25 |

Risk seviyeleri: 30+ dusuk, 50-69 orta, 70+ yuksek
Admin e-posta uyarisi suphelilerde tetiklenir.

#### 4.7 Ozel Affiliate Slug

- AJAX ile degistirme
- Validasyon: 3-20 karakter, kucuk harf, alfanumerik + tire
- Rezerve slug'lar: admin, shop, cart, checkout, ref, affiliate, gorilla, test, api
- Rate limit: Saatte maks 5 degisiklik
- Benzersizlik: Buyuk/kucuk harf duyarsiz kontrol

---

## 5. Pluginler Arasi Entegrasyon

### WP Gamify <-> Gorilla LG Koprusu

```
Gorilla LG (class-xp.php bridge)
    │
    ├── gorilla_xp_add()        → WPGamify_XP_Engine::award()
    ├── gorilla_xp_deduct()     → WPGamify_XP_Engine::deduct()
    ├── gorilla_xp_get_balance()→ WPGamify_XP_Engine::get_total_xp()
    ├── gorilla_xp_calculate_level() → WPGamify_XP_Engine::get_user_level_info()
    └── gorilla_xp_get_log()    → WPGamify_XP_Engine::get_history()
```

### Event Zinciri Ornegi (Siparis Tamamlandiginda)

```
Siparis Tamamlandi
    │
    ├── WP Gamify: Order Hook → XP Ver
    │       ├── gamify_after_xp_awarded → Gorilla LG Milestone kontrol
    │       └── gamify_level_up → Gorilla LG Badge kontrol, E-posta, SMS
    │
    ├── Gorilla LG: Tier hesapla → Tier degisimi → E-posta, SMS
    │       └── Badge kontrol → gorilla_badge_earned
    │
    └── Gorilla R&A: Affiliate komisyon → Kredi ver
            └── Tekrar eden komisyon kontrol
```

### Cift Indirim Onleme

Her iki plugin de sepette indirim uygulayabilir. Cakisma onleme:

```php
// Gorilla LG discount hook
if (!empty($GLOBALS['gorilla_discount_applied'])) return;
$GLOBALS['gorilla_discount_applied'] = true;

// WP Gamify discount hook
if (!empty($GLOBALS['gorilla_discount_applied'])) return;
```

### Guvenli Uninstall

WP Gamify uninstall edilirken Gorilla LG aktifse paylasilan tablolar korunur:

```php
$gorilla_active = in_array(
    'gorilla-loyalty-gamification/gorilla-loyalty-gamification.php',
    get_option('active_plugins', []), true
);
if ($gorilla_active) {
    // Sadece config/audit tablolarini sil, XP/level/streak tablolarini koru
}
```

---

## 6. Veritabani Semasi

### WP Gamify Tablolari (5 Tablo)

#### wp_gamify_xp_transactions
```sql
id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
user_id         BIGINT UNSIGNED NOT NULL
amount          INT NOT NULL
source          VARCHAR(50) NOT NULL
source_id       VARCHAR(100) DEFAULT NULL
campaign_mult   DECIMAL(4,2) DEFAULT 1.00
note            VARCHAR(255) DEFAULT NULL
created_at      DATETIME NOT NULL
INDEX user_created (user_id, created_at)
INDEX source_created (source, created_at)
```

#### wp_gamify_user_levels
```sql
user_id         BIGINT UNSIGNED PRIMARY KEY
current_level   INT NOT NULL DEFAULT 1
total_xp        BIGINT DEFAULT 0
rolling_xp      BIGINT DEFAULT 0
grace_until     DATETIME DEFAULT NULL
last_xp_at      DATETIME DEFAULT NULL
updated_at      DATETIME NOT NULL
INDEX current_level (current_level)
INDEX grace_until (grace_until)
```

#### wp_gamify_streaks
```sql
user_id             BIGINT UNSIGNED PRIMARY KEY
current_streak      INT DEFAULT 0
max_streak          INT DEFAULT 0
last_activity_date  DATE DEFAULT NULL
streak_xp_today     INT DEFAULT 0
updated_at          DATETIME NOT NULL
INDEX last_activity_date (last_activity_date)
```

#### wp_gamify_levels_config
```sql
id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
level_number        INT NOT NULL UNIQUE
name                VARCHAR(100) NOT NULL
xp_required         BIGINT NOT NULL
benefits            JSON NOT NULL
icon_attachment_id  BIGINT UNSIGNED DEFAULT NULL
color_hex           VARCHAR(7) DEFAULT '#6366f1'
sort_order          INT DEFAULT 0
created_at          DATETIME NOT NULL
updated_at          DATETIME NOT NULL
```

#### wp_gamify_audit_log
```sql
id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
admin_id        BIGINT UNSIGNED NOT NULL
target_user_id  BIGINT UNSIGNED NOT NULL
action          VARCHAR(50) NOT NULL
amount          INT DEFAULT NULL
before_value    VARCHAR(255) DEFAULT NULL
after_value     VARCHAR(255) DEFAULT NULL
reason          TEXT NOT NULL
created_at      DATETIME NOT NULL
INDEX target_created (target_user_id, created_at)
INDEX admin_created (admin_id, created_at)
```

### Gorilla LG Tablosu (1 Tablo)

#### wp_gorilla_credit_log
```sql
id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
user_id         BIGINT UNSIGNED NOT NULL
amount          DECIMAL(10,2) NOT NULL
balance_after   DECIMAL(10,2) NOT NULL
type            VARCHAR(30) NOT NULL
reason          VARCHAR(255) DEFAULT NULL
reference_id    VARCHAR(100) DEFAULT NULL
created_at      DATETIME NOT NULL
expires_at      DATETIME DEFAULT NULL
INDEX user_id (user_id)
INDEX type (type)
INDEX created_at (created_at)
```

### Gorilla R&A Tablosu (1 Tablo)

#### wp_gorilla_affiliate_clicks
```sql
id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
referrer_user_id    BIGINT UNSIGNED NOT NULL
referrer_code       VARCHAR(20) NOT NULL
visitor_ip          VARCHAR(45) NOT NULL
clicked_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
converted           TINYINT(1) NOT NULL DEFAULT 0
order_id            BIGINT UNSIGNED DEFAULT NULL
converted_at        DATETIME DEFAULT NULL
INDEX referrer_user_id (referrer_user_id)
INDEX referrer_code (referrer_code)
INDEX converted (converted)
INDEX clicked_at (clicked_at)
INDEX referrer_clicked (referrer_user_id, clicked_at)
```

### Kullanici Meta Anahtarlari (Onemli Olanlar)

**WP Gamify:**
- `_wpgamify_xp_expiry_YYYY-MM` - XP expiry islendi guardi
- `_wpgamify_xp_warn_YYYY-MM` - XP uyari gonderildi guardi
- `_wpgamify_first_order_xp` - Ilk siparis bonusu verildi
- `_wpgamify_profile_xp` - Profil XP verildi
- `_wpgamify_suspicious_count` - Suphe sayaci
- `_wpgamify_order_xp_lock_ORDERID` - Siparis XP kilidi

**Gorilla LG:**
- `_gorilla_store_credit` - Kredi bakiyesi
- `_gorilla_lr_tier_key` - Tier anahtari
- `_gorilla_badges` - Kazanilan rozetler (array)
- `_gorilla_birthday` - Dogum tarihi
- `_gorilla_spin_available` - Mevcut donme hakki
- `_gorilla_milestones` - Tamamlanan kilometre taslari
- `_gorilla_social_shares` - Paylasim sayilari
- `_gorilla_challenges_progress` - Gorev ilerlemesi
- `_gorilla_notifications` - Bildirimler (maks 50)
- `_gorilla_sms_phone` - Telefon numarasi
- `_gorilla_spending_cache` - Onbelleklenmis harcama

**Gorilla R&A:**
- `_gorilla_affiliate_code` - Affiliate kodu
- `_gorilla_referred_by` - Referansci user ID
- `_gorilla_affiliate_fraud_score` - Dolandiricilik puani
- `_gorilla_affiliate_fraud_level` - Risk seviyesi

---

## 7. REST API Referansi

### WP Gamify API (Namespace: gamify/v1)

| Method | Endpoint | Yetki | Aciklama |
|--------|----------|-------|----------|
| GET | `/user/stats` | Giris yapmis | XP, seviye, streak, avantajlar, kampanya |
| GET | `/user/xp-history` | Giris yapmis | Sayfalanmis XP islem gecmisi |
| GET | `/user/level` | Giris yapmis | Seviye ilerlemesi ve yol haritasi |

### Gorilla LG API (Namespace: gorilla-lg/v1)

| Method | Endpoint | Yetki | Aciklama |
|--------|----------|-------|----------|
| GET | `/me` | Kullanici | Tier, XP, badge, streak, spin ozeti |
| GET | `/tier` | Kullanici | Detayli tier bilgisi |
| GET | `/tiers` | Herkese acik | Tum tier listesi |
| GET | `/badges` | Kullanici | Kazanilan rozetler |
| GET | `/leaderboard` | Giris yapmis | Siralama (periyot/limit) |
| GET | `/milestones` | Kullanici | Milestone ilerlemesi |
| GET | `/shop` | Kullanici | XP odulleri |
| POST | `/shop/redeem` | Kullanici | XP ile odul al |
| GET | `/streak` | Kullanici | Seri bilgisi |
| GET | `/qr` | Kullanici | QR kod URL |
| POST | `/social/share` | Kullanici | Sosyal paylasim izle |
| GET | `/settings` | Herkese acik | Ozellik bayraklari |
| GET | `/admin/stats` | Admin | Dashboard istatistikleri |
| GET | `/admin/user/{id}` | Admin | Kullanici sadakat verileri |
| GET | `/credit` | Kullanici | Kredi bakiyesi |
| GET | `/credit/log` | Kullanici | Kredi islem gecmisi |

### Gorilla R&A API (Namespace: gorilla-lr/v1)

| Method | Endpoint | Yetki | Aciklama |
|--------|----------|-------|----------|
| GET | `/referrals` | Giris yapmis | Kullanicinin referans basvurulari |
| GET | `/affiliate` | Giris yapmis | Affiliate bilgileri ve istatistikleri |
| GET | `/affiliate/stats` | Giris yapmis | Detayli affiliate istatistikleri |

**Toplam: 20+ REST API endpoint**

---

## 8. Guvenlik Ozeti

### Uygulanan Guvenlik Katmanlari

| Katman | Yontem | Plugin |
|--------|--------|--------|
| **XSS Onleme** | `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` | Tumu |
| **CSRF Korunmasi** | `wp_nonce_field()`, `check_admin_referer()`, `check_ajax_referer()` | Tumu |
| **SQL Injection** | `$wpdb->prepare()` tum sorgularda | Tumu |
| **Race Condition** | MySQL `GET_LOCK`, `INSERT IGNORE`, `START TRANSACTION` | WP Gamify, Gorilla LG |
| **TOCTOU** | Atomik INSERT IGNORE guardlari | WP Gamify |
| **Yetki Kontrolu** | `manage_woocommerce` + `is_user_logged_in()` | Tumu |
| **Rate Limiting** | Transient tabanli bekleme sureleri | Gorilla R&A, Gorilla LG |
| **Veri Dogrulama** | `filter_var()`, `sanitize_*()`, domain whitelist | Tumu |
| **Hassas Veri** | AES-256-CBC sifreleme (SMS kimlik bilgileri) | Gorilla LG |
| **Dolandiricilik** | Otomatik haftalik tarama (4 heuristik) | Gorilla R&A |
| **Cift Indirim** | `$GLOBALS` bayrak kontrolu | WP Gamify + Gorilla LG |
| **Autoloader** | Sinif adi validasyonu (regex) | WP Gamify |

### Son Duzeltmeler (v2.0.0 Guncelleme)

- 6 Kritik, 8 Yuksek, 7 Orta, 4 Dusuk sorun giderildi
- Toplam 22 dosyada 917 ekleme, 582 silme
- 38 sorun 10 ajanli kapsamli denetimden cozuldu
- 16 race condition, mantik hatasi ve sertlestirme sorunu giderildi

---

## 9. GDPR/KVKK Uyumlulugu

### WP Gamify

**Veri Ihrac:**
- XP islem gecmisi (tum kayitlar)
- Seviye bilgileri
- Streak verileri
- Denetim kaydi girisleri

**Veri Silme:**
- Tum XP islemleri
- Seviye kayitlari
- Streak verileri
- `_wpgamify_*` ve `wpgamify_*` meta anahtarlari
- Onbellek anahtarlari

### Gorilla LG

**Veri Ihrac:**
- Kredi bakiyesi ve islem gecmisi (sayfalanmis)
- Dogum gunu, tier gecmisi, rozetler
- Spin gecmisi, milestone ilerlemesi
- Sosyal paylasim sayilari
- Affiliate tiklama gecmisi

**Veri Silme:**
- Tum kisisel gamification verileri
- Islem kayitlari muhasebe icin korunur
- Kullanici gizlilik moduna saygi gosterir

### Gorilla R&A

**Veri Ihrac:**
- Affiliate kodu ve tiklama gecmisi (son 100)
- Referans basvurulari (son 100)
- Referansci bilgisi
- Dolandiricilik tespit verileri

**Veri Silme:**
- Affiliate kodu silinir
- Tiklama IP'leri anonimlestirilir ('0.0.0.0')
- Referans postlari kalici silinir
- Dolandiricilik meta verileri silinir
- Gizlilik politikasi metni otomatik eklenir

---

## 10. Performans Optimizasyonlari

| Optimizasyon | Yontem | Plugin |
|--------------|--------|--------|
| Harcama onbellegi | User meta + 1 saatlik TTL | Gorilla LG |
| Tier onbellegi | PHP static array (istek bazli) | Gorilla LG |
| Dashboard istatistikleri | Transient 1 saatlik TTL | Gorilla LG |
| Leaderboard | 5 dakikalik transient cache | Gorilla LG |
| Tablo varlik kontrolu | Static cache array | Tumu |
| Kampanya | Istek bazli static cache | WP Gamify |
| Badge kontrol | Static cache + batch meta yukle | Gorilla LG |
| Admin N+1 | Toplu SQL sorgusu (HPOS uyumlu) | Gorilla LG |
| Kredi expiry | LIMIT/OFFSET batch paginasyonu | Gorilla LG |
| Transient temizligi | Saatlik cron | WP Gamify |
| Stale kilit temizligi | Gunluk cron (7+ gun) | WP Gamify |
| Grace N+1 | `update_meta_cache()` batch yukleme | Gorilla LG |

---

## 11. Cron Gorevleri

### WP Gamify

| Hook | Siklik | Gorev |
|------|--------|-------|
| `wpgamify_hourly_cache` | Saatlik | Suresi dolmus transient'lari sil |
| `wpgamify_daily_maintenance` | Gunluk | streak_xp_today sifirla, kirik streak kontrol, XP expiry uyar + dusur, grace expiry isle, stale kilit temizle |

### Gorilla LG

| Hook | Siklik | Gorev |
|------|--------|-------|
| `gorilla_lr_daily_tier_check` | Gunluk | Tier grace kontrol, churn tespit, smart coupon olustur |
| `gorilla_sc_daily_check` | Gunluk | Kredi expiry uyari + son kullanma |
| `gorilla_meta_cleanup_weekly` | Haftalik | 90+ gunluk dated meta temizligi |

### Gorilla R&A

| Hook | Siklik | Gorev |
|------|--------|-------|
| Dolandiricilik taramasi | Haftalik | Affiliate tiklama pattern analizi |

---

## 12. E-posta Bildirimleri

### Gorilla LG WC_Email Siniflari (12 Adet)

| Sinif | Tetik | Alici |
|-------|-------|-------|
| Tier Upgrade | Tier yukselme | Musteri |
| Tier Grace Warning | Tier dusme riski | Musteri |
| Tier Downgrade | Tier dusme | Musteri |
| Level Up | XP seviye atlama | Musteri |
| Credit Expiry Warning | Kredi son kullanma yaklasma | Musteri |
| Birthday | Dogum gunu odulu | Musteri |
| Anniversary | Yildonumu odulu | Musteri |
| Churn Reengagement | Yeniden etkilesim bonusu | Musteri |
| XP Expiry Warning | XP son kullanma yaklasma | Musteri |
| Milestone Reached | Kilometre tasi tamamlama | Musteri |
| Badge Earned | Rozet kazanma | Musteri |
| Smart Coupon | Kisisellestirilmis kupon | Musteri |

### Gorilla R&A E-postalari (5 Adet)

| Tip | Tetik | Alici |
|-----|-------|-------|
| Referral Approved | Admin onayi | Musteri |
| Referral Rejected | Admin reddi | Musteri |
| New Referral | Basvuru yapildi | Admin |
| Affiliate Earned | Komisyon kredi | Affiliate |
| Dual Referral Coupon | Cift tarafli odul | Musteri |

**Toplam: 17 e-posta bildirimi**

Tum e-postalar:
- HTML + duz metin varyanti
- WooCommerce Ayarlar > E-postalar'dan ozellestirilebilir
- Tema dosya override destegi
- Yerel tarih/para birimi formatlama

---

## 13. SMS Bildirimleri

**Modul:** Gorilla LG `class-sms.php`
**Saglayici:** Twilio

**Guvenlik:**
- AES-256-CBC sifreleme (her kimlik icin rastgele IV)
- Rate limiting: Alici basina saatte maks 10 SMS
- E.164 uluslararasi format dogrulama

**Tetik Olaylari:**
- Tier yukselme
- Kredi kazanma
- Spin kazanma
- Seviye atlama
- Rozet kazanma

---

## 14. Admin Paneli Ozellikleri

### WP Gamify Admin

| Sayfa | Ozellik |
|-------|---------|
| Dashboard | Istatistik kartlari, dagılım grafikleri, hizli islemler |
| XP Ayarlari | Tum kaynak ayarlari, streak, anti-abuse, expiry |
| Seviye Yonetimi | CRUD seviyeler (surukleme siralamalı, avantajlar, renkler, ikonlar) |
| Manuel XP | Kullanici ara, XP ayarla (neden + denetim kaydi) |
| Denetim Kaydi | Admin islem gecmisi |
| Kurulum Sihirbazi | Ilk kurulum rehberi |

### Gorilla LG Admin

| Sayfa | Ozellik |
|-------|---------|
| Dashboard | Istatistik kartlari (kullanici, tier, XP, kredi) |
| Ayarlar | 10+ bolum, 40+ ayar |
| Kredi Yonetimi | Manuel kredi verme/alma |
| Alt menu | Ayarlar + Kredi Yonetimi |

### Gorilla R&A Admin

| Sayfa | Ozellik |
|-------|---------|
| Dashboard | Istatistikler, top affiliate'ler, son siparisler |
| Ayarlar | Referans, affiliate, tier, tekrar eden, dolandiricilik |
| Referans CPT | Video inceleme, onay/red, toplu islem |

---

## 15. Musteri On Yuzu (Frontend)

### Hesabim Sayfalari

**Gorilla Loyalty Sekmesi** (`/my-account/gorilla-loyalty`):
- Tier karti (mevcut tier, ilerleme cubugu, avantajlar)
- XP ozeti (seviye, toplam XP, siradaki seviye)
- Streak bilgisi
- Rozet koleksiyonu grid'i
- Spin wheel arayuzu
- Milestone ilerleme cubugu
- Leaderboard tablosu
- Points shop odul listesi
- Sosyal paylasim butonlari
- QR kod gosterimi/indirme
- Challenge (gorev) listesi
- Kredi bakiyesi ve islem gecmisi
- Bildirimler
- Ayarlar (dogum gunu, telefon)

**Gorilla Referral Sekmesi** (`/my-account/gorilla-referral`):
- Kredi bakiyesi hero karti
- Affiliate link karti (kopyala butonu, ozel slug)
- Affiliate istatistikleri (tik, donusum, kazanc)
- Tier ilerleme gostergesi (kademeli ise)
- QR kod (opsiyonel)
- Video referans nasil calisir rehberi (5 adim)
- Referans gonderim formu
- Referans gecmisi (durum rozetleri)
- Kredi log tablosu

### Frontend Varliklari

**CSS (toplam ~1,470 satir):**
- `gorilla-base.css` (363 satir) - Paylasilan UI: toast, tab, grid, animasyon
- `loyalty.css` (395 satir) - Tier kartlari, rozetler, spin wheel, leaderboard
- `store-credit.css` (151 satir) - Kredi gosterimi, checkout UI
- `referral.css` (11 satir) - Minimal (kalitimli)

**JavaScript (toplam ~700 satir):**
- `gorilla-base.js` (135 satir) - Toast, AJAX yardimcilari, akordeon
- `loyalty.js` (413 satir) - Spin animasyonu, shop, QR indirme, paylasim
- `store-credit.js` (145 satir) - Kredi toggle, sepete uygula
- `referral.js` (80 satir) - Affiliate kopyalama

---

## 16. WP-CLI Komutlari

**Gorilla LG CLI** (`wp gorilla-lg`):

| Komut | Arguman | Aciklama |
|-------|---------|----------|
| `tier recalculate-all` | `[--dry-run]` | Tum tier'leri yeniden hesapla |
| `tier list` | - | Tier tanimlarini goster |
| `xp add <user_id> <amount>` | `[--reason=]` | Kullaniciya XP ver |
| `xp get <user_id>` | - | XP bakiyesini goster |
| `xp export` | `[--format=csv\|json\|table] [--limit=] [--user_id=]` | XP verisini ihrac et |

---

## 17. Eksikler ve Gelecek Planlar

### WP Gamify

| Eksik | Oncelik | Not |
|-------|---------|-----|
| Kampanya UI (admin) | Yuksek | Faz 3'te planlandi |
| Badge implementasyonu | Orta | Interface mevcut, implementasyon yok |
| Quest implementasyonu | Orta | Interface mevcut, implementasyon yok |
| Toplu XP ayarlama | Dusuk | Manuel XP var, toplu yok |
| Leaderboard (yerlesik) | Orta | Gorilla LG uzerinden saglanir |
| Seviye atlama animasyonu | Dusuk | Frontend animasyon yok |
| Mobil API | Dusuk | REST var ama mobil optimize degil |
| API rate limiting | Orta | Per-IP/istek sinirlaması yok |

### Gorilla LG

| Eksik | Oncelik | Not |
|-------|---------|-----|
| Shortcode destegi | Orta | Sayfa ici tier/XP gosterimi icin |
| Urun oneri motoru | Dusuk | Tier bazli urun onerisi |
| Turnuva sistemi | Dusuk | Sezonsal leaderboard yarismasi |
| Gelismis analitik | Orta | Grafik/chart kutuphanesi |
| Toplu kredi import | Orta | Legacy sistem gocusu icin |
| White-label | Dusuk | Plugin markalamasi sabit |

### Gorilla R&A

| Eksik | Oncelik | Not |
|-------|---------|-----|
| CSV ihrac | Orta | Muhasebe icin toplu veri cikarimi |
| Webhook destegi | Orta | 3. parti entegrasyonlar icin |
| Affiliate odeme takibi | Yuksek | Odeme talebi/durum sistemi yok |
| A/B testi | Dusuk | Referans etkinligi varyant takibi |
| Affiliate askiya alma | Orta | Admin toggle ile devre disi birakma |
| Komisyon yeniden hesaplama | Dusuk | Geriye donuk kademeli oran hesabi |

### Faz 3 Planlari

1. **Kampanya Tablosu:** wp_options'tan ozel tabloya goc, tam CRUD UI, tekrar eden kampanyalar
2. **Badge Sistemi:** WP Gamify icinde tam implementasyon
3. **Quest Sistemi:** WP Gamify icinde tam implementasyon
4. **Gelismis Raporlama:** Admin dashboard'da grafik/chart
5. **Webhook Sistemi:** Dis entegrasyonlar icin outbound webhook'lar
6. **Affiliate Odeme:** Odeme talebi ve takip sistemi

---

## 18. Dosya Yapisi

```
gorilla-loyalty-referral-v2/
│
├── wp-gamify/                                    [XP Motoru - 35 dosya]
│   ├── wp-gamify.php                             Bootstrap (301 satir)
│   ├── uninstall.php                             Guvenli kaldirim
│   ├── includes/
│   │   ├── class-xp-engine.php                   XP motoru
│   │   ├── class-level-manager.php               Seviye yonetimi
│   │   ├── class-streak-manager.php              Seri takibi
│   │   ├── class-anti-abuse.php                  Kotuye kullanim onleme
│   │   ├── class-campaign-manager.php            Kampanya carpan
│   │   ├── class-xp-expiry.php                   XP son kullanma
│   │   ├── class-settings.php                    Ayar yonetimi
│   │   ├── class-activator.php                   DB tablo olusturma
│   │   ├── class-deactivator.php                 Temizlik
│   │   ├── class-migrator.php                    DB goc
│   │   ├── class-gdpr.php                        GDPR uyumu
│   │   └── class-loader.php                      Sinif yukleyici
│   ├── hooks/
│   │   ├── class-order-hooks.php                 Siparis XP
│   │   ├── class-login-hooks.php                 Giris/kayit XP
│   │   ├── class-review-hooks.php                Inceleme XP
│   │   ├── class-discount-hooks.php              Seviye indirimi
│   │   └── class-profile-hooks.php               Profil XP
│   ├── admin/
│   │   ├── class-admin.php                       Admin UI
│   │   ├── class-setup-wizard.php                Kurulum sihirbazi
│   │   └── views/                                Admin sablonlari
│   ├── frontend/
│   │   ├── class-frontend.php                    On yuz
│   │   └── views/                                On yuz sablonlari
│   ├── api/
│   │   ├── class-api-register.php                API kaydi
│   │   └── endpoints/                            3 endpoint sinifi
│   └── interfaces/                               3 interface tanimlari
│
├── gorilla-loyalty-gamification/                 [Sadakat Sistemi - 15 dosya]
│   ├── gorilla-loyalty-gamification.php          Bootstrap (903 satir)
│   ├── uninstall.php                             Guvenli kaldirim
│   ├── includes/
│   │   ├── class-loyalty.php                     Tier, badge, spin, shop (1,314 satir)
│   │   ├── class-store-credit.php                Magaza kredisi (500 satir)
│   │   ├── class-xp.php                          WP Gamify koprusu (339 satir)
│   │   ├── class-challenges.php                  Gorev sistemi (390 satir)
│   │   ├── class-frontend.php                    On yuz (993 satir)
│   │   ├── class-admin.php                       Admin (1,091 satir)
│   │   ├── class-settings.php                    Ayarlar (1,141 satir)
│   │   ├── class-rest-api.php                    REST API (571 satir)
│   │   ├── class-emails.php                      E-posta (264 satir)
│   │   ├── class-wc-emails.php                   WC E-posta (735 satir)
│   │   ├── class-gdpr.php                        GDPR (473 satir)
│   │   ├── class-sms.php                         SMS (272 satir)
│   │   ├── class-coupon-generator.php            Kupon (106 satir)
│   │   ├── class-migration-to-gamify.php         Goc araci (293 satir)
│   │   └── class-cli.php                         WP-CLI (227 satir)
│   ├── assets/
│   │   ├── css/                                  3 CSS dosyasi (909 satir)
│   │   └── js/                                   3 JS dosyasi (693 satir)
│   ├── templates/emails/                         E-posta sablonlari
│   └── languages/                                Ceviri dosyalari
│
├── gorilla-referral-affiliate/                   [Referans Sistemi - 12 dosya]
│   ├── gorilla-referral-affiliate.php            Bootstrap (280 satir)
│   ├── uninstall.php                             Guvenli kaldirim (97 satir)
│   ├── includes/
│   │   ├── class-referral.php                    Video referans CPT (743 satir)
│   │   ├── class-affiliate.php                   Affiliate takip (949 satir)
│   │   ├── class-admin.php                       Admin (194 satir)
│   │   ├── class-frontend.php                    On yuz (489 satir)
│   │   ├── class-settings.php                    Ayarlar (306 satir)
│   │   ├── class-emails.php                      E-posta (203 satir)
│   │   ├── class-wc-emails.php                   WC E-posta (336 satir)
│   │   ├── class-rest-api.php                    REST API (157 satir)
│   │   ├── class-gdpr.php                        GDPR (224 satir)
│   │   └── emails/
│   │       └── class-gorilla-ra-email-base.php   E-posta temeli (109 satir)
│   ├── assets/
│   │   ├── css/referral.css                      Minimal CSS
│   │   └── js/referral.js                        Affiliate kopyalama
│   ├── templates/emails/                         E-posta sablonlari
│   └── languages/                                Ceviri dosyalari
```

---

## 19. Kurulum ve Aktivasyon

### Kurulum Sirasi

1. **WP Gamify** yukle ve etkinlestir
   - 5 DB tablosu olusturulur
   - 8 varsayilan seviye eklenir
   - Cron gorevleri ayarlanir
2. **Gorilla Loyalty & Gamification** yukle ve etkinlestir
   - 1 DB tablosu olusturulur (credit_log)
   - 40+ varsayilan secenek ayarlanir
   - Legacy XP verisi varsa WP Gamify'a goc edilir
3. **Gorilla Referral & Affiliate** yukle ve etkinlestir
   - 1 DB tablosu olusturulur (affiliate_clicks)
   - 20+ varsayilan secenek ayarlanir
   - Permalink yapisinı yenilemek icin bayrak ayarlanir

### Kaldirim (Uninstall)

Her plugin kendi `uninstall.php` dosyasina sahiptir:
- **Veri koruma secenegi:** `keep_data_on_uninstall` ayari ile kontrol
- **Cross-plugin guvenlik:** Paylasilan tablolar diger plugin aktifken silinmez
- **Kilit temizligi:** Transient ve lock anahtarlari her zaman temizlenir

---

## 20. Ozet Istatistikler

| Metrik | WP Gamify | Gorilla LG | Gorilla R&A | Toplam |
|--------|-----------|------------|-------------|--------|
| PHP Dosya | 35 | 15 | 12 | **62** |
| PHP Satir | ~5,000 | ~8,700 | ~4,088 | **~17,800** |
| CSS/JS Satir | ~200 | ~1,470 | ~100 | **~1,770** |
| DB Tablosu | 5 | 1 | 1 | **7** |
| REST Endpoint | 3 | 17+ | 3 | **23+** |
| E-posta | - | 12 | 5 | **17** |
| Admin Sayfa | 6 | 3 | 3 | **12** |
| Cron Gorevi | 2 | 3 | 1 | **6** |
| Hook (Action) | 15+ | 6 | 3 | **24+** |
| Hook (Filter) | 5+ | 2 | 1 | **8+** |
| Guvenlik Guard | 8+ | 10+ | 6+ | **24+** |
| WP-CLI Komut | - | 7 | - | **7** |

### Ozellik Durumu

| Ozellik | Durum |
|---------|-------|
| XP/Seviye sistemi | Uretimde |
| Streak (seri) | Uretimde |
| Sadakat tier'leri | Uretimde |
| Magaza kredisi | Uretimde |
| Rozetler | Uretimde |
| Spin wheel | Uretimde |
| Milestone'lar | Uretimde |
| Points shop | Uretimde |
| Leaderboard | Uretimde |
| Sosyal paylasim | Uretimde |
| QR kod | Uretimde |
| Challenge'lar | Uretimde |
| Video referans | Uretimde |
| Affiliate | Uretimde |
| Kademeli komisyon | Uretimde |
| Tekrar eden komisyon | Uretimde |
| Cift tarafli referans | Uretimde |
| Dolandiricilik tespiti | Uretimde |
| Churn prediction | Uretimde |
| Smart coupon | Uretimde |
| SMS bildirimler | Uretimde |
| 17 e-posta bildirimi | Uretimde |
| GDPR/KVKK | Uretimde |
| HPOS uyumu | Uretimde |
| REST API (23+ endpoint) | Uretimde |
| WP-CLI | Uretimde |
| Kampanya UI | Faz 3 |
| Badge engine (WP Gamify) | Faz 3 |
| Quest engine (WP Gamify) | Faz 3 |
| Webhook sistemi | Faz 3 |
| Gelismis raporlama | Faz 3 |

---

**Son Guncelleme:** 2 Mart 2026
**Commit:** `cbe335d` (master branch)
**Durum:** Production-ready, tum kritik guvenlik yamalari uygulanmis
