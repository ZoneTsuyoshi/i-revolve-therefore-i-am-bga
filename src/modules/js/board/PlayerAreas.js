/**
 * プレイヤーの領域の描画（仕様書 009 §3.7〜§3.11、§4、§13）。U-2 の器（`board/Board.js` の `playerAreas`）に bga-cards の Stock を作り、
 * getAllDatas と通知で更新する。表示の元になる値の変換は `player-view-model.js`（純粋関数）にある。
 *
 * - 全員: 初期自我・強化自我（LineStock、横長）、獲得した目標カード、トークン（心光・万能移動鍵の表/使用済み・自己構成・精神分析）、
 *   自己構成済みカード（LineStock、折り返し。横向き＝`ir-tapped` ＋「Used」帯）、I08 の下のカードの枚数バッジ
 * - 自分だけ: 手札（LineStock、折り返し、レベル・エネルギー・番号順。上限 8 の表示）、I08 の下のカードの中身
 * - 他人: 手札は枚数チップだけ（Stock を作らない）
 * - 選択: `this.selection`（`cards/selection.js` の SelectionController）。`selectHand`・`selectComposed`・`clearSelection`
 *
 * U-4 の `CommonArea.playerStocks`（自我・目標の最小の Stock）はここへ引き取った。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { paintUiSprite } from '../cards/card-face.js';
import { SelectionController } from '../cards/selection.js';
import { el } from '../ui/dom.js';
import {
    HAND_LIMIT, TOKEN_KINDS, applyTotals, compareHand, composedModel, energyMap, handCard, handCountState, handModel, memoryInfoTable,
    restoreWild, tokensModel, underModel,
} from './player-view-model.js';
import { egoCard, faceUpMemory, objectivesModel, toInt } from './view-model.js';

/**
 * @typedef {object} PlayerAreasDeps
 * @property {import('./Board.js').BoardRefs} refs
 * @property {import('../cards/managers.js').Managers} cards
 * @property {any} BgaCards `importEsmLib('bga-cards', '1.x')`
 * @property {import('../cards/card-face.js').ImgUrl} imgUrl
 * @property {() => boolean} animationsActive
 * @property {number | null} [me] 自分の ID（観戦者は null）
 */

/**
 * @typedef {object} PlayerStocks
 * @property {any} ego
 * @property {any} enhEgo
 * @property {any} owned
 * @property {any} composed
 * @property {any | null} hand
 * @property {any | null} under
 */

export class PlayerAreas {
    /**
     * @param {PlayerAreasDeps} deps
     */
    constructor(deps) {
        this.refs = deps.refs;
        this.cards = deps.cards;
        this.lib = deps.BgaCards;
        this.imgUrl = deps.imgUrl;
        this.animationsActive = deps.animationsActive;
        this.me = deps.me ?? null;
        /** @type {Map<number, PlayerStocks>} */
        this.stocks = new Map();
        /** @type {Map<number, import('./player-view-model.js').Tokens>} */
        this.tokens = new Map();
        /** @type {Map<number, Record<string, { root: HTMLElement, count: HTMLElement }>>} */
        this.tokenEls = new Map();
        /** @type {Map<number, number>} 他人（観戦時は全員）の手札の枚数 */
        this.handCounts = new Map();
        /** @type {Map<number, { level: number, energy: number }>} 想い出カードの種類 → レベルと印刷のエネルギー */
        this.info = new Map();
        /** @type {Map<number, { level: number, energy: number, type: number }>} 手札のカードの並び順の元（id → 現在の値） */
        this.handData = new Map();
        /** @type {Map<number, number>} 自己構成済みカードの ID → I08 の下の枚数 */
        this.underCounts = new Map();
        /** @type {Map<number, number>} 下のカードの ID → 親の ID（本人だけ） */
        this.underHost = new Map();
        /** @type {Set<number>} 横向き（使用済み）の自己構成済みカード */
        this.tapped = new Set();
        /** @type {Map<string, number>} 獲得した目標カードのキー → 現在の得点 */
        this.goalPoints = new Map();
        this.selection = new SelectionController();
        /** setup の非同期の追加がすべて終わったら解決する（テスト用） */
        this.ready = Promise.resolve();
        for (const [playerId, area] of this.refs.playerAreas) {
            this.stocks.set(playerId, this.createStocks(area));
        }
    }

