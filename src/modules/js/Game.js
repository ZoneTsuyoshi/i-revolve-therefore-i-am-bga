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
import { createCardManagers } from './cards/managers.js';
import { PlaceholderState } from './states/PlaceholderState.js';
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

        // Studio の雛形の状態（サーバー側 States/PlayerTurn.php）。サーバー側の置き換え後に状態ごとのクラスへ差し替える
        this.bga.states.register('PlayerTurn', new PlaceholderState(this, bga));
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
        // 幅を測って data-layout と CSS 変数を入れ、以後は ResizeObserver で追従する。layout が変わるたびに piles と open の置き場所を替える
        this.layout = new Layout(board.root, (layout) => placeFloating(board, layout));

        this.setupNotifications();
    }

    setupNotifications() {
        // notif_xxx という名前のメソッドを通知名に対応させて自動で購読する
        this.bga.notifications.setupPromiseNotifications({});
    }
}
