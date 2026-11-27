// ブラウザ内で動く（build.mjs が HTML に埋め込む）。
// ①フォントの読み込みを待つ ②文字の箱 .t の中身が --h（--w）をはみ出したら、フォントサイズを 0.25 pt ずつ下げて収める
//   （下限は指定値の --fit-min 倍、既定 0.85。.nofit なら下げない）③結果を window.__rb に入れる（build.mjs が読む）。
// 座標・大きさの単位は pt（CSS の 1pt = 4/3 px）。
(() => {
  'use strict';
  const STEP = 0.25; // フォントサイズを下げる刻み（pt）
  const DEFAULT_MIN = 0.85;
  const PX_PER_PT = 4 / 3;

  const num = (cs, name, def) => {
    const v = parseFloat(cs.getPropertyValue(name));
    return Number.isFinite(v) ? v : def;
  };

  async function loadFonts() {
    // 宣言されているフォントをすべて先に読み込む（使われて初めて読む挙動だと、測定が代替フォントで行われる）
    const faces = [...document.fonts];
    await Promise.all(faces.map((f) => f.load().catch(() => null)));
    await document.fonts.ready;
  }

  /** 中身（.tin）が箱（--w × --h）をはみ出しているか。1 px 未満の誤差は許す */
  function overflows(el, tin, wPx, hPx) {
    const tooTall = hPx > 0 && tin.offsetHeight > hPx + 1;
    const tooWide = tin.scrollWidth > wPx + 1 || el.scrollWidth > wPx + 1;
    return tooTall || tooWide;
  }

  function fitBox(el) {
    const cs = getComputedStyle(el);
    const base = num(cs, '--fs', 7);
    const w = num(cs, '--w', 0);
    const h = num(cs, '--h', 0);
    const min = base * num(cs, '--fit-min', DEFAULT_MIN);
    let tin = el.querySelector(':scope > .tin');
    if (!tin) {
      tin = document.createElement('div');
      tin.className = 'tin';
      while (el.firstChild) tin.appendChild(el.firstChild);
      el.appendChild(tin);
    }
    let fs = base;
    el.style.fontSize = '';
    if (!el.classList.contains('nofit')) {
      while (overflows(el, tin, w * PX_PER_PT, h * PX_PER_PT) && fs - STEP >= min - 1e-6) {
        fs -= STEP;
        el.style.fontSize = `${fs}pt`;
      }
    }
    const over = overflows(el, tin, w * PX_PER_PT, h * PX_PER_PT);
    if (over) el.setAttribute('data-overflow', '1');
    else el.removeAttribute('data-overflow');
    const text = tin.textContent.replace(/\s+/g, ' ').trim();
    return {
      label: el.dataset.name || text.slice(0, 40),
      x: num(cs, '--x', 0), y: num(cs, '--y', 0), w, h,
      fontSize: base, finalFontSize: fs, shrunk: fs < base - 1e-6,
      contentHeight: Math.round((tin.offsetHeight / PX_PER_PT) * 100) / 100,
      overflow: over,
    };
  }

  async function run() {
    await loadFonts();
    const report = {};
    for (const sheet of document.querySelectorAll('.sheet')) {
      const boxes = [...sheet.querySelectorAll('.t')].map(fitBox);
      report[sheet.dataset.id] = {
        boxes,
        erases: sheet.querySelectorAll('.e').length,
        overflowCount: boxes.filter((b) => b.overflow).length,
        shrunkCount: boxes.filter((b) => b.shrunk).length,
      };
    }
    window.__rb = report;
    window.__rbDone = true;
  }

  const start = () => run().catch((err) => { window.__rbError = String(err && err.stack ? err.stack : err); window.__rbDone = true; });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
