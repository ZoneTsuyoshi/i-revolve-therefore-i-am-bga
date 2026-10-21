/**
 * 英語テキストの中のアイコン記法（仕様書 009 §8.3）。`{light}` のような波括弧を、UI スプライトの小さなアイコンに置き換える。
 * 文字列は textContent で入れる（HTML として解釈しない）ので、カード名などに `<` が含まれても壊れない。
 * `{spirit}`・`{floor}` は原本アセットに画像が無いので、インラインの小さな svg（四角の中に S・F）で描く。
 * アイコン名の表と分割（`tokenizeText`）は DOM に依存しない `icon-names.js` にある。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { paintUiSprite } from '../cards/card-face.js';
import { ICONS, tokenizeText } from './icon-names.js';

/**
 * アイコンの読み上げ名（翻訳済み。`_()` は setup 以降に呼ぶ。抽出のため `_()` のリテラルで書く）。表に無い名前は英語の原文。
 * @param {string} name `ICONS` のキー
 * @param {string} fallback
 * @returns {string}
 */
function iconLabel(name, fallback) {
    switch (name) {
        case 'light': return _('Psychic Light');
        case 'draw': return _('Draw');
        case 'open': return _('Open card');
        case 'energy': return _('Energy');
        case 'wisdom': return _('Wisdom key');
        case 'will': return _('Will key');
        case 'emotion': return _('Emotion key');
        case 'wild': return _('Wild key');
        case 'wildkey': return _('Omni-Move Key token');
        case 'spirit': return _('Mind Track card');
        case 'floor': return _('Floor card');
        case 'score': return _('Construction Score');
        case 'lock_wisdom': return _('Wisdom lock');
        case 'lock_will': return _('Will lock');
        case 'lock_emotion': return _('Emotion lock');
        case 'token_compose': return _('Construction token');
        case 'token_analysis': return _('Psychoanalysis token');
        default: return fallback;
    }
}

/**
 * アイコン 1 つの要素。画像があるものは UI スプライト、無いもの（精神カード・床カード）はインラインの svg。
 * @param {string} name `ICONS` のキー
 * @param {import('../cards/card-face.js').ImgUrl} imgUrl
 * @returns {HTMLElement}
 */
export function createIcon(name, imgUrl) {
    const def = ICONS[name];
    const span = document.createElement('span');
    span.className = `ir-i ir-i-${name.replaceAll('_', '-')}`;
    span.setAttribute('role', 'img');
    span.setAttribute('aria-label', iconLabel(name, def.label));
    if (def.sprite !== undefined) {
        paintUiSprite(span, def.sprite, imgUrl);
    } else {
        span.append(letterSvg(def.letter ?? '?'));
    }
    return span;
}

/**
 * 四角の中に 1 文字の小さな svg（カード画像の模倣はしない）。
 * @param {string} letter
 * @returns {SVGSVGElement}
 */
function letterSvg(letter) {
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 16 16');
    svg.setAttribute('aria-hidden', 'true');
    const rect = document.createElementNS(ns, 'rect');
    rect.setAttribute('x', '1');
    rect.setAttribute('y', '1');
    rect.setAttribute('width', '14');
    rect.setAttribute('height', '14');
    rect.setAttribute('rx', '2');
    rect.setAttribute('fill', 'none');
    rect.setAttribute('stroke', 'currentColor');
    rect.setAttribute('stroke-width', '1.5');
    const text = document.createElementNS(ns, 'text');
    text.setAttribute('x', '8');
    text.setAttribute('y', '12');
    text.setAttribute('text-anchor', 'middle');
    text.setAttribute('font-size', '11');
    text.setAttribute('font-weight', 'bold');
    text.setAttribute('fill', 'currentColor');
    text.textContent = letter;
    svg.append(rect, text);
    return svg;
}

/**
 * 英語の文字列を、アイコン入りの DocumentFragment にする。
 * @param {string} str
 * @param {import('../cards/card-face.js').ImgUrl} imgUrl
 * @returns {DocumentFragment}
 */
export function renderText(str, imgUrl) {
    const fragment = document.createDocumentFragment();
    for (const part of tokenizeText(str)) {
        fragment.append('icon' in part ? createIcon(part.icon, imgUrl) : document.createTextNode(part.text));
    }
    return fragment;
}
