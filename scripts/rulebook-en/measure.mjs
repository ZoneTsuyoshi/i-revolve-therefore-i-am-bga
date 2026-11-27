// 測定補助。座標はすべて PDF のポイント（pt、左上原点）。画像は build/rulebook-en/ の orig・bg・preview（400 dpi）。
//   crop <orig|bg|preview> <pN> <x> <y> <w> <h> [--zoom 2] [--grid 10] [--out パス]   領域を拡大し、pt の目盛りを重ねて切り出す
//   spans <pN> <x> <y> <w> <h> [--all]                                                領域に重なる span の一覧（spans/pN.json から。--all で残した span も）
//   color <orig|bg|preview> <pN> <x> <y> [<w> <h>]                                    点・矩形の色（代表色と平均。覆う四角の色決め用）
//   compare <pN> [<x> <y> <w> <h>] [--zoom 2] [--grid 10]                             原本と preview を左右に並べる（領域を省くとページ全体）
// 出力の画像は build/rulebook-en/tmp/（Git 管理外）。Read ツールで開いて見る（大きい画像は縮小されるので、小さい字は領域を絞って zoom を上げる）。
import { mkdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import sharp from 'sharp';
import { outDir } from './lib/paths.mjs';

const DPI = 400;
const K = DPI / 72; // 1 pt = K px

/** 引数列から --name 値 を取り出し、残りを返す（純粋な関数） */
export function takeOpts(args, names) {
  const rest = [...args];
  const opts = {};
  for (const n of names) {
    const i = rest.indexOf(`--${n}`);
    if (i < 0) continue;
    const isFlag = n === 'all';
    opts[n] = isFlag ? true : rest[i + 1];
    rest.splice(i, isFlag ? 1 : 2);
  }
  return { opts, rest };
}

const num = (v, name) => {
  const n = Number(v);
  if (!Number.isFinite(n)) throw new Error(`${name} が数値ではありません: ${v}`);
  return n;
};

function gridSvg(w, h, x0, y0, zoom, grid) {
  const px = K * zoom; // 1 pt あたりの出力 px
  const parts = [];
  const first = (v) => Math.ceil(v / grid) * grid;
  for (let x = first(x0); x <= x0 + w / px; x += grid) {
    const X = (x - x0) * px;
    const major = Math.round(x / grid) % 5 === 0;
    parts.push(`<line x1="${X}" y1="0" x2="${X}" y2="${h}" stroke="${major ? '#ff00ff' : '#00b0ff'}" stroke-opacity="${major ? 0.7 : 0.4}" stroke-width="1"/>`);
    if (major) parts.push(`<text x="${X + 2}" y="11" font-size="11" fill="#ff00ff" font-family="sans-serif">${x}</text>`);
  }
  for (let y = first(y0); y <= y0 + h / px; y += grid) {
    const Y = (y - y0) * px;
    const major = Math.round(y / grid) % 5 === 0;
    parts.push(`<line x1="0" y1="${Y}" x2="${w}" y2="${Y}" stroke="${major ? '#ff00ff' : '#00b0ff'}" stroke-opacity="${major ? 0.7 : 0.4}" stroke-width="1"/>`);
    if (major) parts.push(`<text x="2" y="${Y - 2}" font-size="11" fill="#ff00ff" font-family="sans-serif">${y}</text>`);
  }
  return Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}">${parts.join('')}</svg>`);
}

async function cropImage(file, [x, y, w, h], zoom, grid) {
  const meta = await sharp(file).metadata();
  const left = Math.max(0, Math.round(x * K));
  const top = Math.max(0, Math.round(y * K));
  const width = Math.min(meta.width - left, Math.round(w * K));
  const height = Math.min(meta.height - top, Math.round(h * K));
  const outW = Math.round(width * zoom);
  const outH = Math.round(height * zoom);
  const base = await sharp(file).extract({ left, top, width, height }).resize(outW, outH, { kernel: 'lanczos3' }).png().toBuffer();
  if (!grid) return { buf: base, w: outW, h: outH };
  const buf = await sharp(base).composite([{ input: gridSvg(outW, outH, left / K, top / K, zoom, grid) }]).png().toBuffer();
  return { buf, w: outW, h: outH };
}

function imagePath(out, kind, id) {
  if (!['orig', 'bg', 'preview'].includes(kind)) throw new Error(`画像の種類は orig・bg・preview のどれか: ${kind}`);
  return path.join(out, kind, `${id}.png`);
}

const hex = (r, g, b) => `#${[r, g, b].map((v) => Math.round(v).toString(16).padStart(2, '0')).join('')}`;

