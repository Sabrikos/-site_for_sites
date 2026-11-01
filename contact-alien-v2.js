(() => {
 const el=document.querySelector('[data-contact-alien]'); if(!el)return;
 const image=el.querySelector('.contact-alien__original'), media=matchMedia('(prefers-reduced-motion: reduce)'),fine=matchMedia('(hover: hover) and (pointer: fine)');
 let visible=false,frame=0,blink=0,wave=0,dwell=0,pose=null,lastReach=0,lastTime=0;
 let x=0,y=0,tx=0,ty=0,arm=0,reach=-80;
 const active=()=>visible&&!document.hidden&&!media.matches;
 const clamp=(n,a,b)=>Math.max(a,Math.min(b,n));
 const queue=()=>{if(!frame&&active())frame=requestAnimationFrame(draw);};
 function gesture(kind){if(!active()||pose)return;pose={kind,start:Date.now()};el.dataset.gesture=kind;queue();}
 function draw(){
  frame=0;if(!active())return;const now=Date.now(),dt=lastTime?Math.min(50,now-lastTime):16;lastTime=now;const ease=1-Math.exp(-dt/100);
  x+=(tx-x)*ease;y+=(ty-y)*ease;let target=0;
  if(pose){const t=now-pose.start,duration=pose.kind==='wave'?2600:1900;
   if(t>=duration){pose=null;el.dataset.gesture='idle';}
   else{const envelope=Math.min(1,t/500,(duration-t)/550);target=envelope*(pose.kind==='wave'?-100+12*Math.sin(Math.max(0,t-450)/150):reach);}}
  arm+=(target-arm)*ease;
  el.style.setProperty('--alien-eye-x',`${x.toFixed(2)}px`);el.style.setProperty('--alien-eye-y',`${y.toFixed(2)}px`);el.style.setProperty('--alien-arm-angle',`${arm.toFixed(2)}deg`);
  if(pose||Math.abs(arm)>.05||Math.abs(tx-x)+Math.abs(ty-y)>.05)queue();else lastTime=0;
 }
 function scheduleBlink(){if(!active())return;blink=setTimeout(()=>{if(!active())return;el.classList.add('is-blinking');blink=setTimeout(()=>{el.classList.remove('is-blinking');scheduleBlink();},180);},3800+Math.random()*3200);}
 function scheduleWave(){if(!active())return;wave=setTimeout(()=>{gesture('wave');scheduleWave();},11000+Math.random()*6500);}
 function reset(){clearTimeout(blink);clearTimeout(wave);clearTimeout(dwell);dwell=0;cancelAnimationFrame(frame);frame=0;pose=null;lastTime=0;x=y=tx=ty=arm=0;el.classList.remove('is-blinking');el.dataset.gesture='idle';for(const key of ['--alien-eye-x','--alien-eye-y','--alien-arm-angle'])el.style.removeProperty(key);}
 function refresh(){reset();if(active()){scheduleBlink();scheduleWave();}}
 const ready=()=>{if(image.naturalWidth){el.classList.add('is-ready');refresh();}};
 if(image.complete)ready();else image.addEventListener('load',ready,{once:true});
 if('IntersectionObserver'in window)new IntersectionObserver(entries=>{visible=entries.some(e=>e.isIntersecting);refresh();},{threshold:.08}).observe(el);else{visible=true;refresh();}
 document.addEventListener('pointermove',event=>{
  if(!active()||!fine.matches||event.pointerType==='touch')return;const box=el.getBoundingClientRect();if(!box.width)return;
  const px=(event.clientX-box.left)*1086/box.width,py=(event.clientY-box.top)*1448/box.height;
  tx=clamp((px-590)/22,-22,16);ty=clamp((py-600)/35,-14,14);
  const near=px>600&&Math.hypot(px-730,py-800)<700;reach=clamp(Math.atan2(py-885,px-660)*180/Math.PI-62,-130,-30);
  if(near&&!dwell&&!pose&&Date.now()-lastReach>6500)dwell=setTimeout(()=>{dwell=0;if(active()){lastReach=Date.now();gesture('reach');}},450);
  else if(!near){clearTimeout(dwell);dwell=0;}queue();
 },{passive:true});
 document.documentElement.addEventListener('pointerleave',()=>{tx=ty=0;clearTimeout(dwell);dwell=0;queue();});
 document.addEventListener('visibilitychange',refresh);media.addEventListener('change',refresh);fine.addEventListener('change',refresh);
 window.addEventListener('pagehide',reset);window.addEventListener('pageshow',e=>{if(e.persisted)refresh();});
})();
