// GMS App — Install Prompt Handler
// Shows a custom "Install GMS App?" banner instead of relying on the browser's
// default mini-infobar, and registers the service worker for installability.
(function () {
    var LS_KEY = 'gms_pwa_prompt_dismissed_at';
    var DISMISS_DAYS = 7;
    var deferredPrompt = null;
    // Resolves to wherever this script is actually served from (root,
    // /gms/, /ganeshman_school/, etc.) so paths below never break in a subfolder.
    var BASE = new URL('./', document.currentScript ? document.currentScript.src : window.location.href).href.replace(/assets\/js\/$/, '');

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches ||
               window.navigator.standalone === true; // iOS
    }

    function recentlyDismissed() {
        var t = localStorage.getItem(LS_KEY);
        if (!t) return false;
        var days = (Date.now() - parseInt(t, 10)) / 86400000;
        return days < DISMISS_DAYS;
    }

    function markDismissed() {
        try { localStorage.setItem(LS_KEY, Date.now().toString()); } catch (e) {}
    }

    function isIOS() {
        return /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
    }
    function isSafari() {
        return /^((?!chrome|android|crios|fxios).)*safari/i.test(navigator.userAgent);
    }

    function injectStyles() {
        if (document.getElementById('gmsInstallStyles')) return;
        var style = document.createElement('style');
        style.id = 'gmsInstallStyles';
        style.textContent =
            '@keyframes gmsSlideUp{from{transform:translateY(24px);opacity:0}to{transform:translateY(0);opacity:1}}' +
            '#gmsInstallBanner{position:fixed;left:16px;right:16px;bottom:16px;max-width:420px;margin:0 auto;' +
            'background:#1a3c5e;color:#fff;border-radius:14px;box-shadow:0 10px 34px rgba(0,0,0,.35);' +
            'padding:16px 16px;display:flex;gap:13px;align-items:center;z-index:99999;' +
            'font-family:Poppins,Arial,sans-serif;animation:gmsSlideUp .3s ease;}' +
            '#gmsInstallBanner img{width:46px;height:46px;border-radius:11px;flex-shrink:0;object-fit:cover;}' +
            '#gmsInstallBanner .gms-title{font-weight:700;font-size:.92rem;line-height:1.3;}' +
            '#gmsInstallBanner .gms-sub{font-size:.76rem;opacity:.82;margin-top:2px;line-height:1.4;}' +
            '#gmsInstallBtn{background:#f5a623;color:#1f2937;border:none;padding:10px 16px;border-radius:8px;' +
            'font-weight:700;font-size:.8rem;cursor:pointer;white-space:nowrap;flex-shrink:0;}' +
            '#gmsInstallClose{background:transparent;border:none;color:rgba(255,255,255,.65);font-size:1.2rem;' +
            'cursor:pointer;padding:2px 4px;flex-shrink:0;line-height:1;}' +
            '@media(max-width:420px){#gmsInstallBanner{flex-wrap:wrap;}#gmsInstallBtn{margin-left:59px;}}';
        document.head.appendChild(style);
    }

    function showBanner(onInstallClick, iosMode) {
        if (document.getElementById('gmsInstallBanner') || isStandalone()) return;
        injectStyles();

        var el = document.createElement('div');
        el.id = 'gmsInstallBanner';
        el.innerHTML =
            '<img src="' + BASE + 'assets/icons/icon-192.png" alt="GMS">' +
            '<div style="flex:1;min-width:0;">' +
                '<div class="gms-title">Install GMS App</div>' +
                '<div class="gms-sub">' + (iosMode
                    ? 'Tap the Share icon, then "Add to Home Screen"'
                    : 'Quick access from your home screen, works like a real app') +
                '</div>' +
            '</div>' +
            (iosMode ? '' : '<button id="gmsInstallBtn">Install</button>') +
            '<button id="gmsInstallClose" aria-label="Dismiss">&times;</button>';
        document.body.appendChild(el);

        document.getElementById('gmsInstallClose').onclick = function () {
            markDismissed();
            el.remove();
        };
        if (!iosMode) {
            document.getElementById('gmsInstallBtn').onclick = function () {
                el.remove();
                if (onInstallClick) onInstallClick();
            };
        }
    }

    // ── Android / Desktop Chrome / Edge ──────────────────────────────────
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        if (isStandalone() || recentlyDismissed()) return;
        setTimeout(function () {
            showBanner(function () {
                if (!deferredPrompt) return;
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function (choice) {
                    if (choice.outcome !== 'accepted') markDismissed();
                    deferredPrompt = null;
                });
            }, false);
        }, 2500);
    });

    window.addEventListener('appinstalled', function () {
        var el = document.getElementById('gmsInstallBanner');
        if (el) el.remove();
        try { localStorage.removeItem(LS_KEY); } catch (e) {}
    });

    // ── iOS Safari (no beforeinstallprompt support — show manual steps) ──
    if (isIOS() && isSafari() && !isStandalone() && !recentlyDismissed()) {
        setTimeout(function () { showBanner(null, true); }, 2500);
    }

    // ── Register service worker ───────────────────────────────────────────
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(BASE + 'sw.js').catch(function (err) {
                console.warn('GMS App: service worker registration failed', err);
            });
        });
    }
})();