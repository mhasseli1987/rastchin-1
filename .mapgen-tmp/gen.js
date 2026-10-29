// مولد نقشه ایران: سیلوئت دست‌ساز + بذر استان‌ها → مش بدون درز
'use strict';
const fs = require('fs');

const W = 1000, H = 900, CELL = 10;
const COLS = Math.round(W / CELL), ROWS = Math.round(H / CELL);

// --- سیلوئت ایران (دست‌ساز، تقریبی) — clockwise از گوشه شمال‌غربی
const SILHOUETTE = [
  [35, 35], [85, 47], [135, 76], [210, 104], [240, 110], [256, 101], // مرز ترکیه/ارمنستان/آذربایجان تا آستارا
  [277, 158], [310, 168], [340, 192], [385, 206], [430, 220], [470, 214], [505, 192], [535, 168], // ساحل خزر
  [610, 156], [685, 150], [735, 156], [785, 162], [835, 208], [855, 191], // مرز ترکمنستان
  [865, 278], [880, 347], [890, 417], [860, 533], [900, 591], [940, 672], [970, 753], [930, 812], [890, 870], // مرز افغانستان و پاکستان
  [840, 864], [700, 847], [625, 754], [550, 788], [510, 794], [460, 730], [410, 690], [350, 655], // ساحل خلیج فارس و مکران
  [260, 591], [210, 568], [185, 533], [115, 446], [85, 365], [110, 301], [50, 179], // مرز عراق تا سه‌مرزی ترکیه
];

// --- بذر ۳۱ استان: [کد, نام, اولویت(≈log مساحت؛ کوچک‌تر مقدم), چندضلعی تقریبی با وسعت واقعی]
const SEEDS = [
  ['THR', 'تهران', 1.74, [[330, 235], [415, 238], [418, 300], [385, 330], [330, 315]]],
  ['ALB', 'البرز', 1.38, [[300, 250], [348, 248], [350, 294], [304, 296]]],
  ['QHM', 'قم', 1.30, [[320, 330], [368, 328], [370, 372], [322, 372]]],
  ['GZN', 'قزوین', 1.48, [[255, 208], [314, 204], [318, 262], [258, 268]]],
  ['ZJN', 'زنجان', 1.98, [[195, 204], [264, 200], [268, 262], [198, 266]]],
  ['WAZ', 'آذربایجان غربی', 2.24, [[36, 52], [150, 48], [176, 118], [160, 232], [92, 235], [40, 150]]],
  ['EAZ', 'آذربایجان شرقی', 2.29, [[148, 62], [242, 126], [246, 238], [182, 266], [148, 205]]],
  ['ARD', 'اردبیل', 1.90, [[208, 94], [272, 90], [276, 173], [224, 186], [206, 140]]],
  ['GIL', 'گیلان', 1.78, [[262, 145], [338, 170], [346, 212], [300, 232], [260, 202]]],
  ['MST', 'مازندران', 2.00, [[334, 190], [502, 187], [510, 242], [420, 258], [340, 246]]],
  ['GOL', 'گلستان', 1.93, [[508, 180], [604, 170], [610, 233], [545, 260], [508, 236]]],
  ['NKH', 'خراسان شمالی', 2.08, [[604, 158], [700, 158], [706, 243], [640, 256], [602, 224]]],
  ['RKH', 'خراسان رضوی', 2.70, [[656, 242], [738, 172], [848, 208], [862, 345], [800, 415], [690, 400], [650, 330]]],
  ['SKH', 'خراسان جنوبی', 2.60, [[648, 425], [790, 418], [876, 495], [858, 555], [720, 540], [640, 480]]],
  ['SMN', 'سمنان', 2.60, [[482, 240], [600, 232], [652, 300], [640, 462], [560, 456], [500, 395], [478, 300]]],
  ['ISF', 'اصفهان', 2.66, [[370, 375], [548, 352], [585, 430], [560, 538], [428, 528], [368, 445]]],
  ['YZD', 'یزد', 2.66, [[500, 368], [628, 392], [655, 495], [600, 552], [540, 545], [502, 462]]],
  ['CHB', 'چهارمحال و بختیاری', 1.85, [[370, 462], [432, 458], [438, 516], [376, 518]]],
  ['MKZ', 'مرکزی', 2.10, [[302, 300], [402, 310], [405, 398], [350, 428], [304, 378]]],
  ['HDN', 'همدان', 1.90, [[222, 272], [316, 270], [320, 358], [268, 398], [224, 348]]],
  ['KRD', 'کردستان', 2.10, [[70, 242], [174, 240], [176, 330], [120, 378], [70, 328]]],
  ['KRH', 'کرمانشاه', 2.00, [[122, 322], [222, 320], [226, 418], [160, 444], [122, 398]]],
  ['ILA', 'ایلام', 1.90, [[76, 378], [158, 372], [162, 468], [110, 498], [74, 448]]],
  ['LRS', 'لرستان', 2.08, [[178, 398], [282, 394], [286, 478], [226, 518], [176, 462]]],
  ['KHZ', 'خوزستان', 2.44, [[172, 462], [298, 468], [332, 558], [290, 636], [206, 610], [168, 528]]],
  ['QSM', 'کهگیلویه و بویراحمد', 1.81, [[300, 528], [362, 520], [368, 582], [304, 590]]],
  ['BJR', 'بوشهر', 2.00, [[298, 580], [382, 575], [412, 660], [355, 700], [295, 652]]],
  ['FRS', 'فارس', 2.72, [[352, 522], [520, 540], [578, 640], [525, 728], [405, 718], [352, 620]]],
  ['HRZ', 'هرمزگان', 2.48, [[462, 762], [520, 705], [640, 705], [665, 790], [600, 856], [500, 830]]],
  ['KRN', 'کرمان', 2.89, [[562, 650], [580, 528], [680, 472], [795, 545], [828, 680], [745, 800], [610, 810], [565, 700]]],
  ['STA', 'سیستان و بلوچستان', 2.89, [[700, 545], [872, 540], [950, 700], [925, 820], [790, 862], [665, 795], [690, 650]]],
];

