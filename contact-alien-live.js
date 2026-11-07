/* Continuous 2.5D face rig. Native video supplies idle motion; cursor motion
   transforms one neutral head texture and the original iris textures. */
window.startVegaAlienLive = function(root, manifest) {
  const canvas=root.querySelector('canvas'), ctx=canvas.getContext('2d');
  if(!ctx)return false;
  const second=manifest.videoPrefix==='tariffs', W=second?320:300,H=second?326:400;
  canvas.width=W;canvas.height=H;
  const reduced=matchMedia('(prefers-reduced-motion: reduce)');
  const clip=second?{src:'tariffs/wave-front.webp',width:320,height:326,columns:10,frames:75,fps:24}:manifest.wave;
  const anchor=second?67:72, neck=second?187:225, pivot=second?177:150;
  const eyes=second?[
    {x:141,y:135.5,rx:22,ry:21,crop:[128,122,34,34],r:17},
    {x:213.5,y:138,rx:22,ry:21,crop:[193,124,34,34],r:17}
  ]:[
    {x:112,y:163,rx:26,ry:24,crop:[98,142,40,44],r:20},
    {x:197,y:171,rx:24,ry:24,crop:[175,154,38,38],r:19}
  ];
  const source=document.createElement('canvas');source.width=W;source.height=H;
  const sourceCtx=source.getContext('2d'), sheet=new Image();
  let ready=false,visible=false,pointer=null,raf=0,last=0;
  let position=anchor,velocity=0,direction=1,headX=0,headY=0,faceBlend=0,mode='idle';
  const clamp=(v,a,b)=>Math.max(a,Math.min(b,v));
  function original(frame,context=ctx) {
    context.drawImage(sheet,frame%clip.columns*W,Math.floor(frame/clip.columns)*H,W,H,0,0,W,H);
  }
  function finish() {
    if(!second)ctx.clearRect(230,370,70,30);
    root.dataset.animation=mode;
    root.dataset.gazeX=headX.toFixed(3);root.dataset.gazeY=headY.toFixed(3);
  }
  function renderFace() {
    ctx.clearRect(0,0,W,H);
    ctx.drawImage(source,0,neck,W,H-neck,0,neck,W,H-neck);
    ctx.drawImage(source,pivot-16,neck-7,32,14,pivot-16,neck-9,32,18);
    ctx.save();ctx.translate(pivot,neck);ctx.rotate(headX*0.035);
    ctx.transform(1,headY*0.015,headX*0.025,1,headX*2,headY*2);
    ctx.translate(-pivot,-neck);
    ctx.drawImage(source,0,0,W,neck,0,0,W,neck);
    for(const eye of eyes) {
      ctx.save();ctx.beginPath();ctx.ellipse(eye.x,eye.y,eye.rx,eye.ry,0,0,Math.PI*2);ctx.clip();
      ctx.globalAlpha=faceBlend;
      const white=ctx.createRadialGradient(eye.x-4,eye.y-7,2,eye.x,eye.y,eye.rx*1.2);
      white.addColorStop(0,'#fffaff');white.addColorStop(.65,'#f7eafb');white.addColorStop(1,'#c4a8e0');
      ctx.fillStyle=white;ctx.fillRect(eye.x-eye.rx,eye.y-eye.ry,eye.rx*2,eye.ry*2);
      const rx=eye.crop[2]/2,ry=eye.crop[3]/2;
      const originalX=eye.crop[0]+rx,originalY=eye.crop[1]+ry;
      const px=originalX+(eye.x+headX*7-originalX)*faceBlend;
      const py=originalY+(eye.y+headY*6-originalY)*faceBlend;
      ctx.save();ctx.beginPath();ctx.ellipse(px,py,rx,ry,0,0,Math.PI*2);ctx.clip();
      ctx.drawImage(source,...eye.crop,px-rx,py-ry,rx*2,ry*2);
      ctx.restore();ctx.restore();
    }
    ctx.restore();finish();
  }
  function queue(){if(!raf&&ready&&visible&&!document.hidden)raf=requestAnimationFrame(tick);}
  function tick(now) {
    raf=0;if(!ready||!visible||document.hidden)return;
    const dt=last?Math.min(50,Math.max(0,now-last)):16;last=now;
    if(reduced.matches) {
      mode='idle';ctx.clearRect(0,0,W,H);original(anchor);finish();return;
    }
    let tx=0,ty=0;
    if(pointer) {
      const b=root.getBoundingClientRect();
      const dx=pointer.x-(b.left+b.width*pivot/W),dy=pointer.y-(b.top+b.height*(second?137:167)/H);
      tx=clamp(dx/Math.max(b.width*.75,160),-1,1);
      ty=clamp(dy/Math.max(b.height*.65,140),-1,1);
    }
    if(pointer && mode==='idle')mode='settle';
    if(mode==='settle') {
      // Complete the existing gesture toward an open-eyed, hands-down native
      // frame, instead of dissolving or jumping to a different body pose.
      const delta=anchor-position;
      position+=Math.sign(delta)*Math.min(Math.abs(delta),dt*.075);
      ctx.clearRect(0,0,W,H);original(Math.round(position));finish();
      if(Math.abs(delta)<.1){mode='tracking';headX=0;headY=0;}
    } else if(mode==='tracking'||mode==='return') {
      mode=pointer?'tracking':'return';
      const damping=1-Math.exp(-dt/110);
      headX+=(tx-headX)*damping;headY+=(ty-headY)*damping;
      faceBlend+=((pointer?1:0)-faceBlend)*damping;
      renderFace();
      if(!pointer&&Math.abs(headX)+Math.abs(headY)+faceBlend<.008) {
        headX=0;headY=0;faceBlend=0;mode='idle';position=anchor;velocity=0;
      }
    } else {
      // Reflect at the same source frame, easing the velocity near both ends.
      const edge=Math.min(position,clip.frames-1-position);
      const speed=clip.fps*Math.max(.12,Math.min(1,edge/7));
      velocity+=(direction*speed-velocity)*(1-Math.exp(-dt/90));
      position+=velocity*dt/1000;
      if(position>=clip.frames-1){position=clip.frames-1;direction=-1;}
      if(position<=0){position=0;direction=1;}
      ctx.clearRect(0,0,W,H);original(Math.round(position));finish();
    }
    queue();
  }
  function point(event){if(event.pointerType==='touch'||reduced.matches)return;pointer={x:event.clientX,y:event.clientY};queue();}
  function leave(){pointer=null;queue();}
  function refresh(){cancelAnimationFrame(raf);raf=0;last=0;queue();}
  document.addEventListener('pointermove',point,{passive:true});
  document.addEventListener('mousemove',point,{passive:true});
  document.addEventListener('click',point,{passive:true});
  document.documentElement.addEventListener('pointerleave',leave);
  window.addEventListener('blur',leave);
  document.addEventListener('visibilitychange',refresh);
  reduced.addEventListener('change',()=>{pointer=null;refresh();});
  window.addEventListener('pagehide',()=>{cancelAnimationFrame(raf);raf=0;last=0;});
  window.addEventListener('pageshow',refresh);
  if('IntersectionObserver' in window)new IntersectionObserver(entries=>{visible=entries.some(e=>e.isIntersecting);refresh();},{rootMargin:'50px'}).observe(root);
  else visible=true;
  sheet.onload=()=>{original(anchor,sourceCtx);ready=true;canvas.hidden=false;root.classList.add('is-ready');queue();};
  sheet.onerror=()=>{root.dataset.animation='fallback';};
  sheet.src='assets/alien/'+clip.src;
  return true;
};