    /**
     * @param {import('./Board.js').PlayerAreaRefs} area
     * @returns {PlayerStocks}
     */
    createStocks(area) {
        const wrap = { wrap: 'wrap', gap: 'var(--ir-gap)' };
        return {
            ego: new this.lib.LineStock(this.cards.land, area.ego, { gap: '0' }),
            enhEgo: new this.lib.LineStock(this.cards.land, area.enhEgo, { gap: '0' }),
            owned: new this.lib.LineStock(this.cards.land, area.ownedGoals, { gap: 'var(--ir-gap)' }),
            composed: new this.lib.LineStock(this.cards.mem, area.composed, wrap),
            hand: area.hand === null ? null : new this.lib.LineStock(this.cards.mem, area.hand, { ...wrap, sort: compareHand }),
            under: area.under === null ? null : new this.lib.LineStock(this.cards.mem, area.under, wrap),
        };
    }

    // ---- 初期表示 ----

    /**
     * getAllDatas の値から描く。中身が無くても例外を出さない。
     * @param {Record<string, any>} gamedatas
     */
    setup(gamedatas) {
        this.info = memoryInfoTable(gamedatas?.catalog);
        const jobs = [];
        const playerData = gamedatas?.playerData ?? {};
        for (const playerId of this.refs.playerAreas.keys()) {
            const data = playerData[playerId];
            this.renderTokens(playerId);
            this.setTokens(playerId, data?.tokens, true);
            const stocks = this.stocks.get(playerId);
            const ego = egoCard(data?.initialEgo);
            const enh = egoCard(data?.enhancedEgo);
            if (ego !== null) {
                jobs.push(stocks?.ego.addCard(ego));
            }
            if (enh !== null) {
                jobs.push(stocks?.enhEgo.addCard(enh));
            }
            for (const entry of composedModel(data?.composed)) {
                jobs.push(this.addComposed(playerId, entry.card, { tapped: entry.tapped, underCount: entry.underCount }));
            }
            if (stocks?.hand === null || stocks?.hand === undefined) {
                this.setHandCount(playerId, data?.handCount);
            }
        }
        for (const entry of objectivesModel(gamedatas?.objectives).owned) {
            jobs.push(this.stocks.get(entry.owner)?.owned.addCard(entry.card));
        }
        const mine = this.me === null ? undefined : this.stocks.get(this.me);
        if (mine?.hand) {
            for (const card of handModel(gamedatas?.myHand, this.info)) {
                this.handData.set(card.id, card);
                jobs.push(mine.hand.addCard(card));
            }
            this.refreshHandCount();
        }
        if (mine?.under) {
            for (const group of underModel(gamedatas?.myUnder)) {
                for (const card of group.cards) {
                    this.underHost.set(card.id, group.host);
                    jobs.push(mine.under.addCard(card));
                }
            }
        }
        this.setTurn(toInt(gamedatas?.turnPlayer));
        this.ready = Promise.all(jobs).then(() => undefined);
    }

    /**
     * @param {number} playerId
     * @returns {boolean} 手札の Stock を持つ（自分の領域）
     */
    isMine(playerId) {
        return this.stocks.get(playerId)?.hand !== null && this.stocks.get(playerId)?.hand !== undefined;
    }

    /**
     * 手番のプレイヤーの領域の枠を太くする（`ir-turn`。色だけに頼らない）。0 なら全部外す。
     * @param {number} playerId
     */
    setTurn(playerId) {
        for (const [id, area] of this.refs.playerAreas) {
            area.area.classList.toggle('ir-turn', id === playerId);
        }
    }

    // ---- トークン ----

