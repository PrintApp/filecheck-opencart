(function () {
    'use strict';

    // ── Product tab lazy loader ───────────────────────────────────────────────
    var tabEl = document.getElementById('fc-product-tab');
    if (tabEl) {
        var loaded = false;
        var tabLink = document.querySelector('a[href="#tab-filecheck"]');
        if (tabLink) {
            tabLink.addEventListener('click', function () {
                if (loaded) return;
                loaded = true;
                var url = tabEl.getAttribute('data-load');
                if (!url) return;
                fetch(url, { credentials: 'same-origin' })
                    .then(function (r) { return r.text(); })
                    .then(function (html) { tabEl.innerHTML = html; })
                    .catch(function () { tabEl.innerHTML = '<p style="color:red;padding:10px">Failed to load Filecheck settings.</p>'; });
            });
        }
    }

    // ── Test connection button ────────────────────────────────────────────────
    var testBtn = document.getElementById('fc-test-connection');
    if (testBtn) {
        testBtn.addEventListener('click', function () {
            var result = document.getElementById('fc-connection-result');
            var sk     = document.querySelector('[name="module_filecheck_secret_key"]');
            var apiUrl = document.querySelector('[name="module_filecheck_api_url"]');
            var url    = testBtn.getAttribute('data-ajax');

            if (!sk || !sk.value.trim()) {
                if (result) { result.style.color = '#c00'; result.textContent = 'Secret Key is required.'; }
                return;
            }

            testBtn.disabled = true;
            if (result) { result.style.color = '#888'; result.textContent = 'Testing…'; }

            var body = new URLSearchParams({
                sk:      sk.value.trim(),
                api_url: apiUrl ? apiUrl.value.trim() : '',
            });

            fetch(url, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (result) {
                        result.style.color   = data.success ? '#007a3d' : '#c00';
                        result.textContent   = data.success || data.error || 'Unknown error.';
                    }
                })
                .catch(function (err) {
                    if (result) { result.style.color = '#c00'; result.textContent = 'Request failed: ' + err.message; }
                })
                .finally(function () { testBtn.disabled = false; });
        });
    }

    // ── Order job details panel ───────────────────────────────────────────────
    var panel = document.getElementById('filecheck-job-panel');
    if (panel) {
        var orderId  = panel.getAttribute('data-order-id');
        var ajaxUrl  = panel.getAttribute('data-ajax');
        if (orderId && ajaxUrl) {
            var body = new URLSearchParams({ order_id: orderId });
            fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.success || !data.items || !data.items.length) {
                        panel.innerHTML = '<p style="color:#888;font-size:13px;">No Filecheck jobs found for this order.</p>';
                        return;
                    }
                    renderJobItems(panel, data.items);
                })
                .catch(function () {
                    panel.innerHTML = '<p style="color:#c00;font-size:13px;">Could not load Filecheck job details.</p>';
                });
        }
    }

    function badge(outcome) {
        var map = { pass: ['#007a3d', 'PASS'], warn: ['#b45309', 'WARN'], fail: ['#c00', 'FAIL'] };
        var b   = map[outcome] || ['#666', (outcome || 'PENDING').toUpperCase()];
        return '<span style="display:inline-block;padding:1px 7px;border-radius:3px;font-size:10px;font-weight:700;color:#fff;background:' + b[0] + '">' + b[1] + '</span>';
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function renderJobItems(container, items) {
        var html = '';
        items.forEach(function (item) {
            html += '<div style="margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid #eee;">';
            html += '<strong>' + esc(item.jobId) + '</strong>';
            if (item.error) {
                html += '<br><span style="color:#c00;font-size:11px;">' + esc(item.error) + '</span>';
            } else {
                if (item.status) html += ' &mdash; <span style="font-size:11px;color:#555;">' + esc(item.status) + '</span>';
                if (item.files && item.files.length) {
                    html += '<ul style="margin:6px 0 0;padding:0;list-style:none;">';
                    item.files.forEach(function (f) {
                        html += '<li style="margin:0 0 8px;">';
                        html += badge(f.outcome) + ' <span style="font-size:12px;">' + esc(f.name) + '</span>';
                        if (f.proofs && f.proofs.length) {
                            html += '<div style="margin:4px 0;display:flex;gap:4px;flex-wrap:wrap;">';
                            f.proofs.forEach(function (p) {
                                html += '<a href="' + esc(p.url) + '" target="_blank" rel="noopener">' +
                                    '<img src="' + esc(p.url) + '" alt="proof" style="width:48px;height:48px;object-fit:cover;border:1px solid #ddd;border-radius:3px;"></a>';
                            });
                            html += '</div>';
                        }
                        if (f.downloadUrl) {
                            html += '<br><a href="' + esc(f.downloadUrl) + '" target="_blank" rel="noopener" class="btn btn-xs btn-default" style="margin-top:3px;">Download</a>';
                        }
                        html += '</li>';
                    });
                    html += '</ul>';
                }
            }
            html += '<a href="' + esc(item.adminUrl) + '" target="_blank" rel="noopener" class="btn btn-xs btn-info" style="margin-top:5px;">View on Filecheck</a>';
            html += '</div>';
        });
        container.innerHTML = html;
    }
})();
