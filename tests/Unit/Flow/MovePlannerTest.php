<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Flow\MoveBoard;
use Bga\Games\IRevolveThereforeIAm\Flow\MovePlanner;
use Bga\Games\IRevolveThereforeIAm\Flow\MoveViolation;
use Bga\Games\IRevolveThereforeIAm\Material\FloorBonus;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use IRevolveTests\Support\MoveBoards;
use IRevolveTests\Support\QueryKit;
use PHPUnit\Framework\TestCase;

/**
 * 移動の経路と支払いの検証（仕様書 006 §5.1、008 §6.2、001 §6・§6-1）。
 */
final class MovePlannerTest extends TestCase
{
    private const W = KeyType::WISDOM;
    private const F = KeyType::WILL;
    private const E = KeyType::EMOTION;

    private function planner(?QueryKit $kit = null): MovePlanner
    {
        $kit ??= new QueryKit();

        return new MovePlanner($kit->move, $kit->light);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function assertViolation(string $reason, MovePlanner $planner, MoveBoard $board, array $input): void
    {
        try {
            $planner->plan($board, $input);
        } catch (MoveViolation $e) {
            self::assertSame($reason, $e->reason, $e->getMessage());

            return;
        }
        self::fail("expected $reason");
    }

    /** @return array{src: string, card: int, key: int} */
    private static function tab(int $card, int $key = 0): array
    {
        return ['src' => 'TABLEAU', 'card' => $card, 'key' => $key];
    }

    /** @return array{src: string, card: int, key: int} */
    private static function hand(int $card, int $key = 0): array
    {
        return ['src' => 'HAND', 'card' => $card, 'key' => $key];
    }

    private const LIGHT = ['src' => 'LIGHT'];
    private const TOKEN = ['src' => 'TOKEN'];

    // ---- 経路 ----

    public function testMustMoveAtLeastOneRoomWhenAnyMoveIsAffordable(): void
    {
        $board = MoveBoards::board(position: 2, wildUp: 1);
        $this->assertViolation(MoveViolation::MUST_MOVE, $this->planner(), $board, ['rooms' => [], 'payments' => []]);
        $this->assertViolation(MoveViolation::MUST_MOVE, $this->planner(), $board, []);
    }

    /** Q-38: どう組み合わせても 1 部屋も払えないときは、移動せずに想起フェイズへ進む。 */
    public function testStaysWhenNothingIsAffordable(): void
    {
        $board = MoveBoards::board(position: 2, hand: [], tableau: [MoveBoards::tapped(5, self::W)]);
        $planner = $this->planner();

        self::assertFalse($planner->canMove($board));
        $outcome = $planner->plan($board, ['rooms' => [], 'payments' => []]);
        self::assertFalse($outcome->moved());
        self::assertSame([], $outcome->steps);
    }

    public function testSkipRouteCountsAsAnAffordableMove(): void
    {
        // 次の部屋 4（レベル 2、錠前 2）は払えないが、スキップすれば 7 ではなく 5（レベル 1）に入れる
        $board = MoveBoards::board(position: 3, tableau: [MoveBoards::card(5, self::W)]);
        $planner = $this->planner();

        self::assertTrue($planner->canMove($board));
    }

    public function testRouteMustFollowTheCorridorClockwise(): void
    {
        $planner = $this->planner();
        $board = MoveBoards::board(position: 5, wildUp: 5);
        $pay = [[self::TOKEN]];

        self::assertTrue($planner->plan($board, ['rooms' => [6], 'payments' => $pay])->moved());
        $this->assertViolation(MoveViolation::INVALID_ROUTE, $planner, $board, ['rooms' => [4], 'payments' => $pay]);
        $this->assertViolation(MoveViolation::INVALID_ROUTE, $planner, $board, ['rooms' => [7], 'payments' => $pay]);
        $this->assertViolation(MoveViolation::INVALID_ROUTE, $planner, $board, ['rooms' => [5], 'payments' => $pay]);
        $this->assertViolation(MoveViolation::INVALID_ROUTE, $planner, $board, ['rooms' => [13], 'payments' => $pay]);
    }

    public function testCorridorWrapsAroundFromTwelveToOne(): void
    {
        $planner = $this->planner();
        $board = MoveBoards::board(position: 11, wildUp: 3);
        $outcome = $planner->plan($board, [
            'rooms' => [12, 1],
            'payments' => [[self::TOKEN], [self::TOKEN, self::TOKEN]],
        ]);

        self::assertSame([12, 1], array_map(static fn($s): int => $s->pos, $outcome->steps));
        self::assertSame(11, $outcome->steps[0]->from);
        self::assertSame(12, $outcome->steps[1]->from);
        self::assertSame(FloorBonus::ANALYSIS_TOKEN, $outcome->steps[1]->entryBonus);
    }

    public function testRouteLengthIsBoundedAndListsMustMatch(): void
    {
        $planner = $this->planner();
        $board = MoveBoards::board(position: 1, wildUp: 100);
        $this->assertViolation(MoveViolation::BAD_INPUT, $planner, $board, ['rooms' => [2], 'payments' => []]);
        $this->assertViolation(MoveViolation::BAD_INPUT, $planner, $board, ['rooms' => 'x', 'payments' => []]);
        $this->assertViolation(MoveViolation::BAD_INPUT, $planner, $board, ['rooms' => ['2'], 'payments' => [[self::TOKEN]]]);
        $this->assertViolation(MoveViolation::BAD_INPUT, $planner, $board, ['rooms' => [2], 'payments' => [[['src' => 'BANK']]]]);
        $this->assertViolation(MoveViolation::BAD_INPUT, $planner, $board, ['rooms' => [2], 'payments' => [[['src' => 'HAND']]]]);

        $rooms = [];
        $payments = [];
        $pos = 1;
        for ($i = 0; $i < 25; $i++) {
            $pos = MoveBoard::after($pos);
            $rooms[] = $pos;
            $payments[] = $board->room($pos)->level === 2 ? [self::TOKEN, self::TOKEN] : [self::TOKEN];
        }
        $this->assertViolation(MoveViolation::BAD_INPUT, $planner, $board, ['rooms' => $rooms, 'payments' => $payments]);
    }

    // ---- スキップ ----

    public function testSkippingALevel2RoomEntersTheNextOneAndReportsTheSkipBonus(): void
    {
        $planner = $this->planner();
        // 3 の次の 4 はレベル 2（周遊ボーナス: 心光）。スキップして 5 に入る
        $board = MoveBoards::board(position: 3, wildUp: 1);
        $outcome = $planner->plan($board, ['rooms' => [5], 'payments' => [[self::TOKEN]]]);

        $step = $outcome->steps[0];
        self::assertSame(4, $step->skipped);
        self::assertSame(FloorBonus::LIGHT, $step->skipBonus);
        self::assertSame(5, $step->pos);
        self::assertSame(FloorBonus::NONE, $step->entryBonus);
        self::assertSame(3, $step->from);
    }

    public function testOnlyALevel2RoomCanBeSkipped(): void
    {
        $planner = $this->planner();
        $board = MoveBoards::board(position: 2, wildUp: 3);
        // 3 はレベル 1 なのでスキップできない
        $this->assertViolation(MoveViolation::INVALID_ROUTE, $planner, $board, ['rooms' => [4], 'payments' => [[self::TOKEN, self::TOKEN]]]);
        // 3 に入れば次の 4（レベル 2）に入室できる
        $outcome = $planner->plan($board, [
            'rooms' => [3, 4],
            'payments' => [[self::TOKEN], [self::TOKEN, self::TOKEN]],
        ]);
        self::assertSame([null, null], array_map(static fn($s): ?int => $s->skipped, $outcome->steps));
    }

    public function testLevel2RoomNeedsTwoLocksPaidWithTheSameKindOfLock(): void
    {
        $planner = $this->planner();
        $board = MoveBoards::board(position: 3, locks: [4 => self::F], wildUp: 2, tableau: [MoveBoards::card(5, self::F, self::F)]);

        $this->assertViolation(MoveViolation::LOCK_COUNT, $planner, $board, ['rooms' => [4], 'payments' => [[self::TOKEN]]]);
        $this->assertViolation(MoveViolation::LOCK_COUNT, $planner, MoveBoards::board(position: 2, wildUp: 2), [
            'rooms' => [3],
            'payments' => [[self::TOKEN, self::TOKEN]],
        ]);
        // 2 本の意志鍵を持つ 1 枚のカードで、レベル 2 の部屋の錠前 2 個を払える
        $outcome = $planner->plan($board, ['rooms' => [4], 'payments' => [[self::tab(5, 0), self::tab(5, 1)]]]);
        self::assertSame([5], $outcome->steps[0]->tapped);
    }

    // ---- 鍵 ----

    public function testKeyMustMatchTheLockUnlessWild(): void
    {
        $planner = $this->planner();
        $board = MoveBoards::board(
            position: 5,
            locks: [6 => self::E],
            tableau: [MoveBoards::card(1, self::W), MoveBoards::card(2, self::E), MoveBoards::card(3, KeyType::WILD)],
        );

        $this->assertViolation(MoveViolation::KEY_MISMATCH, $planner, $board, ['rooms' => [6], 'payments' => [[self::tab(1)]]]);
        self::assertTrue($planner->plan($board, ['rooms' => [6], 'payments' => [[self::tab(2)]]])->moved());
        self::assertTrue($planner->plan($board, ['rooms' => [6], 'payments' => [[self::tab(3)]]])->moved());
    }

    /** UT-22: M096 があれば、意志鍵を万能鍵として扱い、知恵の錠前を払える（元の種類としても使える）。 */
    public function testM096ViewsWillKeyAsWildInMove(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M096']);
        $planner = $this->planner($kit);
        $board = MoveBoards::board(position: 5, locks: [6 => self::W, 7 => self::F], tableau: [MoveBoards::card(1, self::F)]);

        self::assertTrue($planner->plan($board, ['rooms' => [6], 'payments' => [[self::tab(1)]]])->moved());

        $without = $this->planner();
        $this->assertViolation(MoveViolation::KEY_MISMATCH, $without, $board, ['rooms' => [6], 'payments' => [[self::tab(1)]]]);
    }

    /** FAQ 行 19・20（UT-24）: M096 で意志鍵を万能鍵として使っても、使った鍵の種類は印刷された意志で数える。 */
    public function testKeyKindIsCountedByThePrintedKeyEvenWhenViewedAsWild(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M096']);
        $outcome = $this->planner($kit)->plan(
            MoveBoards::board(position: 5, locks: [6 => self::W], hand: [MoveBoards::card(1, self::F)]),
            ['rooms' => [6], 'payments' => [[self::hand(1)]]],
        );

        self::assertSame([self::F], $outcome->keyKinds);
    }

    /** FAQ 行 19: 心光・万能移動鍵トークンで払った錠前は「その鍵を使用」に数えない。 */
    public function testLightAndTokenPaymentsAreNotCountedAsKeyKinds(): void
    {
        $outcome = $this->planner()->plan(
            MoveBoards::board(position: 4, lights: 1, wildUp: 1),
            ['rooms' => [5, 6], 'payments' => [[self::LIGHT], [self::TOKEN]]],
        );

        self::assertSame([], $outcome->keyKinds);
        self::assertSame(1, $outcome->wildTokensUsed);
    }

    public function testCardMustBeOwnedUntappedAndEachKeyUsedOnce(): void
    {
        $planner = $this->planner();
        $board = MoveBoards::board(
            position: 4,
            tableau: [MoveBoards::card(1, self::W), MoveBoards::tapped(2, self::W)],
            hand: [MoveBoards::card(3, self::W)],
        );

        $this->assertViolation(MoveViolation::BAD_CARD, $planner, $board, ['rooms' => [5], 'payments' => [[self::tab(9)]]]);
        $this->assertViolation(MoveViolation::BAD_CARD, $planner, $board, ['rooms' => [5], 'payments' => [[self::tab(2)]]]);
        // 手札のカードを TABLEAU として払うことはできない
        $this->assertViolation(MoveViolation::BAD_CARD, $planner, $board, ['rooms' => [5], 'payments' => [[self::tab(3)]]]);
        // 鍵の番号が範囲外
        $this->assertViolation(MoveViolation::BAD_CARD, $planner, $board, ['rooms' => [5], 'payments' => [[self::tab(1, 1)]]]);
        // 1 本しかない鍵を 2 部屋で使う
        $this->assertViolation(MoveViolation::BAD_CARD, $planner, $board, [
            'rooms' => [5, 6],
            'payments' => [[self::tab(1)], [self::tab(1)]],
        ]);
    }

    public function testHandCardsAreDiscardedAndCountedForKeyKinds(): void
    {
        $planner = $this->planner();
        $board = MoveBoards::board(
            position: 4,
            locks: [5 => self::W, 6 => self::F],
            hand: [MoveBoards::card(1, self::W), MoveBoards::card(2, self::F)],
            wildUp: 1,
        );
        $outcome = $planner->plan($board, [
            'rooms' => [5, 6],
            'payments' => [[self::hand(1)], [self::hand(2)]],
        ]);
        self::assertSame([1], $outcome->steps[0]->discarded);
        self::assertSame([2], $outcome->steps[1]->discarded);
        self::assertEqualsCanonicalizing([self::W, self::F], $outcome->keyKinds);

        // 万能移動鍵トークンは鍵の種類に数えず、トークンの数に数える
        $mixed = $planner->plan($board, [
            'rooms' => [5, 6],
            'payments' => [[self::TOKEN], [self::hand(2)]],
        ]);
        self::assertSame(1, $mixed->wildTokensUsed);
        self::assertSame([self::F], $mixed->keyKinds);
    }

    // ---- 複数の鍵を持つカード ----

    public function testMultiKeyCardCannotBeSplitAcrossNonConsecutiveRooms(): void
    {
        $planner = $this->planner();
        $board = MoveBoards::board(
            position: 5,
            tableau: [MoveBoards::card(1, self::W, self::W), MoveBoards::card(2, self::W)],
        );

        // 6 と 8 で同じカードの鍵を 1 つずつ使い、間の 7（レベル 2）を別のカードで払う: ✗
        $this->assertViolation(MoveViolation::SPLIT_CARD, $planner, $board, [
            'rooms' => [6, 7, 8],
            'payments' => [[self::tab(1, 0)], [self::tab(2), self::TOKEN], [self::tab(1, 1)]],
        ]);
    }

    public function testMultiKeyCardCanPayConsecutiveRoomsAndOneRoomAtOnce(): void
    {
        $planner = $this->planner();
        $board = MoveBoards::board(position: 5, tableau: [MoveBoards::card(1, self::W, self::W)], wildUp: 1);

        // 連続する 6 と 7 で、1 枚のカードの 2 本の鍵を 1 本ずつ使う: ✓（7 のもう 1 つの錠前は万能移動鍵トークン）
        $outcome = $planner->plan($board, [
            'rooms' => [6, 7],
            'payments' => [[self::tab(1, 0)], [self::tab(1, 1), self::TOKEN]],
        ]);
        self::assertSame([1], $outcome->steps[0]->tapped);
        self::assertSame([], $outcome->steps[1]->tapped, '横向きにするのは初めて使う入室だけ');

        // 1 つの部屋（レベル 2 の 7）の錠前 2 個を 1 枚の 2 鍵カードで払う: ✓
        $single = $planner->plan(
            MoveBoards::board(position: 6, tableau: [MoveBoards::card(1, self::W, self::W)]),
            ['rooms' => [7], 'payments' => [[self::tab(1, 0), self::tab(1, 1)]]],
        );
        self::assertSame([1], $single->steps[0]->tapped);
    }

    /** Q-07: 間にスキップしたレベル 2 の部屋があっても「連続」とみなす。 */
    public function testSkippedLevel2RoomBetweenCountsAsConsecutive(): void
    {
        $planner = $this->planner();
        $board = MoveBoards::board(position: 2, tableau: [MoveBoards::card(1, self::W, self::W)]);

        // 3 に入り、4（レベル 2）をスキップして 5 に入る。同じカードの 2 本の鍵を 3 と 5 で使う
        $outcome = $planner->plan($board, [
            'rooms' => [3, 5],
            'payments' => [[self::tab(1, 0)], [self::tab(1, 1)]],
        ]);
        self::assertSame(4, $outcome->steps[1]->skipped);
        self::assertSame([1], $outcome->steps[0]->tapped);
    }

    // ---- 心光 ----

    public function testLightCostsEscalateWithEachUseAcrossTheWholeMove(): void
    {
        $planner = $this->planner();
        $rooms = ['rooms' => [5, 6, 7], 'payments' => [[self::LIGHT], [self::LIGHT], [self::LIGHT, self::LIGHT]]];

        // 1 回目 1 個、2 回目 2 個、3 回目 3 個、4 回目 4 個 = 10 個（レベル 2 の 7 の周遊ボーナスは入室の後に得る）
        $enough = $planner->plan(MoveBoards::board(position: 4, lights: 10), $rooms);
        self::assertSame([1, 2, 7], array_map(static fn($s): int => $s->lightsPaid, $enough->steps));
        self::assertSame(4, $enough->lightUses);

        $this->assertViolation(MoveViolation::NOT_ENOUGH_RESOURCES, $planner, MoveBoards::board(position: 4, lights: 9), $rooms);
    }

    /** UT-17・UT-18: M063 は移動の心光の行使を 1・1・2・2・3 にする。 */
    public function testM063FlattensTheEscalationInMove(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M063']);
        $planner = $this->planner($kit);
        $input = ['rooms' => [5, 6, 7], 'payments' => [[self::LIGHT], [self::LIGHT], [self::LIGHT, self::LIGHT]]];

        $outcome = $planner->plan(MoveBoards::board(position: 4, lights: 6), $input);
        self::assertSame(6, array_sum(array_map(static fn($s): int => $s->lightsPaid, $outcome->steps)));
        $this->assertViolation(MoveViolation::NOT_ENOUGH_RESOURCES, $planner, MoveBoards::board(position: 4, lights: 5), $input);
    }

    public function testWildMoveKeyTokensAreLimitedToTheFaceUpOnes(): void
    {
        $planner = $this->planner();
        $input = ['rooms' => [5, 6], 'payments' => [[self::TOKEN], [self::TOKEN]]];

        self::assertSame(2, $planner->plan(MoveBoards::board(position: 4, wildUp: 2), $input)->wildTokensUsed);
        $this->assertViolation(MoveViolation::NOT_ENOUGH_RESOURCES, $planner, MoveBoards::board(position: 4, wildUp: 1), $input);
    }

    // ---- 周遊ボーナスで得た資源 ----

    /** UT-26・T8-5: 入室した部屋の周遊ボーナス（心光）を、その後の部屋の支払いに使える。 */
    public function testTourLightFromAnEnteredRoomPaysForTheNextRoom(): void
    {
        $planner = $this->planner();
        $input = ['rooms' => [4, 5], 'payments' => [[self::TOKEN, self::TOKEN], [self::LIGHT]]];

        self::assertTrue($planner->plan(MoveBoards::board(position: 3, wildUp: 2, lights: 0), $input)->moved());
        // ボーナスは入室の支払いの後なので、その部屋自身の支払いには使えない
        $own = ['rooms' => [4], 'payments' => [[self::TOKEN, self::LIGHT]]];
        $this->assertViolation(
            MoveViolation::NOT_ENOUGH_RESOURCES,
            $planner,
            MoveBoards::board(position: 3, wildUp: 1, lights: 0),
            $own,
        );
    }

    /** スキップした部屋の周遊ボーナス（万能移動鍵トークン）は、次の入室の支払いより先に得る。 */
    public function testSkippedRoomBonusIsGainedBeforePayingTheNextRoom(): void
    {
        $planner = $this->planner();
        $input = ['rooms' => [8], 'payments' => [[self::TOKEN]]];

        // 6 の次の 7 はレベル 2（万能移動鍵）をスキップして 8 へ。万能移動鍵が手元に無くても払える
        $outcome = $planner->plan(MoveBoards::board(position: 6, wildUp: 0), $input);
        self::assertSame(7, $outcome->steps[0]->skipped);
        self::assertSame(1, $outcome->wildTokensUsed);

        // スキップしなければ（7 に入るなら）その部屋のボーナスは入室の後
        $this->assertViolation(
            MoveViolation::NOT_ENOUGH_RESOURCES,
            $planner,
            MoveBoards::board(position: 6, wildUp: 0),
            ['rooms' => [7], 'payments' => [[self::TOKEN, self::TOKEN]]],
        );
    }

    public function testBonusFromTheSkippedRoomLightPaysTheNextRoom(): void
    {
        $planner = $this->planner();
        // 3 の次の 4 をスキップ（周遊ボーナス: 心光 1）して 5 へ。心光 1 個で払える
        $outcome = $planner->plan(MoveBoards::board(position: 3), ['rooms' => [5], 'payments' => [[self::LIGHT]]]);
        self::assertSame(1, $outcome->steps[0]->lightsPaid);
    }

    // ---- MD-06（錠前の軽減。Q-70） ----

    /** UT-25: M064〜M066 は、このターン最初のレベル 2 の部屋への入室の錠前の数を減らす（重ねて最大 -2 = 0 個）。 */
    public function testLockReductionOnlyForTheFirstLevel2EntryOfTheTurn(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M064']);
        $planner = $this->planner($kit);
        $board = MoveBoards::board(position: 3, lights: 30);

        // 最初のレベル 2 の部屋 4 は錠前 1 個。1 個の支払いで入れる
        $outcome = $planner->plan($board, ['rooms' => [4], 'payments' => [[self::LIGHT]]]);
        self::assertSame(1, $outcome->steps[0]->lockReduction);
        self::assertCount(1, $outcome->steps[0]->payments);
        // 2 個の支払いは錠前の数が合わない
        $this->assertViolation(MoveViolation::LOCK_COUNT, $planner, $board, ['rooms' => [4], 'payments' => [[self::LIGHT, self::LIGHT]]]);

        // 2 回目のレベル 2 の入室（7）は軽減されない（2 個）
        $outcome = $planner->plan($board, [
            'rooms' => [4, 5, 6, 7],
            'payments' => [[self::LIGHT], [self::LIGHT], [self::LIGHT], [self::LIGHT, self::LIGHT]],
        ]);
        self::assertSame([1, 0, 0, 0], array_map(static fn($step) => $step->lockReduction, $outcome->steps));
        // このターンにすでにレベル 2 の部屋に入室していれば軽減されない
        $this->assertViolation(
            MoveViolation::LOCK_COUNT,
            $planner,
            MoveBoards::board(position: 3, priorLv2Entries: 1),
            ['rooms' => [4], 'payments' => [[self::LIGHT]]],
        );
    }

    public function testThreeCardsReduceTheFirstLevel2RoomToNoLocks(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M064', 'M065', 'M066']);
        $planner = $this->planner($kit);
        $board = MoveBoards::board(position: 3);

        $outcome = $planner->plan($board, ['rooms' => [4], 'payments' => [[]]]);
        self::assertSame(2, $outcome->steps[0]->lockReduction);
        self::assertSame([], $outcome->steps[0]->payments);
        self::assertSame(0, $outcome->wildTokensUsed);
        self::assertSame([], $outcome->keyKinds);
        $this->assertViolation(MoveViolation::LOCK_COUNT, $planner, $board, ['rooms' => [4], 'payments' => [[self::LIGHT]]]);
    }

    /** 軽減後の錠前の数で canMove と zombie の自動の支払いが決まる。 */
    public function testCanMoveAndAutoPlanUseTheReducedLocks(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M064', 'M065']);
        $planner = $this->planner($kit);
        $board = MoveBoards::board(position: 3); // 心光もトークンも無い。部屋 4 は錠前 2 個 - 2 = 0 個
        self::assertTrue($planner->canMove($board));
        self::assertSame(['rooms' => [4], 'payments' => [[]]], $planner->autoPlan($board));

        $one = new QueryKit();
        $one->game->giveTableau(1, ['M064']);
        $planner = $this->planner($one);
        // 1 枚なら錠前は 1 個。心光 1 個で入れる（部屋 4 に入る。スキップはしない）
        self::assertSame(['rooms' => [4], 'payments' => [[self::LIGHT]]], $planner->autoPlan(MoveBoards::board(position: 3, lights: 1)));
    }

    public function testNoReductionWithoutTheCardsAndEffectPaymentIsGone(): void
    {
        $planner = $this->planner();
        $board = MoveBoards::board(position: 3, tableau: [MoveBoards::card(1, self::W)]);
        $this->assertViolation(MoveViolation::LOCK_COUNT, $planner, $board, ['rooms' => [4], 'payments' => [[self::LIGHT]]]);
        // 'EFFECT' の支払い手段は無い
        $this->assertViolation(MoveViolation::BAD_INPUT, $planner, $board, [
            'rooms' => [4],
            'payments' => [[['src' => 'EFFECT', 'card' => 1], self::LIGHT]],
        ]);
    }

    // ---- canMove / zombie ----

    public function testCanMoveCountsKeysTokensAndLightCosts(): void
    {
        $planner = $this->planner();
        // 次の 6 は錠前 1 個: 知恵鍵のカード 1 枚で払える
        self::assertTrue($planner->canMove(MoveBoards::board(position: 5, locks: [6 => self::W], hand: [MoveBoards::card(1, self::W)])));
        // 意志鍵では払えない
        self::assertFalse($planner->canMove(MoveBoards::board(position: 5, locks: [6 => self::W], hand: [MoveBoards::card(1, self::F)])));
        // 心光 1 個で足りる
        self::assertTrue($planner->canMove(MoveBoards::board(position: 5, lights: 1)));
        // 何も払えなければ動けない
        self::assertFalse($planner->canMove(MoveBoards::board(position: 5)));
        // 次がレベル 2（錠前 2 個、心光なら 1 + 2 = 3 個）でも、スキップして次の次（錠前 1 個）に入れる
        self::assertTrue($planner->canMove(MoveBoards::board(position: 9, lights: 1)));
    }

    public function testZombiePaysWithTokenThenTableauKeyThenLight(): void
    {
        $planner = $this->planner();

        $token = $planner->autoPlan(MoveBoards::board(position: 5, wildUp: 1, lights: 3, tableau: [MoveBoards::card(1, self::W)]));
        self::assertSame(['rooms' => [6], 'payments' => [[self::TOKEN]]], $token);

        $key = $planner->autoPlan(MoveBoards::board(position: 5, lights: 3, tableau: [MoveBoards::card(1, self::W)]));
        self::assertSame([[['src' => 'TABLEAU', 'card' => 1, 'key' => 0]]], $key['payments']);

        $light = $planner->autoPlan(MoveBoards::board(position: 5, lights: 3));
        self::assertSame([[self::LIGHT]], $light['payments']);

        // 手札は捨てない
        $none = $planner->autoPlan(MoveBoards::board(position: 5, hand: [MoveBoards::card(1, self::W)]));
        self::assertSame(['rooms' => [], 'payments' => []], $none);
    }

    public function testZombieDoesNotSkipALevel2RoomWhenItCanPayForIt(): void
    {
        $planner = $this->planner();
        $input = $planner->autoPlan(MoveBoards::board(position: 3, wildUp: 2));
        self::assertSame([4], $input['rooms']);
        self::assertSame([[self::TOKEN, self::TOKEN]], $input['payments']);
    }

    public function testZombieSkipsALevel2RoomItCannotPayForAndTriesTheNext(): void
    {
        $planner = $this->planner();
        // 4（錠前 2 個）は払えない。スキップして 5 に入る（スキップの周遊ボーナスは心光 1）
        $input = $planner->autoPlan(MoveBoards::board(position: 3, wildUp: 1));
        self::assertSame([5], $input['rooms']);
        self::assertSame([[self::TOKEN]], $input['payments']);

        // 次がレベル 1 で払えなければ移動しない
        self::assertSame([], $planner->autoPlan(MoveBoards::board(position: 5))['rooms']);
    }

    public function testZombieStayIsAllowedEvenIfAHandCardCouldHavePaid(): void
    {
        $planner = $this->planner();
        $board = MoveBoards::board(position: 5, hand: [MoveBoards::card(1, self::W)]);
        $outcome = $planner->plan($board, $planner->autoPlan($board), true);
        self::assertFalse($outcome->moved());
    }
}
