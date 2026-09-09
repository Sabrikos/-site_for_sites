const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const {spawnSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const base = process.env.TEST_BASE_URL || 'http://127.0.0.1:8001';
const artifacts = process.env.TEST_ARTIFACTS || path.join(process.env.TEMP, 'webstart-browser-artifacts');
const php = process.env.PHP_BINARY;
function fixture(data) {
    const result = spawnSync(php, [path.join(__dirname, 'browser-fixture.php')], {input: JSON.stringify(data), encoding: 'utf8', cwd: root});
    if (result.status !== 0) throw new Error('Test fixture failed: ' + result.stderr);
    return JSON.parse(result.stdout);
}
let checks = 0;
function pass(text) { checks++; console.log('PASS:', text); }

(async () => {
    fs.mkdirSync(artifacts, {recursive: true});
    const browser = await chromium.launch({headless: true, executablePath: process.env.CHROMIUM_BINARY});
    const credentials = fixture({action: 'create'});
    const conversations = new Set();
    const errors = [];
    try {
        const context = await browser.newContext({viewport: {width: 1920, height: 1080}});
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        page.on('response', async response => {
            if (response.url().endsWith('/api/chat.php') && response.ok()) {
                const body = await response.json().catch(() => ({}));
                if (body.conversation_id) conversations.add(body.conversation_id);
            }
        });
        for (const url of ['/', '/tariffs.php', '/cart.php', '/create-order.php', '/privacy.php', '/admin/login.php']) {
            const response = await page.goto(base + url);
            assert.equal(response.status(), 200, url);
            assert(!/Warning:|Fatal error:|Parse error:/.test(await page.locator('body').innerText()), url);
        }
        pass('public pages return 200 without PHP errors');
        for (const file of ['/.env', '/database.sql', '/storage/logs/app.log', '/services/AiAssistant.php', '/tests/assistant.php', '/.git/config']) {
            const response = await context.request.get(base + file);
            assert.equal(response.status(), 404, file);
        }
        pass('private files blocked by local router');
        for (const width of process.env.TEST_FLOWS_ONLY ? [] : [1920, 1440, 1024, 850, 760, 390, 320]) {
            await page.setViewportSize({width, height: width < 500 ? 844 : 1080});
            await page.goto(base + '/tariffs.php');
            const issues = await page.locator('.tariff-card').evaluateAll(cards => cards.flatMap(card => {
                const bounds = card.getBoundingClientRect();
                return Array.from(card.querySelectorAll('h3, .tariff-description, .tariff-price, button')).flatMap(node => {
                    const range = document.createRange();
                    range.selectNodeContents(node);
                    return Array.from(range.getClientRects()).filter(rect => rect.width > 0 && (rect.left < bounds.left - 1 || rect.right > bounds.right + 1 || rect.bottom > bounds.bottom + 1)).map(() => node.textContent.trim());
                });
            }));
            assert.deepEqual(issues, [], 'card overflow at ' + width);
            const horizontalOverflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
            assert(!horizontalOverflow, 'page overflow at ' + width);
            if (width === 1920) {
                const ys = await page.locator('#landing .tariff-card').evaluateAll(cards => cards.map(card => card.getBoundingClientRect().top));
                assert(ys.every(y => Math.abs(y - ys[0]) < 1), 'three desktop columns');
                await page.locator('#other').screenshot({path: path.join(artifacts, 'tariff-other-desktop.png')});
                await page.screenshot({path: path.join(artifacts, 'tariffs-desktop.png')});
            }
            if (width === 390) await page.locator('#other').screenshot({path: path.join(artifacts, 'tariff-other-mobile.png')});
            pass('all tariff text fits at ' + width + 'px');
        }
        await page.setViewportSize({width: 1440, height: 1000});
        await page.goto(base + '/tariffs.php');
        await page.locator('.tariff-button').first().click();
        assert(await page.locator('.tariff-button').first().evaluate(button => button.classList.contains('selected')));
        await page.goto(base + '/cart.php');
        assert(await page.locator('.cart-product').count() > 0);
        pass('tariff selection persists in cart');
        await page.goto(base + '/create-order.php');
        const csrfOrder = await page.locator('[name=csrf_token]').inputValue();
        const tariffId = await page.evaluate(() => { const item = JSON.parse(localStorage.getItem('webstartCart'))[0]; return item.tariff_id ?? item.id; });
        const orderResponse = await context.request.post(base + '/create-order.php', {form: {
            csrf_token: csrfOrder, customer_name: 'Browser test', customer_phone: '+70000000000', customer_email: 'browser-test@example.invalid',
            project_comment: credentials.username, personal_data_consent: '1', cart_json: JSON.stringify([{id: tariffId, price: 1}])
        }});
        assert.equal(orderResponse.status(), 200);
        const orders = fixture({action: 'order', marker: credentials.username});
        assert(orders.length === 1 && Number(orders[0].total) === Number(orders[0].current_price) && Number(orders[0].price) !== 1);
        pass('site order saved with DB price despite forged price');
        await page.goto(base + '/');
        await page.locator('.web-chat-toggle').click();
        await page.waitForFunction(() => document.querySelector('.web-chat-message'));
        const send = async text => {
            await page.locator('.web-chat-form textarea').fill(text);
            const response = page.waitForResponse(r => r.url().endsWith('/api/chat.php') && (r.request().postData() || '').includes('action=message'));
            await page.locator('.web-chat-form button').click();
            const data = await (await response).json();
            assert(!data.error, data.error);
            conversations.add(data.conversation_id);
            return data;
        };
        let data = await send('Расскажи про Лендинг Бизнес и AI ассистента');
        await page.waitForTimeout(2700);
        assert(await page.locator('.web-chat-message.is-user').count() > 0, 'user message renders in chat');
        assert(await page.locator('.web-chat-message.is-user').last().innerText() === 'Расскажи про Лендинг Бизнес и AI ассистента');
        assert(data.messages.some(row => row.sender === 'bot' && row.message.includes('Лендинг — Бизнес')));
        await page.screenshot({path: path.join(artifacts, 'chat-desktop.png')});
        pass('chat sends message and renders contextual answer');
        const denied = await context.request.post(base + '/api/chat.php', {form: {action: 'message', message: 'No csrf'}});
        assert.equal(denied.status(), 403);
        const crossOrigin = await context.request.post(base + '/api/chat.php', {headers: {Origin: 'https://untrusted.invalid'}, form: {action: 'start'}});
        assert.equal(crossOrigin.status(), 403);
        const stranger = await browser.newContext();
        const unauthorized = await stranger.request.post(base + '/admin/chat-api.php', {form: {action: 'poll', chat_id: data.conversation_id}});
        assert.equal(unauthorized.status(), 401);
        await stranger.close();
        pass('CSRF, cross-origin and anonymous admin access blocked');
        data = await send('Позови человека');
        assert.equal(data.status, 'waiting_human');
        const id = data.conversation_id;
        const adminContext = await browser.newContext({viewport: {width: 1440, height: 1000}});
        const admin = await adminContext.newPage();
        admin.on('pageerror', error => errors.push(error.message));
        await admin.goto(base + '/admin/login.php');
        await admin.locator('[name=username]').fill(credentials.username);
        await admin.locator('[name=password]').fill(credentials.password);
        await Promise.all([admin.waitForURL('**/admin/orders.php'), admin.locator('button[type=submit]').click()]);
        await admin.goto(base + '/admin/chats.php?id=' + id);
        await admin.getByRole('button', {name: 'Подключиться', exact: true}).click();
        await admin.waitForTimeout(500);
        await admin.locator('#adminReply').fill('Я подключился к диалогу.');
        await page.waitForFunction(() => document.querySelector('.web-chat-status').textContent.includes('Администратор печатает'), null, {timeout: 6500});
        await admin.locator('.admin-reply-form button').click();
        await page.waitForFunction(() => Array.from(document.querySelectorAll('.is-admin')).some(node => node.textContent.includes('Я подключился')), null, {timeout: 8000});
        const botCount = await page.locator('.web-chat-message.is-bot').count();
        await send('<img src=x onerror="window.chatXss=1"> Ещё вопрос');
        assert.equal(await page.locator('.web-chat-message.is-bot').count(), botCount);
        assert.equal(await page.locator('.web-chat-message img').count(), 0);
        assert.equal(await page.evaluate(() => Boolean(window.chatXss)), false);
        pass('handoff, live admin typing, reply delivery, XSS and AI silence');
        await admin.screenshot({path: path.join(artifacts, 'admin-chat-desktop.png')});
        await admin.getByRole('button', {name: 'Вернуть ассистенту', exact: true}).click();
        await admin.waitForTimeout(400);
        data = await send('Лендинг Бизнес');
        assert.equal(data.status, 'bot');
        assert(data.messages.some(row => row.sender === 'bot'));
        await admin.getByRole('button', {name: 'Завершить диалог', exact: true}).click();
        await page.waitForFunction(() => document.querySelector('.web-chat-status').textContent.includes('завершён'));
        assert(await page.locator('.web-chat-form textarea').isDisabled());
        pass('return to assistant and closed conversation UI');
        await page.setViewportSize({width: 390, height: 844});
        await page.screenshot({path: path.join(artifacts, 'chat-mobile.png')});
        const geometry = await page.locator('.web-chat-panel').evaluate(panel => {
            const b = panel.getBoundingClientRect();
            return {fits: b.left >= 0 && b.right <= innerWidth && b.top >= 0 && b.bottom <= innerHeight,
                topmost: panel.contains(document.elementFromPoint(b.left + b.width / 2, b.top + 15))};
        });
        assert(geometry.fits && geometry.topmost);
        await admin.goto(base + '/admin/faq.php');
        assert.equal(await admin.locator('h1').innerText(), 'База знаний');
        assert.deepEqual(errors, []);
        pass('mobile chat framing, stacking, FAQ and zero JS errors');
        console.log('Total browser checks:', checks, 'Artifacts:', artifacts);
    } finally {
        await browser.close();
        fixture({action: 'cleanup', username: credentials.username, conversation_ids: [...conversations]});
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
