// U-5: 偽の DOM・偽の bga-cards で、PublicView の形の gamedatas を PlayerAreas に通し、通知で表示が動くことと、選択の掃除を確認する
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { FakeBgaAnimations, FakeBgaCards, FakeElement, FakeStock, installFakeDom } from './fake-dom.mjs';

installFakeDom();
const { buildBoard } = await import('../../src/modules/js/board/Board.js');
const { CommonArea } = await import('../../src/modules/js/board/CommonArea.js');
const { PlayerAreas } = await import('../../src/modules/js/board/PlayerAreas.js');
const { createCardManagers } = await import('../../src/modules/js/cards/managers.js');
const { CommonNotifications } = await import('../../src/modules/js/notifications.js');
const { PlayerNotifications } = await import('../../src/modules/js/player-notifications.js');

const P1 = 2310001;
const P2 = 2310002;
const players = { [P1]: { id: String(P1), name: 'Alice', color: 'b3202a' }, [P2]: { id: String(P2), name: 'Bob', color: '2a4fa0' } };

function gamedatas() {
  return {
    catalog: { memory: [{ type: 1, level: 1, energy: 3 }, { type: 2, level: 1, energy: 1 }, { type: 60, level: 2, energy: 0 }, { type: 7, level: 1, energy: 2 }] },
    playerorder: [P1, P2],
    turnPlayer: String(P1),
    rooms: [], open: [], decks: {}, discards: {}, spirit: [], enhDeckCount: 2,
    objectives: [{ id: 1, slot: 0, owner: null }, { id: 4, slot: 1, owner: String(P2) }],
    playerData: {
      [P1]: {
        persona: 1, tokens: { light: 2, wildUp: 3, wildUsed: 1, compose: 1, analysis: 0 }, handCount: 3,
        composed: [{ id: 50, type: 7, tapped: true, underCount: 1 }, { id: 51, type: 2, tapped: false, underCount: 0 }],
        initialEgo: 'I02', enhancedEgo: 'E01', objectives: [],
      },
      [P2]: { persona: 4, tokens: { light: 0, wildUp: 2, wildUsed: 0, compose: 0, analysis: 1 }, handCount: '5', composed: [{ id: 60, type: 1, tapped: false, underCount: 0 }], initialEgo: 'I05', enhancedEgo: null },
    },
    myHand: [{ id: 30, type: 60, energy: 0 }, { id: 31, type: 1, energy: 3 }, { id: 32, type: 2, energy: 1 }],
    myUnder: [{ host: 50, cards: [{ id: 70, type: 1 }] }],
    myObjectiveStatus: [],
  };
}

function build(me = P1) {
  const host = new FakeElement('div');
  const board = buildBoard(/** @type {any} */ (host), { players, playerOrder: [P1, P2], me });
  const cards = createCardManagers({ BgaCards: FakeBgaCards, BgaAnimations: FakeBgaAnimations, imgUrl: (f) => `img/${f}`, animationsActive: () => true });
  const deps = { refs: board, cards, BgaCards: FakeBgaCards, imgUrl: (/** @type {string} */ f) => `img/${f}`, animationsActive: () => true };
  const areas = new PlayerAreas({ ...deps, me });
  const common = new CommonArea({ ...deps, playerAreas: areas, players });
  const pn = new PlayerNotifications(areas);
  const cn = new CommonNotifications(common, pn);
  return { board, areas, common, pn, cn };
}

const keysIn = (/** @type {FakeElement | null | undefined} */ el) => (el?.querySelectorAll('.ir-card') ?? []).map((c) => c.dataset.key);
const stockOf = (/** @type {any} */ areas, /** @type {number} */ id) => areas.stocks.get(id);

