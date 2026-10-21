/**
 * カードの選択の基盤（仕様書 009 §4.3、003 §6.2・§13、姉妹 MJ #46）。
 * 選べる・選択中の見た目のクラス（`ir-selectable`・`ir-selected`）は `paintSelection` の 1 か所で決め、
 * 状態を出るとき `SelectionController.clear()` で全体を掃除する。選べないカードは暗くしない（クラスを付けない）。
 * 実際の自己構成・支払いの UI（I-07 の後続）は、手札・自己構成済みの Stock に対して `start` を呼び、`clear` を状態の離脱で呼ぶ。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** 選べる印の枠 */
export const CLASS_SELECTABLE = 'ir-selectable';
/** 選択中の印の枠 */
export const CLASS_SELECTED = 'ir-selected';

/**
 * @typedef {object} SelectionOptions
 * @property {Iterable<number | string> | null} [ids] 選べるカードの ID。null なら Stock のカードすべて
 * @property {'single' | 'multiple'} [mode] 既定は multiple
 * @property {number} [max] multiple の最大枚数（既定は無制限）
 * @property {(ids: (number | string)[]) => void} [onChange] 選択が変わるたびに、選択中の ID を渡す
 * @property {Iterable<number | string>} [selected] 最初から選択済みにするカードの ID（選べるカードの中のものだけ。画面を作り直すときに選択を引き継ぐ）
 */

/**
 * 選択の次の状態（純粋関数）。single は置き換え、multiple は切り替え（上限を超える追加は無視）。選べない ID は無視。
 * @param {(number | string)[]} current 今の選択
 * @param {number | string} id クリックされたカード
 * @param {{ mode: 'single' | 'multiple', max?: number, selectable?: (id: number | string) => boolean }} rule
 * @returns {(number | string)[]}
 */
export function nextSelection(current, id, { mode, max = Infinity, selectable = () => true }) {
  if (!selectable(id)) {
    return current;
  }
  const has = current.some((c) => String(c) === String(id));
  if (mode === 'single') {
    return has ? [] : [id];
  }
  if (has) {
    return current.filter((c) => String(c) !== String(id));
  }
  return current.length >= max ? current : [...current, id];
}

/**
 * 見た目のクラスを決める（純粋関数）。選択中は選択中の印だけ（選べる印と重ねない）。
 * @param {{ selectable: boolean, selected: boolean }} state
 * @returns {string[]}
 */
export function selectionClasses({ selectable, selected }) {
  if (selected) {
    return [CLASS_SELECTED];
  }
  return selectable ? [CLASS_SELECTABLE] : [];
}

/**
 * 1 枚のカード要素に見た目のクラスを付け直す。
 * @param {HTMLElement} element
 * @param {{ selectable: boolean, selected: boolean }} state
 */
function paintElement(element, state) {
  const classes = selectionClasses(state);
  element.classList.toggle(CLASS_SELECTABLE, classes.includes(CLASS_SELECTABLE));
  element.classList.toggle(CLASS_SELECTED, classes.includes(CLASS_SELECTED));
}

/**
 * @typedef {object} Entry
 * @property {any} stock
 * @property {Set<string> | null} ids
 * @property {'single' | 'multiple'} mode
 * @property {number} max
 * @property {((ids: (number | string)[]) => void) | undefined} onChange
 */

/** 複数の Stock にまたがる選択を 1 か所で管理する */
export class SelectionController {
  constructor() {
    /** @type {Entry[]} */
    this.entries = [];
  }

  /**
   * Stock のカードを選べるようにする。同じ Stock への再呼び出しは前の設定を置き換える。
   * @param {any} stock bga-cards の Stock
   * @param {SelectionOptions} [options]
   */
  start(stock, options = {}) {
    this.entries = this.entries.filter((e) => e.stock !== stock);
    const entry = {
      stock,
      ids: options.ids === undefined || options.ids === null ? null : new Set([...options.ids].map(String)),
      mode: options.mode ?? 'multiple',
      max: options.max ?? Infinity,
      onChange: options.onChange,
    };
    this.entries.push(entry);
    stock.setSelectionMode(entry.mode, this.selectableCards(entry));
    for (const id of options.selected ?? []) {
      const card = this.selectableCards(entry).find((/** @type {{ id: number | string }} */ c) => String(c.id) === String(id));
      if (card !== undefined) {
        stock.selectCard(card, true);
      }
    }
    stock.onSelectionChange = (/** @type {{ id: number | string }[]} */ selection, /** @type {{ id: number | string } | null} */ last) => {
      if (selection.length > entry.max && last !== null && last !== undefined) {
        stock.unselectCard(last, true);
        this.paint(entry);
        return;
      }
      this.paint(entry);
      entry.onChange?.(selection.map((c) => c.id));
    };
    this.paint(entry);
  }

  /**
   * 選べるカード（bga-cards の `setSelectionMode` の第 2 引数）。
   * @param {Entry} entry
   * @returns {any[]}
   */
  selectableCards(entry) {
    return entry.stock.getCards().filter((/** @type {{ id: number | string }} */ c) => entry.ids === null || entry.ids.has(String(c.id)));
  }

  /**
   * Stock のカードの見た目を、今の選択に合わせる。
   * @param {Entry} entry
   */
  paint(entry) {
    const selected = new Set((entry.stock.getSelection?.() ?? []).map((/** @type {{ id: number | string }} */ c) => String(c.id)));
    for (const card of entry.stock.getCards()) {
      const element = entry.stock.getCardElement(card);
      if (element instanceof HTMLElement) {
        const isSelected = selected.has(String(card.id));
        paintElement(element, { selectable: entry.ids === null || entry.ids.has(String(card.id)), selected: isSelected });
      }
    }
  }

  /** カードが加わった・外れたあとに、見た目を付け直す */
  refresh() {
    for (const entry of this.entries) {
      entry.stock.setSelectionMode(entry.mode, this.selectableCards(entry));
      this.paint(entry);
    }
  }

  /**
   * 選択中の ID。
   * @param {any} stock
   * @returns {(number | string)[]}
   */
  getSelected(stock) {
    return (stock.getSelection?.() ?? []).map((/** @type {{ id: number | string }} */ c) => c.id);
  }

  /** 選択中のカードがあるか（どの Stock でも） */
  get active() {
    return this.entries.length > 0;
  }

  /** 状態を出るとき: すべての Stock の選択モード・選択・見た目のクラスを掃除する */
  clear() {
    for (const entry of this.entries) {
      const { stock } = entry;
      stock.onSelectionChange = undefined;
      stock.unselectAll?.();
      stock.setSelectionMode('none');
      for (const card of stock.getCards()) {
        const element = stock.getCardElement(card);
        if (element instanceof HTMLElement) {
          paintElement(element, { selectable: false, selected: false });
        }
      }
    }
    this.entries = [];
  }
}
