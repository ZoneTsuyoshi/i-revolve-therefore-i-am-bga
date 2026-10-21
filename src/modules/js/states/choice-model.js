/**
 * 効果の選択（状態 71）・床アクションの選択（51）・手札上限（72）・フリーアクションの純粋関数（仕様書 009 §4、006 §5.3・§7.4・§8、008 §6.4）。
 * DOM もフレームワークも使わない。回答の形は `States/EffectChoice::actResolveChoice` の説明と `Flow/ChoiceFlow` に合わせる。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { levelOfPosition } from '../board/view-model.js';
import { tokenizeText } from '../ui/icon-names.js';
import { int } from '../ui/dom.js';
import { intList, list, obj, privateOf } from './args.js';

/** 選択の種類（`Material/ChoiceKind`）。`null`（種類なし）は効果の分岐 */
export const KIND = {
  ORDER_EFFECTS: 'ORDER_EFFECTS',
  CONFIRM_USE: 'CONFIRM_USE',
  PICK_FIELD_CARD: 'PICK_FIELD_CARD',
  PICK_DISCARD: 'PICK_DISCARD',
  SELECT_DISCARD_FOR_LIGHT: 'SELECT_DISCARD_FOR_LIGHT',
  SELECT_SWAP_HAND_FIELD: 'SELECT_SWAP_HAND_FIELD',
  SELECT_SWAP_SAME_COST: 'SELECT_SWAP_SAME_COST',
  CONFIRM_MOVE: 'CONFIRM_MOVE',
  SWAP_ROOM_CARDS: 'SWAP_ROOM_CARDS',
  SWAP_ROOM_FLOORS: 'SWAP_ROOM_FLOORS',
  WARP_ROOM: 'WARP_ROOM',
  ENTREPRENEUR_SWAP: 'ENTREPRENEUR_SWAP',
  PICK_ENHANCED_EGO: 'PICK_ENHANCED_EGO',
  PICK_SPIRIT_BRANCH: 'PICK_SPIRIT_BRANCH',
};

/**
 * @typedef {object} Choice
 * @property {number} chooser 選択者
 * @property {number} turnPlayer
 * @property {string | null} kind 選択の種類（分岐は null）
 * @property {string} source 出典（カードの ID など）
 * @property {Record<string, any>} data 公開できる候補
 * @property {boolean} skippable
 * @property {[number, number][]} pairs SELECT_SWAP_SAME_COST の (自己構成済み, 手札)
 * @property {string[]} egoCandidates PICK_ENHANCED_EGO の候補
 */

/**
 * 状態 71 の args → 選択。
 * @param {unknown} args
 * @returns {Choice}
 */
export function choiceFromArgs(args) {
  const a = obj(args);
  const priv = privateOf(args);
  /** @type {[number, number][]} */
  const pairs = [];
  for (const pair of list(priv.pairs)) {
    const p = list(pair);
    if (p.length === 2) {
      pairs.push([int(p[0]), int(p[1])]);
    }
  }
  return {
    chooser: int(a.chooser),
    turnPlayer: int(a.turnPlayer),
    kind: typeof a.choiceKind === 'string' ? a.choiceKind : null,
    source: typeof a.source === 'string' ? a.source : '',
    data: obj(a.data),
    skippable: a.skippable === true,
    pairs,
    egoCandidates: list(priv.egoCandidates).map(String),
  };
}

/**
 * 手札・場札から選ぶ枚数の規則。
 * @param {Choice} choice
 * @returns {{ exact: number | null }} 枚数がちょうど決まっているなら exact
 */
export function pickCount(choice) {
  if (choice.kind === KIND.PICK_FIELD_CARD || choice.kind === KIND.PICK_DISCARD) {
    return { exact: Math.max(1, int(choice.data.count ?? 1)) };
  }
  return { exact: null };
}

/**
 * 選んだ枚数が足りているか（PICK_FIELD_CARD・PICK_DISCARD）。
 * @param {Choice} choice
 * @param {number[]} selected
 * @returns {boolean}
 */
export function pickReady(choice, selected) {
  const { exact } = pickCount(choice);
  return exact !== null && selected.length === exact;
}

