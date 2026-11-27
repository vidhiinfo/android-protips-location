// First screen of the app: opens the site chosen earlier, or asks which site to open (saved on the phone).
// The website's menu has "Change site", which comes back here with ?choose=1.
(function () {
  var KEY = 'vis.site';
  var sites = window.VIS_SITES || [];
  var saved = null;
  try { saved = localStorage.getItem(KEY); } catch (e) { /* private storage off: ask every time */ }
  var choose = /[?&]choose=1/.test(location.search);
  var known = sites.some(function (s) { return s.url === saved; });
  function open(url) { location.replace(url + '/login'); }
  if (sites.length === 1 && !choose) { open(sites[0].url); return; }
  if (saved && known && !choose) { open(saved); return; }
  document.getElementById('msg').hidden = true;
  document.getElementById('sites').hidden = false;
  var list = document.getElementById('list');
  sites.forEach(function (s) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'site' + (s.url === saved ? ' current' : '');
    b.innerHTML = '<b></b><span></span>';
    b.firstChild.textContent = s.name; b.lastChild.textContent = s.local || '';
    b.addEventListener('click', function () {
      try { localStorage.setItem(KEY, s.url); } catch (e) { /* still open it */ }
      open(s.url);
    });
    list.appendChild(b);
  });
})();
