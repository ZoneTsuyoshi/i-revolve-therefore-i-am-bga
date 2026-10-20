<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops\Hand;

use Bga\Games\IRevolveThereforeIAm\Effects\Engine\ChoiceRequest;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\AbstractOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpAnswer;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpContext;
use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;

/**
 * OP-08 `SWAP_HAND_OPEN`: 手札と場札を好きな枚数入れ替える（0 枚可）。同数で、手札から場へ出せるのはレベル 1 のカードだけ
 * （R8-9、Q-58）。手札または場札が 0 枚（出せるカードが無い）なら飛ばす。
 */
final class SwapHandOpen extends AbstractOp
{
    public function choiceKind(): ChoiceKind
    {
        return ChoiceKind::SELECT_SWAP_HAND_FIELD;
    }

    public function request(OpContext $context): ?ChoiceRequest
    {
        if ($context->view->fieldIds() === [] || $this->level1Hand($context) === []) {
            return null;
        }

        return new ChoiceRequest(ChoiceKind::SELECT_SWAP_HAND_FIELD, $context->owner, 0, ['field' => $context->view->fieldIds()]);
    }

    public function execute(OpContext $context, OpAnswer $answer): array
    {
        if ($answer->skipped || $this->request($context) === null) {
            return [];
        }
        $hand = $this->intList($answer, 'hand');
        $field = $this->intList($answer, 'field');
        $this->assertCount(count($hand), count($field), 'field cards (same number as hand cards)');
        $this->assertSubset($hand, $this->level1Hand($context), 'the level 1 hand cards');
        $this->assertSubset($field, $context->view->fieldIds(), 'the field');
        if ($hand !== []) {
            $context->mutator->swapHandAndField($context->owner, $hand, $field);
        }

        return [];
    }

    /**
     * @return list<int>
     */
    private function level1Hand(OpContext $context): array
    {
        $ids = [];
        foreach ($context->view->handIds($context->owner) as $cardId) {
            if (MemoryCards::get($context->view->cardType($cardId))->level === 1) {
                $ids[] = $cardId;
            }
        }

        return $ids;
    }
}
