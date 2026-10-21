/**
 * 状態 71 `EffectChoice`: 効果の選択の操作 UI（仕様書 009 §4、006 §7.4、008 §6.4）。選択の種類（`choiceKind`）ごとに、
 * 手札・場札・自己構成済みカード・回廊の部屋・ステータスバーのボタンのどれで答えるかを切り替える。回答は `actResolveChoice` の
 * `payload`（JSON 文字列。形は choice-model.js の `answer`）。スキップできる種類には「Skip」を出す（`actSkipChoice`）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { ActionState } from './ActionState.js';
import { list, obj } from './args.js';
import {
  KIND, answer, choiceFromArgs, costText, entrepreneurHand, entrepreneurRooms, firstRooms, pairHand, pairTableau, pickCount, pickReady,
  plainText, secondRooms, spiritParts, swapHandFieldReady, toIds,
} from './choice-model.js';

/**
 * 鍵の名前（翻訳済み）。
 * @param {string} type
 * @returns {string}
 */
function keyName(type) {
  switch (type) {
    case 'WILL': return _('Will');
    case 'EMOTION': return _('Emotion');
    case 'WILD': return _('Wild');
    default: return _('Wisdom');
  }
}

export class ChoiceState extends ActionState {
  /**
   * @param {Bga} bga
   * @param {() => import('./ActionState.js').ActionDeps} getDeps
   */
  constructor(bga, getDeps) {
    super(bga, getDeps);
    /** @type {import('./choice-model.js').Choice | null} */
    this.choice = null;
    /** @type {number[]} 手札の選択 */
    this.hand = [];
    /** @type {number[]} 場札の選択 */
    this.field = [];
    /** @type {number | null} 自己構成済みカードの選択（SELECT_SWAP_SAME_COST） */
    this.tableau = null;
    /** @type {number[]} 部屋の選択 */
    this.rooms = [];
    /** @type {string | null} 強化自我の選択 */
    this.ego = null;
  }

  /** 選択中の値を捨てる */
  reset() {
    this.hand = [];
    this.field = [];
    this.tableau = null;
    this.rooms = [];
    this.ego = null;
  }

  /** @param {unknown} args */
  begin(args) {
    this.choice = choiceFromArgs(args);
    this.reset();
    if (this.choice.kind === KIND.PICK_ENHANCED_EGO) {
      this.showCandidates(this.choice.egoCandidates, null, (key) => {
        this.ego = key;
        this.render();
      });
    }
  }

  end() {
    this.choice = null;
    this.reset();
  }

  /**
   * 回答を送る。
   * @param {Record<string, unknown>} payload
   */
  reply(payload) {
    this.send('actResolveChoice', { payload: JSON.stringify(payload) });
  }

  /**
   * 「Confirm」ボタン。
   * @param {boolean} ready
   * @param {() => Record<string, unknown>} payload
   */
  confirmButton(ready, payload) {
    this.button(_('Confirm'), () => this.reply(payload()), { disabled: !ready });
  }

  /**
   * 出典のカード名（分かれば）。
   * @param {string} source
   * @returns {string}
   */
  sourceName(source) {
    return this.getDeps().texts?.cards[source]?.name ?? source;
  }

  /**
   * 手札を選ぶ（選択が変わったら hand に入れて作り直す）。
   * @param {number[]} ids 選べる手札
   * @param {'single' | 'multiple'} mode
   */
  chooseHand(ids, mode) {
    this.pickHand({
      ids,
      mode,
      selected: this.hand,
      onChange: (selected) => {
        this.hand = selected;
        this.render();
      },
    });
  }

  /**
   * 場札を選ぶ（選択が変わったら field に入れて作り直す）。
   * @param {number[]} ids 選べる場札
   */
  chooseField(ids) {
    this.pickOpen({
      ids,
      selected: this.field,
      onChange: (selected) => {
        this.field = selected;
        this.render();
      },
    });
  }

