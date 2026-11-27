// 英語版ルールブックのビルドで使うパスと、フォントの取得。原本（data/）は読み取りのみ。
import { mkdir, rename, stat, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { dataDir, fontsDir, mainTreeRoot, FONTS as META_FONTS } from '../../meta-images/lib/paths.mjs';

export { dataDir, fontsDir, mainTreeRoot };

/** ツール一式（scripts/rulebook-en/）の絶対パス */
export const TOOL_DIR = path.resolve(import.meta.dirname, '..');

/** 出力先（main の作業ツリーの build/rulebook-en/）。IR_RULEBOOK_OUT で上書きできる（prepare.sh も同じ） */
export function outDir() {
  return process.env.IR_RULEBOOK_OUT ? path.resolve(process.env.IR_RULEBOOK_OUT) : path.join(mainTreeRoot(), 'build/rulebook-en');
}

/** 原稿の置き場。RULEBOOK_PAGES_DIR で上書きできる（部品の試験用） */
export function pagesDir() {
  return process.env.RULEBOOK_PAGES_DIR ? path.resolve(process.env.RULEBOOK_PAGES_DIR) : path.join(TOOL_DIR, 'pages');
}

const BASE = 'https://github.com/google/fonts/raw/main/ofl';
/** OFL のフォント（build/fonts/ に初回だけ取得。コミットしない）。weight は @font-face の font-weight */
export const FACES = [
  { family: 'Barlow Semi Condensed', weight: 500, file: 'BarlowSemiCondensed-Medium.ttf', url: `${BASE}/barlowsemicondensed/BarlowSemiCondensed-Medium.ttf` },
  { family: 'Barlow Semi Condensed', weight: 600, file: 'BarlowSemiCondensed-SemiBold.ttf', url: `${BASE}/barlowsemicondensed/BarlowSemiCondensed-SemiBold.ttf` },
  { family: 'Barlow Semi Condensed', weight: 700, file: 'BarlowSemiCondensed-Bold.ttf', url: `${BASE}/barlowsemicondensed/BarlowSemiCondensed-Bold.ttf` },
  { family: 'Barlow Semi Condensed', weight: 800, file: 'BarlowSemiCondensed-ExtraBold.ttf', url: `${BASE}/barlowsemicondensed/BarlowSemiCondensed-ExtraBold.ttf` },
  { family: 'Barlow Condensed', weight: 500, file: 'BarlowCondensed-Medium.ttf', url: `${BASE}/barlowcondensed/BarlowCondensed-Medium.ttf` },
  { family: 'Barlow Condensed', weight: 700, file: 'BarlowCondensed-Bold.ttf', url: `${BASE}/barlowcondensed/BarlowCondensed-Bold.ttf` },
  // 可変フォント（400〜800）。題字用のイタリック
  { family: 'EB Garamond', weight: '400 800', style: 'italic', file: META_FONTS.garamond.file, url: `${BASE}/ebgaramond/EBGaramond-Italic%5Bwght%5D.ttf` },
];

/** @font-face の CSS を作る（フォントは fonts のフォルダの絶対パスを file:// で参照する） */
export function fontFaceCss(fonts) {
  return fonts.map((f) => `@font-face { font-family: "${f.family}"; font-weight: ${f.weight}; font-style: ${f.style ?? 'normal'}; src: url("${f.href}") format("truetype"); }`).join('\n');
}

/** キャッシュに無いフォントだけを取得し、{ ...FACES の項目, href } の配列を返す。並行して動いても壊れないよう、書き込みは一時ファイル経由 */
export async function ensureFonts() {
  const dir = fontsDir();
  await mkdir(dir, { recursive: true });
  const result = [];
  for (const f of FACES) {
    const dest = path.join(dir, f.file);
    let ok = false;
    try {
      ok = (await stat(dest)).size > 10000;
    } catch { /* 無ければ取得する */ }
    if (!ok) {
      console.log(`フォントを取得: ${f.url}`);
      const res = await fetch(f.url, { redirect: 'follow' });
      if (!res.ok) throw new Error(`フォントを取得できません（${res.status}）: ${f.url}`);
      const tmp = `${dest}.${process.pid}.tmp`;
      await writeFile(tmp, Buffer.from(await res.arrayBuffer()));
      await rename(tmp, dest);
    }
    result.push({ ...f, href: new URL(`file://${dest}`).href });
  }
  return result;
}
