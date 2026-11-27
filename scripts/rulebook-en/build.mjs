// 英語版ルールブック PDF のビルド。使い方・方針は README.md。
//   node scripts/rulebook-en/build.mjs [--only p3,p5] [--png] [--debug] [--dpi 300] [--quality 88]
// 下地（prepare.py が作った「日本語の本文を抜いたページ」）に、pages/pN.html の英語の断片を重ね、ローカルの Chrome で PDF にする。
// 出力は main の作業ツリーの build/rulebook-en/（Git 管理外。原本の図版を含むのでコミットしない）。
/* global window, document */
import { mkdir, readFile, rename, rm, stat, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import sharp from 'sharp';
import { launchBrowser } from '../meta-images/lib/render.mjs';
import { ICONS, ICON_HEIGHT } from './assets.mjs';
import { PAGE_IDS, USAGE, parseArgs } from './lib/args.mjs';
import { pageHtml } from './lib/html.mjs';
import { dataDir, ensureFonts, fontFaceCss, outDir, pagesDir, TOOL_DIR } from './lib/paths.mjs';
import { countPdfPages } from './lib/pdf.mjs';

export const BOOK_NAME = 'I-Revolve-Therefore-I-Am-Rulebook-EN.pdf';
export const BOOK_TITLE = 'I Revolve, Therefore I Am - Rulebook (English)';
/** prepare.py が書く下地・原本画像の解像度（dpi）。pt → px の換算に使う */
export const BASE_DPI = 400;

/** 一時ファイルに書いてから置き換える（並行して動いても、読む側が書きかけを見ない） */
async function writeAtomic(dest, data) {
  const tmp = `${dest}.${process.pid}.${Date.now()}.tmp`;
  await writeFile(tmp, data);
  await rename(tmp, dest);
}

async function fileExists(p) {
  try { return (await stat(p)).isFile(); } catch { return false; }
}

/** アイコンを assets/ に書き出し、icons.css の中身を返す */
export async function buildAssets(out, data) {
  const dir = path.join(out, 'assets');
  await mkdir(dir, { recursive: true });
  const rules = [];
  for (const [name, spec] of Object.entries(ICONS)) {
    let input;
    if (spec.src) {
      input = path.join(data, spec.src);
    } else {
      const k = BASE_DPI / 72;
      const orig = path.join(out, 'orig', `${spec.page}.png`);
      if (!(await fileExists(orig))) throw new Error(`原本のページ画像がありません: ${orig}（npm run prepare:rulebook-en）`);
      input = await sharp(orig).extract({
        left: Math.round(spec.x * k), top: Math.round(spec.y * k), width: Math.round(spec.w * k), height: Math.round(spec.h * k),
      }).png().toBuffer();
    }
    if (typeof input === 'string' && !(await fileExists(input))) throw new Error(`アイコンの原本がありません: ${input}（assets.mjs の ${name}）`);
    // 原本の鍵・錠前の画像は、絵の周りに透明な余白が広い（絵は画像の 2 割ほど）。余白を落としてから縮める
    if (spec.src) {
      try { input = await sharp(input).trim().png().toBuffer(); } catch { /* 落とす余白が無い */ }
    }
    const { data: buf, info } = await sharp(input).resize({ height: ICON_HEIGHT, fit: 'inside' }).png({ compressionLevel: 9 }).toBuffer({ resolveWithObject: true });
    const dest = path.join(dir, `${name}.png`);
    await writeAtomic(dest, buf);
    const ar = Math.round((info.width / info.height) * 1000) / 1000;
    rules.push(`.ic.${name} { --ar: ${ar}; background-image: url("${pathToFileURL(dest).href}"); }`);
  }
  return rules.join('\n');
}

/** 下地を縮小・JPEG 化して返す（URL）。縮尺・品質ごとにキャッシュする */
async function backgroundJpeg(out, id, dpi, quality) {
  const src = path.join(out, 'bg', `${id}.png`);
  if (!(await fileExists(src))) throw new Error(`下地がありません: ${src}（npm run prepare:rulebook-en を先に実行）`);
  const dir = path.join(out, 'bgjpg');
  await mkdir(dir, { recursive: true });
  const dest = path.join(dir, `${id}-d${dpi}-q${quality}.jpg`);
  const srcTime = (await stat(src)).mtimeMs;
  let fresh = false;
  try { fresh = (await stat(dest)).mtimeMs >= srcTime; } catch { /* 無ければ作る */ }
  if (!fresh) {
    const meta = await sharp(src).metadata();
    const buf = await sharp(src).resize({ width: Math.round((meta.width * dpi) / BASE_DPI), kernel: 'lanczos3' })
      .jpeg({ quality, mozjpeg: true, chromaSubsampling: '4:4:4' }).toBuffer();
    await writeAtomic(dest, buf);
  }
  return pathToFileURL(dest).href;
}

async function readPage(id) {
  const file = path.join(pagesDir(), `${id}.html`);
  try { return await readFile(file, 'utf8'); } catch { return ''; }
}

function summarize(report, ids) {
  const lines = [];
  let overflow = 0;
  let shrunk = 0;
  for (const id of ids) {
    const r = report[id];
    if (!r) continue;
    lines.push(`${id}: 文字の箱 ${r.boxes.length}、縮小 ${r.shrunkCount}、はみ出し ${r.overflowCount}`);
    for (const b of r.boxes) {
      if (b.shrunk || b.overflow) {
        lines.push(`  ${b.overflow ? 'はみ出し' : '縮小'}: 「${b.label}」 (${b.x}, ${b.y}) 指定 ${b.fontSize} pt → ${b.finalFontSize} pt、中身の高さ ${b.contentHeight} / 箱 ${b.h} pt`);
      }
    }
    overflow += r.overflowCount;
    shrunk += r.shrunkCount;
  }
  return { lines, overflow, shrunk };
}

async function main() {
  const args = parseArgs(process.argv.slice(2));
  if (args.help) { console.log(USAGE); return 0; }
  const out = outDir();
  const ids = args.only ?? PAGE_IDS;
  const full = args.only === null;
  const work = path.join(out, `.work-${process.pid}-${Date.now()}`);
  const suffix = args.debug ? '-debug' : '';
  let browser = null;
  let exitCode = 0;
  try {
    const fonts = await ensureFonts();
    const iconsCss = await buildAssets(out, dataDir());
    const cssText = await readFile(path.join(TOOL_DIR, 'rulebook.css'), 'utf8');
    const fitJs = await readFile(path.join(TOOL_DIR, 'fit.js'), 'utf8');
    const sections = [];
    for (const id of ids) {
      sections.push(pageHtml({ id, bgHref: await backgroundJpeg(out, id, args.dpi, args.quality), body: await readPage(id) }));
    }
    const html = `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>${BOOK_TITLE}</title>
<style>
${fontFaceCss(fonts)}
${cssText}
${iconsCss}
</style></head>
<body${args.debug ? ' class="debug"' : ''}>
${sections.join('\n')}
<script>
${fitJs}
</script>
</body></html>`;
    await mkdir(work, { recursive: true });
    const htmlFile = path.join(work, 'index.html');
    await writeFile(htmlFile, html);

    browser = await launchBrowser();
    const load = async (context) => {
      const page = await context.newPage();
      await page.goto(pathToFileURL(htmlFile).href, { waitUntil: 'load' });
      await page.waitForFunction(() => window.__rbDone === true, null, { timeout: 120000 });
      const err = await page.evaluate(() => window.__rbError);
      if (err) throw new Error(`fit.js が失敗: ${err}`);
      const broken = await page.evaluate(() => [...document.images].filter((i) => i.naturalWidth === 0).map((i) => i.src));
      if (broken.length) throw new Error(`画像を読み込めません: ${broken.join(', ')}`);
      return page;
    };

    // PDF（印刷用のレイアウトで測る）
    const pdfCtx = await browser.newContext();
    const page = await load(pdfCtx);
    await page.emulateMedia({ media: 'print' });
    await page.evaluate(async () => { await document.fonts.ready; });
    const report = await page.evaluate(() => window.__rb);
    const writeOnlyDebug = args.debug && args.png;
    if (!writeOnlyDebug) {
      if (full) {
        const buf = await page.pdf({ preferCSSPageSize: true, printBackground: true });
        const n = countPdfPages(buf);
        if (n !== null && n !== ids.length) throw new Error(`PDF のページ数が ${n} です（${ids.length} のはず。ページが 2 枚目に溢れていないか確認）`);
        await mkdir(out, { recursive: true });
        await writeAtomic(path.join(out, args.debug ? BOOK_NAME.replace('.pdf', '-debug.pdf') : BOOK_NAME), buf);
        console.log(`PDF: ${path.join(out, BOOK_NAME)}（${(buf.length / 1048576).toFixed(2)} MB、${n ?? '?'} ページ）`);
      } else {
        await mkdir(path.join(out, 'partial'), { recursive: true });
        for (const [i, id] of ids.entries()) {
          const buf = await page.pdf({ preferCSSPageSize: true, printBackground: true, pageRanges: String(i + 1) });
          const n = countPdfPages(buf);
          if (n !== null && n !== 1) throw new Error(`${id}: PDF のページ数が ${n} です（1 のはず）`);
          const dest = path.join(out, 'partial', `${id}${suffix}.pdf`);
          await writeAtomic(dest, buf);
          console.log(`PDF: ${dest}（${(buf.length / 1048576).toFixed(2)} MB）`);
        }
      }
    }

    // 確認用の画像（下地と同じ縮尺・原本と同じ座標）
    if (args.png) {
      const ctx = await browser.newContext({ deviceScaleFactor: BASE_DPI / 96 });
      const p = await load(ctx);
      await p.evaluate(() => document.body.classList.add('preview'));
      await mkdir(path.join(out, 'preview'), { recursive: true });
      for (const id of ids) {
        const shot = await p.locator(`.outer[data-id="${id}"]`).screenshot({ type: 'png' });
        const bgMeta = await sharp(path.join(out, 'bg', `${id}.png`)).metadata();
        const png = await sharp(shot).resize(bgMeta.width, bgMeta.height, { fit: 'fill' }).png().toBuffer();
        const dest = path.join(out, 'preview', `${id}${suffix}.png`);
        await writeAtomic(dest, png);
        console.log(`PNG: ${dest}`);
      }
    }

    await mkdir(out, { recursive: true });
    for (const id of ids) await writeAtomic(path.join(out, `report-${id}.json`), `${JSON.stringify(report[id], null, 1)}\n`);
    const { lines, overflow, shrunk } = summarize(report, ids);
    console.log(lines.join('\n'));
    console.log(`合計: 縮小 ${shrunk}、はみ出し ${overflow}`);
    if (full && overflow > 0) {
      console.error('はみ出しが残っています。言い回しを短くするか、箱を広げてください。');
      exitCode = 1;
    }
  } catch (e) {
    console.error(e.message ?? e);
    exitCode = 1;
  } finally {
    try { await browser?.close(); } catch { /* 終了済み */ }
    await rm(work, { recursive: true, force: true, maxRetries: 10, retryDelay: 100 });
  }
  return exitCode;
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
  process.exit(await main());
}
