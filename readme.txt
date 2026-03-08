=== Site Scripts & Speed Manager ===
Contributors: thinkaboveai
Donate link: https://thinkabove.ai
Tags: defer, async, scripts, performance, speed
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 2.3.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See every JavaScript file on your WordPress site. Control how each one loads. Speed up your pages without breaking anything.

== Description ==

**Site Scripts & Speed Manager** gives you full visibility and control over every JavaScript file loading on your WordPress site.

Most performance plugins offer a blanket "defer all scripts" checkbox — then you spend hours figuring out what broke. This plugin takes a different approach: **see first, then optimize.**

1. **Crawl your entire site** or scan a single page to discover every script
2. **See exactly** what each script is, where it comes from, how it currently loads, and which pages it appears on
3. **Choose per-script**: Defer, Async, or None — with visual badges, filter tabs, and live search
4. **Master kill switch** — if anything breaks, flip it off instantly. No cache clearing needed.

= Full Site Crawl =

Don't just scan one page — crawl your **entire site** with a single click. The plugin automatically discovers your pages, posts, and category archives (up to 50 pages, 10 posts, and 5 categories), scans each one for scripts, and merges the results into a unified view. An animated progress bar shows crawl status in real-time.

= Per-Page Script Tracking =

After a full site crawl, every script shows how many pages it appears on. Hover over the count to see the list of page paths. Scripts that only load on inner pages (not the homepage) are highlighted with an **"inner only"** badge, so you can immediately spot page-specific scripts from plugins like WooCommerce, contact forms, or page builders.

= Per-Script Loading Control =

Each discovered script gets its own dropdown: **Defer**, **Async**, or **None**. No blanket rules — you choose exactly how each script loads based on what it is and what it does. Color-coded dropdowns make it easy to see your choices at a glance: blue for Defer, amber for Async.

= Protected Scripts =

jQuery Core and jQuery Migrate are automatically locked and cannot be deferred or async'd. Deferring jQuery breaks the majority of WordPress sites, so the plugin prevents you from making that mistake. Protected scripts show a lock icon and their dropdowns are disabled.

= Smart Script Classification =

Every script is tagged with a visual badge:

* 🔒 **Protected** — jQuery core / jQuery Migrate — locked, cannot be modified
* **WP** (blue) — Registered via wp_enqueue_script() — fully controllable with Defer, Async, or None
* **EXT** (amber) — Hardcoded or external scripts — shown for visibility only (must be modified at source)

= Filter Tabs & Live Search =

Filter the script table by category:

* **All** — every script found
* **Controllable** — WP-enqueued scripts you can modify
* **External** — hardcoded/third-party scripts (display only)
* **Protected** — locked scripts (jQuery)
* **Inner-Page Only** — scripts not found on the homepage (appears after a full site crawl)

Type in the search box to instantly filter by handle name or source URL. Filters and search work together.

= Stats Dashboard =

A real-time stats bar shows total scripts, controllable count, deferred count, async count, external count, and pages scanned (after a full crawl). Stats update automatically when you change strategies.

= Bulk Actions =

* **Defer All** — sets every controllable script to Defer in one click
* **Reset All** — clears all strategies back to None
* **Save Settings** — persists your choices to the database

= Master Kill Switch =

A prominent on/off toggle at the top of the page. When active, the plugin modifies script tags on the front-end. When off, all scripts load normally — no changes are applied, no cache clearing needed, and your saved settings are preserved for when you turn it back on.

= Built-in Strategy Reference =

Detailed cards built right into the admin page explain exactly what each loading strategy does:

* **None (Default)** — The browser downloads and runs the script immediately, pausing HTML parsing. Required for jQuery and scripts that must run before the page renders.
* **Defer (Recommended)** — The browser downloads in parallel with HTML parsing, then executes after the document is fully parsed. Maintains execution order among deferred scripts. Best for most scripts.
* **Async** — The browser downloads in parallel, then executes as soon as the download is complete — regardless of HTML parsing state. Execution order is not guaranteed. Best only for fully independent scripts like analytics and tracking pixels.

Each card includes detailed "When to use", "Impact", and "Caution" guidance.

= Modern Admin UI =

A completely custom-built interface with CSS custom properties for consistent theming, color-coded dropdowns, change highlighting (modified rows turn amber until saved), responsive layout for all screen sizes, animated progress bar during crawls, and hover-to-expand source URLs in the table.

= Zero Bloat =

No external API calls. No tracking or analytics. No ads, banners, or upsells. No additional database tables. All settings are stored in a single wp_options row.

= Who Is This For? =

* **WordPress developers** who want granular script control without writing code
* **Site owners** looking to improve Core Web Vitals and page speed scores
* **Agencies** managing multiple client sites that need per-site optimization
* **Anyone** tired of blindly deferring scripts and breaking things

