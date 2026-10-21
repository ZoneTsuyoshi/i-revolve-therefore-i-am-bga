<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Effects\Ops;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\ActionAvailability;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectEngine;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\EngineResult;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpRegistry;
use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;
use Bga\Games\IRevolveThereforeIAm\Material\ComposeContext;
use Bga\Games\IRevolveThereforeIAm\Material\EffectKind;
use Bga\Games\IRevolveThereforeIAm\Material\EffectNode;
use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;
use Bga\Games\IRevolveThereforeIAm\Material\FloorBonus;
use Bga\Games\IRevolveThereforeIAm\Material\Mechanism;
use Bga\Games\IRevolveThereforeIAm\Material\Op;
use Bga\Games\IRevolveThereforeIAm\Material\OwnerScope;
use Bga\Games\IRevolveThereforeIAm\Material\RecallGroup;
use IRevolveTests\Support\ArrayCatalog;
use IRevolveTests\Support\ArrayGame;
use PHPUnit\Framework\TestCase;

/**
 * 自己構成・精神分析・移動に関わる原子操作 OP-10〜OP-15・OP-25 と、エンジンの行動の要求（008 §3.1、§11.2 の順 4）。
 */
final class ActionOpsTest extends TestCase
{
    private ArrayGame $game;

    protected function setUp(): void
    {
        $this->game = new ArrayGame();
    }

    /**
     * @param list<EffectNode> $ops
     */
    private function engine(array $ops, ?ActionAvailability $availability = null): EffectEngine
    {
        $spec = new EffectSpec('T.1', 'T', Mechanism::ACTION, EffectKind::NONE, OwnerScope::TURN_PLAYER, $ops);

        return new EffectEngine($this->game, $this->game, new ArrayCatalog([$spec]), new OpRegistry(), $availability);
    }

    /**
     * @param list<EffectNode> $ops
     * @return array{EffectEngine, EffectFrame, EngineResult}
     */
    private function begin(array $ops, ?ActionAvailability $availability = null): array
    {
        $engine = $this->engine($ops, $availability);
        $frame = $engine->start('T.1', 1);

        return [$engine, $frame, $engine->run($frame)];
    }

    private static function availability(bool $compose, bool $analyze): ActionAvailability
    {
        return new class ($compose, $analyze) implements ActionAvailability {
            public function __construct(private readonly bool $compose, private readonly bool $analyze)
            {
            }

            public function canCompose(int $player, ComposeContext $context, int $discount): bool
            {
                return $this->compose;
            }

            public function canAnalyze(int $player, int $discount): bool
            {
                return $this->analyze;
            }
        };
    }

    // ---- OP-10 / OP-11 ----

    public function testComposeWaitsForTheActionAndContinuesAfterItIsDone(): void
    {
        [$engine, $frame, $result] = $this->begin([
            EffectNode::optional(EffectNode::op(Op::COMPOSE, ['ctx' => ComposeContext::FLOOR, 'discount' => 1])),
            EffectNode::op(Op::GAIN_LIGHT, ['n' => 1]),
        ]);

        self::assertTrue($result->choice?->isAction());
        self::assertNull($result->choice->kind);
        self::assertSame(['ctx' => 'FLOOR', 'discount' => 1, 'action' => 'OP-10'], $result->choice->data);
        self::assertFalse($result->isDone());
        self::assertSame([], $this->game->lights);

        $done = $engine->completeAction($frame);

        self::assertTrue($done->isDone());
        self::assertSame(1, $this->game->lights[1]);
    }

    public function testCancelingTheOptionalActionSkipsToTheRest(): void
    {
        [$engine, $frame] = $this->begin([
            EffectNode::optional(EffectNode::op(Op::ANALYZE, ['ctx' => 'FLOOR'])),
            EffectNode::op(Op::GAIN_LIGHT, ['n' => 1]),
        ]);

        $done = $engine->cancelAction($frame);

        self::assertTrue($done->isDone());
        self::assertSame(1, $this->game->lights[1]);
        self::assertSame([0 => 0], $frame->decisions);
    }

