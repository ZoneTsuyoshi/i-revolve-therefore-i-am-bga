// U-10: 精神分析の支払いの仮検証。PHP の AnalysisActionTest（tests/Unit/Flow/AnalysisActionTest.php）の主要ケースと同じ結果になることを確かめる
import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
  REASON, SRC, addPay, analysisFromArgs, canPay, checkAnalysis, emptyState, isComplete, lightChoices, lightCost, maxMatching, payChoices,
  removableKeys, removals, removeKey, toPayment, undo, unpaid,
} from '../../src/modules/js/states/analysis-plan.js';

const W = 'WISDOM';
const F = 'WILL';
const E = 'EMOTION';
const WILD = 'WILD';

const card = (id, ...keys) => ({ id, type: id, keys, tapped: false });
const tapped = (id, ...keys) => ({ ...card(id, ...keys), tapped: true });
/** 62 の args（AnalysisAction::args と同じ形） */
function board({ destinations = [{ id: 'R1A', tier: 1, cost: { [W]: 1 } }], tableau = [], hand = [], light = 0, lightTotals = [1, 3, 6, 10, 15, 21, 28, 36], reduction = 0, keyServes, canAnalyze = true } = {}) {
  let uses = 0;
  while (uses < 8 && lightTotals[uses] <= light) {
    uses++;
  }
  return analysisFromArgs({
    turnPlayer: 99, context: 'FLOOR', discount: 0, reduction, destinations, tableau, light, lightTotals, maxLightUses: uses, canAnalyze,
    ...(keyServes === undefined ? {} : { keyServes }), _private: { 99: { hand } },
  });
}
const tab = (c, key = 0, f = W) => ({ src: 'TABLEAU', card: c, key, for: f });
const hnd = (c, key = 0, f = W) => ({ src: 'HAND', card: c, key, for: f });
const lit = (f = W) => ({ src: 'LIGHT', for: f });
const plan = (keys, { destination = 'R1A', removed = {} } = {}) => ({ destination, removed, keys });
const reason = (b, state) => {
  const r = checkAnalysis(b, state);
  return r.ok ? 'OK' : r.reason;
};

test('analysisFromArgs: 手札は _private[自分] でも _private 直下でもよい。壊れた args でも例外を出さない', () => {
  assert.equal(board({ hand: [card(3, W)] }).hand.get(3).keys[0], W);
  assert.equal(analysisFromArgs({ _private: { hand: [card(4, F)] } }).hand.size, 1);
  const empty = analysisFromArgs(undefined);
  assert.equal(empty.destinations.length, 0);
  assert.equal(empty.canAnalyze, false);
});

test('手札の鍵でも、自己構成済みの縦向きのカードの鍵でも払える（手札は捨て、自己構成済みは横向きにする）', () => {
  const b = board({ tableau: [card(10, W)], hand: [card(1, W)] });
  assert.equal(reason(b, plan([hnd(1)])), 'OK');
  assert.equal(reason(b, plan([tab(10)])), 'OK');
  assert.equal(isComplete(b, plan([hnd(1)])), true);
  assert.equal(isComplete(b, plan([])), false);
});

test('横向きの自己構成済みカードは使えない。同じ鍵は 2 回使えない。コストはちょうど覆う（過払い不可）', () => {
  const b = board({ destinations: [{ id: 'R1A', tier: 1, cost: { [W]: 2 } }], tableau: [tapped(10, W), card(11, W)], hand: [card(1, W, W)] });
  assert.equal(reason(b, plan([tab(10), hnd(1)])), REASON.CARD);
  assert.equal(reason(b, plan([hnd(1, 0), hnd(1, 0)])), REASON.KEY_TWICE);
  assert.equal(reason(b, plan([hnd(1, 0), hnd(1, 1)])), 'OK');
  assert.equal(reason(b, plan([hnd(1, 0)])), REASON.COST);
  assert.equal(reason(b, plan([hnd(1, 0), hnd(1, 1), tab(11)])), REASON.COST);
  assert.equal(reason(b, plan([hnd(1, 5)])), REASON.KEY_INDEX);
  assert.equal(reason(b, plan([hnd(99)])), REASON.CARD);
});

