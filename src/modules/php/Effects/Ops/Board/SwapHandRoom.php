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
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;

/**
 * OP-19 `SWAP_HAND_ROOM`（I05）: どの人格もいない部屋の想い出カードと、手札の同じレベルのカード 1 枚を入れ替える。
 * 手札から置くカードは裏向き（FAQ 行 34）。答えは `{"hand": 手札のカード ID, "pos": 部屋の位置}`、スキップは入れ替えない。
 * 公開の候補（`rooms`）は人格のいない部屋の位置だけで、手札のレベルは含めない（手札の情報を漏らさない）。
 */
final class SwapHandRoom extends AbstractOp
{
    public function choiceKind(): ChoiceKind
    {
        return ChoiceKind::ENTREPRENEUR_SWAP;
    }

    public function request(OpContext $context): ?ChoiceRequest
    {
        $rooms = $this->rooms($context);
        if ($rooms === [] || $this->pairs($context) === []) {
            return null;
        }

        return new ChoiceRequest(ChoiceKind::ENTREPRENEUR_SWAP, $context->owner, 0, ['rooms' => $rooms]);
    }

    public function execute(OpContext $context, OpAnswer $answer): array
    {
        if ($answer->skipped || $this->request($context) === null) {
            return [];
        }
        $hand = $answer->data['hand'] ?? null;
        $pos = $answer->data['pos'] ?? null;
        if (!is_int($hand) || !is_int($pos) || !in_array([$hand, $pos], $this->pairs($context), true)) {
            throw new EffectViolation('Choose a hand card and an unoccupied room of the same level');
        }
        $context->mutator->swapHandAndRoom($context->owner, $hand, $pos);

        return [];
    }

    /**
     * 人格のいない、想い出カードのある部屋の位置。
     *
     * @return list<int>
     */
    private function rooms(OpContext $context): array
    {
        $rooms = [];
        for ($pos = 1; $pos <= Corridor::ROOM_COUNT; $pos++) {
            if ($context->view->roomCardId($pos) !== null && $context->view->playersAt($pos) === []) {
                $rooms[] = $pos;
            }
        }

        return $rooms;
    }

    /**
     * 入れ替えられる (手札のカード, 部屋の位置) の対（レベルが同じ）。
     *
     * @return list<array{int, int}>
     */
    private function pairs(OpContext $context): array
    {
        $pairs = [];
        foreach ($context->view->handIds($context->owner) as $cardId) {
            $level = MemoryCards::get($context->view->cardType($cardId))->level;
            foreach ($this->rooms($context) as $pos) {
                if (Corridor::levelOf($pos) === $level) {
                    $pairs[] = [$cardId, $pos];
                }
            }
        }

        return $pairs;
    }
}
