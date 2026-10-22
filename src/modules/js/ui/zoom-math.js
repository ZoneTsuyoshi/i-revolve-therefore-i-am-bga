/**
 * 拡大表示とツールチップの寸法・位置の計算（仕様書 009 §7.1・§7.2）。DOM に依存しない純粋関数。
 * 高さの基準は `100vh` ではなく `window.innerHeight`（iOS の 100vh はツールバーを含み下が切れる。003 §13、姉妹 MJ #39・#52）。呼び出し側が渡す。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** 拡大画像の幅の上限（想い出カード / 横長カード。009 §7.2: min(92vw, 360)・min(92vw, 480)） */
const PORTRAIT_MAX_W = 360;
const LANDSCAPE_MAX_W = 480;
/** 縦横比（高さ / 幅）。スプライトのセルと同じ: 想い出 280:390、横長 400:287 */
const PORTRAIT_RATIO = 390 / 280;
const LANDSCAPE_RATIO = 287 / 400;
/** 英語テキストの列の最小幅。これと画像が横に並ばないときは縦に積む */
const TEXT_MIN_W = 240;
const GAP = 12;
const MARGIN = 8;
/** 枠の余白（padding 10 × 2）と罫線（1 × 2） */
const DIALOG_CHROME = 22;

/**
 * @typedef {object} ZoomLayout
 * @property {number} imageW 拡大画像の幅（px）
 * @property {number} imageH 拡大画像の高さ（px）
 * @property {boolean} stacked 画像の下にテキストを積むか（狭い画面）。false なら横に並べる
 * @property {number} textMaxH テキストの欄の高さの上限（px。超えたら枠の中でスクロール）
 * @property {number} dialogMaxH 拡大表示の枠全体（余白・罫線を含む）の高さの上限（px）
 */

/**
 * 拡大表示の寸法。
 * @param {object} p
 * @param {number} p.vw window.innerWidth
 * @param {number} p.vh window.innerHeight
 * @param {number} [p.top] 上端に空ける高さ（ステータスバーの下端。ボタンを隠さない）
 * @param {boolean} p.landscape 横長のカードか
 * @returns {ZoomLayout}
 */
export function zoomLayout({ vw, vh, top = 0, landscape }) {
    const ratio = landscape ? LANDSCAPE_RATIO : PORTRAIT_RATIO;
    // 枠の中身（画像とテキスト）に使える幅と高さ
    const availW = Math.max(100, vw - MARGIN * 2 - DIALOG_CHROME);
    const availH = Math.max(100, vh - Math.max(0, top) - MARGIN * 2 - DIALOG_CHROME);
    const maxW = Math.max(80, Math.min(vw * 0.92, landscape ? LANDSCAPE_MAX_W : PORTRAIT_MAX_W));
    const sideBySide = availW >= Math.min(maxW, availH / ratio) + GAP + TEXT_MIN_W;
    if (sideBySide) {
        const imageW = Math.floor(Math.min(maxW, availH / ratio));
        return { imageW, imageH: Math.round(imageW * ratio), stacked: false, textMaxH: Math.floor(availH), dialogMaxH: Math.floor(availH) + DIALOG_CHROME };
    }
    // 縦に積む: 画像は枠の高さの 55 % まで。残りがテキスト
    const imageW = Math.floor(Math.min(maxW, (availH * 0.55) / ratio, availW));
    const imageH = Math.round(imageW * ratio);
    return { imageW, imageH, stacked: true, textMaxH: Math.max(60, Math.floor(availH - imageH - GAP)), dialogMaxH: Math.floor(availH) + DIALOG_CHROME };
}

/**
 * @typedef {{ left: number, top: number, right: number, bottom: number }} Box
 */

/**
 * ツールチップの位置（`position: fixed` の left・top）。カードの右に出し、入らなければ左。縦は画面内に収める。
 * @param {object} p
 * @param {Box} p.card カードの矩形
 * @param {number} p.tipW ツールチップの幅
 * @param {number} p.tipH ツールチップの高さ
 * @param {number} p.vw window.innerWidth
 * @param {number} p.vh window.innerHeight
 * @returns {{ left: number, top: number }}
 */
export function tooltipPosition({ card, tipW, tipH, vw, vh }) {
    let left = card.right + GAP;
    if (left + tipW > vw - MARGIN) {
        left = card.left - GAP - tipW;
    }
    left = Math.min(Math.max(MARGIN, left), Math.max(MARGIN, vw - tipW - MARGIN));
    const top = Math.min(Math.max(MARGIN, card.top), Math.max(MARGIN, vh - tipH - MARGIN));
    return { left: Math.round(left), top: Math.round(top) };
}

/**
 * ツールチップの中の拡大画像の寸法。想い出カード（縦長）は画像の右にテキスト、横長カードは画像の上にテキスト（stacked）。
 * 画面が低いときは、高さを画面の 45 % までに抑える。
 * @param {object} p
 * @param {boolean} p.landscape 横長のカードか
 * @param {number} p.vw window.innerWidth
 * @param {number} p.vh window.innerHeight
 * @returns {{ imageW: number, imageH: number, stacked: boolean }}
 */
export function tooltipImageSize({ landscape, vw, vh }) {
    const ratio = landscape ? LANDSCAPE_RATIO : PORTRAIT_RATIO;
    const wantW = landscape ? 300 : 210;
    const imageW = Math.max(80, Math.floor(Math.min(wantW, vw * 0.5, (vh * 0.45) / ratio)));
    return { imageW, imageH: Math.round(imageW * ratio), stacked: landscape };
}
