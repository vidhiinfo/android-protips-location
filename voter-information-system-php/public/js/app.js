// Confirmation for destructive actions (kept out of inline handlers for the CSP)
document.addEventListener('submit', function (e) {
  var msg = e.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) e.preventDefault();
});
// Print buttons
document.addEventListener('click', function (e) {
  if (e.target.closest('[data-print]')) window.print();
});
// Auto-print when the print view is opened with ?autoprint
if (document.body && document.body.hasAttribute('data-autoprint')) {
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
}
// Page-size selector submits the filter form
document.addEventListener('change', function (e) {
  if (e.target.matches('[data-autosubmit]')) e.target.form.submit();
});
// Language switcher: reload the current page with ?lang=xx (server stores it in a cookie)
document.addEventListener('change', function (e) {
  if (e.target.matches('[data-lang-switch]')) {
    var url = new URL(window.location.href);
    url.searchParams.set('lang', e.target.value);
    url.searchParams.delete('error');
    window.location.href = url.toString();
  }
});
// House No links: show everyone at that house in a popup (falls back to a new window without JS)
document.addEventListener('click', function (e) {
  var link = e.target.closest('a[data-household]');
  if (!link || !window.bootstrap) return;
  e.preventDefault();
  var body = document.getElementById('householdBody');
  var href = link.getAttribute('href');
  document.getElementById('householdOpen').setAttribute('href', href);
  body.innerHTML = '<div class="text-center py-4"><div class="spinner-border"></div></div>';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('householdModal')).show();
  fetch(href + (href.indexOf('?') < 0 ? '?' : '&') + 'fragment=1', { credentials: 'same-origin' })
    .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
    .then(function (html) { body.innerHTML = html; })
    .catch(function () { window.open(href, '_blank'); });
});

// Ward No filter: show only the booths of the chosen ward in the Booth drop-down next to it
document.querySelectorAll('select[data-ward-select]').forEach(function (ward) {
    var booth = ward.dataset.boothSelect ? document.getElementById(ward.dataset.boothSelect) : null;
    if (!booth) return;
    var map = {}, all = [];
    try { map = JSON.parse(ward.dataset.map || '{}'); all = JSON.parse(ward.dataset.all || '[]').map(String); } catch (e) { return; }
    var first = booth.options.length ? booth.options[0].text : '';
    function apply() {
        var keep = ward.value ? (map[ward.value] || []).map(String) : all;
        var current = booth.value;
        booth.innerHTML = '';
        booth.add(new Option(first, ''));
        keep.forEach(function (b) { booth.add(new Option(b, b, false, b === current)); });
        if (keep.indexOf(current) < 0) booth.value = '';
    }
    ward.addEventListener('change', apply);
})
;

// Caste -> Sub Caste: the Sub Caste drop-down shows only the sub castes of the chosen caste (Caste master).
// Listens on the document, so it also works inside the House No popup that is loaded later.
document.addEventListener('change', function (e) {
    var caste = e.target;
    if (!caste.matches || !caste.matches('select[data-caste-select]')) return;
    var sub = document.getElementById(caste.dataset.subSelect);
    if (!sub) return;
    var map = {};
    try { map = JSON.parse(caste.dataset.map || '{}'); } catch (err) { return; }
    var empty = sub.options.length ? sub.options[0].text : '--';
    var current = sub.value;
    sub.innerHTML = '';
    sub.add(new Option(empty, ''));
    (map[caste.value] || []).forEach(function (s) { sub.add(new Option(s, s, false, s === current)); });
});

// Field survey: copy the first member's answer (caste + sub caste, political status ...) to everyone in the house.
document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('[data-copy]');
    if (!btn) return;
    var cards = document.querySelectorAll('[data-member]');
    if (cards.length < 2) return;
    btn.dataset.copy.split(',').forEach(function (f) {
        var src = cards[0].querySelector('[data-f="' + f + '"]');
        if (!src) return;
        for (var i = 1; i < cards.length; i++) {
            var dst = cards[i].querySelector('[data-f="' + f + '"]');
            if (!dst) continue;
            if (dst.tagName === 'SELECT' && src.tagName === 'SELECT') dst.innerHTML = src.innerHTML;   // sub caste options follow the caste
            dst.value = src.value;
        }
    });
    btn.classList.replace('btn-outline-primary', 'btn-success');
});

// Voter list: tick voters, then set caste / political status ... for all of them (bulk bar appears at the bottom).
(function () {
    var bar = document.querySelector('[data-bulk-bar]');
    if (!bar) return;
    var boxes = function () { return document.querySelectorAll('input[data-check]'); };
    var all = document.querySelector('[data-check-all]');
    function refresh() {
        var n = Array.prototype.filter.call(boxes(), function (b) { return b.checked; }).length;
        bar.classList.toggle('d-none', n === 0);
        bar.querySelector('[data-bulk-count]').textContent = n;
    }
    document.addEventListener('change', function (e) {
        if (e.target === all) boxes().forEach(function (b) { b.checked = all.checked; });
        if (e.target === all || (e.target.matches && e.target.matches('input[data-check]'))) refresh();
    });
    bar.addEventListener('submit', function (e) {
        var btn = bar.querySelector('[data-confirm-bulk]');
        var msg = btn.dataset.confirmBulk.replace('{0}', bar.querySelector('[data-bulk-count]').textContent);
        if (!window.confirm(msg)) e.preventDefault();
    });
    refresh();
})();

