// U-10: 自己構成の支払いの仮検証。PHP の ComposeActionTest（tests/Unit/Flow/ComposeActionTest.php）の主要ケースと同じ結果になることを確かめる
import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
  REASON, architectHand, architectTableau, canAddLight, checkPayment, composableIds, composeFromArgs, discardOptions, emptyPayment,
  energyNeed, extraOptions, lightCost, paidEnergy, shortfall, tapOptions, toPayment, toggle,
} from '../../src/modules/js/states/compose-plan.js';

const row = (id, level, need, composable = true) => ({ id, type: id, level, need, composable });

/** 61 の args（ComposeAction::args と同じ形）。心光の逓増は既定で 1, 3, 6, 10, ... */
function args({ context = 'PHASE', hand = [], light = 0, lightTotals = [1, 3, 6, 10, 15, 21, 28, 36], maxLightUses, extra = {} } = {}) {
  let uses = 0;
  while (uses < 8 && lightTotals[uses] <= light) {
    uses++;
  }
  return {
    turnPlayer: 99, context, discount: 0, light, lightTotals, maxLightUses: maxLightUses ?? uses, canCompose: true,
    ...extra, _private: { 99: { hand, ...(extra.architectPairs ? { architectPairs: extra.architectPairs } : {}) } },
  };
}
const pay = (cardId, parts = {}) => ({ ...emptyPayment(), cardId, ...parts });
const reason = (board, payment) => {
  const r = checkPayment(board, payment);
  return r.ok ? 'OK' : r.reason;
};

test('composeFromArgs: 手札の必要エネルギーは _private[自分] でも _private 直下でもよい。壊れた args でも例外を出さない', () => {
  const board = composeFromArgs(args({ hand: [row(5, 2, 3)] }));
  assert.equal(board.hand.get(5).need, 3);
  assert.equal(board.hand.get(5).level, 2);
  assert.equal(composeFromArgs({ context: 'PHASE', _private: { hand: [row(6, 1, 1)] } }).hand.size, 1);
  const empty = composeFromArgs(undefined);
  assert.equal(empty.hand.size, 0);
  assert.equal(empty.canCompose, false);
  assert.equal(empty.paysEnergy, true);
});

test('手札を捨てると、そのカードのレベル分のエネルギーを払う（レベル 2 は 2 つ分。過払い可）', () => {
  const board = composeFromArgs(args({ hand: [row(1, 1, 2), row(2, 1, 0), row(3, 1, 0), row(4, 2, 0)] }));
  assert.equal(reason(board, pay(1, { discard: [2, 3] })), 'OK');
  assert.equal(reason(board, pay(1, { discard: [4] })), 'OK');
  assert.equal(reason(board, pay(1, { discard: [2, 4] })), 'OK');
  assert.equal(paidEnergy(board, pay(1, { discard: [2, 4] })), 3);
  assert.equal(shortfall(board, pay(1, { discard: [2] })), 1);
});

test('エネルギーが足りない・捨てられない手札・自己構成できないカードは拒否される', () => {
  const board = composeFromArgs(args({ hand: [row(1, 1, 2), row(2, 1, 0), row(7, 1, 0, false)] }));
  assert.equal(reason(board, pay(1, { discard: [2] })), REASON.ENERGY);
  assert.equal(reason(board, pay(1, { discard: [1, 2] })), REASON.DISCARD_CARD);
  assert.equal(reason(board, pay(1, { discard: [99] })), REASON.DISCARD_CARD);
  assert.equal(reason(board, pay(1, { discard: [2, 2] })), REASON.DISCARD_CARD);
  assert.equal(reason(board, pay(7)), REASON.CARD);
  assert.equal(reason(board, pay(null)), REASON.CARD);
  assert.deepEqual(composableIds(board), [1, 2]);
});

test('必要エネルギーが 0 なら何も払わなくてよい（軽減で 0 未満にならない）', () => {
  const board = composeFromArgs(args({ hand: [row(1, 1, 0)] }));
  assert.equal(reason(board, pay(1)), 'OK');
  assert.equal(energyNeed(board, pay(1)), 0);
});

test('心光の行使は逓増する（1 回目は 1 個、2 回目までで 3 個）。持っている心光を超えると拒否', () => {
  const board = composeFromArgs(args({ hand: [row(1, 1, 2)], light: 3 }));
  assert.equal(reason(board, pay(1, { light: 2 })), 'OK');
  assert.equal(lightCost(board, 2), 3);
  assert.equal(lightCost(board, 0), 0);
  assert.equal(canAddLight(board, pay(1, { light: 1 })), true);
  assert.equal(canAddLight(board, pay(1, { light: 2 })), false);
  const poor = composeFromArgs(args({ hand: [row(1, 1, 2)], light: 2 }));
  assert.equal(reason(poor, pay(1, { light: 2 })), REASON.LIGHT);
  assert.equal(canAddLight(poor, pay(1, { light: 1 })), false);
});

test('手札の捨てと心光は組み合わせられる', () => {
  const board = composeFromArgs(args({ hand: [row(1, 1, 2), row(2, 1, 0)], light: 1 }));
  assert.equal(reason(board, pay(1, { discard: [2], light: 1 })), 'OK');
  assert.equal(reason(board, pay(1, { discard: [2] })), REASON.ENERGY);
});

