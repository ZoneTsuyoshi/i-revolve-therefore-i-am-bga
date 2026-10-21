<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\ActionAvailability;
use Bga\Games\IRevolveThereforeIAm\Material\ComposeContext;

/**
 * 自己構成・精神分析を行える状況かの答え（{@see ActionAvailability}）。エンジンが 61・62 へ出る前の判断と、トークンの空撃ち防止（Q-28）に使う。
 */
final class ActionAvailabilityService implements ActionAvailability
{
    public function __construct(
        private readonly ComposePlanner $compose,
        private readonly AnalysisPlanner $analysis,
    ) {
    }

    public function canCompose(int $player, ComposeContext $context, int $discount): bool
    {
        return $this->compose->canCompose($player, $context, $discount);
    }

    public function canAnalyze(int $player, int $discount): bool
    {
        return $this->analysis->canAnalyze($player, $discount);
    }
}
