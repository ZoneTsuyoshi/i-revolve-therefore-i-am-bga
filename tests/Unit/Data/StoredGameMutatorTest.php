<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Data;

use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Material\FloorBonus;
use Bga\Games\IRevolveThereforeIAm\Material\Origin;
use Bga\Games\IRevolveThereforeIAm\Material\RecallGroup;
use Bga\Games\IRevolveThereforeIAm\Material\TokenKind;
use IRevolveTests\Support\MoveFixture;
use PHPUnit\Framework\TestCase;

/**
 * `GameMutator` の本番の実装（DB に書き、通知を溜める。008 §11.1、007 §10）。
 */
final class StoredGameMutatorTest extends TestCase
{
    private const P = 11;

    private MoveFixture $fx;

    protected function setUp(): void
    {
        $this->fx = new MoveFixture();
    }

    /** 引いたカードは本人の `_private` にだけ入り、公開の args には枚数と山の同期だけが載る（007 §9、N12）。 */
    public function testDrawnCardsAreOnlyInThePrivatePart(): void
    {
        $this->fx->kit->mutator->drawFromDeck(self::P, 2, Origin::FLOOR);
        $notices = $this->fx->kit->mutator->drain();

        self::assertCount(1, $notices);
        $args = $notices[0]->args;
        self::assertSame('cardsDrawn', $notices[0]->type);
        self::assertSame(2, $args['count']);
        self::assertSame('FLOOR', $args['reason']);
        self::assertSame(2, $args['hand_count']);
        self::assertArrayNotHasKey('cards', $args);
        self::assertCount(2, $args['_private'][self::P]['cards']);
        self::assertArrayHasKey(1, $args['decks']);
        self::assertArrayHasKey(2, $args['decks']);
        self::assertNotNull($args['decks'][1]['top']);
        self::assertSame([], $this->fx->kit->mutator->drain(), '取り出した分は消える');
    }

    public function testDrawingMoreThanTheDeckHasReturnsWhatWasDrawnAndFlagsTheDeck(): void
    {
        $deck = $this->fx->memory->count(MemoryLocation::Deck1);
        $drawn = $this->fx->kit->mutator->drawFromDeck(self::P, $deck + 3, null);
        $this->fx->kit->mutator->flagDeckExhausted();
        $this->fx->kit->mutator->flagDeckExhausted();

        self::assertSame($deck, $drawn);
        self::assertSame([1], $this->fx->globals->deckOut());
    }

    /** 場札は公開（種類つき）で、枠が通知に入る。交換は場札の枠に手札のカードが入る。 */
    public function testFieldCardsAreGainedAndSwappedBySlot(): void
    {
        $m = $this->fx->kit->mutator;
        $field = $this->fx->memory->inLocAnyArg(MemoryLocation::Open);
        $m->takeFromField(self::P, $field[0]->id);
        $gained = $m->drain()[0];

        self::assertSame('cardsGained', $gained->type);
        self::assertSame([$field[0]->locationArg], $gained->args['from_slots']);
        self::assertSame($field[0]->type, $gained->args['cards'][0]['type']);

        $hand = $this->fx->giveHand(self::P, [1]);
        $m->swapHandAndField(self::P, $hand, [$field[1]->id]);
        $swapped = $m->drain()[0];

        self::assertSame('cardsExchanged', $swapped->type);
        self::assertSame('HAND_OPEN', $swapped->args['kind']);
        self::assertSame(MemoryLocation::Open, $this->fx->memory->find($hand[0])?->location);
        self::assertSame($field[1]->locationArg, $this->fx->memory->find($hand[0])?->locationArg);
        self::assertSame(MemoryLocation::Hand, $this->fx->memory->find($field[1]->id)?->location);
    }

    public function testTokensAndLightAreCountedWithNotices(): void
    {
        $m = $this->fx->kit->mutator;

        $m->gainLight(self::P, 2, Origin::RECALL);
        $m->gainToken(self::P, TokenKind::WILD_MOVE_KEY, 1);
        $m->grantFloorBonus(self::P, FloorBonus::LIGHT);
        $m->grantFloorBonus(self::P, FloorBonus::NONE);
        $notices = $m->drain();

        self::assertSame(3, $this->fx->players->light(self::P));
        self::assertSame(1, $this->fx->players->wildUp(self::P));
        self::assertSame(3, $this->fx->globals->turnContext()->lightsGained);
        self::assertCount(3, $notices);
        self::assertSame('RECALL', $notices[0]->args['reason']);
        self::assertSame('TOUR_BONUS', $notices[2]->args['reason']);
        self::assertSame('TOUR', $notices[2]->args['origin']);
    }

