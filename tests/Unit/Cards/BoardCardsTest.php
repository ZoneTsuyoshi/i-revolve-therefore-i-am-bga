<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Cards;

use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use IRevolveTests\Support\CardBench;
use PHPUnit\Framework\TestCase;

/**
 * ボードに触る操作（OP-16〜OP-21。M052〜M057・M072・I04・I05。UT-45・UT-46・UT-52）。
 * 床の配置は固定（位置 N の床は F0N）。手番は 11。
 */
final class BoardCardsTest extends TestCase
{
    private CardBench $b;

    protected function setUp(): void
    {
        $this->b = new CardBench();
    }

    private function roomCard(int $pos): int
    {
        return $this->b->fx->memory->inLoc(MemoryLocation::Room, $pos)[0]->id;
    }

    /** UT-52: M052〜M054 は同じレベルの 2 つの部屋の想い出カード（裏向き）を入れ替える。人格のいる部屋も対象。 */
    public function testSwapRoomCards(): void
    {
        $this->b->fx->giveTableau(CardBench::P, [52]);
        $this->b->fx->place(22, 3);
        [$two, $three] = [$this->roomCard(2), $this->roomCard(3)];

        $this->b->emit(Event::TURN_START);
        $this->b->drive(['use', ['a' => 2, 'b' => 3]]);

        self::assertSame($three, $this->roomCard(2));
        self::assertSame($two, $this->roomCard(3));
        self::assertContains('roomsSwapped', $this->b->noticeTypes());
        $swapped = array_values(array_filter($this->b->notices, static fn ($n): bool => $n->type === 'roomsSwapped'))[0];
        self::assertSame('CARD', $swapped->args['kind']);
        self::assertArrayNotHasKey('type', $swapped->args['after'][0], '券面は誰も見ない');
        self::assertSame(['CONFIRM_USE', 'SWAP_ROOM_CARDS'], $this->b->choices);
    }

    /** UT-52: 同じレベルでない 2 部屋・同じ部屋は拒否される。スキップなら何も起きない。 */
    public function testSwapRoomCardsValidation(): void
    {
        $this->b->fx->giveTableau(CardBench::P, [53]);
        $one = $this->roomCard(1);
        $this->b->emit(Event::TURN_START);
        self::assertSame(Transition::CHOICE, $this->b->fx->kit->resolver->resolve()->transition);
        $this->b->fx->kit->choices->answer(CardBench::P, ['use' => true]);
        self::assertSame(Transition::CHOICE, $this->b->fx->kit->resolver->resolve()->transition);

        foreach ([['a' => 1, 'b' => 2], ['a' => 2, 'b' => 2], ['a' => 2, 'b' => 13]] as $answer) {
            try {
                $this->b->fx->kit->choices->answer(CardBench::P, $answer);
                self::fail('rejected');
            } catch (EffectViolation) {
                self::addToAssertionCount(1);
            }
        }
        $this->b->fx->kit->choices->skip(CardBench::P);
        $this->b->drive();
        self::assertSame($one, $this->roomCard(1));
    }

    /** UT-52: M055〜M057 はレベル 1 の 2 つの部屋の床だけを入れ替える。想い出カード・床の下・人格は位置に残る。 */
    public function testSwapRoomFloorsLeavesEverythingElse(): void
    {
        $this->b->fx->giveTableau(CardBench::P, [55]);
        $this->b->fx->place(22, 2);
        [$cardTwo, $cardThree] = [$this->roomCard(2), $this->roomCard(3)];
        $hand = $this->b->fx->giveHand(CardBench::P, [1])[0];
        $this->b->fx->memory->move($hand, MemoryLocation::UnderFloor, 2);

        $this->b->emit(Event::TURN_START);
        $this->b->drive(['use', ['a' => 2, 'b' => 3]]);

        self::assertSame(3, $this->b->fx->rooms->floorAt(2));
        self::assertSame(2, $this->b->fx->rooms->floorAt(3));
        self::assertSame([$cardTwo, $cardThree], [$this->roomCard(2), $this->roomCard(3)]);
        self::assertSame([$hand], $this->b->fx->view->floorUnderIds(2), '床の下のカードは部屋の位置に残る');
        self::assertSame(2, $this->b->fx->players->persona(22));

        // レベル 2 の部屋は選べない
        $b = new CardBench();
        $b->fx->giveTableau(CardBench::P, [56]);
        $b->emit(Event::TURN_START);
        $b->fx->kit->resolver->resolve();
        $b->fx->kit->choices->answer(CardBench::P, ['use' => true]);
        $b->fx->kit->resolver->resolve();
        $this->expectException(EffectViolation::class);
        $b->fx->kit->choices->answer(CardBench::P, ['a' => 1, 'b' => 2]);
    }

