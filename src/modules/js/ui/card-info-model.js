/**
 * ツールチップ・拡大表示・想起中の案内パネルの中身（仕様書 009 §7.4、§8.4）。スプライトのキー（`M014`・`Rev1B`・`F03`・`R2`・`I05`・`E01`・`O003`）から、
 * 見出し・数値の行・本文の「表示モデル」を作る純粋関数。DOM にも生成物 `ui/sprites.js` にも依存しない（Node のテストから読み込める）。
 * 数値・識別子は gamedatas の `catalog`（PHP の素材）から、英語の文字列は `texts.js` から取る。ラベルは `_()` で翻訳する（呼ぶのは setup 以降）。
 * 文字列の中のアイコンは波括弧の記法（`ui/icons.js`）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { toInt } from '../board/view-model.js';

/**
 * @typedef {object} Catalog gamedatas.catalog（PublicView::catalog）の必要な部分
 * @property {{ type?: number, id?: string, level?: number, energy?: number, keys?: string[], back?: string, score?: number, effectKind?: string, recall?: string }[]} [memory]
 * @property {{ code?: string, level?: number, bonus?: string }[]} [floor]
 * @property {{ id?: string, tier?: number, cost?: Record<string, number> | unknown[], hasLight?: boolean }[]} [spirit]
 * @property {{ id?: string, kind?: string, effectKind?: string | null }[]} [ego]
 * @property {{ code?: string, scoreTable?: number[][] }[]} [objective]
 */

/**
 * @typedef {object} CatalogIndex
 * @property {Map<string, NonNullable<Catalog['memory']>[number]>} memory `M014` → 定義
 * @property {Map<string, NonNullable<Catalog['floor']>[number]>} floor `F03` → 定義
 * @property {Map<string, NonNullable<Catalog['spirit']>[number]>} spirit `R2` → 定義
 * @property {Map<string, NonNullable<Catalog['ego']>[number]>} ego `I05` → 定義
 * @property {Map<string, NonNullable<Catalog['objective']>[number]>} objective `O003` → 定義
 */

/**
 * @typedef {object} InfoSection
 * @property {string} [label]
 * @property {string} text アイコン記法つきの英語
 */

/**
 * @typedef {object} InfoModel
 * @property {string} title 見出し（例: No.1 Abandoned Idea）
 * @property {string} [subtitle] Level 1 など
 * @property {string[]} stats 数値・アイコンの行（アイコン記法つき）
 * @property {InfoSection[]} sections 本文
 * @property {boolean} landscape 横長のカードか（拡大画像の幅と比率を決める）
 */

/**
 * @typedef {object} InfoContext
 * @property {import('./texts.js').CardTexts} texts
 * @property {CatalogIndex} catalog
 * @property {(key: string) => { x: number, points: number } | undefined} [objectiveStatus] 自分の目標の現在値
 * @property {(id: string) => boolean | undefined} [spiritHasLight] 精神カードの上に今、心光があるか
 * @property {string | null} [roomLock] 部屋の裏向きの想い出カードの裏面のキー（`Rev1B`）。床カードの説明に足す
 */

/**
 * `${name}` を値に置き換える（翻訳した文字列の語順を保つため、断片をつなげずにテンプレートごと翻訳する）。
 * @param {string} template
 * @param {Record<string, string | number>} vars
 * @returns {string}
 */
export function fmt(template, vars) {
    return template.replace(/\$\{(\w+)\}/g, (whole, name) => (Object.hasOwn(vars, name) ? String(vars[name]) : whole));
}

/**
 * gamedatas.catalog を ID で引ける形にする。欠けていても空の表を返す。
 * @param {Catalog | null | undefined} catalog
 * @returns {CatalogIndex}
 */
