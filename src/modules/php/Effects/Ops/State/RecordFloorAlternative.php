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
use Bga\Games\IRevolveThereforeIAm\Material\ActionKind;

/**
 * OP-24 `RECORD_FLOOR_ALTERNATIVE`: 床アクションの代わりに行える行動を `floor_alternatives` に記録する。ターンプレイヤーだけ
 * （FAQ 行 7）。同じ種類は割引が最大のもの 1 つにまとめる（FAQ 行 12。M062 で 2 回発動しても記録は 1 つ）。
 */
final class RecordFloorAlternative extends AbstractOp
{
    public function execute(OpContext $context, OpAnswer $answer): array
    {
        if (!$context->isTurnPlayer()) {
            return [];
        }
        $kind = $context->node->params['kind'] ?? null;
        if (!$kind instanceof ActionKind) {
            throw new \LogicException('RECORD_FLOOR_ALTERNATIVE needs an ActionKind in "kind"');
        }
        $discount = $context->node->intParam('discount', 0);

        $alternatives = [];
        $merged = false;
        foreach ($context->view->floorAlternatives() as $existing) {
            if ($existing['kind'] === $kind) {
                $existing['discount'] = max($existing['discount'], $discount);
                $merged = true;
            }
            $alternatives[] = $existing;
        }
        if (!$merged) {
            $alternatives[] = ['kind' => $kind, 'discount' => $discount];
        }
        $context->mutator->setFloorAlternatives($alternatives);

        return [];
    }
}
