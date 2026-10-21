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
use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;

/**
 * OP-13 `GAIN_ENHANCED_EGO`: 強化自我の山の全カードを本人にだけ見せ、1 枚を獲得して公開する（Q-08）。
 * 候補は公開の選択に含めない（71 の `_private` が山から作る）。山が空なら飛ばし、1 枚だけなら選ばずに取る。
 */
final class GainEnhancedEgo extends AbstractOp
{
    public function choiceKind(): ChoiceKind
    {
        return ChoiceKind::PICK_ENHANCED_EGO;
    }

    public function request(OpContext $context): ?ChoiceRequest
    {
        if (count($context->view->enhancedEgoIds()) < 2) {
            return null;
        }

        return new ChoiceRequest(ChoiceKind::PICK_ENHANCED_EGO, $context->owner, 0);
    }

    public function execute(OpContext $context, OpAnswer $answer): array
    {
        $candidates = $context->view->enhancedEgoIds();
        if ($candidates === []) {
            return [];
        }
        if (count($candidates) === 1) {
            $ego = $candidates[0];
        } else {
            $ego = $answer->data['ego'] ?? null;
            if (!is_string($ego) || !in_array($ego, $candidates, true)) {
                throw new EffectViolation('Choose one of the enhanced ego cards in the deck');
            }
        }
        $context->mutator->obtainEnhancedEgo($context->owner, $ego);

        return [];
    }
}
