// U-2: レイアウトの計算・スプライトの背景指定・プレイヤーの並び（生成物 sprites.js には依存しない）。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { computeSizes, layoutOf, RING_CELLS } from '../../src/modules/js/ui/layout-math.js';
import { gridCellStyle, isPortraitKey, rectStyle } from '../../src/modules/js/cards/sprite-css.js';
import { orderPlayers } from '../../src/modules/js/board/player-order.js';

test('layoutOf: 境界は 1100 と 600', () => {
  assert.equal(layoutOf(1100), 'wide');
  assert.equal(layoutOf(1099), 'medium');
  assert.equal(layoutOf(600), 'medium');
  assert.equal(layoutOf(599), 'narrow');
});

test('computeSizes: 仕様書 009 §2.4 の計算例', () => {
  const at = (/** @type {number} */ w) => computeSizes(w);
  assert.deepEqual(pick(at(1100)), { layout: 'wide', roomW: 171, cardW: 102, landW: 119 });
  assert.deepEqual(pick(at(1400)), { layout: 'wide', roomW: 213, cardW: 127, landW: 149 });
  assert.deepEqual(pick(at(740)), { layout: 'medium', roomW: 179, cardW: 107, landW: 125 });
  assert.deepEqual(pick(at(600)), { layout: 'medium', roomW: 144, cardW: 86, landW: 100 });
  assert.deepEqual(pick(at(360)), { layout: 'narrow', roomW: 85, cardW: 85, landW: 116 });
  assert.equal(at(390).roomW, 93);
});

/** @param {ReturnType<typeof computeSizes>} s */
function pick(s) {
  return { layout: s.layout, roomW: s.roomW, cardW: s.cardW, landW: s.landW };
}

test('computeSizes: 幅が 0・NaN でも下限で計算する', () => {
  assert.equal(computeSizes(0).rootW, 360);
  assert.equal(computeSizes(NaN).layout, 'narrow');
});

test('RING_CELLS: 12 部屋が 4 x 4 の外周を時計回りに 1 周する', () => {
  assert.equal(RING_CELLS.length, 12);
  assert.equal(new Set(RING_CELLS.map(([c, r]) => `${c},${r}`)).size, 12);
  assert.ok(RING_CELLS.every(([c, r]) => c === 1 || c === 4 || r === 1 || r === 4));
  assert.deepEqual(RING_CELLS[0], [1, 1]);
  assert.deepEqual(RING_CELLS[3], [4, 1]);
  assert.deepEqual(RING_CELLS[6], [4, 4]);
  assert.deepEqual(RING_CELLS[9], [1, 4]);
});

test('gridCellStyle: パーセントで指定する', () => {
  assert.deepEqual(gridCellStyle({ col: 0, row: 0 }, { cols: 12, rows: 9 }), { size: '1200% 900%', position: '0% 0%' });
  assert.deepEqual(gridCellStyle({ col: 11, row: 8 }, { cols: 12, rows: 9 }), { size: '1200% 900%', position: '100% 100%' });
  assert.equal(gridCellStyle({ col: 3, row: 0 }, { cols: 12, rows: 9 }).position, '27.273% 0%');
  // 1 列のシートでも 0 除算にならない
  assert.deepEqual(gridCellStyle({ col: 0, row: 0 }, { cols: 1, rows: 1 }), { size: '100% 100%', position: '0% 0%' });
});

test('rectStyle: シート内の矩形をパーセントで指定する', () => {
  const sheet = { w: 1024, h: 162 };
  assert.deepEqual(rectStyle({ x: 0, y: 0, w: 96, h: 96 }, sheet), { size: '1066.667% 168.75%', position: '0% 0%' });
  assert.equal(rectStyle({ x: 928, y: 66, w: 96, h: 96 }, sheet).position, '100% 100%');
});

test('isPortraitKey: 想い出（表・裏）だけ縦長', () => {
  for (const key of ['M001', 'M099', 'Rev1B', 'Rev2H']) {
    assert.equal(isPortraitKey(key), true, key);
  }
  for (const key of ['F01', 'Frev1', 'R1A', 'Rrev1', 'I01', 'Irev', 'E01', 'Erev', 'O001', 'Orev']) {
    assert.equal(isPortraitKey(key), false, key);
  }
});

test('orderPlayers: 自分を先頭に席順の時計回り。観戦者は席順のまま', () => {
  const players = { 10: {}, 20: {}, 30: {} };
  assert.deepEqual(orderPlayers(['10', '20', '30'], players, 20), [20, 30, 10]);
  assert.deepEqual(orderPlayers([10, 20, 30], players, null), [10, 20, 30]);
  assert.deepEqual(orderPlayers(undefined, players, 30), [30, 10, 20]);
  assert.deepEqual(orderPlayers([], {}, 5), []);
  assert.deepEqual(orderPlayers([10, 99], players, 99), [10, 20, 30]);
});
