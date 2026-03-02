# Gorilla WooCommerce Plugin Suite

A production-ready, modular WordPress/WooCommerce ecosystem for **loyalty programs**, **gamification**, **referral marketing**, and **affiliate management**. Built for high-traffic stores with enterprise-grade security, atomic concurrency protection, and full GDPR/KVKK compliance.

**Version:** 2.0.0 | **PHP:** 7.4+ / 8.0+ | **WooCommerce:** 7.0+ (tested up to 9.5) | **WordPress:** 6.0+ | **HPOS:** Fully compatible

---

## Architecture Overview

```
                        ┌──────────────────┐
                        │   WooCommerce    │
                        └────────┬─────────┘
                                 │
         ┌───────────────────────┼───────────────────────┐
         │                       │                       │
┌────────▼────────┐    ┌────────▼────────┐    ┌────────▼────────┐
│   WP Gamify     │    │  Gorilla L&G    │    │  Gorilla R&A    │
│   (XP Engine)   │◄───│  (Loyalty)      │───►│  (Referral)     │
│                 │    │                 │    │                 │
│ • XP Engine     │    │ • Loyalty Tiers │    │ • Video Referral│
│ • 8 Levels      │    │ • Store Credit  │    │ • Affiliate     │
│ • Streaks       │    │ • 20+ Badges    │    │ • Tiered Comm.  │
│ • Campaigns     │    │ • Spin Wheel    │    │ • Recurring Rev.│
│ • Anti-Abuse    │    │ • Points Shop   │    │ • Dual Rewards  │
│ • Grace Periods │    │ • Milestones    │    │ • Fraud Detect. │
│ • XP Expiry     │    │ • Leaderboard   │    │ • Custom Slugs  │
│ • Audit Log     │    │ • Challenges    │    │ • QR Codes      │
│                 │    │ • SMS (Twilio)  │    │                 │
│ 5 DB Tables     │    │ • 12 WC Emails  │    │ 5 WC Emails     │
│ 3 REST APIs     │    │ • 17+ REST APIs │    │ 3 REST APIs     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

---

## Plugins

### 1. WP Gamify (`wp-gamify/`)

> Centralized XP, leveling, and gamification engine.

| Feature | Details |
|---------|---------|
| **XP Engine** | Award/deduct XP from 10+ sources (orders, reviews, login, referral, profile, etc.) |
| **8-Level System** | Configurable levels with XP thresholds, discounts (up to 20%), free shipping, early access |
| **Login Streaks** | Exponential XP rewards: `base * multiplier^(day-1)`, configurable cycle length |
| **Campaigns** | Temporary XP multipliers (e.g., 2x XP Weekend) with date range |
| **Anti-Abuse** | Daily XP cap (500), duplicate review blocking, self-referral detection, suspicious activity logging |
| **Grace Periods** | 14-day protection before level downgrade when XP drops |
| **XP Expiry** | Auto-expire old XP (configurable months) with email warnings |
| **Audit Log** | Full admin action history with before/after values |
| **Level Discounts** | Automatic cart discounts based on user level |
| **Free Shipping** | Level-based free shipping benefits |

**Technical Highlights:**
- MySQL `GET_LOCK` for concurrent XP safety
- Atomic `INSERT IGNORE` idempotency guards
- Rolling (6-month) or all-time level calculation modes
- 15+ action hooks + 5+ filter hooks for extensibility
- 5 custom database tables with proper indexing

---

### 2. Gorilla Loyalty & Gamification (`gorilla-loyalty-gamification/`)

> Complete loyalty program with 13+ customer engagement features.

| Feature | Details |
|---------|---------|
| **Loyalty Tiers** | 5 tiers (Bronze to Diamond) based on spending, with configurable benefits |
| **Store Credit** | Atomic balance management, checkout integration, auto-refund on cancellation |
| **Badges** | 20+ achievement badges (spending, behavior, tier-based), auto-unlocking |
| **Spin Wheel** | Configurable prizes with probability weights, confetti animations |
| **Points Shop** | Spend XP for discount coupons, free shipping, custom rewards |
| **Leaderboard** | Weekly/monthly/all-time rankings with anonymization option |
| **Milestones** | Goal-based achievements (first order, 10 orders, spending goals) |
| **Challenges** | Weekly/one-time quests with XP + credit rewards |
| **Social Sharing** | Facebook, Twitter, WhatsApp, Instagram, TikTok with daily XP limits |
| **QR Codes** | Downloadable customer referral QR codes |
| **Birthday/Anniversary** | Automatic credit rewards on special dates |
| **Churn Prevention** | Detects inactive users, sends re-engagement bonuses |
| **Smart Coupons** | Auto-generated personalized coupons targeting favorite categories |
| **SMS Notifications** | Twilio integration with AES-256-CBC encrypted credentials |
| **12 WC Emails** | Tier upgrade/downgrade, level up, credit expiry, birthday, milestone, badge, etc. |
| **17+ REST APIs** | Full API coverage for all features |
| **WP-CLI** | `wp gorilla-lg tier`, `wp gorilla-lg xp` commands |

**Technical Highlights:**
- WP Gamify bridge layer for backward compatibility
- MySQL row locks + GET_LOCK for credit safety (TOCTOU protected)
- Batch processing for cron jobs (50-1000 records/run)
- Spending cache with 1-hour TTL
- Cross-plugin double-discount prevention via `$GLOBALS` flag

---

### 3. Gorilla Referral & Affiliate (`gorilla-referral-affiliate/`)

> Video referral program and full affiliate marketing system.

| Feature | Details |
|---------|---------|
| **Video Referrals** | Customers submit video reviews (YouTube, Instagram, TikTok, Twitter, Facebook, Twitch, Vimeo) |
| **Affiliate System** | Unique affiliate codes, cookie-based tracking (1-365 days), checkout attribution |
| **Tiered Commission** | Dynamic rates based on cumulative sales (e.g., 10% -> 15% -> 20% -> 25%) |
| **Recurring Revenue** | Earn commission on repeat purchases from referred customers (1-24 months) |
| **Dual-Sided Rewards** | Both referrer (credit) and new customer (coupon) get rewarded |
| **Fraud Detection** | Weekly automated scan: IP concentration, click bursts, zero-conversion, low diversity |
| **Custom Slugs** | Personalized affiliate URLs with validation and rate limiting |
| **5 WC Emails** | Approval/rejection notifications, commission alerts, dual reward coupons |
| **3 REST APIs** | Referral submissions, affiliate info, detailed stats |

**Technical Highlights:**
- Transient-based rate limiting (5-min cooldown, 5 slug changes/hour)
- Atomic double-check locking for approval flow
- IP anonymization for GDPR compliance
- Cross-plugin safety guards on uninstall

---

## Dependency Chain

```
WooCommerce (required)
└── WP Gamify (standalone, required by Gorilla LG)
    └── Gorilla Loyalty & Gamification (requires WP Gamify)
        └── Gorilla Referral & Affiliate (requires Gorilla LG)
