(() => {
    'use strict';
    if (document.getElementById('analytics-preferences')) return;

    const counterId = 114096795;
    const storageKey = 'vegaAnalyticsConsent';
    const endpoint = '/api/analytics-consent.php';
    const production = ['vegastud1o.ru', 'www.vegastud1o.ru'].includes(location.hostname);
    let serverState = null;
    let running = false;
    let busy = false;
    let generation = 0;
    let expiryTimer;

    function storedChoice() {
        try { return JSON.parse(localStorage.getItem(storageKey)); }
        catch (error) { return null; }
    }

    function saveChoice(value) {
        try { localStorage.setItem(storageKey, JSON.stringify(value)); }
        catch (error) { /* No stored preference means no automatic activation. */ }
    }

    function clearAnalyticsStorage() {
        const ownedKey = (key) => /^_ym(?:_|114096795(?:_|$))/.test(key) ||
            /^ytm_(?:tf|tag)_114096795_/.test(key) || key === 'zz';
        for (const name of ['localStorage', 'sessionStorage']) {
            try {
                const storage = window[name];
                Object.keys(storage).filter(ownedKey).forEach((key) => storage.removeItem(key));
            } catch (error) { /* Browser settings can disable storage. */ }
        }
        const paths = new Set(['/']);
        let path = '';
        for (const part of location.pathname.split('/').filter(Boolean)) {
            path += '/' + part;
            paths.add(path);
            paths.add(path + '/');
        }
        const domains = new Set(['', location.hostname, '.' + location.hostname]);
        if (production) {
            domains.add('vegastud1o.ru');
            domains.add('.vegastud1o.ru');
        }
        for (const cookie of document.cookie.split(';')) {
            const name = cookie.trim().split('=')[0];
            if (!ownedKey(name) && name !== 'gdpr') continue;
            for (const cookiePath of paths) {
                for (const domain of domains) {
                    document.cookie = name + '=; Max-Age=0; path=' + cookiePath +
                        (domain ? '; domain=' + domain : '') +
                        (location.protocol === 'https:' ? '; Secure' : '') + '; SameSite=Lax';
                }
            }
        }
    }

    function stopCounter() {
        clearTimeout(expiryTimer);
        window['disableYaCounter' + counterId] = true;
        if (window.ym) {
            window.ym(counterId, 'destruct');
            if (Array.isArray(window.ym.a)) {
                window.ym.a = window.ym.a.filter((args) => args[0] !== counterId);
            }
        }
        document.getElementById('vega-metrica-script')?.remove();
        const wasRunning = running;
        running = false;
        clearAnalyticsStorage();
        return wasRunning;
    }

    function startCounter() {
        if (!production || running) return;
        running = true;
        window['disableYaCounter' + counterId] = false;
        window.ym = window.ym || function () {
            (window.ym.a = window.ym.a || []).push(arguments);
        };
        window.ym.l = Date.now();
        window.ym(counterId, 'init', {
            ssr: true, webvisor: false, clickmap: false, trackLinks: false,
            trackHash: false, ecommerce: false, disableYtm: true, sendTitle: false,
            accurateTrackBounce: true, url: location.origin + location.pathname, referrer: ''
        });
        const script = document.createElement('script');
        script.id = 'vega-metrica-script';
        script.async = true;
        script.src = 'https://mc.yandex.ru/metrika/tag.js?id=' + counterId;
        document.head.appendChild(script);
    }

    async function request(options = {}) {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetch(endpoint, {
                credentials: 'same-origin', cache: 'no-store', ...options, signal: controller.signal
            });
            const result = await response.json();
            if (!response.ok) throw new Error('Consent request failed');
            return result;
        } finally { clearTimeout(timeout); }
    }

    const panel = document.createElement('section');
    panel.id = 'analytics-preferences';
    panel.className = 'analytics-consent';
    panel.setAttribute('aria-labelledby', 'analytics-heading');
    panel.innerHTML = `
        <h2 id="analytics-heading">Настройки аналитики</h2>
        <p class="analytics-consent__text">Загрузка настроек аналитики…</p>
        <a href="https://yandex.ru/legal/confidential/" target="_blank" rel="noopener noreferrer">Обработка данных Яндексом</a>
        <div class="analytics-consent__actions">
            <button type="button" data-choice="accepted" disabled>Разрешить аналитику</button>
            <button type="button" data-choice="rejected">Отказаться</button>
        </div>
        <p class="analytics-consent__status" role="status"></p>`;
    const status = panel.querySelector('.analytics-consent__status');
    const accept = panel.querySelector('[data-choice="accepted"]');
    const settings = document.createElement('button');
    settings.type = 'button';
    settings.className = 'analytics-settings';
    settings.textContent = 'Настройки cookie';
    settings.setAttribute('aria-controls', panel.id);

    function showPanel(show) {
        panel.hidden = !show;
        settings.hidden = show;
        settings.setAttribute('aria-expanded', String(show));
    }

    function scheduleExpiry(expiresAt) {
        clearTimeout(expiryTimer);
        const remaining = expiresAt - Date.now();
        expiryTimer = setTimeout(() => {
            if (Date.now() < expiresAt) return scheduleExpiry(expiresAt);
            const reload = stopCounter();
            showPanel(true);
            if (reload) location.reload();
        }, Math.max(0, Math.min(remaining, 2147483647)));
    }

    function applyState(state, keepOpen = false) {
        serverState = state;
        panel.querySelector('.analytics-consent__text').textContent = state.text;
        accept.disabled = false;
        const local = storedChoice();
        const accepted = state.choice === 'accepted' && local?.choice === 'accepted' &&
            local.receipt === state.receipt && local.version === state.version && state.expiresAt > Date.now();
        const rejected = state.choice === 'rejected' || (local?.choice === 'rejected' && local.pending);
        clearTimeout(expiryTimer);
        if (accepted) {
            startCounter();
            scheduleExpiry(state.expiresAt);
        } else if (stopCounter()) {
            location.reload();
        }
        showPanel(keepOpen || (!accepted && !rejected));
    }

    async function refresh(keepOpen = false) {
        const currentGeneration = ++generation;
        try {
            let state = await request();
            const pending = storedChoice();
            if (pending?.choice === 'rejected' && pending.pending && pending.requestId) {
                state = await request({
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': state.csrf},
                    body: JSON.stringify({
                        choice: 'rejected', requestId: pending.requestId,
                        version: state.version, expectedReceipt: state.receipt
                    })
                });
                if (currentGeneration === generation) {
                    saveChoice({choice: state.choice, receipt: state.receipt, version: state.version});
                }
            }
            if (currentGeneration === generation) applyState(state, keepOpen);
        } catch (error) {
            if (currentGeneration !== generation) return;
            stopCounter();
            status.textContent = 'Аналитика выключена. Не удалось получить настройки. Попробуйте позже.';
            panel.querySelector('.analytics-consent__text').textContent = 'Для подключения аналитики требуется ваше разрешение.';
            accept.disabled = true;
        }
    }

    settings.addEventListener('click', () => {
        showPanel(true);
        panel.querySelector('button:not(:disabled)').focus();
        refresh(true);
    });
    panel.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-choice]');
        if (!button || busy) return;
        const currentGeneration = ++generation;
        busy = true;
        const nextChoice = button.dataset.choice;
        const pending = {choice: nextChoice, requestId: crypto.randomUUID(), pending: nextChoice === 'rejected'};
        const reload = nextChoice === 'rejected' ? stopCounter() : false;
        saveChoice(pending);
        accept.disabled = true;
        status.textContent = 'Сохраняем выбор…';
        try {
            serverState = serverState || await request();
            const state = await request({
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': serverState.csrf},
                body: JSON.stringify({
                    choice: nextChoice, requestId: pending.requestId, version: serverState.version,
                    expectedReceipt: serverState.receipt
                })
            });
            if (currentGeneration !== generation) return;
            saveChoice({choice: state.choice, receipt: state.receipt, version: state.version});
            applyState(state);
            status.textContent = '';
            settings.focus();
        } catch (error) {
            if (currentGeneration === generation) {
                stopCounter();
                saveChoice(nextChoice === 'rejected' ? pending : {choice: 'rejected'});
                status.textContent = 'Аналитика выключена. Записать выбор на сервере не удалось. Повторите попытку позже.';
                showPanel(true);
                serverState = null;
            }
        } finally {
            busy = false;
            accept.disabled = !serverState;
            if (reload) location.reload();
        }
    });
    window.addEventListener('storage', (event) => {
        if (event.key !== storageKey && event.key !== null) return;
        if (storedChoice()?.choice !== 'accepted' || !storedChoice()?.receipt) stopCounter();
        refresh();
    });
    window.addEventListener('pageshow', (event) => { if (event.persisted && !busy) refresh(); });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && !busy) refresh();
    });
    document.body.append(panel, settings);
    showPanel(true);
    refresh();
})();
