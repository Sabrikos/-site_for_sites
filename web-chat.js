(function () {
    const root = document.createElement('div');
    root.className = 'web-chat';
    root.innerHTML = `
        <button class="web-chat-toggle" type="button" aria-label="Открыть чат" aria-expanded="false" aria-controls="webChatPanel">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v12H9l-5 4V5Z" fill="currentColor"/><path d="M8 9h8M8 13h5" stroke="#6655ee" stroke-width="2"/></svg>
        </button>
        <section class="web-chat-panel" id="webChatPanel" role="dialog" aria-label="Чат с WebStart Studio" aria-hidden="true">
            <div class="web-chat-header">
                <div><strong>WebStart Assistant</strong><span class="web-chat-status" role="status"></span></div>
                <button class="web-chat-close" type="button" aria-label="Закрыть чат">×</button>
            </div>
            <div class="web-chat-messages" role="log" aria-label="Сообщения" aria-live="off"></div>
            <p class="web-chat-error" role="alert" hidden></p>
            <form class="web-chat-form">
                <textarea name="message" rows="2" maxlength="2000" placeholder="Введите сообщение..." aria-label="Ваше сообщение" required></textarea>
                <button type="submit" aria-label="Отправить сообщение">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 4 17 8-17 8 3-8-3-8Zm3 8h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                </button>
            </form>
        </section>
        <span class="chat-sr-only web-chat-announcer" aria-live="polite"></span>
    `;
    document.body.appendChild(root);
    const toggle = root.querySelector('.web-chat-toggle');
    const close = root.querySelector('.web-chat-close');
    const panel = root.querySelector('.web-chat-panel');
    const list = root.querySelector('.web-chat-messages');
    const form = root.querySelector('.web-chat-form');
    const textarea = form.querySelector('textarea');
    const submit = form.querySelector('button');
    const status = root.querySelector('.web-chat-status');
    const error = root.querySelector('.web-chat-error');
    const announcer = root.querySelector('.web-chat-announcer');
    let lastId = 0;
    let csrf = '';
    let starting = false;
    let sending = false;
    let polling = false;
    let state = {status: 'bot'};
    const animated = new Map();

    function showError(message = '') {
        error.textContent = message;
        error.hidden = !message;
    }

    function updateStatus() {
        let text = 'В сети';
        let typing = false;
        if (state.status === 'closed') text = 'Диалог завершён';
        else if (state.admin_typing && state.status === 'human') {
            text = 'Администратор печатает';
            typing = true;
        } else if (state.status === 'waiting_human' || state.status === 'human') {
            text = state.admin_online ? 'Администратор в сети' : 'Ожидаем администратора';
        } else if (sending || state.assistant_typing || animated.size) {
            text = 'Assistant печатает';
            typing = true;
        }
        if (starting) text = 'Подключение...';
        status.textContent = text;
        status.classList.toggle('is-typing', typing);
        if (typing) {
            const dots = document.createElement('span');
            dots.className = 'chat-typing-dots';
            dots.setAttribute('aria-hidden', 'true');
            for (let i = 0; i < 3; i++) dots.appendChild(document.createElement('i'));
            status.appendChild(dots);
        }
        textarea.disabled = state.status === 'closed' || starting;
        submit.disabled = sending || starting || state.status === 'closed';
    }

    function nearBottom() {
        return list.scrollHeight - list.scrollTop - list.clientHeight < 65;
    }

    function addMessage(message, animate) {
        const id = Number(message.id);
        if (id <= lastId) return;
        lastId = id;
        const follow = nearBottom();
        const item = document.createElement('div');
        item.className = 'web-chat-message is-' + ({user: 'user', admin: 'admin', bot: 'bot'}[message.sender] || 'bot');
        item.dataset.messageId = String(id);
        list.appendChild(item);
        const text = String(message.message);
        if (!animate || message.sender !== 'bot' || matchMedia('(prefers-reduced-motion: reduce)').matches) {
            item.textContent = text;
            if (follow) list.scrollTop = list.scrollHeight;
            return;
        }
        const words = text.match(/\S+\s*/g) || [text];
        const duration = Math.min(2400, Math.max(450, words.length * 18));
        const started = performance.now();
        animated.set(id, {item, text});
        function frame(now) {
            if (!animated.has(id)) return;
            const scroll = nearBottom();
            const progress = Math.min(1, (now - started) / duration);
            item.textContent = words.slice(0, Math.ceil(words.length * progress)).join('');
            if (scroll) list.scrollTop = list.scrollHeight;
            if (progress < 1 && root.classList.contains('is-open')) requestAnimationFrame(frame);
            else {
                item.textContent = text;
                animated.delete(id);
                announcer.textContent = text;
                updateStatus();
            }
        }
        requestAnimationFrame(frame);
    }

    async function request(action, body = {}) {
        const response = await fetch('api/chat.php', {
            method: 'POST', credentials: 'same-origin',
            body: new URLSearchParams({action, csrf_token: csrf, after_id: String(lastId), ...body})
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Не удалось отправить сообщение.');
        if (data.csrf_token) csrf = data.csrf_token;
        if (data.max_input_length) textarea.maxLength = data.max_input_length;
        state = data;
        for (const message of data.messages || []) addMessage(message, action !== 'start');
        updateStatus();
        return data;
    }

    async function openChat() {
        root.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        panel.setAttribute('aria-hidden', 'false');
        if (!csrf && !starting) {
            starting = true;
            updateStatus();
            try {
                await request('start');
                showError();
                list.scrollTop = list.scrollHeight;
            } catch (exception) { showError('Не удалось открыть чат. Проверьте соединение и откройте его снова.'); }
            finally { starting = false; updateStatus(); }
        }
        textarea.focus();
    }

    function closeChat() {
        root.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        panel.setAttribute('aria-hidden', 'true');
        for (const {item, text} of animated.values()) item.textContent = text;
        animated.clear();
        toggle.focus();
    }
    toggle.addEventListener('click', openChat);
    close.addEventListener('click', closeChat);
    panel.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeChat();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const message = textarea.value.trim();
        if (!message || sending || starting || !csrf) return;
        sending = true;
        updateStatus();
        showError();
        const draft = textarea.value;
        try {
            await request('message', {message});
            if (textarea.value === draft) textarea.value = '';
        } catch (exception) {
            showError(exception instanceof TypeError ? 'Нет связи с сервером. Текст сохранён в поле ввода.' : exception.message);
        } finally {
            sending = false;
            updateStatus();
        }
    });
    setInterval(async () => {
        if (!root.classList.contains('is-open') || !csrf || document.hidden || polling || starting) return;
        polling = true;
        try { await request('poll'); }
        catch (exception) { showError('Нет связи с сервером. Повторяем подключение...'); }
        finally { polling = false; }
    }, 2500);
    updateStatus();
}());