    public function testAnActionThatIsNotOptionalCannotBeCancelled(): void
    {
        [$engine, $frame] = $this->begin([EffectNode::op(Op::ANALYZE, ['ctx' => 'TOKEN'])]);

        $this->expectException(EffectViolation::class);
        $engine->cancelAction($frame);
    }

    public function testActionMethodsRequireAPendingAction(): void
    {
        [$engine, $frame] = $this->begin([EffectNode::op(Op::GAIN_LIGHT, ['n' => 1])]);

        foreach ([$engine->completeAction(...), $engine->cancelAction(...)] as $call) {
            try {
                $call($frame);
                self::fail('no action is pending');
            } catch (EffectViolation) {
                self::addToAssertionCount(1);
            }
        }
    }

    /** 行える状況でなければ、61・62 には出ず飛ばす（自己構成できるカードが無い、コストを払えない）。 */
    public function testUnavailableActionsAreSkipped(): void
    {
        [, , $result] = $this->begin([EffectNode::optional(EffectNode::op(Op::COMPOSE, ['ctx' => ComposeContext::BONUS_LV1]))], self::availability(false, true));
        self::assertTrue($result->isDone());

        [, , $result] = $this->begin([EffectNode::optional(EffectNode::op(Op::ANALYZE, ['ctx' => 'FLOOR']))], self::availability(true, false));
        self::assertTrue($result->isDone());

        [, , $result] = $this->begin([EffectNode::optional(EffectNode::op(Op::ANALYZE, ['ctx' => 'FLOOR']))], self::availability(false, true));
        self::assertTrue($result->choice?->isAction());
    }

    public function testActionPendingSurvivesTheDatabaseRoundTrip(): void
    {
        [, $frame, $result] = $this->begin([EffectNode::optional(EffectNode::op(Op::COMPOSE, ['ctx' => ComposeContext::PHASE]))]);

        $restored = EffectFrame::fromArray($frame->toArray());

        self::assertEquals($result->choice, $restored->pending);
        self::assertTrue($restored->pending?->isAction());
    }

    public function testActionsCannotBeSkippedOrAnsweredAsChoices(): void
    {
        [$engine, $frame] = $this->begin([EffectNode::optional(EffectNode::op(Op::COMPOSE, ['ctx' => ComposeContext::PHASE]))]);

        foreach ([static fn () => $engine->skip($frame), static fn () => $engine->answer($frame, [])] as $call) {
            try {
                $call();
                self::fail('an action is pending');
            } catch (EffectViolation) {
                self::addToAssertionCount(1);
            }
        }
    }

    // ---- 分岐の事前選択（51） ----

    public function testSelectBranchSkipsTheQuestion(): void
    {
        $engine = new EffectEngine($this->game, $this->game, ArrayCatalog::withTestSpecs());
        self::assertSame(2, $engine->branchCount('F09'));
        self::assertSame(0, $engine->branchCount('F01'));
        $frame = $engine->start('F09', 1);
        $engine->selectBranch($frame, 1);

        $result = $engine->run($frame);

        self::assertTrue($result->isDone());
        self::assertSame(2, $this->game->lights[1]);
        try {
            $engine->selectBranch($engine->start('F09', 1), 2);
            self::fail('unknown branch');
        } catch (EffectViolation) {
            self::addToAssertionCount(1);
        }
        $this->expectException(EffectViolation::class);
        $engine->selectBranch($engine->start('F01', 1), 0);
    }

    // ---- OP-25 と積む操作の後の停止 ----

    public function testRecallActivateQueuesTheActivationAndYields(): void
    {
        [$engine, $frame, $result] = $this->begin([
            EffectNode::op(Op::RECALL_ACTIVATE, ['card' => 'M001', 'times' => 2, 'groups' => [RecallGroup::DRAW, RecallGroup::GAIN]]),
            EffectNode::op(Op::GAIN_LIGHT, ['n' => 1]),
        ]);

        self::assertTrue($result->yielded);
        self::assertFalse($result->isDone());
        self::assertSame([[1, 'M001', 2, [RecallGroup::DRAW, RecallGroup::GAIN]]], $this->game->recallPushes);
        self::assertSame([], $this->game->lights, '積んだ frame を先に解決するため、続きはまだ');
        self::assertSame(1, $frame->step);

        self::assertTrue($engine->run($frame)->isDone());
        self::assertSame(1, $this->game->lights[1]);
    }

