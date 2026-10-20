/**
 * プレイヤーの領域に関わる通知の受け取り（仕様書 007 §10.2、009 §3.7〜§3.11）。
 * `notif_<通知名>` を BGA が購読する。共通の領域の通知（`notifications.js`）と同名のメソッドは持てないので、
 * 手札・自己構成済み・トークン・自我・目標が変わる通知のうち共通側と重なるもの（N12 N13 N14 N15 N22 N26 N27 N29）は
 * `on…` のフックを CommonNotifications から呼ぶ。ここの `notif_…` は重ならないもの（N04 N16 N17 N18 N19 N20 N21 N35）。
 * プレイヤーパネル（U-6、`PlayerPanels`）も、手札の枚数・トークン・自己構成済み・スコアの変化をここのハンドラとフックから反映する。
 * 本人向けの値（`_private`）は `privateArg` で、合成済みの args でも `_private` の下でも読む。args が欠けていても例外を出さない。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { intList, privateArg } from './board/player-view-model.js';
import { faceUpMemory, faceUpRefs, toInt } from './board/view-model.js';

/** @typedef {Record<string, any>} Args */

export class PlayerNotifications {
    /**
     * @param {import('./board/PlayerAreas.js').PlayerAreas} areas
     * @param {import('./board/PlayerPanels.js').PlayerPanels | null} [panels]
     */
    constructor(areas, panels = null) {
        this.areas = areas;
        /** @type {import('./board/PlayerPanels.js').PlayerPanels | null} プレイヤーパネル（U-6）。更新は各ハンドラの最後にフックで反映する */
        this.panels = panels;
    }

    /**
     * 他人の手札の枚数を通知の `hand_count` に合わせる（自分の手札は Stock の枚数が正）。
     * @param {Args} args
     */
    syncHandCount(args) {
        this.areas.setHandCount(toInt(args?.player_id), args?.hand_count);
    }

    /**
     * プレイヤーパネルの手札の枚数を通知の `hand_count` に合わせる（自分も含む。無ければ何もしない）。
     * @param {Args} args
     */
    syncPanelHand(args) {
        this.panels?.setHandCount(toInt(args?.player_id), args?.hand_count);
    }

    /**
     * 本人向けのカードの列（`_private.cards` など）。
     * @param {Args} args
     * @param {string} key
     * @returns {{ id: number, type: number }[]}
     */
    privateCards(args, key = 'cards') {
        return faceUpRefs(privateArg(args, key, this.areas.me));
    }

    // ---- 共通側の通知から呼ばれるフック ----

    /**
     * N12: 山から手札へ。本人は `_private.cards` で券面が届く。
     * @param {Args} args
     * @param {HTMLElement | undefined} deck
     */
    async onCardsDrawn(args, deck) {
        this.syncPanelHand(args);
        if (this.areas.isMine(toInt(args?.player_id))) {
            await this.areas.addToHand(this.privateCards(args), deck);
        } else {
            this.syncHandCount(args);
        }
    }

    /**
     * N13: 場札・床の下から手札へ（公開された種類つき）。場札のカードは場札の Stock からそのまま移る。
     * @param {Args} args
     * @param {HTMLElement | undefined} room 床の下から取るときの部屋
     */
    async onCardsGained(args, room) {
        this.syncPanelHand(args);
        if (this.areas.isMine(toInt(args?.player_id))) {
            await this.areas.addToHand(faceUpRefs(args?.cards), room);
        } else {
            this.syncHandCount(args);
        }
    }

    /**
     * N14: 想起で部屋のカードが手札へ（部屋の Stock から移る）。
     * @param {Args} args
     */
    async onRoomCardAcquired(args) {
        this.syncPanelHand(args);
        if (this.areas.isMine(toInt(args?.player_id))) {
            await this.areas.addToHand(faceUpRefs([args?.card]));
        } else {
            this.syncHandCount(args);
        }
    }

    /**
     * N15: 手札から捨札へ（捨札の山へ移ったあとの掃除）。I08 の下のカードが捨札へ移ったときは下の欄と枚数バッジも直す。
     * @param {Args} args
     */
    async onCardsDiscarded(args) {
        this.syncPanelHand(args);
        const ids = faceUpRefs(args?.cards).map((c) => c.id);
        if (this.areas.isMine(toInt(args?.player_id))) {
            await this.areas.removeFromHand(ids);
        } else {
            this.syncHandCount(args);
        }
        if (args?.reason === 'UNDER_RELEASED') {
            this.areas.releaseUnder(ids);
        }
    }

