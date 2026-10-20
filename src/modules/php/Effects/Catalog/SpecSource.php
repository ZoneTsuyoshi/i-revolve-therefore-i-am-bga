<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Catalog;

use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;

/**
 * 効果の定義の引き先。本番は `EffectCatalog`（`CatalogSpecSource`）、単体テストは任意の定義を差し込める。
 */
interface SpecSource
{
    /**
     * カード ID（想い出 `M058`・自我 `I01` など）の効果。無ければ空。
     *
     * @return list<EffectSpec>
     */
    public function forCard(string $cardId): array;

    public function find(string $effectId): ?EffectSpec;
}
