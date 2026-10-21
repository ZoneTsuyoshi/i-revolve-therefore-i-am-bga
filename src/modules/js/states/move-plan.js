/**
 * 移動の計画の純粋関数（仕様書 009 §3.1、006 §5.1）。DOM もフレームワークも使わない。
 * サーバーの `Flow/MovePlanner` の規則（経路、錠前の数と鍵の種類、複数鍵カードの分割禁止、心光の逓増、周遊ボーナスで得た資源）を
 * クライアント側で仮検証し、払えない選択肢を選べなくするために使う。最終的な検証はサーバー。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { int } from '../ui/dom.js';

/** 回廊の部屋の数 */
export const ROOM_COUNT = 12;

/** 支払いの src（Effects/Query/KeyPayment と同じ綴り） */
export const SRC = { TABLEAU: 'TABLEAU', HAND: 'HAND', LIGHT: 'LIGHT', TOKEN: 'TOKEN', EFFECT: 'EFFECT' };

/** 拒否の理由（Flow/MoveViolation と同じ綴り） */
export const REASON = {
  INVALID_ROUTE: 'INVALID_ROUTE',
  LOCK_COUNT: 'LOCK_COUNT',
  KEY_MISMATCH: 'KEY_MISMATCH',
  BAD_CARD: 'BAD_CARD',
  SPLIT_CARD: 'SPLIT_CARD',
  BAD_GRANT: 'BAD_GRANT',
  NOT_ENOUGH_RESOURCES: 'NOT_ENOUGH_RESOURCES',
  TOO_LONG: 'TOO_LONG',
};

/**
 * @typedef {object} PayItem
 * @property {string} src SRC のどれか
 * @property {number} [card] カード ID（TABLEAU・HAND・EFFECT）
 * @property {number} [key] カードの鍵の番号（TABLEAU・HAND）
 */

/**
 * @typedef {object} Step
 * @property {number} pos 入る部屋の位置
 * @property {PayItem[]} payments 錠前 1 個につき 1 つ
 */

/**
 * @typedef {object} BoardCard
 * @property {number} id
 * @property {string[]} keys
 * @property {boolean} tapped
 */

/**
 * @typedef {object} BoardRoom
 * @property {number} pos
 * @property {number} level
 * @property {string | null} lock
 * @property {string} bonus
 */

/**
 * @typedef {object} MoveBoard
 * @property {number} position 今いる部屋
 * @property {Map<number, BoardRoom>} rooms
 * @property {Map<number, BoardCard>} tableau
 * @property {Map<number, BoardCard>} hand
 * @property {number} light
 * @property {number} wildUp
 * @property {number[]} lightCosts n 回目の心光の行使の個数（n = 1 から）
 * @property {number} grantedKeys MD-06 の万能鍵（このターン最初のレベル 2 の入室にだけ使える数。0 なら使えない）
 * @property {Record<string, string[]>} keyServes 持つ鍵 → 払える錠前の種類
 * @property {number} maxRooms
 * @property {boolean} canMove
 */

/**
 * @param {unknown} value
 * @returns {Record<string, any>}
 */
function obj(value) {
  return value !== null && typeof value === 'object' ? /** @type {Record<string, any>} */ (value) : {};
}

/**
 * @param {unknown} list
 * @returns {Map<number, BoardCard>}
 */
function cardMap(list) {
  /** @type {Map<number, BoardCard>} */
  const map = new Map();
  for (const raw of Array.isArray(list) ? list : []) {
    const c = obj(raw);
    const id = int(c.id);
    map.set(id, { id, keys: Array.isArray(c.keys) ? c.keys.map(String) : [], tapped: c.tapped === true || c.tapped === 1 || c.tapped === '1' });
  }
  return map;
}

/**
 * 状態 31 の args（`getArgs` の形。手札は `_private`）を計画用の盤にする。形が崩れていても例外を出さない。
 * `_private` は本人にだけ届く。args._private.hand か args._private[自分].hand のどちらでも受け取る。
 * @param {unknown} args
 * @returns {MoveBoard}
 */
