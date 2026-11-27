// U-18: ツールチップの位置と画像の寸法（011 §6.3・§6.4）。DOM に依存しない純粋関数。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { imageHeight, imageWidthRange, imageWidthSteps, placeTip } from '../../src/modules/js/ui/tip-placement.js';

const viewport = { width: 1000, height: 800 };
/** @param {number} left @param {number} top @param {number} w @param {number} h */
const box = (left, top, w, h) => ({ left, top, right: left + w, bottom: top + h });
/** 矩形が交差するか */
const overlaps = (/** @type {any} */ a, /** @type {any} */ b) => a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top;
const rectOf = (/** @type {any} */ r, /** @type {{ width: number, height: number }} */ size) => ({ left: r.left, top: r.top, right: r.left + size.width, bottom: r.top + size.height });

test('placeTip: 右に入るなら右（対象の右 8px から。縦は対象の上端に揃える）', () => {
  const anchor = box(100, 200, 100, 140);
  const r = placeTip({ anchor, size: { width: 300, height: 200 }, viewport });
  assert.equal(r.placement, 'right');
  assert.equal(r.left, 208);
  assert.equal(r.top, 200);
  assert.equal(r.maxWidth, 300);
});

test('placeTip: 右に入らなければ左', () => {
  const anchor = box(800, 200, 100, 140);
  const r = placeTip({ anchor, size: { width: 300, height: 200 }, viewport });
  assert.equal(r.placement, 'left');
  assert.equal(r.left, 800 - 8 - 300);
});

test('placeTip: 左右に入らなければ下（横は対象の左端に揃え、画面内に挟む）', () => {
  const anchor = box(300, 100, 400, 100);
  const r = placeTip({ anchor, size: { width: 500, height: 200 }, viewport });
  assert.equal(r.placement, 'below');
  assert.equal(r.top, 208);
  assert.equal(r.left, 300);
  const edge = placeTip({ anchor: box(500, 100, 450, 100), size: { width: 700, height: 200 }, viewport });
  assert.equal(edge.placement, 'below');
  assert.equal(edge.left, 1000 - 8 - 700, '右の端から 8px');
});

test('placeTip: 下にも入らなければ上', () => {
  const anchor = box(300, 600, 400, 160);
  const r = placeTip({ anchor, size: { width: 500, height: 300 }, viewport });
  assert.equal(r.placement, 'above');
  assert.equal(r.top, 600 - 8 - 300);
});

test('placeTip: 画面の端から 8px 以上離す（上下左右）', () => {
  const anchor = box(100, 780, 100, 10);
  const r = placeTip({ anchor, size: { width: 300, height: 400 }, viewport });
  assert.equal(r.placement, 'right');
  assert.equal(r.top, 800 - 8 - 400, '下の端から 8px');
  const top = placeTip({ anchor: box(100, 0, 100, 40), size: { width: 300, height: 100 }, viewport });
  assert.equal(top.top, 8, '上の端から 8px');
  const wide = placeTip({ anchor: box(10, 300, 100, 40), size: { width: 980, height: 100 }, viewport });
  assert.ok(wide.left >= 8 && wide.left + 980 <= 1000 - 8);
});

test('placeTip: ステータスバー（top）の下から始める', () => {
  const anchor = box(100, 0, 100, 60);
  const r = placeTip({ anchor, size: { width: 300, height: 100 }, viewport, top: 140 });
  assert.equal(r.placement, 'right');
  assert.equal(r.top, 140 + 8);
  // 上に置く案は、バーの下に収まらなければ選ばれない
  const bottom = placeTip({ anchor: box(300, 200, 400, 560), size: { width: 700, height: 150 }, viewport, top: 140 });
  assert.notEqual(bottom.placement, 'above', '対象の上は 200 − 148 − 8 = 44px しかない');
  assert.ok(bottom.top >= 148 || bottom.placement === 'fit');
});

test('placeTip: どこにも入らないときは fit。いちばん広い側の余白に収まる大きさを返す', () => {
  const anchor = box(300, 300, 100, 100);
  const r = placeTip({ anchor, size: { width: 900, height: 900 }, viewport });
  assert.equal(r.placement, 'fit');
  assert.ok(r.maxWidth <= 900 && r.maxHeight <= 900);
  const placed = rectOf(r, { width: r.maxWidth, height: r.maxHeight });
  assert.ok(placed.left >= 8 && placed.top >= 8 && placed.right <= 1000 - 8 && placed.bottom <= 800 - 8, '画面に収まる');
  assert.ok(!overlaps(placed, anchor), '対象を覆わない');
  // 右（592 × 784）が左（284 × 784）・上下より広い
  assert.equal(r.side, 'right');
});

test('placeTip: 返す矩形は、どの側でも対象と交差しない', () => {
  const sizes = [{ width: 120, height: 80 }, { width: 300, height: 600 }, { width: 700, height: 300 }, { width: 990, height: 790 }];
  const anchors = [box(0, 0, 50, 50), box(950, 750, 50, 50), box(450, 350, 100, 100), box(10, 400, 980, 50), box(400, 200, 200, 500)];
  for (const anchor of anchors) {
    for (const size of sizes) {
      for (const top of [0, 140]) {
        const r = placeTip({ anchor, size, viewport, top });
        const placed = rectOf(r, { width: r.maxWidth, height: r.maxHeight });
        assert.ok(!overlaps(placed, anchor), `${JSON.stringify(anchor)} ${JSON.stringify(size)} ${r.placement}`);
        assert.ok(placed.left >= 8 - 1e-6 && placed.right <= 1000 - 8 + 1e-6, '横が画面内');
        assert.ok(placed.top >= top + 8 - 1e-6 && placed.bottom <= 800 - 8 + 1e-6, `縦がバーの下・画面内 ${JSON.stringify([anchor, size, top, r, placed])}`);
      }
    }
  }
});

test('imageWidthRange: 希望は縦長 220・横長 300、最小は 120・180。卓の同じカードの 1.25 倍以上', () => {
  assert.deepEqual(imageWidthRange({ landscape: false }), { want: 220, min: 120 });
  assert.deepEqual(imageWidthRange({ landscape: true }), { want: 300, min: 180 });
  // 手札 84〜128、部屋の床 150〜213、精神・目標 105〜114
  assert.deepEqual(imageWidthRange({ landscape: false, tableWidth: 128 }), { want: 220, min: 160 });
  assert.deepEqual(imageWidthRange({ landscape: true, tableWidth: 213 }), { want: 300, min: 267 });
  assert.deepEqual(imageWidthRange({ landscape: true, tableWidth: 114 }), { want: 300, min: 180 });
  // 卓のカードが大きいときは、希望も最小に合わせる（常に 1.25 倍以上）
  assert.deepEqual(imageWidthRange({ landscape: false, tableWidth: 220 }), { want: 275, min: 275 });
});

test('imageWidthSteps: 希望から最小まで大きい順。最後は最小', () => {
  const steps = imageWidthSteps({ want: 220, min: 120 });
  assert.equal(steps[0], 220);
  assert.equal(steps[steps.length - 1], 120);
  assert.deepEqual([...steps].sort((a, b) => b - a), steps);
  assert.deepEqual(imageWidthSteps({ want: 275, min: 275 }), [275]);
});

test('imageHeight: 縦長 280:390、横長 400:287', () => {
  assert.equal(imageHeight(280, false), 390);
  assert.equal(imageHeight(400, true), 287);
});
