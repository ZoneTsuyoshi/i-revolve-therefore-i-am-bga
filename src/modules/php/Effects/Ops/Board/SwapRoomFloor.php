<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops\Board;

use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\ChoiceRequest;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\AbstractOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpAnswer;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpContext;
use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;
use Bga\Games\IRevolveThereforeIAm\Material\Corridor;

/**
 * OP-18 `SWAP_ROOM_FLOOR`（M055〜M057）: 回廊上のレベル 1 の 2 つの部屋の床カードを入れ替える。床だけが動き、想い出カード・
 * 床の下のカード・人格は位置に残る（R8-11、Q-60）。人格のいる部屋も対象にできる。答えは `{"a": 位置, "b": 位置}`、スキップは入れ替えない。
 */
final class SwapRoomFloor extends AbstractOp
{
    public function choiceKind(): ChoiceKind
    {
        return ChoiceKind::SWAP_ROOM_FLOORS;
    }

    public function request(OpContext $context): ChoiceRequest
    {
        return new ChoiceRequest(ChoiceKind::SWAP_ROOM_FLOORS, $context->owner, 0, ['rooms' => $this->rooms()]);
    }

    public function execute(OpContext $context, OpAnswer $answer): array
    {
        if ($answer->skipped) {
            return [];
        }
        $a = $answer->data['a'] ?? null;
        $b = $answer->data['b'] ?? null;
        $rooms = $this->rooms();
        if (!is_int($a) || !is_int($b) || $a === $b || !in_array($a, $rooms, true) || !in_array($b, $rooms, true)) {
            throw new EffectViolation('Choose two different level 1 rooms');
        }
        $context->mutator->swapRoomFloors($context->owner, $a, $b);

        return [];
    }

    /**
     * レベル 1 の部屋の位置。
     *
     * @return list<int>
     */
    private function rooms(): array
    {
        return array_values(array_filter(
            range(1, Corridor::ROOM_COUNT),
            static fn (int $pos): bool => Corridor::levelOf($pos) === 1,
        ));
    }
}
