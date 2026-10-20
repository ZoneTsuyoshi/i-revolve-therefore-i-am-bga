/**
 * 共通の領域の表示モデル（仕様書 009 §3.1〜§3.6、007 §8.2・§10）。getAllDatas と通知の args を、描画しやすい形に直す純粋関数。
 * DOM にも生成物 `ui/sprites.js` にも BGA にも依存しない（Node のテストから読み込める）。
 * 数値は文字列で届くことがあるので、必ず整数に通す（003 §6.1）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** 四隅（レベル 2）の巡り順の位置 */
const CORNER_POSITIONS = [1, 4, 7, 10];
/** 場札のスロット数 */
export const OPEN_SLOT_COUNT = 6;

/**
 * 整数にする（できなければ 0）。
 * @param {unknown} value
 * @returns {number}
 */
export function toInt(value) {
  const n = typeof value === 'number' ? value : parseInt(String(value), 10);
  return Number.isFinite(n) ? Math.trunc(n) : 0;
}

/**
 * @param {number} n
 * @param {number} width
 * @returns {string}
 */
function pad(n, width) {
  return String(n).padStart(width, '0');
}

/** 想い出カードの種類（card_type）→ スプライトのキー `M014` */
export const memKey = (/** @type {unknown} */ type) => `M${pad(toInt(type), 3)}`;
/** 床カードの ID（1〜12）→ `F01` */
export const floorKey = (/** @type {unknown} */ id) => `F${pad(toInt(id), 2)}`;
/** 目標カードの ID（1〜7）→ `O001` */
export const objectiveKey = (/** @type {unknown} */ id) => `O${pad(toInt(id), 3)}`;

/**
 * 山札の一番上の裏面のキー（`1B`）→ スプライトのキー（`Rev1B`）。空・null は null。
 * @param {unknown} top
 * @returns {string | null}
 */
export function backSpriteKey(top) {
  return typeof top === 'string' && top !== '' ? `Rev${top}` : null;
}

/**
 * 位置（巡り順 1〜12）のレベル。四隅が 2。
 * @param {number} pos
 * @returns {1 | 2}
 */
export function levelOfPosition(pos) {
  return CORNER_POSITIONS.includes(pos) ? 2 : 1;
}

/**
 * @typedef {object} CatalogMemory
 * @property {number} type
 * @property {number} level
 */

/**
 * カタログから、想い出カードの種類 → レベルの表を作る。
 * @param {{ memory?: CatalogMemory[] } | null | undefined} catalog
 * @returns {Map<number, number>}
 */
export function levelTable(catalog) {
  const table = new Map();
  for (const def of catalog?.memory ?? []) {
    table.set(toInt(def.type), toInt(def.level));
  }
  return table;
}

// ---- プレイヤーの色 ----

/** 駒画像の 4 色（gameinfos の player_colors と同じ値。009 §5） */
const COLOR_SWATCH = /** @type {const} */ ({
  red: [0xb3, 0x20, 0x2a],
  yellow: [0xd6, 0xb6, 0x1e],
  green: [0x5f, 0x9c, 0x3a],
  blue: [0x2a, 0x4f, 0xa0],
});

/** @typedef {'red' | 'yellow' | 'green' | 'blue'} PlayerColor */

/**
 * プレイヤー色（`b3202a` など）→ 駒画像の色名。完全一致が無ければ RGB が最も近い色。不正な値は赤。
 * @param {unknown} hex
 * @returns {PlayerColor}
 */
