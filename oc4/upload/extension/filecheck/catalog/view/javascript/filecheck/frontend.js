(function () {
    'use strict';

    var cfg = window.FILECHECK_OC_CONFIG;
    if (!cfg || !cfg.publishableKey || !cfg.workflowId) return;

    // ── localStorage resume helpers ───────────────────────────────────────────
    var STORE_KEY = 'fc_job_' + cfg.productId;

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
        var body = new URLSearchParams({
            nonce:      cfg.nonce,
            product_id: cfg.productId,
            job_id:     jobId || '',
        });
        fetch(cfg.saveJobUrl, {
            method:      'POST',
            credentials: 'same-origin',
            body:        body,
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

        if (btn && btn.parentNode) {
            btn.parentNode.insertBefore(slot, btn);
        } else {
            var form = document.getElementById('form-product') || document.querySelector('form.product-form');
            if (form) form.appendChild(slot);
        }
        return slot;
    }

    function init() {
        var slot = ensureSlot();
        if (!slot) return;

        whenReady().then(function () {
            var fcOpts = {};
            if (cfg.agentId) fcOpts.agentId = cfg.agentId;

            var fc      = window.Filecheck(cfg.publishableKey, fcOpts);
            var elOpts  = {
                workflowId:         cfg.workflowId,
                cartButtonSelector: '#button-cart',
            };
            if (cfg.connectorId) elOpts.connectorId = cfg.connectorId;

            var resumeJobId = getStoredJobId();
            if (resumeJobId) elOpts.jobId = resumeJobId;

            var el = fc.elements.create('intake', elOpts);
            el.mount('#' + slot.id);

            el.on('status', function (e) {
                storeJobId(e.jobId || null);
                saveJobIdToSession(e.jobId || null);
            });

            el.on('error', function (err) {
                console.error('[Filecheck OC] Widget uploader error:', err);
            });

            var btn = document.getElementById('button-cart') || document.querySelector('.btn-cart');
            if (btn) {
                btn.addEventListener('click', function () {
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