test('M070 で心光の逓増が軽くなる（lightTotals が変わる）', () => {
  const board = composeFromArgs(args({ hand: [row(1, 1, 3)], light: 3, lightTotals: [1, 2, 3, 4, 5, 6, 7, 8] }));
  assert.equal(reason(board, pay(1, { light: 3 })), 'OK');
  assert.equal(lightCost(board, 3), 3);
});

test('エネルギーを払わない文脈（ボーナス・M089）では、何を払っても拒否される', () => {
  const board = composeFromArgs(args({ context: 'BONUS_LV1', hand: [row(1, 1, 0), row(2, 1, 0)] }));
  assert.equal(board.paysEnergy, false);
  assert.equal(reason(board, pay(1)), 'OK');
  assert.equal(reason(board, pay(1, { discard: [2] })), REASON.NOT_PAYING);
  assert.equal(reason(board, pay(1, { light: 1 })), REASON.NOT_PAYING);
  assert.deepEqual(discardOptions(board, pay(1)), []);
  assert.equal(canAddLight(board, pay(1)), false);
});

test('M095: 感情鍵のカードを横向きにすると、1 枚につきエネルギー -1（必要エネルギーを超える枚数は不可）', () => {
  const board = composeFromArgs(args({ hand: [row(1, 1, 2)], extra: { tapCandidates: [10, 11, 12] } }));
  assert.equal(reason(board, pay(1, { tap: [10, 11] })), 'OK');
  assert.equal(energyNeed(board, pay(1, { tap: [10, 11] })), 0);
  assert.equal(reason(board, pay(1, { tap: [10] })), REASON.ENERGY);
  assert.equal(reason(board, pay(1, { tap: [10, 11, 12] })), REASON.TAP_COUNT);
  assert.equal(reason(board, pay(1, { tap: [10, 10] })), REASON.TAP);
  assert.equal(reason(board, pay(1, { tap: [99] })), REASON.TAP);
  assert.deepEqual(tapOptions(board, pay(1)), [10, 11, 12]);
  // 必要エネルギーの枚数に達したら、選択済みの分だけ（外すことはできる）
  assert.deepEqual(tapOptions(board, pay(1, { tap: [10, 11] })), [10, 11]);
  // M095 が無ければ候補が空で、横向きは拒否される
  const none = composeFromArgs(args({ hand: [row(1, 1, 2)] }));
  assert.equal(reason(none, pay(1, { tap: [10] })), REASON.TAP);
  assert.deepEqual(tapOptions(none, pay(1)), []);
});

test('I08: 追加で払う手札は上限まで。捨てるカードとは別で、エネルギーには数えない', () => {
  const board = composeFromArgs(args({ hand: [row(1, 1, 1), row(2, 1, 0), row(3, 1, 0), row(4, 1, 0)], extra: { extraMax: 2 } }));
  assert.equal(reason(board, pay(1, { discard: [2], extra: [3, 4] })), 'OK');
  assert.equal(reason(board, pay(1, { discard: [2], extra: [3, 4, 2] })), REASON.EXTRA_MAX);
  assert.equal(reason(board, pay(1, { discard: [2], extra: [2] })), REASON.EXTRA_CARD);
  assert.equal(reason(board, pay(1, { discard: [2], extra: [1] })), REASON.EXTRA_CARD);
  assert.equal(reason(board, pay(1, { extra: [3] })), REASON.ENERGY);
  assert.deepEqual(extraOptions(board, pay(1, { discard: [2] })), [3, 4]);
  assert.deepEqual(discardOptions(board, pay(1, { extra: [3] })), [2, 4]);
  const none = composeFromArgs(args({ hand: [row(1, 1, 0), row(2, 1, 0)] }));
  assert.equal(reason(none, pay(1, { extra: [2] })), REASON.EXTRA_MAX);
  assert.deepEqual(extraOptions(none, pay(1)), []);
});

test('I08 の追加支払いは、エネルギーを払わない文脈でもできる（FAQ 行 38）', () => {
  const board = composeFromArgs(args({ context: 'BONUS_ANY', hand: [row(1, 1, 0), row(2, 1, 0)], extra: { extraMax: 1 } }));
  assert.equal(reason(board, pay(1, { extra: [2] })), 'OK');
});

test('I03: 入れ替えられる組は _private の architectPairs から。自己構成済みごとに手札の候補が決まる', () => {
  const board = composeFromArgs(args({ hand: [row(1, 1, 1)], extra: { architectPairs: [[20, 1], [20, 2], [21, 2]] } }));
  assert.deepEqual(architectTableau(board), [20, 21]);
  assert.deepEqual(architectHand(board, 20), [1, 2]);
  assert.deepEqual(architectHand(board, 21), [2]);
  assert.deepEqual(architectHand(board, 99), []);
});

test('toPayment は送る形（discard・light・tap・extra）。toggle は含まれていれば外す', () => {
  assert.deepEqual(toPayment(pay(1, { discard: [2], light: 1, tap: [3], extra: [4] })), { discard: [2], light: 1, tap: [3], extra: [4] });
  assert.deepEqual(toggle([1, 2], 2), [1]);
  assert.deepEqual(toggle([1], 5), [1, 5]);
});