export function colorName(hex) {
  const text = typeof hex === 'string' ? hex.replace(/^#/, '') : '';
  if (!/^[0-9a-fA-F]{6}$/.test(text)) {
    return 'red';
  }
  const rgb = [0, 2, 4].map((i) => parseInt(text.slice(i, i + 2), 16));
  let best = /** @type {PlayerColor} */ ('red');
  let bestDistance = Infinity;
  for (const [name, swatch] of Object.entries(COLOR_SWATCH)) {
    const distance = swatch.reduce((sum, v, i) => sum + (v - rgb[i]) ** 2, 0);
    if (distance < bestDistance) {
      bestDistance = distance;
      best = /** @type {PlayerColor} */ (name);
    }
  }
  return best;
}

/**
 * ミープルの UI スプライトのキー
 * @param {PlayerColor} color
 */
export const meepleSprite = (color) => `meeple_${color}`;
/**
 * キューブの UI スプライトのキー
 * @param {PlayerColor} color
 */
export const cubeSprite = (color) => `cube_${color}`;

/**
 * 同じ部屋に置くミープルのスロット 0〜3（左上・右上・左下・右下。009 §3.1）。席順の何番目かで決めるので、動いても場所が変わらない。
 * @param {number[]} seatOrder 席順のプレイヤー ID
 * @param {number} playerId
 * @returns {number}
 */
export function meepleSlot(seatOrder, playerId) {
  const index = seatOrder.indexOf(playerId);
  return index < 0 ? 0 : Math.min(index, 3);
}

// ---- カードの参照 ----

/**
 * @typedef {object} CardView bga-cards に渡すカードのデータ
 * @property {number | string} id
 * @property {string} key スプライトのキー
 */

/**
 * 通知の `cardRef` の列を、種類つきのカード（表向き）にする。種類の無いもの（非公開）は除く。
 * @param {unknown} refs
 * @returns {{ id: number, type: number }[]}
 */
export function faceUpRefs(refs) {
  const out = [];
  for (const ref of Array.isArray(refs) ? refs : []) {
    if (ref !== null && typeof ref === 'object' && 'id' in ref && 'type' in ref) {
      out.push({ id: toInt(ref.id), type: toInt(ref.type) });
    }
  }
  return out;
}

/**
 * 想い出カード（表向き）のカードデータ。
 * @param {{ id: unknown, type: unknown }} card
 * @returns {CardView}
 */
export function faceUpMemory(card) {
  return { id: toInt(card.id), key: memKey(card.type) };
}

/**
 * 想い出カード（裏向き）のカードデータ。キーは `Rev1B`。
 * @param {unknown} id
 * @param {unknown} back 裏面のキー（`1B`）
 * @returns {CardView}
 */
export function faceDownMemory(id, back) {
  return { id: toInt(id), key: backSpriteKey(back) ?? '' };
}

// ---- 回廊 ----

/**
 * @typedef {object} RoomModel
 * @property {number} pos
 * @property {number} level
 * @property {CardView} floor 床カード（横長）
 * @property {CardView | null} card 部屋の想い出カード（裏向きなら裏面、想起中は表）。無ければ null
 */

/**
 * getAllDatas の `rooms` → 位置の昇順の表示モデル。壊れた行は飛ばす。
 * @param {unknown} rooms
 * @returns {RoomModel[]}
 */
export function roomsModel(rooms) {
  const out = [];
  for (const row of Array.isArray(rooms) ? rooms : []) {
    const pos = toInt(row?.pos);
    if (pos < 1 || pos > 12) {
      continue;
    }
    const raw = row.card;
    let card = null;
    if (raw !== null && raw !== undefined && typeof raw === 'object') {
      card = raw.faceUp === true && raw.type !== undefined ? faceUpMemory(raw) : faceDownMemory(raw.id, raw.back);
    }
    out.push({ pos, level: levelOfPosition(pos), floor: { id: floorKey(row.floor), key: floorKey(row.floor) }, card });
  }
  return out.sort((a, b) => a.pos - b.pos);
}

/**
 * 人格の位置。`playerData[id].persona`（0 = 未配置）。
 * @param {Record<string, any> | undefined} playerData
 * @returns {Map<number, number>} プレイヤー ID → 位置 1〜12（置かれていない人は含めない）
 */
export function personaPositions(playerData) {
  const out = new Map();
  for (const [id, data] of Object.entries(playerData ?? {})) {
    const pos = toInt(data?.persona);
    if (pos >= 1 && pos <= 12) {
      out.set(toInt(id), pos);
    }
  }
  return out;
}

// ---- 場札・山札・捨札 ----

/**
 * getAllDatas の `open` → スロット番号つきの表向きカード。範囲外・重複したスロットは除く。
 * @param {unknown} open
 * @returns {{ slot: number, card: CardView }[]}
 */
export function openModel(open) {
  const seen = new Set();
  const out = [];
  for (const row of Array.isArray(open) ? open : []) {
    const slot = toInt(row?.slot);
    if (slot < 0 || slot >= OPEN_SLOT_COUNT || seen.has(slot)) {
      continue;
    }
    seen.add(slot);
    out.push({ slot, card: faceUpMemory(row) });
  }
  return out.sort((a, b) => a.slot - b.slot);
}

/**
 * @typedef {object} DeckModel
 * @property {number} count
 * @property {CardView | null} top 一番上の裏面（山札）。空なら null
 */

/**
 * 山札の同期（`decks`。getAllDatas と、通知 N12・N29・N32 の args 共通）。山が欠けていれば 0 枚。
 * @param {unknown} decks `{ "1": {count, top}, "2": {...} }`
 * @returns {Record<1 | 2, DeckModel>}
 */
export function decksModel(decks) {
  /** @param {unknown} level */
  const one = (level) => {
    const raw = /** @type {any} */ (decks)?.[/** @type {any} */ (level)];
    const count = Math.max(0, toInt(raw?.count));
    const key = backSpriteKey(raw?.top);
    return { count, top: count > 0 && key !== null ? { id: `deck-${level}-top`, key } : null };
  };
  return { 1: one(1), 2: one(2) };
}

/**
 * 捨札（`discards[level]` の一覧、捨てた順）→ 枚数と一番上（最後）のカード。
 * @param {unknown} list
 * @returns {{ count: number, top: CardView | null }}
 */
export function discardModel(list) {
  const cards = faceUpRefs(list);
  const last = cards[cards.length - 1];
  return { count: cards.length, top: last === undefined ? null : faceUpMemory(last) };
}

// ---- 精神トラック ----

/**
 * @typedef {object} SpiritSlotModel
 * @property {string} id 精神カードの ID（`R1A` など。キーと同じ）
 * @property {number} tier 1〜5
 * @property {number} slot 同じ階層の並び
 * @property {boolean} light 心光トークンが載っているか
 * @property {number[]} cubes ここにいるプレイヤーの ID（席順）
 */

/**
 * @typedef {object} SpiritModel
 * @property {SpiritSlotModel[]} cards (tier, slot) の昇順
 * @property {number[]} start 階層 0（開始位置）にいるプレイヤーの ID（席順）
 */

/**
 * 精神トラック。キューブの位置は `playerData[id].spirit`（null = 開始位置）。
 * @param {unknown} spirit getAllDatas の `spirit`
 * @param {Record<string, any> | undefined} playerData
 * @param {number[]} seatOrder 席順のプレイヤー ID（キューブの並び順）
 * @returns {SpiritModel}
 */
export function spiritModel(spirit, playerData, seatOrder) {
  const cards = [];
  for (const row of Array.isArray(spirit) ? spirit : []) {
    if (typeof row?.id !== 'string') {
      continue;
    }
    cards.push({ id: row.id, tier: toInt(row.tier), slot: toInt(row.slot), light: row.light === true, cubes: /** @type {number[]} */ ([]) });
  }
  cards.sort((a, b) => a.tier - b.tier || a.slot - b.slot);
  /** @type {number[]} */
  const start = [];
  const ids = seatOrder.length > 0 ? seatOrder : Object.keys(playerData ?? {}).map(toInt);
  for (const playerId of ids) {
    const data = playerData?.[playerId] ?? playerData?.[String(playerId)];
    if (data === undefined) {
      continue;
    }
    const at = typeof data.spirit === 'string' ? cards.find((c) => c.id === data.spirit) : undefined;
    (at === undefined ? start : at.cubes).push(playerId);
  }
  return { cards, start };
}

// ---- 目標カード・強化自我 ----

/**
 * @typedef {object} ObjectivesModel
 * @property {number} slotCount 共通の場のスロット数（獲得されても枠は残す）
 * @property {{ slot: number, card: CardView }[]} common 場に並んでいるもの
 * @property {{ owner: number, card: CardView }[]} owned 獲得済み（獲得者の領域へ）
 */

/**
 * getAllDatas の `objectives`（`{id, slot, owner}`）→ 場と獲得者に振り分ける。
 * @param {unknown} objectives
 * @returns {ObjectivesModel}
 */
export function objectivesModel(objectives) {
  const common = [];
  const owned = [];
  let slotCount = 0;
  let index = 0;
  for (const row of Array.isArray(objectives) ? objectives : []) {
    const key = objectiveKey(row?.id);
    const card = { id: key, key };
    const slot = row?.slot === null || row?.slot === undefined ? index : toInt(row.slot);
    slotCount = Math.max(slotCount, slot + 1);
    index++;
    if (row?.owner !== null && row?.owner !== undefined) {
      owned.push({ owner: toInt(row.owner), card });
    } else {
      common.push({ slot, card });
    }
  }
  return { slotCount, common, owned };
}

/**
 * 自我カード（`I05`・`E02`）のカードデータ。
 * @param {unknown} egoId
 * @returns {CardView | null}
 */
export function egoCard(egoId) {
  return typeof egoId === 'string' && /^[IE]\d{2}$/.test(egoId) ? { id: egoId, key: egoId } : null;
}

/**
 * 目標カードの現在の X と得点のバッジの文言の部品（009 §3.6）。N23 の `statuses` を ID → 値にする。
 * @param {unknown} statuses
 * @returns {Map<string, { x: number, points: number }>} 目標カードのキー（`O001`）→ 値
 */
export function objectiveStatusMap(statuses) {
  const out = new Map();
  for (const row of Array.isArray(statuses) ? statuses : []) {
    out.set(objectiveKey(row?.obj_id ?? row?.id), { x: toInt(row?.x), points: toInt(row?.points) });
  }
  return out;
}

// ---- 通知の args の正規化 ----

/**
 * N29 `cleanup` の補充分。
 * @param {Record<string, any>} args
 * @returns {{ rooms: { pos: number, card: CardView }[], open: { slot: number, card: CardView }[] }}
 */
export function cleanupRefills(args) {
  const rooms = [];
  for (const row of Array.isArray(args?.rooms) ? args.rooms : []) {
    rooms.push({ pos: toInt(row?.pos), card: faceDownMemory(row?.card_id, row?.back) });
  }
  return { rooms, open: openModel(args?.open) };
}

/**
 * N28 `roomsSwapped` の入れ替え後の状態。
 * @param {Record<string, any>} args
 * @returns {{ kind: 'CARD' | 'FLOOR', after: { pos: number, card: CardView }[] }}
 */
export function roomsSwappedAfter(args) {
  const kind = args?.kind === 'FLOOR' ? 'FLOOR' : 'CARD';
  const after = [];
  for (const row of Array.isArray(args?.after) ? args.after : []) {
    const pos = toInt(row?.pos);
    after.push({ pos, card: kind === 'FLOOR' ? { id: floorKey(row?.floor_id), key: floorKey(row?.floor_id) } : faceDownMemory(row?.card_id, row?.back) });
  }
  return { kind, after };
}
