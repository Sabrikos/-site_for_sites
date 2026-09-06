(() => {
    const SPEED = 16;
    const FPS = 24;
    const FRAME_INTERVAL = 1000 / FPS;
    const GAP = '        ';
    const SAMPLE_COUNT = 90;
    const DPR_LIMIT = 1.25;

    const rowStyles = [
        { size: 6.8, alpha: 0.30 },
        { size: 7.6, alpha: 0.42 },
        { size: 8.4, alpha: 0.56 },
        { size: 9.1, alpha: 0.70 },
        { size: 10, alpha: 0.84 },
        { size: 10, alpha: 0.84 },
        { size: 10, alpha: 0.84 },
        { size: 9.1, alpha: 0.70 },
        { size: 8.4, alpha: 0.56 },
        { size: 7.6, alpha: 0.42 },
        { size: 6.8, alpha: 0.30 },
    ];

    let visible = true;
    let lastFrameTime = 0;

    function pointOnCubic(path, t) {
        const mt = 1 - t;
        const mt2 = mt * mt;
        const t2 = t * t;

        return {
            x: mt2 * mt * path.x0 + 3 * mt2 * t * path.x1 + 3 * mt * t2 * path.x2 + t2 * t * path.x3,
            y: mt2 * mt * path.y0 + 3 * mt2 * t * path.y1 + 3 * mt * t2 * path.y2 + t2 * t * path.y3,
        };
    }

    function tangentOnCubic(path, t) {
        const mt = 1 - t;

        return {
            x: 3 * mt * mt * (path.x1 - path.x0) + 6 * mt * t * (path.x2 - path.x1) + 3 * t * t * (path.x3 - path.x2),
            y: 3 * mt * mt * (path.y1 - path.y0) + 6 * mt * t * (path.y2 - path.y1) + 3 * t * t * (path.y3 - path.y2),
        };
    }

    function parseCubicPath(pathElement) {
        const d = pathElement.getAttribute('d') || '';
        const values = d.match(/-?\d+(?:\.\d+)?/g)?.map(Number) || [];

        if (values.length < 8) {
            return null;
        }

        return {
            x0: values[0],
            y0: values[1],
            x1: values[2],
            y1: values[3],
            x2: values[4],
            y2: values[5],
            x3: values[6],
            y3: values[7],
        };
    }

    function buildSamples(path) {
        const samples = [];
        let previous = pointOnCubic(path, 0);
        let length = 0;

        samples.push({ t: 0, length: 0, ...previous });

        for (let i = 1; i <= SAMPLE_COUNT; i++) {
            const t = i / SAMPLE_COUNT;
            const point = pointOnCubic(path, t);
            length += Math.hypot(point.x - previous.x, point.y - previous.y);
            samples.push({ t, length, ...point });
            previous = point;
        }

        return { samples, length };
    }
    function buildPointCache(path, samples, pathLength) {
        const cache = [];
        const maxLength = Math.ceil(pathLength);
        let sampleIndex = 1;

        for (let distance = 0; distance <= maxLength; distance++) {
            while (sampleIndex < samples.length - 1 && samples[sampleIndex].length < distance) {
                sampleIndex++;
            }

            const current = samples[sampleIndex];
            const previous = samples[sampleIndex - 1] || samples[0];
            const segmentLength = current.length - previous.length || 1;
            const progress = Math.max(0, Math.min(1, (distance - previous.length) / segmentLength));
            const t = previous.t + (current.t - previous.t) * progress;
            const point = pointOnCubic(path, t);
            const tangent = tangentOnCubic(path, t);
            const angle = Math.atan2(tangent.y, tangent.x);

            cache.push({
                ...point,
                angle,
                cos: Math.cos(angle),
                sin: Math.sin(angle),
            });
        }

        return cache;
    }

    function getPointAtLength(row, targetLength) {
        if (row.pointCache?.length) {
            const cacheIndex = Math.max(0, Math.min(row.pointCache.length - 1, Math.round(targetLength)));
            return row.pointCache[cacheIndex];
        }

        if (targetLength <= 0) {
            const point = pointOnCubic(row.path, 0);
            const tangent = tangentOnCubic(row.path, 0);

            return {
                ...point,
                angle: Math.atan2(tangent.y, tangent.x),
            };
        }

        if (targetLength >= row.length) {
            const point = pointOnCubic(row.path, 1);
            const tangent = tangentOnCubic(row.path, 1);

            return {
                ...point,
                angle: Math.atan2(tangent.y, tangent.x),
            };
        }

        let low = 1;
        let high = row.samples.length - 1;

        while (low < high) {
            const middle = (low + high) >> 1;

            if (row.samples[middle].length < targetLength) {
                low = middle + 1;
            } else {
                high = middle;
            }
        }

        const current = row.samples[low];
        const previous = row.samples[low - 1];

        if (current && previous) {
            const segmentLength = current.length - previous.length || 1;
            const progress = (targetLength - previous.length) / segmentLength;
            const t = previous.t + (current.t - previous.t) * progress;
            const point = pointOnCubic(row.path, t);
            const tangent = tangentOnCubic(row.path, t);

            return {
                ...point,
                angle: Math.atan2(tangent.y, tangent.x),
            };
        }

        const point = pointOnCubic(row.path, 1);
        const tangent = tangentOnCubic(row.path, 1);

        return {
            ...point,
            angle: Math.atan2(tangent.y, tangent.x),
        };
    }

    function measureCharacters(ctx, phrase) {
        let offset = 0;

        return Array.from(phrase).map((char) => {
            const width = Math.max(ctx.measureText(char).width, 2);
            const character = {
                char,
                width,
                offset: offset + width / 2,
                drawable: char.trim().length > 0,
            };

            offset += width;

            return character;
        });
    }

    function prepareRows(svg, ctx) {
        const paths = Array.from(svg.querySelectorAll('defs path'));
        const textPaths = Array.from(svg.querySelectorAll('textPath'));

        return textPaths.map((textPath, index) => {
            const path = parseCubicPath(paths[index]);
            const style = rowStyles[index] || rowStyles[rowStyles.length - 1];
            const phrase = `${textPath.textContent.trim()}${GAP}`;

            ctx.font = `800 ${style.size}px Consolas, "Courier New", monospace`;

            const characters = measureCharacters(ctx, phrase);
            const phraseWidth = characters.reduce((sum, character) => sum + character.width, 0);
            const geometry = path ? buildSamples(path) : null;

            return {
                path,
                characters,
                phraseWidth,
                phaseShift: index * 22,
                size: style.size,
                alpha: style.alpha,
                samples: geometry?.samples || [],
                length: geometry?.length || 260,
                pointCache: geometry ? buildPointCache(path, geometry.samples, geometry.length) : [],
            };
        }).filter((row) => row.path && row.characters.length > 0);
    }

    function drawRow(ctx, row, elapsedSeconds, scaleX, scaleY) {
        ctx.font = `800 ${row.size}px Consolas, "Courier New", monospace`;
        ctx.fillStyle = `rgba(238, 241, 255, ${row.alpha})`;
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'center';

        const phase = (elapsedSeconds * SPEED + row.phaseShift) % row.phraseWidth;
        const safePadding = 26;
        const firstRepeat = Math.floor((-safePadding - phase) / row.phraseWidth) - 1;
        const lastRepeat = Math.ceil((row.length + safePadding - phase) / row.phraseWidth) + 1;

        for (let repeat = firstRepeat; repeat <= lastRepeat; repeat++) {
            const repeatOffset = repeat * row.phraseWidth + phase;

            for (const character of row.characters) {
                if (!character.drawable) {
                    continue;
                }

                const center = repeatOffset + character.offset;

                if (center < -safePadding || center > row.length + safePadding) {
                    continue;
                }

                const point = getPointAtLength(row, center);
                const cos = point.cos ?? Math.cos(point.angle || 0);
                const sin = point.sin ?? Math.sin(point.angle || 0);

                ctx.setTransform(
                    scaleX * cos,
                    scaleY * sin,
                    -scaleX * sin,
                    scaleY * cos,
                    scaleX * point.x,
                    scaleY * point.y
                );
                ctx.fillText(character.char, 0, 0);
            }
        }
    }

    function initVisibilityObserver(target) {
        if (!('IntersectionObserver' in window)) {
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            visible = entries.some((entry) => entry.isIntersecting);
        }, { threshold: 0.01 });

        observer.observe(target);
    }

    function initSphereCodeLoop() {
        const svg = document.querySelector('.sphere-code-map');
        const planet = document.querySelector('.hero-planet');

        if (!svg || !planet) {
            return;
        }

        svg.classList.add('sphere-code-map-source');

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d', { alpha: true });
        canvas.className = 'sphere-code-canvas';
        planet.appendChild(canvas);

        if (!ctx) {
            return;
        }

        let rows = [];
        let width = 260;
        let height = 260;
        let dpr = 1;
        let scaleX = 1;
        let scaleY = 1;
        const startTime = performance.now();

        function resize() {
            const rect = planet.getBoundingClientRect();
            dpr = Math.min(window.devicePixelRatio || 1, DPR_LIMIT);
            width = Math.max(Math.round(rect.width), 1);
            height = Math.max(Math.round(rect.height), 1);

            canvas.width = Math.round(width * dpr);
            canvas.height = Math.round(height * dpr);
            canvas.style.width = `${width}px`;
            canvas.style.height = `${height}px`;
            scaleX = dpr * width / 260;
            scaleY = dpr * height / 260;
            ctx.setTransform(scaleX, 0, 0, scaleY, 0, 0);
            rows = prepareRows(svg, ctx);
        }

        function draw(currentTime) {
            requestAnimationFrame(draw);

            if (document.hidden || !visible || currentTime - lastFrameTime < FRAME_INTERVAL) {
                return;
            }

            lastFrameTime = currentTime;

            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.setTransform(scaleX, 0, 0, scaleY, 0, 0);
            ctx.save();
            ctx.beginPath();
            ctx.arc(130, 130, 130, 0, Math.PI * 2);
            ctx.clip();

            const elapsedSeconds = (currentTime - startTime) / 1000;
            rows.forEach((row) => drawRow(ctx, row, elapsedSeconds, scaleX, scaleY));

            ctx.restore();
        }

        resize();
        initVisibilityObserver(planet);
        window.addEventListener('resize', resize, { passive: true });
        requestAnimationFrame(draw);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSphereCodeLoop);
    } else {
        initSphereCodeLoop();
    }
})();