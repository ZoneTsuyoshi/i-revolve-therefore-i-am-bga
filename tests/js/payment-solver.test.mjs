// 支払いの自動割り当て（まとめて選んだ資源 → 各錠前・コストへの割り当て）。
// サーバーの検証（MovePlanner・AnalysisPlanner・ComposePlanner）と同じ規則の仮検証（checkPlan・checkAnalysis・checkPayment）に通ること、
// 払える選び方なら必ず見つけ、払えない選び方では「不足」を正しく出すことを確かめる。
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { analysisFromArgs, checkAnalysis, solveAnalysisPayment, toPayment as analysisPayment } from '../../src/modules/js/states/analysis-plan.js';
import { checkPayment, composeFromArgs, solveComposePayment, toPayment as composePayment } from '../../src/modules/js/states/compose-plan.js';
import {
  REASON, boardFromArgs, checkPlan, lockTotal, payOptions, pendingRoom, reachableRooms, routeExtras, solveMovePayment, toInput,
} from '../../src/modules/js/states/move-plan.js';
import { stepQuantity } from '../../src/modules/js/states/payment-ui.js';

const W = 'WISDOM';
const F = 'WILL';
const E = 'EMOTION';
const WILD = 'WILD';

// ---- 移動 ----

const BONUS = { 1: 'ANALYSIS_TOKEN', 4: 'LIGHT', 7: 'WILD_MOVE_KEY', 10: 'COMPOSE_TOKEN' };
const SERVES = { WISDOM: [W], WILL: [F], EMOTION: [E], WILD: [W, F, E] };
const card = (id, ...keys) => ({ id, type: id, keys, tapped: false });
const tapped = (id, ...keys) => ({ ...card(id, ...keys), tapped: true });

function board({ position = 1, locks = {}, hand = [], tableau = [], light = 0, wildUp = 0, lockReduction = 0, keyServes = SERVES, lightCosts = [1, 2, 3, 4, 5, 6], canMove = true } = {}) {
  const rooms = [];
  for (let pos = 1; pos <= 12; pos++) {
    rooms.push({ pos, level: [1, 4, 7, 10].includes(pos) ? 2 : 1, lock: locks[pos] ?? W, bonus: BONUS[pos] ?? 'NONE' });
  }
  return boardFromArgs({
    position, rooms, maxRooms: 24, canMove, resources: { light, wildUp, lightCosts, lockReduction }, keyServes, tableau, _private: { 99: { hand } },
  });
}

/** 選んだ資源のうち、与えた全部を選んだことにする */
const all = (b, extra = {}) => ({ tableau: [...b.tableau.keys()], hand: [...b.hand.keys()], light: 9, token: 9, effect: 0, ...extra });

test('移動: 順不同にまとめて選んだ鍵が、錠前の種類に合わせて自動で割り当てられる', () => {
  // 位置 5 → 6（意志）→ 7（レベル 2: 知恵・知恵）。選ぶ順は問わない
  const b = board({ position: 5, locks: { 6: F, 7: W }, tableau: [card(1, W), card(2, F)], hand: [card(3, W)] });
  const result = solveMovePayment(b, [6, 7], { tableau: [1, 2], hand: [3] });
  assert.equal(result.complete, true);
  assert.equal(result.paid, 3);
  assert.deepEqual(result.steps[0].payments, [{ src: 'TABLEAU', card: 2, key: 0 }]);
  assert.deepEqual(result.steps[1].payments.map((p) => p.card).sort(), [1, 3]);
  assert.equal(checkPlan(b, result.steps).ok, true);
  assert.deepEqual(toInput(result.steps).rooms, [6, 7]);
});

test('移動: 鍵の種類が合わなければ不足を出す（割り当て不能）', () => {
  const b = board({ position: 5, locks: { 6: E }, tableau: [card(1, W), card(2, F)] });
  const result = solveMovePayment(b, [6], { tableau: [1, 2] });
  assert.equal(result.complete, false);
  assert.equal(result.paid, 0);
  assert.deepEqual(result.needed, { [E]: 1 });
  assert.deepEqual(result.unused.tableau.sort(), [1, 2]);
  // 万能鍵なら何でも払える
  assert.equal(solveMovePayment(board({ position: 5, locks: { 6: E }, tableau: [card(1, WILD)] }), [6], { tableau: [1] }).complete, true);
});

