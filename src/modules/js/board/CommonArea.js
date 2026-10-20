/**
 * 共通の領域の描画（仕様書 009 §3.1〜§3.6、§13）。回廊（床・裏向きの想い出カード・人格）、場札、山札・捨札、精神トラック
 * （心光・キューブ）、強化自我の山、目標カードを、U-2 の器（`board/Board.js`）に bga-cards の Stock で描き、通知で更新する。
 * 表示の元になる値の変換は `view-model.js`（純粋関数）にある。このクラスは DOM と Stock を持つ。
 * カード ID: 想い出カードは card_id（整数）、横長カードはスプライトのキー（`F01`・`R1A`・`O001`・`I05`・`E02`）を文字列の ID にする。
 * プレイヤーの領域（手札・自己構成済み・自我・トークン・獲得した目標。U-5）は `PlayerAreas`、プレイヤーパネル（U-6）、移動の計画（U-3）はここでは扱わない。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { paintUiSprite } from '../cards/card-face.js';
import { el } from '../ui/dom.js';
import {
    colorName, cubeSprite, decksModel, discardModel, faceUpMemory, faceUpRefs,
    levelTable, meepleSlot, meepleSprite, objectivesModel, objectiveStatusMap, openModel, personaPositions, roomsModel,
    spiritModel, toInt, OPEN_SLOT_COUNT,
} from './view-model.js';

/** ミープルの移動 1 ステップの長さ（ms。009 §3.1。全体で 0.8 秒に収まるのは通知側で制限する） */
const MEEPLE_STEP_MS = 250;
/** 山札の厚み（枚数 → 重ね枚数。009 §3.3） */
const DECK_THICKNESSES = [0, 2, 5, 10, 20, 30];
const COUNTER = { show: true, position: 'bottom', extraClasses: 'ir-count' };

/**
 * @typedef {object} CommonAreaDeps
 * @property {import('./Board.js').BoardRefs} refs
 * @property {import('../cards/managers.js').Managers} cards
 * @property {any} BgaCards `importEsmLib('bga-cards', '1.x')`
 * @property {import('../cards/card-face.js').ImgUrl} imgUrl
 * @property {() => boolean} animationsActive
 * @property {import('./PlayerAreas.js').PlayerAreas} playerAreas プレイヤーの領域（自我・獲得した目標の置き場）
 * @property {Record<string, { name?: string, color?: string }>} players `gamedatas.players`
 */

/** 描画の担当 */
export class CommonArea {
    /**
     * @param {CommonAreaDeps} deps
     */
    constructor(deps) {
        this.refs = deps.refs;
        this.cards = deps.cards;
        this.imgUrl = deps.imgUrl;
        this.animationsActive = deps.animationsActive;
        this.players = deps.players;
        this.playerAreas = deps.playerAreas;
        this.lib = deps.BgaCards;
        /** @type {Map<number, any>} 位置 → 床カードの Stock */
        this.floorStocks = new Map();
        /** @type {Map<number, any>} 位置 → 部屋の想い出カードの Stock */
        this.roomStocks = new Map();
        /** @type {any[]} */
        this.openStocks = [];
        /** @type {Record<number, any>} */
        this.decks = {};
        /** @type {Record<number, any>} */
        this.discards = {};
        /** 捨札の一番上と枚数（Deck の setCardNumber に渡す） */
        this.discardState = /** @type {Record<number, { count: number, top: import('./view-model.js').CardView | null }>} */ ({});
        /** @type {any} */
        this.enhDeck = null;
        this.enhCount = 0;
        /** @type {any} */
        this.goalStock = null;
        /** @type {Map<string, HTMLElement>} 精神カードの ID → 器（心光・キューブを重ねる） */
        this.spiritSlots = new Map();
        /** @type {Map<string, any>} */
        this.spiritStocks = new Map();
        /** @type {Map<string, HTMLElement>} */
        this.lights = new Map();
        /** @type {HTMLElement | null} */
        this.startCubes = null;
        /** @type {Map<number, HTMLElement>} */
        this.cubes = new Map();
        /** @type {Map<number, HTMLElement>} */
        this.meeples = new Map();
        /** @type {Map<number, number>} 想い出カードの種類 → レベル */
        this.levels = new Map();
        /** @type {Map<string, { x: number, points: number }>} */
        this.objectiveStatus = new Map();
        /** @type {number[]} 席順（ミープルのスロットとキューブの並び） */
        this.seatOrder = deps.refs.playerOrder;
    }