    /**
     * N26: 手札と場札（HAND_OPEN）・手札と自己構成済み（TABLEAU_HAND）の交換。
     * HAND_OPEN は本人の手札だけ動かす（場札側は共通の領域）。TABLEAU_HAND は自己構成済みと手札の両方。
     * 手札から出るカードは、交換した自己構成済みカードの横向きの状態を引き継ぐ（008 R8-10(b)）。
     * @param {Args} args
     */
    async onCardsExchanged(args) {
        const playerId = toInt(args?.player_id);
        this.syncPanelHand(args);
        if (args?.kind === 'TABLEAU_HAND') {
            this.panels?.exchangeComposed(playerId, args?.pairs);
        }
        const pairs = Array.isArray(args?.pairs) ? args.pairs : [];
        const mine = this.areas.isMine(playerId);
        if (args?.kind === 'HAND_OPEN') {
            if (mine) {
                await this.areas.addToHand(faceUpRefs(pairs.map((/** @type {Args} */ p) => p?.in)));
            }
            return;
        }
        if (args?.kind !== 'TABLEAU_HAND') {
            return;
        }
        for (const pair of pairs) {
            const inRef = faceUpRefs([pair?.in])[0];
            const outRef = faceUpRefs([pair?.out])[0];
            const tapped = inRef !== undefined && this.areas.tapped.has(inRef.id);
            const jobs = [];
            if (inRef !== undefined) {
                const area = this.areas.refs.playerAreas.get(playerId)?.area;
                jobs.push(mine ? this.areas.addToHand([inRef]) : this.areas.removeComposed(playerId, inRef.id, area));
            }
            if (outRef !== undefined) {
                const area = this.areas.refs.playerAreas.get(playerId)?.area;
                jobs.push(this.areas.addComposed(playerId, faceUpMemory(outRef), { tapped, from: mine ? undefined : area }));
            }
            await Promise.all(jobs);
        }
        this.syncHandCount(args);
    }

    /**
     * N27: I05。部屋のカードが手札へ（本人は券面が届く）、手札のカードが部屋へ。本人の手札は共通側が部屋を動かす前に整える。
     * 私的な値の名前は 007 に明記が無いので `room_card`・`hand_card`（cardRef）と読む。
     * @param {Args} args
     */
    async onRoomCardSwappedWithHand(args) {
        if (!this.areas.isMine(toInt(args?.player_id))) {
            return;
        }
        const room = this.privateCards(args, 'room_card');
        await this.areas.removeFromHand([toInt(args?.hand_card_id)]);
        await this.areas.addToHand(room);
    }

    /**
     * N29: クリーンアップ。自己構成済みを縦に戻し、万能移動鍵を表に戻す。
     * @param {Args} args
     */
    onCleanup(args) {
        this.areas.setTapped(intList(args?.untapped), false);
        this.areas.restoreWildKeys(toInt(args?.player_id), args?.wild_restored);
        this.panels?.setTokens(toInt(args?.player_id), this.areas.tokens.get(toInt(args?.player_id)));
    }

    /**
     * N24: 精神分析で階層が変わる（プレイヤーパネルの階層）。心光は N16 が続く。
     * @param {Args} args
     */
    onSpiritAdvanced(args) {
        this.panels?.setTier(toInt(args?.player_id), args?.tier);
    }

    // ---- このクラスだけの通知 ----

    /** N04: ターンの開始。手番のプレイヤーの領域の枠を太くする */
    async notif_turnStarted(/** @type {Args} */ args) {
        this.areas.setTurn(toInt(args?.player_id));
    }

    /** N16: トークンの増減。`totals` は変更後の全値 */
    async notif_tokensChanged(/** @type {Args} */ args) {
        this.areas.setTokens(toInt(args?.player_id), args?.totals);
        this.panels?.applyTokenTotals(toInt(args?.player_id), args?.totals);
    }

    /** N17: 自己構成済みカードを横向き（使用済み）にする・戻す */
    async notif_cardsTapped(/** @type {Args} */ args) {
        this.areas.setTapped(intList(args?.card_ids), args?.tapped === true);
    }

    /** N18: 手札から自己構成済みエリアへ。他人には手札の枚数だけ */
    async notif_cardComposed(/** @type {Args} */ args) {
        const playerId = toInt(args?.player_id);
        const card = faceUpRefs([args?.card])[0];
        if (card === undefined) {
            return;
        }
        this.syncPanelHand(args);
        this.panels?.addComposed(playerId, card);
        const mine = this.areas.isMine(playerId);
        const from = mine ? undefined : this.areas.refs.playerAreas.get(playerId)?.area;
        // 本人のカードは手札の Stock から自己構成済みの Stock へ要素ごと移る
        await this.areas.addComposed(playerId, faceUpMemory(card), { from });
        if (mine) {
            await this.areas.removeFromHand([card.id]);
        } else {
            this.syncHandCount(args);
        }
    }

    /** N19: I08 の追加支払い。本人は下のカードの中身、他人は枚数だけ */
    async notif_cardsAttached(/** @type {Args} */ args) {
        const playerId = toInt(args?.player_id);
        this.syncPanelHand(args);
        await this.areas.attachUnder(playerId, toInt(args?.host_id), toInt(args?.count), this.privateCards(args));
        if (!this.areas.isMine(playerId)) {
            this.syncHandCount(args);
        }
    }

    /** N20: I04。手札から床の下へ（公開）。手札の枚数だけ変わる */
    async notif_cardsPlacedUnderFloor(/** @type {Args} */ args) {
        const playerId = toInt(args?.player_id);
        this.syncPanelHand(args);
        if (this.areas.isMine(playerId)) {
            await this.areas.removeFromHand(faceUpRefs(args?.cards).map((c) => c.id), this.areas.refs.rooms.get(toInt(args?.pos)));
        } else {
            this.syncHandCount(args);
        }
    }

    /** N35: 手札の現在のエネルギー（M098。本人だけ） */
    /** N21: 自己構成スコアの変化（プレイヤーパネルのカウンター。共通側と重ならない） */
    async notif_scoreUpdated(/** @type {Args} */ args) {
        this.panels?.setScores(args?.scores);
    }

    async notif_handEnergyUpdated(/** @type {Args} */ args) {
        this.areas.setEnergies(args?.energies);
    }
}
