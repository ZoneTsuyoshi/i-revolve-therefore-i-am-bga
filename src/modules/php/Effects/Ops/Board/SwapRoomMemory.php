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
 * OP-17 `SWAP_ROOM_MEMORY`（M052〜M054）: 回廊上の同じレベルの 2 つの部屋の想い出カード（裏向き）を入れ替える。
 * 人格のいる部屋も対象にできる（R8-11、Q-60）。答えは `{"a": 位置, "b": 位置}`、スキップは入れ替えない。
 * 入れ替える候補が無ければ（同じレベルで想い出カードのある部屋が 2 つ無ければ）聞かずに飛ばす。
 */
final class SwapRoomMemory extends AbstractOp
{
    public function choiceKind(): ChoiceKind
    {
        return ChoiceKind::SWAP_ROOM_CARDS;
    }

    public function request(OpContext $context): ?ChoiceRequest
    {
        $rooms = $this->rooms($context);
        if (!$this->hasPair($rooms)) {
            return null;
        }

        return new ChoiceRequest(ChoiceKind::SWAP_ROOM_CARDS, $context->owner, 0, ['rooms' => $rooms]);
    }

    public function execute(OpContext $context, OpAnswer $answer): array
    {
        if ($answer->skipped || $this->request($context) === null) {
            return [];
        }
        $a = $answer->data['a'] ?? null;
        $b = $answer->data['b'] ?? null;
        $rooms = $this->rooms($context);
        if (
            !is_int($a) || !is_int($b) || $a === $b || !in_array($a, $rooms, true) || !in_array($b, $rooms, true)
            || Corridor::levelOf($a) !== Corridor::levelOf($b)
        ) {
            throw new EffectViolation(clienttranslate('Choose two different rooms of the same level'));
        }
        $context->mutator->swapRoomCards($context->owner, $a, $b);

        return [];
    }

    /**
     * 想い出カードのある部屋の位置。
     *
     * @return list<int>
     */
    private function rooms(OpContext $context): array
    {
        $rooms = [];
        for ($pos = 1; $pos <= Corridor::ROOM_COUNT; $pos++) {
            if ($context->view->roomCardId($pos) !== null) {
                $rooms[] = $pos;
            }
        }

        return $rooms;
    }

    /**
     * @param list<int> $rooms
     */
    private function hasPair(array $rooms): bool
    {
        $byLevel = [1 => 0, 2 => 0];
        foreach ($rooms as $pos) {
            $byLevel[Corridor::levelOf($pos)]++;
        }

        return $byLevel[1] >= 2 || $byLevel[2] >= 2;
    }
}