test('移動: 足りなければ払えた分と、足りない錠前の種類を出す', () => {
  const b = board({ position: 3, locks: { 4: F, 5: W }, tableau: [card(1, F)] });
  // 4 はレベル 2 の意志（2 個）、5 は知恵 1 個。意志の鍵 1 本だけ選んだ
  const result = solveMovePayment(b, [4, 5], { tableau: [1] });
  assert.equal(result.complete, false);
  assert.equal(result.total, 3);
  assert.equal(result.paid, 1);
  assert.deepEqual(result.needed, { [F]: 1, [W]: 1 });
});

test('移動: 複数鍵カードは分割できない（離れた入室には使えない）が、連続する入室なら分割できる（Q-07）', () => {
  // 6・7・8 の順。カード 1 の知恵 2 本を 6 と 8 に分けると分割禁止。7 はレベル 2 なので 7 に両方使うか、6・7 に分ける
  const b = board({ position: 5, tableau: [card(1, W, W), card(2, W)], wildUp: 0 });
  const result = solveMovePayment(b, [6, 7, 8], { tableau: [1, 2] });
  // 錠前は 1 + 2 + 1 = 4 個、鍵は 3 本: 足りない
  assert.equal(result.complete, false);
  assert.equal(result.paid, 3);
  assert.equal(result.needed[W], 1);
  // 6・7 だけなら: カード 1 を 6 と 7 に分割、カード 2 を 7 に。連続なので可
  const ok = solveMovePayment(b, [6, 7], { tableau: [1, 2] });
  assert.equal(ok.complete, true);
  assert.equal(checkPlan(b, ok.steps).ok, true);
  // 離れた 6 と 8 に鍵 2 本のカードしか無いとき: 分割禁止で払えない（スキップした 7 は間にある入室ではないが、6 と 8 は連続した入室）
  const split = board({ position: 5, tableau: [card(1, W, W), card(2, W, W)] });
  const far = solveMovePayment(split, [6, 7, 8], { tableau: [1] });
  assert.equal(far.complete, false);
  // 6 に 1 本、7 に 1 本ならカード 1 と、7 のもう 1 本をトークンで
  const mixed = solveMovePayment(board({ position: 5, tableau: [card(1, W, W)], wildUp: 5 }), [6, 7, 8], { tableau: [1], token: 5 });
  assert.equal(mixed.complete, true);
  assert.equal(checkPlan(board({ position: 5, tableau: [card(1, W, W)], wildUp: 5 }), mixed.steps).ok, true);
});

test('移動: 分割禁止のため、隙間を空ける割り当ては選ばない（鍵 3 本のうち離れた使い方になる配置は不可）', () => {
  // カード 1 は知恵 2 本。入室 6（1 個）・7（2 個）・8（1 個）の 6 と 8 にしか使えない配置なら不可、7 に 2 本なら可
  const b = board({ position: 5, tableau: [card(1, W, W)], wildUp: 2 });
  const result = solveMovePayment(b, [6, 7, 8], { tableau: [1], token: 2 });
  assert.equal(result.complete, true);
  const cardSteps = result.steps.map((s, i) => (s.payments.some((p) => p.card === 1) ? i : -1)).filter((i) => i >= 0);
  assert.ok(cardSteps.every((v, i) => i === 0 || v === cardSteps[i - 1] + 1), `連続: ${cardSteps}`);
});