    public function testRecallActivateAsTheLastStepDoesNotYield(): void
    {
        [, , $result] = $this->begin([EffectNode::op(Op::RECALL_ACTIVATE, ['card' => 'M001', 'times' => 1])]);

        self::assertTrue($result->isDone());
        self::assertCount(1, $this->game->recallPushes);
    }

    /** M071: `card` に EVENT_CARD を指定すると、誘発したイベントの card_type の想起効果を使う。 */
    public function testRecallActivateCanUseTheComposedCardOfTheEvent(): void
    {
        $spec = new EffectSpec('T.1', 'T', Mechanism::ACTION, EffectKind::NONE, OwnerScope::TURN_PLAYER, [
            EffectNode::op(Op::RECALL_ACTIVATE, ['card' => 'EVENT_CARD', 'times' => 1]),
        ]);
        $engine = new EffectEngine($this->game, $this->game, new ArrayCatalog([$spec]));
        $frame = $engine->start('T.1', 1, 'M071', ['card_type' => 'M013']);

        $engine->run($frame);

        self::assertSame([[1, 'M013', 1, []]], $this->game->recallPushes);
    }

    // ---- OP-12 ----

    public function testAdvanceTierAsksForTheBranchWhenThereAreSeveral(): void
    {
        $this->game->spirit[1] = 'R2';
        $this->game->spiritLights['R3B'] = true;
        [$engine, $frame, $result] = $this->begin([EffectNode::op(Op::ADVANCE_TIER, ['via' => 'EFFECT', 'max_tier' => 4])]);

        self::assertSame(ChoiceKind::PICK_SPIRIT_BRANCH, $result->choice?->kind);
        self::assertSame(['R3A', 'R3B', 'R3C'], $result->choice->data['candidates']);
        self::assertFalse($result->choice->kind->skippable());

        try {
            $engine->answer($frame, ['spirit' => 'R1A']);
            self::fail('not on the next tier');
        } catch (EffectViolation) {
            self::addToAssertionCount(1);
        }
        $done = $engine->answer($frame, ['spirit' => 'R3B']);

        self::assertTrue($done->isDone());
        self::assertSame([[1, 'R3B', 'EFFECT']], $this->game->advances);
        // EV-06 の誘発は advanceSpirit（SpiritArrival）が解決スタックに積むので、操作はイベントを返さない
        self::assertSame([], $done->events);
    }

    public function testAdvanceTierWithASingleNextCardDoesNotAsk(): void
    {
        $this->game->spirit[1] = 'R1A';
        [, , $result] = $this->begin([EffectNode::op(Op::ADVANCE_TIER, ['via' => 'EFFECT', 'max_tier' => 4])]);

        self::assertTrue($result->isDone());
        self::assertSame([[1, 'R2', 'EFFECT']], $this->game->advances);
        self::assertSame(1, $this->game->victoryChecks);
    }

    /** M088 は第五階層に上げられない（max_tier = 4）。 */
    public function testAdvanceTierStopsAtTheMaxTier(): void
    {
        $this->game->spirit[1] = 'R4';
        [, , $result] = $this->begin([EffectNode::op(Op::ADVANCE_TIER, ['via' => 'EFFECT', 'max_tier' => 4])]);

        self::assertTrue($result->isDone());
        self::assertSame([], $this->game->advances);
        self::assertSame([], $result->events);
    }

    // ---- OP-13 / OP-14 ----

