<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Effects\Query;

use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Material\ActionContext;
use IRevolveTests\Support\QueryKit;
use PHPUnit\Framework\TestCase;

/**
 * 仕様書 008 §12.2 の UT-17〜UT-19（心光の行使の逓増）。
 */
final class LightScheduleQueryTest extends TestCase
{
    /** UT-17: 既定は 1・3・6・10・15・21 個。M070 は 1・2・4・6・9・15（6 回目は 6 個）。M059・M063 も同じ表。 */
    public function testCumulativeTotals(): void
    {
        $kit = new QueryKit();
        $totals = static fn (ActionContext $c): array => array_map(
            static fn (int $uses): int => $kit->light->totalLights(1, $c, $uses),
            [1, 2, 3, 4, 5, 6],
        );
        self::assertSame([1, 3, 6, 10, 15, 21], $totals(ActionContext::COMPOSE));

        $kit->game->giveTableau(1, ['M070', 'M059', 'M063']);
        self::assertSame([1, 2, 4, 6, 9, 15], $totals(ActionContext::COMPOSE));
        self::assertSame([1, 2, 4, 6, 9, 15], $totals(ActionContext::ANALYSIS));
        self::assertSame([1, 2, 4, 6, 9, 15], $totals(ActionContext::MOVE));
        self::assertSame(6, $kit->light->lightsForUse(1, ActionContext::COMPOSE, 6)); // 表の外は n 個
        self::assertSame(7, $kit->light->lightsForUse(1, ActionContext::COMPOSE, 7));
    }

    /** UT-18: M059 は精神分析、M063 は移動、M070 は自己構成だけに効く。 */
    public function testEachCardOnlyAffectsItsOwnContext(): void
    {
        foreach (
            [
            'M059' => ActionContext::ANALYSIS,
            'M063' => ActionContext::MOVE,
            'M070' => ActionContext::COMPOSE,
            ] as $card => $context
        ) {
            $kit = new QueryKit();
            $kit->game->giveTableau(1, [$card]);
            foreach (ActionContext::cases() as $other) {
                $expected = $other === $context ? 4 : 6;
                self::assertSame($expected, $kit->light->totalLights(1, $other, 3), "{$card} in {$other->value}");
            }
        }
    }

    /** UT-18 の後半: アクションごとに回数が 0 から数え直される（問い合わせは回数を保存しない）。 */
    public function testCountsRestartForEveryAction(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M070']);
        self::assertSame(1, $kit->light->lightsForUse(1, ActionContext::COMPOSE, 1));
        self::assertSame(2, $kit->light->lightsForUse(1, ActionContext::COMPOSE, 3));
        self::assertSame(1, $kit->light->lightsForUse(1, ActionContext::COMPOSE, 1)); // 次のアクションの 1 回目
    }

    /** UT-19: M059 があるとき、2 回目の心光の行使を 2 個で払う入力は拒否される（1 個で払う）。 */
    public function testOverpaymentIsRejected(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M059']);

        $kit->light->assertPayment(1, ActionContext::ANALYSIS, 2, 2); // 1 + 1
        $this->expectException(EffectViolation::class);
        $kit->light->assertPayment(1, ActionContext::ANALYSIS, 2, 3); // 軽減を適用せずに 1 + 2
    }

    public function testDefaultPaymentMustBeExact(): void
    {
        $kit = new QueryKit();
        $kit->light->assertPayment(1, ActionContext::MOVE, 3, 6);
        $this->expectException(EffectViolation::class);
        $kit->light->assertPayment(1, ActionContext::MOVE, 3, 5);
    }

    public function testOtherPlayersCardsDoNotApply(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(2, ['M059']);
        self::assertSame(3, $kit->light->totalLights(1, ActionContext::ANALYSIS, 2));
        self::assertSame(2, $kit->light->totalLights(2, ActionContext::ANALYSIS, 2));
    }
}
