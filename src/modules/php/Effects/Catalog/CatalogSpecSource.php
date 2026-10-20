<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Catalog;

use Bga\Games\IRevolveThereforeIAm\Material\EffectCatalog;
use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;

/**
 * `EffectCatalog`（手書きの効果定義）を引く `SpecSource`。
 */
final class CatalogSpecSource implements SpecSource
{
    /** @return list<EffectSpec> */
    public function forCard(string $cardId): array
    {
        return EffectCatalog::forCard($cardId);
    }

    public function find(string $effectId): ?EffectSpec
    {
        return EffectCatalog::find($effectId);
    }
}
