<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 想い出カード 1 種の定義（002 §6、§12）。`count` はデッキに入る枚数。
 * `effectTextJa` は効果の無いカード（`EffectKind::NONE`）では空文字列。
 */
final readonly class MemoryCardDefinition
{
    /**
     * @param list<KeyType> $keys 右の鍵アイコンを上から並べた順（1 本または 2 本）
     */
    public function __construct(
        public string $id,
        public string $nameJa,
        public string $nameEn,
        public string $nameId,
        public int $count,
        public int $level,
        public int $energy,
        public array $keys,
        public CardBack $back,
        public int $score,
        public EffectKind $effectKind,
        public string $effectTextJa,
        public RecallEffect $recall,
    ) {
    }
}
