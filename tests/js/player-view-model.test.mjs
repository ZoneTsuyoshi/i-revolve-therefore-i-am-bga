// U-5: プレイヤーの領域の表示モデル・選択の純粋関数。sprites.js に依存しない。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
  HAND_LIMIT, applyTotals, compareHand, composedModel, energyMap, handCard, handCountState, handModel, intList, memoryInfoTable,
  privateArg, restoreWild, tokensModel, underModel,
} from '../../src/modules/js/board/player-view-model.js';
import { CLASS_SELECTABLE, CLASS_SELECTED, nextSelection, selectionClasses } from '../../src/modules/js/cards/selection.js';

const catalog = { memory: [{ type: 1, level: 1, energy: 3 }, { type: 2, level: 1, energy: 1 }, { type: 60, level: 2, energy: 0 }, { type: 5, level: 1, energy: 1 }] };

test('memoryInfoTable: レベルとエネルギー、壊れた行は飛ばす', () => {
  const info = memoryInfoTable({ memory: [...catalog.memory, null, { type: 'x' }, { type: 9, level: 7, energy: '2' }] });
  assert.deepEqual(info.get(60), { level: 2, energy: 0 });
  assert.deepEqual(info.get(9), { level: 1, energy: 2 });
  assert.equal(info.size, 5);
  assert.equal(memoryInfoTable(undefined).size, 0);
});

test('handModel: レベル、エネルギー、カード番号の昇順。文字列の数値も整数に通す', () => {
  const info = memoryInfoTable(catalog);
  const hand = handModel([
    { id: '10', type: '60', energy: '0' }, { id: 11, type: 1, energy: 3 }, { id: 12, type: 5, energy: 1 },
    { id: 13, type: 2, energy: 1 }, { id: 14, type: 2, energy: 0 }, { bad: true }, null,
  ], info);
  assert.deepEqual(hand.map((c) => c.id), [14, 13, 12, 11, 10]);
  assert.equal(hand[0].key, 'M002');
  assert.equal(hand[4].level, 2);
  assert.deepEqual(handModel('x', info), []);
});

test('handCard: カタログに無い種類は レベル 1・印刷 0、現在のエネルギーは印刷と違ってよい', () => {
  const info = memoryInfoTable(catalog);
  assert.deepEqual(handCard({ id: 1, type: 99 }, info), { id: 1, key: 'M099', type: 99, level: 1, energy: 0, printed: 0 });
  const c = handCard({ id: 2, type: 1, energy: 2 }, info);
  assert.equal(c.energy, 2);
  assert.equal(c.printed, 3);
  assert.equal(compareHand({ id: 1, type: 1, level: 1, energy: 1 }, { id: 2, type: 1, level: 1, energy: 1 }) < 0, true);
});

test('handCountState: 上限 8 を超えたら本人だけ over', () => {
  assert.deepEqual(handCountState(8, true), { count: 8, over: false });
  assert.deepEqual(handCountState(9, true), { count: 9, over: true });
  assert.deepEqual(handCountState(9, false), { count: 9, over: false });
  assert.deepEqual(handCountState(-3, true), { count: 0, over: false });
  assert.equal(HAND_LIMIT, 8);
});

test('composedModel と underModel', () => {
  const composed = composedModel([{ id: '5', type: 3, tapped: true, underCount: '2' }, { id: 6, type: 4, tapped: false }, { id: 7 }, null]);
  assert.deepEqual(composed, [
    { card: { id: 5, key: 'M003' }, tapped: true, underCount: 2 },
    { card: { id: 6, key: 'M004' }, tapped: false, underCount: 0 },
  ]);
  assert.deepEqual(underModel([{ host: '5', cards: [{ id: '20', type: 8 }, { id: 21 }] }, { host: 6, cards: [] }, 'x']), [{ host: 5, cards: [{ id: 20, key: 'M008' }] }]);
  assert.deepEqual(composedModel(undefined), []);
});

test('トークン: 初期値、N16 の totals の差分反映、万能移動鍵の反転', () => {
  const t = tokensModel({ light: '2', wildUp: 3, wildUsed: 1, compose: 0, analysis: -4 });
  assert.deepEqual(t, { light: 2, wildUp: 3, wildUsed: 1, compose: 0, analysis: 0 });
  assert.deepEqual(tokensModel(null), { light: 0, wildUp: 0, wildUsed: 0, compose: 0, analysis: 0 });
  const t2 = applyTotals(t, { light: 5, wild_used: '0', ignored: 9 });
  assert.deepEqual(t2, { light: 5, wildUp: 3, wildUsed: 0, compose: 0, analysis: 0 });
  assert.deepEqual(applyTotals(t, undefined), t);
  assert.deepEqual(restoreWild(t, 1), { ...t, wildUp: 4, wildUsed: 0 });
  assert.deepEqual(restoreWild(t, 5), { ...t, wildUp: 4, wildUsed: 0 }, '裏の数を超えて戻さない');
  assert.deepEqual(restoreWild(t, undefined), t);
});

test('privateArg: 合成済みの args でも _private の下でも読む', () => {
  assert.deepEqual(privateArg({ cards: [1] }, 'cards'), [1]);
  assert.deepEqual(privateArg({ _private: { cards: [2] } }, 'cards'), [2]);
  assert.deepEqual(privateArg({ _private: { 77: { cards: [3] } } }, 'cards', 77), [3]);
  assert.equal(privateArg({ _private: { 77: { cards: [3] } } }, 'cards', 78), undefined);
  assert.equal(privateArg(undefined, 'cards'), undefined);
});

test('intList と energyMap', () => {
  assert.deepEqual(intList(['1', 2, 'x']), [1, 2, 0]);
  assert.deepEqual(intList(null), []);
  assert.deepEqual([...energyMap([{ card_id: '3', energy: '2' }, { energy: 1 }, null])], [[3, 2]]);
});

test('nextSelection: single は置き換え、multiple は切り替えと上限、選べないものは無視', () => {
  assert.deepEqual(nextSelection([], 1, { mode: 'single' }), [1]);
  assert.deepEqual(nextSelection([1], 2, { mode: 'single' }), [2]);
  assert.deepEqual(nextSelection([1], 1, { mode: 'single' }), []);
  assert.deepEqual(nextSelection([1], 2, { mode: 'multiple' }), [1, 2]);
  assert.deepEqual(nextSelection([1, 2], 2, { mode: 'multiple' }), [1]);
  assert.deepEqual(nextSelection([1, 2], 3, { mode: 'multiple', max: 2 }), [1, 2]);
  assert.deepEqual(nextSelection([1], 9, { mode: 'multiple', selectable: (id) => id !== 9 }), [1]);
  assert.deepEqual(nextSelection(['1'], 1, { mode: 'multiple' }), [], '文字列と数値は同じ ID として扱う');
});

test('selectionClasses: 選択中は選択中の印だけ。選べないカードにはクラスを付けない（暗くしない）', () => {
  assert.deepEqual(selectionClasses({ selectable: true, selected: false }), [CLASS_SELECTABLE]);
  assert.deepEqual(selectionClasses({ selectable: true, selected: true }), [CLASS_SELECTED]);
  assert.deepEqual(selectionClasses({ selectable: false, selected: false }), []);
});
