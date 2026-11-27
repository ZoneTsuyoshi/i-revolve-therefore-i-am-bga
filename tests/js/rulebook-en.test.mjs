// 英語版ルールブックの原稿の検査と、ビルドの純粋な関数のテスト。data/ も Chrome も要らない。実行: npm run test:js
import assert from 'node:assert/strict';
import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import { test } from 'node:test';
import { PAGE_IDS, parseArgs, parseOnly } from '../../scripts/rulebook-en/lib/args.mjs';
import { findJapanese, findMissingVars, hasScript, stripComments, styleVars } from '../../scripts/rulebook-en/lib/html.mjs';
import { countPdfPages } from '../../scripts/rulebook-en/lib/pdf.mjs';
import { takeOpts } from '../../scripts/rulebook-en/measure.mjs';

const PAGES = path.resolve(import.meta.dirname, '../../scripts/rulebook-en/pages');

test('原稿 pages/*.html: コメントの外に日本語が無く、<script> が無く、.t・.e の変数がそろっている', async () => {
  const files = (await readdir(PAGES)).filter((f) => f.endsWith('.html'));
  assert.ok(files.length > 0);
  for (const f of files) {
    assert.match(f, /^p[1-8]\.html$/, `${f}: ファイル名は p1〜p8`);
    const html = await readFile(path.join(PAGES, f), 'utf8');
    assert.deepEqual(findJapanese(html), [], `${f}: 日本語の文字が残っている`);
    assert.equal(hasScript(html), false, `${f}: <script> は書かない`);
    assert.deepEqual(findMissingVars(html), [], `${f}: --x --y --w --h が足りない`);
  }
});

test('findJapanese: コメントの中は許し、外は行番号つきで報告する', () => {
  assert.deepEqual(findJapanese('<!-- 日本語のメモ -->\n<div>English</div>'), []);
  assert.deepEqual(findJapanese('<!--\n日本語\n-->\n<div>ひらがな</div>'), [{ line: 4, text: '<div>ひらがな</div>' }]);
  assert.equal(findJapanese('<div>「かぎ」</div>').length, 1);
  assert.equal(findJapanese('<div>全角ＡＢＣ</div>').length, 1);
  assert.deepEqual(findJapanese('<div>“quote” — dash ≧ 20</div>'), []);
});

test('findMissingVars: 足りない変数と、数値でない値を報告する', () => {
  assert.deepEqual(findMissingVars('<div class="t" style="--x:1;--y:2;--w:3;--h:4;--fs:7">a</div>'), []);
  assert.deepEqual(findMissingVars('<div class="e" style="--x:1;--y:2;--w:3"></div>'), [{ tag: '<div class="e" style="--x:1;--y:2;--w:3">', missing: ['h'] }]);
  assert.equal(findMissingVars('<div class="t" style="--x:1;--y:2;--w:3;--h:4;--fs:7pt">a</div>').length, 1);
  assert.deepEqual(findMissingVars('<div class="li"><span class="b7">x</span></div>'), []);
  assert.deepEqual(findMissingVars('<!-- <div class="t"> --><div class="c">x</div>'), []);
});

test('stripComments・styleVars・hasScript', () => {
  assert.equal(stripComments('a<!-- x -->b'), 'ab');
  assert.deepEqual(styleVars('--x:1; --fit-min : 0.9;color:red'), { x: '1', 'fit-min': '0.9' });
  assert.equal(hasScript('<script>1</script>'), true);
  assert.equal(hasScript('<!-- <script> -->'), false);
});

test('parseOnly: p3・3・重複・順序', () => {
  assert.deepEqual(parseOnly('p5,3,p3'), ['p3', 'p5']);
  assert.throws(() => parseOnly('p9'), /知らないページ/);
  assert.throws(() => parseOnly(''), /指定/);
  assert.equal(PAGE_IDS.length, 8);
});

test('parseArgs: 既定値と各オプション。不正な値は例外', () => {
  assert.deepEqual(parseArgs([]), { only: null, png: false, debug: false, dpi: 300, quality: 88, help: false });
  assert.deepEqual(parseArgs(['--only', 'p4', '--png', '--debug', '--dpi', '200', '--quality', '70']), { only: ['p4'], png: true, debug: true, dpi: 200, quality: 70, help: false });
  assert.throws(() => parseArgs(['--nope']), /不明/);
  assert.throws(() => parseArgs(['--dpi']), /値がありません/);
  assert.throws(() => parseArgs(['--dpi', '10']), /72/);
  assert.throws(() => parseArgs(['--quality', '0']), /1〜100/);
});

test('countPdfPages: ページツリーの /Count を読む。読めなければ null', () => {
  assert.equal(countPdfPages(Buffer.from('<< /Type /Pages /Kids [1 0 R] /Count 8 >>')), 8);
  assert.equal(countPdfPages(Buffer.from('<< /Count 2 /Kids [1 0 R] /Type /Pages >>')), 2);
  assert.equal(countPdfPages(Buffer.from('%PDF nothing')), null);
});

test('takeOpts: 値つきオプションとフラグを取り出し、残りを返す', () => {
  const { opts, rest } = takeOpts(['bg', 'p4', '--zoom', '3', '1', '--all'], ['zoom', 'grid', 'all']);
  assert.deepEqual(opts, { zoom: '3', all: true });
  assert.deepEqual(rest, ['bg', 'p4', '1']);
});
