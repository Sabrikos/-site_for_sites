"use strict";

(() => {
  const saved = window.TihoSaved;
  const catalog = window.TihoCatalog;
  let filter = "all";
  const $ = (selector) => document.querySelector(selector);
  const number = (count, one, few, many) =>
    `${count} ${count % 10 === 1 && count % 100 !== 11 ? one : count % 10 >= 2 && count % 10 <= 4 && (count % 100 < 12 || count % 100 > 14) ? few : many}`;
  function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }
  function button(text, attribute, value, className = "text-link") {
    const node = element("button", className, text);
    node.type = "button";
    node.dataset[attribute] = value;
    return node;
  }
  function productCard(id) {
    const product = catalog[id];
    const card = element("article", "saved-product");
    card.dataset.savedProduct = id;
    const visual = element("div", "saved-product-visual");
    const open = button("", "product", id, "saved-product-open");
    open.setAttribute("aria-label", `Рассмотреть: ${product.title}`);
    const image = element("img");
    image.src = product.image;
    image.alt = product.title;
    image.loading = "lazy";
    image.width = 600;
    image.height = 600;
    open.append(image);
    const remove = button("×", "favorite", id, "saved-remove");
    remove.setAttribute("aria-label", `Удалить из избранного: ${product.title}`);
    remove.setAttribute("aria-pressed", "true");
    visual.append(open, remove);
    const copy = element("div", "saved-product-copy");
    const title = element("h3");
    title.append(button(product.title, "product", id, "saved-title-button"));
    copy.append(
      title,
      element("p", "", product.subtitle),
      element("p", "saved-price", product.price),
    );
    const wishes = saved.wishes().filter((wish) => wish.productId === id);
    if (wishes.length) {
      const notes = element("div", "saved-product-wishes");
      notes.append(...wishes.map((wish) => wishCard(wish, true)));
      copy.append(notes);
    }
    card.append(visual, copy);
    return card;
  }
  function wishCard(wish, compact = false) {
    const card = element("article", "saved-wish");
    card.dataset.savedWish = wish.id;
    const top = element("div", "saved-wish-top");
    top.append(element("span", "saved-pill", compact ? "Ваши пожелания" : "Ваша идея"));
    const remove = button("×", "removeWish", wish.id, "saved-remove");
    remove.setAttribute("aria-label", `Удалить пожелание: ${wish.item}`);
    top.append(remove);
    card.append(top);
    if (!compact) {
      if (wish.productId) {
        const product = catalog[wish.productId];
        const open = button("", "product", wish.productId, "saved-wish-image");
        open.setAttribute("aria-label", `Рассмотреть: ${product.title}`);
        const image = element("img");
        image.src = product.image;
        image.alt = product.title;
        image.loading = "lazy";
        open.append(image);
        card.append(open);
      }
      card.append(
        element("h3", "", wish.item === "Моя идея" ? "Вещь по вашему замыслу" : wish.item),
      );
    }
    const details = element("dl", "saved-wish-details");
    for (const [label, value] of [
      ["Оттенок", wish.color],
      ["Размер", wish.size || "Обсудим индивидуально"],
    ]) {
      const row = element("div");
      row.append(element("dt", "", label), element("dd", "", value));
      details.append(row);
    }
    card.append(details);
    if (wish.idea) card.append(element("p", "saved-wish-note", wish.idea));
    card.append(button("Редактировать пожелания ↗", "editWish", wish.id));
    return card;
  }
  function render() {
    const productIds = saved.products();
    const wishes = saved.wishes();
    const total = saved.count();
    const separateWishes =
      filter === "wishes" ? wishes : wishes.filter((wish) => !productIds.includes(wish.productId));
    $("#saved-total").textContent = total;
    $("#saved-product-count").textContent = number(productIds.length, "вещь", "вещи", "вещей");
    $("#saved-wish-count").textContent = number(
      separateWishes.length,
      "пожелание",
      "пожелания",
      "пожеланий",
    );
    $("#tab-all-count").textContent = total;
    $("#tab-products-count").textContent = productIds.length;
    $("#tab-wishes-count").textContent = wishes.length;
    $("#saved-products-grid").replaceChildren(
      ...(filter === "wishes" ? [] : productIds.map(productCard)),
    );
    $("#saved-wishes-grid").replaceChildren(
      ...(filter === "products" ? [] : separateWishes.map((wish) => wishCard(wish))),
    );
    $("#saved-products-section").hidden = filter === "wishes" || !productIds.length;
    $("#saved-wishes-section").hidden = filter === "products" || !separateWishes.length;
    const empty =
      !total ||
      (filter === "products" && !productIds.length) ||
      (filter === "wishes" && !wishes.length);
    $("#saved-empty").hidden = !empty;
    $("#saved-empty-title").textContent =
      filter === "wishes"
        ? "Здесь будет ваша идея."
        : filter === "products"
          ? "Найдём вашу любимую вещь?"
          : "Тепло начинается с выбора.";
    $("#saved-empty-text").textContent =
      filter === "wishes"
        ? "Соберите цвет, размер и детали будущего изделия — и добавьте пожелания в избранное."
        : "Сохраняйте понравившиеся изделия сердечком и добавляйте свои пожелания. Всё будет рядом на этой странице.";
    document.querySelectorAll("[data-saved-filter]").forEach((node) => {
      const active = node.dataset.savedFilter === filter;
      node.classList.toggle("active", active);
      node.setAttribute("aria-pressed", String(active));
    });
  }
  document.querySelectorAll("[data-saved-filter]").forEach((node) =>
    node.addEventListener("click", () => {
      filter = node.dataset.savedFilter;
      render();
    }),
  );
  window.addEventListener("tiho:saved-change", render);
  render();
})();
