// スプライトの配置規則（仕様書 009 §10.2〜§10.4）。build-sprites.mjs と check-sprites.mjs から使う純関数。
// 副作用なし。キーは 002 のカード ID に従う。

const pad = (n, w) => String(n).padStart(w, '0');
const range = (a, b) => Array.from({ length: b - a + 1 }, (_, i) => a + i);

// ---- キー（並び順がそのままセル番号） ----
export const MEMORY_KEYS = range(1, 99).map((n) => `M${pad(n, 3)}`);
export const MEMORY_BACK_KEYS = ['Rev1B', 'Rev1F', 'Rev1H', 'Rev2B', 'Rev2F', 'Rev2H'];
export const FLOOR_KEYS = range(1, 12).map((n) => `F${pad(n, 2)}`);
export const FLOOR_BACK_KEYS = ['Frev1', 'Frev2'];
export const SPIRIT_KEYS = ['R1A', 'R1B', 'R1C', 'R2', 'R3A', 'R3B', 'R3C', 'R4', 'R5'];
export const SPIRIT_BACK_KEYS = range(1, 5).map((n) => `Rrev${n}`);
export const EGO_KEYS = range(1, 8).map((n) => `I${pad(n, 2)}`);
export const ENHANCED_KEYS = range(1, 5).map((n) => `E${pad(n, 2)}`);
export const OBJECTIVE_KEYS = range(1, 7).map((n) => `O${pad(n, 3)}`);

const MEM_ORDER = [...MEMORY_KEYS, ...MEMORY_BACK_KEYS];
const LAND_ORDER = [
  ...FLOOR_KEYS, ...FLOOR_BACK_KEYS, ...SPIRIT_KEYS, ...SPIRIT_BACK_KEYS,
  ...EGO_KEYS, 'Irev', ...ENHANCED_KEYS, 'Erev', ...OBJECTIVE_KEYS, 'Orev',
];

// ---- シート定義 ----
// kind: 'mem' | 'land' | 'ui'。cols/rows/cellW/cellH は ui では持たない。
export const SHEETS = {
  ir_mem: { file: 'ir_mem.webp', kind: 'mem', cellW: 280, cellH: 390, cols: 12, rows: 9, keys: MEM_ORDER },
  ir_land: { file: 'ir_land.webp', kind: 'land', cellW: 400, cellH: 287, cols: 10, rows: 6, keys: LAND_ORDER },
  ir_mem_zoom_1: { file: 'ir_mem_zoom_1.webp', kind: 'mem', cellW: 450, cellH: 627, cols: 6, rows: 6, keys: MEMORY_KEYS.slice(0, 33) },
  ir_mem_zoom_2: { file: 'ir_mem_zoom_2.webp', kind: 'mem', cellW: 450, cellH: 627, cols: 6, rows: 6, keys: MEMORY_KEYS.slice(33, 66) },
  ir_mem_zoom_3: { file: 'ir_mem_zoom_3.webp', kind: 'mem', cellW: 450, cellH: 627, cols: 6, rows: 6, keys: MEMORY_KEYS.slice(66, 99) },
  ir_land_zoom_1: { file: 'ir_land_zoom_1.webp', kind: 'land', cellW: 560, cellH: 402, cols: 6, rows: 4, keys: [...FLOOR_KEYS, ...OBJECTIVE_KEYS] },
  ir_land_zoom_2: { file: 'ir_land_zoom_2.webp', kind: 'land', cellW: 560, cellH: 402, cols: 6, rows: 4, keys: [...SPIRIT_KEYS, ...EGO_KEYS, ...ENHANCED_KEYS] },
  ir_ui: { file: 'ir_ui.webp', kind: 'ui' },
};
export const SHEET_IDS = Object.keys(SHEETS);
export const UI_SHEET_WIDTH = 1024;
export const UI_GAP = 2;

