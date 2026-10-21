// カード定義データの整合検査（仕様書 002 §13）。
//   node scripts/check-card-data.mjs [card.csv のパス] [--csv]
// 常に行う検査（CI でも動く。card.csv は不要）:
//   1. 仕様書 002 の表の内部整合（件数、レベル別枚数、鍵・裏面の分布、識別子の一意性、想起効果の対応など）
//   2. 生成物（src/modules/php/Material/）が、仕様書から再生成した内容と一致すること
// card.csv があるときだけ行う検査（--csv を付けると無いときも失敗にする）:
//   3. card.csv と仕様書 002 の一致（名前・枚数・レベル・エネルギー・鍵・裏面・スコア・効果の種類・想起グループ）
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { compareCsv, findCsv, readCardCsv } from './lib/card-csv.mjs';
import { OUT_DIR, renderAll } from './lib/card-php.mjs';
import { expandMemoryIds, readCardSpec } from './lib/card-spec.mjs';

const args = process.argv.slice(2);
const requireCsv = args.includes('--csv');
const explicit = args.find((a) => !a.startsWith('--'));

const errors = [];
const fail = (msg) => errors.push(msg);
/** キーの順序に依存しない JSON 表現。 */
const canon = (v) => JSON.stringify(v, (_, x) => (x && typeof x === 'object' && !Array.isArray(x) ? Object.fromEntries(Object.entries(x).sort(([a], [b]) => (a < b ? -1 : 1))) : x));
const expect = (label, actual, expected) => {
  if (canon(actual) !== canon(expected)) {
    fail(`${label}: 実際=${JSON.stringify(actual)} 期待=${JSON.stringify(expected)}`);
  }
};
const sum = (xs) => xs.reduce((a, b) => a + b, 0);
const count = (xs, key) => xs.reduce((m, x) => ((m[key(x)] = (m[key(x)] ?? 0) + 1), m), {});
const idRe = /^[a-z][a-z0-9]*(_[a-z0-9]+)*$/;
const enumRe = /^[A-Z][A-Z0-9]*(_[A-Z0-9]+)*$/;
const unique = (label, xs) => {
  const dup = xs.filter((x, i) => xs.indexOf(x) !== i);
  if (dup.length > 0) fail(`${label}: 重複があります: ${[...new Set(dup)]}`);
};

const spec = readCardSpec();
const { memory, floors, analysis, ego, objectives } = spec;

// ---------- 1. 仕様書の表の内部整合 ----------

// 列挙
expect('鍵の種類', spec.keyTypes.map((k) => k.id), ['WISDOM', 'WILL', 'EMOTION', 'WILD']);
expect('効果の種類', spec.effectKinds.map((k) => k.id), ['TURN_START', 'ACTIVATED', 'CONSTANT', 'INSTANT', 'HELD', 'NONE']);
expect('想起効果の数', spec.recallEffects.length, 13);
expect('想起グループ', spec.recallGroups.map((g) => g.otype), ['c', 's', 'w', 'a', 'a2']);
for (const id of [...spec.keyTypes, ...spec.effectKinds, ...spec.recallEffects, ...spec.recallGroups].map((x) => x.id)) {
  if (!enumRe.test(id)) fail(`列挙値が UPPER_SNAKE_CASE ではありません: ${id}`);
}
const groupedEffects = spec.recallGroups.flatMap((g) => g.effects);
expect('3.5 のグループに全想起効果が 1 回ずつ現れる', [...groupedEffects].sort(), spec.recallEffects.map((e) => e.id).sort());

