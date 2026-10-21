<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\PublicView;
use Bga\Games\IRevolveThereforeIAm\Flow\Notice;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use IRevolveTests\Support\EndgameRig;
use IRevolveTests\Support\MoveFixture;
use PHPUnit\Framework\TestCase;

/**
 * クリーンアップ（001 §13、006 §5.6）a〜d と、山札切れ（Q-05・Q-24・Q-42）。
 */
final class CleanupPhaseTest extends TestCase
{
    private const P = 11;

    private MoveFixture $fx;

    private EndgameRig $rig;

    protected function setUp(): void
    {
        $this->fx = new MoveFixture();
        $this->rig = new EndgameRig($this->fx);
    }

    /**
     * @return list<Notice>
     */
    private function cleanup(string $expected = Transition::SKIP): array
    {
        self::assertSame($expected, $this->rig->cleanup->run());

        return $this->rig->kit->mutator->drain();
    }

    /** デッキの先頭 `$keep` 枚だけを残して除外にする。 */
    private function shrinkDeck(int $level, int $keep): void
    {
        $loc = MemoryLocation::deck($level)->value;
        $this->fx->db->execute(
            "UPDATE `memory_card` SET `card_loc` = 'OUT', `card_loc_arg` = 0 WHERE `card_loc` = '$loc' AND `card_id` NOT IN ("
            . "SELECT `card_id` FROM `memory_card` WHERE `card_loc` = '$loc' ORDER BY `card_pos` ASC LIMIT $keep)",
        );
    }

    /** a: ターンプレイヤーの横向きの自己構成済みカードだけが縦に戻る。 */
    public function testComposedCardsAreUntapped(): void
    {
        $mine = $this->fx->giveTableau(self::P, [1, 2]);
        $others = $this->fx->giveTableau(22, [3]);
        $this->fx->memory->setTapped([...$mine, ...$others], true);

        $notices = $this->cleanup();

        self::assertSame('cleanup', $notices[0]->type);
        self::assertSame($mine, $notices[0]->args['untapped']);
        foreach ($mine as $id) {
            self::assertFalse($this->fx->memory->find($id)?->tapped);
        }
        self::assertTrue($this->fx->memory->find($others[0])?->tapped, '他のプレイヤーのカードは戻さない');
    }

    /** b: 裏向き（使用済み）の万能移動鍵が表に戻る。 */
    public function testUsedWildKeysAreRestored(): void
    {
        $this->fx->players->setToken(self::P, PlayerValues::COLUMN_WILD_UP, 1);
        $this->fx->players->setToken(self::P, PlayerValues::COLUMN_WILD_USED, 2);

        $notices = $this->cleanup();

        self::assertSame(2, $notices[0]->args['wild_restored']);
        self::assertSame(3, $this->fx->players->wildUp(self::P));
        self::assertSame(0, $this->fx->players->wildUsed(self::P));
        self::assertSame(self::P, $notices[0]->args['player_id']);
    }

    /** c: カードのない部屋に、その部屋のレベルの山札から 1 枚が裏向きで補充される。 */
    public function testEmptyRoomsAreRefilledFromTheDeckOfTheirLevel(): void
    {
        foreach ([2, 1] as $pos) {  // 2 はレベル 1、1 はレベル 2
            $card = $this->fx->memory->inLoc(MemoryLocation::Room, $pos)[0];
            $this->fx->memory->move($card->id, MemoryLocation::Hand, self::P);
        }
        $top1 = $this->fx->memory->peekDeck(1, 1)[0];
        $top2 = $this->fx->memory->peekDeck(2, 1)[0];
        $deck1 = $this->fx->memory->count(MemoryLocation::Deck1);
        $deck2 = $this->fx->memory->count(MemoryLocation::Deck2);

        $notices = $this->cleanup();

        $rooms = [];
        foreach ($notices[0]->args['rooms'] as $row) {
            $rooms[$row['pos']] = $row;
        }
        self::assertSame($top2, $rooms[1]['card_id']);
        self::assertSame($top1, $rooms[2]['card_id']);
        self::assertMatchesRegularExpression('/^[12][A-Z]$/', $rooms[1]['back']);
        self::assertSame($top2, $this->fx->memory->inLoc(MemoryLocation::Room, 1)[0]->id);
        self::assertFalse($this->fx->memory->inLoc(MemoryLocation::Room, 1)[0]->faceUp);
        self::assertSame($deck1 - 1, $this->fx->memory->count(MemoryLocation::Deck1));
        self::assertSame($deck2 - 1, $this->fx->memory->count(MemoryLocation::Deck2));
        self::assertSame($deck1 - 1, $notices[0]->args['decks'][1]['count']);
        self::assertSame([], $this->fx->globals->deckOut());
        self::assertArrayNotHasKey('type', $rooms[1], '補充した部屋のカードは裏向きなので種類を載せない');
    }

