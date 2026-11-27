// PDF の簡易検査（純粋な関数。build.mjs とテストが使う）

/** PDF のバイト列から、ページツリーの /Count（総ページ数）を読む。読めなければ null */
export function countPdfPages(buf) {
  const text = Buffer.from(buf).toString('latin1');
  let best = null;
  for (const m of text.matchAll(/\/Type\s*\/Pages\b[^>]*?\/Count\s+(\d+)/g)) best = Math.max(best ?? 0, Number(m[1]));
  if (best === null) for (const m of text.matchAll(/\/Count\s+(\d+)[^>]*?\/Type\s*\/Pages\b/g)) best = Math.max(best ?? 0, Number(m[1]));
  return best;
}