test('setup: 手札・自己構成済み・横向き・下のカード・自我・目標・トークン・他人の手札', async () => {
  const { board, areas, common } = build();
  common.setup(gamedatas());
  areas.setup(gamedatas());
  await areas.ready;
  const mine = /** @type {any} */ (board.playerAreas.get(P1));
  const theirs = /** @type {any} */ (board.playerAreas.get(P2));

  // 手札はレベル→エネルギー→番号の順（M002 e1, M001 e3, M060 L2）に stock へ入る
  assert.deepEqual(stockOf(areas, P1).hand.getCards().map((/** @type {any} */ c) => c.key), ['M002', 'M001', 'M060']);
  assert.equal(mine.handInfo.textContent, '3 / 8');
  assert.equal(mine.handInfo.hasClass('ir-over-limit'), false);

  // 自己構成済み: 横向きと Used 帯、I08 の下の枚数バッジ。下のカードの中身（本人）
  assert.deepEqual(keysIn(mine.composed), ['M007', 'M002']);
  const tapped = stockOf(areas, P1).composed.getCardElement({ id: 50 });
  assert.equal(tapped.hasClass('ir-tapped'), true);
  assert.equal(tapped.querySelector('.ir-used-band')?.textContent, 'Used');
  assert.equal(tapped.querySelector('.ir-under-badge')?.textContent, 'Under: 1');
  assert.equal(stockOf(areas, P1).composed.getCardElement({ id: 51 }).hasClass('ir-tapped'), false);
  assert.deepEqual(keysIn(mine.under), ['M001']);

  // 自我・目標（獲得済みは持ち主の領域）。手番の枠
  assert.deepEqual(keysIn(mine.ego), ['I02']);
  assert.deepEqual(keysIn(mine.enhEgo), ['E01']);
  assert.deepEqual(keysIn(theirs.ownedGoals), ['O004']);
  assert.equal(mine.area.hasClass('ir-turn'), true);
  assert.equal(theirs.area.hasClass('ir-turn'), false);

  // トークン: 5 種類を常に出し、0 は薄く
  assert.equal(mine.tokens.querySelectorAll('.ir-token').length, 5);
  assert.equal(mine.tokens.querySelector('.ir-token[data-kind="wildUsed"]')?.querySelector('.ir-token-count')?.textContent, '+ 1 used');
  assert.equal(theirs.tokens.querySelector('.ir-token[data-kind="light"]')?.hasClass('ir-zero'), true);

  // 他人は手札の Stock を持たず枚数チップだけ
  assert.equal(theirs.hand, null);
  assert.equal(theirs.handCount.querySelector('.ir-hand-count-text')?.textContent, '5');
  assert.equal(stockOf(areas, P2).hand, null);
  assert.equal(stockOf(areas, P2).under, null);
});

test('setup: 空・欠けた gamedatas、観戦者でも例外を出さない', async () => {
  for (const data of [{}, { playerData: null, myHand: 'x', myUnder: 5, objectives: 1, catalog: [] }, undefined]) {
    const { areas } = build();
    assert.doesNotThrow(() => areas.setup(/** @type {any} */ (data)));
    await areas.ready;
  }
  const { areas, board } = build(/** @type {any} */ (null));
  areas.setup(/** @type {any} */ (gamedatas()));
  await areas.ready;
  assert.equal(areas.isMine(P1), false);
  assert.equal(board.playerAreas.get(P1)?.handCount?.querySelector('.ir-hand-count-text')?.textContent, '3');
});

