/**
 * 想起中の案内パネル（仕様書 009 §3.9。011 §3.1 のとおり U-19 で廃止する。それまでは動くまま残す）。
 * 想起フェイズ中、環の上（`#ir-recall-panel`）に、部屋のカードの拡大画像と英語の想起効果を出す。
 * カードのツールチップ・拡大表示は `Tips.js`（011 §6）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { paintCardFace } from '../cards/card-face.js';
import { describeCard, indexCatalog } from './card-info-model.js';
import { buildInfo } from './tip-content.js';

/**
 * @typedef {object} RecallPanelDeps
 * @property {HTMLElement} recallPanel `#ir-recall-panel`
 * @property {import('./texts.js').CardTexts} texts
 * @property {import('./card-info-model.js').Catalog | null | undefined} catalog gamedatas.catalog
 * @property {import('../cards/card-face.js').ImgUrl} imgUrl
 * @property {(playerId: number) => string | undefined} [playerName]
 */

export class RecallPanel {
    /**
     * @param {RecallPanelDeps} deps
     */
    constructor(deps) {
        this.deps = deps;
        this.catalog = indexCatalog(deps.catalog);
    }

    // ---- 想起中の案内パネル（009 §3.9） ----

    /**
     * 部屋のカードの拡大画像と英語の想起効果を、想起が終わるまで固定表示する。
     * @param {{ key: string, activators?: number[] }} p
     */
    showRecall({ key, activators = [] }) {
        const panel = this.deps.recallPanel;
        const model = describeCard(key, { texts: this.deps.texts, catalog: this.catalog });
        if (model === null) {
            this.hideRecall();
            return;
        }
        panel.replaceChildren();
        const image = document.createElement('div');
        image.className = 'ir-recall-image';
        image.dataset.irZoomKey = key;
        paintCardFace(image, key, this.deps.imgUrl, 'zoom');
        const body = document.createElement('div');
        body.className = 'ir-recall-body';
        const heading = document.createElement('div');
        heading.className = 'ir-recall-heading';
        heading.textContent = _('Recall phase');
        body.append(heading);
        // 数値の行は省いて、見出しと効果・想起効果だけを出す
        body.append(buildInfo({ ...model, stats: [] }, this.deps.imgUrl));
        const names = activators.map((id) => this.deps.playerName?.(id)).filter((n) => n !== undefined && n !== '');
        if (names.length > 0) {
            const who = document.createElement('div');
            who.className = 'ir-recall-who';
            who.textContent = `${_('Recall effect by')}: ${names.join(', ')}`;
            body.append(who);
        }
        panel.append(image, body);
        panel.hidden = false;
    }

    hideRecall() {
        const panel = this.deps.recallPanel;
        panel.hidden = true;
        panel.replaceChildren();
    }
}
