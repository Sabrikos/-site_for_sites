(function (root) {
    'use strict';
    const key = 'webstartCart';
    function create(storage, catalog) {
        const entries = new Map(catalog.map(item => [String(item.tariff_id), item]));
        function read() {
            try { const data = JSON.parse(storage.getItem(key) || '[]'); return Array.isArray(data) ? data : []; }
            catch (_) { return []; }
        }
        function ids(items) {
            return [...new Set(items.map(item => String(item && typeof item === 'object' ? (item.tariff_id ?? item.id ?? '') : item)).filter(id => entries.has(id)))];
        }
        function hydrate(items) { return ids(items).map(id => ({ ...entries.get(id) })); }
        function save(items) { const selected = ids(items); const serialized = JSON.stringify(selected); if (storage.getItem(key) !== serialized) storage.setItem(key, serialized); return hydrate(selected); }
        let removed = 0;
        function load() {
            const original = read();
            const originalIds = [...new Set(original.map(item => String(item && typeof item === 'object' ? (item.tariff_id ?? item.id ?? '') : item)).filter(Boolean))];
            removed = originalIds.length - ids(original).length;
            return save(original);
        }
        function removedCount() { return removed; }
        function total(items) { return hydrate(items).reduce((sum, item) => sum + Number(item.price), 0); }
        return { load, save, total, removedCount };
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = { create };
    else if (root.VegaCart && typeof root.VegaCart.configureCatalog === 'function') {
        // Keep the shared cart API and its change events used by the assistant.
        root.VegaCart.configureCatalog(root.VEGA_TARIFFS);
        if (root.VegaCart.removedCount()) {
            const notice = document.createElement('p');
            notice.className = 'catalog-cart-notice';
            notice.setAttribute('role', 'status');
            notice.textContent = 'Некоторые ранее выбранные услуги больше недоступны и убраны из корзины. Проверьте состав заявки.';
            document.querySelector('main')?.prepend(notice);
        }
        if (typeof root.updateHeaderCartCounter === 'function') root.updateHeaderCartCounter();
    } else root.VegaCart = create(root.localStorage, root.VEGA_TARIFFS);
})(typeof window !== 'undefined' ? window : globalThis);