    /**
     * 初期表示（getAllDatas の値から）。中身が無くても例外を出さない。
     * @param {Record<string, any>} gamedatas
     */
    setup(gamedatas) {
        this.levels = levelTable(gamedatas?.catalog);
        this.setupRooms(roomsModel(gamedatas?.rooms));
        this.setupOpen(openModel(gamedatas?.open));
        this.setupPiles(decksModel(gamedatas?.decks), gamedatas?.discards);
        this.setupSpirit(spiritModel(gamedatas?.spirit, gamedatas?.playerData, this.seatOrder), gamedatas?.playerData);
        this.setupEnhDeck(toInt(gamedatas?.enhDeckCount));
        this.setupObjectives(objectivesModel(gamedatas?.objectives));
        this.setupPersonas(personaPositions(gamedatas?.playerData));
        this.setObjectiveStatus(objectiveStatusMap(gamedatas?.myObjectiveStatus));
    }

    // ---- 作る ----

    /** @param {import('./view-model.js').RoomModel[]} rooms */
    setupRooms(rooms) {
        for (const [pos, floorEl] of this.refs.roomFloors) {
            this.floorStocks.set(pos, new this.lib.LineStock(this.cards.land, floorEl, { gap: '0' }));
        }
        for (const [pos, cardEl] of this.refs.roomCards) {
            this.roomStocks.set(pos, new this.lib.LineStock(this.cards.mem, cardEl, { gap: '0' }));
        }
        for (const room of rooms) {
            this.floorStocks.get(room.pos)?.addCard(room.floor);
            if (room.card !== null) {
                this.roomStocks.get(room.pos)?.addCard(room.card);
            }
        }
    }

    /** @param {{ slot: number, card: import('./view-model.js').CardView }[]} open */
    setupOpen(open) {
        this.openStocks = this.refs.openSlots.map((slotEl) => new this.lib.LineStock(this.cards.mem, slotEl, { gap: '0' }));
        for (const entry of open) {
            this.openStocks[entry.slot]?.addCard(entry.card);
        }
    }

    /**
     * @param {Record<1 | 2, import('./view-model.js').DeckModel>} decks
     * @param {unknown} discards
     */
    setupPiles(decks, discards) {
        for (const level of [1, 2]) {
            const deckEl = this.refs.pileSlots[`deck-${level}`];
            const discardEl = this.refs.pileSlots[`discard-${level}`];
            const model = decks[/** @type {1 | 2} */ (level)];
            this.decks[level] = new this.lib.Deck(this.cards.mem, deckEl, {
                cardNumber: model.count,
                topCard: model.top ?? undefined,
                counter: COUNTER,
                thicknesses: DECK_THICKNESSES,
            });
            const discard = discardModel(/** @type {any} */ (discards)?.[level]);
            this.discardState[level] = discard;
            this.discards[level] = new this.lib.Deck(this.cards.mem, discardEl, {
                cardNumber: discard.count,
                topCard: discard.top ?? undefined,
                counter: COUNTER,
                thicknesses: DECK_THICKNESSES,
            });
        }
    }

