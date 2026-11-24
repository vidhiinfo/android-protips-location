// Dependent district dropdowns: <select data-state-select="#district"> + <select id="district" data-districts='{"stateId":[[id,name],...]}'>
function bindStateDistrict(stateSel) {
    const target = document.querySelector(stateSel.dataset.stateSelect);
    if (!target) return;
    const map = JSON.parse(target.dataset.districts || '{}');
    const placeholder = target.dataset.placeholder || '— District —';
    const refill = () => {
        const current = target.dataset.value || target.value;
        target.innerHTML = '';
        target.append(new Option(placeholder, ''));
        (map[stateSel.value] || []).forEach(([id, name]) => {
            const o = new Option(name, id);
            if (String(id) === String(current)) o.selected = true;
            target.append(o);
        });
        target.dataset.value = '';
    };
    stateSel.addEventListener('change', refill);
    refill();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-state-select]').forEach(bindStateDistrict);

    // Confirm destructive actions
    document.querySelectorAll('form[data-confirm]').forEach((f) =>
        f.addEventListener('submit', (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));

    // Mobile sidebar
    const toggle = document.getElementById('nav-toggle');
    const side = document.getElementById('sidebar');
    toggle?.addEventListener('click', () => side.classList.toggle('-translate-x-full'));

    // Photo preview
    document.querySelectorAll('input[type=file][data-preview]').forEach((inp) =>
        inp.addEventListener('change', () => {
            const img = document.querySelector(inp.dataset.preview);
            if (img && inp.files[0]) { img.src = URL.createObjectURL(inp.files[0]); img.classList.remove('hidden'); }
        }));

    // Auto-submit filter selects
    document.querySelectorAll('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form.submit()));

    // Copy-to-clipboard buttons
    document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', async () => {
        await navigator.clipboard.writeText(document.querySelector(b.dataset.copy).value);
        const t = b.textContent; b.textContent = 'Copied ✓'; setTimeout(() => (b.textContent = t), 1500);
    }));
});
