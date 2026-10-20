/**
 * DOM を作る小さな補助。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/**
 * @param {string} tag
 * @param {{ id?: string, class?: string, data?: Record<string, string | number>, attrs?: Record<string, string> }} [opts]
 * @param {(Node | string)[]} [children]
 * @returns {HTMLElement}
 */
export function el(tag, opts = {}, children = []) {
  const node = document.createElement(tag);
  if (opts.id !== undefined) {
    node.id = opts.id;
  }
  if (opts.class !== undefined) {
    node.className = opts.class;
  }
  for (const [key, value] of Object.entries(opts.data ?? {})) {
    node.dataset[key] = String(value);
  }
  for (const [key, value] of Object.entries(opts.attrs ?? {})) {
    node.setAttribute(key, value);
  }
  node.append(...children);
  return node;
}

/**
 * ID・枚数などの数値は文字列で届くことがあるので、必ず整数に通す（仕様書 003 §6.1）。
 * @param {unknown} value
 * @returns {number} 整数にできなければ 0
 */
export function int(value) {
  const n = typeof value === 'number' ? value : parseInt(String(value), 10);
  return Number.isFinite(n) ? Math.trunc(n) : 0;
}
