/**
 * 状態 62 `Analysis`: 精神分析の操作 UI（仕様書 009 §4、006 §5.4、001 §10）。規則は analysis-plan.js の純粋関数に任せ、
 * ここは自己構成済みカード・手札・ステータスバーのボタンをつなぐだけ。
 *
 * 流れ: 進む先を選ぶ（払えない先は無効。1 つなら自動）→ 払うものをまとめて選ぶ
 * （縦向きの自己構成済みカード・手札はクリックで選択のオン・オフ、心光は「+1／-1」）。
 * 軽減で取り除く鍵と各コストへの割り当ては自動（`solveAnalysisPayment`）で、ステータスバーに充足・不足・余りが出る。
 * 全部払えたら「Confirm」で `actAnalyze`。「Reset」で選択を外し、「Change destination」で進む先を選び直し、「Cancel」で戻る。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { ActionState } from './ActionState.js';
import { analysisFromArgs, canPay, destinationOf, lightCost, solveAnalysisPayment, toPayment } from './analysis-plan.js';
import { costText, spiritParts } from './choice-model.js';
import { cardLabel, keyName, neededText, statusText, stepQuantity } from './payment-ui.js';

/** @returns {{ tableau: number[], hand: number[], light: number }} */
function emptySelection() {
  return { tableau: [], hand: [], light: 0 };
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
    /** @type {string | null} 進む先（精神カード） */
    this.destination = null;
    this.selection = emptySelection();
  }

  /** @param {unknown} args */
  begin(args) {
    this.board = analysisFromArgs(args);
    this.destination = null;
    this.selection = emptySelection();
  }

  end() {
    this.board = null;
    this.destination = null;
    this.selection = emptySelection();
  }

  draw() {
    const board = this.board;
    if (board === null) {
      return;
    }
    if (this.destination === null && board.destinations.length === 1 && canPay(board, board.destinations[0])) {
      this.destination = board.destinations[0].id;
    }
    const bar = this.bga.statusBar;
    if (!board.canAnalyze || board.destinations.length === 0) {
      bar.setTitle(_('${you} cannot pay for any psychoanalysis now'));
    } else if (destinationOf(board, this.destination) === undefined) {
      bar.setTitle(_('${you} must choose a spirit card to advance to'));
      this.drawDestinations(board);
    } else {
      this.drawPayment(board);
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
        this.destination = destination.id;
        this.selection = emptySelection();
        this.render();
      }, { disabled: !payable, tooltip: payable ? undefined : _('You cannot pay for this') });
    }
  }

  /**
   * 払うものをまとめて選び、自動の割り当てを見せる。
   * @param {import('./analysis-plan.js').AnalysisBoard} board
   */
  drawPayment(board) {
    const { playerAreas, me } = this.getDeps();
    const sel = this.selection;
    const solution = solveAnalysisPayment(board, this.destination, sel);
    const parts = spiritParts(this.destination ?? '');
    /** @type {string[]} */
    const unused = [
      ...solution.unused.tableau.map((id) => cardLabel(this.getDeps(), 'TABLEAU', id)),
      ...solution.unused.hand.map((id) => cardLabel(this.getDeps(), 'HAND', id)),
    ];
    if (solution.unused.light > 0) {
      unused.push(`${_('Psychic Light')} × ${solution.unused.light}`);
    }
    let status = statusText({ paid: solution.paid, total: solution.total, needed: neededText(solution.needed), unused: unused.join(', '), keys: true });
    const removed = neededText(Object.fromEntries(Object.entries(solution.state.removed).filter(([, n]) => n > 0)));
    if (removed !== '') {
      status = `${_('Removed by the reduction: ${keys}').replace('${keys}', removed)}. ${status}`;
    }
    this.bga.statusBar.setTitle(_('Tier ${tier} ${branch}: ${status}'), { tier: parts.tier, branch: parts.branch, status });

    const free = (/** @type {Map<number, import('./analysis-plan.js').AnalysisCard>} */ cards) => [...cards.values()].filter((c) => !c.tapped && c.keys.length > 0).map((c) => c.id);
    const tableau = free(board.tableau);
    if (me !== null && tableau.length > 0) {
      playerAreas?.selectComposed(me, {
        ids: tableau,
        mode: 'multiple',
        selected: sel.tableau,
        onChange: (ids) => {
          this.selection = { ...sel, tableau: ids.map(Number) };
          this.render();
        },
      });
    }
    const hand = free(board.hand);
    if (hand.length > 0) {
      playerAreas?.selectHand({
        ids: hand,
        mode: 'multiple',
        selected: sel.hand,
        onChange: (ids) => {
          this.selection = { ...sel, hand: ids.map(Number) };
          this.render();
        },
      });
    }
    if (board.maxLightUses > 0 && board.light > 0) {
      const next = sel.light + 1;
      const step = lightCost(board, next) - lightCost(board, sel.light);
      this.button(_('Psychic Light +1 (selected ${count}, cost ${cost})').replace('${count}', String(sel.light)).replace('${cost}', Number.isFinite(step) ? String(step) : '-'), () => {
        this.selection = { ...sel, light: stepQuantity(sel.light, 1, board.maxLightUses) };
        this.render();
      }, {
        color: 'secondary',
        disabled: next > board.maxLightUses || lightCost(board, next) > board.light,
        tooltip: _('Pay 1 key with Psychic Light. The nth use in an action costs more'),
      });
      if (sel.light > 0) {
        this.button(_('Psychic Light -1'), () => {
          this.selection = { ...sel, light: sel.light - 1 };
          this.render();
        }, { color: 'secondary' });
      }
    }
    if (sel.tableau.length + sel.hand.length + sel.light > 0) {
      this.button(_('Reset'), () => {
        this.selection = emptySelection();
        this.render();
      }, { color: 'alert', tooltip: _('Deselect every resource') });
    }
    if (board.destinations.length > 1) {
      this.button(_('Change destination'), () => {
        this.destination = null;
        this.selection = emptySelection();
        this.render();
      }, { color: 'secondary' });
    }
    this.button(_('Confirm'), () => {
      this.send('actAnalyze', { destination: this.destination, payment: JSON.stringify(toPayment(solution.state)) });
    }, { disabled: !solution.complete });
  }
}
