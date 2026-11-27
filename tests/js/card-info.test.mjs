// U-7: ツールチップ・拡大表示・案内パネルの中身（表示モデル）、拡大の寸法、カード名の下の英語名、通知とカード名キャプション。
// 生成物 sprites.js には依存しない部分だけ。実行: npm run test:js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { FakeBgaAnimations, FakeBgaCards, FakeElement, installFakeDom } from './fake-dom.mjs';

installFakeDom();
const { buildCardTexts } = await import('../../src/modules/js/ui/texts.js');
const { captionOf, describeCard, describeLock, formatScoreTable, fmt, indexCatalog, isLandscapeKey, keyKind } = await import('../../src/modules/js/ui/card-info-model.js');
const { Tips, SELECTABLE_SELECTOR } = await import('../../src/modules/js/ui/Tips.js');
const { recallFromGamedatas } = await import('../../src/modules/js/board/view-model.js');
const { createCardManagers } = await import('../../src/modules/js/cards/managers.js');
const { CommonNotifications } = await import('../../src/modules/js/notifications.js');

const texts = buildCardTexts();
/** PublicView::catalog の形（必要な部分） */
const catalog = indexCatalog({
  memory: [
    { type: 1, id: 'M001', level: 1, energy: 2, keys: ['WISDOM'], back: '1B', score: 1, effectKind: 'NONE', recall: 'DRAW_1' },
    { type: 62, id: 'M062', level: 1, energy: 3, keys: ['WILL'], back: '1F', score: 2, effectKind: 'ACTIVATED', recall: 'OPEN_DRAW_1' },
    { type: 98, id: 'M098', level: 2, energy: 7, keys: ['EMOTION', 'EMOTION'], back: '2H', score: 4, effectKind: 'HELD', recall: 'DRAW_1' },
  ],
  floor: [{ id: 1, code: 'F01', level: 2, bonus: 'ANALYSIS_TOKEN' }, { id: 2, code: 'F02', level: 1, bonus: 'NONE' }],
  spirit: [{ id: 'R2', tier: 2, cost: { WISDOM: 1, EMOTION: 1, WILL: 1 }, hasLight: true }, { id: 'R5', tier: 5, cost: { WISDOM: 4, EMOTION: 4, WILL: 4 }, hasLight: false }],
  ego: [{ id: 'I05', kind: 'INITIAL', effectKind: null }, { id: 'E03', kind: 'ENHANCEMENT', effectKind: 'TURN_START' }],
  objective: [{ id: 1, code: 'O001', scoreTable: [[1, 1], [2, 2], [4, 3], [6, 4]] }, { id: 4, code: 'O004', scoreTable: [[1, 1], [3, 2], [5, 3], [7, 4]] }],
});
const ctx = (/** @type {object} */ extra = {}) => ({ texts, catalog, ...extra });

test('keyKind / isLandscapeKey: スプライトのキーの種類', () => {
  assert.equal(keyKind('M014'), 'memory');
  assert.equal(keyKind('Rev2F'), 'back');
  assert.equal(keyKind('F12'), 'floor');
  assert.equal(keyKind('R3B'), 'spirit');
  assert.equal(keyKind('R5'), 'spirit');
  assert.equal(keyKind('I08'), 'ego');
  assert.equal(keyKind('E05'), 'ego');
  assert.equal(keyKind('O007'), 'objective');
  assert.equal(keyKind('token_light'), null);
  assert.equal(keyKind(''), null);
  assert.equal(isLandscapeKey('M001'), false);
  assert.equal(isLandscapeKey('Rev1B'), false);
  assert.equal(isLandscapeKey('F01'), true);
  assert.equal(isLandscapeKey('O001'), true);
});

test('想い出カード: 見出しは「No.N 英語名」、数値の行、効果の種類と効果文、想起効果', () => {
  const plain = describeCard('M001', ctx());
  assert.equal(plain?.title, 'No.1 Abandoned Idea');
  assert.equal(plain?.subtitle, 'Level 1');
  assert.deepEqual(plain?.stats, ['Energy: {energy} 2', 'Score: {score} 1', 'Keys: {wisdom}', 'Lock: {lock_wisdom}']);
  assert.deepEqual(plain?.sections, [{ label: 'Recall', text: 'Draw 1 Memory card from the deck {draw}' }]);
  assert.equal(plain?.landscape, false);

  const effect = describeCard('M062', ctx());
  assert.equal(effect?.title, 'No.62 Self-Contradiction');
  assert.equal(effect?.sections[0].label, 'Activated');
  assert.match(effect?.sections[0].text ?? '', /recall effect twice/);
  assert.equal(effect?.sections[1].label, 'Recall');

  const lv2 = describeCard('M098', ctx());
  assert.equal(lv2?.subtitle, 'Level 2');
  assert.ok(lv2?.stats.includes('Keys: {emotion}{emotion}'));
  assert.ok(lv2?.stats.includes('Lock: {lock_emotion}{lock_emotion}'));
  assert.equal(lv2?.sections[0].label, 'Held');
});

