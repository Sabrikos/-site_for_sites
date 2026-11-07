const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
function setup(){
  const events={},frames=[],videos=[]; let observe,now=0,plays=0,seeks=0;
  const ctx={clearRect(){},drawImage(){},getImageData(){return {data:new Uint8ClampedArray(4)};},putImageData(){}};
  const canvas={getContext:()=>ctx};
  const root={dataset:{},querySelector:()=>canvas,classList:{add(){}},getBoundingClientRect:()=>({left:0,top:0,width:300,height:400})};
  const document={hidden:false,documentElement:{addEventListener(){}},addEventListener:(name,fn)=>events[name]=fn,createElement(name){
    if(name==='canvas')return {getContext:()=>ctx};
    let time=0;
    const video={readyState:2,duration:2,paused:true,ended:false,seeking:false,load(){},addEventListener(name,fn){this[name]=fn;},pause(){this.paused=true;},play(){plays++;this.paused=false;return Promise.resolve();},get currentTime(){return time;},set currentTime(v){seeks++;time=v;}};
    videos.push(video);return video;
  }};
  const env={window:{innerWidth:1200,innerHeight:800,addEventListener(){}},document,matchMedia:()=>({matches:false,addEventListener(){}}),Image:class{constructor(){this.complete=true;this.naturalWidth=300;}},IntersectionObserver:class{constructor(f){observe=f;}observe(){}},requestAnimationFrame:f=>(frames.push(f),frames.length),cancelAnimationFrame(){},performance:{now:()=>now},Uint8ClampedArray};
  vm.runInNewContext(fs.readFileSync(require.resolve('../contact-alien-video.js'),'utf8'),env);
  env.window.startVegaAlienVideo(root,{width:300,height:400,poster:'poster.png',videoPrefix:'home',loopDurationMs:5200,gaze:{origin:[.5,.34]},wave:{src:'wave.webp',frames:96,width:300,height:400,columns:10}});
  observe([{isIntersecting:true}]);
  return {events,root,videos,get plays(){return plays;},get seeks(){return seeks;},tick(t){now=t;const fn=frames.shift();if(fn)fn(t);}};
}
test('cursor starts real playback without a seek on every tick and remains active while stationary',()=>{
  const w=setup();w.tick(0);w.events.pointermove({clientX:550,clientY:136,pointerType:'mouse'});w.tick(16);
  assert.equal(w.root.dataset.animation,'video-gaze');assert.ok(w.plays>0);
  const seeks=w.seeks;w.tick(32);w.tick(48);assert.equal(w.seeks,seeks);
  w.tick(5100);assert.equal(w.root.dataset.animation,'video-gaze');
});
test('touch does not start videos; a failed video returns to wave',()=>{
  const w=setup();w.tick(0);w.events.pointermove({clientX:550,clientY:136,pointerType:'touch'});w.tick(16);assert.equal(w.videos.length,0);
  w.events.pointermove({clientX:550,clientY:136,pointerType:'mouse'});w.tick(32);w.videos[0].error();w.tick(48);assert.equal(w.root.dataset.animation,'waveLoop');
});
