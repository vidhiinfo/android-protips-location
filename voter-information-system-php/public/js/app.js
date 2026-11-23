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
    var map = {}, labels = {};
    try { map = JSON.parse(caste.dataset.map || '{}'); labels = JSON.parse(caste.dataset.labels || '{}'); } catch (err) { return; }
    var empty = sub.options.length ? sub.options[0].text : '--';
    var current = sub.value;
    var list = map[caste.value] || [];
    if (!caste.value && caste.dataset.all) { try { list = JSON.parse(caste.dataset.all); } catch (err) {} }   // filter: no caste = every sub caste
    sub.innerHTML = '';
    sub.add(new Option(empty, ''));
    list.forEach(function (s) { sub.add(new Option(labels[s] || s, s, false, s === current)); });
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

// Admin > Users > Access area: Ward / Booth drop-downs (tick one or more) from the chosen assembly; the booth list
// follows the chosen wards; "Roll page" fills the serial range (30 voters per page) of a single booth.
(function () {
    var root = document.querySelector('[data-area-picker]');
    if (!root) return;
    var map = {}; try { map = JSON.parse(root.dataset.map || '{}'); } catch (e) {}
    var asm = document.querySelector('[data-area-assembly]');
    var val = function (f) { return root.querySelector('[data-area-value="' + f + '"]'); };
    var ints = function (s) { return (s || '').split(/[\s,;]+/).filter(function (x) { return /^\d+$/.test(x); }).map(Number); };
    var from = root.querySelector('[name=scope_serial_from]'), to = root.querySelector('[name=scope_serial_to]'), page = root.querySelector('[data-area-page]');
    function data() { return map[asm && asm.value] || { wards: [], booths: {} }; }
    function render(f, items) {
        var dd = root.querySelector('[data-area-dd="' + f + '"]');
        if (!dd) return;   // Ward No hidden (SHOW_WARD_NO=false)
        var menu = dd.querySelector('.dropdown-menu'), btn = dd.querySelector('button');
        var chosen = ints(val(f).value).filter(function (x) { return items.indexOf(x) >= 0; });
        val(f).value = chosen.join(', ');
        menu.innerHTML = '';
        if (!items.length) menu.innerHTML = '<div class="small text-muted px-2">' + root.dataset.none + '</div>';
        items.forEach(function (n) {
            var id = f + '-' + n, lab = document.createElement('label');
            lab.className = 'dropdown-item d-flex gap-2 align-items-center py-1'; lab.htmlFor = id;
            lab.innerHTML = '<input type="checkbox" class="form-check-input m-0" id="' + id + '" value="' + n + '"' + (chosen.indexOf(n) >= 0 ? ' checked' : '') + '> ' + n;
            menu.appendChild(lab);
        });
        btn.textContent = chosen.length ? (chosen.length <= 4 ? chosen.join(', ') : root.dataset.selectedN.replace('{0}', chosen.length)) : root.dataset.all;
    }
    function booths() {
        var d = data(), wards = val('scope_wards') ? ints(val('scope_wards').value) : [];
        return Object.keys(d.booths || {}).map(Number).filter(function (b) { return !wards.length || wards.indexOf(d.booths[b].ward) >= 0; }).sort(function (a, b) { return a - b; });
    }
    function pages() {
        var b = ints(val('scope_booths').value), d = data(), cur = page.value;
        page.innerHTML = '<option value="">' + page.options[0].text + '</option>';
        page.disabled = b.length !== 1;
        if (b.length !== 1) return;
        var max = (d.booths[b[0]] || {}).max || 0;
        for (var p = 1; p * 30 - 29 <= max; p++) {
            var lo = p * 30 - 29, hi = Math.min(p * 30, max);
            var o = new Option(root.dataset.page.replace('{0}', p) + ' (' + lo + '-' + hi + ')', lo + '-' + hi);
            if (String(from.value) === String(lo) && String(to.value) === String(hi)) o.selected = true;
            page.add(o);
        }
        if (cur && !page.value) page.value = '';
    }
    function refresh() { render('scope_wards', data().wards || []); render('scope_booths', booths()); pages(); }
    root.addEventListener('change', function (e) {
        var cb = e.target.closest('.dropdown-menu input[type=checkbox]');
        if (cb) {
            var f = cb.closest('[data-area-dd]').dataset.areaDd;
            val(f).value = Array.prototype.map.call(cb.closest('.dropdown-menu').querySelectorAll('input:checked'), function (i) { return i.value; }).join(', ');
            refresh();
        }
        if (e.target === page) {
            if (page.value) { var r = page.value.split('-'); from.value = r[0]; to.value = r[1]; } else { from.value = ''; to.value = ''; }
        }
    });
    if (asm) asm.addEventListener('change', refresh);
    refresh();
})();