test('想い出カード: カタログが無くても名前と効果文は出る（落ちない）', () => {
  const bare = describeCard('M062', { texts, catalog: indexCatalog(undefined) });
  assert.equal(bare?.title, 'No.62 Self-Contradiction');
  assert.deepEqual(bare?.stats, []);
  assert.equal(bare?.sections.length, 1);
  assert.equal(bare?.sections[0].label, undefined);
  assert.equal(describeCard('X999', ctx()), null);
  assert.equal(describeCard('M500', ctx()), null);
});

test('裏向きの想い出カード: 錠前（レベル 2 は 2 つ）と移動コスト', () => {
  const back = describeCard('Rev2F', ctx());
  assert.equal(back?.title, 'Face-down memory card');
  assert.equal(back?.subtitle, 'Level 2');
  assert.deepEqual(back?.stats, ['Lock: {lock_will}{lock_will}']);
  assert.deepEqual(back?.sections, [{ text: 'Move cost: 2 × Will key' }]);
  assert.deepEqual(describeLock('1H'), { level: 1, count: 1, type: 'EMOTION', icons: '{lock_emotion}' });
  assert.equal(describeLock('9Z'), null);
});

test('床カード: 部屋名、レベル、周遊ボーナス、床アクション、同じ部屋の裏向きカードの錠前', () => {
  const corner = describeCard('F01', ctx({ roomLock: 'Rev2B' }));
  assert.equal(corner?.title, 'DEMETER');
  assert.equal(corner?.subtitle, 'Level 2 room');
  assert.deepEqual(corner?.stats, ['Tour bonus: {token_analysis} Psychoanalysis token']);
  assert.deepEqual(corner?.sections[0], { label: 'Floor action', text: 'Draw 3 Memory cards {draw}' });
  assert.equal(corner?.sections[1].label, 'Face-down memory card');
  assert.match(corner?.sections[1].text ?? '', /Move cost: 2 × Wisdom key/);
  const plain = describeCard('F02', ctx());
  assert.equal(plain?.subtitle, 'Level 1 room');
  assert.deepEqual(plain?.stats, []);
  assert.equal(plain?.sections.length, 1);
  assert.equal(plain?.landscape, true);
});

test('精神カード: 階層、コスト、心光の有無、ボーナス', () => {
  const tier2 = describeCard('R2', ctx({ spiritHasLight: () => true }));
  assert.equal(tier2?.title, 'Tier 2');
  assert.deepEqual(tier2?.stats, ['Cost: {lock_wisdom}{lock_emotion}{lock_will}', 'Psychic Light: {light}']);
  assert.equal(tier2?.sections[0].label, 'Bonus');
  const taken = describeCard('R2', ctx({ spiritHasLight: () => false }));
  assert.equal(taken?.stats[1], 'Psychic Light: taken');
  const top = describeCard('R5', ctx());
  assert.equal(top?.stats.length, 1);
  assert.match(top?.sections[0].text ?? '', /win the game immediately/);
});

test('自我カード: 初期自我は種類なし、強化自我は種類つき', () => {
  const initial = describeCard('I05', ctx());
  assert.equal(initial?.title, 'Entrepreneur');
  assert.equal(initial?.subtitle, 'Initial Ego');
  assert.equal(initial?.sections[0].label, undefined);
  const enhanced = describeCard('E03', ctx());
  assert.equal(enhanced?.subtitle, 'Enhanced Ego');
  assert.equal(enhanced?.sections[0].label, 'Turn start');
});

test('目標カード: 条件・X の定義・得点表、現在の X', () => {
  const goal = describeCard('O004', ctx({ objectiveStatus: () => ({ x: 6, points: 3 }) }));
  assert.equal(goal?.title, 'Hand Goal');
  assert.deepEqual(goal?.stats, ['Your current X: 6 (3 points)']);
  assert.deepEqual(goal?.sections.map((s) => s.label), ['Condition', 'X', 'Score (X → points)']);
  assert.equal(goal?.sections[2].text, '1–2 → 1, 3–4 → 2, 5–6 → 3, 7+ → 4');
  assert.equal(describeCard('O001', ctx())?.stats.length, 0);
});

test('formatScoreTable: 範囲の書き方', () => {
  assert.equal(formatScoreTable([[1, 1], [2, 2], [4, 3], [6, 4]]), '1 → 1, 2–3 → 2, 4–5 → 3, 6+ → 4');
  assert.equal(formatScoreTable([[1, 1], [2, 2], [3, 3], [4, 4]]), '1 → 1, 2 → 2, 3 → 3, 4+ → 4');
  assert.equal(formatScoreTable(undefined), '');
});

test('fmt: ${name} の置き換え。足りない値はそのまま', () => {
  assert.equal(fmt('Level ${level} room', { level: 2 }), 'Level 2 room');
  assert.equal(fmt('${a} ${b}', { a: 'x' }), 'x ${b}');
});

