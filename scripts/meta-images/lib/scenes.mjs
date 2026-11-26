// メタ画像の構図（HTML/CSS）。仕様書 009 §14.3
import { pathToFileURL } from 'node:url';
import path from 'node:path';

const url = (/** @type {string} */ p) => encodeURI(pathToFileURL(p).href);

/** @param {{ mincho: string, garamond: string }} fonts */
function fontFaces(fonts) {
  return `
@font-face { font-family: "IR Mincho"; src: url("${url(fonts.mincho)}"); font-weight: 800; }
@font-face { font-family: "IR Garamond"; src: url("${url(fonts.garamond)}"); font-weight: 400 800; font-style: italic; }`;
}

const BASE_CSS = `
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { overflow: hidden; }
body { position: relative; }
.abs { position: absolute; }
`;

const page = (/** @type {string} */ css, /** @type {string} */ body, /** @type {string} */ script = '') =>
  `<!doctype html><html><head><meta charset="utf-8"><style>${BASE_CSS}${css}</style></head><body>${body}${script ? `<script>${script}</script>` : ''}</body></html>`;

/** 想い出カードの額縁（x = 128〜598、y = 40〜512。文字なし）だけを見せる要素 */
function frameEl(/** @type {string} */ data, /** @type {string|number} */ id, /** @type {number} */ height, /** @type {string} */ style) {
  const s = height / 472;
  const w = 470 * s;
  return `<div class="abs" style="width:${w}px;height:${height}px;overflow:hidden;${style}">` +
    `<img src="${url(path.join(data, `cards/memory/${id}.png`))}" style="position:absolute;width:${722 * s}px;left:${-128 * s}px;top:${-40 * s}px"></div>`;
}

// ---------------------------------------------------------------------------------------------
// Title（2000 × 2000、透過）
// ---------------------------------------------------------------------------------------------
export const TITLE_ZONE = { top: 612, bottom: 1388 };

/** @param {{ mincho: string, garamond: string }} fonts */
export function titleHtml(fonts) {
  const css = `${fontFaces(fonts)}
html, body { width: 2000px; height: 2000px; background: transparent; }
.t { position: absolute; left: 0; width: 2000px; text-align: center; color: #f4ead6; white-space: nowrap;
  paint-order: stroke fill; -webkit-text-stroke-color: #2a1a0e; text-shadow: 0 10px 28px rgba(20, 10, 4, .6), 0 2px 6px rgba(20, 10, 4, .6); }
.en { font-family: "IR Garamond", serif; font-style: italic; font-weight: 700; line-height: 1; }
.jp { font-family: "IR Mincho", serif; font-weight: 800; line-height: 1; letter-spacing: .06em; }
.jp .big { font-size: 1.3em; }
.orn { position: absolute; left: 0; width: 2000px; display: flex; justify-content: center; align-items: center; gap: 22px;
  filter: drop-shadow(0 0 3px #2a1a0e) drop-shadow(0 6px 10px rgba(20,10,4,.55)); }
.orn i { display: block; height: 5px; width: 300px; background: #f4ead6; border-radius: 3px; }
.orn b { display: block; width: 30px; height: 30px; border: 6px solid #f4ead6; border-radius: 50%; }`;
  const body = `
<div class="t en" id="l1">I Revolve,</div>
<div class="t en" id="l2">Therefore I Am</div>
<div class="orn" id="orn"><i></i><b></b><i></i></div>
<div class="t jp" id="jp">我<span class="big">廻</span>る、故に<span class="big">我</span>あり</div>`;
  const script = `
window.__fit = () => {
  const l1 = document.getElementById('l1'), l2 = document.getElementById('l2'), jp = document.getElementById('jp');
  const textWidth = (el) => { const r = document.createRange(); r.selectNodeContents(el); return r.getBoundingClientRect().width; };
  // 英語: 広い方の行を 1780px にする
  let f = 300; l1.style.fontSize = l2.style.fontSize = f + 'px';
  f = f * 1780 / textWidth(l2);
  const lh = f * 0.92; // 行間は詰め気味
  for (const el of [l1, l2]) { el.style.fontSize = f + 'px'; el.style.webkitTextStrokeWidth = (f * 0.035) + 'px'; }
  const mid = 1000, gap = lh;
  l1.style.top = (mid - gap / 2 - f / 2) + 'px';
  l2.style.top = (mid + gap / 2 - f / 2) + 'px';
  // 日本語: 1 行の幅を 1300px にする
  let j = 100; jp.style.fontSize = j + 'px';
  j = j * 1300 / textWidth(jp);
  jp.style.fontSize = j + 'px'; jp.style.webkitTextStrokeWidth = (j * 0.055) + 'px';
  jp.style.top = (1545 - j * 1.3 / 2) + 'px';
  document.getElementById('orn').style.top = '1392px';
  return { enFont: f, enW1: textWidth(l1), enW2: textWidth(l2), jpFont: j, jpW: textWidth(jp),
    enTop: l1.getBoundingClientRect().top, enBottom: l2.getBoundingClientRect().bottom };
};`;
  return page(css, body, script);
}

