// 出力画像の検査（純粋関数。仕様書 009 §14.1・§14.4）。違反を文字列の配列で返す
export const MAX_BYTES = 2 * 1024 * 1024;

/** @typedef {{ format: string, width: number, height: number, hasAlpha: boolean, bytes: number, corners?: number[] | null }} ImageInfo */

/** 固定の寸法・形式の画像の要件 */
export const SPECS = {
  'box.png': { format: 'png', width: 280, height: 280, alpha: true },
  'icon.png': { format: 'png', width: 50, height: 50 },
  'title.png': { format: 'png', width: 2000, height: 2000, alpha: true, maxBytes: MAX_BYTES, transparentCorners: true },
  'banner.jpg': { format: 'jpeg', width: 1386, height: 400, maxBytes: MAX_BYTES },
};

/** Display は 1140 × 760 の JPEG で作る（公式の範囲は高さ 400〜760、幅は高さの 1.5 倍まで） */
export const DISPLAY_SIZE = { width: 1140, height: 760 };
export const DISPLAY_NAMES = ['display-1.jpg', 'display-2.jpg', 'display-3.jpg'];

/**
 * Display の寸法が公式の範囲に収まっているか
 * @param {number} width
 * @param {number} height
 */
export function displayDimensionErrors(width, height) {
  const errors = [];
  if (height < 400 || height > 760) errors.push(`高さ ${height} は 400〜760 の範囲外`);
  if (width > height * 1.5) errors.push(`幅 ${width} が高さの 1.5 倍（${height * 1.5}）を超える`);
  return errors;
}

/**
 * 1 枚の画像を要件と照合する
 * @param {string} name 出力ファイル名（box.png など）
 * @param {ImageInfo} info
 * @returns {string[]}
 */
export function checkImage(name, info) {
  const errors = [];
  const isDisplay = DISPLAY_NAMES.includes(name);
  const spec = isDisplay
    ? { format: 'jpeg', ...DISPLAY_SIZE, maxBytes: undefined, alpha: undefined, transparentCorners: undefined }
    : SPECS[/** @type {keyof typeof SPECS} */ (name)];
  if (spec === undefined) return [`${name}: 要件の定義がありません`];
  if (info.format !== spec.format) errors.push(`${name}: 形式が ${info.format}（${spec.format} が必要）`);
  if (info.width !== spec.width || info.height !== spec.height) {
    errors.push(`${name}: 寸法が ${info.width} × ${info.height}（${spec.width} × ${spec.height} が必要）`);
  }
  if (isDisplay) errors.push(...displayDimensionErrors(info.width, info.height).map((e) => `${name}: ${e}`));
  if (spec.alpha === true && !info.hasAlpha) errors.push(`${name}: アルファチャンネルがない（透過が必要）`);
  if (spec.maxBytes !== undefined && info.bytes > spec.maxBytes) {
    errors.push(`${name}: 容量 ${info.bytes} バイトが上限 ${spec.maxBytes} を超える`);
  }
  if (spec.transparentCorners === true) {
    if (!Array.isArray(info.corners) || info.corners.length !== 4) errors.push(`${name}: 四隅のアルファを測れていない`);
    else if (info.corners.some((a) => a !== 0)) errors.push(`${name}: 四隅が透明でない（${info.corners.join(', ')}）`);
  }
  return errors;
}

/**
 * RGBA の生データ（4 バイト / 画素）から四隅のアルファを取り出す（左上・右上・左下・右下）
 * @param {Uint8Array} raw
 * @param {number} width
 * @param {number} height
 */
export function cornerAlphas(raw, width, height) {
  const at = (/** @type {number} */ x, /** @type {number} */ y) => raw[(y * width + x) * 4 + 3];
  return [at(0, 0), at(width - 1, 0), at(0, height - 1), at(width - 1, height - 1)];
}