    /**
     * @param {import('./view-model.js').SpiritModel} model
     * @param {Record<string, any> | undefined} playerData
     */
    setupSpirit(model, playerData) {
        for (const card of model.cards) {
            const slotEl = this.refs.spiritTrack.querySelector(`.ir-spirit-slot[data-tier="${card.tier}"][data-slot="${card.slot}"]`);
            if (!(slotEl instanceof HTMLElement)) {
                continue;
            }
            const host = el('div', { class: 'ir-spirit-card' });
            const cubes = el('div', { class: 'ir-cubes' });
            slotEl.append(host, cubes);
            slotEl.dataset.spirit = card.id;
            this.spiritSlots.set(card.id, cubes);
            const stock = new this.lib.LineStock(this.cards.land, host, { gap: '0' });
            this.spiritStocks.set(card.id, stock);
            stock.addCard({ id: card.id, key: card.id });
            if (card.light) {
                const light = el('div', { class: 'ir-light', attrs: { 'aria-hidden': 'true' } });
                paintUiSprite(light, 'token_light', this.imgUrl);
                slotEl.append(light);
                this.lights.set(card.id, light);
            }
        }
        // 階層 0（開始位置）の帯
        const startCubes = el('div', { class: 'ir-cubes' });
        this.startCubes = startCubes;
        this.refs.spiritTrack.append(el('div', { class: 'ir-spirit-start' }, [
            el('span', { class: 'ir-spirit-start-label' }, [_('Start')]),
            startCubes,
        ]));
        const all = [...this.seatOrder];
        for (const id of Object.keys(playerData ?? {}).map(toInt)) {
            if (!all.includes(id)) {
                all.push(id);
            }
        }
        for (const playerId of all) {
            if (playerData?.[playerId] === undefined) {
                continue;
            }
            const cube = this.createCube(playerId);
            const where = model.cards.find((c) => c.cubes.includes(playerId));
            (where === undefined ? startCubes : this.spiritSlots.get(where.id) ?? startCubes).append(cube);
        }
    }

    /** @param {number} count */
    setupEnhDeck(count) {
        this.enhCount = count;
        this.enhDeck = new this.lib.Deck(this.cards.land, this.refs.enhPile, {
            cardNumber: count,
            topCard: count > 0 ? { id: 'enh-top', key: 'Erev' } : undefined,
            counter: COUNTER,
            thicknesses: DECK_THICKNESSES,
        });
    }

    /** @param {import('./view-model.js').ObjectivesModel} model */
    setupObjectives(model) {
        const count = Math.max(model.slotCount, 0);
        this.goalStock = new this.lib.SlotStock(this.cards.land, this.refs.goals, {
            slotsIds: Array.from({ length: count }, (_unused, i) => i),
            slotClasses: ['ir-slot', 'ir-slot-l'],
            mapCardToSlot: (/** @type {{ slot?: number }} */ card) => card.slot ?? 0,
            gap: 'var(--ir-gap)',
        });
        for (const entry of model.common) {
            this.goalStock.addCard({ ...entry.card, slot: entry.slot });
        }
    }

    /** @param {Map<number, number>} positions */
    setupPersonas(positions) {
        for (const [playerId, pos] of positions) {
            this.placeMeepleNow(playerId, pos);
        }
    }

    // ---- 駒（ミープル・キューブ） ----

    /**
     * @param {number} playerId
     * @returns {import('./view-model.js').PlayerColor}
     */
    colorOf(playerId) {
        return colorName(this.players[playerId]?.color);
    }

    /**
     * @param {number} playerId
     * @returns {HTMLElement}
     */
    createCube(playerId) {
        const cube = el('div', { class: 'ir-cube', data: { player: playerId }, attrs: { title: this.players[playerId]?.name ?? '' } });
        paintUiSprite(cube, cubeSprite(this.colorOf(playerId)), this.imgUrl);
        this.cubes.set(playerId, cube);
        return cube;
    }

    /**
     * @param {number} playerId
     * @returns {HTMLElement}
     */
    meepleOf(playerId) {
        let meeple = this.meeples.get(playerId);
        if (meeple === undefined) {
            meeple = el('div', {
                class: 'ir-meeple',
                data: { player: playerId, slot: meepleSlot(this.seatOrder, playerId) },
                attrs: { title: this.players[playerId]?.name ?? '' },
            });
            paintUiSprite(meeple, meepleSprite(this.colorOf(playerId)), this.imgUrl);
            this.meeples.set(playerId, meeple);
        }
        return meeple;
    }

