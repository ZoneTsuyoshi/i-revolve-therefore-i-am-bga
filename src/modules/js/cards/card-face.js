/**
 * カード 1 枚をスプライトから描画する（仕様書 009 §10）。配置表 `ui/sprites.js`（生成物）を引く。
 * 見つからないキーでも例外を出さず、`ir-sprite-missing` を付けて空の面にする。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { CARDS, SHEETS, UI, ZOOM } from '../ui/sprites.js';
import { gridCellStyle, isPortraitKey, rectStyle } from './sprite-css.js';

/** @typedef {'base' | 'zoom'} Tier */
/** @typedef {{ sheet: string, col: number, row: number }} CardCell */
/** @typedef {{ file: string, w: number, h: number, cols?: number, rows?: number }} SheetInfo */

// 生成物の型は個別のキーのリテラルになるので、キーの文字列で引けるように型を広げる
const CARD_TABLE = /** @type {Record<string, CardCell>} */ (/** @type {unknown} */ (CARDS));
const ZOOM_TABLE = /** @type {Record<string, CardCell>} */ (/** @type {unknown} */ (ZOOM));
const SHEET_TABLE = /** @type {Record<string, SheetInfo>} */ (/** @type {unknown} */ (SHEETS));
const UI_TABLE = /** @type {Record<string, { sheet: string, x: number, y: number, w: number, h: number }>} */ (/** @type {unknown} */ (UI));
/** @typedef {(file: string) => string} ImgUrl 画像ファイル名 → URL（BGA では `bga.images.getImgUrl`） */

/**
 * キーが配置表にあるか。
 * @param {string} key
 * @param {Tier} [tier]
 * @returns {boolean}
 */
export function hasCard(key, tier = 'base') {
  return (tier === 'zoom' ? ZOOM_TABLE : CARD_TABLE)[key] !== undefined;
}

/**
 * 要素の背景にカードの面を描く。大きさは要素（CSS）が決める。
 * ZOOM に無いキー（裏面）は base にフォールバックする。
 * @param {HTMLElement} div
 * @param {string} key `M001`・`Rev1B`・`F01`・`R1A`・`I01`・`E01`・`O001` など（仕様書 009 §10.3）
 * @param {ImgUrl} imgUrl
 * @param {Tier} [tier]
 */
export function paintCardFace(div, key, imgUrl, tier = 'base') {
  const cell = (tier === 'zoom' ? ZOOM_TABLE[key] : undefined) ?? CARD_TABLE[key];
  const sheet = cell === undefined ? undefined : SHEET_TABLE[cell.sheet];
  div.dataset.irKey = key;
  div.classList.add('ir-face');
  div.classList.toggle('ir-sprite-missing', cell === undefined || sheet === undefined);
  if (cell === undefined || sheet === undefined || sheet.cols === undefined || sheet.rows === undefined) {
    div.style.backgroundImage = '';
    return;
  }
  const bg = gridCellStyle(cell, { cols: sheet.cols, rows: sheet.rows });
  div.style.backgroundImage = `url(${imgUrl(sheet.file)})`;
  div.style.backgroundSize = bg.size;
  div.style.backgroundPosition = bg.position;
}

/**
 * 要素の背景に UI スプライト（トークン・ミープル・キューブ・鍵・アイコン）を描く。要素の縦横比は矩形の w : h に合わせること。
 * @param {HTMLElement} div
 * @param {string} key `token_light`・`meeple_red`・`key_wisdom` など
 * @param {ImgUrl} imgUrl
 */
export function paintUiSprite(div, key, imgUrl) {
  const rect = UI_TABLE[key];
  const sheet = rect === undefined ? undefined : SHEET_TABLE[rect.sheet];
  div.dataset.irUi = key;
  div.classList.add('ir-ui-sprite');
  div.classList.toggle('ir-sprite-missing', rect === undefined || sheet === undefined);
  if (rect === undefined || sheet === undefined) {
    div.style.backgroundImage = '';
    return;
  }
  const bg = rectStyle(rect, sheet);
  div.style.backgroundImage = `url(${imgUrl(sheet.file)})`;
  div.style.backgroundSize = bg.size;
  div.style.backgroundPosition = bg.position;
  div.style.aspectRatio = `${rect.w} / ${rect.h}`;
}

export { isPortraitKey };
