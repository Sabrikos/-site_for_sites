(() => {
  'use strict';
  const root = document.querySelector('[data-contact-alien]');
  if (!root) return;
  const canvas = root.querySelector('canvas');
  const context = canvas?.getContext('2d');
  if (!context) return;
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const fine = matchMedia('(hover: hover) and (pointer: fine)');
  const clips = {};
  const pendingClips = new Map();
  const failedClips = new Set();
  let bodyImage = null;
  let visible = false, loaded = false, loading = false, raf = 0, previous = 0;
  let gesture = null, target = .5, position = .5, lastDraw = '';
  let targetY = .5, positionY = .5, gazeDirection = 'right', gazeAmount = 0;
  let cursorX = 270, cursorY = 202;
  let pointerEngaged=false, gazePhase=0, targetPhase=0;
  const wrap=value=>((value%1)+1)%1;
  let nextBlink = 0, nextWave = 0, nearSince = 0, nextReach = 0, nearActive = false;
  const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
  const running = () => loaded && visible && !document.hidden && !reduced.matches;
  const available = name => !failedClips.has(name) && !failedClips.has(name+'Bridge');
  function drawGaze() {
    const clip = clips.gaze;
    if (!clip) return false;
    let sample,lastFrame;
    if(clip.ring){
      const phase=wrap(gazePhase),anchors=clip.anchors;
      let i=1;while(i<anchors.length-1&&phase>anchors[i][0])i++;
      const a=anchors[i-1],b=anchors[i];sample=a[1]+(phase-a[0])/(b[0]-a[0])*(b[1]-a[1]);lastFrame=clip.frames-1;
    }else{
      const path=clip.directions[gazeDirection];sample=path.start+gazeAmount*(path.frames-1);lastFrame=path.start+path.frames-1;
    }
    const first=Math.floor(sample), last=Math.min(lastFrame,first+1), fraction=sample-first;
    const key='gaze:'+sample.toFixed(3);
    if(lastDraw===key) return true;
    context.clearRect(0,0,300,400);
    // Blend only adjacent frames of one continuous recording, never different
    // directions or separate arm poses. The seated body stays fixed.
    context.globalCompositeOperation='lighter';
    for(const [frame,weight] of [[first,1-fraction],[last,fraction]]) {
      if(!weight) continue;
      context.globalAlpha=weight;
      context.drawImage(clip.image,(frame%clip.columns)*300,Math.floor(frame/clip.columns)*240,300,240,0,0,300,240);
    }
    context.globalAlpha=1;context.globalCompositeOperation='source-over';
    if(bodyImage) context.drawImage(bodyImage,0,240,300,160,0,240,300,160);
    lastDraw=key;
    root.dataset.animation='gaze';root.dataset.frame=String(Math.round(sample));
    root.dataset.gazeX=position.toFixed(3);root.dataset.gazeY=positionY.toFixed(3);
    root.dataset.gazeDirection=gazeDirection;root.dataset.gazeAmount=gazeAmount.toFixed(3);
    if(clip.ring)root.dataset.gazePhase=wrap(gazePhase).toFixed(4);
    return true;
  }
  function draw(name, number) {
    const clip = clips[name];
    if (!clip) return;
    const frame = clamp(Math.round(number), 0, clip.frames - 1);
    if (!Number.isFinite(frame)) return;
    const key = name + ':' + frame;
    if (lastDraw === key) return;
    context.clearRect(0, 0, canvas.width, canvas.height);
    context.drawImage(clip.image, (frame % clip.columns) * clip.width,
      Math.floor(frame / clip.columns) * clip.height, clip.width, clip.height,
      0, 0, canvas.width, canvas.height);
    if (bodyImage && (name === 'head' || name === 'blink')) {
      context.clearRect(0, 240, 300, 160);
      context.drawImage(bodyImage, 0, 240, 300, 160, 0, 240, 300, 160);
    }
    lastDraw = key;
    root.dataset.animation = name;
    root.dataset.frame = String(frame);
  }
  function resetTimers(now) {
    nextBlink = now + 3200 + Math.random() * 3500;
    nextWave = now + 11000 + Math.random() * 10000;
    nextReach = now + 4000;
    nearSince = 0; nearActive = false;
  }
  function begin(name, now) {
    if (gesture) return false;
    if(clips.gaze?.ring&&pointerEngaged&&name!=='reach')return false;
    const bridgeName = name + 'Bridge';
    if (failedClips.has(name) || failedClips.has(bridgeName)) return false;
    if (!clips[name] || (window.VegaAlienMotion && !clips[bridgeName])) {
      if (pendingClips.has(name) || pendingClips.has(bridgeName)) return false;
      Promise.all([ensureClip(name), ...(window.VegaAlienMotion ? [ensureClip(bridgeName)] : [])]).then(() => { if (running() && (name !== 'reach' || (nearSince && performance.now() - nearSince > 450))) begin(name, performance.now()); }).catch(() => {});
      return false;
    }
    if (name === 'wave') { delete clips.reach; delete clips.reachBridge; }
    if (name === 'reach') { delete clips.wave; delete clips.waveBridge; }
    if (name === 'reach') { nextReach = now + 9000 + Math.random()*7000; nearSince = 0; }
    gesture = {name, queuedAt:now, started:clips.gaze?.ring?now:(clips.head || clips.gaze) ? null : now, phase:'entry'};
    return true;
  }
  function tick(now) {
    raf = 0;
    if (!running()) return;
    const dt = previous ? Math.min(60, now - previous) : 16;
    previous = now;
    const anchor = clips.gaze ? .5 : clips.head?.anchor ?? .5;
    const ring=clips.gaze?.ring;
    const follow = (value, desired) => value + clamp((desired-value)*(1-Math.exp(-dt/90)),-dt*.0028,dt*.0028);
    position = follow(position, gesture&&!ring ? anchor : target);
    positionY = follow(positionY, gesture&&!ring ? .5 : targetY);
    if(ring){
      let desired=wrap(targetPhase);
      for(const [a,b] of clips.gaze.blocked||[]){
        if(desired>a&&desired<b){
          const distance=p=>Math.abs(Math.atan2(Math.sin((p-gazePhase)*Math.PI*2),Math.cos((p-gazePhase)*Math.PI*2)));
          desired=distance(a)<=distance(b)?a:b;
        }
      }
      let delta=wrap(desired-wrap(gazePhase)+.5)-.5;
      if(Math.abs(delta+.5)<1e-9)delta=.5;
      gazePhase+=clamp(delta*(1-Math.exp(-dt/100)),-dt*.0008,dt*.0008);
    }
    const dx=position-.5,dy=positionY-.5;
    const vertical=gazeDirection==='up'||gazeDirection==='down';
    // Hysteresis prevents rapid axis swapping around diagonal boundaries.
    const useVertical=vertical ? Math.abs(dy)*1.25>=Math.abs(dx) : Math.abs(dy)>Math.abs(dx)*1.25;
    const direction=useVertical ? (dy<0?'up':'down') : (dx<0?'left':'right');
    const amount=(gesture&&!ring)||direction!==gazeDirection ? 0 : Math.min(1,Math.abs(useVertical?dy:dx)*2);
    gazeAmount+=clamp((amount-gazeAmount)*(1-Math.exp(-dt/80)),-dt*.0045,dt*.0045);
    if(!gesture && direction!==gazeDirection && gazeAmount<.004) {gazeAmount=0;gazeDirection=direction;}

    if (!gesture) {
      if ((!ring||!pointerEngaged) && now >= nextWave && window.VegaAlienClips.wave && available('wave')) {
        begin('wave', now); nextWave = now + 15000 + Math.random() * 10000;
      } else if (nearSince && now - nearSince > 450 && now >= nextReach && window.VegaAlienClips.reach && available('reach')) {
        begin('reach', now);
      } else if ((!ring||!pointerEngaged) && now >= nextBlink) {
        begin('blink', now); nextBlink = now + 4500 + Math.random() * 3500;
      }
    }
    if(gesture?.name==='reach'&&!nearActive){
      const bridge=clips.reachBridge;
      const entry=bridge?(bridge.frames/2-1)/bridge.fps*1000:0;
      if(gesture.started===null||now-gesture.started<entry)gesture=null;
    }
    if (gesture && gesture.started === null) {
      if (Math.abs(position - anchor) < .004 && Math.abs(positionY-.5)<.004 && gazeAmount<.004) {
        position=anchor; positionY=.5; gazeAmount=0; gesture.started = now;
      }
      else {
        if(!drawGaze()) draw('head', (clips.head.from ?? 0) + position * ((clips.head.to ?? clips.head.frames - 1) - (clips.head.from ?? 0)));
        raf = requestAnimationFrame(tick);
        return;
      }
    }
    if (gesture) {
      const clip = clips[gesture.name];
      const bridge=clips[gesture.name+'Bridge'];
      let elapsed=(now-gesture.started)/1000;
      if(ring){gesture.elapsed=(gesture.elapsed??-dt)+dt*(pointerEngaged&&gesture.name!=='reach'?3:1);elapsed=gesture.elapsed/1000;}
      const bridgeDuration=bridge ? (bridge.frames/2-1)/bridge.fps : 0;
      const duration=(clip.frames-1)/clip.fps;
      const track=clip.track;
      if(track && elapsed>=bridgeDuration && (elapsed>=bridgeDuration+track.start/clip.fps || gesture.outAt!==undefined || !nearActive)){
        if(gesture.trackAt===undefined){gesture.trackAt=now;gesture.trackFrame=Math.min(track.start,(elapsed-bridgeDuration)*clip.fps);}
        if(gesture.outAt===undefined){
          if(nearActive && (ring || now-gesture.trackAt<track.holdMs)){
            // Select complete, coherent Kling poses near the cursor. No arm
            // mesh, synthetic pupils or separately overlaid limbs are rendered.
            let nearest=track.points[0],distance=Infinity;
            for(const point of track.points){const d=Math.hypot(point[1]-cursorX,point[2]-cursorY);if(d<distance){distance=d;nearest=point;}}
            gesture.trackFrame+=clamp((nearest[0]-gesture.trackFrame)*(1-Math.exp(-dt/75)),-dt*.045,dt*.045);
          }else{gesture.outAt=now;gesture.outFrame=track.returnMirror===undefined?gesture.trackFrame:track.returnMirror-gesture.trackFrame;}
        }
        const frame=gesture.outAt===undefined?gesture.trackFrame:gesture.outFrame+(now-gesture.outAt)/1000*clip.fps;
        elapsed=bridgeDuration+frame/clip.fps;
      }
      if (bridge && elapsed < bridgeDuration) {
        draw(gesture.name+'Bridge',elapsed*bridge.fps);
      } else if (elapsed < bridgeDuration+duration) {
        gesture.phase='play'; draw(gesture.name,(elapsed-bridgeDuration)*clip.fps);
      } else if (bridge && elapsed < bridgeDuration*2+duration) {
        gesture.phase='exit'; draw(gesture.name+'Bridge',bridge.frames/2+(elapsed-bridgeDuration-duration)*bridge.fps);
      } else {
        if(gesture.name==='reach'&&ring)nextReach=now+9000+Math.random()*7000;
        gesture=null;
      }
    }
    if (!gesture) {
      const head = clips.head;
      if (drawGaze()) { /* Each direction connects through the shared neutral pose. */ }
      else if (head) draw('head', (head.from ?? 0) + position * ((head.to ?? head.frames - 1) - (head.from ?? 0)));
      else draw('blink', 0);
    }
    raf = requestAnimationFrame(tick);
  }
  function refresh() {
    cancelAnimationFrame(raf); raf = 0; previous = 0; gesture = null;
    if (!loaded) return;
    if (reduced.matches) { target = position = targetY = positionY = .5; gazeAmount=0; draw('blink', 0); }
    resetTimers(performance.now());
    if (running()) raf = requestAnimationFrame(tick);
  }
  function ensureClip(name) {
    if (clips[name]) return Promise.resolve();
    if (pendingClips.has(name)) return pendingClips.get(name);
    const data = name==='gaze' ? window.VegaAlienMotion?.gaze : name.endsWith('Bridge') ? window.VegaAlienMotion?.bridges[name.slice(0,-6)] : window.VegaAlienClips?.[name];
    if (!data) return Promise.reject(new Error('Missing clip'));
    const promise = (async () => {
      const image = new Image();
      image.src = 'assets/alien/' + data.src;
      await image.decode();
      if (!(data.frames > 0 && data.width > 0 && data.height > 0 && data.columns > 0 && data.fps > 0)) throw new Error('Invalid clip');
      clips[name] = {...data, image};
      failedClips.delete(name);
    })().catch(error => { failedClips.add(name); throw error; }).finally(() => pendingClips.delete(name));
    pendingClips.set(name, promise);
    return promise;
  }
  async function load() {
    if (loaded || loading) return;
    loading = true;
    try {
      const manifest = window.VegaAlienClips;
      if (!manifest) throw new Error('Animation manifest unavailable');
      await ensureClip('blink');
      const poster = new Image();
      poster.src = 'assets/alien/wave-poster.png';
      await poster.decode(); bodyImage = poster;
      if (window.VegaAlienMotion) await ensureClip('gaze');
      else if (manifest.head) ensureClip('head').catch(() => {});
      if (!clips.blink) throw new Error('Base animation unavailable');
      loaded = true;
      draw('blink', 0);
      canvas.hidden = false;
      root.classList.add('is-ready');
      refresh();
    } catch (_) {
      // The original image remains available when assets cannot load.
      root.dataset.animation = 'fallback';
    } finally { loading = false; }
  }
  document.addEventListener('pointermove', event => {
    if (!running() || !fine.matches || event.pointerType === 'touch') return;
    const box = root.getBoundingClientRect();
    if (!box.width) return;
    target = clamp(.5 + (event.clientX - box.left - box.width * .52) / (box.width * 2.5), 0, 1);
    targetY = clamp(.5 + (event.clientY - box.top - box.height * .4) / (box.height*1.3),0,1);
    cursorX=(event.clientX-box.left)/box.width*300;
    cursorY=(event.clientY-box.top)/box.height*400;
    pointerEngaged=true;
    const dx=cursorX-155,dy=cursorY-160;
    if(Math.hypot(dx,dy)>14)targetPhase=wrap(-Math.atan2(dy,dx)/(Math.PI*2));
    const points=window.VegaAlienClips?.reach?.track?.points;
    const near = points ? points.some(p=>Math.hypot(cursorX-p[1],cursorY-p[2])<34) : event.clientX > box.left + box.width*.58 && Math.hypot(
      event.clientX-box.left-box.width*.76,event.clientY-box.top-box.height*.61)<box.width*.23;
    if (!near) nearSince = 0;
    else if (!nearActive) nearSince = Math.random()<.55 ? performance.now() : 0;
    nearActive=near;
  }, {passive:true});
  function leave(){pointerEngaged=false;if(!clips.gaze?.ring)target=targetY=.5;nearSince=0;nearActive=false;nextWave=performance.now()+10000;nextBlink=performance.now()+3500;}
  document.documentElement.addEventListener('pointerleave',leave);
  fine.addEventListener('change',leave);
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(entries => {
      visible = entries.some(entry => entry.isIntersecting);
      if (visible) load();
      refresh();
    }, {rootMargin:'100px', threshold:0}).observe(root);
  } else { visible = true; load(); }
  reduced.addEventListener('change', refresh);
  document.addEventListener('visibilitychange', refresh);
  window.addEventListener('pagehide', () => { cancelAnimationFrame(raf); raf = 0; });
  window.addEventListener('pageshow', refresh);
})();