// Field survey: one tap for the political status (fills its drop-down), and the first member's caste / sub caste /
// place of origin fill in automatically for family members that are still empty (shown in yellow; change if different).
(function () {
    var form = document.getElementById('surveyForm');
    if (!form) return;
    form.addEventListener('click', function (e) {
        var b = e.target.closest('[data-quick-value]');
        if (!b) return;
        var box = b.closest('[data-quick]'), sel = form.querySelector('[name="' + box.dataset.quick + '"]');
        if (!sel) return;
        var v = b.dataset.quickValue;
        if (sel.tagName === 'SELECT' && !Array.prototype.some.call(sel.options, function (o) { return o.value === v; })) sel.add(new Option(v, v));
        sel.value = v;
        box.querySelectorAll('[data-quick-value]').forEach(function (x) {
            var c = x.dataset.quickClass;
            x.className = 'btn btn-sm ' + (x === b ? 'btn-' + c : 'btn-outline-' + c);
        });
    });
    // picking from the drop-down highlights the matching quick button
    form.addEventListener('change', function (e) {
        var t = e.target;
        if (t.name && /\[political_status\]$/.test(t.name)) {
            var box = form.querySelector('[data-quick="' + t.name + '"]');
            if (box) box.querySelectorAll('[data-quick-value]').forEach(function (x) { var c = x.dataset.quickClass; x.className = 'btn btn-sm ' + (x.dataset.quickValue === t.value ? 'btn-' + c : 'btn-outline-' + c); });
        }
        var cards = form.querySelectorAll('[data-member]');
        if (cards.length < 2 || !t.dataset || !t.dataset.f || ['caste', 'sub_caste', 'place_of_origin'].indexOf(t.dataset.f) < 0) return;
        if (!cards[0].contains(t)) return;
        setTimeout(function () {   // after the sub caste list has been rebuilt for the new caste
            for (var i = 1; i < cards.length; i++) {
                ['caste', 'sub_caste', 'place_of_origin'].forEach(function (f) {
                    var src = cards[0].querySelector('[data-f="' + f + '"]'), dst = cards[i].querySelector('[data-f="' + f + '"]');
                    if (!src || !dst || (dst.value && !dst.classList.contains('autofilled'))) return;
                    if (dst.tagName === 'SELECT' && src.tagName === 'SELECT' && f === 'sub_caste') dst.innerHTML = src.innerHTML;
                    dst.value = src.value;
                    dst.classList.toggle('autofilled', !!src.value);
                });
            }
        }, 0);
    });
    form.addEventListener('input', function (e) { if (e.target.classList) e.target.classList.remove('autofilled'); });
})();

// long-running forms (roll update compare / apply): show progress and stop double submits
document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;
    var b = e.target.querySelector('button[data-busy]');
    if (!b) return;
    setTimeout(function () { b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + b.getAttribute('data-busy'); }, 0);
});

// two-step login set-up: draw the authenticator QR code (vendor/qrcode/qrcode.js, no inline script because of the CSP)
(function () {
    var el = document.getElementById('otp-qr');
    if (!el || typeof qrcode === 'undefined') return;
    var qr = qrcode(0, 'M'); qr.addData(el.getAttribute('data-uri')); qr.make();
    el.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2 });
})();

// map of surveyed houses (vendor/leaflet): green = surveyed, orange = partly, red = not yet
(function () {
    var el = document.getElementById('houseMap');
    if (!el || typeof L === 'undefined') return;
    var pts = JSON.parse(el.getAttribute('data-points') || '[]');
    var map = L.map(el);
    L.tileLayer(el.getAttribute('data-tiles'), { maxZoom: 19, attribution: el.getAttribute('data-attribution') }).addTo(map);
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var bounds = [];
    pts.forEach(function (p) {
        var c = p.done >= p.n ? '#198754' : p.done ? '#fd7e14' : '#dc3545';
        L.circleMarker([p.lat, p.lng], { radius: 8, color: '#fff', weight: 2, fillColor: c, fillOpacity: 0.9 }).addTo(map)
            .bindPopup('<b>' + esc(p.house) + '</b><br>' + esc(p.addr) + '<br>' + esc(el.getAttribute('data-l-members').replace('{0}', p.n)) + ' · ' + p.done + '/' + p.n
                + '<br><a href="' + esc(p.url) + '">' + esc(el.getAttribute('data-l-open')) + '</a>');
        bounds.push([p.lat, p.lng]);
    });
    if (bounds.length) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 18 });
})();

