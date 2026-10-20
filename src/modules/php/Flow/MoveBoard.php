<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

/**
 * 移動の検証に必要な盤面の写し（読み取り専用）。`MoveBoardReader` が DB から作り、単体テストは直接組み立てる。
 */
final readonly class MoveBoard
{
    /**
     * @param array<int, MoveRoom> $rooms 位置 → 部屋（1〜12）
     * @param array<int, MoveCard> $hand 手札（個体 ID → カード）
     * @param array<int, MoveCard> $tableau 自己構成済みカード（個体 ID → カード）
     * @param int $priorLv2Entries このターンの、この移動より前のレベル 2 の部屋への入室回数
     */
    public function __construct(
        public int $player,
        public int $position,
        public array $rooms,
        public array $hand,
        public array $tableau,
        public int $lights,
        public int $wildUp,
        public int $priorLv2Entries = 0,
    ) {
    }

    /**
     * @throws MoveViolation 位置が 1〜12 でないとき
     */
    public function room(int $pos): MoveRoom
    {
        return $this->rooms[$pos] ?? throw new MoveViolation(MoveViolation::INVALID_ROUTE, "unknown room $pos");
    }

    /**
     * 時計回りの次の位置（12 の次は 1）。
     */
    public static function after(int $pos): int
    {
        return $pos % 12 + 1;
    }
}
