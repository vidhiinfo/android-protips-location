/*
 * Offline field app (/field): booth pack on the phone (IndexedDB "vis-field"), offline voter search, offline poll-day marking.
 * Every action goes into one outbox with a unique id and is sent in batches whenever there is signal; re-sending is harmless.
 * The pack is removed after OFFLINE_KEEP_DAYS, on logout and when another user logs in on the same phone.
 */
(function () {
  'use strict';
  var app = document.getElementById('fieldApp');
  if (!app) return;
  var L = JSON.parse(app.getAttribute('data-i18n') || '{}');
  var me = app.getAttribute('data-user') || '';
  var MAX = +app.getAttribute('data-max') || 5;
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var fmt = function (s) { var a = arguments; return String(s).replace(/\{(\d+)\}/g, function (m, i) { return a[+i + 1] != null ? a[+i + 1] : m; }); };
  var uid = function () { return Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10); };

  // ------------------------------------------------------------ storage
  var dbp = null;
  function db() {
    if (dbp) return dbp;
    dbp = new Promise(function (ok, fail) {
      var r = indexedDB.open('vis-field', 1);
      r.onupgradeneeded = function () { var d = r.result; d.createObjectStore('meta'); d.createObjectStore('packs'); d.createObjectStore('outbox', { keyPath: 'uid' }); };
      r.onsuccess = function () { ok(r.result); };
      r.onerror = function () { fail(r.error); };
    });
    return dbp;
  }
  function tx(store, mode, fn) {
    return db().then(function (d) {
      return new Promise(function (ok, fail) {
        var t = d.transaction(store, mode), out = fn(t.objectStore(store));
        t.oncomplete = function () { ok(out && out.result !== undefined ? out.result : out); };
        t.onerror = function () { fail(t.error); };
      });
    });
  }
  var put = function (store, val, key) { return tx(store, 'readwrite', function (s) { key === undefined ? s.put(val) : s.put(val, key); }); };
  var all = function (store) { return tx(store, 'readonly', function (s) { return s.getAll(); }); };
  var clear = function (store) { return tx(store, 'readwrite', function (s) { s.clear(); }); };

  var meta = null, packs = {}, outbox = [], tab = 'search', pollBooth = null, onlySup = false;
  var key = function (a, b) { return a + '|' + b; };

  function load() {
    return Promise.all([tx('meta', 'readonly', function (s) { return s.get('info'); }), all('packs'), all('outbox')]).then(function (r) {
      meta = r[0] || null; packs = {};
      r[1].forEach(function (p) { packs[key(p.a, p.no)] = p; });
      outbox = r[2].sort(function (x, y) { return x.seq - y.seq; });
    });
  }
  var savePack = function (p) { return put('packs', p, key(p.a, p.no)); };
  var hasPacks = function () { return Object.keys(packs).length > 0; };
  var expired = function () { return meta && (Date.now() - Date.parse(meta.at)) > (meta.keepDays || 3) * 86400000; };
  function wipe() { meta = null; packs = {}; outbox = []; return Promise.all([clear('meta'), clear('packs'), clear('outbox')]); }

  // ------------------------------------------------------------ status bar
  function status() {
    var on = navigator.onLine;
    $('fNet').innerHTML = on ? '<span class="text-success fw-semibold"><i class="bi bi-wifi"></i> ' + esc(L.online) + '</span>'
      : '<span class="text-danger fw-semibold"><i class="bi bi-wifi-off"></i> ' + esc(L.offline) + '</span>';
    $('fWait').innerHTML = outbox.length ? '<span class="badge text-bg-warning">' + esc(fmt(L.waiting, outbox.length)) + '</span>' : '';
    $('fLast').textContent = fmt(L.synced, meta && meta.syncedAt ? new Date(meta.syncedAt).toLocaleTimeString() : L.never);
    document.querySelectorAll('[data-online-only]').forEach(function (e) { e.classList.toggle('d-none', !on); });
    var have = Object.keys(packs).map(function (k) { return packs[k]; });
    $('fHave').innerHTML = have.length ? '<i class="bi bi-phone"></i> ' + esc(fmt(L.downloaded, have.map(function (p) { return p.no; }).join(', '), meta ? new Date(meta.at).toLocaleString() : ''))
      + ' · ' + esc(fmt(L.votersCount, have.reduce(function (n, p) { return n + p.voters.length; }, 0)))
      + (expired() ? ' · <span class="text-danger">' + esc(L.expired) + '</span>' : '')
      + ' <a href="#" class="text-danger ms-2" data-f-remove><i class="bi bi-trash"></i></a>' : '';
    $('fTabs').classList.toggle('d-none', !hasPacks());
    $('fSearch').classList.toggle('d-none', !hasPacks() || tab !== 'search');
    $('fPoll').classList.toggle('d-none', !hasPacks() || tab !== 'poll');
  }
  // short messages float at the bottom of the screen, newest only, so the grid does not jump
  var toast = document.createElement('div');
  toast.style.cssText = 'position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:1080;max-width:92vw;min-width:240px';
  document.body.appendChild(toast);
  var toastTimer = null;
  function note(msg, kind) {
    toast.innerHTML = '<div class="alert alert-' + (kind || 'info') + ' py-2 small shadow mb-0 text-center">' + esc(msg) + '</div>';
    clearTimeout(toastTimer); toastTimer = setTimeout(function () { toast.innerHTML = ''; }, 3500);
  }

  // ------------------------------------------------------------ search (works offline)
  var norm = function (s) { return String(s || '').toLowerCase().normalize('NFC').replace(/[\s.\-\/]/g, ''); };
  function leanBadge(l) {
    var m = { S: ['primary', L.support], N: ['secondary', L.neutral], O: ['danger', L.opposition], U: ['dark', L.notMet] }[l];
    return m ? '<span class="badge text-bg-' + m[0] + '">' + esc(m[1]) + '</span>' : '';
  }
  function card(p, v) {
    return '<div class="list-group-item"><div class="d-flex gap-2 align-items-start"><div class="me-auto"><div class="fw-semibold">' + esc(v.sl) + '. ' + esc(v.n)
      + (v.nl ? ' <span class="text-muted">' + esc(v.nl) + '</span>' : '') + '</div>'
      + '<div class="small text-muted">' + esc(v.r || '') + (v.rl ? ' (' + esc(v.rl) + ')' : '') + ' · ' + esc(v.g || '') + ' · ' + esc(L.age) + ' ' + esc(v.a || '-') + '</div>'
      + '<div class="small">' + esc(L.booth) + ' ' + esc(p.no) + ' · ' + esc(L.serial) + ' ' + esc(v.sl) + ' · ' + esc(L.house) + ' ' + esc(v.h || '-') + ' · ' + esc(v.epic)
      + (v.m ? ' · <a href="tel:' + esc(v.m) + '">' + esc(v.m) + '</a>' : '') + '</div></div>'
      + '<div class="text-end">' + leanBadge(v.l) + (v.v ? '<div><span class="badge text-bg-success mt-1"><i class="bi bi-check2"></i> ' + esc(L.voted) + '</span></div>' : '') + '</div></div>'
      + (v.h ? '<a href="#" class="small" data-f-family="' + esc(key(p.a, p.no)) + '" data-house="' + esc(v.h) + '"><i class="bi bi-house"></i> ' + esc(L.family) + '</a>' : '') + '</div>';
  }
  function search() {
    var q = norm($('fQ').value), out = [], box = $('fResults');
    if (q.length < 1) { box.innerHTML = '<div class="text-muted small">' + esc(L.typeToSearch) + '</div>'; return; }
    var num = /^\d+$/.test(q);
    Object.keys(packs).forEach(function (k) {
      var p = packs[k];
      p.voters.forEach(function (v) {
        if (out.length >= 60) return;
        var hit = num ? (String(v.sl) === q || norm(v.h) === q) : (norm(v.n).indexOf(q) >= 0 || norm(v.nl).indexOf(q) >= 0 || norm(v.epic) === q || norm(v.r).indexOf(q) >= 0 || norm(v.rl).indexOf(q) >= 0 || norm(v.h) === q);
        if (hit) out.push(card(p, v));
      });
    });
    box.innerHTML = out.length ? '<div class="list-group">' + out.join('') + '</div>' : '<div class="text-muted small">' + esc(L.noResults) + '</div>';
  }
  function family(k, h) {
    var p = packs[k], hk = norm(h);
    $('fResults').innerHTML = '<div class="small fw-semibold mb-1"><i class="bi bi-house"></i> ' + esc(L.house) + ' ' + esc(h) + ' · ' + esc(L.booth) + ' ' + esc(p.no) + '</div><div class="list-group">'
      + p.voters.filter(function (v) { return norm(v.h) === hk; }).map(function (v) { return card(p, v); }).join('') + '</div>';
  }

  // ------------------------------------------------------------ poll day (works offline)
  function pollView() {
    var box = $('fPoll');
    if (!meta || !meta.election) { box.innerHTML = '<div class="alert alert-info small">' + esc(L.noElection) + '</div>'; return; }
    if (!meta.canPoll) { box.innerHTML = '<div class="alert alert-info small">' + esc(L.noPoll) + '</div>'; return; }
    var keys = Object.keys(packs).sort(function (x, y) { return packs[x].no - packs[y].no; });
    if (!pollBooth || !packs[pollBooth]) pollBooth = keys[0];
    var p = packs[pollBooth], n = p.voters.length, voted = 0, sup = 0, supV = 0;
    p.voters.forEach(function (v) { if (v.v) voted++; if (v.l === 'S') { sup++; if (v.v) supV++; } });
    var h = '<div class="d-flex flex-wrap gap-2 align-items-center mb-2"><select class="form-select form-select-sm" style="max-width:12rem" data-f-pollbooth>'
      + keys.map(function (k) { return '<option value="' + esc(k) + '"' + (k === pollBooth ? ' selected' : '') + '>' + esc(L.booth) + ' ' + esc(packs[k].no) + '</option>'; }).join('') + '</select>'
      + '<span class="fw-semibold">' + esc(meta.election.name) + '</span></div>'
      + '<div class="row g-2 mb-2"><div class="col-6"><div class="stat"><div class="small text-muted">' + esc(L.turnout) + '</div><div class="num">' + voted + ' / ' + n
      + ' <span class="small text-muted">(' + (n ? Math.round(100 * voted / n) : 0) + '%)</span></div></div></div>'
      + '<div class="col-6"><div class="stat"><div class="small text-muted">' + esc(L.supportersLeft) + '</div><div class="num text-primary">' + (sup - supV) + ' / ' + sup + '</div></div></div></div>'
      + '<form class="d-flex gap-2 mb-2" data-f-markform><input class="form-control" inputmode="numeric" name="sl" placeholder="' + esc(L.serialPh) + '" autocomplete="off" data-scan="submit">'
      + '<button class="btn btn-success text-nowrap"><i class="bi bi-check2"></i> ' + esc(L.markBtn) + '</button></form>'
      + '<div class="form-check small mb-2"><input class="form-check-input" type="checkbox" id="fOnlySup" data-f-onlysup' + (onlySup ? ' checked' : '') + '><label class="form-check-label" for="fOnlySup">'
      + esc(L.onlySupporters) + '</label></div><div class="d-flex flex-wrap gap-1">';
    p.voters.forEach(function (v) {
      if (onlySup && v.l !== 'S') return;
      h += '<button type="button" class="btn btn-sm ' + (v.v ? 'btn-success' : (v.l === 'S' ? 'btn-outline-primary' : 'btn-outline-secondary')) + '" style="min-width:3.2rem" data-f-vid="' + v.id + '" title="' + esc(v.n) + '">' + esc(v.sl) + '</button>';
    });
    box.innerHTML = h + '</div>';
  }
  function setVote(p, v, voted) {
    v.v = voted ? 1 : 0;
    var item = { uid: uid(), seq: Date.now() + Math.random(), type: voted ? 'mark' : 'unmark', voter_id: v.id, election_id: meta.election.id, at: new Date().toISOString(), a: p.a, b: p.no };
    outbox.push(item);
    return Promise.all([put('outbox', item), savePack(p)]).then(function () { pollView(); status(); kick(); });
  }
  function tapVoter(id) {
    var p = packs[pollBooth], v = p.voters.find(function (x) { return x.id === id; });
    if (!v) return;
    if (v.v) { if (confirm(fmt(L.confirmUnmark, v.sl, v.n))) setVote(p, v, false).then(function () { note(fmt(L.unmarked, v.sl), 'secondary'); }); return; }
    setVote(p, v, true).then(function () { note(fmt(L.markOk, v.sl, v.n), 'success'); });
  }

  // ------------------------------------------------------------ server: download / sync
  function token() {
    return fetch(app.getAttribute('data-token-url'), { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) { return j.token; });
  }
  var busy = false, timer = null;
  function kick() { if (navigator.onLine) { clearTimeout(timer); timer = setTimeout(function () { sync(true); }, 800); } }

  /** Sends up to 500 outbox items, applies the server's marks, keeps what is still unsent on top. */
  function sync(quiet) {
    if (busy || !navigator.onLine || !meta) return Promise.resolve();
    busy = true; if (!quiet) note(L.sending);
    var batch = outbox.slice(0, 500);
    var booths = Object.keys(packs).map(function (k) { return { a: packs[k].a, b: packs[k].no }; });
    return token().then(function (t) {
      return fetch(app.getAttribute('data-sync'), { method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': t },
        body: JSON.stringify({ items: batch.map(function (i) { return { uid: i.uid, type: i.type, voter_id: i.voter_id, election_id: i.election_id, at: i.at }; }), booths: booths }) });
    }).then(function (r) {
      if (r.status === 401 || r.status === 419) throw new Error(L.loginAgain);
      if (!r.ok) throw new Error(L.failed + ' (' + r.status + ')');
      return r.json();
    }).then(function (res) {
      var gone = {};
      (res.done || []).forEach(function (u) { gone[u] = 1; });
      (res.rejected || []).forEach(function (x) { gone[x.uid] = 1; });
      if ((res.rejected || []).length) note(fmt(L.rejected, res.rejected.length), 'warning');
      outbox = outbox.filter(function (i) { return !gone[i.uid]; });
      // server marks (all agents) + this phone's unsent changes on top
      Object.keys(res.marks || {}).forEach(function (k) {
        var p = packs[k]; if (!p) return;
        var set = {}; res.marks[k].forEach(function (id) { set[id] = 1; });
        p.voters.forEach(function (v) { v.v = set[v.id] ? 1 : 0; });
        outbox.forEach(function (i) { if (key(i.a, i.b) !== k) return; var v = p.voters.find(function (x) { return x.id === i.voter_id; }); if (v) v.v = i.type === 'mark' ? 1 : 0; });
      });
      meta.syncedAt = res.at;
      return Promise.all([tx('outbox', 'readwrite', function (s) { Object.keys(gone).forEach(function (u) { s.delete(u); }); }), put('meta', meta, 'info')]
        .concat(Object.keys(packs).map(function (k) { return savePack(packs[k]); }))).then(function () {
        if (!quiet) note(L.sentOk, 'success');
        if (outbox.length) setTimeout(function () { busy = false; sync(true); }, 300);   // more than one batch waiting
      });
    }).catch(function (e) { if (!quiet || e.message === L.loginAgain) note(e.message || L.failed, 'danger'); })
      .then(function () { busy = false; status(); if (tab === 'poll') pollView(); else search(); });
  }

  function download(form) {
    var fd = new FormData(form), bs = fd.getAll('booths[]');
    if (!bs.length) { note(L.pickBooths, 'warning'); return; }
    if (bs.length > MAX) { note(fmt(L.tooMany, MAX), 'warning'); return; }
    if (outbox.length && !confirm(L.confirmReplace)) return;
    var go = function () {
      note(L.downloading);
      var q = new URLSearchParams(); q.set('assemblyNo', fd.get('assemblyNo')); bs.forEach(function (b) { q.append('booths[]', b); });
      return fetch(app.getAttribute('data-pack') + '?' + q.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) {
        if (r.status === 401) throw new Error(L.loginAgain);
        if (!r.ok) throw new Error(L.failed + ' (' + r.status + ')');
        return r.json();
      }).then(function (pk) {
        return Promise.all([clear('packs')]).then(function () {
          packs = {};
          meta = { assembly: pk.assembly, user: pk.user, at: pk.at, keepDays: pk.keepDays, election: pk.election, canPoll: pk.canPoll, syncedAt: pk.at };
          var w = [put('meta', meta, 'info')];
          pk.booths.forEach(function (b) { var p = { a: pk.assembly, no: b.no, name: b.name, voters: b.voters }; packs[key(p.a, p.no)] = p; w.push(savePack(p)); });
          return Promise.all(w);
        });
      }).then(function () { note(fmt(L.downloaded, Object.keys(packs).map(function (k) { return packs[k].no; }).join(', '), new Date(meta.at).toLocaleString()), 'success'); status(); search(); })
        .catch(function (e) { note(e.message, 'danger'); });
    };
    (outbox.length ? sync(true) : Promise.resolve()).then(go);   // unsent marks go first
  }

  // ------------------------------------------------------------ events
  app.addEventListener('click', function (e) {
    var t = e.target.closest('[data-f-tab],[data-f-sync],[data-f-remove],[data-f-vid],[data-f-family]');
    if (!t) return;
    e.preventDefault();
    if (t.hasAttribute('data-f-tab')) {
      tab = t.getAttribute('data-f-tab');
      app.querySelectorAll('[data-f-tab]').forEach(function (a) { a.classList.toggle('active', a === t); });
      status(); if (tab === 'poll') pollView(); else search();
    } else if (t.hasAttribute('data-f-sync')) sync(false);
    else if (t.hasAttribute('data-f-vid')) tapVoter(+t.getAttribute('data-f-vid'));
    else if (t.hasAttribute('data-f-family')) family(t.getAttribute('data-f-family'), t.getAttribute('data-house'));
    else if (t.hasAttribute('data-f-remove')) { if (confirm(L.confirmRemove)) wipe().then(function () { status(); $('fResults').innerHTML = ''; }); }
  });
  app.addEventListener('change', function (e) {
    if (e.target.matches('[data-f-pollbooth]')) { pollBooth = e.target.value; pollView(); }
    else if (e.target.matches('[data-f-onlysup]')) { onlySup = e.target.checked; pollView(); }
    else if (e.target.matches('[data-f-assembly]')) location.href = location.pathname + '?assemblyNo=' + encodeURIComponent(e.target.value);
  });
  app.addEventListener('submit', function (e) {
    if (e.target.id === 'fDownload') { e.preventDefault(); download(e.target); return; }
    if (e.target.matches('[data-f-markform]')) {
      e.preventDefault();
      var sl = e.target.sl.value.trim(), p = packs[pollBooth], up = sl.toUpperCase();
      // a serial number, or an EPIC number (typed or scanned from the voter slip / card)
      var v = p && p.voters.find(function (x) { return String(x.sl) === sl || (x.epic && String(x.epic).toUpperCase() === up); });
      if (!v && /[A-Z]/.test(up)) {   // scanned card from another downloaded booth: switch to it
        Object.keys(packs).some(function (k) { var w = packs[k].voters.find(function (x) { return x.epic && String(x.epic).toUpperCase() === up; }); if (w) { pollBooth = k; p = packs[k]; v = w; return true; } return false; });
      }
      if (!v) { note(fmt(L.notFound, sl), 'warning'); return; }
      if (v.v) { note(fmt(L.alreadyMarked, v.sl, v.n), 'warning'); return; }
      setVote(p, v, true).then(function () { note(fmt(L.markOk, v.sl, v.n), 'success'); var i = $('fPoll').querySelector('[name=sl]'); if (i) i.focus(); });
    }
  });
  app.addEventListener('change', function (e) {
    var c = e.target.closest('#fDownload input[type=checkbox]');
    if (c) c.parentNode.classList.toggle('active', c.checked);
  });
  $('fQ').addEventListener('input', search);
  window.addEventListener('online', function () { status(); sync(true); });
  window.addEventListener('offline', status);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) sync(true); });
  setInterval(function () { if (!document.hidden) sync(true); }, 30000);   // background: send the outbox, refresh other agents' marks

  // ------------------------------------------------------------ start
  load().then(function () {
    if (meta && meta.user !== me) return wipe().then(function () { note(L.otherUser, 'warning'); });
    if (expired() && !outbox.length) return wipe();
  }).then(function () { status(); search(); if (navigator.onLine) sync(true); })
    .catch(function () { note(L.failed, 'danger'); });
})();