    /** UT-52（R8-12）: M072 のワープは入室に数えず、「止まった」扱いもしない。今いる部屋も選べる。 */
    public function testWarpDoesNotCountAsAnEntry(): void
    {
        $this->b->fx->giveTableau(CardBench::P, [72]);
        $this->b->fx->place(CardBench::P, 4);

        $this->b->emit(Event::TURN_START);
        $this->b->drive(['use', ['pos' => 9]]);

        self::assertSame(9, $this->b->fx->players->persona(CardBench::P));
        $context = $this->b->fx->globals->turnContext();
        self::assertSame(0, $context->roomsEnteredCount);
        self::assertSame([], $context->enteredRooms);
        $moved = array_values(array_filter($this->b->notices, static fn ($n): bool => $n->type === 'personaMoved'))[0];
        self::assertSame('WARP', $moved->args['reason']);
        self::assertFalse($moved->args['counts_as_entry']);
    }

    /** UT-46: I05 は人格のいない同レベルの部屋の想い出カードと、手札の 1 枚を入れ替える。手札から置くカードは裏向き。 */
    public function testEntrepreneurSwapsWithAnUnoccupiedRoom(): void
    {
        $this->b->ego('I05');
        $this->b->fx->place(CardBench::P, 1);
        $this->b->fx->place(22, 6);
        $hand = $this->b->fx->giveHand(CardBench::P, [1])[0];
        $room5 = $this->roomCard(5);

        $this->b->emit(Event::TURN_START);
        $this->b->drive(['use', ['hand' => $hand, 'pos' => 5]]);

        self::assertSame($hand, $this->roomCard(5));
        self::assertSame(MemoryLocation::Hand, $this->b->fx->memory->find($room5)?->location);
        self::assertFalse($this->b->fx->memory->find($hand)?->faceUp);
        $notice = array_values(array_filter($this->b->notices, static fn ($n): bool => $n->type === 'roomCardSwappedWithHand'))[0];
        self::assertSame($hand, $notice->args['hand_card_id']);
        self::assertArrayHasKey(CardBench::P, $notice->args['_private'], '取った部屋のカードは本人にだけ券面を通知する');
    }

    /** UT-46: 人格のいる部屋・レベルの違う部屋は選べない。 */
    public function testEntrepreneurRejectsOccupiedAndWrongLevelRooms(): void
    {
        $this->b->ego('I05');
        $this->b->fx->place(CardBench::P, 1);
        $this->b->fx->place(22, 6);
        $hand = $this->b->fx->giveHand(CardBench::P, [1])[0];
        $this->b->emit(Event::TURN_START);
        $this->b->fx->kit->resolver->resolve();
        $this->b->fx->kit->choices->answer(CardBench::P, ['use' => true]);
        $this->b->fx->kit->resolver->resolve();

        foreach ([['hand' => $hand, 'pos' => 6], ['hand' => $hand, 'pos' => 1], ['hand' => $hand, 'pos' => 4]] as $answer) {
            try {
                $this->b->fx->kit->choices->answer(CardBench::P, $answer);
                self::fail('rejected');
            } catch (EffectViolation) {
                self::addToAssertionCount(1);
            }
        }
    }

    /** UT-45: I04.2 は止まった部屋の床の下のカードを全て手札に加える（強制）。想起効果の移動先でも。上限超過は単位の終わりに捨てる。 */
    public function testLogicianCollectsCardsUnderTheFloorWhereItStops(): void
    {
        $this->b->ego('I04');
        $cards = $this->b->fx->giveHand(CardBench::P, [1, 2]);
        $this->b->fx->place(CardBench::P, 6);
        foreach ($cards as $card) {
            $this->b->fx->memory->move($card, MemoryLocation::UnderFloor, 6);
        }

        self::assertFalse($this->b->emit(Event::PERSONA_STOPPED, ['room' => 5, 'via' => 'MOVE']), '床の下が空の部屋では働かない');
        self::assertTrue($this->b->emit(Event::PERSONA_STOPPED, ['room' => 6, 'via' => 'RECALL_MOVE']));
        $this->b->drive();

        self::assertSame(2, $this->b->hand());
        self::assertSame([], $this->b->fx->view->floorUnderIds(6));
        self::assertContains('cardsGained', $this->b->noticeTypes());
    }

    /** UT-45（R8-15）: 払った手札は、現在の部屋から反時計回りに 1 枚ずつ床の下へ（現在の部屋を含む。公開）。 */
    public function testPlaceUnderFloorGoesCounterClockwiseFromTheCurrentRoom(): void
    {
        $this->b->fx->place(CardBench::P, 2);
        [$a, $b, $c] = $this->b->fx->giveHand(CardBench::P, [1, 2, 3]);

        $this->b->fx->kit->mutator->placeUnderFloor(CardBench::P, [$a, $b, $c]);

        self::assertSame([$a], $this->b->fx->view->floorUnderIds(2));
        self::assertSame([$b], $this->b->fx->view->floorUnderIds(1));
        self::assertSame([$c], $this->b->fx->view->floorUnderIds(12), '1 の次は 12');
        $notices = $this->b->fx->kit->mutator->drain();
        self::assertCount(3, $notices);
        self::assertSame('cardsPlacedUnderFloor', $notices[0]->type);
    }
}
