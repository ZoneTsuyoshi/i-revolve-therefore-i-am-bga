// U-8: ログ引数の整形（ui/log-format.js）。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';

/** @type {any} */ (globalThis)._ = (/** @type {string} */ text) => text;

const { buildCardTexts } = await import('../../src/modules/js/ui/texts.js');
const { cardKeyOf, escapeHtml, formatLogArgs } = await import('../../src/modules/js/ui/log-format.js');

const texts = buildCardTexts();
const ctx = { texts, translate: (/** @type {string} */ s) => `T(${s})`, iconHtml: (/** @type {string} */ n) => `<i ${n}>` };
const fmt = (/** @type {any} */ args, log = '') => formatLogArgs(log, args, ctx).args;

test('escapeHtml', () => {
  assert.equal(escapeHtml(`<a href="x">&'`), '&lt;a href=&quot;x&quot;&gt;&amp;&#39;');
});

test('cardKeyOf: 引数から ID を引く', () => {
  assert.equal(cardKeyOf('card_name', { card: { id: 5, type: 14 } }), 'M014');
  assert.equal(cardKeyOf('card_name', { card: { id: 5, type: '7' } }), 'M007');
  assert.equal(cardKeyOf('card_name', {}), null);
  assert.equal(cardKeyOf('card_name', { card: null }), null);
  assert.equal(cardKeyOf('card_name', { card: { id: 1 } }), null);
  assert.equal(cardKeyOf('ego_name', { ego_id: 'E01' }), 'E01');
  assert.equal(cardKeyOf('ego_name', { ego_id: 'zzz' }), null);
  assert.equal(cardKeyOf('source_name', { source: 'M052' }), 'M052');
  assert.equal(cardKeyOf('source_name', { source: 'I05' }), 'I05');
  assert.equal(cardKeyOf('objective_name', { obj_id: 3 }), 'O003');
  assert.equal(cardKeyOf('objective_name', { obj_id: 'O002' }), 'O002');
  assert.equal(cardKeyOf('other', { ego_id: 'E01' }), null);
});

test('カード名: No.N 英語名 の span にして、i18n から外す', () => {
  const out = fmt({ card: { id: 9, type: 1 }, card_name: 'Abandoned Idea', room_name: 'Hall', i18n: ['room_name', 'card_name'] });
  assert.equal(out.card_name, `<span class="ir-log-card" data-ir-zoom-key="M001">No.1 ${texts.cards.M001.name}</span>`);
  assert.equal(out.room_name, '<span class="ir-log-room">T(Hall)</span>');
  assert.deepEqual(out.i18n, []);
});

test('部屋名: 太字の span にして翻訳する', () => {
  const out = fmt({ room_name: 'Hall', pos: 3, i18n: ['room_name'] });
  assert.equal(out.room_name, '<span class="ir-log-room">T(Hall)</span>');
  assert.deepEqual(out.i18n, []);
  const swap = fmt({ room_a: 'A', room_b: 'B', i18n: ['room_a', 'room_b'] });
  assert.equal(swap.room_a, '<span class="ir-log-room">T(A)</span>');
  assert.equal(swap.room_b, '<span class="ir-log-room">T(B)</span>');
});

test('自我・目標・発動元', () => {
  assert.match(fmt({ ego_id: 'E02', ego_name: 'x', i18n: ['ego_name'] }).ego_name, /data-ir-zoom-key="E02"/);
  assert.match(fmt({ obj_id: 4, objective_name: 'x', i18n: ['objective_name'] }).objective_name, /data-ir-zoom-key="O004"/);
  assert.match(fmt({ source: 'I05', source_name: 'x', i18n: ['source_name'] }).source_name, /data-ir-zoom-key="I05"/);
});

test('トークン名: delta が 1 種類ならアイコンを付ける', () => {
  assert.equal(fmt({ delta: { light: 2 }, token_name: 'inner light', amount: 2 }).token_name, '<span class="ir-log-token"><i light> T(inner light)</span>');
  assert.equal(fmt({ delta: { wild_up: 1 }, token_name: 'wild move key' }).token_name, '<span class="ir-log-token"><i wildkey> T(wild move key)</span>');
  assert.equal(fmt({ delta: { light: 1, compose: 1 }, token_name: 'x' }).token_name, '<span class="ir-log-token">T(x)</span>');
  assert.equal(fmt({ token_name: 'x' }).token_name, '<span class="ir-log-token">T(x)</span>');
});

test('名前は HTML を解釈させない', () => {
  assert.equal(fmt({ room_name: '<b>x</b>' }).room_name, '<span class="ir-log-room">T(&lt;b&gt;x&lt;/b&gt;)</span>');
});

test('種類が分からないカード名は元のまま（秘密のカードの名前を作らない）', () => {
  const out = fmt({ card_name: 'Something', i18n: ['card_name'] });
  assert.equal(out.card_name, 'Something');
  assert.deepEqual(out.i18n, ['card_name']);
});

test('壊れた引数でも例外を出さない・元の args を変えない', () => {
  for (const bad of [undefined, null, 5, 'x', [], {}, { card: 'x', delta: 3, i18n: 'x', token_name: 5, room_name: 7 }]) {
    assert.doesNotThrow(() => formatLogArgs('${a}', bad, ctx));
  }
  const args = { card: { id: 1, type: 2 }, card_name: 'n', i18n: ['card_name'] };
  const out = formatLogArgs('x', args, ctx);
  assert.deepEqual(args.i18n, ['card_name']);
  assert.equal(args.card_name, 'n');
  assert.equal(out.log, 'x');
  assert.equal(formatLogArgs('x', null, ctx).args, null);
});

test('2 回通しても同じ結果', () => {
  const once = formatLogArgs('', { card: { id: 1, type: 2 }, card_name: 'n', room_name: 'R', i18n: ['card_name', 'room_name'] }, ctx);
  assert.deepEqual(once.args.i18n, []);
  const twice = formatLogArgs('', once.args, ctx);
  assert.deepEqual(twice.args, once.args);
});
