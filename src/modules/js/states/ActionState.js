/**
 * 操作 UI の状態クラスの土台（U-10。仕様書 009 §4、006 §6）。入場で args を読み、`draw()` で画面（ステータスバーのボタン・カードの選択・部屋の選択）を作り、
 * 選択が変わるたびに `render()` で作り直す（U-3 の MoveState と同じ作り）。ターンプレイヤーには、フリーアクション
 * （手札を捨てて心光を得る `actDiscardForLight`、目標カードの獲得 `actClaimObjective`）のボタンを足す。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { SelectionController } from '../cards/selection.js';
import { int } from '../ui/dom.js';
import { claimableObjectives, canUseFreeActions, discardLightGain, toIds } from './choice-model.js';
import { CLASS_ROOM_ROUTE, CLASS_ROOM_SELECTABLE } from './MoveState.js';

/**
 * 状態クラスが盤面に触るための参照（Game.js が setup のあとで渡す）。
 * @typedef {object} ActionDeps
 * @property {import('../board/PlayerAreas.js').PlayerAreas | null} playerAreas
 * @property {import('../board/CommonArea.js').CommonArea | null} common
 * @property {import('../board/Board.js').BoardRefs | null} refs
 * @property {number | null} me 自分のプレイヤー ID（観戦なら null）
 * @property {import('../cards/managers.js').Managers | null} cards
 * @property {any} BgaCards
 * @property {import('../ui/texts.js').CardTexts | null} texts
 * @property {any} catalog `gamedatas.catalog`（精神カードのコストなど）
 */

/** 一時的な候補のカード置き場（初期自我のドラフト、強化自我の選択）の器のクラス */
export const CLASS_PICK_PANEL = 'ir-pick-panel';

export class ActionState {
  /**
   * @param {Bga} bga
   * @param {() => ActionDeps} getDeps setup のあとで作られる盤面への参照
   * @param {{ freeActions?: boolean }} [options] freeActions: フリーアクションのボタンを足すか（既定は足す）
   */
  constructor(bga, getDeps, options = {}) {
    this.bga = bga;
    this.getDeps = getDeps;
    this.freeActions = options.freeActions ?? true;
    this.active = false;
    /** @type {any} */
    this.args = null;
    /** 入場・離脱のたびに増やす。非同期の結果が古いかどうかの判定に使う */
    this.session = 0;
    /** @type {Array<[HTMLElement, () => void]>} */
    this.listeners = [];
    /** @type {HTMLElement | null} */
    this.panelElement = null;
    /** @type {import('../cards/selection.js').SelectionController | null} */
    this.panelSelection = null;
    // フリーアクション「手札を捨てて心光を得る」
    this.discardMode = false;
    /** @type {number[]} */
    this.discardSelected = [];
    /** @type {HTMLElement[]} */
    this.claimButtons = [];
  }

  /**
   * @param {unknown} args
   * @param {boolean} isCurrentPlayerActive
   */
  onEnteringState(args, isCurrentPlayerActive) {
    this.args = args;
    this.active = isCurrentPlayerActive;
    this.session++;
    this.discardMode = false;
    this.discardSelected = [];
    if (!isCurrentPlayerActive) {
      return;
    }
    this.begin(args);
    this.render();
  }

  /**
   * @param {unknown} _args
   * @param {boolean} _isCurrentPlayerActive
   */
  onLeavingState(_args, _isCurrentPlayerActive) {
    this.active = false;
    this.session++;
    this.clearUi();
    this.clearPanel();
    this.setClaimListener(false);
    this.end();
    this.args = null;
  }

  /**
   * 入場のとき（args から計画を作る）。サブクラスが上書きする。
   * @param {unknown} _args
   */
  begin(_args) {
  }

  /** 離脱のとき（計画を捨てる）。サブクラスが上書きする。 */
  end() {
  }

  /** 画面を作る（ステータスバーのタイトル・ボタン、カードや部屋の選択）。サブクラスが上書きする。 */
  draw() {
  }

  /** 今の計画に合わせて、画面を作り直す */
  render() {
    if (!this.active) {
      return;
    }
    this.clearUi();
    if (this.discardMode) {
      this.drawDiscardForLight();
      return;
    }
    this.draw();
    this.addFreeActionButtons();
  }

  /** ステータスバーのボタン、部屋の印、カードの選択を外す */
  clearUi() {
    this.bga.statusBar.removeActionButtons();
    this.claimButtons = [];
    this.clearRooms();
    this.getDeps().playerAreas?.clearSelection();
  }

