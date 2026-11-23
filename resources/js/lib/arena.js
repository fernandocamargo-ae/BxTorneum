// Batalla de beys en canvas sobre la foto del estadio.
// La geometria (centro, matriz del piso, riel y salidas) se midio en public/images/arena/estadio.webp (1100x1028).
import { BEYS } from './beyRoster';

const W = 1100, H = 1028;
const C = { x: 582.6, y: 455.1 };
const S = { a: 341.2, b: 13.6, c: 13.6, d: 297.8 }; // piso (disco unidad) -> pixeles de la foto
const UP = { x: 0.276, y: -0.961 };                 // "arriba" de la camara en la foto
const ZPX = 175;                                    // pixeles por unidad de altura
const NOTCH = rad(281), NOTCH_R = rad(314) - Math.PI * 2; // quiebre del riel, segun por que lado se llega
const RAIL = 0.925;
// Salidas reales del estadio (lado del logo): dos Over Zones en las esquinas y la Xtreme Zone al centro
const ZONES = {
  overA: { x: -1.27, y: 0.14, ex: -1.0, ey: 0.17, w: 0.3 },
  overB: { x: 0.34, y: 1.25, ex: 0.27, ey: 0.98, w: 0.3 },
  xtreme: { x: -0.95, y: 1.36, ex: -0.6, ey: 0.8, w: 0.42 },
};
const FIN = { spin: ['SPIN FINISH', 1], over: ['OVER FINISH', 2], burst: ['BURST FINISH', 2], xtreme: ['XTREME FINISH', 3] };
function rad(d) { return d * Math.PI / 180; }
const TAU = Math.PI * 2, rnd = (a, b) => a + Math.random() * (b - a);
const hyp = Math.hypot, clamp = (v, a, b) => Math.max(a, Math.min(b, v));

function proj(x, y, z) {
  const dip = Math.max(0, 1 - x * x - y * y) * 12; // el piso es concavo
  return {
    x: C.x + S.a * x + S.b * y + UP.x * (z * ZPX - dip),
    y: C.y + S.c * x + S.d * y + UP.y * (z * ZPX - dip),
  };
}

function load(src) {
  return new Promise((ok, no) => { const i = new Image(); i.onload = () => ok(i); i.onerror = no; i.src = src; });
}
function canvas(n) { const c = document.createElement('canvas'); c.width = c.height = n; return c; }

// Tres versiones del bey: nitido, con barrido de giro y oscurecido (para dar grosor)
function sprites(img) {
  const N = 320, base = canvas(N), g = base.getContext('2d');
  const k = N / Math.max(img.width, img.height), w = img.width * k, h = img.height * k;
  g.imageSmoothingQuality = 'high';
  g.drawImage(img, (N - w) / 2, (N - h) / 2, w, h);
  const spin = (deg, n) => {
    const c = canvas(N), q = c.getContext('2d');
    q.translate(N / 2, N / 2);
    for (let i = 0; i < n; i++) {
      q.save(); q.globalAlpha = 1 / (i + 1); q.rotate(rad((i / (n - 1) - 0.5) * deg));
      q.drawImage(base, -N / 2, -N / 2); q.restore();
    }
    return c;
  };
  const soft = spin(5, 7), fast = spin(34, 22), dark = canvas(N), d = dark.getContext('2d');
  d.drawImage(soft, 0, 0); d.globalCompositeOperation = 'source-atop'; d.fillStyle = 'rgba(6,8,14,.74)'; d.fillRect(0, 0, N, N);
  return { soft, fast, dark };
}

