/**
 * 盤面の枠（仕様書 009 §2.2・§2.4・§2.5・§15）を、空の器として作る。中身の描画は U-3〜U-6。
 * 器の id とクラスは 009 §15 に従う。後続のタスクは `refs` から器を取り、そこに Stock を作る。
 *
 * #ir-root
 *   #ir-main
 *     #ir-slot-piles-top        … wide・medium では ir-piles がここ
 *     #ir-cloister              … 4 × 4 グリッドの環（12 部屋 ir-room[data-pos]）
 *       #ir-cloister-center     … wide・medium は ir-open、narrow は ir-piles
 *     #ir-slot-open-below       … narrow では ir-open がここ
 *   #ir-common                  … ir-spirit-track、ir-enh-pile、ir-goals
 *   #ir-recall-panel            … 想起中だけ表示（U-7）
 *   #ir-players                 … ir-player-area[data-player]（自分が先頭）
 *
 * ir-piles と ir-open は DOM ノードを付け替えるだけで作り直さない（Stock が壊れない）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { el, int } from '../ui/dom.js';
import { RING_CELLS } from '../ui/layout-math.js';
import { orderPlayers } from './player-order.js';

/** 四隅（レベル 2）の巡り順の位置 */
const CORNER_POSITIONS = [1, 4, 7, 10];
/** 精神トラックの階層ごとのスロット数（第一・第三階層は 2 枚並列。009 §2.2） */
const SPIRIT_SLOTS = { 5: 1, 4: 1, 3: 2, 2: 1, 1: 2 };
const OPEN_SLOTS = 6;

/**
 * @typedef {object} BoardRefs
 * @property {HTMLElement} root
 * @property {HTMLElement} cloister
 * @property {HTMLElement} center ir-cloister-center
 * @property {HTMLElement} slotPilesTop
 * @property {HTMLElement} slotOpenBelow
 * @property {Map<number, HTMLElement>} rooms 位置 1〜12 → ir-room
 * @property {Map<number, HTMLElement>} roomFloors 位置 → 床カードの器
 * @property {Map<number, HTMLElement>} roomCards 位置 → 裏向き想い出カードの器
 * @property {HTMLElement} piles
 * @property {Record<string, HTMLElement>} pileSlots `deck-1`・`discard-1`・`deck-2`・`discard-2`
 * @property {HTMLElement} open
 * @property {HTMLElement[]} openSlots
 * @property {HTMLElement} spiritTrack
 * @property {HTMLElement} enhPile
 * @property {HTMLElement} goals
 * @property {HTMLElement} recallPanel
 * @property {HTMLElement} players
 * @property {Map<number, PlayerAreaRefs>} playerAreas プレイヤー ID → 領域
 * @property {number[]} playerOrder 表示順
 */

/**
 * @typedef {object} PlayerAreaRefs
 * @property {HTMLElement} area
 * @property {HTMLElement} ego 初期自我
 * @property {HTMLElement} enhEgo 強化自我
 * @property {HTMLElement} tokens
 * @property {HTMLElement} ownedGoals 獲得した目標カード
 * @property {HTMLElement | null} hand 自分の領域だけ（他は枚数チップ）
 * @property {HTMLElement | null} handCount 他プレイヤー・観戦時の手札の枚数
 * @property {HTMLElement} composed 自己構成済みエリア
 */

/**
 * @param {string} extra
 * @param {Record<string, string | number>} [data]
 * @param {string} [id]
 * @returns {HTMLElement}
 */
function slot(extra, data = {}, id) {
    return el('div', { id, class: `ir-slot ${extra}`, data });
}

/**
 * 盤面の枠を作って `host`（ゲーム領域）に入れる。
 * @param {HTMLElement} host `bga.gameArea.getElement()`
 * @param {object} p
 * @param {Record<string, { id?: string | number, name?: string, color?: string }>} [p.players] `gamedatas.players`
 * @param {unknown} [p.playerOrder] `gamedatas.playerorder`
 * @param {number | null} [p.me] 自分の ID。観戦者は null
 * @returns {BoardRefs}
 */
