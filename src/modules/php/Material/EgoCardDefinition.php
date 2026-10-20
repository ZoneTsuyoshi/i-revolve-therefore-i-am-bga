<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 自我カード（初期自我・強化自我）1 枚の定義（002 §9）。
 * 初期自我は印刷にも card.csv にも効果の種類が無いので `effectKind` は null（仕様書 008 が割り当てる）。
 */
final readonly class EgoCardDefinition
{
    public function __construct(
        public string $id,
        public string $nameJa,
        public string $nameEn,
        public string $nameId,
        public EgoKind $kind,
        public ?EffectKind $effectKind,
        public string $effectTextJa,
    ) {
    }
}
