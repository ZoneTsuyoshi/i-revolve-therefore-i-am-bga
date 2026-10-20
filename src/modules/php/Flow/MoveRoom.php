<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Material\FloorBonus;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;

/**
 * 移動の盤面の 1 つの部屋（公開情報）。
 */
final readonly class MoveRoom
{
    /**
     * @param KeyType|null $lock 部屋の想い出カード裏面の錠前の種類。カードが無い（補充の途中）なら null
     * @param string $name 部屋の名前（床カードの部屋名。通知用の英語原文）
     * @param bool $othersPresent ターンプレイヤー以外の人格がいるか
     */
    public function __construct(
        public int $pos,
        public int $level,
        public ?KeyType $lock,
        public FloorBonus $bonus,
        public string $name,
        public bool $othersPresent,
    ) {
    }
}