test('万能鍵は何の代わりにもなる。知恵鍵は感情のコストを払えない', () => {
  const wild = board({ destinations: [{ id: 'R1A', tier: 1, cost: { [E]: 1 } }], hand: [card(1, WILD)] });
  assert.equal(reason(wild, plan([hnd(1, 0, E)])), 'OK');
  const wisdom = board({ destinations: [{ id: 'R1A', tier: 1, cost: { [E]: 1 } }], hand: [card(1, W)] });
  assert.equal(reason(wisdom, plan([hnd(1, 0, E)])), REASON.KEY_MISMATCH);
  assert.equal(canPay(wisdom, wisdom.destinations[0]), false);
});

test('M094: 知恵鍵を万能鍵としても見られるなら、keyServes に従って感情のコストを払える', () => {
  const b = board({
    destinations: [{ id: 'R1A', tier: 1, cost: { [E]: 1 } }], hand: [card(1, W)], keyServes: { [W]: [W, E] },
  });
  assert.equal(reason(b, plan([hnd(1, 0, E)])), 'OK');
  assert.equal(canPay(b, b.destinations[0]), true);
});

test('心光の行使は逓増する。持っている心光を超えると拒否', () => {
  const b = board({ destinations: [{ id: 'R1B', tier: 1, cost: { [W]: 2 } }], light: 3 });
  assert.equal(reason(b, plan([lit(), lit()], { destination: 'R1B' })), 'OK');
  assert.equal(lightCost(b, 2), 3);
  const poor = board({ destinations: [{ id: 'R1B', tier: 1, cost: { [W]: 2 } }], light: 2 });
  assert.equal(reason(poor, plan([lit(), lit()], { destination: 'R1B' })), REASON.LIGHT);
  assert.deepEqual(lightChoices(poor, plan([lit()], { destination: 'R1B' })), []);
  assert.deepEqual(lightChoices(b, plan([lit()], { destination: 'R1B' })), [W]);
  assert.deepEqual(lightChoices(b, plan([lit(), lit()], { destination: 'R1B' })), []);
});

test('コストの軽減: 取り除く鍵を選ぶ。合計は min(reduction, コストの総数) ちょうど', () => {
  const b = board({ destinations: [{ id: 'R2', tier: 2, cost: { [W]: 1, [F]: 1, [E]: 1 } }], reduction: 1, hand: [card(1, W), card(2, F)] });
  assert.equal(reason(b, plan([hnd(1, 0, W), hnd(2, 0, F)], { destination: 'R2', removed: { [E]: 1 } })), 'OK');
  assert.equal(reason(b, plan([hnd(1, 0, W), hnd(2, 0, F)], { destination: 'R2', removed: {} })), REASON.REMOVED);
  assert.equal(reason(b, plan([hnd(1, 0, W)], { destination: 'R2', removed: { [E]: 2 } })), REASON.REMOVED);
  assert.equal(reason(b, plan([hnd(1, 0, W), hnd(2, 0, F)], { destination: 'R2', removed: { [W]: 1 } })), REASON.COST);
  assert.deepEqual(removableKeys(b, plan([], { destination: 'R2' })).sort(), [E, F, W].sort());
  assert.deepEqual(removableKeys(b, plan([], { destination: 'R2', removed: { [E]: 1 } })), []);
  // コストの総数より軽減が大きくても、取り除くのはコストの総数まで
  const big = board({ destinations: [{ id: 'R1A', tier: 1, cost: { [W]: 1 } }], reduction: 2 });
  assert.equal(reason(big, plan([], { removed: { [W]: 1 } })), 'OK');
  assert.deepEqual(unpaid(b, plan([hnd(1, 0, W)], { destination: 'R2', removed: { [E]: 1 } })), { [F]: 1 });
});

