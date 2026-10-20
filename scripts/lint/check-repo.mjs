// S2・R1・S3: 禁止ファイル・src/ 衛生・data/ の追跡禁止・ドキュメントのリンクの境界の検査（仕様書 004 §2.4）。
// 外部依存なし。git ls-files の出力を検査する。実行: npm run lint:repo
import { execFileSync } from 'node:child_process';
import { readFileSync, statSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// src/ 以外で追跡できる 1 ファイルの上限（バイト）。src/img/ は第 3 段階の A1 が見る
export const MAX_TRACKED_BYTES = 1_000_000;

// 追跡してはいけないトップレベルのディレクトリ（R1）
const untrackedDirs = ['data', '.worktrees', 'node_modules', 'vendor', 'build'];

// 認証情報などの禁止ファイル（.gitignore と同じ集合）
const forbiddenGlobal = [
  { test: (b) => b === '.env' || (b.startsWith('.env.') && b !== '.env.example'), label: '.env 系ファイル' },
  { test: (b) => b === 'sftp.json', label: 'sftp.json' },
  { test: (b) => b.endsWith('.pem'), label: '*.pem' },
  { test: (b) => b.startsWith('id_rsa'), label: 'id_rsa*' },
  { test: (b) => b.startsWith('id_ed25519'), label: 'id_ed25519*' },
];

// BGA 配布のスタブはリポジトリのどこにも追跡しない（.gitignore だけでは git add -f を防げない）
const stubFiles = ['_ide_helper.php', 'bga-framework.d.ts'];

// src/ 配下に置いてはいけない開発用ファイル
const devFile = [
  { test: (b) => b === 'package.json' || b === 'package-lock.json', label: 'package.json 系' },
  { test: (b) => b === 'composer.json' || b === 'composer.lock', label: 'composer 系' },
  { test: (b) => /^tsconfig.*\.json$/.test(b), label: 'tsconfig*.json' },
  { test: (b) => /^eslint\.config\./.test(b), label: 'eslint.config.*' },
  { test: (b) => /^stylelint\.config\./.test(b), label: 'stylelint.config.*' },
  { test: (b) => /^\..*rc/.test(b), label: '.*rc* 設定ファイル' },
  { test: (b) => /^phpcs\.xml/.test(b), label: 'phpcs.xml*' },
  { test: (b) => /^phpstan\.neon/.test(b), label: 'phpstan.neon*' },
  { test: (b) => /\.(test|spec)\./.test(b), label: '*.test.* / *.spec.*' },
];
const devDirs = ['node_modules', 'tests'];

// src/ に置かない BGA の非推奨ファイル（仕様書 003 §3。Studio の初期ファイルの誤取り込みを防ぐ）
const deprecatedFile = [
  { test: (b) => b === 'states.inc.php' || b === 'material.inc.php', label: 'states.inc.php / material.inc.php' },
  { test: (b) => /\.action\.php$/.test(b), label: '*.action.php' },
  { test: (b) => /\.view\.php$/.test(b), label: '*.view.php' },
  { test: (b) => /\.tpl$/.test(b), label: '*.tpl' },
  { test: (b) => b === 'rollup.config.mjs', label: 'rollup.config.mjs' },
];
const deprecatedDirs = ['src-disabled'];

// src/ 配下のファイル名に使える文字（空白・括弧・非 ASCII を禁止。BGA の画像ファイル名の制約。003 §9）
const safeName = /^[A-Za-z0-9_.\-/]+$/;

// CR（\r）を禁止する src/ のファイル（PHPCS は PHP しか見ないため）
const crChecked = /\.(php|js|css|jsonc|sql)$/;

/**
 * Markdown 本文から [文](リンク先) のリンク先を取り出す。
 * フェンス付きコードブロックとコードスパンの中は除く。参照形式のリンクは見ない。
 * @param {string} markdown
 * @returns {string[]}
 */
export function extractLinkTargets(markdown) {
  const lines = markdown.split(/\r?\n/);
  const kept = [];
  let fence = null;
  for (const line of lines) {
    const m = /^ {0,3}(`{3,}|~{3,})/.exec(line);
    if (fence) {
      if (m && m[1][0] === fence.char && m[1].length >= fence.len && /^ {0,3}[`~]+\s*$/.test(line)) fence = null;
      continue;
    }
    if (m) {
      fence = { char: m[1][0], len: m[1].length };
      continue;
    }
    kept.push(line);
  }
  // コードスパン（同じ長さのバッククォート列で囲まれた範囲）を空白に置換する
  const text = kept.join('\n').replace(/(`+)([\s\S]*?[^`])\1(?!`)/g, ' ');
  const targets = [];
  // [文](target "title") / [文](<target with space>)。画像 ![alt](src) も同じ形式で拾う
  const re = /\]\(\s*(?:<([^>\n]*)>|([^\s)]*))[^)]*\)/g;
  let m;
  while ((m = re.exec(text)) !== null) {
    const t = m[1] ?? m[2];
    if (t) targets.push(t);
  }
  return targets;
}

/**
 * リンク先が data/ 配下またはリポジトリ外を指すかを判定する。問題が無ければ null。
 * @param {string} docFile リポジトリ直下からの Markdown のパス（/ 区切り）
 * @param {string} target リンク先の文字列
 * @returns {string | null} 問題の説明
 */
export function checkLinkBoundary(docFile, target) {
  if (target.startsWith('#')) return null;
  if (/^[A-Za-z][A-Za-z0-9+.-]*:/.test(target)) return null; // http:, https:, mailto: など
  if (target.startsWith('//')) return null;
  let pathPart = target.split('#')[0].split('?')[0];
  if (!pathPart) return null;
  try {
    pathPart = decodeURIComponent(pathPart);
  } catch {
    // デコードできなければそのまま扱う
  }
  const resolved = pathPart.startsWith('/')
    ? path.posix.normalize(pathPart.slice(1))
    : path.posix.normalize(path.posix.join(path.posix.dirname(docFile), pathPart));
  if (resolved === '..' || resolved.startsWith('../')) {
    return `リポジトリの外を指すリンクです（${target}）。コード表記にしてください`;
  }
  if (resolved === 'data' || resolved.startsWith('data/')) {
    return `data/ を指すリンクです（${target}）。コード表記にしてください`;
  }
  return null;
}

/**
 * 追跡ファイルの一覧と読み取り関数から、問題の一覧を返す。
 * @param {string[]} files git ls-files の出力
 * @param {{ readText: (f: string) => string, size: (f: string) => number, gitignore: string | null }} io
 * @returns {string[]} 各行 `<ファイル>: <理由>`
 */
export function checkRepo(files, io) {
  const errors = [];
  const fail = (file, message) => errors.push(`${file}: ${message}`);

  for (const file of files) {
    const parts = file.split('/');
    const base = parts[parts.length - 1];

    // R1: 原本などを追跡しない
    for (const dir of untrackedDirs) {
      if (parts[0] === dir && parts.length > 1) {
        fail(file, `${dir}/ のファイルはコミットできません（Git に入れない領域です）`);
      }
    }
    for (const rule of forbiddenGlobal) {
      if (rule.test(base)) fail(file, `コミット禁止のファイルです（${rule.label}）`);
    }
    if (stubFiles.includes(base)) fail(file, 'BGA 配布のスタブはコミットできません（再配布不可）');
  }

  // R1(b): .gitignore に /data/ の行があること
  if (io.gitignore === null) {
    fail('.gitignore', 'ファイルがありません');
  } else if (!io.gitignore.split(/\r?\n/).some((l) => l.trim() === '/data/')) {
    fail('.gitignore', '`/data/` の行がありません（原本を Git に入れないために必要です）');
  }

  for (const file of files) {
    const parts = file.split('/');
    const base = parts[parts.length - 1];

    if (file.startsWith('src/')) {
      for (const rule of devFile) {
        if (rule.test(base)) fail(file, `src/ に開発用ファイルは置けません（${rule.label}）`);
      }
      for (const dir of devDirs) {
        if (parts.slice(0, -1).includes(dir)) fail(file, `src/ に開発用ディレクトリは置けません（${dir}/）`);
      }
      for (const rule of deprecatedFile) {
        if (rule.test(base)) fail(file, `src/ に非推奨のファイルは置けません（${rule.label}）`);
      }
      for (const dir of deprecatedDirs) {
        if (parts.slice(0, -1).includes(dir)) fail(file, `src/ に非推奨のディレクトリは置けません（${dir}/）`);
      }
      if (file.startsWith('src/ts/')) fail(file, 'src/ に非推奨のディレクトリは置けません（ts/）');
      if (!safeName.test(file)) {
        fail(file, 'ファイル名に空白・括弧・非 ASCII 文字などは使えません（使える文字: A-Z a-z 0-9 _ . -）');
      }
      if (crChecked.test(base) && io.readText(file).includes('\r')) {
        fail(file, '改行コードに CR が含まれています（LF にしてください）');
      }
    }

    // 大きな追跡ファイル（src/img/ は第 3 段階の A1 が見る）
    if (!file.startsWith('src/img/')) {
      const size = io.size(file);
      if (size > MAX_TRACKED_BYTES) {
        fail(file, `${size} バイトで、上限 ${MAX_TRACKED_BYTES} バイトを超えています（原本の画像・PDF は data/ に置く）`);
      }
    }

    // S3: ドキュメントのリンクの境界
    if (/\.md$/i.test(file)) {
      for (const target of extractLinkTargets(io.readText(file))) {
        const problem = checkLinkBoundary(file, target);
        if (problem) fail(file, problem);
      }
    }
  }
  return errors;
}

function main() {
  const files = execFileSync('git', ['ls-files', '-z'], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 })
    .split('\0')
    .filter(Boolean);
  const tryRead = (f) => {
    try {
      return readFileSync(f, 'utf8');
    } catch {
      return null;
    }
  };
  const errors = checkRepo(files, {
    // 作業ツリーで削除済みの追跡ファイルは空として扱う
    readText: (f) => tryRead(f) ?? '',
    size: (f) => {
      try {
        return statSync(f).size;
      } catch {
        return 0;
      }
    },
    gitignore: tryRead('.gitignore'),
  });
  if (errors.length > 0) {
    console.error(`check-repo: ${errors.length} 件の問題があります。`);
    for (const e of errors) console.error(`  ${e}`);
    process.exit(1);
  }
  const srcCount = files.filter((f) => f.startsWith('src/')).length;
  console.log(`check-repo: OK（追跡ファイル ${files.length} 件、うち src/ ${srcCount} 件）`);
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) main();
