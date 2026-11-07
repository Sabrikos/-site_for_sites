"use strict";
const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
const products = window.TihoCatalog;
const saved = window.TihoSaved;
let favorites = new Set(saved.products());
let filter = "all",
  chosenColor = "Лаванда",
  activeProduct = null,
  editingWishId = null,
  toastTimer;

function toast(message) {
  const el = $(".toast");
  el.textContent = message;
  el.classList.add("visible");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove("visible"), 3200);
}
function updateFavorites() {
  favorites = new Set(saved.products());
  const total = saved.count();
  $("#favorite-count").textContent = total;
  $(".favorite-toggle").setAttribute("aria-label", `Открыть избранное, сохранено: ${total}`);
  if ($(".filter-count")) $(".filter-count").textContent = favorites.size;
  $$("[data-favorite]").forEach((button) => {
    const selected = favorites.has(button.dataset.favorite);
    button.setAttribute("aria-pressed", String(selected));
    button.setAttribute(
      "aria-label",
      `${selected ? "Убрать из избранного" : "Добавить в избранное"}: ${products[button.dataset.favorite].title}`,
    );
    if (button.id === "detail-favorite")
      button.textContent = selected ? "В избранном ♥" : "Добавить в избранное ♡";
  });
  applyFilter(filter);
}
function applyFilter(value) {
  if (!$(".collection-toolbar")) return;
  filter = value;
  let count = 0;
  $$("[data-filter]").forEach((button) => {
    const active = button.dataset.filter === value;
    button.classList.toggle("active", active);
    button.setAttribute("aria-pressed", String(active));
  });
  const query = ($("#product-search")?.value || "").trim().toLocaleLowerCase("ru");
  $$(".product").forEach((card) => {
    const product = products[card.dataset.id];
    const categoryMatches =
      value === "all" ||
      card.dataset.category === value ||
      (value === "favorites" && favorites.has(card.dataset.id));
    const show =
      categoryMatches &&
      (!query ||
        `${product.title} ${product.description} ${product.subtitle}`
          .toLocaleLowerCase("ru")
          .includes(query));
    card.hidden = !show;
    if (show) count++;
  });
  $(".collection-count").textContent =
    `${String(count).padStart(2, "0")} ${count === 1 ? "изделие" : count > 1 && count < 5 ? "изделия" : "изделий"}`;
  $(".empty-favorites").hidden = count !== 0;
  $(".empty-favorites h3").textContent = query
    ? "Пока ничего не нашлось."
    : "Сохраните то, что откликнулось.";
  $(".empty-favorites p").textContent = query
    ? "Попробуйте другое название или посмотрите всю коллекцию."
    : "Нажмите на сердечко у любимой вещи — она появится здесь.";
  $(".collection-footnote").hidden = count === 0;
  requestAnimationFrame(() => window.ScrollTrigger?.refresh());
}
$$("[data-filter]").forEach((button) =>
  button.addEventListener("click", () => applyFilter(button.dataset.filter)),
);
$("[data-reset-filter]")?.addEventListener("click", () => {
  $("#product-search").value = "";
  applyFilter("all");
});

const menu = $(".menu-toggle");
function closeMenu() {
  menu.setAttribute("aria-expanded", "false");
  menu.setAttribute("aria-label", "Открыть меню");
  $("#navigation").classList.remove("open");
}
menu.addEventListener("click", () => {
  const expanded = menu.getAttribute("aria-expanded") !== "true";
  menu.setAttribute("aria-expanded", String(expanded));
  menu.setAttribute("aria-label", expanded ? "Закрыть меню" : "Открыть меню");
  $("#navigation").classList.toggle("open", expanded);
});
$$("#navigation a").forEach((link) => link.addEventListener("click", closeMenu));
document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") closeMenu();
});

function showDialog(dialog) {
  closeMenu();
  dialog.showModal();
  document.body.classList.add("modal-open");
}
$$("dialog").forEach((dialog) => {
  $(".dialog-close", dialog).addEventListener("click", () => dialog.close());
  dialog.addEventListener("click", (event) => {
    if (event.target !== dialog) return;
    const r = dialog.getBoundingClientRect();
    if (
      event.clientX < r.left ||
      event.clientX > r.right ||
      event.clientY < r.top ||
      event.clientY > r.bottom
    )
      dialog.close();
  });
  dialog.addEventListener("close", () => {
    if (!$("dialog[open]")) document.body.classList.remove("modal-open");
  });
});
function openProduct(id) {
  const product = products[id];
  if (!product) return;
  activeProduct = id;
  $("#detail-title").textContent = product.title;
  $("#detail-image").src = product.image;
  $("#detail-image").alt = product.title;
  $("#detail-description").textContent = product.description;
  $("#detail-price").textContent = product.price;
  $("#detail-favorite").dataset.favorite = id;
  updateFavorites();
  showDialog($("#product-dialog"));
}
function openOrder(product = null, wish = null) {
  editingWishId = wish?.id || null;
  const form = $("#order-form");
  form.reset();
  form.elements.namedItem("item").value =
    wish?.item || (product ? products[product].title : "Моя идея");
  form.elements.namedItem("color").value = wish?.color || chosenColor;
  form.elements.namedItem("size").value = wish?.size || "";
  form.elements.namedItem("idea").value = wish?.idea || "";
  showDialog($("#order-dialog"));
  $("#order-dialog").scrollTop = 0;
}