    /** d: 場札が 6 枚未満なら、空いた枠にレベル 1 の山札から表向きで補充する。 */
    public function testOpenCardsAreRefilledToSix(): void
    {
        $open = $this->fx->memory->inLocAnyArg(MemoryLocation::Open);
        self::assertCount(6, $open);
        foreach ([$open[1], $open[4]] as $card) {
            $this->fx->memory->move($card->id, MemoryLocation::Hand, self::P);
        }
        $next = $this->fx->memory->peekDeck(1, 2);

        $notices = $this->cleanup();

        $refilled = $notices[0]->args['open'];
        self::assertSame([$open[1]->locationArg, $open[4]->locationArg], array_column($refilled, 'slot'));
        self::assertSame($next, array_column($refilled, 'id'));
        self::assertSame(
            PublicView::definition($this->fx->memory->find($next[0])?->type ?? 0)->id,
            sprintf('M%03d', $refilled[0]['type']),
        );
        self::assertCount(6, $this->fx->memory->inLocAnyArg(MemoryLocation::Open));
    }

    /** 何も補充しなくても N29 を送る（クライアントが回復の表示を行う）。 */
    public function testNothingToDoStillSendsTheNotification(): void
    {
        $notices = $this->cleanup();

        self::assertCount(1, $notices);
        self::assertSame([], $notices[0]->args['rooms']);
        self::assertSame([], $notices[0]->args['open']);
        self::assertSame([], $notices[0]->args['untapped']);
        self::assertSame(0, $notices[0]->args['wild_restored']);
        self::assertNull($this->fx->globals->endReason());
    }

    /** レベル 1 の山札が場札の補充で足りない: 補充できるだけ補充して全員敗北（Q-05）。 */
    public function testLevelOneDeckRunningOutDuringOpenRefillEndsInDeckOut(): void
    {
        foreach (array_slice($this->fx->memory->inLocAnyArg(MemoryLocation::Open), 0, 3) as $card) {
            $this->fx->memory->move($card->id, MemoryLocation::Hand, self::P);
        }
        $this->shrinkDeck(1, 2);

        $notices = $this->cleanup(Transition::GAME_END);

        self::assertCount(2, $notices[0]->args['open'], '引けるだけ引く');
        self::assertCount(5, $this->fx->memory->inLocAnyArg(MemoryLocation::Open));
        self::assertSame(0, $this->fx->memory->count(MemoryLocation::Deck1));
        self::assertSame('DECK_OUT', $this->fx->globals->endReason());
        self::assertNull($this->fx->globals->winnerId());
        self::assertSame(['cleanup', 'deckOut'], array_map(static fn (Notice $n): string => $n->type, $notices));
        self::assertSame(1, $notices[1]->args['level']);
    }

    /** 山札が 0 枚になっただけでは敗北にならない（引く必要があるのに足りなかった瞬間だけ）。 */
    public function testAnEmptyDeckThatIsNotNeededDoesNotEndTheGame(): void
    {
        $this->shrinkDeck(1, 0);

        $this->cleanup();

        self::assertNull($this->fx->globals->endReason());
    }

