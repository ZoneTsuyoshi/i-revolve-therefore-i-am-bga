/**
 * 共通の領域に関わる通知の受け取り（仕様書 007 §10.2、009 §3）。メソッド名 `notif_<通知名>` を BGA が購読する
 * （`bga.notifications.setupPromiseNotifications({ handlers: [このクラス] })`）。args のキーは snake_case。
 * 戻り値の Promise が終わるまで次の通知は処理されない。引数の形が欠けていても例外を出さない。
 * プレイヤーの領域（手札・自己構成済み・トークンなど。U-5）にも関わる通知は、同名のメソッドを 2 つのクラスに分けられないので、
 * こちらから `PlayerNotifications` のフック（`on…`）を呼ぶ。
 * 扱う通知: N02 N03 N06 N08 N09 N12 N13 N14 N15 N22 N23 N24 N25 N26 N27 N28 N29 N32（画面を更新するもの）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { cleanupRefills, faceDownMemory, faceUpMemory, faceUpRefs, levelOfPosition, objectiveKey, objectiveStatusMap, roomsSwappedAfter, toInt } from './board/view-model.js';

/** @typedef {Record<string, any>} Args */

export class CommonNotifications {
    /**
     * @param {import('./board/CommonArea.js').CommonArea} common
     * @param {import('./player-notifications.js').PlayerNotifications | null} [players]
     */
    constructor(common, players = null) {
        this.common = common;
        /** @type {import('./player-notifications.js').PlayerNotifications | null} */
        this.players = players;
    }

    /** N02: 初期自我の一斉公開 */
    async notif_initialEgosRevealed(/** @type {Args} */ args) {
        const moves = [];
        for (const row of Array.isArray(args?.egos) ? args.egos : []) {
            moves.push(this.common.showEgo(toInt(row?.player_id), 'ego', row?.ego_id));
        }
        await Promise.all(moves);
    }

    /** N03: 人格を四隅の部屋に置く */
    async notif_personaPlaced(/** @type {Args} */ args) {
        await this.common.movePersona(toInt(args?.player_id), toInt(args?.pos));
    }

    /** N06: 1 部屋ずつの入室 */
    async notif_roomEntered(/** @type {Args} */ args) {
        await this.common.movePersona(toInt(args?.player_id), toInt(args?.pos));
    }

    /** N08: 想起効果・ワープによる移動 */
    async notif_personaMoved(/** @type {Args} */ args) {
        await this.common.movePersona(toInt(args?.player_id), toInt(args?.pos));
    }

    /** N09: 想起で部屋のカードを表向きにする */
    async notif_recallStarted(/** @type {Args} */ args) {
        if (args?.card !== undefined && args.card !== null) {
            await this.common.revealRoomCard(toInt(args.pos), args.card);
        }
    }

    /** N12: 山札から引く（山の同期と、手札・他人の手札の枚数） */
    async notif_cardsDrawn(/** @type {Args} */ args) {
        this.common.syncDecks(args?.decks);
        await this.players?.onCardsDrawn(args, this.common.deckElement(toInt(args?.level)));
    }

    /** N13: 場札を手札へ（床の下からは何も動かない） */
    async notif_cardsGained(/** @type {Args} */ args) {
        const room = args?.source === 'UNDER_FLOOR' ? this.common.refs.rooms.get(toInt(args?.pos)) : undefined;
        await this.players?.onCardsGained(args, room);
        if (args?.source === 'OPEN') {
            await this.common.removeFromOpen(args?.cards, this.common.areaOf(toInt(args?.player_id)));
        }
    }

    /** N14: 想起の獲得。部屋のカードが手札へ（部屋は空く） */
    async notif_roomCardAcquired(/** @type {Args} */ args) {
        await this.players?.onRoomCardAcquired(args);
        await this.common.removeRoomCard(toInt(args?.pos), this.common.areaOf(toInt(args?.player_id)));
    }

    /** N15: 手札から捨札へ */
    async notif_cardsDiscarded(/** @type {Args} */ args) {
        await this.common.discardCards(args?.cards, args?.pile_counts, toInt(args?.player_id));
        await this.players?.onCardsDiscarded(args);
    }

    /** N22: 目標カードの獲得。共通の場から獲得者の領域へ */
    async notif_objectiveClaimed(/** @type {Args} */ args) {
        await this.common.claimObjective(objectiveKey(args?.obj_id), toInt(args?.player_id), args?.points);
    }

    /** N23: 目標の現在の X と得点（本人だけに届く） */
    async notif_objectiveStatus(/** @type {Args} */ args) {
        this.common.setObjectiveStatus(objectiveStatusMap(args?.statuses));
    }

    /** N24: 精神分析。キューブを進め、到着した精神カードの心光を取る */
    async notif_spiritAdvanced(/** @type {Args} */ args) {
        const to = typeof args?.to_spirit === 'string' ? args.to_spirit : null;
        await this.common.advanceSpirit(toInt(args?.player_id), to, args?.light_taken === true);
    }

    /** N25: 強化自我の獲得。山の枚数を減らし、獲得者の領域へ */
    async notif_enhancedEgoObtained(/** @type {Args} */ args) {
        this.common.setEnhCount(toInt(args?.deck_count));
        await this.common.showEgo(toInt(args?.player_id), 'enhEgo', args?.ego_id, this.common.refs.enhPile);
    }

    /** N26: 手札と場札の交換（HAND_OPEN）。場札から取り、手札から出たカードを同じ枠に置く */
    async notif_cardsExchanged(/** @type {Args} */ args) {
        await this.players?.onCardsExchanged(args);
        if (args?.kind !== 'HAND_OPEN') {
            return;
        }
        const from = this.common.areaOf(toInt(args?.player_id));
        const pairs = Array.isArray(args?.pairs) ? args.pairs : [];
        await this.common.removeFromOpen(faceUpRefs(pairs.map((/** @type {Args} */ p) => p?.in)), from);
        const entries = [];
        for (const pair of pairs) {
            const out = faceUpRefs([pair?.out])[0];
            if (out !== undefined) {
                entries.push({ slot: toInt(pair?.slot), card: faceUpMemory(out) });
            }
        }
        await this.common.addToOpen(entries, from);
    }

    /** N27: I05。部屋のカードが手札へ、手札のカードが裏向きで部屋へ */
    async notif_roomCardSwappedWithHand(/** @type {Args} */ args) {
        const pos = toInt(args?.pos);
        await this.players?.onRoomCardSwappedWithHand(args);
        const area = this.common.areaOf(toInt(args?.player_id));
        await this.common.setRoomCard(pos, faceDownMemory(args?.hand_card_id, args?.back), area);
    }

    /** N28: 部屋の入れ替え（想い出カード or 床カード） */
    async notif_roomsSwapped(/** @type {Args} */ args) {
        const { kind, after } = roomsSwappedAfter(args);
        await this.common.swapRooms(kind, after);
    }

    /** N29: クリーンアップ。部屋と場札の補充（山から飛んでくる） */
    async notif_cleanup(/** @type {Args} */ args) {
        this.common.syncDecks(args?.decks);
        this.players?.onCleanup(args);
        const { rooms, open } = cleanupRefills(args);
        const moves = rooms.map((room) => this.common.setRoomCard(room.pos, room.card, this.common.deckElement(levelOfPosition(room.pos))));
        await Promise.all(moves);
        await this.common.addToOpen(open, this.common.deckElement(1));
    }

    /** N32: 山札の同期 */
    async notif_decksChanged(/** @type {Args} */ args) {
        this.common.syncDecks(args?.decks);
    }
}
