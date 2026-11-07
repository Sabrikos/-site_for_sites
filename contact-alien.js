(() => {
  'use strict';
  const root = document.querySelector('[data-contact-alien]');
  const canvas = root?.querySelector('canvas');
  const context = canvas?.getContext('2d');
  const manifest = window.VegaAlienIdle;
  if (!context || !manifest) return;
  if (window.startVegaAlienCursor?.(root, manifest)) return;
  if (window.startVegaAlienLive?.(root, manifest)) return;
  if (manifest.videoPrefix && window.startVegaAlienVideo?.(root, manifest)) return;
  const width = manifest.width ?? 300, height = manifest.height ?? 400;
  const mark = manifest.mark ?? [230, 370, 70, 30];
  const loopDuration = Number.isFinite(manifest.loopDurationMs) ? manifest.loopDurationMs : 0;
  const bodyY = manifest.freezeBodyY === null ? null : manifest.freezeBodyY ?? 240;
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const finePointer = matchMedia('(hover: hover) and (pointer: fine)');
  const clips = new Map(), pending = new Map(), failed = new Set();
  const clamp = (n, min, max) => Math.max(min, Math.min(max, n));
  const ease = t => t <= 0 ? 0 : t >= 1 ? 1 : t * t * (3 - 2 * t);
  let poster, loaded = false, loading = false, visible = false;
  let current = null, elapsed = 0, previous = 0, raf = 0;
  let nextBlink = Infinity, nextWave = Infinity;
  let gazeFrames = null, gazePending = null, gazeFailed = false;
  let pointerActive = false, trackingBlend = 0, lastPointerAt = 0;
  const gazeWeights = {center: 1, left: 0, right: 0, up: 0, down: 0};
  let targetX = 0, targetY = 0, gazeX = 0, gazeY = 0;
  let currentGaze = 'center', fromGaze = 'center', gazeStarted = 0;
  const GAZE_FADE_MS = 210, TRACK_FADE_MS = 260;
  // Smooth idle breathing anchored at the bottom centre.
  const BREATH_PERIOD = 4200, BREATH_X = 0.004, BREATH_Y = 0.011;
  const BLINK_GAP = 2600, BLINK_SPREAD = 2600, WAVE_GAP = 9000, WAVE_SPREAD = 5000;
  const BRIDGE_SLOWDOWN = 1.35;
  let breathTime = 0;
  const running = () => loaded && visible && !document.hidden && !reduced.matches;
  const later = (now, minimum, spread) => now + minimum + Math.random() * spread;
  function breathe() {
    const s = Math.sin(breathTime / BREATH_PERIOD * Math.PI * 2);
    context.setTransform(1 + s * BREATH_X, 0, 0, 1 + s * BREATH_Y,
      -s * BREATH_X * width / 2, -s * BREATH_Y * height);
  }
  function finish(key, frame) {
    context.setTransform(1, 0, 0, 1, 0, 0);
    if (mark?.length === 4) context.clearRect(...mark);
    root.dataset.animation = key; root.dataset.frame = String(frame);
  }
  function sprite(clip, frame) {
    context.drawImage(clip.image, frame % clip.columns * clip.width,
      Math.floor(frame / clip.columns) * clip.height, clip.width, clip.height, 0, 0, width, height);
  }
  function picture(src) {
    return new Promise((resolve, reject) => {
      const image = new Image();
      image.onload = () => resolve(image);
      image.onerror = reject;
      image.src = 'assets/alien/' + src;
      if (image.decode) image.decode().then(() => resolve(image), reject);
    });
  }
  function idle() {
    if (!poster) return;
    context.setTransform(1, 0, 0, 1, 0, 0); context.clearRect(0, 0, width, height);
    breathe(); context.drawImage(poster, 0, 0, width, height);
    finish('idle', 0);
  }
  function draw(clip, position, key, fixedBody = false) {
    const last = clip.frames - 1;
    const p = Math.max(0, Math.min(last, position));
    const frame = Math.min(last, Math.floor(p)), mix = frame < last ? p - frame : 0;
    context.setTransform(1, 0, 0, 1, 0, 0); context.clearRect(0, 0, width, height);
    breathe();
    if (mix < 0.01) sprite(clip, frame);
    else if (mix > 0.99) sprite(clip, frame + 1);
    else {
      context.globalCompositeOperation = 'lighter';
      context.globalAlpha = 1 - mix; sprite(clip, frame);
      context.globalAlpha = mix; sprite(clip, frame + 1);
      context.globalAlpha = 1; context.globalCompositeOperation = 'source-over';
    }
    if (fixedBody && bodyY !== null) {
      context.clearRect(0, bodyY, width, height - bodyY);
      context.drawImage(poster, 0, bodyY, width, height - bodyY, 0, bodyY, width, height - bodyY);
    }
    finish(key, Math.round(p));
  }
  function drawAtlasBlend(clip, position, alpha) {
    if (alpha <= 0) return;
    const p = ((position % clip.frames) + clip.frames) % clip.frames;
    const frame = Math.floor(p), next = (frame + 1) % clip.frames;
    const mix = p - frame;
    context.globalAlpha = alpha * (1 - mix); sprite(clip, frame);
    if (mix > 0.01 && next !== frame) {
      context.globalAlpha = alpha * mix; sprite(clip, next);
    }
    context.globalAlpha = 1;
  }
  function drawGazeBlend(now, alpha) {
    if (!gazeFrames || alpha <= 0) return;
    const col = Math.round(clamp((gazeX + 1) * 8, 0, 16));
    const row = Math.round(clamp((gazeY + 1) * 8, 0, 16));
    context.globalAlpha = alpha;
    context.drawImage(gazeFrames, col * width, row * height, width, height, 0, 0, width, height);
    context.globalAlpha = 1;
  }
  function drawInteractive(clip, position, key, now) {
    const frame = Math.round(position) % clip.frames;
    context.setTransform(1, 0, 0, 1, 0, 0); context.clearRect(0, 0, width, height);
    breathe();
    context.globalCompositeOperation = 'lighter';
    const blend = gazeFrames ? clamp(trackingBlend, 0, 1) : 0;
    drawAtlasBlend(clip, position, 1 - blend);
    drawGazeBlend(now, blend);
    context.globalAlpha = 1; context.globalCompositeOperation = 'source-over';
    finish(blend > 0.55 ? 'gaze' : key, frame);
  }
  function drawLoop(clip, position, key) {
    const p = ((position % clip.frames) + clip.frames) % clip.frames;
    const frame = Math.floor(p), next = (frame + 1) % clip.frames, mix = p - frame;
    context.setTransform(1, 0, 0, 1, 0, 0); context.clearRect(0, 0, width, height);
    breathe();
    if (mix < 0.01) sprite(clip, frame);
    else {
      context.globalCompositeOperation = 'lighter';
      context.globalAlpha = 1 - mix; sprite(clip, frame);
      context.globalAlpha = mix; sprite(clip, next);
      context.globalAlpha = 1; context.globalCompositeOperation = 'source-over';
    }
    finish(key, Math.round(p) % clip.frames);
  }
  function ensure(name) {
    if (clips.has(name)) return Promise.resolve(clips.get(name));
    if (pending.has(name)) return pending.get(name);
    const data = manifest[name];
    const bridgeImage = loopDuration > 0 || !data.bridge ? Promise.resolve(null) : picture(data.bridge.src);
    const promise = Promise.all([picture(data.src), bridgeImage]).then(([image, bridge]) => {
      const clip = {...data, image, bridge: bridge ? {...data.bridge, image: bridge} : null};
      clips.set(name, clip); return clip;
    }).catch(() => { failed.add(name); return null; }).finally(() => pending.delete(name));
    pending.set(name, promise);
    return promise;
  }
  function ensureGaze() {
    if (!manifest.gaze || gazeFrames || gazeFailed) return Promise.resolve(gazeFrames);
    if (gazePending) return gazePending;
    gazePending = picture(manifest.gaze.grid).then(image => {
      gazeFrames = image;
      trackingBlend = 0;
      if (running()) queue();
      return gazeFrames;
    }).catch(() => { gazeFailed = true; return null; }).finally(() => { gazePending = null; });
    return gazePending;
  }
  function queue() {
    if (!raf && running()) raf = requestAnimationFrame(tick);
  }
  function begin(name) {
    if (current || pending.has(name) || failed.has(name)) return;
    ensure(name).then(clip => {
      if (!clip || current || !running()) return;
      current = name; elapsed = 0; queue();
    });
  }
  function setPointer(event, now) {
    const box = root.getBoundingClientRect();
    if (!box.width || !box.height) return;
    const origin = manifest.gaze.origin || [0.5, 0.34];
    const ox = box.left + box.width * origin[0], oy = box.top + box.height * origin[1];
    const vw = document.documentElement.clientWidth || window.innerWidth || box.width;
    const vh = document.documentElement.clientHeight || window.innerHeight || box.height;
    const dx = event.clientX - ox, dy = event.clientY - oy;
    targetX = clamp(dx / Math.max(1, dx < 0 ? ox : vw - ox), -1, 1);
    targetY = clamp(dy / Math.max(1, dy < 0 ? oy : vh - oy), -1, 1);
    currentGaze = Math.abs(targetX) > Math.abs(targetY) ? (targetX < 0 ? 'left' : 'right') : (targetY < 0 ? 'up' : 'down');
    root.dataset.gazeDirection = (targetY < -0.15 ? 'up' : targetY > 0.15 ? 'down' : '') + (targetX < -0.15 ? 'left' : targetX > 0.15 ? 'right' : '') || 'center';
    gazeStarted = now;
  }
  function resetPointer() {
    pointerActive = false;
    delete root.dataset.gazeDirection;
    queue();
  }
  function tick(now) {
    raf = 0; if (!running()) return;
    const dt = previous ? Math.min(100, Math.max(0, now - previous)) : 0;
    previous = now;
    breathTime = (breathTime + dt) % BREATH_PERIOD;
    if (pointerActive && now - lastPointerAt >= 5000) { pointerActive = false; delete root.dataset.gazeDirection; }
    const blendFactor = 1 - Math.exp(-dt / 150);
    gazeX += (targetX - gazeX) * blendFactor;
    gazeY += (targetY - gazeY) * blendFactor;
    const ax = Math.abs(gazeX), ay = Math.abs(gazeY);
    gazeWeights.center = (1 - ax) * (1 - ay);
    const horizontal = ax * (1 - ay / 2), vertical = ay * (1 - ax / 2);
    gazeWeights.left = gazeX < 0 ? horizontal : 0;
    gazeWeights.right = gazeX > 0 ? horizontal : 0;
    gazeWeights.up = gazeY < 0 ? vertical : 0;
    gazeWeights.down = gazeY > 0 ? vertical : 0;
    const targetBlend = pointerActive && gazeFrames && finePointer.matches && !reduced.matches ? 1 : 0;
    const blendStep = dt / TRACK_FADE_MS;
    trackingBlend = targetBlend > trackingBlend
      ? Math.min(targetBlend, trackingBlend + blendStep)
      : Math.max(targetBlend, trackingBlend - blendStep);
    if (loopDuration > 0) {
      if (!current && !pending.size && !failed.has('wave')) begin('wave');
      if (current === 'wave' && clips.has('wave')) {
        elapsed = (pointerActive && gazeFrames) || trackingBlend > 0.05 ? 0 : (elapsed + dt) % loopDuration;
        const clip = clips.get('wave');
        if (manifest.gaze) drawInteractive(clip, elapsed / loopDuration * clip.frames, 'waveLoop', now);
        else drawLoop(clip, elapsed / loopDuration * clip.frames, 'waveLoop');
      } else idle();
    } else {
      if (!current && !pending.size) {
        if (now >= nextWave) {
          nextWave = later(now, WAVE_GAP, WAVE_SPREAD); begin('wave');
        } else if (now >= nextBlink) {
          nextBlink = later(now, BLINK_GAP, BLINK_SPREAD); begin('blink');
        }
      }
      if (current) {
        elapsed += dt;
        const clip = clips.get(current), bridge = clip.bridge;
        const half = bridge.frames / 2 - 1;
        const entry = half / bridge.fps * 1000 * BRIDGE_SLOWDOWN;
        const duration = (clip.frames - 1) / clip.fps * 1000;
        if (elapsed < entry) draw(bridge, ease(elapsed / entry) * half, current + 'Bridge');
        else if (elapsed < entry + duration) draw(clip, (elapsed - entry) / 1000 * clip.fps, current, current === 'blink');
        else if (elapsed < entry + duration + entry) draw(bridge, bridge.frames / 2 + ease((elapsed - entry - duration) / entry) * half, current + 'Bridge');
        else { current = null; nextBlink = later(now, BLINK_GAP, BLINK_SPREAD); idle(); }
      } else idle();
    }
    if (targetBlend !== trackingBlend || (pointerActive && gazeFrames && performance.now() - gazeStarted < GAZE_FADE_MS)) queue();
    else if (loopDuration > 0 && current === 'wave') queue();
    else if (current) queue();
    else if (running()) queue();
  }
  function refresh() {
    cancelAnimationFrame(raf); raf = 0; previous = 0;
    if (!loaded) return;
    if (reduced.matches) {
      pointerActive = false; trackingBlend = 0; delete root.dataset.gazeDirection;
      current = null; elapsed = 0; idle();
    } else if (loopDuration > 0 && !current && !pending.size && !failed.has('wave')) begin('wave');
    if (running()) queue();
  }
  async function load() {
    if (loaded || loading) return; loading = true;
    try {
      poster = await picture(manifest.poster); loaded = true;
      canvas.width = width; canvas.height = height; canvas.hidden = false;
      idle(); root.classList.add('is-ready');
      const now = performance.now();
      if (loopDuration > 0 && !reduced.matches) {
        if (await ensure('wave')) { current = 'wave'; elapsed = 0; }
      } else {
        nextBlink = later(now, 1800, 1500); nextWave = later(now, 4500, 2500);
      }
      refresh();
    } catch (_) { root.dataset.animation = 'fallback'; }
    finally { loading = false; }
  }
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(entries => {
      visible = entries.some(entry => entry.isIntersecting);
      if (visible) load(); refresh();
    }, {rootMargin: '100px'}).observe(root);
  } else { visible = true; load(); }
  document.addEventListener('pointermove', event => {
    if (!running() || !finePointer.matches || event.pointerType === 'touch' || !manifest.gaze) return;
    pointerActive = true; lastPointerAt = performance.now();
    setPointer(event, lastPointerAt);
    ensureGaze(); queue();
  }, {passive: true});
  document.documentElement.addEventListener('pointerleave', resetPointer);
  finePointer.addEventListener('change', () => { if (!finePointer.matches) resetPointer(); });
  reduced.addEventListener('change', refresh);
  document.addEventListener('visibilitychange', refresh);
  window.addEventListener('pagehide', () => { cancelAnimationFrame(raf); raf = 0; previous = 0; });
  window.addEventListener('pageshow', refresh);
})();
