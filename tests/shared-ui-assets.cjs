// Read-only page checks. Chat and consent APIs are mocked; external requests are blocked.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const base = new URL(process.env.SITE_URL || 'http://127.0.0.1:8000/');

(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'msedge' });
    try {
        for (const file of ['home.php', 'tariffs.php']) {
            const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
            const page = await context.newPage();
            const errors = [], failedAssets = [];
            page.on('pageerror', error => errors.push(error.message));
            page.on('response', response => {
                if (/assets\/alien\/|contact-alien-idle\.js|analytics-consent\.(css|js)/.test(response.url()) && response.status() >= 400) {
                    failedAssets.push(response.status() + ' ' + new URL(response.url()).pathname);
                }
            });
            await page.route('**/*', route => {
                const url = new URL(route.request().url());
                if (url.origin !== base.origin) return route.abort();
                if (url.pathname.endsWith('/api/analytics-consent.php')) return route.fulfill({ json: {
                    choice: null, receipt: null, version: 'synthetic', expiresAt: null,
                    csrf: 'synthetic', text: 'Synthetic consent for a read-only UI check.',
                } });
                if (url.pathname.endsWith('/api/chat.php')) return route.fulfill({ json: {
                    status: 'bot', messages: [], choices: [], csrf_token: 'synthetic',
                } });
                return route.continue();
            });
            const response = await page.goto(new URL(file, base).href);
            assert.equal(response.status(), 200, file + ' HTTP status');
            const mascot = page.locator('[data-contact-alien]').first();
            await mascot.scrollIntoViewIfNeeded();
            await page.waitForFunction(() => {
                const root = document.querySelector('[data-contact-alien]');
                const img = root?.querySelector('img');
                return root?.classList.contains('is-ready') && img?.complete && img.naturalWidth > 0;
            });
            const box = await mascot.boundingBox();
            assert.ok(box && box.width > 100 && box.height > 100, file + ' visible mascot bounds');
            assert.equal(await mascot.locator('canvas').evaluate(el => el.hidden), false);
            const banner = page.locator('#analytics-preferences');
            await banner.waitFor({ state: 'visible' });
            assert.equal(await banner.evaluate(el => getComputedStyle(el).position), 'fixed');
            const before = await banner.boundingBox();
            await page.evaluate(() => scrollBy(0, -350));
            await page.waitForTimeout(100);
            const after = await banner.boundingBox();
            assert.ok(Math.abs(before.y - after.y) < 1, file + ' cookie banner stays at viewport bottom');
            assert.match(await page.locator('link[href*="analytics-consent.css"]').getAttribute('href'), /\?v=[a-f0-9]{12}$/);
            assert.deepEqual(failedAssets, [], file + ' resource failures');
            assert.deepEqual(errors, [], file + ' JavaScript errors');
            console.log('PASS ' + file + ': decoded mascot, running canvas, fixed cookie banner, versioned assets');
            await context.close();
        }
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
