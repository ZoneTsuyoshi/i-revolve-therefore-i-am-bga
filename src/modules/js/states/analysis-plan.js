/**
 * 精神分析（状態 62）の支払いの純粋関数（仕様書 009 §4、006 §5.4、001 §10）。DOM もフレームワークも使わない。
 * サーバーの `Flow/AnalysisPlanner` の規則（進む先、コストの軽減で取り除く鍵、鍵 1 つずつの支払い、心光の逓増）を、
 * 画面で払えない選択肢を選べなくするために仮検証する。最終的な検証はサーバー。
 *
 * 支払い（`actAnalyze` の `payment`）:
 * `{"removed": {"WISDOM": 1}, "keys": [{"src": "TABLEAU"|"HAND"|"LIGHT", "card": ID, "key": 鍵の番号, "for": "WISDOM"}...]}`
 * - removed: 軽減で取り除くコストの鍵。合計は `min(reduction, コストの総数)` ちょうど
 * - keys: 残りのコストの鍵 1 つずつ。`for` は払うコストの種類。残りのコストをちょうど覆う（過払い不可）
 * - TABLEAU は縦向きの自己構成済みカードを横向きにし、HAND は手札を捨てて、そのカードの `key` 番目（印刷順）の鍵を使う
 * - LIGHT は心光の行使 1 回（X 回目は X 個）。万能移動鍵トークンは使えない
 * 万能鍵は何の代わりにもなる。M094 などで知恵鍵を万能鍵として見るときは、args の `keyServes`（`{持っている鍵: [払える錠前]}`）が必要。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { int } from '../ui/dom.js';
import { list, obj, privateOf } from './args.js';

/** 支払いの src */
export const SRC = { TABLEAU: 'TABLEAU', HAND: 'HAND', LIGHT: 'LIGHT' };

/** 鍵の種類 */
export const KEYS = ['WISDOM', 'WILL', 'EMOTION'];
const WILD = 'WILD';

/** 拒否の理由（サーバーの文言の要点） */
export const REASON = {
  DESTINATION: 'DESTINATION',
  REMOVED: 'REMOVED',
  SOURCE: 'SOURCE',
  CARD: 'CARD',
  KEY_INDEX: 'KEY_INDEX',
  KEY_TWICE: 'KEY_TWICE',
  KEY_MISMATCH: 'KEY_MISMATCH',
  COST: 'COST',
  LIGHT: 'LIGHT',
};

/**
 * @typedef {object} AnalysisCard
 * @property {number} id
 * @property {string[]} keys 印刷順の鍵
 * @property {boolean} tapped
 */

/**
 * @typedef {object} Destination
 * @property {string} id 精神カード（`R3A`）
 * @property {number} tier
 * @property {Record<string, number>} cost 鍵の種類 → 本数
 */

/**
 * @typedef {object} AnalysisBoard
 * @property {string} context FLOOR / TOKEN
 * @property {number} reduction 軽減できる鍵の個数
 * @property {Destination[]} destinations
 * @property {Map<number, AnalysisCard>} tableau
 * @property {Map<number, AnalysisCard>} hand
 * @property {number} light
 * @property {number[]} lightTotals
 * @property {number} maxLightUses
 * @property {boolean} canAnalyze
 * @property {Record<string, string[]>} keyServes 持っている鍵 → 払える錠前
 */

/**
 * @typedef {object} KeyPay
 * @property {string} src SRC のどれか
 * @property {number} [card]
 * @property {number} [key]
 * @property {string} for 払うコストの鍵
 */

/**
 * @typedef {object} AnalysisState
 * @property {string | null} destination
 * @property {Record<string, number>} removed
 * @property {KeyPay[]} keys
 */

/** 既定の「持っている鍵 → 払える錠前」（万能は何にでも） */
const DEFAULT_SERVES = { WISDOM: ['WISDOM'], WILL: ['WILL'], EMOTION: ['EMOTION'], WILD: KEYS };

/**
 * @param {unknown} raw
 * @param {boolean} tableau
 * @returns {AnalysisCard}
 */
function cardOf(raw, tableau) {
  const c = obj(raw);
  return { id: int(c.id), keys: list(c.keys).map(String), tapped: tableau && c.tapped === true };
}

/**
 * 状態 62 の args → 計画用の盤。
 * @param {unknown} args
 * @returns {AnalysisBoard}
 */
