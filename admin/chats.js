(function () {
    const shell = document.querySelector('[data-admin-chat-csrf]');
    if (!shell) return;
    const thread = document.querySelector('[data-chat-id]');
    const navigation = document.querySelector('.admin-chat-list');
    const error = document.querySelector('.admin-chat-error');
    const list = thread?.querySelector('.admin-chat-messages');
    const form = thread?.querySelector('.admin-reply-form');
    const input = form?.querySelector('textarea');
    const status = thread?.querySelector('.admin-chat-status');
    const labels = {bot:'Ассистент', waiting_human:'Ожидает администратора', human:'Администратор', closed:'Завершён'};
    const chatId = Number(thread?.dataset.chatId) || 0;
    let lastId = Math.max(0, ...Array.from(list?.querySelectorAll('[data-message-id]') || [], node => Number(node.dataset.messageId)));
    let sending = false, polling = false, typingTimer, lastTyped = 0, queue = Promise.resolve();

    function displayError(exception) { error.textContent = exception.message; }
    async function fetchData(action, value = '') {
        const response = await fetch('chat-api.php', {
            method:'POST', credentials:'same-origin',
            body:new URLSearchParams({action, value, chat_id:String(chatId), csrf_token:shell.dataset.adminChatCsrf, after_id:String(lastId)}),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Не удалось выполнить действие.');
        return data;
    }
    async function refreshList() {
        const data = await fetchData('list');
        const scroll = navigation.scrollTop;
        const focusedHref = navigation.contains(document.activeElement)
            ? document.activeElement.getAttribute('href') : null;
        const items = data.conversations.map(chat => {
            const link = document.createElement('a');
            link.className = 'admin-chat-list-item';
            link.href = 'chats.php?id=' + Number(chat.id) + '#chatThread';
            if (Number(chat.id) === chatId) link.setAttribute('aria-current', 'page');
            const title = document.createElement('strong');
            title.append('#' + Number(chat.id) + ' ');
            const badge = document.createElement('span');
            badge.className = 'admin-chat-badge is-' + chat.status;
            badge.textContent = labels[chat.status] || chat.status;
            title.append(badge);
            const preview = document.createElement('span');
            preview.className = 'admin-chat-preview';
            preview.textContent = (chat.last_message || '').slice(0, 90);
            link.append(title, preview);
            if (Number(chat.unread_count) > 0) {
                const unread = document.createElement('small');
                unread.className = 'admin-chat-unread';
                unread.textContent = 'Новых сообщений: ' + Number(chat.unread_count);
                link.append(unread);
            }
            return link;
        });
        navigation.replaceChildren(...items);
        navigation.scrollTop = scroll;
        if (focusedHref) {
            Array.from(navigation.querySelectorAll('a')).find(link =>
                link.getAttribute('href') === focusedHref)?.focus({preventScroll:true});
        }
    }
    function setStatus(value) {
        if (!form) return;
        status.textContent = labels[value] || value;
        const canReply = value === 'waiting_human' || value === 'human';
        input.disabled = !canReply;
        form.querySelector('button').disabled = sending || !canReply;
    }
    function request(action, value = '') {
        const pending = queue.then(async () => {
            const data = await fetchData(action, value);
            setStatus(data.status);
            for (const message of data.messages || []) {
                if (Number(message.id) <= lastId) continue;
                lastId = Number(message.id);
                const follow = list.scrollHeight - list.scrollTop - list.clientHeight < 60;
                const item = document.createElement('div');
                item.className = 'admin-chat-message is-' + message.sender;
                item.dataset.messageId = String(message.id);
                const name = document.createElement('small');
                name.textContent = ({user:'Посетитель', bot:'Ассистент', admin:'Администратор'}[message.sender] || '') + ' · ' + message.created_at;
                const text = document.createElement('p');
                text.textContent = message.message;
                item.append(name, text);
                list.append(item);
                if (follow || message.sender === 'admin') list.scrollTop = list.scrollHeight;
            }
            error.textContent = '';
        });
        queue = pending.catch(() => {});
        return pending;
    }
    if (form) {
        setStatus(thread.dataset.status);
        list.scrollTop = list.scrollHeight;
        input.addEventListener('input', () => {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                lastTyped = Date.now();
                request('typing', input.value.trim() ? '1' : '0').catch(displayError);
            }, Date.now() - lastTyped > 2000 ? 0 : 450);
        });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (sending || input.disabled || !input.value.trim()) return;
            sending = true;
            form.querySelector('button').disabled = true;
            clearTimeout(typingTimer);
            const value = input.value;
            try {
                await request('reply', value);
                if (input.value === value) input.value = '';
                await refreshList();
            } catch (exception) { displayError(exception); }
            finally { sending = false; form.querySelector('button').disabled = input.disabled; }
        });
        thread.querySelectorAll('.admin-chat-status-form').forEach(statusForm => {
            statusForm.addEventListener('submit', async event => {
                event.preventDefault();
                try { await request('status', event.submitter.value); await refreshList(); }
                catch (exception) { displayError(exception); }
            });
        });
    }
    setInterval(async () => {
        if (document.hidden || polling || sending) return;
        polling = true;
        try {
            if (chatId) await request('poll');
            await refreshList();
        } catch (exception) { displayError(exception); }
        finally { polling = false; }
    }, 2500);
}());
