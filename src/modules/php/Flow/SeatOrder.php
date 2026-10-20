<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

/**
 * 席順（player_no の昇順 = 手番順。仕様書 007 §7.1 T-8）の計算。フレームワークを呼ばない純粋な関数。
 */
final class SeatOrder
{
    private function __construct()
    {
    }

    /**
     * 次の手番のプレイヤー（最後の人の次は 1 番手）。
     *
     * @param list<int> $seats 席順（手番順）のプレイヤー ID
     */
    public static function next(array $seats, int $current): int
    {
        $i = array_search($current, $seats, true);
        if ($i === false) {
            throw new \InvalidArgumentException("unknown player: $current");
        }

        return $seats[($i + 1) % count($seats)];
    }

    /**
     * 初期自我のドラフトとミープル配置の順（手番順の逆）。
     *
     * @param list<int> $seats
     * @return list<int>
     */
    public static function reversed(array $seats): array
    {
        return array_reverse($seats);
    }

    /**
     * 指定のプレイヤーから時計回り（席順）に並べた全員（想起の発動順。仕様書 006 §5.2）。
     *
     * @param list<int> $seats
     * @return list<int>
     */
    public static function startingFrom(array $seats, int $first): array
    {
        $i = array_search($first, $seats, true);
        if ($i === false) {
            throw new \InvalidArgumentException("unknown player: $first");
        }

        return [...array_slice($seats, $i), ...array_slice($seats, 0, $i)];
    }
}
