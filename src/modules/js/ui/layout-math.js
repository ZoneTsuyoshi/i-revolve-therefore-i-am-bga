/**
 * レイアウトの計算（仕様書 009 §2.4）。DOM に触れない純粋関数。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** medium の下限（px） */
export const MEDIUM_MIN = 600;
/**
 * 幅が測れないとき・極端に狭いときに使う下限。`game_interface_width.min`（360）から、左右の余白 16 と、プレビューの枠の余白を引いた値。
 * これより上げると、360px の画面で盤面が右へはみ出す（010 §8）。
 */
export const ROOT_WIDTH_FLOOR = 300;

/** 部屋の幅 W の上限（px） */
export const ROOM_W_MAX = 213;
/** 高さで縮めるときの W の下限（px。010 §3.3） */
export const ROOM_W_MIN_BY_HEIGHT = 150;
/** 標準カード幅 w の上限（px。スプライトの解像度が DPR 2 でぼやけない範囲。009 §2.4） */
export const CARD_W_MAX = 128;
/** プレイヤーの領域のカード幅 wp の下限（px。wide・medium） */
export const PLAYER_CARD_W_MIN = 104;
/** 横長カード幅 c の上限（px。narrow） */
export const LAND_W_MAX = 149;
/** 環と山札の行の高さ Hb(W) = 4.906 W + 4g の係数 */
export const RING_H_RATIO = 4.906;
/** 縦スクロールバーの出入りで配置が行き来しないための余裕（px。010 §3.3） */
export const HYSTERESIS = 24;

/** 想い出カードのセルの縦横比（高さ / 幅 = 390 / 280） */
export const MEM_RATIO = 390 / 280;
/** 横長カードのセルの縦横比（高さ / 幅 = 287 / 400） */
export const LAND_RATIO = 287 / 400;

/** @typedef {'xl' | 'wide' | 'medium' | 'narrow'} LayoutName */

/** 配置の大きさの順（大きいほど広い） */
export const LAYOUT_RANK = { narrow: 0, medium: 1, wide: 2, xl: 3 };

/**
 * プレイヤーの列の幅 P_w = 4 × wp + 3g + 22（22 は区画の枠と余白）。
 * @param {number} wp
 * @param {number} gap
 * @returns {number}
 */
export function playerColumnWidth(wp, gap) {
  return 4 * wp + 3 * gap + 22;
}

/**
 * 幅から求める W（wide 系は 6.1 W + 5g + 16、medium・narrow は 4 W + 3g）。
 * @param {LayoutName} layout
 * @param {number} rootW
 * @param {number} gap
 * @returns {number}
 */
function roomWidthByWidth(layout, rootW, gap) {
  if (layout === 'xl' || layout === 'wide') {
    return Math.min(ROOM_W_MAX, Math.floor((rootW - 5 * gap - 16) / 6.1));
  }
  if (layout === 'medium') {
    return Math.min(ROOM_W_MAX, Math.floor((rootW - 3 * gap) / 4));
  }
  return Math.floor((rootW - 3 * gap) / 4);
}

/**
 * 高さから求める W（環と山札の行が見える高さ Hv に収まる最大の W）。
 * @param {number} viewHeight 見える高さ Hv。有限でなければ制限なし
 * @param {number} gap
 * @returns {number}
 */
function roomWidthByHeight(viewHeight, gap) {
  return Number.isFinite(viewHeight) ? Math.floor((viewHeight - 4 * gap) / RING_H_RATIO) : Infinity;
}

/**
 * 配置の判定（010 §3.3）。上から順に、最初に満たしたもの。
 * @param {number} rootW
 * @param {number} viewHeight
 * @returns {LayoutName}
 */
export function layoutOf(rootW, viewHeight = Infinity) {
  const w = Math.max(ROOT_WIDTH_FLOOR, Math.round(Number.isFinite(rootW) ? rootW : 0));
  const wx = Math.min(ROOM_W_MAX, Math.max(roomWidthByHeight(viewHeight, 8), ROOM_W_MIN_BY_HEIGHT));
  if (w >= 6.1 * wx + 7 * 8 + playerColumnWidth(CARD_W_MAX, 8)) {
    return 'xl';
  }
  if (roomWidthByWidth('wide', w, 8) >= ROOM_W_MIN_BY_HEIGHT) {
    return 'wide';
  }
  return w >= MEDIUM_MIN ? 'medium' : 'narrow';
}

