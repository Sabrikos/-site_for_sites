const test=require('node:test'),assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs');
function scene(second=false) {
 const events={},frames=new Map();let image,id=0,now=0,observer;
 const draws=[],context={clearRect(){},drawImage(...args){draws.push(args);}};
 const canvas={hidden:true,getContext:()=>context};
 const root={dataset:{},querySelector:()=>canvas,classList:{add(){}}};
 const document={hidden:false,querySelectorAll:()=>[root],addEventListener:(n,f)=>events[n]=f};
 const media={matches:false,addEventListener:(n,f)=>events.reduced=f};
 const window={VegaAlienIdle:{videoPrefix:second?'tariffs':'home'},IntersectionObserver:true,
  addEventListener:(n,f)=>events[n]=f};
 const env={window,document,Image:class{constructor(){image=this;}},
  IntersectionObserver:class{constructor(f){observer=f;}observe(){}},
  matchMedia:()=>media,requestAnimationFrame:f=>(frames.set(++id,f),id),cancelAnimationFrame:i=>frames.delete(i)};
 vm.runInNewContext(fs.readFileSync('contact-alien-idle.js','utf8'),env);
 observer([{isIntersecting:true}]);image.onload();
 return {root,events,frames,document,media,draws,canvas,observer,
  step(ms){const end=now+ms;while(now<end){now+=16;const callbacks=[...frames.values()];frames.clear();callbacks.forEach(f=>f(now));}}};
}
for(const second of [false,true]) test('native idle continues independently of pointer '+second,()=>{
 const s=scene(second);s.step(800);const a=s.root.dataset.frame;s.step(800);
 assert.notEqual(s.root.dataset.frame,a);assert.equal(s.root.dataset.animation,'idle');
 assert.equal(s.events.pointermove,undefined);assert.equal(s.events.mousemove,undefined);
 s.step(25000);
 assert.ok(+s.root.dataset.frame>=0&&+s.root.dataset.frame<(second?241:190));
});
test('visibility and motion preferences pause and resume playback',()=>{
 const s=scene();s.step(800);s.document.hidden=true;s.events.visibilitychange();assert.equal(s.frames.size,0);
 s.document.hidden=false;s.events.visibilitychange();assert.ok(s.frames.size);
 s.observer([{isIntersecting:false}]);assert.equal(s.frames.size,0);
 s.observer([{isIntersecting:true}]);assert.ok(s.frames.size);
 s.media.matches=true;s.events.reduced();assert.equal(s.frames.size,0);
 assert.equal(s.root.dataset.frame,'0');assert.equal(s.root.dataset.animation,'still');
});