  /**
   * ステータスバーのボタンを足す。
   * @param {string} label
   * @param {() => void} onClick
   * @param {{ color?: 'primary' | 'secondary' | 'alert', disabled?: boolean, tooltip?: string, title?: string }} [params]
   * @returns {HTMLButtonElement}
   */
  button(label, onClick, params = {}) {
    return this.bga.statusBar.addActionButton(label, onClick, params);
  }

  /**
   * サーバーのアクションを送る。
   * @param {string} action
   * @param {Record<string, unknown>} [args]
   * @returns {Promise<boolean>} サーバーが受け付けたら true（エラーならメッセージは BGA が出す）
   */
  send(action, args = {}) {
    return Promise.resolve(this.bga.actions.performAction(action, args)).then(() => true, () => false);
  }

  // ---- 部屋の選択 ----

  /**
   * 回廊の部屋を選べるようにする（クリックで onPick）。選択済みの部屋には順番の丸を出す。
   * @param {Iterable<number>} rooms 選べる部屋
   * @param {(pos: number) => void} onPick
   * @param {number[]} [chosen] 選択済みの部屋（順番に丸を出す）
   */
  pickRooms(rooms, onPick, chosen = []) {
    const refs = this.getDeps().refs;
    for (const pos of rooms) {
      const element = refs?.rooms.get(pos);
      if (element === undefined) {
        continue;
      }
      element.classList.add(CLASS_ROOM_SELECTABLE);
      const handler = () => onPick(pos);
      element.addEventListener('click', handler);
      this.listeners.push([element, handler]);
    }
    chosen.forEach((pos, i) => {
      const element = refs?.rooms.get(pos);
      if (element !== undefined) {
        element.classList.add(CLASS_ROOM_ROUTE);
        element.dataset.order = String(i + 1);
      }
    });
  }

  clearRooms() {
    for (const [element, handler] of this.listeners) {
      element.removeEventListener('click', handler);
    }
    this.listeners = [];
    for (const room of this.getDeps().refs?.rooms.values() ?? []) {
      room.classList.remove(CLASS_ROOM_SELECTABLE, CLASS_ROOM_ROUTE);
      delete room.dataset.order;
    }
  }

  // ---- 場札の選択 ----

  /**
   * 場札（共通の場の 6 枚）を選べるようにする。場札はスロットごとに別の Stock なので、全体の選択をここでまとめる。
   * @param {{ ids: Iterable<number>, selected: number[], onChange: (selected: number[]) => void }} options
   */
  pickOpen(options) {
    const { playerAreas, common } = this.getDeps();
    if (playerAreas === null || common === null) {
      return;
    }
    const allowed = new Set([...options.ids].map(String));
    let selected = options.selected.slice();
    for (const stock of common.openStocks) {
      const card = stock.getCards().find((/** @type {{ id: number | string }} */ c) => allowed.has(String(c.id)));
      if (card === undefined) {
        continue;
      }
      const id = int(card.id);
      playerAreas.selection.start(stock, {
        ids: [card.id],
        mode: 'multiple',
        selected: selected.includes(id) ? [card.id] : [],
        onChange: (ids) => {
          selected = ids.length > 0 ? [...selected.filter((s) => s !== id), id] : selected.filter((s) => s !== id);
          options.onChange(selected);
        },
      });
    }
  }

  // ---- 手札の選択 ----

  /**
   * 手札のカードを選べるようにする。
   * @param {{ ids: Iterable<number>, mode: 'single' | 'multiple', selected: number[], onChange: (selected: number[]) => void }} options
   */
  pickHand(options) {
    this.getDeps().playerAreas?.selectHand({
      ids: options.ids,
      mode: options.mode,
      selected: options.selected,
      onChange: (ids) => options.onChange(toIds(ids)),
    });
  }

  // ---- 候補のカード置き場 ----

