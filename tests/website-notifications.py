"""Exercise actual HTTP entry points in a disposable DB, with all HTTPS intercepted."""
import concurrent.futures
import copy
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ['PHP_BINARY']
DB = 'vega_chat_test_' + secrets.token_hex(6)
processes = []
folder = Path(tempfile.mkdtemp(prefix='vega-notification-test-'))
checks = 0
created = False

def check(condition, label):
    global checks
    if not condition:
        raise AssertionError(label)
    checks += 1
    print('PASS', label, flush=True)

def command(*args):
    p = subprocess.run([PHP, *args], cwd=ROOT, capture_output=True, text=True)
    if p.returncode:
        raise RuntimeError(p.stdout + p.stderr)
    return p.stdout

class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), urllib.request.ProxyHandler({}))
        self.csrf = ''
    def call(self, path, data=None, port=8121):
        body = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            with self.opener.open(urllib.request.Request('http://127.0.0.1:'+str(port)+path, body), timeout=20) as r:
                return r.status, r.read().decode()
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode()
    def chat(self, action, **data):
        code, raw = self.call('/api/chat.php', dict(action=action, csrf_token=self.csrf, after_id=0, **data))
        result = json.loads(raw)
        self.csrf = result.get('csrf_token', self.csrf)
        return code, result

