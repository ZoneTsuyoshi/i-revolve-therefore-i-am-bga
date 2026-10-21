<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Context;

use Bga\Games\IRevolveThereforeIAm\Material\ComposeContext;

/**
 * 自己構成・精神分析のアクション（OP-10・OP-11）を行える状況かを答える口。行えないとき、エンジンは 61・62 へ出ずに飛ばす
 * （「自己構成できるカードが無ければ、その COMPOSE は何もせずに終わる」。008 §6.5.2）。精神分析トークンの空撃ち防止（Q-28）にも使う。
 * 本番の実装は `Flow/` の `ComposeAction`・`AnalysisAction`。
 */
interface ActionAvailability
{
    /**
     * @param int $discount 想起効果の代替などの割引（`ctx.discount`）
     */
    public function canCompose(int $player, ComposeContext $context, int $discount): bool;

    /**
     * @param int $discount 精神分析コストの割引（想起効果の代替の 0 または 1）
     */
    public function canAnalyze(int $player, int $discount): bool;
}
