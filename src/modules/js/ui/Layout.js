/**
 * レイアウトの切り替え（仕様書 009 §2.1・§2.4）。`@media` ではなく、`#ir-root` の実際の幅を ResizeObserver で測り、
 * `data-layout`（wide / medium / narrow）を `#ir-root` に、サイズの CSS 変数を `:root` に入れる。
 * `:root` に入れるのは、アニメーション中のカードが `body` 直下の animation-surface へ移されても大きさが変わらないようにするため（003 §6.2）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { computeSizes } from './layout-math.js';

const DEBOUNCE_MS = 80;

export class Layout {
    /**
     * @param {HTMLElement} root `#ir-root`
     * @param {(layout: 'wide' | 'medium' | 'narrow') => void} [onLayoutChange] data-layout が変わったときに呼ぶ（DOM の置き場所の切り替え）
     */
    constructor(root, onLayoutChange) {
        this.root = root;
        this.onLayoutChange = onLayoutChange;
        /** @type {'wide' | 'medium' | 'narrow' | null} */
        this.layout = null;
        /** @type {number | null} */
        this.timer = null;
        this.lastWidth = 0;
        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(() => this.schedule()).observe(root);
        }
        window.addEventListener('resize', () => this.schedule());
        this.update();
    }

    schedule() {
        if (this.timer !== null) {
            clearTimeout(this.timer);
        }
        this.timer = window.setTimeout(() => {
            this.timer = null;
            this.update();
        }, DEBOUNCE_MS);
    }

    /** 幅を測り直して data-layout と CSS 変数を更新する。幅が 0（非表示中）のときは前回の値を保つ */
    update() {
        const measured = Math.round(this.root.getBoundingClientRect().width || this.root.clientWidth);
        const width = measured > 0 ? measured : this.lastWidth || window.innerWidth;
        this.lastWidth = width;
        const sizes = computeSizes(width);
        const style = document.documentElement.style;
        style.setProperty('--ir-root-w', `${sizes.rootW}px`);
        style.setProperty('--ir-gap', `${sizes.gap}px`);
        style.setProperty('--ir-room-w', `${sizes.roomW}px`);
        style.setProperty('--ir-w', `${sizes.cardW}px`);
        style.setProperty('--ir-c', `${sizes.landW}px`);
        style.setProperty('--ir-room-card-w', `${sizes.roomCardW}px`);
        this.root.dataset.layout = sizes.layout;
        if (this.layout !== sizes.layout) {
            this.layout = sizes.layout;
            this.onLayoutChange?.(sizes.layout);
        }
    }
}
