# Site Scripts & Speed Manager

**A WordPress plugin that allows you to defer, async and ignore scripts with a simple interface and powerful results. More control with ease of use.**

![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue?logo=wordpress)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple?logo=php)
![License](https://img.shields.io/badge/License-GPLv2-green)
![Version](https://img.shields.io/badge/Version-2.1.1-orange)

---

## The Problem

Most performance plugins offer a blanket "defer all scripts" checkbox — then you spend hours figuring out what broke. You can't see what scripts are loading, where they come from, or which ones are safe to modify.

## The Solution

**Site Scripts & Speed Manager** gives you full visibility and per-script control:

1. **Scan any page** on your site to discover every JavaScript file
2. **See exactly** what each script is, where it comes from, and how it currently loads
3. **Choose per-script**: Defer, Async, or None (ignore)
4. **Master kill switch** — if anything breaks, flip it off instantly

---

## Features

| Feature | Description |
|---|---|
| **Visual Script Discovery** | Scan any URL and see every script in a clean table |
| **Per-Script Control** | Dropdown for each script: Defer, Async, or None |
| **Protected Scripts** | jQuery core & Migrate are auto-protected (can't be deferred) |
| **Script Type Badges** | WP-enqueued, External/Hardcoded, or Protected — instantly visible |
| **Multi-Page Scanning** | Scan multiple pages — results merge automatically |
| **Bulk Actions** | "All → Defer" and "All → None" for quick setup |
| **Master Toggle** | On/off kill switch — removes all modifications instantly |
| **Native WP UI** | Clean WordPress admin styling with color-coded controls |
| **Zero Bloat** | No tracking, no external API calls, no ads, no upsells |

---

## Screenshots

### Admin Dashboard
The main settings page with master toggle, URL scanner, and script table:

- **Master switch** — green border when active, instant on/off
- **URL scanner** — enter any page, hit Scan
- **Script table** — handle, source URL, type badge, current status, strategy dropdown
- **Bulk actions** — defer all controllable scripts with one click

---

## How It Works

### Loading Strategies

| Strategy | Behavior | Best For |
|---|---|---|
| **None** | Downloads and runs immediately, blocks rendering | jQuery, critical scripts |
| **Defer** | Downloads in parallel, runs after HTML is parsed (maintains order) | Most scripts |
| **Async** | Downloads in parallel, runs as soon as ready (no order guarantee) | Analytics, tracking |

### Script Classification

| Badge | Meaning | Controllable? |
|---|---|---|
| 🔒 Protected | jQuery core/Migrate — cannot be modified | No |
| **WP** | Registered via `wp_enqueue_script()` | Yes |
| **EXT** | Hardcoded or external — shown for visibility | No (modify at source) |

---

## Installation

### Manual Upload
1. Download the latest release ZIP
2. Upload the `site-scripts-speed-manager` folder to `/wp-content/plugins/`
3. Activate in **Plugins** menu
4. Go to **Settings → Scripts & Speed**

### From WordPress Admin
1. Go to **Plugins → Add New**
2. Search for "Site Scripts & Speed Manager"
3. Click **Install Now**, then **Activate**
4. Go to **Settings → Scripts & Speed**

---

## Quick Start

1. Navigate to **Settings → Scripts & Speed**
2. Enter your homepage URL (pre-filled) and click **Scan Scripts**
3. Review the discovered scripts
4. Set scripts to **Defer** or **Async** as needed
5. Click **Save Settings**
6. Turn on the **Master Switch**
7. Test your site — if anything breaks, flip the switch off

---

## Safety Features

- **jQuery & jQuery Migrate** are protected by default — dropdowns are disabled
- **Master kill switch** — instantly disables all modifications without losing settings
- **No cache clearing needed** — the switch works immediately
- **Settings preserved** when toggled off — flip back on when ready

---

## Technical Details

- **Hook used:** `script_loader_tag` filter (priority 99)
- **Storage:** Single `wp_options` row (`sssm_settings`)
- **Admin page:** `settings_page_site-scripts-speed-manager`
- **AJAX actions:** `sssm_scan`, `sssm_save`, `sssm_toggle`
- **Nonce verified** on all AJAX calls
- **Capability check:** `manage_options` required
- **Clean uninstall:** Removes option on plugin deletion (including multisite)

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- No external dependencies

---

## Changelog

### 2.1.1
- Initial public release
- Visual script discovery via URL scanning
- Per-script defer/async/none controls
- Protected scripts (jQuery core, jQuery Migrate)
- Multi-page scan merging
- Bulk actions
- Master on/off kill switch
- Native WordPress admin UI
- Clean uninstall support

---

## Author

**Think Above AI**
[https://thinkabove.ai](https://thinkabove.ai)

## License

GPL v2 or later — [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