/**
 * @typedef {object} LayoutSizes
 * @property {LayoutName} layout
 * @property {number} rootW 計算に使った幅 Wc（px）
 * @property {number} viewHeight 計算に使った見える高さ Hv（px。測れないときは Infinity）
 * @property {number} gap 隙間 g（px）
 * @property {number} roomW 部屋（床カード）の幅 W
 * @property {number} cardW 標準カード幅 w（環の中の場札・山札）
 * @property {number} playerCardW プレイヤーの領域のカード幅 wp（手札・自己構成済み）
 * @property {number} landW 横長カード幅 c（精神・目標・自我）
 * @property {number} roomCardW 部屋の裏向き想い出カードの幅（0.5 W）
 */

/**
 * 幅 Wc と見える高さ Hv から、配置とカードの大きさを求める（010 §3.3）。
 * @param {number} rootWidth `#ir-root` の幅（px）
 * @param {number} [viewHeight] 見える高さ Hv（px）。省略・非有限なら高さで抑えない
 * @param {LayoutName} [forceLayout] 配置を指定する（ヒステリシスで現在の配置を保つとき）
 * @returns {LayoutSizes}
 */
export function computeSizes(rootWidth, viewHeight = Infinity, forceLayout) {
  const rootW = Math.max(ROOT_WIDTH_FLOOR, Math.round(Number.isFinite(rootWidth) ? rootWidth : 0));
  const hv = Number.isFinite(viewHeight) && viewHeight > 0 ? viewHeight : Infinity;
  const layout = forceLayout ?? layoutOf(rootW, hv);
  const gap = layout === 'narrow' ? 6 : 8;
  let roomW = roomWidthByWidth(layout, rootW, gap);
  if (layout !== 'narrow') {
    roomW = Math.min(roomW, Math.max(roomWidthByHeight(hv, gap), ROOM_W_MIN_BY_HEIGHT));
  }
  const cardW = layout === 'narrow' ? Math.min(CARD_W_MAX, roomW) : Math.min(CARD_W_MAX, Math.floor(0.6 * roomW));
  let playerCardW;
  if (layout === 'xl') {
    playerCardW = CARD_W_MAX;
  } else if (layout === 'narrow') {
    playerCardW = cardW;
  } else {
    playerCardW = Math.min(CARD_W_MAX, Math.max(Math.floor(0.6 * roomW), PLAYER_CARD_W_MIN));
  }
  const landW = layout === 'narrow' ? Math.min(LAND_W_MAX, Math.floor((rootW - 2 * gap) / 3)) : Math.floor(0.7 * roomW);
  return { layout, rootW, viewHeight: hv, gap, roomW, cardW, playerCardW, landW, roomCardW: Math.round(roomW * 5) / 10 };
}

/**
 * 前回の配置を踏まえて配置を決める（ヒステリシス。010 §3.3）。大きい配置へ変えるのは、幅が 24px 狭くても同じ配置になるときだけ。
 * 小さい配置へはすぐ変える。縦スクロールバー（15〜20px）の出入りで、配置が行き来しないようにする。
 * @param {number} rootWidth
 * @param {number} viewHeight
 * @param {LayoutName | null} previous 前回の配置。初回は null
 * @returns {LayoutSizes}
 */
export function resolveSizes(rootWidth, viewHeight, previous) {
  const now = computeSizes(rootWidth, viewHeight);
  if (previous === null || LAYOUT_RANK[now.layout] <= LAYOUT_RANK[previous]) {
    return now;
  }
  const safe = computeSizes(rootWidth - HYSTERESIS, viewHeight);
  if (safe.layout === now.layout) {
    return now;
  }
  // 今の幅で大きい配置になっても、幅が狭いときは別の配置になる: 前回と安全な配置のうち大きい方を使う
  const keep = LAYOUT_RANK[safe.layout] > LAYOUT_RANK[previous] ? safe.layout : previous;
  return computeSizes(rootWidth, viewHeight, keep);
}

/**
 * 回廊の部屋の位置（巡り順 1〜12）→ 4 × 4 グリッドの [列, 行]（009 §2.4。左上から時計回り）。
 * @type {ReadonlyArray<readonly [number, number]>}
 */
export const RING_CELLS = [
  [1, 1], [2, 1], [3, 1], [4, 1],
  [4, 2], [4, 3], [4, 4],
  [3, 4], [2, 4], [1, 4],
  [1, 3], [1, 2],
];
