/**
 * 状態機械の骨格（I-03）を Studio で回すための仮の状態クラス。
 * 移動（31 Move）と自己構成フェイズ（60 Construct）に、そのまま次へ進めるボタンだけを出す。
 * 本物の操作 UI（経路の計画、自己構成・精神分析）は U-3〜U-5 と I-06・I-07 で置き換える。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/**
 * @typedef {object} StubButton
 * @property {string} label ボタンの文言（英語原文）
 * @property {string} action サーバーのアクション名
 * @property {Record<string, unknown>} [args] アクションの引数
 */

class StubState {
    /**
     * @param {Bga} bga
     * @param {StubButton[]} buttons
     */
    constructor(bga, buttons) {
        this.bga = bga;
        this.buttons = buttons;
    }

    /**
     * @param {unknown} _args
     * @param {boolean} isCurrentPlayerActive
     */
    onEnteringState(_args, isCurrentPlayerActive) {
        if (!isCurrentPlayerActive) {
            return;
        }
        for (const button of this.buttons) {
            this.bga.statusBar.addActionButton(_(button.label), () => {
                this.bga.actions.performAction(button.action, button.args ?? {});
            });
        }
    }

    /**
     * @param {unknown} _args
     * @param {boolean} _isCurrentPlayerActive
     */
    onLeavingState(_args, _isCurrentPlayerActive) {
    }
}

/**
 * 仮の状態クラスを登録する。
 *
 * @param {Bga} bga
 */
export function registerStubStates(bga) {
    // actMove の経路は I-06 まで検証しないので、空の経路で次へ進む
    bga.states.register('Move', new StubState(bga, [
        { label: 'Move (placeholder)', action: 'actMove', args: { plan: JSON.stringify([]) } },
    ]));
    bga.states.register('Construct', new StubState(bga, [
        { label: 'End compose phase', action: 'actEndConstruct' },
    ]));
}