export function indexCatalog(catalog) {
    /** @type {CatalogIndex} */
    const index = { memory: new Map(), floor: new Map(), spirit: new Map(), ego: new Map(), objective: new Map() };
    for (const def of Array.isArray(catalog?.memory) ? catalog.memory : []) {
        if (typeof def?.id === 'string') {
            index.memory.set(def.id, def);
        }
    }
    for (const def of Array.isArray(catalog?.floor) ? catalog.floor : []) {
        if (typeof def?.code === 'string') {
            index.floor.set(def.code, def);
        }
    }
    for (const def of Array.isArray(catalog?.spirit) ? catalog.spirit : []) {
        if (typeof def?.id === 'string') {
            index.spirit.set(def.id, def);
        }
    }
    for (const def of Array.isArray(catalog?.ego) ? catalog.ego : []) {
        if (typeof def?.id === 'string') {
            index.ego.set(def.id, def);
        }
    }
    for (const def of Array.isArray(catalog?.objective) ? catalog.objective : []) {
        if (typeof def?.code === 'string') {
            index.objective.set(def.code, def);
        }
    }
    return index;
}

/** @typedef {'memory' | 'back' | 'floor' | 'spirit' | 'ego' | 'objective'} KeyKind */

/**
 * スプライトのキーの種類。
 * @param {string} key
 * @returns {KeyKind | null}
 */
export function keyKind(key) {
    if (/^M\d{3}$/.test(key)) {
        return 'memory';
    }
    if (/^Rev[12][BFH]$/.test(key)) {
        return 'back';
    }
    if (/^F\d{2}$/.test(key)) {
        return 'floor';
    }
    if (/^R[1-5][ABC]?$/.test(key)) {
        return 'spirit';
    }
    if (/^[IE]\d{2}$/.test(key)) {
        return 'ego';
    }
    if (/^O\d{3}$/.test(key)) {
        return 'objective';
    }
    return null;
}

/**
 * 横長のカードか（想い出カードとその裏面以外）。
 * @param {string} key
 * @returns {boolean}
 */
export function isLandscapeKey(key) {
    const kind = keyKind(key);
    return kind !== null && kind !== 'memory' && kind !== 'back';
}

/**
 * @param {string} key `M014`
 * @param {string} name
 * @returns {string} 「No.14 Fate of Wisdom」
 */
function memoryTitle(key, name) {
    return fmt(_('No.${number} ${name}'), { number: toInt(key.slice(1)), name });
}

/** @param {'WISDOM' | 'WILL' | 'EMOTION' | string} type */
function keyIcon(type) {
    return { WISDOM: '{wisdom}', WILL: '{will}', EMOTION: '{emotion}', WILD: '{wild}' }[type] ?? '';
}

/** @param {'B' | 'F' | 'H' | string} letter 裏面の錠前の種類 */
function lockType(letter) {
    return { B: 'WISDOM', F: 'WILL', H: 'EMOTION' }[letter] ?? 'WISDOM';
}

/** @param {string} type */
function lockIcon(type) {
    return { WISDOM: '{lock_wisdom}', WILL: '{lock_will}', EMOTION: '{lock_emotion}' }[type] ?? '';
}

/** @param {string} type */
function keyName(type) {
    switch (type) {
        case 'WILL': return _('Will');
        case 'EMOTION': return _('Emotion');
        case 'WILD': return _('Wild');
        default: return _('Wisdom');
    }
}

/**
 * 裏面のキー（`Rev1B` または `1B`）の錠前の説明。
 * @param {string} backKey
 * @returns {{ level: number, count: number, type: string, icons: string } | null}
 */
export function describeLock(backKey) {
    const match = /^(?:Rev)?([12])([BFH])$/.exec(backKey);
    if (match === null) {
        return null;
    }
    const level = toInt(match[1]);
    const type = lockType(match[2]);
    return { level, count: level, type, icons: lockIcon(type).repeat(level) };
}

/**
 * 錠前の本文（「Move cost: 2 × Wisdom key」）。
 * @param {NonNullable<ReturnType<typeof describeLock>>} lock
 * @returns {string}
 */
function lockCostText(lock) {
    return fmt(_('Move cost: ${count} × ${key} key'), { count: lock.count, key: keyName(lock.type) });
}

/**
 * 目標カードの得点表（`[[下限, 得点], ...]`）を「1 → 1, 2–3 → 2, 4–5 → 3, 6+ → 4」の形にする。
 * @param {unknown} table
 * @returns {string}
 */
