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

A plugin that allows you to defer, async and ignore scripts with a simple interface and powerful results. More control with ease of use.

== Description ==

**Site Scripts & Speed Manager** gives you full visibility and control over every JavaScript file loading on your WordPress site.

Most performance plugins offer a blanket "defer all scripts" checkbox — then you spend hours figuring out what broke. This plugin takes a different approach:

1. **Scan any page** on your site to discover every script
2. **See exactly** what each script is, where it comes from, and how it currently loads
3. **Choose per-script**: Defer, Async, or None (ignore)
4. **Master kill switch** — if anything breaks, flip it off instantly. No cache clearing needed.

= Key Features =

* **Visual Script Discovery** — Scan any URL on your site and see every script in a clean table with handles, source URLs, types, and current loading status
* **Per-Script Control** — Dropdown for each script: Defer, Async, or None
* **Protected Scripts** — jQuery and jQuery Migrate are automatically protected and cannot be deferred (deferring these breaks most WordPress sites)
* **Script Type Badges** — Instantly see which scripts are WordPress-enqueued (controllable), external/hardcoded (display only), or protected
* **Multi-Page Scanning** — Scan multiple pages and results merge, so you catch page-specific scripts
* **Bulk Actions** — "All Controllable → Defer" and "All → None" buttons for quick setup
* **Master On/Off Toggle** — Instant kill switch removes all modifications without touching settings
* **Clean UI** — Native WordPress admin styling, color-coded dropdowns, change highlighting
* **Zero Bloat** — No tracking, no external API calls, no ads, no upsells

= How It Works =

Scripts load in three ways:

* **None (Default)** — Script downloads and runs immediately, blocking page rendering. Required for jQuery and critical scripts.
* **Defer** — Downloads in parallel with HTML parsing, executes after the page is fully parsed. Maintains execution order. Best for most scripts.
* **Async** — Downloads in parallel, executes as soon as ready (no guaranteed order). Best for independent scripts like analytics and tracking.

= Who Is This For? =

* **WordPress developers** who want granular script control without code
* **Site owners** looking to improve Core Web Vitals and page speed scores
* **Agencies** managing multiple client sites that need per-site optimization
* **Anyone** tired of blindly deferring scripts and breaking things

== Installation ==

1. Upload the `site-scripts-speed-manager` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Scripts & Speed** in the admin menu
4. Enter a URL and click **Scan Scripts**
5. Choose Defer, Async, or None for each script
6. Click **Save Settings**
7. Turn on the **Master Switch** to activate

== Frequently Asked Questions ==

= Will this break my site? =

The plugin comes with safety features:

* jQuery and jQuery Migrate are **protected by default** — they cannot be deferred
* The **master kill switch** lets you instantly disable all modifications
* Settings are preserved when you turn off — just flip the switch back on when ready

= Can I use this with caching plugins? =

Yes. This plugin modifies script tags at the WordPress level, which happens before any page caching. It works alongside WP Rocket, W3 Total Cache, LiteSpeed Cache, Breeze, and others.

= What about scripts added by page builders? =

Scripts enqueued via `wp_enqueue_script()` (the WordPress way) are fully controllable. Hardcoded scripts injected directly into templates are shown in the scan results but marked as "Hardcoded" — they need to be modified at their source.

= Does this phone home or track anything? =

No. Zero external calls, zero tracking, zero analytics. The scanner fetches pages from your own server using `wp_remote_get()`.

= How is this different from WP Rocket or Autoptimize? =

Those plugins offer blanket defer/async options with a textarea for exclusions. You defer everything, then add exclusions when things break. This plugin lets you **see every script first** and make informed decisions per-script before anything changes.

== Screenshots ==

1. Main dashboard with master toggle and URL scanner
2. Script discovery table with per-script defer/async controls
3. Loading strategies explained — built-in reference

== Changelog ==

= 2.1.1 =
* Initial public release
* Visual script discovery via URL scanning
* Per-script defer/async/none controls
* Protected scripts (jQuery core, jQuery Migrate)
* Multi-page scan merging
* Bulk actions (All → Defer, All → None)
* Master on/off kill switch
* Native WordPress admin UI
* Clean uninstall support

== Upgrade Notice ==

= 2.1.1 =
Initial release. Install to start optimising your script loading strategy with full visibility and control.
