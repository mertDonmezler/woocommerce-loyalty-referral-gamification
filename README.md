# WooCommerce Loyalty, Referral & Gamification Suite

**The most complete loyalty ecosystem for WooCommerce.** Turn one-time buyers into lifelong brand advocates with a powerful all-in-one plugin that combines loyalty tiers, XP gamification, video referrals, affiliate tracking, and 13+ engagement features.

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-3.0.1-orange.svg)](#changelog)

---

## Why This Plugin?

Most loyalty plugins do **one thing**. This does **everything** — and does it securely.

| Problem | Solution |
|---------|----------|
| Customers buy once and never return | 6-tier loyalty system with automatic discounts |
| No word-of-mouth growth | Video referral system with store credit rewards |
| Affiliate programs are separate plugins | Built-in affiliate tracking with tiered commissions |
| Gamification requires 3rd-party SaaS | Native XP, levels, badges, leaderboard, spin wheel |
| Security is an afterthought | 73 security hardening fixes, atomic transactions, GDPR-compliant |

---

## Features

### Loyalty & Tiers
- **6-Tier Loyalty System** — Automatic tier progression based on total spending
- **Per-Tier Discounts** — Each tier unlocks a higher automatic discount percentage
- **Store Credit** — Award, deduct, and let customers apply credit at checkout
- **Coupon Generator** — Auto-generate WooCommerce coupons as rewards

### XP & Gamification
- **XP Engine** — Earn XP from purchases, reviews, referrals, social shares, and registrations
- **Level System** — Configurable XP thresholds with level-up notifications
- **Login Streaks** — Daily login rewards with 7-day and 30-day streak bonuses
- **Birthday Rewards** — Automatic XP + credit on customers' birthdays
- **Badges** — Achievement system (first purchase, spending milestones, streak records, etc.)
- **Milestones** — Customizable goals with XP/credit rewards on completion
- **Leaderboard** — Monthly top earners with gold/silver/bronze podium display
- **Spin Wheel** — Lucky wheel with configurable prizes and canvas animation
- **Points Shop** — Spend XP to redeem rewards (converts to WooCommerce coupons)

### Referral & Affiliate
- **Video Referral System** — Customers submit video testimonials for store credit
- **Dual-Sided Referral** — Both referrer and new customer get rewarded
- **Affiliate Tracking** — Cookie-based click tracking with dashboard analytics
- **Tiered Commissions** — Commission rates increase with affiliate sales volume
- **Recurring Commissions** — Earn from repeat purchases within a configurable window
- **Social Sharing** — Share buttons with XP rewards for Facebook, Twitter, WhatsApp
- **QR Code Generation** — Unique QR codes for offline referral tracking

### Admin & Compliance
- **Full Admin Dashboard** — Stats, user management, credit adjustments, bulk actions
- **10-Section Settings Panel** — Granular control over every feature
- **18 REST API Endpoints** — Full programmatic access for headless/mobile builds
- **11 Email Notifications** — Automated emails for every major event
- **GDPR Compliant** — Full data export and erasure (Article 15 & 17)
- **Clean Uninstall** — Removes all data, options, cron jobs, and transients

---

## Security

This plugin was built with **security-first architecture** and has undergone 3 rounds of comprehensive auditing:

- **Atomic Transaction Guards** — All credit/XP operations use `INSERT...WHERE NOT EXISTS` or `INSERT IGNORE` to prevent double-spend and race conditions
- **SQL Injection Defense** — Every database query uses `$wpdb->prepare()` with proper placeholders
- **XSS Prevention** — All JavaScript DOM operations use `textContent` (never `innerHTML` with user data)
- **CSRF Protection** — WordPress nonce verification on every AJAX and REST endpoint
- **Rate Limiting** — Transient-based rate limits on shop redemption, social sharing, and admin actions
- **IDOR Protection** — REST API endpoints enforce authentication and ownership checks
- **Idempotency Guards** — Checkout deduction, refund, birthday, milestone, and affiliate credits all have atomic duplicate-prevention
- **HPOS Compatible** — Fully compatible with WooCommerce High-Performance Order Storage

---

## Installation

1. Download the latest release ZIP
2. Go to **WordPress Admin > Plugins > Add New > Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Activate the plugin
5. Navigate to **WooCommerce > Loyalty Settings** to configure

### Requirements
- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+
- MySQL 5.7+ (InnoDB)

---

## Architecture

```
plugin-root/
  ├── plugin-main.php              # Bootstrap, activation, enqueue
  ├── uninstall.php                # Clean removal of all data
  ├── assets/
  │   ├── css/frontend.css         # Responsive UI (768px/600px/380px breakpoints)
  │   └── js/frontend.js           # Spin wheel, AJAX handlers, animations
  └── includes/
      ├── class-store-credit.php   # Credit balance & checkout integration
      ├── class-xp.php            # XP engine, streaks, birthday, milestones
      ├── class-loyalty.php       # Tiers, badges, spin, shop, social, QR
      ├── class-referral.php      # Video referral CPT system
      ├── class-affiliate.php     # Affiliate tracking & commissions
      ├── class-frontend.php      # Customer-facing account pages
      ├── class-admin.php         # Admin dashboard & management
      ├── class-settings.php      # 10-section settings panel
      ├── class-emails.php        # 11 notification templates
      ├── class-rest-api.php      # 18 REST API endpoints
      └── class-gdpr.php          # Data export & erasure
```

### Database Schema
- 3 custom tables: `credit_log`, `affiliate_clicks`, `xp_log`
- 11 user meta keys for gamification state
- 30+ wp_options for configuration
- Zero new tables for v3.0 features (leverages existing meta system)

---

## REST API

18 endpoints under `/wp-json/gorilla-lr/v1/`:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/me` | Current user profile (XP, tier, badges, streak) |
| GET | `/badges` | All badge definitions with earned status |
| GET | `/leaderboard` | Monthly top XP earners |
| GET | `/milestones` | Milestone progress for current user |
| GET | `/shop` | Available rewards in points shop |
| GET | `/streak` | Login streak data |
| GET | `/qr` | QR code URL for referral |
| GET | `/settings` | Public plugin settings |
| POST | `/shop/redeem` | Redeem a reward with XP |
| POST | `/social/share` | Track social share for XP |
| GET | `/affiliate/stats` | Affiliate dashboard data |
| POST | `/referral/submit` | Submit video referral |
| ... | ... | + 6 more admin endpoints |

All endpoints enforce authentication, nonce verification, and rate limiting.

---

## Frontend

The customer-facing UI is built with **vanilla JS + CSS** (zero framework dependencies):

- **Canvas-based Spin Wheel** — Smooth easing animation with prize rendering
- **Confetti Celebration** — Particle animation on wins
- **Toast Notifications** — Accessible (`role="alert"`) with close button and progress bar
- **Progress Bars** — IntersectionObserver-triggered CSS animations
- **Responsive Design** — 4 breakpoints (768px, 600px, 380px, hover:none)
- **Accessibility** — `prefers-reduced-motion`, `focus-visible`, screen reader utilities
- **Ripple Effects** — Material-style click feedback on interactive elements

---

## Configuration

All features are individually toggleable from **WooCommerce > Loyalty Settings**:

| Section | Key Settings |
|---------|-------------|
| **General** | Store credit label, checkout toggle |
| **Loyalty Tiers** | 6 tier names, spending thresholds, discount percentages |
| **XP & Levels** | XP per action (purchase, review, register), level thresholds |
| **Gamification** | Birthday XP/credit, streak bonuses, milestone definitions |
| **Spin Wheel** | Prize list (label + XP amount), daily spin limit |
| **Points Shop** | Rewards (name, XP cost, coupon value), shop toggle |
| **Referral** | Credit amounts, video requirements, dual-sided rewards |
| **Affiliate** | Commission rates, cookie duration, tier thresholds |
| **Social** | Enabled platforms, XP per share, daily share limit |
| **Advanced** | QR method, coupon prefix, leaderboard size |

---

## Changelog

### 3.0.1 (Security & Quality Patch)
- 73 fixes across security, data integrity, GDPR, performance, and UX
- Atomic transaction guards on all credit/XP operations
- innerHTML XSS eliminated from toast system
- GDPR export/erase completeness (XP log, 6 missing meta keys, referral posts)
- All CSS classes wired to PHP output (zero orphaned styles)
- `wc_get_orders()` safety checks across all files

### 3.0.0
- 13 new features: Birthday rewards, Login streaks, Badges, Spin wheel, Dual-sided referral, Tiered affiliate commissions, Milestones, Points shop, Leaderboard, Social sharing, Coupon generator, Recurring affiliate, QR codes
- 18 REST API endpoints
- 11 email notification templates
- GDPR data export & erasure
- Full admin dashboard

### 2.3.0
- 6-tier loyalty system with automatic discounts
- Video referral system with store credit
- Affiliate tracking with cookie-based attribution
- XP engine with levels
- Admin dashboard and settings panel

---

## Tech Stack

| Component | Technology |
|-----------|-----------|
| Backend | PHP 7.4+ (procedural, WordPress coding standards) |
| Database | MySQL 5.7+ with InnoDB (atomic transactions) |
| Frontend | Vanilla JavaScript ES5+ (zero dependencies) |
| Styling | Custom CSS with CSS custom properties |
| API | WordPress REST API (WP_REST_Controller) |
| Compatibility | WooCommerce HPOS, WordPress 6.0+, PHP 8.x |

---

## License

GPLv2 or later. See [LICENSE](LICENSE) for details.

---

<p align="center">
  <strong>Built for stores that take customer retention seriously.</strong><br>
  <em>Stop losing customers. Start building loyalty.</em>
</p>