// --- هندسه کمکی
function inPoly(px, py, poly) {
  let inside = false;
  for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
    const [xi, yi] = poly[i], [xj, yj] = poly[j];
    if ((yi > py) !== (yj > py) && px < ((xj - xi) * (py - yi)) / (yj - yi) + xi) inside = !inside;
  }
  return inside;
}
const key = (c, r) => r * COLS + c;

// --- شطرنجی‌سازی: هر سلول داخل سیلوئت → استانِ بذرِ دربرگیرنده با کوچک‌ترین اولویت
const grid = new Int8Array(COLS * ROWS).fill(-1);
const codes = SEEDS.map((s) => s[0]);
for (let r = 0; r < ROWS; r++) {
  for (let c = 0; c < COLS; c++) {
    const x = c * CELL + CELL / 2, y = r * CELL + CELL / 2;
    if (!inPoly(x, y, SILHOUETTE)) continue;
    let best = -1, bestPri = Infinity;
    SEEDS.forEach((s, i) => {
      if (inPoly(x, y, s[3]) && s[2] < bestPri) { best = i; bestPri = s[2]; }
    });
    grid[key(c, r)] = best;
  }
}

// --- مساحت هدف هر استان به سلول (بر اساس مساحت واقعی نسبی) برای رشد موزون
const TARGETS = { STA: 780, KRN: 780, FRS: 525, RKH: 505, YZD: 555, ISF: 460, SMN: 415, SKH: 405, HRZ: 305, KHZ: 275, WAZ: 175, EAZ: 195, MST: 100, GOL: 85, NKH: 120, ARD: 80, ZJN: 95, GZN: 70, HDN: 80, KRD: 125, KRH: 105, ILA: 80, LRS: 120, MKZ: 125, THR: 55, ALB: 20, QHM: 45, CHB: 70, QSM: 65, BJR: 100, GIL: 60 };

