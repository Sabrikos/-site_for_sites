(function () {
  const key = "webstartCart";
  let catalog = null;
  let removed = 0;
  function normalize(items) {
    if (!catalog) return items;
    const ids = [...new Set(items.map(item => String(item && typeof item === 'object' ? (item.tariff_id ?? item.id ?? '') : item)))];
    return ids.filter(value => catalog.has(value)).map(value => ({...catalog.get(value)}));
  }
  function read() {
    const value = JSON.parse(localStorage.getItem(key) || "[]");
    if (!Array.isArray(value)) throw new Error("Не удалось прочитать корзину. Откройте корзину и проверьте выбранные услуги.");
    return normalize(value);
  }
  function id(item) { return String(item.tariff_id ?? item.id); }
  function write(items) {
    const selected = normalize(items);
    localStorage.setItem(key, JSON.stringify(selected));
    window.dispatchEvent(new Event("vega:cart-changed"));
    return selected;
  }
  function configureCatalog(entries) {
    const original = read();
    catalog = new Map(entries.map(item => [String(item.tariff_id), item]));
    const selected = normalize(original);
    removed = new Set(original.map(item => String(item && typeof item === 'object' ? (item.tariff_id ?? item.id ?? '') : item))).size - selected.length;
    localStorage.setItem(key, JSON.stringify(selected));
  }
  function contains(tariffId) { return read().some(item => id(item) === String(tariffId)); }
  function add(item) {
    if (!item || !/^[1-9][0-9]*$/.test(String(item.tariff_id)) || !Number.isFinite(Number(item.price)) || Number(item.price) < 0 || (catalog && !catalog.has(String(item.tariff_id)))) {
      throw new Error("Этот тариф недоступен. Выберите его ещё раз.");
    }
    const items = read();
    if (items.some(existing => id(existing) === String(item.tariff_id))) return false;
    if (items.length >= 12) throw new Error("В корзине уже 12 услуг. Удалите лишние, чтобы добавить новую.");
    items.push({tariff_id: String(item.tariff_id), service: String(item.service), tariff: String(item.tariff), price: Number(item.price)});
    write(items);
    return true;
  }
  window.VegaCart = {read, write, add, contains, configureCatalog,
    load: read, save: write, removedCount: () => removed,
    total: items => normalize(items).reduce((sum, item) => sum + Number(item.price), 0)};
  window.addEventListener("storage", event => {
    if (event.key === key || event.key === null) window.dispatchEvent(new Event("vega:cart-changed"));
  });
})();
