/**
 * プレイヤーパネル（BGA 標準のパネルに足す 3 行。仕様書 009 §6）。
 *   1 行目: 自己構成スコア（N / 20）・手札の枚数・心光
 *   2 行目: 万能移動鍵（表 + 使用済み）・自己構成トークン・精神分析トークン
 *   3 行目: 精神階層・鍵の本数（知恵・意志・感情・万能）
 * 値はすべて公開情報。更新は通知から `PlayerNotifications`・`CommonNotifications` のフックが呼ぶ（このクラスは通知名を知らない）。
 * 標準のスコア欄は使わない（終了時の勝者 1 / 他 0 になるため。`hideStandardScore`）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { paintUiSprite } from '../cards/card-face.js';
import { el } from '../ui/dom.js';
import {
  KEY_KINDS, applyComposedChange, clampTier, exchangeChange, keyCounts, keyTable, panelState, scoreText, scoresFromArgs, tierText, wildText,
} from './player-panel-model.js';
import { applyTotals, tokensModel } from './player-view-model.js';
import { toInt } from './view-model.js';

/**
 * @typedef {object} PanelDeps
 * @property {(playerId: number) => HTMLElement | null | undefined} getElement `bga.playerPanels.getElement`
 * @property {(file: string) => string} imgUrl
 * @property {() => boolean} animationsActive
 */

/** 値が変わったときの強調（CSS の `ir-pop`、0.3 秒）を付け直す時間 */
const POP_MS = 300;

export class PlayerPanels {
    /**
     * @param {PanelDeps} deps
     */
    constructor(deps) {
        this.deps = deps;
        /** @type {Map<number, import('./player-panel-model.js').PanelState>} */
        this.states = new Map();
        /** @type {Map<number, Record<string, HTMLElement>>} */
        this.values = new Map();
        /** @type {Map<number, string[]>} */
        this.keyTable = new Map();
    }

    /**
     * @param {Record<string, any>} gamedatas
     */
    setup(gamedatas) {
        this.keyTable = keyTable(gamedatas?.catalog);
        const playerData = gamedatas?.playerData ?? {};
        for (const playerId of Object.keys(playerData).map(toInt)) {
            this.states.set(playerId, panelState(playerData[playerId]));
            this.build(playerId);
            this.render(playerId, false);
        }
    }

    // ---- 更新（通知のフックから） ----

    /**
     * N21: 自己構成スコア。
     * @param {unknown} scores
     */
    setScores(scores) {
        for (const [playerId, total] of scoresFromArgs(scores)) {
            this.update(playerId, (s) => ({ ...s, score: total }));
        }
    }

    /**
     * 手札の枚数（各通知の `hand_count`。無ければ何もしない）。
     * @param {number} playerId
     * @param {unknown} count
     */
    setHandCount(playerId, count) {
        if (count !== undefined && count !== null) {
            this.update(playerId, (s) => ({ ...s, handCount: Math.max(0, toInt(count)) }));
        }
    }

    /**
     * N16: トークン。`totals` は変わった分だけ（snake_case）。
     * @param {number} playerId
     * @param {unknown} totals
     */
    applyTokenTotals(playerId, totals) {
        this.update(playerId, (s) => ({ ...s, tokens: applyTotals(s.tokens, totals) }));
    }

    /**
     * トークンの全値（`PlayerAreas.tokens` と同期するとき）。
     * @param {number} playerId
     * @param {unknown} tokens
     */
    setTokens(playerId, tokens) {
        this.update(playerId, (s) => ({ ...s, tokens: tokensModel(tokens) }));
    }

    /**
     * N24: 精神階層。
     * @param {number} playerId
     * @param {unknown} tier
     */
    setTier(playerId, tier) {
        if (tier !== undefined && tier !== null) {
            this.update(playerId, (s) => ({ ...s, tier: clampTier(tier) }));
        }
    }

    /**
     * N18: 自己構成済みが増える（鍵の本数が変わる）。
     * @param {number} playerId
     * @param {{ id: unknown, type: unknown } | null | undefined} card
     */
    addComposed(playerId, card) {
        if (card !== null && card !== undefined) {
            this.update(playerId, (s) => ({ ...s, composed: applyComposedChange(s.composed, { added: [card] }) }));
        }
    }

