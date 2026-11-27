// BGA の API を模して、このゲームのクライアント（src/modules/js/Game.js）をブラウザで表示するプレビュー（使い方は README.md）。
// URL の引数: fixture（固定データの名前）、theme（light / dark）、spectator=1、animations=1、touch=0/1、captions=1

/** この幅（px）未満をモバイル表示にする。BGA の実際の境目は未確認なのでここで仮置きしている */
const MOBILE_MAX_WIDTH = 980;
/** クライアントに状態クラスが登録されていなくてよい状態（BGA が何も出さない） */
const NO_CLIENT_HANDLER = ['GameEnd'];

const params = new URLSearchParams(location.search);
const fixtureName = params.get('fixture') ?? '4p-late';
const theme = params.get('theme') === 'dark' ? 'dark' : 'light';
const spectator = params.get('spectator') === '1';
const animations = params.get('animations') === '1';

const root = document.documentElement;
root.dataset.theme = theme;

/** @param {unknown} error */
function showError(error) {
  const message = error instanceof Error ? `${error.message}\n${error.stack ?? ''}` : String(error);
  const bar = /** @type {HTMLElement} */ (document.getElementById('pv-error'));
  bar.hidden = false;
  bar.textContent += `${message}\n`;
  root.dataset.error = `${root.dataset.error ?? ''}${message}\n`;
}
window.addEventListener('error', (event) => showError(event.error ?? event.message));
window.addEventListener('unhandledrejection', (event) => showError(event.reason));

/** @param {string} text */
const escapeHtml = (text) => text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] ?? c);

// ---- body のクラス（BGA と同じ名前）----
const touchParam = params.get('touch');
const isTouch = touchParam === null ? navigator.maxTouchPoints > 0 || 'ontouchstart' in window : touchParam === '1';
function updateBodyClasses() {
  const mobile = window.innerWidth < MOBILE_MAX_WIDTH;
  document.body.classList.toggle('mobile_version', mobile);
  document.body.classList.toggle('desktop_version', !mobile);
  document.body.classList.toggle('touch-device', isTouch);
  document.body.classList.toggle('notouch-device', !isTouch);
  // プレイヤー設定 100 Card captions（cssPref のクラスは html にも body にも付く）
  document.body.classList.toggle('ir-captions-on', params.get('captions') === '1');
}
updateBodyClasses();
window.addEventListener('resize', updateBodyClasses);

// ---- グローバル（BGA が提供するもの）----
Object.assign(window, {
  _: (/** @type {string} */ text) => text,
  importEsmLib: async (/** @type {string} */ name, /** @type {string} */ version) => {
    const lib = await import(/* @vite-ignore */ `https://x.boardgamearena.net/data/game-libs/${name}/${version}/dist/${name}.esm.js`);
    return lib;
  },
});

/** @type {{ me: number, state: { name: string, active: boolean, args: any, actplayer?: number } | null, steps?: any[], replay?: { name: string, args: any }[] }} */
let meta;
/** @type {Record<string, any>} */
let players = {};
/** @type {Record<string, any>} */
const registered = {};
/** @type {{ name: string, args: unknown }[]} */
const actions = [];
/** @type {any[]} */
window.__irActions = actions;
/** @type {{ message: string, type: string }[]} */
const messages = [];
/** @type {any[]} */
window.__irMessages = messages;
/** @type {{ handlers: any[] }} */
const notifications = { handlers: [] };

/** @param {number | string} id */
function playerNameHtml(id) {
  const p = players[String(id)];
  return p === undefined ? String(id) : `<span class="pv-player-name" style="font-weight:bold;color:#${p.color}">${escapeHtml(p.name)}</span>`;
}

// BGA の標準の構造（#page-title の中に #pagemaintitletext と #generalactions）に合わせた id
const titleEl = /** @type {HTMLElement} */ (document.getElementById('pagemaintitletext'));
const buttonsEl = /** @type {HTMLElement} */ (document.getElementById('generalactions'));
const gameArea = /** @type {HTMLElement} */ (document.getElementById('game_play_area'));
const panelsEl = /** @type {HTMLElement} */ (document.getElementById('pv-panels'));

