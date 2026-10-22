/**
 * カードのツールチップ・拡大表示・想起中の案内パネル（仕様書 009 §3.9、§7）。
 * 画像は日本語の印刷のままなので、英語の名前・効果文・想起効果を添える（中身は `card-info-model.js`、文字列は `texts.js`）。
 *
 * - ツールチップ: マウスを 0.4 秒乗せると、カードの横に、拡大画像と英語のテキストを出す（タッチでは出さない）。
 *   PC では、クリックでは拡大を開かない。クリックは選択などの操作だけに使う（拡大が選択の邪魔をしないように）。
 * - 拡大表示: タッチ端末の 0.5 秒の長押しだけで開く（タップは選択などの操作）。拡大画像と英語テキストを並べて出す。
 *   選択モードのカードなら、拡大の中に Select ボタンを出す（009 §7.3）。
 *   `document.body` 直下に出し（CSS zoom や transform の祖先を避ける）、高さの基準は `window.innerHeight`（003 §13）。
 *   ステータスバー（`#page-title`）の下から始め、ボタンを隠さない。
 * - 想起中の案内パネル: 想起フェイズ中、環の上（`#ir-recall-panel`）に、部屋のカードの拡大画像と英語の想起効果を出す。
 * カードの特定は、DOM の `.ir-card` の中の面（`data-ir-key`。cards/card-face.js が付ける）から行う。イベントは `#ir-root` で受ける（委譲）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { paintCardFace } from '../cards/card-face.js';
import { renderText } from './icons.js';
import { describeCard, indexCatalog, isLandscapeKey, keyKind } from './card-info-model.js';
import { tooltipImageSize, tooltipPosition, zoomLayout } from './zoom-math.js';

/** マウスを乗せてからツールチップを出すまで（009 §7.1: 300〜500 ms） */
const HOVER_DELAY_MS = 400;
/** 長押しで拡大を開くまで（009 §7.1） */
const LONG_PRESS_MS = 500;
/** 長押しの間に指がこれ以上動いたらキャンセル */
const MOVE_TOLERANCE_PX = 10;
/** 選択モードのカードに付くクラス（cards/selection.js と bga-cards） */
const SELECTION_SELECTOR = '.ir-selectable, .ir-selected, .bga-cards_selectable-card, .bga-cards_selected-card';
/** 拡大を開いた直後の、指を離したときの click で閉じてしまわないための猶予（ms） */
const OPEN_GUARD_MS = 500;

/**
 * @typedef {object} Target
 * @property {string} key スプライトのキー
 * @property {HTMLElement} element カードの要素（または拡大のきっかけの要素）
 * @property {boolean} selectionMode 選択モードのカードか
 */

/**
 * @typedef {object} CardInfoDeps
 * @property {HTMLElement} root `#ir-root`
 * @property {HTMLElement} recallPanel `#ir-recall-panel`
 * @property {import('./texts.js').CardTexts} texts
 * @property {import('./card-info-model.js').Catalog | null | undefined} catalog gamedatas.catalog
 * @property {import('../cards/card-face.js').ImgUrl} imgUrl
 * @property {() => Map<string, { x: number, points: number }> | undefined} [objectiveStatus] 自分の目標の現在値
 * @property {(id: string) => boolean | undefined} [spiritHasLight]
 * @property {(playerId: number) => string | undefined} [playerName]
 */

export class CardInfo {
    /**
     * @param {CardInfoDeps} deps
     */
    constructor(deps) {
        this.deps = deps;
        this.catalog = indexCatalog(deps.catalog);
        /** @type {HTMLElement | null} */
        this.tip = null;
        /** @type {HTMLElement | null} */
        this.zoom = null;
        /** @type {number | null} */
        this.hoverTimer = null;
        /** @type {number | null} */
        this.pressTimer = null;
        /** @type {HTMLElement | null} */
        this.hoverElement = null;
        this.suppressClick = false;
        this.zoomOpenedAt = 0;
        this.pressStart = { x: 0, y: 0 };
        /** @type {(() => void) | null} */
        this.onResize = null;
    }

