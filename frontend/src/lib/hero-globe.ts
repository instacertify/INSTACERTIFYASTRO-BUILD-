import { geoGraticule10, geoOrthographic, geoPath, type GeoContext, type GeoPermissibleObjects } from 'd3-geo';
import { LAND_SHAPES } from './globe-land';

const RAD = Math.PI / 180;
const LIGHT = '191,227,255';
const VIEW_LAT = 18 * RAD; // seen from a little above the equator, so Europe and India face the viewer
const TURN_SECONDS = 48; // one full rotation
const TRIP_SECONDS = 3.6; // one product's trip along an arc
const FRAME_MS = 1000 / 30; // the motion is slow, so 30 fps looks the same as 60 at half the CPU

type Point = { lat: number; lng: number };
type Arc = { samples: (Point & { h: number })[]; offset: number };

const land = {
  type: 'MultiPolygon' as const,
  coordinates: LAND_SHAPES.split('|').map((poly) =>
    poly.split(';').map((ring) => {
      const n = ring.split(',').map(Number);
      const pts: [number, number][] = [];
      for (let i = 0, x = 0, y = 0; i < n.length; i += 2) {
        x += n[i];
        y += n[i + 1];
        pts.push([x / 10, y / 10]);
      }
      return pts;
    }),
  ),
};
const graticule = geoGraticule10();

// Star dust drifting in a shell around the globe.
const dust = Array.from({ length: 70 }, (_, i) => {
  const t = (i * 0.618034) % 1;
  return {
    lat: Math.asin(1 - (2 * (i + 0.5)) / 70),
    lng: i * 2.399963,
    h: 1.06 + 0.4 * t,
    size: 0.6 + 1.1 * ((i * 0.381966) % 1),
    seed: t * Math.PI * 2,
  };
});

/** Orbits around the globe: radius (in globe radii), how far the plane leans back, its roll, and speed in radians a second. */
type Orbit = { r: number; tilt: number; roll: number; speed: number; dots: number; sats: number; alpha: number; tests?: boolean };
const ORBITS: Orbit[] = [
  { r: 1.12, tilt: 74 * RAD, roll: -18 * RAD, speed: 0.32, dots: 0, sats: 2, alpha: 0.5 },
  { r: 1.22, tilt: 79 * RAD, roll: 16 * RAD, speed: -0.18, dots: 96, sats: 1, alpha: 0.55 },
  // Open enough that its near half passes under the globe, so the testing labels never cross the map.
  { r: 1.32, tilt: 38 * RAD, roll: -6 * RAD, speed: 0.09, dots: 0, sats: 0, alpha: 0.32, tests: true },
  // Upright orbits, rolled round towards vertical
  { r: 1.17, tilt: 76 * RAD, roll: 90 * RAD, speed: 0.22, dots: 0, sats: 1, alpha: 0.45 },
  { r: 1.27, tilt: 72 * RAD, roll: 62 * RAD, speed: -0.14, dots: 80, sats: 0, alpha: 0.4 },
  { r: 1.2, tilt: 80 * RAD, roll: 118 * RAD, speed: 0.17, dots: 0, sats: 1, alpha: 0.35 },
];
/** Point on an orbit; z > 0 is the half in front of the globe (sin t > 0). */
const orbitAt = (o: Orbit, t: number, roll: number) => {
  const c = Math.cos(t) * o.r;
  const s = Math.sin(t) * o.r;
  const y = s * Math.cos(o.tilt);
  return { x: c * Math.cos(roll) - y * Math.sin(roll), y: c * Math.sin(roll) + y * Math.cos(roll) };
};

const toVec = (p: Point) => [Math.cos(p.lat) * Math.cos(p.lng), Math.cos(p.lat) * Math.sin(p.lng), Math.sin(p.lat)];
/** Great-circle path between two points, lifted off the surface towards its middle. */
const arcBetween = (a: Point, b: Point, steps = 40) => {
  const va = toVec(a);
  const vb = toVec(b);
  const angle = Math.acos(Math.min(1, va[0] * vb[0] + va[1] * vb[1] + va[2] * vb[2]));
  const lift = 0.08 + 0.22 * (angle / Math.PI);
  const samples: (Point & { h: number })[] = [];
  for (let s = 0; s <= steps; s++) {
    const t = s / steps;
    const wa = Math.sin((1 - t) * angle) / Math.sin(angle);
    const wb = Math.sin(t * angle) / Math.sin(angle);
    const v = [0, 1, 2].map((k) => wa * va[k] + wb * vb[k]);
    samples.push({ lat: Math.asin(v[2]), lng: Math.atan2(v[1], v[0]), h: 1 + lift * Math.sin(Math.PI * t) });
  }
  return samples;
};

