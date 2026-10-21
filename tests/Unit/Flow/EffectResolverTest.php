<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\ChoiceRequest;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;
use Bga\Games\IRevolveThereforeIAm\Material\Condition;
use Bga\Games\IRevolveThereforeIAm\Material\ConditionRef;
use Bga\Games\IRevolveThereforeIAm\Material\EffectCatalog;
use Bga\Games\IRevolveThereforeIAm\Material\EffectKind;
use Bga\Games\IRevolveThereforeIAm\Material\EffectLimit;
use Bga\Games\IRevolveThereforeIAm\Material\EffectNode;
use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\Mechanism;
use Bga\Games\IRevolveThereforeIAm\Material\Op;
use Bga\Games\IRevolveThereforeIAm\Material\Origin;
use Bga\Games\IRevolveThereforeIAm\Material\OwnerScope;
use Bga\Games\IRevolveThereforeIAm\Material\ResourceTouch;
use IRevolveTests\Support\ArrayCatalog;
use IRevolveTests\Support\MoveFixture;
use IRevolveTests\Support\TestSpecs;
use PHPUnit\Framework\TestCase;

/**
 * 効果解決スタック（70 + 71 + 72。仕様書 006 §7・§8、008 §6.3・§6.4・§6.9、UT-74〜UT-80）。
 */
final class EffectResolverTest extends TestCase
{
    private const P = 11;

    /**
     * @param array<string, scalar|null> $touches
     * @param list<ConditionRef> $conditions
     */
    private static function trigger(
        string $source,
        Op $op,
        string $touches,
        Event $event = Event::MOVE_ENDED,
        bool $optional = false,
        EffectLimit $limit = EffectLimit::PER_EVENT,
        array $conditions = [],
    ): EffectSpec {
        return new EffectSpec(
            id: $source . '.1',
            source: $source,
            mechanism: Mechanism::TRIGGER,
            kind: EffectKind::ACTIVATED,
            ownerScope: OwnerScope::OWNER,
            ops: [EffectNode::op($op, ['n' => 1, 'origin' => Origin::EFFECT])],
            events: [$event],
            conditions: $conditions,
            optional: $optional,
            limit: $limit,
            touchesOverride: ResourceTouch::parseList($touches),
        );
    }

    /**
     * @param list<EffectSpec> $extra
     */
    private static function fixture(array $extra = []): MoveFixture
    {
        return new MoveFixture(new ArrayCatalog([...array_values(EffectCatalog::all()), ...TestSpecs::all(), ...$extra]));
    }

    private static function lightEvent(): GameEvent
    {
        return new GameEvent(Event::MOVE_ENDED, self::P, ['rooms_entered' => 1]);
    }

    /** UT-75: 手札上限は単位の終わりに確認する。床アクションの途中（3 枚引いた直後）では捨てさせない。 */
    public function testHandLimitIsCheckedAtTheEndOfTheUnitNotInTheMiddle(): void
    {
        $fx = self::fixture();
        $fx->place(self::P, 3); // ATHENA: 3 枚引く → 1 枚捨てる
        $fx->giveHandAny(self::P, 8);
        $fx->kit->floor->start();

        $mid = $fx->kit->resolver->resolve();
        self::assertSame(Transition::CHOICE, $mid->transition, '11 枚になっていても、捨てるのは床アクションの 1 枚（PICK_DISCARD）が先');
        self::assertSame(11, $fx->memory->count(MemoryLocation::Hand, self::P));

        $hand = array_map(static fn ($c): int => $c->id, $fx->memory->inLoc(MemoryLocation::Hand, self::P));
        $fx->kit->choices->answer(self::P, ['cards' => [$hand[0]]]);
        $end = $fx->kit->resolver->resolve();

        self::assertSame(Transition::HAND_LIMIT, $end->transition);
        self::assertSame(self::P, $end->activePlayer);
        self::assertSame(2, $fx->handLimit->excess(self::P));

        // 超過分のうち 1 枚だけ捨てても、まだ超過しているので 72 のまま
        $fx->globals->setResume(StateId::CONSTRUCT);
        self::assertSame(Transition::HAND_LIMIT, $fx->kit->resolver->resolve()->transition);
        $over = $fx->handLimit->zombieChoice(self::P);
        $fx->handLimit->discardExcess(self::P, 'P11', $over);
        self::assertSame(Transition::RESUME_CONSTRUCT, $fx->kit->resolver->resolve()->transition);
    }

