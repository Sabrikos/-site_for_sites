"""Real parallel HTTP requests to three PHP processes, disposable MySQL only."""
import concurrent.futures, contextlib, http.cookiejar, json, os, pathlib, secrets, subprocess, tempfile, time, urllib.request, urllib.parse, urllib.error

ROOT=pathlib.Path(__file__).resolve().parents[1]
PHP=os.environ['PHP_BINARY']
DB='vega_chat_test_'+secrets.token_hex(6)
servers=[]
checks=0
def check(ok,label):
    global checks
    if not ok: raise AssertionError(label)
    checks+=1
    print('PASS:',label,flush=True)
def fixture(action):
    p=subprocess.run([PHP,str(ROOT/'tests/concurrent-chat-fixture.php'),action,DB],cwd=ROOT,capture_output=True,text=True)
    if p.returncode: raise RuntimeError('Disposable fixture failed: '+p.stdout+p.stderr)
    return p.stdout
class Client:
    def __init__(self,i):
        self.base='http://127.0.0.1:'+str(8111+i%3)
        self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),urllib.request.ProxyHandler({}))
        self.csrf=''
    def call(self,action,**kw):
        body=urllib.parse.urlencode(dict(action=action,csrf_token=self.csrf,after_id=0,**kw)).encode()
        try:
            with self.opener.open(urllib.request.Request(self.base+'/api/chat.php',body),timeout=15) as r: code=r.status; result=json.load(r)
        except urllib.error.HTTPError as e: code=e.code; result=json.load(e)
        if 'csrf_token' in result: self.csrf=result['csrf_token']
        return code,result
def parallel(fn):
    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool: return list(pool.map(fn,clients))
created=False
try:
    fixture('create');created=True
    with contextlib.nullcontext(tempfile.mkdtemp(prefix='vega-chat-concurrent-')) as temp:
        router=pathlib.Path(temp)/'router.php'
        # Load project configuration, then remove all external credentials before routing.
        router.write_text("<?php require '"+ROOT.as_posix()+"/app.php'; putenv('TELEGRAM_BOT_TOKEN='); putenv('AI_API_KEY='); putenv('OPENAI_API_KEY='); putenv('GIGACHAT_AUTH_KEY='); return require '"+ROOT.as_posix()+"/router.php';",encoding='utf-8')
        env=dict(os.environ,DB_NAME=DB,AI_PROVIDER='decision-tree')
        for port in (8111,8112,8113):
            log=open(pathlib.Path(temp)/('server-'+str(port)+'.log'),'w')
            servers.append((subprocess.Popen([PHP,'-S','127.0.0.1:'+str(port),'-t',str(ROOT),str(router)],cwd=ROOT,env=env,stdout=log,stderr=log),log))
        clients=[Client(i) for i in range(4)]
        for _ in range(30):
            try:
                urllib.request.urlopen(clients[0].base+'/privacy.php',timeout=1).close();break
            except OSError: time.sleep(.1)
        starts=parallel(lambda c:c.call('start'))
        check(all(code==200 for code,_ in starts),'four sessions start across three PHP processes')
        ids=[data['conversation_id'] for _,data in starts]
        check(len(set(ids))==4,'each visitor has a unique conversation')
        for i,c in enumerate(clients): c.id=ids[i]
        selected=[]
        for i,(_,s) in enumerate(starts):
            selected.append([x['id'] for x in s['choices'] if x['id'].startswith('service:')][i%len([x for x in s['choices'] if x['id'].startswith('service:')])])
        for c,choice in zip(clients,selected): c.choice=choice
        results=parallel(lambda c:c.call('message',choice=c.choice))
        check(all(code==200 for code,_ in results),'concurrent service selections succeed')
        for c,(_,s) in zip(clients,results): c.tariff=next(x['id'] for x in s['choices'] if x['id'].startswith('tariff:'))
        results=parallel(lambda c:c.call('message',choice=c.tariff))
        check(all(code==200 for code,_ in results),'concurrent tariff selections succeed')
        results=parallel(lambda c:c.call('message',choice='human'))
        check(all(code==200 and s['status']=='waiting_human' for code,s in results),'four independent human handoffs')
        for i,c in enumerate(clients): c.marker='private-test-visitor-'+str(i)+'-'+secrets.token_hex(4)
        results=parallel(lambda c:c.call('message',message=c.marker))
        check(all(code==200 for code,_ in results),'four human messages stored without external credentials')
        results=parallel(lambda c:c.call('poll',conversation_id=ids[(clients.index(c)+1)%4]))
        for i,(code,s) in enumerate(results):
            text='\n'.join(m['message'] for m in s['messages'])
            check(code==200 and s['conversation_id']==ids[i] and clients[i].marker in text and all(c.marker not in text for c in clients if c is not clients[i]),'visitor '+str(i+1)+' cannot read another history even with forged conversation_id')
        original=clients[0].csrf;clients[0].csrf=clients[1].csrf
        check(clients[0].call('poll')[0]==403,'foreign session CSRF rejected')
        clients[0].csrf=original
        # Two tabs sharing cookies race a start and a poll on two independent PHP processes.
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            shared=list(pool.map(lambda action:clients[0].call(action),['start','poll']))
        check(all(code==200 and s['conversation_id']==ids[0] for code,s in shared),'same-browser tabs preserve one conversation')
        print(fixture('verify'),flush=True)
        results=parallel(lambda c:c.call('poll'))
        for i,(code,s) in enumerate(results):
            replies=[m['message'] for m in s['messages'] if m['sender']=='admin']
            check(code==200 and s['status']=='human' and replies==['specialist-test-reply-'+str(ids[i])],'specialist reply delivered only to visitor '+str(i+1))
        print('RESULT:',checks,'checks passed; no real Telegram or LLM calls.',flush=True)
finally:
    for server,log in servers:
        server.terminate();server.wait(timeout=10);log.close()
    if created: print(fixture('drop'),flush=True)
    if 'temp' in globals():
        folder=pathlib.Path(temp).resolve()
        if folder.parent==pathlib.Path(tempfile.gettempdir()).resolve() and folder.name.startswith('vega-chat-concurrent-'):
            for f in folder.iterdir():
                if f.is_file(): f.unlink()
            folder.rmdir()
