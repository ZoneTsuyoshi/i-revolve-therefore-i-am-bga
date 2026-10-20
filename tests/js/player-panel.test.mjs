// U-6: プレイヤーパネルの表示モデル（純粋関数）と、偽の DOM でのパネル更新・通知のフック。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { FakeElement, installFakeDom } from './fake-dom.mjs';
import {
  WIN_SCORE, applyComposedChange, clampTier, composedMap, exchangeChange, keyCounts, keyTable, panelState, scoreText, scoresFromArgs, tierText, wildText,
} from '../../src/modules/js/board/player-panel-model.js';

installFakeDom();
const { PlayerPanels } = await import('../../src/modules/js/board/PlayerPanels.js');
const { PlayerNotifications } = await import('../../src/modules/js/player-notifications.js');
const { CommonNotifications } = await import('../../src/modules/js/notifications.js');

const P1 = 2310001;
const P2 = 2310002;
const catalog = {
  memory: [
    { type: 1, keys: ['WISDOM', 'WILD'] }, { type: 2, keys: ['WILL'] }, { type: 3, keys: [] },
    { type: 4, keys: ['WISDOM', 'WISDOM', 'EMOTION'] }, { type: 5 }, { type: 'x', keys: ['WILL'] },
  ],
};

test('keyTable / keyCounts: 自己構成済みカードの鍵を数える。カタログに無い種類・壊れた行は 0 本', () => {
  const table = keyTable(catalog);
  assert.deepEqual(table.get(1), ['WISDOM', 'WILD']);
  assert.deepEqual(table.get(5), []);
  assert.equal(table.size, 5);
  assert.deepEqual(keyCounts(new Map([[10, 1], [11, 4], [12, 99], [13, 2]]), table), { WISDOM: 3, WILL: 1, EMOTION: 1, WILD: 1 });
  assert.deepEqual(keyCounts(new Map(), keyTable(undefined)), { WISDOM: 0, WILL: 0, EMOTION: 0, WILD: 0 });
});

test('panelState / composedMap: 欠けた値は 0、階層は 0〜5 に収める', () => {
  const s = panelState({ score: '7', handCount: 5, tier: 9, tokens: { light: 3, wildUp: 1 }, composed: [{ id: 1, type: 2 }, { bad: 1 }, null] });
  assert.equal(s.score, 7);
  assert.equal(s.handCount, 5);
  assert.equal(s.tier, 5);
  assert.deepEqual(s.tokens, { light: 3, wildUp: 1, wildUsed: 0, compose: 0, analysis: 0 });
  assert.deepEqual([...s.composed], [[1, 2]]);
  assert.deepEqual(panelState(undefined).tokens.light, 0);
  assert.equal(clampTier(-3), 0);
  assert.equal(composedMap('x').size, 0);
});

test('scoresFromArgs: 変化した人だけ。total が無い行は飛ばす', () => {
  const m = scoresFromArgs({ [P1]: { total: 12, memory: 9, objective: 3 }, [P2]: { memory: 1 } });
  assert.deepEqual([...m], [[P1, 12]]);
  assert.equal(scoresFromArgs(null).size, 0);
});

test('applyComposedChange / exchangeChange: 追加と削除。元の Map は変えない', () => {
  const base = new Map([[1, 1], [2, 2]]);
  const next = applyComposedChange(base, { added: [{ id: 3, type: 4 }, { id: 0, type: 1 }], removed: [1] });
  assert.deepEqual([...next], [[2, 2], [3, 4]]);
  assert.equal(base.size, 2);
  const change = exchangeChange([{ out: { id: 7, type: 3 }, in: { id: 2, type: 2 } }, { out: { id: 8 } }, null]);
  assert.deepEqual(change.removed, [2]);
  assert.deepEqual(change.added, [{ id: 7, type: 3 }]);
});

test('表示文字列', () => {
  assert.equal(WIN_SCORE, 20);
  assert.equal(scoreText(7), '7 / 20');
  assert.equal(scoreText(-1), '0 / 20');
  assert.equal(wildText({ wildUp: 3, wildUsed: 1 }), '3 + 1');
  assert.equal(tierText(3), 'Tier 3');
});

// ---- 偽の DOM ----

function gamedatas() {
  return {
    catalog,
    playerData: {
      [P1]: { tier: 1, score: 7, handCount: 5, tokens: { light: 3, wildUp: 3, wildUsed: 1, compose: 0, analysis: 2 }, composed: [{ id: 50, type: 1 }, { id: 51, type: 2 }] },
      [P2]: { tier: 0, score: 0, handCount: 4, tokens: { light: 0, wildUp: 4, wildUsed: 0, compose: 1, analysis: 0 }, composed: [] },
    },
  };
}