export function analysisFromArgs(args) {
  const a = obj(args);
  const priv = privateOf(args);
  /** @type {Map<number, AnalysisCard>} */
  const tableau = new Map();
  for (const raw of list(a.tableau)) {
    const card = cardOf(raw, true);
    tableau.set(card.id, card);
  }
  /** @type {Map<number, AnalysisCard>} */
  const hand = new Map();
  for (const raw of list(priv.hand)) {
    const card = cardOf(raw, false);
    hand.set(card.id, card);
  }
  const destinations = list(a.destinations).map((raw) => {
    const d = obj(raw);
    /** @type {Record<string, number>} */
    const cost = {};
    for (const [key, count] of Object.entries(obj(d.cost))) {
      if (int(count) > 0) {
        cost[key] = int(count);
      }
    }
    return { id: String(d.id ?? ''), tier: int(d.tier), cost };
  });
  /** @type {Record<string, string[]>} */
  const keyServes = { ...DEFAULT_SERVES };
  for (const [held, locks] of Object.entries(obj(a.keyServes))) {
    keyServes[held] = list(locks).map(String);
  }
  return {
    context: typeof a.context === 'string' ? a.context : 'FLOOR',
    reduction: Math.max(0, int(a.reduction)),
    destinations,
    tableau,
    hand,
    light: Math.max(0, int(a.light)),
    lightTotals: list(a.lightTotals).map(int),
    maxLightUses: Math.max(0, int(a.maxLightUses)),
    canAnalyze: a.canAnalyze === true,
    keyServes,
  };
}

/** @returns {AnalysisState} */
export function emptyState() {
  return { destination: null, removed: {}, keys: [] };
}

/**
 * @param {AnalysisBoard} board
 * @param {string | null} id
 * @returns {Destination | undefined}
 */
export function destinationOf(board, id) {
  return board.destinations.find((d) => d.id === id);
}

/**
 * @param {Record<string, number>} counts
 * @returns {number}
 */
function total(counts) {
  return Object.values(counts).reduce((sum, n) => sum + n, 0);
}

/**
 * 取り除く鍵の個数（`min(reduction, コストの総数)`）。
 * @param {AnalysisBoard} board
 * @param {Destination} destination
 * @returns {number}
 */
export function removalCount(board, destination) {
  return Math.min(board.reduction, total(destination.cost));
}

/**
 * まだ取り除ける鍵の種類（コストに残っているもの）。取り除く個数に達していれば空。
 * @param {AnalysisBoard} board
 * @param {AnalysisState} state
 * @returns {string[]}
 */
export function removableKeys(board, state) {
  const destination = destinationOf(board, state.destination);
  if (destination === undefined || total(state.removed) >= removalCount(board, destination)) {
    return [];
  }
  return Object.keys(destination.cost).filter((key) => destination.cost[key] - (state.removed[key] ?? 0) > 0);
}

/**
 * 軽減の選択が済んだか。
 * @param {AnalysisBoard} board
 * @param {AnalysisState} state
 * @returns {boolean}
 */
export function removalDone(board, state) {
  const destination = destinationOf(board, state.destination);
  return destination !== undefined && total(state.removed) === removalCount(board, destination);
}

/**
 * 軽減後のコスト（0 本の鍵は含めない）。
 * @param {Destination} destination
 * @param {Record<string, number>} removed
 * @returns {Record<string, number>}
 */
export function remainingCost(destination, removed) {
  /** @type {Record<string, number>} */
  const out = {};
  for (const [key, count] of Object.entries(destination.cost)) {
    const left = count - (removed[key] ?? 0);
    if (left > 0) {
      out[key] = left;
    }
  }
  return out;
}

/**
 * まだ払っていないコスト（軽減の選択が済んでいるとき）。
 * @param {AnalysisBoard} board
 * @param {AnalysisState} state
 * @returns {Record<string, number>}
 */
export function unpaid(board, state) {
  const destination = destinationOf(board, state.destination);
  if (destination === undefined) {
    return {};
  }
  const left = remainingCost(destination, state.removed);
  for (const pay of state.keys) {
    if (left[pay.for] !== undefined) {
      left[pay.for] -= 1;
      if (left[pay.for] <= 0) {
        delete left[pay.for];
      }
    }
  }
  return left;
}

/**
 * 持っている鍵 `held` で、錠前 `needed` を払えるか。
 * @param {AnalysisBoard} board
 * @param {string} held
 * @param {string} needed
 * @returns {boolean}
 */
export function serves(board, held, needed) {
  return held === WILD || held === needed || (board.keyServes[held] ?? []).includes(needed);
}

