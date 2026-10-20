<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Setup;

use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;

/**
 * セットアップで作る初期状態（仕様書 007 §13）。SetupPlanner が乱数から作り、GameSetup が DB に書く。
 * DB にもフレームワークにも依存しない値なので、PHPUnit でそのまま検査できる。
 */
final readonly class SetupPlan
{
    /**
     * @param list<array{id: int, type: int, loc: MemoryLocation, arg: int, pos: int}> $memoryCards 想い出カード 144 枚（OUT を含む）
     * @param array<int, int> $roomFloors 位置（1〜12）→ 床カード ID（1〜12）
     * @param list<array{id: string, tier: int, slot: int, light: bool}> $spirits 精神カード 7 枚
     * @param list<array{id: string, loc: string, owner: ?int, pos: int}> $egos 自我カード 13 枚（初期 8・強化 5）
     * @param list<array{id: int, state: string, slot: ?int}> $objectives 目標カード 7 枚
     * @param array<int, array{light: int, wild_up: int, compose: int, analysis: int}> $players プレイヤー ID → 初期トークン
     * @param int $deck1Initial 配り終えた後のレベル 1 の山札の枚数
     * @param int $deck2Initial 配り終えた後のレベル 2 の山札の枚数
     */
    public function __construct(
        public array $memoryCards,
        public array $roomFloors,
        public array $spirits,
        public array $egos,
        public array $objectives,
        public array $players,
        public int $deck1Initial,
        public int $deck2Initial,
    ) {
    }
}
