<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops\Board;

use Bga\Games\IRevolveThereforeIAm\Effects\Ops\AbstractOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpAnswer;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpContext;
use Bga\Games\IRevolveThereforeIAm\Material\RecallGroup;

/**
 * OP-25 `RECALL_ACTIVATE`: 想い出カード `card` の想起効果を `times` 回発動する（M071 は自己構成したカードの想起効果を、グループ
 * `DRAW`・`GAIN`・`EXCHANGE` に限って使う。R8-5、Q-54）。発動は 006 の `RECALL_ACTIVATION` frame として解決スタックの先頭に積む。
 *
 * パラメータ: `card`（`M0NN`。{@see self::EVENT_CARD} なら、誘発したイベントの `card_type` = 自己構成したカード）、`times`、
 * `groups`（{@see RecallGroup} の配列。無ければ制限なし）。
 */
final class RecallActivate extends AbstractOp
{
    /** `card` に指定すると、誘発したイベント（EV-05）の `card_type`、つまり自己構成したカードの想起効果を使う（M071）。 */
    public const EVENT_CARD = 'EVENT_CARD';

    public function execute(OpContext $context, OpAnswer $answer): array
    {
        $card = $context->node->params['card'] ?? null;
        if ($card === self::EVENT_CARD) {
            $card = $context->payload['card_type'] ?? null;
        }
        if (!is_string($card)) {
            throw new \LogicException('RECALL_ACTIVATE needs a card id in "card"');
        }
        $groups = [];
        foreach ((array) ($context->node->params['groups'] ?? []) as $group) {
            if ($group instanceof RecallGroup) {
                $groups[] = $group;
            }
        }
        $context->mutator->pushRecallActivation($context->owner, $card, $context->node->intParam('times', 1), $groups);

        return [];
    }
}
