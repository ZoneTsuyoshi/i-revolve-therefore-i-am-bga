<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops\Board;

use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\ChoiceRequest;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\AbstractOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpAnswer;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpContext;
use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;
use Bga\Games\IRevolveThereforeIAm\Material\Corridor;

/**
 * OP-16 `WARP`（M072）: 好きな部屋に人格を移す。今いる部屋も選んでよい。入室済みとは数えず、周遊ボーナスも「止まった」扱いも無い
 * （R8-12、Q-61）。答えは `{"pos": 部屋の位置}`、スキップは移動しない。
 */
final class Warp extends AbstractOp
{
    public function choiceKind(): ChoiceKind
    {
        return ChoiceKind::WARP_ROOM;
    }

    public function request(OpContext $context): ChoiceRequest
    {
        return new ChoiceRequest(ChoiceKind::WARP_ROOM, $context->owner, 0, ['rooms' => range(1, Corridor::ROOM_COUNT)]);
    }

    public function execute(OpContext $context, OpAnswer $answer): array
    {
        if ($answer->skipped) {
            return [];
        }
        $pos = $answer->data['pos'] ?? null;
        if (!is_int($pos) || !Corridor::isValid($pos)) {
            throw new EffectViolation('Choose a room to warp to');
        }
        $context->mutator->warpPersona($context->owner, $pos);

        return [];
    }
}
