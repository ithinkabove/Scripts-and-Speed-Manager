# Site Scripts & Speed Manager

**See every JavaScript file on your WordPress site. Control how each one loads. Speed up your pages without breaking anything.**

![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue?logo=wordpress)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple?logo=php)
![License](https://img.shields.io/badge/License-GPLv2-green)
![Version](https://img.shields.io/badge/Version-2.3.0-orange)

---

## The Problem

Most performance plugins give you a single "defer all scripts" checkbox. You enable it, your site breaks, and you spend hours trying to figure out which script caused the problem — with no visibility into what's actually loading on your pages.

## The Solution

**Site Scripts & Speed Manager** takes a different approach: **see first, then optimize.**

Instead of blindly deferring everything, you scan your site, see every script in a clean visual table, and make informed per-script decisions. If anything breaks, one toggle turns it all off instantly.

---

## Features

### Full Site Crawl
Don't just scan one page — crawl your **entire site** with a single click. The plugin automatically discovers your pages, posts, and category archives (up to 50 pages, 10 posts, and 5 categories), scans each one for scripts, and merges the results into a unified view.

### Per-Page Script Tracking
After a full site crawl, every script shows how many pages it appears on. Scripts that only load on inner pages (not the homepage) are highlighted with an **"inner only"** badge, so you can immediately spot page-specific scripts from plugins like WooCommerce, contact forms, or page builders that only load where needed.

### Per-Script Loading Control
Each discovered script gets its own dropdown: **Defer**, **Async**, or **None**. No blanket rules — you decide exactly how each script loads based on what it is and what it does.

### Protected Scripts
jQuery Core and jQuery Migrate are automatically locked and cannot be deferred or async'd. Deferring jQuery breaks the majority of WordPress sites, so the plugin prevents you from making that mistake.

### Smart Script Classification
Every script is tagged with a visual badge:

| Badge | Meaning | Controllable? |
|---|---|---|
| 🔒 **Protected** | jQuery core / jQuery Migrate — locked | No |
| **WP** | Registered via `wp_enqueue_script()` | Yes — Defer, Async, or None |
| **EXT** | Hardcoded or external — shown for visibility | No (must be modified at source) |

### Single-Page Scan
Enter any URL on your site and scan just that page. Useful for testing specific pages like checkout, contact forms, or landing pages that load unique scripts.

### Scan Merging
Results from multiple scans (single-page or crawl) merge automatically. Scan your homepage, then scan your shop page — the table shows the combined set of scripts with page counts.

### Bulk Actions
- **Defer All** — sets every controllable script to Defer in one click
- **Reset All** — clears all strategies back to None
- **Save Settings** — persists your choices to the database

### Filter Tabs
Filter the script table by category:
- **All** — every script found
- **Controllable** — WP-enqueued scripts you can modify
- **External** — hardcoded/third-party scripts (display only)
- **Protected** — locked scripts (jQuery)
- **Inner-Page Only** — scripts not found on the homepage (appears after a full site crawl)

### Live Search
Type in the search box to instantly filter scripts by handle name or source URL. Works in combination with the filter tabs.

### Stats Dashboard
A real-time stats bar shows:
- **Total Scripts** — how many scripts were discovered
- **Controllable** — how many you can modify
- **Deferred** — how many are set to Defer
- **Async** — how many are set to Async
- **External** — how many are hardcoded/third-party
- **Pages Scanned** — how many pages were crawled (after a full crawl)

### Master Kill Switch
A prominent on/off toggle at the top of the page. When active, the plugin modifies script tags on the front-end. When off, **all scripts load normally** — no changes are applied, no cache clearing needed, and your saved settings are preserved for when you turn it back on.

### Loading Strategy Reference
Built into the admin page — detailed cards explaining exactly what **None**, **Defer**, and **Async** do, including:
- **When to use** each strategy
- **Performance impact** of each
- **Cautions** and common mistakes (e.g., why Async can break jQuery-dependent scripts)

### Modern Admin UI
A completely custom-built interface with:
- CSS custom properties for consistent theming
- Color-coded strategy dropdowns (blue for Defer, amber for Async)
- Change highlighting — modified rows turn amber until saved
- Responsive layout for all screen sizes
- Animated progress bar during site crawls
- Hover-to-expand source URLs in the table

### Zero Bloat
- No external API calls
- No tracking or analytics
- No ads, banners, or upsells
- No additional database tables
- Single `wp_options` row for all settings

---

## How It Works

### Loading Strategies Explained

| Strategy | Download | Execution | Order Guaranteed? | Best For |
|---|---|---|---|---|
| **None** | Blocks HTML parsing | Immediately on download | Yes | jQuery, critical above-the-fold scripts |
| **Defer** | Parallel (non-blocking) | After HTML is fully parsed | Yes (among deferred) | Most scripts — sliders, forms, UI, WooCommerce |
| **Async** | Parallel (non-blocking) | As soon as download completes | No | Analytics, tracking pixels, chat widgets |

### Under the Hood
- Hooks into WordPress via the `script_loader_tag` filter at priority 99
- Only modifies scripts that are registered via `wp_enqueue_script()`
- Adds `defer` or `async` attributes to `<script>` tags based on your settings
- Scanning uses `wp_remote_get()` to fetch page HTML from your own server
- All AJAX endpoints are nonce-verified with `manage_options` capability checks

---

## Installation

### From ZIP
1. Download the latest `site-scripts-speed-manager.zip` from [Releases](https://github.com/ithinkabove/Scripts-and-Speed-Manager/releases) or the repo
2. In WordPress, go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP and click **Install Now**
4. Activate the plugin

### Manual Upload
1. Extract the ZIP
2. Upload the `site-scripts-speed-manager` folder to `/wp-content/plugins/`
3. Activate in the **Plugins** menu

### After Activation
The plugin appears as **Scripts & Speed** in the WordPress admin sidebar with a speedometer icon.

---

## Quick Start

1. Go to **Scripts & Speed** in the admin menu
2. Click **Crawl Entire Site** to discover all scripts across your site
3. Review the results — scripts are sorted by type with visual badges
4. Set scripts to **Defer** (recommended for most) or **Async** (for analytics/tracking only)
5. Click **Save Settings**
6. Turn on the **Master Switch** to activate
7. Test your site — if anything breaks, flip the switch off instantly

---

## Safety Features

- **jQuery & jQuery Migrate** are protected by default — their dropdowns are disabled
- **Master kill switch** — instantly disables all modifications without losing your saved settings
- **No cache clearing needed** — the toggle takes effect immediately
- **Settings are preserved** when toggled off — flip back on when ready
- **Per-script control** — if one script causes issues, change just that one instead of disabling everything

---

## Technical Details

| Detail | Value |
|---|---|
| **Hook** | `script_loader_tag` filter (priority 99) |
| **Storage** | Single `wp_options` row (`sssm_settings`) |
| **Admin page** | Top-level menu with `dashicons-performance` icon |
| **AJAX endpoints** | `sssm_scan`, `sssm_crawl`, `sssm_save`, `sssm_toggle` |
| **Security** | Nonce verification + `manage_options` capability on all endpoints |
| **Crawl limits** | Up to 50 pages, 10 posts, 5 category archives |
| **Scan timeout** | 30 seconds per URL |
| **Clean uninstall** | Removes all data on plugin deletion (including multisite) |
| **Dependencies** | jQuery (bundled with WordPress) |

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- No external dependencies

---

## Changelog

### 2.3.0
- **Full site crawl** — discover scripts across your entire site with one click
- **Per-page script tracking** — see how many pages each script appears on
- **Inner-page-only detection** — highlight scripts not found on the homepage
- **Expanded strategy definitions** — detailed "When to use", "Impact", and "Caution" for each strategy
- **Crawl progress bar** — animated progress during site crawls
- **Pages Scanned stat** — stats bar shows crawl coverage
- **Inner-Page Only filter tab** — filter to scripts only found on inner pages
- Plugin Check compliance — inline nonce verification, sanitized inputs, `wp_parse_url()`

### 2.2.0
- Complete UI/UX redesign with modern card layout and CSS custom properties
- Color-coded strategy dropdowns with change highlighting
- Live search and filter tabs (All, Controllable, External, Protected)
- Stats dashboard with real-time counters
- Branding footer with Think Above AI
- Moved to top-level admin menu with speedometer icon

### 2.1.1
- Initial public release
- Visual script discovery via URL scanning
- Per-script defer/async/none controls
- Protected scripts (jQuery core, jQuery Migrate)
- Multi-page scan merging
- Bulk actions (Defer All, Reset All)
- Master on/off kill switch
- Clean uninstall support

---

## Author

**Think Above AI**
[https://thinkabove.ai](https://thinkabove.ai)

## License

GPL v2 or later — [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
