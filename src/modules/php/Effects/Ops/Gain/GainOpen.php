<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops\Gain;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\ChoiceRequest;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\AbstractOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpAnswer;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpContext;
use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\Origin;

/**
 * OP-02 `GAIN_OPEN`: 場札から n 枚選んで手札に加える。場札が 0 枚なら何もしない（FAQ 行 6）。
 * n 以下の枚数しか無ければ全部を取る（選ぶ余地が無いので聞かない）。場札の補充はクリーンアップまで行われない（001 §13）。
 */
final class GainOpen extends AbstractOp
{
    public function choiceKind(): ChoiceKind
    {
        return ChoiceKind::PICK_FIELD_CARD;
    }

    public function request(OpContext $context): ?ChoiceRequest
    {
        $count = $this->amount($context);
        $field = $context->view->fieldIds();
        if ($count === 0 || count($field) <= $count) {
            return null;
        }

        return new ChoiceRequest(ChoiceKind::PICK_FIELD_CARD, $context->owner, 0, ['count' => $count, 'candidates' => $field]);
    }

    public function execute(OpContext $context, OpAnswer $answer): array
    {
        $count = $this->amount($context);
        $field = $context->view->fieldIds();
        if ($count === 0) {
            return [];
        }

        if ($this->request($context) === null) {
            $taken = $field;
        } else {
            $taken = $this->intList($answer, 'cards');
            $this->assertCount(count($taken), $count, 'field cards');
            $this->assertSubset($taken, $field, 'the field');
        }
        foreach ($taken as $cardId) {
            $context->mutator->takeFromField($context->owner, $cardId);
        }

        return $context->node->originParam() === Origin::FLOOR
            ? [new GameEvent(Event::FLOOR_GAIN, $context->owner, ['element' => 'OPEN'])]
            : [];
    }
}
