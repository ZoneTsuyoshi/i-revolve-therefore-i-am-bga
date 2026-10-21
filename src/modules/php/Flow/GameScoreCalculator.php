<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\MemoryCardRepository;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\ObjectiveRepository;
use Bga\Games\IRevolveThereforeIAm\Data\PublicView;
use Bga\Games\IRevolveThereforeIAm\Effects\Objective\ObjectiveEvaluator;

/**
 * 自己構成スコアの本番の計算（仕様書 008 §6.7）。
 * 自己構成済みの想い出カードの印刷スコアの合計 + I08 の下の裏向きのカードの枚数 + 獲得した目標カードの現在の得点。
 * 目標の得点は呼ぶたびに X を数え直し、保存しない（Q-13）。獲得した目標は手放さないが、X が下がれば得点も下がる。
 */
final class GameScoreCalculator implements ScoreCalculator
{
    public function __construct(
        private readonly MemoryCardRepository $memory,
        private readonly ObjectiveRepository $objectives,
        private readonly ObjectiveEvaluator $evaluator,
    ) {
    }

    public function scores(array $seats): array
    {
        $tableau = $this->memory->inLocAnyArg(MemoryLocation::Tableau);
        $under = $this->memory->inLocAnyArg(MemoryLocation::UnderCard);
        $owned = array_filter($this->objectives->all(), static fn (array $o): bool => $o['owner'] !== null);
        $out = [];
        foreach ($seats as $playerId) {
            $memory = 0;
            $attached = 0;
            foreach ($tableau as $card) {
                if ($card->locationArg !== $playerId) {
                    continue;
                }
                $memory += PublicView::definition($card->type)->score;
                $attached += count(array_filter($under, static fn ($u): bool => $u->locationArg === $card->id));
            }
            $objective = 0;
            foreach ($owned as $o) {
                if ($o['owner'] === $playerId) {
                    $objective += $this->evaluator->scoreOf(ObjectiveEvaluator::cardId($o['id']), $playerId);
                }
            }
            $out[$playerId] = ['memory' => $memory, 'attached' => $attached, 'objective' => $objective, 'total' => $memory + $attached + $objective];
        }

        return $out;
    }
}
