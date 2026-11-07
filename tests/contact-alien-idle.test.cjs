const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const path=require('node:path');

async function widget({fail='',tariffs=false,reducedMotion=false}={}) {
  let now=0,id=0,observe,draws=0;
  const frames=new Map(),listeners={},requested=[];
  const context={
    globalAlpha:1,
    globalCompositeOperation:'source-over',
    setTransform(){},
    clearRect(){},
    drawImage(){draws++;}
  };
  const canvas={width:300,height:400,hidden:true,getContext:()=>context};
  const root={
    dataset:{},
    querySelector:()=>canvas,
    classList:{add(){}},
    getBoundingClientRect:()=>({left:100,top:100,width:300,height:400})
  };
  const reduced={matches:reducedMotion,addEventListener:(n,f)=>listeners.reduced=f};
  const fine={matches:true,addEventListener:(n,f)=>listeners.fine=f};
  const document={
    hidden:false,
    querySelector:()=>root,
    documentElement:{addEventListener:(n,f)=>listeners[`document:${n}`]=f},
    addEventListener:(n,f)=>listeners[n]=f
  };
  const env={
    window:{addEventListener:(n,f)=>listeners[n]=f},
    document,
    matchMedia:query=>query.includes('reduced-motion')?reduced:fine,
    performance:{now:()=>now},
    Math:Object.assign(Object.create(Math),{random:()=>0}),
    Image:class{
      async decode(){
        requested.push(this.src);
        if(fail&&this.src.includes(fail))throw new Error('Unavailable');
      }
    },
    IntersectionObserver:class{constructor(f){observe=f;}observe(){}},
    requestAnimationFrame:f=>(frames.set(++id,f),id),
    cancelAnimationFrame:i=>frames.delete(i)
  };
  env.window.IntersectionObserver=env.IntersectionObserver;
  const manifest=tariffs
    ? '../assets/alien/tariffs/idle-manifest.js'
    : '../assets/alien/idle-manifest.js';
  vm.runInNewContext(fs.readFileSync(path.join(__dirname,manifest),'utf8'),env);
  vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../contact-alien.js'),'utf8'),env);
  observe([{isIntersecting:true}]);
  await new Promise(r=>setImmediate(r));
  const w={
    root,canvas,reduced,fine,document,listeners,frames,requested,
    get draws(){return draws;},
    frame(t){
      now=t;
      const callbacks=[...frames.values()];
      frames.clear();
      callbacks.forEach(f=>f(t));
    },
    async advance(start,end,step=20){
      for(let t=start;t<=end;t+=step){
        w.frame(t);
        await new Promise(r=>setImmediate(r));
      }
    },
    movePointer(x,y,pointerType='mouse'){
      listeners.pointermove({clientX:x,clientY:y,pointerType});
    }
  };
  return w;
}

test('homepage alien continuously loops its wave atlas over 5.2 seconds',async()=>{
  const w=await widget();
  assert.equal(w.canvas.hidden,false);
  assert.ok(w.requested.some(src=>src.includes('wave-camera.webp')));
  assert.ok(!w.requested.some(src=>src.includes('bridge.webp')));
  w.frame(0);
  assert.equal(w.root.dataset.animation,'waveLoop');
  assert.equal(w.root.dataset.frame,'0');
  await w.advance(20,5200)
  assert.equal(w.root.dataset.animation,'waveLoop');
  assert.equal(w.root.dataset.frame,'0');
  assert.ok(w.draws>0);
});

test('tariffs alien loops a clean 64-frame atlas at its native rate',async()=>{
  const w=await widget({tariffs:true});
  assert.equal(w.canvas.width,320);
  assert.equal(w.canvas.height,326);
  assert.ok(w.requested.some(src=>src.includes('wave-gaze.webp')));
  assert.ok(!w.requested.some(src=>src.includes('wave-bridge.webp')));
  w.frame(0);
  assert.equal(w.root.dataset.animation,'waveLoop');
  await w.advance(20,2700)
  assert.equal(w.root.dataset.frame,'0');
});

test('desktop pointer selects a recorded gaze pose and transitions smoothly',async()=>{
  const w=await widget();
  w.movePointer(310,236);
  assert.equal(w.root.dataset.gazeDirection,'right');
  await new Promise(r=>setImmediate(r));
  assert.ok(w.requested.some(src=>src.includes('gaze-new/gaze-soft.webp')));
  await w.advance(0,420);
  assert.equal(w.root.dataset.animation,'gaze');
  w.movePointer(190,236);
  assert.equal(w.root.dataset.gazeDirection,'left');
  await w.advance(440,760);
  assert.equal(w.root.dataset.animation,'gaze');
});

