(() => {
  'use strict';
  const root=document.querySelector('[data-contact-alien]');
  const canvas=root?.querySelector('canvas'),ctx=canvas?.getContext('2d');
  const math=window.VegaAlienMath;
  if(!ctx||!math)return;
  const {clamp,shoulder,elbow,wrist,solve,damp}=math;
  const reduced=matchMedia('(prefers-reduced-motion: reduce)');
  const fine=matchMedia('(hover: hover) and (pointer: fine)');
  const W=1086,H=1448;
  let loaded=false,loading=false,visible=false,raf=0,previous=0;
  let base,arm,armWithoutHand,palm,original,openHand=false;
  let mode='idle',modeStart=0,nextWave=0,nextReach=0,nextBlink=0,blinkStart=-10000,nearSince=0;
  let nearLast=false,reachEligible=false;
  let pointer={x:540,y:630,active:false};
  const hand={x:wrist.x,y:wrist.y,vx:0,vy:0};
  const gaze={x:0,y:0,vx:0,vy:0};
  const armPath=new Path2D('M445 827 Q468 846 461 883 L438 956 L412 1030 L409 1068 Q437 1088 426 1124 Q420 1143 398 1135 Q384 1147 369 1140 Q352 1155 330 1145 Q310 1147 298 1137 Q278 1139 271 1124 Q262 1105 282 1088 Q300 1073 323 1070 L344 1009 L367 940 Q390 859 419 837 Z');
  const repairPath=new Path2D('M250 820 L480 820 L474 900 L442 1000 L431 1160 L250 1160Z');
  const eyes=[
    {x:426,y:584,rx:53,ry:73,box:[428,513,108,149],white:[338,499,205,188],dest:[328,487,207,185],path:new Path2D('M332 587 C333 535 374 491 427 490 C481 486 518 522 530 568 C541 615 515 658 483 667 C438 683 374 669 347 645 C334 632 328 612 332 587Z')},
    {x:738,y:627,rx:46,ry:65,box:[727,569,100,140],white:[657,557,183,175],dest:[641,540,185,177],path:new Path2D('M644 637 C654 590 694 548 741 542 C778 534 808 552 820 591 C836 637 816 687 788 703 C752 725 700 714 664 691 C643 676 636 660 644 637Z')}
  ];
  const mesh=[],vertices=[];
  const bindA=Math.atan2(elbow.y-shoulder.y,elbow.x-shoulder.x);
  const bindB=Math.atan2(wrist.y-elbow.y,wrist.x-elbow.x);
  const makeLayer=()=>{const c=document.createElement('canvas');c.width=W;c.height=H;return c;};
  const image=async src=>{const i=new Image();i.src=src;await i.decode();return i;};
  const active=()=>loaded&&visible&&!document.hidden&&!reduced.matches;
  function rotate(p,origin,angle,target){
    const x=p.x-origin.x,y=p.y-origin.y,c=Math.cos(angle),s=Math.sin(angle);
    return {x:target.x+x*c-y*s,y:target.y+x*s+y*c};
  }
  function deform(p,pose){
    const upper=rotate(p,shoulder,pose.a-bindA,shoulder);
    const lower=rotate(p,elbow,pose.b-bindB,pose.elbow);
    let t=clamp((p.y-960)/90,0,1);t=t*t*(3-2*t);
    return {x:upper.x+(lower.x-upper.x)*t,y:upper.y+(lower.y-upper.y)*t};
  }
  function triangle(source,dest,texture){
    const [a,b,c]=source,[p,q,r]=dest;
    const det=a.x*(b.y-c.y)+b.x*(c.y-a.y)+c.x*(a.y-b.y);
    if(Math.abs(det)<.001)return;
    const transform=(v0,v1,v2)=>[(v0*(b.y-c.y)+v1*(c.y-a.y)+v2*(a.y-b.y))/det,
      (v0*(c.x-b.x)+v1*(a.x-c.x)+v2*(b.x-a.x))/det,
      (v0*(b.x*c.y-c.x*b.y)+v1*(c.x*a.y-a.x*c.y)+v2*(a.x*b.y-b.x*a.y))/det];
    const tx=transform(p.x,q.x,r.x),ty=transform(p.y,q.y,r.y);
    ctx.save();ctx.beginPath();ctx.moveTo(p.x,p.y);ctx.lineTo(q.x,q.y);ctx.lineTo(r.x,r.y);ctx.closePath();ctx.clip();
    ctx.transform(tx[0],ty[0],tx[1],ty[1],tx[2],ty[2]);ctx.drawImage(texture,0,0);ctx.restore();
  }
  function drawArm(pose){
    const texture=openHand?armWithoutHand:arm;
    for(const vertex of vertices)vertex.deformed=deform(vertex,pose);
    for(const tri of mesh)triangle(tri,tri.map(p=>p.deformed),texture);
    if(openHand){
      ctx.save();ctx.translate(pose.wrist.x,pose.wrist.y);
      // Wrist rotation keeps the greeting palm upright while the elbow bends.
      const angle=mode==='reach'?Math.atan2(pointer.y-pose.wrist.y,pointer.x-pose.wrist.x)+1.844:Math.sin((performance.now()-modeStart)/1000*9)*.16;
      ctx.rotate(angle);
      ctx.scale(-.85,.85);ctx.drawImage(palm,-850,-925);ctx.restore();
    }
  }
  function render(now){
    const scale=canvas.width/W;
    ctx.setTransform(scale,0,0,scale,0,0);ctx.clearRect(0,0,W,H);
    ctx.drawImage(base,0,831,W,H-831,0,831,W,H-831);
    ctx.drawImage(base,480,832,105,30,480,808,105,54);
    const pose=solve(hand.x,hand.y);
    // Head is a separate part: small cursor-directed neck movement, fixed body.
    ctx.save();ctx.translate(gaze.x*5,gaze.y*3);
    ctx.translate(540,815);ctx.rotate(gaze.x*.02);ctx.translate(-540,-815);
    ctx.drawImage(base,0,0,W,831,0,0,W,831);
    const blinkTime=(now-blinkStart)/1000;
    const closure=blinkTime>=0&&blinkTime<.18 ? Math.sin(blinkTime/.18*Math.PI) : 0;
    for(const eye of eyes){
      ctx.save();ctx.clip(eye.path);
      const lid=ctx.createLinearGradient(eye.x-80,eye.y-100,eye.x+100,eye.y+100);
      lid.addColorStop(0,'#ac76ff');lid.addColorStop(.55,'#8650f9');lid.addColorStop(1,'#713deb');
      ctx.fillStyle=lid;ctx.fillRect(eye.x-130,eye.y-120,260,240);
      ctx.save();ctx.beginPath();ctx.ellipse(eye.x,eye.y,125,115*(1-closure),0,0,Math.PI*2);ctx.clip();
      ctx.drawImage(clean,...eye.white,...eye.dest);
      const px=eye.x+gaze.x*34,py=eye.y+gaze.y*25;
      ctx.save();ctx.beginPath();ctx.ellipse(px,py,eye.rx,eye.ry,0,0,Math.PI*2);ctx.clip();
      ctx.drawImage(original,...eye.box,px-eye.rx,py-eye.ry,eye.rx*2,eye.ry*2);
      ctx.restore();ctx.restore();
      if(closure>.95){ctx.beginPath();ctx.moveTo(eye.x-80,eye.y);ctx.quadraticCurveTo(eye.x,eye.y+20,eye.x+80,eye.y);ctx.strokeStyle='#24103f';ctx.lineWidth=5;ctx.stroke();}
      ctx.restore();
    }
    ctx.restore();
    // A reaching hand can pass in front of the face; drawing it behind the head
    // makes an upward reach disappear at the chin.
    drawArm(pose);
    root.dataset.animation=mode;
    root.dataset.handX=pose.wrist.x.toFixed(1);root.dataset.handY=pose.wrist.y.toFixed(1);
    root.dataset.gazeX=gaze.x.toFixed(3);root.dataset.gazeY=gaze.y.toFixed(3);
    root.dataset.camera=String(mode==='wave');
    root.dataset.grip=openHand?'open':'closed';
  }
  let clean;
  async function load(){
    if(loaded||loading)return;loading=true;
    try{
      let source,wave;
      [original,clean,source,wave]=await Promise.all([
        image('assets/images/contact-alien.png'),image('assets/alien/puppet-clean-plate.png'),
        image('assets/images/contact-alien-kling-reference.png'),image('assets/images/contact-alien-wave.png')]);
      base=makeLayer();const b=base.getContext('2d');b.drawImage(source,0,0);
      // Chroma-key the already cleaned original reference once, not every frame.
      const pixels=b.getImageData(0,0,W,H),data=pixels.data;
      for(let n=0;n<data.length;n+=4){const excess=data[n+1]-Math.max(data[n],data[n+2]);data[n+3]=Math.round(clamp((65-excess)/50,0,1)*255);if(data[n+3]<250)data[n+1]=Math.min(data[n+1],Math.max(data[n],data[n+2]));}
      b.putImageData(pixels,0,0);
      b.save();b.clip(repairPath);b.clearRect(250,820,230,340);b.drawImage(clean,0,0);b.restore();
      arm=makeLayer();const a=arm.getContext('2d');a.clip(armPath);a.drawImage(original,0,0);
      armWithoutHand=makeLayer();const ah=armWithoutHand.getContext('2d');ah.drawImage(arm,0,0);ah.clearRect(250,1080,240,100);
      palm=makeLayer();const p=palm.getContext('2d');p.beginPath();p.moveTo(802,864);p.lineTo(799,820);p.quadraticCurveTo(804,775,838,805);p.quadraticCurveTo(865,740,891,766);p.quadraticCurveTo(907,774,899,792);p.quadraticCurveTo(946,771,948,815);p.lineTo(942,835);p.quadraticCurveTo(987,828,973,863);p.quadraticCurveTo(945,899,888,935);p.lineTo(849,945);p.lineTo(819,911);p.closePath();p.clip();p.drawImage(wave,0,0);
      const points=new Map();
      const vertex=(x,y)=>{const key=x+':'+y;if(!points.has(key)){const p={x,y};points.set(key,p);vertices.push(p);}return points.get(key);};
      for(let y=820;y<1160;y+=34)for(let x=260;x<480;x+=55){const a=vertex(x,y),b=vertex(x+55,y),c=vertex(x+55,y+34),d=vertex(x,y+34);mesh.push([a,b,c],[a,c,d]);}
      canvas.width=600;canvas.height=800;loaded=true;canvas.hidden=false;root.classList.add('is-ready');refresh();
    }catch(error){root.dataset.animation='fallback';}finally{loading=false;}
  }
  function tick(now){
    raf=0;if(!active())return;
    const dt=Math.min(.035,previous?(now-previous)/1000:1/60);previous=now;
    if(mode==='idle'){
      if(pointer.active&&reachEligible&&nearSince&&now-nearSince>350&&now>=nextReach){mode='reach';modeStart=now;nearSince=0;reachEligible=false;nextReach=now+9000+Math.random()*7000;}
      else if(now>=nextWave){mode='wave';modeStart=now;nextWave=now+15000+Math.random()*9000;}
    }else if((mode==='reach'&&(!pointer.active||!nearLast||now-modeStart>2400))||(mode==='wave'&&now-modeStart>2100))mode='idle';
    if(now>=nextBlink){blinkStart=now;nextBlink=now+3500+Math.random()*3500;}
    let hx=wrist.x,hy=wrist.y;
    if(mode==='reach'){
      const dx=pointer.x-shoulder.x,dy=pointer.y-shoulder.y,d=Math.hypot(dx,dy)||1;
      const projected=solve(shoulder.x+dx*(Math.max(0,d-18)/d),shoulder.y+dy*(Math.max(0,d-18)/d));
      hx=projected.wrist.x;hy=projected.wrist.y;
    }else if(mode==='wave'){
      const t=(now-modeStart)/1000;hx=235+Math.sin(t*9)*23;hy=720+Math.cos(t*9)*8;
    }
    [hand.x,hand.vx]=damp(hand.x,hand.vx,hx,dt,mode==='reach'?25:19);
    [hand.y,hand.vy]=damp(hand.y,hand.vy,hy,dt,mode==='reach'?25:19);
    const away=Math.hypot(hand.x-wrist.x,hand.y-wrist.y);
    if(mode==='reach')openHand=now-modeStart<650&&away>35;
    else if(mode==='wave'&&away>35)openHand=true;
    else if(away<25)openHand=false;
    const dx=pointer.x-540,dy=pointer.y-600,d=Math.hypot(dx,dy,330);
    const gx=pointer.active&&mode!=='wave'?dx/d:0,gy=pointer.active&&mode!=='wave'?dy/d:0;
    [gaze.x,gaze.vx]=damp(gaze.x,gaze.vx,gx,dt,25);[gaze.y,gaze.vy]=damp(gaze.y,gaze.vy,gy,dt,25);
    render(now);raf=requestAnimationFrame(tick);
  }
  function refresh(){
    cancelAnimationFrame(raf);raf=0;previous=0;mode='idle';nearSince=0;nearLast=false;reachEligible=false;blinkStart=-10000;
    hand.x=wrist.x;hand.y=wrist.y;hand.vx=hand.vy=0;gaze.x=gaze.y=gaze.vx=gaze.vy=0;openHand=false;
    nextWave=performance.now()+9000+Math.random()*5000;nextReach=performance.now()+1500;nextBlink=performance.now()+2500;
    if(loaded){render(performance.now());if(active())raf=requestAnimationFrame(tick);}
  }
  function leave(){pointer.active=false;nearSince=0;nearLast=false;reachEligible=false;}
  document.addEventListener('pointermove',event=>{
    if(!active()||!fine.matches||event.pointerType==='touch')return;
    const box=canvas.getBoundingClientRect();if(!box.width||!box.height)return;
    pointer={x:(event.clientX-box.left)/box.width*W,y:(event.clientY-box.top)/box.height*H,active:true};
    const near=Math.hypot(pointer.x-shoulder.x,pointer.y-shoulder.y)<330&&pointer.y>550&&pointer.y<1180;
    if(!near){nearSince=0;reachEligible=false;}
    else if(!nearLast){nearSince=performance.now();reachEligible=Math.random()<.55;}
    nearLast=near;
  },{passive:true});
  document.documentElement.addEventListener('pointerleave',leave);fine.addEventListener('change',()=>{leave();refresh();});
  reduced.addEventListener('change',()=>{leave();refresh();});
  document.addEventListener('visibilitychange',()=>{leave();refresh();});
  window.addEventListener('pagehide',()=>{leave();cancelAnimationFrame(raf);raf=0;});
  window.addEventListener('pageshow',refresh);
  if('IntersectionObserver'in window)new IntersectionObserver(entries=>{visible=entries.some(e=>e.isIntersecting);if(visible)load();else leave();refresh();},{rootMargin:'100px'}).observe(root);
  else{visible=true;load();}
})();