// 期待する原本の寸法（±許容）
export const SOURCE_SIZE = {
  mem: { w: [722, 723], h: [1006, 1007] },
  land: { w: [1005, 1009], h: [721, 725] },
};

// ---- 原本ファイル（data/ からの相対パス） ----
export function cardSource(key) {
  let m;
  if ((m = /^M(\d{3})$/.exec(key))) return `cards/memory/${Number(m[1])}.png`;
  if (/^Rev[12][BFH]$/.test(key)) return `cards/memory/${key}.png`;
  if ((m = /^F(\d{2})$/.exec(key))) return `cards/floor/F${Number(m[1])}.png`;
  if (/^Frev[12]$/.test(key)) return `cards/floor/${key}.png`;
  if (SPIRIT_KEYS.includes(key)) return `cards/analysis/${key[0]}${key[1]}${key.slice(2).toLowerCase()}.png`;
  if ((m = /^Rrev([1-5])$/.exec(key))) return `cards/analysis/R${m[1]}rev.png`;
  if ((m = /^I(\d{2})$/.exec(key))) return `cards/ability/I${Number(m[1])}.png`;
  if (key === 'Irev') return 'cards/ability/Irev.png';
  if ((m = /^E(\d{2})$/.exec(key))) return `cards/enhancement/E${Number(m[1])}.png`;
  if (key === 'Erev') return 'cards/enhancement/Erev.png';
  if ((m = /^O(\d{3})$/.exec(key))) return `cards/objective/O${Number(m[1])}.png`;
  if (key === 'Orev') return 'cards/objective/Orev.png';
  throw new Error(`不明なカードキー: ${key}`);
}

// ---- UI スプライト（キー定義順に左から右へ詰める） ----
// size: 'token' = 96x96、'native' = 原寸、'h64' = 高さ 64 に縮小
export const UI_ITEMS = [
  { key: 'token_light', src: 'tokens/tokenHR.png', size: 'token' },
  { key: 'token_wildkey', src: 'tokens/tokenM.png', size: 'token' },
  { key: 'token_wildkey_used', src: 'tokens/tokenMrev.png', size: 'token' },
  { key: 'token_compose', src: 'tokens/tokenC.png', size: 'token' },
  { key: 'token_analysis', src: 'tokens/tokenR.png', size: 'token' },
  { key: 'meeple_red', src: 'piecies/meeple/meeple_r@4x.png', size: 'native' },
  { key: 'meeple_yellow', src: 'piecies/meeple/meeple_y@4x.png', size: 'native' },
  { key: 'meeple_green', src: 'piecies/meeple/meeple_g@4x.png', size: 'native' },
  { key: 'meeple_blue', src: 'piecies/meeple/meeple_b@4x.png', size: 'native' },
  { key: 'cube_red', src: 'piecies/cube/cube_red@4x.png', size: 'native' },
  { key: 'cube_yellow', src: 'piecies/cube/cube_yellow@4x.png', size: 'native' },
  { key: 'cube_green', src: 'piecies/cube/cube_green@4x.png', size: 'native' },
  { key: 'cube_blue', src: 'piecies/cube/cube_blue@4x.png', size: 'native' },
  { key: 'key_wisdom', src: 'icons/key_book@4x.png', size: 'h64' },
  { key: 'key_will', src: 'icons/key_fire@4x.png', size: 'h64' },
  { key: 'key_emotion', src: 'icons/key_heart@4x.png', size: 'h64' },
  { key: 'key_wild', src: 'icons/key_general@4x.png', size: 'h64' },
  { key: 'lock_wisdom', src: 'icons/lock_book@4x.png', size: 'h64' },
  { key: 'lock_will', src: 'icons/lock_fire@4x.png', size: 'h64' },
  { key: 'lock_emotion', src: 'icons/lock_heart@4x.png', size: 'h64' },
  { key: 'icon_draw', src: 'icons/draw@4x.png', size: 'h64' },
  { key: 'icon_open', src: 'icons/opendraw@4x.png', size: 'h64' },
  { key: 'icon_energy', src: 'icons/cost@4x.png', size: 'h64' },
  { key: 'icon_score', src: 'icons/score@4x.png', size: 'h64' },
];
export const UI_KEYS = UI_ITEMS.map((i) => i.key);

