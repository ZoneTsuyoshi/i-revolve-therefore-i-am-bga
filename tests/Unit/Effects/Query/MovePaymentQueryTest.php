<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Effects\Query;

use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\KeyPayment;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\RouteStep;
use Bga\Games\IRevolveThereforeIAm\Material\Condition;
use Bga\Games\IRevolveThereforeIAm\Material\ConditionRef;
use Bga\Games\IRevolveThereforeIAm\Material\FloorBonus;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;
use IRevolveTests\Support\QueryKit;
use IRevolveTests\Support\ArrayGame;
use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionContext;
use PHPUnit\Framework\TestCase;

/**
 * 仕様書 008 §12.2 の UT-24〜UT-26（移動の支払い）。
 */
final class MovePaymentQueryTest extends TestCase
{
    /** UT-24: 意志を M003（手札）、知恵・感情を M082（手札）で払えば M061 の条件（CD-02）を満たす。 */
    public function testAllThreeKindsPaidByCards(): void
    {
        $kit = new QueryKit();
        $payments = [
            new KeyPayment(KeyPayment::HAND, MemoryCards::get('M003')->keys[0]),
            new KeyPayment(KeyPayment::HAND, MemoryCards::get('M082')->keys[0]),
            new KeyPayment(KeyPayment::HAND, MemoryCards::get('M082')->keys[1]),
        ];
        $kinds = $kit->move->keyKindsPaid($payments);

        self::assertEqualsCanonicalizing([KeyType::WILL, KeyType::WISDOM, KeyType::EMOTION], $kinds);
        $kit->game->keyKindsPaid = $kinds;
        self::assertTrue($this->cd02($kit->game, $kit));
    }

    /** UT-24: 心光・万能移動鍵トークンでの支払いや、カードの万能鍵は数えない。 */
    public function testWildPaymentsDoNotCount(): void
    {
        $kit = new QueryKit();
        foreach ([KeyPayment::LIGHT, KeyPayment::TOKEN] as $source) {
            $kinds = $kit->move->keyKindsPaid([
                new KeyPayment(KeyPayment::HAND, KeyType::WILL),
                new KeyPayment(KeyPayment::TABLEAU, KeyType::WISDOM),
                new KeyPayment($source),
            ]);
            $kit->game->keyKindsPaid = $kinds;
            self::assertFalse($this->cd02($kit->game, $kit), $source);
        }
        $kinds = $kit->move->keyKindsPaid([
            new KeyPayment(KeyPayment::HAND, KeyType::WILL),
            new KeyPayment(KeyPayment::HAND, KeyType::WISDOM),
            new KeyPayment(KeyPayment::HAND, KeyType::WILD), // M040 などの万能鍵
        ]);
        self::assertEqualsCanonicalizing([KeyType::WILL, KeyType::WISDOM], $kinds);
    }

    /** UT-24: M096 があれば、意志鍵のカードで払った分は意志として数える（読み替えと独立。印刷された鍵で数える）。 */
    public function testPrintedKindCountsEvenWithM096(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M096']);
        $kinds = $kit->move->keyKindsPaid([
            new KeyPayment(KeyPayment::HAND, KeyType::WILL),
            new KeyPayment(KeyPayment::HAND, KeyType::WISDOM),
            new KeyPayment(KeyPayment::TABLEAU, KeyType::EMOTION),
        ]);
        $kit->game->keyKindsPaid = $kinds;
        self::assertTrue($this->cd02($kit->game, $kit));
    }

    public function testWildTokensUsedCountsOnlyTokens(): void
    {
        $kit = new QueryKit();
        $payments = [
            new KeyPayment(KeyPayment::TOKEN),
            new KeyPayment(KeyPayment::LIGHT),
            new KeyPayment(KeyPayment::TOKEN),
            new KeyPayment(KeyPayment::HAND, KeyType::WILL),
        ];
        self::assertSame(2, $kit->move->wildTokensUsed($payments));
        self::assertSame(0, $kit->move->wildTokensUsed([]));
    }

