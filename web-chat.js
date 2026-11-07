(function () {
  const root = document.createElement("div");
  root.className = "web-chat";
  root.innerHTML = `
        <button class="web-chat-toggle" type="button" aria-label="Открыть чат" aria-expanded="false" aria-controls="webChatPanel">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v12H9l-5 4V5Z" fill="currentColor"/><path d="M8 9h8M8 13h5" stroke="#6655ee" stroke-width="2"/></svg>
        </button>
        <section class="web-chat-panel" id="webChatPanel" role="dialog" aria-label="Чат с Vega Studio" aria-hidden="true">
            <div class="web-chat-header">
            <div><strong>Vega Assistant</strong><span class="web-chat-status" role="status"></span></div>
                <button class="web-chat-close" type="button" aria-label="Закрыть чат">×</button>
            </div>
            <div class="web-chat-messages" role="log" aria-label="Сообщения" aria-live="off"></div>
            <p class="web-chat-error" role="alert" hidden></p>
            <div class="web-chat-choices" role="group" aria-label="Меню услуг" hidden></div>
            <form class="web-chat-form" hidden>
                <textarea name="message" rows="2" maxlength="2000" placeholder="Введите сообщение..." aria-label="Ваше сообщение" required></textarea>
                <button type="submit" aria-label="Отправить сообщение">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 4 17 8-17 8 3-8-3-8Zm3 8h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                </button>
            </form>
        </section>
        <span class="chat-sr-only web-chat-announcer" aria-live="polite"></span>
    `;
  document.body.appendChild(root);
  const toggle = root.querySelector(".web-chat-toggle");
  const close = root.querySelector(".web-chat-close");
  const panel = root.querySelector(".web-chat-panel");
  const list = root.querySelector(".web-chat-messages");
  const form = root.querySelector(".web-chat-form");
  const choices = root.querySelector(".web-chat-choices");
  const textarea = form.querySelector("textarea");
  const submit = form.querySelector("button");
  const status = root.querySelector(".web-chat-status");
  const error = root.querySelector(".web-chat-error");
  const announcer = root.querySelector(".web-chat-announcer");
  let lastId = 0;
  let csrf = "";
  let starting = false;
  let sending = false;
  let polling = false;
  let state = { status: "bot" };
  const animated = new Map();

  function showError(message = "") {
    error.textContent = message;
    error.hidden = !message;
  }

  function resizeComposer() {
    textarea.style.height = "auto";
    textarea.style.height = Math.min(textarea.scrollHeight, 112) + "px";
  }

  function updateComposerState() {
    const canWrite = state.status === "waiting_human" || state.status === "human";
    form.hidden = !canWrite;
    textarea.disabled = !canWrite || starting;
    submit.disabled = !canWrite || !textarea.value.trim() || sending || starting;
    if (canWrite) resizeComposer();
  }

  function renderChoices() {
    const items = state.status === "bot" ? state.choices || [] : state.status === "waiting_human" ? [{ id: "cancel_waiting", label: "Отменить вызов и вернуться к помощнику" }] : [];
    const existing = Array.from(choices.children);
    if (items.length !== existing.length || items.some(({ id, label }, i) =>
      existing[i].value !== id || existing[i].dataset.label !== label)) {
      choices.replaceChildren(...items.map(({ id, label }) => {
        const button = document.createElement("button");
        button.type = "button";
        button.value = id;
        button.dataset.label = label;
        button.classList.toggle("is-specialist", id === "human");
        const icons = {
          landing: '<rect x="4" y="3" width="16" height="12" rx="2"/><path d="m4 15-2 5h20l-2-5M9 17h6"/>',
          shop: '<path d="M2 3h3l3 12h11l3-9H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>',
          code: '<path d="m7 6-5 6 5 6m10-12 5 6-5 6M14 3l-4 18"/>',
          ai: '<rect x="5" y="5" width="14" height="14" rx="3"/><rect x="9" y="9" width="6" height="6" rx="1"/><path d="M9 2v3m6-3v3M9 19v3m6-3v3M2 9h3m-3 6h3m14-6h3m-3 6h3"/>',
          layers: '<path d="m12 3 10 6-10 6L2 9Zm-9 11 9 5 9-5M3 19l9 5 9-5"/>',
          grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
          human: '<path d="M4 14v-3a8 8 0 0 1 16 0v3M20 17v1a3 3 0 0 1-3 3h-3"/><rect x="2" y="11" width="4" height="7" rx="2"/><rect x="18" y="11" width="4" height="7" rx="2"/>',
        };
        let icon = "layers";
        if (id === "human") icon = "human";
        else if (id === "root") icon = "grid";
        else if (/\u043b\u0435\u043d\u0434\u0438\u043d\u0433/i.test(label)) icon = "landing";
        else if (/\u043c\u0430\u0433\u0430\u0437\u0438\u043d/i.test(label)) icon = "shop";
        else if (/\u0434\u043e\u0440\u0430\u0431\u043e\u0442/i.test(label)) icon = "code";
        else if (/\u0438\u0438|ai/i.test(label)) icon = "ai";
        const symbol = document.createElementNS("http://www.w3.org/2000/svg", "svg");
        symbol.setAttribute("viewBox", "0 0 24 24");
        symbol.setAttribute("aria-hidden", "true");
        symbol.innerHTML = icons[icon];
        const caption = document.createElement("span");
        caption.className = "web-chat-choice-label";
        caption.textContent = label;
        button.append(symbol, caption);
        return button;
      }));
    }
    choices.hidden = items.length === 0;
    for (const button of choices.children) button.disabled = sending || starting;
  }

  function updateStatus() {
    renderChoices();
    let text = "Vega Assistant в сети";
    let typing = false;
    if (state.status === "closed") text = "Диалог завершён";
    else if (state.admin_typing && state.status === "human") {
      text = "Администратор печатает";
      typing = true;
    } else if (state.status === "waiting_human" || state.status === "human") {
      text = state.admin_online
        ? "Администратор в сети"
        : "Администратор подключён, ожидаем ответа";
    } else if (sending || state.assistant_typing || animated.size) {
      text = "Vega Assistant печатает";
      typing = true;
    }
    if (starting) text = "Подключение...";
    status.textContent = text;
    status.classList.toggle("is-typing", typing);
    if (typing) {
      const dots = document.createElement("span");
      dots.className = "chat-typing-dots";
      dots.setAttribute("aria-hidden", "true");
      for (let i = 0; i < 3; i++) dots.appendChild(document.createElement("i"));
      status.appendChild(dots);
    }
    updateComposerState();
  }

  function nearBottom() {
    return list.scrollHeight - list.scrollTop - list.clientHeight < 65;
  }

  function chatBrandText(value) {
    return String(value)
      .replace(/WebStart Assistant/gi, "Vega Assistant")
      .replace(/WebStart Studio/gi, "Vega Studio")
      .replace(/WebStart/gi, "Vega");
  }

  function addMessage(message, animate) {
    const id = Number(message.id);
    if (id <= lastId) return;
    lastId = id;
    const follow = nearBottom();
    const item = document.createElement("div");
    item.className =
      "web-chat-message is-" +
      ({ user: "user", admin: "admin", bot: "bot" }[message.sender] || "bot");
    item.dataset.messageId = String(id);
    list.appendChild(item);
    const text = chatBrandText(message.message);
    if (
      !animate ||
      message.sender !== "bot" ||
      matchMedia("(prefers-reduced-motion: reduce)").matches
    ) {
      item.textContent = text;
      if (follow) list.scrollTop = list.scrollHeight;
      return;
    }
    const words = text.match(/\S+\s*/g) || [text];
    const duration = Math.min(2400, Math.max(450, words.length * 18));
    const started = performance.now();
    animated.set(id, { item, text });
    function frame(now) {
      if (!animated.has(id)) return;
      const scroll = nearBottom();
      const progress = Math.min(1, (now - started) / duration);
      item.textContent = words
        .slice(0, Math.ceil(words.length * progress))
        .join("");
      if (scroll) list.scrollTop = list.scrollHeight;
      if (progress < 1 && root.classList.contains("is-open"))
        requestAnimationFrame(frame);
      else {
        item.textContent = text;
        animated.delete(id);
        announcer.textContent = text;
        updateStatus();
      }
    }
    requestAnimationFrame(frame);
  }

  let requestQueue = Promise.resolve();
  function request(action, body = {}) {
    const pending = requestQueue.then(() => requestNow(action, body));
    requestQueue = pending.catch(() => {});
    return pending;
  }

  async function requestNow(action, body) {
    const response = await fetch("api/chat.php", {
      method: "POST",
      credentials: "same-origin",
      body: new URLSearchParams({
        action,
        csrf_token: csrf,
        after_id: String(lastId),
        ...body,
      }),
    });
    const data = await response.json();
    if (!response.ok)
      throw new Error(data.error || "Не удалось отправить сообщение.");
    if (data.csrf_token) csrf = data.csrf_token;
    if (data.max_input_length) textarea.maxLength = data.max_input_length;
    state = data;
    for (const message of data.messages || [])
      addMessage(message, action !== "start");
    updateStatus();
    return data;
  }

  async function openChat() {
    root.classList.add("is-open");
    toggle.setAttribute("aria-expanded", "true");
    panel.setAttribute("aria-hidden", "false");
    if (!csrf && !starting) {
      starting = true;
      updateStatus();
      try {
        await request("start");
        showError();
        list.scrollTop = list.scrollHeight;
      } catch (exception) {
        showError(
          "Не удалось открыть чат. Проверьте соединение и откройте его снова.",
        );
      } finally {
        starting = false;
        updateStatus();
      }
    }
    (form.hidden ? close : textarea).focus();
  }

  function closeChat() {
    root.classList.remove("is-open");
    toggle.setAttribute("aria-expanded", "false");
    panel.setAttribute("aria-hidden", "true");
    for (const { item, text } of animated.values()) item.textContent = text;
    animated.clear();
    toggle.focus();
  }
  toggle.addEventListener("click", openChat);
  close.addEventListener("click", closeChat);
  panel.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeChat();
  });
  choices.addEventListener("click", async (event) => {
    const button = event.target.closest("button");
    if (!button || sending || starting || !csrf) return;
    sending = true;
    updateStatus();
    showError();
    try {
      if (button.value === "cancel_waiting") await request("cancel_waiting");
      else await request("message", { choice: button.value });
      if (button.value === "human" && !form.hidden && root.classList.contains("is-open")) {
        textarea.focus();
      }
    } catch (exception) {
      showError(exception.message);
    } finally {
      sending = false;
      updateStatus();
    }
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const message = textarea.value.trim();
    if (form.hidden || !message || sending || starting || !csrf) return;
    sending = true;
    updateStatus();
    showError();
    const draft = textarea.value;
    try {
      await request("message", { message });
      if (textarea.value === draft) textarea.value = "";
      updateComposerState();
    } catch (exception) {
      showError(
        exception instanceof TypeError
          ? "Нет связи с сервером. Текст сохранён в поле ввода."
          : exception.message,
      );
    } finally {
      sending = false;
      updateStatus();
    }
  });
  textarea.addEventListener("input", updateComposerState);
  textarea.addEventListener("keydown", (event) => {
    if (event.key !== "Enter" || event.shiftKey || event.isComposing) return;
    event.preventDefault();
    if (typeof form.requestSubmit === "function") form.requestSubmit();
    else submit.click();
  });
  setInterval(async () => {
    if (
      !root.classList.contains("is-open") ||
      !csrf ||
      document.hidden ||
      polling ||
      starting ||
      sending
    )
      return;
    polling = true;
    try {
      await request("poll");
    } catch (exception) {
      showError("Нет связи с сервером. Повторяем подключение...");
    } finally {
      polling = false;
    }
  }, 2500);
  resizeComposer();
  updateStatus();
})();