export function boardFromArgs(args) {
  const a = obj(args);
  const priv = obj(a._private);
  const handList = Array.isArray(priv.hand) ? priv.hand : (Object.values(priv).map(obj).find((p) => Array.isArray(p.hand))?.hand ?? []);
  /** @type {Map<number, BoardRoom>} */
  const rooms = new Map();
  for (const raw of Array.isArray(a.rooms) ? a.rooms : []) {
    const r = obj(raw);
    const pos = int(r.pos);
    rooms.set(pos, { pos, level: int(r.level) === 2 ? 2 : 1, lock: typeof r.lock === 'string' ? r.lock : null, bonus: String(r.bonus ?? 'NONE') });
  }
  const res = obj(a.resources);
  /** @type {Record<string, string[]>} */
  const keyServes = {};
  for (const [held, locks] of Object.entries(obj(a.keyServes))) {
    keyServes[held] = Array.isArray(locks) ? locks.map(String) : [];
  }
  return {
    position: int(a.position),
    rooms,
    tableau: cardMap(a.tableau),
    hand: cardMap(handList),
    light: int(res.light),
    wildUp: int(res.wildUp),
    lightCosts: Array.isArray(res.lightCosts) ? res.lightCosts.map(int) : [],
    grantedKeys: int(res.grantedKeys),
    keyServes,
    maxRooms: int(a.maxRooms) || 24,
    canMove: a.canMove === true || a.canMove === 1 || a.canMove === '1',
  };
}

/**
 * 時計回りの次の位置（12 の次は 1）。
 * @param {number} pos
 * @returns {number}
 */
export function after(pos) {
  return (pos % ROOM_COUNT) + 1;
}

/**
 * @param {MoveBoard} board
 * @param {Step[]} steps
 * @returns {number} 計画の終点（空なら今いる部屋）
 */
export function endPosition(board, steps) {
  return steps.length === 0 ? board.position : steps[steps.length - 1].pos;
}

/**
 * 鍵 `held` で錠前 `lock` を払えるか。
 * @param {MoveBoard} board
 * @param {string} lock
 * @param {string} held
 * @returns {boolean}
 */
export function canServe(board, lock, held) {
  return (board.keyServes[held] ?? []).includes(lock);
}

/**
 * n 回目の心光の行使の個数。表より多い回数は最後の値を使う。
 * @param {MoveBoard} board
 * @param {number} n 1 から
 * @returns {number}
 */
export function lightCostOf(board, n) {
  const costs = board.lightCosts;
  if (costs.length === 0) {
    return n;
  }
  return costs[Math.min(n, costs.length) - 1];
}

/**
 * 経路と支払いを仮検証する。サーバーの `MovePlanner::plan` と同じ規則。
 * `partial` が true なら、最後の入室の支払いが錠前の数に満たなくてよい（支払いの途中）。
 * @param {MoveBoard} board
 * @param {Step[]} steps
 * @param {{ partial?: boolean }} [options]
 * @returns {{ ok: true } | { ok: false, reason: string, detail: string }}
 */
