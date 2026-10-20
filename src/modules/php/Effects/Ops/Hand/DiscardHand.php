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

/**
 * OP-06 `DISCARD_HAND`: 手札から n 枚を捨てる（必須。ちょうど n 枚）。手札が n 枚以下なら選ぶ余地が無いので全部を捨てる。
 * 捨札はカードのレベルの捨札（Q-23）。手札の中身はクライアントが持つので、選択の内容には枚数だけを入れる。
 */
final class DiscardHand extends AbstractOp
{
    public function choiceKind(): ChoiceKind
    {
        return ChoiceKind::PICK_DISCARD;
    }

    public function request(OpContext $context): ?ChoiceRequest
    {
        $count = $context->node->intParam('n', 1);
        if (count($context->view->handIds($context->owner)) <= $count) {
            return null;
        }

        return new ChoiceRequest(ChoiceKind::PICK_DISCARD, $context->owner, 0, ['count' => $count]);
    }

    public function execute(OpContext $context, OpAnswer $answer): array
    {
        $count = $context->node->intParam('n', 1);
        $hand = $context->view->handIds($context->owner);

        if ($this->request($context) === null) {
            $discarded = $hand;
        } else {
            $discarded = $this->intList($answer, 'cards');
            $this->assertCount(count($discarded), $count, 'cards to discard');
            $this->assertSubset($discarded, $hand, 'the hand');
        }
        if ($discarded !== []) {
            $context->mutator->discardFromHand($context->owner, $discarded);
        }

        return [];
    }
}
