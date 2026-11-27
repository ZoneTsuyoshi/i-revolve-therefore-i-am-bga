// U-12: Action Bar のボタンの並び・トークンを押す操作・獲得できる目標の選択（仕様書 010 §2）
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { FakeBgaAnimations, FakeBgaCards, FakeElement, installFakeDom } from './fake-dom.mjs';

installFakeDom();
const {
  claimView, cumulativeOf, lightLimit, showTokenUseButton, sortButtons, stepTokenCount, tokenQuantityView,
} = await import('../../src/modules/js/states/action-bar-model.js');
const { quantityTokenPick } = await import('../../src/modules/js/states/payment-ui.js');
const { buildBoard } = await import('../../src/modules/js/board/Board.js');
const { PlayerAreas } = await import('../../src/modules/js/board/PlayerAreas.js');
const { createCardManagers } = await import('../../src/modules/js/cards/managers.js');

const P1 = 2310001;

test('sortButtons: 青 → 白 → 赤。同じ色は追加順。色なしは青（取り消しが右端）', () => {
  const sorted = sortButtons([
    { label: 'Cancel', params: { color: /** @type {const} */ ('alert') } },
    { label: 'Change card', params: { color: /** @type {const} */ ('secondary') } },
    { label: 'Confirm', params: {} },
    { label: 'Choose extra cards', params: { color: /** @type {const} */ ('secondary') } },
    { label: 'Claim goal' },
  ]);
  assert.deepEqual(sorted.map((b) => b.label), ['Confirm', 'Claim goal', 'Change card', 'Choose extra cards', 'Cancel']);
});

test('lightLimit: 持っている数と行使回数の上限で止まる（N 回目は N 個）', () => {
  const nth = cumulativeOf((n) => n); // 1, 1+2=3, 3+3=6
  assert.equal(lightLimit(nth, 0, 5), 0);
  assert.equal(lightLimit(nth, 1, 5), 1);
  assert.equal(lightLimit(nth, 2, 5), 1);
  assert.equal(lightLimit(nth, 3, 5), 2);
  assert.equal(lightLimit(nth, 10, 2), 2);
  // 表にない回数（Infinity）は行使できない
  assert.equal(lightLimit((uses) => (uses <= 2 ? uses : Infinity), 99, 9), 2);
});

test('tokenQuantityView: 上限で押せず、次のコストを出す', () => {
  const nth = cumulativeOf((n) => n);
  assert.deepEqual(tokenQuantityView({ count: 0, max: 2, cumulative: nth }), { canAdd: true, canRemove: false, nextCost: 1 });
  assert.deepEqual(tokenQuantityView({ count: 1, max: 2, cumulative: nth }), { canAdd: true, canRemove: true, nextCost: 2 });
  assert.deepEqual(tokenQuantityView({ count: 2, max: 2, cumulative: nth }), { canAdd: false, canRemove: true, nextCost: null });
  assert.deepEqual(tokenQuantityView({ count: 0, max: 0 }), { canAdd: false, canRemove: false, nextCost: null });
  assert.deepEqual(tokenQuantityView({ count: 0, max: 3 }), { canAdd: true, canRemove: false, nextCost: 1 });
});

test('stepTokenCount: 0 以上、上限以下', () => {
  assert.equal(stepTokenCount(0, -1, 3), 0);
  assert.equal(stepTokenCount(3, 1, 3), 3);
  assert.equal(stepTokenCount(1, 1, 3), 2);
});

test('quantityTokenPick: 押すと +1、バッジで -1、上限では押せない', () => {
  /** @type {number[]} */
  const calls = [];
  const nth = cumulativeOf((n) => n);
  const pick = quantityTokenPick({ kind: 'light', count: 1, max: 2, cumulative: nth, onChange: (n) => calls.push(n) });
  assert.equal(pick.canAdd, true);
  assert.equal(pick.cost, 2);
  assert.match(pick.tooltip, /costs? \$\{cost\}|\b2\b/);
  pick.onAdd();
  pick.onRemove?.();
  assert.deepEqual(calls, [2, 0]);
  const full = quantityTokenPick({ kind: 'wildUp', count: 2, max: 2, onChange: () => {} });
  assert.equal(full.canAdd, false);
  assert.equal(full.cost, null);
  full.onAdd();
});

