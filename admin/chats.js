(function () {
    const thread = document.querySelector('[data-chat-id]');
    if (!thread || !Number(thread.dataset.chatId)) return;
    const list = thread.querySelector('.admin-chat-messages');
    const form = thread.querySelector('.admin-reply-form');
    const input = form.querySelector('textarea');
    const error = document.querySelector('.admin-chat-error');
    const status = thread.querySelector('.admin-chat-status');
    const labels = {bot: 'Ассистент', waiting_human: 'Ожидает администратора', human: 'Администратор', closed: 'Завершён'};
    let lastId = Math.max(0, ...Array.from(list.querySelectorAll('[data-message-id]'), node => Number(node.dataset.messageId)));
    let polling = false;
    let sending = false;
    let typingTimer;
    let lastTyped = 0;
    list.scrollTop = list.scrollHeight;

    async function request(action, value = '') {
        const response = await fetch('chat-api.php', {method: 'POST', credentials: 'same-origin',
            body: new URLSearchParams({action, value, chat_id: thread.dataset.chatId, csrf_token: thread.dataset.csrf, after_id: String(lastId)})});
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Не удалось выполнить действие.');
        status.textContent = labels[data.status] || data.status;
        const currentLink = document.querySelector('.admin-chat-list-item[aria-current]');
        if (currentLink) {
            currentLink.querySelector('strong').textContent = '#' + thread.dataset.chatId + ' · ' + (labels[data.status] || data.status);
            const latest = (data.messages || []).at(-1);
            if (latest) currentLink.querySelector('span').textContent = latest.message.slice(0, 90);
        }
        for (const message of data.messages || []) {
            if (Number(message.id) <= lastId) continue;
            lastId = Number(message.id);
            const follow = list.scrollHeight - list.scrollTop - list.clientHeight < 60;
            const item = document.createElement('div');
            item.className = 'admin-chat-message is-' + ({user: 'user', bot: 'bot', admin: 'admin'}[message.sender] || 'bot');
            item.dataset.messageId = String(message.id);
            const name = document.createElement('small');
            name.textContent = ({user: 'Посетитель', bot: 'Ассистент', admin: 'Администратор'}[message.sender] || '') + ' · ' + message.created_at;
            const text = document.createElement('p');
            text.textContent = message.message;
            item.append(name, text);
            list.appendChild(item);
            if (follow) list.scrollTop = list.scrollHeight;
        }
        error.textContent = '';
    }
    async function typing() {
        lastTyped = Date.now();
        try { await request('typing', input.value.trim() ? '1' : '0'); }
        catch (exception) { error.textContent = exception.message; }
    }
    input.addEventListener('input', () => {
        clearTimeout(typingTimer);
        if (Date.now() - lastTyped > 2000) typing();
        else typingTimer = setTimeout(typing, 450);
    });
    input.addEventListener('blur', () => {
        clearTimeout(typingTimer);
        request('typing', '0').catch(() => {});
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (sending || !input.value.trim()) return;
        sending = true;
        form.querySelector('button').disabled = true;
        clearTimeout(typingTimer);
        const value = input.value;
        try {
            await request('reply', value);
            if (input.value === value) input.value = '';
        } catch (exception) { error.textContent = exception.message; }
        finally { sending = false; form.querySelector('button').disabled = false; }
    });
    thread.querySelectorAll('.admin-chat-status-form').forEach(statusForm => {
        statusForm.addEventListener('submit', async event => {
            event.preventDefault();
            try { await request('status', event.submitter.value); }
            catch (exception) { error.textContent = exception.message; }
        });
    });
    setInterval(async () => {
        if (document.hidden || polling || sending || Date.now() - lastTyped < 6000) return;
        polling = true;
        try { await request('poll'); }
        catch (exception) { error.textContent = exception.message; }
        finally { polling = false; }
    }, 2500);
}());