    /** Q-09: 想起効果の移動も入室に数える。12 部屋目の入室で刹那の追憶が成立する。 */
    public function testTwelfthEntryByARecallMoveWinsInstantly(): void
    {
        $ctx = $this->fx->globals->turnContext();
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11] as $pos) {
            $ctx->recordEntry($pos, in_array($pos, [1, 4, 7, 10], true));
        }
        $this->fx->globals->setTurnContext($ctx);
        $this->fx->place(self::P, 11);

        $this->fx->kit->mutator->movePersona(self::P, 12, true);

        self::assertSame(12, $this->fx->players->persona(self::P));
        self::assertSame('INSTANT_MEMORY', $this->fx->globals->endReason());
        self::assertSame(self::P, $this->fx->globals->winnerId());
        self::assertTrue($this->fx->kit->mutator->isGameOver());
    }

    public function testRecallActivationFrameIsQueuedAtTheFront(): void
    {
        $this->fx->kit->mutator->pushRecallActivation(self::P, 'M013', 1, [RecallGroup::DRAW, RecallGroup::GAIN]);

        $frames = $this->fx->stack->all();
        self::assertSame(EffectFrame::KIND_RECALL_ACTIVATION, $frames[0]->kind);
        self::assertSame('M013', $frames[0]->source);
        self::assertSame(['DRAW', 'GAIN'], $frames[0]->params['groups']);

        $this->fx->globals->setResume(60);
        self::assertSame(Transition::RESUME_CONSTRUCT, $this->fx->kit->resolver->resolve()->transition);
        self::assertSame(1, $this->fx->players->light(self::P));
    }

    /** M071: 発動してよい想起効果のグループを限る（DRAW・GAIN・EXCHANGE。床の代替・移動は使えない。R8-5）。 */
    public function testRecallActivationHonorsTheAllowedGroups(): void
    {
        $this->fx->kit->mutator->pushRecallActivation(self::P, 'M031', 1, [RecallGroup::DRAW, RecallGroup::GAIN, RecallGroup::EXCHANGE]);
        $this->fx->globals->setResume(60);
        $this->fx->kit->resolver->resolve();
        self::assertSame([], $this->fx->globals->turnContext()->floorAlternatives, 'ALT_ANALYSIS は使えない');

        $this->fx->kit->mutator->pushRecallActivation(self::P, 'M031', 1, []);
        $this->fx->kit->resolver->resolve();
        self::assertCount(1, $this->fx->globals->turnContext()->floorAlternatives);
    }

    /** UT-29 / UT-39: 効果による階層の上昇は、精神分析の実行回数に数えない。到着の心光とボーナスは同じように処理する。 */
    public function testAdvancingByAnEffectDoesNotCountAsAnAnalysisAction(): void
    {
        $hadLight = $this->fx->kit->mutator->advanceSpirit(self::P, 'R1A', 'EFFECT');

        self::assertTrue($hadLight);
        self::assertSame('R1A', $this->fx->players->spirit(self::P));
        self::assertSame(0, $this->fx->globals->turnContext()->analysisActionsDone);
        self::assertSame('EFFECT', $this->fx->kit->mutator->drain()[0]->args['via']);
        self::assertSame(Transition::RESUME_CONSTRUCT, $this->afterResolve());
        self::assertSame(1, $this->fx->players->light(self::P));
        self::assertSame(1, $this->fx->memory->count(MemoryLocation::Hand, self::P), 'R1 のボーナス');
    }

    private function afterResolve(): string
    {
        $this->fx->globals->setResume(60);

        return $this->fx->kit->resolver->resolve()->transition;
    }

    public function testRoomCardAcquisitionIsAnnouncedWithItsType(): void
    {
        $id = $this->fx->roomCard(2, 13);

        self::assertSame($id, $this->fx->kit->mutator->takeRoomCard(self::P, 2));
        $notice = $this->fx->kit->mutator->drain()[0];

        self::assertSame('roomCardAcquired', $notice->type);
        self::assertSame(['id' => $id, 'type' => 13], $notice->args['card']);
        self::assertNull($this->fx->kit->mutator->takeRoomCard(self::P, 2), '部屋は空になる');
    }
}
