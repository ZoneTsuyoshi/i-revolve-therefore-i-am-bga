/**
 * 状態 31 `Move`: ターンプレイヤーが経路と支払いを計画し、`actMove` で 1 回送る（仕様書 009 §3.1、006 §5.1）。
 * 規則は move-plan.js の純粋関数に任せ、ここは回廊の部屋・手札・自己構成済みカード・ステータスバーのボタンをつなぐだけ。
 *
 * 流れ:
 * 1. 入る部屋を順にクリックして経路を作る（入れる部屋が光る。「Undo last room」（赤・右端）か、経路の最後の部屋を押すと 1 つ戻す）
 * 2. 払うものをまとめて選ぶ: 自己構成済みカード・手札をクリックで選択のオン・オフ（鍵は順不同）、心光・万能移動鍵は領域のトークンを押して数量
 * 3. 選んだ資源は規則を守って各部屋の錠前に自動で割り当てられる（`solveMovePayment`）。ステータスバーに充足・不足・余りが出る
 * 4. 全部の錠前が払えたら「Confirm move」（割り当ての結果を `{rooms, payments}` にして送る）。Reset はない（010 §2.3）
 * 払える移動が無いときは「Stay」で空の経路を送る（Q-38）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import {
  boardFromArgs, lightCostOf, lockTotal, reachableRooms, routeExtras, solveMovePayment, toInput,
} from './move-plan.js';
import { cumulativeOf, lightLimit } from './action-bar-model.js';
import { cardLabel, neededText, quantityTokenPick, statusText } from './payment-ui.js';

/** 入れる部屋の印 */
export const CLASS_ROOM_SELECTABLE = 'ir-room-selectable';
/** 経路に入った部屋の印（順番は data-order） */
export const CLASS_ROOM_ROUTE = 'ir-room-route';
/** 経路の最後の部屋の印（押すと 1 つ戻す。010 §2.4） */
export const CLASS_ROOM_UNDO = 'ir-room-undo';

/**
 * @typedef {object} MoveDeps
 * @property {import('../board/PlayerAreas.js').PlayerAreas | null} playerAreas
 * @property {import('../board/Board.js').BoardRefs | null} refs
 * @property {number | null} me 自分のプレイヤー ID（観戦なら null）
 * @property {import('../ui/texts.js').CardTexts | null} [texts] カード名（余りの表示用）
 */

/** @returns {{ tableau: number[], hand: number[], light: number, token: number }} */
function emptySelection() {
  return { tableau: [], hand: [], light: 0, token: 0 };
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
      room.classList.remove(CLASS_ROOM_SELECTABLE, CLASS_ROOM_ROUTE, CLASS_ROOM_UNDO);
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
    const reachable = reachableRooms(board, route);
    // 経路の最後の部屋を押すと 1 つ戻す（その部屋へ入り直せるときは、入る操作を優先する）
    const last = route.length > 0 ? route[route.length - 1] : null;
    const lastElement = last === null ? undefined : refs?.rooms.get(last);
    if (last !== null && lastElement !== undefined && !reachable.includes(last)) {
      lastElement.classList.add(CLASS_ROOM_UNDO);
      const undo = () => this.undoRoom();
      lastElement.addEventListener('click', undo);
      this.listeners.push([lastElement, undo]);
    }
    for (const pos of reachable) {
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

    // 青 → 白 → 赤の順（Confirm move / Stay を先頭、Undo last room は赤で右端。010 §2.3）。Reset はなく、選んだ資源は個別に押して外す
    if (solution?.complete === true) {
      bar.addActionButton(_('Confirm move'), () => this.confirm());
    } else if (route.length > 0) {
      bar.addActionButton(_('Confirm move'), () => {}, { disabled: true, tooltip: _('Select enough resources to pay for every lock') });
    }
    if (route.length === 0 && !board.canMove) {
      bar.addActionButton(_('Stay'), () => this.confirm());
    }
    if (route.length > 0) {
      bar.addActionButton(_('Undo last room'), () => this.undoRoom(), { color: 'alert', tooltip: _('You can also click the last room of the route') });
    }
  }

  /** 経路の最後の部屋を 1 つ戻す */
  undoRoom() {
    this.route = this.route.slice(0, -1);
    this.render();
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
      parts.push(`${_('Psychic Light')} × ${u.light}`);
    }
    if (u.token > 0) {
      parts.push(`${_('Omni-Move Key')} × ${u.token}`);
    }
    return parts.join(', ');
  }

  /**
   * 払う資源をまとめて選ぶ: カードはクリックで選択のオン・オフ、心光・トークンは数量のボタン。
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
    /**
     * @param {'light' | 'token'} kind
     * @param {number} max
     * @param {(uses: number) => number} [cumulative]
     */
    const quantity = (kind, max, cumulative) => quantityTokenPick({
      kind: kind === 'light' ? 'light' : 'wildUp',
      count: this.selection[kind],
      max,
      cumulative,
      onChange: (count) => {
        this.selection = { ...this.selection, [kind]: count };
        this.render();
      },
    });
    const lightHeld = board.light + extras.light;
    const tokenHeld = board.wildUp + extras.token;
    const costOf = cumulativeOf((n) => lightCostOf(board, n));
    // 心光・万能移動鍵は、領域のトークンを押して数量を選ぶ（010 §2.4）。持っていないものは出さない
    /** @type {Partial<Record<'light' | 'wildUp', import('../board/PlayerAreas.js').TokenPick>>} */
    const picks = {};
    if (lightHeld > 0) {
      picks.light = quantity('light', lightLimit(costOf, lightHeld, total), costOf);
    }
    if (tokenHeld > 0) {
      picks.wildUp = quantity('token', Math.min(total, tokenHeld));
    }
    playerAreas?.pickTokens(picks);
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
