/**
 * ツールチップの管理役（仕様書 011 §6。作り直し）。カードの拡大・英語の説明も、カード以外の文字だけの説明も、ここ 1 か所で出す。
 *
 * - 受け方: `document` で `pointerover` / `pointerout` / `pointerdown` / `pointerup` / `pointercancel` / `keydown` / `scroll` / `click` を受ける
 *   （部品を描き直しても結び直しが要らない。ログ・ステータスバーのカード名も同じ）。
 * - 指定: カードは `.ir-card`（面の `data-ir-key`）か `data-ir-zoom-key` / `data-ir-tip="card:<キー>"`。文字だけは `data-ir-tip="text"` + `data-ir-tip-text`。
 * - 出し方（§6.2）: マウスはホバー 400ms（選択中の要素は 700ms）。タッチは、選べない要素はタップ、選べる要素・`ir-off` は長押し 500ms。
 *   入力の種別はイベントごとの `pointerType` で判定する。外側のタップ・スクロール・Esc・状態の切り替えで閉じる。閉じるタップは下の操作を通す。
 * - 位置は `tip-placement.js` の純粋関数。`document.body` 直下に `position: fixed` で出し、ステータスバー（`#page-title`）の下に収める。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { paintCardFace } from '../cards/card-face.js';
import { describeCard, indexCatalog, isLandscapeKey, keyKind } from './card-info-model.js';
import { buildInfo } from './tip-content.js';
import { imageHeight, imageWidthRange, imageWidthSteps, placeTip } from './tip-placement.js';

/** マウスを乗せてからツールチップを出すまで（011 §6.2） */
const HOVER_DELAY_MS = 400;
/** 選択中・選べる要素の上では、選ぶ操作の邪魔をしないよう遅くする */
const HOVER_DELAY_SELECTABLE_MS = 700;
/** タッチで選べる要素を長押しして出すまで */
const LONG_PRESS_MS = 500;
/** 長押しの間に指がこれ以上動いたらキャンセル */
const MOVE_TOLERANCE_PX = 10;
/** 長押しで出した直後の click を選択として働かせない猶予（ms） */
const OPEN_GUARD_MS = 600;
/** ツールチップの最大幅 */
const MAX_WIDTH = 560;
/** 画面の端・対象との間（px） */
const MARGIN = 8;
/**
 * 「選べる要素」のクラス（1 か所。011 §5.1 の `ir-can`・`ir-picked`・`ir-off`。`ir-token-pick` は押せるトークンのフック）。
 * `ir-off`（選べない理由を出す要素）もタッチではタップを理由に使うので、長押しで出す。
 */
export const SELECTABLE_SELECTOR = ['.ir-can', '.ir-picked', '.ir-off', '.ir-token-pick'].join(', ');

/**
 * @typedef {object} Target
 * @property {'card' | 'text'} kind
 * @property {string} key カードのスプライトのキー（kind が card のとき）
 * @property {string} text 文字だけの説明（kind が text のとき）
 * @property {HTMLElement} element ツールチップの対象の要素
 */

/**
 * @typedef {object} TipsDeps
 * @property {HTMLElement} root `#ir-root`
 * @property {import('./texts.js').CardTexts} texts
 * @property {import('./card-info-model.js').Catalog | null | undefined} catalog gamedatas.catalog
 * @property {import('../cards/card-face.js').ImgUrl} imgUrl
 * @property {() => Map<string, { x: number, points: number }> | undefined} [objectiveStatus] 自分の目標の現在値
 * @property {(id: string) => boolean | undefined} [spiritHasLight]
 */

/** 今動いている管理役（`Tips.dismiss()` が使う） */
/** @type {Tips | null} */
let active = null;

export class Tips {
    /**
     * @param {TipsDeps} deps
     */
    constructor(deps) {
        this.deps = deps;
        this.catalog = indexCatalog(deps.catalog);
        /** @type {{ element: HTMLElement, tip: HTMLElement, selectable: boolean } | null} 開いているツールチップ */
        this.current = null;
        /** @type {number | null} */
        this.hoverTimer = null;
        /** @type {HTMLElement | null} */
        this.hoverElement = null;
        /** @type {{ target: Target, x: number, y: number, timer: number | null, long: boolean, moved: boolean, wasOpen: boolean, selectable: boolean } | null} タッチの途中 */
        this.press = null;
        this.suppressClickUntil = 0;
        /** @type {MutationObserver | null} */
        this.observer = null;
    }

    /** どこからでも（状態の切り替え・通知の後・選択の掃除）ツールチップを閉じる（011 §6.8） */
    static dismiss() {
        active?.dismiss();
    }

