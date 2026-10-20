// 原本（data/）から WebP スプライトと配置表を生成する（仕様書 009 §10・§11）。ローカルのみで実行。
// 使い方: npm run build:sprites [-- --data <原本の絶対パス>] [--out <dir>] [--js <path>]
// ワークツリーでは data/ が見えないので --data で指定する。data/ は読み取りのみ。
import { mkdir, readFile, stat, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';
import {
  SHEETS, SHEET_IDS, SOURCE_SIZE, UI_ITEMS, cardSource, uiOutputSize,
  buildTable, renderTable, cellOf,
} from './lib/sprite-layout.mjs';
import { checkSprites } from './check-sprites.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

function parseArgs(argv) {
  const o = {
    data: path.join(ROOT, 'data'),
    out: path.join(ROOT, 'src/img'),
    js: path.join(ROOT, 'src/modules/js/ui/sprites.js'),
  };
  for (let i = 0; i < argv.length; i++) {
    if (argv[i] === '--data') o.data = path.resolve(argv[++i]);
    else if (argv[i] === '--out') o.out = path.resolve(argv[++i]);
    else if (argv[i] === '--js') o.js = path.resolve(argv[++i]);
    else throw new Error(`不明なオプション: ${argv[i]}`);
  }
  return o;
}

const within = (v, [lo, hi]) => v >= lo && v <= hi;

async function loadSource(data, rel, label) {
  const file = path.join(data, rel);
  try {
    await stat(file);
  } catch {
    throw new Error(`原本が見つかりません: ${file}（${label}）`);
  }
  const buf = await readFile(file);
  const meta = await sharp(buf).metadata();
  if (!meta.hasAlpha) throw new Error(`原本が透過(RGBA)ではありません: ${rel}`);
  return { buf, w: meta.width, h: meta.height };
}

async function main() {
  const opts = parseArgs(process.argv.slice(2));
  try {
    await stat(opts.data);
  } catch {
    throw new Error(`原本のディレクトリが見つかりません: ${opts.data}（--data <絶対パス> で指定）`);
  }

  // 1. 入力の検査と読み込み
  const sources = new Map();
  for (const id of SHEET_IDS) {
    const s = SHEETS[id];
    if (s.kind === 'ui') continue;
    for (const key of s.keys) {
      if (sources.has(key)) continue;
      const rel = cardSource(key);
      const src = await loadSource(opts.data, rel, key);
      const range = SOURCE_SIZE[s.kind];
      if (!within(src.w, range.w) || !within(src.h, range.h)) {
        throw new Error(`原本の寸法が想定外: ${rel} は ${src.w}x${src.h}`);
      }
      sources.set(key, src);
    }
  }
  const uiSources = new Map();
  const uiSizes = {};
  for (const item of UI_ITEMS) {
    const src = await loadSource(opts.data, item.src, item.key);
    uiSources.set(item.key, src);
    uiSizes[item.key] = uiOutputSize(item, src.w, src.h);
  }

  // 2〜4. 縮小・合成・書き出し
  const table = buildTable(uiSizes);
  await mkdir(opts.out, { recursive: true });
  const transparent = { r: 0, g: 0, b: 0, alpha: 0 };
  for (const id of SHEET_IDS) {
    const s = SHEETS[id];
    const sh = table.sheets[id];
    const inputs = [];
    if (s.kind === 'ui') {
      for (const item of UI_ITEMS) {
        const r = table.ui[item.key];
        const buf = await sharp(uiSources.get(item.key).buf)
          .resize(r.w, r.h, { fit: 'fill', kernel: 'lanczos3' }).png().toBuffer();
        inputs.push({ input: buf, left: r.x, top: r.y });
      }
    } else {
      for (const key of s.keys) {
        const { col, row } = cellOf(id, key);
        const buf = await sharp(sources.get(key).buf)
          .resize(s.cellW, s.cellH, { fit: 'fill', kernel: 'lanczos3' }).png().toBuffer();
        inputs.push({ input: buf, left: col * s.cellW, top: row * s.cellH });
      }
    }
    const webp = s.kind === 'ui'
      ? { lossless: true, effort: 6 }
      : { quality: 80, alphaQuality: 100, effort: 6, smartSubsample: true };
    const out = await sharp({ create: { width: sh.w, height: sh.h, channels: 4, background: transparent } })
      .composite(inputs).webp(webp).toBuffer();
    await writeFile(path.join(opts.out, s.file), out);
    console.log(`${s.file}: ${sh.w}x${sh.h}, ${out.length} bytes`);
  }

  // 5. 配置表
  await mkdir(path.dirname(opts.js), { recursive: true });
  await writeFile(opts.js, renderTable(table));

  // 6. 出力の検査
  const result = await checkSprites({ imgDir: opts.out, jsFile: opts.js });
  for (const w of result.warnings) console.warn(`警告: ${w}`);
  if (result.errors.length) {
    for (const e of result.errors) console.error(`エラー: ${e}`);
    process.exit(1);
  }
}

main().catch((e) => {
  console.error(e.message);
  process.exit(1);
});