export function checkPlan(board, steps, { partial = false } = {}) {
  /** @param {string} reason @param {string} detail */
  const fail = (reason, detail) => /** @type {const} */ ({ ok: false, reason, detail });
  if (steps.length > board.maxRooms) {
    return fail(REASON.TOO_LONG, 'route too long');
  }
  let pos = board.position;
  let lights = board.light;
  let tokens = board.wildUp;
  let lightUses = 0;
  let lv2Seen = 0;
  /** @type {{ ok: false, reason: string, detail: string } | null} */
  let shortage = null;
  /** @type {Set<string>} */
  const usedSlots = new Set();
  /** @type {Set<number>} */
  const effectUsed = new Set();
  /** @type {Map<number, number[]>} */
  const cardEntries = new Map();

  for (const [index, step] of steps.entries()) {
    const next = after(pos);
    let skipped = null;
    if (step.pos !== next) {
      if (board.rooms.get(next)?.level !== 2 || step.pos !== after(next)) {
        return fail(REASON.INVALID_ROUTE, `room ${step.pos} after ${pos}`);
      }
      skipped = next;
    }
    const room = board.rooms.get(step.pos);
    if (room === undefined || room.lock === null) {
      return fail(REASON.INVALID_ROUTE, `room ${step.pos} has no memory card`);
    }
    const isLast = index === steps.length - 1;
    const count = step.payments.length;
    if (count > room.level || (count < room.level && !(partial && isLast))) {
      return fail(REASON.LOCK_COUNT, `room ${step.pos} needs ${room.level}`);
    }
    const grants = room.level === 2 && lv2Seen === 0 ? Math.min(board.grantedKeys, 2) : 0;
    let effects = 0;
    let stepLights = 0;
    let stepTokens = 0;
    for (const item of step.payments) {
      if (item.src === SRC.TABLEAU || item.src === SRC.HAND) {
        const card = (item.src === SRC.TABLEAU ? board.tableau : board.hand).get(item.card ?? -1);
        const key = item.key;
        if (card === undefined || card.tapped || key === undefined || key < 0 || key >= card.keys.length) {
          return fail(REASON.BAD_CARD, `${item.src} ${item.card}`);
        }
        const slot = `${item.src}:${card.id}:${key}`;
        if (usedSlots.has(slot)) {
          return fail(REASON.BAD_CARD, `key used twice: ${slot}`);
        }
        usedSlots.add(slot);
        if (!canServe(board, room.lock, card.keys[key])) {
          return fail(REASON.KEY_MISMATCH, `${card.keys[key]} for ${room.lock}`);
        }
        const entries = cardEntries.get(card.id) ?? [];
        if (!entries.includes(index)) {
          entries.push(index);
        }
        cardEntries.set(card.id, entries);
      } else if (item.src === SRC.LIGHT) {
        stepLights++;
      } else if (item.src === SRC.TOKEN) {
        stepTokens++;
      } else if (item.src === SRC.EFFECT) {
        const card = board.tableau.get(item.card ?? -1);
        if (card === undefined || effectUsed.has(card.id)) {
          return fail(REASON.BAD_GRANT, `card ${item.card}`);
        }
        effectUsed.add(card.id);
        effects++;
      } else {
        return fail(REASON.BAD_CARD, `src ${item.src}`);
      }
    }
    if (effects > grants) {
      return fail(REASON.BAD_GRANT, `too many granted keys for room ${step.pos}`);
    }
    // 資源は 1 部屋ずつ順に: スキップした部屋のボーナス → 支払い → 入った部屋のボーナス
    if (skipped !== null) {
      [lights, tokens] = applyBonus(board.rooms.get(skipped)?.bonus, lights, tokens);
    }
    for (let i = 0; i < stepLights; i++) {
      lightUses++;
      lights -= lightCostOf(board, lightUses);
    }
    tokens -= stepTokens;
    if ((lights < 0 || tokens < 0) && shortage === null) {
      // サーバーと同じ順序で理由を返すため、資源の不足は分割の検査のあとで報告する
      shortage = fail(REASON.NOT_ENOUGH_RESOURCES, `room ${index + 1} of the route`);
    }
    [lights, tokens] = applyBonus(room.bonus, lights, tokens);
    if (room.level === 2) {
      lv2Seen++;
    }
    pos = step.pos;
  }

  // 同じカードを使う入室は、入室した部屋の列で連続している（スキップを挟んでも連続。Q-07）
  for (const [cardId, indices] of cardEntries) {
    if (Math.max(...indices) - Math.min(...indices) + 1 !== indices.length) {
      return fail(REASON.SPLIT_CARD, `card ${cardId}`);
    }
  }
  return shortage ?? { ok: true };
}

/**
 * @param {string | undefined} bonus
 * @param {number} lights
 * @param {number} tokens
 * @returns {[number, number]}
 */
