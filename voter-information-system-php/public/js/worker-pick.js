/* Worker and grievance forms: pick the person from the voter list instead of typing the name (no spelling mistakes).
   data-key="id" stores the voter id (grievance) instead of the EPIC No. (worker); data-name-input fills and locks a name box. */
(function () {
    var box = document.querySelector('[data-voter-pick]');
    if (!box) return;
    var q = box.querySelector('#voter-q'), results = box.querySelector('[data-results]'), epic = box.querySelector('[data-pick-value]') || document.querySelector('[data-pick-value]');
    var key = box.dataset.key || 'epic', nameInput = box.dataset.nameInput ? document.querySelector(box.dataset.nameInput) : null;
    var place = document.querySelector('[data-pick-address]');
    var picked = box.querySelector('[data-picked]'), search = box.querySelector('[data-search]');
    var asm = document.querySelector('[data-pick-assembly]'), booth = document.querySelector('[data-pick-booth]');
    var role = document.querySelector('[data-role-select]'), toggle = document.querySelector('[data-manual-toggle]');
    var manual = document.getElementById('manual-name'), mobile = document.querySelector('input[name=mobile]');
    var timer = null, seq = 0;

    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
    function info(v) {
        return [v.epic, v.relative, (v.gender || '') + (v.age ? ' ' + v.age : ''), box.dataset.boothLabel + ' ' + v.booth + ', ' + box.dataset.srLabel + ' ' + v.serial]
            .filter(function (x) { return x && String(x).trim(); }).join(' · ');
    }
    function setBooth(v) {
        if (!booth) return;
        if (booth.value && asm && String(asm.value) === String(v.assembly)) return;   // booth chosen already (e.g. "+ Add" for a booth)
        var has = Array.prototype.some.call(booth.options, function (o) { return o.value === String(v.booth); });
        if (!has) { var o = el('option', null, v.booth); o.value = v.booth; booth.appendChild(o); }
        booth.value = String(v.booth);
    }
    function pick(v) {
        epic.value = v[key];
        if (nameInput) { nameInput.value = v.name; nameInput.readOnly = true; }
        if (place && !place.value && v.address) place.value = v.address;
        picked.querySelector('[data-picked-name]').textContent = v.name;
        picked.querySelector('[data-picked-info]').textContent = info(v);
        var link = picked.querySelector('a'); if (link) link.remove();
        setBooth(v);
        if (asm && !asm.disabled && (!role || role.value !== 'LS_INCHARGE')) asm.value = String(v.assembly);
        if (mobile && !mobile.value && v.mobile) mobile.value = v.mobile;
        picked.classList.remove('d-none'); search.classList.add('d-none');
        if (toggle) toggle.classList.add('d-none');
        if (manual) manual.classList.remove('show');
        results.innerHTML = '';
    }
    function unpick() {
        epic.value = '';
        if (nameInput) { nameInput.readOnly = false; nameInput.value = ''; }
        picked.classList.add('d-none'); search.classList.remove('d-none');
        if (toggle) toggle.classList.remove('d-none');
        q.value = ''; q.focus();
    }
    function show(list) {
        results.innerHTML = '';
        if (!list.length) { results.appendChild(el('div', 'list-group-item small text-muted', box.dataset.none)); return; }
        list.forEach(function (v) {
            var b = el('button', 'list-group-item list-group-item-action py-1');
            b.type = 'button';
            b.appendChild(el('span', 'fw-semibold', v.name));
            if (v.taken) b.appendChild(el('span', 'badge text-bg-warning ms-1', box.dataset.taken));
            b.appendChild(el('div', 'small text-muted', info(v)));
            if (v.taken) b.disabled = true; else b.addEventListener('click', function () { pick(v); });
            results.appendChild(b);
        });
    }
    function run() {
        var term = q.value.trim();
        if (term.length < 2) { results.innerHTML = ''; return; }
        var p = new URLSearchParams({ q: term });
        if (box.dataset.for) p.set('for', box.dataset.for);
        if (asm && (!role || role.value !== 'LS_INCHARGE')) p.set('assemblyNo', asm.value);
        if (booth && booth.value) p.set('boothNo', booth.value);
        var mine = ++seq;
        results.innerHTML = ''; results.appendChild(el('div', 'list-group-item small text-muted', box.dataset.searching));
        fetch(box.dataset.url + '?' + p.toString(), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : []; })
            .then(function (list) { if (mine === seq) show(list); })
            .catch(function () { if (mine === seq) results.innerHTML = ''; });
    }
    q.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(run, 300); });
    q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(timer); run(); } });
    box.querySelector('[data-unpick]').addEventListener('click', unpick);
})();
