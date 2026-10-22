/**
 * 状態 31 `Move`: ターンプレイヤーが経路と支払いを計画し、`actMove` で 1 回送る（仕様書 009 §3.1、006 §5.1）。
 * 規則は move-plan.js の純粋関数に任せ、ここは回廊の部屋・手札・自己構成済みカード・ステータスバーのボタンをつなぐだけ。
 *
 * 流れ:
 * 1. 入る部屋を順にクリックして経路を作る（入れる部屋が光る。「Undo last room」で最後の部屋を取り消す）
 * 2. 払うものをまとめて選ぶ: 自己構成済みカード・手札をクリックで選択のオン・オフ（鍵は順不同）、心光・万能移動鍵・効果の万能鍵は「+1／-1」の数量
 * 3. 選んだ資源は規則を守って各部屋の錠前に自動で割り当てられる（`solveMovePayment`）。ステータスバーに充足・不足・余りが出る
 * 4. 全部の錠前が払えたら「Confirm move」（割り当ての結果を `{rooms, payments}` にして送る）。「Reset」で選択を全部外す
 * 払える移動が無いときは「Stay」で空の経路を送る（Q-38）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import {
  boardFromArgs, lightCostOf, lockTotal, reachableRooms, routeExtras, solveMovePayment, toInput,
} from './move-plan.js';
import { cardLabel, neededText, statusText, stepQuantity } from './payment-ui.js';

/** 入れる部屋の印 */
export const CLASS_ROOM_SELECTABLE = 'ir-room-selectable';
/** 経路に入った部屋の印（順番は data-order） */
export const CLASS_ROOM_ROUTE = 'ir-room-route';

/**
 * @typedef {object} MoveDeps
 * @property {import('../board/PlayerAreas.js').PlayerAreas | null} playerAreas
 * @property {import('../board/Board.js').BoardRefs | null} refs
 * @property {number | null} me 自分のプレイヤー ID（観戦なら null）
 * @property {import('../ui/texts.js').CardTexts | null} [texts] カード名（余りの表示用）
 */

/** @returns {{ tableau: number[], hand: number[], light: number, token: number, effect: number }} */
function emptySelection() {
  return { tableau: [], hand: [], light: 0, token: 0, effect: 0 };
}

export class MoveState {
  /**
   * @param {Bga} bga
   * @param {() => MoveDeps} getDeps setup のあとで作られる盤面への参照
   */
  constructor(bga, getDeps) {
    this.bga = bga;
    this.getDeps = getDeps;
    /** @type {import('./move-plan.js').MoveBoard | null} */
    this.board = null;
    /** @type {number[]} 入る部屋の位置を順に */
    this.route = [];
    this.selection = emptySelection();
    /** @type {import('./move-plan.js').MoveSolution | null} */
    this.solution = null;
    this.active = false;
    /** @type {Array<[HTMLElement, () => void]>} */
    this.listeners = [];
  }

  /**
   * @param {unknown} args
   * @param {boolean} isCurrentPlayerActive
   */
  onEnteringState(args, isCurrentPlayerActive) {
    this.route = [];
    this.selection = emptySelection();
    this.solution = null;
    this.active = isCurrentPlayerActive;
    if (!isCurrentPlayerActive) {
      return;
    }
    this.board = boardFromArgs(args);
    this.render();
  }

  /**
   * @param {unknown} _args
   * @param {boolean} _isCurrentPlayerActive
   */
  onLeavingState(_args, _isCurrentPlayerActive) {
    this.active = false;
    this.clearMarks();
    this.getDeps().playerAreas?.clearSelection();
    this.route = [];
    this.selection = emptySelection();
    this.solution = null;
    this.board = null;
  }

  /** 部屋の印とクリックを外し、経路の印も消す */
  clearMarks() {
    for (const [element, handler] of this.listeners) {
      element.removeEventListener('click', handler);
    }
    this.listeners = [];
    const refs = this.getDeps().refs;
    for (const room of refs?.rooms.values() ?? []) {
      room.classList.remove(CLASS_ROOM_SELECTABLE, CLASS_ROOM_ROUTE);
      delete room.dataset.order;
    }
  }

  /** 経路・選択の今の形に合わせて、画面（部屋・カード・ボタン・タイトル）を作り直す */
  render() {
    const board = this.board;
    if (!this.active || board === null) {
      return;
    }
    const deps = this.getDeps();
    const { playerAreas, refs, me } = deps;
    this.clearMarks();
    playerAreas?.clearSelection();
    const bar = this.bga.statusBar;
    bar.removeActionButtons();

    const route = this.route;
    this.solution = route.length === 0 ? null : solveMovePayment(board, route, this.selection);
    const solution = this.solution;

    // 経路の印と、次に入れる部屋
    route.forEach((pos, i) => {
      const room = refs?.rooms.get(pos);
      if (room !== undefined) {
        room.classList.add(CLASS_ROOM_ROUTE);
        room.dataset.order = String(i + 1);
      }
    });
    for (const pos of reachableRooms(board, route)) {
      const element = refs?.rooms.get(pos);
      if (element === undefined) {
        continue;
      }
      element.classList.add(CLASS_ROOM_SELECTABLE);
      const handler = () => {
        this.route = [...this.route, pos];
        this.render();
      };
      element.addEventListener('click', handler);
      this.listeners.push([element, handler]);
    }

    if (route.length === 0) {
      bar.setTitle(board.canMove ? _('${you} must choose the next room to enter') : _('${you} cannot afford to move'));
    } else if (solution !== null) {
      bar.setTitle(_('Route: ${route}. ${status}'), {
        route: route.map((pos) => `${_('Room')} ${pos}`).join(' > '),
        status: statusText({
          paid: solution.paid,
          total: solution.total,
          needed: neededText(solution.needed),
          unused: this.unusedText(solution),
        }),
      });
      this.drawResources(board, playerAreas, me);
    }

    if (solution?.complete === true) {
      bar.addActionButton(_('Confirm move'), () => this.confirm());
    } else if (route.length > 0) {
      bar.addActionButton(_('Confirm move'), () => {}, { disabled: true, tooltip: _('Select enough resources to pay for every lock') });
    }
    if (route.length === 0 && !board.canMove) {
      bar.addActionButton(_('Stay'), () => this.confirm());
    }
    if (route.length > 0) {
      bar.addActionButton(_('Undo last room'), () => {
        this.route = this.route.slice(0, -1);
        this.render();
      }, { color: 'secondary' });
    }
    if (this.hasSelection()) {
      bar.addActionButton(_('Reset'), () => {
        this.selection = emptySelection();
        this.render();
      }, { color: 'alert', tooltip: _('Deselect every resource') });
    }
  }