    /**
     * N26 TABLEAU_HAND: 自己構成済みのカードの入れ替え。
     * @param {number} playerId
     * @param {unknown} pairs
     */
    exchangeComposed(playerId, pairs) {
        this.update(playerId, (s) => ({ ...s, composed: applyComposedChange(s.composed, exchangeChange(pairs)) }));
    }

    /**
     * @param {number} playerId
     * @param {(s: import('./player-panel-model.js').PanelState) => import('./player-panel-model.js').PanelState} fn
     */
    update(playerId, fn) {
        const current = this.states.get(playerId);
        if (current === undefined) {
            return;
        }
        this.states.set(playerId, fn(current));
        this.render(playerId, this.deps.animationsActive());
    }

    // ---- 描画 ----

    /**
     * 3 行を作る（初回だけ）。
     * @param {number} playerId
     */
    build(playerId) {
        const host = this.deps.getElement(playerId);
        if (host === null || host === undefined || this.values.has(playerId)) {
            return;
        }
        /** @type {Record<string, HTMLElement>} */
        const values = {};
        /**
         * @param {string} name
         * @param {string} title
         * @param {string | null} sprite UI スプライトのキー（null なら iconClass の CSS 描画）
         * @param {string} [iconClass]
         */
        const item = (name, title, sprite, iconClass = '') => {
            const icon = el('span', { class: `ir-pp-icon ${iconClass}`.trim(), attrs: { 'aria-hidden': 'true' } });
            if (sprite !== null) {
                paintUiSprite(icon, sprite, this.deps.imgUrl);
            }
            const value = el('span', { class: 'ir-pp-value', data: { item: name } });
            values[name] = value;
            return el('span', { class: 'ir-pp-item', data: { item: name }, attrs: { title: _(title) } }, [icon, value]);
        };
        const row = (/** @type {HTMLElement[]} */ ...items) => el('div', { class: 'ir-pp-row' }, items);
        const keys = KEY_KINDS.map(({ kind, sprite, name }) => item(`key${kind}`, name, sprite));
        const root = el('div', { class: 'ir-pp', data: { player: playerId } }, [
            row(item('score', 'Self-composition score', 'icon_score'), item('hand', 'Hand (cards)', null, 'ir-hand-icon'), item('light', 'Inner light', 'token_light')),
            row(item('wild', 'Wild move key (face up + used)', 'token_wildkey'), item('compose', 'Self-composition token', 'token_compose'), item('analysis', 'Psychoanalysis token', 'token_analysis')),
            row(item('tier', 'Psyche tier', null, 'ir-tier-icon'), ...keys),
        ]);
        host.append(root);
        this.values.set(playerId, values);
        this.hideStandardScore(playerId);
    }

    /**
     * BGA 標準のスコア欄を隠す（`#player_score_<id>` の親。要素が見つからなければ何もしない。Studio で確認: 技術 No.9）。
     * @param {number} playerId
     */
    hideStandardScore(playerId) {
        const score = document.getElementById(`player_score_${playerId}`);
        const holder = score?.closest('.player_score') ?? score?.parentElement;
        holder?.classList.add('ir-hide-standard-score');
    }

    /**
     * @param {number} playerId
     * @param {boolean} animate 値が変わった項目を強調する
     */
    render(playerId, animate) {
        const state = this.states.get(playerId);
        const values = this.values.get(playerId);
        if (state === undefined || values === undefined) {
            return;
        }
        const keys = keyCounts(state.composed, this.keyTable);
        /** @type {Record<string, string>} */
        const texts = {
            score: scoreText(state.score),
            hand: String(state.handCount),
            light: String(state.tokens.light),
            wild: wildText(state.tokens),
            compose: String(state.tokens.compose),
            analysis: String(state.tokens.analysis),
            tier: tierText(state.tier),
        };
        for (const { kind } of KEY_KINDS) {
            texts[`key${kind}`] = String(keys[kind]);
        }
        for (const [name, text] of Object.entries(texts)) {
            const node = values[name];
            if (node === undefined || node.textContent === text) {
                continue;
            }
            const first = node.textContent === '';
            node.textContent = text;
            if (animate && !first) {
                node.classList.toggle('ir-pop', false);
                // 同じ要素で連続して変わっても再生し直す
                void node.offsetWidth;
                node.classList.toggle('ir-pop', true);
                setTimeout(() => node.classList.toggle('ir-pop', false), POP_MS);
            }
        }
    }
}