test('claimView: 獲得できなくなった目標の選択は外れる。獲得できる目標がなければ空', () => {
  const claimable = [{ key: 'O001', id: 1, points: 3 }, { key: 'O004', id: 4, points: 2 }];
  assert.deepEqual(claimView(claimable, 'O004'), { keys: ['O001', 'O004'], selected: claimable[1] });
  assert.deepEqual(claimView(claimable, 'O009'), { keys: ['O001', 'O004'], selected: null });
  assert.deepEqual(claimView([], 'O001'), { keys: [], selected: null });
});

test('showTokenUseButton: 0 個なら出さない', () => {
  assert.equal(showTokenUseButton(0), false);
  assert.equal(showTokenUseButton(1), true);
});

function build() {
  const host = new FakeElement('div');
  const players = { [P1]: { id: String(P1), name: 'Alice', color: 'b3202a' } };
  const board = buildBoard(/** @type {any} */ (host), { players, playerOrder: [P1], me: P1 });
  const cards = createCardManagers({ BgaCards: FakeBgaCards, BgaAnimations: FakeBgaAnimations, imgUrl: (f) => `img/${f}`, animationsActive: () => true });
  const areas = new PlayerAreas({ refs: board, cards, BgaCards: FakeBgaCards, imgUrl: (/** @type {string} */ f) => `img/${f}`, animationsActive: () => true, me: P1 });
  areas.setup({ playerData: { [P1]: { tokens: { light: 3, wildUp: 2, wildUsed: 0, compose: 1, analysis: 0 } } } });
  const token = (/** @type {string} */ kind) => /** @type {any} */ (board.playerAreas.get(P1)).tokens.querySelector(`.ir-token[data-kind="${kind}"]`);
  return { areas, token };
}

test('pickTokens: 押すと +1、バッジを押すと -1、掃除で枠・バッジ・リスナーが全部外れる', () => {
  const { areas, token } = build();
  let count = 1;
  const rebuild = () => areas.pickTokens({
    light: quantityTokenPick({ kind: 'light', count, max: 2, cumulative: cumulativeOf((n) => n), onChange: (n) => { count = n; rebuild(); } }),
  });
  rebuild();
  const light = token('light');
  assert.equal(light.hasClass('ir-selected'), true);
  assert.equal(light.hasClass('ir-token-pick'), true);
  assert.equal(light.querySelector('.ir-token-plus')?.textContent, '+2');
  assert.equal(light.querySelector('.ir-token-badge')?.textContent, '1');
  light.fire('click');
  assert.equal(count, 2);
  // 上限に達したら押せない（枠は選択中の印だけ。「+」なし。押しても変わらない）
  assert.equal(token('light').querySelector('.ir-token-plus'), null);
  assert.equal(token('light').hasClass('ir-token-pick'), false);
  token('light').fire('click');
  assert.equal(count, 2);
  // バッジを押すと 1 減る（トークン自身の押下にはならない）
  token('light').querySelector('.ir-token-badge').fire('click');
  assert.equal(count, 1);
  // 状態を出るとき
  areas.clearSelection();
  for (const kind of ['light', 'wildUp']) {
    const t = token(kind);
    assert.equal(t.hasClass('ir-selected') || t.hasClass('ir-selectable') || t.hasClass('ir-token-pick'), false);
    assert.equal(t.querySelector('.ir-token-badge'), null);
    assert.equal((t.listeners.click ?? []).length, 0);
  }
  assert.equal(token('light').getAttribute('data-ir-tip-text'), 'Psychic Light: 3', '選択を外すと個数つきのツールチップに戻る');
});

test('pickTokens: 使えないトークンは「選べる」の枠を出さない。使えるものは押すと onAdd', () => {
  const { areas, token } = build();
  let used = 0;
  areas.pickTokens({
    compose: { canAdd: true, count: 0, plus: false, tooltip: 'use', onAdd: () => { used += 1; } },
    analysis: { canAdd: false, count: 0, plus: false, tooltip: 'You have no psychoanalysis token', onAdd: () => { used += 10; } },
  });
  assert.equal(token('compose').hasClass('ir-selectable'), true);
  assert.equal(token('compose').querySelector('.ir-token-plus'), null);
  assert.equal(token('analysis').hasClass('ir-selectable'), false);
  token('compose').fire('click');
  token('analysis').fire('click');
  assert.equal(used, 1);
  assert.equal(token('analysis').getAttribute('data-ir-tip-text'), 'You have no psychoanalysis token');
});
