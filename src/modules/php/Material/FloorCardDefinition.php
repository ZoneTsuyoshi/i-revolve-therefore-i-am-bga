<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 床カード 1 枚の定義（002 §7）。`defaultPosition` は初回配置の位置（回廊を時計回りに数えた 1〜12）。
 * 床アクションの構造化は仕様書 008。
 */
final readonly class FloorCardDefinition
{
    public function __construct(
        public string $id,
        public string $roomName,
        public string $nameId,
        public int $level,
        public string $actionTextJa,
        public FloorBonus $bonus,
        public int $defaultPosition,
    ) {
    }
}