function applyBonus(bonus, lights, tokens) {
  if (bonus === 'LIGHT') {
    return [lights + 1, tokens];
  }
  if (bonus === 'WILD_MOVE_KEY') {
    return [lights, tokens + 1];
  }
  return [lights, tokens];
}

/**
 * 計画が確定できる形か（最後の部屋まで払い終わり、仮検証を通る）。経路が空のときは canMove が false の場合だけ（Q-38）。
 * @param {MoveBoard} board
 * @param {Step[]} steps
 * @returns {boolean}
 */
export function isComplete(board, steps) {
  if (steps.length === 0) {
    return !board.canMove;
  }
  return checkPlan(board, steps).ok;
}

/**
 * 最後の入室がまだ払い終わっていないとき、その部屋（支払いを待っている部屋）。
 * @param {MoveBoard} board
 * @param {Step[]} steps
 * @returns {BoardRoom | null}
 */
export function pendingRoom(board, steps) {
  const last = steps[steps.length - 1];
  const room = last === undefined ? undefined : board.rooms.get(last.pos);
  return room !== undefined && last.payments.length < room.level ? room : null;
}

/**
 * 次に入れる部屋（支払い待ちが無いときだけ）。次の部屋、次がレベル 2 ならそのスキップ先も。
 * 払える見込みのない部屋（仮検証で最初の支払いの選択肢が無い）は除く。
 * @param {MoveBoard} board
 * @param {Step[]} steps
 * @returns {number[]}
 */
export function nextRooms(board, steps) {
  if (pendingRoom(board, steps) !== null || steps.length >= board.maxRooms) {
    return [];
  }
  const from = endPosition(board, steps);
  const next = after(from);
  const out = [next];
  if (board.rooms.get(next)?.level === 2) {
    out.push(after(next));
  }
  return out.filter((pos) => (board.rooms.get(pos)?.lock ?? null) !== null && canComplete(board, [...steps, { pos, payments: [] }]));
}

/**
 * 最後の入室を、仮検証を通る支払いの組み合わせで払い終えられるか（深さは錠前の数まで）。
 * @param {MoveBoard} board
 * @param {Step[]} steps
 * @returns {boolean}
 */
export function canComplete(board, steps) {
  if (pendingRoom(board, steps) === null) {
    return checkPlan(board, steps).ok;
  }
  return payOptions(board, steps).some((item) => canComplete(board, addPayment(steps, item)));
}

/**
 * @typedef {object} PayOption
 * @property {PayItem} item 追加する支払い
 */

/**
 * 最後の入室の次の錠前に使える支払い。仮検証を通るものだけ。カードは鍵の番号ごと（同じカードの 2 本目の鍵も別の選択肢）。
 * 万能鍵（EFFECT）は、まだ使っていない自己構成済みカードのうち ID が最小のもの 1 枚分。
 * @param {MoveBoard} board
 * @param {Step[]} steps 最後の入室は支払いの途中でよい
 * @returns {PayItem[]}
 */
export function payOptions(board, steps) {
  const last = steps[steps.length - 1];
  if (last === undefined || pendingRoom(board, steps) === null) {
    return [];
  }
  /** @type {PayItem[]} */
  const candidates = [{ src: SRC.LIGHT }, { src: SRC.TOKEN }];
  for (const [src, cards] of /** @type {[string, Map<number, BoardCard>][]} */ ([[SRC.TABLEAU, board.tableau], [SRC.HAND, board.hand]])) {
    for (const card of cards.values()) {
      if (card.tapped) {
        continue;
      }
      for (let key = 0; key < card.keys.length; key++) {
        candidates.push({ src, card: card.id, key });
      }
    }
  }
  const effectCard = [...board.tableau.keys()].sort((x, y) => x - y).find((id) => !steps.some((s) => s.payments.some((p) => p.src === SRC.EFFECT && p.card === id)));
  if (effectCard !== undefined) {
    candidates.push({ src: SRC.EFFECT, card: effectCard });
  }
  return candidates.filter((item) => {
    const trial = steps.map((s, i) => (i === steps.length - 1 ? { pos: s.pos, payments: [...s.payments, item] } : s));
    return checkPlan(board, trial, { partial: true }).ok;
  });
}