function build() {
  const hosts = new Map([[P1, new FakeElement('div')], [P2, new FakeElement('div')]]);
  const scoreHolder = new FakeElement('span');
  const scoreValue = new FakeElement('span');
  scoreHolder.append(scoreValue);
  /** @type {any} */ (scoreValue).closest = () => scoreHolder;
  /** @type {any} */ (globalThis).document.getElementById = (/** @type {string} */ id) => (id === `player_score_${P1}` ? scoreValue : null);
  const panels = new PlayerPanels({ getElement: (id) => hosts.get(id), imgUrl: (f) => `img/${f}`, animationsActive: () => false });
  panels.setup(gamedatas());
  const text = (/** @type {number} */ id, /** @type {string} */ item) => hosts.get(id)?.querySelector('.ir-pp')?.querySelectorAll('.ir-pp-value').find((v) => v.dataset.item === item)?.textContent;
  return { panels, text, hosts, scoreHolder };
}

test('setup: 3 行・各項目・標準のスコア欄を隠す', () => {
  const { text, hosts, scoreHolder } = build();
  const rows = hosts.get(P1)?.querySelector('.ir-pp')?.children ?? [];
  assert.deepEqual(rows.map((r) => r.children.length), [3, 3, 5]);
  assert.equal(text(P1, 'score'), '7 / 20');
  assert.equal(text(P1, 'hand'), '5');
  assert.equal(text(P1, 'light'), '3');
  assert.equal(text(P1, 'wild'), '3 + 1');
  assert.equal(text(P1, 'compose'), '0');
  assert.equal(text(P1, 'analysis'), '2');
  assert.equal(text(P1, 'tier'), 'Tier 1');
  assert.equal(text(P1, 'keyWISDOM'), '1');
  assert.equal(text(P1, 'keyWILL'), '1');
  assert.equal(text(P1, 'keyWILD'), '1');
  assert.equal(text(P2, 'wild'), '4 + 0');
  assert.equal(text(P2, 'keyWISDOM'), '0');
  assert.ok(scoreHolder.hasClass('ir-hide-standard-score'));
});

test('通知のフック: 手札・トークン・スコア・階層・自己構成済み（鍵）が動く。壊れた args でも例外なし', async () => {
  const { panels, text } = build();
  const areas = /** @type {any} */ ({
    me: null, tokens: new Map(), isMine: () => false, setHandCount() {}, setTokens() {}, setTapped() {}, restoreWildKeys() {},
    refs: { playerAreas: new Map() }, addComposed: async () => {}, removeComposed: async () => {}, removeFromHand: async () => {}, addToHand: async () => {},
    attachUnder: async () => {}, tapped: new Set(),
  });
  const pn = new PlayerNotifications(areas, panels);
  await pn.notif_tokensChanged({ player_id: P1, totals: { light: 5, wild_up: 2, wild_used: 2 } });
  assert.equal(text(P1, 'light'), '5');
  assert.equal(text(P1, 'wild'), '2 + 2');
  await pn.notif_cardComposed({ player_id: P1, card: { id: 60, type: 4 }, hand_count: 4 });
  assert.equal(text(P1, 'hand'), '4');
  assert.equal(text(P1, 'keyWISDOM'), '3');
  assert.equal(text(P1, 'keyEMOTION'), '1');
  await pn.notif_scoreUpdated({ player_id: P1, scores: { [P1]: { total: 11 } } });
  assert.equal(text(P1, 'score'), '11 / 20');
  await pn.notif_cardsAttached({ player_id: P1, host_id: 60, count: 2, hand_count: 2 });
  assert.equal(text(P1, 'hand'), '2');
  await pn.onCardsExchanged({ player_id: P1, kind: 'TABLEAU_HAND', hand_count: 2, pairs: [{ out: { id: 70, type: 3 }, in: { id: 50, type: 1 } }] });
  assert.equal(text(P1, 'keyWILD'), '0');
  assert.equal(text(P1, 'keyWISDOM'), '2');
  pn.onCleanup({ player_id: P1, wild_restored: 1 });
  areas.tokens.set(P1, { light: 5, wildUp: 4, wildUsed: 1, compose: 0, analysis: 2 });
  pn.onCleanup({ player_id: P1 });
  assert.equal(text(P1, 'wild'), '4 + 1');
  // 共通側の N24・N12 からのフック
  const common = /** @type {any} */ ({ advanceSpirit: async () => {}, syncDecks() {}, deckElement() {} });
  const cn = new CommonNotifications(common, pn);
  await cn.notif_spiritAdvanced({ player_id: P1, to_spirit: 'R1A', tier: 2 });
  assert.equal(text(P1, 'tier'), 'Tier 2');
  await cn.notif_cardsDrawn({ player_id: P2, hand_count: 7, decks: {} });
  assert.equal(text(P2, 'hand'), '7');
  for (const args of [undefined, null, {}, { player_id: 'x', scores: 5, totals: 3, card: 1, pairs: 'y' }]) {
    await pn.notif_scoreUpdated(/** @type {any} */ (args));
    await pn.notif_tokensChanged(/** @type {any} */ (args));
    await pn.notif_cardComposed(/** @type {any} */ (args));
  }
  assert.equal(text(P2, 'hand'), '7');
});
