// BGA のゲームページ用メタ画像（Box・Icon・Title・Banner・Display）を原本（data/）から生成する。仕様書 009 §14。ローカルのみ
// 使い方: npm run build:meta-images（data/ とローカルの Chrome が必要。環境変数 IR_DATA_DIR・IR_META_OUT・CHROME_PATH）
import { mkdir, readFile, rm, stat, writeFile } from 'node:fs/promises';
import path from 'node:path';
import sharp from 'sharp';
import { cornerAlphas, checkImage, DISPLAY_NAMES, SPECS } from './lib/checks.mjs';
import { dataDir, ensureFonts, outDir } from './lib/paths.mjs';
import { launchBrowser, shoot } from './lib/render.mjs';
import {
  bannerHtml, display2Html, display3Html, iconFontHtml, titleHtml, titleOnLightHtml,
} from './lib/scenes.mjs';

/** 箱の表のタイトルの「廻」を切り出す範囲（原本 502 × 650 の画素。目視で決めた） */
const ICON_CROP = { left: 288, top: 205, width: 86, height: 86 };
/** ゲーム盤面（1800 × 1120）から 1680 × 1120 を切り出すときの左端（左右 120 px のうち、右の 120 px を削る（左の山札を欠けさせない）） */
const BOARD_CROP_LEFT = 0;

const JPG = { mozjpeg: true, quality: 88 };