// --- پرکردن خانه‌های بدون بذر: رشد موزون Dijkstra — استان بزرگ‌تر سریع‌تر رشد می‌کند تا اندازه‌ها نزدیک هدف بمانند
{
  const w = SEEDS.map((s, i) => 1 / Math.sqrt(TARGETS[s[0]] || 100)); // هزینه گام: استان بزرگ گرانایرانی‌تر؟ نه — گام ارزان‌تر
  const INF = 1e12;
  const dist = new Float64Array(COLS * ROWS).fill(INF);
  // صف اولویت ساده (آرایه + مرتب‌سازی هر بار کافی است برای ~7000 سلول؟ نه — heap ساده می‌سازیم)
  const heap = [];
  const push = (d, k) => { heap.push([d, k]); let i = heap.length - 1; while (i > 0) { const p = (i - 1) >> 1; if (heap[p][0] <= heap[i][0]) break; [heap[p], heap[i]] = [heap[i], heap[p]]; i = p; } };
  const pop = () => { const top = heap[0]; const last = heap.pop(); if (heap.length) { heap[0] = last; let i = 0; for (;;) { const l = 2 * i + 1, r = l + 1; let m = i; if (l < heap.length && heap[l][0] < heap[m][0]) m = l; if (r < heap.length && heap[r][0] < heap[m][0]) m = r; if (m === i) break; [heap[m], heap[i]] = [heap[i], heap[m]]; i = m; } } return top; };
  for (let r = 0; r < ROWS; r++) {
    for (let c = 0; c < COLS; c++) {
      const k = key(c, r);
      if (grid[k] >= 0) { dist[k] = 0; push(0, k); }
    }
  }
  while (heap.length) {
    const [d, k] = pop();
    if (d > dist[k]) continue;
    const v = grid[k];
    const c = k % COLS, r = (k - c) / COLS;
    [[c - 1, r], [c + 1, r], [c, r - 1], [c, r + 1]].forEach(([cc, rr]) => {
      if (cc < 0 || rr < 0 || cc >= COLS || rr >= ROWS) return;
      const nk = key(cc, rr);
      const nd = d + w[v];
      if (nd < dist[nk] - 1e-9) { dist[nk] = nd; grid[nk] = v; push(nd, nk); }
    });
  }
}

// --- ردیابی مرز هر استان: یال‌های سلولی → حلقه
function ringOf(idx) {
  const edges = new Map(); // "x1,y1|x2,y2" مرتب
  const push = (a, b) => {
    const e = [a, b].sort((p, q) => (p[0] - q[0]) || (p[1] - q[1]));
    const k = e[0].join(',') + '|' + e[1].join(',');
    if (edges.has(k)) edges.delete(k); else edges.set(k, e);
  };
  for (let r = 0; r < ROWS; r++) {
    for (let c = 0; c < COLS; c++) {
      if (grid[key(c, r)] !== idx) continue;
      const x = c * CELL, y = r * CELL;
      const up = r > 0 && grid[key(c, r - 1)] === idx;
      const dn = r < ROWS - 1 && grid[key(c, r + 1)] === idx;
      const lf = c > 0 && grid[key(c - 1, r)] === idx;
      const rt = c < COLS - 1 && grid[key(c + 1, r)] === idx;
      if (!up) push([x, y], [x + CELL, y]);
      if (!dn) push([x, y + CELL], [x + CELL, y + CELL]);
      if (!lf) push([x, y], [x, y + CELL]);
      if (!rt) push([x + CELL, y], [x + CELL, y + CELL]);
    }
  }
  // زنجیره‌سازی یال‌ها به حلقه — هر یال از هر دو سر قابل پیمایش است
  const atPoint = new Map(); // "x,y" -> [edgeKey,...]
  edges.forEach((e, k) => {
    for (const p of e) {
      const pk = p.join(',');
      if (!atPoint.has(pk)) atPoint.set(pk, []);
      atPoint.get(pk).push(k);
    }
  });
  const rings = [];
  while (edges.size) {
    const firstKey = edges.keys().next().value;
    const first = edges.get(firstKey);
    edges.delete(firstKey);
    const start = first[0].join(',');
    const ring = [first[0], first[1]];
    let guard = 0;
    while (guard++ < 50000) {
      const cur = ring[ring.length - 1].join(',');
      if (cur === start) { ring.pop(); break; }
      const cands = (atPoint.get(cur) || []).filter((k) => edges.has(k));
      if (!cands.length) break;
      const e = edges.get(cands[0]);
      edges.delete(cands[0]);
      ring.push(e[0].join(',') === cur ? e[1] : e[0]);
    }
    rings.push(ring);
  }
  rings.sort((a, b) => b.length - a.length);
  return rings[0] || [];
}