test('captionOf: 想い出は「No.N 英語名」、他は名前。裏面・不明は undefined', () => {
  assert.equal(captionOf('M098', texts), "No.98 Warmth of Mother's Hand");
  assert.equal(captionOf('F07', texts), 'HESTIA');
  assert.equal(captionOf('I01', texts), 'Maestro');
  assert.equal(captionOf('R3A', texts), 'Tier 3');
  assert.equal(captionOf('O002', texts), 'Level 2 Composed Goal');
  assert.equal(captionOf('Rev1B', texts), undefined);
  assert.equal(captionOf('', texts), undefined);
});

test('createCardManagers: captionOf が返す英語名を ir-caption に入れる。裏面には入れない', () => {
  /** @type {any} */
  let options;
  const BgaCards = { Manager: class { constructor(/** @type {any} */ o) { options = o; } } };
  createCardManagers({
    BgaCards, BgaAnimations: FakeBgaAnimations, imgUrl: (f) => f, animationsActive: () => false,
    captionOf: (key) => captionOf(key, texts),
  });
  const div = new FakeElement('div');
  options.setupDiv({ id: 5, key: 'M001' }, div);
  assert.equal(div.children.length, 1);
  assert.equal(div.children[0].className, 'ir-caption');
  assert.equal(div.children[0].textContent, 'No.1 Abandoned Idea');
  const back = new FakeElement('div');
  options.setupDiv({ id: 6, key: 'Rev1B' }, back);
  assert.equal(back.children.length, 0);
  const explicit = new FakeElement('div');
  options.setupDiv({ id: 7, key: 'M001', caption: 'X' }, explicit);
  assert.equal(explicit.children[0].textContent, 'X');
  assert.ok(FakeBgaCards);
});

test('Tips: 選べる要素のセレクターに、旧クラスと新クラス（U-19）の両方がある', () => {
  for (const name of ['ir-selectable', 'ir-selected', 'bga-cards_selectable-card', 'bga-cards_selected-card', 'ir-room-selectable', 'ir-token-pick', 'ir-can', 'ir-picked', 'ir-off']) {
    assert.ok(SELECTABLE_SELECTOR.includes(`.${name}`), name);
  }
});

test('Tips: 長押しで出した直後の click だけ止める。通常のクリックは止めない', () => {
  const tips = new Tips({ root: new FakeElement('div'), texts, catalog: undefined, imgUrl: (f) => f });
  let stopped = 0;
  const event = { target: null, preventDefault() {}, stopPropagation() { stopped += 1; } };
  tips.handleClick(event);
  assert.equal(stopped, 0, '通常のクリックは止めない（選択に届く）');
  tips.suppressClickUntil = performance.now() + 500;
  tips.handleClick(event);
  assert.equal(stopped, 1);
  assert.equal(tips.suppressClickUntil, 0, '1 回だけ');
  tips.handleClick(event);
  assert.equal(stopped, 1);
});

test('Tips.dismiss: 管理役がなくても例外にならない', () => {
  Tips.dismiss();
});

test('recallFromGamedatas: 想起フェイズで表向きの部屋のカードがあるときだけ', () => {
  const rooms = [{ pos: 1, card: { id: 5, back: '1B', faceUp: false } }, { pos: 2, card: { id: 6, back: '1F', faceUp: true, type: 62 } }];
  assert.deepEqual(recallFromGamedatas({ phase: 'RECALL', rooms, turn: { recall: { activators: ['10', 11] } } }), { key: 'M062', activators: [10, 11] });
  assert.deepEqual(recallFromGamedatas({ phase: 'RECALL', rooms }), { key: 'M062', activators: [] });
  assert.equal(recallFromGamedatas({ phase: 'MOVE', rooms }), null);
  assert.equal(recallFromGamedatas({ phase: 'RECALL', rooms: [] }), null);
  assert.equal(recallFromGamedatas(undefined), null);
});

test('通知: 想起の開始で案内パネルを出し、獲得・クリーンアップ・想起以外のフェイズで閉じる', async () => {
  /** @type {string[]} */
  const log = [];
  const common = {
    revealRoomCard: async () => { log.push('reveal'); },
    syncDecks() {},
    deckElement: () => undefined,
    setRoomCard: async () => {},
    addToOpen: async () => {},
    removeRoomCard: async () => {},
    areaOf: () => undefined,
  };
  const recall = { showRecall: (/** @type {any} */ p) => log.push(`show ${p.key} ${p.activators.join(',')}`), hideRecall: () => log.push('hide') };
  const n = new CommonNotifications(/** @type {any} */ (common), null, recall);
  await n.notif_recallStarted({ pos: 2, card: { id: 6, type: '62' }, activators: ['10', '11'] });
  assert.deepEqual(log, ['show M062 10,11', 'reveal']);
  log.length = 0;
  await n.notif_phaseStarted({ phase: 'RECALL' });
  assert.deepEqual(log, []);
  await n.notif_phaseStarted({ phase: 'FLOOR' });
  await n.notif_roomCardAcquired({ pos: 2, player_id: 10 });
  await n.notif_cleanup({ decks: {}, rooms: [], open: [] });
  assert.deepEqual(log, ['hide', 'hide', 'hide']);
  // パネル無しでも落ちない
  await new CommonNotifications(/** @type {any} */ (common)).notif_recallStarted({ pos: 2, card: { id: 6, type: 62 } });
});
