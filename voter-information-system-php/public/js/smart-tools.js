/*
 * Smart tools (Admin > Settings > Smart tools), each switched on or off there:
 *  - Scan: camera button next to fields marked data-scan. Reads the barcode printed on voter slips, the QR code on
 *    new voter cards (built-in BarcodeDetector, Android Chrome) or the printed EPIC number (Tesseract OCR, served from
 *    this site, so it also works on iPhone and, once loaded, without internet). Nothing leaves the phone.
 *  - Voice: microphone button next to fields marked data-voice; speech to text in the screen language
 *    (browser speech service, needs internet).
 * data-scan="submit" submits the form after a scan; data-scan="input" only fills the field (and fires "input").
 */
(function () {
  'use strict';
  var body = document.body;
  if (!body) return;
  var SCAN = body.getAttribute('data-vt-scan') === '1', VOICE = body.getAttribute('data-vt-voice') === '1';
  if (!SCAN && !VOICE) return;
  var L = {};
  try { L = JSON.parse(body.getAttribute('data-tools-i18n') || '{}'); } catch (e) { /* labels fall back to English */ }
  var t = function (k, d) { return L[k] || d; };
  // the site's own folder (/vis/, /pbvis/ ...), taken from this script's address
  var me = document.currentScript || document.querySelector('script[src*="js/smart-tools.js"]');
  var base = me ? me.src.replace(/js\/smart-tools\.js.*$/, '') : '/';
  var EPIC = /\b([A-Z]{3}[0-9]{7})\b/;
  var OLD_EPIC = /\b([A-Z]{2}\/[0-9]{2}\/[0-9]{3}\/[0-9]{6,7})\b/;

  // OCR mixes up O/0, I/1, S/5, B/8, Z/2: EPIC is 3 letters + 7 digits, so each part is corrected on its own
  var TO_L = { '0': 'O', '1': 'I', '5': 'S', '8': 'B', '2': 'Z' }, TO_D = { O: '0', Q: '0', D: '0', I: '1', L: '1', S: '5', B: '8', Z: '2' };
  function epicFrom(text) {
    var s = String(text || '').toUpperCase();
    var m = s.match(OLD_EPIC);
    if (m) return m[1];
    var parts = s.split(/[^A-Z0-9]+/).filter(Boolean), toks = parts.slice();
    for (var k = 0; k + 1 < parts.length; k++) toks.push(parts[k] + parts[k + 1]);   // "XYZ 1234567"
    for (var i = 0; i < toks.length; i++) {
      var tk = toks[i];
      for (var j = 0; j + 10 <= tk.length; j++) {
        var c = tk.substr(j, 10);
        var fixed = c.slice(0, 3).replace(/[0-9]/g, function (x) { return TO_L[x] || x; }) + c.slice(3).replace(/[A-Z]/g, function (x) { return TO_D[x] || x; });
        if (EPIC.test(fixed) && /[A-Z]{3}/.test(c.slice(0, 3).replace(/[0-9]/g, function (x) { return TO_L[x] || '#'; })) && (c.slice(3).match(/[0-9]/g) || []).length >= 6) return fixed;
      }
    }
    return null;
  }

  // ---------------------------------------------------------------- buttons next to the fields
  function group(el) {
    if (el.parentNode && el.parentNode.classList && el.parentNode.classList.contains('input-group')) return el.parentNode;
    if (el.tagName === 'TEXTAREA') return null;
    var g = document.createElement('div');
    g.className = 'input-group';
    ['mb-2', 'mb-3', 'w-100'].forEach(function (c) { if (el.classList.contains(c)) { el.classList.remove(c); g.classList.add(c); } });
    el.parentNode.insertBefore(g, el); g.appendChild(el);
    return g;
  }
  function addButton(el, icon, title, cls, onClick) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'btn btn-outline-secondary ' + cls; b.title = title; b.setAttribute('aria-label', title);
    b.innerHTML = '<i class="bi bi-' + icon + '"></i>';
    b.addEventListener('click', function (e) { e.preventDefault(); onClick(b); });
    var g = group(el);
    if (g) g.appendChild(b);
    else { b.className = 'btn btn-sm btn-outline-secondary mt-1 ' + cls; b.innerHTML += ' ' + title; el.parentNode.insertBefore(b, el.nextSibling); }
    return b;
  }
  function enhance(root) {
    if (SCAN) root.querySelectorAll('input[data-scan]:not([data-scan-ready]), textarea[data-scan]:not([data-scan-ready])').forEach(function (el) {
      el.setAttribute('data-scan-ready', '1');
      addButton(el, 'upc-scan', t('scan', 'Scan card'), 'vt-scan', function () {
        openScanner(function (code) {
          el.value = code;
          el.dispatchEvent(new Event('input', { bubbles: true }));
          if (el.getAttribute('data-scan') === 'submit' && el.form) {
            if (el.form.requestSubmit) el.form.requestSubmit(); else el.form.submit();
          }
        });
      });
    });
    if (VOICE && SpeechRec) root.querySelectorAll('input[data-voice]:not([data-voice-ready]), textarea[data-voice]:not([data-voice-ready])').forEach(function (el) {
      el.setAttribute('data-voice-ready', '1');
      addButton(el, 'mic', t('speak', 'Speak'), 'vt-mic', function (b) { listen(el, b); });
    });
  }

  // ---------------------------------------------------------------- voice typing
  var SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
  var LANGS = { en: 'en-IN', hi: 'hi-IN', pa: 'pa-IN', mr: 'mr-IN', gu: 'gu-IN', bn: 'bn-IN', ta: 'ta-IN', te: 'te-IN', ur: 'ur-IN' };
  var active = null;
  function listen(el, btn) {
    if (active) { active.stop(); return; }
    if (!navigator.onLine) { toast(t('voiceOffline', 'Voice typing needs internet.'), 'warning'); return; }
    var r = new SpeechRec();
    r.lang = LANGS[(document.documentElement.lang || 'en').slice(0, 2)] || 'en-IN';
    r.interimResults = false; r.maxAlternatives = 1;
    r.onstart = function () { active = r; btn.classList.add('btn-danger', 'text-white'); toast(t('listening', 'Listening… speak now'), 'info'); };
    r.onerror = function (e) { toast(t('voiceError', 'Could not hear that. Try again.') + (e.error === 'not-allowed' ? ' (' + t('micBlocked', 'microphone blocked') + ')' : ''), 'warning'); };
    r.onend = function () { active = null; btn.classList.remove('btn-danger', 'text-white'); };
    r.onresult = function (e) {
      var said = e.results[0][0].transcript.trim();
      if (el.tagName === 'TEXTAREA' && el.value.trim()) el.value = el.value.replace(/\s*$/, ' ') + said;
      else el.value = said;
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.focus();
    };
    try { r.start(); } catch (e) { active = null; }
  }

  // ---------------------------------------------------------------- scanner
  var ui = null, stream = null, timer = null, detector = null, done = null;
  function openScanner(cb) {
    done = cb;
    if (!ui) build();
    ui.box.classList.remove('d-none');
    say(t('scanHelp', 'Point the camera at the barcode or QR code, or fit the EPIC number inside the box and press "Read printed number".'));
    if ('BarcodeDetector' in window && !detector) {
      try { detector = new window.BarcodeDetector({ formats: ['qr_code', 'code_128', 'code_39'] }); } catch (e) { detector = null; }
    }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { noCamera(); return; }
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: { ideal: 1280 } }, audio: false }).then(function (s) {
      stream = s; ui.video.srcObject = s; ui.video.play();
      if (detector) timer = setInterval(detect, 350);
    }).catch(noCamera);
  }
  function noCamera() { say(t('noCamera', 'Camera not available here. Take a photo of the card instead.'), 'warning'); }
  function close() {
    if (timer) clearInterval(timer); timer = null;
    if (stream) stream.getTracks().forEach(function (k) { k.stop(); }); stream = null;
    if (ui) ui.box.classList.add('d-none');
  }
  function found(code) {
    close();
    toast(t('found', 'Found') + ' ' + code, 'success');
    if (done) done(code);
  }
  function detect() {
    if (!detector || !ui.video.videoWidth) return;
    detector.detect(ui.video).then(function (codes) {
      for (var i = 0; i < codes.length; i++) {
        var raw = String(codes[i].rawValue || '').trim(), e = epicFrom(raw);
        if (e) { found(e); return; }
        if (/^[A-Z0-9\/]{8,20}$/i.test(raw)) { found(raw.toUpperCase()); return; }   // our slip barcode is the EPIC itself
      }
    }).catch(function () { /* next frame */ });
  }
  function frame(src) {
    // the guide box: middle 84% x 34% of the picture
    var w = src.videoWidth || src.naturalWidth || src.width, h = src.videoHeight || src.naturalHeight || src.height;
    var cw = Math.round(w * 0.84), ch = Math.round(h * 0.34), c = document.createElement('canvas');
    var scale = Math.min(2, 1600 / cw);
    c.width = Math.round(cw * scale); c.height = Math.round(ch * scale);
    var g = c.getContext('2d');
    g.filter = 'grayscale(1) contrast(1.4)';
    g.drawImage(src, Math.round((w - cw) / 2), Math.round((h - ch) / 2), cw, ch, 0, 0, c.width, c.height);
    return c;
  }
  var ocrWorker = null;
  function ocr(canvas) {
    say(t('reading', 'Reading the number…'), 'info');
    var ready = ocrWorker ? Promise.resolve(ocrWorker) : loadScript(base + 'vendor/tesseract/tesseract.min.js').then(function () {
      return window.Tesseract.createWorker('eng', 1, {
        workerPath: base + 'vendor/tesseract/worker.min.js', corePath: base + 'vendor/tesseract/core', langPath: base + 'vendor/tesseract/lang',
        workerBlobURL: false, gzip: true
      });
    }).then(function (w) {
      ocrWorker = w;
      return w.setParameters({ tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789/' }).then(function () { return w; });
    });
    return ready.then(function (w) { return w.recognize(canvas); }).then(function (r) {
      var e = epicFrom(r.data.text);
      if (e) found(e); else say(t('notRead', 'Could not read an EPIC number. Hold steady, a little closer, in good light, and try again.') + (r.data.text.trim() ? ' (' + r.data.text.trim().slice(0, 40) + ')' : ''), 'warning');
    }).catch(function () { say(t('ocrFailed', 'Number reading is not available on this phone. Type the EPIC number instead.'), 'danger'); });
  }
  function fromPhoto(file) {
    if (!file) return;
    var img = new Image();
    img.onload = function () {
      if (detector) {
        detector.detect(img).then(function (codes) {
          var e = codes.length ? (epicFrom(codes[0].rawValue) || String(codes[0].rawValue).trim().toUpperCase()) : null;
          if (e) found(e); else ocr(frame(img));
        }).catch(function () { ocr(frame(img)); });
      } else ocr(frame(img));
    };
    // a data: URL (the page's security policy allows those, not blob: URLs)
    var fr = new FileReader();
    fr.onload = function () { img.src = fr.result; };
    fr.readAsDataURL(file);
  }
  function loadScript(src) {
    return new Promise(function (ok, bad) {
      if (window.Tesseract) { ok(); return; }
      var s = document.createElement('script'); s.src = src; s.onload = ok; s.onerror = bad; document.head.appendChild(s);
    });
  }
  function say(msg, kind) { ui.msg.className = 'small mt-2 text-' + ({ warning: 'warning', danger: 'danger', info: 'info' }[kind] || 'light'); ui.msg.textContent = msg; }
  function build() {
    var box = document.createElement('div');
    box.className = 'vt-scanner d-none';
    box.setAttribute('role', 'dialog'); box.setAttribute('aria-label', t('scanTitle', 'Scan voter card or slip'));
    box.innerHTML = '<div class="vt-inner"><div class="d-flex align-items-center mb-2"><strong class="me-auto"><i class="bi bi-upc-scan"></i> ' + t('scanTitle', 'Scan voter card or slip') + '</strong>'
      + '<button type="button" class="btn btn-sm btn-light" data-vt-close>' + t('close', 'Close') + '</button></div>'
      + '<div class="vt-view"><video playsinline muted></video><div class="vt-guide"></div></div>'
      + '<div class="d-flex flex-wrap gap-2 mt-2"><button type="button" class="btn btn-primary" data-vt-read><i class="bi bi-fonts"></i> ' + t('readNumber', 'Read printed number') + '</button>'
      + '<label class="btn btn-outline-light mb-0"><i class="bi bi-camera"></i> ' + t('photo', 'Take photo') + '<input type="file" accept="image/*" capture="environment" class="d-none" data-vt-photo></label></div>'
      + '<div class="small mt-2" data-vt-msg></div></div>';
    body.appendChild(box);
    ui = { box: box, video: box.querySelector('video'), msg: box.querySelector('[data-vt-msg]') };
    box.querySelector('[data-vt-close]').addEventListener('click', close);
    box.querySelector('[data-vt-read]').addEventListener('click', function () {
      if (!ui.video.videoWidth) { noCamera(); return; }
      ocr(frame(ui.video));
    });
    box.querySelector('[data-vt-photo]').addEventListener('change', function (e) { fromPhoto(e.target.files[0]); e.target.value = ''; });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !box.classList.contains('d-none')) close(); });
  }

  // ---------------------------------------------------------------- small message at the bottom
  function toast(msg, kind) {
    var d = document.createElement('div');
    d.className = 'vt-toast alert alert-' + (kind || 'info') + ' py-2 px-3 small shadow';
    d.textContent = msg; body.appendChild(d);
    setTimeout(function () { d.remove(); }, 2600);
  }

  window.VisTools = { scan: openScanner, epicFrom: epicFrom };
  enhance(document);
  // pages that draw parts later (offline field app) get their buttons too
  new MutationObserver(function (list) {
    for (var i = 0; i < list.length; i++) if (list[i].addedNodes.length) { enhance(document); break; }
  }).observe(body, { childList: true, subtree: true });
})();
