<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 回廊の幾何（001 §3）。12 の部屋が時計回りに位置 1〜12 で並び、四隅（1・4・7・10）がレベル 2。
 */
final class Corridor
{
    public const ROOM_COUNT = 12;

    /** レベル 2 の位置（四隅） */
    public const LEVEL2_POSITIONS = [1, 4, 7, 10];

    private function __construct()
    {
    }

    /**
     * @throws \InvalidArgumentException 位置が 1〜12 ではない
     */
    public static function levelOf(int $position): int
    {
        if ($position < 1 || $position > self::ROOM_COUNT) {
            throw new \InvalidArgumentException("invalid room position: {$position}");
        }

        return in_array($position, self::LEVEL2_POSITIONS, true) ? 2 : 1;
    }

    public static function isValid(int $position): bool
    {
        return $position >= 1 && $position <= self::ROOM_COUNT;
    }

    /** 時計回りに 1 つ先の位置 */
    public static function next(int $position): int
    {
        return $position % self::ROOM_COUNT + 1;
    }

    /** 反時計回りに 1 つ先の位置 */
    public static function previous(int $position): int
    {
        return ($position + self::ROOM_COUNT - 2) % self::ROOM_COUNT + 1;
    }
}