    /**
     * トークンの欄を作る（種類ごとに「画像 + 個数」。0 でも出して行の位置を固定する）。
     * @param {number} playerId
     */
    renderTokens(playerId) {
        const holder = this.refs.playerAreas.get(playerId)?.tokens;
        if (holder === undefined || this.tokenEls.has(playerId)) {
            return;
        }
        /** @type {Record<string, { root: HTMLElement, count: HTMLElement }>} */
        const els = {};
        for (const { kind, sprite, name } of TOKEN_KINDS) {
            const icon = el('span', { class: 'ir-token-icon', attrs: { 'aria-hidden': 'true' } });
            paintUiSprite(icon, sprite, this.imgUrl);
            const count = el('span', { class: 'ir-token-count' });
            const root = el('span', { class: 'ir-token', data: { kind }, attrs: { title: _(name) } }, [icon, count]);
            holder.append(root);
            els[kind] = { root, count };
        }
        this.tokenEls.set(playerId, els);
    }

    /**
     * トークンの個数を設定する。`absolute` が真なら playerData の `tokens`（キャメルケース、全値）、偽なら N16 の `totals`（snake_case、変わった分だけ）。
     * @param {number} playerId
     * @param {unknown} values
     * @param {boolean} [absolute] true: 全値（getAllDatas）、false: N16 の totals
     */
    setTokens(playerId, values, absolute = false) {
        const current = this.tokens.get(playerId) ?? tokensModel(null);
        this.paintTokens(playerId, absolute ? tokensModel(values) : applyTotals(current, values));
    }

    /**
     * クリーンアップで万能移動鍵が裏から表へ戻る。
     * @param {number} playerId
     * @param {unknown} restored
     */
    restoreWildKeys(playerId, restored) {
        const current = this.tokens.get(playerId) ?? tokensModel(null);
        this.paintTokens(playerId, restoreWild(current, restored), true);
    }

    /**
     * @param {number} playerId
     * @param {import('./player-view-model.js').Tokens} tokens
     * @param {boolean} [flip] 万能移動鍵の反転のアニメーションを出す
     */
    paintTokens(playerId, tokens, flip = false) {
        const previous = this.tokens.get(playerId);
        this.tokens.set(playerId, tokens);
        const els = this.tokenEls.get(playerId);
        if (els === undefined) {
            return;
        }
        for (const { kind } of TOKEN_KINDS) {
            const entry = els[kind];
            const value = tokens[kind];
            entry.count.textContent = kind === 'wildUsed' ? _('+ ${n} used').replace('${n}', String(value)) : String(value);
            entry.root.classList.toggle('ir-zero', value === 0);
            if (flip && previous !== undefined && previous[kind] !== value) {
                // 反転は CSS のアニメーション（.ir-token-flip）。クラスは付け直して再生する
                entry.root.classList.toggle('ir-token-flip', false);
                entry.root.classList.toggle('ir-token-flip', this.animationsActive());
            }
        }
    }

    // ---- 手札 ----

    /**
     * 他人の手札の枚数チップ（カードの束のアイコン + 数字。009 §3.11）。観戦時は全員。手札の Stock を持つ自分には使わない。
     * @param {number} playerId
     * @param {unknown} count
     */
    setHandCount(playerId, count) {
        if (count === undefined || count === null || this.isMine(playerId)) {
            return;
        }
        const n = handCountState(toInt(count), false).count;
        this.handCounts.set(playerId, n);
        const holder = this.refs.playerAreas.get(playerId)?.handCount;
        if (holder === null || holder === undefined) {
            return;
        }
        if (holder.children.length === 0) {
            holder.append(el('span', { class: 'ir-hand-icon', attrs: { 'aria-hidden': 'true' } }), el('span', { class: 'ir-hand-count-text' }));
        }
        const text = holder.querySelector('.ir-hand-count-text');
        if (text !== null) {
            text.textContent = _('Hand: ${n}').replace('${n}', String(n));
        }
    }

