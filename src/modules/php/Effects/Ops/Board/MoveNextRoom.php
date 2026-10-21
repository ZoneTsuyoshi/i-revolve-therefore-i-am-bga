<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops\Board;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\AbstractOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpAnswer;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpContext;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\FloorCards;

/**
 * OP-15 `MOVE_NEXT_ROOM`: 想起効果による移動。次のレベル `level` の部屋まで時計回りに進む。入室コストは払わない（Q-06）。
 * 通過したレベル 2 の部屋の周遊ボーナスと、到着した部屋がレベル 2 ならその周遊ボーナスを得て（FAQ 行 8）、到着した部屋を
 * 入室に数える（12 部屋で刹那の追憶。Q-09）。EV-03（止まった。`via = RECALL_MOVE`）を起こす。移動先の想い出カードは表向きにしない
 * （FAQ 行 9）。ターンプレイヤーだけが行う（Q-39）。
 */
final class MoveNextRoom extends AbstractOp
{
    /** レベル 2 の部屋の位置（四隅。001 §3） */
    private const LEVEL2_POSITIONS = [1, 4, 7, 10];

    public function execute(OpContext $context, OpAnswer $answer): array
    {
        if (!$context->isTurnPlayer()) {
            return [];
        }
        $level = $context->node->intParam('level', 1);
        $player = $context->owner;
        $pos = $context->view->personaPosition($player);
        for ($i = 0; $i < 12; $i++) {
            $pos = $pos % 12 + 1;
            $roomLevel = in_array($pos, self::LEVEL2_POSITIONS, true) ? 2 : 1;
            if ($roomLevel === $level) {
                break;
            }
            if ($roomLevel === 2) {
                // 通過したレベル 2 の部屋（スキップ）の周遊ボーナス
                $context->mutator->grantFloorBonus($player, FloorCards::get($context->view->floorIdAt($pos))->bonus);
            }
        }
        $context->mutator->movePersona($player, $pos, true);
        if ($level === 2) {
            $context->mutator->grantFloorBonus($player, FloorCards::get($context->view->floorIdAt($pos))->bonus);
        }
        $others = array_values(array_filter(
            $context->view->playersAt($pos),
            static fn (int $id): bool => $id !== $player,
        ));

        return [new GameEvent(Event::PERSONA_STOPPED, $player, [
            'room' => $pos,
            'via' => 'RECALL_MOVE',
            'other_persona' => $others !== [],
        ])];
    }
}
