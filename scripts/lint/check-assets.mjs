// A1: src/img/ の上限検査（仕様書 004 §2.9）。コミット済みの画像を直接検査する。原本（data/）も sharp も不要。
// 実行: npm run lint:assets。src/img/ が無ければ対象 0 件で成功する。
// スプライト固有の整合（配置表との一致）は scripts/check-sprites.mjs（npm run check:sprites）の担当で、ここでは見ない。
// マニフェストとの整合は 009 が形式を決めてから checkManifest として足す（拡張点）。
import { readdir, readFile, stat } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { imageSize } from 'image-size';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
export const LIMITS = {
  maxFileBytes: 2_000_000,
  maxTotalBytes: 15_000_000,
  warnTotalBytes: 10_000_000,
  maxPx: 4096,
  warnFileCount: 10,
};
const NAME_RE = /^[a-z0-9_]+\.(webp|png|jpg|svg)$/;
const IMAGE_EXT_RE = /\.(webp|png|jpg|jpeg|gif|svg|avif)$/i;

/** 拡張点: マニフェスト（配置表）との整合。形式と置き場所は 009 が決める。 */
export function checkManifest(_files) {
  return [];
}

export async function checkAssets({ imgDir = path.join(ROOT, 'src/img') } = {}) {
  const errors = [];
  const warnings = [];
  let entries;
  try {
    entries = await readdir(imgDir, { withFileTypes: true });
  } catch (e) {
    if (e.code === 'ENOENT') return { errors, warnings, total: 0, count: 0 };
    throw e;
  }
  const files = [];
  for (const e of entries) {
    if (e.isDirectory()) errors.push(`サブディレクトリは禁止: ${e.name}`);
    else if (e.name !== '.gitkeep') files.push(e.name);
  }

  let total = 0;
  for (const f of files) {
    if (!IMAGE_EXT_RE.test(f)) {
      errors.push(`画像でないファイルがあります: ${f}`);
      continue;
    }
    if (!NAME_RE.test(f)) errors.push(`ファイル名が規則違反（小文字英数字と _ のみ、拡張子は webp/png/jpg/svg）: ${f}`);
    const full = path.join(imgDir, f);
    const size = (await stat(full)).size;
    total += size;
    if (size > LIMITS.maxFileBytes) errors.push(`${f} が ${size} バイト（上限 ${LIMITS.maxFileBytes}）`);
    if (f.endsWith('.svg')) continue;
    try {
      const dim = imageSize(await readFile(full));
      if (dim.width > LIMITS.maxPx || dim.height > LIMITS.maxPx) {
        errors.push(`${f} が ${dim.width}x${dim.height}（上限 ${LIMITS.maxPx}px）`);
      }
    } catch (e) {
      errors.push(`${f} の寸法を読めません: ${e.message}`);
    }
  }
  if (total > LIMITS.maxTotalBytes) errors.push(`総量 ${total} バイト（上限 ${LIMITS.maxTotalBytes}）`);
  else if (total > LIMITS.warnTotalBytes) warnings.push(`総量 ${total} バイト（${LIMITS.warnTotalBytes} 超）`);
  if (files.length >= LIMITS.warnFileCount) {
    warnings.push(`src/img/ 直下のファイルが ${files.length} 個（10 個未満が望ましい。直下の画像はすべてプリロードされる）`);
  }
  errors.push(...checkManifest(files));
  return { errors, warnings, total, count: files.length };
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const { errors, warnings, total, count } = await checkAssets();
  for (const w of warnings) console.log(`::warning title=src/img::${w}`);
  if (errors.length) {
    for (const e of errors) console.error(`::error title=src/img::${e}`);
    console.error(`check-assets: ${errors.length} 件の問題があります。`);
    process.exit(1);
  }
  console.log(`check-assets: OK（画像 ${count} 件、総量 ${total} バイト）`);
}
