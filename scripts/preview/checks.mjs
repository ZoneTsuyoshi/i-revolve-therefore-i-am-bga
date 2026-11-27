// ブラウザの中で動く自動検査（仕様書 011 §9.2）。`shoot.mjs` が `page.evaluate` に渡す。
// `page.evaluate` は関数を文字列にして送るので、関数は外の変数を使わず、定数も中に書く（自己完結）。

/**
 * 検査 2・3・4・5・6・8 を実行し、警告の文を検査ごとに返す。
 * - 2: `#ir-root` とパネル（`.ir-pp`）の中に `position: fixed / sticky` の要素がない
 * - 3: 押せる要素（`button`・`[role="button"]`・`.ir-can`・`.ir-token-pick`）が 32 × 32px 以上
 * - 4: `#ir-root` とパネルの中に `title` 属性がない
 * - 5: テキストを持つ要素の文字が 12px 以上（`#ir-root`・パネル・ツールチップ）
 * - 6: 実際にスクロールする入れ子の要素がない
 * - 8: 角丸が 3px を超える要素は、許可した部品（トークン・心光・キューブ・コマの画像、BGA 標準のボタン）だけ
 * （検査 9 は `runBlockedClickCheck`。検査 7 は `locateTipTarget` / `measureTip`）
 * @returns {{ 2: string[], 3: string[], 4: string[], 5: string[], 6: string[], 8: string[] }}
 */
export function runChecks() {
  /** 検査 8 で角丸を許す部品（物理的に丸い部品の画像の要素。仕様書 011 §1.1） */
  // 手札のレールの BGA 標準のボタン（`destination` に置く。BGA の見た目のままなので、こちらでは角丸を変えない。011 §8）も許す
  const ROUND_ALLOWED = ['.ir-token-icon', '.ir-light', '.ir-cube', '.ir-meeple', '.ir-player-meeple', '.ir-pp-icon', '.ir-rail-actions button'];
  const MIN_TAP = 32;
  const MIN_FONT = 12;
  const MAX_RADIUS = 3;
  const scope = '#ir-root, #ir-root *, .ir-pp, .ir-pp *';
  const scopeAndTips = `${scope}, .ir-tip, .ir-tip *`;
  const label = (/** @type {Element} */ e) => `${e.tagName.toLowerCase()}.${String(e.className).split(' ').filter((c) => c !== '').slice(0, 2).join('.')}`;
  const visible = (/** @type {Element} */ e) => {
    const r = e.getBoundingClientRect();
    return r.width > 0 && r.height > 0 && getComputedStyle(e).visibility !== 'hidden';
  };
  const uniq = (/** @type {string[]} */ list) => [...new Set(list)];
  /** @type {{ 2: string[], 3: string[], 4: string[], 5: string[], 6: string[], 8: string[] }} */
  const out = { 2: [], 3: [], 4: [], 5: [], 6: [], 8: [] };

  // 2: 浮いた要素
  for (const e of document.querySelectorAll(scope)) {
    const position = getComputedStyle(e).position;
    if (position === 'fixed' || position === 'sticky') {
      out[2].push(`${label(e)}（${position}）`);
    }
  }

  // 3: 押せる要素の大きさ
  for (const e of document.querySelectorAll('#ir-root button, #ir-root [role="button"], #ir-root .ir-can, #ir-root .ir-token-pick, .ir-pp button')) {
    const r = e.getBoundingClientRect();
    if (visible(e) && (r.width < MIN_TAP - 0.5 || r.height < MIN_TAP - 0.5)) {
      out[3].push(`${label(e)}（${Math.round(r.width)}×${Math.round(r.height)}）`);
    }
  }

  // 4: title 属性（ツールチップは Tips.js の data-ir-tip。011 §6.5）
  for (const e of document.querySelectorAll('#ir-root [title], .ir-pp [title]')) {
    out[4].push(`${label(e)}（title="${e.getAttribute('title')}"）`);
  }

  // 5: 文字の大きさ（テキストノードを直接持つ要素）
  for (const e of document.querySelectorAll(scopeAndTips)) {
    const hasText = [...e.childNodes].some((n) => n.nodeType === Node.TEXT_NODE && (n.textContent ?? '').trim() !== '');
    if (!hasText || !visible(e)) {
      continue;
    }
    const size = parseFloat(getComputedStyle(e).fontSize);
    if (size < MIN_FONT - 0.01) {
      out[5].push(`${label(e)}（${size}px）`);
    }
  }

  // 6: 入れ子のスクロール（タッチで開いたツールチップの内側は #ir-root の外なので対象外）
  for (const e of document.querySelectorAll('#ir-root *')) {
    const cs = getComputedStyle(e);
    const scrollY = cs.overflowY === 'auto' || cs.overflowY === 'scroll';
    const scrollX = cs.overflowX === 'auto' || cs.overflowX === 'scroll';
    if ((scrollY && e.scrollHeight > e.clientHeight + 1) || (scrollX && e.scrollWidth > e.clientWidth + 1)) {
      out[6].push(label(e));
    }
  }

  // 8: 角丸（要素と、content を持つ ::before / ::after）
  const radiusOf = (/** @type {CSSStyleDeclaration} */ cs) => {
    let max = 0;
    for (const corner of ['borderTopLeftRadius', 'borderTopRightRadius', 'borderBottomLeftRadius', 'borderBottomRightRadius']) {
      const value = cs[/** @type {'borderTopLeftRadius'} */ (corner)];
      if (value.endsWith('%')) {
        max = Math.max(max, parseFloat(value) > 0 ? 9999 : 0);
      } else {
        max = Math.max(max, parseFloat(value) || 0);
      }
    }
    return max;
  };
  for (const e of document.querySelectorAll(scopeAndTips)) {
    if (!visible(e) || ROUND_ALLOWED.some((selector) => e.matches(selector))) {
      continue;
    }
    const own = radiusOf(getComputedStyle(e));
    if (own > MAX_RADIUS) {
      out[8].push(`${label(e)}（${own === 9999 ? '50%' : `${own}px`}）`);
    }
    for (const pseudo of ['::before', '::after']) {
      const cs = getComputedStyle(e, pseudo);
      if (cs.content !== 'none' && cs.content !== 'normal' && radiusOf(cs) > MAX_RADIUS) {
        out[8].push(`${label(e)}${pseudo}（角丸）`);
      }
    }
  }

  for (const key of /** @type {const} */ ([2, 3, 4, 5, 6, 8])) {
    out[key] = uniq(out[key]);
  }
  return out;
}

