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