export async function startArena(cv, opt) {
  const names = opt.beys, pool = opt.pool || [], R = opt.radius || 0.28, ko = !!opt.ko;
  const base = opt.path ?? '/images/arena/';
  let stopped = false, frame = 0;
  const SP = {}, get = async (n) => { SP[n] = sprites(await load(base + n + '.webp')); };
  const [stadium] = await Promise.all([load(base + 'estadio.webp'), ...names.map(get)]);
  const rest = (async () => { for (const n of pool) if (!SP[n] && !stopped) { try { await get(n); } catch (_) {} await new Promise((ok) => setTimeout(ok, 60)); } })();
  if (opt.manual) await rest;
  let deck = [];
  function draft(taken) { // reparte beys sin repetir hasta agotar el grupo
    for (let k = 0; k < 2; k++) {
      const i = deck.findIndex((n) => SP[n] && !taken.includes(n));
      if (i >= 0) return deck.splice(i, 1)[0];
      deck = [...new Set([...pool, ...names])].sort(() => Math.random() - 0.5);
    }
    return taken[0];
  }
  if (stopped || !cv.isConnected) return null;
  const ctx = cv.getContext('2d');
  if (!ctx) return null;
  const still = matchMedia('(prefers-reduced-motion: reduce)').matches;
  let dpr = 1, cw = 0, ch = 0, view = null;

  function resize() {
    cw = cv.clientWidth; ch = cv.clientHeight; dpr = Math.min(2, window.devicePixelRatio || 1);
    cv.width = Math.round(cw * dpr); cv.height = Math.round(ch * dpr);
    const s = (opt.scale || 1) * Math.min(cw / W, ch / H);
    view = { s, x: cw * (opt.cx ?? 0.5) - (W / 2) * s, y: ch * (opt.cy ?? 0.5) - (H / 2) * s };
  }
  resize();

  const sparks = [], rings = [];
  let shake = 0, freeze = 0, time = 0, slow = 0, label = null, zoneFx = null, rot = 0;
  const pieces = [];

  // Giro derecho = horario visto desde arriba. En el riel el engranaje lo hace avanzar al reves de su giro.
  function kit(n) { const spin = BEYS[n].spin === 'left' ? -1 : 1; return { sp: SP[n], pal: BEYS[n].trail, spin, dir: -spin }; }
  const beys = names.map((n, i) => ({
    n, i, ...kit(n),
    x: 0, y: 0, z: 0, vx: 0, vy: 0, ang: rnd(0, TAU), w: 16, lean: 0.05, ph: rnd(0, TAU),
    mode: 'wait', t: -(i * 0.75 + 0.25), alpha: 1, fall: 0, vz: 0, cool: 1, think: rnd(1, 2), trail: [], glow: 0, th: 0, om: 0,
  }));

  function launch(b) {
    const a = rnd(0, TAU), r = 0.42;
    b.lx = Math.cos(a) * r; b.ly = Math.sin(a) * r;
    if (b.dir < 0) { b.sx = b.lx + 2.3; b.sy = b.ly - 1.15; } else { b.sx = b.lx - 0.75; b.sy = b.ly - 2.5; }
    b.x = b.sx; b.y = b.sy; b.z = 1.25; b.mode = 'fly'; b.t = 0; b.trail.length = 0; b.w = 24; b.glow = 0; b.th = 0; b.alpha = 1; b.fall = 0; b.down = false; b.gate = false; b.lean = 0.05; b.seek = 0; b.charge = 0;
  }
  function burst(x, y, z, n, pow, pal) {
    const p = proj(x, y, z);
    for (let i = 0; i < n; i++) {
      const a = rnd(0, TAU), v = rnd(0.25, 1) * pow;
      sparks.push({ x: p.x, y: p.y, vx: Math.cos(a) * v, vy: Math.sin(a) * v * 0.8 - pow * 0.25, t: 0, d: rnd(0.22, 0.6), c: pal[(Math.random() * pal.length) | 0], s: rnd(1.6, 4.2) });
    }
  }
  function clash(x, y, z, f) {
    burst(x, y, z, Math.round(8 + f * 11), 260 + f * 170, ['#ffffff', '#fff3b0', '#ffd21f', '#ff8a1f']);
    const near = rings.some((g) => g.t < 0.16 && hyp(g.x - x, g.y - y) < 0.3); if (!near) rings.push({ x, y, z, t: 0, f });
    shake = Math.max(shake, Math.min(11, f * 3)); if (f > 2) freeze = 0.055;
  }

  function stepBey(b, dt) {
    b.ph += dt * 7 * b.spin; if (!/exit|rest|spin/.test(b.mode)) b.lean += (0.045 - b.lean) * dt * 1.8; b.cool -= dt; b.glow = Math.max(0, b.glow - dt * 1.4);
    if (!/spin|rest|lift/.test(b.mode)) b.w += ((b.mode === 'dash' ? 30 : 10) - b.w) * dt * 2.5; b.ang += b.w * b.spin * dt; b.t += dt;

    if (b.mode === 'wait') { if (b.t >= 0) launch(b); return; }

    if (b.mode === 'fly') {
      const k = clamp(b.t / 0.5, 0, 1), e = k * k * (0.35 + 0.65 * k);
      b.x = b.sx + (b.lx - b.sx) * e; b.y = b.sy + (b.ly - b.sy) * e; b.z = 1.25 * (1 - e);
      if (k >= 1) {
        b.mode = 'free'; b.z = 0; b.t = 0; { const q = hyp(b.x, b.y) || 1; b.vx = -b.y / q * 1.5 * b.dir; b.vy = b.x / q * 1.5 * b.dir; } b.lean = 0.2; b.cool = 0.8; b.think = rnd(0.6, 1.4);
        clash(b.x, b.y, 0, 1.6);
      }
      return;
    }

    if (b.mode === 'lift') { // el ganador sale de la mano de su blader
      const k = clamp(b.t / 0.5, 0, 1); b.z = k * k * 1.1; b.alpha = 1 - k; b.lean = 0.05; b.w += (6 - b.w) * dt * 4;
      if (k >= 1) swap(b);
      return;
    }

    if (b.mode === 'exit') { // va a su zona: salta el riel y cae
      const z = b.zone, far = b.fin === 'xtreme', r = hyp(b.x, b.y);
      const gx = b.gate ? z.x : z.ex, gy = b.gate ? z.y : z.ey, dx = gx - b.x, dy = gy - b.y, d = hyp(dx, dy) || 0.001;
      const sp = far ? 4.6 : 3.1, q = Math.min(1, dt * (far ? 11 : 9));
      b.vx += (dx / d * sp - b.vx) * q; b.vy += (dy / d * sp - b.vy) * q;
      b.x += b.vx * dt; b.y += b.vy * dt; b.lean = Math.min(0.34, b.lean + dt * 0.5);
      if (!b.gate && (d < 0.16 || b.t > 1)) b.gate = true;
      if (!b.gate) b.z += ((r > 0.72 ? 0.16 : 0) - b.z) * Math.min(1, dt * 10);
      else {
        b.vz -= dt * (far ? 3.4 : 4.2); b.z += b.vz * dt;
        if (far) {
          b.alpha = clamp((2.35 - hyp(b.x, b.y)) * 2.2, 0, 1);
          if (b.t > 1.8 || hyp(b.x, b.y) > 2.35) { b.alpha = 0; b.mode = 'gone'; b.t = 0; }
          else if (d < 0.3) { b.zone = { ...z, x: z.x - 0.6, y: z.y + 0.95 }; }
        } else {
          b.z = Math.max(-0.05, b.z);
          if (d < 0.07) { b.mode = 'rest'; b.t = 0; b.x = z.x; b.y = z.y; b.vx *= 0.12; b.vy *= 0.12; b.z = Math.max(b.z, 0.02); b.vz = 0.5; shake = Math.max(shake, 5); burst(b.x, b.y, 0, 16, 240, ['#4fc3f7', '#ffffff', '#eaf3ff']); }
        }
      }
      return;
    }

    if (b.mode === 'gone') { if (b.t > 1.1) done(b); return; }

    if (b.mode === 'rest') { // dentro de la Over Zone: rebota y se detiene
      b.vz -= dt * 4.2; b.z += b.vz * dt; if (b.z < -0.05) { b.z = -0.05; b.vz = Math.abs(b.vz) * 0.42; }
      b.x += b.vx * dt; b.y += b.vy * dt; b.vx *= 1 - dt * 5; b.vy *= 1 - dt * 5;
      b.x += (b.zone.x - b.x) * dt * 3; b.y += (b.zone.y - b.y) * dt * 3;
      b.w = Math.max(0, b.w - dt * 13); b.lean = Math.min(0.42, b.lean + dt * 0.25);
      if (b.t > 1.7) { b.alpha -= dt * 2.6; if (b.alpha <= 0) done(b); }
      return;
    }

    if (b.mode === 'spin') { // pierde giro, cabecea y cae
      const k = clamp(b.t / 2.7, 0, 1);
      b.w = 10 * Math.pow(1 - k, 1.35); b.ph += dt * 27 * k * (1 - k) * b.spin; b.lean = 0.05 + 0.5 * k * k;
      b.fall = clamp((k - 0.72) / 0.28, 0, 1);
      b.vx += (-b.x * 0.8 - b.vx * 1.6) * dt; b.vy += (-b.y * 0.8 - b.vy * 1.6) * dt; b.x += b.vx * dt; b.y += b.vy * dt; { const q = hyp(b.x, b.y); if (q > 0.74) { b.x *= 0.74 / q; b.y *= 0.74 / q; b.vx *= 0.5; b.vy *= 0.5; } }
      if (k >= 1) {
        if (!b.down) { b.down = true; b.rest = b.ph; say('spin'); shake = Math.max(shake, 2.5); burst(b.x, b.y, 0, 9, 150, ['#ffffff', '#c3ccdf']); }
        b.ph = b.rest; if (b.t > 4.6) { b.alpha -= dt * 2.6; if (b.alpha <= 0) done(b); }
      }
      return;
    }

    if (b.mode === 'burst') { if (b.t > 2.5) done(b); return; }

    if (b.mode === 'dash') {
      b.om += (7.4 - b.om) * dt * 5; b.th += b.om * b.dir * dt; b.glow = 1;
      const r = hyp(b.x, b.y), nr = r + (RAIL - r) * Math.min(1, dt * 16);
      b.x = Math.cos(b.th) * nr; b.y = Math.sin(b.th) * nr;
      b.vx = -Math.sin(b.th) * b.om * nr * 0.5 * b.dir; b.vy = Math.cos(b.th) * b.om * nr * 0.5 * b.dir;
      if (Math.random() < dt * 90) {
        const p = proj(Math.cos(b.th) * 0.965, Math.sin(b.th) * 0.965, 0), a = b.th + Math.PI + rnd(0.5, 1.6) * b.dir;
        sparks.push({ x: p.x, y: p.y, vx: Math.cos(a) * rnd(120, 420), vy: Math.sin(a) * rnd(120, 420), t: 0, d: rnd(0.2, 0.45), c: Math.random() < 0.6 ? '#7dff9b' : '#ffffff', s: rnd(1.6, 3.4) });
      }
      if ((b.th - b.stop) * b.dir >= 0) { // el quiebre del riel lo dispara al centro
        let tx = -0.1, ty = 0.15;
        const o = beys.filter((q) => q !== b && q.mode === 'free')[0];
        if (o) { tx = o.x + o.vx * 0.12; ty = o.y + o.vy * 0.12; }
        const dx = tx - b.x, dy = ty - b.y, d = hyp(dx, dy) || 1;
        b.vx = dx / d * 4; b.vy = dy / d * 4; b.mode = 'free'; b.cool = 2.2; b.think = 1.2; b.charge = 0.7;
        burst(b.x, b.y, 0, 22, 420, ['#7dff9b', '#ffffff', '#2bd14f']);
        shake = Math.max(shake, 4);
      }
      return;
    }

    // libre
    const r = hyp(b.x, b.y) || 0.001, nx = b.x / r, ny = b.y / r, sp = hyp(b.vx, b.vy) || 0.001;
    b.charge = Math.max(0, (b.charge || 0) - dt);
    const k = b.charge > 0 ? 0.6 : 3.1;
    // cada bey busca volver a su sentido de recorrido
    const tv = (-ny * b.vx + nx * b.vy) * b.dir, dr = b.charge > 0 ? 0.8 : clamp((1.3 - tv) * 5.5, -1, 11);
    let ax = -b.x * k - ny * dr * b.dir, ay = -b.y * k + nx * dr * b.dir;
    const want = b.charge > 0 ? 3.4 : 1.35;
    const push = (want - sp) * (tv < 0 && sp < want ? 0.3 : 1.6); ax += b.vx / sp * push; ay += b.vy / sp * push;
    b.think -= dt;
    if (b.think <= 0) {
      b.think = rnd(1.3, 2.6);
      const o = beys.filter((q) => q !== b && q.mode === 'free');
      const busy = beys.some((q) => q.mode === 'dash');
      if (b.cool <= 0 && !busy && Math.random() < 0.62) { // busca el riel
        b.seek = 1.1;
      } else if (o.length) {
        const q = o[(Math.random() * o.length) | 0], dx = q.x - b.x, dy = q.y - b.y, d = hyp(dx, dy) || 1;
        b.vx += dx / d * 1.6; b.vy += dy / d * 1.6;
      }
    }
    if (b.seek > 0) { b.seek -= dt; ax += nx * 7.5 - ny * 3.4 * b.dir; ay += ny * 7.5 + nx * 3.4 * b.dir; }
    b.vx += ax * dt; b.vy += ay * dt;
    const m = hyp(b.vx, b.vy); if (m > 4.2) { b.vx *= 4.2 / m; b.vy *= 4.2 / m; }
    b.x += b.vx * dt; b.y += b.vy * dt;
    const r2 = hyp(b.x, b.y), a = Math.atan2(b.y, b.x), inNotch = Math.abs(((a - rad(297) + Math.PI * 3) % TAU) - Math.PI) < rad(15);
    const lim = inNotch ? 0.66 : 0.9;
    if (r2 > lim) {
      const ux = b.x / r2, uy = b.y / r2, vt = (-uy * b.vx + ux * b.vy) * b.dir;
      const th = (a + TAU) % TAU, clear = b.dir > 0 ? th < NOTCH - 0.9 || th > rad(322) : th > rad(6) && th < rad(270);
      if (clear && b.cool <= 0 && vt > 0.35 && !beys.some((q) => q.mode === 'dash')) {
        b.mode = 'dash'; b.th = th; b.stop = b.dir > 0 ? (th > NOTCH ? NOTCH + TAU : NOTCH) : NOTCH_R;
        b.om = Math.max(2.2, vt); b.seek = 0;
        burst(b.x, b.y, 0, 16, 300, ['#7dff9b', '#ffffff']);
      } else {
        b.x = ux * lim; b.y = uy * lim;
        const vr = b.vx * ux + b.vy * uy;
        if (vr > 0) { b.vx -= ux * vr * 1.75; b.vy -= uy * vr * 1.75; if (vr > 0.9) burst(b.x, b.y, 0, 6, 190, ['#7dff9b', '#ffffff']); }
      }
    }
  }

  function collide() {
    for (let i = 0; i < beys.length; i++) for (let j = i + 1; j < beys.length; j++) {
      const A = beys[i], B = beys[j];
      if ((A.mode === 'spin' && B.mode === 'free') || (B.mode === 'spin' && A.mode === 'free')) {
        const F = A.mode === 'free' ? A : B, D = F === A ? B : A, ex = F.x - D.x, ey = F.y - D.y, e = hyp(ex, ey) || 0.001, m = R * 1.95;
        if (e < m) { F.x = D.x + ex / e * m; F.y = D.y + ey / e * m; const v = F.vx * ex / e + F.vy * ey / e; if (v < 0) { F.vx -= ex / e * v * 1.5; F.vy -= ey / e * v * 1.5; D.vx += ex / e * v * 0.25; D.vy += ey / e * v * 0.25; if (v < -0.5) burst(D.x + ex / e * R, D.y + ey / e * R, 0.1, 5, 150, ['#ffffff', '#ffd21f']); } }
        continue;
      }
      if (!/free|dash/.test(A.mode) || !/free|dash/.test(B.mode)) continue;
      let dx = B.x - A.x, dy = B.y - A.y; const d = hyp(dx, dy) || 0.001, min = R * 1.9;
      if (d >= min) continue;
      dx /= d; dy /= d;
      const rv = (A.vx - B.vx) * dx + (A.vy - B.vy) * dy, o = (min - d) / 2;
      if (A.mode !== 'dash') { A.x -= dx * o; A.y -= dy * o; }
      if (B.mode !== 'dash') { B.x += dx * o; B.y += dy * o; }
      if (rv <= 0) continue;
      const dashA = A.mode === 'dash', dashB = B.mode === 'dash', hotA = dashA || A.charge > 0, hotB = dashB || B.charge > 0, armed = ko && round && round.loser && !round.over && round.type !== 'spin';
      const grip = (A.spin + B.spin) / 2 * 0.5, same = A.spin === B.spin;
      const J = same ? rv * 1.1 + 0.4 : rv * 0.96 + 0.3, kA = dashA ? 0.25 : 1, kB = dashB ? 0.25 : 1;
      A.vx -= dx * J * kA - dy * grip; A.vy -= dy * J * kA + dx * grip;
      B.vx += dx * J * kB - dy * grip; B.vy += dy * J * kB + dx * grip;
      A.charge = B.charge = 0; A.lean = Math.max(A.lean, 0.1 + rv * 0.045); B.lean = Math.max(B.lean, 0.1 + rv * 0.045);
      clash(A.x + dx * d / 2, A.y + dy * d / 2, 0.12, clamp(rv, 0.5, 4));
      if (armed) {
        const L = A === round.loser ? A : B === round.loser ? B : null, K = L === A ? B : A, sg = L === B ? 1 : -1;
        const hot = K === A ? hotA : hotB;
        const ok = L && (round.type === 'xtreme' ? hot || round.wait > 4.5 : rv > 0.9 || round.wait > 2.5);
        if (ok) finish(L, K, round.type, dx * sg, dy * sg);
      }
    }
  }

  // ---------- rondas y finales
  const bag = []; let round = null;
  function nextRound() {
    if (!bag.length) bag.push(...['xtreme', 'over', 'spin', 'burst'].sort(() => Math.random() - 0.5));
    round = { type: opt.force || bag.pop(), t: 0, wait: 0, dur: rnd(3.6, 5.4), loser: null, over: false };
  }
  function rounds(dt) {
    if (!ko) { // sin eliminaciones: cada tanto entra otro bey
      if (opt.rotate && pool.length && (rot += dt) > opt.rotate) {
        rot = 0; const c = beys.filter((b) => b.mode === 'free'); if (c.length) { const b = c[(Math.random() * c.length) | 0]; b.mode = 'lift'; b.t = 0; }
      }
      return;
    }
    if (!round) nextRound();
    if (round.over) return;
    if (!round.loser) {
      if (beys.every((b) => b.mode === 'free' || b.mode === 'dash')) round.t += dt;
      if (round.t > round.dur) round.loser = beys[opt.force ? 1 : (Math.random() * beys.length) | 0];
      return;
    }
    round.wait += dt;
    const L = round.loser, K = beys.find((b) => b !== L);
    if (round.type === 'spin') { if (L.mode === 'free') { L.mode = 'spin'; L.t = 0; L.seek = 0; round.over = true; K.cool = 9; } return; }
    if (round.type === 'xtreme' && K.mode === 'free' && !(K.charge > 0)) { K.cool = 0; K.seek = 0.5; L.seek = 0; L.cool = 1; }
    else if (K.mode === 'free' && L.mode === 'free') { // el atacante carga
      const dx = L.x - K.x, dy = L.y - K.y, d = hyp(dx, dy) || 1; K.vx += dx / d * 9 * dt; K.vy += dy / d * 9 * dt; L.seek = 0; L.cool = 1;
    }
  }
  function finish(L, K, type, nx, ny) {
    round.over = true; L.t = 0; L.seek = 0; L.charge = 0; L.fin = type; L.gate = false; L.glow = 0;
    K.cool = 9; K.seek = 0; slow = type === 'xtreme' ? 0.75 : 0.5; freeze = 0.09;
    clash(L.x, L.y, 0.12, 4);
    if (type === 'burst') {
      L.mode = 'burst'; shake = 12; burst(L.x, L.y, 0.2, 60, 620, ['#ffffff', ...L.pal]);
      const mk = (kind, v, up, w) => pieces.push({ kind, b: { sp: L.sp, pal: L.pal }, x: L.x, y: L.y, z: kind === 'blade' ? 0.22 : kind === 'ratchet' ? 0.13 : 0.06, vx: nx * v + rnd(-0.5, 0.5), vy: ny * v + rnd(-0.5, 0.5), vz: up, ang: L.ang, w: w * L.spin, ph: rnd(0, TAU), tilt: 0, t: 0 });
      mk('blade', 1.5, 1.5, 11); mk('ratchet', 0.7, 2, 7); mk('bit', -0.9, 1.7, 16);
      L.trail.length = 0; say('burst'); return;
    }
    if (type === 'over') { // la esquina mas cercana al golpe
      const f = (z) => (z.ex - L.x) * nx + (z.ey - L.y) * ny - 0.5 * hyp(z.ex - L.x, z.ey - L.y);
      L.zone = f(ZONES.overA) > f(ZONES.overB) ? ZONES.overA : ZONES.overB;
    } else L.zone = ZONES.xtreme;
    L.mode = 'exit'; L.vx = nx * 3.4; L.vy = ny * 3.4; L.vz = type === 'xtreme' ? 0.75 : 0.35; L.z = 0.02; L.lean = 0.16;
    zoneFx = { z: L.zone, type, t: 0 }; say(type);
  }
  function say(type) { label = { text: FIN[type][0], pts: FIN[type][1], type, t: 0 }; }
  function swap(b) {
    b.mode = 'wait'; b.t = -0.5; b.trail.length = 0; b.alpha = 1; b.z = 0;
    if (pool.length) { b.n = draft(beys.map((q) => q.n)); Object.assign(b, kit(b.n)); }
  }
  function done(L) { // como en un combate real, en cada ronda lanzan los dos
    swap(L); nextRound();
    beys.forEach((b) => { if (b !== L && pool.length && /free|dash/.test(b.mode)) { b.mode = 'lift'; b.t = 0; b.glow = 0; b.trail.length = 0; } });
  }
  function stepPieces(dt) {
    for (let i = pieces.length; i--;) {
      const q = pieces[i]; q.t += dt; q.vz -= 4.6 * dt; q.x += q.vx * dt; q.y += q.vy * dt; q.z += q.vz * dt; q.ang += q.w * dt; q.ph += dt * 8;
      const fl = q.kind === 'blade' ? 0.03 : 0.02;
      if (q.z < fl) { q.z = fl; q.vz = Math.abs(q.vz) * 0.38; q.vx *= 0.72; q.vy *= 0.72; q.w *= 0.7; }
      q.tilt = clamp(q.z * 1.6, 0, 0.5) + (q.z <= fl + 0.01 ? 0.02 : 0);
      const r = hyp(q.x, q.y); if (r > 0.82) { const ux = q.x / r, uy = q.y / r, v = q.vx * ux + q.vy * uy; q.x = ux * 0.82; q.y = uy * 0.82; if (v > 0) { q.vx -= ux * v * 1.6; q.vy -= uy * v * 1.6; } }
      for (const b of beys) if (/free|dash/.test(b.mode) && q.z < 0.25) {
        const ex = q.x - b.x, ey = q.y - b.y, e = hyp(ex, ey) || 0.001, m = R * (q.kind === 'blade' ? 1.95 : q.kind === 'ratchet' ? 1.5 : 1.2);
        if (e < m) { q.x += ex / e * (m - e) * Math.min(1, dt * 30); q.y += ey / e * (m - e) * Math.min(1, dt * 30); q.vx += ex / e * dt * 14; q.vy += ey / e * dt * 14; }
      }
      q.w *= 1 - dt * 1.1; if (q.t > 2.5) pieces.splice(i, 1);
    }
  }

  function body(b) { return proj(b.x, b.y, b.z + 0.2); }
  function step(dt) {
    time += dt; rounds(dt); stepPieces(dt);
    if (label && (label.t += dt) > 2.4) label = null;
    if (zoneFx && (zoneFx.t += dt) > 1.5) zoneFx = null;
    for (const b of beys) stepBey(b, dt);
    collide();
    for (const b of beys) {
      if (/wait|burst|rest|spin|lift|gone/.test(b.mode)) { b.trail.shift(); continue; }
      const p = body(b); b.trail.push({ x: p.x, y: p.y, v: b.mode === 'fly' || b.mode === 'exit' ? 5 : b.mode === 'dash' ? 4.2 : hyp(b.vx, b.vy) });
      const keep = b.mode === 'fly' ? 62 : b.mode === 'dash' ? 58 : 26;
      while (b.trail.length > keep) b.trail.shift();
    }
    for (let i = sparks.length; i--;) { const s = sparks[i]; s.t += dt; s.x += s.vx * dt; s.y += s.vy * dt; s.vy += 520 * dt; s.vx *= 1 - dt * 1.6; if (s.t > s.d) sparks.splice(i, 1); }
    for (let i = rings.length; i--;) { rings[i].t += dt; if (rings[i].t > 0.42) rings.splice(i, 1); }
    shake *= Math.exp(-dt * 9);
  }

  // ---------- dibujo
  function drawTrail(b) {
    const T = b.trail; if (T.length < 4) return;
    const half = R * 300 * 0.74, n = b.pal.length;
    for (let k = 0; k < n; k++) {
      const off = ((k + 0.5) / n * 2 - 1) * half, wid = half * 2 / n * (k === 2 ? 0.22 : 0.62);
      const lag = Math.round(((k * 7) % 5) * 1.6), from = Math.min(T.length - 3, lag);
      ctx.beginPath(); let L = [], Rr = [];
      for (let i = from; i < T.length; i++) {
        const a = T[Math.max(0, i - 2)], c = T[Math.min(T.length - 1, i + 2)], dx = c.x - a.x, dy = c.y - a.y, d = hyp(dx, dy) || 1;
        const u = (i - from) / (T.length - 1 - from), q = wid * 0.5 * Math.sqrt(u), ox = -dy / d, oy = dx / d, f = off * (0.35 + 0.65 * u);
        L.push([T[i].x + ox * (f + q), T[i].y + oy * (f + q)]); Rr.push([T[i].x + ox * (f - q), T[i].y + oy * (f - q)]);
      }
      L.concat(Rr.reverse()).forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
      const v = T[T.length - 1].v;
      ctx.globalAlpha = clamp((v - 1.35) / 1.5, 0, 1) * 0.95; ctx.fillStyle = b.pal[k]; ctx.fill();
    }
    ctx.globalAlpha = 1;
  }
  function drawRail(b) {
    if (b.glow <= 0 || !b.th) return;
    ctx.save(); ctx.translate(C.x, C.y); ctx.transform(S.a, S.c, S.b, S.d, 0, 0);
    const end = b.mode === 'dash' ? b.th : b.stop || b.th, len = 1.25;
    ctx.globalCompositeOperation = 'lighter'; ctx.lineCap = 'round';
    for (let i = 0; i < 10; i++) {
      ctx.beginPath(); ctx.arc(0, 0, 1, end - b.dir * len * (1 - i / 10), end, b.dir < 0);
      ctx.strokeStyle = `rgba(90,255,140,${0.055 * b.glow})`; ctx.lineWidth = 0.075; ctx.stroke();
    }
    ctx.restore();
  }
  function disc(p, r, lx, ly) { // deja el contexto en el plano del bey
    ctx.translate(p.x, p.y); ctx.transform(S.a * r, S.c * r, S.b * r, S.d * r, 0, 0);
    const l = hyp(lx, ly); if (l > 0.001) { const a = Math.atan2(ly, lx); ctx.rotate(a); ctx.scale(1 - Math.min(0.3, l * 0.55), 1); ctx.rotate(-a); }
  }
  function drawShadow(b) {
    const p = proj(b.x, b.y, 0), g = 1 + b.z * 0.5; ctx.save();
    ctx.translate(p.x - 10 - b.z * 60, p.y + 14 + b.z * 40); ctx.transform(S.a * R * g, S.c * R * g, S.b * R * g, S.d * R * g, 0, 0);
    const gr = ctx.createRadialGradient(0, 0, 0.15, 0, 0, 1.12), al = clamp(0.62 - b.z * 0.45, 0, 0.62);
    gr.addColorStop(0, `rgba(10,14,36,${al})`); gr.addColorStop(0.62, `rgba(10,14,36,${al * 0.62})`); gr.addColorStop(1, 'rgba(10,14,36,0)');
    ctx.fillStyle = gr; ctx.beginPath(); ctx.arc(0, 0, 1.12, 0, TAU); ctx.fill(); ctx.restore();
  }
  function drawZone() {
    if (!zoneFx) return; const { z, type, t } = zoneFx, k = t / 1.5, p = proj(type === 'xtreme' ? -0.78 : z.x, type === 'xtreme' ? 1.06 : z.y, 0);
    const al = (1 - k) * (0.65 + 0.35 * Math.sin(t * 26)), c = type === 'xtreme' ? '60,255,110' : '60,170,255';
    ctx.save(); ctx.globalCompositeOperation = 'source-atop'; ctx.translate(p.x, p.y); ctx.transform(S.a * z.w, S.c * z.w, S.b * z.w, S.d * z.w, 0, 0);
    const g = ctx.createRadialGradient(0, 0, 0, 0, 0, 1.25); g.addColorStop(0, `rgba(${c},${0.95 * al})`); g.addColorStop(0.6, `rgba(${c},${0.5 * al})`); g.addColorStop(1, `rgba(${c},0)`);
    ctx.fillStyle = g; ctx.beginPath(); ctx.arc(0, 0, 1.25, 0, TAU); ctx.fill(); ctx.restore();
  }
  function drawPiece(q) {
    const p = proj(q.x, q.y, q.z), al = clamp((2.5 - q.t) * 3, 0, 1), lx = Math.cos(q.ph) * q.tilt, ly = Math.sin(q.ph) * q.tilt;
    const s0 = proj(q.x, q.y, 0), rr = R * (q.kind === 'blade' ? 1 : q.kind === 'ratchet' ? 0.56 : 0.24);
    ctx.save(); ctx.globalAlpha = al;
    ctx.save(); ctx.translate(s0.x - q.z * 60, s0.y + 8 + q.z * 40); ctx.transform(S.a * rr, S.c * rr, S.b * rr, S.d * rr, 0, 0);
    const sg = ctx.createRadialGradient(0, 0, 0.2, 0, 0, 1.15); sg.addColorStop(0, 'rgba(10,14,36,.5)'); sg.addColorStop(1, 'rgba(10,14,36,0)');
    ctx.fillStyle = sg; ctx.beginPath(); ctx.arc(0, 0, 1.15, 0, TAU); ctx.fill(); ctx.restore();
    if (q.kind === 'blade') {
      for (let i = 6; i >= 1; i--) { ctx.save(); disc({ x: p.x - UP.x * i * 2.1, y: p.y - UP.y * i * 2.1 }, R, lx, ly); ctx.rotate(q.ang); ctx.drawImage(q.b.sp.dark, -1, -1, 2, 2); ctx.restore(); }
      disc(p, R, lx, ly); ctx.rotate(q.ang); ctx.drawImage(q.b.sp.soft, -1, -1, 2, 2);
    } else if (q.kind === 'ratchet') {
      for (let i = 7; i >= 0; i--) {
        ctx.save(); disc({ x: p.x - UP.x * i * 2.2, y: p.y - UP.y * i * 2.2 }, rr, lx, ly); ctx.rotate(q.ang); ctx.beginPath();
        for (let k = 0; k < 36; k++) { const a = k / 36 * TAU, m = k % 12 < 5 ? 1 : 0.8; ctx.lineTo(Math.cos(a) * m, Math.sin(a) * m); }
        ctx.closePath(); ctx.moveTo(0.42, 0); ctx.arc(0, 0, 0.42, 0, TAU, true); ctx.fillStyle = i ? '#171b26' : q.b.pal[1]; ctx.fill('evenodd');
        if (!i) { ctx.lineWidth = 0.06; ctx.strokeStyle = 'rgba(255,255,255,.55)'; ctx.stroke(); } ctx.restore();
      }
    } else {
      for (let i = 8; i >= 0; i--) { ctx.save(); disc({ x: p.x - UP.x * i * 2.4, y: p.y - UP.y * i * 2.4 }, rr * (1 - i * 0.07), lx, ly); ctx.fillStyle = i ? '#20242f' : q.b.pal[3]; ctx.beginPath(); ctx.arc(0, 0, 1, 0, TAU); ctx.fill(); if (!i) { ctx.rotate(q.ang); ctx.fillStyle = 'rgba(255,255,255,.7)'; ctx.fillRect(-0.7, -0.13, 1.4, 0.26); } ctx.restore(); }
    }
    ctx.restore();
  }
  function drawLabel() {
    if (!label || !opt.hud) return;
    const t = label.t, inn = clamp(t / 0.16, 0, 1), out = clamp((2.4 - t) / 0.22, 0, 1), e = 1 - (1 - inn) ** 3, top = opt.hud === 'top';
    const px = clamp(70 * view.s, 15, 46), x = top ? 8 : view.x + 6 * view.s, y = top ? px * 1.1 : view.y + 985 * view.s;
    ctx.save(); ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.translate(Math.max(8, x) - (1 - e) * 60, y); ctx.transform(1, 0, -0.2, 1, 0, 0); ctx.globalAlpha = Math.min(e, out);
    try { ctx.fontStretch = 'condensed'; } catch (_) {}
    ctx.font = `900 ${px}px 'Archivo Variable', Arial, sans-serif`;
    const w = ctx.measureText(label.text).width, q = `+${label.pts}`, w2 = ctx.measureText(q).width, h = px * 1.28, pad = px * 0.34;
    const c = { xtreme: '#2bd14f', over: '#4fc3f7', burst: '#ff8a1f', spin: '#f4f6fb' }[label.type];
    ctx.fillStyle = c; ctx.fillRect(0, -h * 0.5, w + pad * 2, h); ctx.fillStyle = '#0b0c10'; ctx.fillRect(w + pad * 2, -h * 0.5, w2 + pad * 2, h);
    ctx.strokeStyle = c; ctx.lineWidth = 2; ctx.strokeRect(w + pad * 2, -h * 0.5 + 1, w2 + pad * 2 - 1, h - 2);
    ctx.textBaseline = 'middle'; ctx.fillStyle = '#0b0c10'; ctx.fillText(label.text, pad, px * 0.05); ctx.fillStyle = c; ctx.fillText(q, w + pad * 3, px * 0.05);
    ctx.restore();
  }
  function drawBey(b) {
    const lx = Math.cos(b.ph) * b.lean, ly = Math.sin(b.ph) * b.lean, sc = (1 + b.z * 0.32) * (b.mode === 'rest' ? 0.92 : 1), r = R * sc;
    ctx.save(); ctx.globalAlpha = clamp(b.alpha, 0, 1);
    const foot = proj(b.x, b.y, b.z), lift = 40 * sc * (1 - 0.6 * b.fall), top = { x: foot.x + UP.x * lift + lx * 60, y: foot.y + UP.y * lift + ly * 60 };
    const at = (k) => ({ x: foot.x + (top.x - foot.x) * k, y: foot.y + (top.y - foot.y) * k });
    for (let i = 0; i <= 8; i++) { // punta y ratchet
      const k = i / 8 * 0.72, p = at(k), q = i < 4 ? 0.13 + i * 0.035 : 0.4 + (i - 4) * 0.05;
      ctx.save(); disc(p, r * q, 0, 0); ctx.fillStyle = i < 4 ? '#20242f' : i === 8 ? b.pal[1] : b.pal[0]; ctx.beginPath(); ctx.arc(0, 0, 1, 0, TAU); ctx.fill(); ctx.restore();
    }
    for (let i = 6; i >= 1; i--) { // grosor del metal
      const p = { x: top.x - UP.x * i * 2.1 * sc, y: top.y - UP.y * i * 2.1 * sc };
      ctx.save(); disc(p, r, lx, ly); ctx.rotate(b.ang); ctx.drawImage(b.sp.dark, -1, -1, 2, 2); ctx.restore();
    }
    ctx.save(); disc(top, r, lx, ly);
    ctx.save(); ctx.rotate(b.ang);
    const f = clamp((b.w - 13) / 12, 0, 1);
    ctx.drawImage(b.sp.soft, -1, -1, 2, 2);
    if (f > 0) { ctx.globalAlpha = f * clamp(b.alpha, 0, 1); ctx.drawImage(b.sp.fast, -1, -1, 2, 2); }
    ctx.restore();
    // reflejo fijo: la luz no gira con el bey
    ctx.beginPath(); ctx.arc(0, 0, 0.93, 0, TAU); ctx.clip();
    ctx.globalCompositeOperation = 'soft-light';
    const cg = ctx.createConicGradient(-2.5 + lx * 2, 0, 0);
    [[0, .75], [.1, 0], [.28, 0], [.36, .35], [.44, 0], [.6, 0], [.68, .55], [.78, 0], [.92, 0], [1, .75]].forEach(([s, a]) => cg.addColorStop(s, `rgba(255,255,255,${a})`));
    ctx.fillStyle = cg; ctx.fillRect(-1, -1, 2, 2);
    ctx.restore();
    // arcos de velocidad
    ctx.save(); disc(top, r, lx, ly); ctx.lineCap = 'round';
    for (let i = 0; i < 3; i++) {
      const a = b.ang * 0.55 + i * 2.1; ctx.beginPath(); ctx.arc(0, 0, 1.03 + i * 0.035, a, a + 0.7 + f * 0.6);
      ctx.strokeStyle = `rgba(255,255,255,${(0.22 + f * 0.4) * clamp(b.w / 8, 0, 1)})`; ctx.lineWidth = 0.022; ctx.stroke();
    }
    ctx.restore(); ctx.restore();
  }
  function draw() {
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cw, ch);
    ctx.translate(view.x + (Math.random() - 0.5) * shake, view.y + (Math.random() - 0.5) * shake); ctx.scale(view.s, view.s);
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(stadium, 0, 0, W, H);
    const on = beys.filter((b) => !/wait|burst|gone/.test(b.mode)).sort((p, q) => (p.z - q.z) || (body(p).y - body(q).y));
    drawZone(); on.forEach(drawRail); on.forEach((b) => { if (b.alpha > 0.5 && hyp(b.x, b.y) < 1.45) drawShadow(b); }); on.forEach(drawTrail); on.forEach(drawBey); pieces.forEach(drawPiece);
    if (on.some((b) => /exit|rest/.test(b.mode) && hyp(b.x, b.y) > 0.98)) { // la tapa transparente queda por encima de las zonas
      ctx.save(); ctx.beginPath(); ctx.rect(0, 0, W, H); ctx.save(); ctx.translate(C.x, C.y); ctx.transform(S.a, S.c, S.b, S.d, 0, 0); ctx.arc(0, 0, 1.03, 0, TAU, true); ctx.restore(); ctx.clip('evenodd');
      ctx.globalAlpha = 0.3; ctx.drawImage(stadium, 0, 0, W, H); ctx.restore();
    }
    for (const g of rings) {
      const k = g.t / 0.42, p = proj(g.x, g.y, g.z); ctx.save(); ctx.translate(p.x, p.y); ctx.globalCompositeOperation = 'lighter';
      if (k < 0.45) { // destello
        const r = (70 + g.f * 34) * (0.5 + k), q = ctx.createRadialGradient(0, 0, 0, 0, 0, r);
        q.addColorStop(0, `rgba(255,255,255,${1 - k * 2.2})`); q.addColorStop(0.35, `rgba(255,214,90,${0.7 - k * 1.5})`); q.addColorStop(1, 'rgba(255,140,30,0)');
        ctx.fillStyle = q; ctx.beginPath(); ctx.arc(0, 0, r, 0, TAU); ctx.fill();
      }
      ctx.globalCompositeOperation = 'source-over';
      ctx.transform(S.a / 320, S.c / 320, S.b / 320, S.d / 320, 0, 0);
      ctx.beginPath(); ctx.arc(0, 0, 30 + (90 + g.f * 46) * (1 - (1 - k) ** 3), 0, TAU); ctx.strokeStyle = `rgba(255,255,255,${(1 - k) * 0.9})`; ctx.lineWidth = 9 * (1 - k); ctx.stroke(); ctx.restore();
    }
    ctx.lineCap = 'round';
    for (const s of sparks) {
      ctx.globalAlpha = 1 - s.t / s.d; ctx.strokeStyle = s.c; ctx.lineWidth = s.s; ctx.beginPath(); ctx.moveTo(s.x, s.y); ctx.lineTo(s.x - s.vx * 0.035, s.y - s.vy * 0.035); ctx.stroke();
    }
    ctx.globalAlpha = 1; drawLabel();
  }

  const ro = new ResizeObserver(() => { if (!stopped && cv.clientWidth) { resize(); draw(); } });
  ro.observe(cv);
  let visible = true, last = 0, acc = 0, io = null;
  const api = { destroy() { stopped = true; cancelAnimationFrame(frame); ro.disconnect(); io?.disconnect(); }, step, draw, pieces, get round() { return round; }, get label() { return label; }, run(sec) { for (let i = 0; i < sec * 120; i++) step(1 / 120); draw(); }, beys };
  if (still) { // movimiento reducido: una pose fija
    beys.forEach((b, i) => { const a = -2.2 + i * TAU / beys.length; b.mode = 'free'; b.x = Math.cos(a) * 0.42; b.y = Math.sin(a) * 0.42; });
    draw(); return api;
  }
  io = new IntersectionObserver((e) => { visible = e[0].isIntersecting; });
  io.observe(cv);
  if (opt.manual) { draw(); return api; }
  (function loop(now) {
    frame = requestAnimationFrame(loop);
    const dt = Math.min(0.05, (now - last) / 1000 || 0); last = now;
    if (!visible || document.hidden) return;
    if (freeze > 0) freeze -= dt; else { if (slow > 0) slow -= dt; acc += dt * (slow > 0 ? 0.38 : 1); while (acc > 1 / 120) { step(1 / 120); acc -= 1 / 120; } }
    draw();
  })(0);
  return api;
};
