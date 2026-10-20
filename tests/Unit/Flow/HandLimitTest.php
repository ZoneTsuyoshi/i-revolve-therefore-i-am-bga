<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\PublicView;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use IRevolveTests\Support\MoveFixture;
use PHPUnit\Framework\TestCase;

/**
 * 手札上限（8 枚。仕様書 006 §8、Q-32・Q-41）。
 */
final class HandLimitTest extends TestCase
{
    private MoveFixture $fx;

    protected function setUp(): void
    {
        $this->fx = new MoveFixture();
    }

    public function testNobodyIsOverWithEightCards(): void
    {
        $this->fx->giveHandAny(11, 8);
        self::assertNull($this->fx->handLimit->firstOver(11));
        self::assertFalse($this->fx->handLimit->pushCheckIfNeeded(11));
        self::assertSame(0, $this->fx->stack->count());
    }

    /** ターンプレイヤーを先に、続いて席順（006 §8）。 */
    public function testTurnPlayerFirstThenSeatOrder(): void
    {
        $this->fx->giveHandAny(22, 9);
        $this->fx->giveHandAny(11, 9);
        self::assertSame(11, $this->fx->handLimit->firstOver(11));
        self::assertSame(22, $this->fx->handLimit->firstOver(22));

        $this->fx->giveHandAny(33, 10);
        $this->fx->memory->move($this->fx->memory->inLoc(MemoryLocation::Hand, 11)[0]->id, MemoryLocation::Discard1);
        self::assertSame(33, $this->fx->handLimit->firstOver(33));
        self::assertSame(22, $this->fx->handLimit->firstOver(22));
        self::assertSame(2, $this->fx->handLimit->excess(33));
    }

    public function testDiscardExcessMustBeExactlyTheExcessFromTheHand(): void
    {
        $fx = $this->fx;
        $hand = $fx->giveHandAny(11, 10);
        $other = $fx->giveHandAny(22, 1);

        foreach ([[], [$hand[0]], [$hand[0], $hand[0]], [$hand[0], $other[0]], [$hand[0], $hand[1], $hand[2]]] as $bad) {
            try {
                $fx->handLimit->discardExcess(11, 'Alice', $bad);
                self::fail('expected a violation');
            } catch (EffectViolation) {
                self::assertSame(10, $fx->memory->count(MemoryLocation::Hand, 11));
            }
        }

        $notice = $fx->handLimit->discardExcess(11, 'Alice', [$hand[0], $hand[1]]);
        self::assertSame('cardsDiscarded', $notice->type);
        self::assertSame('HAND_LIMIT', $notice->args['reason']);
        self::assertSame(8, $notice->args['hand_count']);
        self::assertSame(2, $notice->args['count']);
        self::assertSame(8, $fx->memory->count(MemoryLocation::Hand, 11));
    }

    /** Q-23: 捨札は捨てたカードのレベルの捨札。 */
    public function testDiscardsGoToThePileOfTheirLevel(): void
    {
        $fx = $this->fx;
        $fx->giveHandAny(11, 7);
        [$lv1, $lv2] = [$fx->giveHand(11, [1])[0], $fx->giveHand(11, [85])[0]];

        $fx->handLimit->discardExcess(11, 'Alice', [$lv1]);
        self::assertSame(MemoryLocation::Discard1, $fx->memory->find($lv1)?->location);
        $fx->giveHand(11, [2]);
        $fx->handLimit->discardExcess(11, 'Alice', [$lv2]);
        self::assertSame(MemoryLocation::Discard2, $fx->memory->find($lv2)?->location);
    }

    public function testZombieDiscardsLevel1FirstFromTheBack(): void
    {
        $fx = $this->fx;
        $fx->giveHandAny(11, 7);
        $lv2 = $fx->giveHand(11, [85, 86])[0];
        $hand = $fx->memory->inLoc(MemoryLocation::Hand, 11);
        $lv1Ids = array_values(array_map(
            static fn($c): int => $c->id,
            array_filter($hand, static fn($c): bool => PublicView::definition($c->type)->level === 1),
        ));
        rsort($lv1Ids);

        self::assertSame(1, $fx->handLimit->excess(11));
        $choice = $fx->handLimit->zombieChoice(11);
        self::assertSame([$lv1Ids[0]], $choice, 'レベル 1 のうち ID が最大のもの');
        self::assertNotContains($lv2, $choice);
        $fx->handLimit->discardExcess(11, 'Alice', $choice);
        self::assertSame(8, $fx->memory->count(MemoryLocation::Hand, 11));
    }
}
