# WP Gamify — Mimari Plan & Teknik Tasarım Dokümanı

**WordPress + WooCommerce Custom Plugin**  
**PHP 8.x + WordPress 6.x**  
Versiyon 1.0 · Şubat 2026

---

## İçindekiler

1. [Genel Prensipler](#1-genel-prensipler)
2. [Plugin Dosya Yapısı](#2-plugin-dosya-yapısı)
3. [Veritabanı Tabloları](#3-veritabanı-tabloları)
4. [Dinamik Yapı Kuralları](#4-dinamik-yapı-kuralları)
5. [Faz 1 Kapsam Listesi](#5-faz-1-kapsam-listesi)
6. [Teknik Kararlar](#6-teknik-kararlar)
7. [Performans & Cache Stratejisi](#7-performans--cache-stratejisi)
8. [Güvenlik & Anti-Abuse](#8-güvenlik--anti-abuse)
9. [Genişletilebilirlik](#9-genişletilebilirlik)
10. [Onaylanan Teknik Tavsiyeler](#10-onaylanan-teknik-tavsiyeler)
11. [Faz Yol Haritası](#11-faz-yol-haritası)

---

## 1. Genel Prensipler

- **Tüm değerler admin'den dinamik** — Hiçbir sayı, isim, görsel veya eşik değeri kod içine hard-code edilmez. Tamamı veritabanından okunur.
- **XP asla kaybolmaz** — Level, rozet veya kademe silinse bile müşterinin XP'si korunur. Silinen yapı, müşteriyi mevcut config'e göre yeniden hesaplar.
- **Source of truth her zaman XP + config** — `current_level` gibi cache kolonları performans içindir, asıl hesaplama her zaman XP + levels_config üzerinden yapılır.
- **Transaction log asla silinmez** — İptal/iade durumunda negatif kayıt eklenir, DELETE yapılmaz.
- **Timezone farkındalığı** — Tüm tarih işlemleri `wp_timezone()` ile yapılır. Streak sıfırlama, gece yarısı kontrolü timezone'a göre çalışır.

---

## 2. Plugin Dosya Yapısı

```
wp-gamify/
├── wp-gamify.php                        # Ana plugin dosyası, bootstrap
├── uninstall.php                        # Temiz kaldırma (tablolar + options)
│
├── includes/                            # Core PHP sınıfları
│   ├── class-activator.php              # Kurulum, DB tabloları oluşturma, seed data
│   ├── class-deactivator.php            # Deaktivasyon
│   ├── class-loader.php                 # Hook yönetimi
│   ├── class-xp-engine.php              # XP verme, hesaplama, limit kontrolü
│   ├── class-level-manager.php          # Level hesaplama, atlama, faydalar
│   ├── class-streak-manager.php         # Streak takibi, XP çarpanı
│   ├── class-campaign-manager.php       # Aktif kampanya kontrolü
│   ├── class-anti-abuse.php             # Günlük tavan, rate limit
│   ├── class-settings.php               # Tek JSON settings yönetimi
│   └── class-migrator.php               # DB versiyon & migration yönetimi
│
├── interfaces/                          # PHP Interface'ler (genişletilebilirlik)
│   ├── interface-reward-source.php      # Her XP kaynağı bu interface'i implement eder
│   ├── interface-badge-evaluator.php    # Rozet koşul değerleyici
│   └── interface-quest-checker.php      # Görev ilerleme kontrolcüsü
│
├── hooks/                               # WooCommerce & WP event listener'ları
│   ├── class-order-hooks.php            # order_completed → XP ver
│   ├── class-login-hooks.php            # wp_login → streak güncelle
│   ├── class-review-hooks.php           # review_approved → XP ver
│   └── class-discount-hooks.php         # Level indirimi & kargo uygulama
│
├── api/                                 # REST API
│   ├── class-api-register.php           # Endpoint kayıt (gamify/v1)
│   └── endpoints/
│       ├── class-endpoint-stats.php     # GET /user/stats
│       ├── class-endpoint-history.php   # GET /user/xp-history
│       └── class-endpoint-level.php     # GET /user/level
│
├── admin/                               # Admin panel
│   ├── class-admin.php                  # Admin menü kaydı
│   ├── class-setup-wizard.php           # İlk kurulum wizard'ı
│   ├── views/
│   │   ├── dashboard.php                # Ana özet sayfası (kartlar + grafikler)
│   │   ├── xp-settings.php              # XP kaynak ayarları
│   │   ├── levels.php                   # Level CRUD yönetimi
│   │   ├── badge-tiers.php              # Rozet kademe yönetimi (Faz 2)
│   │   ├── badges.php                   # Rozet CRUD (Faz 2)
│   │   ├── manual-xp.php                # Manuel XP + audit log
│   │   └── audit-log.php                # Audit log görüntüleme
│   └── assets/
│       ├── admin.css                    # Modern özel CSS
│       └── admin.js                     # Admin interaktivite, Media Library
│
├── frontend/                            # Müşteri tarafı
│   ├── class-frontend.php               # My Account sekme kaydı
│   ├── views/
│   │   └── my-account-dashboard.php     # XP/Level/Streak paneli
│   └── assets/
│       ├── dashboard.css                # Modern kart tasarımı, TCG estetiği
│       └── dashboard.js                 # Animasyonlar, sayaçlar (sadece dashboard'da yüklenir)
│
└── languages/                           # i18n (TR/EN)
    ├── wp-gamify-tr_TR.po
    └── wp-gamify-tr_TR.mo
```

---

## 3. Veritabanı Tabloları

### 3.1 Faz 1 Tabloları

```sql
-- Tüm XP hareketleri (ASLA DELETE YAPILMAZ, iade = negatif kayıt)
wp_gamify_xp_transactions
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
  user_id         BIGINT UNSIGNED NOT NULL
  amount          INT NOT NULL              -- Negatif olabilir (iade)
  source          VARCHAR(50) NOT NULL      -- 'order', 'review', 'streak', 'login', 'manual', 'birthday' ...
  source_id       VARCHAR(100)              -- order_id, review_id vs.
  campaign_mult   DECIMAL(4,2) DEFAULT 1.00
  note            VARCHAR(255)              -- Admin notu veya otomatik açıklama
  created_at      DATETIME NOT NULL
  INDEX (user_id, created_at)
  INDEX (source, created_at)

-- Kullanıcı level/XP özet (cache, source of truth = xp_transactions + levels_config)
wp_gamify_user_levels
  user_id         BIGINT UNSIGNED PRIMARY KEY
  current_level   INT NOT NULL DEFAULT 1   -- CACHE: her zaman hesaplanabilir
  total_xp        BIGINT DEFAULT 0         -- Tüm zamanlar toplamı
  rolling_xp      BIGINT DEFAULT 0         -- Kayan pencere XP (cron ile güncellenir)
  grace_until     DATETIME NULL            -- Yumuşak iniş bitiş tarihi
  last_xp_at      DATETIME NULL
  updated_at      DATETIME NOT NULL

-- Streak takibi
wp_gamify_streaks
  user_id             BIGINT UNSIGNED PRIMARY KEY
  current_streak      INT DEFAULT 0
  max_streak          INT DEFAULT 0
  last_activity_date  DATE NULL
  streak_xp_today     INT DEFAULT 0         -- Bugün streak'ten kazanılan XP
  updated_at          DATETIME NOT NULL

-- Level tanımları (dinamik, admin'den tam CRUD)
wp_gamify_levels_config
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
  level_number         INT NOT NULL UNIQUE
  name                 VARCHAR(100) NOT NULL
  xp_required          BIGINT NOT NULL
  benefits             JSON NOT NULL         -- indirim%, kargo, taksit, erken_erisim ...
  icon_attachment_id   BIGINT UNSIGNED NULL  -- WordPress Media Library ID
  color_hex            VARCHAR(7) DEFAULT '#6366f1'
  sort_order           INT DEFAULT 0
  created_at           DATETIME NOT NULL
  updated_at           DATETIME NOT NULL

-- Admin işlem logu (asla silinemez)
wp_gamify_audit_log
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
  admin_id         BIGINT UNSIGNED NOT NULL
  target_user_id   BIGINT UNSIGNED NOT NULL
  action           VARCHAR(50) NOT NULL    -- 'xp_add', 'xp_remove', 'level_set', 'badge_give' ...
  amount           INT NULL
  before_value     VARCHAR(255) NULL       -- İşlem öncesi durum
  after_value      VARCHAR(255) NULL       -- İşlem sonrası durum
  reason           TEXT NOT NULL           -- Zorunlu açıklama
  created_at       DATETIME NOT NULL
  INDEX (target_user_id, created_at)
  INDEX (admin_id, created_at)
```

### 3.2 Faz 2 Tabloları (Rozet & Görev)

```sql
-- Rozet kademeleri (dinamik: bronz/gümüş/altın varsayılan ama değiştirilebilir)
wp_gamify_badge_tiers
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
  name                 VARCHAR(100) NOT NULL   -- 'Bronz', 'Gümüş', 'Altın' (değiştirilebilir)
  sort_order           INT DEFAULT 0           -- Sıralama (sürükle-bırak)
  icon_attachment_id   BIGINT UNSIGNED NULL    -- Genel tier ikonu (fallback)
  color_hex            VARCHAR(7) NOT NULL     -- Bronz=#cd7f32, Gümüş=#c0c0c0, Altın=#ffd700
  created_at           DATETIME NOT NULL
  updated_at           DATETIME NOT NULL

-- Rozet tanımları
wp_gamify_badges
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
  name                 VARCHAR(100) NOT NULL
  description          TEXT                    -- "Nasıl kazanılır" metni
  category             VARCHAR(50) NOT NULL    -- 'shopping', 'community', 'loyalty', 'hidden'
  criteria             JSON NOT NULL           -- Koşul tanımı
  is_hidden            TINYINT(1) DEFAULT 0    -- ??? olarak görünür
  is_active            TINYINT(1) DEFAULT 1
  sort_order           INT DEFAULT 0
  created_at           DATETIME NOT NULL
  updated_at           DATETIME NOT NULL

-- Her rozet x her kademe için eşik, XP ödülü ve özel görsel
wp_gamify_badge_tier_config
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
  badge_id             INT UNSIGNED NOT NULL
  tier_id              INT UNSIGNED NOT NULL
  icon_attachment_id   BIGINT UNSIGNED NULL    -- Rozet+kademe'ye özel görsel (yoksa tier fallback)
  xp_threshold         INT NOT NULL            -- Bu kademeye ulaşmak için gereken değer
  xp_reward            INT NOT NULL            -- Kazanınca verilen XP
  sort_order           INT DEFAULT 0
  UNIQUE KEY (badge_id, tier_id)
  FOREIGN KEY (badge_id) REFERENCES wp_gamify_badges(id)
  FOREIGN KEY (tier_id) REFERENCES wp_gamify_badge_tiers(id)

-- Müşterinin kazandığı rozetler
wp_gamify_user_badges
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
  user_id     BIGINT UNSIGNED NOT NULL
  badge_id    INT UNSIGNED NOT NULL
  tier_id     INT UNSIGNED NOT NULL
  earned_at   DATETIME NOT NULL
  xp_awarded  INT NOT NULL
  INDEX (user_id, badge_id)

-- Görev tanımları
wp_gamify_quests
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
  title        VARCHAR(200) NOT NULL
  description  TEXT
  type         VARCHAR(50) NOT NULL    -- 'daily', 'weekly', 'monthly', 'event', 'product'
  criteria     JSON NOT NULL
  xp_reward    INT NOT NULL
  starts_at    DATETIME NULL
  ends_at      DATETIME NULL
  is_active    TINYINT(1) DEFAULT 1
  sort_order   INT DEFAULT 0
  created_at   DATETIME NOT NULL
  updated_at   DATETIME NOT NULL

-- Müşterinin görev ilerlemesi
wp_gamify_quest_progress
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
  user_id      BIGINT UNSIGNED NOT NULL
  quest_id     INT UNSIGNED NOT NULL
  progress     INT DEFAULT 0
  completed_at DATETIME NULL
  period_key   VARCHAR(20) NOT NULL    -- '2026-02-24' (daily), '2026-W08' (weekly), '2026-02' (monthly)
  UNIQUE KEY (user_id, quest_id, period_key)
```

### 3.3 Sonraki Fazların Tabloları (Referans için)

Faz 3+: `wp_gamify_campaigns`, `wp_gamify_referrals`, `wp_gamify_influencers`, `wp_gamify_balances`, `wp_gamify_seasons`, `wp_gamify_card_sets`, `wp_gamify_cards`, `wp_gamify_user_cards`, `wp_gamify_packs`, `wp_gamify_trades`, `wp_gamify_quiz_questions`, `wp_gamify_notifications`, `wp_gamify_email_templates`, `wp_gamify_email_log` ve diğerleri — her faz başında kendi migration'ı ile eklenir.

---

## 4. Dinamik Yapı Kuralları

### 4.1 Level Sistemi

- 8 varsayılan level **sadece seed data** — kurulumda yüklenir, sonrası tamamen admin kontrolünde
- Level sayısının alt veya üst sınırı yoktur (2 de olur, 20 de)
- Admin level ekler, düzenler, siler, sırasını değiştirir
- Level silindiğinde **XP kaybolmaz** — müşteri mevcut XP'sine göre yeni config'de otomatik yeniden hesaplanır
- Level adı, görseli (Media Library), rengi düzenlenebilir
- **Silme uyarısı:** "Bu levelde X müşteri var, silmek istediğinize emin misiniz?"
- `current_level` DB kolonu **cache**'dir — source of truth her zaman `XP + levels_config`

### 4.2 Rozet Kademeleri

- Varsayılan: Bronz / Gümüş / Altın (seed data)
- Kademe adı, görseli, rengi, sırası tamamen değiştirilebilir
- Yeni kademe eklenebilir, mevcut kademe silinebilir
- Her rozet için her kademeye **özel görsel** tanımlanabilir (tanımlanmazsa tier'ın genel görseli fallback)
- Kademe silinirse o kademede rozet alan müşterilerin rozetleri **korunur**, sadece kademe etiketi değişir

### 4.3 Görsel Yönetimi

- Tüm görseller (level ikonu, rozet görseli, kart görseli) **WordPress Media Library** üzerinden yönetilir
- DB'de görsel path değil **attachment_id** saklanır
- `wp_get_attachment_image_url($id, 'thumbnail')` ile çekilir
- Görsel güncellendiğinde otomatik yansır, broken link oluşmaz

---

## 5. Faz 1 Kapsam Listesi

### ✅ Dahil

**XP Motoru**
- Sipariş tamamlanınca XP (sabit + harcama bazlı, kategori çarpanı)
- İlk alışveriş bonusu (tek seferlik)
- Yorum onayında XP (min. karakter kontrolü)
- Özel günler: ilk üyelik, doğum günü, yıldönümü
- Günlük XP tavanı (varsayılan 500 XP, admin'den ayarlanabilir)
- İade/iptal → negatif XP kaydı
- Kampanya çarpanı motoru (Faz 3'te UI gelir, motor şimdi hazır)

**Streak Sistemi**
- `wp_login` hook'unda günlük giriş tespiti
- Katlayan XP (Gün1: 2XP → x2 çarpan → Gün7: 64XP tavan)
- 1 gün tolerans seçeneği (admin toggle)
- 7 günde döngüsel sıfırlama (opsiyonel toggle)

**Level Sistemi**
- XP → Level hesaplama (rolling window veya all-time, admin seçer)
- Level atlama tespiti + hook sistemi
- Yumuşak iniş: 2 haftalık grace period (admin'den ayarlanabilir)
- Level faydaları: indirim%, kargo, taksit, erken erişim

**WooCommerce Entegrasyonu**
- `woocommerce_cart_calculate_fees` → level indirimi
- `woocommerce_before_calculate_totals` → kargo avantajı

**Admin Panel**
- Dashboard: Özet kartları (bugün XP, aktif kullanıcı, level dağılımı)
- XP Ayarları: Her kaynak toggle + değer
- Level Yönetimi: Tam CRUD, sürükle-bırak sıralama, görsel/renk/isim
- Manuel XP: Müşteri arama, XP ekle/çıkar, sebep zorunlu, audit log
- İlk Kurulum Wizard'ı

**My Account Dashboard**
- Level göstergesi (isim, ikon, animasyonlu ilerleme çubuğu)
- XP özeti (bu ay / toplam / kaynak dağılımı)
- Aktif faydalar özeti
- Streak sayacı
- XP geçmişi logu (filtrelenebilir)

### ❌ Faz 1'e Dahil Değil

Rozetler (Faz 2), Görevler (Faz 2), Liderlik tablosu (Faz 3), Kampanya UI (Faz 3), Referans/Influencer (Faz 4), Paket açma (Faz 5), Sezon sistemi (Faz 6), Sosyal profil/takas (Faz 7), Quiz (Faz 8), Wrapped (Faz 9), Sürpriz sistemi (Faz 10)

---

## 6. Teknik Kararlar

| Konu | Karar | Neden |
|------|-------|-------|
| PHP minimum versiyon | 8.0 | Enum, named args, null-safe operator |
| XP hesaplama zamanı | Realtime (sipariş anında) | Cache ile desteklenir |
| Kayan pencere | DB query + cron cache | Performans için saatlik pre-cache |
| Admin AJAX | `wp_ajax_` + nonce | Güvenli, WordPress standardı |
| Frontend API | `gamify/v1` REST namespace | WC REST API'ye ek olarak |
| Settings storage | Tek JSON (`wp_options`) | Hızlı, cache-friendly, yönetimi kolay |
| Level benefits | DB JSON kolonu | Esnek, admin'den tam kontrol |
| CSS framework | Sıfırdan özel CSS | Bağımlılık yok, TCG estetiği |
| DB versiyon yönetimi | Migration sistemi | Faz geçişlerinde güvenli schema update |
| Görsel yönetimi | WordPress Media Library | attachment_id ile broken link riski yok |

---

## 7. Performans & Cache Stratejisi

**Rolling XP Cache**
- Kayan pencere hesabı (`SELECT SUM(xp) WHERE created_at > DATE_SUB(...)`) her yüklemede çalıştırılmaz
- `wp_gamify_user_levels.rolling_xp` kolonu **saatlik cron** ile güncellenir
- Yeni XP geldiğinde bu kullanıcının cache'i invalidate edilir
- Dashboard her zaman cache'den okur

**Settings Cache**
- `gamify_settings` option'ı WordPress object cache'de tutulur
- Güncelleme sonrası `wp_cache_delete('gamify_settings', 'options')` çalışır

**Level Hesaplama**
```php
// Her yerde bu fonksiyon kullanılır
function gamify_calculate_level(int $xp): int {
    $levels = gamify_get_levels_config(); // cache'den gelir
    $current = 1;
    foreach ($levels as $level) {
        if ($xp >= $level->xp_required) {
            $current = $level->level_number;
        }
    }
    return $current;
}
```

---

## 8. Güvenlik & Anti-Abuse

**AJAX/REST Güvenliği**
- Her admin işleminde: `check_ajax_referer()` + `current_user_can('manage_options')`
- Manuel XP gibi hassas işlemlerde ek: `current_user_can('manage_woocommerce')`
- REST endpoint'lerinde `permission_callback` zorunlu

**Anti-Abuse Kuralları**
- Günlük XP tavanı (varsayılan: 500 XP) — admin'den ayarlanabilir
- Aynı ürüne birden fazla yorum engeli
- Self-referral engeli
- Aynı IP/adres çoklu hesap kontrolü
- Rate limiting: Belirli aksiyonlar için günlük/haftalık tekrar limitleri
- XP audit log: Şüpheli aktivite admin bildirimi

**Sipariş İptali**
- `woocommerce_order_status_refunded` hook'unda negatif XP kaydı eklenir
- Level yeniden hesaplanır, grace period tetiklenebilir

---

## 9. Genişletilebilirlik

**PHP Interface'ler**
```php
// Her XP kaynağı bu interface'i implement eder
interface GamifyRewardSource {
    public function getId(): string;
    public function calculate(int $user_id, mixed $context): int;
    public function isEnabled(): bool;
    public function getDailyLimit(): int;
}

// Rozet koşul değerleyici
interface GamifyBadgeEvaluator {
    public function evaluate(int $user_id): int; // İlerleme değeri döner
}

// Görev ilerleme kontrolcüsü
interface GamifyQuestChecker {
    public function check(int $user_id, string $period_key): int;
}
```

**WordPress Hook'ları (Plugin İçi)**
```php
// XP verilmeden önce — miktar değiştirilebilir
$xp = apply_filters('gamify_xp_before_award', $xp, $source, $user_id, $context);

// XP verildikten sonra — diğer modüller tetiklenir
do_action('gamify_after_xp_awarded', $user_id, $xp, $source, $source_id);

// Level atlandığında
do_action('gamify_level_up', $user_id, $old_level, $new_level);

// Level düştüğünde
do_action('gamify_level_down', $user_id, $old_level, $new_level);

// Grace period başladığında
do_action('gamify_grace_period_started', $user_id, $current_level, $grace_until);
```

**WooCommerce Hook'ları (Plugin İçi — tek dosyada toplanmış)**
```php
// hooks/class-order-hooks.php
add_action('woocommerce_order_status_completed', ...)
add_action('woocommerce_order_status_refunded', ...)

// hooks/class-review-hooks.php
add_action('woocommerce_product_review_approved', ...)

// hooks/class-login-hooks.php
add_action('wp_login', ...)
add_action('user_register', ...)

// hooks/class-discount-hooks.php
add_filter('woocommerce_cart_calculate_fees', ...)
add_action('woocommerce_before_calculate_totals', ...)
```

---

## 10. Onaylanan Teknik Tavsiyeler

| # | Tavsiye | Durum |
|---|---------|-------|
| 1 | Rolling XP için saatlik cron cache | ✅ Uygulanacak |
| 2 | XP transactions'a asla DELETE, iade = negatif kayıt | ✅ Uygulanacak |
| 3 | Settings tek JSON olarak `wp_options`'da | ✅ Uygulanacak |
| 4 | `do_action` / `apply_filters` ile genişletilebilirlik | ✅ Uygulanacak |
| 5 | DB migration sistemi (versiyon takibi) | ✅ Uygulanacak |
| 6 | Nonce + Capability kontrolü her admin işleminde | ✅ Uygulanacak |
| 7 | PHP Interface'ler (GamifyRewardSource vb.) | ✅ Uygulanacak |
| 8 | Frontend dashboard JS sadece dashboard sayfasında yüklenir | ✅ Uygulanacak |
| 9 | WC hook'ları tek dosyada toplanır | ✅ Uygulanacak |
| 10 | Türkçe karakter & `wp_timezone()` ile timezone yönetimi | ✅ Uygulanacak |
| 11 | `uninstall.php` ile temiz kaldırma (opsiyonel "verileri koru") | ✅ Uygulanacak |
| 12 | İlk kurulum wizard'ı (seed data + temel ayarlar) | ✅ Uygulanacak |

---

## 11. Faz Yol Haritası

| Faz | Süre | Kapsam |
|-----|------|--------|
| **Faz 1** | 4-6 hafta | XP motoru + streak + level sistemi + My Account dashboard + Admin panel (temel) |
| **Faz 2** | 3-4 hafta | Rozet sistemi (dinamik kademeler, tam CRUD) + görev motoru (günlük/haftalık/aylık) |
| **Faz 3** | 2-3 hafta | Kampanya sistemi UI + bildirim motoru + liderlik tablosu |
| **Faz 4** | 3-4 hafta | Referans & influencer sistemi + başvuru formu + influencer dashboard |
| **Faz 5** | 4-5 hafta | Sanal paket açma + set toplama + paket açma animasyonu (TCG Pocket tarzı) |
| **Faz 6** | 2-3 hafta | Sezon sistemi + sezon XP takibi (level XP'sinden bağımsız) + sezon ödülleri |
| **Faz 7** | 3-4 hafta | Koleksiyon vitrini + sosyal profil + kart takas sistemi |
| **Faz 8** | 2-3 hafta | Bilgi & quiz sistemi + soru havuzu yönetimi |
| **Faz 9** | 2-3 hafta | Kişisel istatistikler + sezon/yıllık özet (Wrapped) + paylaşım kartları |
| **Faz 10** | 1-2 hafta | Sürpriz & delight mekaniği |
| **Faz 11** | 2 hafta | Raporlama & analitik panel + anti-abuse refinement |
| **Sürekli** | — | A/B testleri, denge ayarları, yeni set/rozet/görev ekleme |

---

## Notlar

- Her faz kendi DB migration'ı ile gelir — eski kurulumlar otomatik güncellenir
- Faz geçişlerinde mevcut kod bozulmaz, yeni modüller hook sistemi üzerinden eklenir
- Tüm sayısal değerler (XP miktarları, eşikler, süreler) admin panelden değiştirilebilir
- Bu doküman her faz sonrası güncellenmelidir

---

*WP Gamify Mimari Plan v1.0 — Şubat 2026*