== Installation ==

1. Upload the `site-scripts-speed-manager` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Scripts & Speed** in the admin sidebar (speedometer icon)
4. Click **Crawl Entire Site** or enter a URL and click **Scan Page**
5. Choose Defer, Async, or None for each script
6. Click **Save Settings**
7. Turn on the **Master Switch** to activate

== Frequently Asked Questions ==

= Will this break my site? =

The plugin has multiple safety features:

* jQuery and jQuery Migrate are **protected by default** — they cannot be deferred or async'd
* The **master kill switch** lets you instantly disable all modifications without losing settings
* The switch takes effect immediately — **no cache clearing needed**
* If a single script causes issues, you can change just that one script instead of disabling everything

= Can I scan multiple pages? =

Yes, in two ways:

1. **Crawl Entire Site** — automatically discovers and scans up to 65 URLs (pages, posts, categories) and merges all results
2. **Scan Page** — scan individual URLs one at a time; results merge with any previous scan

After a crawl, each script shows how many pages it appears on, and scripts unique to inner pages are flagged.

= Can I use this with caching plugins? =

Yes. This plugin modifies script tags at the WordPress level via the `script_loader_tag` filter, which runs before any page caching. It works alongside WP Rocket, W3 Total Cache, LiteSpeed Cache, Breeze, and all other caching solutions.

= What about scripts added by page builders? =

Scripts enqueued via `wp_enqueue_script()` (the WordPress way) are fully controllable. Hardcoded scripts injected directly into templates are shown in the scan results but marked as "EXT" (External) — they need to be modified at their source (the theme or plugin that adds them).

= Does this phone home or track anything? =

No. Zero external calls, zero tracking, zero analytics. The scanner fetches pages from your own server using `wp_remote_get()`. All data stays on your site.

= How is this different from WP Rocket or Autoptimize? =

Those plugins offer blanket defer/async options with a textarea for exclusions. You defer everything, then add exclusions when things break. This plugin lets you **see every script first** and make informed decisions per-script before anything changes. You also get per-page tracking, so you know exactly which scripts load where.

= What does the "Inner-Page Only" badge mean? =

After a full site crawl, scripts that appear on inner pages but NOT on the homepage get this badge. These are typically scripts loaded by plugins that only run on specific pages — like WooCommerce on shop/cart pages, or a contact form plugin on your contact page. This helps you identify scripts you might safely defer without affecting your homepage loading speed.

= What happens when I uninstall the plugin? =

All plugin data is removed from the database on uninstall, including on multisite installations. No orphaned data is left behind.

== Screenshots ==

1. Main dashboard showing the master toggle, URL scanner, and "Crawl Entire Site" button
2. Script discovery table with per-script strategy dropdowns, type badges, page counts, and filter tabs
3. Stats dashboard showing script counts and pages scanned
4. Loading strategy reference cards with detailed guidance built into the admin page

== Changelog ==

= 2.3.0 =
* **Full site crawl** — discover scripts across your entire site with one click (pages, posts, categories)
* **Per-page script tracking** — see how many pages each script appears on with hover tooltip
* **Inner-page-only detection** — badge highlights scripts not found on the homepage
* **Inner-Page Only filter tab** — quickly filter to scripts unique to inner pages
* **Expanded strategy definitions** — detailed "When to use", "Impact", and "Caution" for each strategy
* **Crawl progress bar** — animated progress indicator during site crawls
* **Pages Scanned stat** — stats bar shows crawl coverage
* Plugin Check compliance — inline nonce verification, sanitized inputs, wp_parse_url()

= 2.2.0 =
* Complete UI/UX redesign with modern card layout and CSS custom properties
* Color-coded strategy dropdowns with change highlighting
* Live search and filter tabs (All, Controllable, External, Protected)
* Stats dashboard with real-time counters
* Branding footer with Think Above AI
* Moved to top-level admin menu with speedometer icon

= 2.1.1 =
* Initial public release
* Visual script discovery via URL scanning
* Per-script defer/async/none controls
* Protected scripts (jQuery core, jQuery Migrate)
* Multi-page scan merging
* Bulk actions (Defer All, Reset All)
* Master on/off kill switch
* Native WordPress admin UI
* Clean uninstall support

== Upgrade Notice ==

= 2.3.0 =
Major update: Full site crawl, per-page script tracking, inner-page-only detection, and expanded strategy reference. Update recommended.

= 2.2.0 =
Complete UI redesign with modern interface, filter tabs, live search, and stats dashboard.

= 2.1.1 =
Initial release. Install to start optimising your script loading strategy with full visibility and control.
