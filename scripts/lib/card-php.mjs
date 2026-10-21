// 仕様書 002 のモデルから、src/modules/php/Material/ の生成物（PHP）を組み立てる。
// 戻り値は { ファイル名: 内容 }。build-card-data.mjs が書き出し、check-card-data.mjs が再生成して突き合わせる。

import path from 'node:path';
import { fileURLToPath } from 'node:url';

/** 生成物の出力先。 */
export const OUT_DIR = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../src/modules/php/Material');

export const NAMESPACE = 'Bga\\Games\\IRevolveThereforeIAm\\Material';

const HEADER = `<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ${NAMESPACE};

// 自動生成。仕様書 002 の表から scripts/build-card-data.mjs が生成した。手で編集しない。
// 変更は仕様書 002（または card.csv）を直して、npm run build:cards で再生成する。

`;

// ---------- 値の整形 ----------

/** PHP の単引用符文字列。長い文字列は 60 文字ごとに連結へ分ける（行長 140 文字の規約のため）。 */
function str(s, indent) {
  const quote = (t) => `'${t.replaceAll('\\', '\\\\').replaceAll("'", "\\'")}'`;
  const chars = Array.from(s);
  if (chars.length <= 70) return quote(s);
  const parts = [];
  for (let i = 0; i < chars.length; i += 60) parts.push(quote(chars.slice(i, i + 60).join('')));
  return parts.join(` .\n${indent}    `);
}
const list = (items) => `[${items.join(', ')}]`;
const enumCase = (type, id) => `${type}::${id}`;

// ---------- 列挙 ----------

function renderEnum(name, doc, cases, extra = '') {
  const body = cases.map((c) => `    /** ${c.doc} */\n    case ${c.name} = '${c.name}';\n`).join('\n');
  return `${HEADER}/**\n * ${doc}\n */\nenum ${name}: string\n{\n${body}${extra}}\n`;
}

function keyType(spec) {
  return renderEnum('KeyType', '鍵の種類（002 §3.2）。', spec.keyTypes.map((k) => ({ name: k.id, doc: k.ja })));
}

function effectKind(spec) {
  return renderEnum(
    'EffectKind',
    '効果の種類（002 §3.3）。`NONE` は効果文が無いカード。',
    spec.effectKinds.map((k) => ({ name: k.id, doc: k.ja })),
  );
}

function recallGroup(spec) {
  return renderEnum(
    'RecallGroup',
    '想起効果のグループ（002 §3.5）。card.csv の `otype` 列に対応する。',
    spec.recallGroups.map((g) => ({ name: g.id, doc: `${g.ja}（otype \`${g.otype}\`）` })),
    `\n    /** card.csv の \`otype\` 列の値。 */\n    public function otype(): string\n    {\n        return match ($this) {\n${spec.recallGroups
      .map((g) => `            self::${g.id} => '${g.otype}',\n`)
      .join('')}        };\n    }\n`,
  );
}

function recallEffect(spec) {
  const groupOf = new Map();
  for (const g of spec.recallGroups) for (const e of g.effects) groupOf.set(e, g.id);
  const arms = spec.recallEffects.map((e) => `            self::${e.id} => RecallGroup::${groupOf.get(e.id)},\n`).join('');
  return renderEnum(
    'RecallEffect',
    '想起効果の識別子（002 §3.4）。処理は仕様書 008。',
    spec.recallEffects.map((e) => ({ name: e.id, doc: e.meaning })),
    `\n    /** 想起効果のグループ（002 §3.5）。 */\n    public function group(): RecallGroup\n    {\n        return match ($this) {\n${arms}        };\n    }\n`,
  );
}

const BACKS = [
  ['Rev1B', 1, 'WISDOM', 'レベル 1、本の錠前 × 1'],
  ['Rev1F', 1, 'WILL', 'レベル 1、炎の錠前 × 1'],
  ['Rev1H', 1, 'EMOTION', 'レベル 1、ハートの錠前 × 1'],
  ['Rev2B', 2, 'WISDOM', 'レベル 2、本の錠前 × 2'],
  ['Rev2F', 2, 'WILL', 'レベル 2、炎の錠前 × 2'],
  ['Rev2H', 2, 'EMOTION', 'レベル 2、ハートの錠前 × 2'],
];

