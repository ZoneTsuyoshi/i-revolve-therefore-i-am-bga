// テスト用の最小の偽の DOM と偽の bga-cards / bga-animations（Node。ブラウザの代わりに setup を通すため）。テストファイルではない
export class FakeElement {
  /** @param {string} tag */
  constructor(tag) {
    this.tag = tag;
    this.id = '';
    this.className = '';
    /** @type {Record<string, string>} */
    this.dataset = {};
    /** @type {Record<string, string>} */
    this.attrs = {};
    /** @type {FakeElement[]} */
    this.children = [];
    /** @type {FakeElement | null} */
    this.parentElement = null;
    this.style = { setProperty() {}, backgroundImage: '', backgroundSize: '', backgroundPosition: '', aspectRatio: '' };
    this.textContent = '';
    const self = this;
    this.classList = {
      add: (/** @type {string[]} */ ...names) => names.forEach((n) => { if (!self.hasClass(n)) { self.className = `${self.className} ${n}`.trim(); } }),
      toggle: (/** @type {string} */ name, /** @type {boolean} */ on) => {
        const has = self.hasClass(name);
        if (on && !has) { self.className = `${self.className} ${name}`.trim(); }
        if (!on && has) { self.className = self.className.split(/\s+/).filter((n) => n !== name).join(' '); }
      },
    };
  }

  /** @param {string} name */
  hasClass(name) {
    return this.className.split(/\s+/).includes(name);
  }

  /** @param {(FakeElement | string)[]} nodes */
  append(...nodes) {
    for (const node of nodes) {
      if (typeof node === 'string') {
        this.textContent += node;
        continue;
      }
      node.remove();
      node.parentElement = this;
      this.children.push(node);
    }
  }

  remove() {
    if (this.parentElement !== null) {
      this.parentElement.children = this.parentElement.children.filter((c) => c !== this);
      this.parentElement = null;
    }
  }

  /** @param {string} name @param {string} value */
  setAttribute(name, value) {
    this.attrs[name] = value;
  }

  /** `.a.b[data-x="1"]` 形式の単純なセレクタだけ */
  matches(/** @type {string} */ selector) {
    const classes = [...selector.matchAll(/\.([\w-]+)/g)].map((m) => m[1]);
    const attrs = [...selector.matchAll(/\[data-([\w-]+)="([^"]*)"\]/g)];
    return classes.every((c) => this.hasClass(c)) && attrs.every((m) => this.dataset[m[1]] === m[2]);
  }

  /** @returns {FakeElement | null} */
  querySelector(/** @type {string} */ selector) {
    return this.querySelectorAll(selector)[0] ?? null;
  }

  /** @returns {FakeElement[]} */
  querySelectorAll(/** @type {string} */ selector) {
    const out = [];
    for (const child of this.children) {
      if (child.matches(selector)) {
        out.push(child);
      }
      out.push(...child.querySelectorAll(selector));
    }
    return out;
  }
}

export function installFakeDom() {
  const g = /** @type {any} */ (globalThis);
  g.HTMLElement = FakeElement;
  g.document = { createElement: (/** @type {string} */ tag) => new FakeElement(tag) };
  g._ = (/** @type {string} */ text) => text;
}

/** 偽の Stock。カードごとに子要素（ir-card）を作る。別の Stock に既にあるカードは移す */
class FakeStock {
  constructor(/** @type {any} */ manager, /** @type {FakeElement} */ element, /** @type {any} */ settings = {}) {
    this.manager = manager;
    this.element = element;
    this.settings = settings;
    /** @type {any[]} */
    this.cards = [];
    /** @type {Map<unknown, FakeElement>} */
    this.elements = new Map();
  }

  getElement() {
    return this.element;
  }

  getCards() {
    return this.cards;
  }

  getCardElement(/** @type {any} */ card) {
    return this.elements.get(card.id);
  }

  async addCard(/** @type {any} */ card, /** @type {any} */ settings) {
    const previous = FakeStock.owners.get(card.id);
    previous?.dropCard(card);
    const node = new FakeElement('div');
    node.className = 'ir-card';
    node.dataset.key = card.key;
    this.element.append(node);
    this.cards.push(card);
    this.elements.set(card.id, node);
    FakeStock.owners.set(card.id, this);
    FakeStock.log.push({ add: card.id, key: card.key, from: settings?.fromElement ?? null });
  }

  dropCard(/** @type {any} */ card) {
    this.cards = this.cards.filter((c) => c.id !== card.id);
    this.elements.get(card.id)?.remove();
    this.elements.delete(card.id);
    if (FakeStock.owners.get(card.id) === this) {
      FakeStock.owners.delete(card.id);
    }
  }

  async removeCard(/** @type {any} */ card, /** @type {any} */ settings) {
    this.dropCard(card);
    FakeStock.log.push({ remove: card.id, to: settings?.slideTo ?? null });
  }
}
/** @type {Map<unknown, FakeStock>} */
FakeStock.owners = new Map();
/** @type {any[]} */
FakeStock.log = [];

class FakeDeck extends FakeStock {
  constructor(/** @type {any} */ manager, /** @type {FakeElement} */ element, /** @type {any} */ settings = {}) {
    super(manager, element, settings);
    this.cardNumber = settings.cardNumber ?? 0;
    this.topCard = settings.topCard;
    if (this.topCard !== undefined) {
      const node = new FakeElement('div');
      node.className = 'ir-card';
      node.dataset.key = this.topCard.key;
      element.append(node);
    }
  }

  setCardNumber(/** @type {number} */ n, /** @type {any} */ top) {
    this.cardNumber = n;
    this.topCard = top;
  }

  async addCard(/** @type {any} */ card, /** @type {any} */ settings) {
    await super.addCard(card, settings);
    this.cardNumber += 1;
    this.topCard = card;
  }
}

export const FakeBgaCards = {
  Manager: class {
    constructor(/** @type {any} */ options) {
      this.options = options;
    }
  },
  LineStock: FakeStock,
  SlotStock: FakeStock,
  Deck: FakeDeck,
};

export const FakeBgaAnimations = {
  Manager: class {
    constructor(/** @type {any} */ options) {
      this.options = options;
    }

    async slideAndAttach(/** @type {FakeElement} */ node, /** @type {FakeElement} */ parent) {
      parent.append(node);
    }
  },
};

export { FakeStock };
