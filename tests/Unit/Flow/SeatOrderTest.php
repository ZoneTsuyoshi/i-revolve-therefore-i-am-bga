<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Flow\SeatOrder;
use PHPUnit\Framework\TestCase;

final class SeatOrderTest extends TestCase
{
    public function testNextWrapsAround(): void
    {
        $seats = [7, 3, 9];
        self::assertSame(3, SeatOrder::next($seats, 7));
        self::assertSame(9, SeatOrder::next($seats, 3));
        self::assertSame(7, SeatOrder::next($seats, 9));
    }

    public function testNextWithTwoPlayers(): void
    {
        self::assertSame(2, SeatOrder::next([1, 2], 1));
        self::assertSame(1, SeatOrder::next([1, 2], 2));
    }

    public function testReversedIsTheDraftAndPlacementOrder(): void
    {
        self::assertSame([4, 3, 2, 1], SeatOrder::reversed([1, 2, 3, 4]));
    }

    public function testStartingFromGivesClockwiseOrder(): void
    {
        self::assertSame([3, 4, 1, 2], SeatOrder::startingFrom([1, 2, 3, 4], 3));
        self::assertSame([1, 2, 3], SeatOrder::startingFrom([1, 2, 3], 1));
    }

    public function testUnknownPlayerThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SeatOrder::next([1, 2], 3);
    }
}