// 原寸 (w, h) から書き出し寸法を決める
export function uiOutputSize(item, w, h) {
  if (item.size === 'token') return { w: 96, h: 96 };
  if (item.size === 'h64') return { w: Math.round((w * 64) / h), h: 64 };
  return { w, h };
}

// シェルフ詰め: items = [{key, w, h}] を定義順に左から右、溢れたら次の段。
export function packShelves(items, width = UI_SHEET_WIDTH, gap = UI_GAP) {
  const rects = {};
  let x = 0;
  let y = 0;
  let shelfH = 0;
  for (const { key, w, h } of items) {
    if (w > width) throw new Error(`${key} が幅 ${width} を超えています`);
    if (x > 0 && x + w > width) {
      y += shelfH + gap;
      x = 0;
      shelfH = 0;
    }
    rects[key] = { x, y, w, h };
    x += w + gap;
    shelfH = Math.max(shelfH, h);
  }
  return { rects, width, height: y + shelfH };
}

// ---- 配置表 ----
// カードのセル位置
export function cellOf(sheetId, key) {
  const s = SHEETS[sheetId];
  const n = s.keys.indexOf(key);
  if (n < 0) throw new Error(`${sheetId} に ${key} がありません`);
  return { col: n % s.cols, row: Math.floor(n / s.cols) };
}

export function sheetSize(sheetId, uiPack) {
  const s = SHEETS[sheetId];
  if (s.kind === 'ui') return { w: uiPack.width, h: uiPack.height };
  return { w: s.cols * s.cellW, h: s.rows * s.cellH };
}

// uiSizes: { key: {w, h} }。sprites.js の全体オブジェクトを返す。
export function buildTable(uiSizes) {
  const uiPack = packShelves(UI_KEYS.map((key) => ({ key, ...uiSizes[key] })));
  const sheets = {};
  for (const id of SHEET_IDS) {
    const s = SHEETS[id];
    const size = sheetSize(id, uiPack);
    sheets[id] = s.kind === 'ui'
      ? { file: s.file, w: size.w, h: size.h }
      : { file: s.file, w: size.w, h: size.h, cols: s.cols, rows: s.rows, cellW: s.cellW, cellH: s.cellH };
  }
  const cards = {};
  const zoom = {};
  const bases = ['ir_mem', 'ir_land'];
  for (const id of SHEET_IDS) {
    const s = SHEETS[id];
    if (s.kind === 'ui') continue;
    const target = bases.includes(id) ? cards : zoom;
    for (const key of s.keys) target[key] = { sheet: id, ...cellOf(id, key) };
  }
  const ui = {};
  for (const key of UI_KEYS) ui[key] = { sheet: 'ir_ui', ...uiPack.rects[key] };
  return { sheets, cards, zoom, ui };
}

const HEADER = '// 自動生成。scripts/build-sprites.mjs が出力。手で編集しない（仕様書 009 §11.3）。\n';
export function renderTable(table) {
  const lines = [HEADER.trimEnd(), ''];
  for (const name of ['sheets', 'cards', 'zoom', 'ui']) {
    lines.push(`export const ${name.toUpperCase()} = {`);
    for (const [k, v] of Object.entries(table[name])) {
      lines.push(`  ${/^[A-Za-z_]\w*$/.test(k) ? k : JSON.stringify(k)}: ${JSON.stringify(v).replace(/"(\w+)":/g, '$1:').replace(/,/g, ', ').replace(/:/g, ': ').replace(/\{/g, '{ ').replace(/\}/g, ' }').replace(/"/g, "'")},`);
    }
    lines.push('};', '');
  }
  return lines.join('\n');
}
