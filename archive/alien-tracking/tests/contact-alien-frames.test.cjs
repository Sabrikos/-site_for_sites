const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

async function widget(optionalFailure = false, head = false, reach = false, motion = false, failSource = '', track = false) {
  const listeners = {}, frames = new Map();
  let observe, id = 0, now = 0;
  const canvas = {width:300,height:400,hidden:true,getContext:()=>({clearRect(){},drawImage(){}})};
  const root = {dataset:{},classList:{add(){}},querySelector:()=>canvas,
    getBoundingClientRect:()=>({left:0,top:0,width:300,height:400})};
  const reduced = {matches:false,addEventListener:(_name, callback)=>{listeners.reduced=callback;}};
  const fine = {matches:true,addEventListener(){}};
  const data = {src:'blink.webp',frames:27,fps:20,width:300,height:400,columns:10};
  const document = {hidden:false,querySelector:()=>root,documentElement:{addEventListener(){}},
    addEventListener:(name,callback)=>{listeners[name]=callback;}};
  const context = {
    document, window:{VegaAlienClips:{blink:data,...(reach ? {reach:{...data,src:'reach.webp',...(track?{frames:89,fps:30,track:{start:35,end:44,returnMirror:88,holdMs:1100,points:[[35,256,192],[44,288,214]]}}:{})}} : {}),...(optionalFailure ? {head:{...data,src:'missing.webp'}} : head ? {head:{...data,src:'head.webp',from:0,to:26}} : {})},
      ...(motion?{VegaAlienMotion:{gaze:{...data,src:'gaze-paths.webp',height:240,frames:80,directions:{up:{start:0,frames:20},down:{start:20,frames:20},left:{start:40,frames:20},right:{start:60,frames:20}}},bridges:{blink:{...data,src:'bridge.webp',frames:24,fps:30},...(reach?{reach:{...data,src:'reach-bridge.webp',frames:24,fps:30}}:{})}}}:{}),addEventListener(){}},
    matchMedia:q=>q.includes('reduced')?reduced:fine,
    performance:{now:()=>now}, Math:Object.assign(Object.create(Math),{random:()=>0}),
    Image:class {async decode(){if(this.src.includes('missing') || (failSource && this.src.includes(failSource))) throw new Error('Unavailable');}},
    IntersectionObserver:class {constructor(callback){observe=callback;} observe(){}},
    requestAnimationFrame:callback=>{frames.set(++id,callback);return id;},
    cancelAnimationFrame:id=>frames.delete(id)
  };
  context.window.IntersectionObserver=context.IntersectionObserver;
  vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../contact-alien.js'),'utf8'),context);
  observe([{isIntersecting:true}]);
  await new Promise(resolve=>setImmediate(resolve));
  return {root,canvas,frames,document,reduced,listeners,
    frame(time){now=time;const callbacks=[...frames.values()];frames.clear();callbacks.forEach(fn=>fn(time));}};
}
test('decoded real-frame clip advances and reduced motion stops rendering',async()=>{
  const w=await widget();
  assert.equal(w.canvas.hidden,false);
  w.frame(3201);w.frame(3851);
  assert.equal(w.root.dataset.frame,'13');
  w.reduced.matches=true;w.listeners.reduced();
  assert.equal(w.frames.size,0);
  assert.equal(w.root.dataset.frame,'0');
});
test('optional missing animation does not disable base blink',async()=>{
  const w=await widget(true);
  assert.equal(w.canvas.hidden,false);
  w.frame(3201);w.frame(3851);
  assert.equal(w.root.dataset.frame,'13');
});
test('head follows pointer gradually with finite frame indices',async()=>{
  const w=await widget(false,true);
  w.frame(100);
  assert.equal(w.root.dataset.animation,'head');
  w.listeners.pointermove({clientX:-600,clientY:100,pointerType:'mouse'});
  w.frame(160);
  const left=Number(w.root.dataset.frame);
  assert.ok(left>0 && left<13);
  w.listeners.pointermove({clientX:900,clientY:100,pointerType:'mouse'});
  for(let t=220;t<1000;t+=60) w.frame(t);
  assert.ok(Number(w.root.dataset.frame)>left);
  assert.ok(Number.isFinite(Number(w.root.dataset.frame)));
});
test('first nearby hover survives lazy gesture loading',async()=>{
  const w=await widget(false,false,true);
  w.frame(100);
  w.listeners.pointermove({clientX:270,clientY:220,pointerType:'mouse'});
  w.frame(4500);
  await new Promise(resolve=>setImmediate(resolve));
  w.frame(4700);
  assert.equal(w.root.dataset.animation,'reach');
  assert.equal(w.root.dataset.frame,'4');
});
test('gaze responds to both cursor axes without teleporting',async()=>{
  const w=await widget(false,false,false,true);
  w.frame(100);
  w.listeners.pointermove({clientX:900,clientY:-500,pointerType:'mouse'});
  w.frame(160);
  assert.ok(Number(w.root.dataset.gazeX)>.5 && Number(w.root.dataset.gazeX)<1);
  assert.ok(Number(w.root.dataset.gazeY)<.5 && Number(w.root.dataset.gazeY)>0);
  for(let t=220;t<2000;t+=60) w.frame(t);
  assert.ok(Number(w.root.dataset.gazeX)>.99);
  assert.ok(Number(w.root.dataset.gazeY)<.01);
  w.listeners.pointermove({clientX:150,clientY:900,pointerType:'mouse'});
  for(let t=2020;t<3100;t+=20) w.frame(t);
  assert.ok(Number(w.root.dataset.gazeY)>.8);
});
test('direction changes pass through neutral before the next gaze path',async()=>{
  const w=await widget(false,false,false,true);
  w.frame(100);
  w.listeners.pointermove({clientX:900,clientY:160,pointerType:'mouse'});
  for(let t=120;t<2000;t+=20)w.frame(t);
  assert.equal(w.root.dataset.gazeDirection,'right');
  assert.ok(Number(w.root.dataset.gazeAmount)>.95);
  w.listeners.pointermove({clientX:150,clientY:-600,pointerType:'mouse'});
  let previousAmount=1, changed=false;
  for(let t=2000;t<3150;t+=20){
    w.frame(t);
    if(w.root.dataset.gazeDirection==='up'){
      assert.ok(previousAmount<.01,'direction must change at neutral');changed=true;break;
    }
    previousAmount=Number(w.root.dataset.gazeAmount);
  }
  assert.equal(changed,true);
});
test('gesture plays entrance and exit bridges before returning to gaze',async()=>{
  const w=await widget(false,false,false,true);
  w.frame(3201);
  await new Promise(resolve=>setImmediate(resolve));
  w.frame(3220);
  assert.equal(w.root.dataset.animation,'blinkBridge');
  assert.equal(w.root.dataset.frame,'0');
  w.frame(3630);
  assert.equal(w.root.dataset.animation,'blink');
  w.frame(4900);
  assert.equal(w.root.dataset.animation,'blinkBridge');
  assert.ok(Number(w.root.dataset.frame)>=12);
  w.frame(5300);
  assert.equal(w.root.dataset.animation,'gaze');
});
test('a failed reach bridge does not starve scheduled blinking',async()=>{
  const w=await widget(false,false,true,true,'reach-bridge');
  w.frame(100);
  w.listeners.pointermove({clientX:270,clientY:220,pointerType:'mouse'});
  w.frame(4500);
  await new Promise(resolve=>setImmediate(resolve));
  w.frame(4550);
  await new Promise(resolve=>setImmediate(resolve));
  let blinkSeen=false;
  for(let t=4600;t<8500;t+=20){w.frame(t);if(w.root.dataset.animation==='blink')blinkSeen=true;}
  assert.equal(blinkSeen,true);
  assert.notEqual(w.root.dataset.animation,'reach');
  // The blink cycle completed and the character returned to gaze.
  assert.equal(w.root.dataset.animation,'gaze');
});
test('Kling reach changes whole-frame pose toward the cursor, then exits',async()=>{
  const w=await widget(false,false,true,false,'',true);
  w.frame(100);w.listeners.pointermove({clientX:256,clientY:192,pointerType:'mouse'});
  w.frame(4500);await new Promise(resolve=>setImmediate(resolve));
  // Intro reaches the interactive part at t5667.
  for(let t=4520;t<5800;t+=20)w.frame(t);
  assert.equal(w.root.dataset.animation,'reach');
  const first=Number(w.root.dataset.frame);assert.ok(first>=35&&first<=44);
  w.listeners.pointermove({clientX:288,clientY:214,pointerType:'mouse'});
  for(let t=5800;t<6100;t+=20)w.frame(t);
  assert.ok(Number(w.root.dataset.frame)>first);
  w.listeners.pointermove({clientX:0,clientY:0,pointerType:'mouse'});
  for(let t=6100;t<8500;t+=20)w.frame(t);
  assert.notEqual(w.root.dataset.animation,'reach');
});

test('leaving during the reach intro selects the identical return pose immediately',async()=>{
  const w=await widget(false,false,true,false,'',true);
  w.frame(100);w.listeners.pointermove({clientX:256,clientY:192,pointerType:'mouse'});
  w.frame(4500);await new Promise(resolve=>setImmediate(resolve));w.frame(4700);
  assert.equal(w.root.dataset.animation,'reach');assert.ok(Number(w.root.dataset.frame)<35);
  w.listeners.pointermove({clientX:0,clientY:0,pointerType:'mouse'});w.frame(4720);
  assert.ok(Number(w.root.dataset.frame)>70);
  w.frame(5100);assert.notEqual(w.root.dataset.animation,'reach');
});
