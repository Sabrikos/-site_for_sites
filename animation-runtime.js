(() => {
    'use strict';

    const gsap = window.gsap ?? null;
    const ScrollTrigger = window.ScrollTrigger ?? null;
    const Lenis = window.Lenis ?? null;
    const Motion = window.Motion ?? null;
    let lenis = null;

    if (gsap && ScrollTrigger) {
        gsap.registerPlugin(ScrollTrigger);
    }

    const runtime = {
        gsap,
        ScrollTrigger,
        Lenis,
        Motion,

        get lenis() {
            return lenis;
        },

        initSmoothScroll(options = {}) {
            if (lenis || !Lenis || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return lenis;
            }

            lenis = new Lenis({
                autoRaf: true,
                anchors: true,
                ...options,
            });

            if (ScrollTrigger) {
                lenis.on('scroll', ScrollTrigger.update);
            }

            return lenis;
        },

        destroySmoothScroll() {
            lenis?.destroy();
            lenis = null;
        },
    };

    Object.defineProperty(window, 'VegaMotion', {
        configurable: false,
        enumerable: true,
        writable: false,
        value: runtime,
    });

    if (document.documentElement.dataset.smoothScroll === 'true') {
        runtime.initSmoothScroll();
    }
})();
