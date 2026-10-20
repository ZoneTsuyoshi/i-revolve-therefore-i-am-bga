<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Flow\GameProgress;
use PHPUnit\Framework\TestCase;

/**
 * 進行度（006 §11）: 勝利条件・山札切れへの近さの最大値、0〜99、単調増加。
 */
final class GameProgressTest extends TestCase
{
    private static function calc(
        int $tier = 0,
        int $score = 0,
        int $deck1Left = 92,
        int $deck2Left = 20,
        int $highWater = 0,
    ): int {
        return GameProgress::calculate($tier, $score, $deck1Left, 92, $deck2Left, 20, $highWater);
    }

    public function testStartIsZero(): void
    {
        self::assertSame(0, self::calc());
    }

    public function testTierProgress(): void
    {
        self::assertSame(20, self::calc(tier: 1));
        self::assertSame(60, self::calc(tier: 3));
        self::assertSame(80, self::calc(tier: 4));
    }

    public function testScoreProgress(): void
    {
        self::assertSame(50, self::calc(score: 10));
        self::assertSame(95, self::calc(score: 19));
    }

    public function testDeckProgress(): void
    {
        self::assertSame(50, self::calc(deck1Left: 46));
        self::assertSame(75, self::calc(deck2Left: 5));
    }

    public function testTakesTheMaximumOfAllIndicators(): void
    {
        self::assertSame(75, self::calc(tier: 1, score: 4, deck1Left: 80, deck2Left: 5));
    }

    public function testNeverReaches100(): void
    {
        self::assertSame(99, self::calc(tier: 5));
        self::assertSame(99, self::calc(score: 25));
        self::assertSame(99, self::calc(deck1Left: 0));
        self::assertSame(99, self::calc(deck2Left: 0, highWater: 150));
    }

    public function testIsMonotonic(): void
    {
        // 目標カードの X が減って自己構成スコアが下がっても戻らない
        self::assertSame(50, self::calc(score: 4, highWater: 50));
    }

    public function testZeroInitialDeckIsIgnored(): void
    {
        self::assertSame(0, GameProgress::calculate(0, 0, 0, 0, 0, 0, 0));
    }
}
