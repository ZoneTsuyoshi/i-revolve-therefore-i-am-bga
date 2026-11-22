/**
 * フリーアクションの操作 UI（手札を捨てて心光を得る `actDiscardForLight`、目標カードの獲得 `actClaimObjective`。仕様書 006 §6.2、010 §2.4）。
 * ターンプレイヤーが操作する状態（Move を含む）から共通に使う。状態クラスは「ホスト」として、ボタンの追加・送信・画面の作り直しを渡す。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { claimableObjectives, canUseFreeActions, discardLightGain, toIds } from './choice-model.js';
import { claimView } from './action-bar-model.js';

/**
 * @typedef {object} FreeActionsHost
 * @property {Bga} bga
 * @property {() => { playerAreas: import('../board/PlayerAreas.js').PlayerAreas | null, common: any, me: number | null, texts: import('../ui/texts.js').CardTexts | null }} getDeps
 * @property {() => number} turnPlayer ターンプレイヤーの ID
 * @property {(label: string, onClick: () => void, params?: { color?: 'primary' | 'secondary' | 'alert', disabled?: boolean, tooltip?: string }) => void} button
 * @property {(action: string, args?: Record<string, unknown>) => Promise<boolean>} send
 * @property {() => void} render 画面を作り直す
 * @property {(ids: number[], gain: number) => void} [onDiscarded] 捨てるのが受け付けられたとき、計画の盤面から捨てたカードを外して心光を増やす
 */

export class FreeActionsUi {
  /**
   * @param {FreeActionsHost} host
   */
  constructor(host) {
    this.host = host;
    this.discardMode = false;
    /** @type {number[]} */
    this.discardSelected = [];
    /** @type {string | null} 獲得するつもりで選んだ目標カードのキー（`O001`。010 §2.4） */
    this.claimSelected = null;
  }

  /** 入場・離脱のとき: 変換モードと目標の選択を戻す */
  reset() {
    this.discardMode = false;
    this.discardSelected = [];
    this.claimSelected = null;
  }

  /**
   * ターンプレイヤーだけに、フリーアクションを足す（010 §2.4）。手札を捨てて心光を得るのは手札の区画の見出しの小さなボタン、
   * 目標の獲得は共通の場の獲得できる目標カードを押して選び、青の「Claim goal」で確定する（獲得できる目標がなければ何も出さない）。
   */
  add() {
    const host = this.host;
    if (!canUseFreeActions(host.getDeps().me, host.turnPlayer())) {
      return;
    }
    host.getDeps().playerAreas?.showDiscardButton({
      label: _('Discard 2 for Psychic Light'),
      tooltip: _('Discard Memory cards from your hand. Every 2 cards\' worth gives 1 Psychic Light (a level 2 card counts as 2)'),
      onClick: () => {
        this.discardMode = true;
        this.discardSelected = [];
        host.render();
      },
    });
    this.setClaimListener(true);
    this.addClaimUi();
  }

  /**
   * 目標の現在値の通知（N23）で、獲得できる目標の表示を作り直すか。
   * @param {boolean} on
   */
  setClaimListener(on) {
    const common = this.host.getDeps().common;
    if (common !== null) {
      common.onObjectiveStatus = on ? () => this.host.render() : null;
    }
  }

  /** 獲得できる目標カードを「選べる」で光らせ、選んだものの「Claim goal」ボタンを出す */
  addClaimUi() {
    const { common, texts, playerAreas } = this.host.getDeps();
    const view = claimView(claimableObjectives(common?.objectiveStatus ?? new Map()), this.claimSelected);
    this.claimSelected = view.selected?.key ?? null;
    if (view.keys.length === 0) {
      return;
    }
    if (common?.goalStock !== null && common?.goalStock !== undefined) {
      playerAreas?.selection.start(common.goalStock, {
        ids: view.keys,
        mode: 'single',
        selected: this.claimSelected === null ? [] : [this.claimSelected],
        onChange: (picked) => {
          this.claimSelected = picked.length > 0 ? String(picked[0]) : null;
          this.host.render();
        },
      });
    }
    const goal = view.selected;
    if (goal !== null) {
      const name = texts?.cards[goal.key]?.name ?? goal.key;
      const label = _('Claim goal: ${name} (+${points})').replace('${name}', name).replace('${points}', String(goal.points));
      this.host.button(label, () => {
        this.host.send('actClaimObjective', { objectiveId: goal.id });
      });
    }
  }

  /** 「手札を捨てて心光を得る」の選択中の画面 */
  drawDiscard() {
    const host = this.host;
    const areas = host.getDeps().playerAreas;
    const gain = discardLightGain(this.discardSelected.map((id) => areas?.handLevel(id) ?? 1));
    host.bga.statusBar.setTitle(_('Select Memory cards to discard for Psychic Light (gain ${light})'), { light: gain });
    areas?.selectHand({
      ids: areas.handData.keys(),
      mode: 'multiple',
      selected: this.discardSelected,
      onChange: (ids) => {
        this.discardSelected = toIds(ids);
        host.render();
      },
    });
    host.button(_('Discard for Psychic Light'), () => {
      const cardIds = this.discardSelected;
      host.send('actDiscardForLight', { cardIds }).then((ok) => {
        if (ok) {
          this.discardMode = false;
          this.discardSelected = [];
          host.onDiscarded?.(cardIds, gain);
          host.render();
        }
      });
    }, { disabled: gain < 1, tooltip: _('You need at least 2 cards\' worth to gain Psychic Light') });
    host.button(_('Cancel'), () => {
      this.discardMode = false;
      this.discardSelected = [];
      host.render();
    }, { color: 'alert' });
  }
}
