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
 * OP-23 `ADD_NEXT_COMPOSE_DISCOUNT`: 「このターンで次の自己構成アクション」のエネルギー軽減を n 足す。
 * ターンプレイヤーだけが得る（ターンプレイヤー以外の発動は何も起きない。FAQ 行 10）。
 */
final class AddNextComposeDiscount extends AbstractOp
{
    public function execute(OpContext $context, OpAnswer $answer): array
    {
        if ($context->isTurnPlayer()) {
            $context->mutator->addNextComposeDiscount($context->node->intParam('n', 1));
        }

        return [];
    }
}
