/**
 * 自己構成（状態 61）の支払いの純粋関数（仕様書 009 §4、006 §5.5、001 §9）。DOM もフレームワークも使わない。
 * サーバーの `Flow/ComposePlanner` の規則を、画面で払えない選択肢を選べなくするために仮検証する。最終的な検証はサーバー。
 *
 * 支払い（`actCompose` の `payment`）: `{"discard": [手札の ID...], "light": 心光の行使の回数, "tap": [自己構成済みの ID...], "extra": [手札の ID...]}`。
 * - discard: 手札を捨てて、そのカードのレベル分のエネルギーを払う（レベル 2 は 2 つ分）
 * - light: 心光でエネルギー何個分を払うか。X 回目の行使は X 個（`lightTotals[X-1]` が総数）
 * - tap: M095 で横向きにする感情鍵のカード。1 枚につきエネルギー -1（そのカードの必要エネルギーを超えない枚数）
 * - extra: I08 の追加支払い（自己構成したカードの下に置く）。エネルギーには数えない
 * 払う合計が必要なエネルギー以上なら成立する（過払い可。Q-22）。同名のカードは自己構成できない（`composable`）。
 *
 * サーバーの args に無い値は省略可として扱う（`tapCandidates`・`extraMax`・`architectPairs`。無ければ機能を出さない）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { int } from '../ui/dom.js';
import { intList, list, obj, privateOf } from './args.js';

/** エネルギーを払う文脈（`ComposeContext::paysEnergy`） */
export const PAYING_CONTEXTS = ['PHASE', 'TOKEN', 'FLOOR'];

/** 拒否の理由（サーバーの文言の要点） */
export const REASON = {
  CARD: 'CARD',
  NOT_PAYING: 'NOT_PAYING',
  EXTRA_MAX: 'EXTRA_MAX',
  EXTRA_CARD: 'EXTRA_CARD',
  DISCARD_CARD: 'DISCARD_CARD',
  TAP: 'TAP',
  TAP_COUNT: 'TAP_COUNT',
  ENERGY: 'ENERGY',
  LIGHT: 'LIGHT',
};

/**
 * @typedef {object} HandEntry
 * @property {number} id
 * @property {number} type
 * @property {number} level
 * @property {number} need この文脈でそのカードを自己構成するのに払うエネルギー（横向きなし）
 * @property {boolean} composable
 */

/**
 * @typedef {object} ComposeBoard
 * @property {string} context PHASE / TOKEN / FLOOR / BONUS_LV1 / BONUS_ANY / EFFECT_FREE_LV1
 * @property {boolean} paysEnergy
 * @property {number} light 持っている心光
 * @property {number[]} lightTotals X 回行使するときの心光の総数（添字 X-1）
 * @property {number} maxLightUses
 * @property {boolean} canCompose
 * @property {Map<number, HandEntry>} hand
 * @property {number[]} tapCandidates M095 で横向きにできる自己構成済みカード
 * @property {number} extraMax I08 の追加支払いの上限
 * @property {[number, number][]} architectPairs I03 で入れ替えられる (自己構成済み, 手札)
 */

/**
 * @typedef {object} ComposePayment
 * @property {number | null} cardId 自己構成するカード
 * @property {number[]} discard
 * @property {number} light 心光の行使の回数
 * @property {number[]} tap
 * @property {number[]} extra
 */

/**
 * 状態 61 の args → 計画用の盤。
 * @param {unknown} args
 * @returns {ComposeBoard}
 */