test('touch pointers and reduced motion do not activate gaze assets',async()=>{
  const w=await widget();
  w.movePointer(310,236,'touch');
  assert.equal(w.root.dataset.gazeDirection,undefined);
  assert.ok(!w.requested.some(src=>src.includes('gaze/')));
  w.reduced.matches=true;
  w.listeners.reduced();
  w.movePointer(310,236);
  assert.equal(w.root.dataset.animation,'idle');
  assert.equal(w.root.dataset.gazeDirection,undefined);
});

test('hidden tab pauses and resumes the current loop pose',async()=>{
  const w=await widget();
  await w.advance(0,1500);
  const pose={...w.root.dataset};
  w.document.hidden=true;
  w.listeners.visibilitychange();
  assert.equal(w.frames.size,0);
  w.frame(100000);
  assert.deepEqual(w.root.dataset,pose);
  w.document.hidden=false;
  w.listeners.visibilitychange();
  w.frame(100020);
  assert.deepEqual(w.root.dataset,pose);
});

test('reduced motion keeps the original stationary pose',async()=>{
  const w=await widget({reducedMotion:true});
  assert.equal(w.root.dataset.animation,'idle');
  assert.equal(w.frames.size,0);
  assert.ok(!w.requested.some(src=>src.includes('wave-camera.webp')));
});

test('a missing wave atlas falls back to the poster without repeated requests',async()=>{
  const w=await widget({fail:'wave-camera.webp'});
  await w.advance(0,50000);
  assert.equal(w.root.dataset.animation,'idle');
  assert.equal(w.requested.filter(src=>src.includes('wave-camera.webp')).length,1);
  assert.ok(w.draws>0);
});

test('tariffs gaze follows direction and returns to loop on pointer leave',async()=>{
  const w=await widget({tariffs:true});
  w.movePointer(310,256);
  assert.equal(w.root.dataset.gazeDirection,'right');
  await new Promise(r=>setImmediate(r));
  assert.ok(w.requested.some(src=>src.includes('tariffs/gaze-new/gaze-soft.webp')));
  await w.advance(0,420);
  assert.equal(w.root.dataset.animation,'gaze');
  w.listeners['document:pointerleave']();
  assert.equal(w.root.dataset.gazeDirection,undefined);
  await w.advance(440,900);
  assert.equal(w.root.dataset.animation,'waveLoop');
});

test('coarse pointer and reduced-motion changes disable active tracking',async()=>{
  const w=await widget();
  w.fine.matches=false;
  w.movePointer(310,236);
  assert.equal(w.root.dataset.gazeDirection,undefined);
  assert.ok(!w.requested.some(src=>src.includes('gaze/')));
  w.fine.matches=true;
  w.movePointer(310,236);
  await new Promise(r=>setImmediate(r));
  await w.advance(0,400);
  assert.equal(w.root.dataset.animation,'gaze');
  w.reduced.matches=true;
  w.listeners.reduced();
  assert.equal(w.root.dataset.gazeDirection,undefined);
  assert.equal(w.root.dataset.animation,'idle');
  assert.equal(w.frames.size,0);
});


test('wave resumes five seconds after pointer stops and gaze resumes on movement', async()=>{
 const w=await widget({tariffs:true});
 w.movePointer(310,256); await new Promise(r=>setImmediate(r));
 await w.advance(0,4980); assert.equal(w.root.dataset.animation,'gaze');
 await w.advance(5000,5400); assert.equal(w.root.dataset.animation,'waveLoop');
 w.movePointer(190,256); await w.advance(5420,5800); assert.equal(w.root.dataset.animation,'gaze');
});

test('pointer corners update both gaze axes and idle still resumes',async()=>{
 const w=await widget({tariffs:true});
 w.movePointer(0,0); await new Promise(r=>setImmediate(r)); await w.advance(0,400);
 assert.equal(w.root.dataset.gazeDirection,'upleft');
 w.movePointer(0,800); await w.advance(420,800);
 assert.equal(w.root.dataset.gazeDirection,'downleft');
 await w.advance(820,5900); assert.equal(w.root.dataset.animation,'waveLoop');
});