    /** イベントを購読する。1 回だけ呼ぶ */
    attach() {
        active = this;
        const on = (/** @type {string} */ type, /** @type {(e: any) => void} */ handler, /** @type {boolean} */ capture = true) => document.addEventListener(type, handler, { capture, passive: false });
        on('pointerover', (e) => this.handlePointerOver(e));
        on('pointerout', (e) => this.handlePointerOut(e));
        on('pointerdown', (e) => this.handlePointerDown(e));
        on('pointermove', (e) => this.handlePointerMove(e));
        on('pointerup', (e) => this.handlePointerUp(e));
        on('pointercancel', () => this.cancelPress());
        on('click', (e) => this.handleClick(e));
        // 長押しのコンテキストメニュー（Android）を、対象の上では出さない
        on('contextmenu', (e) => {
            if ((this.press !== null || performance.now() < this.suppressClickUntil) && this.targetOf(e.target) !== null) {
                e.preventDefault();
            }
        });
        on('keydown', (e) => {
            if (e.key === 'Escape') {
                this.dismiss();
            }
        });
        // 内側のスクロールを許したツールチップの中のスクロールでは閉じない
        on('scroll', (e) => {
            if (!(e.target instanceof Node) || this.current === null || !this.current.tip.contains(e.target)) {
                this.dismiss();
            }
        });
        window.addEventListener('resize', () => this.dismiss());
    }

    // ---- 対象の特定 ----

    /**
     * イベントの対象から、ツールチップの対象を求める。なければ null。
     * @param {EventTarget | null} node
     * @returns {Target | null}
     */
    targetOf(node) {
        if (!(node instanceof Element)) {
            return null;
        }
        const hook = node.closest('[data-ir-tip]');
        if (hook instanceof HTMLElement) {
            const spec = hook.dataset.irTip ?? '';
            if (spec === 'text') {
                const text = hook.dataset.irTipText ?? '';
                return text === '' ? null : { kind: 'text', key: '', text, element: hook };
            }
            if (spec.startsWith('card:')) {
                return { kind: 'card', key: spec.slice('card:'.length), text: '', element: hook };
            }
        }
        const zoom = node.closest('[data-ir-zoom-key]');
        // ログの中のカード名（`span.ir-log-card`）は盤面の外にある
        if (zoom instanceof HTMLElement && (this.deps.root.contains(zoom) || zoom.classList.contains('ir-log-card'))) {
            return { kind: 'card', key: zoom.dataset.irZoomKey ?? '', text: '', element: zoom };
        }
        const card = node.closest('.ir-card');
        if (!(card instanceof HTMLElement) || !this.deps.root.contains(card)) {
            return null;
        }
        // 表の面（front）を優先し、無ければ最初の面
        const face = card.querySelector('[class*="front"][data-ir-key]') ?? card.querySelector('[data-ir-key]');
        const key = face instanceof HTMLElement ? (face.dataset.irKey ?? '') : '';
        if (key === '' || keyKind(key) === null) {
            return null;
        }
        // 裏面は部屋の想い出カードだけ（山札の一番上などは対象外）
        if (keyKind(key) === 'back' && card.closest('.ir-room-card') === null) {
            return null;
        }
        return { kind: 'card', key, text: '', element: card };
    }

    /**
     * 表示モデルを作る。床カードには、同じ部屋の裏向きカードの錠前を足す。
     * @param {Target} target
     * @returns {import('./card-info-model.js').InfoModel | null}
     */
    modelOf(target) {
        let roomLock = null;
        if (keyKind(target.key) === 'floor') {
            const face = target.element.closest('.ir-room')?.querySelector('.ir-room-card [data-ir-key]');
            const backKey = face instanceof HTMLElement ? face.dataset.irKey : undefined;
            roomLock = backKey !== undefined && keyKind(backKey) === 'back' ? backKey : null;
        }
        return describeCard(target.key, {
            texts: this.deps.texts,
            catalog: this.catalog,
            roomLock,
            objectiveStatus: (key) => this.deps.objectiveStatus?.()?.get(key),
            spiritHasLight: this.deps.spiritHasLight,
        });
    }

    // ---- ホバー（マウス・ペン） ----

    /** @param {PointerEvent} event */
    handlePointerOver(event) {
        if (event.pointerType === 'touch') {
            return;
        }
        const target = this.targetOf(event.target);
        if (target === null || this.hoverElement === target.element || this.current?.element === target.element) {
            return;
        }
        this.dismiss();
        this.hoverElement = target.element;
        const selecting = target.element.closest(SELECTABLE_SELECTOR) !== null && target.element.closest('.ir-off') === null;
        this.hoverTimer = window.setTimeout(() => {
            this.hoverTimer = null;
            if (target.element.isConnected) {
                this.open(target);
            }
        }, selecting ? HOVER_DELAY_SELECTABLE_MS : HOVER_DELAY_MS);
    }

