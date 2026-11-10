(function () {
  const key = "webstartCart";
  function read() {
    const value = JSON.parse(localStorage.getItem(key) || "[]");
    if (!Array.isArray(value)) throw new Error("Не удалось прочитать корзину. Откройте корзину и проверьте выбранные услуги.");
    return value;
  }
  function id(item) { return String(item.tariff_id ?? item.id); }
  function write(items) {
    localStorage.setItem(key, JSON.stringify(items));
    window.dispatchEvent(new Event("vega:cart-changed"));
  }
  function contains(tariffId) { return read().some(item => id(item) === String(tariffId)); }
  function add(item) {
    if (!item || !/^[1-9][0-9]*$/.test(String(item.tariff_id)) || !Number.isFinite(Number(item.price)) || Number(item.price) < 0) {
      throw new Error("Этот тариф недоступен. Выберите его ещё раз.");
    }
    const items = read();
    if (items.some(existing => id(existing) === String(item.tariff_id))) return false;
    if (items.length >= 12) throw new Error("В корзине уже 12 услуг. Удалите лишние, чтобы добавить новую.");
    items.push({tariff_id: String(item.tariff_id), service: String(item.service), tariff: String(item.tariff), price: Number(item.price)});
    write(items);
    return true;
  }
  window.VegaCart = {read, write, add, contains};
  window.addEventListener("storage", event => {
    if (event.key === key || event.key === null) window.dispatchEvent(new Event("vega:cart-changed"));
  });
})();
