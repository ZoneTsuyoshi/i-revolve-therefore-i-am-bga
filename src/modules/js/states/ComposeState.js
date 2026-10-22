/**
 * 状態 61 `Compose`: 自己構成の操作 UI（仕様書 009 §4、006 §5.5、001 §9）。規則は compose-plan.js の純粋関数に任せ、
 * ここは手札・自己構成済みカード・ステータスバーのボタンをつなぐだけ。
 *
 * 流れ: 手札から自己構成するカードを選ぶ（自己構成できるカードが光る）→ 払うものをまとめて選ぶ
 * （捨てる手札・M095 で横向きにする自己構成済みカードはクリックで選択のオン・オフ、心光は「+1／-1」、I08 の追加支払いは別の選択）。
 * 割り当ては自動（compose-plan.js の `solveComposePayment`）で、エネルギーが足りたら「Confirm」で `actCompose`。
 * I03（建築家）を持つときは「Swap with Architect」で `actComposeArchitect`。「Cancel」で何も消費せずに戻る。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { memKey } from '../board/view-model.js';
import { ActionState } from './ActionState.js';
import {
  architectHand, architectTableau, composableIds, composeFromArgs, discardOptions, emptyPayment, extraOptions, lightCost, solveComposePayment,
  toPayment,
} from './compose-plan.js';
import { cardLabel } from './payment-ui.js';

/** @returns {{ hand: number[], tap: number[], light: number, extra: number[] }} */
function emptySelection() {
  return { hand: [], tap: [], light: 0, extra: [] };
}

export class ComposeState extends ActionState {
  /**
   * @param {Bga} bga
   * @param {() => import('./ActionState.js').ActionDeps} getDeps
   */
  constructor(bga, getDeps) {
    super(bga, getDeps);
    /** @type {import('./compose-plan.js').ComposeBoard | null} */
    this.board = null;
    this.payment = emptyPayment();
    this.selection = emptySelection();
    /** 手札のクリックで編集する先（エネルギーとして捨てる / I08 の追加支払い） */
    this.editing = /** @type {'discard' | 'extra'} */ ('discard');
    /** I03 の入れ替え中 */
    this.architect = false;
    /** @type {number | null} I03 で選んだ自己構成済みカード */
    this.architectTableauId = null;
    /** @type {number | null} I03 で選んだ手札 */
    this.architectHandId = null;
  }

  /** @param {unknown} args */
  begin(args) {
    this.board = composeFromArgs(args);
    this.payment = emptyPayment();
    this.selection = emptySelection();
    this.editing = 'discard';
    this.architect = false;
    this.architectTableauId = null;
    this.architectHandId = null;
  }

  end() {
    this.board = null;
    this.payment = emptyPayment();
    this.selection = emptySelection();
  }

  /**
   * @param {number | null} cardId
   * @returns {string}
   */
  nameOf(cardId) {
    const type = cardId === null ? undefined : this.board?.hand.get(cardId)?.type;
    return type === undefined ? '' : (this.getDeps().texts?.cards[memKey(type)]?.name ?? memKey(type));
  }

  draw() {
    const board = this.board;
    if (board === null) {
      return;
    }
    if (this.architect) {
      this.drawArchitect(board);
    } else if (this.payment.cardId === null) {
      this.drawChoose(board);
    } else {
      this.drawPay(board);
    }
    this.button(_('Cancel'), () => this.send('actCancel'), { color: 'secondary' });
  }

  /**
   * 自己構成するカードを選ぶ。
   * @param {import('./compose-plan.js').ComposeBoard} board
   */
  drawChoose(board) {
    const ids = composableIds(board);
    this.bga.statusBar.setTitle(ids.length === 0 ? _('${you} have no card you can compose') : _('${you} must choose a card to compose'));
    this.pickHand({
      ids,
      mode: 'single',
      selected: [],
      onChange: (selected) => {
        if (selected.length > 0) {
          this.payment = { ...emptyPayment(), cardId: selected[0] };
          this.selection = emptySelection();
          this.editing = 'discard';
          this.render();
        }
      },
    });
    if (board.architectPairs.length > 0) {
      this.button(_('Swap with Architect'), () => {
        this.architect = true;
        this.architectTableauId = null;
        this.architectHandId = null;
        this.render();
      }, { color: 'secondary', tooltip: _('Instead of composing, swap a constructed card with a Memory card in your hand') });
    }
  }

