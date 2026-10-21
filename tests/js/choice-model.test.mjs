// U-10: 効果の選択（71）・床アクションの選択（51）・手札上限（72）・フリーアクションの純粋関数。回答の形は States/EffectChoice.php と Flow/ChoiceFlow.php に合わせる
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { intList, privateOf } from '../../src/modules/js/states/args.js';
import {
  KIND, answer, canUseFreeActions, choiceFromArgs, claimableObjectives, costText, discardExcessReady, discardLightGain, entrepreneurHand,
  entrepreneurRooms, firstRooms, floorOptionKey, pairHand, pairTableau, pickCount, pickReady, plainText, secondRooms, spiritParts,
  swapHandFieldReady, toIds,
} from '../../src/modules/js/states/choice-model.js';

test('privateOf: _private[自分] でも _private 直下でもよい', () => {
  assert.deepEqual(privateOf({ _private: { 99: { hand: [1] } } }), { hand: [1] });
  assert.deepEqual(privateOf({ _private: { hand: [1] } }), { hand: [1] });
  assert.deepEqual(privateOf(undefined), {});
  assert.deepEqual(intList(['1', 2, 'x']), [1, 2, 0]);
});

test('choiceFromArgs: 公開部分と _private（交換できる組、強化自我の候補）を読む', () => {
  const choice = choiceFromArgs({
    turnPlayer: '5', chooser: '7', choiceKind: 'SELECT_SWAP_SAME_COST', source: 'M034', data: { x: 1 }, skippable: true,
    _private: { 7: { pairs: [[1, 2], ['3', '4']], egoCandidates: ['E01', 'E03'] } },
  });
  assert.equal(choice.chooser, 7);
  assert.equal(choice.turnPlayer, 5);
  assert.equal(choice.kind, KIND.SELECT_SWAP_SAME_COST);
  assert.deepEqual(choice.pairs, [[1, 2], [3, 4]]);
  assert.deepEqual(choice.egoCandidates, ['E01', 'E03']);
  assert.equal(choice.skippable, true);
  // 分岐は種類なし。壊れた args でも例外を出さない
  assert.equal(choiceFromArgs({ choiceKind: null, data: { branches: 2 } }).kind, null);
  assert.equal(choiceFromArgs(undefined).kind, null);
  assert.deepEqual(choiceFromArgs(undefined).pairs, []);
});

test('PICK_FIELD_CARD・PICK_DISCARD は count 枚ちょうど選ぶ', () => {
  const field = choiceFromArgs({ choiceKind: 'PICK_FIELD_CARD', data: { count: 2, candidates: [1, 2, 3] } });
  assert.equal(pickCount(field).exact, 2);
  assert.equal(pickReady(field, [1]), false);
  assert.equal(pickReady(field, [1, 2]), true);
  assert.equal(pickReady(field, [1, 2, 3]), false);
  const light = choiceFromArgs({ choiceKind: 'SELECT_DISCARD_FOR_LIGHT', data: {} });
  assert.equal(pickCount(light).exact, null);
  assert.equal(pickReady(light, [1]), false);
});

test('SELECT_SWAP_HAND_FIELD は手札と場札を同じ枚数ずつ', () => {
  assert.equal(swapHandFieldReady([1, 2], [5, 6]), true);
  assert.equal(swapHandFieldReady([1], [5, 6]), false);
});

test('SELECT_SWAP_SAME_COST: 自己構成済みカードを選ぶと、組になる手札だけが残る', () => {
  const pairs = [[10, 1], [10, 2], [11, 2]];
  assert.deepEqual(pairTableau(pairs), [10, 11]);
  assert.deepEqual(pairHand(pairs, null), [1, 2]);
  assert.deepEqual(pairHand(pairs, 10), [1, 2]);
  assert.deepEqual(pairHand(pairs, 11), [2]);
});

test('部屋を 2 つ選ぶ入れ替え: SWAP_ROOM_CARDS は同レベル、SWAP_ROOM_FLOORS は候補のどれでも', () => {
  // 四隅（1・4・7・10）がレベル 2
  const rooms = [1, 2, 3, 4, 5];
  assert.deepEqual(firstRooms(KIND.SWAP_ROOM_CARDS, rooms), [1, 2, 3, 4, 5]);
  assert.deepEqual(secondRooms(KIND.SWAP_ROOM_CARDS, rooms, 1), [4]);
  assert.deepEqual(secondRooms(KIND.SWAP_ROOM_CARDS, rooms, 2), [3, 5]);
  assert.deepEqual(secondRooms(KIND.SWAP_ROOM_CARDS, rooms, null), rooms);
  // 同レベルの相手がいない部屋は最初に選べない
  assert.deepEqual(firstRooms(KIND.SWAP_ROOM_CARDS, [1, 2]), []);
  const level1 = [2, 3, 5, 6];
  assert.deepEqual(secondRooms(KIND.SWAP_ROOM_FLOORS, level1, 2), [3, 5, 6]);
  assert.deepEqual(firstRooms(KIND.SWAP_ROOM_FLOORS, level1), level1);
});

