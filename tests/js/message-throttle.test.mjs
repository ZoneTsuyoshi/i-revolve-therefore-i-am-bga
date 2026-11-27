// U-19: 選べないものを押したときのメッセージの間引き（011 §5.7）。DOM に依存しない純粋関数。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { MESSAGE_INTERVAL_MS, shouldShow } from '../../src/modules/js/ui/message-throttle.js';

test('shouldShow: 初めての文は出す', () => {
  const r = shouldShow({}, 'A', 1000);
  assert.equal(r.show, true);
  assert.deepEqual(r.history, { A: 1000 });
});

test('shouldShow: 同じ文は 2 秒以内に出さず、2 秒たてば出す', () => {
  const first = shouldShow({}, 'A', 1000);
  const again = shouldShow(first.history, 'A', 1000 + MESSAGE_INTERVAL_MS - 1);
  assert.equal(again.show, false);
  assert.deepEqual(again.history, first.history, '出さないときは履歴を変えない');
  assert.equal(shouldShow(first.history, 'A', 1000 + MESSAGE_INTERVAL_MS).show, true);
});

test('shouldShow: 違う文は別に数える。古い記録は捨てる。元の履歴は変えない', () => {
  const history = { A: 1000 };
  const b = shouldShow(history, 'B', 1500);
  assert.equal(b.show, true);
  assert.deepEqual(b.history, { A: 1000, B: 1500 });
  assert.deepEqual(history, { A: 1000 });
  const later = shouldShow(b.history, 'C', 5000);
  assert.deepEqual(later.history, { C: 5000 });
});

test('shouldShow: 時計が戻っても出す', () => {
  assert.equal(shouldShow({ A: 5000 }, 'A', 1000).show, true);
});