    /**
     * @param {number} pos
     * @returns {HTMLElement | null}
     */
    meepleHolder(pos) {
        return this.refs.rooms.get(pos)?.querySelector('.ir-room-meeples') ?? null;
    }

    /**
     * アニメーション無しで置く（初期表示）。
     * @param {number} playerId
     * @param {number} pos
     */
    placeMeepleNow(playerId, pos) {
        this.meepleHolder(pos)?.append(this.meepleOf(playerId));
    }

    /**
     * 人格を部屋に移す（N03・N06・N08）。
     * @param {number} playerId
     * @param {number} pos
     * @returns {Promise<void>}
     */
    async movePersona(playerId, pos) {
        const holder = this.meepleHolder(pos);
        if (holder !== null) {
            await this.moveElement(this.meepleOf(playerId), holder);
        }
    }

    /**
     * 要素を別の親へ付け替える。アニメーションが有効ならスライドする（bga-animations）。
     * @param {HTMLElement} node
     * @param {HTMLElement} parent
     * @param {number} [duration]
     * @returns {Promise<void>}
     */
    async moveElement(node, parent, duration = MEEPLE_STEP_MS) {
        if (node.parentElement === parent) {
            return;
        }
        const animations = /** @type {any} */ (this.cards.animations);
        if (this.animationsActive() && typeof animations?.slideAndAttach === 'function') {
            try {
                await animations.slideAndAttach(node, parent, { duration });
                return;
            } catch {
                // 失敗したら即時に付け替える
            }
        }
        parent.append(node);
    }

    /**
     * 精神トラックの前進（N24）。キューブを移し、到着した精神カード上の心光を取る。
     * @param {number} playerId
     * @param {string | null} toSpirit
     * @param {boolean} lightTaken
     * @returns {Promise<void>}
     */
    async advanceSpirit(playerId, toSpirit, lightTaken) {
        const cube = this.cubes.get(playerId) ?? this.createCube(playerId);
        const target = (typeof toSpirit === 'string' ? this.spiritSlots.get(toSpirit) : undefined) ?? this.startCubes;
        const moves = [];
        if (target !== null) {
            moves.push(this.moveElement(cube, target, 400));
        }
        if (lightTaken && typeof toSpirit === 'string') {
            moves.push(this.takeLight(toSpirit, playerId));
        }
        await Promise.all(moves);
    }

    /**
     * 精神カード上の心光を取り去る。獲得者の領域のトークン欄（U-5）へ飛ばしてから消す。
     * @param {string} spiritId
     * @param {number} playerId
     * @returns {Promise<void>}
     */
    async takeLight(spiritId, playerId) {
        const light = this.lights.get(spiritId);
        if (light === undefined) {
            return;
        }
        this.lights.delete(spiritId);
        const target = this.refs.playerAreas.get(playerId)?.tokens;
        if (target !== undefined) {
            await this.moveElement(light, target, 400);
        }
        light.remove();
    }

    // ---- カード ----

    /**
     * プレイヤーの領域（カードの飛び先・飛び元）。
     * @param {number} playerId
     * @returns {HTMLElement | undefined}
     */
    areaOf(playerId) {
        return this.refs.playerAreas.get(playerId)?.area;
    }

    /**
     * @param {any[]} stocks
     * @param {number | string} id
     * @returns {any | undefined} 見つかった Stock
     */
    stockHolding(stocks, id) {
        return stocks.find((stock) => stock.getCards().some((/** @type {{ id: unknown }} */ c) => c.id === id));
    }

