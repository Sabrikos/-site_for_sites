// Local browser check with a mocked chat API. Does not create conversations or send messages.
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const base = process.env.VEGA_TEST_URL || 'http://127.0.0.1:8000';
(async () => {
  const browser = await chromium.launch({headless:true, channel:'msedge'});
  try {
    for (const [reducedMotion, delayedAnswer] of [['no-preference', false], ['reduce', false], ['no-preference', true]]) {
      const page = await browser.newPage({viewport:{width:390,height:844},reducedMotion});
      if (delayedAnswer) await page.addInitScript(() => {
        const original = window.setInterval;
        window.setInterval = (fn, ms, ...args) => original(fn, ms === 2500 ? 100 : ms, ...args);
      });
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      const history = Array.from({length:16}, (_,i) => ({id:i+1,sender:'bot',message:'Предыдущее сообщение. '.repeat(8)}));
      let answerPending = false;
      const answer = {id:18,sender:'bot',message:'Новый ответ. '.repeat(180)};
      await page.route('**/api/chat.php', route => {
        const action = new URLSearchParams(route.request().postData()).get('action');
        let messages = [];
        if (action === 'start') messages = history;
        if (action === 'message') {
          messages = [{id:17,sender:'user',message:'Интернет-магазин'}];
          if (delayedAnswer) answerPending = true;
          else messages.push(answer);
        } else if (action === 'poll' && answerPending) {
          messages = [answer];
          answerPending = false;
        }
        return route.fulfill({json:{status:'bot',csrf_token:'synthetic',
          choices:[{id:'service:2',label:'Интернет-магазин'}],
          messages,
        }});
      });
      await page.goto(base,{waitUntil:'domcontentloaded'});
      await page.locator('.web-chat-toggle').click();
      await page.locator('.web-chat-choices button').waitFor();
      await page.waitForTimeout(150);
      await page.locator('.web-chat-messages').evaluate(element => {element.scrollTop=0;});
      const pageY = await page.evaluate(() => scrollY);
      await page.locator('.web-chat-choices button').click();
      await page.locator('[data-message-id="18"]').waitFor();
      if (reducedMotion === 'no-preference') {
        await page.waitForTimeout(80);
        const during = await page.locator('.web-chat-messages').evaluate(element => ({top:element.scrollTop,bottom:element.scrollHeight-element.clientHeight}));
        assert.ok(during.top > 0 && during.top < during.bottom, 'scroll travels gradually rather than jumping');
      }
      await page.waitForFunction(() => {
        const log=document.querySelector('.web-chat-messages');
        return document.querySelector('[data-message-id="18"]').textContent.length > 1500 && log.scrollHeight-log.scrollTop-log.clientHeight < 2;
      });
      assert.equal(await page.evaluate(() => scrollY),pageY,'page outside chat is not scrolled');
      const log=page.locator('.web-chat-messages');
      await log.hover();
      await page.mouse.wheel(0,-400);
      await page.waitForTimeout(350);
      const reading = await log.evaluate(element=>element.scrollTop);
      await page.waitForTimeout(450);
      assert.ok(Math.abs(await log.evaluate(element=>element.scrollTop)-reading)<2,'manual history reading is not pulled down');
      assert.deepEqual(errors,[]);
      await page.close();
      console.log('PASS: new answer follows to bottom, manual reading respected, reducedMotion='+reducedMotion+', delayedAnswer='+delayedAnswer);
    }
  } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