export function buildBoard(host, { players = {}, playerOrder = [], me = null } = {}) {
    const rooms = new Map();
    const roomFloors = new Map();
    const roomCards = new Map();
    RING_CELLS.forEach(([col, row], index) => {
        const pos = index + 1;
        const floor = slot('ir-slot-l ir-room-floor');
        const card = slot('ir-slot-room-card ir-room-card');
        const room = el('div', {
            id: `ir-room-${pos}`,
            class: 'ir-room',
            data: { pos, level: CORNER_POSITIONS.includes(pos) ? 2 : 1 },
        }, [floor, card, el('div', { class: 'ir-room-meeples' })]);
        room.style.gridColumn = String(col);
        room.style.gridRow = String(row);
        rooms.set(pos, room);
        roomFloors.set(pos, floor);
        roomCards.set(pos, card);
    });

    const openSlots = Array.from({ length: OPEN_SLOTS }, (_unused, i) => slot('ir-slot-m ir-open-slot', { slot: i }));
    const open = el('div', { id: 'ir-open', class: 'ir-open', attrs: { 'aria-label': _('Open memory cards') } }, openSlots);

    /** @type {Record<string, HTMLElement>} */
    const pileSlots = {};
    for (const level of [1, 2]) {
        for (const kind of ['deck', 'discard']) {
            pileSlots[`${kind}-${level}`] = slot('ir-slot-m ir-pile-slot', { kind, level }, `ir-${kind}-${level}`);
        }
    }
    const piles = el('div', { id: 'ir-piles', class: 'ir-piles' }, Object.values(pileSlots));

    const center = el('div', { id: 'ir-cloister-center', class: 'ir-cloister-center' });
    const cloister = el('div', { id: 'ir-cloister', class: 'ir-cloister' }, [...rooms.values(), center]);

    const spiritTrack = el('div', { id: 'ir-spirit-track', class: 'ir-spirit-track', attrs: { 'aria-label': _('Spirit track') } },
        Object.entries(SPIRIT_SLOTS).sort((a, b) => Number(b[0]) - Number(a[0])).map(([tier, count]) =>
            el('div', { class: 'ir-spirit-tier', data: { tier } },
                Array.from({ length: count }, (_unused, i) => slot('ir-slot-l ir-spirit-slot', { tier, slot: i })))));
    const enhPile = slot('ir-slot-l ir-enh-pile', {}, 'ir-enh-pile');
    const goals = el('div', { id: 'ir-goals', class: 'ir-goals ir-stock-l', attrs: { 'aria-label': _('Objective cards') } });
    const common = el('div', { id: 'ir-common', class: 'ir-common' }, [
        el('div', { class: 'ir-common-spirit' }, [spiritTrack, enhPile]),
        goals,
    ]);

    const slotPilesTop = el('div', { id: 'ir-slot-piles-top', class: 'ir-slot-host' });
    const slotOpenBelow = el('div', { id: 'ir-slot-open-below', class: 'ir-slot-host' });
    const main = el('div', { id: 'ir-main', class: 'ir-main' }, [slotPilesTop, cloister, slotOpenBelow]);

    const recallPanel = el('div', { id: 'ir-recall-panel', class: 'ir-recall-panel', attrs: { hidden: '' } });

    const order = orderPlayers(playerOrder, players, me);
    const playersEl = el('div', { id: 'ir-players', class: 'ir-players' });
    const playerAreas = new Map();
    for (const playerId of order) {
        const refs = buildPlayerArea(playerId, players[playerId] ?? {}, playerId === me);
        playerAreas.set(playerId, refs);
        playersEl.append(refs.area);
    }

    const root = el('div', { id: 'ir-root', class: 'ir-root', data: { layout: 'wide' } }, [
        el('div', { class: 'ir-board' }, [main, common]),
        recallPanel,
        playersEl,
    ]);
    host.append(root);

    return {
        root, cloister, center, slotPilesTop, slotOpenBelow, rooms, roomFloors, roomCards, piles, pileSlots, open, openSlots,
        spiritTrack, enhPile, goals, recallPanel, players: playersEl, playerAreas, playerOrder: order,
    };
}

/**
 * @param {number} playerId
 * @param {{ name?: string, color?: string }} player
 * @param {boolean} mine
 * @returns {PlayerAreaRefs}
 */
function buildPlayerArea(playerId, player, mine) {
    const ego = slot('ir-slot-l ir-ego');
    const enhEgo = slot('ir-slot-l ir-enh-ego');
    const tokens = el('div', { class: 'ir-tokens' });
    const ownedGoals = el('div', { class: 'ir-owned-goals ir-stock-l' });
    const hand = mine ? el('div', { class: 'ir-hand ir-stock-m', attrs: { 'aria-label': _('Your hand') } }) : null;
    const handCount = mine ? null : el('div', { class: 'ir-hand-count' });
    const composed = el('div', { class: 'ir-composed ir-stock-m', attrs: { 'aria-label': _('Composed cards') } });
    const name = el('span', { class: 'ir-player-name' }, [player.name ?? '']);
    if (typeof player.color === 'string' && /^[0-9a-fA-F]{6}$/.test(player.color)) {
        name.style.setProperty('--ir-player-color', `#${player.color}`);
    }
    const area = el('section', {
        id: `ir-player-area-${playerId}`,
        class: `ir-player-area${mine ? ' ir-mine' : ''}`,
        data: { player: int(playerId) },
    }, [
        el('header', { class: 'ir-player-header' }, [name]),
        el('div', { class: 'ir-player-top' }, [ego, enhEgo, tokens, ownedGoals]),
        ...(hand === null ? [] : [hand]),
        ...(handCount === null ? [] : [handCount]),
        composed,
    ]);
    return { area, ego, enhEgo, tokens, ownedGoals, hand, handCount, composed };
}

/**
 * レイアウトに応じて、ir-piles と ir-open の置き場所を付け替える（009 §2.4）。
 * wide・medium: piles は環の上、open は環の中央。narrow: piles が環の中央、open は環の下。
 * @param {BoardRefs} refs
 * @param {'wide' | 'medium' | 'narrow'} layout
 */
export function placeFloating(refs, layout) {
    if (layout === 'narrow') {
        refs.center.append(refs.piles);
        refs.slotOpenBelow.append(refs.open);
    } else {
        refs.slotPilesTop.append(refs.piles);
        refs.center.append(refs.open);
    }
}
