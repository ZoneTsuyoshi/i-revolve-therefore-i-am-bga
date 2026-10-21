<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Trigger;

use Bga\Games\IRevolveThereforeIAm\Material\EffectCatalog;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;
use Bga\Games\IRevolveThereforeIAm\Material\Op;
use Bga\Games\IRevolveThereforeIAm\Material\ResourceTouch;

/**
 * 同時に誘発した効果の順序（008 §6.3）。触る資源が衝突する対が 1 つでもあれば、ターンプレイヤーが解決順を選ぶ（`ORDER_EFFECTS`）。
 * 衝突が無ければ結果が順序によらないので、固定順（自己構成したカード自身の即時効果、次に効果 ID の昇順）で自動に解決する。
 *
 * 同一の定義（操作列と条件が同じ。M052 / M053 / M054 など）は互いに交換可能なので、対にしても衝突とみなさない（§6.3.1 の 5）。
 */
final class ConflictResolver
{
    /**
     * 解決順を聞く必要があるか。
     *
     * @param list<TriggeredEffect> $effects
     */
    public function needsOrder(array $effects): bool
    {
        $count = count($effects);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $effects[$i]->spec;
                $b = $effects[$j]->spec;
                if ($a->ops == $b->ops && $a->conditions == $b->conditions) {
                    continue;
                }
                if (ResourceTouch::anyConflict($this->touchesOf($effects[$i]), $this->touchesOf($effects[$j]))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 固定順: 自己構成したカード自身の即時効果（`source` が `card_type` と同じもの）、次に効果 ID の昇順。
     *
     * @param list<TriggeredEffect> $effects
     * @return list<TriggeredEffect>
     */
    public function fixedOrder(array $effects): array
    {
        usort($effects, static function (TriggeredEffect $a, TriggeredEffect $b): int {
            $ownA = $a->source === ($a->payload['card_type'] ?? null) ? 0 : 1;
            $ownB = $b->source === ($b->payload['card_type'] ?? null) ? 0 : 1;

            return [$ownA, $a->spec->id] <=> [$ownB, $b->spec->id];
        });

        return $effects;
    }

    /**
     * 効果が実際に触る資源。M071（RECALL_ACTIVATE）は、自己構成したカードの想起効果（RC）が触る資源を足す（008 §6.3.3）。
     *
     * @return list<ResourceTouch>
     */
    private function touchesOf(TriggeredEffect $effect): array
    {
        $touches = $effect->spec->touches();
        $type = $effect->payload['card_type'] ?? null;
        if (!is_string($type)) {
            return $touches;
        }
        foreach ($effect->spec->steps() as $step) {
            if ($step->node->atomicOp() !== Op::RECALL_ACTIVATE) {
                continue;
            }
            $recall = MemoryCards::get($type)->recall;
            $groups = (array) ($step->node->params['groups'] ?? []);
            if ($groups !== [] && !in_array($recall->group(), $groups, true)) {
                continue;
            }
            foreach (EffectCatalog::recall($recall)->touches() as $touch) {
                $touches[] = $touch;
            }
        }

        return $touches;
    }
}
