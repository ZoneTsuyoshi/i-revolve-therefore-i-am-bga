/**
 * 操作の見た目のクラスの付け外しと、カードの選択の基盤（仕様書 011 §5、009 §4.3、003 §6.2・§13、姉妹 MJ #46）。
 * クラスは 4 つだけ: `ir-can`（選べる）・`ir-picked`（選択中）・`ir-off`（今は選べない。暗くして、押すと理由を出す）・`ir-subject`（効果の発生源・問いの対象）。
 * カード・部屋・トークンのクラスの付け外しはこのファイルの `markElement`・`unmarkElement`・`setSubject` に集め、
 * 状態を出るとき `SelectionController.clear()`（Stock）と各状態の掃除（部屋・トークン）で必ず外す（暗くするクラスが次の状態に残らないように）。
 * 実際の自己構成・支払いの UI は、手札・自己構成済みの Stock に対して `start` を呼び、`clear` を状態の離脱で呼ぶ。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

import { Tips } from '../ui/Tips.js';

/** 選べる */
export const CLASS_CAN = 'ir-can';
/** 選択中 */
export const CLASS_PICKED = 'ir-picked';
/** 今は選べない（暗くする。押すと理由をメッセージで出す） */
export const CLASS_OFF = 'ir-off';
/** 効果の発生源・問いの対象 */
export const CLASS_SUBJECT = 'ir-subject';
/** 経路の最後の部屋（押すと 1 つ戻す。見た目はなく、押せることを示す cursor のフック。010 §2.4） */
export const CLASS_UNDO = 'ir-undo';
/** 選べない理由の文（英語。`_()` 済み）を持つ属性。無ければ一般の文を出す */
export const ATTR_WHY = 'data-ir-why';

/**
 * @typedef {object} SelectionOptions
 * @property {Iterable<number | string> | null} [ids] 選べるカードの ID。null なら Stock のカードすべて
 * @property {'single' | 'multiple'} [mode] 既定は multiple
 * @property {number} [max] multiple の最大枚数（既定は無制限）
 * @property {(ids: (number | string)[]) => void} [onChange] 選択が変わるたびに、選択中の ID を渡す
 * @property {Iterable<number | string>} [selected] 最初から選択済みにするカードの ID（選べるカードの中のものだけ。画面を作り直すときに選択を引き継ぐ）
 * @property {string} [why] 選べないカード（`ir-off`）を押したときに出す理由（翻訳済みの文）
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
 * 見た目のクラスを決める（純粋関数）。選択中は選択中の印だけ（選べる印と重ねない）。`off`（選べない）は選択中でも選べるでもないときだけ。
 * @param {{ selectable: boolean, selected: boolean, off?: boolean }} state
 * @returns {string[]}
 */
export function selectionClasses({ selectable, selected, off = false }) {
  if (selected) {
    return [CLASS_PICKED];
  }
  if (selectable) {
    return [CLASS_CAN];
  }
  return off ? [CLASS_OFF] : [];
}

/**
 * 要素の `ir-can` / `ir-picked` / `ir-off` を、状態に合わせて付け直す（カード・部屋・トークンで共通）。`ir-subject` には触らない。
 * @param {Element} element
 * @param {{ selectable?: boolean, selected?: boolean, off?: boolean, why?: string }} state
 */
export function markElement(element, { selectable = false, selected = false, off = false, why }) {
  const classes = selectionClasses({ selectable, selected, off });
  element.classList.toggle(CLASS_CAN, classes.includes(CLASS_CAN));
  element.classList.toggle(CLASS_PICKED, classes.includes(CLASS_PICKED));
  element.classList.toggle(CLASS_OFF, classes.includes(CLASS_OFF));
  if (classes.includes(CLASS_OFF) && why !== undefined && why !== '') {
    element.setAttribute(ATTR_WHY, why);
  } else {
    element.removeAttribute(ATTR_WHY);
  }
}

/**
 * `ir-can` / `ir-picked` / `ir-off` と理由の属性を全部外す（状態を出るとき）。`ir-subject` は `setSubject` で別に外す。
 * @param {Element} element
 */
export function unmarkElement(element) {
  markElement(element, {});
}

/**
 * 効果の発生源・問いの対象の印（`ir-subject`）を付ける・外す。
 * @param {Element} element
 * @param {boolean} on
 */
export function setSubject(element, on) {
  element.classList.toggle(CLASS_SUBJECT, on);
}

/**
 * 押された `ir-off` の要素から、出す理由を求める。
 * @param {Element} off
 * @param {string} fallback 理由が付いていないときの一般の文
 * @returns {string}
 */
export function whyOf(off, fallback) {
  const why = off.getAttribute(ATTR_WHY);
  return why === null || why === '' ? fallback : why;
}

/**
 * 回廊の部屋の見た目を付け直す（部屋は Stock ではないので、ここで 1 か所にまとめる）。経路・選択済みの部屋は `ir-picked` と順番（`data-order`）、
 * 選べる部屋は `ir-can`、それ以外は `ir-off`（弱く暗くする。押すと理由を出す）。`ir-undo`（経路の最後の部屋。押すと戻す）は状態が付け外しする。
 * @param {Map<number, HTMLElement>} rooms 位置 → `.ir-room`
 * @param {{ can: Iterable<number>, route?: number[], why?: (pos: number) => string | undefined }} state
 */
export function paintRooms(rooms, { can, route = [], why }) {
  const selectable = new Set(can);
  for (const [pos, element] of rooms) {
    const order = route.indexOf(pos);
    const picked = order >= 0;
    markElement(element, { selectable: selectable.has(pos), selected: picked, off: true, why: why?.(pos) });
    if (picked) {
      element.dataset.order = String(order + 1);
    } else {
      delete element.dataset.order;
    }
  }
}

/**
 * 回廊の部屋の印を全部外す（状態を出るとき・描き直しの前）。
 * @param {Map<number, HTMLElement>} rooms
 */
export function clearRooms(rooms) {
  for (const element of rooms.values()) {
    unmarkElement(element);
    element.classList.remove(CLASS_UNDO);
    delete element.dataset.order;
  }
}

/**
 * 1 枚のカード要素に見た目のクラスを付け直す。`ir-subject`（問いの対象）のカードは暗くしない。
 * @param {HTMLElement} element
 * @param {{ selectable: boolean, selected: boolean, off?: boolean, why?: string }} state
 */
function paintElement(element, state) {
  markElement(element, { ...state, off: state.off === true && !element.classList.contains(CLASS_SUBJECT) });
}

/**
 * @typedef {object} Entry
 * @property {any} stock
 * @property {Set<string> | null} ids
 * @property {'single' | 'multiple'} mode
 * @property {number} max
 * @property {string | undefined} why
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
      why: options.why,
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
        const selectable = entry.ids === null || entry.ids.has(String(card.id));
        // 選択モードの Stock の、選べないカードだけを暗くする（選択モードでない Stock には付かない）
        paintElement(element, { selectable, selected: isSelected, off: !selectable, why: entry.why });
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
    Tips.dismiss();
    for (const entry of this.entries) {
      const { stock } = entry;
      stock.onSelectionChange = undefined;
      stock.unselectAll?.();
      stock.setSelectionMode('none');
      for (const card of stock.getCards()) {
        const element = stock.getCardElement(card);
        if (element instanceof HTMLElement) {
          unmarkElement(element);
        }
      }
    }
    this.entries = [];
  }
}
