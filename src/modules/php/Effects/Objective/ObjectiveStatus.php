<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Objective;

/**
 * 目標 1 枚の、ある人にとっての現在の評価（008 §6.7）。
 */
final readonly class ObjectiveStatus
{
    /**
     * @param int $x 現在の X（数え直した値）
     * @param int $points X に対する現在の得点（表の最小値に満たなければ 0）
     * @param bool $conditionMet 窓の中で獲得の条件を満たしているか
     */
    public function __construct(
        public int $x,
        public int $points,
        public bool $conditionMet,
    ) {
    }
}