test('進む先でないカードは拒否される', () => {
  const b = board({ hand: [card(1, W)] });
  assert.equal(reason(b, plan([hnd(1)], { destination: 'R3A' })), REASON.DESTINATION);
  assert.equal(reason(b, emptyState()), REASON.DESTINATION);
});

test('canPay: 鍵と心光で払える先だけ選べる（R5 のコストは各鍵 4 つ）', () => {
  const r5 = { id: 'R5', tier: 5, cost: { [W]: 4, [F]: 4, [E]: 4 } };
  const rich = board({ destinations: [r5], tableau: [card(1, W, W), card(2, W, W), card(3, F, F), card(4, F, F), card(5, E, E), card(6, E, E)] });
  assert.equal(canPay(rich, r5), true);
  const poor = board({ destinations: [r5], tableau: [card(1, W, W)] });
  assert.equal(canPay(poor, r5), false);
  // 足りない分は心光で払える
  const withLight = board({ destinations: [{ id: 'R1A', tier: 1, cost: { [W]: 2 } }], hand: [card(1, W)], light: 1 });
  assert.equal(canPay(withLight, withLight.destinations[0]), true);
  const noLight = board({ destinations: [{ id: 'R1A', tier: 1, cost: { [W]: 2 } }], hand: [card(1, W)] });
  assert.equal(canPay(noLight, noLight.destinations[0]), false);
});

test('canPay は軽減のしかたを選べるとして判定する', () => {
  const dest = { id: 'R2', tier: 2, cost: { [W]: 1, [F]: 1 } };
  const b = board({ destinations: [dest], reduction: 1, hand: [card(1, F)] });
  assert.equal(canPay(b, dest), true);
});

test('maxMatching: 万能鍵は他の鍵に譲って最大数になるよう割り当てる', () => {
  const b = board();
  assert.equal(maxMatching(b, [WILD, W], [W, W]), 2);
  assert.equal(maxMatching(b, [WILD, W], [W, F]), 2);
  assert.equal(maxMatching(b, [W], [F]), 0);
  assert.deepEqual(removals({ [W]: 1, [F]: 2 }, 2), [{ [F]: 2 }, { [W]: 1, [F]: 1 }]);
  assert.deepEqual(removals({ [W]: 1 }, 0), [{}]);
});

test('payChoices: 複数の鍵を持つカードは、鍵ごと・払うコストごとの選択肢になる。使った鍵・横向きのカードは出ない', () => {
  const b = board({ destinations: [{ id: 'R1A', tier: 1, cost: { [W]: 1, [E]: 1 } }], tableau: [card(10, W, E), tapped(11, W)], hand: [card(1, WILD)] });
  const choices = payChoices(b, plan([]));
  assert.deepEqual(choices.filter((c) => c.card === 10).map((c) => [c.key, c.for]), [[0, W], [1, E]]);
  assert.deepEqual(choices.filter((c) => c.card === 1).map((c) => c.for), [W, E]);
  assert.equal(choices.some((c) => c.card === 11), false);
  const after = payChoices(b, plan([tab(10, 0, W)]));
  assert.deepEqual(after.filter((c) => c.card === 10).map((c) => [c.key, c.for]), [[1, E]]);
});

test('addPay・removeKey・undo・toPayment: 支払いを組み立て、取り消し、送る形にする', () => {
  let state = { ...emptyState(), destination: 'R2' };
  state = removeKey(state, E);
  state = addPay(state, { src: SRC.HAND, card: 1, key: 0, for: W });
  state = addPay(state, { src: SRC.LIGHT, for: F });
  assert.deepEqual(toPayment(state), { removed: { [E]: 1 }, keys: [{ src: 'HAND', card: 1, key: 0, for: W }, { src: 'LIGHT', for: F }] });
  state = undo(state);
  assert.equal(state.keys.length, 1);
  state = undo(undo(state));
  assert.equal(state.removed[E], 0);
  assert.equal(undo(state).destination, null);
  assert.deepEqual(toPayment({ ...state, removed: { [E]: 0 } }).removed, {});
});
