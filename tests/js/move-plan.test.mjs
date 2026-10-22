// U-3: 移動の計画の仮検証。PHP の MovePlannerTest（tests/Unit/Flow/MovePlannerTest.php）の主要ケースと同じ結果になることを確かめる
import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
  REASON, SRC, addPayment, boardFromArgs, canComplete, cardChoices, checkPlan, describePlan, isComplete, lightCostOf, nextRooms, payOptions,
  pendingRoom, toInput, undoRoom,
} from '../../src/modules/js/states/move-plan.js';

const W = 'WISDOM';
const F = 'WILL';
const E = 'EMOTION';
const WILD = 'WILD';
const BONUS = { 1: 'ANALYSIS_TOKEN', 4: 'LIGHT', 7: 'WILD_MOVE_KEY', 10: 'COMPOSE_TOKEN' };
const SERVES = { WISDOM: [W], WILL: [F], EMOTION: [E], WILD: [W, F, E] };

/** MoveBoards::board と同じ既定（四隅がレベル 2、錠前は既定で知恵） */
function board({ position = 1, locks = {}, hand = [], tableau = [], light = 0, wildUp = 0, grantedKeys = 0, keyServes = SERVES, lightCosts = [1, 2, 3, 4, 5, 6], canMove = true } = {}) {
  const rooms = [];
  for (let pos = 1; pos <= 12; pos++) {
    rooms.push({ pos, level: [1, 4, 7, 10].includes(pos) ? 2 : 1, lock: locks[pos] ?? W, locks: 1, bonus: BONUS[pos] ?? 'NONE' });
  }
  const args = {
    position, rooms, maxRooms: 24, canMove,
    resources: { light, wildUp, lightCosts, grantedKeys }, keyServes, tableau,
    _private: { 99: { hand } },
  };
  return boardFromArgs(args);
}
const card = (id, ...keys) => ({ id, type: id, keys, tapped: false });
const tapped = (id, ...keys) => ({ ...card(id, ...keys), tapped: true });
const tab = (c, key = 0) => ({ src: 'TABLEAU', card: c, key });
const hand = (c, key = 0) => ({ src: 'HAND', card: c, key });
const LIGHT = { src: 'LIGHT' };
const TOKEN = { src: 'TOKEN' };
const plan = (rooms, payments) => rooms.map((pos, i) => ({ pos, payments: payments[i] }));
const reason = (b, steps) => {
  const r = checkPlan(b, steps);
  return r.ok ? 'OK' : r.reason;
};

test('boardFromArgs: 手札は _private[自分] でも _private 直下でもよい。壊れた args でも例外を出さない', () => {
  assert.equal(board({ hand: [card(3, W)] }).hand.get(3).keys[0], W);
  assert.equal(boardFromArgs({ _private: { hand: [card(4, F)] } }).hand.size, 1);
  const empty = boardFromArgs(undefined);
  assert.equal(empty.rooms.size, 0);
  assert.equal(empty.canMove, false);
  assert.equal(empty.maxRooms, 24);
});

test('経路: 時計回りの次、レベル 2 のスキップ、12 の次は 1', () => {
  const b = board({ position: 5, wildUp: 5 });
  assert.equal(reason(b, plan([6], [[TOKEN]])), 'OK');
  for (const bad of [4, 7, 5, 13]) {
    assert.equal(reason(b, plan([bad], [[TOKEN]])), REASON.INVALID_ROUTE, `room ${bad}`);
  }
  const wrap = board({ position: 11, wildUp: 3 });
  assert.equal(reason(wrap, plan([12, 1], [[TOKEN], [TOKEN, TOKEN]])), 'OK');
  // レベル 1 の部屋はスキップできない。レベル 2 なら飛ばせる
  assert.equal(reason(board({ position: 2, wildUp: 3 }), plan([4], [[TOKEN, TOKEN]])), REASON.INVALID_ROUTE);
  assert.equal(reason(board({ position: 3, wildUp: 1 }), plan([5], [[TOKEN]])), 'OK');
});