test('移動: 心光は行使ごとに逓増し、選んだ回数まで。周遊ボーナスの心光を後の部屋で使える', () => {
  // 位置 3 → 4（周遊ボーナス: 心光。レベル 2）→ 5。心光 1 個 + 4 の分は入室後に得る
  const b = board({ position: 3, light: 3, locks: { 4: W } });
  const noKeys = solveMovePayment(b, [4], { light: 2 });
  assert.equal(noKeys.complete, true, '1 + 2 = 3 個で 2 回');
  assert.equal(noKeys.steps[0].payments.length, 2);
  const short = solveMovePayment(board({ position: 3, light: 2 }), [4], { light: 2 });
  assert.equal(short.complete, false);
  assert.equal(short.paid, 1);
  // 4 に入って得た心光は 5 で使える（4 は鍵、5 は心光）
  const bonus = board({ position: 3, light: 0, tableau: [card(1, W, W)] });
  const result = solveMovePayment(bonus, [4, 5], { tableau: [1], light: 1 });
  assert.equal(result.complete, true);
  assert.deepEqual(result.steps[1].payments, [{ src: 'LIGHT' }]);
  // 自身の部屋のボーナスは自分の支払いには使えない
  assert.equal(solveMovePayment(board({ position: 3, light: 0, tableau: [card(1, W)] }), [4], { tableau: [1], light: 1 }).complete, false);
});

test('移動: 万能移動鍵トークンは持つ数まで。選んだのに使わなければ余りに出す', () => {
  const b = board({ position: 4, wildUp: 1 });
  assert.equal(solveMovePayment(b, [5], { token: 1 }).complete, true);
  const none = solveMovePayment(board({ position: 4, wildUp: 0 }), [5], { token: 1 });
  assert.equal(none.complete, false);
  assert.equal(none.unused.token, 1);
  const surplus = solveMovePayment(board({ position: 4, wildUp: 2, tableau: [card(1, W)] }), [5], { tableau: [1], token: 2 });
  assert.equal(surplus.complete, true);
  assert.equal(surplus.unused.token, 2, '鍵で払えるのでトークンは使わない（高価な資源を使わない）');
});

test('移動: 高価な資源（心光・トークン）より鍵を先に使う。余りが少ない選び方を優先する', () => {
  const b = board({ position: 4, light: 1, wildUp: 1, tableau: [card(1, W)], hand: [card(2, W)] });
  // 自己構成済みと手札の両方を選んだ → 錠前 1 個。使うのは自己構成済み（手札は捨てずに済む）
  const result = solveMovePayment(b, [5], { tableau: [1], hand: [2] });
  assert.equal(result.complete, true);
  assert.deepEqual(result.steps[0].payments, [{ src: 'TABLEAU', card: 1, key: 0 }]);
  assert.deepEqual(result.unused.hand, [2]);
});

test('移動: 周遊ボーナス（スキップした部屋のボーナスは次の入室の支払いより先）', () => {
  assert.equal(solveMovePayment(board({ position: 6 }), [8], { token: 1 }).complete, true);
  assert.equal(solveMovePayment(board({ position: 6 }), [7], { token: 2 }).complete, false);
});

test('移動: 錠前の軽減（MD-06）は最初のレベル 2 の入室の錠前だけを減らす', () => {
  const b = board({ position: 3, lockReduction: 1, tableau: [card(1, W)] });
  const result = solveMovePayment(b, [4], { tableau: [1] });
  assert.equal(result.complete, true);
  assert.equal(result.total, 1, '軽減後の錠前の数');
  assert.deepEqual(result.steps[0].payments, [{ src: 'TABLEAU', card: 1, key: 0 }]);
  assert.equal(checkPlan(b, result.steps).ok, true);
  assert.equal(lockTotal(b, [4]), 1);
  // 2 枚分の軽減（錠前 0 個）は何も選ばずに完了する
  const zero = board({ position: 3, lockReduction: 2 });
  const free = solveMovePayment(zero, [4], {});
  assert.equal(free.complete, true);
  assert.deepEqual(free.steps, [{ pos: 4, payments: [] }]);
  assert.equal(lockTotal(zero, [4]), 0);
  // レベル 1 の部屋は減らない
  assert.equal(lockTotal(board({ position: 4, lockReduction: 1 }), [5]), 1);
  // 2 つ目のレベル 2 は減らない: 4（-1 → 1 個）+ 5 + 6 + 7（2 個）
  const later = board({ position: 3, lockReduction: 1, light: 30 });
  assert.equal(lockTotal(later, [4, 5, 6, 7]), 1 + 1 + 1 + 2);
  // 錠前 0 個の部屋の周遊ボーナス（4 は心光）は、次の部屋の支払いに使える
  const chain = board({ position: 3, lockReduction: 2 });
  assert.equal(solveMovePayment(chain, [4, 5], { light: 1 }).complete, true);
  // 軽減のある盤では、軽減の無い盤で払えなかった部屋を選べる
  assert.deepEqual(reachableRooms(board({ position: 3, lockReduction: 2 }), []), [4, 5]);
  assert.deepEqual(reachableRooms(board({ position: 3, lockReduction: 0, tableau: [card(5, W)] }), []), [5]);
});

