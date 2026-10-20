// check-assets.mjs の自己テスト（仕様書 004 §2.9）。実行: npm run test:lint
import assert from 'node:assert/strict';
import { mkdtemp, mkdir, writeFile, rm } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';
import sharp from 'sharp';
import { checkAssets } from './check-assets.mjs';

async function withDir(setup, fn) {
  const dir = await mkdtemp(path.join(os.tmpdir(), 'ir-assets-'));
  try {
    await setup(dir);
    return await fn(dir);
  } finally {
    await rm(dir, { recursive: true, force: true });
  }
}

const png = (w, h) => sharp({ create: { width: w, height: h, channels: 3, background: '#000' } }).png().toBuffer();

test('ディレクトリが無ければ成功', async () => {
  const r = await checkAssets({ imgDir: path.join(os.tmpdir(), 'ir-assets-none-xyz') });
  assert.deepEqual(r.errors, []);
});

test('規則に合う画像は成功', async () => {
  await withDir(async (d) => writeFile(path.join(d, 'ir_a.png'), await png(10, 10)), async (d) => {
    const r = await checkAssets({ imgDir: d });
    assert.deepEqual(r.errors, []);
    assert.deepEqual(r.warnings, []);
  });
});

test('4096px 超・大文字のファイル名・画像でないファイル・サブディレクトリは失敗', async () => {
  await withDir(async (d) => {
    await writeFile(path.join(d, 'big.png'), await png(4097, 1));
    await writeFile(path.join(d, 'Bad.png'), await png(1, 1));
    await writeFile(path.join(d, 'a.txt'), 'x');
    await mkdir(path.join(d, 'sub'));
  }, async (d) => {
    const r = await checkAssets({ imgDir: d });
    assert.equal(r.errors.length, 4);
  });
});

test('1 ファイル 2,000,000 バイト超は失敗', async () => {
  await withDir(async (d) => writeFile(path.join(d, 'big.svg'), Buffer.alloc(2_000_001, 0x20)), async (d) => {
    const r = await checkAssets({ imgDir: d });
    assert.match(r.errors[0], /big\.svg が 2000001 バイト/);
  });
});

test('ファイル数 10 以上は警告のみ', async () => {
  await withDir(async (d) => {
    for (let i = 0; i < 10; i++) await writeFile(path.join(d, `a${i}.svg`), '<svg xmlns="http://www.w3.org/2000/svg"/>');
  }, async (d) => {
    const r = await checkAssets({ imgDir: d });
    assert.deepEqual(r.errors, []);
    assert.equal(r.warnings.length, 1);
  });
});
