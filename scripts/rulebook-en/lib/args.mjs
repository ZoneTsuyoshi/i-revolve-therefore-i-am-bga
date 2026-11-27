// build.mjs のコマンドライン引数の解釈（純粋な関数。テストが使う）

export const PAGE_IDS = ['p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 'p8'];
export const DEFAULTS = { dpi: 300, quality: 88 };

/** "p3,p5"・"3,5" を ['p3','p5'] にする（重複・順序は PAGE_IDS の順にそろえる）。知らない ID は例外 */
export function parseOnly(arg) {
  const ids = String(arg).split(',').map((s) => s.trim()).filter(Boolean).map((s) => (/^\d+$/.test(s) ? `p${s}` : s.toLowerCase()));
  if (ids.length === 0) throw new Error('--only にページを指定してください（例: --only p3,p5）');
  for (const id of ids) if (!PAGE_IDS.includes(id)) throw new Error(`知らないページ: ${id}（${PAGE_IDS.join(' ')}）`);
  return PAGE_IDS.filter((id) => ids.includes(id));
}

/** @param {string[]} argv */
export function parseArgs(argv) {
  const out = { only: null, png: false, debug: false, dpi: DEFAULTS.dpi, quality: DEFAULTS.quality, help: false };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    const val = () => {
      if (i + 1 >= argv.length) throw new Error(`${a} に値がありません`);
      return argv[++i];
    };
    if (a === '--only') out.only = parseOnly(val());
    else if (a === '--png') out.png = true;
    else if (a === '--debug') out.debug = true;
    else if (a === '--dpi') out.dpi = Number(val());
    else if (a === '--quality') out.quality = Number(val());
    else if (a === '--help') out.help = true;
    else throw new Error(`不明なオプション: ${a}`);
  }
  if (!(out.dpi >= 72 && out.dpi <= 400)) throw new Error('--dpi は 72〜400');
  if (!(out.quality >= 1 && out.quality <= 100)) throw new Error('--quality は 1〜100');
  return out;
}

export const USAGE = 'node scripts/rulebook-en/build.mjs [--only p3,p5] [--png] [--debug] [--dpi 300] [--quality 88]';
