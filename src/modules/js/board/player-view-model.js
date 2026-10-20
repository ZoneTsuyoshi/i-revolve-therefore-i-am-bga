/**
 * プレイヤーの領域の表示モデル（純粋関数。仕様書 009 §3.7〜§3.11、007 §8.2）。DOM・bga-cards・スプライト表に依存しない。
 * getAllDatas の `myHand`・`myUnder`・`playerData[id]` と、通知の args を、描画しやすい形に直す。壊れた値でも例外を出さない。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { memKey, toInt } from './view-model.js';

/** 手札の上限（001 §12。獲得の途中では一時的に超える） */
export const HAND_LIMIT = 8;

/** トークンの種類。表示順と UI スプライトのキー、英語の名前 */
export const TOKEN_KINDS = /** @type {const} */ ([
  { kind: 'light', sprite: 'token_light', name: 'Inner light' },
  { kind: 'wildUp', sprite: 'token_wildkey', name: 'Wild move key' },
  { kind: 'wildUsed', sprite: 'token_wildkey_used', name: 'Wild move key (used)' },
  { kind: 'compose', sprite: 'token_compose', name: 'Self-composition token' },
  { kind: 'analysis', sprite: 'token_analysis', name: 'Psychoanalysis token' },
]);

/** N16 の `totals` のキー（snake_case）→ トークンの種類 */
const TOTALS_KEYS = /** @type {const} */ ({ light: 'light', wild_up: 'wildUp', wild_used: 'wildUsed', compose: 'compose', analysis: 'analysis' });

/**
 * @typedef {{ light: number, wildUp: number, wildUsed: number, compose: number, analysis: number }} Tokens
 */

/**
 * @typedef {object} HandCard bga-cards に渡す手札のカード
 * @property {number} id
 * @property {string} key スプライトのキー（`M014`）
 * @property {number} type カード番号（M 番号の数値部分）
 * @property {number} level
 * @property {number} energy 現在の自己構成エネルギー（M098 込み）
 * @property {number} printed 印刷のエネルギー
 */

/**
 * カタログの想い出カード → 種類ごとのレベルと印刷のエネルギー。
 * @param {unknown} catalog `gamedatas.catalog`
 * @returns {Map<number, { level: number, energy: number }>}
 */
export function memoryInfoTable(catalog) {
  const out = new Map();
  const list = /** @type {any} */ (catalog)?.memory;
  for (const row of Array.isArray(list) ? list : []) {
    const type = toInt(row?.type);
    if (type > 0) {
      out.set(type, { level: toInt(row?.level) === 2 ? 2 : 1, energy: toInt(row?.energy) });
    }
  }
  return out;
}

/**
 * 手札の並び順（レベル、エネルギー、カード番号の昇順。009 §3.10）。最後は ID で安定させる。
 * @param {{ id: unknown, type: number, level: number, energy: number }} a
 * @param {{ id: unknown, type: number, level: number, energy: number }} b
 * @returns {number}
 */
export function compareHand(a, b) {
  return (a.level - b.level) || (a.energy - b.energy) || (a.type - b.type) || (toInt(a.id) - toInt(b.id));
}

/**
 * 手札のカード 1 枚（種類つき）。レベルと印刷のエネルギーはカタログから、分からなければ 1 と 0。
 * @param {{ id: unknown, type: unknown, energy?: unknown }} ref
 * @param {Map<number, { level: number, energy: number }>} info
 * @returns {HandCard}
 */
export function handCard(ref, info) {
  const type = toInt(ref.type);
  const row = info.get(type);
  const printed = row?.energy ?? 0;
  return {
    id: toInt(ref.id),
    key: memKey(type),
    type,
    level: row?.level ?? 1,
    energy: ref.energy === undefined || ref.energy === null ? printed : toInt(ref.energy),
    printed,
  };
}

/**
 * getAllDatas の `myHand`（`[{id, type, energy}]`）→ 並べ替え済みの手札。
 * @param {unknown} myHand
 * @param {Map<number, { level: number, energy: number }>} info
 * @returns {HandCard[]}
 */
export function handModel(myHand, info) {
  const out = [];
  for (const row of Array.isArray(myHand) ? myHand : []) {
    if (row !== null && typeof row === 'object' && 'id' in row && 'type' in row) {
      out.push(handCard(row, info));
    }
  }
  return out.sort(compareHand);
}

/**
 * 手札の枚数の表示（本人: 上限との比。他人: 枚数のみで警告なし。009 §3.11）。
 * @param {number} count
 * @param {boolean} mine
 * @returns {{ count: number, over: boolean }}
 */
export function handCountState(count, mine) {
  const n = Math.max(0, toInt(count));
  return { count: n, over: mine && n > HAND_LIMIT };
}

/**
 * @typedef {object} ComposedEntry
 * @property {{ id: number, key: string }} card
 * @property {boolean} tapped 横向き（使用済み）
 * @property {number} underCount I08 の下のカードの枚数
 */