  /** @returns {boolean} 何か選んでいるか */
  hasSelection() {
    const s = this.selection;
    return s.tableau.length + s.hand.length + s.light + s.token + s.effect > 0;
  }

  /**
   * 使われなかった選択の文言。
   * @param {import('./move-plan.js').MoveSolution} solution
   * @returns {string}
   */
  unusedText(solution) {
    const deps = this.getDeps();
    const u = solution.unused;
    const parts = [
      ...u.tableau.map((id) => cardLabel(deps, 'TABLEAU', id)),
      ...u.hand.map((id) => cardLabel(deps, 'HAND', id)),
    ];
    if (u.light > 0) {
      parts.push(`${_('Inner light')} × ${u.light}`);
    }
    if (u.token > 0) {
      parts.push(`${_('Wild move key')} × ${u.token}`);
    }
    if (u.effect > 0) {
      parts.push(`${_('Effect key')} × ${u.effect}`);
    }
    return parts.join(', ');
  }

  /**
   * 払う資源をまとめて選ぶ: カードはクリックで選択のオン・オフ、心光・トークン・効果の鍵は数量のボタン。
   * @param {import('./move-plan.js').MoveBoard} board
   * @param {import('../board/PlayerAreas.js').PlayerAreas | null} playerAreas
   * @param {number | null} me
   */
  drawResources(board, playerAreas, me) {
    const free = (/** @type {Map<number, import('./move-plan.js').BoardCard>} */ cards) => [...cards.values()].filter((c) => !c.tapped && c.keys.length > 0).map((c) => c.id);
    const tableau = free(board.tableau);
    const hand = free(board.hand);
    if (tableau.length > 0 && me !== null) {
      playerAreas?.selectComposed(me, {
        ids: tableau,
        mode: 'multiple',
        selected: this.selection.tableau,
        onChange: (ids) => {
          this.selection = { ...this.selection, tableau: ids.map(Number) };
          this.render();
        },
      });
    }
    if (hand.length > 0) {
      playerAreas?.selectHand({
        ids: hand,
        mode: 'multiple',
        selected: this.selection.hand,
        onChange: (ids) => {
          this.selection = { ...this.selection, hand: ids.map(Number) };
          this.render();
        },
      });
    }
    const total = lockTotal(board, this.route);
    const extras = routeExtras(board, this.route);
    const bar = this.bga.statusBar;
    /**
     * @param {'light' | 'token' | 'effect'} kind
     * @param {string} plus
     * @param {string} minus
     * @param {number} max
     * @param {string} tooltip
     */
    const quantity = (kind, plus, minus, max, tooltip) => {
      const now = this.selection[kind];
      bar.addActionButton(plus.replace('${count}', String(now)), () => {
        this.selection = { ...this.selection, [kind]: stepQuantity(now, 1, max) };
        this.render();
      }, { color: 'secondary', disabled: now >= max, tooltip });
      if (now > 0) {
        bar.addActionButton(minus, () => {
          this.selection = { ...this.selection, [kind]: stepQuantity(now, -1, max) };
          this.render();
        }, { color: 'secondary' });
      }
    };
    if (board.light + extras.light > 0) {
      const next = lightCostOf(board, this.selection.light + 1);
      quantity('light', _('Inner light +1 (selected ${count})'), _('Inner light -1'), total,
        _('Pay 1 key with Psychic Light. The nth use in an action costs more') + ` (${next})`);
    }
    if (board.wildUp + extras.token > 0) {
      quantity('token', _('Wild move key +1 (selected ${count})'), _('Wild move key -1'), total, _('Pay 1 lock with a Wild move key'));
    }
    if (extras.effectKeys > 0) {
      quantity('effect', _('Effect key +1 (selected ${count})'), _('Effect key -1'), extras.effectKeys, _('Pay 1 lock of the first level 2 room with a Wild key from a card effect'));
    }
  }

  /** 経路と支払いを 1 回で送る（JsonParam には JSON 文字列で渡す。姉妹 PE と同じ）。払える移動が無いときは空の経路（Q-38） */
  confirm() {
    if (this.board === null) {
      return;
    }
    const steps = this.solution?.complete === true ? this.solution.steps : [];
    this.bga.actions.performAction('actMove', { plan: JSON.stringify(toInput(steps)) });
  }
}
