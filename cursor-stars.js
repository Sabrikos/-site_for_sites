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
    let canvasHeight = 0;

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
        // Overscan keeps stars available during compositor-driven scrolling.
        canvasHeight = Math.min(height * 3, getDocumentHeight());
        canvas.height = Math.round(canvasHeight * dpr);
        canvas.style.width = `${width}px`;
        canvas.style.height = `${canvasHeight}px`;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        buildStars();
        requestDraw();
    }

    function getColor(hue, alpha) {
        if (hue === -1) return `rgba(255, 255, 255, ${alpha})`;
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
        const alpha = star.constellation
            ? star.opacity + (1 - star.opacity) * influence
            : Math.min(1, star.opacity + influence * 0.72);
        const glow = star.size * ((star.baseGlow ?? (star.ambient ? 0 : 2.2)) + influence * 7.5);
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

    const constellation = document.querySelector('.cart-vega-constellation');
    const nebulaNote = document.querySelector('.cart-vega-nebula');
    const nebulaPointer = document.querySelector('.cart-vega-pointer');
    const showNebula = (visible) => {
        if (!nebulaNote) return;
        nebulaNote.classList.toggle('is-visible', visible);
        nebulaPointer?.classList.toggle('is-visible', visible);
        nebulaNote.setAttribute('aria-hidden', String(!visible));
    };
    constellation?.addEventListener('focus', requestDraw);
    constellation?.addEventListener('blur', requestDraw);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') showNebula(false);
    });
    function drawConstellation() {
        if (!constellation) return;
        const rect = constellation.getBoundingClientRect();
        if (!rect.width || !rect.height || rect.bottom < 0 || rect.top > height) { showNebula(false); return; }
        // Vega -> Zeta -> Delta -> Gamma -> Beta, matching the supplied chart.
        const points = [[214,25,3],[181,51,1.5],[140,66,1.3],[118,156,1.9],[158,143,1.8]]
            .map(([x,y,size], index) => ({x: rect.left + x / 320 * rect.width,
                y: rect.top + y / 190 * rect.height, size,
                opacity: index === 0 ? .78 : .60, baseGlow: index === 0 ? 4.5 : 3.8,
                constellation: true, hue: -1}));
        if (nebulaNote && nebulaPointer) {
            const vega = points[0];
            const noteWidth = nebulaNote.offsetWidth;
            const x = Math.max(16, Math.min(width - noteWidth - 24, vega.x + 30));
            const y = Math.max(90, vega.y + 62);
            nebulaNote.style.setProperty('--nebula-x', `${x}px`);
            nebulaNote.style.setProperty('--nebula-y', `${y}px`);
            const endX = vega.x + 13, endY = vega.y + 4;
            nebulaPointer.querySelector('.cart-vega-pointer__curve').setAttribute('d',
                `M ${x + noteWidth * .58} ${y + 3} C ${x + noteWidth * .62} ${y - 34}, ${endX + 52} ${endY + 8}, ${endX} ${endY}`);
            nebulaPointer.querySelector('.cart-vega-pointer__head').setAttribute('d',
                `M ${endX + 8} ${endY - 4} L ${endX} ${endY} L ${endX + 6} ${endY + 7}`);
        }
        const hovering = mouseInside &&
            mouseX >= Math.min(...points.map(p => p.x)) - 18 &&
            mouseX <= Math.max(...points.map(p => p.x)) + 18 &&
            mouseY >= Math.min(...points.map(p => p.y)) - 18 &&
            mouseY <= Math.max(...points.map(p => p.y)) + 18;
        showNebula(hovering || document.activeElement === constellation);
        ctx.save();
        ctx.lineWidth = .65;
        for (const [a,b] of [[0,1],[1,2],[2,3],[3,4],[4,1]]) {
            const start = points[a], end = points[b];
            const vx = end.x - start.x, vy = end.y - start.y;
            const t = Math.max(0, Math.min(1, ((mouseX-start.x)*vx + (mouseY-start.y)*vy)/(vx*vx+vy*vy)));
            const distance = Math.hypot(mouseX-start.x-t*vx, mouseY-start.y-t*vy);
            const influence = mouseInside ? Math.max(0, 1-distance/ACTIVE_RADIUS) : 0;
            ctx.strokeStyle = `rgba(255,255,255,${.25 + influence*.4})`;
            ctx.shadowColor = 'rgba(255,255,255,.65)';
            ctx.shadowBlur = 2 + influence*4;
            ctx.beginPath();
            ctx.moveTo(start.x,start.y);
            ctx.lineTo(end.x,end.y);
            ctx.stroke();
        }
        ctx.restore();
        for (const star of points) drawStar(star, star.y);
    }

    const drawTariffConstellations = [];
    for (const config of [
        { anchor: '.cart-canis-major', note: '.cart-canis-nebula', pointer: '.cart-canis-pointer',
          // Traced from the reference; the anchor preserves its 502:456 aspect ratio.
          // Sirius is index 0, shared by the brighter star and the tooltip arrow.
          points: [[81.55,28.88,3],[72.95,5.39,1.9],[95,5,2],[73.31,19.01,1.6],[5,31.45,1.7],[20.96,32.24,1.5],[27.05,36.18,1.9],[25.8,48.42,1.8],[45.16,44.08,1.8],[77.25,47.43,2],[22.93,55.53,1.6],[14.32,74.47,1.7],[41.22,95,2],[62.37,60.46,1.8],[91.24,58.88,2]],
          edges: [[1,2],[2,3],[3,1],[3,0],[0,6],[6,5],[5,4],[6,7],[7,8],[8,9],[9,0],[7,10],[10,11],[10,12],[9,13],[9,14]] },
        { anchor: '.tariffs-ursa-minor', note: '.tariffs-ursa-nebula',
          points: [[84,17,3],[70,28,1.7],[58,43,1.8],[47,64,1.6],[32,54,1.4],[22,78,2],[41,88,2.2]],
          edges: [[0,1],[1,2],[2,3],[3,4],[4,5],[5,6],[6,3]] },
        { anchor: '.tariffs-cassiopeia', note: '.tariffs-cassiopeia-nebula',
          points: [[12,18,2.4],[31,54,1.9],[55,50,2.3],[71,86,2.2],[94,59,2.3]],
          edges: [[0,1],[1,2],[2,3],[3,4]] },
        { anchor: '.tariffs-ursa-major', note: '.tariffs-ursa-major-nebula',
          points: [[10,72,2.3],[26,55,2],[42,49,2.1],[59,47,2.5],[88,22,2.4],[94,57,2.2],[73,73,2.1]],
          edges: [[0,1],[1,2],[2,3],[3,4],[4,5],[5,6],[6,3]] },
        { anchor: '.tariffs-reference-constellation', note: '.tariffs-reference-nebula',
          points: [[14,11,1.8],[22,7,2],[17,27,1.8],[22,24,1.8],[29,38,1.6],[36,43,2.7],[45,27,2],[56,35,2.4],[69,10,1.5],[73,12,1.7],[77,19,2],[78,24,1.6],[79,35,1.7],[77,39,1.5],[60,59,2],[58,64,1.8],[56,68,1.7],[62,75,1.5],[64,78,1.8],[79,78,2.2],[62,95,2.1]],
          edges: [[0,2],[1,3],[2,4],[3,4],[4,5],[5,6],[6,7],[7,10],[8,9],[9,10],[10,11],[11,12],[12,13],[7,14],[14,15],[15,16],[16,5],[15,17],[17,18],[14,19],[19,20],[20,16]] }
    ]) {
    const ursaMinor = document.querySelector(config.anchor);
    const ursaNote = document.querySelector(config.note);
    const notePointer = config.pointer ? document.querySelector(config.pointer) : null;
    let ursaDismissed = false;
    function showUrsaNote(visible) {
        ursaNote?.classList.toggle('is-visible', visible);
        notePointer?.classList.toggle('is-visible', visible);
        ursaNote?.setAttribute('aria-hidden', String(!visible));
    }
    ursaMinor?.addEventListener('focus', () => { ursaDismissed = false; requestDraw(); });
    ursaMinor?.addEventListener('blur', requestDraw);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') { ursaDismissed = true; showUrsaNote(false); }
    });
    const tariffBasket = document.querySelector('.tariffs-layout > .cart');
    if (ursaMinor && tariffBasket) {
        const placeUrsa = () => {
            if (config.anchor.includes('reference-constellation')) {
                const section = document.getElementById('other');
                const layout = document.querySelector('.tariffs-layout');
                if (section) ursaMinor.style.top = `${section.getBoundingClientRect().top - layout.getBoundingClientRect().top - 160}px`;
            } else if (config.anchor.includes('cassiopeia') || config.anchor.includes('ursa-major')) {
                const revision = document.getElementById('revision');
                const layout = document.querySelector('.tariffs-layout');
                const offset = config.anchor.includes('cassiopeia') ? -180 : 60;
                if (revision) ursaMinor.style.top = `${revision.getBoundingClientRect().top - layout.getBoundingClientRect().top + offset}px`;
            } else ursaMinor.style.top = `${tariffBasket.offsetHeight + 40}px`;
            requestDraw();
        };
        new ResizeObserver(placeUrsa).observe(tariffBasket);
        new ResizeObserver(placeUrsa).observe(document.querySelector('.tariffs-content'));
        placeUrsa();
    }
    function drawUrsaMinor() {
        if (!ursaMinor) return;
        const rect = ursaMinor.getBoundingClientRect();
        if (!rect.width || !rect.height || rect.bottom < 0 || rect.top > height) { showUrsaNote(false); return; }
        // Seven-star Little Dipper: Polaris, curved handle, and closed bowl.
        const points = config.points
            .map(([x,y,size], i) => ({ x: rect.left + rect.width*x/100,
                y: rect.top + rect.height*y/100, size, hue: -1,
                opacity: i === 0 ? .78 : .57, baseGlow: i === 0 ? 4.5 : 3.8,
                constellation: true }));
        const hovering = mouseInside && mouseX >= Math.min(...points.map(p => p.x)) - 18 &&
            mouseX <= Math.max(...points.map(p => p.x)) + 18 &&
            mouseY >= Math.min(...points.map(p => p.y)) - 18 && mouseY <= Math.max(...points.map(p => p.y)) + 18;
        const focused = document.activeElement === ursaMinor;
        if (!hovering && !focused) ursaDismissed = false;
        if (ursaNote) {
            const cassiopeia = config.anchor.includes('cassiopeia');
            const contentLeft = document.querySelector('.tariffs-content')?.getBoundingClientRect().left ?? 0;
            if (cassiopeia) ursaNote.style.width = `${Math.max(40, Math.min(160, contentLeft - 40))}px`;
            const desiredX = notePointer ? rect.right + 32 : cassiopeia ? rect.left + (rect.width - ursaNote.offsetWidth)/2 : rect.left + rect.width*.55;
            const x = Math.max(16, Math.min(cassiopeia ? contentLeft - ursaNote.offsetWidth - 20 : width - ursaNote.offsetWidth - 16, desiredX));
            const below = Math.max(...points.map(p => p.y)) + 24;
            const desiredY = notePointer ? points[0].y + 24 : cassiopeia ? (below + ursaNote.offsetHeight < height - 24 ? below : Math.min(...points.map(p => p.y)) - ursaNote.offsetHeight - 24) : rect.top + rect.height*.68;
            const y = Math.max(90, Math.min(height - ursaNote.offsetHeight - 24, desiredY));
            ursaNote.style.setProperty('--nebula-x', `${x}px`);
            ursaNote.style.setProperty('--nebula-y', `${y}px`);
            if (notePointer) {
                const sirius = points[0];
                const startX = x + ursaNote.offsetWidth * .2, startY = y + 5;
                const endX = sirius.x + 12, endY = sirius.y;
                notePointer.querySelector('.cart-vega-pointer__curve').setAttribute('d',
                    `M ${startX} ${startY} C ${startX - 25} ${startY - 40}, ${endX + 55} ${endY - 20}, ${endX} ${endY}`);
                notePointer.querySelector('.cart-vega-pointer__head').setAttribute('d',
                    `M ${endX + 7} ${endY - 7} L ${endX} ${endY} L ${endX + 9} ${endY + 3}`);
            }
            // Suppress labels near the sticky basket, including the nebula's halo.
            const basketRect = tariffBasket?.getBoundingClientRect();
            const clearance = 12;
            const nearBasket = (area) => basketRect &&
                area.right > basketRect.left - clearance &&
                area.left < basketRect.right + clearance &&
                area.bottom > basketRect.top - clearance &&
                area.top < basketRect.bottom + clearance;
            const constellationArea = {
                left: Math.min(...points.map(p => p.x)) - 18,
                right: Math.max(...points.map(p => p.x)) + 18,
                top: Math.min(...points.map(p => p.y)) - 18,
                bottom: Math.max(...points.map(p => p.y)) + 18,
            };
            const noteArea = { left: x - 14, right: x + ursaNote.offsetWidth + 14,
                top: y - 14, bottom: y + ursaNote.offsetHeight + 14 };
            const blocked = nearBasket(constellationArea) || nearBasket(noteArea);
            showUrsaNote((hovering || focused) && !ursaDismissed && !blocked);
            ursaNote.classList.toggle('is-obstructed', Boolean(blocked));
        }
        ctx.save();
        ctx.lineWidth = .7;
        for (const [a,b] of config.edges) {
            const start = points[a], end = points[b];
            const vx = end.x-start.x, vy = end.y-start.y;
            const t = Math.max(0, Math.min(1, ((mouseX-start.x)*vx+(mouseY-start.y)*vy)/(vx*vx+vy*vy)));
            const distance = Math.hypot(mouseX-start.x-t*vx, mouseY-start.y-t*vy);
            const influence = mouseInside ? Math.max(0,1-distance/ACTIVE_RADIUS) : 0;
            ctx.strokeStyle = `rgba(255,255,255,${.25+influence*.4})`;
            ctx.shadowColor = 'rgba(255,255,255,.65)';
            ctx.shadowBlur = 2+influence*4;
            ctx.beginPath(); ctx.moveTo(start.x,start.y); ctx.lineTo(end.x,end.y); ctx.stroke();
        }
        ctx.restore();
        points.forEach(star => drawStar(star,star.y));
    }

        drawTariffConstellations.push(drawUrsaMinor);
    }

    function draw() {
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, width, canvasHeight);

        const scrollTop = window.scrollY;
        const origin = Math.max(0, Math.min(scrollTop - height, worldHeight - canvasHeight));
        const bodyTop = document.body.getBoundingClientRect().top + scrollTop;
        canvas.style.top = `${origin - bodyTop}px`;
        ctx.translate(0, scrollTop - origin);
        const topLimit = origin - 40;
        const bottomLimit = origin + canvasHeight + 40;
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

        drawConstellation();
        drawTariffConstellations.forEach(draw => draw());
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