test('通知: 手札・自己構成・横向き・トークン・下のカード・捨札・クリーンアップ', async () => {
  const { board, areas, common, pn, cn } = build();
  common.setup(gamedatas());
  areas.setup(gamedatas());
  await areas.ready;
  const hand = () => stockOf(areas, P1).hand.getCards().map((/** @type {any} */ c) => c.id);

  // N12 引く: 本人は _private、他人は枚数
  await cn.notif_cardsDrawn({ player_id: P1, level: 1, decks: {}, _private: { cards: [{ id: 33, type: 7 }] } });
  assert.deepEqual(hand().includes(33), true);
  await cn.notif_cardsDrawn({ player_id: P2, level: 1, count: 2, hand_count: 7 });
  assert.equal(board.playerAreas.get(P2)?.handCount?.querySelector('.ir-hand-count-text')?.textContent, '7');

  // 上限を超えると印と文言
  for (let i = 0; i < 5; i += 1) {
    await cn.notif_cardsDrawn({ player_id: P1, level: 1, cards: [{ id: 40 + i, type: 1 }] });
  }
  assert.equal(hand().length, 9);
  assert.equal(board.playerAreas.get(P1)?.handInfo?.textContent, '9 / 8 (over the limit)');
  assert.equal(board.playerAreas.get(P1)?.handInfo?.hasClass('ir-over-limit'), true);

  // N18 自己構成: 手札から自己構成済みへ
  await pn.notif_cardComposed({ player_id: P1, card: { id: 33, type: 7 }, hand_count: 8 });
  assert.equal(hand().includes(33), false);
  assert.equal(stockOf(areas, P1).composed.getCards().some((/** @type {any} */ c) => c.id === 33), true);
  await pn.notif_cardComposed({ player_id: P2, card: { id: 61, type: 2 }, hand_count: 6 });
  assert.deepEqual(keysIn(board.playerAreas.get(P2)?.composed), ['M001', 'M002']);
  assert.equal(board.playerAreas.get(P2)?.handCount?.querySelector('.ir-hand-count-text')?.textContent, '6');

  // N17 横向き → クリーンアップで戻る（N29）
  await pn.notif_cardsTapped({ card_ids: [51, 61], tapped: true });
  assert.equal(stockOf(areas, P1).composed.getCardElement({ id: 51 }).hasClass('ir-tapped'), true);
  assert.equal(stockOf(areas, P2).composed.getCardElement({ id: 61 }).querySelector('.ir-used-band') !== null, true);
  await cn.notif_cleanup({ player_id: P1, untapped: [50, 51, 61], wild_restored: 1, decks: {}, rooms: [], open: [] });
  assert.equal(stockOf(areas, P1).composed.getCardElement({ id: 50 }).hasClass('ir-tapped'), false);
  assert.equal(stockOf(areas, P1).composed.getCardElement({ id: 50 }).querySelector('.ir-used-band'), null);
  assert.equal(areas.tokens.get(P1)?.wildUp, 4);
  assert.equal(areas.tokens.get(P1)?.wildUsed, 0);

  // N16 トークン
  await pn.notif_tokensChanged({ player_id: P1, delta: { light: 3 }, totals: { light: 5 } });
  assert.equal(areas.tokens.get(P1)?.light, 5);
  assert.equal(board.playerAreas.get(P1)?.tokens.querySelector('.ir-token[data-kind="light"]')?.querySelector('.ir-token-count')?.textContent, '5');

  // N19 I08: 本人は下のカードの中身、他人は枚数バッジ
  await pn.notif_cardsAttached({ player_id: P1, host_id: 50, count: 1, hand_count: 7, _private: { cards: [{ id: 41, type: 1 }] } });
  assert.deepEqual(keysIn(board.playerAreas.get(P1)?.under), ['M001', 'M001']);
  assert.equal(stockOf(areas, P1).composed.getCardElement({ id: 50 }).querySelector('.ir-under-badge')?.textContent, 'Under: 2');
  await pn.notif_cardsAttached({ player_id: P2, host_id: 60, count: 2, hand_count: 4 });
  assert.equal(stockOf(areas, P2).composed.getCardElement({ id: 60 }).querySelector('.ir-under-badge')?.textContent, 'Under: 2');

  // N15 捨てる: 手札から外れる。I08 の下のカードが捨札へ（UNDER_RELEASED）で枚数バッジが減る
  await cn.notif_cardsDiscarded({ player_id: P1, cards: [{ id: 31, type: 1 }], reason: 'HAND_LIMIT', pile_counts: { 1: 1, 2: 0 }, hand_count: 8 });
  assert.equal(hand().includes(31), false);
  await cn.notif_cardsDiscarded({ player_id: P1, cards: [{ id: 70, type: 1 }], reason: 'UNDER_RELEASED', pile_counts: { 1: 2, 2: 0 } });
  assert.equal(stockOf(areas, P1).composed.getCardElement({ id: 50 }).querySelector('.ir-under-badge')?.textContent, 'Under: 1');

  // N35 エネルギーのバッジ（印刷と違うときだけ）
  await pn.notif_handEnergyUpdated({ energies: [{ card_id: 32, energy: 0 }] });
  assert.equal(stockOf(areas, P1).hand.getCardElement({ id: 32 }).querySelector('.ir-energy-badge')?.textContent, 'E0');
  await pn.notif_handEnergyUpdated({ energies: [{ card_id: 32, energy: 1 }] });
  assert.equal(stockOf(areas, P1).hand.getCardElement({ id: 32 }).querySelector('.ir-energy-badge'), null);

  // N04 手番
  await pn.notif_turnStarted({ player_id: P2, turn_no: 2 });
  assert.equal(board.playerAreas.get(P2)?.area.hasClass('ir-turn'), true);
  assert.equal(board.playerAreas.get(P1)?.area.hasClass('ir-turn'), false);
});

