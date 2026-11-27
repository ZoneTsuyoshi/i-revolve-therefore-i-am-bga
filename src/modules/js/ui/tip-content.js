/**
 * ツールチップの中身の DOM（仕様書 011 §6.1）。表示モデル（`card-info-model.js` の `describeCard`）を英語の名前・数値・効果文の部品にする。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { renderText } from './icons.js';

/**
 * 表示モデルを DOM にする。
 * @param {import('./card-info-model.js').InfoModel} model
 * @param {import('../cards/card-face.js').ImgUrl} imgUrl
 * @returns {HTMLElement}
 */
export function buildInfo(model, imgUrl) {
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
