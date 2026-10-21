// U-4: 共通の領域の表示モデル（純粋関数）と、偽の DOM に対する setup・通知の通し確認。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
  backSpriteKey, cleanupRefills, colorName, decksModel, discardModel, egoCard, faceUpRefs, floorKey, levelOfPosition, levelTable,
  memKey, meepleSlot, objectiveKey, objectiveStatusMap, objectivesModel, openModel, personaPositions, roomsModel, roomsSwappedAfter,
  spiritModel, toInt,
} from '../../src/modules/js/board/view-model.js';

test('キーの組み立て: 0 埋めと不正値', () => {
  assert.equal(memKey(14), 'M014');
  assert.equal(memKey('7'), 'M007');
  assert.equal(floorKey(3), 'F03');
  assert.equal(objectiveKey('2'), 'O002');
  assert.equal(backSpriteKey('1B'), 'Rev1B');
  assert.equal(backSpriteKey(null), null);
  assert.equal(backSpriteKey(''), null);
  assert.equal(toInt('12'), 12);
  assert.equal(toInt(undefined), 0);
});

test('levelOfPosition と levelTable', () => {
  assert.deepEqual([1, 2, 3, 4, 7, 10, 12].map(levelOfPosition), [2, 1, 1, 2, 2, 2, 1]);
  const table = levelTable({ memory: [{ type: 1, level: 1 }, { type: '60', level: '2' }] });
  assert.equal(table.get(60), 2);
  assert.equal(levelTable(null).size, 0);
});

test('colorName: 完全一致・近い色・不正値', () => {
  assert.equal(colorName('b3202a'), 'red');
  assert.equal(colorName('2a4fa0'), 'blue');
  assert.equal(colorName('#5f9c3a'), 'green');
  assert.equal(colorName('d6b61e'), 'yellow');
  assert.equal(colorName('ff0000'), 'red');
  assert.equal(colorName('0000ff'), 'blue');
  assert.equal(colorName('zzz'), 'red');
  assert.equal(colorName(undefined), 'red');
});

test('meepleSlot: 席順で 0〜3', () => {
  assert.equal(meepleSlot([10, 20, 30], 20), 1);
  assert.equal(meepleSlot([10, 20, 30], 99), 0);
  assert.equal(meepleSlot([1, 2, 3, 4, 5], 5), 3);
});

test('roomsModel: 裏向き・想起中の表向き・カード無し、位置の昇順', () => {
  const rooms = roomsModel([
    { pos: 2, floor: 5, level: 1, card: { id: 11, back: '1F', faceUp: true, type: 14 }, under: [] },
    { pos: 1, floor: 1, level: 2, card: { id: '57', back: '2B', faceUp: false }, under: [] },
    { pos: 3, floor: 2, card: null },
    { pos: 13, floor: 2, card: null },
  ]);
  assert.deepEqual(rooms.map((r) => r.pos), [1, 2, 3]);
  assert.deepEqual(rooms[0], { pos: 1, level: 2, floor: { id: 'F01', key: 'F01' }, card: { id: 57, key: 'Rev2B' } });
  assert.deepEqual(rooms[1].card, { id: 11, key: 'M014' });
  assert.equal(rooms[2].card, null);
  assert.deepEqual(roomsModel(undefined), []);
});

test('personaPositions: 未配置（0）は含めない', () => {
  const map = personaPositions({ 2310001: { persona: 4 }, 2310002: { persona: 0 }, 2310003: {} });
  assert.deepEqual([...map], [[2310001, 4]]);
});

test('openModel: 範囲外と重複のスロットを除く', () => {
  const open = openModel([{ slot: 5, id: 9, type: 1 }, { slot: 0, id: 1, type: 2 }, { slot: 0, id: 3, type: 4 }, { slot: 6, id: 4, type: 5 }, { slot: -1, id: 5, type: 6 }]);
  assert.deepEqual(open.map((o) => [o.slot, o.card.id, o.card.key]), [[0, 1, 'M002'], [5, 9, 'M001']]);
});

