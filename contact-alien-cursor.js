/* Plays whole native frames from the paid video library. */
window.startVegaAlienCursor = function (root, manifest) {
  const data = window.VegaAlienNative;
  if (manifest.videoPrefix !== 'home' || !data?.clips?.length) return false;
  const canvas = root.querySelector('canvas');
  const context = canvas?.getContext('2d');
  if (!context) return false;
  canvas.width = data.width; canvas.height = data.height;
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const clips = data.clips.map(clip => ({...clip, image:null, loading:false, failed:false}));
  const rest = clips.find(clip => clip.id === 'rest');
  const primary = clips.find(clip => clip.id === 'gaze');
  const safeReturn = new Set(clips.filter(clip => clip.id==='rest' ||
    clip.links?.rest?.some(link=>link[1]<=.065)).map(clip=>clip.id));
  if (!rest || !primary) return false;
  let active = rest, position = 0, idleDirection = 1, idleCycles = 0;
  let ready = false, visible = true, pointer = null, inactive = Infinity;
  let raf = 0, previous = 0, x = 0, y = 0, mode = 'idle', route = null;
  const clamp = (v,a,b) => Math.max(a,Math.min(b,v));
  const poseDistance = (a,b) => {
    let sum = 0;
    for (let i=0;i<a.length;i++) sum += Math.abs(a[i]-b[i]);
    return sum / (a.length * 255);
  };
  function load(clip) {
    if (clip.image || clip.loading || clip.failed) return;
    clip.loading = true;
    const image = new Image();
    image.onload = () => {
      clip.image = image; clip.loading = false;
      // Retain the idle source, current source and the newly requested source.
      for (const cached of clips) {
        if (cached!==rest && cached!==active && cached!==clip && cached!==route?.destination.clip) cached.image=null;
      }
      if (clip === rest && !ready) {
        ready = true; draw(0,'idle'); canvas.hidden=false;
        root.classList.add('is-ready');
      }
      queue();
    };
    image.onerror = () => {
      clip.loading = false; clip.failed = true;
      if (clip === rest) root.dataset.animation = 'fallback';
    };
    image.src = 'assets/alien/cursor-video/'+clip.src;
  }
  function draw(frame,state) {
    if (!active.image) return;
    frame=clamp(frame,0,active.count-1);
    context.clearRect(0,0,data.width,data.height);
    context.drawImage(active.image,frame%data.columns*data.width,
      Math.floor(frame/data.columns)*data.height,data.width,data.height,
      0,0,data.width,data.height);
    root.dataset.animation=state;
    root.dataset.clip=active.id; root.dataset.frame=String(frame);
    const gaze=active.gaze[frame];
    root.dataset.gazeX=gaze?String(gaze[0]):'';
    root.dataset.gazeY=gaze?String(gaze[1]):'';
  }
  function queue() {
    if (!raf && ready && visible && !document.hidden) raf=requestAnimationFrame(tick);
  }
  function nearest() {
    let best = null;
    for (const clip of clips) {
      if (clip.failed || clip.role !== 'tracking' || !safeReturn.has(clip.id)) continue;
      for (let frame=0;frame<clip.gaze.length;frame++) {
        const gaze=clip.gaze[frame];
        if (!gaze) continue;
        const connection=clip.links?.[active.id]?.[frame];
        if (clip!==active && (!connection || connection[1]>.065)) continue;
        const posePenalty = clip===active ? 0 : connection[1];
        const score=(gaze[0]-x)**2+(gaze[1]-y)**2+posePenalty*.5+(clip===active?0:.035);
        if (!best || score<best.score) best={clip,frame,score};
      }
    }
    return best;
  }
  function plan(destination) {
    load(destination.clip);
    if (!destination.clip.image) return null;
    if (destination.clip===active) return {exit:destination.frame,destination};
    const connection=destination.clip.links?.[active.id]?.[destination.frame];
    if (!connection || connection[1]>.065) return null;
    return {exit:connection[0],destination};
  }

  function advance(dt) {
    if (!route) return true;
    const delta=route.exit-position;
    const speed=clamp(Math.abs(delta)/.3,active.fps*1.5,active.fps*10);
    position+=Math.sign(delta)*Math.min(Math.abs(delta),speed*dt/1000);
    if (Math.abs(route.exit-position)>.01) return false;
    active=route.destination.clip;position=route.destination.frame;route=null;
    return true;
  }
  function tick(now) {
    raf=0;
    if (!ready || !visible || document.hidden) return;
    const dt=previous?clamp(now-previous,0,50):16;previous=now;
    inactive+=dt;
    if (reduced.matches) {
      active=rest;position=0;route=null;mode='idle';draw(0,'still');return;
    }
    const tracking=pointer && inactive<1600;
    if (tracking) {
      const box=root.getBoundingClientRect();
      const ox=box.left+box.width*.5,oy=box.top+box.height*.43;
      const vw=document.documentElement.clientWidth||innerWidth;
      const vh=document.documentElement.clientHeight||innerHeight;
      const tx=clamp((pointer.x-ox)/Math.max(100,(pointer.x<ox?ox:vw-ox)*.65),-1,1);
      const ty=clamp((pointer.y-oy)/Math.max(100,(pointer.y<oy?oy:vh-oy)*.65),-1,1);
      const blend=1-Math.exp(-dt/110);
      x+=(tx-x)*blend;y+=(ty-y)*blend;
      const target=nearest();
      if (target) {
        const currentGaze=active.gaze[Math.round(position)];
        const error=currentGaze?(currentGaze[0]-x)**2+(currentGaze[1]-y)**2:Infinity;
        if (!route && (target.clip!==active || error>target.score+.015)) route=plan(target);
      }
      mode='tracking';
      advance(dt);
      draw(Math.round(position),'tracking');
    } else {
      if (mode==='tracking') {
        // Enter a full idle sequence at the closest available whole-frame pose.
        const pose=active.poses[Math.round(position)];
        let frame=0,best=Infinity;
        for (let i=0;i<rest.count;i++) {
          const connection=rest.links?.[active.id]?.[i];
          if (active!==rest && (!connection || connection[1]>.065)) continue;
          const cost=poseDistance(pose,rest.poses[i]);
          if (cost<best) {best=cost;frame=i;}
        }
        route=plan({clip:rest,frame});
        // Select an explicitly validated return connection, not an arbitrary rest frame.
        mode='return';
      }
      if (mode==='return') {
        if (advance(dt)) {mode='idle';idleDirection=1;}
        draw(Math.round(position),'return');
      } else {
        const remaining=idleDirection>0?active.count-1-position:position;
        position+=idleDirection*dt/1000*active.fps*clamp(remaining/8,.22,1);
        if (position>=active.count-1) {
          position=active.count-1;idleDirection=-1;
          const nextIdle=clips.find(clip=>clip.id===(active===rest?'legs':'rest'));
          if (nextIdle) load(nextIdle);
        }
        if (position<=0) {
          position=0;idleDirection=1;idleCycles++;
          const other=clips.find(clip=>clip.id==='legs');
          const next=idleCycles%2?other:rest;
          if (next && !next.failed && next!==active) {
            load(next);
            if (next.image) {
              const transition=plan({clip:next,frame:0});
              if (transition) {route=transition;mode='return';}
            }
          }
        }
        draw(Math.round(position),'idle');
      }
    }
    queue();
  }
  function refresh() {cancelAnimationFrame(raf);raf=0;previous=0;queue();}
  document.addEventListener('pointermove',event=>{
    if (event.pointerType==='touch' || reduced.matches) return;
    pointer={x:event.clientX,y:event.clientY};inactive=0;queue();
  },{passive:true});
  function leave() {pointer=null;inactive=Infinity;queue();}
  document.documentElement.addEventListener('pointerleave',leave);
  window.addEventListener('blur',leave);
  document.addEventListener('visibilitychange',refresh);
  reduced.addEventListener('change',()=>{leave();refresh();});
  window.addEventListener('pagehide',()=>{cancelAnimationFrame(raf);raf=0;previous=0;});
  window.addEventListener('pageshow',refresh);
  if ('IntersectionObserver' in window) new IntersectionObserver(entries=>{
    visible=entries.some(entry=>entry.isIntersecting);refresh();
  },{rootMargin:'100px'}).observe(root);
  load(rest);load(primary);
  // Additional sources are decoded only when a compatible route needs them.
  return true;
};