    /** ターンプレイヤーは心光への変換（フリーアクション）で超過を解消できる。解消したら 70 の再確認で先へ進む（006 §6.2、§8）。 */
    public function testFreeActionCanResolveTheExcessInsteadOfDiscarding(): void
    {
        $fx = self::fixture();
        $fx->giveHandAny(self::P, 10);
        $fx->kit->construct->end(self::P);

        $over = $fx->kit->resolver->resolve();
        self::assertSame(Transition::HAND_LIMIT, $over->transition);
        self::assertSame(2, $fx->handLimit->excess(self::P));

        $hand = array_map(static fn ($c): int => $c->id, $fx->memory->inLoc(MemoryLocation::Hand, self::P));
        $fx->kit->free->discardForLight(self::P, [$hand[0], $hand[1]]);

        self::assertSame(0, $fx->handLimit->excess(self::P));
        self::assertSame(1, $fx->players->light(self::P));
        self::assertSame(Transition::RESUME_CLEANUP, $fx->kit->resolver->resolve()->transition);
    }

    /** 手札上限を超えたのがターンプレイヤー以外なら、その人を 72 へ（ターンプレイヤーを先に、続いて席順）。 */
    public function testNonTurnPlayersAreAskedAfterTheTurnPlayer(): void
    {
        $fx = self::fixture();
        $fx->giveHandAny(33, 9);
        $fx->giveHandAny(22, 9);
        $fx->kit->construct->end(self::P);

        $first = $fx->kit->resolver->resolve();
        self::assertSame(22, $first->activePlayer);
        $fx->handLimit->discardExcess(22, 'P22', $fx->handLimit->zombieChoice(22));
        $second = $fx->kit->resolver->resolve();
        self::assertSame(33, $second->activePlayer);
    }

    /** 008 §4.2 の 4: 「してもよい」の誘発効果は、解決の番で CONFIRM_USE を聞く。使わないを選べば何も起きない。 */
    public function testOptionalTriggersAskForConfirmation(): void
    {
        $fx = self::fixture([self::trigger('M076', Op::DRAW_DECK, 'D+ H+', optional: true)]);
        $fx->giveTableau(self::P, [76]);
        $fx->kit->triggers->emit([self::lightEvent()]);

        $ask = $fx->kit->resolver->resolve();
        self::assertSame(Transition::CHOICE, $ask->transition);
        $args = $fx->kit->choices->args();
        self::assertSame('CONFIRM_USE', $args['public']['choiceKind']);
        self::assertSame('M076', $args['public']['source']);
        self::assertSame(0, $fx->memory->count(MemoryLocation::Hand, self::P));

        $fx->kit->choices->answer(self::P, ['use' => true]);
        $fx->globals->setResume(StateId::CONSTRUCT);
        self::assertSame(Transition::RESUME_CONSTRUCT, $fx->kit->resolver->resolve()->transition);
        self::assertSame(1, $fx->memory->count(MemoryLocation::Hand, self::P));

        $fx->kit->triggers->emit([self::lightEvent()]);
        $fx->kit->resolver->resolve();
        $fx->kit->choices->skip(self::P);
        self::assertSame(Transition::RESUME_CONSTRUCT, $fx->kit->resolver->resolve()->transition);
        self::assertSame(1, $fx->memory->count(MemoryLocation::Hand, self::P), '使わない');
    }

    /** UT-73 / 006 §7.5: 衝突する効果は、ターンプレイヤーが次に解決するものを選ぶ。選んだものが先に解決される。 */
    public function testConflictingTriggersAreOrderedByTheTurnPlayer(): void
    {
        $fx = self::fixture([
            self::trigger('M073', Op::DRAW_DECK, 'D+ H+'),
            self::trigger('M074', Op::GAIN_LIGHT, 'Hw L+'),
        ]);
        $fx->giveTableau(self::P, [73, 74]);
        $fx->kit->triggers->emit([self::lightEvent()]);

        $ask = $fx->kit->resolver->resolve();

        self::assertSame(Transition::CHOICE, $ask->transition);
        $args = $fx->kit->choices->args();
        self::assertSame('ORDER_EFFECTS', $args['public']['choiceKind']);
        self::assertSame([['spec' => 'M073.1', 'source' => 'M073'], ['spec' => 'M074.1', 'source' => 'M074']], $args['public']['data']['candidates']);

        try {
            $fx->kit->choices->answer(self::P, ['index' => 5]);
            self::fail('unknown index');
        } catch (EffectViolation) {
            self::addToAssertionCount(1);
        }
        $fx->kit->choices->answer(self::P, ['index' => 1]); // M074（心光）を先に
        $fx->globals->setResume(StateId::CONSTRUCT);
        $fx->kit->resolver->resolve();

        self::assertSame(1, $fx->players->light(self::P));
        self::assertSame(1, $fx->memory->count(MemoryLocation::Hand, self::P));
    }

