/**
 * スプライトの背景指定の計算（仕様書 009 §10.1）。DOM にも生成物 `ui/sprites.js` にも依存しない純粋関数。
 * `background-size` と `background-position` はパーセントで統一する（px との混在は BGA の拡縮で Safari が数 px ずれる）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/**
 * @typedef {{ cols: number, rows: number }} GridSheet 等間隔のセルのシート
 * @typedef {{ col: number, row: number }} GridCell
 * @typedef {{ w: number, h: number }} SheetSize
 * @typedef {{ x: number, y: number, w: number, h: number }} Rect UI スプライトの矩形
 * @typedef {{ size: string, position: string }} BackgroundStyle
 */

/**
 * @param {number} index 0 始まりの位置
 * @param {number} count 個数
 * @returns {number} 0〜100（個数が 1 以下なら 0）
 */
function percentAt(index, count) {
  return count > 1 ? (index / (count - 1)) * 100 : 0;
}

/**
 * 小数 3 桁に丸めて文字列にする（"33.333%"）。
 * @param {number} value
 * @returns {string}
 */
function pct(value) {
  return `${Math.round(value * 1000) / 1000}%`;
}

/**
 * 等間隔のグリッドのセル 1 枚を、要素いっぱいに表示する背景指定。
 * @param {GridCell} cell
 * @param {GridSheet} sheet
 * @returns {BackgroundStyle}
 */
export function gridCellStyle(cell, sheet) {
  return {
    size: `${pct(sheet.cols * 100)} ${pct(sheet.rows * 100)}`,
    position: `${pct(percentAt(cell.col, sheet.cols))} ${pct(percentAt(cell.row, sheet.rows))}`,
  };
}

/**
 * シートの任意の矩形（UI スプライト）を、要素いっぱいに表示する背景指定。
 * @param {Rect} rect
 * @param {SheetSize} sheet
 * @returns {BackgroundStyle}
 */
export function rectStyle(rect, sheet) {
  const xRange = sheet.w - rect.w;
  const yRange = sheet.h - rect.h;
  return {
    size: `${pct((sheet.w / rect.w) * 100)} ${pct((sheet.h / rect.h) * 100)}`,
    position: `${pct(xRange > 0 ? (rect.x / xRange) * 100 : 0)} ${pct(yRange > 0 ? (rect.y / yRange) * 100 : 0)}`,
  };
}

/**
 * スプライトのキーが縦長（想い出カード。表 `M001`〜、裏 `Rev1B` など）か。それ以外は横長（床・精神・自我・目標）。
 * @param {string} key
 * @returns {boolean}
 */
export function isPortraitKey(key) {
  return /^M\d/.test(key) || /^Rev\d/.test(key);
}
