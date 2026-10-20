// U-4: 偽の DOM・偽の bga-cards で、実際のセットアップ相当の gamedatas（PublicView の形）を setup に通し、通知で表示が動くことを確認する
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { FakeBgaAnimations, FakeBgaCards, FakeElement, FakeStock, installFakeDom } from './fake-dom.mjs';

installFakeDom();
const { buildBoard } = await import('../../src/modules/js/board/Board.js');
const { PlayerAreas } = await import('../../src/modules/js/board/PlayerAreas.js');
const { CommonArea } = await import('../../src/modules/js/board/CommonArea.js');
const { createCardManagers } = await import('../../src/modules/js/cards/managers.js');
const { CommonNotifications } = await import('../../src/modules/js/notifications.js');

const P1 = 2310001;
const P2 = 2310002;
const players = { [P1]: { id: String(P1), name: 'Alice', color: 'b3202a' }, [P2]: { id: String(P2), name: 'Bob', color: '2a4fa0' } };

/** 2 人用の初期状態（PublicView::allDatas の形。数値の一部は文字列で届くことがあるので混ぜる） */
function gamedatas() {
  const lv1 = [2, 3, 5, 6, 8, 9, 11, 12];
  return {
    catalog: { memory: [{ type: 1, level: 1 }, { type: 60, level: 2 }, { type: 14, level: 1 }], floor: [], spirit: [], ego: [], objective: [] },
    playerorder: [P1, P2],
    rooms: Array.from({ length: 12 }, (_u, i) => ({
      pos: i + 1, floor: i + 1, level: lv1.includes(i + 1) ? 1 : 2,
      card: { id: 100 + i, back: lv1.includes(i + 1) ? '1B' : '2F', faceUp: false }, under: [],
    })),
    open: Array.from({ length: 6 }, (_u, i) => ({ slot: String(i), id: 200 + i, type: 10 + i })),
    decks: { 1: { count: 40, top: '1H' }, 2: { count: '20', top: '2B' } },
    discards: { 1: [{ id: 8, type: 3 }], 2: [] },
    spirit: [
      { id: 'R1A', tier: 1, slot: 0, light: true }, { id: 'R1B', tier: 1, slot: 1, light: true }, { id: 'R2', tier: 2, slot: 0, light: true },
      { id: 'R3A', tier: 3, slot: 0, light: true }, { id: 'R3C', tier: 3, slot: 1, light: true }, { id: 'R4', tier: 4, slot: 0, light: true },
      { id: 'R5', tier: 5, slot: 0, light: false },
    ],
    enhDeckCount: 2,
    objectives: [{ id: 1, slot: 0, owner: null }, { id: 4, slot: 1, owner: null }, { id: 6, slot: 2, owner: null }],
    playerData: {
      [P1]: { persona: 1, spirit: null, tier: 0, initialEgo: 'I02', enhancedEgo: null },
      [P2]: { persona: 4, spirit: 'R1A', tier: 1, initialEgo: null, enhancedEgo: null },
    },
    myObjectiveStatus: [{ id: 1, x: 2, points: 1, claimable: false }],
  };
}

/** @param {Record<string, any>} data */
function build(data) {
  const host = new FakeElement('div');
  const board = buildBoard(/** @type {any} */ (host), { players: data.players ?? players, playerOrder: [P1, P2], me: P1 });
  const cards = createCardManagers({ BgaCards: FakeBgaCards, BgaAnimations: FakeBgaAnimations, imgUrl: (f) => `img/${f}`, animationsActive: () => true });
  const playerAreas = new PlayerAreas({ refs: board, cards, BgaCards: FakeBgaCards, imgUrl: (f) => `img/${f}`, animationsActive: () => true, me: P1 });
  const common = new CommonArea({ refs: board, cards, BgaCards: FakeBgaCards, imgUrl: (f) => `img/${f}`, animationsActive: () => true, playerAreas, players });
  const setup = common.setup.bind(common);
  // 共通の領域の setup のあとにプレイヤーの領域の setup を続ける（Game.js と同じ順）
  common.setup = (/** @type {any} */ gd) => { setup(gd); playerAreas.setup(gd); };
  return { board, common, playerAreas };
}

