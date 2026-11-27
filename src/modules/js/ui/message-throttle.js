/**
 * 選べないものを押したときのメッセージ（仕様書 011 §5.7）の出しすぎを防ぐ純粋関数。同じ文は 2 秒以内に繰り返さない。
 * DOM にも BGA にも依存しない。状態（最後に出した時刻）は呼び出し側が持ち、返り値の `history` を次の呼び出しに渡す。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** 同じ文を繰り返さない間隔（ミリ秒） */
export const MESSAGE_INTERVAL_MS = 2000;

/**
 * メッセージを今出してよいか。
 * @param {Record<string, number>} history 文 → 最後に出した時刻（ミリ秒）
 * @param {string} message
 * @param {number} now 今の時刻（ミリ秒）
 * @param {number} [interval] 繰り返さない間隔
 * @returns {{ show: boolean, history: Record<string, number> }} 出すなら show が真で、history に今の時刻を入れて返す。元の history は変えない
 */
export function shouldShow(history, message, now, interval = MESSAGE_INTERVAL_MS) {
  const last = history[message];
  if (last !== undefined && now - last < interval && now >= last) {
    return { show: false, history };
  }
  // 古い記録は捨てる（履歴が増え続けない）
  /** @type {Record<string, number>} */
  const next = {};
  for (const [text, time] of Object.entries(history)) {
    if (now - time < interval && now >= time) {
      next[text] = time;
    }
  }
  next[message] = now;
  return { show: true, history: next };
}
