// メタ画像の生成で使うパスと、フォントの取得（仕様書 009 §14.4）
import { execFileSync } from 'node:child_process';
import { mkdir, stat, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const WORKTREE_ROOT = path.resolve(HERE, '../../..');

/** メインの作業ツリー（git-common-dir の親）の絶対パス */
export function mainTreeRoot() {
  const common = execFileSync('git', ['rev-parse', '--path-format=absolute', '--git-common-dir'], {
    cwd: WORKTREE_ROOT, encoding: 'utf8',
  }).trim();
  return path.resolve(common, '..');
}

/** 原本（data/）。読み取りのみ。IR_DATA_DIR で上書きできる */
export function dataDir() {
  return process.env.IR_DATA_DIR ? path.resolve(process.env.IR_DATA_DIR) : path.join(mainTreeRoot(), 'data');
}

/** 出力先。IR_META_OUT で上書きできる */
export function outDir() {
  return process.env.IR_META_OUT ? path.resolve(process.env.IR_META_OUT) : path.join(mainTreeRoot(), 'build/meta-images');
}

export function fontsDir() {
  return path.join(mainTreeRoot(), 'build/fonts');
}

const BASE = 'https://github.com/google/fonts/raw/main/ofl';
/** OFL のフォント。EB Garamond は可変フォント（font-weight で太さを選ぶ） */
export const FONTS = {
  mincho: { file: 'ShipporiMinchoB1-ExtraBold.ttf', url: `${BASE}/shipporiminchob1/ShipporiMinchoB1-ExtraBold.ttf` },
  garamond: { file: 'EBGaramond-Italic-wght.ttf', url: `${BASE}/ebgaramond/EBGaramond-Italic%5Bwght%5D.ttf` },
};

/** キャッシュに無いフォントだけをダウンロードし、ファイルの絶対パスを返す */
export async function ensureFonts() {
  const dir = fontsDir();
  await mkdir(dir, { recursive: true });
  const result = {};
  for (const [key, { file, url }] of Object.entries(FONTS)) {
    const dest = path.join(dir, file);
    let ok = false;
    try {
      ok = (await stat(dest)).size > 10000;
    } catch { /* 無ければ取得する */ }
    if (!ok) {
      console.log(`フォントを取得: ${url}`);
      const res = await fetch(url, { redirect: 'follow' });
      if (!res.ok) throw new Error(`フォントを取得できません（${res.status}）: ${url}`);
      await writeFile(dest, Buffer.from(await res.arrayBuffer()));
    }
    result[key] = dest;
  }
  return result;
}