test('移動: 使用済み・選んでいないカードは使わない。空の経路・崩れた経路は完了にならない', () => {
  const b = board({ position: 5, tableau: [tapped(1, W), card(2, W)] });
  assert.equal(solveMovePayment(b, [6], { tableau: [1] }).complete, false);
  assert.equal(solveMovePayment(b, [6], {}).complete, false);
  assert.equal(solveMovePayment(b, [6], { tableau: [2] }).complete, true);
  assert.equal(solveMovePayment(b, [], { tableau: [2] }).complete, false);
  assert.equal(solveMovePayment(b, [9], { tableau: [2] }).complete, false);
  assert.equal(lockTotal(b, [6, 7]), 3);
});

test('移動: M096 のような keyServes の読み替えを使う', () => {
  const b = board({ position: 5, locks: { 6: W }, tableau: [card(1, F)], keyServes: { ...SERVES, WILL: [F, W] } });
  assert.equal(solveMovePayment(b, [6], { tableau: [1] }).complete, true);
});

test('移動: reachableRooms は持っている資源をすべて使っても払えない部屋を選べなくする', () => {
  const b = board({ position: 3, tableau: [card(5, W)] });
  assert.deepEqual(reachableRooms(b, []), [5], '4 は錠前 2 個で鍵 1 本では払えない');
  assert.deepEqual(reachableRooms(board({ position: 3, tableau: [card(5, W, W)] }), []), [4, 5]);
  assert.deepEqual(reachableRooms(board({ position: 2, canMove: false }), []), []);
  // 位置 2 で 3 に入ったあと: 4 は錠前 2 個で払えない。4 を飛ばして 5 に入れば、飛ばした 4 の周遊ボーナス（心光）で払える
  assert.deepEqual(reachableRooms(board({ position: 2, tableau: [card(1, W)] }), [3]), [5]);
});

test('移動: routeExtras は経路の周遊ボーナスの数を返す', () => {
  const b = board({ position: 3, tableau: [card(1, W), card(2, W), card(3, W)] });
  assert.deepEqual(routeExtras(b, [4, 5]), { light: 1, token: 0 });
  assert.deepEqual(routeExtras(b, [5]), { light: 1, token: 0 }, '4 はスキップだが周遊ボーナスは得る');
  assert.equal(routeExtras(board({ position: 5 }), [6]).token, 0);
  assert.equal(routeExtras(board({ position: 5 }), [6, 7, 8]).token, 1, '7 に入る（万能移動鍵）');
  assert.equal(routeExtras(board({ position: 5 }), [6, 8]).token, 1, '7 をスキップしても得る');
});

test('stepQuantity: 数量を 0 以上、上限以下に保つ', () => {
  assert.equal(stepQuantity(0, -1, 3), 0);
  assert.equal(stepQuantity(3, 1, 3), 3);
  assert.equal(stepQuantity(1, 1, 3), 2);
});

