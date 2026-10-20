<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 目標カード 1 枚の定義（002 §10）。
 * 得点の計算（X の数え方）は仕様書 008。
 */
final readonly class ObjectiveCardDefinition
{
    /**
     * @param list<array{int, int}> $scoreTable [X の下限, 得点] の組。X の昇順
     */
    public function __construct(
        public string $id,
        public string $nameJa,
        public string $nameId,
        public string $conditionTextJa,
        public string $xDefinitionJa,
        public array $scoreTable,
    ) {
    }
}
