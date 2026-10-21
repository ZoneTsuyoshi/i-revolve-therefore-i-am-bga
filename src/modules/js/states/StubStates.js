/**
 * 状態機械の骨格（I-03）を Studio で回すための仮の状態クラス。
 * 初期自我のドラフト（21）・人格の配置（24）・自己構成フェイズ（60）に、操作を進めるだけのボタンを出す。
 * 本物の操作 UI（カードの選択、回廊での配置、自己構成・精神分析）は I-06・I-07 などで置き換える（移動の計画は MoveState.js）。
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

/** args からボタンを作る版（ドラフト・配置用）。ボタンの文言はカード ID・位置で、翻訳しない */
class DynamicStubState {
    /**
     * @param {Bga} bga
     * @param {(args: unknown) => StubButton[]} build
     */
    constructor(bga, build) {
        this.bga = bga;
        this.build = build;
    }

    /**
     * @param {unknown} args
     * @param {boolean} isCurrentPlayerActive
     */
    onEnteringState(args, isCurrentPlayerActive) {
        if (!isCurrentPlayerActive) {
            return;
        }
        for (const button of this.build(args)) {
            this.bga.statusBar.addActionButton(button.label, () => {
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
    // 21: 候補は本人だけに _private で届く（受け取り方が args._private か args._private[自分] かは Studio で確認する）
    bga.states.register('SetupEgoPick', new DynamicStubState(bga, (args) => {
        /** @type {any} */
        const priv = /** @type {any} */ (args)?._private ?? {};
        /** @type {string[]} */
        const candidates = priv.candidates ?? Object.values(priv)[0]?.candidates ?? [];
        return candidates.map((egoId) => ({ label: egoId, action: 'actPickInitialEgo', args: { egoId } }));
    }));
    // 24: 空いている四隅（位置 1・4・7・10）
    bga.states.register('SetupMeeplePlace', new DynamicStubState(bga, (args) => {
        /** @type {number[]} */
        const corners = /** @type {any} */ (args)?.freeCorners ?? [];
        return corners.map((pos) => ({ label: `Room ${pos}`, action: 'actPlaceMeeple', args: { pos } }));
    }));
    bga.states.register('Construct', new StubState(bga, [
        { label: 'End compose phase', action: 'actEndConstruct' },
    ]));
}
