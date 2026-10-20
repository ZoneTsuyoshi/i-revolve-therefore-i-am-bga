// P4: BGA のフレームワークを継承するクラスの独自メソッドが `ir` で始まることを検査する（仕様書 004 §2.8、003 §4.3）。
// 外部依存なし・PHP 不要の字句的な簡易解析。実行: npm run lint:prefix [-- <ファイルまたはディレクトリ>...]
// 既定の対象は src/modules/php/ 以下の *.php。無ければ対象 0 件で成功する。
//
// 簡易解析の限界: (1) PHP の全構文は解釈しない（コメント・文字列・ヒアドキュメントは除外する）。
// (2) 1 ファイル 1 名前空間を前提とし、複数あれば失敗にする。(3) class_alias や動的な継承は見ない。
// (4) メソッドがフレームワークの上書きかどうかは判定せず、名前だけを見る。
import { readdirSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const FRAMEWORK_NS = 'Bga\\GameFramework\\';
const TABLE = 'Bga\\GameFramework\\Table';
const GAME_STATE = 'Bga\\GameFramework\\States\\GameState';

// 許可リスト（フレームワークが呼び出す名前で、ir を付けられないもの）。最小に保ち、足すときは PR で根拠（公式ドキュメントの URL）を書く。
// 魔術メソッド（__ 始まり）は全対象クラスで許可する（PHP の言語仕様）。
const ALLOWED = {
  // Game.php（004 §6.1）。debug_ は Studio のデバッグメニューに出る
  table: { names: new Set(['setupNewGame', 'getAllDatas', 'getGameProgression', 'upgradeTableDb']), patterns: [/^debug_[A-Za-z0-9_]+$/] },
  // 状態クラス（004 §5.1）。act で始まるメソッドは #[PossibleAction] が付いたものだけ
  state: { names: new Set(['onEnteringState', 'getArgs', 'zombie']), patterns: [] },
  // それ以外の Bga\GameFramework\ のクラスは魔術メソッドのみ
  other: { names: new Set(), patterns: [] },
};

/**
 * コメント・文字列・ヒアドキュメントを空白に置き換える（改行と文字位置は保つ）。
 * `// ir-lint: allow <理由>` のコメントは allows に集める。
 */
export function sanitize(code) {
  const out = code.split('');
  const allows = [];
  const blank = (from, to) => {
    for (let k = from; k < to; k++) if (out[k] !== '\n') out[k] = ' ';
  };
  const n = code.length;
  let i = 0;
  while (i < n) {
    const c = code[i];
    const c2 = code[i + 1];
    if (c === '/' && c2 === '*') {
      const end = code.indexOf('*/', i + 2);
      const to = end === -1 ? n : end + 2;
      blank(i, to);
      i = to;
    } else if ((c === '/' && c2 === '/') || (c === '#' && c2 !== '[')) {
      let end = code.indexOf('\n', i);
      if (end === -1) end = n;
      const text = code.slice(i, end);
      const m = /^(?:\/\/|#)\s*ir-lint:\s*allow\b(.*)$/.exec(text);
      if (m) allows.push({ end, line: code.slice(0, i).split('\n').length, reason: m[1].replace(/\*\/\s*$/, '').trim() });
      blank(i, end);
      i = end;
    } else if (c === "'" || c === '"') {
      let j = i + 1;
      while (j < n && code[j] !== c) j += code[j] === '\\' ? 2 : 1;
      const to = Math.min(j + 1, n);
      blank(i, to);
      i = to;
    } else if (c === '<' && code.startsWith('<<<', i)) {
      const m = /^<<<[ \t]*(["']?)([A-Za-z_][A-Za-z0-9_]*)\1\r?\n/.exec(code.slice(i, i + 200));
      if (!m) {
        i += 3;
        continue;
      }
      const re = new RegExp(`^[ \\t]*${m[2]}(?![A-Za-z0-9_])`, 'm');
      const bodyStart = i + m[0].length;
      const rest = code.slice(bodyStart);
      const found = re.exec(rest);
      const to = found ? bodyStart + found.index + found[0].length : n;
      blank(i, to);
      i = to;
    } else {
      i++;
    }
  }
  return { text: out.join(''), allows };
}

const TOKEN = /\\?[A-Za-z_][A-Za-z0-9_]*(?:\\[A-Za-z_][A-Za-z0-9_]*)*|::|[{}();,&\\]|[^\s]/g;

function tokenize(text) {
  const tokens = [];
  TOKEN.lastIndex = 0;
  let m;
  while ((m = TOKEN.exec(text)) !== null) tokens.push({ v: m[0], pos: m.index });
  return tokens;
}

/** 現在の名前空間と use から、クラス名を完全修飾名（先頭の \ なし）にする */
function resolveName(name, ns, uses) {
  if (name.startsWith('\\')) return name.slice(1);
  const [first, ...rest] = name.split('\\');
  if (uses.has(first)) return [uses.get(first), ...rest].join('\\');
  return ns ? `${ns}\\${name}` : name;
}

/** 1 ファイルを解析して、宣言（クラス・トレイト）とエラーを返す */
function parseFile(file, code) {
  const { text, allows } = sanitize(code);
  const tokens = tokenize(text);
  const lineStarts = [0];
  for (let k = 0; k < text.length; k++) if (text[k] === '\n') lineStarts.push(k + 1);
  const lineOf = (pos) => {
    let lo = 0;
    let hi = lineStarts.length - 1;
    while (lo < hi) {
      const mid = (lo + hi + 1) >> 1;
      if (lineStarts[mid] <= pos) lo = mid;
      else hi = mid - 1;
    }
    return lo + 1;
  };

  const errors = [];
  const decls = []; // { kind, fqn, name, parent, traits, methods, file, line }
  const anons = []; // { parent, line }
  let ns = '';
  let nsCount = 0;
  const uses = new Map();
  const stack = []; // { decl|null, bodyDepth }
  let depth = 0;
  let pendingDecl = null; // 本体の { を待っている宣言

  const isName = (t) => t && /^\\?[A-Za-z_]/.test(t.v);
  const lower = (t) => (t ? t.v.toLowerCase() : '');

  for (let i = 0; i < tokens.length; i++) {
    const t = tokens[i];
    const v = t.v;
    const top = stack[stack.length - 1];

    if (v === '{') {
      depth++;
      if (pendingDecl) {
        stack.push({ decl: pendingDecl.decl, bodyDepth: depth });
        pendingDecl = null;
      }
      continue;
    }
    if (v === '}') {
      if (top && top.bodyDepth === depth) stack.pop();
      depth--;
      continue;
    }

    const kw = lower(t);

    if (stack.length === 0 && depth === 0) {
      if (kw === 'namespace' && isName(tokens[i + 1]) && tokens[i + 1].v.toLowerCase() !== 'function') {
        nsCount++;
        ns = tokens[i + 1].v.replace(/^\\/, '');
        if (tokens[i + 2] && tokens[i + 2].v === '{') errors.push(`${file}:${lineOf(t.pos)}: namespace ブロック構文は未対応です`);
        continue;
      }
      if (kw === 'use') {
        i = parseTopLevelUse(tokens, i + 1, uses);
        continue;
      }
    }

    // class / trait / interface / enum の宣言（::class と new class は別扱い）
    if ((kw === 'class' || kw === 'trait' || kw === 'interface' || kw === 'enum') && tokens[i - 1]?.v !== '::') {
      const prev = lower(tokens[i - 1]);
      if (kw === 'class' && prev === 'new') {
        // 無名クラス: new class (...) extends X {
        let j = i + 1;
        if (tokens[j]?.v === '(') {
          let d = 0;
          for (; j < tokens.length; j++) {
            if (tokens[j].v === '(') d++;
            else if (tokens[j].v === ')' && --d === 0) {
              j++;
              break;
            }
          }
        }
        let parent = null;
        if (lower(tokens[j]) === 'extends' && isName(tokens[j + 1])) parent = resolveName(tokens[j + 1].v, ns, uses);
        if (parent) anons.push({ parent, line: lineOf(t.pos) });
        while (j < tokens.length && tokens[j].v !== '{') j++;
        pendingDecl = { decl: null };
        i = j - 1;
        continue;
      }
      if (isName(tokens[i + 1])) {
        const name = tokens[i + 1].v;
        let j = i + 2;
        let parent = null;
        if (kw === 'class' && lower(tokens[j]) === 'extends' && isName(tokens[j + 1])) parent = resolveName(tokens[j + 1].v, ns, uses);
        while (j < tokens.length && tokens[j].v !== '{') j++;
        const decl = {
          kind: kw,
          name,
          fqn: ns ? `${ns}\\${name}` : name,
          parent,
          traits: [],
          methods: [],
          file,
          line: lineOf(t.pos),
        };
        if (kw === 'class' || kw === 'trait') decls.push(decl);
        pendingDecl = { decl: kw === 'class' || kw === 'trait' ? decl : null };
        i = j - 1;
        continue;
      }
    }

    if (top && top.decl && top.bodyDepth === depth) {
      if (kw === 'use') {
        // クラス本体の use TraitName, Other; （{ ... } の競合解決ブロックは本体側の深さで読み飛ばされる）
        let j = i + 1;
        while (j < tokens.length && tokens[j].v !== ';' && tokens[j].v !== '{') {
          if (isName(tokens[j])) top.decl.traits.push(resolveName(tokens[j].v, ns, uses));
          j++;
        }
        i = j - 1;
        continue;
      }
      if (kw === 'function') {
        let j = i + 1;
        if (tokens[j]?.v === '&') j++;
        if (isName(tokens[j]) && tokens[j + 1]?.v === '(' && !tokens[j].v.includes('\\')) {
          // 直前の ; { } 以降（修飾子と属性）を取り出す
          let s = t.pos;
          while (s > 0 && !';{}'.includes(text[s - 1])) s--;
          const before = text.slice(s, t.pos);
          const attr = before;
          const line = lineOf(t.pos);
          // 許可コメント: その直後〜function の間に修飾子と属性しか無いもの
          let allow = null;
          for (const a of allows) {
            if (a.end <= t.pos) {
              const between = text.slice(a.end, t.pos);
              if (/^(?:\s|#\[[^\]]*\]|public|protected|private|static|abstract|final|readonly)*$/i.test(between)) allow = a;
            }
          }
          top.decl.methods.push({
            name: tokens[j].v,
            line,
            possibleAction: /#\[[^\]]*\bPossibleAction\b/.test(attr),
            allow,
          });
        }
      }
    }
  }

  if (nsCount > 1) errors.push(`${file}: namespace が複数あります（1 ファイル 1 名前空間のみ対応）`);
  return { decls, anons, errors };
}

/** ファイル先頭の use 文（use A\B; use A\B as C; use A\{B, C as D}; カンマ区切り; use function/const は無視）を読む。; の位置を返す */
function parseTopLevelUse(tokens, start, uses) {
  let i = start;
  const first = tokens[i]?.v.toLowerCase();
  if (first === 'function' || first === 'const') {
    while (i < tokens.length && tokens[i].v !== ';') i++;
    return i;
  }
  let base = null;
  let alias = null;
  const commit = () => {
    if (base) {
      const full = base.replace(/^\\/, '');
      uses.set(alias ?? full.split('\\').pop(), full);
    }
    base = null;
    alias = null;
  };
  let prefix = null; // グループ use の共通部分
  for (; i < tokens.length; i++) {
    const v = tokens[i].v;
    if (v === ';') break;
    if (v === '{') {
      prefix = base;
      base = null;
    } else if (v === '}') {
      commit();
      prefix = null;
    } else if (v === ',') {
      commit();
    } else if (v === '\\') {
      // グループ use の A\B\{
    } else if (v.toLowerCase() === 'as') {
      alias = tokens[++i]?.v ?? null;
    } else if (/^\\?[A-Za-z_]/.test(v)) {
      base = prefix ? `${prefix}\\${v}` : v;
    }
  }
  commit();
  return i;
}

/**
 * 複数のファイルをまとめて検査する。
 * @param {{path: string, code: string}[]} sources
 */
export function checkSources(sources) {
  const errors = [];
  const decls = [];
  const anons = [];
  for (const s of sources) {
    const r = parseFile(s.path, s.code);
    errors.push(...r.errors);
    decls.push(...r.decls);
    anons.push(...r.anons.map((a) => ({ ...a, file: s.path })));
  }
  const byFqn = new Map(decls.map((d) => [d.fqn, d]));

  // フレームワークのクラスまで親を辿り、区分（table / state / other）を決める。辿り着かなければ null
  const categoryOf = (decl, seen = new Set()) => {
    if (!decl.parent || seen.has(decl.fqn)) return null;
    seen.add(decl.fqn);
    if (decl.parent.startsWith(FRAMEWORK_NS)) {
      if (decl.parent === TABLE) return 'table';
      if (decl.parent === GAME_STATE) return 'state';
      return 'other';
    }
    const parentDecl = byFqn.get(decl.parent);
    return parentDecl ? categoryOf(parentDecl, seen) : null;
  };

  // 対象: decl -> 区分の集合
  const targets = new Map();
  for (const d of decls) {
    if (d.kind !== 'class') continue;
    const c = categoryOf(d);
    if (c) targets.set(d, new Set([c]));
  }
  // トレイト: 対象クラス（または対象トレイト）が use するもの。不動点まで伝播する
  for (let changed = true; changed; ) {
    changed = false;
    for (const [d, cats] of [...targets]) {
      for (const tn of d.traits) {
        const tr = byFqn.get(tn);
        if (!tr || tr.kind !== 'trait') continue;
        const set = targets.get(tr) ?? new Set();
        const before = set.size;
        for (const c of cats) set.add(c);
        targets.set(tr, set);
        if (set.size !== before || !targets.has(tr)) changed = true;
      }
    }
  }

  for (const a of anons) {
    const parentDecl = byFqn.get(a.parent);
    if (a.parent.startsWith(FRAMEWORK_NS) || (parentDecl && targets.has(parentDecl))) {
      errors.push(`${a.file}:${a.line}: new class extends ${a.parent} は解析できません（名前付きクラスに書き直してください）`);
    }
  }

  let methodCount = 0;
  let listUses = 0;
  let commentUses = 0;
  for (const [d, cats] of targets) {
    for (const m of d.methods) {
      methodCount++;
      if (m.allow) {
        if (m.allow.reason === '') {
          errors.push(`${d.file}:${m.allow.line}: ir-lint: allow には理由が必要です（${d.name}::${m.name}）`);
        } else {
          commentUses++;
        }
        continue;
      }
      let viaList = false;
      let ok = true;
      for (const c of cats) {
        const rule = ALLOWED[c];
        if (m.name.startsWith('__') || /^ir[A-Z]/.test(m.name)) continue;
        if (rule.names.has(m.name) || rule.patterns.some((p) => p.test(m.name))) {
          viaList = true;
        } else if (c === 'state' && m.name.startsWith('act') && m.possibleAction) {
          viaList = true;
        } else {
          ok = false;
        }
      }
      if (!ok) {
        errors.push(`${d.file}:${m.line}: ${d.name}::${m.name} は ir で始まっていません（フレームワークの上書きなら許可リストへ）`);
      } else if (viaList || m.name.startsWith('__')) {
        listUses++;
      }
    }
  }
  return { errors, classes: targets.size, methods: methodCount, listUses, commentUses };
}

function* walk(p) {
  let entries;
  try {
    entries = readdirSync(p, { withFileTypes: true });
  } catch (e) {
    if (e.code === 'ENOTDIR') {
      yield p;
      return;
    }
    if (e.code === 'ENOENT') return;
    throw e;
  }
  for (const entry of entries) {
    const full = path.join(p, entry.name);
    if (entry.isDirectory()) yield* walk(full);
    else if (entry.name.endsWith('.php')) yield full;
  }
}

function main() {
  const roots = process.argv.length > 2 ? process.argv.slice(2) : ['src/modules/php'];
  const sources = [];
  for (const root of roots) for (const f of walk(root)) sources.push({ path: f, code: readFileSync(f, 'utf8') });
  const r = checkSources(sources);
  if (r.errors.length > 0) {
    console.error(`check-method-prefix: ${r.errors.length} 件の問題があります。`);
    for (const e of r.errors) console.error(`  ${e}`);
    process.exit(1);
  }
  console.log(
    `check-method-prefix: OK（対象クラス ${r.classes} 件、メソッド ${r.methods} 件、許可リスト ${r.listUses} 件、許可コメント ${r.commentUses} 件）`,
  );
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) main();
