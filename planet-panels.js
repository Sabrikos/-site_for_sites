(() => {
    const group = document.querySelector('.planet-panels');
    if (!group) return;
    const canvas = group.querySelector('canvas');
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    const panels = [...group.querySelectorAll('.planet-panel')];
    const texture = document.createElement('canvas');
    const size = 1200;
    const atmosphereScale = size / 670;
    const pad = Math.ceil(110 * atmosphereScale);
    texture.width = texture.height = size + pad * 2;
    const paint = texture.getContext('2d');
    // Match the About Studio globe: off-centre night-side gradient,
    // violet atmospheric light, and a fine rim instead of a broad white band.
    const center = pad + size / 2;
    paint.save();
    paint.translate(center, center);
    paint.rotate(-Math.PI / 6);
    paint.translate(-center, -center);
    const gradient = paint.createRadialGradient(
        pad + size * .6, pad + size * .55, 0,
        pad + size * .6, pad + size * .55, Math.hypot(.6, .55) * size
    );
    gradient.addColorStop(0, '#0b0b1d');
    gradient.addColorStop(.45, '#0b0b1d');
    gradient.addColorStop(.70, '#151532');
    gradient.addColorStop(.91, '#383b89');
    gradient.addColorStop(.99, '#b4c5ff');
    gradient.addColorStop(1, '#b4c5ff');
    paint.beginPath();
    paint.arc(center, center, size / 2, 0, Math.PI * 2);
    paint.fillStyle = gradient;
    paint.shadowColor = '#4e55ff59';
    paint.shadowBlur = 45 * atmosphereScale;
    paint.shadowOffsetX = 17 * atmosphereScale;
    paint.shadowOffsetY = 10 * atmosphereScale;
    paint.fill();
    paint.shadowColor = '#7b82ff90';
    paint.shadowBlur = 18 * atmosphereScale;
    paint.shadowOffsetX = 7 * atmosphereScale;
    paint.shadowOffsetY = 4 * atmosphereScale;
    paint.fill();
    paint.shadowBlur = 0;
    paint.shadowOffsetX = paint.shadowOffsetY = 0;
    paint.save();
    paint.clip();
    // A right-side crescent with a broad, smooth inner falloff.
    // Compensate for the texture rotation to keep the light on screen-right.
    const surface = document.createElement('canvas');
    surface.width = surface.height = size;
    const surfaceContext = surface.getContext('2d');
    const pixels = surfaceContext.createImageData(size, size);
    for (let y = 0; y < size; y++) {
        for (let x = 0; x < size; x++) {
            const nx = (x + .5 - size / 2) / (size / 2);
            const ny = (y + .5 - size / 2) / (size / 2);
            const radius = nx * nx + ny * ny;
            if (radius > 1) continue;
            const nz = Math.sqrt(1 - radius);
            const rightNormal = nx * Math.cos(Math.PI / 6) + ny * .5;
            const illumination = rightNormal * .92 - nz * .4;
            const feather = Math.max(0, Math.min(1, (illumination + .22) / 1.14));
            const smooth = feather * feather * (3 - 2 * feather);
            const lit = Math.pow(smooth, 2);
            const rim = Math.pow(1 - nz, 3) * Math.max(0, rightNormal);
            const offset = (y * size + x) * 4;
            pixels.data[offset] = 9 + 43 * lit + 25 * rim;
            pixels.data[offset + 1] = 9 + 42 * lit + 28 * rim;
            pixels.data[offset + 2] = 25 + 77 * lit + 52 * rim;
            pixels.data[offset + 3] = 255;
        }
    }
    surfaceContext.putImageData(pixels, 0, 0);
    paint.drawImage(surface, pad, pad);
    // A narrow inner atmospheric rim, using the same blue-violet tint.
    paint.strokeStyle = '#7b82ff55';
    paint.lineWidth = 1.5 * atmosphereScale;
    paint.shadowColor = '#7b82ff88';
    paint.shadowBlur = 14 * atmosphereScale;
    paint.stroke();
    paint.restore();
    paint.strokeStyle = '#7b82ff38';
    paint.lineWidth = 1;
    paint.stroke();
    paint.restore();
    let frame;
    function render() {
        const bounds = group.getBoundingClientRect();
        const width = document.documentElement.clientWidth;
        const diameter = width <= 700 ? 1600 : Math.min(1700, Math.max(1000, width * 1.12));
        const originY = 250;
        const height = Math.max(bounds.height + originY, diameter + originY + 200);
        const dpr = Math.min(window.devicePixelRatio || 1, 1.5);
        canvas.width = Math.round(width * dpr);
        canvas.height = Math.round(height * dpr);
        canvas.style.height = `${height}px`;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        const left = width <= 700 ? width / 2 - 1320 : width / 2 - diameter * .77;
        const top = 184;
        const padding = pad * diameter / size;
        const draw = () => ctx.drawImage(texture, left - padding, top - padding, diameter + padding * 2, diameter + padding * 2);
        draw();
        // Re-sample the same object inside each raised pane. Outside remains 1:1.
        // The common planet center keeps the arc continuous between sections.
        panels.forEach(panel => {
            const rect = panel.getBoundingClientRect();
            const x = rect.left;
            const y = rect.top - bounds.top + originY;
            ctx.save();
            ctx.beginPath();
            ctx.roundRect(x, y, rect.width, rect.height, 10);
            ctx.clip();
            ctx.clearRect(x, y, rect.width, rect.height);
            const cx = left + diameter / 2;
            const cy = top + diameter / 2;
            ctx.translate(cx, cy);
            ctx.scale(1.045, 1.045);
            ctx.translate(-cx, -cy);
            draw();
            ctx.restore();
        });
    }
    const schedule = () => { cancelAnimationFrame(frame); frame = requestAnimationFrame(render); };
    const observer = new ResizeObserver(schedule);
    observer.observe(group);
    panels.forEach(panel => observer.observe(panel));
    window.addEventListener('resize', schedule, { passive: true });
    render();
})();
