<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops\Action;

use Bga\Games\IRevolveThereforeIAm\Effects\Engine\ChoiceRequest;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\AbstractOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpAnswer;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpContext;
use Bga\Games\IRevolveThereforeIAm\Material\ComposeContext;
use Bga\Games\IRevolveThereforeIAm\Material\Op;

/**
 * OP-10 `COMPOSE` と OP-11 `ANALYZE`: 自己構成アクション（61）・精神分析アクション（62）を呼ぶ。操作自身は何も変えず、
 * 61・62 に入る要求（{@see ChoiceRequest::action()}）を返す。確定・取り消しは 61・62 がエンジンの
 * `completeAction()`・`cancelAction()` で伝える。行える状況でなければ（自己構成できるカードが無い、コストを払えない）飛ばす。
 *
 * パラメータ: `ctx`（`COMPOSE` は {@see ComposeContext}、`ANALYZE` は `FLOOR` / `TOKEN`）、`discount`（想起効果の代替などの割引）。
 */
final class ActionOp extends AbstractOp
{
    public function __construct(private readonly Op $op)
    {
    }

    public function request(OpContext $context): ?ChoiceRequest
    {
        $discount = $context->node->intParam('discount', 0);
        $ctx = $context->node->params['ctx'] ?? null;
        if ($this->op === Op::COMPOSE) {
            if (!$ctx instanceof ComposeContext) {
                throw new \LogicException('COMPOSE needs a ComposeContext in "ctx"');
            }
            if ($context->availability?->canCompose($context->owner, $ctx, $discount) === false) {
                return null;
            }
            $data = ['ctx' => $ctx->value, 'discount' => $discount];
        } else {
            if ($context->availability?->canAnalyze($context->owner, $discount) === false) {
                return null;
            }
            $data = ['ctx' => is_string($ctx) ? $ctx : 'FLOOR', 'discount' => $discount];
        }

        return ChoiceRequest::action($this->op, $context->owner, 0, $data);
    }

    public function execute(OpContext $context, OpAnswer $answer): array
    {
        if ($this->request($context) === null) {
            return [];
        }

        throw new \LogicException($this->op->name . ' is performed in state 61 or 62, not by the engine');
    }
}