    /** イベントを購読する。1 回だけ呼ぶ */
    attach() {
        const { root } = this.deps;
        root.addEventListener('pointerover', (e) => this.handlePointerOver(e));
        root.addEventListener('pointerout', (e) => this.handlePointerOut(e));
        root.addEventListener('pointerdown', (e) => this.handlePointerDown(e));
        root.addEventListener('pointermove', (e) => this.handlePointerMove(e));
        root.addEventListener('pointerup', () => this.cancelPress());
        root.addEventListener('pointercancel', () => this.cancelPress());
        root.addEventListener('click', (e) => this.handleClick(e));
        // 長押しのコンテキストメニュー（Android）を、カードの上では出さない
        root.addEventListener('contextmenu', (e) => {
            if (this.targetOf(e.target) !== null && (this.pressTimer !== null || this.suppressClick)) {
                e.preventDefault();
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeZoom();
                this.hideTip();
            }
        });
        window.addEventListener('scroll', () => this.hideTip(), { passive: true });
    }

    /**
     * ログの中のカード名（`span.ir-log-card`）にも、ホバー・長押しの説明を付ける。1 回だけ呼ぶ。
     * ログは盤面の外で、後から要素が増えるので、document で受けて対象を絞る。
     */
    attachLog() {
        /** @param {(event: any) => void} handler */
        const onLogCard = (handler) => (/** @type {Event} */ event) => {
            if (event.target instanceof Element && event.target.closest('.ir-log-card') !== null) {
                handler(event);
            }
        };
        document.addEventListener('pointerover', onLogCard((e) => this.handlePointerOver(e)));
        document.addEventListener('pointerout', onLogCard((e) => this.handlePointerOut(e)));
        document.addEventListener('pointerdown', onLogCard((e) => this.handlePointerDown(e)));
        document.addEventListener('pointermove', onLogCard((e) => this.handlePointerMove(e)));
        document.addEventListener('click', onLogCard((e) => this.handleClick(e)));
        document.addEventListener('pointerup', () => this.cancelPress());
        document.addEventListener('pointercancel', () => this.cancelPress());
    }

    // ---- カードの特定 ----

