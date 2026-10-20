<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Material\KeyType;

/**
 * 移動の支払いに使えるカード 1 枚（手札または自己構成済み）。鍵は印刷された順。
 */
final readonly class MoveCard
{
    /**
     * @param list<KeyType> $keys
     */
    public function __construct(
        public int $id,
        public int $type,
        public array $keys,
        public bool $tapped = false,
    ) {
    }
}
