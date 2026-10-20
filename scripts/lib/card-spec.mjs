// 仕様書 002（カードデータ仕様）の表を読み、カード定義のモデルとして返す。
// scripts/build-card-data.mjs と scripts/check-card-data.mjs が使う。
// 表の見出し・列の並びが変わったら、ここを直す。
import { readFileSync } from 'node:fs';

export const SPEC_PATH = new URL('../../docs/specs/002-card-data.md', import.meta.url);

const nfc = (s) => s.normalize('NFC');
const cells = (line) => line.split('|').slice(1, -1).map((s) => nfc(s.trim()));
const code = (s) => {
  const m = s.match(/`([^`]+)`/);
  if (!m) throw new Error(`バッククォートで囲まれた値がありません: ${s}`);
  return m[1];
};
const codes = (s) => [...s.matchAll(/`([^`]+)`/g)].map((m) => m[1]);
const stripTicks = (s) => s.replaceAll('`', '');

/** 見出し（行頭の一致）から次の同レベル以上の見出しまでの行を返す。 */
function section(lines, headingPrefix) {
  const start = lines.findIndex((l) => l.startsWith(headingPrefix));
  if (start < 0) throw new Error(`見出しが見つかりません: ${headingPrefix}`);
  const level = headingPrefix.match(/^#+/)[0].length;
  let end = lines.length;
  for (let i = start + 1; i < lines.length; i++) {
    const m = lines[i].match(/^(#+) /);
    if (m && m[1].length <= level) {
      end = i;
      break;
    }
  }
  return lines.slice(start + 1, end);
}

/** 節内の表の行のうち、第 1 列が first に一致するものを列の配列で返す。 */
function rows(sectionLines, first, width) {
  const out = [];
  for (const line of sectionLines) {
    if (!line.startsWith('|')) continue;
    const c = cells(line);
    if (!first.test(c[0])) continue;
    if (c.length !== width) {
      throw new Error(`列数が ${width} ではありません（${c.length}）: ${line.slice(0, 80)}`);
    }
    out.push(c);
  }
  return out;
}

/** 「M001〜M012、M040〜M042、M097」形式の ID 列を展開する。 */
export function expandMemoryIds(text) {
  const ids = [];
  for (const token of text.matchAll(/M(\d{3})(?:〜M(\d{3}))?/g)) {
    const from = Number(token[1]);
    const to = token[2] ? Number(token[2]) : from;
    for (let n = from; n <= to; n++) ids.push(`M${String(n).padStart(3, '0')}`);
  }
  return ids;
}

/** 「1 → 1、2〜3 → 2、6 以上 → 4」形式の得点表を [X の下限, 得点] の配列にする。 */
export function parseScoreTable(text) {
  return text.split('、').map((part) => {
    const m = part.match(/^\s*(\d+)(?:〜\d+| 以上)?\s*→\s*(\d+)\s*$/);
    if (!m) throw new Error(`得点表を読めません: ${part}`);
    return [Number(m[1]), Number(m[2])];
  });
}

const FLOOR_BONUS_BY_TOKEN = {
  tokenR: 'ANALYSIS_TOKEN',
  tokenHR: 'LIGHT',
  tokenC: 'COMPOSE_TOKEN',
  tokenM: 'WILD_MOVE_KEY',
};

/**
 * 仕様書を読んでモデルを返す。
 * @param {string | URL} file 仕様書 002 のパス
 */
export function readCardSpec(file = SPEC_PATH) {
  const lines = readFileSync(file, 'utf8').split('\n');

  // --- 列挙（3.2〜3.5） ---
  const keyTypes = rows(section(lines, '#### 3.2 '), /^[^-:\s]/, 5)
    .filter((c) => c[1].startsWith('`'))
    .map((c) => ({ id: code(c[1]), ja: c[0] }));
  const effectKinds = rows(section(lines, '#### 3.3 '), /^[^-:\s]/, 4)
    .filter((c) => c[1].startsWith('`'))
    .map((c) => ({ id: code(c[1]), ja: c[0] }));
  const recallRows = rows(section(lines, '#### 3.4 '), /^`/, 4);
  const recallEffects = recallRows.map((c) => ({
    id: code(c[0]),
    meaning: c[2],
    listed: expandMemoryIds(c[3]),
    listedCount: Number(c[3].match(/（(\d+) 種）\s*$/)?.[1] ?? NaN),
  }));
  const groupRows = rows(section(lines, '#### 3.5 '), /^`/, 4).filter((c) => c[0] !== '`otype`');
  const recallGroups = groupRows.map((c) => ({
    otype: code(c[0]),
    id: code(c[1]),
    ja: c[2],
    effects: codes(c[3]),
  }));

  // --- 想い出カード（6.1〜6.4） ---
  const memory = [];
  for (const h of ['#### 6.1 ', '#### 6.2 ', '#### 6.3 ']) {
    for (const c of rows(section(lines, h), /^M\d{3}$/, 12)) {
      memory.push({
        id: c[0],
        nameJa: c[1],
        nameEn: c[2],
        nameId: code(c[3]),
        count: Number(c[4]),
        level: Number(c[5]),
        energy: Number(c[6]),
        keys: codes(c[7]),
        back: code(c[8]),
        score: Number(c[9]),
        effectKind: code(c[10]),
        recall: code(c[11]),
        effectTextJa: '',
      });
    }
  }
  const effectTexts = new Map();
  for (const c of rows(section(lines, '#### 6.4 '), /^M\d{3}$/, 5)) {
    effectTexts.set(c[0], { nameJa: c[1], kind: code(c[2]), text: c[3] });
  }
  for (const m of memory) {
    const t = effectTexts.get(m.id);
    if (t) {
      if (t.nameJa !== m.nameJa) throw new Error(`${m.id}: 6.4 の名前が 6.1〜6.3 と違います`);
      if (t.kind !== m.effectKind) throw new Error(`${m.id}: 6.4 の効果の種類が 6.1〜6.3 と違います`);
      m.effectTextJa = t.text;
    }
  }
  const unknownTexts = [...effectTexts.keys()].filter((id) => !memory.some((m) => m.id === id));
  if (unknownTexts.length > 0) throw new Error(`6.4 に未知の ID があります: ${unknownTexts}`);

  // --- 床カード（7） ---
  const floors = rows(section(lines, '### 7. '), /^F\d{2}$/, 8).map((c) => {
    const tokenMatch = c[6].match(/`(token\w+)`/);
    const bonus = tokenMatch ? FLOOR_BONUS_BY_TOKEN[tokenMatch[1]] : 'NONE';
    if (!bonus) throw new Error(`${c[0]}: 周遊ボーナスのトークンを読めません: ${c[6]}`);
    return {
      id: c[0],
      roomName: c[2],
      nameId: code(c[3]),
      level: Number(c[4]),
      actionTextJa: stripTicks(c[5]),
      bonus,
      // 初回配置は ID 順（002 §7）。位置は回廊を時計回りに数えた 1〜12
      defaultPosition: Number(c[0].slice(1)),
      positionText: c[7],
    };
  });

  // --- 精神カード（8） ---
  const analysis = rows(section(lines, '### 8. '), /^R\d[A-C]?$/, 7).map((c) => ({
    id: c[0],
    floor: Number(c[2]),
    branch: c[3] === '—' ? null : c[3].toUpperCase(),
    cost: Object.fromEntries([...c[4].matchAll(/`(\w+)` × (\d+)/g)].map((m) => [m[1], Number(m[2])])),
    bonusTextJa: stripTicks(c[5]),
    hasLight: c[6] === 'あり',
  }));

  // --- 自我カード（9） ---
  const ego = rows(section(lines, '### 9. '), /^[IE]\d{2}$/, 7).map((c) => ({
    id: c[0],
    nameJa: c[2],
    nameEn: c[3],
    nameId: code(c[4]),
    kind: c[0].startsWith('I') ? 'INITIAL' : 'ENHANCEMENT',
    effectKind: c[5] === '—' ? null : code(c[5]),
    effectTextJa: c[6],
  }));

  // --- 目標カード（10） ---
  const objectives = rows(section(lines, '### 10. '), /^O\d{3}$/, 7).map((c) => ({
    id: c[0],
    nameJa: c[2],
    nameId: code(c[3]),
    conditionTextJa: stripTicks(c[4]),
    xDefinitionJa: c[5],
    scoreTable: parseScoreTable(c[6]),
  }));

  return { keyTypes, effectKinds, recallEffects, recallGroups, memory, floors, analysis, ego, objectives };
}
