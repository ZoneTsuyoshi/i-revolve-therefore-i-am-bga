// U-7 / UT-09（008 §12.1）: texts.js のキーが、効果を持つカード ID（想い出 42、自我 13、目標 7）と一致する。
// ID は PHP の素材（src/modules/php/Material/）から取る。生成物 sprites.js には依存させない（003 §6.1）。実行: npm run test:js
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

/** `_()` は BGA が提供する。テストでは恒等関数 */
/** @type {any} */ (globalThis)._ = (/** @type {string} */ text) => text;

const { buildCardTexts } = await import('../../src/modules/js/ui/texts.js');
const { ICONS, iconNamesIn, tokenizeText } = await import('../../src/modules/js/ui/icon-names.js');

const material = (/** @type {string} */ file) => readFileSync(fileURLToPath(new URL(`../../src/modules/php/Material/${file}`, import.meta.url)), 'utf8');
const texts = buildCardTexts();

/** PHP の素材から ID を取る */
const memory = [...material('MemoryCards.php').matchAll(/'(M\d{3})' => \[(.*?)\n {8}\],/gs)].map((m) => ({
  id: m[1],
  kind: /EffectKind::(\w+)/.exec(m[2])?.[1],
}));
const ids = (/** @type {string} */ file, /** @type {RegExp} */ pattern) => [...material(file).matchAll(pattern)].map((m) => m[1]);
const egoIds = ids('EgoCards.php', /^ {8}'([IE]\d{2})' => \[/gm);
const objectiveIds = ids('ObjectiveCards.php', /^ {8}'(O\d{3})' => \[/gm);
const floorIds = ids('FloorCards.php', /^ {8}'(F\d{2})' => \[/gm);
const spiritIds = ids('AnalysisCards.php', /^ {8}'(R\d[ABC]?)' => \[/gm);
const recallIds = ids('RecallEffect.php', /case (\w+) = /g);
const kindIds = ids('EffectKind.php', /case (\w+) = /g).filter((k) => k !== 'NONE');

const sorted = (/** @type {Iterable<string>} */ items) => [...items].sort();
const keysWith = (/** @type {keyof import('../../src/modules/js/ui/texts.js').CardText} */ field) =>
  Object.entries(texts.cards).filter(([, t]) => t[field] !== undefined).map(([id]) => id);

test('素材の読み取り: 想い出 99・自我 13・目標 7・床 12・精神 9・想起効果 13・効果の種類 5', () => {
  assert.equal(memory.length, 99);
  assert.equal(egoIds.length, 13);
  assert.equal(objectiveIds.length, 7);
  assert.equal(floorIds.length, 12);
  assert.equal(spiritIds.length, 9);
  assert.equal(recallIds.length, 13);
  assert.equal(kindIds.length, 5);
});

test('UT-09: effect を持つキーが、効果のある想い出 42 種と自我 13 種に一致する', () => {
  const withEffect = memory.filter((m) => m.kind !== 'NONE').map((m) => m.id);
  assert.equal(withEffect.length, 42);
  assert.deepEqual(sorted(keysWith('effect')), sorted([...withEffect, ...egoIds]));
});

test('UT-09: condition を持つキーが、目標 7 種に一致する', () => {
  assert.deepEqual(sorted(keysWith('condition')), sorted(objectiveIds));
  assert.deepEqual(sorted(keysWith('x')), sorted(objectiveIds));
});

test('UT-09: 全カードの ID が過不足なくある（想い出 99、床 12、精神 9、自我 13、目標 7）', () => {
  const all = [...memory.map((m) => m.id), ...floorIds, ...spiritIds, ...egoIds, ...objectiveIds];
  assert.deepEqual(sorted(Object.keys(texts.cards)), sorted(all));
  for (const [id, text] of Object.entries(texts.cards)) {
    assert.equal(typeof text.name, 'string', `${id} の name`);
    assert.notEqual(text.name, '', `${id} の name`);
  }
  assert.deepEqual(sorted(keysWith('action')), sorted(floorIds));
  assert.deepEqual(sorted(keysWith('bonus')), sorted(spiritIds));
});

test('UT-09: 想起効果 13 種・効果の種類 5 種が過不足なくある', () => {
  assert.deepEqual(sorted(Object.keys(texts.recall)), sorted(recallIds));
  assert.deepEqual(sorted(Object.keys(texts.kinds)), sorted(kindIds));
});

/** 表の全文字列（名前・効果文・想起効果など） */
function allStrings() {
  const out = [];
  for (const text of Object.values(texts.cards)) {
    out.push(...Object.values(text));
  }
  out.push(...Object.values(texts.recall), ...Object.values(texts.kinds));
  return out;
}

test('アイコン記法は既知のアイコンだけ（009 §8.3）', () => {
  for (const str of allStrings()) {
    for (const name of iconNamesIn(str)) {
      assert.ok(Object.hasOwn(ICONS, name), `未知のアイコン {${name}}: ${str}`);
    }
  }
});

test('英語の文字列の体裁: 日本語・HTML・表の区切り・改行を含まず、文末にピリオドを付けない', () => {
  for (const str of allStrings()) {
    assert.doesNotMatch(str, /[぀-ヿ㐀-鿿]/, `日本語が混ざっている: ${str}`);
    assert.doesNotMatch(str, /[<>|\n]/, `使えない文字: ${str}`);
    assert.equal(str, str.trim(), `前後の空白: ${str}`);
    assert.doesNotMatch(str, /\.$/, `文末のピリオド: ${str}`);
    assert.doesNotMatch(str, /\{\s|\s\}/, `波括弧の中の空白: ${str}`);
  }
});

test('tokenizeText: アイコンと文字に分ける。未知の波括弧は文字のまま', () => {
  assert.deepEqual(tokenizeText('Gain 1 Psychic Light {light}'), [{ text: 'Gain 1 Psychic Light ' }, { icon: 'light' }]);
  assert.deepEqual(tokenizeText('{wisdom}{will} <b>'), [{ icon: 'wisdom' }, { icon: 'will' }, { text: ' <b>' }]);
  assert.deepEqual(tokenizeText('a {nope} b'), [{ text: 'a {nope} b' }]);
  assert.deepEqual(tokenizeText(''), []);
});

test('主要な用語が 009 §8.5・game-description の英訳案に合っている', () => {
  const text = JSON.stringify(allStrings());
  // 同じ意味に別の語を使わない（compose / light / analysis action などの旧案を残さない）
  assert.doesNotMatch(text, /compose action|Compose action|your next compose/i);
  assert.doesNotMatch(text, /\bpersona\b/);
  assert.match(texts.cards.F02.action ?? '', /Psychoanalysis/);
  assert.match(texts.cards.M090.effect ?? '', /Omni-Move Key token/);
});

test('レビュー用の対照表（docs/publishing/card-texts-review.md）が texts.js の全文を含む', () => {
  const doc = readFileSync(fileURLToPath(new URL('../../docs/publishing/card-texts-review.md', import.meta.url)), 'utf8');
  for (const str of allStrings()) {
    assert.ok(doc.includes(str), `対照表に無い英語: ${str}`);
  }
});