    /** @param {PointerEvent} event */
    handlePointerOut(event) {
        if (event.pointerType === 'touch' || this.hoverElement === null) {
            return;
        }
        const to = event.relatedTarget;
        if (to instanceof Node && this.hoverElement.contains(to)) {
            return;
        }
        this.dismiss();
    }

    // ---- タッチ ----

    /** @param {PointerEvent} event */
    handlePointerDown(event) {
        if (this.current !== null && event.target instanceof Node && this.current.tip.contains(event.target)) {
            return;
        }
        const wasOpen = this.current?.element ?? null;
        this.dismiss();
        if (event.pointerType !== 'touch') {
            return;
        }
        const target = this.targetOf(event.target);
        if (target === null) {
            return;
        }
        const selectable = target.element.closest(SELECTABLE_SELECTOR) !== null;
        const press = { target, x: event.clientX, y: event.clientY, timer: /** @type {number | null} */ (null), long: false, moved: false, wasOpen: wasOpen === target.element, selectable };
        if (selectable) {
            press.timer = window.setTimeout(() => {
                press.timer = null;
                press.long = true;
                // 長押しで開いたあとの指を離したときの click を、選択として働かせない
                this.suppressClickUntil = performance.now() + OPEN_GUARD_MS;
                if (target.element.isConnected) {
                    this.open(target, true);
                }
            }, LONG_PRESS_MS);
        }
        this.press = press;
    }

    /** @param {PointerEvent} event */
    handlePointerMove(event) {
        const press = this.press;
        if (press !== null && Math.hypot(event.clientX - press.x, event.clientY - press.y) > MOVE_TOLERANCE_PX) {
            press.moved = true;
            this.cancelPress(false);
        }
    }

    /** @param {PointerEvent} event */
    handlePointerUp(event) {
        const press = this.press;
        if (press === null || event.pointerType !== 'touch') {
            return;
        }
        this.press = null;
        if (press.timer !== null) {
            window.clearTimeout(press.timer);
        }
        // 選べない要素はタップで出す（もう一度タップなら閉じたままにする）。タップは下の操作にもそのまま通す
        if (!press.selectable && !press.long && !press.moved && !press.wasOpen && press.target.element.isConnected) {
            this.open(press.target, true);
        }
    }

    /** @param {boolean} [drop] 途中の記録も捨てる */
    cancelPress(drop = true) {
        const press = this.press;
        if (press === null) {
            return;
        }
        if (press.timer !== null) {
            window.clearTimeout(press.timer);
            press.timer = null;
        }
        if (drop) {
            this.press = null;
        }
    }

    /** @param {MouseEvent} event */
    handleClick(event) {
        if (performance.now() < this.suppressClickUntil) {
            this.suppressClickUntil = 0;
            event.preventDefault();
            event.stopPropagation();
        }
    }

    // ---- 開く・閉じる ----

    /**
     * ツールチップを出す。
     * @param {Target} target
     * @param {boolean} [touch] タッチで開いたか（最後の手段で内側のスクロールを許す）
     */
    open(target, touch = false) {
        const model = target.kind === 'card' ? this.modelOf(target) : null;
        if (target.kind === 'card' && model === null) {
            return;
        }
        this.removeTip();
        const tip = document.createElement('div');
        tip.className = 'ir-tip';
        tip.setAttribute('role', 'tooltip');
        tip.style.visibility = 'hidden';
        document.body.append(tip);
        const element = target.element;
        const rect = element.getBoundingClientRect();
        if (model === null) {
            const text = document.createElement('div');
            text.className = 'ir-tip-text';
            text.textContent = target.text;
            tip.append(text);
            this.arrange(tip, rect, [null], touch, () => {});
        } else {
            const landscape = isLandscapeKey(target.key);
            const tableWidth = element.classList.contains('ir-card') ? rect.width : 0;
            /** @type {Array<number | null>} */
            const widths = [...imageWidthSteps(imageWidthRange({ landscape, tableWidth })), null];
            const info = buildInfo(model, this.deps.imgUrl);
            tip.classList.toggle('ir-tip-stacked', landscape);
            this.arrange(tip, rect, widths, touch, (width) => {
                tip.replaceChildren();
                if (width !== null) {
                    const image = document.createElement('div');
                    image.className = 'ir-tip-image';
                    image.style.width = `${width}px`;
                    image.style.height = `${imageHeight(width, landscape)}px`;
                    paintCardFace(image, target.key, this.deps.imgUrl, 'zoom');
                    tip.append(image);
                }
                tip.append(info);
                tip.classList.toggle('ir-tip-no-image', width === null);
            });
        }
        tip.style.visibility = '';
        this.current = { element, tip, selectable: element.closest(SELECTABLE_SELECTOR) !== null };
        this.watch(element);
    }

