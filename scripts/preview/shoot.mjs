// プレビューのスクリーンショットを撮る。実行: npm run preview:shots（絞り込みの例は README.md）
// Chrome の場所は環境変数 CHROME_PATH（既定は macOS の Google Chrome）
import { chromium } from 'playwright-core';
import { mkdir, readdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { startServer } from './serve.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const MOBILE_MAX_WIDTH = 980;
/** デスクトップの画面の高さから引く、ブラウザのタブ・アドレスバーなどの高さ（px） */
const BROWSER_CHROME_HEIGHT = 130;
const SIZES = ['360x780', '390x844', '768x1024', '1024x768', '1280x800', '1366x768', '1440x900', '1920x1080', '2560x1440'];
const THEMES = ['light', 'dark'];
const CHROME_PATH = process.env.CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

/**
 * 配置の検証（010 §11.1）。ブラウザの中で実行する。
 * @returns {{ layout: string, hv: number, ringBottom: number, ringFits: boolean, margins: boolean, small: string[], wide: string[] }}
 */
function measureLayout() {
  const root = document.getElementById('ir-root');
  const layout = root?.dataset.layout ?? '';
  const main = document.getElementById('ir-main');
  const hv = window.innerHeight;
  const roomW = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--ir-room-w')) || 0;
  // 初期自我などの候補の置き場（ir-pick-panel）と想起の案内パネル（想起中だけ）は一時的に環の上に出るので、その高さは数えない
  const pick = document.querySelector('#ir-root > .ir-pick-panel, #ir-root > .ir-recall-panel:not([hidden])');
  const pickHeight = pick === null ? 0 : pick.getBoundingClientRect().height + 16;
  const ringBottom = main === null ? 0 : Math.round(main.getBoundingClientRect().bottom + window.scrollY - pickHeight);
  const vw = document.documentElement.clientWidth;
  // 盤面の左右に 8px 以上の余白（環・共通の領域・プレイヤーの領域）
  const blocks = ['ir-main', 'ir-common', 'ir-players'].map((id) => document.getElementById(id)).filter((e) => e !== null);
  const margins = blocks.every((e) => {
    const r = e.getBoundingClientRect();
    return e.getClientRects().length === 0 || (r.left >= 8 - 0.5 && r.right <= vw - 8 + 0.5);
  });
  const visible = (/** @type {Element} */ e) => {
    const r = e.getBoundingClientRect();
    return r.width > 0 && r.height > 0 && getComputedStyle(e).visibility !== 'hidden';
  };
  const label = (/** @type {Element} */ e) => `${e.tagName.toLowerCase()}.${String(e.className).split(' ').slice(0, 2).join('.')}`;
  // 操作できる要素は 32 × 32px 以上（ゲームの領域とジャンプリンク）
  const small = [...document.querySelectorAll('#ir-root button, #ir-root [role="button"], #ir-root .ir-token-pick, .bga-jump-to_link')]
    .filter((e) => visible(e) && (e.getBoundingClientRect().width < 31.5 || e.getBoundingClientRect().height < 31.5))
    .map(label);
  // 盤面の中でページの右端・左端をはみ出す要素
  const wide = [...document.querySelectorAll('#ir-root *')]
    .filter((e) => visible(e) && (e.getBoundingClientRect().right > vw + 0.5 || e.getBoundingClientRect().left < -0.5))
    .slice(0, 5)
    .map(label);
  return { layout, hv, ringBottom, // W が下限 150 まで縮んだときは収まらなくてよい（縦にスクロール。010 §3.3）
    ringFits: (layout === 'xl' || layout === 'wide') && roomW > 150 ? ringBottom <= hv : true, margins, small, wide };
}

/** @returns {{ fixture: string[], size: string[], theme: string[], spectator: boolean, strict: boolean }} */
function parseArgs() {
  const result = { fixture: /** @type {string[]} */ ([]), size: /** @type {string[]} */ ([]), theme: /** @type {string[]} */ ([]), spectator: false, strict: false };
  const argv = process.argv.slice(2);
  for (let i = 0; i < argv.length; i++) {
    const key = argv[i];
    if (key === '--spectator') {
      result.spectator = true;
    } else if (key === '--strict') {
      result.strict = true;
    } else if (key === '--fixture' || key === '--size' || key === '--theme') {
      const value = argv[++i];
      if (value === undefined) {
        throw new Error(`${key} に値がありません`);
      }
      result[key.slice(2)].push(...value.split(','));
    } else {
      throw new Error(`不明な引数: ${key}（--fixture / --size / --theme（カンマ区切り可）、--spectator、--strict）`);
    }
  }
  return result;
}

const args = parseArgs();
const allFixtures = (await readdir(path.join(here, 'fixtures')))
  .filter((f) => f.endsWith('.json') && f !== 'catalog.json')
  .map((f) => f.replace(/\.json$/, ''));
const fixtures = args.fixture.length > 0 ? args.fixture : allFixtures;
const sizes = args.size.length > 0 ? args.size : SIZES;
const themes = args.theme.length > 0 ? args.theme : THEMES;
for (const [list, known, label] of [[fixtures, allFixtures, 'fixture'], [sizes, SIZES, 'size'], [themes, THEMES, 'theme']]) {
  for (const item of list) {
    if (label === 'size' ? !/^\d+x\d+$/.test(item) : !known.includes(item)) {
      throw new Error(`不明な ${label}: ${item}`);
    }
  }
}

const { server, url } = await startServer(0);
const browser = await chromium.launch({ executablePath: CHROME_PATH, headless: true });
/** @type {string[]} */
const errors = [];
/** @type {string[]} */
const overflows = [];
/** @type {string[]} */
const layoutNotes = [];
/** @type {string[]} */
const layoutWarnings = [];
let count = 0;
try {
  for (const fixture of fixtures) {
    const dir = path.join(here, 'out', args.spectator ? `${fixture}-spectator` : fixture);
    await mkdir(dir, { recursive: true });
    for (const size of sizes) {
      const [width, height] = size.split('x').map(Number);
      for (const theme of themes) {
        const label = `${fixture} ${size} ${theme}`;
        const mobile = width < MOBILE_MAX_WIDTH;
        const context = await browser.newContext({
          // 画面の大きさ（デスクトップはブラウザの上部の約 130px を引いた見える高さ。010 §3.1 の Hv の近似）
          viewport: { width, height: mobile ? height : height - BROWSER_CHROME_HEIGHT },
          hasTouch: mobile,
          isMobile: false,
          deviceScaleFactor: 1,
          colorScheme: theme,
        });
        const page = await context.newPage();
        page.on('pageerror', (e) => errors.push(`[${label}] pageerror: ${e.message}`));
        page.on('console', (m) => {
          if (m.type() === 'error') {
            errors.push(`[${label}] console.error: ${m.text()} ${m.location().url ?? ''}`);
          }
        });
        page.on('response', (r) => {
          if (r.status() >= 400) {
            errors.push(`[${label}] HTTP ${r.status()}: ${r.url()}`);
          }
        });
        page.on('requestfailed', (r) => errors.push(`[${label}] requestfailed: ${r.url()} ${r.failure()?.errorText ?? ''}`));
        const query = new URLSearchParams({ fixture, theme });
        if (args.spectator) {
          query.set('spectator', '1');
        }
        await page.goto(`${url}/scripts/preview/index.html?${query}`);
        try {
          await page.waitForFunction(() => document.documentElement.dataset.ready === '1' || document.documentElement.dataset.error !== undefined, null, { timeout: 30000 });
        } catch {
          errors.push(`[${label}] data-ready になりませんでした（タイムアウト）`);
        }
        const pageError = await page.evaluate(() => document.documentElement.dataset.error);
        if (pageError !== undefined) {
          errors.push(`[${label}] data-error: ${pageError.trim()}`);
        }
        await page.waitForLoadState('networkidle').catch(() => {});
        await page.waitForTimeout(300);
        // ページが横にはみ出していないこと（010 §11.1）。エラーとは別に数える（--strict なら失敗にする）
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        if (overflow > 0) {
          overflows.push(`[${label}] ページが横にはみ出しています（scrollWidth が innerWidth より ${overflow}px 大きい）`);
        }
        const m = await page.evaluate(measureLayout);
        layoutNotes.push(`[${label}] ${m.layout} 環の下端 ${m.ringBottom} / 高さ ${m.hv}${m.ringFits ? '' : '（収まらない）'}`);
        if (!m.ringFits) {
          layoutWarnings.push(`[${label}] ${m.layout} なのに環の下端（${m.ringBottom}）が見える高さ（${m.hv}）を超えています`);
        }
        if (!m.margins) {
          layoutWarnings.push(`[${label}] 盤面の左右に 8px の余白がありません`);
        }
        if (m.small.length > 0) {
          layoutWarnings.push(`[${label}] 32px 未満の操作要素 ${m.small.length} 件: ${[...new Set(m.small)].join(', ')}`);
        }
        if (m.wide.length > 0) {
          layoutWarnings.push(`[${label}] 画面の外へはみ出す要素: ${m.wide.join(', ')}`);
        }
        const base = path.join(dir, `${size}-${theme}`);
        await page.screenshot({ path: `${base}.png` });
        await page.screenshot({ path: `${base}-full.png`, fullPage: true });
        count += 2;
        await context.close();
      }
    }
  }
} finally {
  await browser.close();
  server.close();
}

console.log(`${count} 枚を scripts/preview/out/ に保存しました`);
console.log(`\n配置（${layoutNotes.length} 件）:`);
for (const n of layoutNotes) {
  console.log(`  ${n}`);
}
console.log(`\n配置の検証の警告: ${layoutWarnings.length} 件（環が高さに収まらない・余白なし・32px 未満・はみ出し）`);
for (const w of layoutWarnings) {
  console.warn(`  ${w}`);
}
if (overflows.length > 0) {
  console.warn(`\n横はみ出しが ${overflows.length} 件ありました（${args.strict ? '--strict のため失敗にします' : '警告のみ。--strict で失敗にできます'}）:`);
  for (const o of overflows) {
    console.warn(`  ${o}`);
  }
}
if (args.strict && overflows.length > 0) {
  process.exit(1);
}
if (errors.length > 0) {
  console.error(`\nエラーが ${errors.length} 件ありました:`);
  for (const e of new Set(errors)) {
    console.error(`  ${e}`);
  }
  process.exit(1);
}
