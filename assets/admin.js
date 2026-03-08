/**
 * Site Scripts & Speed Manager — Admin JavaScript
 * Handles scanning, table rendering, filtering, search, and AJAX save/toggle.
 *
 * @package SiteScriptsSpeedManager
 * @version 2.2.0
 * @author  Think Above AI
 */
(function ($) {
    'use strict';

    var saved   = SSSM.settings.scripts || {};
    var scanned = [];
    var activeFilter = 'all';

    /* ================================================================== */
    /*  Master toggle                                                      */
    /* ================================================================== */

    $('#sssm-master').on('change', function () {
        var on    = $(this).is(':checked');
        var $card = $('.sssm-toggle-card');
        var $lbl  = $('#sssm-master-label');

        $card.toggleClass('on', on);
        $lbl.text(on ? 'Active' : 'Inactive');

        $.post(SSSM.ajax, {
            action:  'sssm_toggle',
            nonce:   SSSM.nonce,
            enabled: on ? 1 : 0
        }, function (r) {
            if (r.success) {
                notice(
                    on ? '✓ Script optimizations are now <strong>active</strong> on the front-end.'
                       : 'Script optimizations <strong>disabled</strong> — all scripts loading normally.',
                    'ok'
                );
            }
        });
    });

    // Init state
    if ($('#sssm-master').is(':checked')) {
        $('.sssm-toggle-card').addClass('on');
    }

    /* ================================================================== */
    /*  Scan                                                               */
    /* ================================================================== */

    $('#sssm-scan').on('click', function () {
        var url = $('#sssm-url').val() || SSSM.home;
        scan(url);
    });

    $('#sssm-url').on('keypress', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#sssm-scan').trigger('click');
        }
    });

    function scan(url) {
        var $btn = $('#sssm-scan');
        var $spin = $('#sssm-spin');

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="font-size:16px;width:16px;height:16px;animation:rotation 1s linear infinite;"></span> Scanning&hellip;');
        $spin.addClass('is-active');
        notice('', 'clear');

        $.post(SSSM.ajax, {
            action: 'sssm_scan',
            nonce:  SSSM.nonce,
            url:    url
        }, function (r) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="font-size:16px;width:16px;height:16px;"></span> Scan Scripts');
            $spin.removeClass('is-active');

            if (r.success) {
                scanned = r.data.scripts;
                buildStats(scanned);
                buildFilters(scanned);
                buildTable(scanned);
                notice('Found <strong>' + r.data.total + '</strong> scripts on <code>' + esc(r.data.url) + '</code>', 'ok');
                $('#sssm-results').slideDown(300);
            } else {
                notice(r.data || 'Scan failed', 'err');
            }
        }).fail(function () {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="font-size:16px;width:16px;height:16px;"></span> Scan Scripts');
            $spin.removeClass('is-active');
            notice('Request failed — check your connection.', 'err');
        });
    }

    /* ================================================================== */
    /*  Stats                                                              */
    /* ================================================================== */

    function buildStats(list) {
        var total = list.length;
        var controllable = list.filter(function (s) { return s.controllable && !s['protected']; }).length;
        var deferred = 0, asynced = 0;

        list.forEach(function (s) {
            var strat = saved[s.key] || 'none';
            if (strat === 'defer') deferred++;
            if (strat === 'async') asynced++;
        });

        var ext = list.filter(function (s) { return s.type === 'external'; }).length;

        $('#sssm-stats').html(
            '<div class="sssm-stat"><div><div class="sssm-stat-num">' + total + '</div><div class="sssm-stat-label">Total Scripts</div></div></div>' +
            '<div class="sssm-stat"><div><div class="sssm-stat-num">' + controllable + '</div><div class="sssm-stat-label">Controllable</div></div></div>' +
            '<div class="sssm-stat"><div><div class="sssm-stat-num">' + deferred + '</div><div class="sssm-stat-label">Deferred</div></div></div>' +
            '<div class="sssm-stat"><div><div class="sssm-stat-num">' + asynced + '</div><div class="sssm-stat-label">Async</div></div></div>' +
            '<div class="sssm-stat"><div><div class="sssm-stat-num">' + ext + '</div><div class="sssm-stat-label">External</div></div></div>'
        );
    }

    /* ================================================================== */
    /*  Filters                                                            */
    /* ================================================================== */

    function buildFilters(list) {
        var counts = { all: list.length, enqueued: 0, external: 0, protected: 0 };

        list.forEach(function (s) {
            if (s['protected']) counts['protected']++;
            else if (s.type === 'enqueued') counts.enqueued++;
            else if (s.type === 'external') counts.external++;
            else if (s.type === 'wordpress') counts.enqueued++;
        });

        var html = '';
        var tabs = [
            { key: 'all', label: 'All' },
            { key: 'enqueued', label: 'Controllable' },
            { key: 'external', label: 'External' },
            { key: 'protected', label: 'Protected' }
        ];

        tabs.forEach(function (t) {
            if (counts[t.key] === 0 && t.key !== 'all') return;
            html += '<button class="sssm-filter-btn' + (activeFilter === t.key ? ' active' : '') + '" data-filter="' + t.key + '">' +
                    t.label + '<span class="sssm-filter-count">(' + counts[t.key] + ')</span></button>';
        });

        $('#sssm-filters').html(html);

        $('#sssm-filters').off('click').on('click', '.sssm-filter-btn', function () {
            activeFilter = $(this).data('filter');
            $(this).addClass('active').siblings().removeClass('active');
            applyFilters();
        });
    }

    function applyFilters() {
        var search = ($('#sssm-search').val() || '').toLowerCase();

        $('#sssm-tbody tr').each(function () {
            var $row = $(this);
            var handle = ($row.data('handle') || '').toLowerCase();
            var src = ($row.data('src') || '').toLowerCase();
            var type = $row.data('type') || '';
            var isProt = $row.data('protected');

            var matchFilter = activeFilter === 'all' ||
                (activeFilter === 'protected' && isProt) ||
                (activeFilter === 'enqueued' && !isProt && (type === 'enqueued' || type === 'wordpress')) ||
                (activeFilter === 'external' && !isProt && type === 'external');

            var matchSearch = !search || handle.indexOf(search) > -1 || src.indexOf(search) > -1;

            $row.toggle(matchFilter && matchSearch);
        });
    }

    /* ================================================================== */
    /*  Search                                                             */
    /* ================================================================== */

    $(document).on('input', '#sssm-search', function () {
        applyFilters();
    });

    /* ================================================================== */
    /*  Table                                                              */
    /* ================================================================== */

    function buildTable(list) {
        var $tb = $('#sssm-tbody').empty();

        // Sort: protected → enqueued → wordpress → external
        list.sort(function (a, b) {
            var order = { 'protected': 0, 'enqueued': 1, 'wordpress': 2, 'external': 3 };
            var oa = a['protected'] ? 0 : (order[a.type] || 3);
            var ob = b['protected'] ? 0 : (order[b.type] || 3);
            if (oa !== ob) return oa - ob;
            return a.handle.localeCompare(b.handle);
        });

        $.each(list, function (i, s) {
            // Current status
            var cur = 'none';
            if (s.has_defer) cur = 'defer';
            if (s.has_async) cur = 'async';
            if (s.has_module) cur = 'module';

            // Saved strategy (or default none)
            var strat = saved[s.key] || 'none';

            // Type badge
            var badge;
            if (s['protected']) {
                badge = '<span class="badge b-lock">🔒</span>';
            } else if (s.type === 'enqueued' || s.type === 'wordpress') {
                badge = '<span class="badge b-wp">WP</span>';
            } else {
                badge = '<span class="badge b-ext">EXT</span>';
            }

            // Current tag
            var curHtml = '<span class="cur-tag cur-' + cur + '">' + cur + '</span>';

            // Strategy select
            var sel;
            if (s['protected']) {
                sel = '<select class="sssm-select" disabled><option>None (Protected)</option></select>';
            } else if (!s.controllable) {
                sel = '<select class="sssm-select" disabled title="Hardcoded scripts cannot be controlled via this plugin."><option>' + cap(cur) + ' (Hardcoded)</option></select>';
            } else {
                sel = '<select class="sssm-select s-' + strat + '" data-key="' + escA(s.key) + '">' +
                      '<option value="none"'  + (strat === 'none'  ? ' selected' : '') + '>None</option>' +
                      '<option value="defer"' + (strat === 'defer' ? ' selected' : '') + '>Defer</option>' +
                      '<option value="async"' + (strat === 'async' ? ' selected' : '') + '>Async</option>' +
                      '</select>';
            }

            var hClass = s['protected'] ? 'sssm-handle sssm-handle-protected' : 'sssm-handle';

            $tb.append(
                '<tr data-handle="' + escA(s.handle) + '" data-src="' + escA(s.src) + '" data-type="' + s.type + '" data-protected="' + (s['protected'] ? '1' : '') + '">' +
                '<td><span class="' + hClass + '">' + esc(s.handle) + '</span></td>' +
                '<td><span class="sssm-src" title="' + escA(s.src) + '">' + esc(s.src) + '</span></td>' +
                '<td>' + badge + '</td>' +
                '<td>' + curHtml + '</td>' +
                '<td>' + sel + '</td>' +
                '</tr>'
            );
        });

        $('#sssm-n').text(list.length);

        // Bind change with visual feedback
        $tb.find('.sssm-select:not(:disabled)').on('change', function () {
            var v = $(this).val();
            $(this).removeClass('s-none s-defer s-async').addClass('s-' + v);
            $(this).closest('tr').toggleClass('sssm-changed', v !== (saved[$(this).data('key')] || 'none'));
        });
    }

    /* ================================================================== */
    /*  Bulk actions                                                       */
    /* ================================================================== */

    $('#sssm-all-defer').on('click', function () {
        $('#sssm-tbody .sssm-select:not(:disabled)').val('defer').trigger('change');
        notice('All controllable scripts set to <strong>Defer</strong>. Click Save to apply.', 'warn');
    });

    $('#sssm-all-none').on('click', function () {
        $('#sssm-tbody .sssm-select:not(:disabled)').val('none').trigger('change');
        notice('All scripts reset to <strong>None</strong>. Click Save to apply.', 'warn');
    });

    /* ================================================================== */
    /*  Save                                                               */
    /* ================================================================== */

    $('#sssm-save').on('click', function () {
        var $btn = $(this);
        var data = {};

        // Start with existing saved settings
        $.extend(data, saved);

        // Override with current table values
        $('#sssm-tbody .sssm-select:not(:disabled)').each(function () {
            data[$(this).data('key')] = $(this).val();
        });

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="font-size:14px;width:14px;height:14px;animation:rotation 1s linear infinite;"></span> Saving&hellip;');

        $.post(SSSM.ajax, {
            action:  'sssm_save',
            nonce:   SSSM.nonce,
            scripts: JSON.stringify(data)
        }, function (r) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="font-size:14px;width:14px;height:14px;"></span> Save Settings');

            if (r.success) {
                saved = data;
                $('.sssm-changed').removeClass('sssm-changed');
                buildStats(scanned);
                notice('✓ ' + r.data.message + ' — <strong>' + r.data.count + '</strong> script(s) with custom loading strategy.', 'ok');
            } else {
                notice('Save failed: ' + (r.data || 'Unknown error'), 'err');
            }
        }).fail(function () {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="font-size:14px;width:14px;height:14px;"></span> Save Settings');
            notice('Save request failed.', 'err');
        });
    });

    /* ================================================================== */
    /*  Notices                                                            */
    /* ================================================================== */

    function notice(msg, type) {
        var $el = $('#sssm-msg');
        if (type === 'clear' || !msg) { $el.empty(); return; }

        var icon = '✓';
        if (type === 'err') icon = '✕';
        if (type === 'warn') icon = '⚠';

        $el.html(
            '<div class="sssm-notice sssm-notice-' + type + '">' +
            '<span class="sssm-notice-icon">' + icon + '</span>' +
            '<div>' + msg + '</div>' +
            '</div>'
        );
        if (type === 'ok') {
            setTimeout(function () { $el.find('.sssm-notice').fadeOut(500, function () { $(this).remove(); }); }, 6000);
        }
    }

    /* ================================================================== */
    /*  Helpers                                                            */
    /* ================================================================== */

    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }

    function escA(s) {
        return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function cap(s) {
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    /* ================================================================== */
    /*  Init: show count of active rules if any                            */
    /* ================================================================== */

    var activeCount = 0;
    $.each(saved, function (k, v) { if (v !== 'none') activeCount++; });
    if (activeCount > 0) {
        notice(activeCount + ' script(s) have custom loading strategies. Scan a page to review or modify.', 'ok');
    }

    // Add spinning keyframe
    if (!document.getElementById('sssm-keyframes')) {
        var style = document.createElement('style');
        style.id = 'sssm-keyframes';
        style.textContent = '@keyframes rotation { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
        document.head.appendChild(style);
    }

})(jQuery);