/** サーバーの検証と同じ規則（仮検証 checkPlan と payOptions）で、選んだ資源だけを使って払い切れる計画があるかを全探索で確かめる */
function oracle(b, route) {
  const rec = (steps) => {
    if (pendingRoom(b, steps) !== null) {
      return payOptions(b, steps).some((item) => rec(steps.map((s, i) => (i === steps.length - 1 ? { pos: s.pos, payments: [...s.payments, item] } : s))));
    }
    if (steps.length === route.length) {
      return checkPlan(b, steps).ok;
    }
    return rec([...steps, { pos: route[steps.length], payments: [] }]);
  };
  return rec([]);
}

test('移動: 乱択の盤で、割り当ての探索の結果が checkPlan による全探索と一致する', () => {
  let seed = 12345;
  const rand = (n) => {
    seed = (seed * 1103515245 + 12345) & 0x7fffffff;
    return seed % n;
  };
  const kinds = [W, F, E, WILD];
  let completes = 0;
  let incompletes = 0;
  for (let round = 0; round < 250; round++) {
    const position = 1 + rand(12);
    const locks = {};
    for (let pos = 1; pos <= 12; pos++) {
      locks[pos] = kinds[rand(3)];
    }
    const makeCards = (n, base) => Array.from({ length: n }, (_v, i) => card(base + i, ...Array.from({ length: 1 + rand(2) }, () => kinds[rand(4)])));
    const b = board({ position, locks, tableau: makeCards(rand(4), 10), hand: makeCards(rand(3), 20), light: rand(5), wildUp: rand(3) });
    // 経路: 次の部屋から 1〜3 部屋（レベル 2 の次の部屋はときどきスキップ）
    const route = [];
    let pos = position;
    for (let i = 0, n = 1 + rand(3); i < n; i++) {
      let next = (pos % 12) + 1;
      if (b.rooms.get(next).level === 2 && rand(3) === 0) {
        next = (next % 12) + 1;
      }
      route.push(next);
      pos = next;
    }
    const selection = all(b);
    const result = solveMovePayment(b, route, selection);
    assert.equal(result.aborted, false);
    assert.equal(result.complete, oracle(b, route), `round ${round} route ${route} ${JSON.stringify([...b.tableau.values()])}`);
    if (result.complete) {
      completes++;
      assert.equal(checkPlan(b, result.steps).ok, true);
    } else {
      incompletes++;
      assert.equal(result.paid < result.total, true);
      const shortage = Object.values(result.needed).reduce((sum, n) => sum + n, 0);
      assert.equal(shortage, result.total - result.paid);
    }
  }
  assert.ok(completes > 20 && incompletes > 20, `${completes} / ${incompletes}`);
});

test('移動: 割り当て不能の理由は仮検証（分割禁止・鍵の種類）と同じ', () => {
  // 手で割り当てると分割禁止になる計画が、そのまま checkPlan で拒否されることの確認（探索はこれを選ばない）
  const b = board({ position: 5, tableau: [card(1, W, W), card(2, W)] });
  const manual = [
    { pos: 6, payments: [{ src: 'TABLEAU', card: 1, key: 0 }] },
    { pos: 7, payments: [{ src: 'TABLEAU', card: 2, key: 0 }, { src: 'TABLEAU', card: 1, key: 1 }] },
  ];
  assert.equal(checkPlan(b, manual).ok, true, '連続なら可');
  const apart = [
    { pos: 6, payments: [{ src: 'TABLEAU', card: 1, key: 0 }] },
    { pos: 7, payments: [{ src: 'TABLEAU', card: 2, key: 0 }, { src: 'TABLEAU', card: 2, key: 0 }] },
  ];
  assert.equal(checkPlan(b, apart).ok, false);
  assert.equal(checkPlan(board({ position: 5, locks: { 6: E }, tableau: [card(1, W)] }), [{ pos: 6, payments: [{ src: 'TABLEAU', card: 1, key: 0 }] }]).reason, REASON.KEY_MISMATCH);
});

// ---- 精神分析 ----

