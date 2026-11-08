"""Exercise actual HTTP entry points in a disposable DB, with all HTTPS intercepted."""
import concurrent.futures
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
    code,result=lead.call('/home.php',dict(csrf_token=leadcsrf,request_token=request,customer_name='Иван Тестовый',
        customer_phone='+79991234567',project_type=service_id,project_theme='PRIVATE-ORDER-MARKER',personal_data_consent='1'))
    check(code==200 and 'Не удалось сохранить' not in result,'website lead remains saved with generic notification')
    cart_client=Client()
    _,checkout=cart_client.call('/create-order.php')
    cartcsrf=re.search(r'name="csrf_token" value="([^"]+)"',checkout).group(1)
    cartrequest=re.search(r'name="request_token" value="([^"]+)"',checkout).group(1)
    catalog_client=Client()
    _,catalog_state=catalog_client.chat('start')
    service_choice=next(item['id'] for item in catalog_state['choices'] if item['id'].startswith('service:'))
    _,catalog_state=catalog_client.chat('message',choice=service_choice)
    tariff=next(item['id'].split(':')[1] for item in catalog_state['choices'] if item['id'].startswith('tariff:'))
    catalog_prices = json.loads(re.search(r'window\.VEGA_TARIFFS = (.*?);</script>', checkout).group(1))
    current_price = next(x['price'] for x in catalog_prices if str(x['tariff_id']) == str(tariff))
    _,stale_result=cart_client.call('/create-order.php',dict(csrf_token=cartcsrf,request_token=cartrequest,
        customer_name='Иван Тестовый',customer_phone='+79991234567',project_comment='PRIVATE-CART-MARKER',
        personal_data_consent='1',cart_json=json.dumps([{'tariff_id':int(tariff), 'price':current_price+1}])))
    check('Стоимость изменилась' in stale_result,'stale cart price requires review before order')
    code,checkout_result=cart_client.call('/create-order.php',dict(csrf_token=cartcsrf,request_token=cartrequest,
        customer_name='Иван Тестовый',customer_phone='+79991234567',project_comment='PRIVATE-CART-MARKER',
        personal_data_consent='1',cart_json=json.dumps([{'tariff_id':int(tariff), 'price': current_price}])))
    check(code==200 and 'Не удалось сохранить заказ' not in checkout_result,'cart order is stored with generic notification')
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
    print('RESULT',checks,'checks passed. No real Telegram or AI requests.',flush=True)
finally:
    for p,log in processes:
        p.terminate();p.wait(timeout=10);log.close()
    if created: print(command('tests/concurrent-chat-fixture.php','drop',DB).strip())
    if folder.parent==Path(tempfile.gettempdir()).resolve() and folder.name.startswith('vega-notification-test-'):
        for item in folder.iterdir():
            if item.is_file(): item.unlink()
        folder.rmdir()
