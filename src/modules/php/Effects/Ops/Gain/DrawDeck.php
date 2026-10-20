<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops\Gain;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\Origin;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\AbstractOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpAnswer;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpContext;

/**
 * OP-01 `DRAW_DECK`: レベル 1 の山札から n 枚引く。足りなければ引けるだけ引き、山札切れのフラグを立てる（Q-05）。
 */
final class DrawDeck extends AbstractOp
{
    public function execute(OpContext $context, OpAnswer $answer): array
    {
        $count = $context->node->intParam('n', 1);
        $origin = $context->node->originParam();

        $drawn = $context->mutator->drawFromDeck($context->owner, $count, $origin);
        if ($drawn < $count) {
            $context->mutator->flagDeckExhausted();
        }

        return $origin === Origin::FLOOR
            ? [new GameEvent(Event::FLOOR_GAIN, $context->owner, ['element' => 'DRAW'])]
            : [];
    }
}
