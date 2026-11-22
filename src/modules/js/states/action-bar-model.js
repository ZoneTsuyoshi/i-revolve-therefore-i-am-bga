/**
 * Action Bar と盤面の部品からの操作の判断（仕様書 010 §2。純粋関数）。ボタンの並び（青 → 白 → 赤）、トークンを押せるかと次のコスト、
 * 獲得できる目標の選択。DOM には触れない。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/** @typedef {'primary' | 'secondary' | 'alert'} ButtonColor */

const COLOR_RANK = { primary: 0, secondary: 1, alert: 2 };

/**
 * ボタンを 青（primary）→ 白（secondary）→ 赤（alert）の順に並べ直す（同じ色の中は追加順のまま。010 §2.2）。
 * @template {{ params?: { color?: ButtonColor } }} T
 * @param {T[]} buttons
 * @returns {T[]}
 */
export function sortButtons(buttons) {
  return buttons
    .map((button, index) => ({ button, index }))
    .sort((a, b) => (COLOR_RANK[a.button.params?.color ?? 'primary'] - COLOR_RANK[b.button.params?.color ?? 'primary']) || (a.index - b.index))
    .map((entry) => entry.button);
}

/**
 * 心光を最大で何回行使できるか（持っている数で払える範囲。行使回数の上限も超えない）。
 * @param {(uses: number) => number} cumulative n 回行使したときの合計の個数（n = 0 なら 0）
 * @param {number} held 持っている（払える）個数
 * @param {number} maxUses 行使回数の上限
 * @returns {number}
 */
export function lightLimit(cumulative, held, maxUses) {
  let uses = 0;
  while (uses < maxUses && cumulative(uses + 1) <= held) {
    uses++;
  }
  return uses;
}

/**
 * 1 回ごとの個数（n 回目は `costOf(n)` 個）を合計にした関数。
 * @param {(n: number) => number} costOf
 * @returns {(uses: number) => number}
 */
export function cumulativeOf(costOf) {
  return (uses) => {
    let sum = 0;
    for (let n = 1; n <= uses; n++) {
      sum += costOf(n);
    }
    return sum;
  };
}

/**
 * 数量を選ぶトークンの見た目の判断。上限に達したら押せない。次のコスト（N 回目は N 個で 1 つ分）は押せるときだけ出す。
 * @param {{ count: number, max: number, cumulative?: (uses: number) => number }} state count: 選択中の回数、max: 上限
 * @returns {{ canAdd: boolean, canRemove: boolean, nextCost: number | null }}
 */
export function tokenQuantityView({ count, max, cumulative = (uses) => uses }) {
  const canAdd = count < max;
  return {
    canAdd,
    canRemove: count > 0,
    nextCost: canAdd ? cumulative(count + 1) - cumulative(count) : null,
  };
}

/**
 * 数量を選ぶトークンの 1 回押すごとの結果（上限・下限を超えない）。
 * @param {number} count
 * @param {1 | -1} delta
 * @param {number} max
 * @returns {number}
 */
export function stepTokenCount(count, delta, max) {
  return Math.max(0, Math.min(max, count + delta));
}

/**
 * 獲得できる目標の選択の状態（010 §2.4）。選んだ目標が獲得できなくなったら選択を外す。
 * @param {{ key: string, id: number, points: number }[]} claimable 獲得できる目標（`claimableObjectives`）
 * @param {string | null} selectedKey 選択中の目標のキー（`O001`）
 * @returns {{ keys: string[], selected: { key: string, id: number, points: number } | null }}
 */
export function claimView(claimable, selectedKey) {
  return {
    keys: claimable.map((goal) => goal.key),
    selected: claimable.find((goal) => goal.key === selectedKey) ?? null,
  };
}

/**
 * 自己構成フェイズのトークンの使用ボタンを出すか（0 個なら出さない。010 §2.3）。
 * @param {number} count
 * @returns {boolean}
 */
export function showTokenUseButton(count) {
  return count >= 1;
}
