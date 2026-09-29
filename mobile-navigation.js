(() => {
    const header = document.querySelector('.main-header');
    const toggle = header?.querySelector('.mobile-menu-toggle');
    const links = header?.querySelector('.header-links');
    const shade = document.querySelector('.mobile-menu-shade');
    if (!toggle || !links || !shade) return;
    // Native anchor navigation must leave room for the fixed/sticky header.
    const updateAnchorOffset = () => {
        document.documentElement.style.setProperty('--navigation-offset', `${header.offsetHeight + 16}px`);
    };
    updateAnchorOffset();
    new ResizeObserver(updateAnchorOffset).observe(header);
    const mobile = matchMedia('(max-width: 1100px)');
    function setOpen(open, restoreFocus = false) {
        header.classList.toggle('menu-open', open);
        // Lock the viewport, not the sticky header's body ancestor.
        document.documentElement.classList.toggle('mobile-menu-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
        shade.hidden = !open;
        if (restoreFocus) toggle.focus({ preventScroll: true });
    }
    toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
    shade.addEventListener('click', () => setOpen(false, true));
    links.addEventListener('click', e => { if (e.target.closest('a')) setOpen(false); });
    document.addEventListener('keydown', e => {
        if (toggle.getAttribute('aria-expanded') !== 'true') return;
        if (e.key === 'Escape') setOpen(false, true);
        if (e.key === 'Tab') {
            const items = [toggle, ...links.querySelectorAll('a'), header.querySelector('.cart-menu-link')];
            const first = items[0], last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });
    mobile.addEventListener('change', () => setOpen(false));
})();
