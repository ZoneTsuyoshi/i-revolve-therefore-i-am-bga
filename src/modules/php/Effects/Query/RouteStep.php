<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Query;

use Bga\Games\IRevolveThereforeIAm\Material\FloorBonus;

/**
 * 移動の経路の 1 つの入室（008 §6.2 の 4・5）。資源（心光・万能移動鍵トークン）の検証に使う。
 */
final readonly class RouteStep
{
    /**
     * @param int $lightUses この入室の心光の行使の回数（1 回 = 錠前 1 個分）
     * @param int $tokenUses この入室で使う万能移動鍵トークンの数
     * @param FloorBonus $skipBonus 直前にスキップしたレベル 2 の部屋の周遊ボーナス（スキップしていなければ `NONE`）。
     *                              入室の支払いより先に得る
     * @param FloorBonus $entryBonus 入室した部屋の周遊ボーナス。支払いの後に得る
     */
    public function __construct(
        public int $lightUses,
        public int $tokenUses,
        public FloorBonus $skipBonus = FloorBonus::NONE,
        public FloorBonus $entryBonus = FloorBonus::NONE,
    ) {
    }
}
