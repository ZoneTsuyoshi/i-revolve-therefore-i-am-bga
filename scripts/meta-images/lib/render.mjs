// HTML を Playwright（ローカルの Chrome）で撮影する（仕様書 009 §14.4）
import { chromium } from 'playwright-core';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
/* global document, window */
import { pathToFileURL } from 'node:url';

const CHROME_PATH = process.env.CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

export async function launchBrowser() {
  try {
    return await chromium.launch({ executablePath: CHROME_PATH, args: ['--allow-file-access-from-files'] });
  } catch (e) {
    throw new Error(`Chrome を起動できません（CHROME_PATH=${CHROME_PATH}）: ${e.message}`, { cause: e });
  }
}

/**
 * HTML を .work/ に書き、file:// で開いて撮影する。
 * フォントと画像の読み込みが終わってから、ページの window.__fit（あれば）を呼び、その戻り値も返す。
 * @returns {Promise<{ buffer: Buffer, fit: unknown }>}
 */
export async function shoot(browser, workDir, name, html, { width, height, scale = 1, transparent = false }) {
  await mkdir(workDir, { recursive: true });
  const file = path.join(workDir, `${name}.html`);
  await writeFile(file, html);
  const page = await browser.newPage({ viewport: { width, height }, deviceScaleFactor: scale });
  try {
    await page.goto(pathToFileURL(file).href, { waitUntil: 'load' });
    const fit = await page.evaluate(async () => {
      await Promise.all(['700 100px "IR Garamond"', '400 100px "IR Mincho"'].map((f) => document.fonts.load(f, '我廻IRev').catch(() => [])));
      await document.fonts.ready;
      const imgs = [...document.images];
      await Promise.all(imgs.map((i) => (i.complete ? null : new Promise((r) => { i.onload = r; i.onerror = r; }))));
      const broken = imgs.filter((i) => i.naturalWidth === 0).map((i) => i.src);
      if (broken.length > 0) throw new Error(`画像を読み込めません: ${broken.join(', ')}`);
      // @ts-ignore ページ側が定義する
      return typeof window.__fit === 'function' ? window.__fit() : null;
    });
    const buffer = await page.screenshot({ type: 'png', omitBackground: transparent, clip: { x: 0, y: 0, width, height } });
    return { buffer, fit };
  } finally {
    await page.close();
  }
}