export function formatScoreTable(table) {
    const rows = (Array.isArray(table) ? table : [])
        .map((row) => [toInt(row?.[0]), toInt(row?.[1])])
        .sort((a, b) => a[0] - b[0]);
    return rows.map(([low, points], i) => {
        const next = rows[i + 1];
        let range = `${low}+`;
        if (next !== undefined) {
            range = next[0] - 1 > low ? `${low}–${next[0] - 1}` : String(low);
        }
        return `${range} → ${points}`;
    }).join(', ');
}

/**
 * 周遊ボーナスの英語（`bonus` は FloorBonus の値）。
 * @param {string | undefined} bonus
 * @returns {string | null} ボーナスが無ければ null
 */
function tourBonusText(bonus) {
    switch (bonus) {
        case 'ANALYSIS_TOKEN': return _('{token_analysis} Psychoanalysis token');
        case 'LIGHT': return _('{light} Psychic Light');
        case 'WILD_MOVE_KEY': return _('{wildkey} Omni-Move Key token');
        case 'COMPOSE_TOKEN': return _('{token_compose} Construction token');
        default: return null;
    }
}

/**
 * 精神カードのコスト（`{ WISDOM: 2 }`）→ 錠前のアイコンの並び。
 * @param {unknown} cost
 * @returns {string}
 */
function spiritCostIcons(cost) {
    let icons = '';
    if (cost !== null && typeof cost === 'object' && !Array.isArray(cost)) {
        for (const type of ['WISDOM', 'EMOTION', 'WILL']) {
            icons += lockIcon(type).repeat(toInt(/** @type {Record<string, unknown>} */ (cost)[type]));
        }
    }
    return icons;
}

/**
 * カードの下に出す短い英語名（プレイヤー設定 Card captions。009 §8.7）。裏面・不明なキーは undefined。
 * @param {string} key
 * @param {import('./texts.js').CardTexts} texts
 * @returns {string | undefined}
 */
export function captionOf(key, texts) {
    const name = texts.cards[key]?.name;
    if (name === undefined) {
        return undefined;
    }
    return keyKind(key) === 'memory' ? memoryTitle(key, name) : name;
}

/**
 * カードの表示モデル。知らないキーは null。
 * @param {string} key
 * @param {InfoContext} ctx
 * @returns {InfoModel | null}
 */
export function describeCard(key, ctx) {
    switch (keyKind(key)) {
        case 'memory': return describeMemory(key, ctx);
        case 'back': return describeBack(key);
        case 'floor': return describeFloor(key, ctx);
        case 'spirit': return describeSpirit(key, ctx);
        case 'ego': return describeEgo(key, ctx);
        case 'objective': return describeObjective(key, ctx);
        default: return null;
    }
}

/**
 * @param {string} key
 * @param {InfoContext} ctx
 * @returns {InfoModel | null}
 */
function describeMemory(key, ctx) {
    const text = ctx.texts.cards[key];
    if (text === undefined) {
        return null;
    }
    const def = ctx.catalog.memory.get(key);
    /** @type {string[]} */
    const stats = [];
    /** @type {InfoSection[]} */
    const sections = [];
    let subtitle;
    if (def !== undefined) {
        subtitle = fmt(_('Level ${level}'), { level: toInt(def.level) });
        stats.push(`${_('Energy')}: {energy} ${toInt(def.energy)}`);
        stats.push(`${_('Score')}: {score} ${toInt(def.score)}`);
        stats.push(`${_('Keys')}: ${(def.keys ?? []).map(keyIcon).join('')}`);
        const lock = describeLock(String(def.back ?? ''));
        if (lock !== null) {
            stats.push(`${_('Lock')}: ${lock.icons}`);
        }
    }
    if (text.effect !== undefined) {
        const kind = def?.effectKind;
        sections.push({ label: kind === undefined ? undefined : ctx.texts.kinds[kind], text: text.effect });
    }
    const recall = def?.recall === undefined ? undefined : ctx.texts.recall[def.recall];
    if (recall !== undefined) {
        sections.push({ label: _('Recall'), text: recall });
    }
    return { title: memoryTitle(key, text.name), subtitle, stats, sections, landscape: false };
}

/**
 * @param {string} key `Rev1B`
 * @returns {InfoModel | null}
 */
