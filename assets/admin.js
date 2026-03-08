/**
 * Site Scripts & Speed Manager — Admin JavaScript
 * Handles scanning, table rendering, and AJAX save/toggle.
 *
 * @package SiteScriptsSpeedManager
 * @version 2.1.1
 * @author  Think Above AI
 */
(function ($) {
    'use strict';

    var saved   = SSSM.settings.scripts || {};
    var scanned = [];

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
            if (r.success) notice(r.data.label + ' — ' + (on ? 'script modifications are now active on the front-end.' : 'all modifications removed.'), 'ok');
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

    // Allow Enter key in URL field
    $('#sssm-url').on('keypress', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#sssm-scan').trigger('click');
        }
    });

    function scan(url) {
        var $btn = $('#sssm-scan');
        var $spin = $('#sssm-spin');

        $btn.prop('disabled', true).text('Scanning…');
        $spin.addClass('is-active');
        notice('', 'clear');

        $.post(SSSM.ajax, {
            action: 'sssm_scan',
            nonce:  SSSM.nonce,
            url:    url
        }, function (r) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="vertical-align:middle;margin-top:-2px;"></span> Scan Scripts');
            $spin.removeClass('is-active');

            if (r.success) {
                scanned = r.data.scripts;
                buildTable(scanned);
                notice('Found <strong>' + r.data.total + '</strong> scripts on <code>' + esc(r.data.url) + '</code>', 'ok');
                $('#sssm-results').slideDown(200);
            } else {
                notice(r.data || 'Scan failed', 'err');
            }
        }).fail(function () {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="vertical-align:middle;margin-top:-2px;"></span> Scan Scripts');
            $spin.removeClass('is-active');
            notice('Request failed — check your connection.', 'err');
        });
    }

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
            } else if (s.type === 'enqueued') {
                badge = '<span class="badge b-wp">WP</span>';
            } else if (s.type === 'wordpress') {
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
                sel = '<select class="sssm-select" disabled title="Hardcoded scripts cannot be controlled via this plugin. They must be modified at the source."><option>' + cap(cur) + ' (Hardcoded)</option></select>';
            } else {
                sel = '<select class="sssm-select s-' + strat + '" data-key="' + escA(s.key) + '">' +
                      '<option value="none"'  + (strat === 'none'  ? ' selected' : '') + '>None</option>' +
                      '<option value="defer"' + (strat === 'defer' ? ' selected' : '') + '>Defer</option>' +
                      '<option value="async"' + (strat === 'async' ? ' selected' : '') + '>Async</option>' +
                      '</select>';
            }

            var hClass = s['protected'] ? 'sssm-handle sssm-handle-protected' : 'sssm-handle';

            $tb.append(
                '<tr>' +
                '<td><span class="' + hClass + '">' + esc(s.handle) + '</span></td>' +
                '<td><span class="sssm-src" title="' + escA(s.src) + '">' + esc(s.src) + '</span></td>' +
                '<td>' + badge + '</td>' +
                '<td>' + curHtml + '</td>' +
                '<td>' + sel + '</td>' +
                '</tr>'
            );
        });

        $('#sssm-n').text(list.length);

        // Bind change
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
    });

    $('#sssm-all-none').on('click', function () {
        $('#sssm-tbody .sssm-select:not(:disabled)').val('none').trigger('change');
    });

    /* ================================================================== */
    /*  Save                                                               */
    /* ================================================================== */

    $('#sssm-save').on('click', function () {
        var $btn = $(this);
        var data = {};

        // Start with existing saved settings (so we don't lose scripts from other page scans)
        $.extend(data, saved);

        // Override with current table values
        $('#sssm-tbody .sssm-select:not(:disabled)').each(function () {
            data[$(this).data('key')] = $(this).val();
        });

        $btn.prop('disabled', true).text('Saving…');

        $.post(SSSM.ajax, {
            action:  'sssm_save',
            nonce:   SSSM.nonce,
            scripts: JSON.stringify(data)
        }, function (r) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="vertical-align:middle;margin-top:-2px;"></span> Save Settings');

            if (r.success) {
                saved = data;
                $('.sssm-changed').removeClass('sssm-changed');
                notice(r.data.message + ' — <strong>' + r.data.count + '</strong> script(s) with custom loading strategy.', 'ok');
            } else {
                notice('Save failed: ' + (r.data || 'Unknown error'), 'err');
            }
        }).fail(function () {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="vertical-align:middle;margin-top:-2px;"></span> Save Settings');
            notice('Save request failed.', 'err');
        });
    });

    /* ================================================================== */
    /*  Notices                                                            */
    /* ================================================================== */

    function notice(msg, type) {
        var $el = $('#sssm-msg');
        if (type === 'clear' || !msg) { $el.empty(); return; }
        $el.html('<div class="sssm-notice sssm-notice-' + type + '">' + msg + '</div>');
        if (type === 'ok') {
            setTimeout(function () { $el.find('.sssm-notice').fadeOut(400, function () { $(this).remove(); }); }, 6000);
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

})(jQuery);
