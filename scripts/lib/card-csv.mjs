// サイト側の card.csv（原本）を読み、仕様書 002 のモデルと突き合わせる。
// card.csv の列の意味は 002 §4。引用符・改行を含む値は無い前提で、見つけたら失敗する。
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

/** 既定の card.csv のパス（リポジトリと同じ階層にサイト側のリポジトリがある前提）。 */
export const DEFAULT_CSV_PATH = path.resolve(
  repoRoot,
  '../danjiri-concerto.github.io/i-revolve-therefore-i-am/data/card.csv',
);

/** ワークツリー（`.worktrees/<slug>`）から実行したときのために、親の階層も探す。 */
export function findCsv(explicit) {
  if (explicit) return explicit;
  const candidates = [
    DEFAULT_CSV_PATH,
    path.resolve(repoRoot, '../../../danjiri-concerto.github.io/i-revolve-therefore-i-am/data/card.csv'),
  ];
  return candidates.find((p) => existsSync(p)) ?? DEFAULT_CSV_PATH;
}

const HEADER = [
  'card_id', 'lv2_id', 'card_name', 'id_name', 'n_card', 'original_id', 'level', 'engine',
  'e1', 'e2', 'cost', 'r', 'otype', 'score', 'condition', 'effect',
];

const KEY = { 知恵: 'WISDOM', 意志: 'WILL', 感情: 'EMOTION', 任意: 'WILD' };
const LOCK = { 知恵: 'B', 意志: 'F', 感情: 'H' };
const KIND = { ターン開始時: 'TURN_START', 起動: 'ACTIVATED', 常時: 'CONSTANT', 即時: 'INSTANT', 保有時: 'HELD', '': 'NONE' };

/** card.csv を行オブジェクトの配列にする。 */
export function readCardCsv(file) {
  const text = readFileSync(file, 'utf8').replace(/^\uFEFF/, '').replaceAll('\r\n', '\n');
  if (text.includes('"') || text.includes('\r')) {
    throw new Error('card.csv に引用符または単独の CR が含まれています。読み取り処理を見直してください');
  }
  const lines = text.split('\n').filter((l) => l !== '');
  const header = lines[0].split(',');
  if (header.join(',') !== HEADER.join(',')) throw new Error(`card.csv のヘッダが想定と違います: ${header}`);
  return lines.slice(1).map((line, i) => {
    const f = line.split(',').map((s) => s.normalize('NFC'));
    if (f.length !== HEADER.length) throw new Error(`card.csv ${i + 2} 行目の列数が ${f.length} です`);
    return Object.fromEntries(HEADER.map((h, j) => [h, f[j]]));
  });
}

/**
 * card.csv と仕様書のモデルを突き合わせ、食い違いを文字列の配列で返す（空なら一致）。
 * 比較するのは両者が同じ形で持つ項目のみ。効果文は、アイコンの表記が違う（002 §16）ため比較しない。
 * 想起効果・床・精神カードは card.csv に無い。
 * @param {ReturnType<typeof import('./card-spec.mjs').readCardSpec>} spec
 * @param {ReturnType<typeof readCardCsv>} csv
 */
export function compareCsv(spec, csv) {
  const diffs = [];
  const eq = (label, actual, expected) => {
    if (actual !== expected) diffs.push(`${label}: card.csv=${JSON.stringify(actual)} 仕様書=${JSON.stringify(expected)}`);
  };
  const groupOf = new Map();
  for (const g of spec.recallGroups) for (const e of g.effects) groupOf.set(e, g.otype);

  const byId = new Map(csv.map((r) => [r.card_id, r]));
  const used = new Set();
  const take = (id) => {
    const r = byId.get(id);
    if (!r) diffs.push(`${id}: card.csv に行がありません`);
    else used.add(id);
    return r;
  };

  for (const m of spec.memory) {
    const n = String(Number(m.id.slice(1)));
    const r = take(n);
    if (!r) continue;
    const l = `${m.id}`;
    eq(`${l} 名前`, r.card_name, m.nameJa);
    eq(`${l} id_name`, r.id_name, `No.${n} ${m.nameJa}`);
    eq(`${l} 枚数`, Number(r.n_card), m.count);
    eq(`${l} レベル`, Number(r.level), m.level);
    eq(`${l} エネルギー`, Number(r.cost), m.energy);
    eq(`${l} 鍵`, [r.e1, r.e2].filter((k) => k !== '').map((k) => KEY[k]).join(','), m.keys.join(','));
    eq(`${l} 裏面`, `Rev${r.level}${LOCK[r.r]}`, m.back);
    eq(`${l} スコア`, Number(r.score), m.score);
    eq(`${l} 効果の種類`, KIND[r.condition], m.effectKind);
    eq(`${l} 効果文の有無`, r.effect !== '', m.effectTextJa !== '');
    eq(`${l} 想起グループ`, r.otype, groupOf.get(m.recall));
    if (m.level === 2) eq(`${l} lv2_id`, Number(r.lv2_id), Number(m.id.slice(1)) - 84);
  }
  for (const e of spec.ego) {
    const r = take(`${e.id[0]}${Number(e.id.slice(1))}`);
    if (!r) continue;
    eq(`${e.id} 名前`, r.id_name, e.nameJa);
    eq(`${e.id} 効果の種類`, KIND[r.condition] ?? r.condition, e.effectKind ?? 'NONE');
  }
  for (const o of spec.objectives) {
    const r = take(`O${Number(o.id.slice(1))}`);
    if (!r) continue;
    // 仕様書の表は「Lv2 自己構成の目標」と英数字と日本語の間に空白を入れている（card.csv は空白なし）ため、空白を除いて比べる
    eq(`${o.id} 名前`, r.id_name.replaceAll(' ', ''), o.nameJa.replaceAll(' ', ''));
  }
  for (const id of byId.keys()) if (!used.has(id)) diffs.push(`${id}: 仕様書に対応するカードがありません`);
  return diffs;
}