```

## Installation

1. Install and activate **WooCommerce** (7.0+)
2. Upload and activate **WP Gamify**
3. Upload and activate **Gorilla Loyalty & Gamification**
4. *(Optional)* Upload and activate **Gorilla Referral & Affiliate**
5. Navigate to **WP Gamify > Setup Wizard** for initial configuration

## Database

| Table | Plugin | Purpose |
|-------|--------|---------|
| `wp_gamify_xp_transactions` | WP Gamify | All XP transactions with source tracking |
| `wp_gamify_user_levels` | WP Gamify | Cached user level data, grace periods |
| `wp_gamify_streaks` | WP Gamify | Login streak tracking |
| `wp_gamify_levels_config` | WP Gamify | Level definitions with benefits (JSON) |
| `wp_gamify_audit_log` | WP Gamify | Admin action audit trail |
| `wp_gorilla_credit_log` | Gorilla LG | Store credit transactions |
| `wp_gorilla_affiliate_clicks` | Gorilla R&A | Affiliate click tracking |

## REST API

**23+ endpoints** across three namespaces:

| Namespace | Endpoints | Auth |
|-----------|-----------|------|
| `gamify/v1` | `/user/stats`, `/user/xp-history`, `/user/level` | User |
| `gorilla-lg/v1` | `/me`, `/tier`, `/badges`, `/leaderboard`, `/shop`, `/milestones`, `/social/share`, `/credit`, + admin endpoints | User/Admin |
| `gorilla-lr/v1` | `/referrals`, `/affiliate`, `/affiliate/stats` | User |

## Security

- **XSS Prevention** - All output escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`)
- **CSRF Protection** - Nonce verification on all forms and AJAX handlers
- **SQL Injection** - `$wpdb->prepare()` on every database query
- **Race Conditions** - MySQL `GET_LOCK`, `INSERT IGNORE`, `START TRANSACTION`
- **TOCTOU Protection** - Atomic INSERT guards prevent double-processing
- **Rate Limiting** - Transient-based cooldowns on sensitive operations
- **Data Encryption** - AES-256-CBC with random IV for SMS credentials
- **Fraud Detection** - Automated weekly affiliate pattern analysis
- **Anti-Abuse** - Daily XP caps, duplicate blocking, self-referral detection
- **Safe Uninstall** - Cross-plugin data protection, shared table awareness

