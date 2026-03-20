<?php
/**
 * Plugin Name: Site Scripts & Speed Manager
 * Plugin URI:  https://thinkabove.ai
 * Description: A plugin that allows you to defer, async and ignore scripts with a simple interface and powerful results. More control with ease of use.
 * Version:     2.3.0
 * Author:      Think Above AI
 * Author URI:  https://thinkabove.ai
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: site-scripts-speed-manager
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

final class SiteScriptsSpeedManager {

    const VERSION    = '2.3.0';
    const OPTION_KEY = 'sssm_settings';
    const SLUG       = 'site-scripts-speed-manager';

    /**
     * Handles that must NEVER be modified — deferring breaks WordPress.
     */
    const PROTECTED_HANDLES = [
        'jquery-core',
        'jquery',
        'jquery-migrate',
    ];

    /** @var array|null Cached settings */
    private $settings;

    /* ================================================================== */
    /*  Bootstrap                                                          */
    /* ================================================================== */

    public function __construct() {
        // Admin
        add_action('admin_menu',            [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);

        // AJAX endpoints
        add_action('wp_ajax_sssm_scan',   [$this, 'ajax_scan']);
        add_action('wp_ajax_sssm_save',   [$this, 'ajax_save']);
        add_action('wp_ajax_sssm_toggle', [$this, 'ajax_toggle']);
        add_action('wp_ajax_sssm_crawl',  [$this, 'ajax_crawl']);

        // Front-end modifier — only when active & not admin
        if ($this->enabled() && !is_admin()) {
            add_filter('script_loader_tag', [$this, 'modify_tag'], 99, 3);
        }
    }

    /* ================================================================== */
    /*  Settings helpers                                                    */
    /* ================================================================== */

    private function defaults(): array {
        return [
            'enabled' => false,
            'scripts' => [],   // handle => defer | async | none
        ];
    }

    private function get(): array {
        if ($this->settings === null) {
            $this->settings = wp_parse_args(
                get_option(self::OPTION_KEY, []),
                $this->defaults()
            );
        }
        return $this->settings;
    }

    private function set(array $data): void {
        $this->settings = wp_parse_args($data, $this->get());
        update_option(self::OPTION_KEY, $this->settings, false);
    }

    private function enabled(): bool {
        return !empty($this->get()['enabled']);
    }

    /* ================================================================== */
    /*  Admin menu & assets                                                */
    /* ================================================================== */

    public function add_menu(): void {
        add_menu_page(
            'Site Scripts & Speed Manager',
            'Scripts & Speed',
            'manage_options',
            self::SLUG,
            [$this, 'render_page'],
            'dashicons-performance',
            81
        );
    }

    public function enqueue_admin(string $hook): void {
        if ($hook !== 'toplevel_page_' . self::SLUG) {
            return;
        }

        $base = plugin_dir_url(__FILE__) . 'assets/';
        wp_enqueue_style(self::SLUG,  $base . 'admin.css', [], self::VERSION);
        wp_enqueue_script(self::SLUG, $base . 'admin.js', ['jquery'], self::VERSION, true);
        wp_localize_script(self::SLUG, 'SSSM', [
            'ajax'      => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('sssm'),
            'home'      => home_url('/'),
            'settings'  => $this->get(),
            'protected' => self::PROTECTED_HANDLES,
        ]);
    }

    /* ================================================================== */
    /*  AJAX — Scan a URL for scripts                                      */
    /* ================================================================== */

    public function ajax_scan(): void {
        check_ajax_referer('sssm', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $url = esc_url_raw(wp_unslash($_POST['url'] ?? ''));
        if (!$url) {
            wp_send_json_error('Please enter a URL.');
        }

        $scripts = $this->scan_url($url);
        if (is_wp_error($scripts)) {
            wp_send_json_error('Fetch failed: ' . $scripts->get_error_message());
        }

        wp_send_json_success([
            'scripts' => array_values($scripts),
            'url'     => $url,
            'total'   => count($scripts),
        ]);
    }

    /* ================================================================== */
    /*  AJAX — Crawl entire site for scripts                               */
    /* ================================================================== */

    public function ajax_crawl(): void {
        check_ajax_referer('sssm', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        // Gather URLs to scan
        $urls = $this->discover_site_urls();

        $all_scripts  = [];   // key => script data
        $page_map     = [];   // key => [ list of page URLs ]
        $pages_scanned = 0;
        $pages_failed  = 0;
        $home_scripts  = [];  // keys found on the homepage

        foreach ($urls as $i => $url) {
            $scripts = $this->scan_url($url);
            if (is_wp_error($scripts)) {
                $pages_failed++;
                continue;
            }
            $pages_scanned++;

            foreach ($scripts as $key => $s) {
                if (!isset($all_scripts[$key])) {
                    $all_scripts[$key] = $s;
                    $page_map[$key]    = [];
                }
                $page_map[$key][] = $url;
            }

            // Track homepage scripts
            if ($url === home_url('/') || $url === home_url('')) {
                $home_scripts = array_keys($scripts);
            }
        }

        // Mark which scripts are NOT on the homepage (unique to inner pages)
        foreach ($all_scripts as $key => &$s) {
            $s['pages']        = $page_map[$key] ?? [];
            $s['page_count']   = count($s['pages']);
            $s['homepage']     = in_array($key, $home_scripts, true);
            $s['unique_pages'] = !in_array($key, $home_scripts, true);
        }
        unset($s);

        wp_send_json_success([
            'scripts'        => array_values($all_scripts),
            'total'          => count($all_scripts),
            'pages_scanned'  => $pages_scanned,
            'pages_failed'   => $pages_failed,
            'pages_total'    => count($urls),
            'urls'           => $urls,
        ]);
    }

    /* ================================================================== */
    /*  Internal: Scan a single URL and return scripts                      */
    /* ================================================================== */

    private function scan_url(string $url): array|\WP_Error {
        $res = wp_remote_get($url, [
            'timeout'   => 30,
            'sslverify' => false,
            'headers'   => ['User-Agent' => 'SiteScriptsSpeedManager/' . self::VERSION],
        ]);

        if (is_wp_error($res)) {
            return $res;
        }

        $html    = wp_remote_retrieve_body($res);
        $scripts = [];

        preg_match_all(
            '/<script\b([^>]*?)\bsrc=["\']([^"\']+)["\']([^>]*)>/i',
            $html,
            $m,
            PREG_SET_ORDER
        );

        foreach ($m as $hit) {
            $attrs = $hit[1] . ' ' . $hit[3];
            $src   = html_entity_decode($hit[2], ENT_QUOTES, 'UTF-8');

            $handle = '';
            if (preg_match('/\bid=["\']([^"\']+?)-js["\']/', $attrs, $id)) {
                $handle = $id[1];
            }

            $has_defer  = (bool) preg_match('/\bdefer\b/i', $attrs);
            $has_async  = (bool) preg_match('/\basync\b/i', $attrs);
            $has_module = (bool) preg_match('/type=["\']module["\']/', $attrs);

            $clean = preg_replace('/[?#].*/', '', $src);
            $key   = $handle ?: md5($clean);

            $is_wp_asset = $handle
                || strpos($src, '/wp-includes/') !== false
                || strpos($src, '/wp-content/')  !== false;

            $type = 'external';
            if ($handle) {
                $type = 'enqueued';
            } elseif ($is_wp_asset) {
                $type = 'wordpress';
            }

            $scripts[$key] = [
                'key'          => $key,
                'handle'       => $handle ?: basename(wp_parse_url($src, PHP_URL_PATH)),
                'src'          => $src,
                'type'         => $type,
                'has_defer'    => $has_defer,
                'has_async'    => $has_async,
                'has_module'   => $has_module,
                'protected'    => in_array($key, self::PROTECTED_HANDLES, true),
                'controllable' => (bool) $handle,
            ];
        }

        return $scripts;
    }

    /* ================================================================== */
    /*  Internal: Discover site URLs to crawl                              */
    /* ================================================================== */

    private function discover_site_urls(): array {
        $urls = [home_url('/')];

        // Get published pages
        $pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ]);
        foreach ($pages as $p) {
            $urls[] = get_permalink($p);
        }

        // Get recent posts (up to 10)
        $posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        foreach ($posts as $p) {
            $urls[] = get_permalink($p);
        }

        // Add common archive/taxonomy pages
        $cats = get_categories(['number' => 5, 'hide_empty' => true]);
        foreach ($cats as $cat) {
            $urls[] = get_category_link($cat->term_id);
        }

        // Deduplicate
        $urls = array_unique(array_filter($urls));

        return array_values($urls);
    }

    /* ================================================================== */
    /*  AJAX — Save script strategies                                      */
    /* ================================================================== */

    public function ajax_save(): void {
        check_ajax_referer('sssm', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $raw = json_decode(sanitize_text_field(wp_unslash($_POST['scripts'] ?? '{}')), true);
        if (!is_array($raw)) {
            wp_send_json_error('Bad payload');
        }

        $clean   = [];
        $allowed = ['defer', 'async', 'none'];

        foreach ($raw as $key => $val) {
            $key = sanitize_text_field($key);
            if (in_array($key, self::PROTECTED_HANDLES, true)) {
                continue;
            }
            $clean[$key] = in_array($val, $allowed, true) ? $val : 'none';
        }

        $s = $this->get();
        // Merge — keep existing settings for scripts not in current scan
        $s['scripts'] = array_merge($s['scripts'], $clean);
        $this->set($s);

        $active = count(array_filter($s['scripts'], function ($v) {
            return $v !== 'none';
        }));

        wp_send_json_success([
            'message' => 'Settings saved',
            'count'   => $active,
        ]);
    }

    /* ================================================================== */
    /*  AJAX — Toggle master switch                                        */
    /* ================================================================== */

    public function ajax_toggle(): void {
        check_ajax_referer('sssm', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $on = !empty($_POST['enabled']);
        $s  = $this->get();
        $s['enabled'] = $on;
        $this->set($s);

        wp_send_json_success([
            'enabled' => $on,
            'label'   => $on ? 'Active' : 'Inactive',
        ]);
    }

    /* ================================================================== */
    /*  Front-end: modify enqueued <script> tags                           */
    /* ================================================================== */

    public function modify_tag(string $tag, string $handle, string $src): string {
        $scripts = $this->get()['scripts'];

        if (!isset($scripts[$handle])) {
            return $tag;
        }

        $strategy = $scripts[$handle];

        if ($strategy === 'none') {
            return $tag;
        }
        if (in_array($handle, self::PROTECTED_HANDLES, true)) {
            return $tag;
        }

        // Don't double-add
        if ($strategy === 'defer' && strpos($tag, 'defer') !== false) {
            return $tag;
        }
        if ($strategy === 'async' && strpos($tag, 'async') !== false) {
            return $tag;
        }

        // Strip any existing defer/async, then add the chosen one
        $tag = preg_replace('/\s+(defer|async)\b/', '', $tag);
        $tag = str_replace('<script ', '<script ' . esc_attr($strategy) . ' ', $tag);

        return $tag;
    }

    /* ================================================================== */
    /*  Admin page                                                         */
    /* ================================================================== */

    public function render_page(): void {
        $s = $this->get();
        $active_count = count(array_filter($s['scripts'], function ($v) { return $v !== 'none'; }));
        ?>
        <div class="wrap" id="sssm-wrap">

            <!-- ─── Page Header ─── -->
            <div class="sssm-page-header">
                <div class="sssm-icon"><span class="dashicons dashicons-performance"></span></div>
                <h1>Scripts &amp; Speed Manager</h1>
            </div>
            <p class="sssm-page-subtitle">Scan, analyze, and optimize how JavaScript loads on your site — per script, with one click.</p>

            <!-- ─── Master Toggle ─── -->
            <div class="sssm-card sssm-toggle-card <?php echo $s['enabled'] ? 'on' : ''; ?>">
                <div class="sssm-flex">
                    <div>
                        <h2 style="margin:0"><span class="sssm-status-dot"></span> Script Optimization</h2>
                        <p class="description" style="margin:4px 0 0">
                            Master switch — when off, all scripts load normally. <?php if ($active_count > 0): ?><strong><?php echo esc_html($active_count); ?> script(s)</strong> with custom strategies.<?php endif; ?>
                        </p>
                    </div>
                    <label class="sssm-switch">
                        <input type="checkbox" id="sssm-master" <?php checked($s['enabled']); ?>>
                        <span class="slider"></span>
                        <span class="lbl" id="sssm-master-label"><?php echo $s['enabled'] ? 'Active' : 'Inactive'; ?></span>
                    </label>
                </div>
            </div>

            <!-- ─── Scanner ─── -->
            <div class="sssm-card sssm-scan-card">
                <div class="sssm-card-header">
                    <div class="sssm-card-icon"><span class="dashicons dashicons-search"></span></div>
                    <div>
                        <h2 style="margin:0">Scan Scripts</h2>
                        <p class="description" style="margin:2px 0 0">Scan a single page or crawl your entire site. Results always merge automatically.</p>
                    </div>
                </div>
                <div class="sssm-scan-row">
                    <input id="sssm-url" type="url"
                           value="<?php echo esc_attr(home_url('/')); ?>"
                           placeholder="<?php echo esc_attr(home_url('/')); ?>">
                    <button id="sssm-scan" class="sssm-btn sssm-btn-primary">
                        <span class="dashicons dashicons-search" style="font-size:16px;width:16px;height:16px;"></span>
                        Scan Page
                    </button>
                    <button id="sssm-crawl" class="sssm-btn sssm-btn-secondary">
                        <span class="dashicons dashicons-admin-site-alt3" style="font-size:16px;width:16px;height:16px;"></span>
                        Crawl Entire Site
                    </button>
                    <span class="spinner" id="sssm-spin"></span>
                </div>
                <div id="sssm-crawl-progress" style="display:none;"></div>
                <div id="sssm-msg"></div>
            </div>

            <!-- ─── Results ─── -->
            <div class="sssm-card" id="sssm-results" style="display:none">

                <!-- Stats bar -->
                <div class="sssm-stats" id="sssm-stats"></div>

                <!-- Header with actions -->
                <div class="sssm-results-header">
                    <div class="sssm-results-title">
                        <h2 style="margin:0">Discovered Scripts</h2>
                        <span class="sssm-pill" id="sssm-n"></span>
                    </div>
                    <div class="sssm-actions">
                        <button class="sssm-btn sssm-btn-secondary" id="sssm-all-defer">
                            <span class="dashicons dashicons-controls-forward" style="font-size:14px;width:14px;height:14px;"></span>
                            Defer All
                        </button>
                        <button class="sssm-btn sssm-btn-secondary" id="sssm-all-none">
                            <span class="dashicons dashicons-dismiss" style="font-size:14px;width:14px;height:14px;"></span>
                            Reset All
                        </button>
                        <button class="sssm-btn sssm-btn-success" id="sssm-save">
                            <span class="dashicons dashicons-saved" style="font-size:14px;width:14px;height:14px;"></span>
                            Save Settings
                        </button>
                    </div>
                </div>

                <!-- Filter tabs -->
                <div class="sssm-filters" id="sssm-filters"></div>

                <!-- Search + legend row -->
                <div class="sssm-table-controls">
                    <div class="sssm-search-wrap">
                        <span class="dashicons dashicons-search"></span>
                        <input type="text" id="sssm-search" placeholder="Filter scripts by name or URL&hellip;">
                    </div>
                    <div class="sssm-legend">
                        <span class="sssm-legend-item"><span class="badge b-lock">🔒</span> Protected</span>
                        <span class="sssm-legend-item"><span class="badge b-wp">WP</span> Controllable</span>
                        <span class="sssm-legend-item"><span class="badge b-ext">EXT</span> Hardcoded</span>
                    </div>
                </div>

                <table id="sssm-table">
                    <thead>
                        <tr>
                            <th style="width:190px">Handle</th>
                            <th>Source</th>
                            <th style="width:70px">Type</th>
                            <th style="width:80px">Current</th>
                            <th style="width:70px">Pages</th>
                            <th style="width:140px">Strategy</th>
                        </tr>
                    </thead>
                    <tbody id="sssm-tbody">
                        <tr>
                            <td colspan="6">
                                <div class="sssm-empty-state">
                                    <span class="dashicons dashicons-media-code"></span>
                                    <p>Scan a page or crawl your site to discover scripts</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ─── Loading Strategies ─── -->
            <div class="sssm-card sssm-muted">
                <h2 style="margin:0 0 4px">Loading Strategies</h2>
                <p class="description" style="margin:0 0 16px">Understanding how each strategy affects script loading and page performance.</p>

                <div class="sssm-strategies">
                    <div class="sssm-strategy-card sssm-strat-none">
                        <h4>
                            <span class="sssm-strategy-icon">—</span>
                            None (Default)
                        </h4>
                        <p>The browser downloads and executes the script <strong>immediately</strong>, pausing all HTML parsing until it finishes. This blocks page rendering — nothing below the script loads until it completes.</p>
                        <div class="sssm-strategy-detail">
                            <strong>When to use:</strong> jQuery, jQuery Migrate, and any script that other scripts depend on to be available immediately. If a script must run before the page renders (e.g., critical above-the-fold functionality), leave it as None.
                        </div>
                        <div class="sssm-strategy-detail">
                            <strong>Impact:</strong> Slower initial page load, but guarantees script execution order and availability.
                        </div>
                    </div>
                    <div class="sssm-strategy-card sssm-strat-defer">
                        <h4>
                            <span class="sssm-strategy-icon">D</span>
                            Defer
                            <span class="sssm-strategy-badge">Recommended</span>
                        </h4>
                        <p>The browser downloads the script <strong>in parallel</strong> with HTML parsing (no blocking), then executes it <strong>after</strong> the entire document is parsed. Multiple deferred scripts maintain their original order.</p>
                        <div class="sssm-strategy-detail">
                            <strong>When to use:</strong> Most scripts — sliders, form handlers, UI enhancements, WooCommerce scripts, page builders, popup plugins, and anything that interacts with the DOM after it's ready. This is the safest performance optimization for the majority of scripts.
                        </div>
                        <div class="sssm-strategy-detail">
                            <strong>Impact:</strong> Significantly faster page rendering. Users see content sooner. Scripts still run in order, so dependencies between deferred scripts are preserved.
                        </div>
                    </div>
                    <div class="sssm-strategy-card sssm-strat-async">
                        <h4>
                            <span class="sssm-strategy-icon">A</span>
                            Async
                        </h4>
                        <p>The browser downloads the script <strong>in parallel</strong> (no blocking), then executes it <strong>as soon as it finishes downloading</strong> — even if the HTML isn't fully parsed yet. Execution order is <strong>not guaranteed</strong>.</p>
                        <div class="sssm-strategy-detail">
                            <strong>When to use:</strong> Only for fully independent scripts that don't depend on other scripts or the DOM being ready — Google Analytics, Meta Pixel, ad tags, tracking pixels, chat widgets, and similar third-party embeds.
                        </div>
                        <div class="sssm-strategy-detail">
                            <strong>⚠️ Caution:</strong> If a script depends on jQuery or another library, using Async can break it because the dependency may not have loaded yet. When in doubt, use Defer instead.
                        </div>
                    </div>
                </div>

                <div class="sssm-tip">
                    <span class="sssm-tip-icon">💡</span>
                    <div><strong>Quick tip:</strong> If anything breaks after optimizing, just flip the master switch off. All modifications are removed instantly — no cache clearing needed.</div>
                </div>
            </div>

            <!-- ─── Footer / Branding ─── -->
            <div class="sssm-footer">
                <span>Built by</span>
                <a href="https://thinkabove.ai" target="_blank" rel="noopener noreferrer">
                    <svg class="sssm-footer-logo" viewBox="0 0 140 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <text x="0" y="17" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="15" font-weight="700" fill="#6b7280">Think Above</text>
                        <text x="110" y="17" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="15" font-weight="700" fill="#2563eb"> AI</text>
                    </svg>
                </a>
                <span class="sssm-footer-sep">·</span>
                <a href="https://thinkabove.ai" target="_blank" rel="noopener noreferrer">thinkabove.ai</a>
                <span class="sssm-footer-sep">·</span>
                <span>v<?php echo esc_html(self::VERSION); ?></span>
            </div>
        </div>
        <?php
    }

    /* ================================================================== */
    /*  Internal helpers                                                   */
    /* ================================================================== */
}

new SiteScriptsSpeedManager();
