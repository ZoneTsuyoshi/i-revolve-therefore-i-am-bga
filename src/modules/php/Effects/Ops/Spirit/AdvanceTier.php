<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops\Spirit;

use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\ChoiceRequest;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\AbstractOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpAnswer;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpContext;
use Bga\Games\IRevolveThereforeIAm\Material\AnalysisCards;
use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;

/**
 * OP-12 `ADVANCE_TIER`: 精神階層を 1 つ上げる（`via = EFFECT`。M088 の効果）。`max_tier` を超える階層へは上げない。
 * 次の階層が 2 枚あるときは進む先を聞く（`PICK_SPIRIT_BRANCH`）。到着した精神カードの心光とボーナスの frame は
 * `GameMutator::advanceSpirit()` が積む。EV-06 を起こす。
 */
final class AdvanceTier extends AbstractOp
{
    public function choiceKind(): ChoiceKind
    {
        return ChoiceKind::PICK_SPIRIT_BRANCH;
    }

    public function request(OpContext $context): ?ChoiceRequest
    {
        $candidates = $this->candidates($context);
        if (count($candidates) < 2) {
            return null;
        }

        return new ChoiceRequest(ChoiceKind::PICK_SPIRIT_BRANCH, $context->owner, 0, ['candidates' => $candidates]);
    }

    public function execute(OpContext $context, OpAnswer $answer): array
    {
        $candidates = $this->candidates($context);
        if ($candidates === []) {
            return [];
        }
        if (count($candidates) === 1) {
            $to = $candidates[0];
        } else {
            $to = $answer->data['spirit'] ?? null;
            if (!is_string($to) || !in_array($to, $candidates, true)) {
                throw new EffectViolation('Choose one of the spirit cards on the next tier');
            }
        }
        $via = is_string($context->node->params['via'] ?? null) ? $context->node->params['via'] : 'EFFECT';
        // EV-06（TIER_UP）の誘発は、到着した精神カードのボーナスと合わせて順序づけるので、advanceSpirit（SpiritArrival）が
        // 解決スタックに積む。ここでイベントを返すと二重に誘発する。
        $context->mutator->advanceSpirit($context->owner, $to, $via);

        return [];
    }

    /**
     * 進める先の精神カード（`max_tier` 以下の階層）。
     *
     * @return list<string>
     */
    private function candidates(OpContext $context): array
    {
        $max = $context->node->intParam('max_tier', 5);

        return array_values(array_filter(
            $context->view->nextSpiritIds($context->owner),
            static fn (string $id): bool => AnalysisCards::get($id)->floor <= $max,
        ));
    }
}
