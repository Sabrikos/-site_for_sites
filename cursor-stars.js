(() => {
    const STAR_COUNT = 360;
    const ACTIVE_RADIUS = 170;
    const MAX_DEVICE_PIXEL_RATIO = 1.5;

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

    function drawStar(star, screenY) {
        const dx = star.x - mouseX;
        const dy = screenY - mouseY;
        const distance = Math.hypot(dx, dy);
        const influence = mouseInside ? Math.max(0, 1 - distance / ACTIVE_RADIUS) : 0;
        const alpha = Math.min(1, star.opacity + influence * 0.72);
        const glow = star.size * (2.2 + influence * 7.5);
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

        for (const star of stars) {
            if (star.y < topLimit || star.y > bottomLimit) {
                continue;
            }

            drawStar(star, star.y - scrollTop);
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
