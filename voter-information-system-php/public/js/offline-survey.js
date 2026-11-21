/*
 * Offline field survey: one booth is downloaded to the phone (IndexedDB), the worker goes house by house without internet,
 * answers wait on the phone and are sent when the connection is back. The booth is removed after OFFLINE_KEEP_DAYS,
 * on "Remove from phone", on logout, and when another user logs in on the same phone.
 */
(function () {
  'use strict';
  var app = document.getElementById('offlineApp');
  if (!app) return;
  var L = JSON.parse(app.getAttribute('data-i18n') || '{}');
  var me = app.getAttribute('data-user') || '';
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var fmt = function (s) { var a = arguments; return String(s).replace(/\{(\d+)\}/g, function (m, i) { return a[+i + 1] != null ? a[+i + 1] : m; }); };

  // ------------------------------------------------------------ IndexedDB
  var dbp = null;
  function db() {
    if (dbp) return dbp;
    dbp = new Promise(function (ok, fail) {
      var r = indexedDB.open('vis-offline', 1);
      r.onupgradeneeded = function () {
        var d = r.result;
        d.createObjectStore('booth');
        d.createObjectStore('queue', { keyPath: 'id' });
        d.createObjectStore('locs', { keyPath: 'id' });
      };
      r.onsuccess = function () { ok(r.result); };
      r.onerror = function () { fail(r.error); };
    });
    return dbp;
  }
  function tx(store, mode, fn) {
    return db().then(function (d) {
      return new Promise(function (ok, fail) {
        var t = d.transaction(store, mode), s = t.objectStore(store), out = fn(s);
        t.oncomplete = function () { ok(out && out.result !== undefined ? out.result : out); };
        t.onerror = function () { fail(t.error); };
      });
    });
  }
  var getBooth = function () { return tx('booth', 'readonly', function (s) { return s.get('current'); }); };
  var putBooth = function (b) { return tx('booth', 'readwrite', function (s) { s.put(b, 'current'); }); };
  var all = function (store) { return tx(store, 'readonly', function (s) { return s.getAll(); }); };
  var clearAll = function () {
    return Promise.all(['booth', 'queue', 'locs'].map(function (n) { return tx(n, 'readwrite', function (s) { s.clear(); }); }));
  };

  var booth = null, queue = {}, locs = {};

  function load() {
    return Promise.all([getBooth(), all('queue'), all('locs')]).then(function (r) {
      booth = r[0] || null; queue = {}; locs = {};
      r[1].forEach(function (q) { queue[q.id] = q; });
      r[2].forEach(function (l) { locs[l.id] = l; });
    });
  }
  var waiting = function () { return Object.keys(queue).length + Object.keys(locs).length; };
  var expired = function () { return booth && (Date.now() - Date.parse(booth.at)) > (booth.keepDays || 3) * 86400000; };

  // ------------------------------------------------------------ screen
  function net() {
    var on = navigator.onLine;
    $('offNet').innerHTML = on ? '<span class="text-success"><i class="bi bi-wifi"></i> ' + esc(L.onlineNow) + '</span>'
      : '<span class="text-danger"><i class="bi bi-wifi-off"></i> ' + esc(L.offlineNow) + '</span>';
    document.querySelectorAll('[data-online-only]').forEach(function (e) { e.classList.toggle('d-none', !on); });
  }

  function status() {
    var box = $('offStatus');
    if (!booth && !waiting()) { box.classList.add('d-none'); $('offList').classList.add('d-none'); return; }
    box.classList.remove('d-none');
    var t = '';
    if (booth) t += '<b>' + esc(fmt(L.downloaded, booth.booth, new Date(booth.at).toLocaleString())) + '</b> · ' + esc(fmt(L.houses, booth.houses.length));
    t += '<br>' + esc(fmt(L.waiting, waiting()));
    if (expired()) t += '<br><span class="text-danger">' + esc(L.expired) + '</span>';
    $('offStatusText').innerHTML = t;
    $('offList').classList.toggle('d-none', !booth || !$('offHouse').classList.contains('d-none'));
  }

  function houseDone(h) {
    var d = 0;
    h.members.forEach(function (m) { var v = cur(m); if (v.political_status || v.caste) d++; });
    return d;
  }
  // member values as on the phone: downloaded values with the unsent changes on top
  function cur(m) {
    var v = Object.assign({}, m.v), q = queue[m.id];
    if (q) Object.assign(v, q.v);
    return v;
  }

  function list() {
    var box = $('offHouses'), f = ($('offFind').value || '').toLowerCase().replace(/[\s.\-]/g, '');
    if (!booth) { box.innerHTML = ''; return; }
    var html = '';
    booth.houses.forEach(function (h, i) {
      if (f && String(h.house).toLowerCase().replace(/[\s.\-]/g, '').indexOf(f) < 0) return;
      var d = houseDone(h), n = h.members.length;
      var badge = d >= n ? '<span class="badge text-bg-success"><i class="bi bi-check-lg"></i> ' + esc(L.done) + '</span>'
        : d ? '<span class="badge text-bg-warning">' + d + '/' + n + '</span>' : '<span class="badge text-bg-secondary">' + esc(L.pending) + '</span>';
      var pin = locs[h.members[0].id] ? ' <i class="bi bi-geo-alt-fill text-primary"></i>' : '';
      html += '<a href="#" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-3" data-house="' + i + '">'
        + '<span class="fs-5 fw-semibold" style="min-width:5.5rem">' + esc(h.house || '-') + pin + '</span>'
        + '<span class="small text-muted me-auto text-truncate">' + esc(h.addr) + '</span>'
        + '<span class="badge text-bg-light border">' + esc(fmt(L.members, n)) + '</span>' + badge + '</a>';
    });
    box.innerHTML = html;
  }

  function select(name, field, opts, val, attrs) {
    var h = '<select class="form-select" name="' + name + '" data-f="' + field + '" ' + (attrs || '') + '><option value="">--</option>', seen = false;
    Object.keys(opts).forEach(function (k) { if (k === val) seen = true; h += '<option value="' + esc(k) + '"' + (k === val ? ' selected' : '') + '>' + esc(opts[k]) + '</option>'; });
    if (val && !seen) h += '<option value="' + esc(val) + '" selected>' + esc(val) + '</option>';
    return h + '</select>';
  }
  function input(name, field, val, type) {
    return '<input class="form-control" name="' + name + '" data-f="' + field + '" value="' + esc(val || '') + '"' + (type === 'tel' ? ' type="tel" inputmode="tel" maxlength="14"' : ' maxlength="150"') + '>';
  }
  function col(label, html, wide) { return '<div class="' + (wide ? 'col-12' : 'col-6 col-md-4') + '"><label class="form-label small mb-0">' + esc(label) + '</label>' + html + '</div>'; }
  function castes() { var o = {}; Object.keys(booth.castes || {}).forEach(function (c) { o[c] = c; }); return o; }
  function subs(caste) { var o = {}; ((booth.castes || {})[caste] || []).forEach(function (s) { o[s] = s; }); return o; }

  var open = null;
  function show(i) {
    var h = booth.houses[i]; open = i;
    var o = booth.opts || {}, hasCastes = Object.keys(booth.castes || {}).length > 0, ro = expired() ? ' disabled' : '';
    var html = '<div class="d-flex align-items-center gap-2 mb-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-off-back><i class="bi bi-list-ul"></i> ' + esc(L.back) + '</button>'
      + '<div class="me-auto"></div>'
      + (i > 0 ? '<button type="button" class="btn btn-outline-secondary btn-sm" data-house="' + (i - 1) + '"><i class="bi bi-chevron-left"></i></button>' : '')
      + '<span class="small text-muted">' + (i + 1) + ' / ' + booth.houses.length + '</span>'
      + (i < booth.houses.length - 1 ? '<button type="button" class="btn btn-outline-secondary btn-sm" data-house="' + (i + 1) + '"><i class="bi bi-chevron-right"></i></button>' : '')
      + '</div><h4 class="mb-0"><i class="bi bi-house-door"></i> ' + esc(L.house) + ' ' + esc(h.house || '-') + '</h4>'
      + '<p class="small text-muted mb-2">' + esc(h.addr) + ' · ' + esc(fmt(L.members, h.members.length)) + '</p><form id="offForm">';
    h.members.forEach(function (m) {
      var v = cur(m), p = 'm' + m.id;
      html += '<div class="card mb-2"><div class="card-body py-2"><div class="fw-semibold">' + esc(m.sl || '') + '. ' + esc(m.name) + (m.local ? ' <span class="text-muted">' + esc(m.local) + '</span>' : '')
        + '</div><div class="small text-muted mb-2">' + esc(m.rel || '') + ' · ' + esc(m.g || '') + ' · ' + esc(L.age) + ' ' + esc(m.age || '-') + '</div>';
      // one-tap leaning
      html += '<div class="d-flex flex-wrap gap-1 mb-2">';
      ['S', 'N', 'O', 'U'].forEach(function (k) {
        var name = (booth.quick || {})[k]; if (!name) return;
        html += '<button type="button" class="btn btn-sm ' + (v.political_status === name ? 'btn-primary' : 'btn-outline-primary') + '" data-quick="' + p + '" data-val="' + esc(name) + '"' + ro + '>' + esc(L[k]) + '</button>';
      });
      html += '</div><div class="row g-2">'
        + col(L.political, Object.keys(o.political_status || {}).length ? select(p + '.political_status', 'political_status', o.political_status, v.political_status, ro) : input(p + '.political_status', 'political_status', v.political_status))
        + col(L.mobile, input(p + '.mobile', 'mobile', v.mobile, 'tel'))
        + col(L.altMobile, input(p + '.alt_mobile', 'alt_mobile', v.alt_mobile, 'tel'))
        + col(L.caste, hasCastes ? select(p + '.caste', 'caste', castes(), v.caste, 'data-caste="' + p + '"' + ro) : input(p + '.caste', 'caste', v.caste))
        + col(L.subCaste, hasCastes ? select(p + '.sub_caste', 'sub_caste', subs(v.caste), v.sub_caste, ro) : input(p + '.sub_caste', 'sub_caste', v.sub_caste))
        + col(L.social, Object.keys(o.social_status || {}).length ? select(p + '.social_status', 'social_status', o.social_status, v.social_status, ro) : input(p + '.social_status', 'social_status', v.social_status))
        + col(L.scheme, Object.keys(o.beneficiary || {}).length ? select(p + '.beneficiary1', 'beneficiary1', o.beneficiary, v.beneficiary1, ro) : input(p + '.beneficiary1', 'beneficiary1', v.beneficiary1));
      if (booth.consent) html += col(L.consent, select(p + '.consent', 'consent', { Y: L.consentY, N: L.consentN }, v.consent, ro));
      html += col(L.remarks, input(p + '.remarks', 'remarks', v.remarks), true) + '</div></div></div>';
    });
    var loc = locs[h.members[0].id];
    html += '<div class="card card-body py-2 mb-2"><div class="d-flex align-items-center gap-2"><button type="button" class="btn btn-outline-primary btn-sm" data-off-geo' + ro + '><i class="bi bi-geo-alt"></i> ' + esc(L.location) + '</button>'
      + '<span class="small text-muted" id="offGeo">' + (loc ? esc(fmt(L.locSaved, Math.round(loc.acc || 0))) : '') + '</span></div></div>'
      + '<div class="survey-actions d-flex gap-2 py-2"><button type="submit" class="btn btn-primary flex-fill"' + ro + '><i class="bi bi-check2-all"></i> ' + esc(L.save) + '</button></div></form>';
    $('offHouse').innerHTML = html;
    $('offHouse').classList.remove('d-none');
    $('offList').classList.add('d-none');
    window.scrollTo(0, 0);
    if (ro) $('offHouse').querySelectorAll('input').forEach(function (e) { e.disabled = true; });
  }
  function back() { open = null; $('offHouse').classList.add('d-none'); $('offHouse').innerHTML = ''; list(); status(); }

  function saveHouse() {
    var h = booth.houses[open], form = $('offForm'), writes = [];
    h.members.forEach(function (m) {
      var p = 'm' + m.id, now = cur(m), changed = {};
      form.querySelectorAll('[name^="' + p + '."]').forEach(function (el) {
        var f = el.getAttribute('data-f'), val = el.value.trim() || null;
        if ((now[f] || null) !== val) changed[f] = val;
      });
      if (!Object.keys(changed).length) return;
      var q = queue[m.id] || { id: m.id, v: {}, base: {} };
      Object.keys(changed).forEach(function (f) { if (!(f in q.base)) q.base[f] = m.v[f] == null ? null : m.v[f]; q.v[f] = changed[f]; });
      queue[m.id] = q; writes.push(q);
    });
    return Promise.all(writes.map(function (q) { return tx('queue', 'readwrite', function (s) { s.put(q); }); })).then(function () {
      flash(fmt(L.saved, writes.length, h.house || '-'), 'success');
      if (open < booth.houses.length - 1) show(open + 1); else back();
      status();
      if (navigator.onLine) sync(true);
    });
  }

  function flash(msg, kind) { $('offResult').innerHTML = '<span class="text-' + (kind || 'muted') + '">' + esc(msg) + '</span>'; }

  // ------------------------------------------------------------ download / sync
  function token() {
    return fetch(app.getAttribute('data-token-url'), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); }).then(function (j) { return j.token; });
  }

  var busy = false;
  function sync(quiet) {
    if (busy || !navigator.onLine || !waiting()) { if (!quiet && !waiting()) flash(L.nothing); return Promise.resolve(); }
    busy = true; if (!quiet) flash(L.sending);
    var body = { changes: Object.keys(queue).map(function (k) { return queue[k]; }), locations: Object.keys(locs).map(function (k) { return locs[k]; }) };
    return token().then(function (t) {
      return fetch(app.getAttribute('data-sync'), { method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': t }, body: JSON.stringify(body) });
    }).then(function (r) {
      if (r.status === 401 || r.status === 419) throw new Error(L.loginAgain);
      if (!r.ok) throw new Error(L.failed + ' (' + r.status + ')');
      return r.json();
    }).then(function (res) {
      var done = res.done || [];
      // accepted: fold into the downloaded copy so the house shows as surveyed, then remove from the queue
      if (booth) booth.houses.forEach(function (h) { h.members.forEach(function (m) { if (done.indexOf(m.id) >= 0 && queue[m.id]) Object.assign(m.v, queue[m.id].v); }); });
      done.forEach(function (id) { delete queue[id]; });
      locs = {};
      return Promise.all([putBooth(booth), tx('locs', 'readwrite', function (s) { s.clear(); }),
        tx('queue', 'readwrite', function (s) { done.forEach(function (id) { s.delete(id); }); })]).then(function () {
        var msg = fmt(L.sent, res.saved, res.located);
        if (res.conflicts) msg += ' · ' + fmt(L.conflicts, res.conflicts);
        if (res.errors && res.errors.length) msg += ' · ' + fmt(L.errors, res.errors.length) + ': ' + res.errors.map(function (e) { return e.name + ' - ' + e.error; }).join('; ');
        flash(msg, res.errors && res.errors.length ? 'danger' : 'success');
        if (!booth) return clearAll();
      });
    }).catch(function (e) { flash(e.message || L.failed, 'danger'); })
      .then(function () { busy = false; list(); status(); });
  }

  function download(url) {
    if (waiting() && !confirm(L.confirmReplace)) return;
    var go = function () {
      flash(L.downloading);
      return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) {
        if (r.status === 401) throw new Error(L.loginAgain);
        if (!r.ok) throw new Error(L.failed + ' (' + r.status + ')');
        return r.json();
      }).then(function (b) {
        booth = b;
        return putBooth(b);
      }).then(function () { flash(fmt(L.downloaded, booth.booth, new Date(booth.at).toLocaleString()), 'success'); list(); status(); })
        .catch(function (e) { flash(e.message, 'danger'); status(); });
    };
    // unsent answers go first, so nothing is lost
    (waiting() ? sync(true) : Promise.resolve()).then(go);
  }

  function geo() {
    var h = booth.houses[open], out = $('offGeo');
    if (!navigator.geolocation) { out.textContent = L.locFailed; return; }
    out.textContent = L.locTaking;
    navigator.geolocation.getCurrentPosition(function (p) {
      var l = { id: h.members[0].id, lat: p.coords.latitude, lng: p.coords.longitude, acc: p.coords.accuracy };
      locs[l.id] = l;
      tx('locs', 'readwrite', function (s) { s.put(l); }).then(function () { out.textContent = fmt(L.locSaved, Math.round(l.acc)); status(); });
    }, function () { out.textContent = L.locFailed; }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 30000 });
  }

  // ------------------------------------------------------------ events
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-house],[data-off-back],[data-off-sync],[data-off-remove],[data-off-download],[data-quick],[data-off-geo]');
    if (!t || !app.contains(t)) return;
    e.preventDefault();
    if (t.hasAttribute('data-house')) show(+t.getAttribute('data-house'));
    else if (t.hasAttribute('data-off-back')) back();
    else if (t.hasAttribute('data-off-sync')) sync(false);
    else if (t.hasAttribute('data-off-download')) download(t.getAttribute('data-off-download'));
    else if (t.hasAttribute('data-off-geo')) geo();
    else if (t.hasAttribute('data-quick')) {
      var sel = $('offForm').querySelector('[name="' + t.getAttribute('data-quick') + '.political_status"]'), val = t.getAttribute('data-val');
      if (sel.tagName === 'SELECT' && !Array.prototype.some.call(sel.options, function (o) { return o.value === val; })) sel.add(new Option(val, val));
      sel.value = val;
      t.parentNode.querySelectorAll('[data-quick]').forEach(function (b) { b.className = 'btn btn-sm ' + (b === t ? 'btn-primary' : 'btn-outline-primary'); });
    } else if (t.hasAttribute('data-off-remove')) {
      if (!confirm(L.confirmRemove)) return;
      clearAll().then(function () { booth = null; queue = {}; locs = {}; back(); flash(''); });
    }
  });
  document.addEventListener('change', function (e) {
    var c = e.target.closest('[data-caste]');
    if (!c) return;
    var sub = $('offForm').querySelector('[name="' + c.getAttribute('data-caste') + '.sub_caste"]');
    if (sub && sub.tagName === 'SELECT') sub.outerHTML = select(sub.name, 'sub_caste', subs(c.value), null);
  });
  document.addEventListener('submit', function (e) { if (e.target.id === 'offForm') { e.preventDefault(); saveHouse(); } });
  $('offFind').addEventListener('input', list);
  window.addEventListener('online', function () { net(); sync(true); });
  window.addEventListener('offline', net);

  // ------------------------------------------------------------ start
  load().then(function () {
    // another login on this phone: the previous user's booth is not theirs to see
    if (booth && booth.user !== me) return clearAll().then(load).then(function () { flash(L.otherUser, 'danger'); });
    // past the keep period and nothing waiting: remove
    if (expired() && !waiting()) return clearAll().then(load);
  }).then(function () { net(); list(); status(); if (navigator.onLine) sync(true); })
    .catch(function () { flash(L.failed, 'danger'); });
})();