test('経路の長さの上限は maxRooms', () => {
  const b = board({ position: 1, wildUp: 100 });
  const steps = [];
  let pos = 1;
  for (let i = 0; i < 25; i++) {
    pos = (pos % 12) + 1;
    steps.push({ pos, payments: Array(b.rooms.get(pos).level).fill(TOKEN) });
  }
  assert.equal(reason(b, steps), REASON.TOO_LONG);
  assert.equal(reason(b, steps.slice(0, 24)), 'OK');
});

test('錠前の数: レベル 2 は 2 個、レベル 1 は 1 個。部分入力は最後の部屋だけ許す', () => {
  const b = board({ position: 3, locks: { 4: F }, wildUp: 2, tableau: [card(5, F, F)] });
  assert.equal(reason(b, plan([4], [[TOKEN]])), REASON.LOCK_COUNT);
  assert.equal(reason(board({ position: 2, wildUp: 2 }), plan([3], [[TOKEN, TOKEN]])), REASON.LOCK_COUNT);
  assert.equal(reason(b, plan([4], [[tab(5, 0), tab(5, 1)]])), 'OK');
  assert.equal(checkPlan(b, plan([4], [[TOKEN]]), { partial: true }).ok, true);
  const two = board({ position: 2, wildUp: 5 });
  assert.equal(checkPlan(two, plan([3, 4], [[], [TOKEN]]), { partial: true }).ok, false, '途中の部屋は払い終えている必要がある');
});

test('鍵の種類: 合わない鍵は不可、万能と keyServes の読み替え（M096）は可', () => {
  const b = board({ position: 5, locks: { 6: E }, tableau: [card(1, W), card(2, E), card(3, WILD)] });
  assert.equal(reason(b, plan([6], [[tab(1)]])), REASON.KEY_MISMATCH);
  assert.equal(reason(b, plan([6], [[tab(2)]])), 'OK');
  assert.equal(reason(b, plan([6], [[tab(3)]])), 'OK');
  const m096 = board({ position: 5, locks: { 6: W }, tableau: [card(1, F)], keyServes: { ...SERVES, WILL: [F, W] } });
  assert.equal(reason(m096, plan([6], [[tab(1)]])), 'OK');
});

test('カード: 使用済み・持っていない・鍵の番号が範囲外・同じ鍵の二重使用は不可', () => {
  const b = board({ position: 5, tableau: [tapped(1, W), card(2, W)], hand: [card(3, W, W)] });
  assert.equal(reason(b, plan([6], [[tab(1)]])), REASON.BAD_CARD);
  assert.equal(reason(b, plan([6], [[tab(9)]])), REASON.BAD_CARD);
  assert.equal(reason(b, plan([6], [[tab(2, 1)]])), REASON.BAD_CARD);
  assert.equal(reason(b, plan([6, 7], [[tab(2)], [tab(2), tab(2)]])), REASON.BAD_CARD);
  assert.equal(reason(b, plan([6], [[hand(3)]])), 'OK');
});

test('複数鍵のカード: 連続する入室なら分割してよい、離れるとだめ。スキップを挟んでも連続（Q-07）', () => {
  const b = board({ position: 5, tableau: [card(1, W, W), card(2, W)] });
  assert.equal(reason(b, plan([6, 7, 8], [[tab(1, 0)], [tab(2), TOKEN], [tab(1, 1)]])), REASON.SPLIT_CARD);
  const ok = board({ position: 5, tableau: [card(1, W, W)], wildUp: 1 });
  assert.equal(reason(ok, plan([6, 7], [[tab(1, 0)], [tab(1, 1), TOKEN]])), 'OK');
  assert.equal(reason(board({ position: 6, tableau: [card(1, W, W)] }), plan([7], [[tab(1, 0), tab(1, 1)]])), 'OK');
  assert.equal(reason(board({ position: 2, tableau: [card(1, W, W)] }), plan([3, 5], [[tab(1, 0)], [tab(1, 1)]])), 'OK');
});

