<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Query;

use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionContext;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Material\AnalysisCardDefinition;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\Modifier;

/**
 * 精神分析のコスト（008 §6.5.3。MD-03）。コストの鍵の個数を `reduction` 個減らす。どの鍵を減らすかはプレイヤーが選ぶ（T8-3）。
 * 0 個を下限にする。
 */
final class AnalysisCostQuery
{
    public function __construct(private readonly ActiveSpecs $active)
    {
    }

    /**
     * 減らせる鍵の個数。MD-03（E02）の合計に、想起効果の代替の割引（`$contextDiscount`）を足す。
     */
    public function reduction(int $player, int $contextDiscount = 0): int
    {
        $reduction = $contextDiscount;
        foreach ($this->active->modifiers($player, Modifier::ANALYSIS_COST_DELTA, new ConditionContext($player)) as $spec) {
            $delta = $spec->params['delta'] ?? 0;
            $reduction -= is_int($delta) ? $delta : 0;
        }

        return max(0, $reduction);
    }

    /**
     * 減らした後の残りのコスト。`$removed` は減らす鍵の種類と本数（プレイヤーの選択）。
     * 本数の合計は `min(reduction, コストの総数)` と一致し、各鍵の本数はコストを超えてはならない。
     *
     * @param array<string, int> $removed キーは KeyType の値
     * @return array<string, int> 残りのコスト（キーは KeyType の値。0 本の鍵は含めない）
     * @throws EffectViolation
     */
    public function remaining(AnalysisCardDefinition $card, int $reduction, array $removed): array
    {
        $total = array_sum($card->cost);
        $expected = min($reduction, $total);
        if (array_sum($removed) !== $expected) {
            throw new EffectViolation("Must remove exactly {$expected} key(s) from the cost");
        }

        $remaining = [];
        foreach ($card->cost as $key => $count) {
            $take = $removed[$key] ?? 0;
            if ($take < 0 || $take > $count) {
                throw new EffectViolation("Cannot remove {$take} {$key} key(s) from the cost");
            }
            if ($count - $take > 0) {
                $remaining[$key] = $count - $take;
            }
        }
        foreach ($removed as $key => $take) {
            if (KeyType::tryFrom((string) $key) === null) {
                throw new EffectViolation("Unknown key type: {$key}");
            }
            if ($take !== 0 && !isset($card->cost[$key])) {
                throw new EffectViolation("The cost has no {$key} key to remove");
            }
        }

        return $remaining;
    }
}
