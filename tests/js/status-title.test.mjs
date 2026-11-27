// U-19: ステータスバーの文の HTML（011 §3.2・§3.4）と、操作の見た目のクラスの付け外し（011 §5.1）。DOM に依存しない部分を中心に。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { FakeElement, installFakeDom } from './fake-dom.mjs';
import { clearRooms, markElement, paintRooms, setSubject, unmarkElement, whyOf } from '../../src/modules/js/cards/selection.js';
import { cardNameHtml } from '../../src/modules/js/ui/log-format.js';
import { textToHtml, unescapeOwnTags } from '../../src/modules/js/ui/status-title.js';

installFakeDom();

const texts = /** @type {any} */ ({ cards: { M015: { name: 'Edge of Consciousness', effect: '' } } });

test('textToHtml: {wisdom} はアイコン、文字は HTML の特殊文字を避ける', () => {
  assert.equal(textToHtml('2 {wisdom} 1 {will}', (name) => `<i ${name}>`), '2 <i wisdom> 1 <i will>');
  assert.equal(textToHtml('A & B {unknown}', () => 'X'), 'A &amp; B {unknown}');
});

test('cardNameHtml: カード名は span.ir-log-card（ツールチップのキーつき）。キーが分からなければ名前だけ（特殊文字は避ける）', () => {
  assert.equal(cardNameHtml('M015', texts), '<span class="ir-log-card" data-ir-zoom-key="M015">No.15 Edge of Consciousness</span>');
  assert.equal(cardNameHtml('Zzz', texts, 'a<b'), 'a&lt;b');
});

test('unescapeOwnTags: エスケープされた span / img だけ HTML に戻す。他の文字には触らない', () => {
  const escaped = '&lt;span class=&quot;ir-log-card&quot; data-ir-zoom-key=&quot;M015&quot;&gt;Edge&lt;/span&gt; &amp; &lt;b&gt;x&lt;/b&gt;';
  assert.equal(unescapeOwnTags(escaped), '<span class="ir-log-card" data-ir-zoom-key="M015">Edge</span> &amp; &lt;b&gt;x&lt;/b&gt;');
  assert.equal(unescapeOwnTags('plain'), 'plain');
});

test('markElement / unmarkElement / setSubject: 付け外しは 1 か所。ir-off は理由つき、外すと全部消える（ir-subject は別）', () => {
  const e = new FakeElement('div');
  markElement(/** @type {any} */ (e), { off: true, why: 'because' });
  assert.equal(e.hasClass('ir-off'), true);
  assert.equal(whyOf(/** @type {any} */ (e), 'fallback'), 'because');
  markElement(/** @type {any} */ (e), { selectable: true, off: true });
  assert.equal(e.hasClass('ir-off'), false, '選べるなら暗くしない');
  assert.equal(e.hasClass('ir-can'), true);
  markElement(/** @type {any} */ (e), { selected: true });
  assert.equal(e.hasClass('ir-picked') && !e.hasClass('ir-can'), true);
  setSubject(/** @type {any} */ (e), true);
  unmarkElement(/** @type {any} */ (e));
  assert.equal(e.hasClass('ir-picked') || e.hasClass('ir-can') || e.hasClass('ir-off'), false);
  assert.equal(e.hasClass('ir-subject'), true);
  assert.equal(whyOf(/** @type {any} */ (e), 'fallback'), 'fallback');
});

test('paintRooms / clearRooms: 選べる部屋は can、経路は picked と順番、他は off。掃除で全部消える', () => {
  const rooms = new Map([1, 2, 3, 4].map((pos) => [pos, new FakeElement('div')]));
  const map = /** @type {Map<number, any>} */ (rooms);
  paintRooms(map, { can: [3], route: [1, 2], why: (pos) => (pos === 4 ? 'far' : undefined) });
  assert.equal(rooms.get(1)?.hasClass('ir-picked'), true);
  assert.equal(rooms.get(2)?.dataset.order, '2');
  assert.equal(rooms.get(3)?.hasClass('ir-can'), true);
  assert.equal(rooms.get(4)?.hasClass('ir-off'), true);
  assert.equal(rooms.get(4)?.getAttribute('data-ir-why'), 'far');
  clearRooms(map);
  for (const room of rooms.values()) {
    assert.equal(room.hasClass('ir-picked') || room.hasClass('ir-can') || room.hasClass('ir-off') || room.hasClass('ir-undo'), false);
    assert.equal(room.dataset.order, undefined);
  }
});