const keysIn = (/** @type {FakeElement} */ el) => el.querySelectorAll('.ir-card').map((c) => c.dataset.key);

test('setup: 回廊・場札・山札・精神トラック・強化自我・目標が描かれる', () => {
  const { board, common } = build({});
  common.setup(gamedatas());

  assert.deepEqual([...board.roomFloors.values()].map((f) => keysIn(f)[0]), Array.from({ length: 12 }, (_u, i) => `F${String(i + 1).padStart(2, '0')}`));
  assert.equal(keysIn(/** @type {any} */ (board.roomCards.get(1)))[0], 'Rev2F');
  assert.equal(keysIn(/** @type {any} */ (board.roomCards.get(2)))[0], 'Rev1B');
  assert.deepEqual(board.openSlots.map((s) => keysIn(s)[0]), ['M010', 'M011', 'M012', 'M013', 'M014', 'M015']);
  assert.equal(common.decks[1].cardNumber, 40);
  assert.equal(common.decks[1].topCard.key, 'Rev1H');
  assert.equal(common.decks[2].cardNumber, 20);
  assert.equal(common.discards[1].cardNumber, 1);
  assert.equal(common.discards[1].topCard.key, 'M003');
  assert.equal(common.discards[2].cardNumber, 0);
  assert.equal(common.enhDeck.cardNumber, 2);

  // 精神トラック: 7 枚、心光は第一〜第四階層の 6 つ、キューブ（P1 は開始位置、P2 は R1A）
  assert.equal(common.spiritSlots.size, 7);
  assert.equal(common.lights.size, 6);
  assert.equal(common.cubes.get(P1)?.parentElement?.parentElement?.hasClass('ir-spirit-start'), true);
  assert.equal(common.cubes.get(P2)?.parentElement?.parentElement?.dataset.spirit, 'R1A');

  // 人格
  assert.equal(common.meeples.get(P1)?.parentElement?.hasClass('ir-room-meeples'), true);
  assert.equal(board.rooms.get(4)?.querySelector('.ir-meeple')?.dataset.player, String(P2));
  assert.equal(board.rooms.get(4)?.querySelector('.ir-meeple')?.dataset.irUi, 'meeple_blue');

  // 目標カード（3 枠）と公開済みの初期自我
  assert.deepEqual(common.goalStock.getCards().map((/** @type {any} */ c) => c.key), ['O001', 'O004', 'O006']);
  assert.deepEqual(keysIn(/** @type {any} */ (board.playerAreas.get(P1)?.ego)), ['I02']);
  // 自分の目標の「現在の X と得点」
  assert.equal(common.objectiveStatus.get('O001')?.points, 1);
});

test('setup: 空の gamedatas・欠けた値でも例外を出さない', () => {
  for (const data of [{}, { rooms: null, open: 'x', decks: [], spirit: {}, playerData: null, objectives: 5 }]) {
    const { common } = build({});
    assert.doesNotThrow(() => common.setup(/** @type {any} */ (data)));
  }
  const { common } = build({});
  assert.doesNotThrow(() => common.setup(/** @type {any} */ (undefined)));
});

