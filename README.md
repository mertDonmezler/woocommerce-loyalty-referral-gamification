# Gorilla WooCommerce Plugin Suite

Three modular WordPress/WooCommerce plugins for loyalty, referral, and holographic card effects.

## Plugins

### 1. Gorilla Loyalty & Gamification (`gorilla-loyalty-gamification/`)
**Version:** 1.0.0 | **Requires:** WooCommerce 7.0+ | **PHP:** 7.4+

Complete loyalty and gamification system:
- **Store Credit** - Balance management, checkout integration, coupons
- **Tier System** - Spending-based loyalty tiers with discounts
- **XP & Levels** - Experience points for orders, reviews, referrals
- **Badges** - Achievement-based badge system
- **Spin Wheel** - Gamified prize wheel
- **Points Shop** - Redeem XP for rewards
- **Leaderboard** - Monthly/all-time XP rankings
- **Milestones** - Goal-based achievements
- **Social Share** - Share for XP rewards
- **Birthday & Anniversary** - Automated reward system
- **Login Streaks** - Daily login rewards
- **Challenges** - Time-limited goals
- **Churn Prediction** - Re-engagement automation
- **Smart Coupons** - AI-targeted discount coupons
- **QR Code** - Customer loyalty QR codes
- **SMS Notifications** - Twilio integration
- **WooCommerce Emails** - 12 WC email templates
- **REST API** - Full API with 15+ endpoints
- **GDPR/KVKK** - Privacy tools compliance
- **WP-CLI** - Command line management

### 2. Gorilla Referral & Affiliate (`gorilla-referral-affiliate/`)
**Version:** 1.0.0 | **Requires:** WooCommerce 7.0+, Gorilla Loyalty & Gamification | **PHP:** 7.4+

Referral and affiliate marketing:
- **Video Referrals** - Customer video review system
- **Affiliate Links** - Cookie-based tracking
- **Tiered Commission** - Sales-based commission tiers
- **Recurring Commission** - Ongoing affiliate earnings
- **Dual Referral** - Two-sided referral rewards
- **Fraud Detection** - IP/pattern analysis
- **Custom Slugs** - Personalized affiliate URLs
- **WooCommerce Emails** - 5 WC email templates
- **REST API** - Referral & affiliate endpoints
- **GDPR/KVKK** - Privacy tools compliance

### 3. WooCommerce Holo Cards (`poke-holo-cards/`)
**Version:** 1.0.0 | **WooCommerce:** Optional | **PHP:** 7.4+

3D holographic card effects:
- **Shortcodes** - `[holo_card]`, `[holo_gallery]`, `[holo_carousel]`
- **Gutenberg Block** - Native block editor support
- **Page Builders** - Elementor, Beaver Builder, Divi, Bricks
- **WC Product Gallery** - Holographic product images
- **Card Collections** - Customer card collections (WC)
- **Pack Opening** - Card pack opening experience (WC)
- **AR Viewer** - Augmented reality card viewing
- **Analytics** - View/interaction tracking

## Dependency Chain

```
WooCommerce (required)
├── Gorilla Loyalty & Gamification (standalone)
│   └── Gorilla Referral & Affiliate (requires Loyalty)
└── WooCommerce Holo Cards (WC optional, standalone)
```

## Installation

1. Install and activate **WooCommerce**
2. Install **Gorilla Loyalty & Gamification** (upload ZIP via Plugins > Add New)
3. (Optional) Install **Gorilla Referral & Affiliate**
4. (Optional) Install **WooCommerce Holo Cards**

## CI/CD

Each plugin has its own GitHub Actions workflow:
- Tag `loyalty-v1.0.0` → builds Loyalty ZIP
- Tag `referral-v1.0.0` → builds Referral ZIP
- Tag `holo-v1.0.0` → builds Holo Cards ZIP

## Author

**Mert Donmezler**

## License

GPLv2 or later
