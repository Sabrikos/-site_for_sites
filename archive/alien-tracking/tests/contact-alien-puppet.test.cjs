const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
const path=require('node:path');
const math=require('../contact-alien-math.js');

test('IK keeps both bone lengths and finite joints for all cursor directions',()=>{
  for(let angle=-Math.PI;angle<Math.PI;angle+=.1)for(const radius of [0,10,100,230,10000]){
    const p=math.solve(math.shoulder.x+Math.cos(angle)*radius,math.shoulder.y+Math.sin(angle)*radius);
    assert.ok(Number.isFinite(p.a)&&Number.isFinite(p.b));
    assert.ok(Math.abs(Math.hypot(p.elbow.x-math.shoulder.x,p.elbow.y-math.shoulder.y)-math.l1)<1e-6);
    assert.ok(Math.abs(Math.hypot(p.wrist.x-p.elbow.x,p.wrist.y-p.elbow.y)-math.l2)<1e-6);
  }
  const pose=math.solve(math.wrist.x,math.wrist.y);
  assert.ok(Math.hypot(pose.elbow.x-math.elbow.x,pose.elbow.y-math.elbow.y)<1e-6);
});
test('critically damped follow settles quickly without overshoot',()=>{
  let x=0,v=0;
  for(let i=0;i<20;i++){[x,v]=math.damp(x,v,1,1/60,25);assert.ok(x<=1&&x>=0);}
  assert.ok(x>.99);
});
async function widget(random=0){
  let now=0,observe,id=0;
  const frames=new Map(),listeners={};
  const noop=()=>{};
  const context=new Proxy({getImageData:()=>({data:new Uint8ClampedArray(4)}),createLinearGradient:()=>({addColorStop:noop})},{get:(o,k)=>k in o?o[k]:noop,set:(o,k,v)=>(o[k]=v,true)});
  const canvas={width:300,height:400,getContext:()=>context,getBoundingClientRect:()=>({left:0,top:0,width:1086,height:1448})};
  const root={dataset:{},querySelector:()=>canvas,classList:{add:noop}};
  const reduced={matches:false,addEventListener:(n,f)=>listeners.reduced=f};
  const fine={matches:true,addEventListener:noop};
  const document={hidden:false,querySelector:()=>root,createElement:()=>({getContext:()=>context}),documentElement:{addEventListener:(n,f)=>listeners[n]=f},addEventListener:(n,f)=>listeners[n]=f};
  const env={document,window:{VegaAlienMath:math,addEventListener:noop},performance:{now:()=>now},
    matchMedia:q=>q.includes('reduced')?reduced:fine,Path2D:class{},Image:class{async decode(){}},
    Math:Object.assign(Object.create(Math),{random:()=>random}),
    IntersectionObserver:class{constructor(f){observe=f;}observe(){}},
    requestAnimationFrame:f=>(frames.set(++id,f),id),cancelAnimationFrame:i=>frames.delete(i)};
  env.window.IntersectionObserver=env.IntersectionObserver;
  vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../contact-alien-puppet.js'),'utf8'),env);
  observe([{isIntersecting:true}]);await new Promise(r=>setImmediate(r));
  return {root,reduced,frames,listeners,observe,frame(t){now=t;const f=[...frames.values()];frames.clear();f.forEach(fn=>fn(t));},move(x,y){listeners.pointermove({clientX:x,clientY:y,pointerType:'mouse'});}};
}
test('reaching wrist changes direction while the same reach is playing',async()=>{
  const w=await widget();w.frame(100);w.move(640,820);
  for(let t=1600;t<2000;t+=20)w.frame(t);
  assert.equal(w.root.dataset.animation,'reach');
  const right=Number(w.root.dataset.handX);assert.ok(right>550);
  w.move(230,850);for(let t=2000;t<2400;t+=20)w.frame(t);
  assert.equal(w.root.dataset.animation,'reach');
  assert.ok(Number(w.root.dataset.handX)<right-180);
  assert.ok(Number(w.root.dataset.gazeX)<0);
});
test('wave looks into the camera and returns the hand smoothly',async()=>{
  const w=await widget();w.frame(100);w.move(1000,100);
  w.frame(9001);for(let t=9020;t<9600;t+=20)w.frame(t);
  assert.equal(w.root.dataset.animation,'wave');assert.equal(w.root.dataset.camera,'true');
  assert.ok(Math.abs(Number(w.root.dataset.gazeX))<.01);
  for(let t=9600;t<11100;t+=20)w.frame(t);
  w.frame(11100);const before=Number(w.root.dataset.handY);w.frame(11102);
  assert.equal(w.root.dataset.animation,'idle');
  assert.ok(Math.abs(Number(w.root.dataset.handY)-before)<2);
});
test('reduced motion and leaving the viewport stop scheduled drawing',async()=>{
  const w=await widget();w.frame(100);assert.ok(w.frames.size>0);
  w.reduced.matches=true;w.listeners.reduced();assert.equal(w.frames.size,0);
  assert.equal(w.root.dataset.animation,'idle');
  w.reduced.matches=false;w.listeners.reduced();assert.ok(w.frames.size>0);
  w.observe([{isIntersecting:false}]);assert.equal(w.frames.size,0);
});
test('far cursor never triggers a reach; a close approach can be skipped',async()=>{
  const far=await widget();far.frame(100);far.move(1000,100);
  for(let t=1600;t<4000;t+=20)far.frame(t);
  assert.equal(far.root.dataset.animation,'idle');
  const skipped=await widget(.9);skipped.frame(100);skipped.move(640,820);
  for(let t=1600;t<4000;t+=20)skipped.frame(t);
  assert.equal(skipped.root.dataset.animation,'idle');
});
test('moving away cancels reach without teleporting the wrist',async()=>{
  const w=await widget();w.frame(100);w.move(640,820);
  for(let t=1600;t<2000;t+=20)w.frame(t);
  assert.equal(w.root.dataset.animation,'reach');
  const before=Number(w.root.dataset.handX);w.move(1500,100);w.frame(1982);
  assert.equal(w.root.dataset.animation,'idle');
  assert.ok(Math.abs(Number(w.root.dataset.handX)-before)<2);
});