  draw() {
    const choice = this.choice;
    if (choice === null) {
      return;
    }
    const bar = this.bga.statusBar;
    const data = choice.data;
    const playerAreas = this.getDeps().playerAreas;
    const handIds = () => [...(playerAreas?.handData.keys() ?? [])];
    switch (choice.kind) {
      case KIND.PICK_FIELD_CARD: {
        const count = pickCount(choice).exact ?? 1;
        bar.setTitle(_('${you} must take ${count} open card(s)'), { count });
        this.chooseField(toIds(list(data.candidates)));
        this.confirmButton(pickReady(choice, this.field), () => answer.cards(this.field));
        break;
      }
      case KIND.PICK_DISCARD: {
        const count = pickCount(choice).exact ?? 1;
        bar.setTitle(_('${you} must discard ${count} card(s) from your hand'), { count });
        this.chooseHand(handIds(), 'multiple');
        this.confirmButton(pickReady(choice, this.hand), () => answer.cards(this.hand));
        break;
      }
      case KIND.SELECT_DISCARD_FOR_LIGHT:
        bar.setTitle(_('${you} may discard Memory cards to gain Psychic Light (gain ${light})'), { light: this.hand.length });
        this.chooseHand(handIds(), 'multiple');
        this.confirmButton(this.hand.length > 0, () => answer.cards(this.hand));
        break;
      case KIND.SELECT_SWAP_HAND_FIELD:
        bar.setTitle(_('${you} may swap level 1 cards in your hand with open cards (choose the same number of each)'));
        this.chooseHand(handIds().filter((id) => playerAreas?.handLevel(id) === 1), 'multiple');
        this.chooseField(toIds(list(data.field)));
        this.confirmButton(this.hand.length > 0 && swapHandFieldReady(this.hand, this.field), () => answer.swapHandField(this.hand, this.field));
        break;
      case KIND.SELECT_SWAP_SAME_COST:
        this.drawSwapSameCost(choice);
        break;
      case KIND.CONFIRM_USE:
        bar.setTitle(_('${you} may use the effect of ${card}'), { card: this.sourceName(choice.source) });
        this.button(_('Use'), () => this.reply(answer.use(true)));
        this.button(_('Do not use'), () => this.reply(answer.use(false)), { color: 'secondary' });
        break;
      case KIND.CONFIRM_MOVE:
        bar.setTitle(_('${you} may move to the next room'));
        this.button(_('Move'), () => this.reply(answer.use(true)));
        this.button(_('Stay'), () => this.reply(answer.use(false)), { color: 'secondary' });
        break;
      case KIND.SWAP_ROOM_CARDS:
      case KIND.SWAP_ROOM_FLOORS:
        this.drawSwapRooms(choice);
        break;
      case KIND.WARP_ROOM:
        bar.setTitle(_('${you} may warp to any room'));
        this.pickRooms(toIds(list(data.rooms)), (pos) => {
          this.rooms = this.rooms[0] === pos ? [] : [pos];
          this.render();
        }, this.rooms);
        this.confirmButton(this.rooms.length === 1, () => answer.warp(this.rooms[0]));
        break;
      case KIND.ENTREPRENEUR_SWAP:
        this.drawEntrepreneur(choice);
        break;
      case KIND.PICK_ENHANCED_EGO:
        bar.setTitle(_('${you} must choose an Enhanced Ego card'));
        this.button(_('Confirm'), () => {
          if (this.ego !== null) {
            this.reply(answer.ego(this.ego));
          }
        }, { disabled: this.ego === null });
        break;
      case KIND.PICK_SPIRIT_BRANCH:
        this.drawSpiritBranch(choice);
        break;
      case KIND.ORDER_EFFECTS:
        bar.setTitle(_('${you} must choose which effect to resolve first'));
        list(data.candidates).map(obj).forEach((candidate, index) => {
          const source = String(candidate.source ?? '');
          const effect = this.getDeps().texts?.cards[source]?.effect;
          this.button(this.sourceName(source), () => this.reply(answer.index(index)), {
            tooltip: effect === undefined ? undefined : plainText(effect),
          });
        });
        break;
      case null:
        this.drawBranch(choice);
        break;
      default:
        bar.setTitle(_('${you} must make a choice'));
        break;
    }
    if (choice.skippable && choice.kind !== KIND.CONFIRM_USE && choice.kind !== KIND.CONFIRM_MOVE) {
      this.button(_('Skip'), () => this.send('actSkipChoice'), { color: 'secondary' });
    }
  }

  /**
   * SELECT_SWAP_SAME_COST: 自己構成済みカードを選び、同じエネルギーの手札を選ぶ。
   * @param {import('./choice-model.js').Choice} choice
   */
  drawSwapSameCost(choice) {
    const { playerAreas, me } = this.getDeps();
    this.bga.statusBar.setTitle(_('${you} may swap a constructed card with a Memory card of the same energy in your hand'));
    if (me !== null) {
      playerAreas?.selectComposed(me, {
        ids: pairTableau(choice.pairs),
        mode: 'single',
        selected: this.tableau === null ? [] : [this.tableau],
        onChange: (selected) => {
          this.tableau = selected.length === 0 ? null : Number(selected[0]);
          this.hand = this.hand.filter((id) => pairHand(choice.pairs, this.tableau).includes(id));
          this.render();
        },
      });
    }
    this.chooseHand(pairHand(choice.pairs, this.tableau), 'single');
    const tableau = this.tableau;
    this.confirmButton(tableau !== null && this.hand.length === 1, () => answer.swapSameCost(tableau ?? 0, this.hand[0]));
  }