try:
    command('tests/concurrent-chat-fixture.php', 'create', DB)
    created = True
    isolation = subprocess.run(
        [PHP, '-r', "require 'bd.php'; echo (string) $pdo->query('SELECT DATABASE()')->fetchColumn();"],
        cwd=ROOT, env=dict(os.environ, DB_NAME=DB), capture_output=True, text=True,
    )
    if isolation.returncode or isolation.stdout.strip() != DB:
        raise RuntimeError('Disposable database isolation unavailable; HTTP tests aborted before migrations, fixtures or requests.')
    migration = subprocess.run([PHP, 'migrate.php'], cwd=ROOT, env=dict(os.environ, DB_NAME=DB), capture_output=True, text=True)
    if migration.returncode:
        raise RuntimeError('Disposable database migration failed: ' + migration.stdout + migration.stderr)
    command('tests/notifications-fixture.php', DB)
    log_path = folder/'outgoing.jsonl'
    router = folder/'router.php'
    router.write_text("""<?php
require '"""+ROOT.as_posix()+"""/app.php';
putenv('TELEGRAM_BOT_TOKEN=test-token');
putenv('TELEGRAM_SUPPORT_CHAT_ID=-1001234567890');
putenv('AI_PROVIDER=openai-compatible');
putenv('AI_API_KEY=test-external-key');
putenv('OPENAI_API_KEY=test-external-key');
class TestHttps {
 public $context;
 private string $body='{"ok":true,"result":{"message_id":1}}';
 private int $position=0;
 public function stream_open($path,$mode,$options,&$opened): bool {
  if (isset($_GET['simulate_delivery_failure'])) $this->body='{"ok":false}';
  $opts=stream_context_get_options($this->context);
  file_put_contents('"""+log_path.as_posix()+"""',json_encode(['url'=>$path,'body'=>$opts['http']['content']??''])."\n",FILE_APPEND|LOCK_EX);
  return true;
 }
 public function stream_read($count): string {$s=substr($this->body,$this->position,$count);$this->position+=strlen($s);return $s;}
 public function stream_eof(): bool {return $this->position>=strlen($this->body);}
 public function stream_stat(): array {return [];}
}
stream_wrapper_unregister('https');
stream_wrapper_register('https',TestHttps::class);
return require '"""+ROOT.as_posix()+"""/router.php';
""", encoding='utf-8')
    env=dict(os.environ, DB_NAME=DB)
    for port in [8121,8122]:
        log=open(folder/('server-'+str(port)+'.log'),'w')
        p=subprocess.Popen([PHP,'-S','127.0.0.1:'+str(port),'-t',str(ROOT),str(router)],cwd=ROOT,env=env,stdout=log,stderr=log)
        processes.append((p,log))
    visitor=Client()
    for _ in range(40):
        try:
            visitor.call('/privacy.php')
            break
        except OSError: time.sleep(.1)
    code,state=visitor.chat('start')
    check(code==200 and state['choices'], 'menu available with external AI credentials configured')
    conversation=state['conversation_id']
    check(visitor.chat('message', message='Иван +79991234567')[0] in (409, 422), 'free text rejected before specialist request')
    def request_specialist(port):
        code,raw=visitor.call('/api/chat.php',dict(action='message',csrf_token=visitor.csrf,choice='human',after_id=0),port=port)
        return code,json.loads(raw)
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        duplicates=list(pool.map(request_specialist,[8121,8122]))
    check(all(code==200 for code,_ in duplicates),'parallel specialist clicks are idempotent')
    code,state=duplicates[0]
    check(code==200 and state['status']=='waiting_human','specialist request opens website waiting state')
    check(visitor.chat('message',choice='human')[0]==200,'repeat specialist button is idempotent')
    private='Иван Тестовый +79991234567 PRIVATE-VISITOR-MARKER'
    code,state=visitor.chat('message',message=private)
    check(code==200 and any(m['message']==private for m in state['messages']),'name and phone stored and returned from website DB')
    outsider=Client()
    check(outsider.call('/admin/chats.php')[1].find('name="password"')>=0,'general chats link requires login')
    check(outsider.call('/admin/chat-api.php',dict(action='list'))[0]==401,'unauthenticated admin API blocked')
    admin=Client()
    _,login=admin.call('/admin/login.php')
    token=re.search(r'name="csrf_token" value="([^"]+)"',login).group(1)
    admin.call('/admin/login.php',dict(username='test-notifications',password='DisposableTestPassword!',csrf_token=token))
    _,overview=admin.call('/admin/chats.php')
    check('Новых сообщений:' in overview and 'Ожидает администратора' in overview,'waiting conversations and unread messages shown in admin')
    _,thread=admin.call('/admin/chats.php?id='+str(conversation))
    check(private in thread,'visitor name and phone visible to authenticated administrator')
    csrf=re.search(r'data-admin-chat-csrf="([^"]+)"',thread).group(1)
    reply='Ответ специалиста PRIVATE-OPERATOR-MARKER'
    code,raw=admin.call('/admin/chat-api.php',dict(action='reply',value=reply,chat_id=conversation,csrf_token=csrf))
    check(code==200,'operator replies through website administration')
    code,state=visitor.chat('poll')
    check(any(m['message']==reply for m in state['messages']),'operator reply reaches correct website visitor')
    for path in ['/telegram-webhook.php','/telegram-poll.php','/telegram-worker.php']:
        code,_=outsider.call(path,dict(message=private))
        check(code in [403,404,410],'incoming disabled '+path)
    # Repeated cancel/request cannot create a stream of notifications.
    client=Client();client.chat('start');client.chat('message',choice='human')
    client.chat('cancel_waiting');client.chat('message',choice='human')
    # Trigger both real website order entry points, with only test credentials/data.
    lead=Client();_,home=lead.call('/home.php')
    leadcsrf=re.search(r'name="csrf_token" value="([^"]+)"',home).group(1)
    request=re.search(r'name="request_token" value="([^"]+)"',home).group(1)
    service_id=re.search(r'<option\s+value="([1-9][0-9]*)"',home).group(1)
    lead_payload=dict(csrf_token=leadcsrf,request_token=request,customer_name='Иван Тестовый',
        customer_phone='+79991234567',project_type=service_id,project_theme='PRIVATE-ORDER-MARKER',personal_data_consent='1')
    check('name="contact_website"' in home, 'lead form includes anti-spam trap')
    trap_payload=dict(lead_payload, contact_website='0')
    trap_code,_=lead.call('/home.php',trap_payload)
    check(trap_code==422 and command('tests/order-dedupe-fixture.php',DB,request).strip() == '0', 'filled lead trap creates no order')
    trap_array=dict(lead_payload, **{'contact_website[]':'spam'})
    check(lead.call('/home.php',trap_array)[0]==422, 'array-valued lead trap is rejected')
    malformed=dict(lead_payload, **{'customer_name[]':'test'})
    malformed.pop('customer_name')
    malformed_code,_=lead.call('/home.php',malformed)
    check(malformed_code != 500, 'array-valued lead input does not crash')
    invalid_consent=dict(lead_payload, personal_data_consent='0')
    _,consent_body=lead.call('/home.php',invalid_consent)
    check('Подтвердите согласие' in consent_body and command('tests/order-dedupe-fixture.php',DB,request).strip() == '0', 'invalid consent is rejected without saving')
    array_consent=dict(lead_payload, **{'personal_data_consent[]':'1'})
    array_consent.pop('personal_data_consent')
    _,array_body=lead.call('/home.php',array_consent)
    check('Подтвердите согласие' in array_body and command('tests/order-dedupe-fixture.php',DB,request).strip() == '0', 'array consent is rejected without saving')
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        lead_results=list(pool.map(lambda port: lead.call('/home.php',lead_payload,port=port), [8121,8122]))
    check(any(code==200 and 'Заявка №' in result for code,result in lead_results), 'one lead request succeeds')
    check(command('tests/order-dedupe-fixture.php',DB,request).strip() == '1', 'simultaneous lead requests create exactly one order')
    cart_client=Client()
    _,checkout=cart_client.call('/create-order.php')
    cartcsrf=re.search(r'name="csrf_token" value="([^"]+)"',checkout).group(1)
    cartrequest=re.search(r'name="request_token" value="([^"]+)"',checkout).group(1)
    catalog_client=Client()
    _,catalog_state=catalog_client.chat('start')
    service_choice=next(item['id'] for item in catalog_state['choices'] if item['id'].startswith('service:'))
    _,catalog_state=catalog_client.chat('message',choice=service_choice)
    tariff=next(item['id'].split(':')[1] for item in catalog_state['choices'] if item['id'].startswith('tariff:'))
    cart_payload=dict(csrf_token=cartcsrf,request_token=cartrequest,
        customer_name='Иван Тестовый',customer_phone='+79991234567',project_comment='PRIVATE-CART-MARKER',
        personal_data_consent='1',cart_json=json.dumps([{'tariff_id':int(tariff)}]))
    check('name="contact_website"' in checkout, 'checkout form includes anti-spam trap')
    trap_cart=dict(cart_payload,contact_website='https://spam.invalid')
    trap_code,_=cart_client.call('/create-order.php',trap_cart)
    check(trap_code==422 and command('tests/order-dedupe-fixture.php',DB,cartrequest).strip() == '0', 'filled cart trap creates no order')
    malformed_cart=dict(cart_payload, **{'cart_json[]':'invalid'})
    malformed_cart.pop('cart_json')
    malformed_code,malformed_body=cart_client.call('/create-order.php',malformed_cart)
    check(malformed_code != 500 and 'Warning' not in malformed_body and command('tests/order-dedupe-fixture.php',DB,cartrequest).strip() == '0', 'array cart input is rejected without PHP warnings')
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        cart_results=list(pool.map(lambda port: cart_client.call('/create-order.php',cart_payload,port=port), [8121,8122]))
    check(any(code==200 and 'Заявка №' in result for code,result in cart_results), 'one cart request succeeds')
    check(command('tests/order-dedupe-fixture.php',DB,cartrequest).strip() == '1', 'simultaneous cart requests create exactly one order')
    failed_delivery=Client()
    _,failure_form=failed_delivery.call('/home.php')
    failedcsrf=re.search(r'name="csrf_token" value="([^"]+)"',failure_form).group(1)
    failedrequest=re.search(r'name="request_token" value="([^"]+)"',failure_form).group(1)
    code,failure_result=failed_delivery.call('/home.php?simulate_delivery_failure=1',dict(csrf_token=failedcsrf,
        request_token=failedrequest,customer_name='Тест Сбой',customer_phone='+79991234567',project_type=service_id,
        project_theme='PRIVATE-FAILURE-MARKER',personal_data_consent='1'))
    check(code==200 and 'Не удалось сохранить' not in failure_result,
          'notification delivery failure does not undo a saved lead')
    events=[json.loads(line) for line in log_path.read_text().splitlines()]
    check(all('api.telegram.org/' in e['url'] and e['url'].endswith('/sendMessage') for e in events),'no conversation requests to any external AI')
    payloads=[urllib.parse.parse_qs(e['body']) for e in events]
    check(len([p for p in payloads if p.get('text')==['На сайте посетитель ожидает специалиста.']])==2,'one notification per requested conversation despite repeated calls')
    check(any(p.get('text')==['На сайте поступила новая заявка.'] for p in payloads),'new lead sends generic order notification')
    for payload in payloads:
        encoded=json.dumps(payload,ensure_ascii=False)
        check(not any(marker in encoded for marker in ['Иван','79991234567','PRIVATE-','callback_data','?id=']),
              'outgoing payload contains no identity, transcript or dialog controls')
        button=json.loads(payload['reply_markup'][0])['inline_keyboard'][0][0]
        check(button in [
            dict(text='Открыть чаты',url='https://vegastud1o.ru/admin/chats.php'),
            dict(text='Открыть заявки',url='https://vegastud1o.ru/admin/orders.php')],
            'notification button links only to general protected admin page')
    check(len([p for p in payloads if p.get('text')==['На сайте поступила новая заявка.']])==3,
          'each new lead/cart has one generic notification attempt including delivery failure')
    mobile_env=dict(os.environ,VEGA_NOTIFICATION_TEST_URL='http://127.0.0.1:8121',
                    VEGA_NOTIFICATION_TEST_SCREENSHOT=str(folder/'mobile-admin.png'))
    mobile=subprocess.run(['node',str(ROOT/'tests/admin-chat-mobile.cjs')],cwd=ROOT,env=mobile_env,capture_output=True,text=True,encoding='utf-8')
    print(mobile.stdout,flush=True)
    if mobile.returncode: raise RuntimeError(mobile.stderr)
    lead_ui=subprocess.run(['node',str(ROOT/'tests/lead-form.cjs')],cwd=ROOT,env=mobile_env,capture_output=True,text=True,encoding='utf-8')
    print(lead_ui.stdout,flush=True)
    if lead_ui.returncode: raise RuntimeError(lead_ui.stderr)
    # New lead paths use the same isolated DB and intercepted HTTPS as all other checks.
    notification_offset=len(log_path.read_text().splitlines())
    command('tests/security-rate-fixture.php',DB,'order','unused')
    for selected,comment,label in [
        ('automation','','Автоматизация бизнеса'),
        ('','COMMENT-ONLY-LEAD','Без выбранной услуги'),
        (service_id,'',None),
    ]:
        applicant=Client()
        _,form=applicant.call('/home.php')
        csrf_token=re.search(r'name="csrf_token" value="([^"]+)"',form).group(1)
        request_token=re.search(r'name="request_token" value="([^"]+)"',form).group(1)
        payload=dict(csrf_token=csrf_token,request_token=request_token,customer_name='Тест заявки',
                     customer_phone='+79990001122',personal_data_consent='1',project_type=selected,project_theme=comment)
        if selected=='':
            for invalid_type,invalid_text in [('', '   '),('arbitrary-service','COMMENT-ONLY-LEAD')]:
                _,invalid_body=applicant.call('/home.php',dict(payload,project_type=invalid_type,project_theme=invalid_text))
                check('Заявка №' not in invalid_body and command('tests/order-dedupe-fixture.php',DB,request_token).strip()=='0',
                      'empty details or unknown service are rejected on server')
        code,result=applicant.call('/home.php',payload)
        match=re.search(r'Заявка №\s*(\d+)',result)
        check(code==200 and match is not None,'lead saved with '+(selected or 'comment only'))
        _,stored=admin.call('/admin/order.php?id='+match.group(1))
        check((not label or label in stored) and (not comment or comment in stored),'lead details are visible in administrator view')
    extra_outgoing=[json.loads(line) for line in log_path.read_text().splitlines()][notification_offset:]
    check(len(extra_outgoing)==3 and all(urllib.parse.parse_qs(entry['body']).get('text')==['На сайте поступила новая заявка.'] for entry in extra_outgoing),
          'new lead paths send only three generic order notifications')
    # Synthetic malicious content remains text, and cannot grant extra privileges.
    xss_payload='<img src=x onerror=alert(1)>'
    xss_client=Client(); _,xss_state=xss_client.chat('start'); xss_id=xss_state['conversation_id']
    xss_client.chat('message',choice='human')
    code,_=xss_client.chat('message',message=xss_payload,role='admin')
    _,xss_thread=admin.call('/admin/chats.php?id='+str(xss_id))
    check(code==200 and '&lt;img src=x onerror=alert(1)&gt;' in xss_thread and xss_payload not in xss_thread, 'visitor markup is escaped in admin transcript')
    check(xss_client.call('/admin/chat-api.php',dict(action='list'))[0]==401, 'extra role field does not grant admin access')
    # A GET or invalid CSRF must not log out a valid admin; old session cannot be replayed.
    _,logout_page=admin.call('/admin/chats.php')
    logout_token=re.search(r'<form method="post" action="/admin/logout.php".*?name="csrf_token" value="([^"]+)"',logout_page,re.S).group(1)
    saved_cookies=[copy.copy(cookie) for cookie in admin.jar]
    check(admin.call('/admin/logout.php')[0]==405, 'logout rejects GET')
    check(admin.call('/admin/logout.php',dict(csrf_token='invalid'))[0]==403, 'logout rejects invalid CSRF')
    admin.call('/admin/logout.php',dict(csrf_token=logout_token))
    for cookie in saved_cookies: admin.jar.set_cookie(cookie)
    _,replayed=admin.call('/admin/chats.php')
    check('name="password"' in replayed, 'destroyed admin session cannot be replayed')
    # Login SQL syntax is handled as a username, not executable SQL.
    injection_client=Client(); _,login_html=injection_client.call('/admin/login.php')
    injection_csrf=re.search(r'name="csrf_token" value="([^"]+)"',login_html).group(1)
    injection_client.call('/admin/login.php',dict(username="' OR 1=1 -- ",password='not-valid',csrf_token=injection_csrf))
    check(injection_client.call('/admin/chat-api.php',dict(action='list'))[0]==401, 'SQL-like login does not bypass authentication')
    # Exercise rate limits only on the disposable database/server.
    limited=Client()
    code,poll_state=limited.chat('start')
    check(code==200, 'chat starts before rate-limit exercise')
    command('tests/security-rate-fixture.php',DB,'poll',str(poll_state['conversation_id']))
    normal_code,_=limited.chat('poll')
    blocked_code,_=limited.chat('poll')
    check(normal_code==200 and blocked_code==429, 'excessive chat polling receives 429')
    login_client=Client()
    _,login_page=login_client.call('/admin/login.php')
    login_csrf=re.search(r'name="csrf_token" value="([^"]+)"',login_page).group(1)
    command('tests/security-rate-fixture.php',DB,'login','unused')
    login_code,login_body=login_client.call('/admin/login.php',dict(csrf_token=login_csrf,username='unlisted-test-user',password='invalid-test-password'))
    next_csrf=re.search(r'name="csrf_token" value="([^"]+)"',login_body).group(1)
    blocked_login,_=login_client.call('/admin/login.php',dict(csrf_token=next_csrf,username='unlisted-test-user',password='invalid-test-password'))
    check(login_code==200 and blocked_login==429, 'excessive login attempts receive 429')
    command('tests/security-rate-fixture.php',DB,'control','unused')
    normal_start,_=limited.chat('start')
    blocked_start,_=limited.chat('start')
    check(normal_start==200 and blocked_start==429, 'repeated chat starts receive 429')
    print('RESULT',checks,'checks passed. No real Telegram or AI requests.',flush=True)
finally:
    for p,log in processes:
        p.terminate();p.wait(timeout=10);log.close()
    if created: print(command('tests/concurrent-chat-fixture.php','drop',DB).strip())
    if folder.parent==Path(tempfile.gettempdir()).resolve() and folder.name.startswith('vega-notification-test-'):
        for item in folder.iterdir():
            if item.is_file(): item.unlink()
        folder.rmdir()
