/**
 * 状態クラスの仮置き（Studio の雛形にあった PlayerTurn 用）。サーバー側の状態が決まったら（I-03、U-3〜）、状態ごとのクラスに置き換える。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
export class PlaceholderState {
    /**
     * @param {unknown} game
     * @param {Bga} bga
     */
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
    }

    /**
     * @param {unknown} _args
     * @param {boolean} _isCurrentPlayerActive
     */
    onEnteringState(_args, _isCurrentPlayerActive) {
    }

    /**
     * @param {unknown} _args
     * @param {boolean} _isCurrentPlayerActive
     */
    onLeavingState(_args, _isCurrentPlayerActive) {
    }
}
