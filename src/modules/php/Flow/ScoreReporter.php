<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

/**
 * N21 `scoreUpdated` の差分を作る（仕様書 007 §11）。前回の値は 1 リクエストの中のメモリにだけ持ち、保存しない
 * （リロード後は getAllDatas が再計算する）。{@see self::prime()} で基準を取り、{@see self::report()} が変わった人だけを返す。
 */
final class ScoreReporter
{
    /** @var array<int, int>|null プレイヤー ID → 前回の合計 */
    private ?array $last = null;

    /**
     * @param \Closure(): list<int> $seats 席順のプレイヤー ID
     */
    public function __construct(
        private readonly ScoreCalculator $calculator,
        private readonly \Closure $seats,
    ) {
    }

    /**
     * 基準を取る（変化の比較の起点）。リクエストの最初に呼ぶ。
     */
    public function prime(): void
    {
        $this->last = $this->totals($this->calculator->scores(($this->seats)()));
    }

    public function report(): ?Notice
    {
        $scores = $this->calculator->scores(($this->seats)());
        $totals = $this->totals($scores);
        $changed = [];
        foreach ($scores as $playerId => $row) {
            if ($this->last === null || ($this->last[$playerId] ?? null) !== $totals[$playerId]) {
                $changed[$playerId] = ['total' => $row['total'], 'memory' => $row['memory'] + $row['attached'], 'objective' => $row['objective']];
            }
        }
        $this->last = $totals;

        return $changed === [] ? null : Notification::scoreUpdated($changed);
    }

    /**
     * @param array<int, array{total: int}> $scores
     * @return array<int, int>
     */
    private function totals(array $scores): array
    {
        return array_map(static fn (array $row): int => $row['total'], $scores);
    }
}