/**
 * 心光の行使の回数。
 * @param {AnalysisState} state
 * @returns {number}
 */
export function lightUses(state) {
  return state.keys.filter((k) => k.src === SRC.LIGHT).length;
}

/**
 * 心光を `uses` 回行使するときの心光の総数。
 * @param {AnalysisBoard} board
 * @param {number} uses
 * @returns {number}
 */
export function lightCost(board, uses) {
  return uses <= 0 ? 0 : (board.lightTotals[uses - 1] ?? Infinity);
}

/**
 * @typedef {object} PayChoice
 * @property {string} src TABLEAU / HAND
 * @property {number} card
 * @property {number} key 鍵の番号
 * @property {string} held 持っている鍵
 * @property {string} for 払うコストの鍵
 */

/**
 * 使える鍵ごとに、払えるコストの種類（まだ払っていないもの）を数えた支払いの選択肢。
 * 縦向きの自己構成済みカードと手札の、まだ使っていない鍵が対象。
 * @param {AnalysisBoard} board
 * @param {AnalysisState} state
 * @returns {PayChoice[]}
 */
export function payChoices(board, state) {
  if (!removalDone(board, state)) {
    return [];
  }
  const left = unpaid(board, state);
  const used = new Set(state.keys.filter((k) => k.src !== SRC.LIGHT).map((k) => `${k.src}:${k.card}:${k.key}`));
  /** @type {PayChoice[]} */
  const out = [];
  for (const [src, cards] of /** @type {const} */ ([[SRC.TABLEAU, board.tableau], [SRC.HAND, board.hand]])) {
    for (const card of cards.values()) {
      if (card.tapped) {
        continue;
      }
      card.keys.forEach((held, key) => {
        if (used.has(`${src}:${card.id}:${key}`)) {
          return;
        }
        for (const need of Object.keys(left)) {
          if (serves(board, held, need)) {
            out.push({ src, card: card.id, key, held, for: need });
          }
        }
      });
    }
  }
  return out;
}

/**
 * 心光で払えるコストの種類（もう 1 回行使できるとき）。
 * @param {AnalysisBoard} board
 * @param {AnalysisState} state
 * @returns {string[]}
 */
export function lightChoices(board, state) {
  if (!removalDone(board, state)) {
    return [];
  }
  const next = lightUses(state) + 1;
  if (next > board.maxLightUses || lightCost(board, next) > board.light) {
    return [];
  }
  return Object.keys(unpaid(board, state));
}

/**
 * 全部払えて、送れる状態か。
 * @param {AnalysisBoard} board
 * @param {AnalysisState} state
 * @returns {boolean}
 */
export function isComplete(board, state) {
  return state.destination !== null && removalDone(board, state) && Object.keys(unpaid(board, state)).length === 0;
}

/**
 * 支払いを検証する（`AnalysisPlanner::plan` と同じ順）。
 * @param {AnalysisBoard} board
 * @param {AnalysisState} state
 * @returns {{ ok: true } | { ok: false, reason: string }}
 */
export function checkAnalysis(board, state) {
  const destination = destinationOf(board, state.destination);
  if (destination === undefined) {
    return { ok: false, reason: REASON.DESTINATION };
  }
  const expected = removalCount(board, destination);
  let sum = 0;
  for (const [key, count] of Object.entries(state.removed)) {
    if (count < 0 || count > (destination.cost[key] ?? 0)) {
      return { ok: false, reason: REASON.REMOVED };
    }
    sum += count;
  }
  if (sum !== expected) {
    return { ok: false, reason: REASON.REMOVED };
  }
  const remaining = remainingCost(destination, state.removed);
  /** @type {Record<string, number>} */
  const paid = {};
  const used = new Set();
  for (const pay of state.keys) {
    if (!KEYS.includes(pay.for)) {
      return { ok: false, reason: REASON.SOURCE };
    }
    paid[pay.for] = (paid[pay.for] ?? 0) + 1;
    if (pay.src === SRC.LIGHT) {
      continue;
    }
    if (pay.src !== SRC.TABLEAU && pay.src !== SRC.HAND) {
      return { ok: false, reason: REASON.SOURCE };
    }
    const card = (pay.src === SRC.TABLEAU ? board.tableau : board.hand).get(pay.card ?? -1);
    if (card === undefined || card.tapped) {
      return { ok: false, reason: REASON.CARD };
    }
    const held = card.keys[pay.key ?? -1];
    if (held === undefined) {
      return { ok: false, reason: REASON.KEY_INDEX };
    }
    const slot = `${pay.src}:${pay.card}:${pay.key}`;
    if (used.has(slot)) {
      return { ok: false, reason: REASON.KEY_TWICE };
    }
    used.add(slot);
    if (!serves(board, held, pay.for)) {
      return { ok: false, reason: REASON.KEY_MISMATCH };
    }
  }
  const keys = new Set([...Object.keys(paid), ...Object.keys(remaining)]);
  for (const key of keys) {
    if ((paid[key] ?? 0) !== (remaining[key] ?? 0)) {
      return { ok: false, reason: REASON.COST };
    }
  }
  if (lightCost(board, lightUses(state)) > board.light) {
    return { ok: false, reason: REASON.LIGHT };
  }
  return { ok: true };
}