function analysisBoard({ destinations = [{ id: 'R1A', tier: 1, cost: { [W]: 1 } }], tableau = [], hand = [], light = 0, lightTotals = [1, 3, 6, 10, 15, 21, 28, 36], reduction = 0, keyServes } = {}) {
  let uses = 0;
  while (uses < 8 && lightTotals[uses] <= light) {
    uses++;
  }
  return analysisFromArgs({
    turnPlayer: 99, context: 'FLOOR', reduction, destinations, tableau, light, lightTotals, maxLightUses: uses, canAnalyze: true,
    ...(keyServes === undefined ? {} : { keyServes }), _private: { 99: { hand } },
  });
}

test('精神分析: 選んだ鍵がコストの種類に自動で割り当てられ、送る形（removed・keys）が検証を通る', () => {
  const b = analysisBoard({ destinations: [{ id: 'R2A', tier: 2, cost: { [W]: 1, [F]: 1, [E]: 1 } }], tableau: [card(1, E), card(2, W)], hand: [card(3, F)] });
  const result = solveAnalysisPayment(b, 'R2A', { tableau: [1, 2], hand: [3] });
  assert.equal(result.complete, true);
  assert.equal(result.paid, 3);
  assert.equal(checkAnalysis(b, result.state).ok, true);
  const payment = analysisPayment(result.state);
  assert.deepEqual(payment.keys.map((k) => `${k.card}:${k.for}`).sort(), [`1:${E}`, `2:${W}`, `3:${F}`].sort());
});

test('精神分析: 種類が合わない鍵だけでは不足を出す。万能鍵は何にでも充てる', () => {
  const b = analysisBoard({ destinations: [{ id: 'R1A', tier: 1, cost: { [F]: 2 } }], tableau: [card(1, W), card(2, F), card(3, WILD)] });
  const result = solveAnalysisPayment(b, 'R1A', { tableau: [1, 2] });
  assert.equal(result.complete, false);
  assert.deepEqual(result.needed, { [F]: 1 });
  assert.deepEqual(result.unused.tableau, [1]);
  assert.equal(solveAnalysisPayment(b, 'R1A', { tableau: [2, 3] }).complete, true);
});

test('精神分析: 軽減で取り除く鍵は自動で選び、払えるほうに寄せる（M094 の軽減）', () => {
  const b = analysisBoard({ destinations: [{ id: 'R2A', tier: 2, cost: { [W]: 1, [F]: 1 } }], reduction: 1, tableau: [card(1, F)] });
  const result = solveAnalysisPayment(b, 'R2A', { tableau: [1] });
  assert.equal(result.complete, true);
  assert.deepEqual(result.state.removed, { [W]: 1 });
  assert.equal(checkAnalysis(b, result.state).ok, true);
  // コストが全部取り除かれるなら、何も選ばなくても完了
  const free = analysisBoard({ reduction: 5 });
  assert.equal(solveAnalysisPayment(free, 'R1A', {}).complete, true);
});

test('精神分析: 心光は選んだ回数まで、逓増の総数が持っている心光以内', () => {
  const b = analysisBoard({ destinations: [{ id: 'R1A', tier: 1, cost: { [W]: 2 } }], light: 3 });
  const two = solveAnalysisPayment(b, 'R1A', { light: 2 });
  assert.equal(two.complete, true, '1 + 2 = 3');
  assert.equal(analysisPayment(two.state).keys.length, 2);
  assert.equal(solveAnalysisPayment(analysisBoard({ destinations: [{ id: 'R1A', tier: 1, cost: { [W]: 2 } }], light: 2 }), 'R1A', { light: 2 }).complete, false);
  // 鍵で払えるところは鍵を先に使う
  const mixed = analysisBoard({ destinations: [{ id: 'R1A', tier: 1, cost: { [W]: 2 } }], light: 3, tableau: [card(1, W)] });
  const result = solveAnalysisPayment(mixed, 'R1A', { tableau: [1], light: 2 });
  assert.equal(result.complete, true);
  assert.equal(result.unused.light, 1);
});