    /** レベル 2 の山札も同じ: レベル 2 の部屋の補充で足りなければ全員敗北（Q-24）。 */
    public function testLevelTwoDeckRunningOutDuringRoomRefillEndsInDeckOut(): void
    {
        foreach ([1, 4] as $pos) {
            $card = $this->fx->memory->inLoc(MemoryLocation::Room, $pos)[0];
            $this->fx->memory->move($card->id, MemoryLocation::Hand, self::P);
        }
        $this->shrinkDeck(2, 1);

        $notices = $this->cleanup(Transition::GAME_END);

        self::assertCount(1, $notices[0]->args['rooms'], '引けるだけ引く');
        self::assertSame([2], $this->fx->globals->deckOut());
        self::assertSame('DECK_OUT', $this->fx->globals->endReason());
        self::assertSame(2, $notices[1]->args['level']);
        self::assertSame(0, $this->fx->memory->count(MemoryLocation::Deck2));
    }

    /** 山札切れと勝利が同時なら勝利を優先する（Q-05）。 */
    public function testVictoryWinsOverDeckOut(): void
    {
        $this->fx->giveTableau(22, EndgameRig::typesForScore(20));
        foreach (array_slice($this->fx->memory->inLocAnyArg(MemoryLocation::Open), 0, 2) as $card) {
            $this->fx->memory->move($card->id, MemoryLocation::Hand, self::P);
        }
        $this->shrinkDeck(1, 0);

        $this->cleanup(Transition::GAME_END);

        self::assertSame('FRAGMENTS', $this->fx->globals->endReason());
        self::assertSame(22, $this->fx->globals->winnerId());
    }

    /** 引く処理の共通の口: 足りなければ引けるだけ引いてフラグを立て、frame の終了時（スタックが空）に全員敗北にする。 */
    public function testShortDrawEndsTheGameAtTheEndOfTheFrame(): void
    {
        $this->shrinkDeck(1, 2);

        $drawn = $this->rig->kit->mutator->drawFromDeck(self::P, 5, null);
        self::assertSame(2, $drawn);
        self::assertSame([1], $this->fx->globals->deckOut());
        self::assertNull($this->fx->globals->endReason(), '判定は frame の終了時');

        $result = $this->rig->kit->resolver->resolve();

        self::assertSame(Transition::GAME_END, $result->transition);
        self::assertSame('DECK_OUT', $this->fx->globals->endReason());
        self::assertSame('deckOut', $result->notices[array_key_last($result->notices)]->type);
    }

    /** 入力待ちの frame の途中では判定しない（frame が終わるまで）。 */
    public function testDeckOutIsNotSettledWhileAFrameIsWaiting(): void
    {
        $this->shrinkDeck(1, 0);
        $this->rig->kit->mutator->drawFromDeck(self::P, 1, null);
        $this->fx->stack->pushFront([new EffectFrame(EffectFrame::KIND_END_CHECK, self::P)]);
        $head = $this->fx->stack->peek() ?? throw new \LogicException('no frame');
        $this->fx->stack->markWaiting($head->id ?? 0, ['kind' => 'CONFIRM_USE', 'owner' => self::P, 'step' => 0, 'data' => []]);

        $result = $this->rig->kit->resolver->resolve();

        self::assertSame(Transition::CHOICE, $result->transition);
        self::assertNull($this->fx->globals->endReason());
    }

    /** 引く側で勝利が成立していれば、山札切れより勝利（frame の終了時の判定でも）。 */
    public function testVictoryWinsOverShortDrawAtTheEndOfTheFrame(): void
    {
        $this->fx->giveTableau(self::P, EndgameRig::typesForScore(20));
        $this->shrinkDeck(1, 0);
        $this->rig->kit->mutator->drawFromDeck(self::P, 1, null);

        $result = $this->rig->kit->resolver->resolve();

        self::assertSame(Transition::GAME_END, $result->transition);
        self::assertSame('FRAGMENTS', $this->fx->globals->endReason());
        self::assertSame(self::P, $this->fx->globals->winnerId());
    }
}
