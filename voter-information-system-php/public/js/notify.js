/* Bell in the top bar: the unread count is checked every minute while a page is open (and when the page is shown again). */
(function () {
    var box = document.querySelector('[data-notif]');
    if (!box) return;
    var badge = box.querySelector('[data-notif-badge]'), icon = box.querySelector('.bi');
    function check() {
        if (document.hidden) return;
        fetch(box.dataset.countUrl, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d) return;
                var n = d.unread || 0;
                badge.textContent = n > 99 ? '99+' : String(n);
                badge.classList.toggle('d-none', !n);
                if (icon) icon.className = 'bi bi-bell' + (n ? '-fill' : '');
            }).catch(function () {});
    }
    setInterval(check, 60000);
    document.addEventListener('visibilitychange', check);
})();
