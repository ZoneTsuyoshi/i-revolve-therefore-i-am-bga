/**
 * 準備の操作 UI（U-10）: 21 初期自我のドラフト、24 人格の配置（仕様書 009 §4、006 §4、001 §4）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { ActionState } from './ActionState.js';
import { list, privateOf } from './args.js';
import { toIds } from './choice-model.js';

/**
 * 21: 回ってきた山から初期自我を 1 枚選ぶ。候補は本人にだけ届く（`_private`）。候補のカードを並べて選び、「Confirm」で送る。
 */
export class SetupEgoPickState extends ActionState {
  /**
   * @param {Bga} bga
   * @param {() => import('./ActionState.js').ActionDeps} getDeps
   */
  constructor(bga, getDeps) {
    super(bga, getDeps, { freeActions: false });
    /** @type {string[]} */
    this.candidates = [];
    /** @type {string | null} */
    this.selected = null;
  }

  /** @param {unknown} args */
  begin(args) {
    this.candidates = list(privateOf(args).candidates).map(String);
    this.selected = null;
    this.showCandidates(this.candidates, null, (key) => {
      this.selected = key;
      this.render();
    });
  }

  end() {
    this.candidates = [];
    this.selected = null;
  }

  draw() {
    this.bga.statusBar.setTitle(_('${you} must choose an initial ego card'));
    this.button(_('Confirm'), () => {
      if (this.selected !== null) {
        this.send('actPickInitialEgo', { egoId: this.selected });
      }
    }, { disabled: this.selected === null });
  }
}

/**
 * 24: まだミープルがいない四隅（レベル 2 の部屋）から 1 つ選んで置く。光る部屋を選び、「Confirm」で送る。
 */
export class SetupMeeplePlaceState extends ActionState {
  /**
   * @param {Bga} bga
   * @param {() => import('./ActionState.js').ActionDeps} getDeps
   */
  constructor(bga, getDeps) {
    super(bga, getDeps, { freeActions: false });
    /** @type {number[]} */
    this.corners = [];
    /** @type {number | null} */
    this.chosen = null;
  }

  /** @param {unknown} args */
  begin(args) {
    this.corners = toIds(list(/** @type {any} */ (args)?.freeCorners));
    this.chosen = null;
  }

  end() {
    this.corners = [];
    this.chosen = null;
  }

  draw() {
    this.bga.statusBar.setTitle(_('${you} must place your persona in a corner room'));
    this.pickRooms(this.corners, (pos) => {
      this.chosen = this.chosen === pos ? null : pos;
      this.render();
    }, this.chosen === null ? [] : [this.chosen]);
    this.button(_('Confirm'), () => {
      if (this.chosen !== null) {
        this.send('actPlaceMeeple', { pos: this.chosen });
      }
    }, { disabled: this.chosen === null });
  }
}