// field survey house page: take the house's GPS location (saved with the form)
document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-geo]');
    if (!b) return;
    e.preventDefault();
    var form = b.closest('form'), out = document.getElementById(b.getAttribute('data-geo'));
    if (!navigator.geolocation) { out.textContent = b.getAttribute('data-l-failed'); return; }
    out.textContent = b.getAttribute('data-l-taking');
    navigator.geolocation.getCurrentPosition(function (p) {
        form.querySelector('[name=lat]').value = p.coords.latitude.toFixed(7);
        form.querySelector('[name=lng]').value = p.coords.longitude.toFixed(7);
        form.querySelector('[name=acc]').value = Math.round(p.coords.accuracy);
        out.textContent = b.getAttribute('data-l-ready').replace('{0}', Math.round(p.coords.accuracy));
    }, function () { out.textContent = b.getAttribute('data-l-failed'); }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 30000 });
});

// offline data on this phone: [database, store of unsent items] (offline survey, offline field app)
var OFFLINE_DBS = [['vis-offline', 'queue'], ['vis-field', 'outbox']];

/** Calls back with the number of unsent offline items on this phone (0 when nothing was ever downloaded). */
function offlinePending(cb) {
    if (!window.indexedDB) { cb(0); return; }
    var total = 0, left = OFFLINE_DBS.length;
    var fin = function () { if (--left === 0) cb(total); };
    OFFLINE_DBS.forEach(function (x) {
        var r = indexedDB.open(x[0]);
        r.onupgradeneeded = function () { r.transaction.abort(); };   // never used on this phone: do not create it
        r.onerror = fin;
        r.onsuccess = function () {
            var d = r.result;
            if (!d.objectStoreNames.contains(x[1])) { d.close(); fin(); return; }
            var q = d.transaction(x[1]).objectStore(x[1]).count();
            q.onsuccess = function () { total += q.result; d.close(); fin(); };
            q.onerror = function () { d.close(); fin(); };
        };
    });
}

// logout: remove offline booths from the phone (warn when something has not been sent yet)
document.addEventListener('submit', function (e) {
    if (!e.target.matches('[data-logout]') || !window.indexedDB || e.target.dataset.cleared) return;
    e.preventDefault();
    var form = e.target, done = function () { form.dataset.cleared = '1'; form.submit(); };
    offlinePending(function (n) {
        if (n > 0 && !confirm(form.getAttribute('data-l-unsent') || 'Unsent offline answers will be lost. Log out?')) return;
        var left = OFFLINE_DBS.length;
        OFFLINE_DBS.forEach(function (x) { var del = indexedDB.deleteDatabase(x[0]); del.onsuccess = del.onerror = del.onblocked = function () { if (--left === 0) done(); }; });
    });
});

// status badge in the menu bar on every page: offline, or items still waiting to be sent
(function () {
    var badge = document.getElementById('offlineBadge');
    if (!badge) return;
    var show = function () {
        offlinePending(function (n) {
            var off = !navigator.onLine;
            badge.classList.toggle('d-none', !off && !n);
            badge.className = badge.className.replace(/text-bg-\w+/, off ? 'text-bg-danger' : 'text-bg-warning');
            badge.innerHTML = '<i class="bi bi-' + (off ? 'wifi-off' : 'cloud-upload') + '"></i> ' + (off ? badge.getAttribute('data-l-off') : '') + (n ? ' ' + badge.getAttribute('data-l-wait').replace('{0}', n) : '');
        });
    };
    window.addEventListener('online', show); window.addEventListener('offline', show);
    show(); setInterval(show, 20000);
})();

// duplicates: the voter chosen to keep is never also ticked for merging
document.addEventListener('change', function (e) {
    if (!e.target.matches('[data-dup-keep]')) return;
    e.target.form.querySelectorAll('[data-dup-drop]').forEach(function (c) { c.checked = c.value !== e.target.value; });
});

// inbox: show the newest message
document.querySelectorAll('[data-scroll-end]').forEach(function (el) { el.scrollTop = el.scrollHeight; });