/** Title を明るい背景に重ねて読めるか確かめる用（登録しない）。Title の PNG を file:// で読む */
export function titleOnLightHtml(titleFile) {
  return page(`html,body{width:1000px;height:1000px;background:#f2efe9}`, `<img src="${url(titleFile)}" style="width:1000px;height:1000px">`);
}

// ---------------------------------------------------------------------------------------------
// Banner（1386 × 400、文字なし）
// ---------------------------------------------------------------------------------------------
export const BANNER_ORDER = [79, 2, 46, 94, 3, 91];

export function bannerHtml(data) {
  const FH = 232; // 額の高さ
  const FW = Math.round(FH * 470 / 472);
  const X0 = 330, STEP = 256, TOP = 66;
  const frames = BANNER_ORDER.map((id, i) => {
    const x = X0 + i * STEP;
    return `
<div class="abs glow" style="left:${x + FW / 2 - 230}px;top:0;"></div>
<div class="abs" style="left:${x}px;top:${TOP + 10}px;width:${FW}px;height:${FH}px;box-shadow:0 26px 30px rgba(0,0,0,.55), 0 4px 8px rgba(0,0,0,.6);"></div>
${frameEl(data, id, FH, `left:${x}px;top:${TOP}px;filter:brightness(.8) saturate(.95);`)}
<div class="abs" style="left:${x}px;top:${TOP}px;width:${FW}px;height:${FH}px;background:linear-gradient(180deg,rgba(255,200,130,.20),rgba(255,200,130,0) 55%);"></div>
<div class="abs lamp" style="left:${x + FW / 2 - 58}px;top:${TOP - 30}px;"></div>
<div class="abs rod" style="left:${x + FW / 2 - 2}px;top:${TOP - 24}px;"></div>`;
  }).join('');
  const panels = Array.from({ length: 12 }, (_, i) => `<div class="abs panel" style="left:${20 + i * 128}px;"></div>`).join('');
  const css = `
html, body { width: 1386px; height: 400px; background: #1d140d; }
.wall { position: absolute; inset: 0; background: linear-gradient(180deg, #2f2318 0%, #281d13 40%, #1e150e 100%); }
.grain { position: absolute; inset: 0; opacity: .16; mix-blend-mode: overlay;
  background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='300' height='300'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2' seed='7'/><feColorMatrix values='0 0 0 0 .5  0 0 0 0 .4  0 0 0 0 .3  0 0 0 1.2 -.1'/></filter><rect width='300' height='300' filter='url(%23n)'/></svg>"); }
.crown { position: absolute; left: 0; top: 0; width: 100%; height: 34px;
  background: linear-gradient(180deg, #4d3a26 0, #5a452d 5px, #16100a 5px, #16100a 8px, #3d2d1d 8px, #2c2015 18px, #8f6f40 18px, #8f6f40 19px, #130d08 19px, #130d08 23px, #34261a 23px, #2a1f14 32px, #0f0a06 32px);
  box-shadow: 0 10px 18px rgba(0,0,0,.5); }
.rail { position: absolute; left: 0; top: 336px; width: 100%; height: 12px;
  background: linear-gradient(180deg, #8f6f40 0, #8f6f40 1px, #54402a 1px, #3a2b1c 8px, #0f0a06 8px); box-shadow: 0 -4px 14px rgba(0,0,0,.4); }
.wains { position: absolute; left: 0; top: 348px; width: 100%; height: 52px; background: linear-gradient(180deg, #1a120b, #110b07); }
.panel { top: 360px; width: 104px; height: 60px; border: 1px solid rgba(143,111,64,.38); box-shadow: inset 0 0 12px rgba(0,0,0,.6), 0 -1px 0 rgba(0,0,0,.5); }
.glow { width: 460px; height: 340px;
  background: radial-gradient(ellipse 50% 70% at 50% 14%, rgba(255,196,110,.55), rgba(255,160,70,.2) 45%, rgba(255,150,60,0) 72%); mix-blend-mode: screen; }
.lamp { width: 116px; height: 8px; border-radius: 4px; background: linear-gradient(180deg, #e8c27a, #8a6a2e); box-shadow: 0 0 14px 4px rgba(255,200,120,.75), 0 3px 4px rgba(0,0,0,.6); }
.rod { width: 4px; height: 24px; background: linear-gradient(90deg, #5c4521, #b99049, #5c4521); }
.vig { position: absolute; inset: 0; pointer-events: none;
  background: radial-gradient(ellipse 85% 120% at 58% 45%, rgba(0,0,0,0) 45%, rgba(0,0,0,.6) 100%), linear-gradient(90deg, rgba(8,5,3,.55) 0, rgba(8,5,3,0) 340px); }`;
  return page(css, `<div class="wall"></div><div class="grain"></div>${panels}<div class="wains" style="z-index:0"></div>${frames}<div class="crown"></div><div class="rail"></div><div class="vig"></div>`);
}