  /**
   * SWAP_ROOM_CARDS / SWAP_ROOM_FLOORS: 部屋を 2 つ選ぶ。
   * @param {import('./choice-model.js').Choice} choice
   */
  drawSwapRooms(choice) {
    const kind = choice.kind ?? '';
    const rooms = toIds(list(choice.data.rooms));
    this.bga.statusBar.setTitle(kind === KIND.SWAP_ROOM_CARDS
      ? _('${you} may swap the Memory cards of 2 rooms of the same level')
      : _('${you} may swap the floor cards of 2 level 1 rooms'));
    const first = this.rooms.length > 0 ? this.rooms[0] : null;
    const selectable = this.rooms.length >= 2 ? [] : (first === null ? firstRooms(kind, rooms) : secondRooms(kind, rooms, first));
    this.pickRooms([...this.rooms, ...selectable], (pos) => {
      this.rooms = this.rooms.includes(pos) ? this.rooms.filter((r) => r !== pos) : [...this.rooms, pos];
      this.render();
    }, this.rooms);
    this.confirmButton(this.rooms.length === 2, () => answer.rooms(this.rooms[0], this.rooms[1]));
  }

  /**
   * ENTREPRENEUR_SWAP: 手札のカードと、人格のいない同レベルの部屋を選ぶ。
   * @param {import('./choice-model.js').Choice} choice
   */
  drawEntrepreneur(choice) {
    const playerAreas = this.getDeps().playerAreas;
    const rooms = toIds(list(choice.data.rooms));
    const levelOf = (/** @type {number} */ id) => playerAreas?.handLevel(id) ?? 1;
    this.bga.statusBar.setTitle(_('${you} may swap a card in your hand with the Memory card of a room without a persona (same level)'));
    const room = this.rooms.length > 0 ? this.rooms[0] : null;
    const handChosen = this.hand.length > 0 ? this.hand[0] : null;
    this.chooseHand(entrepreneurHand([...(playerAreas?.handData.keys() ?? [])], levelOf, room), 'single');
    this.pickRooms(entrepreneurRooms(rooms, handChosen === null ? null : levelOf(handChosen)), (pos) => {
      this.rooms = this.rooms[0] === pos ? [] : [pos];
      this.render();
    }, this.rooms);
    this.confirmButton(room !== null && handChosen !== null, () => answer.entrepreneur(handChosen ?? 0, room ?? 0));
  }

  /**
   * PICK_SPIRIT_BRANCH: 次の階層が 2 枚あるときの進む先。
   * @param {import('./choice-model.js').Choice} choice
   */
  drawSpiritBranch(choice) {
    this.bga.statusBar.setTitle(_('${you} must choose a spirit card to advance to'));
    const catalog = list(this.getDeps().catalog?.spirit).map(obj);
    for (const id of list(choice.data.candidates).map(String)) {
      const { tier, branch } = spiritParts(id);
      const cost = costText(obj(catalog.find((row) => row.id === id)?.cost), keyName);
      const label = _('Tier ${tier} ${branch}: ${cost}').replace('${tier}', String(tier)).replace('${branch}', branch).replace('${cost}', cost);
      this.button(label.replace(/\s+/g, ' ').trim(), () => this.reply(answer.spirit(id)));
    }
  }

  /**
   * 効果の分岐（種類なし）: 分岐の数だけ選択肢を出す。
   * @param {import('./choice-model.js').Choice} choice
   */
  drawBranch(choice) {
    const branches = Math.max(0, Number(choice.data.branches) || 0);
    const effect = this.getDeps().texts?.cards[choice.source]?.effect;
    this.bga.statusBar.setTitle(_('${you} must choose one of the effects'));
    for (let branch = 0; branch < branches; branch++) {
      this.button(_('Option ${number}').replace('${number}', String(branch + 1)), () => this.reply(answer.branch(branch)), {
        tooltip: effect === undefined ? undefined : plainText(effect),
      });
    }
  }
}
