/**
 * ログ文の引数の整形（仕様書 009 §9、007 §10.2）。BGA の `bgaFormatText(log, args)` から呼ぶ純粋関数。
 * サーバーは `card_name` などを英語の文字列で送る（`i18n` つき）。ここでは、通知の引数に含まれる内部の値（カードの種類・自我の ID・
 * 目標の ID・トークンの種類）から、ログ上の表示を作り直す。
 *   - カード名（`card_name`・`ego_name`・`source_name`・`objective_name`）→ 「No.N 英語名」の `span.ir-log-card`（ホバー・長押しで説明）
 *   - 部屋名（`room_name`・`room_a`・`room_b`）→ `span.ir-log-room`（太字）
 *   - トークン名（`token_name`）→ アイコン + 名前
 * 置き換えた引数は HTML なので、`i18n` の一覧から外す（HTML は翻訳しない。名前は `texts.js` の `_()` で翻訳済み）。
 * 整形済みの印（`ir_formatted`）を付けるので、二重に呼ばれても結果は同じ。DOM にも BGA にも依存しない（アイコンの HTML は呼び出し側が渡す）。欠けた引数・壊れた引数でも例外を出さず、元のまま返す。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { memKey, objectiveKey } from '../board/view-model.js';
import { captionOf, keyKind } from './card-info-model.js';

/**
 * @typedef {object} LogContext
 * @property {import('./texts.js').CardTexts} texts
 * @property {(text: string) => string} translate 動的な文字列の翻訳（`_`）。原文はサーバーの `clienttranslate()` が抽出済み
 * @property {(icon: string) => string} iconHtml アイコン名（`ui/icon-names.js` の `ICONS` のキー）→ HTML
 */

/** N16 `delta` のキー → アイコン名 */
const TOKEN_ICONS = /** @type {Record<string, string>} */ ({
  light: 'light',
  wild_up: 'wildkey',
  wild_used: 'wildkey',
  compose: 'token_compose',
  analysis: 'token_analysis',
});

/** 部屋名の引数 */
const ROOM_FIELDS = ['room_name', 'room_a', 'room_b'];

/**
 * HTML の特殊文字を避ける。
 * @param {unknown} value
 * @returns {string}
 */
export function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] ?? c);
}

/**
 * 引数 → スプライトのキー（`M014` など）。分からなければ null。
 * @param {string} field
 * @param {Record<string, any>} args
 * @returns {string | null}
 */
export function cardKeyOf(field, args) {
  /** @type {unknown} */
  let key;
  switch (field) {
    case 'card_name':
      key = args.card !== null && typeof args.card === 'object' && args.card.type !== undefined && args.card.type !== null ? memKey(args.card.type) : null;
      break;
    case 'ego_name':
      key = args.ego_id;
      break;
    case 'source_name':
      key = args.source;
      break;
    case 'objective_name':
      key = typeof args.obj_id === 'number' ? objectiveKey(args.obj_id) : args.obj_id;
      break;
    default:
      return null;
  }
  return typeof key === 'string' && key !== 'M000' && keyKind(key) !== null ? key : null;
}

/**
 * ログの引数を整形する。引数は変更せず、コピーを返す。
 * @param {string} log
 * @param {unknown} args
 * @param {LogContext} ctx
 * @returns {{ log: string, args: any }}
 */
export function formatLogArgs(log, args, ctx) {
  if (args === null || typeof args !== 'object' || Array.isArray(args) || /** @type {any} */ (args).ir_formatted === true) {
    return { log, args };
  }
  /** @type {Record<string, any>} */
  const out = { ...args, ir_formatted: true };
  const replaced = new Set();

  for (const field of ['card_name', 'ego_name', 'source_name', 'objective_name']) {
    const key = cardKeyOf(field, out);
    if (key === null) {
      continue;
    }
    const name = captionOf(key, ctx.texts) ?? (typeof out[field] === 'string' ? ctx.translate(out[field]) : undefined);
    if (name !== undefined) {
      out[field] = `<span class="ir-log-card" data-ir-zoom-key="${key}">${escapeHtml(name)}</span>`;
      replaced.add(field);
    }
  }

  for (const field of ROOM_FIELDS) {
    if (typeof out[field] === 'string' && out[field] !== '') {
      out[field] = `<span class="ir-log-room">${escapeHtml(ctx.translate(out[field]))}</span>`;
      replaced.add(field);
    }
  }

  if (typeof out.token_name === 'string' && out.token_name !== '') {
    const kinds = Object.keys(out.delta !== null && typeof out.delta === 'object' ? out.delta : {});
    const icon = kinds.length === 1 ? TOKEN_ICONS[kinds[0]] : undefined;
    const name = escapeHtml(ctx.translate(out.token_name));
    out.token_name = `<span class="ir-log-token">${icon === undefined ? '' : `${ctx.iconHtml(icon)} `}${name}</span>`;
    replaced.add('token_name');
  }

  if (Array.isArray(out.i18n)) {
    out.i18n = out.i18n.filter((/** @type {unknown} */ field) => !replaced.has(field));
  }
  return { log, args: out };
}