// --- نقاط تلاقی چند استان: نقطه‌ شبکه‌ای که چهار سلول پیرامونش ≥۳ مالک دارد باید در همه حلقه‌ها بماند (پیشگیری از درز)
const JUNCTIONS = new Set();
for (let r = 0; r <= ROWS; r++) {
  for (let c = 0; c <= COLS; c++) {
    const ids = new Set();
    [[c - 1, r - 1], [c, r - 1], [c - 1, r], [c, r]].forEach(([cc, rr]) => {
      if (cc < 0 || rr < 0 || cc >= COLS || rr >= ROWS) return;
      const v = grid[key(cc, rr)];
      if (v >= 0) ids.add(v);
    });
    if (ids.size >= 3) JUNCTIONS.add(c * CELL + ',' + r * CELL);
  }
}

// --- حذف نقاط هم‌خط (به‌جز نقاط تلاقی چند استان)
function simplify(points) {
  const out = [];
  for (let i = 0; i < points.length; i++) {
    const a = points[(i - 1 + points.length) % points.length];
    const b = points[i];
    const c = points[(i + 1) % points.length];
    const cross = (b[0] - a[0]) * (c[1] - a[1]) - (b[1] - a[1]) * (c[0] - a[0]);
    if (Math.abs(cross) > 0.5 || JUNCTIONS.has(b[0] + ',' + b[1])) out.push(b); // نقطه شکست واقعی یا نقطه تلاقی
  }
  return out;
}

// --- لرزش قطعیِ هر یال: کلید یال = دو سر مرتب‌شده → هر دو استان همسایه خروجی یکسان می‌گیرند
const edgeCache = new Map();
function jitteredEdge(a, b) {
  const ck = [a, b].sort((p, q) => (p[0] - q[0]) || (p[1] - q[1]));
  const cacheKey = ck[0].join(',') + '|' + ck[1].join(',');
  if (edgeCache.has(cacheKey)) return edgeCache.get(cacheKey);
  const dx = b[0] - a[0], dy = b[1] - a[1];
  const len = Math.hypot(dx, dy);
  const nx = -dy / len, ny = dx / len; // عمود
  // بذر شبه‌تصادفی پایدار از مختصات
  let h = 2166136261;
  for (const ch of cacheKey) { h ^= ch.charCodeAt(0); h = Math.imul(h, 16777619); }
  const rand = (i) => {
    let v = (h ^ Math.imul(i + 1, 2654435761)) >>> 0;
    return (v % 1000) / 1000 - 0.5; // [-0.5, 0.5)
  };
  const steps = Math.max(2, Math.min(6, Math.round(len / 45)));
  const pts = [];
  for (let i = 0; i <= steps; i++) {
    const t = i / steps;
    let x = a[0] + dx * t, y = a[1] + dy * t;
    if (i > 0 && i < steps) {
      const amp = 5.5;
      const off = Math.sin(t * Math.PI) * (0.3 + rand(i)) * amp; // وسط یال بیشترین جابه‌جایی، دو سر دست‌نخورده
      x += nx * off; y += ny * off;
    }
    pts.push([Math.round(x * 10) / 10, Math.round(y * 10) / 10]);
  }
  edgeCache.set(cacheKey, pts);
  return pts;
}