function cardBack() {
  const levelArms = BACKS.map(([n, lv]) => `            self::${n} => ${lv},\n`).join('');
  const lockArms = BACKS.map(([n, , key]) => `            self::${n} => KeyType::${key},\n`).join('');
  const extra =
    `\n    /** 想い出カードのレベル。 */\n    public function level(): int\n    {\n        return match ($this) {\n${levelArms}        };\n    }\n` +
    `\n    /** 錠前（移動コスト）の種類。レベル 2 は同じ種類の錠前が 2 つ。 */\n    public function lock(): KeyType\n    {\n        return match ($this) {\n${lockArms}        };\n    }\n`;
  const body = BACKS.map(([n, , , doc]) => `    /** ${doc} */\n    case ${n} = '${n}';\n`).join('\n');
  return `${HEADER}/**\n * 想い出カードの裏面（錠前。002 §5.3）。値は画像名。\n */\nenum CardBack: string\n{\n${body}${extra}}\n`;
}

// ---------- データ ----------

function wrapClass(name, doc, constDoc, rowsText, returnType, ctor, getDoc) {
  return `${HEADER}/**\n * ${doc}\n */\nfinal class ${name}\n{\n    /** ${constDoc} */\n    private const ROWS = [\n${rowsText}    ];\n\n    /** @var array<string, ${returnType}>|null */\n    private static ?array $all = null;\n\n    /**\n     * @return array<string, ${returnType}> ID をキーとする定義（定義順）\n     */\n    public static function all(): array\n    {\n        if (self::$all === null) {\n            $all = [];\n            foreach (self::ROWS as $id => $row) {\n                $all[$id] = ${ctor};\n            }\n            self::$all = $all;\n        }\n\n        return self::$all;\n    }\n\n    /**\n     * ${getDoc}\n     *\n     * @throws \\OutOfBoundsException 未知の ID\n     */\n    public static function get(string $id): ${returnType}\n    {\n        return self::all()[$id] ?? throw new \\OutOfBoundsException("Unknown card id: {$id}");\n    }\n}\n`;
}

function memoryCards(spec) {
  const ind = '            ';
  const rowsText = spec.memory
    .map((m) => {
      const keys = list(m.keys.map((k) => enumCase('KeyType', k)));
      return (
        `        '${m.id}' => [\n` +
        `${ind}${str(m.nameJa, ind)}, ${str(m.nameEn, ind)}, '${m.nameId}',\n` +
        `${ind}${m.count}, ${m.level}, ${m.energy}, ${keys}, ${enumCase('CardBack', m.back)}, ${m.score},\n` +
        `${ind}${enumCase('EffectKind', m.effectKind)},\n` +
        `${ind}${str(m.effectTextJa, ind)},\n` +
        `${ind}${enumCase('RecallEffect', m.recall)},\n` +
        '        ],\n'
      );
    })
    .join('');
  return wrapClass(
    'MemoryCards',
    '想い出カード 99 種（144 枚）の定義（002 §6）。',
    '行の並びは MemoryCardDefinition のコンストラクタ引数（`id` の次から）と同じ。',
    rowsText,
    'MemoryCardDefinition',
    'new MemoryCardDefinition($id, ...$row)',
    '想い出カードの定義を返す（`M001`〜`M099`）。',
  );
}

function floorCards(spec) {
  const ind = '            ';
  const rowsText = spec.floors
    .map(
      (f) =>
        `        '${f.id}' => [\n` +
        `${ind}'${f.roomName}', '${f.nameId}', ${f.level}, ${str(f.actionTextJa, ind)},\n` +
        `${ind}${enumCase('FloorBonus', f.bonus)}, ${f.defaultPosition},\n` +
        '        ],\n',
    )
    .join('');
  return wrapClass(
    'FloorCards',
    '床カード 12 枚の定義（002 §7）。',
    '行の並びは FloorCardDefinition のコンストラクタ引数（`id` の次から）と同じ。',
    rowsText,
    'FloorCardDefinition',
    'new FloorCardDefinition($id, ...$row)',
    '床カードの定義を返す（`F01`〜`F12`）。',
  );
}

