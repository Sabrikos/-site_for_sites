/* Recorded motion: play forward/reverse to a target; no per-frame video seeking. */
window.startVegaAlienVideo = function (root, manifest) {
  const canvas = root.querySelector('canvas'), ctx = canvas.getContext('2d');
  if (!ctx) return false;
  const w = manifest.width || 300, h = manifest.height || 400;
  canvas.width = w; canvas.height = h;
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const scratch = document.createElement('canvas'); scratch.width = w; scratch.height = h;
  const key = scratch.getContext('2d', {willReadFrequently: true});
  const poster = new Image(), wave = new Image();
  poster.src = 'assets/alien/' + manifest.poster;
  wave.src = 'assets/alien/' + manifest.wave.src;
  const pairs = {};
  let active = null, axis = 'x', target = 0.5, pointerPresent = false, lastPoint = null;
  let visible = false, raf = 0, previous = 0, idleTime = 0, failed = false;
  const clamp = (v,a,b) => Math.max(a,Math.min(b,v));
  function finishFrame() {
    if (manifest.videoPrefix === 'home') ctx.clearRect(230,370,70,30);
  }
  function pause() { for (const p of Object.values(pairs)) for (const v of p) v.pause(); }
  function load(axisName) {
    if (pairs[axisName]) return pairs[axisName];
    pairs[axisName] = ['', '-reverse'].map(suffix => {
      const v = document.createElement('video');
      v.muted = true; v.playsInline = true; v.preload = 'auto';
      v.src = 'assets/alien/video/' + manifest.videoPrefix + '-' + axisName + suffix + '.mp4';
      v.addEventListener('error', () => { failed = true; pause(); });
      v.load(); return v;
    });
    return pairs[axisName];
  }
  function play(v) { if (v.paused) v.play().catch(() => {}); }
  function steer() {
    const pair = load(axis);
    if (!pair.every(v => v.readyState >= 2 && Number.isFinite(v.duration))) return null;
    const duration = pair[0].duration;
    if (!active || active.axis !== axis) {
      pause(); active = {axis, reverse:false, video:pair[0]};
      // One seek on entering a different clip, rather than a seek on every animation frame.
      active.video.currentTime = clamp(duration / 2, 0, duration - 0.04);
    }
    let v = active.video;
    const position = active.reverse ? duration - v.currentTime : v.currentTime;
    const desired = target * duration, error = desired - position;
    if (Math.abs(error) < 0.045) { v.pause(); return v; }
    const reverse = error < 0;
    if (reverse !== active.reverse) {
      v.pause(); v = pair[reverse ? 1 : 0];
      v.currentTime = clamp(reverse ? duration - position : position, 0, v.duration - 0.04);
      active = {axis, reverse, video:v};
    }
    v.playbackRate = clamp(Math.abs(error) * 2.5, 0.5, 2);
    if (!v.seeking) play(v);
    return v;
  }
  function drawVideo(v) {
    if (v.readyState < 2 || v.seeking) return false;
    key.drawImage(v, 0, 0, w, h);
    const pixels = key.getImageData(0,0,w,h), data = pixels.data;
    for (let i=0;i<data.length;i+=4) {
      const excess = data[i+1] - Math.max(data[i], data[i+2]);
      const alpha = clamp((65-excess)/50,0,1);
      data[i+3] = Math.round(alpha*255);
      if (alpha < 0.99) data[i+1] = Math.min(data[i+1],Math.max(data[i],data[i+2]));
    }
    key.putImageData(pixels,0,0);
    ctx.clearRect(0,0,w,h); ctx.drawImage(scratch,0,0);
    root.dataset.animation = 'video-gaze';
    return true;
  }
  function drawIdle(dt) {
    if (!wave.complete || !wave.naturalWidth || reduced.matches) {
      if (poster.complete && poster.naturalWidth) { ctx.clearRect(0,0,w,h); ctx.drawImage(poster,0,0,w,h); }
      return;
    }
    idleTime += dt;
    const clip = manifest.wave;
    const phase = idleTime / (manifest.loopDurationMs || 5200) * Math.PI;
    const frame = Math.round((1-Math.cos(phase)) / 2 * (clip.frames-1));
    ctx.clearRect(0,0,w,h);
    ctx.drawImage(wave,frame%clip.columns*clip.width,Math.floor(frame/clip.columns)*clip.height,clip.width,clip.height,0,0,w,h);
    root.dataset.animation = 'waveLoop';
  }
  function tick(now) {
    raf = 0;
    if (!visible || document.hidden) { pause(); previous = 0; return; }
    const dt = previous ? Math.min(now-previous,80) : 0; previous = now;
    const tracking = !failed && !reduced.matches && pointerPresent;
    if (tracking) { const v = steer(); if (v) drawVideo(v); else drawIdle(dt); }
    else { pause(); active = null; drawIdle(dt); }
    finishFrame();
    if (!reduced.matches) raf = requestAnimationFrame(tick);
  }
  function refresh() { cancelAnimationFrame(raf); raf=0; previous=0; pause(); if (visible && !document.hidden) raf=requestAnimationFrame(tick); }
  function point(event) {
    if (reduced.matches || event.pointerType === 'touch') return;
    const box=root.getBoundingClientRect(), origin=manifest.gaze.origin;
    const x=(event.clientX-box.left-box.width*origin[0])/Math.max(box.width,window.innerWidth/3);
    const y=(event.clientY-box.top-box.height*origin[1])/Math.max(box.height,window.innerHeight/3);
    // Axis hysteresis prevents chatter when crossing a diagonal.
    if (axis==='x' && Math.abs(y)>Math.abs(x)*1.4) axis='y';
    else if (axis==='y' && Math.abs(x)>Math.abs(y)*1.4) axis='x';
    target=clamp(((axis==='x'?x:y)+1)/2,0.02,0.98);
    pointerPresent=true; lastPoint={clientX:event.clientX,clientY:event.clientY}; load(axis);
  }
  document.addEventListener('pointermove',point,{passive:true});
  document.addEventListener('pointerdown',point,{passive:true});
  document.documentElement.addEventListener('pointerleave',()=>{pointerPresent=false;lastPoint=null;});
  window.addEventListener('blur',()=>{pointerPresent=false;lastPoint=null;});
  window.addEventListener('scroll',()=>{if (lastPoint) point(lastPoint);},{passive:true});
  new IntersectionObserver(entries=>{visible=entries.some(e=>e.isIntersecting);refresh();},{rootMargin:'50px'}).observe(root);
  document.addEventListener('visibilitychange',refresh);
  reduced.addEventListener('change',refresh);
  window.addEventListener('pagehide',()=>{pause();cancelAnimationFrame(raf);});
  window.addEventListener('pageshow',refresh);
  poster.onload=()=>{canvas.hidden=false;root.classList.add('is-ready');refresh();};
  return true;
};
