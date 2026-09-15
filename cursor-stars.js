(() => {
    const STAR_COUNT = 240;
    // Match the density of the former three CSS star tiles, without repeating them.
    const BACKGROUND_STAR_LAYERS = [
        { area: 140 * 140, size: 2, opacity: 0.18, hue: 0.2 },
        { area: 190 * 190, size: 1, opacity: 0.34, hue: 0.6 },
        { area: 220 * 220, size: 2, opacity: 0.18, hue: 0.35 },
    ];
    const scatterSeed = Math.random() * 100000;
    const ACTIVE_RADIUS = 170;
    const MAX_DEVICE_PIXEL_RATIO = 1;
    const EXCLUDE_PADDING = 12;

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d', { alpha: true });
    const stars = [];

    let width = 0;
    let height = 0;
    let worldHeight = 0;
    let mouseX = -9999;
    let mouseY = -9999;
    let mouseInside = false;
    let frameId = null;
    let dpr = 1;

    canvas.className = 'stars-canvas';
    canvas.setAttribute('aria-hidden', 'true');
    document.body.prepend(canvas);

    function random(seed) {
        const value = Math.sin(seed) * 10000;
        return value - Math.floor(value);
    }

    function getDocumentHeight() {
        return Math.max(
            document.body.scrollHeight,
            document.documentElement.scrollHeight,
            window.innerHeight
        );
    }

    function buildStars() {
        stars.length = 0;
        worldHeight = getDocumentHeight();

        for (let i = 0; i < STAR_COUNT; i++) {
            const preferRight = i % 4 === 0;
            const preferContentGap = i % 5 === 0;
            let x = random(i * 13.17 + 8) * width;
            let y = random(i * 19.91 + 4) * worldHeight;

            if (preferRight) {
                x = width * (0.72 + random(i * 31.13) * 0.26);
            }

            if (preferContentGap) {
                x = width * (0.18 + random(i * 17.77) * 0.68);
            }

            stars.push({
                x,
                y,
                size: 0.7 + random(i * 7.31) * 1.7,
                opacity: 0.22 + random(i * 11.83) * 0.38,
                hue: random(i * 5.43),
            });
        }

        if (document.body.classList.contains('home-page')) {
            let index = 0;
            for (const layer of BACKGROUND_STAR_LAYERS) {
                const count = Math.round(width * worldHeight / layer.area);
                for (let i = 0; i < count; i++, index++) {
                    const x = random(scatterSeed + index * 41.37 + 1) * width;
                    const colorChoice = random(scatterSeed + index * 53.29 + 7);
                    // Recolor some blue stars on the left; preserve their positions and density.
                    const recolor = x < width * 0.5 && layer.hue < 0.45 && colorChoice < 0.45;
                    const hue = recolor ? (colorChoice < 0.20 ? 0.6 : 0.85) : layer.hue;
                    stars.push({
                        x,
                        y: random(scatterSeed + index * 67.91 + 2) * worldHeight,
                        size: layer.size * (0.8 + random(index * 23.17 + 3) * 0.4),
                        opacity: recolor ? Math.max(layer.opacity, 0.26) : layer.opacity,
                        hue,
                        ambient: true,
                    });
                }
            }
        }
    }

    function resize() {
        dpr = Math.min(window.devicePixelRatio || 1, MAX_DEVICE_PIXEL_RATIO);
        width = window.innerWidth;
        height = window.innerHeight;

        canvas.width = Math.round(width * dpr);
        canvas.height = Math.round(height * dpr);
        canvas.style.width = `${width}px`;
        canvas.style.height = `${height}px`;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        buildStars();
        requestDraw();
    }

    function getColor(hue, alpha) {
        if (hue < 0.45) {
            return `rgba(82, 96, 255, ${alpha})`;
        }

        if (hue < 0.75) {
            return `rgba(216, 213, 255, ${alpha})`;
        }

        return `rgba(179, 92, 255, ${alpha})`;
    }

    function getProtectedHeroAreas() {
        const selectors = [
            '.hero-label',
            '.hero-content h1',
            '.hero-content > p',
            '.hero-buttons',
            '.hero-planet',
            '.hero-rocket',
        ];

        return selectors
            .map((selector) => document.querySelector(selector))
            .filter(Boolean)
            .map((element) => element.getBoundingClientRect())
            .filter((rect) => rect.bottom >= 0 && rect.top <= height)
            .map((rect) => ({
                left: rect.left - EXCLUDE_PADDING,
                right: rect.right + EXCLUDE_PADDING,
                top: rect.top - EXCLUDE_PADDING,
                bottom: rect.bottom + EXCLUDE_PADDING,
            }));
    }

    function getProtectedFooterAreas() {
        const footer = document.querySelector('.main-footer');

        if (!footer) {
            return [];
        }

        const footerRect = footer.getBoundingClientRect();

        if (footerRect.bottom < 0 || footerRect.top > height) {
            return [];
        }

        const footerBottom = footer.querySelector('.footer-bottom');
        const lunarSurface = footer.querySelector('.footer-lunar-svg');
        const protectedTopCandidates = [footerRect.top + footerRect.height * 0.55];

        if (footerBottom) {
            protectedTopCandidates.push(footerBottom.getBoundingClientRect().top - 18);
        }

        if (lunarSurface) {
            protectedTopCandidates.push(lunarSurface.getBoundingClientRect().top - 22);
        }

        return [{
            left: footerRect.left,
            right: footerRect.right,
            top: Math.min(...protectedTopCandidates),
            bottom: footerRect.bottom,
        }];
    }
    function isInsideProtectedArea(x, y, protectedAreas) {
        return protectedAreas.some((area) => (
            x >= area.left &&
            x <= area.right &&
            y >= area.top &&
            y <= area.bottom
        ));
    }

    function drawStar(star, screenY) {
        const dx = star.x - mouseX;
        const dy = screenY - mouseY;
        const distance = Math.hypot(dx, dy);
        const influence = mouseInside ? Math.max(0, 1 - distance / ACTIVE_RADIUS) : 0;
        const alpha = Math.min(1, star.opacity + influence * 0.72);
        const glow = star.size * ((star.ambient ? 0 : 2.2) + influence * 7.5);
        const size = star.size * (1 + influence * 0.75);

        if (glow > 0) {
            const gradient = ctx.createRadialGradient(star.x, screenY, 0, star.x, screenY, glow);
            gradient.addColorStop(0, getColor(star.hue, alpha * 0.78));
            gradient.addColorStop(0.32, getColor(star.hue, alpha * 0.22));
            gradient.addColorStop(1, getColor(star.hue, 0));
            ctx.fillStyle = gradient;
            ctx.beginPath();
            ctx.arc(star.x, screenY, glow, 0, Math.PI * 2);
            ctx.fill();
        }

        ctx.fillStyle = getColor(star.hue, alpha);
        ctx.beginPath();
        ctx.arc(star.x, screenY, size, 0, Math.PI * 2);
        ctx.fill();
    }

    function draw() {
        ctx.clearRect(0, 0, width, height);

        const scrollTop = window.scrollY;
        const topLimit = scrollTop - 40;
        const bottomLimit = scrollTop + height + 40;
        const protectedAreas = getProtectedHeroAreas().concat(getProtectedFooterAreas());

        for (const star of stars) {
            if (star.y < topLimit || star.y > bottomLimit) {
                continue;
            }

            const screenY = star.y - scrollTop;

            if (isInsideProtectedArea(star.x, screenY, protectedAreas)) {
                continue;
            }

            drawStar(star, screenY);
        }

        frameId = null;
    }

    function requestDraw() {
        if (frameId === null) {
            frameId = requestAnimationFrame(draw);
        }
    }

    window.addEventListener('mousemove', (event) => {
        mouseX = event.clientX;
        mouseY = event.clientY;
        mouseInside = true;
        requestDraw();
    }, { passive: true });

    window.addEventListener('mouseleave', () => {
        mouseInside = false;
        requestDraw();
    });

    window.addEventListener('scroll', requestDraw, { passive: true });
    window.addEventListener('resize', resize);
    window.addEventListener('load', resize);

    resize();
})();