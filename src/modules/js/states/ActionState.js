/**
 * 操作 UI の状態クラスの土台（U-10。仕様書 009 §4、006 §6）。入場で args を読み、`draw()` で画面（ステータスバーのボタン・カードの選択・部屋の選択）を作り、
 * 選択が変わるたびに `render()` で作り直す（U-3 の MoveState と同じ作り）。ターンプレイヤーには、フリーアクション
 * （手札を捨てて心光を得る `actDiscardForLight`、目標カードの獲得 `actClaimObjective`）のボタンを足す。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { SelectionController, clearRooms, paintRooms } from '../cards/selection.js';
import { int } from '../ui/dom.js';
import { Tips } from '../ui/Tips.js';
import { sortButtons } from './action-bar-model.js';
import { toIds } from './choice-model.js';
import { FreeActionsUi } from './FreeActionsUi.js';

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
    this.free = new FreeActionsUi({
      bga,
      getDeps,
      turnPlayer: () => this.turnPlayer(),
      button: (label, onClick, params) => this.button(label, onClick, params),
      send: (action, args) => this.send(action, args),
      render: () => this.render(),
      onDiscarded: (ids, gain) => this.onDiscardedForLight(ids, gain),
    });
    /** @type {Array<{ label: string, onClick: () => void, params: Record<string, any> }> | null} 画面を作っている間のボタン（青 → 白 → 赤に並べ直してから出す） */
    this.queue = null;
  }

  /**
   * @param {unknown} args
   * @param {boolean} isCurrentPlayerActive
   */
  onEnteringState(args, isCurrentPlayerActive) {
    this.args = args;
    Tips.dismiss();
    this.active = isCurrentPlayerActive;
    this.session++;
    this.free.reset();
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
    Tips.dismiss();
    this.active = false;
    this.session++;
    this.clearUi();
    this.clearPanel();
    this.free.setClaimListener(false);
    this.free.reset();
    this.end();
    this.args = null;
  }

  /**
   * 入場のとき（args から計画を作る）。サブクラスが上書きする。
   * @param {unknown} _args
   */
  begin(_args) {
  }

  /**
   * 手札を捨てて心光を得るのが受け付けられたとき。サーバーは状態に入り直さず args も送り直さないので、
   * args 由来のモデル（手札・心光）を持つ状態が、捨てたカードを外し、心光を増やす。サブクラスが上書きする。
   * @param {number[]} _ids 捨てた手札
   * @param {number} _gain 得た心光
   */
  onDiscardedForLight(_ids, _gain) {
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
    this.queue = [];
    if (this.free.discardMode) {
      this.free.drawDiscard();
    } else {
      this.draw();
      if (this.freeActions) {
        this.free.add();
      }
    }
    this.flushButtons();
  }

  /** ステータスバーのボタン、部屋の印、カードの選択（トークン・目標・精神カード・見出しのボタンを含む）を外す */
  clearUi() {
    this.queue = null;
    this.bga.statusBar.removeActionButtons();
    this.clearRooms();
    this.getDeps().playerAreas?.clearSelection();
  }

  /**
   * ステータスバーのボタンを足す。`render` の間は一度ためて、青 → 白 → 赤の順に並べ直して出す（取り消し系は右端。010 §2.2）。
   * @param {string} label
   * @param {() => void} onClick
   * @param {{ color?: 'primary' | 'secondary' | 'alert', disabled?: boolean, tooltip?: string, title?: string }} [params]
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
   * 回廊の部屋を選べるようにする（クリックで onPick）。選べる部屋は `ir-can`、選択済みの部屋は `ir-picked` と順番の札、他の部屋は `ir-off`（011 §5.5）。
   * @param {Iterable<number>} rooms 選べる部屋
   * @param {(pos: number) => void} onPick
   * @param {number[]} [chosen] 選択済みの部屋（順番に札を出す）
   */
  pickRooms(rooms, onPick, chosen = []) {
    const refs = this.getDeps().refs;
    const selectable = [...rooms];
    for (const pos of selectable) {
      const element = refs?.rooms.get(pos);
      if (element === undefined) {
        continue;
      }
      const handler = () => onPick(pos);
      element.addEventListener('click', handler);
      this.listeners.push([element, handler]);
    }
    if (refs !== null && refs !== undefined) {
      paintRooms(refs.rooms, { can: selectable.filter((pos) => !chosen.includes(pos)), route: chosen });
    }
  }

  clearRooms() {
    for (const [element, handler] of this.listeners) {
      element.removeEventListener('click', handler);
    }
    this.listeners = [];
    const rooms = this.getDeps().refs?.rooms;
    if (rooms !== undefined) {
      clearRooms(rooms);
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
        // 選べない場札も、選択モードに入れて暗くする（押すと理由を出す。011 §5.3）
        playerAreas.selection.start(stock, { ids: [], mode: 'multiple' });
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

  // ---- 精神カードの選択 ----

  /**
   * 精神トラックのカードを選べるようにする（行き先の選択。押すとボタンと同じ選択になる。010 §2.4）。
   * @param {Iterable<string>} ids 選べる精神カード（`R1a`）
   * @param {string | null} selected 選択中のカード
   * @param {(id: string | null) => void} onChange 押したカード（選択を外したら null）
   */
  pickSpirits(ids, selected, onChange) {
    const { playerAreas, common } = this.getDeps();
    if (playerAreas === null || common === null) {
      return;
    }
    for (const id of ids) {
      const stock = common.spiritStocks.get(id);
      if (stock === undefined) {
        continue;
      }
      playerAreas.selection.start(stock, {
        ids: [id],
        mode: 'single',
        selected: selected === id ? [id] : [],
        onChange: (picked) => onChange(picked.length > 0 ? id : null),
      });
    }
  }

  // ---- 候補のカード置き場 ----

  /**
   * 一時的な候補のカード（初期自我・強化自我）を並べて、1 枚選べるようにする。置き場は環の上（`#ir-main` の先頭）に、
   * 箱ではなくレール（上端の線とラベル）で出す（011 §3.3）。
   * @param {string[]} keys 候補のカード（`I05`）
   * @param {string | null} selected 選択中
   * @param {(key: string | null) => void} onChange
   * @param {string} label レールのラベル（翻訳済み。例: `Choose an Initial Ego`）
   * @returns {Promise<void>}
   */
  async showCandidates(keys, selected, onChange, label) {
    const deps = this.getDeps();
    const host = deps.refs?.root.querySelector('#ir-main');
    if (host === null || host === undefined || deps.cards === null || deps.BgaCards === undefined) {
      return;
    }
    const session = this.session;
    this.clearPanel();
    const element = document.createElement('div');
    element.className = `${CLASS_PICK_PANEL} ir-rail`;
    element.setAttribute('role', 'group');
    element.setAttribute('aria-label', label);
    const title = document.createElement('div');
    title.className = 'ir-rail-title';
    const labelElement = document.createElement('span');
    labelElement.className = 'ir-rail-label';
    labelElement.textContent = label;
    title.append(labelElement);
    const cards = document.createElement('div');
    cards.className = 'ir-pick-cards';
    element.append(title, cards);
    host.prepend(element);
    this.panelElement = element;
    const stock = new deps.BgaCards.LineStock(deps.cards.land, cards, { gap: 'var(--ir-gap)', wrap: 'wrap' });
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
}
