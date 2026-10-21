<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\MemoryCardRepository;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\PublicView;

/**
 * 暫定のスコア計算: 自己構成済みの想い出カードの印刷スコアの合計と、I08 の下のカード 1 枚につき 1 点だけ。
 * TODO(I-10): 獲得した目標カードの得点（ObjectiveEvaluator）と初期自我 I8 の加算を足した ScoreCalculator に置き換える。
 */
final class PrintedScoreCalculator implements ScoreCalculator
{
    public function __construct(private readonly MemoryCardRepository $memory)
    {
    }

    public function scores(array $seats): array
    {
        $tableau = $this->memory->inLocAnyArg(MemoryLocation::Tableau);
        $under = $this->memory->inLocAnyArg(MemoryLocation::UnderCard);
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
            $out[$playerId] = ['memory' => $memory, 'attached' => $attached, 'objective' => 0, 'total' => $memory + $attached];
        }

        return $out;
    }
}