    /** 自分の手札の枚数と上限の表示（超えたら印と文言で示す。色だけにしない） */
    refreshHandCount() {
        const mine = this.me === null ? undefined : this.stocks.get(this.me);
        const info = this.me === null ? null : this.refs.playerAreas.get(this.me)?.handInfo;
        if (mine?.hand === null || mine?.hand === undefined || info === null || info === undefined) {
            return;
        }
        const state = handCountState(mine.hand.getCards().length, true);
        let text = _('Hand: ${n} / ${max}').replace('${n}', String(state.count)).replace('${max}', String(HAND_LIMIT));
        if (state.over) {
            text += ` ${_('(over the limit)')}`;
        }
        info.textContent = text;
        info.classList.toggle('ir-over-limit', state.over);
        this.selection.refresh();
    }

    /**
     * 手札にカードを加える（山札・場札・部屋・自己構成済みから飛んでくる。別の Stock に既にあるカードはそこから移る）。
     * @param {{ id: unknown, type: unknown, energy?: unknown }[]} refs 種類つきのカード
     * @param {HTMLElement | undefined} [from] 飛んでくる元の要素
     * @returns {Promise<void>}
     */
    async addToHand(refs, from) {
        const hand = this.me === null ? null : this.stocks.get(this.me)?.hand;
        if (hand === null || hand === undefined) {
            return;
        }
        const moves = [];
        for (const ref of refs) {
            const card = handCard(ref, this.info);
            this.handData.set(card.id, card);
            this.tapped.delete(card.id);
            moves.push(hand.addCard(card, from === undefined ? undefined : { fromElement: from }));
        }
        await Promise.all(moves);
        this.afterHandChange(refs.map((r) => toInt(r.id)));
        this.refreshHandCount();
    }

    /**
     * 手札からカードを取り除く（他の Stock へ移ったあとの掃除を含む。手札に無ければ何もしない）。
     * @param {number[]} ids
     * @param {HTMLElement | undefined} [to] 飛び先
     * @returns {Promise<void>}
     */
    async removeFromHand(ids, to) {
        const hand = this.me === null ? null : this.stocks.get(this.me)?.hand;
        if (hand === null || hand === undefined) {
            return;
        }
        const moves = [];
        for (const id of ids) {
            const card = hand.getCards().find((/** @type {{ id: number }} */ c) => c.id === id);
            if (card !== undefined) {
                moves.push(hand.removeCard(card, to === undefined ? { fadeOut: true } : { slideTo: to }));
            }
            this.handData.delete(id);
        }
        await Promise.all(moves);
        this.refreshHandCount();
    }

    /**
     * 手札の現在のエネルギー（N35、M098）を反映する。印刷と違うカードにバッジを出す。
     * @param {unknown} energies
     */
    setEnergies(energies) {
        const hand = this.me === null ? null : this.stocks.get(this.me)?.hand;
        if (hand === null || hand === undefined) {
            return;
        }
        for (const [id, energy] of energyMap(energies)) {
            const card = hand.getCards().find((/** @type {{ id: number }} */ c) => c.id === id);
            if (card !== undefined) {
                card.energy = energy;
            }
        }
        this.afterHandChange([...energyMap(energies).keys()]);
    }

    /**
     * 手札のカードのエネルギーバッジ（現在値が印刷と違うときだけ）。
     * @param {number[]} ids
     */
    afterHandChange(ids) {
        const hand = this.me === null ? null : this.stocks.get(this.me)?.hand;
        if (hand === null || hand === undefined) {
            return;
        }
        for (const id of ids) {
            const card = hand.getCards().find((/** @type {{ id: number }} */ c) => c.id === id);
            const element = card === undefined ? undefined : hand.getCardElement(card);
            if (card === undefined || !(element instanceof HTMLElement)) {
                continue;
            }
            element.querySelector('.ir-energy-badge')?.remove();
            if (card.energy !== card.printed) {
                element.append(el('span', { class: 'ir-energy-badge', attrs: { title: _('Current composition energy') } }, [`E${card.energy}`]));
            }
        }
    }

    // ---- 自己構成済み ----