test('通知: 入室・精神分析・捨札・補充・目標獲得・入れ替えで表示が動く', async () => {
  FakeStock.log.length = 0;
  const { board, common } = build({});
  common.setup(gamedatas());
  const notif = new CommonNotifications(common);

  // N06 入室: P1 のミープルが部屋 2 へ
  await notif.notif_roomEntered({ player_id: String(P1), pos: '2' });
  assert.equal(common.meeples.get(P1)?.parentElement?.parentElement, board.rooms.get(2));

  // N09 想起: 部屋 2 のカードが表向きに
  await notif.notif_recallStarted({ pos: 2, card: { id: 101, type: 14 } });
  assert.equal(keysIn(/** @type {any} */ (board.roomCards.get(2)))[0], 'M014');

  // N14 想起の獲得: 部屋のカードが無くなる
  await notif.notif_roomCardAcquired({ player_id: P1, pos: 2 });
  assert.deepEqual(keysIn(/** @type {any} */ (board.roomCards.get(2))), []);

  // N29 クリーンアップ: 部屋 2 と場札スロット 1 を補充、山の枚数と一番上が変わる
  await notif.notif_cleanup({
    player_id: P1, rooms: [{ pos: 2, card_id: 300, back: '1F' }], open: [{ slot: 1, id: 301, type: 20 }],
    decks: { 1: { count: 38, top: '1B' }, 2: { count: 20, top: '2B' } },
  });
  assert.equal(keysIn(/** @type {any} */ (board.roomCards.get(2)))[0], 'Rev1F');
  assert.equal(common.decks[1].cardNumber, 38);
  assert.equal(common.decks[1].topCard.key, 'Rev1B');

  // N13 場札を取る
  await notif.notif_cardsGained({ player_id: P2, source: 'OPEN', cards: [{ id: 200, type: 10 }] });
  assert.deepEqual(keysIn(board.openSlots[0]), []);

  // N15 捨札（レベル 2 のカード = 種類 60）
  await notif.notif_cardsDiscarded({ player_id: P1, cards: [{ id: 400, type: 60 }], pile_counts: { 1: 1, 2: 1 } });
  assert.equal(common.discards[2].cardNumber, 1);
  assert.equal(common.discards[2].topCard.key, 'M060');

  // N24 精神分析: P2 が R1A から R2 へ。R2 の心光を取る
  await notif.notif_spiritAdvanced({ player_id: P2, from_spirit: 'R1A', to_spirit: 'R2', tier: 2, light_taken: true });
  assert.equal(common.cubes.get(P2)?.parentElement, common.spiritSlots.get('R2'));
  assert.equal(common.lights.has('R2'), false);

  // N25 強化自我
  await notif.notif_enhancedEgoObtained({ player_id: P2, ego_id: 'E03', deck_count: 1 });
  assert.equal(common.enhDeck.cardNumber, 1);
  assert.deepEqual(keysIn(/** @type {any} */ (board.playerAreas.get(P2)?.enhEgo)), ['E03']);

  // N22 目標の獲得
  await notif.notif_objectiveClaimed({ player_id: P2, obj_id: 4 });
  assert.deepEqual(common.goalStock.getCards().map((/** @type {any} */ c) => c.key), ['O001', 'O006']);
  assert.deepEqual(keysIn(/** @type {any} */ (board.playerAreas.get(P2)?.ownedGoals)), ['O004']);

  // N28 床の入れ替え（部屋 2 と 3）
  await notif.notif_roomsSwapped({ kind: 'FLOOR', pos_a: 2, pos_b: 3, after: [{ pos: 2, floor_id: 3 }, { pos: 3, floor_id: 2 }] });
  assert.deepEqual(keysIn(/** @type {any} */ (board.roomFloors.get(2))), ['F03']);
  assert.deepEqual(keysIn(/** @type {any} */ (board.roomFloors.get(3))), ['F02']);

  // N23 目標の状況
  await notif.notif_objectiveStatus({ statuses: [{ obj_id: 6, x: 0, points: 0, claimable: false }] });
  assert.equal(common.objectiveStatus.has('O001'), false);
  assert.equal(common.objectiveStatus.get('O006')?.x, 0);
});

test('通知: args が欠けていても例外を出さない', async () => {
  const { common } = build({});
  common.setup(gamedatas());
  const notif = new CommonNotifications(common);
  for (const name of Object.getOwnPropertyNames(CommonNotifications.prototype).filter((n) => n.startsWith('notif_'))) {
    await assert.doesNotReject(() => /** @type {any} */ (notif)[name]({}), name);
    await assert.doesNotReject(() => /** @type {any} */ (notif)[name](undefined), name);
  }
});
