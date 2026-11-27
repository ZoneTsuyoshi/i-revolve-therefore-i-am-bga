// 原稿（pages/pN.html の断片）の検査と、ページの HTML の組み立て（純粋な関数。build.mjs とテストが使う）

/** HTML コメント（<!-- … -->）を取り除く */
export function stripComments(html) {
  return html.replace(/<!--[\s\S]*?-->/g, '');
}

// ひらがな・カタカナ・漢字・全角の記号・全角英数（半角カナを含む）
const JA = /[\u3000-\u303f\u3040-\u30ff\u3400-\u4dbf\u4e00-\u9fff\uff01-\uff9f\uffe0-\uffee]/;

/** コメントの外に日本語の文字が残っている行を返す（[{ line, text }]） */
export function findJapanese(html) {
  const masked = html.replace(/<!--[\s\S]*?-->/g, (m) => m.replace(/[^\n]/g, ' '));
  const hits = [];
  masked.split('\n').forEach((text, i) => { if (JA.test(text)) hits.push({ line: i + 1, text: text.trim() }); });
  return hits;
}

/** style="…" の中の --変数名 の値を { 名前: 値 } にする */
export function styleVars(style) {
  const vars = {};
  for (const m of style.matchAll(/--([a-z-]+)\s*:\s*([^;"]+)/gi)) vars[m[1]] = m[2].trim();
  return vars;
}

/** 文字の箱 .t と覆う四角 .e の必須の変数（--x --y --w --h）がそろっているか。足りないものを返す */
export function findMissingVars(html) {
  const problems = [];
  const body = stripComments(html);
  for (const m of body.matchAll(/<(?:div|span)\b[^>]*\bclass="([^"]*)"[^>]*>/g)) {
    const classes = m[1].split(/\s+/);
    if (!classes.includes('t') && !classes.includes('e')) continue;
    const style = /\bstyle="([^"]*)"/.exec(m[0])?.[1] ?? '';
    const vars = styleVars(style);
    const missing = ['x', 'y', 'w', 'h'].filter((k) => !(k in vars));
    if (missing.length) problems.push({ tag: m[0].slice(0, 80), missing });
    for (const k of ['x', 'y', 'w', 'h', 'fs', 'lh']) {
      if (k in vars && !Number.isFinite(Number(vars[k]))) problems.push({ tag: m[0].slice(0, 80), missing: [`--${k} は数値（単位なし）`] });
    }
  }
  return problems;
}

/** <script> を含むか */
export function hasScript(html) {
  return /<script\b/i.test(stripComments(html));
}

/** HTML の特殊文字をエスケープする */
export function esc(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/**
 * ページ 1 枚の HTML。bgHref は下地の画像の URL、body は原稿の断片
 * @param {{ id: string, bgHref: string, body: string }} p
 */
export function pageHtml({ id, bgHref, body }) {
  return `<section class="outer" data-id="${esc(id)}"><div class="sheet" data-id="${esc(id)}"><img class="bg" src="${esc(bgHref)}" alt="">\n${body}\n</div></section>`;
}
