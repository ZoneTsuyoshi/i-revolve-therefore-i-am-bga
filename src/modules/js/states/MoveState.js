/**
 * 状態 31 `Move`: ターンプレイヤーが経路と支払いを計画し、`actMove` で 1 回送る（仕様書 009 §3.1、006 §5.1）。
 * 規則は move-plan.js の純粋関数に任せ、ここは回廊の部屋・手札・自己構成済みカード・ステータスバーのボタンをつなぐだけ。
 *
 * 流れ:
 * 1. 入る部屋を順にクリックして経路を作る（入れる部屋が光る。「Undo last room」（赤・右端）か、経路の最後の部屋を押すと 1 つ戻す）
 * 2. 払うものをまとめて選ぶ: 自己構成済みカード・手札をクリックで選択のオン・オフ（鍵は順不同）、心光・万能移動鍵は領域のトークンを押して数量
 * 3. 選んだ資源は規則を守って各部屋の錠前に自動で割り当てられる（`solveMovePayment`）。ステータスバーに充足・不足・余りが出る
 * 4. 全部の錠前が払えたら「Confirm move」（割り当ての結果を `{rooms, payments}` にして送る）。Reset はない（010 §2.3）
 * ターンプレイヤーには、手札のタブの「Discard 2 for Psychic Light」と、獲得できる目標（青の「Claim goal」）のフリーアクションも出す（006 §6.2。FreeActionsUi.js）。
 * 払える移動が無いときは「Stay」で空の経路を送る（Q-38）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import {
  afterDiscard, boardFromArgs, keepReachableRoute, lightCostOf, lockTotal, reachableRooms, routeExtras, solveMovePayment, toInput, withoutDiscarded,
} from './move-plan.js';
import { cumulativeOf, lightLimit, sortButtons } from './action-bar-model.js';
import { FreeActionsUi } from './FreeActionsUi.js';
import { int } from '../ui/dom.js';
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
 * @property {import('../board/CommonArea.js').CommonArea | null} common 共通の場（獲得できる目標。フリーアクション）
 * @property {import('../board/Board.js').BoardRefs | null} refs
 * @property {number | null} me 自分のプレイヤー ID（観戦なら null）
 * @property {import('../ui/texts.js').CardTexts | null} texts カード名（余りの表示用）
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
    /** @type {number | null} ターンプレイヤー（args の turnPlayer。フリーアクションを出すか決める） */
    this.turnPlayerId = null;
    /** フリーアクションを送ったあとか（サーバーが状態に入り直したとき、経路を戻すための印） */
    this.freeSent = false;
    /** @type {{ position: number, route: number[], selection: ReturnType<typeof emptySelection> } | null} 入り直しのために取っておいた計画 */
    this.keep = null;
    /** @type {Array<{ label: string, onClick: () => void, params: Record<string, any> }> | null} 画面を作っている間のボタン（青 → 白 → 赤に並べ直す） */
    this.queue = null;
    // フリーアクション（手札を捨てて心光を得る・目標の獲得）。ActionState と同じ部品（006 §6.2）
    this.free = new FreeActionsUi({
      bga,
      getDeps,
      turnPlayer: () => this.turnPlayerId ?? -1,
      button: (label, onClick, params = {}) => this.button(label, onClick, params),
      send: (action, args = {}) => {
        this.freeSent = true;
        return Promise.resolve(this.bga.actions.performAction(action, args)).then(() => true, () => false);
      },
      render: () => this.render(),
      onDiscarded: (ids, gain) => this.applyDiscard(ids, gain),
    });
  }

  /**
   * ステータスバーのボタンを足す。`render` の間は一度ためて、青 → 白 → 赤に並べ直して出す（010 §2.2）。
   * @param {string} label
   * @param {() => void} onClick
   * @param {{ color?: 'primary' | 'secondary' | 'alert', disabled?: boolean, tooltip?: string }} [params]
   */
  button(label, onClick, params = {}) {
    if (this.queue === null) {
      this.bga.statusBar.addActionButton(label, onClick, params);
    } else {
      this.queue.push({ label, onClick, params });
    }
  }

  /** ためたボタンを、青 → 白 → 赤の順に出す */
  flushButtons() {
    const queue = this.queue ?? [];
    this.queue = null;
    for (const { label, onClick, params } of sortButtons(queue)) {
      this.bga.statusBar.addActionButton(label, onClick, params);
    }
  }

  /**
   * 手札を捨てて心光を得たあと、計画の盤面と選択を合わせる（サーバーが状態に入り直さなくても合うように）。
   * @param {number[]} ids
   * @param {number} gain
   */
  applyDiscard(ids, gain) {
    if (this.board !== null) {
      this.board = afterDiscard(this.board, ids, gain);
    }
    this.selection = withoutDiscarded(this.selection, ids);
  }

  /** フリーアクションのあと入り直すときのために、経路と選択を取っておく */
  snapshot() {
    this.keep = this.freeSent && this.board !== null
      ? { position: this.board.position, route: this.route, selection: this.selection }
      : null;
  }

  /**
   * @param {unknown} args
   * @param {boolean} isCurrentPlayerActive
   */
  onEnteringState(args, isCurrentPlayerActive) {
    if (this.active) {
      this.snapshot();
    }
    const keep = this.keep;
    this.keep = null;
    this.freeSent = false;
    this.free.reset();
    this.route = [];
    this.selection = emptySelection();
    this.solution = null;
    this.active = isCurrentPlayerActive;
    this.turnPlayerId = int((/** @type {any} */ (args))?.turnPlayer);
    if (!isCurrentPlayerActive) {
      return;
    }
    this.board = boardFromArgs(args);
    // フリーアクションのあとの入り直しなら、同じ位置の経路と、まだ持っている手札・自己構成済みの選択を戻す
    if (keep !== null && keep.position === this.board.position) {
      const board = this.board;
      this.route = keepReachableRoute(board, keep.route);
      this.selection = {
        ...keep.selection,
        tableau: keep.selection.tableau.filter((id) => board.tableau.has(id)),
        hand: keep.selection.hand.filter((id) => board.hand.has(id)),
      };
    }
    this.render();
  }

  /**
   * @param {unknown} _args
   * @param {boolean} _isCurrentPlayerActive
   */
  onLeavingState(_args, _isCurrentPlayerActive) {
    this.snapshot();
    this.active = false;
    this.free.setClaimListener(false);
    this.free.reset();
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
    this.queue = [];

    // 手札を捨てて心光を得る画面: 部屋と支払いの選択は外し、経路は保つ（Cancel で計画に戻る）
    if (this.free.discardMode) {
      this.free.drawDiscard();
      this.flushButtons();
      return;
    }

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
      this.button(_('Confirm move'), () => this.confirm());
    } else if (route.length > 0) {
      this.button(_('Confirm move'), () => {}, { disabled: true, tooltip: _('Select enough resources to pay for every lock') });
    }
    if (route.length === 0 && !board.canMove) {
      this.button(_('Stay'), () => this.confirm());
    }
    if (route.length > 0) {
      this.button(_('Undo last room'), () => this.undoRoom(), { color: 'alert', tooltip: _('You can also click the last room of the route') });
    }
    this.free.add();
    this.flushButtons();
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
    // 移動を送ったら、離脱のときに計画を取っておかない（フリーアクションのあとの入り直し用の印を戻す）
    this.freeSent = false;
    this.bga.actions.performAction('actMove', { plan: JSON.stringify(toInput(steps)) });
  }
}
