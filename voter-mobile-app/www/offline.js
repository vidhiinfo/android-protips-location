// Back to the application once the phone is online again (the server address comes from capacitor.config.json).
var cfg = (window.Capacitor && window.Capacitor.getServerUrl && window.Capacitor.getServerUrl()) || '';
function retry() { if (cfg) location.href = cfg; else history.back(); }
document.getElementById('retry').addEventListener('click', retry);
window.addEventListener('online', retry);