test('心光: 行使ごとに逓増し、周遊ボーナスの心光で足りる', () => {
  const rooms = plan([5, 6, 7], [[LIGHT], [LIGHT], [LIGHT, LIGHT]]);
  assert.equal(reason(board({ position: 4, light: 10 }), rooms), 'OK');
  assert.equal(reason(board({ position: 4, light: 9 }), rooms), REASON.NOT_ENOUGH_RESOURCES);
  // M063 は 1・1・2・2・3
  const flat = { lightCosts: [1, 1, 2, 2, 3, 3] };
  assert.equal(reason(board({ position: 4, light: 6, ...flat }), rooms), 'OK');
  assert.equal(reason(board({ position: 4, light: 5, ...flat }), rooms), REASON.NOT_ENOUGH_RESOURCES);
  assert.equal(lightCostOf(board({ lightCosts: [1, 2] }), 5), 2, '表を超えたら最後の値');
  assert.equal(lightCostOf(board({ lightCosts: [] }), 3), 3);
});

test('万能移動鍵トークンは表向きの数まで', () => {
  const input = plan([5, 6], [[TOKEN], [TOKEN]]);
  assert.equal(reason(board({ position: 4, wildUp: 2 }), input), 'OK');
  assert.equal(reason(board({ position: 4, wildUp: 1 }), input), REASON.NOT_ENOUGH_RESOURCES);
});

test('周遊ボーナス: 入室した部屋のボーナスは後の部屋に使え、その部屋自身の支払いには使えない（T8-5）', () => {
  assert.equal(reason(board({ position: 3, wildUp: 2 }), plan([4, 5], [[TOKEN, TOKEN], [LIGHT]])), 'OK');
  assert.equal(reason(board({ position: 3, wildUp: 1 }), plan([4], [[TOKEN, LIGHT]])), REASON.NOT_ENOUGH_RESOURCES);
});

test('周遊ボーナス: スキップした部屋のボーナスは次の入室の支払いより先に得る', () => {
  assert.equal(reason(board({ position: 6 }), plan([8], [[TOKEN]])), 'OK');
  assert.equal(reason(board({ position: 6 }), plan([7], [[TOKEN, TOKEN]])), REASON.NOT_ENOUGH_RESOURCES);
  assert.equal(reason(board({ position: 3 }), plan([5], [[LIGHT]])), 'OK');
});

test('万能鍵（MD-06）: このターン最初のレベル 2 の入室にだけ、持つ枚数まで。同じカードは 2 度使えない', () => {
  const two = board({ position: 3, grantedKeys: 2, tableau: [card(1, W), card(2, W)] });
  assert.equal(reason(two, plan([4], [[{ src: 'EFFECT', card: 1 }, { src: 'EFFECT', card: 2 }]])), 'OK');
  assert.equal(reason(two, plan([4], [[{ src: 'EFFECT', card: 1 }, { src: 'EFFECT', card: 1 }]])), REASON.BAD_GRANT);
  // 最初のレベル 2 を過ぎたあとの 2 つ目のレベル 2 には使えない
  const route = plan([4, 5, 6, 7], [[{ src: 'EFFECT', card: 1 }, { src: 'EFFECT', card: 2 }], [LIGHT], [LIGHT], [{ src: 'EFFECT', card: 1 }, TOKEN]]);
  assert.equal(reason(board({ position: 3, grantedKeys: 2, light: 9, wildUp: 1, tableau: [card(1, W), card(2, W)] }), route), REASON.BAD_GRANT);
  // 持っていなければ使えない、レベル 1 の部屋にも使えない
  assert.equal(reason(board({ position: 3, tableau: [card(1, W)] }), plan([4], [[{ src: 'EFFECT', card: 1 }, TOKEN]])), REASON.BAD_GRANT);
  assert.equal(reason(board({ position: 4, grantedKeys: 1, tableau: [card(1, W)] }), plan([5], [[{ src: 'EFFECT', card: 1 }]])), REASON.BAD_GRANT);
});

test('Q-38: 空の経路は canMove が false のときだけ確定できる', () => {
  assert.equal(isComplete(board({ canMove: false }), []), true);
  assert.equal(isComplete(board({ canMove: true }), []), false);
  assert.equal(isComplete(board({ position: 5, wildUp: 1 }), plan([6], [[TOKEN]])), true);
  assert.equal(isComplete(board({ position: 5, wildUp: 1 }), plan([6], [[]])), false);
});