    /**
     * 自己構成済みエリアにカードを置く。
     * @param {number} playerId
     * @param {import('./view-model.js').CardView} card
     * @param {{ from?: HTMLElement, tapped?: boolean, underCount?: number }} [opts]
     * @returns {Promise<void>}
     */
    async addComposed(playerId, card, opts = {}) {
        const stock = this.stocks.get(playerId)?.composed;
        if (stock === undefined) {
            return;
        }
        const id = toInt(card.id);
        if (opts.tapped === true) {
            this.tapped.add(id);
        } else {
            this.tapped.delete(id);
        }
        this.underCounts.set(id, opts.underCount ?? this.underCounts.get(id) ?? 0);
        this.handData.delete(id);
        await stock.addCard(card, opts.from === undefined ? undefined : { fromElement: opts.from });
        this.decorateComposed(playerId, id);
        this.refreshHandCount();
    }

    /**
     * 自己構成済みカードを取り除く。
     * @param {number} playerId
     * @param {number} id
     * @param {HTMLElement | undefined} [to]
     * @returns {Promise<void>}
     */
    async removeComposed(playerId, id, to) {
        const stock = this.stocks.get(playerId)?.composed;
        const card = stock?.getCards().find((/** @type {{ id: number }} */ c) => c.id === id);
        this.tapped.delete(id);
        this.underCounts.delete(id);
        if (stock !== undefined && card !== undefined) {
            await stock.removeCard(card, to === undefined ? { fadeOut: true } : { slideTo: to });
        }
    }

    /**
     * 自己構成済みカードの ID から持ち主を探す。
     * @param {number} id
     * @returns {{ playerId: number, stock: any, card: any } | undefined}
     */
    findComposed(id) {
        for (const [playerId, stocks] of this.stocks) {
            const card = stocks.composed.getCards().find((/** @type {{ id: number }} */ c) => c.id === id);
            if (card !== undefined) {
                return { playerId, stock: stocks.composed, card };
            }
        }
        return undefined;
    }

    /**
     * 横向き（使用済み）にする・縦に戻す（N17、クリーンアップ N29）。
     * @param {number[]} ids
     * @param {boolean} tapped
     */
    setTapped(ids, tapped) {
        for (const id of ids) {
            if (tapped) {
                this.tapped.add(id);
            } else {
                this.tapped.delete(id);
            }
            const found = this.findComposed(id);
            if (found !== undefined) {
                this.decorateComposed(found.playerId, id);
            }
        }
    }

    /**
     * 横向きの表現（`ir-tapped` + 「Used」帯）と、I08 の下のカードの枚数バッジを要素に反映する。
     * @param {number} playerId
     * @param {number} id
     */
    decorateComposed(playerId, id) {
        const stock = this.stocks.get(playerId)?.composed;
        const card = stock?.getCards().find((/** @type {{ id: number }} */ c) => c.id === id);
        const element = card === undefined ? undefined : stock.getCardElement(card);
        if (!(element instanceof HTMLElement)) {
            return;
        }
        const tapped = this.tapped.has(id);
        element.classList.toggle('ir-tapped', tapped);
        element.querySelector('.ir-used-band')?.remove();
        if (tapped) {
            element.append(el('span', { class: 'ir-used-band' }, [_('Used')]));
        }
        element.querySelector('.ir-under-badge')?.remove();
        const under = this.underCounts.get(id) ?? 0;
        if (under > 0) {
            element.append(el('span', { class: 'ir-under-badge' }, [_('Under: ${n}').replace('${n}', String(under))]));
        }
    }

    // ---- I08 の下のカード ----

    /**
     * 親の下に置く（N19）。本人は中身を下のカードの欄に加え、全員が親の枚数バッジを増やす。
     * @param {number} playerId 持ち主
     * @param {number} hostId 親の自己構成済みカード
     * @param {number} count
     * @param {{ id: unknown, type: unknown }[]} refs 本人だけ（種類つき）
     * @returns {Promise<void>}
     */
    async attachUnder(playerId, hostId, count, refs) {
        this.underCounts.set(hostId, (this.underCounts.get(hostId) ?? 0) + Math.max(0, count));
        const under = this.me === null ? null : this.stocks.get(this.me)?.under;
        if (this.isMine(playerId) && under !== null && under !== undefined) {
            const moves = [];
            for (const ref of refs) {
                const card = faceUpMemory(ref);
                this.underHost.set(toInt(card.id), hostId);
                this.handData.delete(toInt(card.id));
                moves.push(under.addCard(card));
            }
            await Promise.all(moves);
        }
        this.decorateComposed(playerId, hostId);
        this.refreshHandCount();
    }

