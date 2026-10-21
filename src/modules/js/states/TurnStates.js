/**
 * ターン中の単純な操作 UI（U-10）: 60 自己構成フェイズ、51 床アクションの選択、72 手札上限（仕様書 009 §4、006 §5.3・§5.5・§8）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { int } from '../ui/dom.js';
import { ActionState } from './ActionState.js';
import { list, obj } from './args.js';
import { discardExcessReady, floorOptionKey, plainText } from './choice-model.js';

/**
 * 60: 自己構成フェイズの窓。自己構成（フェイズに 1 回）・自己構成トークン・精神分析トークンを任意の順に使い、「End construct phase」で終える。
 * 使えないものは無効にする（args の可否どおり。空撃ち不可）。フリーアクションのボタンも出る。
 */
export class ConstructState extends ActionState {
  draw() {
    const a = obj(this.args);
    const composeTokens = int(a.composeTokens);
    const analysisTokens = int(a.analysisTokens);
    this.bga.statusBar.setTitle(_('${you} may compose, analyze, or end the compose phase'));
    this.button(_('Construct a card'), () => this.send('actStartCompose'), {
      disabled: a.phaseComposeUsed === true,
      tooltip: a.phaseComposeUsed === true ? _('You already composed in the compose phase') : undefined,
    });
    this.button(_('Use construct token (${count})').replace('${count}', String(composeTokens)), () => this.send('actUseComposeToken'), {
      color: 'secondary',
      disabled: a.canComposeToken !== true,
      tooltip: composeTokens < 1 ? _('You have no construct token') : (a.canComposeToken === true ? undefined : _('There is no card you can compose')),
    });
    this.button(_('Use psychoanalysis token (${count})').replace('${count}', String(analysisTokens)), () => this.send('actUseAnalysisToken'), {
      color: 'secondary',
      disabled: a.canAnalysisToken !== true,
      tooltip: analysisTokens < 1 ? _('You have no psychoanalysis token') : (a.canAnalysisToken === true ? undefined : _('You cannot pay for any psychoanalysis now')),
    });
    this.button(_('End construct phase'), () => this.send('actEndConstruct'), { color: 'alert' });
  }
}

/**
 * 分岐のある床カード（「/」の左右）の、分岐ごとの床アクションの英語文。`_()` の抽出のため文字列はここに書く。
 * @param {string} floorId
 * @returns {string[]}
 */
function branchTexts(floorId) {
  switch (floorId) {
    case 'F09': return [_('Take 2 open cards {open}'), _('Gain 2 Psychic Light {light}')];
    case 'F12': return [_('Take a Construction action with energy {energy} -1'), _('Draw 2 Memory cards {draw}')];
    default: return [];
  }
}

/**
 * 51: 床アクションか代替かの選択（「/」の分岐を含む）。選択肢を床アクションの英語文つきのボタンで出す。
 */
export class FloorChooseState extends ActionState {
  draw() {
    const a = obj(this.args);
    const floorId = typeof a.floor === 'string' ? a.floor : '';
    const texts = this.getDeps().texts;
    const room = typeof a.roomName === 'string' ? a.roomName : '';
    this.bga.statusBar.setTitle(_('${you} must choose a floor action'));
    for (const option of list(a.options).map(obj)) {
      const key = floorOptionKey({ kind: String(option.kind ?? ''), discount: option.discount });
      let text;
      if (key.type === 'floor') {
        text = texts?.cards[floorId]?.action ?? '';
      } else if (key.type === 'branch') {
        text = branchTexts(floorId)[key.branch] ?? texts?.cards[floorId]?.action ?? '';
      } else {
        text = texts?.recall[key.key] ?? '';
      }
      const label = plainText(text) || String(option.kind);
      const prefix = key.type === 'recall' || room === '' ? '' : `${room}: `;
      this.button(prefix + label, () => this.send('actChooseFloorAction', { kind: String(option.kind ?? '') }), {
        color: key.type === 'recall' ? 'secondary' : 'primary',
      });
    }
  }
}

/**
 * 72: 手札が上限（8 枚）を超えたら、超過分ちょうどを選んで捨てる。フリーアクション（心光への変換）で超過が解消したら、サーバーが自動で先へ進める。
 */
export class HandLimitState extends ActionState {
  constructor(/** @type {Bga} */ bga, /** @type {() => import('./ActionState.js').ActionDeps} */ getDeps) {
    super(bga, getDeps);
    /** @type {number[]} */
    this.selected = [];
  }

  begin() {
    this.selected = [];
  }

  end() {
    this.selected = [];
  }

  /** 今の超過枚数。手札の枚数は画面から数え（フリーアクションで減る）、分からなければ args の値 */
  excess() {
    const a = obj(this.args);
    const hand = this.getDeps().playerAreas?.handData;
    const limit = int(a.limit) || 8;
    return hand === undefined || hand.size === 0 ? int(a.excess) : Math.max(0, hand.size - limit);
  }

  draw() {
    const excess = this.excess();
    this.bga.statusBar.setTitle(_('${you} must discard ${count} card(s) down to the hand limit'), { count: excess });
    this.pickHand({
      ids: this.getDeps().playerAreas?.handData.keys() ?? [],
      mode: 'multiple',
      selected: this.selected,
      onChange: (selected) => {
        this.selected = selected;
        this.render();
      },
    });
    this.button(_('Discard selected cards'), () => this.send('actDiscardExcess', { cardIds: this.selected }), {
      disabled: !discardExcessReady(excess, this.selected),
      tooltip: _('Choose exactly the excess cards'),
    });
  }
}
