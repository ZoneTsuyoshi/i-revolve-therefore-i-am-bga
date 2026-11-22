/**
 * レイアウトの切り替え（仕様書 009 §2.1・§2.4）。`@media` ではなく、`#ir-root` の実際の幅を ResizeObserver で測り、
 * `data-layout`（xl / wide / medium / narrow。010 §3）を `#ir-root` と `<html>`（data-ir-layout）に、サイズの CSS 変数を `:root` に入れる。
 * 幅 Wc は `#ir-root` の内容の幅（左右の余白 8px を除く）、見える高さ Hv は `window.innerHeight` から `#ir-root` の上端（ページの先頭からの位置）を引いた値。
 * `:root` に入れるのは、アニメーション中のカードが `body` 直下の animation-surface へ移されても大きさが変わらないようにするため（003 §6.2）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { resolveSizes } from './layout-math.js';

const DEBOUNCE_MS = 80;

export class Layout {
    /**
     * @param {HTMLElement} root `#ir-root`
     * @param {(layout: import('./layout-math.js').LayoutName) => void} [onLayoutChange] data-layout が変わったときに呼ぶ（DOM の置き場所の切り替え）
     */
    constructor(root, onLayoutChange) {
        this.root = root;
        this.onLayoutChange = onLayoutChange;
        /** @type {import('./layout-math.js').LayoutName | null} */
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
        const rect = this.root.getBoundingClientRect();
        const cs = getComputedStyle(this.root);
        const padding = (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.paddingRight) || 0);
        const measured = Math.round((rect.width || this.root.clientWidth) - padding);
        const width = measured > 0 ? measured : this.lastWidth || window.innerWidth;
        this.lastWidth = width;
        // Hv: ページの先頭から #ir-root の上端まで（トップバー・ステータスバー）を、見える高さから引く
        const top = rect.top + window.scrollY;
        const height = Math.max(0, window.innerHeight - top);
        const sizes = resolveSizes(width, height > 0 ? height : Infinity, this.layout);
        const style = document.documentElement.style;
        style.setProperty('--ir-root-w', `${sizes.rootW}px`);
        style.setProperty('--ir-gap', `${sizes.gap}px`);
        style.setProperty('--ir-room-w', `${sizes.roomW}px`);
        style.setProperty('--ir-w', `${sizes.cardW}px`);
        // narrow の山札・捨札は環の中央に 2 × 2 で収まる 0.7 W。それ以外は標準カード幅
        style.setProperty('--ir-wd', `${sizes.layout === 'narrow' ? Math.floor(0.7 * sizes.roomW) : sizes.cardW}px`);
        style.setProperty('--ir-wp', `${sizes.playerCardW}px`);
        style.setProperty('--ir-c', `${sizes.landW}px`);
        style.setProperty('--ir-room-card-w', `${sizes.roomCardW}px`);
        this.root.dataset.layout = sizes.layout;
        document.documentElement.dataset.irLayout = sizes.layout;
        if (this.layout !== sizes.layout) {
            this.layout = sizes.layout;
            this.onLayoutChange?.(sizes.layout);
        }
    }
}