/**
 * 検査 9: `ir-off`（今は選べない）の要素を押すと、理由のメッセージが出る（`window.__irMessages` が増える。無反応にしない。011 §5.7）。
 * `ir-off` がない場面では何もしない。押した要素を `ir-off` の種類（部屋・カード・トークン）ごとに 1 つずつ試す。
 * メッセージは 2 秒以内に同じ文を繰り返さない仕様なので、押すたびに文が変わるものだけを数える（同じ文のときは「増えない」を警告にしない）。
 * `page.evaluate` に渡す関数は自己完結（外の変数を使わない）。
 * @returns {{ tried: number, warnings: string[] }}
 */
export function runBlockedClickCheck() {
  const label = (/** @type {Element} */ e) => `${e.tagName.toLowerCase()}.${String(e.className).split(' ').filter((c) => c !== '').slice(0, 2).join('.')}`;
  /** @type {{ message: string, type: string }[]} */
  const messages = /** @type {any} */ (window).__irMessages ?? [];
  /** @type {string[]} */
  const warnings = [];
  const seen = new Set();
  let tried = 0;
  for (const e of document.querySelectorAll('#ir-root .ir-off')) {
    const r = e.getBoundingClientRect();
    const kind = e.classList.contains('ir-room') ? 'room' : (e.classList.contains('ir-token') ? 'token' : 'card');
    if (r.width < 4 || r.height < 4 || seen.has(kind)) {
      continue;
    }
    seen.add(kind);
    tried += 1;
    const before = messages.length;
    const lastMessage = messages.length > 0 ? messages[messages.length - 1].message : null;
    /** @type {HTMLElement} */ (e).click();
    const after = messages.length;
    const message = after > before ? messages[after - 1].message : null;
    if (after === before) {
      // 同じ文を 2 秒以内に出さないための間引きなら、直前の文が同じ理由のはず
      const why = e.getAttribute('data-ir-why');
      if (lastMessage === null || (why !== null && why !== lastMessage)) {
        warnings.push(`${label(e)} を押してもメッセージが出ない`);
      }
    } else if (message === null || message.trim() === '') {
      warnings.push(`${label(e)} を押したメッセージが空`);
    }
  }
  return { tried, warnings };
}

