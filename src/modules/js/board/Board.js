/**
 * 盤面の枠（仕様書 009 §2.2・§2.4・§2.5・§15）を、空の器として作る。中身の描画は U-3〜U-6。
 * 器の id とクラスは 009 §15 に従う。後続のタスクは `refs` から器を取り、そこに Stock を作る。
 *
 * #ir-root（xl・wide・medium は CSS の grid、narrow は flex の order で並べる。narrow は自分の領域を共通の領域より先に出す。010 §3）
 *   #ir-main
 *     .ir-pick-panel            … 候補の置き場（初期自我のドラフト・強化自我の選択の間だけ。states/ActionState.js が先頭に足す。011 §2.1・§3.3）
 *     #ir-slot-piles-top        … xl・wide・medium では ir-piles がここ
 *     #ir-cloister              … 4 × 4 グリッドの環（12 部屋 ir-room[data-pos]）
 *       #ir-cloister-center     … xl・wide・medium は ir-open、narrow は ir-piles
 *     #ir-slot-open-below       … narrow では ir-open がここ
 *   #ir-common                  … ir-spirit-track、ir-enh-pile、ir-goals
 *   #ir-players                 … ir-player-area[data-player]（自分が先頭）
 *
 * ir-piles と ir-open は DOM ノードを付け替えるだけで作り直さない（Stock が壊れない）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { el, int, tipAttrs } from '../ui/dom.js';
import { createSymbol } from '../ui/icons.js';
import { RING_CELLS } from '../ui/layout-math.js';
import { colorName } from './view-model.js';
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
 * @property {HTMLElement | null} handActions 自分の領域だけ。手札のレールの右端（ターンプレイヤー用の小さなボタンを置く。010 §2.4、011 §8）
 * @property {HTMLElement | null} handWarn 自分の領域だけ。手札が上限を超えたときだけ出す警告（SVG + N / 8。レールの「Hand」の横）
 * @property {HTMLElement | null} handInfo 自分の領域だけ。警告の文字（N / 8）
 * @property {HTMLElement | null} under 自分の領域だけ。I08 の下のカード（中身）
 * @property {HTMLElement} composed 自己構成済みエリア
 * @property {HTMLElement} meeple 名前の行のミープル（プレイヤー色。PlayerAreas がスプライトを描く）
 * @property {HTMLElement} score 名前の行の「N / 20」
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
        // Stock の要素（floor・card）は bga-cards の CSS が position を上書きすることがある。
        // 配置（abs）は Stock の外の箱に持たせ、Stock は箱の中の通常の流れに置く（009 §3.1）。
        const room = el('div', {
            id: `ir-room-${pos}`,
            class: 'ir-room',
            data: { pos, level: CORNER_POSITIONS.includes(pos) ? 2 : 1 },
        }, [
            el('div', { class: 'ir-room-floor-box' }, [floor]),
            el('div', { class: 'ir-room-card-box' }, [card]),
            el('div', { class: 'ir-room-meeples' }),
        ]);
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

    const order = orderPlayers(playerOrder, players, me);
    const playersEl = el('div', { id: 'ir-players', class: 'ir-players' });
    const playerAreas = new Map();
    for (const playerId of order) {
        const refs = buildPlayerArea(playerId, players[playerId] ?? {}, playerId === me);
        playerAreas.set(playerId, refs);
        playersEl.append(refs.area);
    }

    const root = el('div', { id: 'ir-root', class: 'ir-root', data: { layout: 'wide' } }, [
        main,
        common,
        playersEl,
    ]);
    host.append(root);

    /** @type {BoardRefs} */
    const refs = {
        root, cloister, center, slotPilesTop, slotOpenBelow, rooms, roomFloors, roomCards, piles, pileSlots, open, openSlots,
        spiritTrack, enhPile, goals, players: playersEl, playerAreas, playerOrder: order,
    };
    // bga-cards の Stock は置き場が document に付いていないと例外を出す。Layout が付け替えるより前に Stock を作るので、先に既定の置き場所へ付ける。
    placeFloating(refs, 'wide');
    return refs;
}

