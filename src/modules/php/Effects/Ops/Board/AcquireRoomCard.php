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
 * OP-26 `ACQUIRE_ROOM_CARD`: ターンプレイヤーが、想起フェイズ開始時の部屋の想い出カードを手札に加える（Q-04）。
 * 部屋は空になり、クリーンアップ c で補充される。獲得するのは開始時の部屋のカード（FAQ 行 9）。
 */
final class AcquireRoomCard extends AbstractOp
{
    public function execute(OpContext $context, OpAnswer $answer): array
    {
        $position = $context->view->recallRoomPosition();
        if ($position === null || !$context->isTurnPlayer()) {
            throw new \LogicException('ACQUIRE_ROOM_CARD needs the turn player in a recall phase');
        }
        $context->mutator->takeRoomCard($context->owner, $position);

        return [];
    }
}
