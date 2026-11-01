"use strict";

// Shared persistence for the catalogue, the wish form and the favourites page.
window.TihoSaved = (() => {
  const productKey = "tiho-favorites";
  const wishKey = "tiho-wishes";
  const catalog = window.TihoCatalog;
  const productByTitle = new Map(
    Object.entries(catalog).map(([id, product]) => [product.title, id]),
  );
  const items = [...Object.values(catalog).map((product) => product.title), "Моя идея"];
  const colors = [
    "Лаванда",
    "Молочный",
    "Изумруд",
    "Карамель",
    "Ягодный",
    "Другой — расскажу ниже",
  ];
  const own = (id) => Object.prototype.hasOwnProperty.call(catalog, id);
  const emit = () => window.dispatchEvent(new Event("tiho:saved-change"));
  function read(key) {
    try {
      const value = JSON.parse(localStorage.getItem(key) || "[]");
      return Array.isArray(value) ? value : [];
    } catch {
      return [];
    }
  }
  function commit(values) {
    const previous = new Map();
    try {
      for (const key of Object.keys(values)) previous.set(key, localStorage.getItem(key));
      for (const [key, value] of Object.entries(values)) {
        localStorage.setItem(key, JSON.stringify(value));
      }
    } catch {
      try {
        for (const [key, value] of previous) {
          if (value === null) localStorage.removeItem(key);
          else localStorage.setItem(key, value);
        }
      } catch {}
      throw new Error(
        "Не удалось сохранить. Проверьте, доступно ли хранилище браузера, и попробуйте ещё раз.",
      );
    }
    emit();
  }
  function products() {
    return [...new Set(read(productKey).filter((id) => typeof id === "string" && own(id)))];
  }
  function wishes() {
    return read(wishKey)
      .filter(
        (wish) =>
          wish &&
          typeof wish.id === "string" &&
          wish.id.length < 100 &&
          items.includes(wish.item) &&
          colors.includes(wish.color) &&
          typeof wish.size === "string" &&
          typeof wish.idea === "string" &&
          wish.size.length <= 80 &&
          wish.idea.length <= 1000,
      )
      .map((wish) => ({ ...wish, productId: productByTitle.get(wish.item) || null }));
  }
  function count() {
    const ids = products();
    return ids.length + wishes().filter((wish) => !ids.includes(wish.productId)).length;
  }
  function normalize(fields) {
    return {
      item: items.includes(fields.item) ? fields.item : "Моя идея",
      productId: productByTitle.get(fields.item) || null,
      color: colors.includes(fields.color) ? fields.color : "Лаванда",
      size: String(fields.size || "")
        .trim()
        .slice(0, 80),
      idea: String(fields.idea || "")
        .trim()
        .slice(0, 1000),
    };
  }
  const sameWish = (a, b) => ["item", "color", "size", "idea"].every((key) => a[key] === b[key]);
  function saveWish(fields, editId = null) {
    const value = normalize(fields);
    const list = wishes();
    const existing = editId
      ? list.find((wish) => wish.id === editId)
      : list.find((wish) => sameWish(wish, value));
    if (!editId && existing) {
      const productIds = products();
      if (existing.productId && !productIds.includes(existing.productId)) {
        commit({ [productKey]: [...productIds, existing.productId] });
      }
      return existing;
    }
    const now = new Date().toISOString();
    const id =
      existing?.id ||
      (window.crypto?.randomUUID
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`);
    const record = { ...value, id, createdAt: existing?.createdAt || now, updatedAt: now };
    const productIds = products();
    if (record.productId && !productIds.includes(record.productId))
      productIds.push(record.productId);
    commit({
      [productKey]: productIds,
      [wishKey]: existing
        ? list.map((wish) => (wish.id === id ? record : wish))
        : [...list, record],
    });
    return record;
  }
  function toggleProduct(id) {
    if (!own(id)) return;
    const list = products();
    // Removing a heart never deletes the user's notes.
    commit({
      [productKey]: list.includes(id) ? list.filter((value) => value !== id) : [...list, id],
    });
  }
  function removeWish(id) {
    commit({ [wishKey]: wishes().filter((wish) => wish.id !== id) });
  }
  function clear() {
    commit({ [productKey]: [], [wishKey]: [] });
  }
  window.addEventListener("storage", (event) => {
    if ([productKey, wishKey, null].includes(event.key)) emit();
  });
  return Object.freeze({ products, wishes, count, saveWish, toggleProduct, removeWish, clear });
})();
