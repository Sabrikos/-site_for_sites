const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');

function widget(reducedMotion=false) {
  const events={},callbacks=new Map(),draws=[];
  let id=0,clock=0;
  const context={clearRect(){},drawImage(...args){draws.push(args);}};
  const canvas={getContext:()=>context};
  const root={dataset:{},querySelector:()=>canvas,classList:{add(){}},
    getBoundingClientRect:()=>({left:100,top:100,width:300,height:400})};
  const document={hidden:false,documentElement:{clientWidth:1000,clientHeight:800,
    addEventListener:(k,f)=>events[k]=f},addEventListener:(k,f)=>events[k]=f};
  const media={matches:reducedMotion,addEventListener:(k,f)=>events.reduced=f};
  const clip=(id,role)=>({id,role,src:id+'.webp',fps:24,count:60,
    gaze:Array.from({length:60},(_,i)=>[Math.sin(i/59*Math.PI*2),Math.cos(i/59*Math.PI*2)]),
    poses:Array.from({length:60},(_,i)=>[Math.round(i/3),Math.round(i/3),Math.round(i/3)])});
  const data={width:300,height:400,columns:10,clips:[clip('rest','idle'),clip('gaze','tracking'),clip('legs','idle')]};
  for(const destination of data.clips) {
    destination.links={};
    for(const source of data.clips) if(source!==destination) {
      destination.links[source.id]=Array.from({length:60},(_,i)=>[i,0]);
    }
  }
  const window={VegaAlienNative:data,addEventListener:(k,f)=>events[k]=f};
  const images=[];
  class Image {set src(v){this.url=v;images.push(this);}}
  const env={window,document,Image,matchMedia:()=>media,
    requestAnimationFrame:f=>(callbacks.set(++id,f),id),
    cancelAnimationFrame:i=>callbacks.delete(i)};
  vm.runInNewContext(fs.readFileSync('contact-alien-cursor.js','utf8'),env);
  assert.equal(window.startVegaAlienCursor(root,{videoPrefix:'home'}),true);
  for(const image of images){image.done=true;image.onload();}
  function step(ms) {
    const end=clock+ms;
    while(clock<end) {
      clock+=16;
      const pending=[...callbacks.values()];callbacks.clear();
      pending.forEach(f=>f(clock));
      for(const image of images) if(!image.done){image.done=true;image.onload();}
    }
  }
  return {root,events,step,draws,callbacks,document,media};
}

test('whole idle video keeps playing including when no pupil frame is neutral',()=>{
  const w=widget();w.step(1000);const a=w.root.dataset.frame;
  w.step(700);assert.notEqual(w.root.dataset.frame,a);
  assert.equal(w.root.dataset.animation,'idle');
});
test('a stationary pointer returns to full idle after inactivity',()=>{
  const w=widget();w.events.pointermove({clientX:950,clientY:750,pointerType:'mouse'});
  w.step(500);assert.equal(w.root.dataset.animation,'tracking');
  w.step(4500);assert.equal(w.root.dataset.animation,'idle');
  const frame=w.root.dataset.frame;w.step(400);assert.notEqual(w.root.dataset.frame,frame);
});
test('idle sequences alternate through matched native poses',()=>{
  const w=widget();w.step(6500);
  assert.equal(w.root.dataset.clip,'legs');
});
test('hidden pages and reduced motion stop scheduling',()=>{
  const w=widget();w.document.hidden=true;w.events.visibilitychange();
  assert.equal(w.callbacks.size,0);
  const r=widget(true);r.step(100);
  assert.equal(r.callbacks.size,0);assert.equal(r.root.dataset.animation,'still');
});
