// プレビューのスクリーンショットを撮る。実行: npm run preview:shots（絞り込みの例は README.md）
// Chrome の場所は環境変数 CHROME_PATH（既定は macOS の Google Chrome）
import { chromium } from 'playwright-core';
import { mkdir, readdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { locateTipTarget, measureTip, runBlockedClickCheck, runChecks } from './checks.mjs';
import { startServer } from './serve.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const MOBILE_MAX_WIDTH = 980;
/** デスクトップの画面の高さから引く、ブラウザのタブ・アドレスバーなどの高さ（px） */
const BROWSER_CHROME_HEIGHT = 130;
const SIZES = ['360x780', '390x844', '768x1024', '1024x768', '1280x800', '1366x768', '1440x900', '1920x1080', '2560x1440'];
const THEMES = ['light', 'dark'];
/** 検査 7 の代表の対象（011 §9.2）。カードとカード以外 */
const TIP_KINDS = [
  { name: 'hand', selector: '.ir-hand .ir-card' },
  { name: 'composed', selector: '.ir-composed .ir-card' },
  { name: 'room-floor', selector: '.ir-room-floor .ir-card' },
  { name: 'room-back', selector: '.ir-room-card .ir-card' },
  { name: 'open', selector: '.ir-open-slot .ir-card' },
  { name: 'spirit', selector: '.ir-spirit-track .ir-card' },
  { name: 'objective', selector: '.ir-goals .ir-card' },
  { name: 'ego', selector: '.ir-ego .ir-card' },
  { name: 'enh-pile', selector: '.ir-enh-pile' },
  { name: 'token', selector: '.ir-token:not(.ir-zero)' },
  { name: 'panel-item', selector: '.ir-pp-item' },
  { name: 'pile-count', selector: '.ir-pile-slot' },
  { name: 'score', selector: '.ir-player-score' },
  { name: 'cube', selector: '.ir-cube' },
];
/** ツールチップを出す待ち（ホバー 400ms・選択中 700ms・長押し 500ms に余裕を足す） */
const HOVER_WAIT_MS = 520;
const HOVER_SELECTING_WAIT_MS = 820;
const LONG_PRESS_WAIT_MS = 620;
const CHROME_PATH = process.env.CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

/**
 * 配置の検証（010 §11.1）。ブラウザの中で実行する。
 * @returns {{ layout: string, hv: number, ringBottom: number, ringFits: boolean, margins: boolean, wide: string[] }}
 */
function measureLayout() {
  const root = document.getElementById('ir-root');
  const layout = root?.dataset.layout ?? '';
  const main = document.getElementById('ir-main');
  const hv = window.innerHeight;
  const roomW = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--ir-room-w')) || 0;
  // 初期自我などの候補の置き場（ir-pick-panel。#ir-main の先頭）は一時的に環の上に出るので、その高さは数えない（想起の案内パネルは廃止。011 §3.1）
  const pick = document.querySelector('#ir-main > .ir-pick-panel');
  const pickHeight = pick === null || main === null
    ? 0
    : pick.getBoundingClientRect().height + (parseFloat(getComputedStyle(pick).marginTop) || 0) + (parseFloat(getComputedStyle(main).rowGap) || 0);
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
  // 操作できる要素の大きさは検査 3（checks.mjs）で見る
  // 盤面の中でページの右端・左端をはみ出す要素
  const wide = [...document.querySelectorAll('#ir-root *')]
    .filter((e) => visible(e) && (e.getBoundingClientRect().right > vw + 0.5 || e.getBoundingClientRect().left < -0.5))
    .slice(0, 5)
    .map(label);
  return { layout, hv, ringBottom, // W が下限 150 まで縮んだときは収まらなくてよい（縦にスクロール。010 §3.3）
    ringFits: (layout === 'xl' || layout === 'wide') && roomW > 150 ? ringBottom <= hv : true, margins, wide };
}

/**
 * 検査 7: 代表の対象にマウスを乗せる（デスクトップ）か、タップ・長押しする（タッチ）。出たツールチップを測り、必要なら撮る。
 * @param {import('playwright-core').Page} page
 * @param {import('playwright-core').BrowserContext} context
 * @param {boolean} mobile
 * @param {string | null} shotBase 撮影するときの、ファイル名の前の部分（null なら撮らない）
 * @returns {Promise<string[]>}
 */
async function checkTips(page, context, mobile, shotBase) {
  const warnings = [];
  const cdp = mobile ? await context.newCDPSession(page) : null;
  for (const kind of TIP_KINDS) {
    // 対象ごとに探し直す（スクロールで位置が変わるため）
    // （スクロールが滑らかに動くので、止まるのを待ってもう一度測る）
    await page.evaluate(locateTipTarget, kind);
    await page.waitForTimeout(450);
    const target = await page.evaluate(locateTipTarget, kind);
    if (target === null) {
      continue;
    }
    const label = `${target.name}（${mobile ? (target.selectable ? '長押し' : 'タップ') : (target.selectable ? 'ホバー 700ms' : 'ホバー')}）`;
    if (mobile && cdp !== null) {
      const point = [{ x: target.x, y: target.y, id: 1 }];
      if (target.selectable) {
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: point });
        await page.waitForTimeout(LONG_PRESS_WAIT_MS);
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
      } else {
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: point });
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        await page.waitForTimeout(80);
      }
    } else {
      await page.mouse.move(1, 1);
      await page.mouse.move(target.x, target.y);
      await page.waitForTimeout(target.selectable ? HOVER_SELECTING_WAIT_MS : HOVER_WAIT_MS);
    }
    for (const w of await page.evaluate(measureTip, { x: target.x, y: target.y })) {
      warnings.push(`${label} ${w}`);
    }
    if (shotBase !== null) {
      await page.screenshot({ path: `${shotBase}-tip-${target.name}.png` });
    }
    // 閉じる（Esc）。出たままでないこと
    await page.keyboard.press('Escape');
    await page.mouse.move(1, 1);
    await page.waitForTimeout(30);
    if (await page.evaluate(() => document.querySelector('.ir-tip') !== null)) {
      warnings.push(`${label} Esc で閉じない`);
    }
  }
  await cdp?.detach();
  return warnings;
}