export function composeFromArgs(args) {
  const a = obj(args);
  const priv = privateOf(args);
  /** @type {Map<number, HandEntry>} */
  const hand = new Map();
  for (const raw of list(priv.hand)) {
    const h = obj(raw);
    const id = int(h.id);
    hand.set(id, { id, type: int(h.type), level: int(h.level) === 2 ? 2 : 1, need: Math.max(0, int(h.need)), composable: h.composable === true });
  }
  const context = typeof a.context === 'string' ? a.context : 'PHASE';
  /** @type {[number, number][]} */
  const architectPairs = [];
  for (const pair of list(priv.architectPairs ?? a.architectPairs)) {
    const p = list(pair);
    if (p.length === 2) {
      architectPairs.push([int(p[0]), int(p[1])]);
    }
  }
  return {
    context,
    paysEnergy: PAYING_CONTEXTS.includes(context),
    light: Math.max(0, int(a.light)),
    lightTotals: intList(a.lightTotals),
    maxLightUses: Math.max(0, int(a.maxLightUses)),
    canCompose: a.canCompose === true,
    hand,
    tapCandidates: intList(a.tapCandidates),
    extraMax: Math.max(0, int(a.extraMax)),
    architectPairs,
  };
}

/** @returns {ComposePayment} */
export function emptyPayment() {
  return { cardId: null, discard: [], light: 0, tap: [], extra: [] };
}

/**
 * 自己構成できる手札のカード ID。
 * @param {ComposeBoard} board
 * @returns {number[]}
 */
export function composableIds(board) {
  return [...board.hand.values()].filter((h) => h.composable).map((h) => h.id);
}

/**
 * 払うエネルギー（横向きにした枚数だけ減る。0 未満にならない）。
 * @param {ComposeBoard} board
 * @param {ComposePayment} payment
 * @returns {number}
 */
export function energyNeed(board, payment) {
  const entry = payment.cardId === null ? undefined : board.hand.get(payment.cardId);
  if (entry === undefined || !board.paysEnergy) {
    return 0;
  }
  return Math.max(0, entry.need - payment.tap.length);
}

/**
 * 心光の行使を `uses` 回するときの心光の総数。
 * @param {ComposeBoard} board
 * @param {number} uses
 * @returns {number}
 */
export function lightCost(board, uses) {
  if (uses <= 0) {
    return 0;
  }
  return board.lightTotals[uses - 1] ?? Infinity;
}

/**
 * 今の支払いで払えたエネルギー（捨てた手札のレベルの和＋心光の行使の回数）。
 * @param {ComposeBoard} board
 * @param {ComposePayment} payment
 * @returns {number}
 */
export function paidEnergy(board, payment) {
  let paid = payment.light;
  for (const id of payment.discard) {
    paid += board.hand.get(id)?.level ?? 0;
  }
  return paid;
}

/**
 * 足りないエネルギー。
 * @param {ComposeBoard} board
 * @param {ComposePayment} payment
 * @returns {number}
 */
export function shortfall(board, payment) {
  return Math.max(0, energyNeed(board, payment) - paidEnergy(board, payment));
}

/**
 * 心光の行使をもう 1 回増やせるか。
 * @param {ComposeBoard} board
 * @param {ComposePayment} payment
 * @returns {boolean}
 */
export function canAddLight(board, payment) {
  const next = payment.light + 1;
  return board.paysEnergy && next <= board.maxLightUses && lightCost(board, next) <= board.light;
}

/**
 * エネルギーとして捨てられる手札（自己構成するカードと、追加支払いに選んだカードを除く）。
 * @param {ComposeBoard} board
 * @param {ComposePayment} payment
 * @returns {number[]}
 */
export function discardOptions(board, payment) {
  if (!board.paysEnergy) {
    return [];
  }
  return [...board.hand.keys()].filter((id) => id !== payment.cardId && !payment.extra.includes(id));
}

/**
 * I08 の追加支払いにできる手札（自己構成するカードと、捨てるカードを除く）。
 * @param {ComposeBoard} board
 * @param {ComposePayment} payment
 * @returns {number[]}
 */
export function extraOptions(board, payment) {
  if (board.extraMax <= 0) {
    return [];
  }
  return [...board.hand.keys()].filter((id) => id !== payment.cardId && !payment.discard.includes(id));
}

/**
 * M095 で横向きにできる自己構成済みカード（必要エネルギーを超えない枚数まで）。
 * @param {ComposeBoard} board
 * @param {ComposePayment} payment
 * @returns {number[]}
 */
