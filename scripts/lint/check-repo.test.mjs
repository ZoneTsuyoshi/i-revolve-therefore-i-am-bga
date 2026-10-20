// check-repo.mjs の自己テスト（仕様書 004 §2.4）。実行: npm run test:lint
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { checkLinkBoundary, checkRepo, extractLinkTargets } from './check-repo.mjs';

const GITIGNORE = '/data/\n';
const run = (files, contents = {}, opts = {}) =>
  checkRepo(files, {
    readText: (f) => contents[f] ?? '',
    size: (f) => opts.sizes?.[f] ?? 0,
    gitignore: opts.gitignore === undefined ? GITIGNORE : opts.gitignore,
  });

test('extractLinkTargets: 通常のリンクと画像を拾う', () => {
  assert.deepEqual(extractLinkTargets('[a](x.md) と ![b](img/y.png "t")'), ['x.md', 'img/y.png']);
});

test('extractLinkTargets: コードスパンとコードブロックの中は除く', () => {
  const md = ['`[a](../x.md)` と [b](ok.md)', '```text', '[c](../y.md)', '```', '[d](z.md)'].join('\n');
  assert.deepEqual(extractLinkTargets(md), ['ok.md', 'z.md']);
});

test('extractLinkTargets: 山括弧のリンク先と参照形式', () => {
  assert.deepEqual(extractLinkTargets('[a](<dir/a b.md>) [r][ref]\n\n[ref]: ../x.md'), ['dir/a b.md']);
});

test('checkLinkBoundary: ../ の解決', () => {
  assert.equal(checkLinkBoundary('docs/specs/a.md', '../tasks.md'), null);
  assert.match(checkLinkBoundary('docs/a.md', '../../outside/x.md'), /リポジトリの外/);
  assert.match(checkLinkBoundary('README.md', '../other/x.md'), /リポジトリの外/);
});

test('checkLinkBoundary: data/ を指すリンク', () => {
  assert.match(checkLinkBoundary('README.md', 'data/cards/1.png'), /data\//);
  assert.match(checkLinkBoundary('docs/a.md', '../data/x.pdf#p1'), /data\//);
  assert.equal(checkLinkBoundary('docs/a.md', 'data/x.md'), null); // docs/data/x.md は問題なし
  assert.equal(checkLinkBoundary('README.md', 'database/x.md'), null);
});

test('checkLinkBoundary: スキームつき・フラグメントのみは見ない', () => {
  assert.equal(checkLinkBoundary('a.md', 'https://example.com/../data/x'), null);
  assert.equal(checkLinkBoundary('a.md', 'mailto:a@example.com'), null);
  assert.equal(checkLinkBoundary('a.md', '#section'), null);
});

test('checkRepo: 問題が無ければ空', () => {
  assert.deepEqual(run(['README.md', 'src/modules/php/Game.php']), []);
});

test('checkRepo: data/ の追跡と .gitignore の /data/ 行', () => {
  assert.equal(run(['data/cards/1.png']).length, 1);
  assert.equal(run(['README.md'], {}, { gitignore: 'node_modules/\n' }).length, 1);
  assert.equal(run(['README.md'], {}, { gitignore: null }).length, 1);
  assert.equal(run(['.worktrees/x/a.md']).length, 1);
});

test('checkRepo: 禁止ファイルとスタブ', () => {
  assert.equal(run(['.env']).length, 1);
  assert.deepEqual(run(['.env.example']), []);
  assert.equal(run(['sftp.json']).length, 1);
  assert.equal(run(['tools/bga/_ide_helper.php']).length, 1);
});

test('checkRepo: src/ の開発用ファイル・非推奨ファイル・ファイル名', () => {
  assert.equal(run(['src/package.json']).length, 1);
  assert.equal(run(['src/states.inc.php']).length, 1);
  assert.equal(run(['src/img/a b.png']).length, 1);
  assert.equal(run(['src/ts/a.ts']).length, 1);
  assert.deepEqual(run(['docs/a b.md']), []); // src/ 以外のファイル名は制限しない
});

test('checkRepo: src/ の CR', () => {
  assert.equal(run(['src/a.php'], { 'src/a.php': '<?php\r\n' }).length, 1);
  assert.deepEqual(run(['src/a.php'], { 'src/a.php': '<?php\n' }), []);
  assert.deepEqual(run(['src/a.png'], { 'src/a.png': 'x\ry' }), []);
});

test('checkRepo: 1 MB 超の追跡ファイル（src/img/ は除く）', () => {
  assert.equal(run(['docs/a.pdf'], {}, { sizes: { 'docs/a.pdf': 1_000_001 } }).length, 1);
  assert.deepEqual(run(['docs/a.pdf'], {}, { sizes: { 'docs/a.pdf': 1_000_000 } }), []);
  assert.deepEqual(run(['src/img/a.webp'], {}, { sizes: { 'src/img/a.webp': 1_500_000 } }), []);
});

test('checkRepo: Markdown の data/ リンクはコード表記なら成功', () => {
  assert.equal(run(['docs/a.md'], { 'docs/a.md': '[x](../data/a.png)\n' }).length, 1);
  assert.deepEqual(run(['docs/a.md'], { 'docs/a.md': '`data/a.png`\n' }), []);
});
