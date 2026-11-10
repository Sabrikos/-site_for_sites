// Render the real admin template using CLI-local temporary tables and a
// non-persisted synthetic session. Browser requests never access real orders.
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const html = execFileSync(process.env.PHP_BINARY || 'php', ['-r', `
ob_start(); require 'tests/order-lifecycle-retention.php'; ob_end_clean();
$pdo->exec("INSERT INTO orders(customer_name,phone,total,status,last_contact_at,personal_data_expires_at) VALUES ('Synthetic administrator test','+70000000000',NULL,'discussion',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 6 MONTH))");
$_GET['id']=(int)$pdo->lastInsertId(); $_SERVER['REQUEST_METHOD']='GET';
appStartSession(); $_SESSION['admin_id']=1; $_SESSION['admin_last_activity']=time(); $_SESSION['admin_username']='Synthetic';
require 'admin/order.php'; session_abort();
`], {cwd:root,encoding:'utf8'});
assert.ok(!html.includes('Fatal error'));
assert.ok(html.includes('name="agreed_price"') && html.includes('value="save_price"'));
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'msedge'});
 try {
  for(const width of [1440,375,320]){
   const page=await browser.newPage({viewport:{width,height:900}});
   const errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.route('**/admin/order.php*',route=>route.fulfill({contentType:'text/html; charset=utf-8',body:html}));
   await page.goto('http://127.0.0.1:8000/admin/order.php?id=synthetic');
   assert.equal(await page.locator('#orderStatus option').count(),5);
   assert.equal(await page.locator('#orderStatus').inputValue(),'discussion');
   assert.equal(await page.locator('button[value="contact_today"]').count(),1);
   assert.equal(await page.locator('#linkedChatId').count(),1);
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'admin card fits '+width+'px');
   assert.deepEqual(errors,[]);
   await page.close();
  }
  console.log('PASS: actual admin order template fits desktop, 375px and 320px; five statuses, contact date and chat linking available. No real client records accessed.');
 }finally{await browser.close()}
})().catch(error=>{console.error(error);process.exitCode=1});
