/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * IRevolveThereforeIAm implementation : © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * クライアントの入口（仕様書 003 §6.1、009 §15）。Game.js は setup、状態クラスの登録、通知の購読だけを持つ。
 * 盤面の枠は board/、カード表示は cards/、レイアウトの計算は ui/ にある。
 */
import { buildBoard, placeFloating } from './board/Board.js';
import { CommonArea } from './board/CommonArea.js';
import { PlayerAreas } from './board/PlayerAreas.js';
import { PlayerPanels } from './board/PlayerPanels.js';
import { createCardManagers } from './cards/managers.js';
import { CommonNotifications } from './notifications.js';
import { PlayerNotifications } from './player-notifications.js';
import { registerStubStates } from './states/StubStates.js';
import { Layout } from './ui/Layout.js';
import { int } from './ui/dom.js';

const BgaAnimations = await importEsmLib('bga-animations', '1.x');
const BgaCards = await importEsmLib('bga-cards', '1.x');

export class Game {
    /**
     * @param {Bga} bga
     */
    constructor(bga) {
        this.bga = bga;
        /** @type {import('./board/Board.js').BoardRefs | null} */
        this.board = null;
        /** @type {import('./cards/managers.js').Managers | null} */
        this.cards = null;
        /** @type {Layout | null} */
        this.layout = null;
        /** @type {CommonArea | null} */
        this.common = null;
        /** @type {PlayerAreas | null} */
        this.playerAreas = null;
        /** @type {PlayerPanels | null} */
        this.panels = null;

        // Studio の雛形の状態（サーバー側 States/PlayerTurn.php）。サーバー側の置き換え後に状態ごとのクラスへ差し替える
        registerStubStates(bga);
    }

    /**
     * 画面を組み立てる。ページを開くたび（F5 を含む）に呼ばれる。gamedatas は getAllDatas の戻り値（仕様書 007 §8）。
     * 中身が無い（空の gamedatas）でも例外を出さない。
     *
     * @param {Gamedatas} gamedatas
     */
    setup(gamedatas) {
        this.gamedatas = gamedatas;
        // 拡大表示用のシートは使うまで読み込まない（009 §10.1）
        this.bga.images.dontPreloadImages([1, 2, 3].map((n) => `ir_mem_zoom_${n}.webp`).concat(['ir_land_zoom_1.webp', 'ir_land_zoom_2.webp']));

        this.cards = createCardManagers({
            BgaCards,
            BgaAnimations,
            imgUrl: (file) => this.bga.images.getImgUrl(file),
            animationsActive: () => this.bga.gameui.bgaAnimationsActive(),
        });

        const spectator = this.bga.players.isCurrentPlayerSpectator();
        const board = buildBoard(this.bga.gameArea.getElement(), {
            players: gamedatas?.players ?? {},
            playerOrder: gamedatas?.playerorder,
            me: spectator ? null : int(this.bga.players.getCurrentPlayerId()),
        });
        this.board = board;
        // プレイヤーの領域（手札・自己構成済み・自我・トークン・獲得した目標。U-5）。自我と目標は共通の領域の通知からも使う
        const playerAreas = new PlayerAreas({
            refs: board,
            cards: this.cards,
            BgaCards,
            imgUrl: (file) => this.bga.images.getImgUrl(file),
            animationsActive: () => this.bga.gameui.bgaAnimationsActive(),
            me: spectator ? null : int(this.bga.players.getCurrentPlayerId()),
        });
        this.playerAreas = playerAreas;
        // 共通の領域（回廊・場札・山札・精神トラック・強化自我・目標。U-4）。中身は gamedatas から描く
        this.common = new CommonArea({
            refs: board,
            cards: this.cards,
            BgaCards,
            imgUrl: (file) => this.bga.images.getImgUrl(file),
            animationsActive: () => this.bga.gameui.bgaAnimationsActive(),
            playerAreas,
            players: gamedatas?.players ?? {},
        });
        this.common.setup(gamedatas);
        playerAreas.setup(gamedatas);
        // プレイヤーパネルの 3 行（スコア・手札・トークン・階層・鍵。U-6）。BGA 標準の枠に足す
        this.panels = new PlayerPanels({
            getElement: (playerId) => this.bga.playerPanels.getElement(playerId),
            imgUrl: (file) => this.bga.images.getImgUrl(file),
            animationsActive: () => this.bga.gameui.bgaAnimationsActive(),
        });
        this.panels.setup(gamedatas);
        // 幅を測って data-layout と CSS 変数を入れ、以後は ResizeObserver で追従する。layout が変わるたびに piles と open の置き場所を替える
        this.layout = new Layout(board.root, (layout) => placeFloating(board, layout));

        this.setupNotifications();
    }

    setupNotifications() {
        // notif_xxx という名前のメソッドを通知名に対応させて自動で購読する（共通の領域の通知は CommonNotifications）
        const players = this.playerAreas === null ? null : new PlayerNotifications(this.playerAreas, this.panels);
        const handlers = this.common === null ? [] : [new CommonNotifications(this.common, players), ...(players === null ? [] : [players])];
        this.bga.notifications.setupPromiseNotifications({ handlers });
    }
}
