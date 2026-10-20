<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops\Hand;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\ChoiceRequest;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\AbstractOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpAnswer;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpContext;
use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\Origin;

/**
 * OP-07 `DISCARD_FOR_LIGHT`: 手札から好きな枚数（0 枚可）を捨て、捨てた枚数だけ心光を得る。レベル 2 も 1 枚と数える
 * （R8-8、Q-57）。手札が 0 枚なら飛ばす。
 */
final class DiscardForLight extends AbstractOp
{
    public function choiceKind(): ChoiceKind
    {
        return ChoiceKind::SELECT_DISCARD_FOR_LIGHT;
    }

    public function request(OpContext $context): ?ChoiceRequest
    {
        if ($context->view->handIds($context->owner) === []) {
            return null;
        }

        return new ChoiceRequest(ChoiceKind::SELECT_DISCARD_FOR_LIGHT, $context->owner, 0);
    }

    public function execute(OpContext $context, OpAnswer $answer): array
    {
        if ($answer->skipped || $this->request($context) === null) {
            return [];
        }
        $discarded = $this->intList($answer, 'cards');
        $this->assertSubset($discarded, $context->view->handIds($context->owner), 'the hand');
        if ($discarded === []) {
            return [];
        }

        $origin = $context->node->originParam() ?? Origin::EFFECT;
        $context->mutator->discardFromHand($context->owner, $discarded);
        $context->mutator->gainLight($context->owner, count($discarded), $origin);

        return [new GameEvent(Event::LIGHT_GAINED, $context->owner, ['count' => count($discarded), 'origin' => $origin->value])];
    }
}
