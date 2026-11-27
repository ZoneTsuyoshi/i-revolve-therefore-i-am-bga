/**
 * 支払い UI（移動・自己構成・精神分析）の共通の補助。「払うものをまとめて選び、自動で割り当て、足りない・余りを見せる」の表示まわり。
 * `_()` は setup 以降に呼ぶこと（翻訳の抽出のため、リテラルで書く）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

import { memKey } from '../board/view-model.js';
import { escapeHtml } from '../ui/log-format.js';
import { textToHtml } from '../ui/status-title.js';
import { stepTokenCount, tokenQuantityView } from './action-bar-model.js';

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

/** 鍵の種類 → アイコン名（`ui/icon-names.js` の `ICONS`） */
const KEY_ICONS = /** @type {Record<string, string>} */ ({ WISDOM: 'wisdom', WILL: 'will', EMOTION: 'emotion', WILD: 'wild' });

/**
 * 足りない鍵の文言（`2 {wisdom} 1 {will}`。鍵はアイコンの記法で、`textToHtml` が画像にする。011 §3.4）。
 * @param {Record<string, number>} needed 鍵の種類 → 個数
 * @returns {string}
 */
export function neededText(needed) {
  return ['WISDOM', 'WILL', 'EMOTION', 'WILD']
    .filter((type) => (needed[type] ?? 0) > 0)
    .map((type) => `${needed[type]} {${KEY_ICONS[type]}}`)
    .join(' ');
}

/**
 * ステータスの文言（充足・不足・余り）を 1 つにつなぐ。空の部分は省く。ステータスバーの HTML として渡す（鍵はアイコン、文字は特殊文字を避ける）。
 * `Locks: 1 / 3. Need: 2 {wisdom}. Unused: Card A`（011 §3.4）。経路は盤面の順番の札で見えるので出さない。
 * @param {{ paid: number, total: number, needed: string, unused: string, keys?: boolean, iconHtml: (name: string) => string }} parts needed: `neededText` の記法。unused: 平文。keys: 精神分析のコストの鍵（既定は移動の錠前）
 * @returns {string}
 */
export function statusText({ paid, total, needed, unused, keys = false, iconHtml }) {
  const out = [(keys ? _('keys ${paid} / ${total}') : _('Locks: ${paid} / ${total}')).replace('${paid}', String(paid)).replace('${total}', String(total))];
  if (needed !== '') {
    out.push(_('Need: ${keys}').replace('${keys}', textToHtml(needed, iconHtml)));
  }
  if (unused !== '') {
    out.push(_('Unused: ${items}').replace('${items}', escapeHtml(unused)));
  }
  return out.join('. ');
}

/**
 * 心光・万能移動鍵の数量を、領域のトークンを押して選ぶための設定（010 §2.4）。上限に達したら押せない。
 * 次のコスト（N 回目の行使は N 個で 1 つ分）は、押せるときだけツールチップと「+N」に出す。
 * @param {{ kind: 'light' | 'wildUp', count: number, max: number, cumulative?: (uses: number) => number, onChange: (count: number) => void }} options
 * @returns {import('../board/PlayerAreas.js').TokenPick}
 */
export function quantityTokenPick({ kind, count, max, cumulative, onChange }) {
  const view = tokenQuantityView({ count, max, cumulative });
  const selected = String(count);
  let tooltip;
  if (!view.canAdd) {
    tooltip = max > 0 ? _('You have selected as many as you can use') : _('You cannot use this now');
  } else if (kind === 'light') {
    tooltip = _('Pay with Psychic Light. The next use costs ${cost}. Selected: ${count}').replace('${cost}', String(view.nextCost)).replace('${count}', selected);
  } else {
    tooltip = _('Pay 1 lock with an Omni-Move Key. Selected: ${count}').replace('${count}', selected);
  }
  return {
    canAdd: view.canAdd,
    count,
    cost: kind === 'light' ? view.nextCost : null,
    tooltip,
    removeTooltip: _('Deselect one'),
    onAdd: () => onChange(stepTokenCount(count, 1, max)),
    onRemove: () => onChange(stepTokenCount(count, -1, max)),
  };
}