// 想い出カード
expect('想い出カードの種類数', memory.length, 99);
expect('想い出カードの ID', memory.map((m) => m.id), Array.from({ length: 99 }, (_, i) => `M${String(i + 1).padStart(3, '0')}`));
expect('想い出カードの枚数合計', sum(memory.map((m) => m.count)), 144);
const lv1 = memory.filter((m) => m.level === 1);
const lv2 = memory.filter((m) => m.level === 2);
expect('レベル 1 の種類数 / 枚数', [lv1.length, sum(lv1.map((m) => m.count))], [84, 120]);
expect('レベル 2 の種類数 / 枚数', [lv2.length, sum(lv2.map((m) => m.count))], [15, 24]);
expect('レベル 2 の ID（M085〜M099）', lv2.map((m) => m.id), expandMemoryIds('M085〜M099'));
const backCount = {};
for (const m of memory) backCount[m.back] = (backCount[m.back] ?? 0) + m.count;
expect('裏面ごとの枚数', backCount, { Rev1B: 40, Rev1F: 40, Rev1H: 40, Rev2B: 8, Rev2F: 8, Rev2H: 8 });
expect('裏面ごとの種類数（レベル 2）', count(lv2, (m) => m.back), { Rev2B: 5, Rev2H: 5, Rev2F: 5 });
for (const m of memory) {
  if (m.back !== `Rev${m.level}${m.back.at(-1)}`) fail(`${m.id}: 裏面 ${m.back} がレベル ${m.level} と合いません`);
  if (m.keys.length < 1 || m.keys.length > 2) fail(`${m.id}: 鍵の本数が ${m.keys.length} です`);
  if (!m.keys.every((k) => spec.keyTypes.some((t) => t.id === k))) fail(`${m.id}: 未知の鍵 ${m.keys}`);
  if (!(m.energy >= 2 && m.energy <= 7)) fail(`${m.id}: エネルギー ${m.energy} が 2〜7 の範囲外です`);
  if (!(m.score >= 1 && m.score <= 4)) fail(`${m.id}: スコア ${m.score} が 1〜4 の範囲外です`);
  if (!(m.count >= 1 && m.count <= 4)) fail(`${m.id}: 枚数 ${m.count} が 1〜4 の範囲外です`);
  if (!spec.effectKinds.some((k) => k.id === m.effectKind)) fail(`${m.id}: 未知の効果の種類 ${m.effectKind}`);
  if ((m.effectKind === 'NONE') !== (m.effectTextJa === '')) fail(`${m.id}: 効果の種類 NONE と効果文の有無が合いません`);
  if (!idRe.test(m.nameId)) fail(`${m.id}: name_id が snake_case ではありません: ${m.nameId}`);
  if (!spec.recallEffects.some((e) => e.id === m.recall)) fail(`${m.id}: 未知の想起効果 ${m.recall}`);
}
unique('想い出カードの name_id', memory.map((m) => m.nameId));
unique('想い出カードの名前', memory.map((m) => m.nameJa));
expect('効果文のある想い出カード', memory.filter((m) => m.effectTextJa !== '').length, 42);
expect(
  '効果文の無いカード（M001〜M051、M082〜M084、M091〜M093）',
  memory.filter((m) => m.effectKind === 'NONE').map((m) => m.id),
  expandMemoryIds('M001〜M051、M082〜M084、M091〜M093'),
);

// 鍵の構成（002 §5.2）
const shape = (m) => (m.keys.length === 1 ? (m.keys[0] === 'WILD' ? 'wild1' : 'single') : m.keys[0] === m.keys[1] ? (m.keys[0] === 'WILD' ? 'wildwild' : 'same2') : 'diff2');
const ids = (pred) => memory.filter(pred).map((m) => m.id);
expect('鍵 1 本・万能（M040〜M051）', ids((m) => shape(m) === 'wild1'), expandMemoryIds('M040〜M051'));
expect('鍵 2 本・異種（M082〜M090）', ids((m) => shape(m) === 'diff2'), expandMemoryIds('M082〜M090'));
expect('鍵 2 本・万能 + 万能（M091〜M093）', ids((m) => shape(m) === 'wildwild'), expandMemoryIds('M091〜M093'));
expect('鍵 2 本・同種（M094〜M099）', ids((m) => shape(m) === 'same2'), expandMemoryIds('M094〜M099'));
expect('鍵 1 本・単色', ids((m) => shape(m) === 'single'), expandMemoryIds('M001〜M039、M052〜M081'));
expect(
  '2 本鍵の個別構成（M082〜M090）',
  Object.fromEntries(memory.filter((m) => shape(m) === 'diff2').map((m) => [m.id, m.keys.join('+')])),
  {
    M082: 'WISDOM+EMOTION', M083: 'EMOTION+WILL', M084: 'WILL+WISDOM',
    M085: 'WISDOM+EMOTION', M086: 'EMOTION+WILL', M087: 'WILL+WISDOM',
    M088: 'WISDOM+WILL', M089: 'EMOTION+WISDOM', M090: 'WILL+EMOTION',
  },
);
// 1 本鍵のレベル 1 は 知恵・感情・意志 に偏りなく分かれる（裏面と鍵の種類の数）
expect('レベル 1 の裏面の種類数', count(lv1, (m) => m.back), { Rev1B: 28, Rev1H: 28, Rev1F: 28 });

