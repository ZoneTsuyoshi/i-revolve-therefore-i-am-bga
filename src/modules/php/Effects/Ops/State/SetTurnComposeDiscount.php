<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops\State;

use Bga\Games\IRevolveThereforeIAm\Effects\Ops\AbstractOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpAnswer;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpContext;

/**
 * OP-22 `SET_TURN_COMPOSE_DISCOUNT`: このターンの自己構成エネルギーを n 軽減する（POSEIDON）。以降の全ての自己構成アクションに効き、
 * 累積しない（007 §7.3）。
 */
final class SetTurnComposeDiscount extends AbstractOp
{
    public function execute(OpContext $context, OpAnswer $answer): array
    {
        $context->mutator->setTurnComposeDiscount($context->node->intParam('n', 1));

        return [];
    }
}