test('通知: 場札・部屋・交換・目標・自我が手札と領域に届く', async () => {
  const { board, areas, common, cn, pn } = build();
  const data = gamedatas();
  data.open = Array.from({ length: 6 }, (_u, i) => ({ slot: i, id: 200 + i, type: 1 }));
  data.rooms = [{ pos: 2, floor: 2, level: 1, card: { id: 101, back: '1B', faceUp: false }, under: [] }];
  common.setup(data);
  areas.setup(data);
  await areas.ready;
  const hand = () => stockOf(areas, P1).hand.getCards().map((/** @type {any} */ c) => c.id);

  // N13 場札を取る（本人）
  await cn.notif_cardsGained({ player_id: P1, source: 'OPEN', cards: [{ id: 200, type: 1 }], hand_count: 4 });
  assert.equal(hand().includes(200), true);
  assert.deepEqual(keysIn(board.openSlots[0]), []);

  // N14 想起の獲得（本人）
  await cn.notif_recallStarted?.({ pos: 2, card: { id: 101, type: 2 } });
  await cn.notif_roomCardAcquired({ player_id: P1, pos: 2, card: { id: 101, type: 2 }, hand_count: 5 });
  assert.equal(hand().includes(101), true);

  // N26 手札と場札の交換: 場札 201 が手札へ、手札 30 が場札スロット 1 へ
  await cn.notif_cardsExchanged({ player_id: P1, kind: 'HAND_OPEN', pairs: [{ out: { id: 30, type: 60 }, in: { id: 201, type: 1 }, slot: 1 }] });
  assert.equal(hand().includes(201), true);
  assert.equal(hand().includes(30), false);
  assert.deepEqual(keysIn(board.openSlots[1]), ['M060']);

  // N26 TABLEAU_HAND: 横向きを引き継ぐ（50 は横向き）
  await cn.notif_cardsExchanged({ player_id: P1, kind: 'TABLEAU_HAND', pairs: [{ out: { id: 32, type: 2 }, in: { id: 50, type: 7 } }] });
  assert.equal(hand().includes(50), true);
  assert.equal(stockOf(areas, P1).composed.getCardElement({ id: 32 }).hasClass('ir-tapped'), true);
  // `out` は手札から自己構成済みへ、`in` は自己構成済みから手札へ（HAND_OPEN と同じ向き）。元の場所には残らない
  assert.equal(stockOf(areas, P1).hand.getCards().some((/** @type {{ id: number }} */ c) => c.id === 32), false);
  assert.equal(stockOf(areas, P1).composed.getCards().some((/** @type {{ id: number }} */ c) => c.id === 50), false);
  assert.equal(stockOf(areas, P1).hand.getCards().filter((/** @type {{ id: number }} */ c) => c.id === 50).length, 1);
  assert.equal(stockOf(areas, P1).composed.getCards().filter((/** @type {{ id: number }} */ c) => c.id === 32).length, 1);

  // N27 I05: 部屋のカードが手札へ（本人は券面）、手札のカードが部屋へ
  await cn.notif_roomCardSwappedWithHand({ player_id: P1, pos: 2, room_card_id: 101, hand_card_id: 31, back: '1F', _private: { room_card: { id: 101, type: 2 } } });
  assert.equal(hand().includes(31), false);

  // N22 目標の獲得（得点バッジ）、N25 強化自我、N02 初期自我の一斉公開
  await cn.notif_objectiveClaimed({ player_id: P1, obj_id: 1, points: 3 });
  assert.deepEqual(keysIn(board.playerAreas.get(P1)?.ownedGoals), ['O001']);
  assert.equal(stockOf(areas, P1).owned.getCardElement({ id: 'O001' }).querySelector('.ir-goal-points')?.textContent, '+3');
  await cn.notif_enhancedEgoObtained({ player_id: P2, ego_id: 'E03', deck_count: 1 });
  assert.deepEqual(keysIn(board.playerAreas.get(P2)?.enhEgo), ['E03']);
  await cn.notif_initialEgosRevealed({ egos: [{ player_id: P2, ego_id: 'I07' }] });
  assert.equal(keysIn(board.playerAreas.get(P2)?.ego).includes('I07'), true);

  // N20 I04: 手札から床の下へ
  await pn.notif_cardsPlacedUnderFloor({ player_id: P1, pos: 2, cards: [{ id: 33, type: 7 }], hand_count: 3 });
});