async function main() {
  const data = dataDir();
  const out = outDir();
  const prev = path.join(out, 'preview');
  const work = path.join(out, '.work');
  try {
    await stat(path.join(data, 'box/box@4x.png'));
  } catch {
    throw new Error(`原本が見つかりません: ${data}（IR_DATA_DIR で指定できます）`);
  }
  await rm(work, { recursive: true, force: true });
  await mkdir(prev, { recursive: true });
  const fonts = await ensureFonts();
  const browser = await launchBrowser();
  const write = async (/** @type {string} */ name, /** @type {Buffer} */ buf) => { await writeFile(path.join(out, name), buf); };
  const hiRes = async (/** @type {string} */ name, /** @type {string} */ html, /** @type {number} */ w, /** @type {number} */ h) => {
    const { buffer } = await shoot(browser, work, name, html, { width: w, height: h, scale: 2 });
    return sharp(buffer).resize(w, h, { kernel: 'lanczos3' });
  };

  try {
    // Box
    const boxSrc = path.join(data, 'box/box@4x.png');
    const boxResized = await sharp(boxSrc).resize({ height: 280, kernel: 'lanczos3' }).png().toBuffer();
    const boxMeta = await sharp(boxResized).metadata();
    const box = await sharp({ create: { width: 280, height: 280, channels: 4, background: { r: 0, g: 0, b: 0, alpha: 0 } } })
      .composite([{ input: boxResized, left: Math.round((280 - (boxMeta.width ?? 0)) / 2), top: 0 }]).png({ compressionLevel: 9 }).toBuffer();
    await write('box.png', box);

    // Icon（切り出し版を基本とし、代替案の「廻」の字も作って並べる）
    const iconCrop = await sharp(boxSrc).extract(ICON_CROP).resize(50, 50, { kernel: 'lanczos3' }).flatten({ background: '#5b472f' }).png().toBuffer();
    const iconFont = await (await hiRes('icon-font', iconFontHtml(fonts), 200, 200)).resize(50, 50, { kernel: 'lanczos3' }).png().toBuffer();
    await write('icon.png', iconCrop);
    const up = (/** @type {Buffer} */ b) => sharp(b).resize(200, 200, { kernel: 'nearest' }).png().toBuffer();
    const cands = [iconCrop, iconFont];
    const candTiles = [];
    for (const [i, b] of cands.entries()) {
      candTiles.push({ input: b, left: 20 + i * 260, top: 20 }, { input: await up(b), left: 20 + i * 260, top: 90 });
    }
    await sharp({ create: { width: 540, height: 310, channels: 3, background: '#8b8b8b' } }).composite(candTiles).png().toFile(path.join(prev, 'icon-candidates.png'));

    // Title
    const { buffer: titleRaw, fit } = await shoot(browser, work, 'title', titleHtml(fonts), { width: 2000, height: 2000, transparent: true });
    console.log('Title の配置:', JSON.stringify(fit));
    const title = await sharp(titleRaw).png({ compressionLevel: 9, palette: false }).toBuffer();
    await write('title.png', title);
    const { buffer: light } = await shoot(browser, work, 'title-light', titleOnLightHtml(path.join(out, 'title.png')), { width: 1000, height: 1000 });
    await writeFile(path.join(prev, 'title-on-light.png'), light);

    // Banner
    const banner = await (await hiRes('banner', bannerHtml(data), 1386, 400)).jpeg(JPG).toBuffer();
    await write('banner.jpg', banner);

    // Display
    const board = await sharp(path.join(data, 'thumbnails/ゲーム盤面.png'))
      .extract({ left: BOARD_CROP_LEFT, top: 0, width: 1680, height: 1120 }).resize(1140, 760, { kernel: 'lanczos3' }).jpeg(JPG).toBuffer();
    await write('display-1.jpg', board);
    await write('display-2.jpg', await (await hiRes('display-2', display2Html(data), 1140, 760)).jpeg(JPG).toBuffer());
    await write('display-3.jpg', await (await hiRes('display-3', display3Html(data), 1140, 760)).jpeg(JPG).toBuffer());

    // ゲームページの見本（バナーの左に箱、タイトルを重ねる。登録しない）
    const TS = 0.32;
    const titleSize = Math.round(2000 * TS);
    const titleCut = Math.round(1000 * TS - 150); // 英語の中心（y = 1000）をバナーの y = 150 に置く
    const titleSmall = await sharp(title).resize(titleSize, titleSize, { kernel: 'lanczos3' })
      .extract({ left: 0, top: titleCut, width: titleSize, height: 400 }).png().toBuffer();
    const cx = 320 + (1386 - 320) / 2;
    await sharp(banner).composite([
      { input: box, left: 28, top: 60 },
      { input: titleSmall, left: Math.round(cx - 1000 * TS), top: 0 },
    ]).png().toFile(path.join(prev, 'gamepage-mock.png'));
  } finally {
    await browser.close();
  }

  // 検査
  const errors = [];
  const names = [...Object.keys(SPECS), ...DISPLAY_NAMES];
  for (const name of names) {
    const file = path.join(out, name);
    const buf = await readFile(file);
    const meta = await sharp(buf).metadata();
    let corners = null;
    if (SPECS[/** @type {keyof typeof SPECS} */ (name)]?.transparentCorners) {
      const { data: raw, info } = await sharp(buf).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
      corners = cornerAlphas(raw, info.width, info.height);
    }
    const info = { format: meta.format ?? '', width: meta.width ?? 0, height: meta.height ?? 0, hasAlpha: meta.hasAlpha === true, bytes: buf.length, corners };
    console.log(`${name}: ${info.format} ${info.width} × ${info.height} ${(info.bytes / 1024).toFixed(0)} KB${info.hasAlpha ? ' alpha' : ''}`);
    errors.push(...checkImage(name, info));
  }

  // 全出力の一覧
  const tiles = [];
  const thumb = async (/** @type {string} */ name, /** @type {number} */ w) => sharp(path.join(out, name)).resize({ width: w, height: w, fit: 'inside' }).flatten({ background: '#cfc7b8' }).png().toBuffer();
  const CW = 1400;
  const place = [
    ['banner.jpg', 20, 20, 680], ['display-1.jpg', 720, 20, 330], ['display-2.jpg', 1070, 20, 310],
    ['display-3.jpg', 720, 240, 330], ['title.png', 20, 235, 330], ['box.png', 370, 250, 180], ['icon.png', 570, 250, 50],
  ];
  for (const [name, left, top, w] of place) tiles.push({ input: await thumb(String(name), Number(w)), left: Number(left), top: Number(top) });
  await sharp({ create: { width: CW, height: 580, channels: 3, background: '#cfc7b8' } }).composite(tiles).jpeg({ quality: 88 }).toFile(path.join(prev, 'contact-sheet.jpg'));
  await rm(work, { recursive: true, force: true });

  if (errors.length > 0) {
    console.error(`検査に失敗しました:\n${errors.map((e) => `  - ${e}`).join('\n')}`);
    process.exit(1);
  }
  console.log(`完了: ${out}`);
}

main().catch((e) => { console.error(e); process.exit(1); });