## GDPR / KVKK Compliance

All three plugins register with WordPress Privacy Tools:

- **Data Export** - Full user data export (XP, credits, tiers, badges, affiliates, clicks)
- **Data Erasure** - Complete PII removal with IP anonymization
- **Privacy Policy** - Auto-suggested privacy policy text
- **Right to be Forgotten** - Full compliance with GDPR Article 17

## Email Notifications

**17 WooCommerce email templates:**

| Plugin | Emails |
|--------|--------|
| Gorilla LG (12) | Tier upgrade/downgrade/grace, level up, credit expiry, birthday, anniversary, churn, XP expiry, milestone, badge, smart coupon |
| Gorilla R&A (5) | Referral approved/rejected, new referral (admin), affiliate earned, dual referral coupon |

## WP-CLI

```bash
wp gorilla-lg tier list                          # List all tiers
wp gorilla-lg tier recalculate-all --dry-run     # Preview tier changes
wp gorilla-lg xp add <user_id> 100 --reason="Bonus"  # Award XP
wp gorilla-lg xp get <user_id>                   # Check balance
wp gorilla-lg xp export --format=csv             # Export XP data
```

## Hooks & Extensibility

**Key action hooks for developers:**

```php
// XP Events
do_action('gamify_after_xp_awarded', $user_id, $amount, $source, $source_id);
do_action('gamify_level_up', $user_id, $old_level, $new_level);
do_action('gamify_level_down', $user_id, $old_level, $new_level);

// Loyalty Events
do_action('gorilla_tier_upgraded', $user_id, $old_tier, $new_tier);
do_action('gorilla_badge_earned', $user_id, $badge_id);
do_action('gorilla_credit_adjusted', $user_id, $amount, $reason);

// Referral Events
do_action('gorilla_referral_approved', $user_id, $post_id);
do_action('gorilla_affiliate_sale', $referrer_id, $order_id, $commission);
```

**Key filter hooks:**

```php
// Modify XP before awarding (add custom multipliers, caps, etc.)
add_filter('gamify_xp_before_award', function($xp, $source, $user_id, $context) {
    return $xp;
}, 10, 4);

// Modify order XP calculation
add_filter('gamify_order_xp', function($total_xp, $order_id, $user_id) {
    return $total_xp;
}, 10, 3);
```

## Stats

| Metric | Count |
|--------|-------|
| PHP Files | 62 |
| Lines of Code | ~19,500 |
| Database Tables | 7 |
| REST API Endpoints | 23+ |
| Email Templates | 17 |
| Security Guards | 24+ |
| Cron Jobs | 6 |
| Action Hooks | 24+ |
| Filter Hooks | 8+ |
| WP-CLI Commands | 7 |

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 6.0+ |
| PHP | 7.4+ (WP Gamify: 8.0+) |
| WooCommerce | 7.0+ |
| MySQL | 5.7+ (InnoDB) |

## Author

**Mert Donmezler** - [gorillacustomcards.com](https://gorillacustomcards.com)

## License

GPLv2 or later - [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