export function tapOptions(board, payment) {
  const entry = payment.cardId === null ? undefined : board.hand.get(payment.cardId);
  if (entry === undefined || !board.paysEnergy || payment.tap.length >= entry.need) {
    return payment.tap.slice();
  }
  return board.tapCandidates.slice();
}

/**
 * 選択の切り替え（含まれていれば外し、なければ加える）。
 * @param {number[]} ids
 * @param {number} id
 * @returns {number[]}
 */
export function toggle(ids, id) {
  return ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id];
}

/**
 * 支払いを検証する（`ComposePlanner::plan` と同じ順）。
 * @param {ComposeBoard} board
 * @param {ComposePayment} payment
 * @returns {{ ok: true } | { ok: false, reason: string }}
 */
export function checkPayment(board, payment) {
  const cardId = payment.cardId;
  const entry = cardId === null ? undefined : board.hand.get(cardId);
  if (cardId === null || entry === undefined || !entry.composable) {
    return { ok: false, reason: REASON.CARD };
  }
  if (payment.extra.length > board.extraMax) {
    return { ok: false, reason: REASON.EXTRA_MAX };
  }
  if (!board.paysEnergy && (payment.discard.length > 0 || payment.tap.length > 0 || payment.light > 0)) {
    return { ok: false, reason: REASON.NOT_PAYING };
  }
  for (const id of payment.extra) {
    if (id === cardId || payment.discard.includes(id) || !board.hand.has(id)) {
      return { ok: false, reason: REASON.EXTRA_CARD };
    }
  }
  if (new Set(payment.extra).size !== payment.extra.length || new Set(payment.discard).size !== payment.discard.length) {
    return { ok: false, reason: REASON.DISCARD_CARD };
  }
  for (const id of payment.discard) {
    if (id === cardId || !board.hand.has(id)) {
      return { ok: false, reason: REASON.DISCARD_CARD };
    }
  }
  if (payment.tap.length > 0) {
    const unique = new Set(payment.tap).size === payment.tap.length;
    if (!unique || payment.tap.some((id) => !board.tapCandidates.includes(id))) {
      return { ok: false, reason: REASON.TAP };
    }
    if (payment.tap.length > entry.need) {
      return { ok: false, reason: REASON.TAP_COUNT };
    }
  }
  if (paidEnergy(board, payment) < energyNeed(board, payment)) {
    return { ok: false, reason: REASON.ENERGY };
  }
  if (lightCost(board, payment.light) > board.light) {
    return { ok: false, reason: REASON.LIGHT };
  }
  return { ok: true };
}

/**
 * `actCompose` の `payment`（JSON にして送る）。
 * @param {ComposePayment} payment
 * @returns {{ discard: number[], light: number, tap: number[], extra: number[] }}
 */
export function toPayment(payment) {
  return { discard: payment.discard.slice(), light: payment.light, tap: payment.tap.slice(), extra: payment.extra.slice() };
}

/**
 * I03（建築家）で入れ替えられる自己構成済みカード。
 * @param {ComposeBoard} board
 * @returns {number[]}
 */
export function architectTableau(board) {
  return [...new Set(board.architectPairs.map(([t]) => t))];
}

/**
 * I03 で、選んだ自己構成済みカードと入れ替えられる手札。
 * @param {ComposeBoard} board
 * @param {number} tableauId
 * @returns {number[]}
 */
export function architectHand(board, tableauId) {
  return board.architectPairs.filter(([t]) => t === tableauId).map(([, h]) => h);
}

// ---- まとめて選んだ資源の自動割り当て ----

/**
 * @typedef {object} ComposeSelection
 * @property {number[]} [hand] エネルギーの支払いに選んだ手札
 * @property {number[]} [tap] M095 で横向きにするために選んだ自己構成済みカード
 * @property {number} [light] 心光の行使の回数（上限）
 * @property {number[]} [extra] I08 の追加支払いに選んだ手札（エネルギーには数えない）
 */

