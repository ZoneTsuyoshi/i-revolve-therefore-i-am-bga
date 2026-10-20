<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Effects\Query;

use IRevolveTests\Support\QueryKit;
use PHPUnit\Framework\TestCase;

/**
 * 仕様書 008 §12.4 の UT-53 のうち、発動回数の部分（MD-11。M062）。2 回目の確認（`CONFIRM_USE`）と発動の実行は想起の実装で足す。
 */
final class RecallTimesQueryTest extends TestCase
{
    public function testTurnPlayerRecallsTwiceWhenAnotherPersonaIsInTheRoom(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M062']);
        $kit->game->persona = [1 => 5, 2 => 5];

        self::assertSame(2, $kit->recall->times(1, 5));
        // 他の人格は 1 回
        self::assertSame(1, $kit->recall->times(2, 5));
    }

    public function testSingleOccupantRecallsOnce(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M062']);
        $kit->game->persona = [1 => 5, 2 => 6];

        self::assertSame(1, $kit->recall->times(1, 5));
    }

    public function testWithoutM062EveryoneRecallsOnce(): void
    {
        $kit = new QueryKit();
        $kit->game->persona = [1 => 5, 2 => 5];

        self::assertSame(1, $kit->recall->times(1, 5));
    }
}
