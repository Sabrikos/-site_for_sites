/* Motion AI Kit guidance: compositor transforms, restrained gestures, reduced motion. */
(() => {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  const { gsap, ScrollTrigger, Lenis, Motion } = window;
  let lenis;
  let context;
  let stopHover;
  const tick = time => lenis?.raf(time * 1000);
  function start() {
    if (reduced.matches) return;
    if (gsap && ScrollTrigger) {
      gsap.registerPlugin(ScrollTrigger);
      if (Lenis && matchMedia('(pointer: fine)').matches) {
        lenis = new Lenis({ duration: 1.05, smoothWheel: true, anchors: { offset: -110 },
          prevent: node => node.tagName === 'DIALOG' || !!node.closest('dialog') });
        lenis.on('scroll', ScrollTrigger.update);
        gsap.ticker.add(tick);
        gsap.ticker.lagSmoothing(0);
      }
      context = gsap.context(() => {
        gsap.from('.hero-copy > *', { y: 20, opacity: 0, duration: .85,
          stagger: .09, ease: 'power2.out', clearProps: 'all' });
        gsap.from('.hero-visual', { y: 24, opacity: 0, duration: 1.15,
          ease: 'power2.out', clearProps: 'all' });
        document.querySelectorAll('.section-heading, .story-copy, .seasonal-copy, .custom-copy, .process-grid > div, .closing h2').forEach(el => {
          gsap.from(el, { y: 24, opacity: 0, duration: .75, ease: 'power2.out',
            clearProps: 'all', scrollTrigger: { trigger: el, start: 'top 93%', once: true } });
        });
      });
      document.querySelectorAll('dialog').forEach(dialog => {
        new MutationObserver(() => document.querySelector('dialog[open]') ? lenis?.stop() : lenis?.start())
          .observe(dialog, { attributes: true, attributeFilter: ['open'] });
      });
      document.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click', () => requestAnimationFrame(() => ScrollTrigger.refresh())));
      document.fonts.ready.then(() => ScrollTrigger.refresh());
    }
    if (Motion) {
      stopHover = Motion.hover('.product-arrow, .heart-button', el => {
        Motion.animate(el, { transform: 'scale(1.08)' }, { type: 'spring', bounce: .12, duration: .35 });
        return () => Motion.animate(el, { transform: 'scale(1)' }, { type: 'spring', bounce: .12, duration: .35 });
      });
    }
  }
  reduced.addEventListener('change', () => {
    stopHover?.(); context?.revert(); if (gsap) gsap.ticker.remove(tick); lenis?.destroy(); lenis = null;
    start();
  });
  start();
})();
