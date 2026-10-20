<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Condition;

use Bga\Games\IRevolveThereforeIAm\Material\MemoryCardDefinition;

/**
 * 条件の評価に渡す文脈（008 §5.2）。ゲーム状態は `GameView` から読み、ここには問い合わせの対象とイベントの内容だけを持つ。
 */
final readonly class ConditionContext
{
    /**
     * @param int $player 効果の持ち主
     * @param MemoryCardDefinition|null $card 自己構成するカード（CD-06・CD-13）
     * @param array<string, mixed> $payload イベントの内容と問い合わせの補足。
     *   `room`（CD-04）、`entering_level`（CD-05）、`composed_count`（CD-07）、`via`（CD-08）、
     *   `dest_had_light`（CD-09）、`origin`（CD-10）、`element`（CD-11）
     */
    public function __construct(
        public int $player,
        public ?MemoryCardDefinition $card = null,
        public array $payload = [],
    ) {
    }
}
