<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Flow\MoveResult;
use Bga\Games\IRevolveThereforeIAm\Flow\MoveViolation;
use Bga\Games\IRevolveThereforeIAm\Flow\Notice;
use Bga\Games\IRevolveThereforeIAm\Material\EffectCatalog;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\FloorBonus;
use Bga\Games\IRevolveThereforeIAm\Material\FloorCards;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\Op;
use Bga\Games\IRevolveThereforeIAm\Material\TokenKind;
use IRevolveTests\Support\MoveFixture;
use PHPUnit\Framework\TestCase;

/**
 * 移動フェイズの適用（`MovePhase` = 盤面の読み込み + 検証 + DB への適用）。SQLite 上の 3 人のゲームで確かめる（仕様書 006 §5.1、007 §10）。
 */
final class MovePhaseTest extends TestCase
{
    private const P = 11;
    private const TOKEN = ['src' => 'TOKEN'];
    private const LIGHT = ['src' => 'LIGHT'];

    private MoveFixture $fx;

    protected function setUp(): void
    {
        $this->fx = new MoveFixture();
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function move(array $plan, bool $zombie = false): MoveResult
    {
        return $this->fx->phase->submit(self::P, 'Alice', $plan, $zombie);
    }

    /**
     * @return list<string>
     */
    private static function types(MoveResult $result): array
    {
        return array_map(static fn(Notice $n): string => $n->type, $result->notices);
    }

    /**
     * @return list<Notice>
     */
    private static function ofType(MoveResult $result, string $type): array
    {
        return array_values(array_filter($result->notices, static fn(Notice $n): bool => $n->type === $type));
    }

    /**
     * 位置 `$from` から時計回りに全部屋へ入室するための、万能移動鍵トークンだけの経路。
     *
     * @return array<string, mixed>
     */
    private function tokenRoute(int $from, int $count): array
    {
        $rooms = [];
        $payments = [];
        $pos = $from;
        for ($i = 0; $i < $count; $i++) {
            $pos = $pos % 12 + 1;
            $rooms[] = $pos;
            $payments[] = in_array($pos, [1, 4, 7, 10], true) ? [self::TOKEN, self::TOKEN] : [self::TOKEN];
        }

        return ['rooms' => $rooms, 'payments' => $payments];
    }

    // ---- 適用 ----

    public function testMoveTapsCardsDiscardsHandAndSpendsTokens(): void
    {
        $fx = $this->fx;
        $fx->place(self::P, 5);
        $fx->lock(6, KeyType::WISDOM);
        $fx->lock(7, KeyType::WILL);
        $fx->lock(8, KeyType::EMOTION);
        [$tab] = $fx->giveTableau(self::P, [1]);   // M001（知恵）
        [$hand] = $fx->giveHand(self::P, [3]);     // M003（意志）
        $fx->tokens(self::P, light: 5, wildUp: 1);

        $result = $this->move([
            'rooms' => [6, 7, 8],
            'payments' => [
                [['src' => 'TABLEAU', 'card' => $tab, 'key' => 0]],
                [['src' => 'HAND', 'card' => $hand, 'key' => 0], self::TOKEN],
                [self::LIGHT],
            ],
        ]);

        self::assertFalse($result->ended);
        self::assertSame(8, $fx->players->persona(self::P));
        self::assertTrue($fx->memory->find($tab)?->tapped);
        self::assertSame(MemoryLocation::Discard1, $fx->memory->find($hand)?->location);
        self::assertSame(4, $fx->players->light(self::P), '心光 5 から 1 回目の行使で 1 個');
        // 万能移動鍵: 1 → 使って 0（裏 1）→ レベル 2 の 7 の周遊ボーナスで表が 1
        self::assertSame(1, $fx->players->wildUp(self::P));
        self::assertSame(1, $fx->players->wildUsed(self::P));

        self::assertSame([
            'cardsTapped', 'roomEntered',
            'cardsDiscarded', 'tokensChanged', 'roomEntered', 'tokensChanged',
            'tokensChanged', 'roomEntered',
        ], self::types($result));

        $entered = self::ofType($result, 'roomEntered');
        self::assertSame(['from_pos' => 6, 'pos' => 7, 'step' => 2, 'entered_count' => 2], array_intersect_key(
            $entered[1]->args,
            ['from_pos' => 0, 'pos' => 0, 'step' => 0, 'entered_count' => 0],
        ));
        self::assertSame('HESTIA', $entered[1]->args['room_name']);
        self::assertSame(
            ['tapped' => [], 'discarded' => [['id' => $hand, 'type' => 3]], 'light' => 0, 'wild_tokens' => 1, 'granted_keys' => 0],
            $entered[1]->args['payment'],
        );

        $ctx = $fx->globals->turnContext();
        self::assertSame([6, 7, 8], $ctx->enteredRooms);
        self::assertSame(3, $ctx->roomsEnteredCount);
        self::assertSame(1, $ctx->lv2EntriesCount);
        self::assertSame(3, $ctx->moveSteps);
        self::assertEqualsCanonicalizing(['WISDOM', 'WILL'], $ctx->moveSummary['key_kinds_paid']);
        self::assertSame(1, $ctx->moveSummary['wild_tokens_used']);
        self::assertSame(1, $ctx->moveSummary['light_spent']);
    }

    public function testNothingChangesWhenTheInputIsRejected(): void
    {
        $fx = $this->fx;
        $fx->place(self::P, 5);
        $fx->tokens(self::P, light: 0, wildUp: 1);

        try {
            $this->move($this->tokenRoute(5, 2));
            self::fail('expected a violation');
        } catch (MoveViolation $e) {
            self::assertSame(MoveViolation::NOT_ENOUGH_RESOURCES, $e->reason);
        }
        self::assertSame(5, $fx->players->persona(self::P));
        self::assertSame(1, $fx->players->wildUp(self::P));
        self::assertSame([], $fx->globals->turnContext()->enteredRooms);
    }

    /** UT-63・Q-26: 周遊ボーナスは入室でもスキップでも、そのたびに得る。スキップのボーナスは次の入室の支払いに使える。 */
    public function testTourBonusesAreGainedOnSkipAndPayForTheNextRooms(): void
    {
        $fx = $this->fx;
        $fx->place(self::P, 3);
        $fx->tokens(self::P, light: 3);

        // 3 → 4 をスキップ（心光 +1 = 4）→ 5 を心光 1 個 → 6 を心光 2 個（2 回目の行使）
        $result = $this->move(['rooms' => [5, 6], 'payments' => [[self::LIGHT], [self::LIGHT]]]);

        self::assertSame(
            ['roomSkipped', 'tokensChanged', 'tokensChanged', 'roomEntered', 'tokensChanged', 'roomEntered'],
            self::types($result),
        );
        self::assertSame('TOUR_BONUS', $result->notices[1]->args['reason']);
        self::assertSame('TOUR', $result->notices[1]->args['origin']);
        self::assertSame(['light' => 1], $result->notices[1]->args['delta']);
        self::assertSame('PAYMENT', $result->notices[2]->args['reason']);
        self::assertSame('', $result->notices[2]->message, '支払いのトークンの増減はログに出さない');
        self::assertSame(1, $fx->players->light(self::P));

        $ctx = $fx->globals->turnContext();
        self::assertSame([4], $ctx->skippedRooms);
        self::assertSame([5, 6], $ctx->enteredRooms, 'スキップは入室に数えない');
        self::assertSame(0, $ctx->lv2EntriesCount);
        self::assertSame(1, $ctx->lightsGained);
        self::assertSame(3, $ctx->moveSteps, 'スキップ 1 + 入室 2。スキップも 1 つ進んだと数える');
    }

    public function testEntryBonusesOfTheFourCornerRooms(): void
    {
        $fx = $this->fx;
        $fx->tokens(self::P, wildUp: 8);

        $fx->place(self::P, 2);
        $this->move($this->tokenRoute(2, 2)); // 3, 4（心光）
        self::assertSame(1, $fx->players->light(self::P));
        self::assertSame(1, $fx->globals->turnContext()->lv2EntriesCount);
        self::assertSame(1, $fx->globals->turnContext()->lightsGained);

        $fx->place(self::P, 9);
        $this->move($this->tokenRoute(9, 1)); // 10（自己構成トークン）
        self::assertSame(1, $fx->players->composeTokens(self::P));

        $fx->place(self::P, 12);
        $this->move($this->tokenRoute(12, 1)); // 1（精神分析トークン）
        self::assertSame(1, $fx->players->analysisTokens(self::P));
    }

    /** 周遊ボーナスの種類は、床カードのデータ（002）と効果カタログ（008 §7.4）で一致している。 */
    public function testTourBonusesMatchTheEffectCatalog(): void
    {
        $expected = [
            'F01' => [TokenKind::ANALYSIS, FloorBonus::ANALYSIS_TOKEN],
            'F07' => [TokenKind::WILD_MOVE_KEY, FloorBonus::WILD_MOVE_KEY],
            'F10' => [TokenKind::COMPOSE, FloorBonus::COMPOSE_TOKEN],
        ];
        foreach ($expected as $floor => [$token, $bonus]) {
            $op = EffectCatalog::tourBonus($floor)?->ops[0];
            self::assertSame(Op::GAIN_TOKEN, $op?->atomicOp());
            self::assertSame($token, $op->params['token']);
            self::assertSame($bonus, FloorCards::get($floor)->bonus);
        }
        self::assertSame(Op::GAIN_LIGHT, EffectCatalog::tourBonus('F04')?->ops[0]->atomicOp());
        self::assertSame(FloorBonus::LIGHT, FloorCards::get('F04')->bonus);
    }

    // ---- 刹那の追憶 ----

    /** 001 §14-1: 開始時の部屋は数えない。1 周して 12 部屋に入室した瞬間に勝利し、残りの経路は適用しない（Q-09）。 */
    public function testEnteringAllTwelveRoomsWinsImmediately(): void
    {
        $fx = $this->fx;
        $fx->place(self::P, 2);
        $fx->tokens(self::P, wildUp: 30);

        // 2 から 3, 4, ..., 12, 1, 2（12 部屋目 = 開始時の部屋に再入室）、さらに 3 に進む入力
        $result = $this->move($this->tokenRoute(2, 13));

        self::assertTrue($result->ended);
        self::assertSame('INSTANT_MEMORY', $fx->globals->endReason());
        self::assertSame(self::P, $fx->globals->winnerId());
        self::assertSame(2, $fx->players->persona(self::P), '12 部屋目で止まり、13 部屋目は適用しない');
        self::assertCount(12, self::ofType($result, 'roomEntered'));
        self::assertSame([], $result->events, '勝利したときは移動終了のトリガーを起こさない');
        self::assertSame(12, count($fx->globals->turnContext()->enteredRooms));
        self::assertSame(0, $fx->stack->count());
    }

    public function testStartingRoomAloneDoesNotCountAndElevenRoomsAreNotEnough(): void
    {
        $fx = $this->fx;
        $fx->place(self::P, 1);
        $fx->tokens(self::P, wildUp: 30);

        $result = $this->move($this->tokenRoute(1, 11)); // 2〜12
        self::assertFalse($result->ended);
        self::assertNull($fx->globals->endReason());
        self::assertNotContains(1, $fx->globals->turnContext()->enteredRooms);

        // 1 周して 1 に入室すれば 12 部屋（同じターンの続きとして再度 31 に入った想定）
        $result = $this->move($this->tokenRoute(12, 1));
        self::assertTrue($result->ended);
    }

    public function testSkippedLevel2RoomsMustBeEnteredForTheInstantMemory(): void
    {
        $fx = $this->fx;
        $fx->place(self::P, 12);
        $fx->tokens(self::P, light: 100, wildUp: 30);

        // 12 → 1 をスキップ → 2, 3, 4（スキップ）, 5, ..., 12 では、スキップした 1・4・7・10 が入室に数えられない
        $rooms = [];
        $payments = [];
        $pos = 12;
        while (count($rooms) < 20) {
            $next = $pos % 12 + 1;
            $skip = in_array($next, [1, 4, 7, 10], true);
            $pos = $skip ? $next % 12 + 1 : $next;
            $rooms[] = $pos;
            $payments[] = [self::TOKEN];
            if ($pos === 12 && count($rooms) > 6) {
                break;
            }
        }
        $result = $this->move(['rooms' => $rooms, 'payments' => $payments]);
        self::assertFalse($result->ended);
        self::assertSame([2, 3, 5, 6, 8, 9, 11, 12], array_values(array_unique($fx->globals->turnContext()->enteredRooms)));
    }

    // ---- Q-38 ----

    public function testStayingPutWhenNothingIsAffordable(): void
    {
        $fx = $this->fx;
        $fx->place(self::P, 5);
        $fx->giveHand(self::P, [1]);   // 知恵鍵の札はあるが、6 の錠前（既定の配置の想い出カード）に合うとは限らない
        $fx->lock(6, KeyType::WILL);

        $result = $this->move(['rooms' => [], 'payments' => []]);

        self::assertSame(['moveBlocked'], self::types($result));
        self::assertSame(5, $fx->players->persona(self::P));
        self::assertFalse($result->ended);
        self::assertSame([Event::MOVE_ENDED], array_map(static fn($e): Event => $e->event, $result->events));
        self::assertSame(0, $result->events[0]->payload['rooms_entered']);
        self::assertSame([], $fx->globals->turnContext()->enteredRooms);
    }

    public function testCannotStayWhileAMoveIsAffordable(): void
    {
        $fx = $this->fx;
        $fx->place(self::P, 5);
        $fx->tokens(self::P, light: 1);

        $this->expectException(MoveViolation::class);
        $this->move(['rooms' => [], 'payments' => []]);
    }

    // ---- 移動終了のイベント ----

    public function testMoveEndedEventCarriesTheSummaryAndPassingOthersIsReported(): void
    {
        $fx = $this->fx;
        $fx->place(self::P, 5);
        $fx->place(22, 6);   // 途中の部屋に他の人格
        $fx->place(33, 8);   // 止まる部屋に他の人格
        $fx->lock(6, KeyType::WISDOM);
        $fx->lock(8, KeyType::WILL);
        [$tab] = $fx->giveTableau(self::P, [1]);
        $fx->tokens(self::P, wildUp: 1);

        $result = $this->move([
            'rooms' => [6, 8],
            'payments' => [[['src' => 'TABLEAU', 'card' => $tab, 'key' => 0]], [self::TOKEN]],
        ]);

        // 7（レベル 2）は 6 の次なのでスキップ
        self::assertSame(
            [Event::PERSONA_PASSED_OTHER, Event::MOVE_ENDED, Event::PERSONA_STOPPED],
            array_map(static fn($e): Event => $e->event, $result->events),
        );
        self::assertSame(['room' => 6], $result->events[0]->payload);
        self::assertSame(2, $result->events[1]->payload['rooms_entered']);
        self::assertSame(1, $result->events[1]->payload['wild_tokens_used']);
        self::assertSame('WISDOM', $result->events[1]->payload['key_kinds']);
        self::assertTrue($result->events[1]->payload['other_persona']);
        self::assertSame(8, $result->events[2]->payload['room']);
    }

    public function testSkippedRoomOccupiedByAnotherPersonaIsNotPassing(): void
    {
        $fx = $this->fx;
        $fx->place(self::P, 6);
        $fx->place(22, 7);   // スキップする 7 にいる（Q-63: 通過に含まない）
        $fx->tokens(self::P, wildUp: 1);

        $result = $this->move(['rooms' => [8], 'payments' => [[self::TOKEN]]]);
        self::assertSame(
            [Event::MOVE_ENDED, Event::PERSONA_STOPPED],
            array_map(static fn($e): Event => $e->event, $result->events),
        );
    }

    // ---- 手札上限 ----

    /** Q-41: 移動中に手札が増えても、移動フェイズ全体の後に確認する。上限以内なら何も積まない。 */
    public function testHandLimitCheckIsPushedOnlyWhenSomeoneIsOverTheLimit(): void
    {
        $fx = $this->fx;
        $fx->place(self::P, 5);
        $fx->tokens(self::P, wildUp: 2);
        $fx->giveHandAny(self::P, 8);
        $this->move($this->tokenRoute(5, 1));
        self::assertSame(0, $fx->stack->count());

        $fx->place(self::P, 5);
        $fx->giveHandAny(self::P, 1);   // 9 枚（I07 のドローで増えた想定）
        $this->move($this->tokenRoute(5, 1));
        $head = $fx->stack->peek();
        self::assertSame(EffectFrame::KIND_HAND_LIMIT_CHECK, $head?->kind);
        self::assertTrue($head->unit);
        self::assertSame(self::P, $head->owner);
    }

    // ---- MD-06 ----

    public function testGrantedKeyMarksTheCardEffectAsUsedThisTurn(): void
    {
        $fx = $this->fx;
        $fx->place(self::P, 3);
        [$m064] = $fx->giveTableau(self::P, [64]);
        $fx->tokens(self::P, wildUp: 1);

        $result = $this->move([
            'rooms' => [4],
            'payments' => [[['src' => 'EFFECT', 'card' => $m064], self::TOKEN]],
        ]);

        $ctx = $fx->globals->turnContext();
        self::assertTrue($ctx->isOnceUsed(self::P, 'M064.1'));
        self::assertSame(1, $ctx->lv2EntriesCount);
        self::assertSame(1, $ctx->moveSummary['wild_tokens_used']);
        self::assertSame(1, self::ofType($result, 'roomEntered')[0]->args['payment']['granted_keys']);
        self::assertSame([], $ctx->moveSummary['key_kinds_paid']);
        // FAQ 行 22: M064〜M066 は万能移動鍵トークンを増やさない（使った 1 つが裏になっただけ）
        self::assertSame(0, $fx->players->wildUp(self::P));
        self::assertSame(1, $fx->players->wildUsed(self::P));
    }

    // ---- zombie ----

    public function testZombiePlanMovesOneRoomWithoutDiscardingTheHand(): void
    {
        $fx = $this->fx;
        $fx->place(self::P, 5);
        $fx->lock(6, KeyType::WISDOM);
        [$tab] = $fx->giveTableau(self::P, [1]);
        $fx->giveHand(self::P, [2, 3]);

        $result = $this->move($fx->phase->zombiePlan(self::P), true);
        self::assertSame(6, $fx->players->persona(self::P));
        self::assertTrue($fx->memory->find($tab)?->tapped);
        self::assertSame(2, $fx->memory->count(MemoryLocation::Hand, self::P));
        self::assertFalse($result->ended);
    }

    public function testZombieStaysPutWhenOnlyHandCardsCouldPay(): void
    {
        $fx = $this->fx;
        $fx->place(self::P, 5);
        $fx->lock(6, KeyType::WISDOM);
        $fx->giveHand(self::P, [1]);

        $result = $this->move($fx->phase->zombiePlan(self::P), true);
        self::assertSame(['moveBlocked'], self::types($result));
        self::assertSame(5, $fx->players->persona(self::P));
        self::assertSame(1, $fx->memory->count(MemoryLocation::Hand, self::P));
    }

    // ---- args ----

    public function testPlanningDataIsEnoughToPlanARoute(): void
    {
        $fx = $this->fx;
        $fx->place(self::P, 3);
        $fx->tokens(self::P, light: 4, wildUp: 2);
        $fx->giveTableau(self::P, [96]);   // M096: 意志鍵を万能鍵として使える
        $fx->giveHand(self::P, [82]);

        $data = $fx->phase->planningData(self::P);
        $public = $data['public'];

        self::assertSame(3, $public['position']);
        self::assertCount(12, $public['rooms']);
        self::assertSame(['pos' => 4, 'level' => 2, 'lock' => $public['rooms'][3]['lock'], 'locks' => 2, 'bonus' => 'LIGHT'], $public['rooms'][3]);
        self::assertSame(['light' => 4, 'wildUp' => 2], array_intersect_key($public['resources'], ['light' => 0, 'wildUp' => 0]));
        self::assertSame([1, 2, 3, 4], array_slice($public['resources']['lightCosts'], 0, 4));
        self::assertTrue($public['canMove']);
        self::assertSame(24, $public['maxRooms']);
        self::assertSame(['WISDOM', 'WILL', 'EMOTION'], $public['keyServes']['WILD']);
        self::assertSame(['WISDOM'], $public['keyServes']['WISDOM']);
        self::assertSame(['WISDOM', 'WILL', 'EMOTION'], $public['keyServes']['WILL'], 'M096: 意志鍵は万能鍵としても使える');
        self::assertCount(1, $public['tableau']);
        self::assertSame(['WILL', 'WILL'], $public['tableau'][0]['keys']);
        self::assertSame(['WISDOM', 'EMOTION'], $data['hand'][0]['keys']);
        self::assertArrayNotHasKey('hand', $public, '手札は公開部分に入れない');
    }
}