    /**
     * イベントの対象から、カードのキーと要素を求める。カードでなければ null。
     * @param {EventTarget | null} node
     * @returns {Target | null}
     */
    targetOf(node) {
        if (!(node instanceof Element)) {
            return null;
        }
        const hook = node.closest('[data-ir-zoom-key]');
        // ログの中のカード名（`span.ir-log-card`）は盤面の外にある
        if (hook instanceof HTMLElement && (this.deps.root.contains(hook) || hook.classList.contains('ir-log-card'))) {
            return { key: hook.dataset.irZoomKey ?? '', element: hook, selectionMode: false };
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
        // 選べるカード自身か、選べる部屋などの中のカード
        const selectionMode = card.closest(SELECTION_SELECTOR) !== null;
        return { key, element: card, selectionMode };
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

    // ---- ホバー（マウスのみ） ----

    /** @param {PointerEvent} event */
    handlePointerOver(event) {
        if (event.pointerType !== 'mouse') {
            return;
        }
        const target = this.targetOf(event.target);
        if (target === null) {
            return;
        }
        if (this.hoverElement === target.element) {
            return;
        }
        this.hideTip();
        this.hoverElement = target.element;
        this.hoverTimer = window.setTimeout(() => {
            this.hoverTimer = null;
            if (target.element.isConnected && this.zoom === null) {
                this.showTip(target);
            }
        }, HOVER_DELAY_MS);
    }

    /** @param {PointerEvent} event */
    handlePointerOut(event) {
        if (event.pointerType !== 'mouse' || this.hoverElement === null) {
            return;
        }
        const to = event.relatedTarget;
        if (to instanceof Node && this.hoverElement.contains(to)) {
            return;
        }
        this.hideTip();
    }

    /** @param {Target} target */
    showTip(target) {
        const model = this.modelOf(target);
        if (model === null) {
            return;
        }
        this.removeTip();
        const tip = document.createElement('div');
        tip.className = 'ir-tip';
        tip.setAttribute('role', 'tooltip');
        const size = tooltipImageSize({ landscape: isLandscapeKey(target.key), vw: window.innerWidth, vh: window.innerHeight });
        const image = document.createElement('div');
        image.className = 'ir-tip-image';
        image.style.width = `${size.imageW}px`;
        image.style.height = `${size.imageH}px`;
        paintCardFace(image, target.key, this.deps.imgUrl, 'zoom');
        tip.classList.toggle('ir-tip-stacked', size.stacked);
        tip.append(image, this.buildInfo(model));
        document.body.append(tip);
        this.tip = tip;
        const position = tooltipPosition({
            card: target.element.getBoundingClientRect(),
            tipW: tip.offsetWidth,
            tipH: tip.offsetHeight,
            vw: window.innerWidth,
            vh: window.innerHeight,
        });
        tip.style.left = `${position.left}px`;
        tip.style.top = `${position.top}px`;
    }

    removeTip() {
        this.tip?.remove();
        this.tip = null;
    }

    hideTip() {
        if (this.hoverTimer !== null) {
            window.clearTimeout(this.hoverTimer);
            this.hoverTimer = null;
        }
        this.hoverElement = null;
        this.removeTip();
    }

    // ---- 長押し（タッチ） ----

    /**
     * クリックでは拡大を開かない（選択などの操作に任せる）。長押しで拡大を開いた直後の click だけ、選択として働かないよう止める。
     * @param {MouseEvent} event
     */
    handleClick(event) {
        if (this.suppressClick) {
            this.suppressClick = false;
            event.preventDefault();
            event.stopPropagation();
        }
    }

    /** @param {PointerEvent} event */
    handlePointerDown(event) {
        this.hideTip();
        if (event.pointerType === 'mouse') {
            return;
        }
        const target = this.targetOf(event.target);
        if (target === null) {
            return;
        }
        this.cancelPress();
        this.pressStart = { x: event.clientX, y: event.clientY };
        this.pressTimer = window.setTimeout(() => {
            this.pressTimer = null;
            // 長押しで開いたあとの指を離したときの click を無視する
            this.suppressClick = true;
            window.setTimeout(() => { this.suppressClick = false; }, 600);
            this.openZoom(target);
        }, LONG_PRESS_MS);
    }

    /** @param {PointerEvent} event */
    handlePointerMove(event) {
        if (this.pressTimer !== null && Math.hypot(event.clientX - this.pressStart.x, event.clientY - this.pressStart.y) > MOVE_TOLERANCE_PX) {
            this.cancelPress();
        }
    }

    cancelPress() {
        if (this.pressTimer !== null) {
            window.clearTimeout(this.pressTimer);
            this.pressTimer = null;
        }
    }

    // ---- 拡大表示 ----

    /**
     * 拡大画像と英語テキストを並べて出す。
     * @param {Target} target
     */
    openZoom(target) {
        const model = this.modelOf(target);
        this.hideTip();
        if (model === null) {
            return;
        }
        this.closeZoom();
        const overlay = document.createElement('div');
        overlay.className = 'ir-zoom';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', model.title);
        const dialog = document.createElement('div');
        dialog.className = 'ir-zoom-dialog';
        const image = document.createElement('div');
        image.className = 'ir-zoom-image';
        paintCardFace(image, target.key, this.deps.imgUrl, 'zoom');
        const side = document.createElement('div');
        side.className = 'ir-zoom-text';
        // 英語が長いときは、この欄の中だけスクロールさせる（ボタンは下に固定）
        const scroll = document.createElement('div');
        scroll.className = 'ir-zoom-scroll';
        scroll.append(this.buildInfo(model));
        side.append(scroll);
        const actions = document.createElement('div');
        actions.className = 'ir-zoom-actions';
        if (target.selectionMode) {
            const select = document.createElement('button');
            select.type = 'button';
            select.className = 'ir-zoom-select';
            select.textContent = _('Select');
            select.addEventListener('click', () => {
                this.closeZoom();
                // bga-cards の選択（カード要素のクリック）をそのまま呼ぶ。このクラスの click は選択モードのカードを無視する
                target.element.click();
            });
            actions.append(select);
        }
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'ir-zoom-close';
        close.textContent = _('Close');
        close.addEventListener('click', () => this.closeZoom());
        actions.append(close);
        side.append(actions);
        dialog.append(image, side);
        overlay.append(dialog);
        // 外側のクリック（タップ）で閉じる
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay && performance.now() - this.zoomOpenedAt > OPEN_GUARD_MS) {
                this.closeZoom();
            }
        });
        document.body.append(overlay);
        this.zoom = overlay;
        this.zoomOpenedAt = performance.now();
        const layout = () => this.layoutZoom(overlay, dialog, image, side, isLandscapeKey(target.key));
        layout();
        this.onResize = layout;
        window.addEventListener('resize', layout);
        close.focus({ preventScroll: true });
    }

    /**
     * 寸法を window.innerHeight から決める。
     * @param {HTMLElement} overlay
     * @param {HTMLElement} dialog
     * @param {HTMLElement} image
     * @param {HTMLElement} side
     * @param {boolean} landscape
     */
    layoutZoom(overlay, dialog, image, side, landscape) {
        const title = document.getElementById('page-title');
        const titleBottom = title === null ? 0 : title.getBoundingClientRect().bottom;
        // ステータスバーが画面の上端に貼り付いているときだけ、その下から始める
        const top = titleBottom > 0 && titleBottom < window.innerHeight / 2 ? Math.ceil(titleBottom) : 0;
        const vh = window.innerHeight;
        const layout = zoomLayout({ vw: window.innerWidth, vh, top, landscape });
        overlay.style.top = `${top}px`;
        overlay.style.height = `${vh - top}px`;
        dialog.style.maxHeight = `${layout.dialogMaxH}px`;
        dialog.classList.toggle('ir-zoom-stacked', layout.stacked);
        image.style.width = `${layout.imageW}px`;
        image.style.height = `${layout.imageH}px`;
        side.style.maxHeight = `${layout.textMaxH}px`;
    }

    closeZoom() {
        if (this.onResize !== null) {
            window.removeEventListener('resize', this.onResize);
            this.onResize = null;
        }
        this.zoom?.remove();
        this.zoom = null;
    }

    // ---- 中身 ----

    /**
     * 表示モデルを DOM にする。
     * @param {import('./card-info-model.js').InfoModel} model
     * @returns {HTMLElement}
     */
    buildInfo(model) {
        const { imgUrl } = this.deps;
        const box = document.createElement('div');
        box.className = 'ir-info';
        const title = document.createElement('div');
        title.className = 'ir-info-title';
        title.textContent = model.title;
        box.append(title);
        if (model.subtitle !== undefined) {
            const sub = document.createElement('div');
            sub.className = 'ir-info-sub';
            sub.textContent = model.subtitle;
            box.append(sub);
        }
        for (const line of model.stats) {
            const row = document.createElement('div');
            row.className = 'ir-info-stat';
            row.append(renderText(line, imgUrl));
            box.append(row);
        }
        for (const section of model.sections) {
            const row = document.createElement('div');
            row.className = 'ir-info-section';
            if (section.label !== undefined) {
                const label = document.createElement('span');
                label.className = 'ir-info-label';
                label.textContent = `${section.label}: `;
                row.append(label);
            }
            row.append(renderText(section.text, imgUrl));
            box.append(row);
        }
        return box;
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
        body.append(this.buildInfo({ ...model, stats: [] }));
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
