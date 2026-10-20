/**
 * 領域の並び順（仕様書 009 §2.5）。DOM に触れない純粋関数。
 * 自分の領域を先頭に、残りは席順で自分の次から時計回り。観戦者・リプレイ（自分がいない）は全員を席順に並べる。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/**
 * @param {unknown} playerOrder `gamedatas.playerorder`（席順の ID。文字列のことがある）。無くてもよい
 * @param {Record<string, unknown>} players `gamedatas.players`（キーが ID）
 * @param {number | null} me 自分の ID（観戦者は null）
 * @returns {number[]}
 */
export function orderPlayers(playerOrder, players, me) {
  const ids = Object.keys(players ?? {}).map(Number).filter(Number.isFinite);
  const seats = (Array.isArray(playerOrder) ? playerOrder : []).map(Number).filter((id) => ids.includes(id));
  // playerorder に無い ID は末尾に昇順で足す
  const seated = [...seats, ...ids.filter((id) => !seats.includes(id)).sort((a, b) => a - b)];
  const myIndex = me === null ? -1 : seated.indexOf(me);
  return myIndex < 0 ? seated : [...seated.slice(myIndex), ...seated.slice(0, myIndex)];
}