/** @returns {{ fixture: string[], size: string[], theme: string[], spectator: boolean, strict: boolean, tips: boolean, tipShots: boolean, captions?: string }} */
function parseArgs() {
  const result = { fixture: /** @type {string[]} */ ([]), size: /** @type {string[]} */ ([]), theme: /** @type {string[]} */ ([]), spectator: false, strict: false, tips: true, tipShots: false };
  const argv = process.argv.slice(2);
  for (let i = 0; i < argv.length; i++) {
    const key = argv[i];
    if (key === '--spectator') {
      result.spectator = true;
    } else if (key === '--strict') {
      result.strict = true;
    } else if (key === '--no-tips') {
      result.tips = false;
    } else if (key === '--tip-shots') {
      result.tipShots = true;
    } else if (key === '--captions') {
      result.captions = argv[++i];
    } else if (key === '--fixture' || key === '--size' || key === '--theme') {
      const value = argv[++i];
      if (value === undefined) {
        throw new Error(`${key} に値がありません`);
      }
      result[key.slice(2)].push(...value.split(','));
    } else {
      throw new Error(`不明な引数: ${key}（--fixture / --size / --theme（カンマ区切り可）、--spectator、--strict、--no-tips、--tip-shots、--captions 0/1）`);
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
/** 検査番号 → 警告（011 §9.2） */
const CHECK_TITLES = { 2: '浮いた要素（fixed / sticky）', 3: '32px 未満の操作要素', 4: 'title 属性', 5: '12px 未満の文字', 6: '入れ子のスクロール', 7: 'ツールチップの位置・大きさ', 8: '角丸 3px 超（許可した部品以外）', 10: '英語名が他の文字・カードと重なる', 9: 'ir-off を押してもメッセージが出ない' };
/** @type {Record<string, string[]>} */
const checkWarnings = { 2: [], 3: [], 4: [], 5: [], 6: [], 7: [], 8: [], 9: [], 10: [] };
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
        if (args.captions !== undefined) {
          query.set('captions', args.captions);
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
        // 自動検査（仕様書 011 §9.2）。検査 2・3・4・5・6・8・10（7・9 は撮影のあと）
        const checks = await page.evaluate(runChecks);
        for (const [no, list] of Object.entries(checks)) {
          for (const item of list) {
            checkWarnings[no].push(`[${label}] ${item}`);
          }
        }
        if (m.wide.length > 0) {
          layoutWarnings.push(`[${label}] 画面の外へはみ出す要素: ${m.wide.join(', ')}`);
        }
        const base = path.join(dir, `${size}-${theme}`);
        await page.screenshot({ path: `${base}.png` });
        await page.screenshot({ path: `${base}-full.png`, fullPage: true });
        count += 2;
        // 検査 7（ツールチップ）。位置は色に依らないので、最初のテーマだけで測る
        if (args.tips && theme === themes[0]) {
          for (const w of await checkTips(page, context, mobile, args.tipShots ? base : null)) {
            checkWarnings[7].push(`[${label}] ${w}`);
          }
        }
        // 検査 9（ir-off を押すとメッセージが出る）。ツールチップの検査のあとに行う。メッセージが出ている間の画面も撮る
        await page.keyboard.press('Escape');
        await page.evaluate(() => window.scrollTo(0, 0));
        const blocked = await page.evaluate(runBlockedClickCheck);
        for (const w of blocked.warnings) {
          checkWarnings[9].push(`[${label}] ${w}`);
        }
        if (blocked.tried > 0) {
          await page.waitForTimeout(100);
          await page.screenshot({ path: `${base}-blocked.png` });
          count += 1;
        }
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
console.log(`\n配置の検証の警告: ${layoutWarnings.length} 件（環が高さに収まらない・余白なし・はみ出し）`);
for (const w of layoutWarnings) {
  console.warn(`  ${w}`);
}
const checkTotal = Object.values(checkWarnings).reduce((sum, list) => sum + list.length, 0);
console.log(`\n自動検査の警告: ${checkTotal} 件（${Object.entries(CHECK_TITLES).map(([no, title]) => `検査 ${no} ${title} ${checkWarnings[no].length}`).join(' / ')}）`);
for (const [no, list] of Object.entries(checkWarnings)) {
  for (const w of list) {
    console.warn(`  [検査 ${no}] ${w}`);
  }
}
if (overflows.length > 0) {
  console.warn(`\n横はみ出しが ${overflows.length} 件ありました（${args.strict ? '--strict のため失敗にします' : '警告のみ。--strict で失敗にできます'}）:`);
  for (const o of overflows) {
    console.warn(`  ${o}`);
  }
}
if (args.strict && (overflows.length > 0 || layoutWarnings.length > 0 || checkTotal > 0)) {
  console.error('--strict のため、警告があるので失敗にします');
  process.exit(1);
}
if (errors.length > 0) {
  console.error(`\nエラーが ${errors.length} 件ありました:`);
  for (const e of new Set(errors)) {
    console.error(`  ${e}`);
  }
  process.exit(1);
}