// --- خروجی
const result = {};
const report = [];
SEEDS.forEach((s, i) => {
  let ring = simplify(ringOf(i));
  if (ring.length < 4) { report.push(`${s[0]}: BAD ring (${ring.length})`); result[s[0]] = ''; return; }
  const d = [];
  for (let j = 0; j < ring.length; j++) {
    const pts = jitteredEdge(ring[j], ring[(j + 1) % ring.length]);
    pts.forEach((p, k) => {
      if (j > 0 && k === 0) return; // نقطه اشتراکی یک‌بار
      d.push((j === 0 && k === 0 ? 'M' : 'L') + p[0] + ',' + p[1]);
    });
  }
  result[s[0]] = d.join('') + 'Z';
  // گزارش مرکز و مساحت برای کنترل جغرافیایی
  const cx = ring.reduce((t, p) => t + p[0], 0) / ring.length;
  const cy = ring.reduce((t, p) => t + p[1], 0) / ring.length;
  let area = 0;
  for (let j = 0; j < ring.length; j++) {
    const p = ring[j], q = ring[(j + 1) % ring.length];
    area += p[0] * q[1] - q[0] * p[1];
  }
  report.push(`${s[0].padEnd(4)} centroid=(${Math.round(cx)},${Math.round(cy)}) area=${Math.round(Math.abs(area) / 2)} pts=${ring.length}`);
});

fs.writeFileSync(__dirname + '/paths.json', JSON.stringify(result, null, 1), 'utf8');
console.log(report.join('\n'));

// --- اعتبارسنجی: پوشش کامل شطرنجی (هیچ سلول داخل سیلوئت بدون مالک نباشد) + شمار استان‌ها
let uncovered = 0, owned = {};
for (let r = 0; r < ROWS; r++) {
  for (let c = 0; c < COLS; c++) {
    const k = key(c, r);
    if (grid[k] === -1 && inPoly(c * CELL + 5, r * CELL + 5, SILHOUETTE)) uncovered++;
    if (grid[k] >= 0) owned[codes[grid[k]]] = (owned[codes[grid[k]]] || 0) + 1;
  }
}
console.log('\nuncovered cells inside silhouette:', uncovered);
console.log('provinces with cells:', Object.keys(owned).length, '/', SEEDS.length);
console.log('cell counts:', Object.entries(owned).sort((a, b) => b[1] - a[1]).map(([k2, v]) => k2 + ':' + v).join(' '));
// دیاگنوستیک: سلول بذری در برابر نهایی + باکس هر استان
SEEDS.forEach((s, i) => {
  let seeded = 0, xs = [], ys = [];
  for (let r = 0; r < ROWS; r++) for (let c = 0; c < COLS; c++) {
    const k = key(c, r);
    if (grid[k] === i) { xs.push(c * CELL); ys.push(r * CELL); }
    if (inPoly(c * CELL + 5, r * CELL + 5, s[3]) && inPoly(c * CELL + 5, r * CELL + 5, SILHOUETTE)) seeded++;
  }
  console.log(`DIAG ${s[0]}: seed=${seeded} final=${owned[s[0]] || 0} bbox=[${Math.min(...xs)}..${Math.max(...xs)}, ${Math.min(...ys)}..${Math.max(...ys)}]`);
});

// --- پیش‌نمایش SVG برای بازدید چشمی
const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}">
<g fill="#c7d5c7" stroke="#5a6b5a" stroke-width="1.2" stroke-linejoin="round">
${SEEDS.map((s) => `<path d="${result[s[0]]}"/>`).join('\n')}
</g>
<g font-family="Tahoma" font-size="15" fill="#1d2921" text-anchor="middle">
${SEEDS.filter((s) => result[s[0]]).map((s) => { const m = /M([0-9.]+),([0-9.]+)/.exec(result[s[0]]); return `<text x="${m[1]}" y="${m[2]}">${s[0]}</text>`; }).join('\n')}
</g></svg>`;
fs.writeFileSync(__dirname + '/preview.svg', svg, 'utf8');
fs.writeFileSync(__dirname + '/preview.html',
  '<!doctype html><meta charset="utf-8"><body style="margin:0"><div style="max-width:900px">' + svg + '</div></body>', 'utf8');
console.log('preview.svg written');
