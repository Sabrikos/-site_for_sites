const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
function widget(second=false){
  const events={},frames=new Map(),draws=[],clears=[];let observe,seq=0,now=0,image;
  const context={save(){},restore(){},beginPath(){},ellipse(){},clip(){},fillRect(){},translate(){},rotate(){},transform(){},createRadialGradient(){return {addColorStop(){}};},clearRect(...a){clears.push(a);},drawImage(...a){draws.push(a);}};
  const canvas={getContext:()=>context};
  const root={dataset:{},querySelector:()=>canvas,classList:{add(){}},getBoundingClientRect:()=>({left:100,top:100,width:300,height:400})};
  const document={hidden:false,documentElement:{addEventListener:(n,f)=>events['root:'+n]=f},addEventListener:(n,f)=>events[n]=f,createElement:()=>({getContext:()=>context})};
  const reduced={matches:false,addEventListener:(n,f)=>events.reduced=f};
  const env={window:{addEventListener:(n,f)=>events['window:'+n]=f},document,matchMedia:()=>reduced,Image:class{constructor(){image=this;}},IntersectionObserver:class{constructor(f){observe=f;}observe(){}},requestAnimationFrame:f=>(frames.set(++seq,f),seq),cancelAnimationFrame:id=>frames.delete(id)};
  env.window.IntersectionObserver=env.IntersectionObserver;
  vm.runInNewContext(fs.readFileSync(require.resolve('../contact-alien-live.js'),'utf8'),env);
  env.window.startVegaAlienLive(root,{videoPrefix:second?'tariffs':'home',wave:{src:'wave.webp',columns:10,frames:96,fps:24}});
  observe([{isIntersecting:true}]);image.onload();
  const w={root,events,document,reduced,frames,draws,clears,move:(x,y)=>events.pointermove({clientX:x,clientY:y,pointerType:'mouse'}),tick(t){now=t;const pending=[...frames.values()];frames.clear();pending.forEach(f=>f(now));},advance(end){for(let t=now+16;t<=end;t+=16)w.tick(t);}};return w;
}
for(const second of [false,true])test(`continuous gaze tracks both axes and holds a stationary cursor: ${second?'tariffs':'home'}`,()=>{
  const w=widget(second);w.tick(0);w.move(0,0);w.advance(1600);
  assert.equal(w.root.dataset.animation,'tracking');assert.ok(+w.root.dataset.gazeX<-.3);assert.ok(+w.root.dataset.gazeY<-.3);
  w.advance(12000);assert.equal(w.root.dataset.animation,'tracking');
  const count=w.draws.length;w.move(0,700);w.tick(12016);
  assert.ok(+w.root.dataset.gazeY<.5,'motion must ease rather than jump to the next target');
  w.advance(14000);assert.ok(+w.root.dataset.gazeX<0);assert.ok(+w.root.dataset.gazeY>0);
  assert.ok(w.draws.slice(count).every(a=>a[0]!==undefined));
  w.events['root:pointerleave']();w.advance(16000);assert.equal(w.root.dataset.animation,'idle');
});
test('reduced motion and hidden pages stop frame scheduling',()=>{
  const w=widget();w.tick(0);w.document.hidden=true;w.events.visibilitychange();assert.equal(w.frames.size,0);
  w.document.hidden=false;w.reduced.matches=true;w.events.reduced();w.tick(100);assert.equal(w.frames.size,0);assert.equal(w.root.dataset.animation,'idle');
});
test('home clears the watermark area in idle and tracking',()=>{
  const w=widget();w.tick(0);w.move(0,0);w.advance(1600);
  assert.deepEqual(w.clears.at(-1),[230,370,70,30]);
});