test('decksModel: 一番上の裏面・空の山・欠けた山', () => {
  const decks = decksModel({ 1: { count: '52', top: '1F' }, 2: { count: 0, top: null } });
  assert.equal(decks[1].count, 52);
  assert.equal(decks[1].top?.key, 'Rev1F');
  assert.equal(decks[2].count, 0);
  assert.equal(decks[2].top, null);
  assert.deepEqual(decksModel(undefined), { 1: { count: 0, top: null }, 2: { count: 0, top: null } });
  // 枚数が 0 なら top があっても裏面は出さない
  assert.equal(decksModel({ 1: { count: 0, top: '1B' } })[1].top, null);
});

test('discardModel: 一番上は最後に捨てたカード', () => {
  assert.deepEqual(discardModel([{ id: 8, type: 3 }, { id: 9, type: 4 }]), { count: 2, top: { id: 9, key: 'M004' } });
  assert.deepEqual(discardModel([]), { count: 0, top: null });
  assert.deepEqual(discardModel(undefined), { count: 0, top: null });
});

test('spiritModel: キューブの位置と開始位置、席順', () => {
  const spirit = [
    { id: 'R3B', tier: 3, slot: 1, light: true },
    { id: 'R1A', tier: 1, slot: 0, light: true },
    { id: 'R1C', tier: 1, slot: 1, light: false },
  ];
  const model = spiritModel(spirit, { 20: { spirit: 'R1C' }, 10: { spirit: null }, 30: { spirit: 'R1C' } }, [30, 10, 20]);
  assert.deepEqual(model.cards.map((c) => c.id), ['R1A', 'R1C', 'R3B']);
  assert.deepEqual(model.cards[1].cubes, [30, 20]);
  assert.deepEqual(model.start, [10]);
  assert.deepEqual(spiritModel(undefined, undefined, []), { cards: [], start: [] });
});

test('objectivesModel: 場と獲得者、枠は獲得後も数える', () => {
  const model = objectivesModel([{ id: 1, slot: 0, owner: null }, { id: 2, slot: 1, owner: 2310001 }, { id: 3, slot: 2, owner: null }]);
  assert.equal(model.slotCount, 3);
  assert.deepEqual(model.common.map((c) => [c.slot, c.card.key]), [[0, 'O001'], [2, 'O003']]);
  assert.deepEqual(model.owned.map((o) => [o.owner, o.card.key]), [[2310001, 'O002']]);
  assert.equal(objectivesModel(undefined).slotCount, 0);
});

test('egoCard と objectiveStatusMap と faceUpRefs', () => {
  assert.deepEqual(egoCard('I05'), { id: 'I05', key: 'I05' });
  assert.equal(egoCard(null), null);
  assert.equal(egoCard('X01'), null);
  const status = objectiveStatusMap([{ obj_id: 2, x: 1, points: '1', claimable: false }]);
  assert.deepEqual(status.get('O002'), { x: 1, points: 1, claimable: false });
  assert.equal(objectiveStatusMap([{ id: 3, x: 4, points: 2, claimable: true }]).get('O003')?.claimable, true);
  // 非公開（type が無い）の cardRef は除く
  assert.deepEqual(faceUpRefs([{ id: 1, type: 2 }, { id: 3 }, null]), [{ id: 1, type: 2 }]);
});

test('通知の args の正規化: cleanup と roomsSwapped', () => {
  const refill = cleanupRefills({ rooms: [{ pos: 3, card_id: 77, back: '1H' }], open: [{ slot: 2, id: 5, type: 9 }] });
  assert.deepEqual(refill.rooms, [{ pos: 3, card: { id: 77, key: 'Rev1H' } }]);
  assert.deepEqual(refill.open.map((o) => o.card.key), ['M009']);
  assert.deepEqual(roomsSwappedAfter({ kind: 'FLOOR', after: [{ pos: 2, floor_id: 6 }, { pos: 3, floor_id: 5 }] }).after.map((a) => a.card.key), ['F06', 'F05']);
  assert.deepEqual(roomsSwappedAfter({ kind: 'CARD', after: [{ pos: 2, card_id: 4, back: '1B' }] }).after, [{ pos: 2, card: { id: 4, key: 'Rev1B' } }]);
  assert.deepEqual(cleanupRefills({}), { rooms: [], open: [] });
});
