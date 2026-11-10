const assert = require('node:assert/strict');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
(async () => {
  assert.equal(process.env.VEGA_NOTIFICATION_TEST_URL, 'http://127.0.0.1:8121');
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROMIUM_EXECUTABLE});
  try {
    const page = await browser.newPage({viewport: {width: 375, height: 812}});
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(process.env.VEGA_NOTIFICATION_TEST_URL + '/home.php');
    await page.locator('#customer_name').fill('Тест формы');
    await page.locator('#customer_phone').fill('+79990001122');
    await page.locator('#home_personal_data_consent').check();
    const state = () => page.evaluate(() => {
      const select = document.getElementById('project_type');
      const comment = document.getElementById('project_theme');
      return {valid: select.form.checkValidity(), service: select.value,
        serviceRequired: select.required, commentRequired: comment.required};
    });
    assert.equal((await state()).valid, false);
    let submissions = 0;
    page.on('request', request => {if (request.method() === 'POST' && request.url().endsWith('/home.php')) submissions++;});
    await page.locator('.contact-submit').click();
    assert.equal(submissions, 0, 'Empty details must be stopped by browser validation');
    await page.locator('#project_theme').fill('Нужно автоматизировать отчёты');
    assert.deepEqual(await state(), {valid: true, service: '', serviceRequired: false, commentRequired: true});
    await page.locator('#project_theme').fill('   ');
    assert.equal((await state()).valid, false);
    await page.locator('#project_theme').fill('');
    for (const link of await page.locator('[data-lead-service="automation"]').all()) {
      await link.click();
      assert.deepEqual(await state(), {valid: true, service: 'automation', serviceRequired: true, commentRequired: false});
      assert.match(await page.locator('#service-select-trigger').innerText(), /Автоматизация бизнеса/);
    }
    await page.locator('#service-select-trigger').click();
    await page.getByRole('option', {name: 'Автоматизация бизнеса', exact: true}).press('Home');
    await page.getByRole('option', {name: 'Не выбирать услугу', exact: true}).press('Enter');
    assert.equal((await state()).valid, false);
    assert.equal((await state()).service, '');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    assert.deepEqual(errors, []);
    console.log('PASS inquiry form accepts service OR comment, all automation links preselect it, and selection can be cleared');
  } finally {await browser.close();}
})().catch(error => {console.error(error); process.exitCode = 1;});
