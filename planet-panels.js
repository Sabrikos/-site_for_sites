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
            const rightNormal = nx * .995 + ny * .10;
            const illumination = rightNormal * .94 - nz * .20;
            const feather = Math.max(0, Math.min(1, (illumination + .30) / 1.24));
            const smooth = feather * feather * (3 - 2 * feather);
            const lit = Math.pow(smooth, 1.65);
            const rim = Math.pow(1 - nz, 3) * Math.max(0, rightNormal);
            const offset = (y * size + x) * 4;
            pixels.data[offset] = 11 + 48 * lit + 25 * rim;
            pixels.data[offset + 1] = 12 + 46 * lit + 28 * rim;
            pixels.data[offset + 2] = 30 + 88 * lit + 52 * rim;
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
        const cx = left + diameter / 2, cy = top + diameter / 2;
        const panelRects = panels.map(panel => panel.getBoundingClientRect());
        const ringPoint = (angle, ring) => {
            const rx = diameter * (.64 + ring * .045);
            const ry = diameter * (.16 + ring * .035);
            const x = rx * Math.cos(angle);
            const y = ry * Math.sin(angle) + diameter * .022 * Math.sin(3 * angle);
            return { x: cx + x * .94 + y * .342, y: cy - x * .342 + y * .94 };
        };
        const drawRings = (front) => {
            ctx.save();
            for (let ring = 0; ring < 2; ring++) {
                const start = front ? 0 : Math.PI;
                ctx.beginPath();
                for (let step = 0; step <= 160; step++) {
                    const point = ringPoint(start + step / 160 * Math.PI, ring);
                    if (step === 0) ctx.moveTo(point.x, point.y);
                    else ctx.lineTo(point.x, point.y);
                }
                const light = ctx.createLinearGradient(cx - diameter*.6, cy, cx + diameter*.6, cy - diameter*.2);
                light.addColorStop(0, front ? '#61559fcc' : '#49416e88');
                light.addColorStop(.22, front ? '#9983eddd' : '#6b589080');
                light.addColorStop(.43, front ? '#ddd0ffed' : '#8872bd88');
                light.addColorStop(.55, front ? '#b7a2ffdd' : '#8872bd77');
                light.addColorStop(.72, front ? '#63558fcc' : '#59467366');
                light.addColorStop(.84, front ? '#8671bfdd' : '#493a6366');
                light.addColorStop(1, front ? '#c1b0ffdd' : '#39305266');
                ctx.strokeStyle = light;
                ctx.lineWidth = ring === 0 ? 2.4 : 2;
                ctx.shadowColor = '#8e72e855';
                ctx.shadowBlur = front ? 7 : 3;
                ctx.stroke();
            }
            ctx.restore();
        };
        drawRings(false);
        ctx.drawImage(texture, left - padding, top - padding, diameter + padding * 2, diameter + padding * 2);
        drawRings(true);
        // Place the moon midway between the two front rings, clear of content.
        const moonRadius = Math.min(78, Math.max(48, width * .048));
        const moonClearance = moonRadius + 12;
        let moon;
        for (let angle = .18; angle < Math.PI; angle += .015) {
            const point = ringPoint(angle, .5);
            const pageY = point.y + bounds.top - originY;
            if (point.x < moonClearance || point.x > width - moonClearance) continue;
            if (panelRects.some(r => point.x > r.left - moonClearance && point.x < r.right + moonClearance && pageY > r.top - moonClearance && pageY < r.bottom + moonClearance)) continue;
            moon = point;
            break;
        }
        if (moon && width > 700) {
            ctx.save();
            const glow = ctx.createRadialGradient(moon.x - moonRadius * .38, moon.y - moonRadius * .38, 0, moon.x, moon.y, moonRadius);
            glow.addColorStop(0, '#8c60db'); glow.addColorStop(.3, '#6943b7'); glow.addColorStop(.65, '#422878'); glow.addColorStop(1, '#271b50');
            ctx.fillStyle = glow;
            ctx.shadowColor = '#9760ff80';
            ctx.shadowBlur = 17;
            ctx.beginPath();
            ctx.arc(moon.x, moon.y, moonRadius, 0, Math.PI * 2);
            ctx.fill();
            // Curved atmospheric bands stay clipped to the satellite surface.
            ctx.save();
            ctx.clip();
            ctx.shadowBlur = 0;
            ctx.translate(moon.x, moon.y);
            ctx.scale(moonRadius, moonRadius);
            ctx.rotate(.18);
            for (const [y, thickness, color] of [
                [-.70, .21, '#9569df38'], [-.24, .28, '#37216b48'],
                [.25, .23, '#9763d838'], [.72, .19, '#34206648']
            ]) {
                ctx.beginPath();
                ctx.moveTo(-1.25, y - .20);
                ctx.bezierCurveTo(-.55, y + .11, .45, y + .21, 1.25, y - .09);
                ctx.lineWidth = thickness;
                ctx.strokeStyle = color;
                ctx.stroke();
                ctx.beginPath();
                ctx.moveTo(-1.25, y - .20 - thickness / 2);
                ctx.bezierCurveTo(-.55, y + .11 - thickness / 2, .45, y + .21 - thickness / 2, 1.25, y - .09 - thickness / 2);
                ctx.strokeStyle = '#b694f522';
                ctx.lineWidth = .018;
                ctx.stroke();
            }
            ctx.beginPath();
            ctx.ellipse(-.43, -.02, .24, .105, .3, 0, Math.PI * 2);
            ctx.fillStyle = '#38216450';
            ctx.fill();
            // Shade the bands together with the sphere, preserving its volume.
            const night = ctx.createLinearGradient(-.75, -.65, .85, .75);
            night.addColorStop(0, '#bbaaff08');
            night.addColorStop(.25, '#12122c08');
            night.addColorStop(.55, '#20123d24');
            night.addColorStop(.8, '#170f343f');
            night.addColorStop(1, '#140e2e66');
            ctx.fillStyle = night;
            ctx.fillRect(-1.5, -1.5, 3, 3);
            // Soft atmospheric limb, strongest on the illuminated left edge.
            const atmosphere = ctx.createRadialGradient(0, 0, .72, 0, 0, 1);
            atmosphere.addColorStop(0, '#9f64ff00');
            atmosphere.addColorStop(.65, '#9f64ff0d');
            atmosphere.addColorStop(.9, '#a374ff42');
            atmosphere.addColorStop(1, '#bd99ff99');
            ctx.fillStyle = atmosphere;
            ctx.fillRect(-1.5, -1.5, 3, 3);
            // Offset circular gradients form soft crescents along opposing limbs.
            // Undo the bands' tilt so the lighting remains aligned to the screen.
            ctx.rotate(-.18);
            const crescentLight = ctx.createRadialGradient(.22, .03, .76, .22, .03, 1.25);
            crescentLight.addColorStop(0, '#b58aff00');
            crescentLight.addColorStop(.35, '#b58aff00');
            crescentLight.addColorStop(.65, '#ae7dff42');
            crescentLight.addColorStop(.85, '#c49effa3');
            crescentLight.addColorStop(1, '#e2ccffe6');
            ctx.fillStyle = crescentLight;
            ctx.fillRect(-1.5, -1.5, 3, 3);
            const crescentShade = ctx.createRadialGradient(-.24, 0, .8, -.24, 0, 1.26);
            crescentShade.addColorStop(0, '#160e3000');
            crescentShade.addColorStop(.4, '#160e3000');
            crescentShade.addColorStop(.75, '#160e3020');
            crescentShade.addColorStop(1, '#160e3047');
            ctx.fillStyle = crescentShade;
            ctx.fillRect(-1.5, -1.5, 3, 3);
            ctx.restore();
            // A crisp, directional limb separates the solid sphere from its halo.
            ctx.shadowColor = '#a776ff99';
            ctx.shadowBlur = 7;
            const rim = ctx.createLinearGradient(
                moon.x - moonRadius, moon.y,
                moon.x + moonRadius, moon.y
            );
            rim.addColorStop(0, '#e0c8ffff');
            rim.addColorStop(.35, '#ac80f5cc');
            rim.addColorStop(.65, '#8660cb77');
            rim.addColorStop(1, '#a071eaa6');
            ctx.strokeStyle = rim;
            ctx.lineWidth = 1.2;
            ctx.beginPath();
            ctx.arc(moon.x, moon.y, moonRadius - .6, 0, Math.PI * 2);
            ctx.stroke();
            ctx.restore();
        }
        panels.forEach((panel, index) => {
            const rect = panelRects[index];
            for (const [edge, y] of [['top', rect.top], ['bottom', rect.bottom]]) {
                const dy = y - bounds.top + originY - cy;
                const x = Math.abs(dy) < diameter/2 ? cx + Math.sqrt((diameter/2)**2 - dy**2) - rect.left : -1000;
                panel.style.setProperty(`--lower-rim-${edge}`, `${x}px`);
            }
        });

    }
    const schedule = () => { cancelAnimationFrame(frame); frame = requestAnimationFrame(render); };
    const observer = new ResizeObserver(schedule);
    observer.observe(group);
    panels.forEach(panel => observer.observe(panel));
    window.addEventListener('resize', schedule, { passive: true });
    render();
})();

// Extend the existing CSS circle using its exact untransformed coordinates.
(() => {
    const wrapper = document.querySelector('.studio-sphere-wrap');
    const panel = wrapper?.querySelector('.about-studio');
    const source = panel?.querySelector('.about-studio__planet');
    const backdrop = wrapper?.querySelector('.studio-sphere-backdrop');
    const continuation = backdrop?.querySelector('.studio-sphere-continuation');
    if (!source || !continuation) return;
    const align = () => {
        const host = source.offsetParent.getBoundingClientRect();
        const target = backdrop.getBoundingClientRect();
        const style = getComputedStyle(source);
        backdrop.style.setProperty('--studio-sphere-x', `${host.left + parseFloat(style.left) - target.left}px`);
        backdrop.style.setProperty('--studio-sphere-y', `${host.top + parseFloat(style.top) - target.top}px`);
        backdrop.style.setProperty('--studio-sphere-size', style.width);
        continuation.style.width = style.width;
        continuation.style.height = style.height;
        continuation.style.transform = style.transform;
    };
    const observer = new ResizeObserver(align);
    observer.observe(panel);
    observer.observe(wrapper);
    window.addEventListener('resize', align, { passive: true });
    align();
})();