const bga = {
  states: {
    register(/** @type {string} */ name, /** @type {any} */ handler) {
      registered[name] = handler;
    },
  },
  gameArea: {
    getElement: () => gameArea,
  },
  players: {
    getCurrentPlayerId: () => (spectator ? 9999999 : meta.me),
    isCurrentPlayerSpectator: () => spectator,
  },
  playerPanels: {
    getElement: (/** @type {number} */ id) => /** @type {HTMLElement} */ (document.getElementById(`pv-panel-body-${id}`)),
  },
  statusBar: {
    setTitle(/** @type {string} */ text, /** @type {Record<string, unknown>} */ args = {}) {
      const actId = meta.state?.actplayer ?? meta.me;
      const you = `<span style="font-weight:bold;color:#${players[String(meta.me)]?.color ?? '000'}">You</span>`;
      let html = text.replaceAll('${you}', you).replaceAll('${actplayer}', playerNameHtml(actId));
      for (const [key, value] of Object.entries(args)) {
        html = html.replaceAll('${' + key + '}', String(value));
      }
      titleEl.innerHTML = html;
    },
    addActionButton(
      /** @type {string} */ label,
      /** @type {(event: Event) => unknown} */ callback,
      /** @type {{ color?: string, disabled?: boolean, tooltip?: string, id?: string, destination?: HTMLElement | string, title?: string }} */ options = {},
    ) {
      const color = options.color ?? 'primary';
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `pv-button pv-button-${color}`;
      button.innerHTML = label;
      if (options.id !== undefined) {
        button.id = options.id;
      }
      if (options.tooltip !== undefined) {
        button.title = options.tooltip;
      }
      // BGA の `title` は、ラベルがアイコンのときの平文の説明（ボタンの title 属性になる）
      if (options.title !== undefined) {
        button.title = options.title;
      }
      if (options.disabled === true) {
        button.disabled = true;
        button.classList.add('pv-disabled');
      }
      button.addEventListener('click', (event) => {
        Promise.resolve()
          .then(() => callback(event))
          .catch(showError);
      });
      // `destination` を渡すと、ステータスバーではなくその要素に置く（BGA の文書: 「ボタンを足す DOM 要素」）
      const destination = typeof options.destination === 'string' ? document.getElementById(options.destination) : options.destination;
      (destination ?? buttonsEl).appendChild(button);
      return button;
    },
    // 文書は対象を書いていない【未確認】。悪い側に寄せ、`destination` に置いたボタンは外さない（実装が自分で remove() する。仕様書 011 §8・§9.1）
    removeActionButtons() {
      buttonsEl.replaceChildren();
    },
  },
  dialogs: {
    // 画面の上に 3 秒出る箱の代用。内容は window.__irMessages に積む（検査 9）
    showMessage(/** @type {string} */ message, /** @type {string} */ type = 'info') {
      messages.push({ message, type });
      const box = document.createElement('div');
      box.className = `pv-message${type === 'error' ? ' pv-message-error' : ''}`;
      box.textContent = message;
      document.body.appendChild(box);
      setTimeout(() => box.remove(), 3000);
    },
  },
  actions: {
    performAction(/** @type {string} */ name, /** @type {unknown} */ args = {}) {
      console.log('performAction', name, JSON.stringify(args));
      actions.push({ name, args });
      return Promise.resolve();
    },
  },
  notifications: {
    setupPromiseNotifications(/** @type {{ handlers: any[] }} */ options) {
      notifications.handlers = options.handlers;
    },
  },
  images: {
    getImgUrl: (/** @type {string} */ file) => `/src/img/${file}`,
    dontPreloadImages() {},
  },
  gameui: {
    bgaAnimationsActive: () => animations,
    // Game.hookLogFormat が差し替える。実物の GameGui にも同名のメソッドがある
    bgaFormatText: (/** @type {string} */ log, /** @type {any} */ args) => ({ log, args }),
  },
};

