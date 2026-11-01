(() => {
  const alien = document.querySelector('[data-contact-alien]');
  if (!alien || !window.matchMedia) return;
  const image = alien.querySelector('.contact-alien__original');
  const waveImage = alien.querySelector('.contact-alien__wave');
  const waveReady = () => waveImage?.complete && waveImage.naturalWidth > 0;
  const motion = matchMedia('(prefers-reduced-motion: reduce)');
  const finePointer = matchMedia('(hover: hover) and (pointer: fine)');
  let visible = false;
  let frame = 0;
  let blinkTimer = 0;
  let waveTimer = 0;
  let grabTimer = 0;
  let lastGrab = 0;
  const allowed = () => visible && !document.hidden && !motion.matches && getComputedStyle(alien).display !== 'none';
  const stopTimers = () => { clearTimeout(blinkTimer); clearTimeout(waveTimer); clearTimeout(grabTimer); cancelAnimationFrame(frame); frame = 0; alien.classList.remove('is-blinking', 'is-waving', 'is-reaching'); alien.style.removeProperty('--alien-eye-x'); alien.style.removeProperty('--alien-eye-y'); };
  const scheduleBlink = () => { clearTimeout(blinkTimer); if (!allowed()) return; blinkTimer = setTimeout(() => { if (!allowed()) return; alien.classList.add('is-blinking'); blinkTimer = setTimeout(() => { alien.classList.remove('is-blinking'); scheduleBlink(); }, 190); }, 4000 + Math.random() * 4000); };
  const scheduleWave = () => { clearTimeout(waveTimer); if (!allowed()) return; waveTimer = setTimeout(() => { if (!allowed()) return; if (waveReady()) alien.classList.add('is-waving'); waveTimer = setTimeout(() => { alien.classList.remove('is-waving'); scheduleWave(); }, 2200); }, 9500 + Math.random() * 6500); };
  const update = () => { stopTimers(); if (allowed()) { scheduleBlink(); scheduleWave(); } };
  const activate = () => { if (!image.naturalWidth) return; alien.classList.add('is-ready'); update(); };
  if (image.complete) activate(); else image.addEventListener('load', activate, { once: true });
  if ('IntersectionObserver' in window) new IntersectionObserver(entries => { visible = entries.some(entry => entry.isIntersecting); update(); }, { threshold: 0.08 }).observe(alien);
  else { visible = true; update(); }
  document.addEventListener('pointermove', event => {
    if (frame || !visible || document.hidden || motion.matches || !finePointer.matches || event.pointerType === 'touch') return;
    const x = event.clientX, y = event.clientY;
    frame = requestAnimationFrame(() => {
      frame = 0;
      if (!allowed()) return;
      const box = alien.getBoundingClientRect();
      const dx = x - (box.left + box.width * 0.55), dy = y - (box.top + box.height * 0.42);
      const distance = Math.hypot(dx, dy);
      alien.style.setProperty('--alien-eye-x', `${Math.max(-10, Math.min(10, dx / 36)).toFixed(1)}px`);
      alien.style.setProperty('--alien-eye-y', `${Math.max(-8, Math.min(8, dy / 42)).toFixed(1)}px`);
      if (waveReady() && distance < box.width * 0.8 && Date.now() - lastGrab > 5000) {
        lastGrab = Date.now();
        alien.style.setProperty('--alien-reach-x', `${Math.max(-18, Math.min(18, dx / 8)).toFixed(1)}px`);
        alien.style.setProperty('--alien-reach-y', `${Math.max(-12, Math.min(12, dy / 10)).toFixed(1)}px`);
        alien.classList.add('is-reaching');
        clearTimeout(grabTimer); grabTimer = setTimeout(() => alien.classList.remove('is-reaching'), 1200);
      }
    });
  }, { passive: true });
  document.addEventListener('visibilitychange', update);
  motion.addEventListener?.('change', update);
  finePointer.addEventListener?.('change', update);
  window.addEventListener('pagehide', stopTimers);
  window.addEventListener('pageshow', event => { if (event.persisted) update(); });
})();
