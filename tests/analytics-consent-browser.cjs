// All pages/API responses are synthetic. Yandex requests are intercepted locally.
// No production analytics, consent journal writes or visitor records are used.
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const origin = 'https://vegastud1o.ru';
const receipt = 'a0000000-0000-4000-8000-000000000001';
const version = 'synthetic-consent';
const initial = () => ({choice:null,receipt:null,version,expiresAt:null,csrf:'synthetic',text:'Full consent terms for the synthetic test.'});

(async () => {
    const browser = await chromium.launch({headless:true,channel:'msedge'});
    try {
        async function fixture({state=initial(), local=null, fail=false, hold=false, width=1440, height=900, basePath='/'}={}) {
            const context = await browser.newContext({viewport:{width,height}});
            if (local) await context.addInitScript(value => localStorage.setItem('vegaAnalyticsConsent',JSON.stringify(value)), local);
            const page = await context.newPage();
            const external = [], errors = [];
            page.on('pageerror', error => errors.push(error.message));
            let release;
            const gate = hold ? new Promise(resolve => {release=resolve;}) : Promise.resolve();
            let postStarted = false;
            await page.route('**/*', async route => {
                const url = new URL(route.request().url());
                if (url.hostname !== 'vegastud1o.ru') {
                    external.push(url.href);
                    // Stub the script without loading any real third-party code.
                    return route.fulfill({contentType:'application/javascript',body:'window.__metricStubLoaded=true;'});
                }
                if (url.pathname === basePath+'api/analytics-consent.php') {
                    if (fail) return route.fulfill({status:503,json:{error:'Synthetic outage'}});
                    if (route.request().method() === 'POST') {
                        postStarted=true;
                        await gate;
                        const input=route.request().postDataJSON();
                        state={...state,choice:input.choice,receipt,expiresAt:Date.now()+86400000};
                    }
                    return route.fulfill({json:state});
                }
                if (url.pathname === basePath+'analytics-consent.js' || url.pathname === basePath+'analytics-consent.css') {
                    return route.fulfill({contentType:url.pathname.endsWith('.js')?'application/javascript':'text/css',body:fs.readFileSync(path.join(root,url.pathname.slice(basePath.length)),'utf8')});
                }
                return route.fulfill({contentType:'text/html',body:`<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="${basePath}analytics-consent.css"><style>body{margin:0}body>*:not(.stars-canvas){position:relative;z-index:auto!important}main{height:5000px}</style><script defer src="${basePath}analytics-consent.js"></script></head><body><main>Test content</main><footer>Footer</footer></body></html>`});
            });
            await page.goto(origin+basePath+'test');
            await page.locator('#analytics-preferences').waitFor({state:'attached'});
            return {page,context,external,errors,release,postStarted:()=>postStarted};
        }

        for (const width of [1440,390,320]) {
            const f=await fixture({width,height:850});
            await f.page.locator('[data-choice="accepted"]').waitFor({state:'visible'});
            await f.page.waitForFunction(()=>!document.querySelector('[data-choice="accepted"]').disabled);
            const before=await f.page.locator('#analytics-preferences').boundingBox();
            await f.page.evaluate(()=>window.scrollTo(0,4000));
            await f.page.waitForTimeout(150);
            const after=await f.page.locator('#analytics-preferences').boundingBox();
            assert.equal(await f.page.locator('#analytics-preferences').evaluate(e=>getComputedStyle(e).position),'fixed');
            assert.ok(Math.abs(before.y-after.y)<1);
            assert.ok(after.x>=0 && after.x+after.width<=width && after.y+after.height<=850);
            assert.ok(after.height < (width>900?140:245), 'compact banner '+JSON.stringify({width,after}));
            assert.deepEqual(f.external, [], 'idle and scrolling never contact Yandex');
            await f.page.locator('[data-choice="rejected"]').click();
            await f.page.locator('#analytics-preferences').waitFor({state:'hidden'});
            assert.deepEqual(f.external, [], 'reject never contacts Yandex');
            assert.equal(await f.page.locator('#analytics-settings').evaluate(e=>getComputedStyle(e).position),'fixed');
            await f.page.locator('#analytics-settings').click();
            assert.deepEqual(f.external, [], 'reopening preferences does not activate analytics');
            assert.deepEqual(f.errors, []);
            await f.context.close();
            console.log('PASS fixed compact banner, idle/scroll/reject/reopen: '+width+'px');
        }

        for (const options of [
            {local:{choice:'accepted',receipt,version}},
            {fail:true,local:{choice:'accepted',receipt,version}},
            {state:{...initial(),choice:'accepted',receipt,expiresAt:Date.now()-1000},local:{choice:'accepted',receipt,version}},
        ]) {
            const f=await fixture(options);
            await f.page.waitForTimeout(250);
            assert.deepEqual(f.external, [], 'local forgery, outage or expired consent must fail closed');
            await f.context.close();
        }
        console.log('PASS forged local consent, API outage and expiry stay disabled');

        const nested=await fixture({basePath:'/vega/'});
        await nested.page.waitForFunction(()=>!document.querySelector('[data-choice="accepted"]').disabled);
        assert.equal(await nested.page.locator('#analytics-preferences').evaluate(e=>getComputedStyle(e).position),'fixed');
        assert.equal(await nested.page.locator('a[href*="privacy.php"]').getAttribute('href'),origin+'/vega/privacy.php#analytics-cookies');
        await nested.page.locator('[data-choice="rejected"]').click();
        await nested.page.locator('#analytics-preferences').waitFor({state:'hidden'});
        assert.deepEqual(nested.external,[]);
        assert.deepEqual(nested.errors,[]);
        await nested.context.close();
        console.log('PASS stylesheet, preferences API and privacy link work from a project subdirectory');

        const f=await fixture({hold:true});
        await f.page.waitForFunction(()=>!document.querySelector('[data-choice="accepted"]').disabled);
        await f.page.locator('[data-choice="accepted"]').click();
        await f.page.waitForTimeout(150);
        assert.equal(f.postStarted(), true);
        assert.deepEqual(f.external, [], 'click alone must not start collection before server acknowledgement');
        f.release();
        await f.page.waitForFunction(()=>window.__metricStubLoaded===true);
        assert.equal(f.external.length,1);
        assert.match(f.external[0], /^https:\/\/mc\.yandex\.ru\/metrika\/tag\.js\?id=114096795$/);
        assert.deepEqual(f.errors, []);
        await f.context.close();
        console.log('PASS only acknowledged explicit consent starts the intercepted counter');

        const prior=await fixture({state:{...initial(),choice:'accepted',receipt,expiresAt:Date.now()+86400000},local:{choice:'accepted',receipt,version}});
        await prior.page.waitForFunction(()=>window.__metricStubLoaded===true);
        const requestCount=prior.external.length;
        await prior.page.evaluate(()=>{
            localStorage.setItem('vegaAnalyticsConsent',JSON.stringify({choice:'rejected'}));
            dispatchEvent(new StorageEvent('storage',{key:'vegaAnalyticsConsent'}));
        });
        await prior.page.waitForTimeout(200);
        assert.equal(await prior.page.evaluate(()=>window.disableYaCounter114096795),true);
        assert.equal(await prior.page.locator('#vega-metrica-script').count(),0);
        assert.equal(prior.external.length,requestCount);
        await prior.context.close();
        console.log('PASS valid prior consent respected; cross-tab rejection stops the counter');
    } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