/**
 * 鍵（供給）とコストの鍵（スロット）の最大マッチング数（`AnalysisPlanner::maxMatching`）。
 * @param {AnalysisBoard} board
 * @param {string[]} supplies 持っている鍵
 * @param {string[]} slots コストの鍵
 * @returns {number}
 */
export function maxMatching(board, supplies, slots) {
  /** @type {Map<number, number>} 供給の番号 → 割り当て済みのスロット */
  const owner = new Map();
  /**
   * @param {number} slot
   * @param {Set<number>} visited
   * @returns {boolean}
   */
  const augment = (slot, visited) => {
    for (let i = 0; i < supplies.length; i++) {
      if (visited.has(i) || !serves(board, supplies[i], slots[slot])) {
        continue;
      }
      visited.add(i);
      const current = owner.get(i);
      if (current === undefined || augment(current, visited)) {
        owner.set(i, slot);
        return true;
      }
    }
    return false;
  };
  let matched = 0;
  for (let slot = 0; slot < slots.length; slot++) {
    if (augment(slot, new Set())) {
      matched++;
    }
  }
  return matched;
}

/**
 * 持っている縦向きの自己構成済みカードと手札の鍵の一覧。
 * @param {AnalysisBoard} board
 * @returns {string[]}
 */
function supplyKeys(board) {
  /** @type {string[]} */
  const out = [];
  for (const card of board.tableau.values()) {
    if (!card.tapped) {
      out.push(...card.keys);
    }
  }
  for (const card of board.hand.values()) {
    out.push(...card.keys);
  }
  return out;
}

/**
 * コストの鍵を `count` 個取り除くすべての方法（取り除いた後の残りではなく、取り除く本数）。
 * @param {Record<string, number>} cost
 * @param {number} count
 * @returns {Record<string, number>[]}
 */
export function removals(cost, count) {
  const keys = Object.keys(cost);
  /** @type {Record<string, number>[]} */
  const results = [];
  /**
   * @param {number} i
   * @param {number} leftToTake
   * @param {Record<string, number>} current
   */
  const recurse = (i, leftToTake, current) => {
    if (i === keys.length) {
      if (leftToTake === 0) {
        results.push(current);
      }
      return;
    }
    for (let take = 0; take <= Math.min(leftToTake, cost[keys[i]]); take++) {
      recurse(i + 1, leftToTake - take, take > 0 ? { ...current, [keys[i]]: take } : current);
    }
  };
  recurse(0, count, {});
  return results;
}

/**
 * 進む先 `destination` を、軽減のしかたを選べるとして払えるか（払えない先は選べなくする）。
 * @param {AnalysisBoard} board
 * @param {Destination} destination
 * @returns {boolean}
 */
export function canPay(board, destination) {
  const supplies = supplyKeys(board);
  for (const removed of removals(destination.cost, removalCount(board, destination))) {
    const slots = [];
    for (const [key, count] of Object.entries(remainingCost(destination, removed))) {
      for (let i = 0; i < count; i++) {
        slots.push(key);
      }
    }
    if (slots.length - maxMatching(board, supplies, slots) <= board.maxLightUses) {
      return true;
    }
  }
  return false;
}

/**
 * `actAnalyze` の `payment`（JSON にして送る）。
 * @param {AnalysisState} state
 * @returns {{ removed: Record<string, number>, keys: KeyPay[] }}
 */
export function toPayment(state) {
  /** @type {Record<string, number>} */
  const removed = {};
  for (const [key, count] of Object.entries(state.removed)) {
    if (count > 0) {
      removed[key] = count;
    }
  }
  return { removed, keys: state.keys.map((k) => (k.src === SRC.LIGHT ? { src: k.src, for: k.for } : { ...k })) };
}

