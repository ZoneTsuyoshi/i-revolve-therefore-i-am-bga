<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops\Board;

use Bga\Games\IRevolveThereforeIAm\Effects\Ops\AbstractOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpAnswer;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpContext;

/**
 * OP-21 `COLLECT_UNDER_FLOOR`（I04.2）: 人格が止まった部屋（誘発したイベントの `room`）の床カードの下の想い出カードを、
 * 全て手札に加える。強制（FAQ 行 33）。上限を超えたら単位の終わりに捨てる。想起効果の移動先でも取る。
 */
final class CollectUnderFloor extends AbstractOp
{
    public function execute(OpContext $context, OpAnswer $answer): array
    {
        $room = $context->payload['room'] ?? null;
        if (!is_int($room) || $context->view->floorUnderIds($room) === []) {
            return [];
        }
        $context->mutator->collectUnderFloor($context->owner, $room);

        return [];
    }
}