function describeBack(key) {
    const lock = describeLock(key);
    if (lock === null) {
        return null;
    }
    return {
        title: _('Face-down memory card'),
        subtitle: fmt(_('Level ${level}'), { level: lock.level }),
        stats: [`${_('Lock')}: ${lock.icons}`],
        sections: [{ text: lockCostText(lock) }],
        landscape: false,
    };
}

/**
 * @param {string} key
 * @param {InfoContext} ctx
 * @returns {InfoModel | null}
 */
function describeFloor(key, ctx) {
    const text = ctx.texts.cards[key];
    if (text === undefined) {
        return null;
    }
    const def = ctx.catalog.floor.get(key);
    /** @type {string[]} */
    const stats = [];
    /** @type {InfoSection[]} */
    const sections = [];
    const bonus = tourBonusText(def?.bonus);
    if (bonus !== null) {
        stats.push(`${_('Tour bonus')}: ${bonus}`);
    }
    if (text.action !== undefined) {
        sections.push({ label: _('Floor action'), text: text.action });
    }
    const lock = ctx.roomLock === null || ctx.roomLock === undefined ? null : describeLock(ctx.roomLock);
    if (lock !== null) {
        sections.push({ label: _('Face-down memory card'), text: `${lock.icons} ${lockCostText(lock)}`.trim() });
    }
    return {
        title: text.name,
        subtitle: def === undefined ? undefined : fmt(_('Level ${level} room'), { level: toInt(def.level) }),
        stats,
        sections,
        landscape: true,
    };
}

/**
 * @param {string} key
 * @param {InfoContext} ctx
 * @returns {InfoModel | null}
 */
function describeSpirit(key, ctx) {
    const text = ctx.texts.cards[key];
    if (text === undefined) {
        return null;
    }
    const def = ctx.catalog.spirit.get(key);
    /** @type {string[]} */
    const stats = [];
    if (def !== undefined) {
        const icons = spiritCostIcons(def.cost);
        if (icons !== '') {
            stats.push(`${_('Cost')}: ${icons}`);
        }
        if (def.hasLight === true) {
            const now = ctx.spiritHasLight?.(key) ?? true;
            stats.push(`${_('Psychic Light')}: ${now ? '{light}' : _('taken')}`);
        }
    }
    /** @type {InfoSection[]} */
    const sections = text.bonus === undefined ? [] : [{ label: _('Bonus'), text: text.bonus }];
    return { title: text.name, stats, sections, landscape: true };
}

/**
 * @param {string} key
 * @param {InfoContext} ctx
 * @returns {InfoModel | null}
 */
function describeEgo(key, ctx) {
    const text = ctx.texts.cards[key];
    if (text === undefined) {
        return null;
    }
    const def = ctx.catalog.ego.get(key);
    const enhanced = key.startsWith('E');
    const kind = def?.effectKind === undefined || def.effectKind === null ? undefined : ctx.texts.kinds[def.effectKind];
    /** @type {InfoSection[]} */
    const sections = text.effect === undefined ? [] : [{ label: kind, text: text.effect }];
    return {
        title: text.name,
        subtitle: enhanced ? _('Enhanced Ego') : _('Initial Ego'),
        stats: [],
        sections,
        landscape: true,
    };
}

/**
 * @param {string} key
 * @param {InfoContext} ctx
 * @returns {InfoModel | null}
 */
function describeObjective(key, ctx) {
    const text = ctx.texts.cards[key];
    if (text === undefined) {
        return null;
    }
    const def = ctx.catalog.objective.get(key);
    /** @type {string[]} */
    const stats = [];
    const status = ctx.objectiveStatus?.(key);
    if (status !== undefined) {
        stats.push(fmt(_('Your current X: ${x} (${points} points)'), { x: status.x, points: status.points }));
    }
    /** @type {InfoSection[]} */
    const sections = [];
    if (text.condition !== undefined) {
        sections.push({ label: _('Condition'), text: text.condition });
    }
    if (text.x !== undefined) {
        sections.push({ label: 'X', text: text.x });
    }
    const table = formatScoreTable(def?.scoreTable);
    if (table !== '') {
        sections.push({ label: _('Score (X → points)'), text: table });
    }
    return { title: text.name, subtitle: _('Goal card'), stats, sections, landscape: true };
}