    /**
     * 親が場を離れて下のカードが捨札へ移った（N15 `UNDER_RELEASED`）後の掃除。
     * @param {number[]} ids 捨てられたカード（親の下のカードとは限らない）
     */
    releaseUnder(ids) {
        const under = this.me === null ? null : this.stocks.get(this.me)?.under;
        for (const id of ids) {
            const host = this.underHost.get(id);
            if (host === undefined) {
                continue;
            }
            this.underHost.delete(id);
            this.underCounts.set(host, Math.max(0, (this.underCounts.get(host) ?? 0) - 1));
            const card = under?.getCards().find((/** @type {{ id: number }} */ c) => c.id === id);
            if (card !== undefined) {
                void under.removeCard(card, { fadeOut: true });
            }
            const found = this.findComposed(host);
            if (found !== undefined) {
                this.decorateComposed(found.playerId, host);
            }
        }
    }

    // ---- 自我・目標 ----

    /**
     * 自我カードを公開して持ち主の領域に置く（N02 の初期自我、N25 の強化自我）。
     * @param {number} playerId
     * @param {'ego' | 'enhEgo'} kind
     * @param {unknown} egoId
     * @param {HTMLElement | undefined} [from]
     * @returns {Promise<void>}
     */
    async showEgo(playerId, kind, egoId, from) {
        const card = egoCard(egoId);
        const stock = this.stocks.get(playerId)?.[kind];
        if (card !== null && stock !== undefined) {
            await stock.addCard(card, from === undefined ? undefined : { fromElement: from });
        }
    }

    /**
     * 獲得した目標カードを置く（N22）。共通の場のスロットにあるカードはそこから移る。右上に得点のバッジ（わかるとき）。
     * @param {string} key 目標カードのキー（`O001`）
     * @param {number} playerId
     * @param {unknown} [points]
     * @returns {Promise<void>}
     */
    async addOwnedGoal(key, playerId, points) {
        const owned = this.stocks.get(playerId)?.owned;
        if (owned === undefined) {
            return;
        }
        if (points !== undefined && points !== null) {
            this.goalPoints.set(key, toInt(points));
        }
        await owned.addCard({ id: key, key });
        this.refreshGoalBadges();
    }

    /** 獲得した目標カードの「+N」バッジ（009 §3.7） */
    refreshGoalBadges() {
        for (const stocks of this.stocks.values()) {
            for (const card of stocks.owned.getCards()) {
                const element = stocks.owned.getCardElement(card);
                const points = this.goalPoints.get(String(card.id));
                if (!(element instanceof HTMLElement)) {
                    continue;
                }
                element.querySelector('.ir-goal-points')?.remove();
                if (points !== undefined) {
                    element.append(el('span', { class: 'ir-goal-points' }, [`+${points}`]));
                }
            }
        }
    }

    // ---- 選択の基盤（実際の UI は I-07 の後続が使う） ----

    /**
     * 手札のカードを選べるようにする。
     * @param {import('../cards/selection.js').SelectionOptions} [options]
     */
    selectHand(options) {
        const hand = this.me === null ? null : this.stocks.get(this.me)?.hand;
        if (hand !== null && hand !== undefined) {
            this.selection.start(hand, options);
        }
    }

    /**
     * 自己構成済みカードを選べるようにする（持ち主の領域）。
     * @param {number} playerId
     * @param {import('../cards/selection.js').SelectionOptions} [options]
     */
    selectComposed(playerId, options) {
        const stock = this.stocks.get(playerId)?.composed;
        if (stock !== undefined) {
            this.selection.start(stock, options);
        }
    }

    /** 状態を出るとき: 選択モードと見た目を全部掃除する */
    clearSelection() {
        this.selection.clear();
    }
}
