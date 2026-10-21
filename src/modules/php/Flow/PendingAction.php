<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;

/**
 * 61・62 が行う自己構成・精神分析アクション 1 回の文脈（006 §5.4・§5.5）。解決スタックの先頭の frame が持つ。
 * `COMPOSE` / `ANALYZE` の frame（床アクションの代替、トークン、フェイズ）か、`SPEC_RUN` の途中の `OP-10` / `OP-11`（床・精神カードのボーナス）。
 */
final readonly class PendingAction
{
    /**
     * @param string $context 自己構成は `PHASE` / `TOKEN` / `FLOOR` / `BONUS_LV1` / `BONUS_ANY` / `EFFECT_FREE_LV1`、精神分析は `FLOOR` / `TOKEN`
     * @param int $discount 想起効果の代替などの割引（`ctx.discount`）
     * @param bool $viaSpec 効果の操作列の途中か（取り消しは「行わない」で続きを解決する）
     * @param int|null $cancelTo 取り消したときに戻る状態 ID（床アクションの代替は 50）
     */
    public function __construct(
        public EffectFrame $row,
        public string $context,
        public int $discount,
        public bool $viaSpec,
        public ?int $cancelTo,
    ) {
    }
}
