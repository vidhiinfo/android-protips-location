/*
 * Campaign dashboard charts (Chart.js, served locally).
 * Colours by role from the validated default palette (dataviz skill, references/palette.md):
 *   male / single series = blue, female = orange, other = aqua; lead = blue, trail = red (diverging pair).
 * Every chart has a table view (button in the card header) - identity is never colour-only.
 */
(function () {
  var root = document.getElementById('dashboard');
  if (!root || !window.Chart) return;

  var RTL = document.documentElement.dir === 'rtl';
  var C = {
    male: '#2a78d6', female: '#eb6834', other: '#1baf7a', single: '#2a78d6',
    lead: '#2a78d6', trail: '#e34948',
    text: '#52514e', muted: '#77766f', grid: '#ecebe7', surface: '#ffffff'
  };
  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.font.size = 12;
  Chart.defaults.color = C.text;
  Chart.defaults.borderColor = C.grid;
  Chart.defaults.maintainAspectRatio = false;
  Chart.defaults.plugins.tooltip.rtl = RTL;
  Chart.defaults.plugins.legend.rtl = RTL;
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.legend.labels.pointStyle = 'rectRounded';

  var nf = new Intl.NumberFormat(document.documentElement.lang || 'en');
  var url = root.getAttribute('data-url') + window.location.search;
  var charts = {}, data;

  fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
    data = d;
    if (!d || !d.tiles) return;
    var T = d.text;
    bar('chartAgeGender', d.ageGender, { axisX: T.ageGroup, axisY: T.voters });
    bar('chartBoothVoters', d.boothVoters, { stacked: true, axisX: T.booth, axisY: T.voters });
    bar('chartMobile', d.mobile, { axisX: T.booth, axisY: '%', max: 100, suffix: '%' });
    bar('chartYoung', d.young, { axisX: T.booth, axisY: T.voters });
    bar('chartHousehold', d.household, { axisX: T.voters, axisY: T.houses });
    if (d.election) {
      bar('chartMargin', d.election.margin, { diverging: true, axisX: T.booth, axisY: d.election.margin.series[0].name });
      bar('chartPartyShare', d.election.partyShare, { horizontal: true, axisX: '%', suffix: '%' });
      bar('chartTurnout', d.election.turnout, { axisX: T.booth, axisY: '%', max: 100, suffix: '%' });
    }
  });

  function bar(id, chart, o) {
    var el = document.getElementById(id);
    if (!el || !chart) return;
    var n = chart.series.length;
    var datasets = chart.series.map(function (s, i) {
      var colour = o.diverging
        ? s.data.map(function (v) { return v >= 0 ? C.lead : C.trail; })
        : C[s.role] || C.single;
      return {
        label: s.name,
        data: s.data,
        backgroundColor: colour,
        // 2px surface gap between stacked segments and adjacent bars; 4px rounded data end
        borderColor: C.surface,
        borderWidth: o.stacked ? { top: i < n - 1 ? 0 : 0, bottom: i > 0 ? 2 : 0 } : 0,
        borderSkipped: o.stacked ? false : 'start',
        borderRadius: o.stacked ? (i === n - 1 ? { topLeft: 4, topRight: 4 } : 0) : 4,
        maxBarThickness: o.horizontal ? 22 : 30,
        categoryPercentage: n > 1 && !o.stacked ? 0.7 : 0.8,
        barPercentage: 0.9
      };
    });
    var valueAxis = {
      beginAtZero: true, stacked: !!o.stacked, suggestedMax: o.max,
      grid: { color: function (c) { return c.tick && c.tick.value === 0 ? C.muted : C.grid; } },
      border: { display: false },
      ticks: { callback: function (v) { return nf.format(v) + (o.suffix || ''); }, maxTicksLimit: 6 },
      title: { display: !!(o.horizontal ? o.axisX : o.axisY), text: o.horizontal ? o.axisX : o.axisY, color: C.muted }
    };
    var catAxis = {
      stacked: !!o.stacked, grid: { display: false }, border: { color: C.grid },
      reverse: RTL && !o.horizontal,
      position: o.horizontal && RTL ? 'right' : undefined,
      title: { display: !!(o.horizontal ? null : o.axisX), text: o.axisX, color: C.muted }
    };
    if (o.horizontal) valueAxis.reverse = RTL;
    var legend = { display: n > 1, position: 'top', align: 'start' };
    if (o.diverging) {
      legend = { display: true, position: 'top', align: 'start', labels: { generateLabels: function () {
        return [{ text: data.text.lead, fillStyle: C.lead, strokeStyle: C.lead, pointStyle: 'rectRounded', hidden: false },
                { text: data.text.trail, fillStyle: C.trail, strokeStyle: C.trail, pointStyle: 'rectRounded', hidden: false }];
      } }, onClick: function () {} };
    }
    charts[id] = new Chart(el, {
      type: 'bar',
      data: { labels: chart.labels, datasets: datasets },
      options: {
        indexAxis: o.horizontal ? 'y' : 'x',
        interaction: { mode: 'index', intersect: false },
        scales: o.horizontal ? { x: valueAxis, y: catAxis } : { x: catAxis, y: valueAxis },
        plugins: {
          legend: legend,
          tooltip: {
            callbacks: {
              title: function (items) { return (o.horizontal || !o.axisX ? '' : o.axisX + ' ') + items[0].label; },
              label: function (c) {
                var v = c.parsed[o.horizontal ? 'x' : 'y'];
                var s = (n > 1 ? c.dataset.label + ': ' : '') + nf.format(v) + (o.suffix || '');
                if (o.diverging) s = (v >= 0 ? data.text.lead : data.text.trail) + ': ' + nf.format(Math.abs(v));
                return s;
              }
            }
          }
        }
      }
    });
    buildTable(id, chart, o);
  }

  function buildTable(id, chart, o) {
    var box = document.getElementById(id + '-table');
    if (!box) return;
    var t = document.createElement('table');
    t.className = 'table table-sm table-striped mb-0';
    var head = t.createTHead().insertRow();
    th(head, o.horizontal ? data.text.party : (o.axisX || ''));
    chart.series.forEach(function (s) { th(head, s.name); });
    var body = t.createTBody();
    chart.labels.forEach(function (label, r) {
      var row = body.insertRow();
      row.insertCell().textContent = label;
      chart.series.forEach(function (s) {
        var cell = row.insertCell();
        cell.className = 'text-end';
        cell.textContent = nf.format(s.data[r]) + (o.suffix || '');
      });
    });
    box.appendChild(t);
  }
  function th(row, text) { var c = document.createElement('th'); c.textContent = text; row.appendChild(c); }

  // Chart <-> table toggle
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-toggle-table]');
    if (!b || !data) return;
    var id = b.getAttribute('data-toggle-table');
    var table = document.getElementById(id + '-table');
    var canvasBox = document.getElementById(id).parentElement;
    var showTable = table.classList.contains('d-none');
    table.classList.toggle('d-none', !showTable);
    canvasBox.classList.toggle('d-none', showTable);
    b.textContent = showTable ? data.text.chart : data.text.table;
  });
})();
