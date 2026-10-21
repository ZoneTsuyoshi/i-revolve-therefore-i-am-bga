<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops\Spirit;

use Bga\Games\IRevolveThereforeIAm\Effects\Ops\AbstractOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpAnswer;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpContext;

/**
 * OP-14 `WIN`: 即座に勝利する（心理の極致。R5 のボーナス）。`kind` は終局の理由（既定は `APEX_OF_PSYCHE`）。
 */
final class Win extends AbstractOp
{
    public function execute(OpContext $context, OpAnswer $answer): array
    {
        $kind = $context->node->params['kind'] ?? 'APEX_OF_PSYCHE';
        $context->mutator->win($context->owner, is_string($kind) ? $kind : 'APEX_OF_PSYCHE');

        return [];
    }
}