test('精神分析: 使用済み・選んでいないカードは使わない。進む先が無ければ完了にならない', () => {
  const b = analysisBoard({ tableau: [tapped(1, W), card(2, W)] });
  assert.equal(solveAnalysisPayment(b, 'R1A', { tableau: [1] }).complete, false);
  assert.equal(solveAnalysisPayment(b, 'R1A', {}).complete, false);
  assert.equal(solveAnalysisPayment(b, 'R1A', { tableau: [2] }).complete, true);
  assert.equal(solveAnalysisPayment(b, null, { tableau: [2] }).complete, false);
});

test('精神分析: 乱択の盤で、割り当ての探索の結果が maxMatching・canPay の判定と一致する', async () => {
  const { canPay } = await import('../../src/modules/js/states/analysis-plan.js');
  let seed = 777;
  const rand = (n) => {
    seed = (seed * 1103515245 + 12345) & 0x7fffffff;
    return seed % n;
  };
  const kinds = [W, F, E, WILD];
  let ok = 0;
  let ng = 0;
  for (let round = 0; round < 200; round++) {
    const cost = {};
    for (const kind of [W, F, E]) {
      if (rand(2) === 0) {
        cost[kind] = 1 + rand(2);
      }
    }
    if (Object.keys(cost).length === 0) {
      cost[W] = 1;
    }
    const makeCards = (n, base) => Array.from({ length: n }, (_v, i) => card(base + i, ...Array.from({ length: 1 + rand(2) }, () => kinds[rand(4)])));
    const b = analysisBoard({
      destinations: [{ id: 'R2A', tier: 2, cost }], tableau: makeCards(rand(4), 10), hand: makeCards(rand(3), 20), light: rand(7), reduction: rand(2),
    });
    const result = solveAnalysisPayment(b, 'R2A', { tableau: [...b.tableau.keys()], hand: [...b.hand.keys()], light: b.maxLightUses });
    assert.equal(result.complete, canPay(b, b.destinations[0]), `round ${round}`);
    if (result.complete) {
      ok++;
      assert.equal(checkAnalysis(b, result.state).ok, true);
    } else {
      ng++;
    }
  }
  assert.ok(ok > 20 && ng > 20, `${ok} / ${ng}`);
});

// ---- 自己構成 ----

const row = (id, level, need, composable = true) => ({ id, type: id, level, need, composable });
function composeArgs({ context = 'PHASE', hand = [], light = 0, lightTotals = [1, 3, 6, 10, 15, 21, 28, 36], tapCandidates = [], extraMax = 0 } = {}) {
  let uses = 0;
  while (uses < 8 && lightTotals[uses] <= light) {
    uses++;
  }
  return {
    turnPlayer: 99, context, light, lightTotals, maxLightUses: uses, canCompose: true, tapCandidates, extraMax, _private: { 99: { hand } },
  };
}

test('自己構成: 選んだ手札・心光から、ちょうど払える組み合わせを自動で選ぶ', () => {
  const b = composeFromArgs(composeArgs({ hand: [row(1, 1, 3), row(2, 1, 0), row(3, 2, 0), row(4, 1, 0)] }));
  // 3 エネルギー: レベル 2 の手札（2）+ レベル 1 の手札（1）= 3。残りの手札は余り
  const result = solveComposePayment(b, 1, { hand: [2, 3, 4] });
  assert.equal(result.complete, true);
  assert.equal(result.paid, 3);
  assert.equal(result.payment.discard.reduce((sum, id) => sum + b.hand.get(id).level, 0), 3);
  assert.equal(result.unused.hand.length, 1);
  assert.equal(checkPayment(b, result.payment).ok, true);
  assert.deepEqual(Object.keys(composePayment(result.payment)).sort(), ['discard', 'extra', 'light', 'tap']);
});

test('自己構成: 足りなければ不足のエネルギーを出す。選ばなければ全部足りない', () => {
  const b = composeFromArgs(composeArgs({ hand: [row(1, 1, 3), row(2, 1, 0)] }));
  const some = solveComposePayment(b, 1, { hand: [2] });
  assert.equal(some.complete, false);
  assert.equal(some.shortfall, 2);
  assert.equal(some.paid, 1);
  assert.equal(solveComposePayment(b, 1, {}).shortfall, 3);
});

