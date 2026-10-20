<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops\Gain;

use Bga\Games\IRevolveThereforeIAm\Effects\Ops\AbstractOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpAnswer;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpContext;
use Bga\Games\IRevolveThereforeIAm\Material\TokenKind;

/**
 * OP-04 `GAIN_TOKEN`: トークンを n 個得る（自己構成トークン / 精神分析トークン / 万能移動鍵トークン。万能移動鍵は表向きで得る）。
 */
final class GainToken extends AbstractOp
{
    public function execute(OpContext $context, OpAnswer $answer): array
    {
        $token = $context->node->params['token'] ?? null;
        if (!$token instanceof TokenKind) {
            throw new \LogicException('GAIN_TOKEN needs a TokenKind in "token"');
        }
        $context->mutator->gainToken($context->owner, $token, $context->node->intParam('n', 1));

        return [];
    }
}