    /**
     * 山札の枚数と一番上の裏面を合わせる（N12・N29・N32 の `decks`）。
     * @param {unknown} decks
     */
    syncDecks(decks) {
        if (decks === undefined || decks === null) {
            return;
        }
        const model = decksModel(decks);
        for (const level of [1, 2]) {
            const entry = model[/** @type {1 | 2} */ (level)];
            this.decks[level]?.setCardNumber(entry.count, entry.top ?? undefined);
        }
    }

    /**
     * 山札の元の位置（カードが飛んでくる元）。
     * @param {number} level
     * @returns {HTMLElement | undefined}
     */
    deckElement(level) {
        return this.refs.pileSlots[`deck-${level}`];
    }

    /**
     * 想い出カードのレベル（カタログから）。分からなければ 1。
     * @param {number} type
     * @returns {number}
     */
    levelOf(type) {
        return this.levels.get(type) ?? 1;
    }

    /**
     * 部屋のカードを置く・差し替える（補充 N29、I05 の入れ替え N27）。
     * @param {number} pos
     * @param {import('./view-model.js').CardView} card
     * @param {HTMLElement | undefined} [from] 飛んでくる元
     * @returns {Promise<void>}
     */
    async setRoomCard(pos, card, from) {
        const stock = this.roomStocks.get(pos);
        if (stock === undefined) {
            return;
        }
        await Promise.all(stock.getCards().filter((/** @type {{ id: unknown }} */ c) => c.id !== card.id).map((/** @type {any} */ c) => stock.removeCard(c)));
        await stock.addCard(card, from === undefined ? undefined : { fromElement: from });
    }

    /**
     * 部屋のカードを取り除く（想起の獲得 N14、I05 の手札との入れ替え N27）。
     * @param {number} pos
     * @param {HTMLElement | undefined} [to] 飛び先
     * @returns {Promise<void>}
     */
    async removeRoomCard(pos, to) {
        const stock = this.roomStocks.get(pos);
        if (stock === undefined) {
            return;
        }
        await Promise.all(stock.getCards().map((/** @type {any} */ c) => stock.removeCard(c, to === undefined ? { fadeOut: true } : { slideTo: to })));
    }

    /**
     * 想起で部屋のカードを表向きにする（N09）。
     * @param {number} pos
     * @param {{ id: unknown, type: unknown }} card
     * @returns {Promise<void>}
     */
    async revealRoomCard(pos, card) {
        const stock = this.roomStocks.get(pos);
        if (stock === undefined) {
            return;
        }
        const face = faceUpMemory(card);
        await Promise.all(stock.getCards().map((/** @type {any} */ c) => stock.removeCard(c)));
        await stock.addCard(face);
    }

    /**
     * 場札から取り除く（N13・N26）。
     * @param {unknown} ids
     * @param {HTMLElement | undefined} [to]
     * @returns {Promise<void>}
     */
    async removeFromOpen(ids, to) {
        const moves = [];
        for (const { id } of faceUpRefs(ids)) {
            const stock = this.stockHolding(this.openStocks, id);
            if (stock !== undefined) {
                moves.push(stock.removeCard({ id }, to === undefined ? { fadeOut: true } : { slideTo: to }));
            }
        }
        await Promise.all(moves);
    }

    /**
     * 場札に置く（補充 N29、交換 N26）。
     * @param {{ slot: number, card: import('./view-model.js').CardView }[]} entries
     * @param {HTMLElement | undefined} [from]
     * @returns {Promise<void>}
     */
    async addToOpen(entries, from) {
        const moves = [];
        for (const entry of entries) {
            const stock = this.openStocks[entry.slot];
            if (stock !== undefined && entry.slot < OPEN_SLOT_COUNT) {
                moves.push(stock.addCard(entry.card, from === undefined ? undefined : { fromElement: from }));
            }
        }
        await Promise.all(moves);
    }