/**
 * `playerData[id].composed`（`[{id, type, tapped, underCount}]`）→ 自己構成した順の表示モデル。
 * @param {unknown} composed
 * @returns {ComposedEntry[]}
 */
export function composedModel(composed) {
  const out = [];
  for (const row of Array.isArray(composed) ? composed : []) {
    if (row === null || typeof row !== 'object' || !('id' in row) || !('type' in row)) {
      continue;
    }
    out.push({
      card: { id: toInt(row.id), key: memKey(row.type) },
      tapped: row.tapped === true || row.tapped === 1 || row.tapped === '1',
      underCount: Math.max(0, toInt(/** @type {any} */ (row).underCount)),
    });
  }
  return out;
}

/**
 * getAllDatas の `myUnder`（`[{host, cards: [{id, type}]}]`）→ 親ごとの下のカード。
 * @param {unknown} myUnder
 * @returns {{ host: number, cards: { id: number, key: string }[] }[]}
 */
export function underModel(myUnder) {
  const out = [];
  for (const row of Array.isArray(myUnder) ? myUnder : []) {
    const cards = [];
    for (const c of Array.isArray(row?.cards) ? row.cards : []) {
      if (c !== null && typeof c === 'object' && 'id' in c && 'type' in c) {
        cards.push({ id: toInt(c.id), key: memKey(c.type) });
      }
    }
    if (cards.length > 0) {
      out.push({ host: toInt(row?.host), cards });
    }
  }
  return out;
}

/**
 * `playerData[id].tokens`（キャメルケース）→ 整数に通したトークン。
 * @param {unknown} tokens
 * @returns {Tokens}
 */
export function tokensModel(tokens) {
  const t = /** @type {any} */ (tokens) ?? {};
  return {
    light: Math.max(0, toInt(t.light)),
    wildUp: Math.max(0, toInt(t.wildUp)),
    wildUsed: Math.max(0, toInt(t.wildUsed)),
    compose: Math.max(0, toInt(t.compose)),
    analysis: Math.max(0, toInt(t.analysis)),
  };
}

/**
 * N16 `tokensChanged` の `totals`（`{light, wild_up, wild_used, compose, analysis}` のうち変わったもの）を反映する。
 * 無いキーは今の値のまま。
 * @param {Tokens} current
 * @param {unknown} totals
 * @returns {Tokens}
 */
export function applyTotals(current, totals) {
  const next = { ...current };
  const t = /** @type {Record<string, unknown>} */ (totals !== null && typeof totals === 'object' ? totals : {});
  for (const [key, kind] of Object.entries(TOTALS_KEYS)) {
    if (t[key] !== undefined && t[key] !== null) {
      next[kind] = Math.max(0, toInt(t[key]));
    }
  }
  return next;
}

/**
 * クリーンアップで万能移動鍵が裏から表へ戻る（N29 `wild_restored`）。
 * @param {Tokens} current
 * @param {unknown} restored
 * @returns {Tokens}
 */
export function restoreWild(current, restored) {
  const n = Math.min(Math.max(0, toInt(restored)), current.wildUsed);
  return { ...current, wildUp: current.wildUp + n, wildUsed: current.wildUsed - n };
}

/**
 * 万能移動鍵の「3 + 1 used」の文言用に、表と裏を分けた数を返す。
 * @param {Tokens} tokens
 * @returns {{ up: number, used: number }}
 */
export function wildCounts(tokens) {
  return { up: tokens.wildUp, used: tokens.wildUsed };
}

/**
 * 通知の本人向けの値を取り出す。`_private` が合成済みの args のトップに来る場合と、`_private` の下に残る場合の両方に対応する。
 * @param {unknown} args
 * @param {string} key
 * @param {number | null} [me] 自分の ID（`_private[me]` を見る）
 * @returns {unknown} 無ければ undefined
 */
export function privateArg(args, key, me = null) {
  const a = /** @type {any} */ (args);
  if (a === null || typeof a !== 'object') {
    return undefined;
  }
  if (a[key] !== undefined) {
    return a[key];
  }
  const priv = a._private;
  if (priv === null || typeof priv !== 'object') {
    return undefined;
  }
  if (priv[key] !== undefined) {
    return priv[key];
  }
  return me === null ? undefined : priv[me]?.[key];
}

/**
 * ID の列（`card_ids` など）を整数の配列にする。
 * @param {unknown} ids
 * @returns {number[]}
 */
export function intList(ids) {
  return (Array.isArray(ids) ? ids : []).map(toInt);
}

/**
 * N35 `handEnergyUpdated` の `energies` → card_id → energy。
 * @param {unknown} energies
 * @returns {Map<number, number>}
 */
export function energyMap(energies) {
  const out = new Map();
  for (const row of Array.isArray(energies) ? energies : []) {
    if (row !== null && typeof row === 'object' && 'card_id' in row) {
      out.set(toInt(row.card_id), toInt(/** @type {any} */ (row).energy));
    }
  }
  return out;
}