/** 通知 `name` を、登録された handlers の notif_<name> に渡す（fixture の replay 用） */
async function dispatchNotification(/** @type {string} */ name, /** @type {any} */ args) {
  for (const handler of notifications.handlers) {
    if (typeof handler[`notif_${name}`] === 'function') {
      await handler[`notif_${name}`](args);
      return;
    }
  }
  throw new Error(`通知 ${name} を処理するハンドラーがありません`);
}

const nextFrame = () => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));

/**
 * fixture の steps（状態に入った後の操作）を実行する。`{ click: セレクター, nth?: 何番目か }`、`{ wait: ミリ秒 }`。
 * @param {any[]} steps
 */
async function runSteps(steps) {
  for (const step of steps) {
    if (typeof step.click === 'string') {
      const found = document.querySelectorAll(step.click);
      const target = /** @type {HTMLElement | undefined} */ (found[step.nth ?? 0]);
      if (target === undefined) {
        throw new Error(`steps: ${step.click}（${step.nth ?? 0} 番目）が見つかりません`);
      }
      target.click();
    } else if (typeof step.wait === 'number') {
      await new Promise((resolve) => setTimeout(resolve, step.wait));
    }
    await nextFrame();
  }
}

async function main() {
  if (!/^[\w-]+$/.test(fixtureName)) {
    throw new Error(`fixture の名前が不正です: ${fixtureName}`);
  }
  const response = await fetch(`/scripts/preview/fixtures/${fixtureName}.json`);
  if (!response.ok) {
    throw new Error(`固定データを読めません: ${fixtureName}（${response.status}）`);
  }
  const fixture = await response.json();
  meta = fixture.preview;
  const gamedatas = fixture.gamedatas;
  players = gamedatas.players;
  if (gamedatas.catalog === undefined) {
    gamedatas.catalog = await (await fetch('/scripts/preview/fixtures/catalog.json')).json();
  }
  const state = meta.state;
  if (state !== null) {
    // BGA は gamedatas.gamestate に今の状態を入れて渡す
    gamedatas.gamestate = { name: state.name, args: state.args };
  }

  // プレイヤーパネル: 自分を先頭に、席順で続ける（観戦者は席順のまま）。標準のスコア欄 `#player_score_<id>` も置く
  const order = /** @type {number[]} */ (gamedatas.playerorder);
  const myIndex = order.indexOf(meta.me);
  const panelOrder = spectator || myIndex < 0 ? order : [...order.slice(myIndex), ...order.slice(0, myIndex)];
  for (const id of panelOrder) {
    panelsEl.insertAdjacentHTML('beforeend', `<div class="pv-panel" id="pv-panel-${id}">
      <div class="pv-panel-name">${playerNameHtml(id)} <span class="player_score">(<span id="player_score_${id}">${players[String(id)].score}</span>)</span></div>
      <div id="pv-panel-body-${id}" class="pv-panel-body"></div>
    </div>`);
  }

  const { Game } = await import('/src/modules/js/Game.js');
  const game = new Game(bga);
  game.setup(gamedatas);
  for (const { name, args } of meta.replay ?? []) {
    await dispatchNotification(name, args);
  }
  if (state !== null) {
    const handler = registered[state.name];
    if (handler === undefined && !NO_CLIENT_HANDLER.includes(state.name)) {
      throw new Error(`状態 ${state.name} は登録されていません`);
    }
    const active = !spectator && state.active;
    titleEl.innerHTML = `${playerNameHtml(active ? meta.me : (state.actplayer ?? meta.me))} (${state.name})`;
    await handler?.onEnteringState(state.args, active);
    await nextFrame();
    await runSteps(meta.steps ?? []);
  }
  window.__irGame = game;

  await document.fonts.ready;
  await nextFrame();
  root.dataset.ready = '1';
}

main().catch(showError);