/**
 * 軽減の鍵を 1 つ取り除く。
 * @param {AnalysisState} state
 * @param {string} key
 * @returns {AnalysisState}
 */
export function removeKey(state, key) {
  return { ...state, removed: { ...state.removed, [key]: (state.removed[key] ?? 0) + 1 }, keys: [] };
}

/**
 * 鍵の支払いを 1 つ足す。
 * @param {AnalysisState} state
 * @param {KeyPay} pay
 * @returns {AnalysisState}
 */
export function addPay(state, pay) {
  return { ...state, keys: [...state.keys, pay] };
}

/**
 * 最後の支払いを取り消す。支払いが無ければ最後の軽減を、それも無ければ進む先を取り消す。
 * @param {AnalysisState} state
 * @returns {AnalysisState}
 */
export function undo(state) {
  if (state.keys.length > 0) {
    return { ...state, keys: state.keys.slice(0, -1) };
  }
  const removedKeys = Object.keys(state.removed).filter((k) => state.removed[k] > 0);
  if (removedKeys.length > 0) {
    const last = removedKeys[removedKeys.length - 1];
    return { ...state, removed: { ...state.removed, [last]: state.removed[last] - 1 } };
  }
  return { ...state, destination: null };
}

// ---- まとめて選んだ資源の自動割り当て ----

/**
 * @typedef {object} AnalysisSelection
 * @property {Iterable<number>} [tableau] 選んだ自己構成済みカード（縦向き）
 * @property {Iterable<number>} [hand] 選んだ手札
 * @property {number} [light] 心光の行使の回数（上限）
 */

/**
 * @typedef {object} AnalysisSolution
 * @property {AnalysisState} state 割り当ての結果（complete が false なら送れない）
 * @property {boolean} complete コストをちょうど払い、仮検証（checkAnalysis）も通る
 * @property {number} total 払うべきコストの鍵の総数（軽減のあと）
 * @property {number} paid 払えた鍵の数
 * @property {Record<string, number>} needed 払えていない鍵の種類 → 個数
 * @property {{ tableau: number[], hand: number[], light: number }} unused 使われなかった選択
 * @property {boolean} aborted 探索の上限に達した
 */

const SEARCH_LIMIT = 300000;
const HOLE_WEIGHT = 1000000;
const UNUSED_WEIGHT = 1000;
/** 資源の「高価さ」（自己構成済み < 手札 < 心光） */
/** @type {Record<string, number>} */
const COST = { TABLEAU: 1, HAND: 3, LIGHT: 4 };

/**
 * 選んだ進む先のコストを、選んだ資源で払う割り当てを探す（全探索）。軽減で取り除く鍵もすべての選び方を試す。
 * 優先順位: 払えない鍵が少ない → 選んだのに使わない資源が少ない → 高価な資源（心光・手札）を使わない。
 * 万能鍵・M094 の読み替えは `serves` の規則どおり。結果は `checkAnalysis` に通して二重に確かめる。
 * @param {AnalysisBoard} board
 * @param {string | null} destinationId
 * @param {AnalysisSelection} selection
 * @param {{ feasibleOnly?: boolean }} [options] feasibleOnly: 払い切れる割り当てが見つかった時点で打ち切る
 * @returns {AnalysisSolution}
 */
