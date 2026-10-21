/**
 * 状態 31 `Move`: ターンプレイヤーが経路と支払いを計画し、`actMove` で 1 回送る（仕様書 009 §3.1、006 §5.1）。
 * 規則は move-plan.js の純粋関数に任せ、ここは回廊の部屋・手札・自己構成済みカード・ステータスバーのボタンをつなぐだけ。
 *
 * 流れ: 入れる部屋が光る → 部屋を選ぶ → その部屋の錠前の数だけ支払い手段（自己構成済みカード、手札、心光、万能移動鍵、効果の万能鍵）を選ぶ
 * → 繰り返し → 「Confirm move」。「Undo last room」で最後の部屋を取り消し、「Reset」で最初から。
 * 払える移動が無いときは「Stay」で空の経路を送る（Q-38）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import {
  SRC, addPayment, boardFromArgs, cardChoices, describePlan, isComplete, lightCostOf, lightUsesOf, nextRooms, payOptions, pendingRoom, toInput,
  undoRoom,
} from './move-plan.js';

/**
 * 経路の要約の文言（move-plan.js の `describePlan` の `tr`）。抽出のため `_()` のリテラルで書く。`_()` は setup 以降に呼ぶ。
 * @param {string} key 英語の原文
 * @returns {string}
 */
function translatePlan(key) {
  switch (key) {
    case 'Room': return _('Room');
    case 'Inner light': return _('Inner light');
    case 'Wild move key': return _('Wild move key');
    case 'Effect key': return _('Effect key');
    case 'Hand': return _('Hand');
    case 'Composed': return _('Composed');
    case 'Wisdom': return _('Wisdom');
    case 'Will': return _('Will');
    case 'Emotion': return _('Emotion');
    case 'Wild': return _('Wild');
    default: return key;
  }
}

/** 入れる部屋の印 */
export const CLASS_ROOM_SELECTABLE = 'ir-room-selectable';
/** 経路に入った部屋の印（順番は data-order） */
export const CLASS_ROOM_ROUTE = 'ir-room-route';

