<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

/**
 * 自己構成スコアを返す唯一の口（仕様書 007 §11）。`WinChecker`（断片の結集）と N21 `scoreUpdated` が使う。
 * 本番の実装は {@see GameScoreCalculator}（想い出カードの印刷スコア + I08 の下のカード + 獲得した目標の現在の得点）。
 */
interface ScoreCalculator
{
    /**
     * @param list<int> $seats 評価するプレイヤー
     * @return array<int, array{memory: int, attached: int, objective: int, total: int}> プレイヤー ID → 内訳（total = 3 つの合計）
     */
    public function scores(array $seats): array;
}