// ---------------------------------------------------------------------------------------------
// Display 2・3（1140 × 760）
// ---------------------------------------------------------------------------------------------
const TABLE_CSS = `
html, body { width: 1140px; height: 760px; background: #1d140d; }
.table { position: absolute; inset: 0;
  background:
    radial-gradient(ellipse 80% 90% at 50% 42%, rgba(255,190,110,.20), rgba(255,170,90,0) 70%),
    repeating-linear-gradient(92deg, rgba(0,0,0,.10) 0 3px, rgba(255,230,190,.025) 3px 9px, rgba(0,0,0,.05) 9px 14px, rgba(255,230,190,.02) 14px 31px),
    linear-gradient(180deg, #3a2a1b, #2a1d12 60%, #20160d); }
.table::after { content: ""; position: absolute; inset: 0; background: radial-gradient(ellipse 90% 100% at 50% 45%, rgba(0,0,0,0) 50%, rgba(0,0,0,.55) 100%); }
.card { position: absolute; filter: drop-shadow(0 14px 16px rgba(0,0,0,.55)) drop-shadow(0 2px 3px rgba(0,0,0,.6)); }
.card img { display: block; width: 100%; height: 100%; }`;

const card = (/** @type {string} */ file, /** @type {number} */ w, /** @type {number} */ h, /** @type {string} */ style) =>
  `<div class="card" style="width:${w}px;height:${h}px;${style}"><img src="${url(file)}"></div>`;

export function display2Html(data) {
  const H = 440, W = Math.round(H * 722 / 1006);
  const f = (/** @type {string|number} */ n) => path.join(data, `cards/memory/${n}.png`);
  const fan = [3, 46, 79, 91, 94];
  const STEP = 156, X0 = 570 - (W + STEP * 4) / 2;
  const rots = [-9, -4.5, 0, 4.5, 9];
  const ys = [92, 70, 58, 70, 92];
  const fronts = fan.map((id, i) => card(f(id), W, H, `left:${X0 + i * STEP}px;top:${ys[i]}px;transform:rotate(${rots[i]}deg);transform-origin:50% 130%;`)).join('');
  const backs =
    card(f('Rev1B'), 200, 279, 'left:30px;top:462px;transform:rotate(-9deg);') +
    card(f('Rev2H'), 200, 279, 'left:912px;top:450px;transform:rotate(8deg);');
  return page(TABLE_CSS, `<div class="table"></div>${backs}${fronts}`);
}

