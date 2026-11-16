/*
 * Analytics charts (Chart.js, served locally). Any <canvas data-chart='{...}'> is drawn from the JSON the server put in it:
 *   {type: bar|line|doughnut, labels: [...], series: [[name, [values], role?], ...], stacked, horizontal, max, suffix, axisX, roles}
 * Colours by role: support = blue, neutral = grey, opposition = red (diverging), unknown = light grey; male blue, female orange.
 * Every chart sits above (or links to) its table, so identity is never colour-only.
 */
(function () {
  if (!window.Chart) return;
  var RTL = document.documentElement.dir === 'rtl';
  var ROLE = { support: '#2a78d6', neutral: '#9a9890', opposition: '#e34948', unknown: '#d9d7d0', other: '#1baf7a', male: '#2a78d6', female: '#eb6834' };
  var CAT = ['#2a78d6', '#eb6834', '#1baf7a', '#8a5cd1', '#d6a40b', '#d64f9b', '#4d9ad6', '#7a7a72', '#5aa02c', '#b0413e'];
  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.font.size = 12;
  Chart.defaults.color = '#52514e';
  Chart.defaults.borderColor = '#ecebe7';
  Chart.defaults.maintainAspectRatio = false;
  Chart.defaults.plugins.tooltip.rtl = RTL;
  Chart.defaults.plugins.legend.rtl = RTL;
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.legend.labels.pointStyle = 'rectRounded';
  var nf = new Intl.NumberFormat(document.documentElement.lang || 'en');

  function draw(cv) {
    var d;
    try { d = JSON.parse(cv.getAttribute('data-chart')); } catch (e) { return; }
    if (!d || !d.labels || !d.labels.length) { cv.parentNode.innerHTML = '<p class="text-muted small m-0 py-4 text-center">' + (cv.getAttribute('data-empty') || '-') + '</p>'; return; }
    var sfx = d.suffix || '';
    var fmt = function (v) { return nf.format(v) + sfx; };
    if (d.type === 'doughnut') {
      var s = d.series[0];
      new Chart(cv, { type: 'doughnut', data: { labels: d.labels, datasets: [{ label: s[0], data: s[1], borderColor: '#fff', borderWidth: 2,
        backgroundColor: d.labels.map(function (_, i) { return (d.roles && ROLE[d.roles[i]]) || CAT[i % CAT.length]; }) }] },
        options: { cutout: '58%', plugins: { legend: { position: 'right' }, tooltip: { callbacks: { label: function (c) {
          var t = c.dataset.data.reduce(function (a, b) { return a + b; }, 0);
          return ' ' + c.label + ': ' + nf.format(c.parsed) + (t ? ' (' + (Math.round(1000 * c.parsed / t) / 10) + '%)' : ''); } } } } } });
      return;
    }
    var line = d.type === 'line', many = d.series.length > 1;
    var ds = d.series.map(function (s, i) {
      var col = (s[2] && ROLE[s[2]]) || CAT[i % CAT.length];
      return { label: s[0], data: s[1], backgroundColor: col, borderColor: col, borderRadius: line ? 0 : 3, maxBarThickness: 36,
        borderWidth: line ? 2 : 0, pointRadius: line ? 2 : 0, tension: 0.25, fill: false };
    });
    var idx = d.horizontal ? 'y' : 'x', val = d.horizontal ? 'x' : 'y';
    var scales = {};
    scales[idx] = { stacked: !!d.stacked, grid: { display: false }, reverse: RTL && idx === 'x', title: { display: !!d.axisX, text: d.axisX || '' }, ticks: { autoSkip: true, maxRotation: 0 } };
    scales[val] = { stacked: !!d.stacked, beginAtZero: true, max: d.max || undefined, ticks: { callback: fmt }, position: RTL && val === 'y' ? 'right' : undefined };
    new Chart(cv, { type: line ? 'line' : 'bar', data: { labels: d.labels, datasets: ds },
      options: { indexAxis: idx, interaction: { mode: 'index', intersect: false }, scales: scales,
        plugins: { legend: { display: many, position: 'bottom' }, tooltip: { callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + fmt(c.parsed[val]); } } } } } });
  }
  document.querySelectorAll('canvas[data-chart]').forEach(draw);
})();