async function colorOf(file, rect) {
  const [x, y, w = 0, h = 0] = rect;
  const left = Math.round(x * K);
  const top = Math.round(y * K);
  const width = Math.max(1, Math.round(w * K));
  const height = Math.max(1, Math.round(h * K));
  const { data, info } = await sharp(file).extract({ left, top, width, height }).removeAlpha().raw().toBuffer({ resolveWithObject: true });
  const hist = new Map();
  let sr = 0, sg = 0, sb = 0;
  const n = info.width * info.height;
  for (let i = 0; i < n; i++) {
    const r = data[i * 3], g = data[i * 3 + 1], b = data[i * 3 + 2];
    sr += r; sg += g; sb += b;
    const key = (r << 16) | (g << 8) | b;
    hist.set(key, (hist.get(key) ?? 0) + 1);
  }
  const top3 = [...hist.entries()].sort((a, b) => b[1] - a[1]).slice(0, 3)
    .map(([k, c]) => `${hex(k >> 16, (k >> 8) & 255, k & 255)}（${Math.round((c / n) * 100)}%）`);
  return { mode: top3, mean: hex(sr / n, sg / n, sb / n), pixels: n };
}

async function main() {
  const out = outDir();
  const [cmd, ...raw] = process.argv.slice(2);
  const { opts, rest } = takeOpts(raw, ['zoom', 'grid', 'out', 'all']);
  const zoom = opts.zoom ? num(opts.zoom, '--zoom') : 2;
  const grid = opts.grid ? num(opts.grid, '--grid') : 10;
  const tmp = path.join(out, 'tmp');
  await mkdir(tmp, { recursive: true });

  if (cmd === 'crop') {
    const [kind, id, ...r] = rest;
    const rect = r.slice(0, 4).map((v) => num(v, '領域'));
    if (rect.length < 4) throw new Error('使い方: crop <orig|bg|preview> <pN> <x> <y> <w> <h> [--zoom 2] [--grid 10]');
    const { buf, w, h } = await cropImage(imagePath(out, kind, id), rect, zoom, grid);
    const dest = opts.out ?? path.join(tmp, `crop-${kind}-${id}-${rect[0]}-${rect[1]}.png`);
    await sharp(buf).toFile(dest);
    console.log(`${dest}（${w}x${h} px、1 pt = ${(K * zoom).toFixed(2)} px、目盛り ${grid} pt）`);
  } else if (cmd === 'spans') {
    const [id, ...r] = rest;
    const [x, y, w, h] = r.slice(0, 4).map((v) => num(v, '領域'));
    const spans = JSON.parse(await readFile(path.join(out, 'spans', `${id}.json`), 'utf8'));
    for (const s of spans) {
      const [x0, y0, x1, y1] = s.bbox;
      if (x1 < x || x0 > x + w || y1 < y || y0 > y + h) continue;
      if (!s.removed && !opts.all) continue;
      console.log(`${s.removed ? '抜' : '残'} (${x0}, ${y0})-(${x1}, ${y1}) ${s.size} pt ${s.font} #${s.color} | ${s.text}`);
    }
  } else if (cmd === 'color') {
    const [kind, id, ...r] = rest;
    const rect = r.map((v) => num(v, '座標'));
    if (rect.length !== 2 && rect.length !== 4) throw new Error('使い方: color <orig|bg|preview> <pN> <x> <y> [<w> <h>]');
    const c = await colorOf(imagePath(out, kind, id), rect);
    console.log(`多い色: ${c.mode.join('、')}  平均: ${c.mean}  （${c.pixels} 画素）`);
  } else if (cmd === 'compare') {
    const [id, ...r] = rest;
    const rect = r.length >= 4 ? r.slice(0, 4).map((v) => num(v, '領域')) : [0, 0, 467.739, 643.487];
    const z = opts.zoom ? zoom : r.length >= 4 ? 2 : 0.5;
    const g = r.length >= 4 ? grid : 0;
    const a = await cropImage(imagePath(out, 'orig', id), rect, z, g);
    const b = await cropImage(imagePath(out, 'preview', id), rect, z, g);
    const gap = 12;
    const dest = opts.out ?? path.join(tmp, `compare-${id}-${rect[0]}-${rect[1]}.png`);
    await sharp({ create: { width: a.w + b.w + gap, height: Math.max(a.h, b.h), channels: 3, background: '#ff00ff' } })
      .composite([{ input: a.buf, left: 0, top: 0 }, { input: b.buf, left: a.w + gap, top: 0 }]).png().toFile(dest);
    console.log(`${dest}（左: 原本、右: preview）`);
  } else {
    console.error('使い方: node scripts/rulebook-en/measure.mjs <crop|spans|color|compare> …（先頭のコメントを参照）');
    process.exit(1);
  }
}

if (import.meta.url === new URL(`file://${process.argv[1]}`).href) {
  main().catch((e) => { console.error(e.message ?? e); process.exit(1); });
}
