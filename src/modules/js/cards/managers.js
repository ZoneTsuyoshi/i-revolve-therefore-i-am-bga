/**
 * bga-cards の CardManager を 2 つ作る（仕様書 009 §10.1・§13）。
 *   mem  = 縦長（想い出カード。表 M001〜、裏 Rev1B など）。要素の型は `ir-m`
 *   land = 横長（床・精神・自我・目標）。要素の型は `ir-l`
 * カードのデータは `{ id, key, backKey? }`。`key` はスプライトのキー、`backKey` は山札の天辺などで裏面を出すときの裏面のキー。
 * `isCardVisible` は常に true（裏向きは「表示そのものを裏面のスプライトにする」＝ key に Rev…・Erev などを渡す。009 §4.1）。
 * ライブラリは `importEsmLib` の結果を注入する（Game.js で読み込む）ので、このファイルは BGA 無しで読み込める。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { paintCardFace } from './card-face.js';

/**
 * @typedef {object} IrCard
 * @property {number | string} id
 * @property {string} key スプライトのキー
 * @property {string} [backKey] 裏面のキー（Deck の fakeCardGenerator などで使う）
 * @property {string} [caption] カードの下に出す英語名（プレイヤー設定 Card captions が On のときだけ見える。009 §8.7）
 */

/**
 * @typedef {object} Managers
 * @property {any} animations BgaAnimations.Manager
 * @property {any} mem 縦長
 * @property {any} land 横長
 */

/**
 * @param {object} deps
 * @param {any} deps.BgaCards `importEsmLib('bga-cards', '1.x')`
 * @param {any} deps.BgaAnimations `importEsmLib('bga-animations', '1.x')`
 * @param {import('./card-face.js').ImgUrl} deps.imgUrl
 * @param {() => boolean} deps.animationsActive
 * @param {{ memW: number, landW: number }} [deps.initial] bga-cards に渡す初期寸法（実寸は CSS 変数で上書きする）
 * @param {(key: string) => string | undefined} [deps.captionOf] スプライトのキー → カードの下に出す英語名（Card captions。ui/card-info-model.js の captionOf）
 * @returns {Managers}
 */
export function createCardManagers({ BgaCards, BgaAnimations, imgUrl, animationsActive, initial = { memW: 128, landW: 149 }, captionOf }) {
    const animations = new BgaAnimations.Manager({ animationsActive });

    /**
     * @param {'ir-m' | 'ir-l'} type
     * @param {number} width
     * @param {number} ratio 高さ / 幅
     */
    const make = (type, width, ratio) => new BgaCards.Manager({
        animationManager: animations,
        type,
        getId: (/** @type {IrCard} */ card) => card.id,
        isCardVisible: () => true,
        fakeCardGenerator: (/** @type {string} */ deckId) => ({ id: `${deckId}-top`, key: '' }),
        // 実寸は :root の CSS 変数で上書きする（irevolvethereforeiam.css）。ここは bga-cards の既定の大きさの置き換え
        cardWidth: width,
        cardHeight: Math.round(width * ratio),
        cardBorderRadius: '0',
        setupDiv: (/** @type {IrCard} */ card, /** @type {HTMLDivElement} */ div) => {
            div.classList.add('ir-card', type);
            div.dataset.irCardId = String(card.id);
            const text = card.caption ?? captionOf?.(card.key);
            if (text !== undefined) {
                const caption = document.createElement('span');
                caption.className = 'ir-caption';
                caption.textContent = text;
                div.append(caption);
            }
        },
        setupFrontDiv: (/** @type {IrCard} */ card, /** @type {HTMLDivElement} */ div) => paintCardFace(div, card.key, imgUrl),
        setupBackDiv: (/** @type {IrCard} */ card, /** @type {HTMLDivElement} */ div) => paintCardFace(div, card.backKey ?? card.key, imgUrl),
    });

    return {
        animations,
        mem: make('ir-m', initial.memW, 390 / 280),
        land: make('ir-l', initial.landW, 287 / 400),
    };
}