test('nextRooms / payOptions: 払える部屋・手段だけ出す。払えない部屋は選べない', () => {
  // 位置 3: 次の 4 はレベル 2（錠前 2）で、知恵鍵 1 本では払えない。スキップ先の 5 は払える
  const b = board({ position: 3, tableau: [card(5, W)] });
  assert.deepEqual(nextRooms(b, []), [5]);
  assert.equal(canComplete(b, [{ pos: 4, payments: [] }]), false);
  // 鍵 2 本なら両方入れる
  assert.deepEqual(nextRooms(board({ position: 3, tableau: [card(5, W, W)] }), []), [4, 5]);
  // 何も払えなければ空（canMove: false の盤）
  assert.deepEqual(nextRooms(board({ position: 2, canMove: false }), []), []);
  // 支払い待ちの間は部屋を選べない
  const half = [{ pos: 5, payments: [] }];
  assert.ok(pendingRoom(b, half));
  assert.deepEqual(nextRooms(b, half), []);
});

test('payOptions: 合う鍵・手札・心光・トークンを出し、合わない鍵や使用済みは出さない', () => {
  const b = board({ position: 5, locks: { 6: F }, tableau: [card(1, W), card(2, F, F), tapped(3, F)], hand: [card(4, F), card(5, E)], light: 1, wildUp: 1 });
  const options = payOptions(b, [{ pos: 6, payments: [] }]);
  const keys = options.map((o) => `${o.src}:${o.card ?? ''}:${o.key ?? ''}`).sort();
  assert.deepEqual(keys, ['HAND:4:0', 'LIGHT::', 'TABLEAU:2:0', 'TABLEAU:2:1', 'TOKEN::'].sort());
  assert.deepEqual([...cardChoices(options, 'TABLEAU').keys()], [2]);
  assert.deepEqual(cardChoices(options, 'TABLEAU').get(2), { src: 'TABLEAU', card: 2, key: 0 });
  // 心光が無ければ出さない
  assert.ok(!payOptions(board({ position: 5 }), [{ pos: 6, payments: [] }]).some((o) => o.src === SRC.LIGHT));
});

test('複数鍵のカードを離れた部屋で使う選択肢は出さない', () => {
  const b = board({ position: 5, tableau: [card(1, W, W)] });
  const steps = plan([6, 7, 8], [[tab(1, 0)], [TOKEN, TOKEN], []]);
  const options = payOptions({ ...b, wildUp: 5 }, steps);
  assert.ok(!options.some((o) => o.src === 'TABLEAU'));
  assert.ok(options.some((o) => o.src === 'TOKEN'));
});

test('addPayment / undoRoom / toInput: 元の配列を変えず、サーバーの入力の形にする', () => {
  const steps = [{ pos: 6, payments: [] }];
  const next = addPayment(steps, tab(2, 1));
  assert.equal(steps[0].payments.length, 0);
  assert.deepEqual(toInput(next), { rooms: [6], payments: [[{ src: 'TABLEAU', card: 2, key: 1 }]] });
  assert.deepEqual(toInput(addPayment(next, TOKEN)).payments[0][1], { src: 'TOKEN' });
  assert.deepEqual(toInput([]), { rooms: [], payments: [] });
  assert.deepEqual(undoRoom(next), []);
  assert.deepEqual(undoRoom([]), []);
});

test('describePlan: 部屋・錠前・支払いを並べる', () => {
  const b = board({ position: 5, locks: { 6: F }, tableau: [card(2, F)], wildUp: 1 });
  assert.equal(describePlan(b, plan([6], [[tab(2)]])), 'Room 6 (Will x1): Composed #2 Will');
  assert.equal(describePlan(b, [{ pos: 6, payments: [] }]), 'Room 6 (Will x1): ...');
  assert.equal(describePlan(b, plan([6], [[TOKEN]])), 'Room 6 (Will x1): Omni-Move Key');
});