    /** 衝突しない効果は、順序を聞かずに固定順（効果 ID の昇順）で自動に解決する。 */
    public function testNonConflictingTriggersResolveAutomatically(): void
    {
        $fx = self::fixture([
            self::trigger('M076', Op::GAIN_LIGHT, 'L+'),
            self::trigger('M073', Op::DRAW_DECK, 'D+ H+'),
        ]);
        $fx->giveTableau(self::P, [76, 73]);
        $fx->kit->triggers->emit([self::lightEvent()]);

        self::assertSame(['SPEC_RUN', 'SPEC_RUN'], array_map(static fn (EffectFrame $f): string => $f->kind, $fx->stack->all()));
        self::assertSame(['M073.1', 'M076.1'], array_map(static fn (EffectFrame $f): ?string => $f->spec, $fx->stack->all()));
        $fx->globals->setResume(StateId::CONSTRUCT);
        self::assertSame(Transition::RESUME_CONSTRUCT, $fx->kit->resolver->resolve()->transition);
        self::assertSame(1, $fx->players->light(self::P));
        self::assertSame(1, $fx->memory->count(MemoryLocation::Hand, self::P));
    }

    /** 008 §4.2 の 4: 解決の開始時に条件をもう一度評価する。満たさなくなっていたら捨てる。1 ターン 1 回の効果は使用済みにする。 */
    public function testConditionsAreEvaluatedAgainWhenResolutionStartsAndOnceIsMarked(): void
    {
        $once = self::trigger(
            'M067',
            Op::GAIN_LIGHT,
            'L+',
            Event::MOVE_ENDED,
            limit: EffectLimit::ONCE_PER_TURN,
            conditions: [ConditionRef::of(Condition::ROOMS_ENTERED_GE, ['n' => 4])],
        );
        $fx = self::fixture([$once]);
        $fx->giveTableau(self::P, [67]);
        $ctx = $fx->globals->turnContext();
        $ctx->roomsEnteredCount = 4;
        $fx->globals->setTurnContext($ctx);
        $fx->kit->triggers->emit([new GameEvent(Event::MOVE_ENDED, self::P, [])]);
        self::assertCount(1, $fx->stack->all());

        // 解決の前に条件が崩れた
        $ctx->roomsEnteredCount = 3;
        $fx->globals->setTurnContext($ctx);
        $fx->globals->setResume(StateId::CONSTRUCT);
        $fx->kit->resolver->resolve();
        self::assertSame(0, $fx->players->light(self::P));

        $ctx->roomsEnteredCount = 5;
        $fx->globals->setTurnContext($ctx);
        $fx->kit->triggers->emit([new GameEvent(Event::MOVE_ENDED, self::P, [])]);
        $fx->kit->resolver->resolve();
        self::assertSame(1, $fx->players->light(self::P));
        self::assertTrue($fx->view->isOnceUsed(self::P, 'M067.1'));
        $fx->kit->triggers->emit([new GameEvent(Event::MOVE_ENDED, self::P, [])]);
        self::assertSame(0, $fx->stack->count(), '使用済みなら誘発しない');
    }

    /** 006 §7.3 / UT-78: 暴走（永久に誘発し続ける効果）は、1 回の入場で処理する frame の上限で例外にする。 */
    public function testRunawayEffectsStopWithAnException(): void
    {
        $loop = self::trigger('M076', Op::GAIN_LIGHT, 'L+', Event::LIGHT_GAINED);
        $fx = self::fixture([$loop]);
        $fx->giveTableau(self::P, [76]);
        $fx->kit->triggers->emit([new GameEvent(Event::LIGHT_GAINED, self::P, ['count' => 1])]);
        $fx->globals->setResume(StateId::CONSTRUCT);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('runaway');
        $fx->kit->resolver->resolve();
    }

    /** 勝利が成立したら、スタックを捨てて終局へ。 */
    public function testGameEndClearsTheStack(): void
    {
        $fx = self::fixture();
        $fx->kit->construct->end(self::P);
        $fx->kit->mutator->win(self::P, 'APEX_OF_PSYCHE');

        $result = $fx->kit->resolver->resolve();

        self::assertSame(Transition::GAME_END, $result->transition);
        self::assertSame(0, $fx->stack->count());
    }

    /** 戻り先（resume）が未設定なら、不正として例外にする。 */
    public function testEmptyStackReturnsToTheResumeState(): void
    {
        $fx = self::fixture();
        $fx->globals->setResume(StateId::MOVE);
        self::assertSame(Transition::RESUME_MOVE, $fx->kit->resolver->resolve()->transition);
        $fx->globals->setResume(0);
        $this->expectException(\LogicException::class);
        $fx->kit->resolver->resolve();
    }

