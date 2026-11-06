/* Automatic animation from whole native video frames. */
(() => {
  'use strict';
  const manifest = window.VegaAlienIdle;
  if (!manifest) return;
  const second = manifest.videoPrefix === 'tariffs';
  const clip = second
    ? {src:'tariffs/idle-full.webp', width:320, height:326, columns:10, frames:241, fps:24}
    : {src:'cursor-video/library/legs.webp', width:300, height:400, columns:10, frames:190, fps:24};
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');

  for (const root of document.querySelectorAll('[data-contact-alien]')) {
    const canvas = root.querySelector('canvas');
    const context = canvas?.getContext('2d');
    if (!context) continue;
    canvas.width = clip.width;
    canvas.height = clip.height;
    let ready = false, visible = false, raf = 0, previous = null;
    let position = 0, direction = 1, image = null;

    function draw() {
      const frame = Math.round(position);
      context.clearRect(0, 0, clip.width, clip.height);
      context.drawImage(image, frame % clip.columns * clip.width,
        Math.floor(frame / clip.columns) * clip.height, clip.width, clip.height,
        0, 0, clip.width, clip.height);
      root.dataset.animation = reduced.matches ? 'still' : 'idle';
      root.dataset.frame = String(frame);
    }
    function queue() {
      if (!raf && ready && visible && !document.hidden && !reduced.matches) {
        raf = requestAnimationFrame(tick);
      }
    }
    function tick(now) {
      raf = 0;
      if (!ready || !visible || document.hidden || reduced.matches) return;
      const dt = previous === null ? 0 : Math.min(100, Math.max(0, now - previous));
      previous = now;
      const distance = direction > 0 ? clip.frames - 1 - position : position;
      const speed = clip.fps * Math.max(.2, Math.min(1, distance / 6));
      position += direction * speed * dt / 1000;
      if (position >= clip.frames - 1) { position = clip.frames - 1; direction = -1; }
      if (position <= 0) { position = 0; direction = 1; }
      draw();
      queue();
    }
    function refresh() {
      cancelAnimationFrame(raf);
      raf = 0;
      previous = null;
      if (ready && reduced.matches) { position = 0; draw(); }
      queue();
    }
    function load() {
      if (image) return;
      image = new Image();
      image.onload = () => {
        ready = true;
        draw();
        canvas.hidden = false;
        root.classList.add('is-ready');
        queue();
      };
      image.onerror = () => { root.dataset.animation = 'fallback'; };
      image.src = 'assets/alien/' + clip.src;
    }
    document.addEventListener('visibilitychange', refresh);
    reduced.addEventListener('change', refresh);
    window.addEventListener('pagehide', () => {
      cancelAnimationFrame(raf); raf = 0; previous = null;
    });
    window.addEventListener('pageshow', refresh);
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(entries => {
        visible = entries.some(entry => entry.isIntersecting);
        if (visible) load();
        refresh();
      }, {rootMargin:'100px'}).observe(root);
    } else { visible = true; load(); }
  }
})();
