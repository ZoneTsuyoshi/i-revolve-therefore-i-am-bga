/**
 * 英語テキストの中のアイコン記法の名前と分割（仕様書 009 §8.3）。DOM にも生成物 `ui/sprites.js` にも依存しない純粋なモジュール
 * （`texts.js` の単体テストから読み込めるように、描画の `icons.js` と分けてある）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/**
 * アイコン名 → UI スプライトのキー、または文字（svg）。`label` は aria-label（英語名）。
 * 009 §8.3 の 11 種に、錠前・トークン・スコアの表示用（ツールチップの数値の行）を足している。
 * @type {Record<string, { sprite?: string, letter?: string, label: string }>}
 */
export const ICONS = {
    light: { sprite: 'token_light', label: 'Psychic Light' },
    draw: { sprite: 'icon_draw', label: 'Draw' },
    open: { sprite: 'icon_open', label: 'Open card' },
    energy: { sprite: 'icon_energy', label: 'Energy' },
    wisdom: { sprite: 'key_wisdom', label: 'Wisdom key' },
    will: { sprite: 'key_will', label: 'Will key' },
    emotion: { sprite: 'key_emotion', label: 'Emotion key' },
    wild: { sprite: 'key_wild', label: 'Wild key' },
    wildkey: { sprite: 'token_wildkey', label: 'Omni-Move Key token' },
    spirit: { letter: 'S', label: 'Mind Track card' },
    floor: { letter: 'F', label: 'Floor card' },
    // 数値の行・周遊ボーナス用
    score: { sprite: 'icon_score', label: 'Construction Score' },
    lock_wisdom: { sprite: 'lock_wisdom', label: 'Wisdom lock' },
    lock_will: { sprite: 'lock_will', label: 'Will lock' },
    lock_emotion: { sprite: 'lock_emotion', label: 'Emotion lock' },
    token_compose: { sprite: 'token_compose', label: 'Construction token' },
    token_analysis: { sprite: 'token_analysis', label: 'Psychoanalysis token' },
};

/** @typedef {{ text: string } | { icon: string }} TextPart */

/**
 * `{name}` で文字列を分ける。`ICONS` に無い名前の波括弧は、ただの文字として残す。
 * @param {string} str
 * @returns {TextPart[]}
 */
export function tokenizeText(str) {
    /** @type {TextPart[]} */
    const parts = [];
    let rest = 0;
    for (const match of str.matchAll(/\{([a-z_]+)\}/g)) {
        const name = match[1];
        if (!Object.hasOwn(ICONS, name)) {
            continue;
        }
        if (match.index > rest) {
            parts.push({ text: str.slice(rest, match.index) });
        }
        parts.push({ icon: name });
        rest = match.index + match[0].length;
    }
    if (rest < str.length) {
        parts.push({ text: str.slice(rest) });
    }
    return parts;
}

/**
 * 文字列の中の `{name}` の名前をすべて取り出す（既知かどうかは問わない。テスト用）。
 * @param {string} str
 * @returns {string[]}
 */
export function iconNamesIn(str) {
    return [...str.matchAll(/\{([^{}]*)\}/g)].map((m) => m[1]);
}