    /** UT-79: 時間切れ（zombie）の既定の答え。スキップ可の種類はスキップ、必須の選択は種類ごとの既定。 */
    public function testDefaultAnswersFollowTheTable(): void
    {
        $fx = self::fixture();
        $field = array_map(static fn ($c): int => $c->id, $fx->memory->inLocAnyArg(MemoryLocation::Open));
        $hand = $fx->giveHand(self::P, [1, 85, 2, 3]); // レベル 1 が 3 枚
        $choices = $fx->kit->choices;
        $frame = static fn (): EffectFrame => new EffectFrame(EffectFrame::KIND_SPEC_RUN, self::P);
        $default = static fn (ChoiceKind|null $kind, array $data = []): ?array => $choices->defaultAnswer(
            $frame(),
            new ChoiceRequest($kind, self::P, 0, $data),
            self::P,
        );

        self::assertSame(['cards' => array_slice($field, 0, 2)], $default(ChoiceKind::PICK_FIELD_CARD, ['count' => 2, 'candidates' => $field]));
        $level1 = [$hand[0], $hand[2], $hand[3]];
        rsort($level1);
        self::assertSame(['cards' => array_slice($level1, 0, 2)], $default(ChoiceKind::PICK_DISCARD, ['count' => 2]), 'レベル 1 を優先して手札の後ろから');
        self::assertSame(['ego' => 'E01'], $default(ChoiceKind::PICK_ENHANCED_EGO));
        self::assertSame(['spirit' => 'R3A'], $default(ChoiceKind::PICK_SPIRIT_BRANCH, ['candidates' => ['R3B', 'R3A']]));
        self::assertSame(['branch' => 0], $default(null, ['branches' => 2]));
        $skippable = [
            ChoiceKind::CONFIRM_USE, ChoiceKind::SELECT_DISCARD_FOR_LIGHT, ChoiceKind::SELECT_SWAP_HAND_FIELD,
            ChoiceKind::SELECT_SWAP_SAME_COST, ChoiceKind::CONFIRM_MOVE, ChoiceKind::SWAP_ROOM_CARDS,
            ChoiceKind::SWAP_ROOM_FLOORS, ChoiceKind::WARP_ROOM, ChoiceKind::ENTREPRENEUR_SWAP,
        ];
        foreach ($skippable as $kind) {
            self::assertNull($default($kind), $kind->value);
            self::assertTrue($kind->skippable(), $kind->value);
        }
        $order = new EffectFrame(EffectFrame::KIND_ORDER_EFFECTS, self::P);
        self::assertSame(
            ['index' => 1],
            $choices->defaultAnswer(
                $order,
                new ChoiceRequest(ChoiceKind::ORDER_EFFECTS, self::P, 0, ['candidates' => [['spec' => 'R2'], ['spec' => 'M081.1']]]),
                self::P,
            ),
        );
    }

    /** SELECT_SWAP_SAME_COST の候補には手札のカード ID が入るので、公開の args には出さず、選択者の private args にだけ入れる。 */
    public function testSwapCandidatesAreOnlyInPrivateArgs(): void
    {
        $fx = self::fixture();
        $fx->place(self::P, 2);
        $fx->roomCard(2, 23); // M023 SWAP_SAME_COST
        $fx->giveTableau(self::P, [5]); // エネルギー 2
        [$hand] = $fx->giveHand(self::P, [8]); // エネルギー 2、別名
        $fx->kit->recall->start();
        $ask = $fx->kit->resolver->resolve();

        self::assertSame(Transition::CHOICE, $ask->transition);
        $args = $fx->kit->choices->args();
        self::assertArrayNotHasKey('pairs', $args['public']['data']);
        self::assertSame($hand, $args['private']['pairs'][0][1]);
        self::assertTrue($args['public']['skippable']);

        $fx->kit->choices->skip(self::P);
        self::assertSame(Transition::RESUME_FLOOR, $fx->kit->resolver->resolve()->transition);
    }

    /** 同じプレイヤーが続けて選択者になれる（選択は frame ごと。006 §7.4）。確認 → 場札の選択の連続。 */
    public function testOnePlayerCanHoldConsecutiveChoices(): void
    {
        $fx = self::fixture();
        $fx->place(self::P, 2);
        $fx->roomCard(2, 33);
        $fx->kit->recall->start();

        $first = $fx->kit->resolver->resolve();
        self::assertSame('CONFIRM_MOVE', $fx->kit->choices->args()['public']['choiceKind']);
        $fx->kit->choices->answer(self::P, ['use' => true]);
        $second = $fx->kit->resolver->resolve();

        self::assertSame($first->activePlayer, $second->activePlayer);
        self::assertSame('PICK_FIELD_CARD', $fx->kit->choices->args()['public']['choiceKind']);
    }
}
