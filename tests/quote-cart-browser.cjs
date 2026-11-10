// No forms are submitted. Chat/analytics endpoints are mocked.
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const vm = require('node:vm');
(async () => {
    const browser = await chromium.launch({headless:true, channel:'msedge'});
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('**/api/chat.php', route => route.fulfill({json:{status:'bot',messages:[],choices:[]}}));
        await page.route('**/api/analytics-consent.php', route => route.fulfill({json:{state:'rejected',consent:false}}));
        await page.addInitScript(() => localStorage.setItem('webstartCart', JSON.stringify([
            {tariff_id:'1',service:'Synthetic service',tariff:'Synthetic option',price:999999},
        ])));
        for (const width of [1440,390,320]) {
            await page.setViewportSize({width,height:900});
            for (const file of ['tariffs.php','cart.php','create-order.php']) {
                await page.goto('http://127.0.0.1:8000/' + file);
                for (const source of await page.locator('script:not([src])').allTextContents()) {
                    if (source.trim()) new vm.Script(source);
                }
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth+1), file+' overflow');
                const container = file==='tariffs.php'?'#cart':file==='cart.php'?'main.cart-page':'#orderItems';
                const text = await page.locator(container).innerText();
                assert(!text.includes('₽'), file+' still displays a price');
                if (file==='tariffs.php') assert.match(await page.locator('#landing .landing-offer__price').innerText(), /₽/);
                if (file==='create-order.php') {
                    const payload=JSON.parse(await page.locator('#cartJson').inputValue());
                    assert.deepEqual(payload,[{tariff_id:'1'}]);
                }
            }
            console.log('PASS: no prices in cart/checkout, ID-only payload, layout '+width);
        }
        assert.deepEqual(errors,[]);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode=1; });