/**
 * ある札（自己構成済みか手札のカード）を使う支払いの候補。クリックされたら最初の候補を割り当てる。
 * @param {PayItem[]} options payOptions の結果
 * @param {string} src SRC.TABLEAU または SRC.HAND
 * @returns {Map<number, PayItem>} カード ID → 使う支払い
 */
export function cardChoices(options, src) {
  /** @type {Map<number, PayItem>} */
  const map = new Map();
  for (const item of options) {
    if (item.src === src && item.card !== undefined && !map.has(item.card)) {
      map.set(item.card, item);
    }
  }
  return map;
}

/**
 * 最後の入室に支払いを足した新しい計画（元は変えない）。
 * @param {Step[]} steps
 * @param {PayItem} item
 * @returns {Step[]}
 */
export function addPayment(steps, item) {
  return steps.map((s, i) => (i === steps.length - 1 ? { pos: s.pos, payments: [...s.payments, item] } : s));
}

/**
 * 最後の入室を取り消した新しい計画。
 * @param {Step[]} steps
 * @returns {Step[]}
 */
export function undoRoom(steps) {
  return steps.slice(0, -1);
}

/**
 * actMove に渡す入力（006 §5.1）。
 * @param {Step[]} steps
 * @returns {{ rooms: number[], payments: Record<string, unknown>[][] }}
 */
export function toInput(steps) {
  return {
    rooms: steps.map((s) => s.pos),
    payments: steps.map((s) => s.payments.map((p) => {
      /** @type {Record<string, unknown>} */
      const out = { src: p.src };
      if (p.card !== undefined) {
        out.card = p.card;
      }
      if (p.key !== undefined) {
        out.key = p.key;
      }
      return out;
    })),
  };
}

/**
 * 心光の行使の回数（計画全体）。
 * @param {Step[]} steps
 * @returns {number}
 */
export function lightUsesOf(steps) {
  return steps.reduce((sum, s) => sum + s.payments.filter((p) => p.src === SRC.LIGHT).length, 0);
}

/**
 * 計画の概要（ステータスバー用の英語。`${...}` を含まない）。部屋の位置を「Room 3 (Will)」の形で並べる。
 * @param {MoveBoard} board
 * @param {Step[]} steps
 * @param {(key: string) => string} [tr] 文言の翻訳
 * @returns {string}
 */
export function describePlan(board, steps, tr = (s) => s) {
  return steps.map((step) => {
    const room = board.rooms.get(step.pos);
    const paid = step.payments.map((p) => describePayment(board, p, tr)).join(', ');
    const lock = room?.lock === null || room === undefined ? '' : ` (${tr(lockName(room.lock))} x${room.level})`;
    return `${tr('Room')} ${step.pos}${lock}: ${paid === '' ? '...' : paid}`;
  }).join(' / ');
}

/**
 * @param {string} key
 * @returns {string}
 */
export function lockName(key) {
  return ({ WISDOM: 'Wisdom', WILL: 'Will', EMOTION: 'Emotion', WILD: 'Wild' })[key] ?? key;
}

/**
 * @param {MoveBoard} board
 * @param {PayItem} item
 * @param {(key: string) => string} tr
 * @returns {string}
 */
function describePayment(board, item, tr) {
  switch (item.src) {
    case SRC.LIGHT:
      return tr('Inner light');
    case SRC.TOKEN:
      return tr('Wild move key');
    case SRC.EFFECT:
      return tr('Effect key');
    default: {
      const card = (item.src === SRC.HAND ? board.hand : board.tableau).get(item.card ?? -1);
      const kind = card === undefined ? '' : tr(lockName(card.keys[item.key ?? 0] ?? ''));
      return `${item.src === SRC.HAND ? tr('Hand') : tr('Composed')} #${item.card} ${kind}`.trim();
    }
  }
}