export function display3Html(data) {
  const d = (/** @type {string} */ p) => path.join(data, p);
  const W = 344, H = Math.round(W * 723 / 1007), STEP = 108;
  const tiers = ['R1a', 'R2', 'R3a', 'R4', 'R5'];
  const X = 56, Y5 = 40;
  // 第五階層が最上段。上の階層ほど手前（下の階層は、下側の費用とボーナスが見える）
  const stack = tiers.map((r, i) => {
    const tier = i; // 0 = 第一階層
    const y = Y5 + (4 - tier) * STEP;
    return card(d(`cards/analysis/${r}.png`), W, H, `left:${X}px;top:${y}px;z-index:${tier + 1};`);
  }).join('');
  const cubeW = 34, cubeH = 39;
  const cube = (/** @type {string} */ c, /** @type {number} */ tier, /** @type {number} */ dx, /** @type {number} */ dy) =>
    `<img class="card" style="width:${cubeW}px;height:${cubeH}px;left:${X + dx}px;top:${Y5 + (4 - tier) * STEP + H - dy}px;z-index:9;filter:drop-shadow(0 4px 4px rgba(0,0,0,.6));" src="${url(d(`piecies/cube/cube_${c}@4x.png`))}">`;
  const cubes = cube('blue', 3, 150, 90) + cube('red', 2, 120, 90) + cube('green', 2, 190, 90) + cube('yellow', 0, 150, 78);
  const side = (/** @type {string} */ p, /** @type {string} */ style) => card(d(p), 330, 237, style);
  const sides =
    side('cards/ability/I1.png', 'left:470px;top:36px;transform:rotate(-4deg);') +
    side('cards/enhancement/E1.png', 'left:800px;top:70px;transform:rotate(3.5deg);') +
    side('cards/objective/O1.png', 'left:628px;top:296px;transform:rotate(-1.5deg);');
  const toks = ['tokenHR', 'tokenM', 'tokenC', 'tokenR'].map((t, i) =>
    card(d(`tokens/${t}.png`), 118, 118, `left:${500 + i * 124}px;top:${548}px;transform:rotate(${[-8, 6, -4, 9][i]}deg);`)).join('');
  // 人格はトークンと重ねず、目標カードの右の空きに 2 × 2 でまとめる（原本 62 × 84 を 1.15 倍ほど）
  const meeples = ['r', 'b', 'g', 'y'].map((c, i) =>
    card(d(`piecies/meeple/meeple_${c}@4x.png`), 71, 96, `left:${975 + (i % 2) * 72}px;top:${300 + Math.floor(i / 2) * 104}px;transform:rotate(${[-5, 4, 3, -4][i]}deg);`)).join('');
  const smallCubes = ['red', 'blue', 'green', 'yellow'].map((c, i) =>
    `<img class="card" style="width:30px;height:34px;left:${978 + i * 36}px;top:512px;" src="${url(d(`piecies/cube/cube_${c}@4x.png`))}">`).join('');
  return page(TABLE_CSS, `<div class="table"></div>${stack}${cubes}${sides}${toks}${meeples}${smallCubes}`);
}

// ---------------------------------------------------------------------------------------------
// Icon の代替案（「廻」の字を箱の色の地に置く）
// ---------------------------------------------------------------------------------------------
/** @param {{ mincho: string, garamond: string }} fonts */
export function iconFontHtml(fonts) {
  const css = `${fontFaces(fonts)}
html, body { width: 200px; height: 200px; background: #5b472f; }
.bg { position: absolute; inset: 0; background: radial-gradient(ellipse 80% 80% at 45% 35%, #8a7050, #5b472f 55%, #2f2216 100%); }
.k { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font: 800 150px/1 "IR Mincho", serif; color: #f4ead6;
  paint-order: stroke fill; -webkit-text-stroke: 4px #2a1a0e; text-shadow: 0 4px 10px rgba(20,10,4,.6); padding-bottom: 8px; }`;
  return page(css, '<div class="bg"></div><div class="k">廻</div>');
}
