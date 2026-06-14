(function () {
    'use strict';

    var cfg = window.FILECHECK_OC_CONFIG;
    if (!cfg || !cfg.publishableKey || !cfg.workflowId) return;

    // ── localStorage resume helpers ───────────────────────────────────────────
    var STORE_KEY = 'fc_job_' + cfg.productId;
    var currentJobId = null;

    function getStoredJobId() {
        try { return localStorage.getItem(STORE_KEY) || null; } catch (e) { return null; }
    }

    function storeJobId(jobId) {
        try {
            jobId ? localStorage.setItem(STORE_KEY, jobId) : localStorage.removeItem(STORE_KEY);
        } catch (e) { /* unavailable */ }
    }

    // ── Save jobId to PHP session via AJAX ────────────────────────────────────
    function saveJobIdToSession(jobId) {
        if (!jobId) return Promise.resolve();

        var body = new URLSearchParams({
            nonce:      cfg.nonce,
            product_id: cfg.productId,
            job_id:     jobId,
        });
        return fetch(cfg.saveJobUrl, {
            method:      'POST',
            credentials: 'same-origin',
            body:        body,
            keepalive:   true,
        }).catch(function (err) {
            console.warn('[Filecheck OC] Session save failed:', err);
        });
    }

    // ── Wait for the async CDN bundle ─────────────────────────────────────────
    function whenReady() {
        return new Promise(function (resolve, reject) {
            var started = Date.now();
            (function tick() {
                if (window.Filecheck && window.Filecheck.mount) return resolve();
                if (Date.now() - started > 10000) return reject(new Error('Filecheck SDK failed to load after 10s'));
                setTimeout(tick, 50);
            })();
        });
    }

    // ── Find or create uploader slot ──────────────────────────────────────────
    function ensureSlot() {
        var id   = 'fc-slot-' + cfg.productId;
        var slot = document.getElementById(id);
        if (slot) return slot;

        var btn = document.getElementById('button-cart') || document.querySelector('.btn-cart');
        slot    = document.createElement('div');
        slot.id = id;
        slot.className = 'fc-slot-wrapper';
        slot.style.marginBottom = '1rem';

        var form = document.getElementById('form-product') || document.querySelector('form.product-form');
        if (form) {
            form.prepend(slot);
        } else if (btn && btn.parentNode) {
            btn.parentNode.insertBefore(slot, btn);
        }
        return slot;
    }

    function init() {
        var slot = ensureSlot();
        if (!slot) return;

        whenReady().then(function () {
            var config = {
                ...(window?.FILECHECK_CONFIG || {}),
                publishableKey:     cfg.publishableKey,
                workflowId:         cfg.workflowId,
                mountSelector:      `#fc-slot-${cfg.productId}`,
                agentId:            cfg.agentId || null,
            };
            if (cfg.connectorId) config.connectorId = cfg.connectorId;

            // Resume a previous job if the customer refreshes the page
            var resumeJobId = getStoredJobId();
            if (resumeJobId) {
                currentJobId = resumeJobId;
                config.jobId = resumeJobId;
            }

            var el = window.Filecheck.mount(config);

            el.on('status', function (e) {
                if (!e.jobId) return;

                currentJobId = e.jobId;
                storeJobId(e.jobId);
                saveJobIdToSession(e.jobId);
            });

            el.on('error', function (err) {
                console.error('[Filecheck OC] Widget uploader error:', err);
            });

            var btn = document.getElementById('button-cart') || document.querySelector('.btn-cart');
            if (btn) {
                btn.addEventListener('click', function () {
                    saveJobIdToSession(currentJobId || getStoredJobId());
                    storeJobId(null);
                }, { once: true });
            }
        }).catch(function (err) {
            console.error('[Filecheck OC] Init error:', err);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();