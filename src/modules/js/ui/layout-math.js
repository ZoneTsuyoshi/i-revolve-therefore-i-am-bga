/**
 * レイアウトの計算（仕様書 009 §2.4）。DOM に触れない純粋関数。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** wide の下限（`#ir-root` の幅。px） */
export const WIDE_MIN = 1100;
/** medium の下限（px） */
export const MEDIUM_MIN = 600;
/** 幅が測れないとき・極端に狭いときに使う下限（`game_interface_width.min` と同じ） */
export const ROOT_WIDTH_FLOOR = 360;

/** 部屋の幅 W の上限（px） */
export const ROOM_W_MAX = 213;
/** 標準カード幅 w の上限（px。スプライトの解像度が DPR 2 でぼやけない範囲。009 §2.4） */
export const CARD_W_MAX = 128;
/** 横長カード幅 c の上限（px。narrow） */
export const LAND_W_MAX = 149;

/** 想い出カードのセルの縦横比（高さ / 幅 = 390 / 280） */
export const MEM_RATIO = 390 / 280;
/** 横長カードのセルの縦横比（高さ / 幅 = 287 / 400） */
export const LAND_RATIO = 287 / 400;

/**
 * `#ir-root` の幅 → data-layout（009 §2.4）。
 * @param {number} rootWidth
 * @returns {'wide' | 'medium' | 'narrow'}
 */
export function layoutOf(rootWidth) {
  if (rootWidth >= WIDE_MIN) {
    return 'wide';
  }
  if (rootWidth >= MEDIUM_MIN) {
    return 'medium';
  }
  return 'narrow';
}

/**
 * @typedef {object} LayoutSizes
 * @property {'wide' | 'medium' | 'narrow'} layout
 * @property {number} rootW 計算に使った幅 Wc（px）
 * @property {number} gap 隙間 g（px）
 * @property {number} roomW 部屋（床カード）の幅 W
 * @property {number} cardW 標準カード幅 w（想い出カード）
 * @property {number} landW 横長カード幅 c（精神・目標・自我）
 * @property {number} roomCardW 部屋の裏向き想い出カードの幅（0.5 W）
 */

/**
 * 幅 Wc から、レイアウトとカードの大きさを求める（009 §2.4 の表）。
 * @param {number} rootWidth `#ir-root` の幅（px）
 * @returns {LayoutSizes}
 */
export function computeSizes(rootWidth) {
  const rootW = Math.max(ROOT_WIDTH_FLOOR, Math.round(Number.isFinite(rootWidth) ? rootWidth : 0));
  const layout = layoutOf(rootW);
  const gap = layout === 'narrow' ? 6 : 8;
  let roomW;
  if (layout === 'wide') {
    roomW = Math.min(ROOM_W_MAX, Math.floor((rootW - 5 * gap - 16) / 6.1));
  } else if (layout === 'medium') {
    roomW = Math.min(ROOM_W_MAX, Math.floor((rootW - 3 * gap) / 4));
  } else {
    roomW = Math.floor((rootW - 3 * gap) / 4);
  }
  const cardW = layout === 'narrow' ? Math.min(CARD_W_MAX, roomW) : Math.min(CARD_W_MAX, Math.floor(0.6 * roomW));
  const landW = layout === 'narrow' ? Math.min(LAND_W_MAX, Math.floor((rootW - 2 * gap) / 3)) : Math.floor(0.7 * roomW);
  return { layout, rootW, gap, roomW, cardW, landW, roomCardW: Math.round(roomW * 5) / 10 };
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
