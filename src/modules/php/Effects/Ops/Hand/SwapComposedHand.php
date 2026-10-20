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
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;

/**
 * OP-09 `SWAP_COMPOSED_HAND`: 自己構成済みの 1 枚と、同じコストの手札の 1 枚を交換してもよい（R8-10、Q-59）。
 * 自己構成ではない扱いで EV-05 は起こさない。同名のカードは自己構成済みに 2 枚置けない（001 §9-1）ので、
 * 手札のカードが交換相手以外の自己構成済みカードと同名なら対にできない。手札のコストは MD-02 込みの現在値。
 * 候補が無ければ飛ばす。スコアが変わるので勝利判定を行う（`Op::checksVictory()`）。
 */
final class SwapComposedHand extends AbstractOp
{
    public function choiceKind(): ChoiceKind
    {
        return ChoiceKind::SELECT_SWAP_SAME_COST;
    }

    public function request(OpContext $context): ?ChoiceRequest
    {
        $pairs = $this->pairs($context);
        if ($pairs === []) {
            return null;
        }

        return new ChoiceRequest(ChoiceKind::SELECT_SWAP_SAME_COST, $context->owner, 0, ['pairs' => $pairs]);
    }

    public function execute(OpContext $context, OpAnswer $answer): array
    {
        if ($answer->skipped || $this->request($context) === null) {
            return [];
        }
        $tableauId = $answer->data['tableau'] ?? null;
        $handId = $answer->data['hand'] ?? null;
        if (!is_int($tableauId) || !is_int($handId) || !in_array([$tableauId, $handId], $this->pairs($context), true)) {
            throw new EffectViolation('The pair cannot be swapped (cost, name or ownership mismatch)');
        }
        $context->mutator->swapComposedAndHand($context->owner, $tableauId, $handId);

        return [];
    }

    /**
     * 交換できる (自己構成済みのカード, 手札のカード) の対。
     *
     * @return list<array{int, int}>
     */
    private function pairs(OpContext $context): array
    {
        $view = $context->view;
        $tableau = $view->tableauIds($context->owner);
        $pairs = [];
        foreach ($tableau as $tableauId) {
            $composed = MemoryCards::get($view->cardType($tableauId));
            $otherTypes = [];
            foreach ($tableau as $otherId) {
                if ($otherId !== $tableauId) {
                    $otherTypes[] = $view->cardType($otherId);
                }
            }
            foreach ($view->handIds($context->owner) as $handId) {
                if (
                    !in_array($view->cardType($handId), $otherTypes, true)
                    && $context->energy->heldEnergy($context->owner, $handId) === $composed->energy
                ) {
                    $pairs[] = [$tableauId, $handId];
                }
            }
        }

        return $pairs;
    }
}