/**
 * @typedef {object} MoveDeps
 * @property {import('../board/PlayerAreas.js').PlayerAreas | null} playerAreas
 * @property {import('../board/Board.js').BoardRefs | null} refs
 * @property {number | null} me 自分のプレイヤー ID（観戦なら null）
 */

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
    /** @type {import('./move-plan.js').Step[]} */
    this.steps = [];
    this.active = false;
    /** @type {Array<[HTMLElement, () => void]>} */
    this.listeners = [];
  }

  /**
   * @param {unknown} args
   * @param {boolean} isCurrentPlayerActive
   */
  onEnteringState(args, isCurrentPlayerActive) {
    this.steps = [];
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
    this.steps = [];
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

  /** 計画の今の形に合わせて、画面（部屋・カード・ボタン・タイトル）を作り直す */
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

    // 経路の印
    this.steps.forEach((step, i) => {
      const room = refs?.rooms.get(step.pos);
      if (room !== undefined) {
        room.classList.add(CLASS_ROOM_ROUTE);
        room.dataset.order = String(i + 1);
      }
    });

    const summary = describePlan(board, this.steps, translatePlan);
    const pending = pendingRoom(board, this.steps);
    if (pending !== null) {
      this.renderPayment(board, pending, summary, playerAreas, me);
    } else {
      this.renderRooms(board, summary, refs);
    }

    if (this.steps.length > 0 && isComplete(board, this.steps)) {
      bar.addActionButton(_('Confirm move'), () => this.confirm());
    }
    if (this.steps.length === 0 && !board.canMove) {
      bar.addActionButton(_('Stay'), () => this.confirm());
    }
    if (this.steps.length > 0) {
      bar.addActionButton(_('Undo last room'), () => {
        this.steps = undoRoom(this.steps);
        this.render();
      }, { color: 'secondary' });
      bar.addActionButton(_('Reset'), () => {
        this.steps = [];
        this.render();
      }, { color: 'alert' });
    }
  }

  /**
   * 支払い待ちの部屋がないとき: 次に入れる部屋を光らせる。
   * @param {import('./move-plan.js').MoveBoard} board
   * @param {string} summary
   * @param {import('../board/Board.js').BoardRefs | null} refs
   */
  renderRooms(board, summary, refs) {
    const rooms = nextRooms(board, this.steps);
    if (this.steps.length === 0 && !board.canMove) {
      this.bga.statusBar.setTitle(_('${you} cannot afford to move'));
    } else if (this.steps.length === 0) {
      this.bga.statusBar.setTitle(_('${you} must choose the next room to enter'));
    } else {
      this.bga.statusBar.setTitle(_('Route: ${route}'), { route: summary });
    }
    for (const pos of rooms) {
      const element = refs?.rooms.get(pos);
      if (element === undefined) {
        continue;
      }
      element.classList.add(CLASS_ROOM_SELECTABLE);
      const handler = () => {
        this.steps = [...this.steps, { pos, payments: [] }];
        this.render();
      };
      element.addEventListener('click', handler);
      this.listeners.push([element, handler]);
    }
  }

  /**
   * 支払い待ちの部屋があるとき: 使える手段だけ選べるようにする。
   * @param {import('./move-plan.js').MoveBoard} board
   * @param {import('./move-plan.js').BoardRoom} pending
   * @param {string} summary
   * @param {import('../board/PlayerAreas.js').PlayerAreas | null} playerAreas
   * @param {number | null} me
   */
  renderPayment(board, pending, summary, playerAreas, me) {
    const options = payOptions(board, this.steps);
    const paid = this.steps[this.steps.length - 1].payments.length;
    this.bga.statusBar.setTitle(_('Pay for room ${room} (${paid} of ${locks}): ${route}'), {
      room: pending.pos, paid, locks: pending.level, route: summary,
    });
    /** @param {import('./move-plan.js').PayItem} item */
    const pay = (item) => {
      this.steps = addPayment(this.steps, item);
      this.render();
    };
    for (const [src, select] of /** @type {const} */ ([[SRC.TABLEAU, 'composed'], [SRC.HAND, 'hand']])) {
      const choices = cardChoices(options, src);
      if (choices.size === 0 || playerAreas === null || me === null) {
        continue;
      }
      /** @type {import('../cards/selection.js').SelectionOptions} */
      const config = {
        ids: choices.keys(),
        mode: 'single',
        onChange: (ids) => {
          const item = ids.length === 0 ? undefined : choices.get(Number(ids[0]));
          if (item !== undefined) {
            pay(item);
          }
        },
      };
      if (select === 'composed') {
        playerAreas.selectComposed(me, config);
      } else {
        playerAreas.selectHand(config);
      }
    }
    const bar = this.bga.statusBar;
    const find = (/** @type {string} */ src) => options.find((o) => o.src === src);
    const light = find(SRC.LIGHT);
    if (light !== undefined) {
      const cost = lightCostOf(board, lightUsesOf(this.steps) + 1);
      bar.addActionButton(_('Inner light (${cost})').replace('${cost}', String(cost)), () => pay(light), { color: 'secondary' });
    }
    const token = find(SRC.TOKEN);
    if (token !== undefined) {
      bar.addActionButton(_('Wild move key'), () => pay(token), { color: 'secondary' });
    }
    const effect = find(SRC.EFFECT);
    if (effect !== undefined) {
      bar.addActionButton(_('Effect key'), () => pay(effect), { color: 'secondary' });
    }
  }

  /** 経路と支払いを 1 回で送る（JsonParam には JSON 文字列で渡す。姉妹 PE と同じ）。払える移動が無いときは空の経路（Q-38） */
  confirm() {
    if (this.board === null) {
      return;
    }
    this.bga.actions.performAction('actMove', { plan: JSON.stringify(toInput(this.steps)) });
  }
}