test('自己構成: 心光は逓増の総数が持っている心光以内の回数まで。手札を優先して、足りない分だけ心光を使う', () => {
  const b = composeFromArgs(composeArgs({ hand: [row(1, 1, 3), row(2, 1, 0)], light: 3 }));
  const result = solveComposePayment(b, 1, { hand: [2], light: 2 });
  assert.equal(result.complete, true, '手札 1 + 心光 2 回（1 + 2 = 3 個）');
  assert.equal(result.payment.light, 2);
  const poor = composeFromArgs(composeArgs({ hand: [row(1, 1, 3), row(2, 1, 0)], light: 1 }));
  const bad = solveComposePayment(poor, 1, { hand: [2], light: 2 });
  assert.equal(bad.complete, false);
  assert.equal(bad.payment.light, 1);
  assert.equal(bad.shortfall, 1);
});

test('自己構成: 過払いは許すが、余りが少ない選び方を優先する（Q-22）', () => {
  const b = composeFromArgs(composeArgs({ hand: [row(1, 1, 1), row(2, 1, 0), row(3, 2, 0)] }));
  const result = solveComposePayment(b, 1, { hand: [2, 3] });
  assert.equal(result.complete, true);
  assert.deepEqual(result.payment.discard, [2], '1 エネルギーならレベル 1 の手札で足りる');
  assert.deepEqual(result.unused.hand, [3]);
});

test('自己構成: M095 の横向きは必要エネルギーを超えない枚数まで、1 枚につきエネルギー -1', () => {
  const b = composeFromArgs(composeArgs({ hand: [row(1, 1, 2), row(2, 1, 0)], tapCandidates: [10, 11, 12] }));
  const result = solveComposePayment(b, 1, { hand: [2], tap: [10, 11, 12] });
  assert.equal(result.complete, true);
  assert.equal(result.payment.tap.length + result.payment.discard.length, 2);
  assert.equal(checkPayment(b, result.payment).ok, true);
  const onlyTap = solveComposePayment(b, 1, { tap: [10, 11, 12] });
  assert.equal(onlyTap.complete, true);
  assert.equal(onlyTap.payment.tap.length, 2);
  assert.deepEqual(onlyTap.unused.tap, [12]);
  // 候補にないカードは横向きにできない
  assert.equal(solveComposePayment(b, 1, { tap: [99] }).complete, false);
});

test('自己構成: エネルギーを払わない文脈では何も選ばなくても完了。自己構成するカード自身は捨てない', () => {
  const free = composeFromArgs(composeArgs({ context: 'BONUS_LV1', hand: [row(1, 1, 0), row(2, 1, 0)] }));
  const result = solveComposePayment(free, 1, { hand: [2], light: 3 });
  assert.equal(result.complete, true);
  assert.deepEqual(result.payment.discard, []);
  const b = composeFromArgs(composeArgs({ hand: [row(1, 1, 1)] }));
  assert.equal(solveComposePayment(b, 1, { hand: [1] }).complete, false);
  assert.equal(solveComposePayment(b, null, {}).complete, false);
});

test('自己構成: I08 の追加支払いは別に選び、エネルギーには数えない', () => {
  const b = composeFromArgs(composeArgs({ hand: [row(1, 1, 1), row(2, 1, 0), row(3, 1, 0)], extraMax: 1 }));
  const result = solveComposePayment(b, 1, { hand: [2], extra: [3] });
  assert.equal(result.complete, true);
  assert.deepEqual(result.payment.discard, [2]);
  assert.deepEqual(result.payment.extra, [3]);
  assert.equal(checkPayment(b, result.payment).ok, true);
  // 追加支払いに選んだカードは、エネルギーの支払いにも選ばれていたら追加支払いを優先する
  const both = solveComposePayment(b, 1, { hand: [3], extra: [3] });
  assert.equal(both.complete, false);
});