// Poll day serial grid: tap a serial no. to mark / unmark "voted" (saved at once).
(function () {
    var grid = document.querySelector('.poll-grid');
    if (!grid) return;
    grid.addEventListener('click', function (e) {
        var b = e.target.closest('.poll-cell');
        if (!b || b.disabled) return;
        b.disabled = true;
        var body = new URLSearchParams({ voter_id: b.dataset.id, _token: grid.dataset.token });
        fetch(grid.dataset.toggleUrl, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { if (r.status === 419) { location.reload(); throw r; } if (!r.ok) throw r; return r.json(); })
            .then(function (d) {
                b.classList.toggle('voted', d.voted);
                var c = document.querySelector('[data-poll-voted]'); if (c && d.stats) c.textContent = d.stats.voted;
            })
            .catch(function () { b.classList.add('err'); })
            .finally(function () { b.disabled = false; });
    });
})();

// ---------------------------------------------------------------- mobile app
// 1. Installable web app (PWA): register the service worker (offline page, app icon on the home screen).
(function () {
    if (!('serviceWorker' in navigator) || location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') return;
    var app = document.querySelector('script[src*="js/app.js"]');
    if (!app) return;
    var base = app.src.replace(/js\/app\.js.*$/, '');   // works from a subfolder (/vis/)
    navigator.serviceWorker.register(base + 'sw.js', { scope: base }).catch(function () {});
})();

// 2. "Install now" button where the browser supports it (Android Chrome / Edge); hidden once installed.
(function () {
    var deferred = null;
    var standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
    if (standalone) document.querySelectorAll('[data-installed]').forEach(function (e) { e.classList.remove('d-none'); });
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault(); deferred = e;
        document.querySelectorAll('[data-install-app]').forEach(function (b) { b.classList.remove('d-none'); });
    });
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-install-app]');
        if (!b || !deferred) return;
        deferred.prompt();
        deferred.userChoice.finally(function () { deferred = null; b.classList.add('d-none'); });
    });
})();

// 3. Inside the Android / iOS app (Capacitor): Excel / PDF downloads are saved and offered to share / open,
//    because a phone app's web view cannot download files by itself.
(function () {
    var C = window.Capacitor;
    if (!C || !C.isNativePlatform || !C.isNativePlatform()) return;
    document.documentElement.classList.add('native-app', 'native-' + C.getPlatform());
    var P = C.Plugins || {};
    var isDownload = function (href) { return /\/export\/|\/import\/template|\/export\?/.test(href); };
    function toBase64(blob) {
        return new Promise(function (ok, fail) {
            var r = new FileReader();
            r.onload = function () { ok(String(r.result).split(',')[1]); };
            r.onerror = fail; r.readAsDataURL(blob);
        });
    }
    function fileName(res, href) {
        var cd = res.headers.get('Content-Disposition') || '';
        var m = cd.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);
        return m ? decodeURIComponent(m[1]) : (href.split('/').pop().split('?')[0] || 'download');
    }
    function busy(on) {
        var el = document.getElementById('native-busy');
        if (!el) { el = document.createElement('div'); el.id = 'native-busy'; el.innerHTML = '<div class="spinner-border text-light"></div>'; document.body.appendChild(el); }
        el.style.display = on ? 'flex' : 'none';
    }
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a || !isDownload(a.href) || !P.Filesystem || !P.Share) return;
        e.preventDefault();
        busy(true);
        fetch(a.href, { credentials: 'include' }).then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            var type = res.headers.get('Content-Type') || '';
            if (type.indexOf('text/html') === 0) { location.href = res.url; return null; }   // e.g. "too many rows" message page
            var name = fileName(res, a.href);
            return res.blob().then(toBase64).then(function (data) {
                return P.Filesystem.writeFile({ path: name, data: data, directory: 'CACHE' });
            }).then(function (f) {
                return P.Share.share({ title: name, files: [f.uri], dialogTitle: name });
            });
        }).catch(function (err) {
            if (!/cancel/i.test(String(err && err.message))) alert(String(err && err.message || err));
        }).finally(function () { busy(false); });
    });
})();

// ---------------------------------------------------------------- expired page protection (phones, especially iPhone Safari)
// A form keeps the security token of the moment the page was opened. When the phone sleeps, the tab is left open
// or Safari shows a saved copy, that token may belong to a finished session ("419 Page Expired").
(function () {
    var loaded = Date.now();
    // Safari "back" / app switch restored a saved copy of the page: load it fresh
    window.addEventListener('pageshow', function (e) { if (e.persisted) location.reload(); });
    var script = document.querySelector('script[src*="js/app.js"]');
    if (!script || !document.querySelector('input[name="_token"]')) return;
    var url = script.src.replace(/js\/app\.js.*$/, '') + 'csrf-token';
    function refresh() {
        return fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
            if (!d || !d.token) return;
            document.querySelectorAll('input[name="_token"]').forEach(function (i) { i.value = d.token; });
            var g = document.querySelector('.poll-grid'); if (g) g.dataset.token = d.token;
            loaded = Date.now();
        }).catch(function () {});
    }
    // back on the page after a while (phone unlocked, tab switched): get a fresh token
    document.addEventListener('visibilitychange', function () { if (!document.hidden && Date.now() - loaded > 5 * 60000) refresh(); });
    // and every 10 minutes while the page stays open
    setInterval(function () { if (!document.hidden) refresh(); }, 10 * 60000);
})();

// Login needs one cookie (the session). If the browser blocks cookies (iPhone: Settings > Safari > Block All Cookies), say so.
(function () {
    var w = document.querySelector('[data-cookie-warning]');
    if (!w) return;
    var ok = navigator.cookieEnabled;
    try { document.cookie = 'vis_ck=1; path=/; SameSite=Lax'; ok = ok && document.cookie.indexOf('vis_ck=1') >= 0; document.cookie = 'vis_ck=; Max-Age=0; path=/'; } catch (e) { ok = false; }
    if (!ok) w.classList.remove('d-none');
})();