/**
 * 領域の上から: 名前の行（ミープル・名前・N / 20）→ 上の行（初期自我・強化自我・獲得した目標・トークン）→ 手札のレール（自分だけ）→ 自己構成済みのレール。
 * 領域は箱で囲まない（011 §4.1）。手番は BGA 標準のパネルの強調とステータスバーで示す。
 * 強化自我の枠と獲得した目標の欄は、持つまで `hidden`（場所を取らない。010 §5.2）。他プレイヤーは手札のマットを持たない（枚数はパネルだけ。010 §4）。
 * @param {number} playerId
 * @param {{ name?: string, color?: string }} player
 * @param {boolean} mine
 * @returns {PlayerAreaRefs}
 */
function buildPlayerArea(playerId, player, mine) {
    const ego = slot('ir-slot-l ir-ego');
    const enhEgo = slot('ir-slot-l ir-enh-ego');
    enhEgo.hidden = true;
    const tokens = el('div', { class: 'ir-tokens', attrs: { role: 'group', 'aria-label': _('Tokens') } });
    const ownedGoals = el('div', { class: 'ir-owned-goals ir-stock-l', attrs: { 'aria-label': _('Claimed objective cards') } });
    ownedGoals.hidden = true;
    const hand = mine ? el('div', { class: 'ir-hand ir-stock-mp', attrs: { 'aria-label': _('Your hand') } }) : null;
    const handInfo = mine ? el('span', { class: 'ir-hand-info' }) : null;
    // 手札の上限超過の警告: SVG の「!」と N / 8（CSS の content の記号は使わない。011 §1.1）
    const handWarn = handInfo === null ? null : el('span', { class: 'ir-hand-warn' }, [createSymbol('warn'), handInfo]);
    if (handInfo !== null && handWarn !== null) {
        handInfo.hidden = true;
        handWarn.hidden = true;
    }
    const handActions = mine ? el('div', { class: 'ir-rail-actions' }) : null;
    const under = mine ? el('div', { class: 'ir-under ir-stock-mp', attrs: { 'aria-label': _('Cards under a composed card') } }) : null;
    const composed = el('div', { class: 'ir-composed ir-stock-mp', attrs: { 'aria-label': _('Composed cards') } });
    const meeple = el('span', { class: 'ir-player-meeple', attrs: { 'aria-hidden': 'true' } });
    const name = el('span', { class: 'ir-player-name' }, [player.name ?? '']);
    const score = el('span', { class: 'ir-player-score', attrs: { ...tipAttrs(_('Construction score')), 'aria-label': _('Construction score') } }, ['0 / 20']);
    // レールの上端の線に載るラベル（文字そのものが名前。title は付けない）。手札は警告が横に並ぶ
    const handTitle = el('div', { class: 'ir-rail-title' }, [el('span', { class: 'ir-rail-label' }, [_('Hand')]), ...(handWarn === null ? [] : [handWarn])]);
    const composedTitle = el('div', { class: 'ir-rail-title' }, [el('span', { class: 'ir-rail-label' }, [_('Constructed')])]);
    const area = el('section', {
        id: `ir-player-area-${playerId}`,
        class: `ir-player-area${mine ? ' ir-mine' : ''}`,
        data: { player: int(playerId) },
    }, [
        el('header', { class: 'ir-player-header', data: { color: colorName(player.color) } }, [meeple, name, score]),
        el('div', { class: 'ir-player-top' }, [ego, enhEgo, ownedGoals, tokens]),
        // 手札と自己構成済みは、レールのラベルと薄いマットの色合いで分ける（作者指示。010 No.3、011 §1.2）
        ...(hand === null || handActions === null ? [] : [el('div', { class: 'ir-rail ir-zone ir-zone-hand', attrs: { role: 'group', 'aria-label': _('Hand') } }, [handTitle, handActions, hand])]),
        el('div', { class: 'ir-rail ir-zone ir-zone-composed', attrs: { role: 'group', 'aria-label': _('Constructed') } }, [
            composedTitle,
            composed,
            ...(under === null ? [] : [under]),
        ]),
    ]);
    return { area, ego, enhEgo, tokens, ownedGoals, hand, handActions, handWarn, handInfo, under, composed, meeple, score };
}

/**
 * レイアウトに応じて、ir-piles と ir-open の置き場所を付け替える（009 §2.4）。
 * xl・wide・medium: piles は環の上、open は環の中央。narrow: piles が環の中央、open は環の下。
 * @param {BoardRefs} refs
 * @param {import('../ui/layout-math.js').LayoutName} layout
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
