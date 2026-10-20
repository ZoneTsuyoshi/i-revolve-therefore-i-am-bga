// コミット済みのスプライトを検査する（仕様書 009 §11.4）。原本（data/）は不要。CI でも実行する。
// 使い方: npm run check:sprites
import { readdir, readFile, stat } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { imageSize } from 'image-size';
import { SHEETS, SHEET_IDS, UI_KEYS, buildTable, renderTable } from './lib/sprite-layout.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
export const LIMITS = {
  maxFileBytes: 2_000_000,
  maxTotalBytes: 15_000_000,
  warnTotalBytes: 8_000_000,
  maxPx: 4096,
  warnFileCount: 10,
};
const NAME_RE = /^[A-Za-z0-9_]+\.webp$/;

export async function checkSprites({
  imgDir = path.join(ROOT, 'src/img'),
  jsFile = path.join(ROOT, 'src/modules/js/ui/sprites.js'),
} = {}) {
  const errors = [];
  const warnings = [];
  let entries = [];
  try {
    entries = await readdir(imgDir, { withFileTypes: true });
  } catch {
    errors.push(`${imgDir} を読めません`);
    return { errors, warnings };
  }
  const files = [];
  for (const e of entries) {
    if (e.isDirectory()) errors.push(`サブディレクトリは禁止: ${e.name}`);
    else files.push(e.name);
  }
  const expected = SHEET_IDS.map((id) => SHEETS[id].file);
  for (const f of expected) if (!files.includes(f)) errors.push(`スプライトがありません: ${f}`);
  for (const f of files) {
    if (f.startsWith('.')) continue;
    if (!NAME_RE.test(f)) errors.push(`ファイル名が規則違反: ${f}`);
    else if (/^ir_/.test(f) && !expected.includes(f)) errors.push(`想定外のスプライト: ${f}`);
  }
  const visible = files.filter((f) => !f.startsWith('.'));
  if (visible.length >= LIMITS.warnFileCount) warnings.push(`ファイル数が ${visible.length} 個（10 個未満が望ましい）`);

  // 寸法・容量
  let total = 0;
  const actual = {};
  for (const f of visible) {
    const size = (await stat(path.join(imgDir, f))).size;
    total += size;
    if (size > LIMITS.maxFileBytes) errors.push(`${f} が ${size} バイト（上限 ${LIMITS.maxFileBytes}）`);
    if (!f.endsWith('.webp')) continue;
    try {
      const dim = imageSize(await readFile(path.join(imgDir, f)));
      actual[f] = dim;
      if (dim.width > LIMITS.maxPx || dim.height > LIMITS.maxPx) errors.push(`${f} が ${dim.width}x${dim.height}（上限 ${LIMITS.maxPx}）`);
    } catch (e) {
      errors.push(`${f} の寸法を読めません: ${e.message}`);
    }
  }
  if (total > LIMITS.maxTotalBytes) errors.push(`総量 ${total} バイト（上限 ${LIMITS.maxTotalBytes}）`);
  else if (total > LIMITS.warnTotalBytes) warnings.push(`総量 ${total} バイト（目標 ${LIMITS.warnTotalBytes} 以下）`);

  // 配置表の整合
  let mod;
  let text;
  try {
    text = await readFile(jsFile, 'utf8');
    mod = await import(`${pathToFileURL(jsFile).href}?t=${Date.now()}`);
  } catch (e) {
    errors.push(`sprites.js を読めません: ${e.message}`);
    return { errors, warnings, total };
  }
  try {
    const uiSizes = {};
    for (const k of UI_KEYS) {
      const r = mod.UI?.[k];
      if (!r) throw new Error(`UI のキーがありません: ${k}`);
      uiSizes[k] = { w: r.w, h: r.h };
    }
    const table = buildTable(uiSizes);
    if (renderTable(table) !== text) errors.push('sprites.js が配置規則からの再計算結果と一致しません');
    for (const [id, sh] of Object.entries(table.sheets)) {
      const dim = actual[sh.file];
      if (dim && (dim.width !== sh.w || dim.height !== sh.h)) {
        errors.push(`${sh.file} の実寸 ${dim.width}x${dim.height} が配置表 ${sh.w}x${sh.h} と不一致`);
      }
    }
    // UI の矩形: 範囲内・重複なし
    const rects = Object.entries(table.ui);
    for (const [k, r] of rects) {
      if (r.x + r.w > table.sheets.ir_ui.w || r.y + r.h > table.sheets.ir_ui.h) errors.push(`UI ${k} がシート外`);
    }
    for (let i = 0; i < rects.length; i++) {
      for (let j = i + 1; j < rects.length; j++) {
        const [, a] = rects[i];
        const [, b] = rects[j];
        if (a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h) {
          errors.push(`UI ${rects[i][0]} と ${rects[j][0]} が重なっています`);
        }
      }
    }
    // カードのセル: 重複なし・範囲内
    for (const id of SHEET_IDS) {
      const s = SHEETS[id];
      if (s.kind === 'ui') continue;
      if (new Set(s.keys).size !== s.keys.length) errors.push(`${id} のキーが重複`);
      if (s.keys.length > s.cols * s.rows) errors.push(`${id} のセル数が不足`);
    }
  } catch (e) {
    errors.push(`配置表の検査に失敗: ${e.message}`);
  }
  return { errors, warnings, total };
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const { errors, warnings, total } = await checkSprites();
  for (const w of warnings) console.warn(`警告: ${w}`);
  if (errors.length) {
    for (const e of errors) console.error(`エラー: ${e}`);
    process.exit(1);
  }
  console.log(`check:sprites OK（総量 ${total} バイト）`);
}
