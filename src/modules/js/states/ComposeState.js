/**
 * 状態 61 `Compose`: 自己構成の操作 UI（仕様書 009 §4、006 §5.5、001 §9）。規則は compose-plan.js の純粋関数に任せ、
 * ここは手札・自己構成済みカード・ステータスバーのボタンをつなぐだけ。
 *
 * 流れ: 手札から自己構成するカードを選ぶ（自己構成できるカードが光る）→ エネルギーの支払いを組み立てる
 * （手札を選んで捨てる、「Inner light」で心光の行使、M095 の横向きにする自己構成済みカードを選ぶ、I08 の追加支払い）→ 「Confirm」で `actCompose`。
 * I03（建築家）を持つときは「Swap with Architect」で `actComposeArchitect`。「Cancel」で何も消費せずに戻る。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { memKey } from '../board/view-model.js';
import { ActionState } from './ActionState.js';
import {
  architectHand, architectTableau, canAddLight, checkPayment, composableIds, composeFromArgs, discardOptions, emptyPayment, energyNeed,
  extraOptions, lightCost, paidEnergy, tapOptions, toPayment,
} from './compose-plan.js';

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
    this.editing = 'discard';
    this.architect = false;
    this.architectTableauId = null;
    this.architectHandId = null;
  }

  end() {
    this.board = null;
    this.payment = emptyPayment();
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
   * エネルギーの支払いを組み立てる。
   * @param {import('./compose-plan.js').ComposeBoard} board
   */
  drawPay(board) {
    const { playerAreas, me } = this.getDeps();
    const payment = this.payment;
    const need = energyNeed(board, payment);
    const paid = paidEnergy(board, payment);
    const card = this.nameOf(payment.cardId);
    if (board.paysEnergy) {
      this.bga.statusBar.setTitle(_('Compose ${card}: energy paid ${paid} of ${need}'), { card, paid, need });
    } else {
      this.bga.statusBar.setTitle(_('Compose ${card}: no energy is needed'), { card });
    }
    playerAreas?.markHand(payment.cardId === null ? [] : [payment.cardId]);

    const editing = board.extraMax > 0 && this.editing === 'extra' ? 'extra' : 'discard';
    const ids = editing === 'extra' ? extraOptions(board, payment) : discardOptions(board, payment);
    if (ids.length > 0) {
      this.pickHand({
        ids,
        mode: 'multiple',
        selected: editing === 'extra' ? payment.extra : payment.discard,
        onChange: (selected) => {
          this.payment = editing === 'extra' ? { ...payment, extra: selected } : { ...payment, discard: selected };
          this.render();
        },
      });
    }
    const taps = tapOptions(board, payment);
    if (taps.length > 0 && me !== null) {
      playerAreas?.selectComposed(me, {
        ids: taps,
        mode: 'multiple',
        selected: payment.tap,
        onChange: (selected) => {
          this.payment = { ...payment, tap: selected.map(Number) };
          this.render();
        },
      });
    }

    if (board.paysEnergy && board.maxLightUses > 0) {
      const next = payment.light + 1;
      const step = lightCost(board, next) - lightCost(board, payment.light);
      this.button(_('Inner light +1 (${cost})').replace('${cost}', Number.isFinite(step) ? String(step) : '-'), () => {
        this.payment = { ...payment, light: next };
        this.render();
      }, { color: 'secondary', disabled: !canAddLight(board, payment), tooltip: _('Pay 1 energy with Psychic Light. The nth use in an action costs more') });
      if (payment.light > 0) {
        this.button(_('Inner light -1'), () => {
          this.payment = { ...payment, light: payment.light - 1 };
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
      this.render();
    }, { color: 'secondary' });
    const check = checkPayment(board, payment);
    this.button(_('Confirm'), () => {
      if (payment.cardId !== null) {
        this.send('actCompose', { cardId: payment.cardId, payment: JSON.stringify(toPayment(payment)) });
      }
    }, { disabled: !check.ok });
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