// 想起効果: 3.4 の「該当する想い出カード」の列と 6 節の割り当てが一致すること
for (const e of spec.recallEffects) {
  const actual = memory.filter((m) => m.recall === e.id).map((m) => m.id);
  expect(`想起効果 ${e.id} の該当カード`, actual, [...e.listed].sort());
  expect(`想起効果 ${e.id} の種数`, actual.length, e.listedCount);
}
expect('想起効果の種数合計', sum(spec.recallEffects.map((e) => e.listedCount)), 99);

// 床カード
expect('床カードの ID', floors.map((f) => f.id), Array.from({ length: 12 }, (_, i) => `F${String(i + 1).padStart(2, '0')}`));
expect('床カードのレベル 2（四隅）', floors.filter((f) => f.level === 2).map((f) => f.defaultPosition), [1, 4, 7, 10]);
expect(
  '周遊ボーナスはレベル 2 のみ',
  floors.map((f) => (f.bonus !== 'NONE') === (f.level === 2)).every(Boolean),
  true,
);
expect('周遊ボーナスは 4 種が 1 つずつ', floors.map((f) => f.bonus).filter((b) => b !== 'NONE').sort(), ['ANALYSIS_TOKEN', 'COMPOSE_TOKEN', 'LIGHT', 'WILD_MOVE_KEY']);
expect('部屋名', floors.map((f) => f.roomName), ['DEMETER', 'HERMES', 'ATHENA', 'POSEIDON', 'ARES', 'DIONYSUS', 'HESTIA', 'APOLLO', 'APHRODITE', 'HADES', 'ARTEMIS', 'HEPHAESTUS']);
for (const f of floors) {
  if (f.nameId !== f.roomName.toLowerCase()) fail(`${f.id}: name_id が部屋名の小文字ではありません`);
}
unique('床カードの name_id', floors.map((f) => f.nameId));

// 精神カード
expect('精神カードの ID', analysis.map((a) => a.id), ['R1A', 'R1B', 'R1C', 'R2', 'R3A', 'R3B', 'R3C', 'R4', 'R5']);
expect('精神カードの階層ごとの枚数', count(analysis, (a) => a.floor), { 1: 3, 2: 1, 3: 3, 4: 1, 5: 1 });
expect('心光トークンが置かれる精神カード', analysis.filter((a) => a.hasLight).map((a) => a.id), ['R1A', 'R1B', 'R1C', 'R2', 'R3A', 'R3B', 'R3C', 'R4']);
expect('精神分析コスト', Object.fromEntries(analysis.map((a) => [a.id, a.cost])), {
  R1A: { WISDOM: 2 }, R1B: { WILL: 2 }, R1C: { EMOTION: 2 },
  R2: { WISDOM: 1, EMOTION: 1, WILL: 1 },
  R3A: { WISDOM: 3 }, R3B: { WILL: 3 }, R3C: { EMOTION: 3 },
  R4: { WISDOM: 2, EMOTION: 2, WILL: 2 },
  R5: { WISDOM: 4, EMOTION: 4, WILL: 4 },
});
for (const a of analysis) {
  for (const k of Object.keys(a.cost)) if (k === 'WILD' || !spec.keyTypes.some((t) => t.id === k)) fail(`${a.id}: コストに使えない鍵 ${k}`);
  if (a.id !== `R${a.floor}${a.branch ?? ''}`) fail(`${a.id}: ID が階層・分岐と合いません`);
}

// 自我カード
expect('自我カードの ID', ego.map((e) => e.id), ['I01', 'I02', 'I03', 'I04', 'I05', 'I06', 'I07', 'I08', 'E01', 'E02', 'E03', 'E04', 'E05']);
expect('強化自我の効果の種類', ego.filter((e) => e.kind === 'ENHANCEMENT').map((e) => e.effectKind), ['CONSTANT', 'CONSTANT', 'TURN_START', 'TURN_START', 'TURN_START']);
expect('初期自我の効果の種類は未割り当て', ego.filter((e) => e.kind === 'INITIAL').map((e) => e.effectKind), Array(8).fill(null));
for (const e of ego) {
  if (!idRe.test(e.nameId)) fail(`${e.id}: name_id が snake_case ではありません`);
  if (e.effectTextJa === '') fail(`${e.id}: 効果文が空です`);
}
unique('自我カードの name_id', ego.map((e) => e.nameId));

