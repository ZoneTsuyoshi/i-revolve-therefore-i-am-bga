/**
 * 支払い UI（移動・自己構成・精神分析）の共通の補助。「払うものをまとめて選び、自動で割り当て、足りない・余りを見せる」の表示まわり。
 * `_()` は setup 以降に呼ぶこと（翻訳の抽出のため、リテラルで書く）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

import { memKey } from '../board/view-model.js';

/** 支払いの src（HAND / TABLEAU） */
const HAND = 'HAND';

/**
 * 鍵の名前（翻訳済み）。
 * @param {string} type
 * @returns {string}
 */
export function keyName(type) {
  switch (type) {
    case 'WILL': return _('Will');
    case 'EMOTION': return _('Emotion');
    case 'WILD': return _('Wild');
    default: return _('Wisdom');
  }
}

/**
 * カードの表示名。分からなければ `#ID`。
 * @param {{ playerAreas: import('../board/PlayerAreas.js').PlayerAreas | null, me: number | null, texts?: import('../ui/texts.js').CardTexts | null }} deps
 * @param {string} src HAND / TABLEAU
 * @param {number} id
 * @returns {string}
 */
export function cardLabel(deps, src, id) {
  const areas = deps.playerAreas;
  /** @type {string | undefined} */
  let key;
  if (src === HAND) {
    const type = areas?.handData.get(id)?.type;
    key = type === undefined ? undefined : memKey(type);
  } else if (deps.me !== null) {
    key = areas?.stocks.get(deps.me)?.composed.getCards().find((/** @type {{ id: number }} */ c) => c.id === id)?.key;
  }
  return key === undefined ? `#${id}` : (deps.texts?.cards[key]?.name ?? key);
}

/**
 * 足りない鍵の文言（`1 × Wisdom, 2 × Will`）。
 * @param {Record<string, number>} needed 鍵の種類 → 個数
 * @returns {string}
 */
export function neededText(needed) {
  return ['WISDOM', 'WILL', 'EMOTION', 'WILD']
    .filter((type) => (needed[type] ?? 0) > 0)
    .map((type) => `${needed[type]} × ${keyName(type)}`)
    .join(', ');
}

/**
 * ステータスの文言（充足・不足・余り）を 1 つにつなぐ。空の部分は省く。
 * @param {{ paid: number, total: number, needed: string, unused: string, keys?: boolean }} parts keys: 精神分析のコストの鍵（既定は移動の錠前）
 * @returns {string}
 */
export function statusText({ paid, total, needed, unused, keys = false }) {
  const out = [(keys ? _('Keys: ${paid} / ${total} paid') : _('Locks: ${paid} / ${total} paid')).replace('${paid}', String(paid)).replace('${total}', String(total))];
  if (needed !== '') {
    out.push(_('Still needed: ${keys}').replace('${keys}', needed));
  }
  if (unused !== '') {
    out.push(_('Unused: ${items}').replace('${items}', unused));
  }
  return out.join('. ');
}

/**
 * 「−／＋」の数量ボタンの上限・下限の切り替え（純粋関数）。
 * @param {number} current
 * @param {number} delta
 * @param {number} max
 * @returns {number}
 */
export function stepQuantity(current, delta, max) {
  return Math.max(0, Math.min(max, current + delta));
}
