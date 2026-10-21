<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Flow\Notice;
use IRevolveTests\Support\MoveFixture;
use PHPUnit\Framework\TestCase;

/**
 * フリーアクション: 想い出カードを捨てて心光を得る（001 §11-3、006 §6、Q-03、FAQ 行 40）。
 */
final class FreeActionServiceTest extends TestCase
{
    private const P = 11;

    private MoveFixture $fx;

    protected function setUp(): void
    {
        $this->fx = new MoveFixture();
    }

    /**
     * @param list<int> $cards
     * @return list<Notice>
     */
    private function discard(array $cards): array
    {
        return $this->fx->kit->free->discardForLight(self::P, $cards);
    }

    /** レベル 1 は 1 枚分。2 枚分につき心光 1 つ。 */
    public function testTwoLevel1CardsMakeOneLight(): void
    {
        [$a, $b] = $this->fx->giveHand(self::P, [1, 2]);

        $notices = $this->discard([$a, $b]);

        self::assertSame(1, $this->fx->players->light(self::P));
        self::assertSame(MemoryLocation::Discard1, $this->fx->memory->find($a)?->location);
        self::assertSame(['cardsDiscarded', 'tokensChanged'], array_map(static fn (Notice $n): string => $n->type, $notices));
        self::assertSame('LIGHT', $notices[0]->args['reason']);
        self::assertSame('DISCARD_LIGHT', $notices[1]->args['reason']);
        self::assertSame(['light' => 1], $notices[1]->args['delta']);
        self::assertSame(1, $this->fx->globals->turnContext()->lightsGained, 'O005 の獲得数に数える');
        self::assertSame(1, $this->fx->victoryChecks);
    }

    /** FAQ 行 40: レベル 2 は 1 枚で 2 枚分。レベル 2 を 1 枚捨てるだけで心光 1 つ（レベル別の捨札へ。Q-23）。 */
    public function testOneLevel2CardMakesOneLight(): void
    {
        [$lv2] = $this->fx->giveHand(self::P, [85]);

        $this->discard([$lv2]);

        self::assertSame(1, $this->fx->players->light(self::P));
        self::assertSame(MemoryLocation::Discard2, $this->fx->memory->find($lv2)?->location);
    }

    /** 端数は失われる（3 枚分は心光 1 つ、5 枚分は 2 つ）。回数は無制限。 */
    public function testRemainderIsLostAndItCanBeRepeated(): void
    {
        [$lv2, $lv1, $lv2b, $x, $y] = $this->fx->giveHand(self::P, [85, 1, 86, 2, 3]);

        $this->discard([$lv2, $lv1]);
        self::assertSame(1, $this->fx->players->light(self::P));
        $this->discard([$lv2b, $x, $y]);
        self::assertSame(3, $this->fx->players->light(self::P), '2 + 1 + 1 = 4 枚分で 2');
    }

    /** 1 枚分だけ（レベル 1 を 1 枚）では心光が得られないので、拒否する（誤って札を失わない）。 */
    public function testOneLevel1CardIsRejected(): void
    {
        [$a, $b] = $this->fx->giveHand(self::P, [1, 2]);

        $this->expectException(EffectViolation::class);
        try {
            $this->discard([$a]);
        } finally {
            self::assertSame(MemoryLocation::Hand, $this->fx->memory->find($a)?->location);
            self::assertSame(0, $this->fx->players->light(self::P));
            unset($b);
        }
    }

    public function testOnlyOwnHandCardsWithoutDuplicates(): void
    {
        [$a, $b] = $this->fx->giveHand(self::P, [1, 2]);
        [$other] = $this->fx->giveHand(22, [3]);

        foreach ([[], [$a, $a], [$a, $other], [$a, 99999]] as $cards) {
            try {
                $this->discard($cards);
                self::fail('expected a violation');
            } catch (EffectViolation) {
                self::addToAssertionCount(1);
            }
        }
        self::assertSame(2, $this->fx->memory->count(MemoryLocation::Hand, self::P));
        unset($b);
    }

    public function testWeightCountsLevel2AsTwo(): void
    {
        $cards = $this->fx->giveHand(self::P, [1, 85, 86]);

        self::assertSame(5, $this->fx->kit->free->weight($cards));
    }
}