    /** UT-25: M064〜M066 の 3 枚。最初のレベル 2 の入室だけ錠前を減らし（重ねて -3 だが錠前は 2 つなので最大 -2）、2 回目以降は無い。 */
    public function testLockReductionOnlyForTheFirstLevelTwoEntry(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M064', 'M065', 'M066']);

        self::assertSame(2, $kit->move->lockReduction(1, 2));
        self::assertSame(0, $kit->move->lockReduction(1, 1)); // レベル 1 の部屋

        $kit->game->lv2Entries = 1; // 2 回目のレベル 2 の入室
        self::assertSame(0, $kit->move->lockReduction(1, 2));
    }

    public function testLockReductionFollowsTheNumberOfCards(): void
    {
        $kit = new QueryKit();
        self::assertSame(0, $kit->move->lockReduction(1, 2));
        $kit->game->giveTableau(1, ['M064']);
        self::assertSame(1, $kit->move->lockReduction(1, 2));
        $kit->game->giveTableau(1, ['M065']);
        self::assertSame(2, $kit->move->lockReduction(1, 2));
    }

    /** M064〜M066 は手札にある間は働かない。 */
    public function testLockReductionIgnoresCardsInHand(): void
    {
        $kit = new QueryKit();
        $kit->game->giveHand(1, ['M064']);
        self::assertSame(0, $kit->move->lockReduction(1, 2));
    }

    /** UT-26: 周遊ボーナスで得た心光・万能移動鍵トークンは、その部屋の後の部屋の支払いに使える。 */
    public function testBonusesFromEarlierRoomsPayLaterRooms(): void
    {
        $kit = new QueryKit();
        // 心光 0・トークン 0 から。1 部屋目（POSEIDON の周遊で心光 +1）→ 2 部屋目で心光 1 回
        $kit->move->assertResources(1, 0, 0, [
            new RouteStep(0, 0, FloorBonus::NONE, FloorBonus::LIGHT),
            new RouteStep(1, 0),
        ]);
        // 1 部屋目の周遊で万能移動鍵トークン +1 → 2 部屋目でトークンを使う
        $kit->move->assertResources(1, 0, 0, [
            new RouteStep(0, 0, FloorBonus::NONE, FloorBonus::WILD_MOVE_KEY),
            new RouteStep(0, 1),
        ]);
        // スキップしたレベル 2 の部屋のボーナスは、次の入室の支払いより先に得る
        $kit->move->assertResources(1, 0, 0, [new RouteStep(1, 0, FloorBonus::LIGHT)]);
        self::addToAssertionCount(1);
    }

    public function testResourcesCannotBeUsedBeforeTheyAreGained(): void
    {
        $kit = new QueryKit();
        $this->expectException(EffectViolation::class);
        // 同じ部屋の周遊ボーナスは支払いの後に得る
        $kit->move->assertResources(1, 0, 0, [new RouteStep(1, 0, FloorBonus::NONE, FloorBonus::LIGHT)]);
    }

    public function testLightUsesAreCountedAcrossTheWholeRoute(): void
    {
        $kit = new QueryKit();
        // 既定: 1 回目 1 個 + 2 回目 2 個 = 3 個
        $kit->move->assertResources(1, 3, 0, [new RouteStep(1, 0), new RouteStep(1, 0)]);
        try {
            $kit->move->assertResources(1, 2, 0, [new RouteStep(1, 0), new RouteStep(1, 0)]);
            self::fail('2 lights are not enough for two uses');
        } catch (EffectViolation) {
            self::addToAssertionCount(1);
        }
        // M063: 1 回目 1 個 + 2 回目 1 個
        $kit->game->giveTableau(1, ['M063']);
        $kit->move->assertResources(1, 2, 0, [new RouteStep(1, 0), new RouteStep(1, 0)]);
    }

    private function cd02(ArrayGame $game, QueryKit $kit): bool
    {
        return $kit->conditions->evaluate(
            ConditionRef::of(Condition::ALL_KEY_KINDS_PAID),
            new ConditionContext($game->turnPlayer),
        );
    }
}