// 目標カード
expect('目標カードの ID', objectives.map((o) => o.id), ['O001', 'O002', 'O003', 'O004', 'O005', 'O006', 'O007']);
for (const o of objectives) {
  expect(`${o.id} の得点`, o.scoreTable.map(([, p]) => p), [1, 2, 3, 4]);
  const xs = o.scoreTable.map(([x]) => x);
  if (xs.some((x, i) => i > 0 && x <= xs[i - 1])) fail(`${o.id}: 得点表の X が昇順ではありません`);
  if (!idRe.test(o.nameId)) fail(`${o.id}: name_id が snake_case ではありません`);
}
expect('O001 の得点表', objectives[0].scoreTable, [[1, 1], [2, 2], [4, 3], [6, 4]]);
expect('O004 の得点表', objectives[3].scoreTable, [[1, 1], [3, 2], [5, 3], [7, 4]]);
unique('目標カードの name_id', objectives.map((o) => o.nameId));

// ---------- 2. 生成物が仕様書と一致すること ----------

const expected = renderAll(spec);
const handWritten = [
  'MemoryCardDefinition.php', 'FloorCardDefinition.php', 'AnalysisCardDefinition.php', 'EgoCardDefinition.php',
  'ObjectiveCardDefinition.php', 'FloorBonus.php', 'EgoKind.php',
  // 仕様書 008 の効果の定義（手書き。生成物ではない）
  'EffectCatalog.php', 'EffectSpec.php', 'EffectNode.php', 'EffectStep.php', 'ConditionRef.php', 'ResourceTouch.php',
  'Op.php', 'Event.php', 'Modifier.php', 'Condition.php', 'Control.php', 'Origin.php', 'ChoiceKind.php',
  'ResourceSymbol.php', 'Access.php', 'Mechanism.php', 'OwnerScope.php', 'EffectLimit.php', 'TokenKind.php',
  'ComposeContext.php', 'ActionKind.php', 'ActionContext.php', 'ValueExpr.php',
  // 回廊の位置の計算（手書き）
  'Corridor.php',
];
const present = existsSync(OUT_DIR) ? readdirSync(OUT_DIR).filter((f) => f.endsWith('.php')).sort() : [];
expect('Material/ のファイル一覧', present, [...Object.keys(expected), ...handWritten].sort());
for (const [name, text] of Object.entries(expected)) {
  const file = path.join(OUT_DIR, name);
  if (!existsSync(file)) continue;
  if (readFileSync(file, 'utf8') !== text) fail(`${name}: 仕様書 002 から再生成した内容と違います。npm run build:cards で再生成してください`);
}
// 手書きのファイルが、生成物で使う値を持っていること
const enumCases = (file) => [...readFileSync(path.join(OUT_DIR, file), 'utf8').matchAll(/^\s+case (\w+) = '/gm)].map((m) => m[1]);
if (existsSync(path.join(OUT_DIR, 'FloorBonus.php'))) {
  expect('FloorBonus の値', enumCases('FloorBonus.php'), ['NONE', 'ANALYSIS_TOKEN', 'LIGHT', 'COMPOSE_TOKEN', 'WILD_MOVE_KEY']);
}
if (existsSync(path.join(OUT_DIR, 'EgoKind.php'))) expect('EgoKind の値', enumCases('EgoKind.php'), ['INITIAL', 'ENHANCEMENT']);
for (const f of present) {
  const text = readFileSync(path.join(OUT_DIR, f), 'utf8');
  if (!text.includes('declare(strict_types=1);')) fail(`${f}: declare(strict_types=1) がありません`);
  if (text.includes('\r')) fail(`${f}: CR が含まれています`);
  if (!text.endsWith('\n') || text.endsWith('\n\n')) fail(`${f}: 末尾の改行が 1 つではありません`);
}

// ---------- 3. card.csv との一致（あるときだけ） ----------

const csvPath = findCsv(explicit);
let csvResult;
if (existsSync(csvPath)) {
  const diffs = compareCsv(spec, readCardCsv(csvPath));
  for (const d of diffs) fail(`card.csv: ${d}`);
  csvResult = `card.csv と一致を確認（${csvPath}）`;
} else if (requireCsv) {
  fail(`card.csv が見つかりません: ${csvPath}`);
} else {
  csvResult = 'card.csv が無いので、card.csv との照合は省略';
}

if (errors.length > 0) {
  console.error(`check-card-data: ${errors.length} 件の問題があります。`);
  for (const e of errors) console.error(`  ${e}`);
  process.exit(1);
}
console.log(`check-card-data: OK（想い出 ${memory.length} 種 ${sum(memory.map((m) => m.count))} 枚、床 ${floors.length}、精神 ${analysis.length}、自我 ${ego.length}、目標 ${objectives.length}）`);
if (csvResult) console.log(`  ${csvResult}`);
