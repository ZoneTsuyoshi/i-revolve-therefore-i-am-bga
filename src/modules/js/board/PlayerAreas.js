/**
 * プレイヤーの領域の描画（仕様書 009 §3.7〜§3.11、§4、§13）。U-2 の器（`board/Board.js` の `playerAreas`）に bga-cards の Stock を作り、
 * getAllDatas と通知で更新する。表示の元になる値の変換は `player-view-model.js`（純粋関数）にある。
 *
 * - 全員: 名前の行（ミープル・名前・N / 20）、初期自我・強化自我（LineStock、横長。強化自我は獲得するまで非表示）、
 *   獲得した目標カード（1 枚以上のときだけ表示）、トークン（画像 + 個数。心光・万能移動鍵の表・万能移動鍵の使用済み・自己構成・精神分析。011 §1.3）、
 *   自己構成済みカード（LineStock、折り返し。横向き＝`ir-tapped` ＋「Used」帯）、I08 の下のカードの枚数バッジ
 * - 自分だけ: 手札（LineStock、折り返し、レベル・エネルギー・番号順。上限 8 を超えたときだけレールのラベルの横に警告）、I08 の下のカードの中身
 * - 他人: 手札のレールを持たない（枚数はパネルだけ。010 §4・§5.1）
 * - 選択: `this.selection`（`cards/selection.js` の SelectionController）。`selectHand`・`selectComposed`・`clearSelection`
 *
 * U-4 の `CommonArea.playerStocks`（自我・目標の最小の Stock）はここへ引き取った。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { paintUiSprite } from '../cards/card-face.js';
import { CLASS_SELECTABLE, CLASS_SELECTED, SelectionController, selectionClasses } from '../cards/selection.js';
import { el, setTip, tipAttrs } from '../ui/dom.js';
import {
    TOKEN_KINDS, applyTotals, compareHand, composedModel, energyMap, handCard, handCountState, handModel, handWarning, memoryInfoTable,
    restoreWild, scoreLabel, tokenChip, tokensModel, underModel,
} from './player-view-model.js';
import { scoresFromArgs } from './player-panel-model.js';
import { colorName, egoCard, faceUpMemory, meepleSprite, objectivesModel, toInt } from './view-model.js';

/**
 * @typedef {object} PlayerAreasDeps
 * @property {import('./Board.js').BoardRefs} refs
 * @property {import('../cards/managers.js').Managers} cards
 * @property {any} BgaCards `importEsmLib('bga-cards', '1.x')`
 * @property {import('../cards/card-face.js').ImgUrl} imgUrl
 * @property {() => boolean} animationsActive
 * @property {number | null} [me] 自分の ID（観戦者は null）
 * @property {Record<string, { name?: string, color?: string }>} [players] `gamedatas.players`（名前の行のミープルの色）
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

/** 押せる（数量を選べる・使える）トークンの印 */
export const CLASS_TOKEN_PICK = 'ir-token-pick';

/**
 * 押せるトークンの設定（`pickTokens`）。
 * @typedef {object} TokenPick
 * @property {boolean} canAdd 押せるか（上限に達していたら偽）
 * @property {number} count 選択中の個数（バッジ。0 なら出さない）
 * @property {number | null} [cost] 次に選ぶときのコスト（N 回目は N 個で 1 つ分。「+N」に出す）
 * @property {boolean} [plus] プラスの印「+」を出すか（既定は出す。トークンの使用では出さない）
 * @property {string} tooltip ツールチップ（押せない理由か、押したときの結果と次のコスト）
 * @property {string} [removeTooltip] バッジのツールチップ
 * @property {() => void} onAdd トークンを押したとき
 * @property {() => void} [onRemove] バッジを押したとき
 */

/** 自己構成するカードとして選んだ手札の印（選択中の印とは別。手札を捨てる選択と重ねる） */
export const CLASS_PENDING = 'ir-pending';

/**
 * トークンのツールチップ（翻訳済み。`_()` は setup 以降に呼ぶ）。抽出のため文字列は `_()` のリテラルで書く。
 * @param {string} kind `TOKEN_KINDS` の kind
 * @returns {string}
 */