test('ENTREPRENEUR_SWAP: 手札と部屋のレベルが同じ組だけ', () => {
  const levels = { 1: 1, 2: 2, 3: 1 };
  const levelOf = (id) => levels[id];
  assert.deepEqual(entrepreneurRooms([1, 2, 4, 5], 2), [1, 4]);
  assert.deepEqual(entrepreneurRooms([1, 2, 4, 5], 1), [2, 5]);
  assert.deepEqual(entrepreneurRooms([1, 2], null), [1, 2]);
  assert.deepEqual(entrepreneurHand([1, 2, 3], levelOf, 1), [2]);
  assert.deepEqual(entrepreneurHand([1, 2, 3], levelOf, 2), [1, 3]);
  assert.deepEqual(entrepreneurHand([1, 2, 3], levelOf, null), [1, 2, 3]);
});

test('回答の形は actResolveChoice の説明どおり', () => {
  assert.deepEqual(answer.cards([1, 2]), { cards: [1, 2] });
  assert.deepEqual(answer.swapHandField([1], [5]), { hand: [1], field: [5] });
  assert.deepEqual(answer.swapSameCost(10, 2), { tableau: 10, hand: 2 });
  assert.deepEqual(answer.use(true), { use: true });
  assert.deepEqual(answer.ego('E03'), { ego: 'E03' });
  assert.deepEqual(answer.spirit('R3B'), { spirit: 'R3B' });
  assert.deepEqual(answer.index(1), { index: 1 });
  assert.deepEqual(answer.branch(0), { branch: 0 });
  assert.deepEqual(answer.rooms(2, 3), { a: 2, b: 3 });
  assert.deepEqual(answer.warp(5), { pos: 5 });
  assert.deepEqual(answer.entrepreneur(7, 3), { hand: 7, pos: 3 });
});

test('floorOptionKey: 床アクション、分岐、想起効果の代替（割引つきは _DISCOUNT_1）', () => {
  assert.deepEqual(floorOptionKey({ kind: 'FLOOR' }), { type: 'floor' });
  assert.deepEqual(floorOptionKey({ kind: 'FLOOR_A', branch: 0 }), { type: 'branch', branch: 0 });
  assert.deepEqual(floorOptionKey({ kind: 'FLOOR_B', branch: 1 }), { type: 'branch', branch: 1 });
  assert.deepEqual(floorOptionKey({ kind: 'ALT_ANALYSIS', discount: 0 }), { type: 'recall', key: 'ALT_ANALYSIS' });
  assert.deepEqual(floorOptionKey({ kind: 'ALT_COMPOSE', discount: 1 }), { type: 'recall', key: 'ALT_COMPOSE_DISCOUNT_1' }); // gitleaks:allow 効果の識別子（認証情報ではない）
});

test('plainText: アイコンの記法を取り除く（未知の波括弧は残す）', () => {
  assert.equal(plainText('Draw 3 Memory cards {draw}'), 'Draw 3 Memory cards');
  assert.equal(plainText('Take 1 open card {open} and gain 1 Psychic Light {light}'), 'Take 1 open card and gain 1 Psychic Light');
  assert.equal(plainText('Take a Construction action with energy {energy} -1'), 'Take a Construction action with energy -1');
  assert.equal(plainText('Draw 1 {draw}, then discard'), 'Draw 1, then discard');
});

test('spiritParts と costText', () => {
  assert.deepEqual(spiritParts('R3B'), { tier: 3, branch: 'B' });
  assert.deepEqual(spiritParts('R2'), { tier: 2, branch: '' });
  assert.deepEqual(spiritParts('x'), { tier: 0, branch: '' });
  const names = { WISDOM: 'Wisdom', WILL: 'Will', EMOTION: 'Emotion' };
  assert.equal(costText({ WILL: 1, WISDOM: 2 }, (t) => names[t]), '2 × Wisdom, 1 × Will');
  assert.equal(costText({}, (t) => names[t]), '');
});

test('discardLightGain: 2 枚分につき心光 1（レベル 2 は 2 枚分）', () => {
  assert.equal(discardLightGain([]), 0);
  assert.equal(discardLightGain([1]), 0);
  assert.equal(discardLightGain([1, 1]), 1);
  assert.equal(discardLightGain([2]), 1);
  assert.equal(discardLightGain([2, 1]), 1);
  assert.equal(discardLightGain([2, 2, 1]), 2);
});

test('claimableObjectives: claimable の目標だけ。canUseFreeActions はターンプレイヤーだけ', () => {
  const statuses = new Map([
    ['O001', { x: 1, points: 1, claimable: false }],
    ['O003', { x: 4, points: 2, claimable: true }],
    ['O005', { x: 1, points: 1 }],
  ]);
  assert.deepEqual(claimableObjectives(statuses), [{ key: 'O003', id: 3, points: 2 }]);
  assert.deepEqual(claimableObjectives(new Map()), []);
  assert.equal(canUseFreeActions(5, 5), true);
  assert.equal(canUseFreeActions(5, 6), false);
  assert.equal(canUseFreeActions(null, 6), false);
});

test('手札上限: 超過分ちょうどを選ぶ', () => {
  assert.equal(discardExcessReady(2, [1, 2]), true);
  assert.equal(discardExcessReady(2, [1]), false);
  assert.equal(discardExcessReady(2, [1, 2, 3]), false);
  assert.equal(discardExcessReady(0, []), false);
  assert.deepEqual(toIds(['3', 4]), [3, 4]);
});