/**
 * 手札と場札を同じ枚数ずつ入れ替える（SELECT_SWAP_HAND_FIELD。0 枚も可）。
 * @param {number[]} hand
 * @param {number[]} field
 * @returns {boolean}
 */
export function swapHandFieldReady(hand, field) {
  return hand.length === field.length;
}

/**
 * 自己構成済みカードと手札の交換（SELECT_SWAP_SAME_COST）で、選べる自己構成済みカード。
 * @param {[number, number][]} pairs
 * @returns {number[]}
 */
export function pairTableau(pairs) {
  return [...new Set(pairs.map(([t]) => t))];
}

/**
 * 選んだ自己構成済みカードと交換できる手札。選んでいなければ交換に使える手札すべて。
 * @param {[number, number][]} pairs
 * @param {number | null} tableauId
 * @returns {number[]}
 */
export function pairHand(pairs, tableauId) {
  return [...new Set(pairs.filter(([t]) => tableauId === null || t === tableauId).map(([, h]) => h))];
}

/**
 * 部屋を 2 つ選ぶ入れ替えで、2 つ目に選べる部屋（SWAP_ROOM_CARDS は同レベル、SWAP_ROOM_FLOORS は候補のどれでも）。
 * @param {string} kind
 * @param {number[]} rooms 候補
 * @param {number | null} first 1 つ目
 * @returns {number[]}
 */
export function secondRooms(kind, rooms, first) {
  if (first === null) {
    return rooms.slice();
  }
  const others = rooms.filter((r) => r !== first);
  return kind === KIND.SWAP_ROOM_CARDS ? others.filter((r) => levelOfPosition(r) === levelOfPosition(first)) : others;
}

/**
 * 1 つ目に選べる部屋（SWAP_ROOM_CARDS は同レベルの相手がいる部屋だけ）。
 * @param {string} kind
 * @param {number[]} rooms
 * @returns {number[]}
 */
export function firstRooms(kind, rooms) {
  return kind === KIND.SWAP_ROOM_CARDS ? rooms.filter((r) => secondRooms(kind, rooms, r).length > 0) : rooms.slice();
}

/**
 * 起業家の入れ替え（ENTREPRENEUR_SWAP）で、手札のカードと入れ替えられる部屋（同レベル）。
 * @param {number[]} rooms 候補の部屋
 * @param {number | null} handLevel 選んだ手札のレベル
 * @returns {number[]}
 */
export function entrepreneurRooms(rooms, handLevel) {
  return handLevel === null ? rooms.slice() : rooms.filter((r) => levelOfPosition(r) === handLevel);
}

/**
 * 起業家の入れ替えで、部屋と入れ替えられる手札（同レベル）。
 * @param {number[]} handIds
 * @param {(id: number) => number} levelOf
 * @param {number | null} pos 選んだ部屋
 * @returns {number[]}
 */
export function entrepreneurHand(handIds, levelOf, pos) {
  return pos === null ? handIds.slice() : handIds.filter((id) => levelOf(id) === levelOfPosition(pos));
}

/**
 * 効果の選択の回答（`actResolveChoice` の `payload`）。
 */
export const answer = {
  /** @param {number[]} cards */
  cards: (cards) => ({ cards }),
  /** @param {number[]} hand @param {number[]} field */
  swapHandField: (hand, field) => ({ hand, field }),
  /** @param {number} tableau @param {number} hand */
  swapSameCost: (tableau, hand) => ({ tableau, hand }),
  /** @param {boolean} use */
  use: (use) => ({ use }),
  /** @param {string} ego */
  ego: (ego) => ({ ego }),
  /** @param {string} spirit */
  spirit: (spirit) => ({ spirit }),
  /** @param {number} index */
  index: (index) => ({ index }),
  /** @param {number} branch */
  branch: (branch) => ({ branch }),
  /** @param {number} a @param {number} b */
  rooms: (a, b) => ({ a, b }),
  /** @param {number} pos */
  warp: (pos) => ({ pos }),
  /** @param {number} hand @param {number} pos */
  entrepreneur: (hand, pos) => ({ hand, pos }),
};

