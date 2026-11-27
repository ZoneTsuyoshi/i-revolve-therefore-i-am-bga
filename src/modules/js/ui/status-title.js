/**
 * ステータスバーの文（`bga.statusBar.setTitle`）に、HTML（カード名の `span.ir-log-card`・鍵のアイコン）を渡す補助（仕様書 011 §3.2・§3.4）。
 * 鍵は `{wisdom}` などの記法で書き、`textToHtml` が `ICONS` のアイコンの HTML にする（ログの `iconHtml` と同じ形）。
 * `setTitle` の `args` が HTML をそのまま描くかは公式の文書に書かれていない【未確認】ので、`setTitleHtml` は文を出したあと、
 * 描かれずにエスケープされていたら（`&lt;span` が見えていたら）HTML に戻す。BGA が HTML を描くなら何もしない。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { createIcon } from './icons.js';
import { tokenizeText } from './icon-names.js';
import { escapeHtml } from './log-format.js';

/**
 * アイコンの HTML を返す関数（アイコン名 → HTML）。
 * @param {{ images: { getImgUrl: (file: string) => string } }} bga
 * @returns {(name: string) => string}
 */
export function iconHtmlOf(bga) {
  return (name) => createIcon(name, (file) => bga.images.getImgUrl(file)).outerHTML;
}

/**
 * `{wisdom}` を含む英語の文を、アイコン入りの HTML にする（文字の部分は HTML の特殊文字を避ける）。
 * @param {string} str
 * @param {(name: string) => string} iconHtml
 * @returns {string}
 */
export function textToHtml(str, iconHtml) {
  return tokenizeText(str).map((part) => ('icon' in part ? iconHtml(part.icon) : escapeHtml(part.text))).join('');
}

/**
 * エスケープされてしまった `span` / `img` のタグを HTML に戻す（純粋関数）。タグの外の文字・他のタグには触らない。
 * @param {string} html
 * @returns {string}
 */
export function unescapeOwnTags(html) {
  return html.replace(/&lt;(\/?(?:span|img)\b.*?)&gt;/g, (_match, inner) => `<${String(inner).replaceAll('&quot;', '"').replaceAll('&#39;', "'").replaceAll('&amp;', '&')}>`);
}

/**
 * HTML を含む引数でタイトルを出す。BGA が HTML を描かず文字にしていたときは、HTML に戻す。
 * @param {{ statusBar: { setTitle: (title: string, args?: Record<string, unknown>) => void } }} bga
 * @param {string} template 英語の文（`_()` 済み。`${you}` や `${card}` などの変数を含んでよい）
 * @param {Record<string, unknown>} [args] 文字列は HTML のまま渡してよい
 */
export function setTitleHtml(bga, template, args = {}) {
  bga.statusBar.setTitle(template, args);
  const title = typeof document.getElementById === 'function' ? (document.getElementById('pagemaintitletext') ?? document.getElementById('page-title')) : null;
  if (title !== null && title.innerHTML.includes('&lt;span')) {
    title.innerHTML = unescapeOwnTags(title.innerHTML);
  }
}