export function solveAnalysisPayment(board, destinationId, selection, { feasibleOnly = false } = {}) {
  const destination = destinationOf(board, destinationId);
  const lightMax = Math.min(Math.max(0, Math.floor(selection.light ?? 0)), board.maxLightUses);
  const tableauIds = [...new Set(selection.tableau ?? [])].filter((id) => board.tableau.get(id)?.tapped === false);
  const handIds = [...new Set(selection.hand ?? [])].filter((id) => board.hand.has(id));
  const unusedAll = { tableau: tableauIds, hand: handIds, light: lightMax };
  if (destination === undefined) {
    return { state: emptyState(), complete: false, total: 0, paid: 0, needed: {}, unused: unusedAll, aborted: false };
  }
  /** @type {{ src: string, card: number, key: number, held: string }[]} */
  const supplies = [];
  for (const [src, ids, cards] of /** @type {const} */ ([[SRC.TABLEAU, tableauIds, board.tableau], [SRC.HAND, handIds, board.hand]])) {
    for (const id of ids) {
      cards.get(id)?.keys.forEach((held, key) => supplies.push({ src, card: id, key, held }));
    }
  }
  const selectedCardCount = tableauIds.length + handIds.length;
  let aborted = false;
  let nodes = 0;
  let bestScore = Infinity;
  /** @type {{ removed: Record<string, number>, picks: (KeyPay | null)[], slots: string[] } | null} */
  let best = null;
  let done = false;

  for (const removed of removals(destination.cost, removalCount(board, destination))) {
    /** @type {string[]} */
    const slots = [];
    for (const [key, count] of Object.entries(remainingCost(destination, removed))) {
      for (let i = 0; i < count; i++) {
        slots.push(key);
      }
    }
    const used = supplies.map(() => false);
    const picks = slots.map(() => /** @type {KeyPay | null} */ (null));
    /** @type {Map<string, number>} */
    const cardCount = new Map();

    /**
     * @param {number} i
     * @param {number} lights 行使の回数
     * @param {number} cost
     * @param {number} holes
     */
    const search = (i, lights, cost, holes) => {
      if (done || aborted || cost + holes * HOLE_WEIGHT >= bestScore) {
        return;
      }
      if (++nodes > SEARCH_LIMIT) {
        aborted = true;
        return;
      }
      if (i === slots.length) {
        const unusedCount = (selectedCardCount - cardCount.size) + (lightMax - lights);
        const score = holes * HOLE_WEIGHT + unusedCount * UNUSED_WEIGHT + cost;
        if (score < bestScore) {
          bestScore = score;
          best = { removed, picks: picks.slice(), slots };
          done = feasibleOnly && holes === 0;
        }
        return;
      }
      /** @type {Set<string>} */
      const tried = new Set();
      for (let s = 0; s < supplies.length; s++) {
        const sup = supplies[s];
        const signature = `${sup.src}:${sup.card}:${sup.held}`;
        if (used[s] || tried.has(signature) || !serves(board, sup.held, slots[i])) {
          continue;
        }
        tried.add(signature);
        const cardKey = `${sup.src}:${sup.card}`;
        const firstUse = !cardCount.has(cardKey);
        used[s] = true;
        cardCount.set(cardKey, (cardCount.get(cardKey) ?? 0) + 1);
        picks[i] = { src: sup.src, card: sup.card, key: sup.key, for: slots[i] };
        search(i + 1, lights, cost + (firstUse ? COST[sup.src] : 0), holes);
        used[s] = false;
        picks[i] = null;
        if (firstUse) {
          cardCount.delete(cardKey);
        } else {
          cardCount.set(cardKey, /** @type {number} */ (cardCount.get(cardKey)) - 1);
        }
      }
      if (lights < lightMax && lightCost(board, lights + 1) <= board.light) {
        picks[i] = { src: SRC.LIGHT, for: slots[i] };
        search(i + 1, lights + 1, cost + COST.LIGHT, holes);
        picks[i] = null;
      }
      if (!feasibleOnly) {
        search(i + 1, lights, cost, holes + 1);
      }
    };
    search(0, 0, 0, 0);
  }

  const found = /** @type {{ removed: Record<string, number>, picks: (KeyPay | null)[], slots: string[] } | null} */ (best);
  if (found === null) {
    const first = removals(destination.cost, removalCount(board, destination))[0] ?? {};
    const slots = Object.entries(remainingCost(destination, first)).flatMap(([key, count]) => Array.from({ length: count }, () => key));
    /** @type {Record<string, number>} */
    const needed = {};
    for (const key of slots) {
      needed[key] = (needed[key] ?? 0) + 1;
    }
    return { state: { destination: destination.id, removed: first, keys: [] }, complete: false, total: slots.length, paid: 0, needed, unused: unusedAll, aborted };
  }
  const keys = found.picks.filter((p) => p !== null);
  /** @type {Record<string, number>} */
  const needed = {};
  found.picks.forEach((p, i) => {
    if (p === null) {
      needed[found.slots[i]] = (needed[found.slots[i]] ?? 0) + 1;
    }
  });
  const state = { destination: destination.id, removed: found.removed, keys };
  const usedCards = new Set(keys.filter((k) => k.src !== SRC.LIGHT).map((k) => `${k.src}:${k.card}`));
  const complete = found.slots.length === keys.length && isComplete(board, state) && checkAnalysis(board, state).ok;
  return {
    state, complete, total: found.slots.length, paid: keys.length, needed, aborted,
    unused: {
      tableau: tableauIds.filter((id) => !usedCards.has(`${SRC.TABLEAU}:${id}`)),
      hand: handIds.filter((id) => !usedCards.has(`${SRC.HAND}:${id}`)),
      light: lightMax - lightUses(state),
    },
  };
}
