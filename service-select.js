(() => {
    const select = document.getElementById('project_type');
    if (!select) return;
    const wrapper = document.createElement('div');
    wrapper.className = 'service-select';
    select.before(wrapper);
    wrapper.append(select);
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'service-select__trigger';
    trigger.id = 'service-select-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', 'service-select-options');
    const label = document.querySelector('label[for="project_type"]');
    if (label) { label.id = 'service-select-label'; label.htmlFor = trigger.id; }
    trigger.setAttribute('aria-labelledby', 'service-select-label service-select-trigger');
    const list = document.createElement('div');
    list.id = 'service-select-options';
    list.className = 'service-select__options';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-labelledby', 'service-select-label');
    list.hidden = true;
    const options = Array.from(select.options).filter(option => option.value !== '');
    const rows = options.map(option => {
        const row = document.createElement('div');
        row.className = 'service-select__option';
        row.setAttribute('role', 'option');
        row.tabIndex = -1;
        row.textContent = option.textContent.trim();
        row.addEventListener('click', () => choose(option.value));
        list.append(row);
        return row;
    });
    function sync() {
        trigger.textContent = select.selectedOptions[0]?.textContent.trim() || 'Выберите услугу';
        rows.forEach((row, index) => row.setAttribute('aria-selected', String(options[index].value === select.value)));
        if (select.value) trigger.removeAttribute('aria-invalid');
    }
    function close(restoreFocus = false) {
        list.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        if (restoreFocus) trigger.focus();
    }
    function open(last = false) {
        list.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        const selected = options.findIndex(option => option.value === select.value);
        rows[last ? rows.length - 1 : Math.max(0, selected)]?.focus();
    }
    function choose(value) {
        select.value = value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        close(true);
    }
    trigger.addEventListener('click', () => list.hidden ? open() : close());
    trigger.addEventListener('keydown', event => {
        if (['ArrowDown', 'ArrowUp'].includes(event.key)) { event.preventDefault(); open(event.key === 'ArrowUp'); }
    });
    list.addEventListener('keydown', event => {
        const index = rows.indexOf(document.activeElement);
        if (event.key === 'Escape') { event.preventDefault(); close(true); }
        else if (event.key === 'Tab') close(true);
        else if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); if (index >= 0) choose(options[index].value); }
        else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? rows.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + rows.length) % rows.length;
            rows[next]?.focus();
        } else if (event.key.length === 1) {
            rows.find(row => row.textContent.toLocaleLowerCase().startsWith(event.key.toLocaleLowerCase()))?.focus();
        }
    });
    document.addEventListener('pointerdown', event => { if (!wrapper.contains(event.target)) close(); });
    // A short grace period lets the pointer cross the gap between trigger and list.
    let leaveTimer;
    wrapper.addEventListener('pointerenter', () => clearTimeout(leaveTimer));
    wrapper.addEventListener('pointerleave', event => {
        if (event.pointerType !== 'mouse') return;
        clearTimeout(leaveTimer);
        leaveTimer = setTimeout(() => {
            if (!wrapper.matches(':hover')) close(list.contains(document.activeElement));
        }, 180);
    });
    wrapper.addEventListener('focusout', () => setTimeout(() => { if (!wrapper.contains(document.activeElement)) close(); }, 0));
    select.addEventListener('change', sync);
    select.addEventListener('invalid', () => { trigger.setAttribute('aria-invalid', 'true'); });
    select.addEventListener('focus', () => trigger.focus());
    select.form?.addEventListener('reset', () => setTimeout(sync, 0));
    select.classList.add('service-select__native');
    select.tabIndex = -1;
    wrapper.append(trigger, list);
    sync();
})();
