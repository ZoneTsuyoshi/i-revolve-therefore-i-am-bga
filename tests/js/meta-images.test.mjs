// U-9: メタ画像の検査関数（仕様書 009 §14.4）。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { checkImage, cornerAlphas, displayDimensionErrors, MAX_BYTES } from '../../scripts/meta-images/lib/checks.mjs';

const ok = {
  title: { format: 'png', width: 2000, height: 2000, hasAlpha: true, bytes: 400000, corners: [0, 0, 0, 0] },
  banner: { format: 'jpeg', width: 1386, height: 400, hasAlpha: false, bytes: 70000 },
};

test('checkImage: 要件を満たす Title と Banner は違反なし', () => {
  assert.deepEqual(checkImage('title.png', ok.title), []);
  assert.deepEqual(checkImage('banner.jpg', ok.banner), []);
});

test('checkImage: 寸法・形式・アルファ・容量・四隅の違反を報告する', () => {
  assert.equal(checkImage('box.png', { format: 'png', width: 280, height: 280, hasAlpha: false, bytes: 1 }).length, 1);
  assert.equal(checkImage('icon.png', { format: 'jpeg', width: 50, height: 50, hasAlpha: false, bytes: 1 }).length, 1);
  assert.equal(checkImage('banner.jpg', { ...ok.banner, width: 1385 }).length, 1);
  assert.equal(checkImage('banner.jpg', { ...ok.banner, bytes: MAX_BYTES + 1 }).length, 1);
  assert.equal(checkImage('title.png', { ...ok.title, corners: [0, 0, 0, 255] }).length, 1);
  assert.equal(checkImage('title.png', { ...ok.title, corners: null }).length, 1);
  assert.equal(checkImage('title.png', { ...ok.title, hasAlpha: false }).length, 1);
});

test('checkImage: Display は 1140 × 760 の JPEG。知らない名前は違反', () => {
  assert.deepEqual(checkImage('display-2.jpg', { format: 'jpeg', width: 1140, height: 760, hasAlpha: false, bytes: 1 }), []);
  assert.equal(checkImage('display-2.jpg', { format: 'jpeg', width: 1200, height: 760, hasAlpha: false, bytes: 1 }).length, 2);
  assert.equal(checkImage('nothing.png', ok.title).length, 1);
});

test('displayDimensionErrors: 高さ 400〜760、幅は高さの 1.5 倍まで', () => {
  assert.deepEqual(displayDimensionErrors(1140, 760), []);
  assert.deepEqual(displayDimensionErrors(600, 400), []);
  assert.equal(displayDimensionErrors(1000, 399).length, 2);
  assert.equal(displayDimensionErrors(1141, 760).length, 1);
  assert.equal(displayDimensionErrors(900, 761).length, 1);
});

test('cornerAlphas: 左上・右上・左下・右下のアルファ', () => {
  const raw = new Uint8Array(3 * 2 * 4);
  raw[3] = 10; raw[(2) * 4 + 3] = 20; raw[(3) * 4 + 3] = 30; raw[(5) * 4 + 3] = 40;
  assert.deepEqual(cornerAlphas(raw, 3, 2), [10, 20, 30, 40]);
});