    /**
     * 画像の幅の候補を大きい順に試し、画面に収まる最初の形に決めて置く（011 §6.4）。どれも収まらなければ最後（文字だけ）を、
     * 収まる大きさに縮めて置く。タッチで開いたときだけ、最後の手段として中身のスクロールを許す。
     * @param {HTMLElement} tip
     * @param {DOMRect} rect
     * @param {Array<number | null>} widths
     * @param {boolean} touch
     * @param {(width: number | null) => void} apply
     */
    arrange(tip, rect, widths, touch, apply) {
        const viewport = { width: window.innerWidth, height: window.innerHeight };
        const bar = document.getElementById('page-title')?.getBoundingClientRect().bottom ?? 0;
        // ステータスバーが画面の上に貼り付いているときだけ、その下から始める
        const top = bar > 0 && bar < viewport.height / 2 ? Math.ceil(bar) : 0;
        const maxW = Math.min(MAX_WIDTH, viewport.width - MARGIN * 2);
        /**
         * @param {number} limit
         * @returns {{ width: number, height: number }}
         */
        const measure = (limit) => {
            tip.style.width = '';
            tip.style.maxWidth = `${limit}px`;
            tip.style.maxHeight = '';
            tip.style.left = '0px';
            tip.style.top = '0px';
            // 幅は切り上げて固定する（小数のまま置くと、置いたあとの折り返しが測ったときと変わる）
            const width = Math.ceil(tip.getBoundingClientRect().width);
            tip.style.width = `${width}px`;
            return { width, height: Math.ceil(tip.getBoundingClientRect().height) };
        };
        /** @param {number} limit */
        const place = (limit) => {
            let size = measure(limit);
            let result = placeTip({ anchor: rect, size, viewport, top });
            if (result.placement === 'fit' && result.maxWidth < size.width && result.maxWidth > 0) {
                // 幅だけ足りないときは、文を折り返して縮める
                size = measure(result.maxWidth);
                result = placeTip({ anchor: rect, size, viewport, top });
            }
            return result;
        };
        /** @type {ReturnType<typeof placeTip> | null} */
        let result = null;
        for (const width of widths) {
            apply(width);
            result = place(maxW);
            // 画像と文を横に並べて、文の欄が最小幅（200px）に足りず右にはみ出すときも、次の（小さい）画像に進む
            if (result.placement !== 'fit' && tip.scrollWidth <= tip.clientWidth + 1) {
                break;
            }
        }
        if (result === null) {
            return;
        }
        if (result.placement === 'fit') {
            // 画像を出さなくても収まらない: 大きさを余白に縮める。ホバーは切り捨て、タッチは中身をスクロールさせる
            const size = measure(Math.max(1, result.maxWidth));
            tip.style.maxHeight = `${Math.floor(result.maxHeight)}px`;
            if (touch) {
                tip.classList.add('ir-tip-scroll');
            }
            const clamped = { width: size.width, height: Math.min(size.height, Math.floor(result.maxHeight)) };
            result = { ...result, ...placeTip({ anchor: rect, size: clamped, viewport, top }), placement: 'fit' };
        }
        tip.style.left = `${result.left}px`;
        tip.style.top = `${result.top}px`;
    }

    /**
     * 対象が DOM から外れた・選べるかどうかが変わったら閉じる（011 §6.8）。
     * @param {HTMLElement} element
     */
    watch(element) {
        if (typeof MutationObserver === 'undefined') {
            return;
        }
        this.observer?.disconnect();
        const selectable = element.closest(SELECTABLE_SELECTOR) !== null;
        this.observer = new MutationObserver(() => {
            if (!element.isConnected || (element.closest(SELECTABLE_SELECTOR) !== null) !== selectable) {
                this.dismiss();
            }
        });
        this.observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
    }

    removeTip() {
        this.observer?.disconnect();
        this.observer = null;
        this.current?.tip.remove();
        this.current = null;
    }

    /** 閉じる（ホバーの待ち・長押しの待ちも取り消す） */
    dismiss() {
        if (this.hoverTimer !== null) {
            window.clearTimeout(this.hoverTimer);
            this.hoverTimer = null;
        }
        this.hoverElement = null;
        this.cancelPress();
        this.removeTip();
    }
}
