// U-2: レイアウトの計算・スプライトの背景指定・プレイヤーの並び（生成物 sprites.js には依存しない）。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { computeSizes, layoutOf, resolveSizes, RING_CELLS } from '../../src/modules/js/ui/layout-math.js';
import { gridCellStyle, isPortraitKey, rectStyle } from '../../src/modules/js/cards/sprite-css.js';
import { orderPlayers } from '../../src/modules/js/board/player-order.js';

test('layoutOf: 高さで W を抑えた xl の条件と wide の下限（Wc ≥ 971）と medium の下限（600）', () => {
  assert.equal(layoutOf(970, Infinity), 'medium');
  assert.equal(layoutOf(971, Infinity), 'wide');
  assert.equal(layoutOf(600), 'medium');
  assert.equal(layoutOf(599), 'narrow');
  // xl: Wc ≥ 6.1 × W_x + 7g + P_w（W_x = min(213, max(W_h, 150))、P_w = 558）。Hv = 810 で W_x = 158、境目は 1577.8
  assert.equal(layoutOf(1578, 810), 'xl');
  assert.equal(layoutOf(1577, 810), 'wide');
  // 高さが低いほど W_x が下限 150 に近づき、xl の境目が下がる（Hv = 700 → 150。境目 1529）
  assert.equal(layoutOf(1529, 700), 'xl');
  assert.equal(layoutOf(1528, 700), 'wide');
});

test('computeSizes: 高さを渡さないときは高さで抑えない（009 §2.4 の計算例）', () => {
  const at = (/** @type {number} */ w) => computeSizes(w);
  assert.deepEqual(pick(at(1100)), { layout: 'wide', roomW: 171, cardW: 102, landW: 119, playerCardW: 104 });
  assert.deepEqual(pick(at(1400)), { layout: 'wide', roomW: 213, cardW: 127, landW: 149, playerCardW: 127 });
  assert.deepEqual(pick(at(740)), { layout: 'medium', roomW: 179, cardW: 107, landW: 125, playerCardW: 107 });
  assert.deepEqual(pick(at(600)), { layout: 'medium', roomW: 144, cardW: 86, landW: 100, playerCardW: 104 });
  assert.deepEqual(pick(at(360)), { layout: 'narrow', roomW: 85, cardW: 85, landW: 116, playerCardW: 85 });
  assert.equal(at(390).roomW, 93);
});

test('computeSizes: 010 §3.4 の代表的な画面（Wc、Hv）の配置と W / w / wp', () => {
  const at = (/** @type {number} */ w, /** @type {number} */ h) => computeSizes(w, h);
  // 1920 × 1080（1630、810）
  assert.deepEqual(pick(at(1630, 810)), { layout: 'xl', roomW: 158, cardW: 94, landW: 110, playerCardW: 128 });
  // 2560 × 1440（2270、1170）: W は上限 213
  assert.deepEqual(pick(at(2270, 1170)), { layout: 'xl', roomW: 213, cardW: 127, landW: 149, playerCardW: 128 });
  // 1440 × 900（1180、700）: 高さで W = 150 に下限止め
  assert.deepEqual(pick(at(1180, 700)), { layout: 'wide', roomW: 150, cardW: 90, landW: 105, playerCardW: 104 });
  // 1366 × 768（1080、520）、1024 × 768（1010、520）も wide の W = 150
  assert.deepEqual(pick(at(1080, 520)), { layout: 'wide', roomW: 150, cardW: 90, landW: 105, playerCardW: 104 });
  assert.deepEqual(pick(at(1010, 520)), { layout: 'wide', roomW: 150, cardW: 90, landW: 105, playerCardW: 104 });
  // 768 × 1024（768、700）: medium。幅からは 186、高さで 150
  assert.deepEqual(pick(at(768, 700)), { layout: 'medium', roomW: 150, cardW: 90, landW: 105, playerCardW: 104 });
  // 390 × 844（390、844）: narrow は高さで抑えない
  assert.deepEqual(pick(at(390, 844)), { layout: 'narrow', roomW: 93, cardW: 93, landW: 126, playerCardW: 93 });
  assert.deepEqual(pick(at(390, 300)), { layout: 'narrow', roomW: 93, cardW: 93, landW: 126, playerCardW: 93 });
});

test('computeSizes: 環と山札の行が Hv に収まる（xl・wide で W が下限に達しない範囲）', () => {
  const s = computeSizes(1630, 810);
  assert.ok(4.906 * s.roomW + 4 * s.gap <= 810);
});

test('resolveSizes: 大きい配置へ変えるのは幅が 24px 狭くても同じ結果になるときだけ（ヒステリシス）', () => {
  // wide から: Hv = 810 の xl の境目は 1578。1578〜1601 は 24px 狭いと wide になるので wide のまま
  assert.equal(resolveSizes(1590, 810, 'wide').layout, 'wide');
  assert.equal(resolveSizes(1602, 810, 'wide').layout, 'xl');
  // 初回・xl のままなら素直に判定
  assert.equal(resolveSizes(1590, 810, null).layout, 'xl');
  assert.equal(resolveSizes(1590, 810, 'xl').layout, 'xl');
  // 小さい配置へはすぐ変える（xl から、境目の 1 px 下で wide）
  assert.equal(resolveSizes(1577, 810, 'xl').layout, 'wide');
  // medium から wide（971）、narrow から medium（600）も同様
  assert.equal(resolveSizes(980, 520, 'medium').layout, 'medium');
  assert.equal(resolveSizes(995, 520, 'medium').layout, 'wide');
  assert.equal(resolveSizes(610, 800, 'narrow').layout, 'narrow');
  assert.equal(resolveSizes(624, 800, 'narrow').layout, 'medium');
});

/** @param {ReturnType<typeof computeSizes>} s */
function pick(s) {
  return { layout: s.layout, roomW: s.roomW, cardW: s.cardW, landW: s.landW, playerCardW: s.playerCardW };
}

test('computeSizes: 幅が 0・NaN でも下限で計算する', () => {
  assert.equal(computeSizes(0).rootW, 300);
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
