// Run against a local PHP server. The chat API is mocked; no forms are submitted.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const base = process.env.VEGA_TEST_URL || 'http://127.0.0.1:8000';
const fixtures = {
  4: {tariff_id: '4', service: 'Интернет-магазин', tariff: 'Старт', price: 30000},
  13: {tariff_id: '13', service: 'Другое', tariff: 'Индивидуальный проект', price: 5000},
};
(async () => {
  const browser = await chromium.launch({headless: true, channel: 'msedge'});
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    let selected = 4;
    await page.route('**/api/chat.php', route => route.fulfill({json: {
      status: 'bot', csrf_token: 'synthetic',
      choices: [
        {id: 'root', label: 'Все услуги'}, {id: 'human', label: 'Позвать специалиста'},
        {id: `cart:${selected}`, label: 'Добавить в корзину', cart_item: fixtures[selected]},
      ],
      messages: [{id: 1, sender: 'bot', message: 'Выбранный тариф можно добавить в корзину.'}],
    }}));
    const ids = () => page.evaluate(() => window.VegaCart.read().map(item => item.tariff_id));
    async function openChat() {
      await page.locator('.web-chat-toggle').click();
      await page.locator('.is-cart-action').waitFor();
    }
    async function add() {
      await openChat();
      await page.locator('.is-cart-action').click();
      await page.waitForFunction(() => document.querySelector('.is-cart-action').disabled);
    }
    await page.goto(base);
    await add();
    assert.deepEqual(await ids(), ['4']);
    assert.equal(await page.locator('#cartCounter').textContent(), '1');
    assert.match(await page.locator('.is-cart-action').textContent(), /В корзине/);
    await page.evaluate(() => window.VegaCart.add({tariff_id:'4', service:'ignored', tariff:'ignored',price:1}));
    assert.deepEqual(await ids(), ['4']);

    selected = 13;
    await page.goto(base + '/tariffs.php');
    await add();
    assert.deepEqual(await ids(), ['4', '13']);
    assert.equal(await page.locator('.cart-remove[data-id="13"]').count(), 1);
    assert.equal(await page.locator('#other .tariff-button').getAttribute('data-id'), '13');
    await page.locator('.web-chat-close').click();
    await page.locator('.cart-remove[data-id="4"]').click();
    assert.deepEqual(await ids(), ['13']);

    selected = 4;
    await page.goto(base + '/cart.php');
    await add();
    assert.equal(await page.locator('#cartPageItems .cart-product').count(), 2);
    await page.locator('.web-chat-close').click();
    await page.locator('.cart-product-remove[data-id="13"]').click();
    assert.deepEqual(await ids(), ['4']);

    selected = 13;
    await page.goto(base + '/create-order.php');
    await page.locator('#customer_name').fill('Synthetic draft');
    await add();
    assert.equal(await page.locator('#orderItems .cart-product').count(), 2);
    assert.deepEqual(JSON.parse(await page.locator('#cartJson').inputValue()).map(item => item.tariff_id), ['4', '13']);
    assert.equal(await page.locator('#customer_name').inputValue(), 'Synthetic draft');
    assert.deepEqual(errors, []);

    const blocked = await browser.newPage();
    await blocked.addInitScript(() => {Storage.prototype.setItem = () => {throw new Error('blocked storage');};});
    await blocked.route('**/api/chat.php', route => route.fulfill({json: {
      status:'bot', csrf_token:'synthetic', messages:[],
      choices:[{id:'cart:4',label:'Добавить в корзину',cart_item:fixtures[4]}],
    }}));
    await blocked.goto(base);
    await blocked.locator('.web-chat-toggle').click();
    await blocked.locator('.is-cart-action').click();
    assert.equal(await blocked.locator('.web-chat-error').isVisible(), true);
    assert.equal(await blocked.locator('.is-cart-action').isDisabled(), false);
    console.log('PASS: chat adds correct tariffs, prevents duplicates, synchronizes tariffs/cart/checkout, preserves draft, reports storage failure. No orders submitted.');
  } finally { await browser.close(); }
})().catch(error => {console.error(error); process.exitCode = 1;});
