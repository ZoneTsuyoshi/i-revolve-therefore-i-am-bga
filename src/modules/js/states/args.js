/**
 * 状態の args を読むための小さな補助（純粋関数）。形が崩れていても例外を出さない。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { int } from '../ui/dom.js';

/**
 * @param {unknown} value
 * @returns {Record<string, any>}
 */
export function obj(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value) ? /** @type {Record<string, any>} */ (value) : {};
}

/**
 * @param {unknown} value
 * @returns {any[]}
 */
export function list(value) {
  return Array.isArray(value) ? value : [];
}

/**
 * 自分にだけ届く `_private` の中身。`args._private[自分]` でも `args._private` 直下でもよい（Studio で確認する。U-3 と同じ）。
 * @param {unknown} args
 * @returns {Record<string, any>}
 */
export function privateOf(args) {
  const priv = obj(obj(args)._private);
  const keys = Object.keys(priv);
  if (keys.length > 0 && keys.every((k) => /^\d+$/.test(k))) {
    return obj(priv[keys[0]]);
  }
  return priv;
}

/**
 * 整数の配列にする（整数でないものは除く）。
 * @param {unknown} value
 * @returns {number[]}
 */
export function intList(value) {
  return list(value).map(int);
}
