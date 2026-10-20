<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops\Gain;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\AbstractOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpAnswer;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpContext;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\Origin;

/**
 * OP-03 `GAIN_LIGHT`: 心光を n 個得る。供給の上限は無い（Q-19）。EV-07（心光の獲得）を起こし、
 * `origin = FLOOR` なら EV-08（床アクションの要素）も起こす。
 */
final class GainLight extends AbstractOp
{
    public function execute(OpContext $context, OpAnswer $answer): array
    {
        $count = $context->node->intParam('n', 1);
        $origin = $context->node->originParam();
        $context->mutator->gainLight($context->owner, $count, $origin);

        $events = [new GameEvent(Event::LIGHT_GAINED, $context->owner, ['count' => $count, 'origin' => $origin?->value])];
        if ($origin === Origin::FLOOR) {
            $events[] = new GameEvent(Event::FLOOR_GAIN, $context->owner, ['element' => 'LIGHT']);
        }

        return $events;
    }
}