function tokenTitle(kind) {
    switch (kind) {
        case 'light': return _('Psychic Light');
        case 'wildUp': return _('Omni-Move Key');
        case 'wildUsed': return _('Omni-Move Key (used)');
        case 'compose': return _('Construct token');
        case 'analysis': return _('Psychoanalysis token');
        default: return '';
    }
}

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
        this.players = deps.players ?? {};
        /** @type {Map<number, PlayerStocks>} */
        this.stocks = new Map();
        /** @type {Map<number, import('./player-view-model.js').Tokens>} */
        this.tokens = new Map();
        /** @type {Map<number, Record<string, { root: HTMLElement, count: HTMLElement }>>} */
        this.tokenEls = new Map();
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
        /** @type {HTMLElement[]} 「自己構成するカード」の印を付けた手札の要素 */
        this.marked = [];
        /** @type {HTMLElement | null} 手札のレールの右端に足した「Discard 2 for Psychic Light」ボタン */
        this.discardButton = null;
        /** @type {(() => void) | null} */
        this.discardButtonHandler = null;
        /** @type {Array<() => void>} トークンを押せるようにした設定を元に戻す関数（`clearTokenPicks` で全部呼ぶ） */
        this.tokenPickCleanups = [];
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
            this.renderHeader(playerId, data?.score);
            this.renderTokens(playerId);
            this.setTokens(playerId, data?.tokens, true);
            const stocks = this.stocks.get(playerId);
            const ego = egoCard(data?.initialEgo);
            const enh = egoCard(data?.enhancedEgo);
            if (ego !== null) {
                jobs.push(stocks?.ego.addCard(ego));
            }
            if (enh !== null) {
                this.reveal(playerId, 'enhEgo');
                jobs.push(stocks?.enhEgo.addCard(enh));
            }
            for (const entry of composedModel(data?.composed)) {
                jobs.push(this.addComposed(playerId, entry.card, { tapped: entry.tapped, underCount: entry.underCount }));
            }
        }
        for (const entry of objectivesModel(gamedatas?.objectives).owned) {
            this.reveal(entry.owner, 'owned');
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
     * 手番のプレイヤーの領域に `data-turn` を付ける（見た目はない。手番は BGA 標準のパネルの強調とステータスバーで示す。011 §4.1）。0 なら全部外す。
     * @param {number} playerId
     */
    setTurn(playerId) {
        for (const [id, area] of this.refs.playerAreas) {
            if (id === playerId) {
                area.area.dataset.turn = '1';
            } else {
                delete area.area.dataset.turn;
            }
        }
    }

    /**
     * 名前の行を整える（プレイヤー色のミープルの形、N / 20）。
     * @param {number} playerId
     * @param {unknown} score
     */
    renderHeader(playerId, score) {
        const refs = this.refs.playerAreas.get(playerId);
        if (refs === undefined) {
            return;
        }
        paintUiSprite(refs.meeple, meepleSprite(colorName(this.players[playerId]?.color)), this.imgUrl);
        this.setScore(playerId, score);
    }

    /**
     * 名前の行の自己構成スコア（パネルと同じ値。UxPdf A.1 のため 2 か所に出す。010 §4）。
     * @param {number} playerId
     * @param {unknown} score
     */
    setScore(playerId, score) {
        const node = this.refs.playerAreas.get(playerId)?.score;
        if (node !== undefined) {
            node.textContent = scoreLabel(score);
        }
    }

    /**
     * N21 `scoreUpdated` の `scores`。
     * @param {unknown} scores
     */
    setScores(scores) {
        for (const [playerId, total] of scoresFromArgs(scores)) {
            this.setScore(playerId, total);
        }
    }

    /**
     * 強化自我の枠・獲得した目標の欄を表示する（持つまでは `hidden`）。カードを加える直前に呼ぶ（非表示のままだと行き先の位置が取れない。010 §5.2）。
     * @param {number} playerId
     * @param {'enhEgo' | 'owned'} kind
     */
    reveal(playerId, kind) {
        const refs = this.refs.playerAreas.get(playerId);
        if (refs !== undefined) {
            (kind === 'enhEgo' ? refs.enhEgo : refs.ownedGoals).hidden = false;
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
        for (const { kind, sprite } of TOKEN_KINDS) {
            const icon = el('span', { class: 'ir-token-icon', attrs: { 'aria-hidden': 'true' } });
            paintUiSprite(icon, sprite, this.imgUrl);
            const count = el('span', { class: 'ir-token-count' });
            // 使用済みの万能移動鍵（新しい要素）には title を付けない（011 §6.5。ラベルは aria-label）
            const root = el('span', { class: 'ir-token', data: { kind }, attrs: kind === 'wildUsed' ? { role: 'img' } : tipAttrs(tokenTitle(kind)) }, [icon, count]);
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
            const chip = tokenChip(kind, tokens);
            entry.count.textContent = String(chip.count);
            entry.root.classList.toggle('ir-zero', chip.zero);
            const label = `${tokenTitle(kind)}: ${entry.count.textContent}`;
            entry.root.dataset.label = label;
            if (entry.root.getAttribute('role') !== 'button') {
                if (kind !== 'wildUsed') {
                    setTip(entry.root, label);
                }
                entry.root.setAttribute('aria-label', label);
            }
            const changed = previous !== undefined && previous[kind] !== tokens[kind];
            if (flip && changed) {
                // 反転は CSS のアニメーション（.ir-token-flip）。クラスは付け直して再生する
                entry.root.classList.toggle('ir-token-flip', false);
                entry.root.classList.toggle('ir-token-flip', this.animationsActive());
            }
        }
    }

    // ---- 手札 ----

    /** 自分の手札が上限を超えたときだけ、手札のレールのラベルの横に SVG の印と「N / 8」の警告を出す（色だけにしない。010 §4、011 §4.1） */
    refreshHandCount() {
        const mine = this.me === null ? undefined : this.stocks.get(this.me);
        const refs = this.me === null ? undefined : this.refs.playerAreas.get(this.me);
        const info = refs?.handInfo;
        if (mine?.hand === null || mine?.hand === undefined || info === null || info === undefined) {
            return;
        }
        const state = handCountState(mine.hand.getCards().length, true);
        const warning = handWarning(state.count);
        info.textContent = warning === null ? '' : `${warning.text} ${_('(over the limit)')}`;
        info.hidden = warning === null;
        if (refs?.handWarn !== null && refs?.handWarn !== undefined) {
            refs.handWarn.hidden = warning === null;
        }
        info.classList.toggle('ir-over-limit', state.over);
        if (warning !== null) {
            setTip(info, _('Over the hand limit'));
            info.setAttribute('aria-label', `${_('Over the hand limit')}: ${warning.text}`);
        } else {
            setTip(info, '');
            info.removeAttribute('aria-label');
        }
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
                element.append(el('span', { class: 'ir-energy-badge', attrs: tipAttrs(_('Current composition energy')) }, [`E${card.energy}`]));
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
            if (kind === 'enhEgo') {
                this.reveal(playerId, kind);
            }
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
        this.reveal(playerId, 'owned');
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

    /**
     * 手札のカードに「自己構成するカード」の印（`ir-pending`）を付ける。前の印は外す。clearSelection で全部外れる。
     * @param {Iterable<number>} ids
     */
    markHand(ids) {
        this.unmarkHand();
        const hand = this.me === null ? null : this.stocks.get(this.me)?.hand;
        if (hand === null || hand === undefined) {
            return;
        }
        const wanted = new Set(ids);
        for (const card of hand.getCards()) {
            const element = hand.getCardElement(card);
            if (wanted.has(toInt(card.id)) && element instanceof HTMLElement) {
                element.classList.add(CLASS_PENDING);
                this.marked.push(element);
            }
        }
    }

    unmarkHand() {
        for (const element of this.marked) {
            element.classList.remove(CLASS_PENDING);
        }
        this.marked = [];
    }

    /**
     * 手札のカードのレベル（分からなければ 1）。
     * @param {number} id
     * @returns {number}
     */
    handLevel(id) {
        return this.handData.get(id)?.level ?? 1;
    }

    /** 状態を出るとき: 選択モードと見た目を全部掃除する */
    clearSelection() {
        this.selection.clear();
        this.unmarkHand();
        this.clearTokenPicks();
        this.clearDiscardButton();
    }

    /**
     * 手札のレールの右端に、小さなボタン（手札を捨てて心光を得る。ターンプレイヤーだけ。010 §2.4）を出す。前のものは外す。
     * 見た目は控えめな角丸 2px の形。BGA 標準のボタン（`destination`）への置き換えは U-19（011 §8）。
     * @param {{ label: string, tooltip: string, onClick: () => void }} options
     */
    showDiscardButton({ label, tooltip, onClick }) {
        this.clearDiscardButton();
        const actions = this.me === null ? null : this.refs.playerAreas.get(this.me)?.handActions;
        if (actions === null || actions === undefined) {
            return;
        }
        const button = el('button', { class: 'ir-zone-button', attrs: { type: 'button' } }, [label]);
        setTip(button, tooltip);
        button.addEventListener('click', onClick);
        this.discardButtonHandler = onClick;
        actions.append(button);
        this.discardButton = button;
    }

    /** レールの右端の小さなボタンを外す */
    clearDiscardButton() {
        if (this.discardButton !== null) {
            if (this.discardButtonHandler !== null) {
                this.discardButton.removeEventListener('click', this.discardButtonHandler);
            }
            this.discardButton.remove();
        }
        this.discardButton = null;
        this.discardButtonHandler = null;
    }

    // ---- トークンを押す操作（010 §2.4。心光・万能移動鍵の数量、自己構成・精神分析トークンの使用） ----

    /**
     * 自分のトークンを押せるようにする（種類ごと）。押せるもの（`canAdd`）に「選べる」の枠と「+」、選んだ個数（`count`）のバッジを出す。
     * トークンを押すと `onAdd`、バッジを押すと `onRemove`。上限に達したもの（`canAdd` が偽）は枠を出さず、押しても何も起きない。
     * 前の設定は先に外す。状態を出るときは `clearSelection` が `clearTokenPicks` を呼ぶ。
     * @param {Partial<Record<'light' | 'wildUp' | 'compose' | 'analysis', TokenPick>>} picks
     */
    pickTokens(picks) {
        this.clearTokenPicks();
        const els = this.me === null ? undefined : this.tokenEls.get(this.me);
        if (els === undefined) {
            return;
        }
        for (const [kind, pick] of Object.entries(picks)) {
            const entry = els[kind];
            if (entry === undefined || pick === undefined) {
                continue;
            }
            this.tokenPickCleanups.push(this.paintTokenPick(entry.root, kind, pick));
        }
    }

    /**
     * 1 種類のトークンを押せるようにして、元に戻す関数を返す。
     * @param {HTMLElement} root
     * @param {string} kind
     * @param {TokenPick} pick
     * @returns {() => void}
     */
    paintTokenPick(root, kind, pick) {
        /** @type {Array<[HTMLElement, string, (event: any) => void]>} */
        const listeners = [];
        /** @type {HTMLElement[]} */
        const added = [];
        const listen = (/** @type {HTMLElement} */ target, /** @type {string} */ type, /** @type {(event: any) => void} */ handler) => {
            target.addEventListener(type, handler);
            listeners.push([target, type, handler]);
        };
        const classes = selectionClasses({ selectable: pick.canAdd, selected: pick.count > 0 });
        for (const name of classes) {
            root.classList.add(name);
        }
        setTip(root, pick.tooltip);
        if (pick.canAdd) {
            root.classList.add(CLASS_TOKEN_PICK);
            root.setAttribute('role', 'button');
            root.setAttribute('tabindex', '0');
            root.setAttribute('aria-label', pick.tooltip);
            listen(root, 'click', () => pick.onAdd());
            listen(root, 'keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    pick.onAdd();
                }
            });
            if (pick.plus !== false) {
                const plus = el('span', { class: 'ir-token-plus', attrs: { 'aria-hidden': 'true' } }, [pick.cost !== null && pick.cost !== undefined && pick.cost > 1 ? `+${pick.cost}` : '+']);
                root.append(plus);
                added.push(plus);
            }
        }
        if (pick.count > 0 && pick.onRemove !== undefined) {
            const onRemove = pick.onRemove;
            const badge = el('button', { class: 'ir-token-badge', attrs: { type: 'button', ...tipAttrs(pick.removeTooltip ?? ''), 'aria-label': pick.removeTooltip ?? '' } }, [String(pick.count)]);
            listen(badge, 'click', (event) => {
                event.stopPropagation();
                onRemove();
            });
            root.append(badge);
            added.push(badge);
        }
        return () => {
            for (const [target, type, handler] of listeners) {
                target.removeEventListener(type, handler);
            }
            for (const node of added) {
                node.remove();
            }
            for (const name of [CLASS_SELECTABLE, CLASS_SELECTED, CLASS_TOKEN_PICK]) {
                root.classList.toggle(name, false);
            }
            for (const name of ['role', 'tabindex', 'aria-label']) {
                root.removeAttribute(name);
            }
            const label = root.dataset.label ?? tokenTitle(kind);
            setTip(root, label);
            root.setAttribute('aria-label', label);
        };
    }

    /** トークンを押せる設定（枠・「+」・バッジ・リスナー）を全部外す */
    clearTokenPicks() {
        for (const cleanup of this.tokenPickCleanups) {
            cleanup();
        }
        this.tokenPickCleanups = [];
    }

}
