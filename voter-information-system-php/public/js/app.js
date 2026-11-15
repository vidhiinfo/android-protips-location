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
    var map = {};
    try { map = JSON.parse(ward.dataset.map || '{}'); } catch (e) { map = {}; }
    var all = Array.prototype.map.call(booth.options, function (o) { return { value: o.value, text: o.text }; });
    function apply() {
        var keep = ward.value ? (map[ward.value] || []).map(String) : null;
        var current = booth.value;
        booth.innerHTML = '';
        all.forEach(function (o) {
            if (o.value === '' || !keep || keep.indexOf(o.value) >= 0) booth.add(new Option(o.text, o.value, false, o.value === current));
        });
        if (keep && current && keep.indexOf(current) < 0) booth.value = '';
    }
    ward.addEventListener('change', apply);
    apply();
});
