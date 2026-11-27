// ブラウザの中で動く自動検査（仕様書 011 §9.2）。`shoot.mjs` が `page.evaluate` に渡す。
// `page.evaluate` は関数を文字列にして送るので、関数は外の変数を使わず、定数も中に書く（自己完結）。

/**
 * 検査 2・3・5・6・8 を実行し、警告の文を検査ごとに返す。
 * - 2: `#ir-root` とパネル（`.ir-pp`）の中に `position: fixed / sticky` の要素がない
 * - 3: 押せる要素（`button`・`[role="button"]`・`.ir-can`・`.ir-token-pick`）が 32 × 32px 以上
 * - 5: テキストを持つ要素の文字が 12px 以上（`#ir-root`・パネル・ツールチップ）
 * - 6: 実際にスクロールする入れ子の要素がない
 * - 8: 角丸が 3px を超える要素は、許可した部品（トークン・心光・キューブ・コマの画像）だけ
 * @returns {{ 2: string[], 3: string[], 5: string[], 6: string[], 8: string[] }}
 */
export function runChecks() {
  /** 検査 8 で角丸を許す部品（物理的に丸い部品の画像の要素。仕様書 011 §1.1） */
  const ROUND_ALLOWED = ['.ir-token-icon', '.ir-light', '.ir-cube', '.ir-meeple', '.ir-player-meeple', '.ir-pp-icon'];
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
  /** @type {{ 2: string[], 3: string[], 5: string[], 6: string[], 8: string[] }} */
  const out = { 2: [], 3: [], 5: [], 6: [], 8: [] };

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

  for (const key of /** @type {const} */ ([2, 3, 5, 6, 8])) {
    out[key] = uniq(out[key]);
  }
  return out;
}