/** Draws and animates one hero globe; see HeroGlobe.astro for the markup it expects. */
export function startGlobe(root: HTMLElement) {
  const canvas = root.querySelector<HTMLCanvasElement>('[data-globe-canvas]')!;
  const ctx = canvas.getContext('2d');
  if (!ctx) return;
  const dark = root.dataset.tone === 'dark';
  const css = getComputedStyle(document.documentElement);
  const accent = css.getPropertyValue('--color-accent-400').trim() || '#f68a4e';
  const deep = '34,81,117';
  const glint = dark ? LIGHT : deep;
  const still = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const projection = geoOrthographic().clipAngle(90).precision(0.4);
  const path = geoPath(projection);
  /** Projects a shape once per frame; the result is filled, stroked and clipped without re-projecting. */
  const shapeOf = (object: GeoPermissibleObjects) => {
    const shape = new Path2D();
    path.context(shape as unknown as GeoContext)(object);
    return shape;
  };

  const tags = [...root.querySelectorAll<HTMLElement>('.globe-tag')].map((el) => ({
    el,
    at: { lat: Number(el.dataset.lat) * RAD, lng: Number(el.dataset.lng) * RAD },
    home: el.hasAttribute('data-home'),
    side: el.dataset.side ?? 'top',
    w: 0,
    h: 0,
  }));
  const tests: string[] = JSON.parse(root.dataset.tests || '[]');
  const chips = [...root.querySelectorAll<HTMLElement>('.globe-test')].map((el, k) => ({
    el,
    name: el.querySelector<HTMLElement>('[data-test-name]')!,
    shown: k,
    w: 0,
    h: 0,
  }));
  // Labels may hang past the globe, but never past the edge of the screen.
  let minX = -Infinity;
  let maxX = Infinity;
  const measure = () => {
    [...tags, ...chips].forEach((t) => ((t.w = t.el.offsetWidth), (t.h = t.el.offsetHeight)));
    const left = root.getBoundingClientRect().left;
    minX = 8 - left;
    maxX = document.documentElement.clientWidth - left - 8;
  };
  const home = tags.find((t) => t.home)?.at ?? tags[0]?.at;
  const arcs: Arc[] = home ? tags.filter((t) => !t.home).map((t, i, all) => ({ samples: arcBetween(home, t.at), offset: i / all.length })) : [];

  // Start with the home market just right of centre, so the routes heading west are in view.
  let spin = (home ? home.lng : 0) - 30 * RAD;
  let size = 0;
  let dpr = 1;

  const resize = () => {
    size = root.clientWidth;
    dpr = Math.min(2, window.devicePixelRatio || 1);
    canvas.width = Math.round(size * dpr);
    canvas.height = Math.round(size * dpr);
  };

  /** Screen position and depth (z > 0 faces the viewer) of a point at height h above the centre. */
  const project = (p: Point, h = 1) => {
    const l = p.lng - spin;
    const x = Math.cos(p.lat) * Math.sin(l);
    const y = Math.cos(VIEW_LAT) * Math.sin(p.lat) - Math.sin(VIEW_LAT) * Math.cos(p.lat) * Math.cos(l);
    const z = Math.sin(VIEW_LAT) * Math.sin(p.lat) + Math.cos(VIEW_LAT) * Math.cos(p.lat) * Math.cos(l);
    return { x: x * h, y: y * h, z, h };
  };
  // A lifted point is hidden only while it sits behind the globe's disc.
  const seen = (q: { x: number; y: number; z: number }) => q.z > 0 || q.x * q.x + q.y * q.y > 1;

  const draw = (time: number) => {
    const R = size * 0.355;
    const cx = size / 2;
    const cy = size / 2;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, size, size);
    const secs = time / 1000;

    // Orbits: the far half goes behind the globe, the near half in front of it.
    const orbits = (front: boolean, tone: string) => {
      ORBITS.forEach((o, i) => {
        const roll = o.roll + (still ? 0 : 0.1 * Math.sin(secs * 0.15 + i * 2));
        const phase = still ? i : secs * o.speed;
        const from = front ? 0 : Math.PI;
        ctx.beginPath();
        for (let k = 0; k <= 64; k++) {
          const p = orbitAt(o, from + (k / 64) * Math.PI, roll);
          if (k) ctx.lineTo(cx + p.x * R, cy - p.y * R);
          else ctx.moveTo(cx + p.x * R, cy - p.y * R);
        }
        ctx.strokeStyle = `rgba(${tone},${o.alpha * (front ? 1 : 0.5)})`;
        ctx.lineWidth = 1;
        ctx.stroke();

        ctx.fillStyle = `rgba(${tone},${front ? 0.85 : 0.35})`;
        for (let k = 0; k < o.dots; k++) {
          const t = phase + (k / o.dots) * Math.PI * 2;
          if (Math.sin(t) > 0 !== front) continue;
          const p = orbitAt(o, t, roll);
          ctx.fillRect(cx + p.x * R - 0.75, cy - p.y * R - 0.75, 1.5, 1.5);
        }

        // Satellites with a fading trail
        for (let j = 0; j < o.sats; j++) {
          const head = phase + (j / o.sats) * Math.PI * 2;
          const dir = Math.sign(o.speed);
          for (let k = 0; k < 12; k++) {
            const t0 = head - dir * (k + 1) * 0.05;
            const t1 = head - dir * k * 0.05;
            if (Math.sin((t0 + t1) / 2) > 0 !== front) continue;
            const a = orbitAt(o, t0, roll);
            const b = orbitAt(o, t1, roll);
            ctx.beginPath();
            ctx.moveTo(cx + a.x * R, cy - a.y * R);
            ctx.lineTo(cx + b.x * R, cy - b.y * R);
            ctx.strokeStyle = `rgba(${tone},${(1 - k / 12) * (front ? 0.9 : 0.4)})`;
            ctx.lineWidth = 1.6;
            ctx.lineCap = 'round';
            ctx.stroke();
          }
          if (Math.sin(head) > 0 === front) {
            const p = orbitAt(o, head, roll);
            ctx.beginPath();
            ctx.arc(cx + p.x * R, cy - p.y * R, front ? 2.4 : 1.8, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(${tone},${front ? 1 : 0.45})`;
            ctx.fill();
          }
        }
      });
    };
    // Star dust turns slower than the globe, so it drifts past it.
    const stardust = (front: boolean) => {
      for (const d of dust) {
        const q = project({ lat: d.lat, lng: d.lng + spin * 0.6 }, d.h);
        if (q.z > 0 !== front) continue;
        const twinkle = still ? 0.5 : 0.5 + 0.5 * Math.sin(secs * 1.3 + d.seed);
        ctx.fillStyle = `rgba(${glint},${(0.3 + 0.55 * twinkle) * (front ? 1 : 0.6)})`;
        ctx.beginPath();
        ctx.arc(cx + q.x * R, cy - q.y * R, d.size, 0, Math.PI * 2);
        ctx.fill();
      }
    };

    stardust(false);
    orbits(false, glint);

    // Sunlight comes from the upper left, so both sea and land are lighter there.
    const lx = cx - R * 0.38;
    const ly = cy - R * 0.42;
    const ocean = ctx.createRadialGradient(lx, ly, 0, cx, cy, R);
    ocean.addColorStop(0, '#f2f8fd');
    ocean.addColorStop(0.55, '#ddedf8');
    ocean.addColorStop(1, '#c2dbee');
    ctx.beginPath();
    ctx.arc(cx, cy, R, 0, Math.PI * 2);
    ctx.fillStyle = ocean;
    ctx.fill();

    projection.scale(R).translate([cx, cy]).rotate([-spin / RAD, -VIEW_LAT / RAD]);
    const landShape = shapeOf(land);

    ctx.strokeStyle = 'rgba(34,81,117,0.07)';
    ctx.lineWidth = 0.6;
    ctx.stroke(shapeOf(graticule));

    // Navy land against the light sea
    const ground = ctx.createRadialGradient(lx, ly, 0, cx, cy, R * 1.05);
    ground.addColorStop(0, '#2f5684');
    ground.addColorStop(0.55, '#1f3f69');
    ground.addColorStop(1, '#152e50');
    ctx.fillStyle = ground;
    ctx.fill(landShape);
    ctx.strokeStyle = 'rgba(255,255,255,0.35)';
    ctx.lineWidth = 0.6;
    ctx.lineJoin = 'round';
    ctx.stroke(landShape);

    // Gentle night-side shading over the whole sphere, so it reads as round rather than flat.
    const shade = ctx.createRadialGradient(lx, ly, R * 0.35, cx, cy, R * 1.02);
    shade.addColorStop(0, 'rgba(22,58,88,0)');
    shade.addColorStop(0.7, 'rgba(22,58,88,0.05)');
    shade.addColorStop(1, 'rgba(22,58,88,0.2)');
    ctx.beginPath();
    ctx.arc(cx, cy, R, 0, Math.PI * 2);
    ctx.fillStyle = shade;
    ctx.fill();

    ctx.lineWidth = 1.2;
    ctx.strokeStyle = dark ? 'rgba(191,227,255,0.6)' : 'rgba(96,150,196,0.55)';
    ctx.stroke();

    // Near halves of the orbits; where they cross the globe they switch to deep blue to stay visible.
    orbits(true, glint);
    ctx.save();
    ctx.beginPath();
    ctx.arc(cx, cy, R, 0, Math.PI * 2);
    ctx.clip();
    orbits(true, deep);
    // Deep blue disappears on the navy land, so the stretches over land are drawn light.
    ctx.clip(landShape);
    orbits(true, LIGHT);
    ctx.restore();
    stardust(true);

    // Trade routes from the home market, each with a product travelling along it
    const trip = still ? 0.6 : ((time / 1000) / TRIP_SECONDS) % 1;
    for (const arc of arcs) {
      const pts = arc.samples.map((s) => project(s, s.h));
      const route = new Path2D();
      let open = false;
      pts.forEach((q, i) => {
        if (!seen(q)) return (open = false);
        const x = cx + q.x * R;
        const y = cy - q.y * R;
        if (open && i) route.lineTo(x, y);
        else route.moveTo(x, y);
        open = true;
      });
      ctx.strokeStyle = dark ? 'rgba(150,190,225,0.5)' : `rgba(${deep},0.25)`;
      ctx.lineWidth = 1;
      ctx.stroke(route);
      if (!dark) {
        ctx.save();
        ctx.clip(landShape);
        ctx.strokeStyle = `rgba(${LIGHT},0.5)`;
        ctx.stroke(route);
        ctx.restore();
      }

      const head = (trip + arc.offset) % 1;
      const from = Math.max(0, Math.floor((head - 0.18) * (pts.length - 1)));
      const to = Math.floor(head * (pts.length - 1));
      ctx.beginPath();
      for (let i = from; i <= to; i++) {
        const q = pts[i];
        if (!seen(q)) continue;
        if (i === from || !seen(pts[i - 1])) ctx.moveTo(cx + q.x * R, cy - q.y * R);
        else ctx.lineTo(cx + q.x * R, cy - q.y * R);
      }
      ctx.strokeStyle = accent;
      ctx.lineWidth = 2;
      ctx.lineCap = 'round';
      ctx.stroke();
      const lead = pts[to];
      if (lead && seen(lead)) {
        ctx.beginPath();
        ctx.arc(cx + lead.x * R, cy - lead.y * R, 2.6, 0, Math.PI * 2);
        ctx.fillStyle = accent;
        ctx.fill();
      }
    }

    // Markets: a point with a ring that keeps sending out a pulse, and the certification label above it
    const pulse = still ? 0 : ((time / 1000) / 2.4) % 1;
    // Nearest labels claim their space first; any label they would cover is hidden.
    const placed: { l: number; t: number; r: number; b: number }[] = [];
    const order = tags.map((tag) => ({ tag, q: project(tag.at) })).sort((a, b) => b.q.z - a.q.z);
    for (const { tag, q } of order) {
      const x = cx + q.x * R;
      const y = cy - q.y * R;
      const show = Math.max(0, Math.min(1, (q.z - 0.12) / 0.2));
      const want = tag.side === 'left' ? x - 10 - tag.w : tag.side === 'right' ? x + 10 : x - tag.w / 2;
      const l = Math.max(minX, Math.min(maxX - tag.w, want));
      const t = tag.side === 'top' ? y - 10 - tag.h : tag.side === 'bottom' ? y + 10 : y - tag.h / 2;
      const box = { l, t, r: l + tag.w, b: t + tag.h };
      const covered = placed.some((p) => box.l < p.r + 4 && box.r > p.l - 4 && box.t < p.b + 2 && box.b > p.t - 2);
      if (show > 0 && !covered) placed.push(box);
      if (show > 0) {
        ctx.globalAlpha = show;
        ctx.beginPath();
        ctx.arc(x, y, tag.home ? 4 : 3.2, 0, Math.PI * 2);
        ctx.fillStyle = accent;
        ctx.fill();
        if (pulse) {
          ctx.beginPath();
          ctx.arc(x, y, 4 + pulse * 12, 0, Math.PI * 2);
          ctx.strokeStyle = accent;
          ctx.globalAlpha = show * (1 - pulse);
          ctx.lineWidth = 1.5;
          ctx.stroke();
        }
        ctx.globalAlpha = 1;
      }
      tag.el.style.transform = `translate3d(${l.toFixed(1)}px, ${t.toFixed(1)}px, 0)`;
      tag.el.style.opacity = covered ? '0' : show.toFixed(2);
    }

    // Testing services riding the outer orbit; market labels keep priority over them.
    const carrier = ORBITS.findIndex((o) => o.tests);
    if (carrier < 0 || !chips.length || !tests.length) return;
    const o = ORBITS[carrier];
    const roll = o.roll + (still ? 0 : 0.1 * Math.sin(secs * 0.15 + carrier * 2));
    const phase = still ? 0.4 : secs * o.speed;
    const riders = chips
      .map((chip, k) => {
        const t = phase + (k / chips.length) * Math.PI * 2;
        // A slot takes the next test name while it is out of sight, deepest behind the globe.
        const lap = Math.floor((t - Math.PI * 1.5) / (Math.PI * 2));
        const next = (((k + chips.length * lap) % tests.length) + tests.length) % tests.length;
        if (next !== chip.shown) {
          chip.shown = next;
          chip.name.dataset.label = tests[next];
          chip.w = chip.el.offsetWidth;
        }
        return { chip, t, front: Math.sin(t) > 0, p: orbitAt(o, t, roll) };
      })
      .sort((a, b) => Number(b.front) - Number(a.front));
    for (const { chip, front, p } of riders) {
      const x = cx + p.x * R;
      const y = cy - p.y * R;
      const l = Math.max(minX, Math.min(maxX - chip.w, x - chip.w / 2));
      const top = y - chip.h / 2;
      const box = { l, t: top, r: l + chip.w, b: top + chip.h };
      // Behind the globe a chip shows only where it is fully clear of the disc.
      const nx = Math.max(box.l, Math.min(cx, box.r)) - cx;
      const ny = Math.max(box.t, Math.min(cy, box.b)) - cy;
      const clear = front || nx * nx + ny * ny > R * R;
      const covered = placed.some((q) => box.l < q.r + 4 && box.r > q.l - 4 && box.t < q.b + 2 && box.b > q.t - 2);
      if (clear && !covered) placed.push(box);
      chip.el.style.transform = `translate3d(${l.toFixed(1)}px, ${top.toFixed(1)}px, 0)`;
      chip.el.style.opacity = clear && !covered ? (front ? '1' : '0.55') : '0';
    }
  };

  // Drag sideways to turn the globe; vertical swipes keep scrolling the page.
  let drag: { x: number; spin: number } | null = null;
  canvas.addEventListener('pointerdown', (e) => {
    drag = { x: e.clientX, spin };
    canvas.setPointerCapture(e.pointerId);
  });
  canvas.addEventListener('pointermove', (e) => {
    if (!drag) return;
    spin = drag.spin - ((e.clientX - drag.x) / Math.max(1, size * 0.38)) * 0.9;
    if (still) draw(0);
  });
  const release = () => (drag = null);
  canvas.addEventListener('pointerup', release);
  canvas.addEventListener('pointercancel', release);

  let raf = 0;
  let last = 0;
  let visible = false;
  const tick = (time: number) => {
    raf = requestAnimationFrame(tick);
    // Dragging keeps the full frame rate so the globe follows the pointer smoothly.
    if (!drag && last && time - last < FRAME_MS - 2) return;
    if (!drag) spin -= ((Math.min(64, time - (last || time)) / 1000) * 2 * Math.PI) / TURN_SECONDS;
    last = time;
    draw(time);
  };
  const run = () => {
    cancelAnimationFrame(raf);
    last = 0;
    if (visible && !document.hidden && !still) raf = requestAnimationFrame(tick);
  };

  resize();
  measure();
  draw(0);
  document.fonts?.ready.then(() => {
    measure();
    draw(performance.now());
  });
  new ResizeObserver(() => {
    resize();
    measure();
    draw(performance.now());
  }).observe(root);
  new IntersectionObserver(([entry]) => {
    visible = entry.isIntersecting;
    run();
  }).observe(root);
  document.addEventListener('visibilitychange', run);
}
