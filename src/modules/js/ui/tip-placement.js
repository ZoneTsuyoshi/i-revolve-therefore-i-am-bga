/**
 * ツールチップの位置と画像の寸法の計算（仕様書 011 §6.3・§6.4）。DOM に依存しない純粋関数。
 * 高さの基準は `100vh` ではなく `window.innerHeight`（iOS の 100vh はツールバーを含み下が切れる。003 §13）。呼び出し側が渡す。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** 縦横比（高さ / 幅）。スプライトのセルと同じ: 想い出 280:390、横長 400:287 */
const PORTRAIT_RATIO = 390 / 280;
const LANDSCAPE_RATIO = 287 / 400;
/** 画像の幅の希望（想い出カード / 横長カード）と最小 */
const PORTRAIT_WANT_W = 220;
const LANDSCAPE_WANT_W = 300;
const PORTRAIT_MIN_W = 120;
const LANDSCAPE_MIN_W = 180;
/** 卓の上の同じカードに対する画像の幅の倍率の下限（B.1。画面の見え方と明確に違うこと） */
const MIN_SCALE = 1.25;

/**
 * @typedef {{ left: number, top: number, right: number, bottom: number }} Box
 * @typedef {'right' | 'left' | 'below' | 'above'} Side
 * @typedef {object} Placement
 * @property {Side | 'fit'} placement 入った側。どの側にも希望の大きさで入らなかったら 'fit'（`side` が選んだ側。`maxWidth` / `maxHeight` に収める）
 * @property {Side} side
 * @property {number} left `position: fixed` の left
 * @property {number} top `position: fixed` の top
 * @property {number} maxWidth 入る最大の幅（入ったときは希望の幅）
 * @property {number} maxHeight 入る最大の高さ（入ったときは希望の高さ）
 */

/**
 * ツールチップの位置。対象のすぐ横に、右 → 左 → 下 → 上の順で、入る側に置く。対象を覆わず、画面の端から `margin` 以上離し、
 * 上は `top`（ステータスバーの下端）より下にする。
 * @param {object} p
 * @param {Box} p.anchor 対象の矩形（getBoundingClientRect）
 * @param {{ width: number, height: number }} p.size ツールチップの希望の大きさ
 * @param {{ width: number, height: number }} p.viewport window.innerWidth / innerHeight
 * @param {number} [p.top] 使える上端（BGA のステータスバー #page-title の下端。なければ 0）
 * @param {number} [p.margin]
 * @param {number} [p.gap] 対象との間
 * @returns {Placement}
 */
export function placeTip({ anchor, size, viewport, top = 0, margin = 8, gap = 8 }) {
    const minX = margin;
    const maxX = viewport.width - margin;
    const minY = Math.max(0, top) + margin;
    const maxY = viewport.height - margin;
    const availW = Math.max(0, maxX - minX);
    const availH = Math.max(0, maxY - minY);
    /** @type {Record<Side, { w: number, h: number }>} */
    const room = {
        right: { w: maxX - (anchor.right + gap), h: availH },
        left: { w: anchor.left - gap - minX, h: availH },
        below: { w: availW, h: maxY - Math.max(anchor.bottom + gap, minY) },
        above: { w: availW, h: anchor.top - gap - minY },
    };
    /** @type {Side[]} */
    const order = ['right', 'left', 'below', 'above'];
    const clamp = (/** @type {number} */ v, /** @type {number} */ lo, /** @type {number} */ hi) => Math.max(lo, Math.min(v, Math.max(lo, hi)));
    /**
     * @param {Side} side
     * @param {number} width
     * @param {number} height
     */
    const at = (side, width, height) => {
        switch (side) {
            case 'right': return { left: Math.ceil(anchor.right + gap), top: Math.round(clamp(anchor.top, minY, maxY - height)) };
            case 'left': return { left: Math.floor(anchor.left - gap - width), top: Math.round(clamp(anchor.top, minY, maxY - height)) };
            case 'below': return { left: Math.round(clamp(anchor.left, minX, maxX - width)), top: Math.ceil(Math.max(anchor.bottom + gap, minY)) };
            default: return { left: Math.round(clamp(anchor.left, minX, maxX - width)), top: Math.floor(anchor.top - gap - height) };
        }
    };
    for (const side of order) {
        if (size.width <= room[side].w && size.height <= room[side].h) {
            return { placement: side, side, ...at(side, size.width, size.height), maxWidth: size.width, maxHeight: size.height };
        }
    }
    // どの側にも入らない: いちばん広い側（同点は順の早いほう）に、その余白へ収まる大きさで置く
    let best = order[0];
    let bestArea = -1;
    for (const side of order) {
        const area = Math.max(0, room[side].w) * Math.max(0, room[side].h);
        if (area > bestArea) {
            best = side;
            bestArea = area;
        }
    }
    const maxWidth = Math.max(0, Math.min(size.width, room[best].w));
    const maxHeight = Math.max(0, Math.min(size.height, room[best].h));
    return { placement: 'fit', side: best, ...at(best, maxWidth, maxHeight), maxWidth, maxHeight };
}

/**
 * ツールチップの中の画像の幅の範囲。希望（縦長 220・横長 300。卓の上の同じカードの 1.25 倍に満たなければその幅）から、
 * 最小（縦長 120・横長 180。同じく卓の上の同じカードの 1.25 倍以上）まで。
 * @param {object} p
 * @param {boolean} p.landscape 横長のカードか
 * @param {number} [p.tableWidth] 卓の上の同じカードの幅（0 なら卓にない）
 * @returns {{ want: number, min: number }}
 */
export function imageWidthRange({ landscape, tableWidth = 0 }) {
    const floor = landscape ? LANDSCAPE_MIN_W : PORTRAIT_MIN_W;
    const min = Math.max(floor, Math.ceil(Math.max(0, tableWidth) * MIN_SCALE));
    return { want: Math.max(landscape ? LANDSCAPE_WANT_W : PORTRAIT_WANT_W, min), min };
}

/**
 * 画像の幅の候補（大きい順。希望から最小まで）。
 * @param {{ want: number, min: number }} range
 * @param {number} [count]
 * @returns {number[]}
 */
export function imageWidthSteps({ want, min }, count = 6) {
    /** @type {number[]} */
    const steps = [];
    for (let i = 0; i < count; i++) {
        const width = Math.round(want - ((want - min) * i) / (count - 1));
        if (steps.length === 0 || steps[steps.length - 1] !== width) {
            steps.push(width);
        }
    }
    return steps;
}

/**
 * 画像の高さ。
 * @param {number} width
 * @param {boolean} landscape
 * @returns {number}
 */
export function imageHeight(width, landscape) {
    return Math.round(width * (landscape ? LANDSCAPE_RATIO : PORTRAIT_RATIO));
}
