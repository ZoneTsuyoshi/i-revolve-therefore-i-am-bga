<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Query;

use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionContext;
use Bga\Games\IRevolveThereforeIAm\Material\Modifier;

/**
 * 想起効果の発動回数（008 §5.1 の MD-11。M062）。想起フェイズ開始時の部屋に他の人格がいるとき、
 * ターンプレイヤーの想起効果の発動回数が 2 になる（他の人格は 1 回。FAQ 行 12〜14）。2 回目は `CONFIRM_USE` で聞く（T8-7）。
 */
final class RecallTimesQuery
{
    public function __construct(private readonly ActiveSpecs $active)
    {
    }

    public function times(int $player, int $roomPosition): int
    {
        if ($player !== $this->active->view()->turnPlayer()) {
            return 1;
        }
        $context = new ConditionContext($player, null, ['room' => $roomPosition]);
        foreach ($this->active->modifiers($player, Modifier::RECALL_TIMES, $context) as $spec) {
            $times = $spec->params['times'] ?? 2;

            return is_int($times) ? $times : 2;
        }

        return 1;
    }
}
