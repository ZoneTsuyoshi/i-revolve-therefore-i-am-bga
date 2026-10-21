/**
 * 状態 62 `Analysis`: 精神分析の操作 UI（仕様書 009 §4、006 §5.4、001 §10）。規則は analysis-plan.js の純粋関数に任せ、
 * ここは自己構成済みカード・手札・ステータスバーのボタンをつなぐだけ。
 *
 * 流れ: 進む先を選ぶ（払えない先は無効。1 つなら自動）→ 軽減で取り除くコストの鍵を選ぶ（種類が 1 つなら自動）
 * → 残りのコストを 1 つずつ払う（縦向きの自己構成済みカードか手札をクリック。1 枚の鍵で払い方が複数あるときは選択肢のボタンが出る。
 * 「Inner light」で心光の行使）→ 全部払えたら「Confirm」で `actAnalyze`。「Undo last payment」「Reset」「Cancel」。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { ActionState } from './ActionState.js';
import {
  SRC, addPay, analysisFromArgs, canPay, checkAnalysis, destinationOf, emptyState, isComplete, lightChoices, lightCost, lightUses,
  payChoices, removableKeys, removeKey, toPayment, undo, unpaid,
} from './analysis-plan.js';
import { costText, spiritParts } from './choice-model.js';

/**
 * 鍵の名前（翻訳済み）。
 * @param {string} type
 * @returns {string}
 */
function keyName(type) {
  switch (type) {
    case 'WILL': return _('Will');
    case 'EMOTION': return _('Emotion');
    case 'WILD': return _('Wild');
    default: return _('Wisdom');
  }
}

export class AnalysisState extends ActionState {
  /**
   * @param {Bga} bga
   * @param {() => import('./ActionState.js').ActionDeps} getDeps
   */
  constructor(bga, getDeps) {
    super(bga, getDeps);
    /** @type {import('./analysis-plan.js').AnalysisBoard | null} */
    this.board = null;
    this.plan = emptyState();
    /** @type {{ src: string, card: number, options: import('./analysis-plan.js').PayChoice[] } | null} 払い方を選んでいる最中のカード */
    this.pending = null;
  }

  /** @param {unknown} args */
  begin(args) {
    this.board = analysisFromArgs(args);
    this.plan = emptyState();
    this.pending = null;
  }

  end() {
    this.board = null;
    this.plan = emptyState();
    this.pending = null;
  }

  /** 進む先が 1 つで払えるなら自動で選び、軽減で取り除く鍵の種類が 1 つなら自動で取り除く */
  autoSteps() {
    const board = this.board;
    if (board === null) {
      return;
    }
    if (this.plan.destination === null && board.destinations.length === 1 && canPay(board, board.destinations[0])) {
      this.plan = { ...this.plan, destination: board.destinations[0].id };
    }
    for (let keys = removableKeys(board, this.plan); keys.length === 1; keys = removableKeys(board, this.plan)) {
      this.plan = removeKey(this.plan, keys[0]);
    }
  }

  draw() {
    const board = this.board;
    if (board === null) {
      return;
    }
    this.autoSteps();
    const bar = this.bga.statusBar;
    const destination = destinationOf(board, this.plan.destination);
    if (!board.canAnalyze || board.destinations.length === 0) {
      bar.setTitle(_('${you} cannot pay for any psychoanalysis now'));
    } else if (destination === undefined) {
      bar.setTitle(_('${you} must choose a spirit card to advance to'));
      this.drawDestinations(board);
    } else if (removableKeys(board, this.plan).length > 0) {
      bar.setTitle(_('Choose a key to remove from the cost (reduction ${count})'), { count: board.reduction });
      for (const key of removableKeys(board, this.plan)) {
        this.button(_('Remove ${key}').replace('${key}', keyName(key)), () => {
          this.plan = removeKey(this.plan, key);
          this.render();
        });
      }
    } else {
      this.drawPayment(board);
    }
    if (this.plan.keys.length > 0) {
      this.button(_('Undo last payment'), () => {
        this.pending = null;
        this.plan = undo(this.plan);
        this.render();
      }, { color: 'secondary' });
    }
    if (this.plan.destination !== null && (this.plan.keys.length > 0 || Object.keys(this.plan.removed).length > 0 || board.destinations.length > 1)) {
      this.button(_('Reset'), () => {
        this.pending = null;
        this.plan = emptyState();
        this.render();
      }, { color: 'alert' });
    }
    this.button(_('Cancel'), () => this.send('actCancel'), { color: 'secondary' });
  }