  /**
   * エネルギーの支払いをまとめて選ぶ: 手札（捨てる）と横向きにする自己構成済みカードはクリックで選択のオン・オフ、心光は数量。
   * 選んだ資源は `solveComposePayment` が自動で割り当て（足りない・余り・過払いを見せる）、足りたときだけ「Confirm」が有効になる。
   * @param {import('./compose-plan.js').ComposeBoard} board
   */
  drawPay(board) {
    const { playerAreas, me } = this.getDeps();
    const cardId = this.payment.cardId;
    const sel = this.selection;
    const solution = solveComposePayment(board, cardId, sel);
    const card = this.nameOf(cardId);
    const status = [];
    if (board.paysEnergy) {
      status.push(_('Energy: paid ${paid} of ${need}').replace('${paid}', String(solution.paid)).replace('${need}', String(solution.need)));
      if (solution.shortfall > 0) {
        status.push(_('Still needed: ${keys}').replace('${keys}', _('${count} energy').replace('${count}', String(solution.shortfall))));
      }
    } else {
      status.push(_('no energy is needed'));
    }
    const unused = this.unusedText(solution);
    if (unused !== '') {
      status.push(_('Unused: ${items}').replace('${items}', unused));
    }
    this.bga.statusBar.setTitle(_('Compose ${card}. ${status}'), { card, status: status.join('. ') });
    playerAreas?.markHand(cardId === null ? [] : [cardId]);

    const editing = board.extraMax > 0 && this.editing === 'extra' ? 'extra' : 'discard';
    const ids = editing === 'extra' ? extraOptions(board, { ...this.payment, discard: sel.hand }) : discardOptions(board, { ...this.payment, extra: sel.extra });
    if (ids.length > 0) {
      this.pickHand({
        ids,
        mode: 'multiple',
        selected: editing === 'extra' ? sel.extra : sel.hand,
        onChange: (selected) => {
          this.selection = editing === 'extra'
            ? { ...sel, extra: selected, hand: sel.hand.filter((id) => !selected.includes(id)) }
            : { ...sel, hand: selected, extra: sel.extra.filter((id) => !selected.includes(id)) };
          this.render();
        },
      });
    }
    if (board.paysEnergy && board.tapCandidates.length > 0 && me !== null) {
      playerAreas?.selectComposed(me, {
        ids: board.tapCandidates,
        mode: 'multiple',
        selected: sel.tap,
        onChange: (selected) => {
          this.selection = { ...sel, tap: selected.map(Number) };
          this.render();
        },
      });
    }

    if (board.paysEnergy && board.maxLightUses > 0) {
      const next = sel.light + 1;
      const step = lightCost(board, next) - lightCost(board, sel.light);
      this.button(_('Psychic Light +1 (selected ${count}, cost ${cost})').replace('${count}', String(sel.light)).replace('${cost}', Number.isFinite(step) ? String(step) : '-'), () => {
        this.selection = { ...sel, light: next };
        this.render();
      }, {
        color: 'secondary',
        disabled: next > board.maxLightUses || lightCost(board, next) > board.light,
        tooltip: _('Pay 1 energy with Psychic Light. The nth use in an action costs more'),
      });
      if (sel.light > 0) {
        this.button(_('Psychic Light -1'), () => {
          this.selection = { ...sel, light: sel.light - 1 };
          this.render();
        }, { color: 'secondary' });
      }
    }
    if (board.extraMax > 0) {
      this.button(editing === 'extra' ? _('Choose energy cards') : _('Choose extra cards'), () => {
        this.editing = editing === 'extra' ? 'discard' : 'extra';
        this.render();
      }, {
        color: 'secondary',
        tooltip: _('Extra payment: put up to ${max} more cards from your hand under the composed card (1 point each)').replace('${max}', String(board.extraMax)),
      });
    }
    this.button(_('Change card'), () => {
      this.payment = emptyPayment();
      this.selection = emptySelection();
      this.render();
    }, { color: 'secondary' });
    if (sel.hand.length + sel.tap.length + sel.light + sel.extra.length > 0) {
      this.button(_('Reset'), () => {
        this.selection = emptySelection();
        this.render();
      }, { color: 'alert', tooltip: _('Deselect every resource') });
    }
    this.button(_('Confirm'), () => {
      if (cardId !== null) {
        this.send('actCompose', { cardId, payment: JSON.stringify(toPayment(solution.payment)) });
      }
    }, { disabled: !solution.complete });
  }

  /**
   * 使われなかった選択の文言。
   * @param {import('./compose-plan.js').ComposeSolution} solution
   * @returns {string}
   */
  unusedText(solution) {
    const deps = /** @type {Parameters<typeof cardLabel>[0]} */ (this.getDeps());
    const parts = [
      ...solution.unused.hand.map((id) => cardLabel(deps, 'HAND', id)),
      ...solution.unused.tap.map((id) => cardLabel(deps, 'TABLEAU', id)),
    ];
    if (solution.unused.light > 0) {
      parts.push(`${_('Psychic Light')} × ${solution.unused.light}`);
    }
    return parts.join(', ');
  }

  /**
   * I03（建築家）: 自己構成済みのカードを選び、入れ替える手札を選ぶ。
   * @param {import('./compose-plan.js').ComposeBoard} board
   */
  drawArchitect(board) {
    const { playerAreas, me } = this.getDeps();
    this.bga.statusBar.setTitle(_('Swap with Architect: choose a constructed card, then a card in your hand'));
    if (me !== null) {
      playerAreas?.selectComposed(me, {
        ids: architectTableau(board),
        mode: 'single',
        selected: this.architectTableauId === null ? [] : [this.architectTableauId],
        onChange: (selected) => {
          this.architectTableauId = selected.length === 0 ? null : Number(selected[0]);
          if (this.architectTableauId === null || !architectHand(board, this.architectTableauId).includes(this.architectHandId ?? -1)) {
            this.architectHandId = null;
          }
          this.render();
        },
      });
    }
    this.pickHand({
      ids: this.architectTableauId === null ? [] : architectHand(board, this.architectTableauId),
      mode: 'single',
      selected: this.architectHandId === null ? [] : [this.architectHandId],
      onChange: (selected) => {
        this.architectHandId = selected.length === 0 ? null : selected[0];
        this.render();
      },
    });
    const tableauCardId = this.architectTableauId;
    const newCardId = this.architectHandId;
    this.button(_('Confirm'), () => {
      if (tableauCardId !== null && newCardId !== null) {
        this.send('actComposeArchitect', { tableauCardId, newCardId });
      }
    }, { disabled: tableauCardId === null || newCardId === null });
    this.button(_('Back'), () => {
      this.architect = false;
      this.render();
    }, { color: 'secondary' });
  }
}