  /**
   * 一時的な候補のカード（初期自我・強化自我）を並べて、1 枚選べるようにする。置き場は盤面の先頭に出す。
   * @param {string[]} keys 候補のカード（`I05`）
   * @param {string | null} selected 選択中
   * @param {(key: string | null) => void} onChange
   * @returns {Promise<void>}
   */
  async showCandidates(keys, selected, onChange) {
    const deps = this.getDeps();
    const root = deps.refs?.root;
    if (root === undefined || deps.cards === null || deps.BgaCards === undefined) {
      return;
    }
    const session = this.session;
    this.clearPanel();
    const element = document.createElement('div');
    element.className = CLASS_PICK_PANEL;
    root.prepend(element);
    this.panelElement = element;
    const stock = new deps.BgaCards.LineStock(deps.cards.land, element, { gap: 'var(--ir-gap)', wrap: 'wrap' });
    await Promise.all(keys.map((key) => stock.addCard({ id: key, key })));
    if (session !== this.session || this.panelElement !== element) {
      return;
    }
    const controller = new SelectionController();
    this.panelSelection = controller;
    controller.start(stock, {
      mode: 'single',
      selected: selected === null ? [] : [selected],
      onChange: (ids) => onChange(ids.length === 0 ? null : String(ids[0])),
    });
  }

  clearPanel() {
    this.panelSelection?.clear();
    this.panelSelection = null;
    this.panelElement?.remove();
    this.panelElement = null;
  }

  // ---- フリーアクション ----

  /** @returns {number} ターンプレイヤー（args の turnPlayer） */
  turnPlayer() {
    return int(this.args?.turnPlayer);
  }

  /** ターンプレイヤーだけに、フリーアクションのボタンを足す */
  addFreeActionButtons() {
    if (!this.freeActions || !canUseFreeActions(this.getDeps().me, this.turnPlayer())) {
      return;
    }
    this.button(_('Discard 2 for Psychic Light'), () => {
      this.discardMode = true;
      this.discardSelected = [];
      this.render();
    }, {
      color: 'secondary',
      tooltip: _('Discard Memory cards from your hand. Every 2 cards\' worth gives 1 Psychic Light (a level 2 card counts as 2)'),
    });
    this.setClaimListener(true);
    this.refreshClaimButtons();
  }

  /**
   * 目標の現在値の通知（N23）で獲得ボタンを更新するか。
   * @param {boolean} on
   */
  setClaimListener(on) {
    const common = this.getDeps().common;
    if (common !== null) {
      common.onObjectiveStatus = on ? () => this.refreshClaimButtons() : null;
    }
  }

  /** 獲得できる目標カードごとにボタンを出す。無ければ無効のボタンを 1 つ出す（サーバーの claimable が来たら有効になる） */
  refreshClaimButtons() {
    for (const button of this.claimButtons) {
      button.remove();
    }
    this.claimButtons = [];
    if (!this.active || this.discardMode) {
      return;
    }
    const { common, texts } = this.getDeps();
    const claimable = claimableObjectives(common?.objectiveStatus ?? new Map());
    if (claimable.length === 0) {
      this.claimButtons.push(this.button(_('Claim goal'), () => {}, {
        color: 'secondary', disabled: true, tooltip: _('No goal card can be claimed now'),
      }));
      return;
    }
    for (const goal of claimable) {
      const name = texts?.cards[goal.key]?.name ?? goal.key;
      const label = _('Claim goal: ${name} (+${points})').replace('${name}', name).replace('${points}', String(goal.points));
      this.claimButtons.push(this.button(label, () => {
        this.send('actClaimObjective', { objectiveId: goal.id });
      }, { color: 'secondary' }));
    }
  }

  /** 「手札を捨てて心光を得る」の選択中の画面 */
  drawDiscardForLight() {
    const gain = discardLightGain(this.discardSelected.map((id) => this.getDeps().playerAreas?.handLevel(id) ?? 1));
    this.bga.statusBar.setTitle(_('Select Memory cards to discard for Psychic Light (gain ${light})'), { light: gain });
    this.pickHand({
      ids: this.getDeps().playerAreas?.handData.keys() ?? [],
      mode: 'multiple',
      selected: this.discardSelected,
      onChange: (selected) => {
        this.discardSelected = selected;
        this.render();
      },
    });
    this.button(_('Discard for Psychic Light'), () => {
      const cardIds = this.discardSelected;
      this.send('actDiscardForLight', { cardIds }).then((ok) => {
        if (ok) {
          this.discardMode = false;
          this.discardSelected = [];
          this.render();
        }
      });
    }, { disabled: gain < 1, tooltip: _('You need at least 2 cards\' worth to gain Psychic Light') });
    this.button(_('Cancel'), () => {
      this.discardMode = false;
      this.discardSelected = [];
      this.render();
    }, { color: 'secondary' });
  }
}