  /**
   * 進む先のボタン（払えない先は無効）。
   * @param {import('./analysis-plan.js').AnalysisBoard} board
   */
  drawDestinations(board) {
    for (const destination of board.destinations) {
      const { tier, branch } = spiritParts(destination.id);
      const label = _('Tier ${tier} ${branch}: ${cost}')
        .replace('${tier}', String(tier))
        .replace('${branch}', branch)
        .replace('${cost}', costText(destination.cost, keyName))
        .replace(/\s+/g, ' ');
      const payable = canPay(board, destination);
      this.button(label, () => {
        this.plan = { ...emptyState(), destination: destination.id };
        this.render();
      }, { disabled: !payable, tooltip: payable ? undefined : _('You cannot pay for this') });
    }
  }

  /**
   * 残りのコストを 1 つずつ払う。
   * @param {import('./analysis-plan.js').AnalysisBoard} board
   */
  drawPayment(board) {
    const { playerAreas, me } = this.getDeps();
    const left = unpaid(board, this.plan);
    const done = isComplete(board, this.plan);
    const tier = destinationOf(board, this.plan.destination)?.id ?? '';
    const parts = spiritParts(tier);
    this.bga.statusBar.setTitle(
      done ? _('Tier ${tier} ${branch}: the cost is paid') : _('Tier ${tier} ${branch}: pay ${cost}'),
      { tier: parts.tier, branch: parts.branch, cost: costText(left, keyName) },
    );
    if (this.pending !== null) {
      this.drawPendingChoices(this.pending);
      return;
    }
    if (!done) {
      const choices = payChoices(board, this.plan);
      const cardsOf = (/** @type {string} */ src) => [...new Set(choices.filter((c) => c.src === src).map((c) => c.card))];
      /** @param {string} src */
      const onPick = (src) => (/** @type {(number | string)[]} */ ids) => {
        if (ids.length > 0) {
          this.pickCard(src, Number(ids[0]), choices);
        }
      };
      if (me !== null && cardsOf(SRC.TABLEAU).length > 0) {
        playerAreas?.selectComposed(me, { ids: cardsOf(SRC.TABLEAU), mode: 'single', onChange: onPick(SRC.TABLEAU) });
      }
      if (cardsOf(SRC.HAND).length > 0) {
        playerAreas?.selectHand({ ids: cardsOf(SRC.HAND), mode: 'single', onChange: onPick(SRC.HAND) });
      }
      const uses = lightUses(this.plan);
      const step = lightCost(board, uses + 1) - lightCost(board, uses);
      for (const type of lightChoices(board, this.plan)) {
        this.button(_('Inner light for ${key} (${cost})').replace('${key}', keyName(type)).replace('${cost}', String(step)), () => {
          this.plan = addPay(this.plan, { src: SRC.LIGHT, for: type });
          this.render();
        }, { color: 'secondary', tooltip: _('Pay 1 key with Psychic Light. The nth use in an action costs more') });
      }
    }
    this.button(_('Confirm'), () => {
      this.send('actAnalyze', { destination: this.plan.destination, payment: JSON.stringify(toPayment(this.plan)) });
    }, { disabled: !done || !checkAnalysis(board, this.plan).ok });
  }

  /**
   * クリックされたカードの鍵で払う。払い方が 1 つに決まれば払い、複数あれば選択肢のボタンを出す。
   * @param {string} src
   * @param {number} card
   * @param {import('./analysis-plan.js').PayChoice[]} choices
   */
  pickCard(src, card, choices) {
    // 同じ（持っている鍵, 払うコスト）の鍵は 1 つにまとめる（番号が小さいほう）
    /** @type {Map<string, import('./analysis-plan.js').PayChoice>} */
    const unique = new Map();
    for (const choice of choices.filter((c) => c.src === src && c.card === card)) {
      const id = `${choice.held}>${choice.for}`;
      if (!unique.has(id)) {
        unique.set(id, choice);
      }
    }
    const options = [...unique.values()];
    if (options.length === 1) {
      this.addChoice(options[0]);
    } else {
      this.pending = { src, card, options };
      this.render();
    }
  }

  /** @param {import('./analysis-plan.js').PayChoice} choice */
  addChoice(choice) {
    this.pending = null;
    this.plan = addPay(this.plan, { src: choice.src, card: choice.card, key: choice.key, for: choice.for });
    this.render();
  }

  /**
   * 払い方の選択肢のボタン。
   * @param {{ src: string, card: number, options: import('./analysis-plan.js').PayChoice[] }} pending
   */
  drawPendingChoices(pending) {
    for (const choice of pending.options) {
      const label = _('Pay ${for} with ${held}').replace('${for}', keyName(choice.for)).replace('${held}', keyName(choice.held));
      this.button(label, () => this.addChoice(choice));
    }
    this.button(_('Back'), () => {
      this.pending = null;
      this.render();
    }, { color: 'secondary' });
  }
}