    public function testGainEnhancedEgo(): void
    {
        [$engine, $frame, $result] = $this->begin([EffectNode::op(Op::GAIN_ENHANCED_EGO)]);
        self::assertTrue($result->isDone(), '山が空なら飛ばす');

        $this->game->enhancedDeck = ['E03'];
        [, , $result] = $this->begin([EffectNode::op(Op::GAIN_ENHANCED_EGO)]);
        self::assertTrue($result->isDone(), '1 枚だけなら選ばずに取る');
        self::assertSame(['E03'], $this->game->egoIds(1));

        $this->game->enhancedDeck = ['E01', 'E02', 'E04'];
        [$engine, $frame, $result] = $this->begin([EffectNode::op(Op::GAIN_ENHANCED_EGO)]);
        self::assertSame(ChoiceKind::PICK_ENHANCED_EGO, $result->choice?->kind);
        self::assertSame([], $result->choice->data, '候補は公開の選択に含めない（71 の private args）');
        foreach ([['ego' => 'E03'], [], ['ego' => 5]] as $bad) {
            try {
                $engine->answer($frame, $bad);
                self::fail('not in the deck');
            } catch (EffectViolation) {
                self::addToAssertionCount(1);
            }
        }
        self::assertTrue($engine->answer($frame, ['ego' => 'E02'])->isDone());
        self::assertSame(['E01', 'E04'], $this->game->enhancedDeck);
        self::assertSame(['E03', 'E02'], $this->game->egoIds(1));
    }

    public function testWinRecordsTheReason(): void
    {
        [, , $result] = $this->begin([EffectNode::op(Op::WIN, ['kind' => 'APEX_OF_PSYCHE']), EffectNode::op(Op::GAIN_LIGHT, ['n' => 1])]);

        self::assertTrue($result->isDone());
        self::assertSame([1, 'APEX_OF_PSYCHE'], $this->game->won);
        self::assertSame([], $this->game->lights, '勝利が確定したら、続きは解決しない');
    }

    // ---- OP-15 ----

    public function testMoveNextRoomLevel1(): void
    {
        $this->game->persona = [1 => 2, 2 => 3];
        [, , $result] = $this->begin([EffectNode::op(Op::MOVE_NEXT_ROOM, ['level' => 1])]);

        self::assertSame([[1, 3, true]], $this->game->moves);
        self::assertSame([], $this->game->bonuses);
        self::assertSame('PERSONA_STOPPED', $result->events[0]->event->name);
        self::assertSame(['room' => 3, 'via' => 'RECALL_MOVE', 'other_persona' => true], $result->events[0]->payload);
    }

    /** 通過したレベル 2 の部屋の周遊ボーナス（スキップ扱い）を得る。12 の次は 1。 */
    public function testMoveNextRoomPassesLevel2RoomsAndWrapsAround(): void
    {
        $this->game->persona = [1 => 3];
        $this->begin([EffectNode::op(Op::MOVE_NEXT_ROOM, ['level' => 1])]);
        self::assertSame([[1, 5, true]], $this->game->moves);
        self::assertSame([[1, FloorBonus::LIGHT]], $this->game->bonuses); // 4 の F04

        $game = new ArrayGame();
        $game->persona = [1 => 12];
        $engine = new EffectEngine($game, $game, new ArrayCatalog([new EffectSpec(
            'T.1',
            'T',
            Mechanism::ACTION,
            EffectKind::NONE,
            OwnerScope::TURN_PLAYER,
            [EffectNode::op(Op::MOVE_NEXT_ROOM, ['level' => 1])],
        )]));
        $engine->run($engine->start('T.1', 1));
        self::assertSame([[1, 2, true]], $game->moves);
        self::assertSame([[1, FloorBonus::ANALYSIS_TOKEN]], $game->bonuses); // 1 の F01
    }

    /** レベル 2 への移動（M096）は、到着した部屋の周遊ボーナスも得る（入室）。レベル 1 の部屋は通過してよい。 */
    public function testMoveNextRoomLevel2ArrivesAtTheCornerAndGetsItsBonus(): void
    {
        $this->game->persona = [1 => 2];
        [, , $result] = $this->begin([EffectNode::op(Op::MOVE_NEXT_ROOM, ['level' => 2])]);

        self::assertSame([[1, 4, true]], $this->game->moves);
        self::assertSame([[1, FloorBonus::LIGHT]], $this->game->bonuses);
        self::assertSame(4, $result->events[0]->payload['room']);
    }

    public function testOnlyTheTurnPlayerMoves(): void
    {
        $this->game->persona = [2 => 2];
        $engine = $this->engine([EffectNode::op(Op::MOVE_NEXT_ROOM, ['level' => 1])]);

        $engine->run($engine->start('T.1', 2));

        self::assertSame([], $this->game->moves);
    }
}
