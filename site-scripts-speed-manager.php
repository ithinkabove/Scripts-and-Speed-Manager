<?php
/**
 * Plugin Name: Site Scripts & Speed Manager
 * Plugin URI:  https://thinkabove.ai/plugins/site-scripts-speed-manager
 * Description: A plugin that allows you to defer, async and ignore scripts with a simple interface and powerful results. More control with ease of use.
 * Version:     2.1.1
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

    const VERSION    = '2.1.1';
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
        add_options_page(
            'Site Scripts & Speed Manager',
            'Scripts & Speed',
            'manage_options',
            self::SLUG,
            [$this, 'render_page']
        );
    }

    public function enqueue_admin(string $hook): void {
        if ($hook !== 'settings_page_' . self::SLUG) {
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
        $this->verify();

        $url = esc_url_raw(wp_unslash($_POST['url'] ?? ''));
        if (!$url) {
            wp_send_json_error('Please enter a URL.');
        }

        $res = wp_remote_get($url, [
            'timeout'   => 30,
            'sslverify' => false,
            'headers'   => ['User-Agent' => 'SiteScriptsSpeedManager/' . self::VERSION],
        ]);

        if (is_wp_error($res)) {
            wp_send_json_error('Fetch failed: ' . $res->get_error_message());
        }

        $html    = wp_remote_retrieve_body($res);
        $scripts = [];

        // Match every <script … src="…" …>
        preg_match_all(
            '/<script\b([^>]*?)\bsrc=["\']([^"\']+)["\']([^>]*)>/i',
            $html,
            $m,
            PREG_SET_ORDER
        );

        foreach ($m as $hit) {
            $attrs = $hit[1] . ' ' . $hit[3];
            $src   = html_entity_decode($hit[2], ENT_QUOTES, 'UTF-8');

            // Extract WP handle from id="handle-js"
            $handle = '';
            if (preg_match('/\bid=["\']([^"\']+?)-js["\']/', $attrs, $id)) {
                $handle = $id[1];
            }

            // Detect existing attributes
            $has_defer  = (bool) preg_match('/\bdefer\b/i', $attrs);
            $has_async  = (bool) preg_match('/\basync\b/i', $attrs);
            $has_module = (bool) preg_match('/type=["\']module["\']/', $attrs);

            // Clean URL for hashing (strip query / fragment)
            $clean = preg_replace('/[?#].*/', '', $src);
            $key   = $handle ?: md5($clean);

            // Classify
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
                'handle'       => $handle ?: basename(parse_url($src, PHP_URL_PATH)),
                'src'          => $src,
                'type'         => $type,
                'has_defer'    => $has_defer,
                'has_async'    => $has_async,
                'has_module'   => $has_module,
                'protected'    => in_array($key, self::PROTECTED_HANDLES, true),
                'controllable' => (bool) $handle,
            ];
        }

        wp_send_json_success([
            'scripts' => array_values($scripts),
            'url'     => $url,
            'total'   => count($scripts),
        ]);
    }

    /* ================================================================== */
    /*  AJAX — Save script strategies                                      */
    /* ================================================================== */

    public function ajax_save(): void {
        $this->verify();

        $raw = json_decode(wp_unslash($_POST['scripts'] ?? '{}'), true);
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
        $this->verify();

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
        ?>
        <div class="wrap" id="sssm-wrap">
            <h1><span class="dashicons dashicons-performance" style="font-size:28px;vertical-align:middle;margin-right:6px;"></span> Site Scripts & Speed Manager</h1>
            <p class="description">Defer, async and ignore scripts with a simple interface and powerful results.</p>

            <!-- ─── Master toggle ─── -->
            <div class="sssm-card sssm-toggle-card <?php echo $s['enabled'] ? 'on' : ''; ?>">
                <div class="sssm-flex">
                    <div>
                        <h2 style="margin:0">Script Optimization</h2>
                        <p class="description" style="margin:4px 0 0">Master switch — turning off removes all modifications instantly.</p>
                    </div>
                    <label class="sssm-switch">
                        <input type="checkbox" id="sssm-master" <?php checked($s['enabled']); ?>>
                        <span class="slider"></span>
                        <span class="lbl" id="sssm-master-label"><?php echo $s['enabled'] ? 'Active' : 'Inactive'; ?></span>
                    </label>
                </div>
            </div>

            <!-- ─── Scanner ─── -->
            <div class="sssm-card">
                <h2>Scan Page</h2>
                <p class="description">Enter any URL on your site. Scripts from multiple scans are merged automatically.</p>
                <div class="sssm-scan-row">
                    <input id="sssm-url" type="url" class="regular-text"
                           value="<?php echo esc_attr(home_url('/')); ?>"
                           placeholder="<?php echo esc_attr(home_url('/')); ?>">
                    <button id="sssm-scan" class="button button-primary">
                        <span class="dashicons dashicons-search" style="vertical-align:middle;margin-top:-2px;"></span> Scan Scripts
                    </button>
                    <span class="spinner" id="sssm-spin"></span>
                </div>
                <div id="sssm-msg"></div>
            </div>

            <!-- ─── Results ─── -->
            <div class="sssm-card" id="sssm-results" style="display:none">
                <div class="sssm-flex" style="margin-bottom:12px;">
                    <h2 style="margin:0">Scripts <span class="sssm-pill" id="sssm-n"></span></h2>
                    <div class="sssm-actions">
                        <button class="button" id="sssm-all-defer">All Controllable → Defer</button>
                        <button class="button" id="sssm-all-none">All → None</button>
                        <button class="button button-primary" id="sssm-save">
                            <span class="dashicons dashicons-saved" style="vertical-align:middle;margin-top:-2px;"></span> Save Settings
                        </button>
                    </div>
                </div>

                <div class="sssm-legend">
                    <span class="badge b-lock">🔒 Protected</span> Cannot be deferred
                    &nbsp;&nbsp;
                    <span class="badge b-wp">WP</span> WordPress-enqueued (controllable)
                    &nbsp;&nbsp;
                    <span class="badge b-ext">EXT</span> External / hardcoded (display only)
                </div>

                <table class="widefat fixed striped" id="sssm-table">
                    <thead>
                        <tr>
                            <th style="width:200px">Handle</th>
                            <th>Source URL</th>
                            <th style="width:80px">Type</th>
                            <th style="width:90px">Current</th>
                            <th style="width:160px">Strategy</th>
                        </tr>
                    </thead>
                    <tbody id="sssm-tbody">
                        <tr><td colspan="5" style="text-align:center;color:#646970;">Scan a page to discover scripts&hellip;</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- ─── Legend ─── -->
            <div class="sssm-card sssm-muted">
                <h3 style="margin-top:0">Loading Strategies Explained</h3>
                <table class="sssm-info">
                    <tr>
                        <td><strong>None</strong></td>
                        <td>Default browser behaviour — script downloads and runs immediately, blocking page rendering. Use for jQuery and critical scripts.</td>
                    </tr>
                    <tr>
                        <td><strong>Defer</strong></td>
                        <td>Downloads in parallel with HTML parsing, executes <em>after</em> the page is fully parsed. Maintains execution order between deferred scripts. <strong>Best for most scripts.</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Async</strong></td>
                        <td>Downloads in parallel, executes as soon as it's ready (no guaranteed order). <strong>Best for independent scripts</strong> like analytics &amp; tracking pixels.</td>
                    </tr>
                </table>
                <p class="description" style="margin-top:12px;">
                    <strong>⚠️ Tip:</strong> If anything breaks, flip the master switch off — all modifications are removed instantly, no cache clearing needed.
                </p>
            </div>
        </div>
        <?php
    }

    /* ================================================================== */
    /*  Internal helpers                                                   */
    /* ================================================================== */

    private function verify(): void {
        check_ajax_referer('sssm', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }
    }
}

new SiteScriptsSpeedManager();