// Delegation also handles cards created or removed on the favourites page.
document.addEventListener("click", (event) => {
  if (!event.target.closest(".header")) closeMenu();
  const favorite = event.target.closest("[data-favorite]");
  const product = event.target.closest("[data-product]");
  const order = event.target.closest("[data-order]");
  const edit = event.target.closest("[data-edit-wish]");
  const remove = event.target.closest("[data-remove-wish]");
  try {
    if (favorite) {
      const id = favorite.dataset.favorite;
      saved.toggleProduct(id);
      toast(
        saved.products().includes(id) ? "Вещь добавлена в избранное" : "Вещь удалена из избранного",
      );
    } else if (product) openProduct(product.dataset.product);
    else if (order) {
      event.preventDefault();
      openOrder();
    } else if (edit) {
      const wish = saved.wishes().find((value) => value.id === edit.dataset.editWish);
      if (wish) openOrder(null, wish);
    } else if (remove) {
      saved.removeWish(remove.dataset.removeWish);
      toast("Пожелания удалены из избранного");
    }
  } catch (error) {
    toast(error.message);
  }
});
$("#order-product").addEventListener("click", () => {
  $("#product-dialog").close();
  openOrder(activeProduct);
});

const colorImages = {
  Лаванда: "assets/basket5.png",
  Молочный: "assets/basket01.png",
  Изумруд: "assets/basket2.png",
  Карамель: "assets/basket3.png",
  Ягодный: "assets/basket4.png",
};
$$("[data-color]").forEach((button) =>
  button.addEventListener("click", () => {
    chosenColor = button.dataset.color;
    const image = $(".palette-photo img");
    image.src = colorImages[chosenColor];
    image.alt = `Вязаная корзинка с кружевом и розами. Оттенок: ${chosenColor}.`;
    $("#color-name").textContent = chosenColor;
    $$("[data-color]").forEach((node) => {
      const selected = node === button;
      node.classList.toggle("selected", selected);
      node.setAttribute("aria-pressed", String(selected));
    });
  }),
);
$("#order-form").addEventListener("submit", (event) => {
  event.preventDefault();
  const dialog = $("#order-dialog");
  const submit = $("button[type='submit']", event.currentTarget);
  if (!dialog.open || submit.disabled) return;
  submit.disabled = true;
  const data = new FormData(event.currentTarget);
  const wasEditing = Boolean(editingWishId);
  try {
    const wish = saved.saveWish(
      {
        item: data.get("item"),
        color: data.get("color"),
        size: data.get("size"),
        idea: data.get("idea"),
      },
      editingWishId,
    );
    editingWishId = wish.id;
    dialog.close();
    toast(
      wasEditing
        ? "Пожелания обновлены"
        : wish.productId
          ? "Товар с вашими пожеланиями добавлен в избранное"
          : "Ваша идея добавлена в избранное",
    );
  } catch (error) {
    toast(error.message);
  } finally {
    submit.disabled = false;
  }
});
$("#about-demo").addEventListener("click", () => showDialog($("#demo-dialog")));
$("#clear-favorites").addEventListener("click", () => {
  try {
    saved.clear();
    toast("Избранное очищено");
  } catch (error) {
    toast(error.message);
  }
});
window.addEventListener("tiho:saved-change", updateFavorites);
window.addEventListener("pageshow", updateFavorites);
updateFavorites();

const searchToggle = $(".search-toggle");
const searchPanel = $("#catalog-search");
const searchInput = $("#product-search");
if (searchPanel && searchInput) {
  searchToggle.addEventListener("click", () => {
    closeMenu();
    searchPanel.hidden = false;
    searchToggle.setAttribute("aria-expanded", "true");
    applyFilter("all");
    $("#collection").scrollIntoView({ behavior: "smooth" });
    searchInput.focus({ preventScroll: true });
  });
  function closeSearch() {
    searchInput.value = "";
    searchPanel.hidden = true;
    searchToggle.setAttribute("aria-expanded", "false");
    applyFilter(filter);
    searchToggle.focus({ preventScroll: true });
  }
  $(".search-close").addEventListener("click", closeSearch);
  searchInput.addEventListener("input", () => applyFilter(filter));
  searchInput.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeSearch();
  });
}