    /**
     * 捨札に積む（N15）。
     * @param {unknown} cards `[cardRef]`（種類つき）
     * @param {Record<string, unknown> | undefined} pileCounts `{ "1": n, "2": n }`
     * @param {number} playerId 捨てた人（カードが飛んでくる元）
     * @returns {Promise<void>}
     */
    async discardCards(cards, pileCounts, playerId) {
        const from = this.areaOf(playerId);
        for (const ref of faceUpRefs(cards)) {
            const level = this.levelOf(ref.type);
            const card = faceUpMemory(ref);
            const state = this.discardState[level] ?? { count: 0, top: null };
            state.count += 1;
            state.top = card;
            this.discardState[level] = state;
            await this.discards[level]?.addCard(card, from === undefined ? undefined : { fromElement: from });
        }
        for (const level of [1, 2]) {
            const count = pileCounts?.[String(level)];
            if (count !== undefined && count !== null) {
                const state = this.discardState[level] ?? { count: 0, top: null };
                state.count = Math.max(0, toInt(count));
                if (state.count === 0) {
                    state.top = null;
                }
                this.discardState[level] = state;
                this.discards[level]?.setCardNumber(state.count, state.top ?? undefined);
            }
        }
    }

    /**
     * 強化自我の山の枚数（N25）。
     * @param {number} count
     */
    setEnhCount(count) {
        this.enhCount = Math.max(0, count);
        this.enhDeck?.setCardNumber(this.enhCount, this.enhCount > 0 ? { id: 'enh-top', key: 'Erev' } : undefined);
    }

    /**
     * 自我カードを公開して持ち主の領域に置く（N02 の初期自我、N25 の強化自我）。
     * @param {number} playerId
     * @param {'ego' | 'enhEgo'} kind
     * @param {unknown} egoId
     * @param {HTMLElement | undefined} [from]
     * @returns {Promise<void>}
     */
    async showEgo(playerId, kind, egoId, from) {
        await this.playerAreas.showEgo(playerId, kind, egoId, from);
    }

    /**
     * 目標カードを獲得者の領域へ移す（N22）。共通の場のスロットは空き枠のまま残る。
     * @param {unknown} objectiveKeyText `O001`
     * @param {number} playerId
     * @param {unknown} [points] 獲得した目標カードの得点（「+N」バッジ）
     * @returns {Promise<void>}
     */
    async claimObjective(objectiveKeyText, playerId, points) {
        if (typeof objectiveKeyText !== 'string') {
            return;
        }
        this.objectiveStatus.delete(objectiveKeyText);
        await this.playerAreas.addOwnedGoal(objectiveKeyText, playerId, points);
        this.refreshObjectiveBadges();
    }

    /**
     * 未獲得の目標カードの「現在の X と得点」のバッジ（N23。自分の分だけ。009 §3.6）。
     * @param {Map<string, { x: number, points: number }>} statuses
     */
    setObjectiveStatus(statuses) {
        this.objectiveStatus = statuses;
        this.refreshObjectiveBadges();
    }

    refreshObjectiveBadges() {
        if (this.goalStock === null) {
            return;
        }
        for (const card of this.goalStock.getCards()) {
            const cardEl = this.goalStock.getCardElement(card);
            if (!(cardEl instanceof HTMLElement)) {
                continue;
            }
            cardEl.querySelector('.ir-goal-badge')?.remove();
            const status = this.objectiveStatus.get(String(card.id));
            if (status !== undefined) {
                const text = _('Now: X = ${x} → ${points} pts').replace('${x}', String(status.x)).replace('${points}', String(status.points));
                cardEl.append(el('span', { class: 'ir-goal-badge' }, [text]));
            }
        }
    }

    /**
     * 部屋の入れ替え（N28）。`after` の状態に合わせて、床カード（FLOOR）か裏向きの想い出カード（CARD）を動かす。
     * @param {'CARD' | 'FLOOR'} kind
     * @param {{ pos: number, card: import('./view-model.js').CardView }[]} after
     * @returns {Promise<void>}
     */
    async swapRooms(kind, after) {
        const stocks = kind === 'FLOOR' ? this.floorStocks : this.roomStocks;
        await Promise.all(after.map((entry) => stocks.get(entry.pos)?.addCard(entry.card)));
    }
}
