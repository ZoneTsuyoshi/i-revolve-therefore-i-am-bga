// 仕様書 002 の表から、カード定義の PHP（src/modules/php/Material/）を生成する。
// 使い方: node scripts/build-card-data.mjs [card.csv のパス] [--skip-csv]
//   card.csv の既定: ../danjiri-concerto.github.io/i-revolve-therefore-i-am/data/card.csv
// 生成の前に card.csv と仕様書の表が一致することを確かめ、食い違いがあれば何も書かずに失敗する（002 §13）。
// 生成物は仕様書の表のみから決まるので、同じ入力なら毎回バイト一致する。
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { compareCsv, findCsv, readCardCsv } from './lib/card-csv.mjs';
import { OUT_DIR, renderAll } from './lib/card-php.mjs';
import { readCardSpec } from './lib/card-spec.mjs';

const args = process.argv.slice(2);
const skipCsv = args.includes('--skip-csv');
const explicit = args.find((a) => !a.startsWith('--'));

const spec = readCardSpec();

if (!skipCsv) {
  const csvPath = findCsv(explicit);
  if (!existsSync(csvPath)) {
    console.error(`card.csv が見つかりません: ${csvPath}\n引数でパスを渡すか、card.csv 無しで生成するなら --skip-csv を付けてください。`);
    process.exit(1);
  }
  const diffs = compareCsv(spec, readCardCsv(csvPath));
  if (diffs.length > 0) {
    console.error(`card.csv と仕様書 002 が ${diffs.length} 件食い違っています（生成しません）:`);
    for (const d of diffs) console.error(`  ${d}`);
    process.exit(1);
  }
  console.log(`card.csv と仕様書 002 は一致しています（${csvPath}）`);
}

mkdirSync(OUT_DIR, { recursive: true });
const files = renderAll(spec);
for (const [name, text] of Object.entries(files)) writeFileSync(path.join(OUT_DIR, name), text);
console.log(`${Object.keys(files).length} ファイルを生成しました: ${OUT_DIR}`);
