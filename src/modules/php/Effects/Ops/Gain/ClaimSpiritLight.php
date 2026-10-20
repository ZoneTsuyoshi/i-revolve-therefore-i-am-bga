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
 * OP-05 `CLAIM_SPIRIT_LIGHT`: 到着した精神カード上の心光を獲得する（あれば。先着 1 人。FAQ 行 11・27）。
 * `origin = SPIRIT_CARD` で EV-07 を起こす（I02 が反応する）。
 */
final class ClaimSpiritLight extends AbstractOp
{
    public function execute(OpContext $context, OpAnswer $answer): array
    {
        if (!$context->mutator->takeSpiritLight($context->owner)) {
            return [];
        }
        $context->mutator->gainLight($context->owner, 1, Origin::SPIRIT_CARD);

        return [new GameEvent(Event::LIGHT_GAINED, $context->owner, ['count' => 1, 'origin' => Origin::SPIRIT_CARD->value])];
    }
}