/**
 * 検査 7 のための、ページの中の部品。ツールチップの対象（代表）を探し、出ているツールチップを測る。
 * `page.evaluate` に渡す関数は自己完結（外の変数を使わない）。
 */

/**
 * 代表の対象を探して、画面の中央にスクロールし、位置を返す（見つからなければ null）。
 * @param {{ name: string, selector: string }} kind
 * @returns {{ name: string, x: number, y: number, selectable: boolean, width: number } | null}
 */
export function locateTipTarget({ name, selector }) {
  const SELECTABLE = '.ir-can, .ir-picked, .ir-off, .ir-token-pick';
  for (const e of document.querySelectorAll(selector)) {
    if (!(e instanceof HTMLElement)) {
      continue;
    }
    e.scrollIntoView({ block: 'center', inline: 'center' });
    const bar = document.getElementById('page-title');
    const barBottom = bar === null ? 0 : bar.getBoundingClientRect().bottom;
    const r = e.getBoundingClientRect();
    if (r.width < 4 || r.height < 4 || r.left < 0 || r.right > window.innerWidth || r.top < barBottom || r.bottom > window.innerHeight) {
      continue;
    }
    // 中心が別の部品（部屋の想い出カードなど）に隠れているときは、下側・上側の点でも試す
    const points = [[0.5, 0.5], [0.5, 0.92], [0.5, 0.08], [0.1, 0.5], [0.9, 0.5]];
    const found = points
      .map(([fx, fy]) => ({ x: r.left + r.width * fx, y: r.top + r.height * fy }))
      .find((p) => {
        const hit = document.elementFromPoint(p.x, p.y);
        return hit !== null && e.contains(hit);
      });
    if (found === undefined) {
      continue;
    }
    const { x, y } = found;
    return { name, x, y, selectable: e.closest(SELECTABLE) !== null, width: r.width };
  }
  return null;
}

/**
 * 出ているツールチップを、座標 (x, y) の対象に対して測り、警告の文を返す。
 * - 画面内（端から 8px）・ステータスバーの下・対象を覆わない・画像が卓の同じカードの 1.25 倍以上
 * @param {{ x: number, y: number }} point 対象の中心
 * @returns {string[]}
 */
export function measureTip(point) {
  const warnings = [];
  const tip = document.querySelector('.ir-tip');
  if (!(tip instanceof HTMLElement)) {
    return ['ツールチップが出ない'];
  }
  const hit = document.elementFromPoint(point.x, point.y);
  const tipRect = tip.getBoundingClientRect();
  const bar = document.getElementById('page-title');
  const barBottom = bar === null ? 0 : Math.max(0, bar.getBoundingClientRect().bottom);
  const vw = window.innerWidth;
  const vh = window.innerHeight;
  const tol = 0.5;
  if (tipRect.left < 8 - tol || tipRect.right > vw - 8 + tol || tipRect.top < 8 - tol || tipRect.bottom > vh - 8 + tol) {
    warnings.push(`画面の端から 8px 以内（${Math.round(tipRect.left)},${Math.round(tipRect.top)}-${Math.round(tipRect.right)},${Math.round(tipRect.bottom)} / ${vw}×${vh}）`);
  }
  if (tipRect.top < barBottom - tol) {
    warnings.push(`ステータスバー（下端 ${Math.round(barBottom)}）にかかる（top ${Math.round(tipRect.top)}）`);
  }
  // 対象: 中心の下の要素から、ツールチップの対象（data-ir-tip・data-ir-zoom-key・.ir-card）を探す
  const target = hit === null ? null : (hit.closest('[data-ir-tip]') ?? hit.closest('[data-ir-zoom-key]') ?? hit.closest('.ir-card'));
  if (target !== null) {
    const r = target.getBoundingClientRect();
    if (tipRect.left < r.right - tol && tipRect.right > r.left + tol && tipRect.top < r.bottom - tol && tipRect.bottom > r.top + tol) {
      warnings.push('対象を覆っている');
    }
    const image = tip.querySelector('.ir-tip-image');
    if (image instanceof HTMLElement && target.classList.contains('ir-card')) {
      const w = image.getBoundingClientRect().width;
      if (w < r.width * 1.25 - 1) {
        warnings.push(`画像（幅 ${Math.round(w)}）が卓の同じカード（幅 ${Math.round(r.width)}）の 1.25 倍未満`);
      }
    }
  }
  return warnings;
}