// ---- 文言の部品（英語を原文にして、呼び出し側が `_()` を通す） ----

/**
 * アイコンの記法（`{draw}`）を取り除いた、ボタン向けの文字列。
 * @param {string} text
 * @returns {string}
 */
export function plainText(text) {
  return tokenizeText(text).map((part) => ('icon' in part ? '' : part.text)).join('').replace(/\s+/g, ' ').replace(/\s+([,.:;])/g, '$1').trim();
}

/**
 * 床アクションの選択肢（51）の文言のキー。`FLOOR_A`・`FLOOR_B` は分岐、`ALT_*` は想起効果の代替。
 * @param {{ kind: string, discount?: number }} option
 * @returns {{ type: 'floor' } | { type: 'branch', branch: number } | { type: 'recall', key: string }}
 */
export function floorOptionKey(option) {
  const kind = String(option.kind);
  if (kind.startsWith('FLOOR_')) {
    return { type: 'branch', branch: kind.charCodeAt(kind.length - 1) - 'A'.charCodeAt(0) };
  }
  if (kind === 'ALT_ANALYSIS' || kind === 'ALT_COMPOSE') {
    return { type: 'recall', key: int(option.discount) > 0 ? `${kind}_DISCOUNT_1` : kind };
  }
  return { type: 'floor' };
}

// ---- 精神カード（PICK_SPIRIT_BRANCH） ----

/**
 * 精神カードの ID（`R3B`）の階層と分岐。
 * @param {string} id
 * @returns {{ tier: number, branch: string }}
 */
export function spiritParts(id) {
  const m = /^R(\d)([A-Z]?)$/.exec(id);
  return { tier: m === null ? 0 : int(m[1]), branch: m === null ? '' : m[2] };
}

// ---- フリーアクション ----

/**
 * 手札を捨てて得る心光（2 枚分につき 1。レベル 2 は 2 枚分）。
 * @param {number[]} levels 捨てるカードのレベル
 * @returns {number}
 */
export function discardLightGain(levels) {
  return Math.floor(levels.reduce((sum, level) => sum + (level === 2 ? 2 : 1), 0) / 2);
}

/**
 * 獲得できる目標カード（N23・`myObjectiveStatus` の `claimable`）。
 * @param {Map<string, { x: number, points: number, claimable?: boolean }>} statuses 目標カードのキー（`O001`）→ 値
 * @returns {{ key: string, id: number, points: number }[]}
 */
export function claimableObjectives(statuses) {
  /** @type {{ key: string, id: number, points: number }[]} */
  const out = [];
  for (const [key, status] of statuses) {
    if (status.claimable === true) {
      out.push({ key, id: int(key.slice(1)), points: status.points });
    }
  }
  return out;
}

/**
 * フリーアクションを使えるか（ターンプレイヤーだけ。006 §6.2）。
 * @param {number | null} me
 * @param {number} turnPlayer
 * @returns {boolean}
 */
export function canUseFreeActions(me, turnPlayer) {
  return me !== null && me === turnPlayer;
}

/**
 * 手札上限で捨てる枚数が足りているか（超過分ちょうど）。
 * @param {number} excess
 * @param {number[]} selected
 * @returns {boolean}
 */
export function discardExcessReady(excess, selected) {
  return excess > 0 && selected.length === excess;
}

/**
 * ID の配列を整数にそろえる（選択の onChange は数値か文字列で返る）。
 * @param {(number | string)[]} values
 * @returns {number[]}
 */
export function toIds(values) {
  return intList(values);
}

/**
 * 鍵のコスト（`{ WISDOM: 2, WILL: 1 }`）の英語の文字列（「2 × Wisdom, 1 × Will」）。
 * @param {Record<string, number>} cost
 * @param {(type: string) => string} nameOf 鍵の名前（翻訳済み）
 * @returns {string}
 */
export function costText(cost, nameOf) {
  return ['WISDOM', 'WILL', 'EMOTION']
    .filter((type) => int(cost[type]) > 0)
    .map((type) => `${int(cost[type])} × ${nameOf(type)}`)
    .join(', ');
}