/**
 * @typedef {object} ComposeSolution
 * @property {ComposePayment} payment 割り当ての結果
 * @property {boolean} complete 必要なエネルギーを払い、仮検証（checkPayment）も通る
 * @property {number} need 払うエネルギー（選んだ横向きの分は引かない）
 * @property {number} paid 払えたエネルギー（横向きの分も含む）
 * @property {number} shortfall 足りないエネルギー
 * @property {{ hand: number[], tap: number[], light: number }} unused 使われなかった選択
 */

/**
 * 選んだ手札・横向きにする自己構成済みカード・心光の回数から、規則を守って支払いを自動で決める（全探索。手札の部分集合 × 心光の回数 × 横向きの枚数）。
 * 優先順位: 足りないエネルギーが少ない → 過払いが少ない → 選んだのに使わない資源が少ない → 高価な資源（心光・捨てる手札）を使わない。
 * 結果は `checkPayment` に通して二重に確かめる。
 * @param {ComposeBoard} board
 * @param {number | null} cardId 自己構成するカード
 * @param {ComposeSelection} selection
 * @returns {ComposeSolution}
 */
export function solveComposePayment(board, cardId, selection) {
  const entry = cardId === null ? undefined : board.hand.get(cardId);
  const extra = [...new Set(selection.extra ?? [])].filter((id) => id !== cardId && board.hand.has(id));
  const handPool = [...new Set(selection.hand ?? [])].filter((id) => id !== cardId && board.hand.has(id) && !extra.includes(id));
  const tapPool = [...new Set(selection.tap ?? [])].filter((id) => board.tapCandidates.includes(id));
  const lightMax = board.paysEnergy ? Math.min(Math.max(0, Math.floor(selection.light ?? 0)), board.maxLightUses) : 0;
  /** @param {number[]} discard @param {number} light @param {number[]} tap */
  const build = (discard, light, tap) => ({ cardId, discard, light, tap, extra });
  if (entry === undefined || !board.paysEnergy) {
    const payment = build([], 0, []);
    return {
      payment, complete: entry !== undefined && checkPayment(board, payment).ok, need: 0, paid: 0, shortfall: 0,
      unused: { hand: handPool, tap: tapPool, light: lightMax },
    };
  }
  const base = entry.need;
  /** @type {{ score: number, discard: number[], light: number, tap: number[], paid: number, shortfall: number } | null} */
  let best = null;
  const pool = handPool.slice(0, 16);
  for (let mask = 0; mask < (1 << pool.length); mask++) {
    const discard = pool.filter((_id, i) => (mask & (1 << i)) !== 0);
    const levels = discard.reduce((sum, id) => sum + (board.hand.get(id)?.level ?? 0), 0);
    for (let taps = 0; taps <= Math.min(tapPool.length, base); taps++) {
      const target = Math.max(0, base - taps);
      for (let light = 0; light <= lightMax; light++) {
        if (lightCost(board, light) > board.light) {
          break;
        }
        const paid = levels + light;
        const short = Math.max(0, target - paid);
        const waste = Math.max(0, paid - target);
        const unusedCount = (pool.length - discard.length) + (tapPool.length - taps) + (lightMax - light);
        const cost = discard.length * 3 + light * 4 + taps;
        const score = short * 1000000 + waste * 10000 + unusedCount * 100 + cost;
        if (best === null || score < best.score) {
          best = { score, discard, light, tap: tapPool.slice(0, taps), paid: paid + taps, shortfall: short };
        }
      }
    }
  }
  const chosen = best ?? { score: 0, discard: [], light: 0, tap: [], paid: 0, shortfall: base };
  const payment = build(chosen.discard, chosen.light, chosen.tap);
  return {
    payment, complete: chosen.shortfall === 0 && checkPayment(board, payment).ok, need: base, paid: chosen.paid, shortfall: chosen.shortfall,
    unused: {
      hand: handPool.filter((id) => !chosen.discard.includes(id)),
      tap: tapPool.filter((id) => !chosen.tap.includes(id)),
      light: lightMax - chosen.light,
    },
  };
}