function analysisCards(spec) {
  const ind = '            ';
  const rowsText = spec.analysis
    .map((a) => {
      const cost = `[${Object.entries(a.cost).map(([k, n]) => `'${k}' => ${n}`).join(', ')}]`;
      return (
        `        '${a.id}' => [\n` +
        `${ind}${a.floor}, ${a.branch === null ? 'null' : `'${a.branch}'`}, ${cost},\n` +
        `${ind}${str(a.bonusTextJa, ind)}, ${a.hasLight ? 'true' : 'false'},\n` +
        '        ],\n'
      );
    })
    .join('');
  return wrapClass(
    'AnalysisCards',
    '精神カード 9 枚の定義（002 §8）。',
    '行の並びは AnalysisCardDefinition のコンストラクタ引数（`id` の次から）と同じ。',
    rowsText,
    'AnalysisCardDefinition',
    'new AnalysisCardDefinition($id, ...$row)',
    '精神カードの定義を返す（`R1A`〜`R5`）。',
  );
}

function egoCards(spec) {
  const ind = '            ';
  const rowsText = spec.ego
    .map(
      (e) =>
        `        '${e.id}' => [\n` +
        `${ind}${str(e.nameJa, ind)}, ${str(e.nameEn, ind)}, '${e.nameId}', ${enumCase('EgoKind', e.kind)},\n` +
        `${ind}${e.effectKind === null ? 'null' : enumCase('EffectKind', e.effectKind)},\n` +
        `${ind}${str(e.effectTextJa, ind)},\n` +
        '        ],\n',
    )
    .join('');
  return wrapClass(
    'EgoCards',
    '初期自我 8 枚・強化自我 5 枚の定義（002 §9）。初期自我の効果の種類は印刷にも card.csv にも無く、null（008 が割り当てる）。',
    '行の並びは EgoCardDefinition のコンストラクタ引数（`id` の次から）と同じ。',
    rowsText,
    'EgoCardDefinition',
    'new EgoCardDefinition($id, ...$row)',
    '自我カードの定義を返す（`I01`〜`I08`、`E01`〜`E05`）。',
  );
}

function objectiveCards(spec) {
  const ind = '            ';
  const rowsText = spec.objectives
    .map(
      (o) =>
        `        '${o.id}' => [\n` +
        `${ind}${str(o.nameJa, ind)}, '${o.nameId}',\n` +
        `${ind}${str(o.conditionTextJa, ind)},\n` +
        `${ind}${str(o.xDefinitionJa, ind)},\n` +
        `${ind}${list(o.scoreTable.map(([x, p]) => `[${x}, ${p}]`))},\n` +
        '        ],\n',
    )
    .join('');
  return wrapClass(
    'ObjectiveCards',
    '目標カード 7 枚の定義（002 §10）。',
    '行の並びは ObjectiveCardDefinition のコンストラクタ引数（`id` の次から）と同じ。',
    rowsText,
    'ObjectiveCardDefinition',
    'new ObjectiveCardDefinition($id, ...$row)',
    '目標カードの定義を返す（`O001`〜`O007`）。',
  );
}

/**
 * ログの `i18n` 引数で送るカード名・部屋名の翻訳用の一覧。BGA の翻訳の抽出は `clienttranslate('...')` のリテラルしか拾わないので、
 * 動的に送る名前の原文をここに列挙する（呼び出されることはない。003 §7.1）。
 */
function translatableNames(spec) {
  const names = [
    ...spec.memory.map((m) => m.nameEn),
    ...spec.floors.map((f) => f.roomName),
    ...spec.ego.map((e) => e.nameEn),
  ];
  const unique = [...new Set(names)];
  const lines = unique.map((n) => `            clienttranslate(${str(n, '            ')}),\n`).join('');
  return (
    `${HEADER}/**\n * ログの \`i18n\` 引数（カード名・部屋名）の翻訳用の一覧。\n */\n` +
    'final class TranslatableNames\n{\n' +
    '    /**\n     * 翻訳の抽出のためだけにある。名前の原文（英語）を返す。\n     *\n     * @return list<string>\n     */\n' +
    '    public static function all(): array\n    {\n        return [\n' +
    lines +
    '        ];\n    }\n}\n'
  );
}

/** 生成するすべてのファイルを `{ ファイル名: 内容 }` で返す。 */
export function renderAll(spec) {
  return {
    'KeyType.php': keyType(spec),
    'EffectKind.php': effectKind(spec),
    'RecallGroup.php': recallGroup(spec),
    'RecallEffect.php': recallEffect(spec),
    'CardBack.php': cardBack(),
    'MemoryCards.php': memoryCards(spec),
    'FloorCards.php': floorCards(spec),
    'AnalysisCards.php': analysisCards(spec),
    'EgoCards.php': egoCards(spec),
    'ObjectiveCards.php': objectiveCards(spec),
    'TranslatableNames.php': translatableNames(spec),
  };
}