test('通知: args が欠けていても例外を出さない（プレイヤーの領域）', async () => {
  const { areas, common, pn, cn } = build();
  common.setup(gamedatas());
  areas.setup(gamedatas());
  await areas.ready;
  for (const obj of [pn, cn]) {
    for (const name of Object.getOwnPropertyNames(Object.getPrototypeOf(obj)).filter((n) => n.startsWith('notif_') || /^on[A-Z]/.test(n))) {
      await assert.doesNotReject(async () => (/** @type {any} */ (obj))[name]({}, undefined), name);
      await assert.doesNotReject(async () => (/** @type {any} */ (obj))[name](undefined, undefined), name);
    }
  }
});

test('選択の基盤: start で選べる印、onChange、上限、clear で全部掃除', async () => {
  const { areas, common } = build();
  common.setup(gamedatas());
  areas.setup(gamedatas());
  await areas.ready;
  const hand = stockOf(areas, P1).hand;
  const changes = [];
  areas.selectHand({ ids: [30, 31], mode: 'multiple', max: 1, onChange: (/** @type {any} */ ids) => changes.push(ids) });
  assert.equal(areas.selection.active, true);
  assert.equal(hand.getCardElement({ id: 30 }).hasClass('ir-selectable'), true);
  assert.equal(hand.getCardElement({ id: 32 }).hasClass('ir-selectable'), false, '選べないカードには印を付けない');

  hand.selectCard({ id: 32 });
  assert.deepEqual(changes, [], '選べないカードのクリックは無視');
  hand.selectCard({ id: 30 });
  assert.deepEqual(changes, [[30]]);
  assert.equal(hand.getCardElement({ id: 30 }).hasClass('ir-selected'), true);
  assert.equal(hand.getCardElement({ id: 30 }).hasClass('ir-selectable'), false);
  hand.selectCard({ id: 31 });
  assert.deepEqual(changes, [[30]], '上限を超える選択は取り消す');
  assert.deepEqual(areas.selection.getSelected(hand), [30]);

  // 自己構成済みも同時に選べる。状態を出るとき clear で全部掃除する
  areas.selectComposed(P1, { mode: 'single' });
  const composed = stockOf(areas, P1).composed;
  assert.equal(composed.getCardElement({ id: 51 }).hasClass('ir-selectable'), true);
  areas.clearSelection();
  assert.equal(areas.selection.active, false);
  for (const id of [30, 31, 32]) {
    const el = hand.getCardElement({ id });
    assert.equal(el.hasClass('ir-selectable') || el.hasClass('ir-selected'), false);
  }
  assert.equal(composed.getCardElement({ id: 51 }).hasClass('ir-selectable'), false);
  assert.equal(hand.selectionMode, 'none');
  assert.deepEqual(hand.getSelection(), []);
  assert.equal(hand.onSelectionChange, undefined);
  FakeStock.log.length = 0;
});
