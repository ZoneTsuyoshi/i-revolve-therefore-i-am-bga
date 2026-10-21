/**
 * プレイヤーパネルの表示モデル（純粋関数。仕様書 009 §6、007 §8.2・§10.2）。DOM・スプライト表に依存しない。壊れた値でも例外を出さない。
 * 自己構成スコアは getAllDatas の `playerData[].score` と N21 `scoreUpdated`、鍵の本数は自己構成済みカードの種類とカタログの `keys` から派生する。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { tokensModel } from './player-view-model.js';
import { toInt } from './view-model.js';

/** 断片の結集（自己構成スコア 20 以上で勝利。001 §15） */
export const WIN_SCORE = 20;

/** 鍵の種類（表示順）、UI スプライトのキー（名前の翻訳は PlayerPanels の `keyTitle`） */
export const KEY_KINDS = /** @type {const} */ ([
  { kind: 'WISDOM', sprite: 'key_wisdom' },
  { kind: 'WILL', sprite: 'key_will' },
  { kind: 'EMOTION', sprite: 'key_emotion' },
  { kind: 'WILD', sprite: 'key_wild' },
]);

/** @typedef {{ WISDOM: number, WILL: number, EMOTION: number, WILD: number }} KeyCounts */

/**
 * @typedef {object} PanelState 1 人分のパネルの値
 * @property {number} score 自己構成スコア
 * @property {number} handCount
 * @property {import('./player-view-model.js').Tokens} tokens
 * @property {number} tier 0〜5
 * @property {Map<number, number>} composed 自己構成済みカードの ID → 種類
 */

/**
 * カタログの想い出カード → 種類ごとの鍵の列（`['WISDOM', 'WILD']` など）。
 * @param {unknown} catalog `gamedatas.catalog`
 * @returns {Map<number, string[]>}
 */
export function keyTable(catalog) {
  const out = new Map();
  const list = /** @type {any} */ (catalog)?.memory;
  for (const row of Array.isArray(list) ? list : []) {
    const type = toInt(row?.type);
    if (type > 0) {
      out.set(type, Array.isArray(row?.keys) ? row.keys.filter((/** @type {unknown} */ k) => typeof k === 'string') : []);
    }
  }
  return out;
}

/**
 * 自己構成済みカードの鍵の本数。カタログに無い種類は 0 本。
 * @param {Map<number, number>} composed ID → 種類
 * @param {Map<number, string[]>} table
 * @returns {KeyCounts}
 */
export function keyCounts(composed, table) {
  const out = { WISDOM: 0, WILL: 0, EMOTION: 0, WILD: 0 };
  for (const type of composed.values()) {
    for (const key of table.get(type) ?? []) {
      if (key in out) {
        out[/** @type {keyof KeyCounts} */ (key)]++;
      }
    }
  }
  return out;
}

/**
 * playerData の `composed`（`[{id, type, ...}]`）→ ID → 種類。
 * @param {unknown} composed
 * @returns {Map<number, number>}
 */
export function composedMap(composed) {
  const out = new Map();
  for (const row of Array.isArray(composed) ? composed : []) {
    if (row !== null && typeof row === 'object' && 'id' in row && 'type' in row) {
      out.set(toInt(row.id), toInt(row.type));
    }
  }
  return out;
}

/**
 * playerData[id] → パネルの初期値。
 * @param {unknown} data
 * @returns {PanelState}
 */
export function panelState(data) {
  const d = /** @type {any} */ (data) ?? {};
  return {
    score: Math.max(0, toInt(d.score)),
    handCount: Math.max(0, toInt(d.handCount)),
    tokens: tokensModel(d.tokens),
    tier: clampTier(d.tier),
    composed: composedMap(d.composed),
  };
}

/**
 * @param {unknown} tier
 * @returns {number} 0〜5
 */
export function clampTier(tier) {
  return Math.min(5, Math.max(0, toInt(tier)));
}

/**
 * N21 `scoreUpdated` の `scores`（`{player_id: {total, memory, objective}}`）→ プレイヤー ID → 合計。
 * @param {unknown} scores
 * @returns {Map<number, number>}
 */
export function scoresFromArgs(scores) {
  const out = new Map();
  if (scores !== null && typeof scores === 'object') {
    for (const [id, row] of Object.entries(scores)) {
      const total = /** @type {any} */ (row)?.total;
      if (total !== undefined && total !== null) {
        out.set(toInt(id), Math.max(0, toInt(total)));
      }
    }
  }
  return out;
}

/**
 * 自己構成済みの増減（N18 の追加、N26 TABLEAU_HAND の入れ替え）を反映した新しい Map。
 * @param {Map<number, number>} composed
 * @param {{ added?: { id: unknown, type: unknown }[], removed?: unknown[] }} change
 * @returns {Map<number, number>}
 */
export function applyComposedChange(composed, change) {
  const next = new Map(composed);
  for (const id of change.removed ?? []) {
    next.delete(toInt(id));
  }
  for (const card of change.added ?? []) {
    if (card !== null && typeof card === 'object' && toInt(card.id) > 0) {
      next.set(toInt(card.id), toInt(card.type));
    }
  }
  return next;
}

/**
 * N26 の `pairs`（`[{out, in}]`。TABLEAU_HAND では `in` が手札へ、`out` が自己構成済みへ）→ 自己構成済みの増減。
 * @param {unknown} pairs
 * @returns {{ added: { id: unknown, type: unknown }[], removed: unknown[] }}
 */
export function exchangeChange(pairs) {
  const added = [];
  const removed = [];
  for (const pair of Array.isArray(pairs) ? pairs : []) {
    if (pair?.in?.id !== undefined) {
      removed.push(pair.in.id);
    }
    if (pair?.out?.id !== undefined && pair.out.type !== undefined) {
      added.push(pair.out);
    }
  }
  return { added, removed };
}

/** @param {number} score */
export const scoreText = (score) => `${Math.max(0, toInt(score))} / ${WIN_SCORE}`;

/**
 * 万能移動鍵の「表 + 使用済み」。
 * @param {{ wildUp: number, wildUsed: number }} tokens
 */
export const wildText = (tokens) => `${tokens.wildUp} + ${tokens.wildUsed}`;

/** @param {number} tier */
export const tierText = (tier) => `Tier ${clampTier(tier)}`;
