<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Flow\ResolveResult;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use IRevolveTests\Support\MoveFixture;
use PHPUnit\Framework\TestCase;

/**
 * 床アクション（50・51 + 70・71。仕様書 006 §5.3、008 §7.4、UT-62・UT-66）。部屋 N の床は F0N（固定配置）。
 */
final class FloorActionPhaseTest extends TestCase
{
    private MoveFixture $fx;

    protected function setUp(): void
    {
        $this->fx = new MoveFixture();
    }

    private function enter(int $pos): void
    {
        $this->fx->place(11, $pos);
    }

    private function resolve(): ResolveResult
    {
        return $this->fx->kit->resolver->resolve();
    }

    /**
     * @return list<int> 場札のカード ID
     */
    private function field(): array
    {
        return array_map(static fn ($c): int => $c->id, $this->fx->memory->inLocAnyArg(MemoryLocation::Open));
    }

    /** 床アクションを実行する（51 を経由しない床）。 */
    private function startOnly(): void
    {
        $result = $this->fx->kit->floor->start();
        self::assertFalse($result['choose']);
    }

    /** UT-62: DEMETER は山札から 3 枚。手札上限は床アクション全体の後。 */
    public function testDemeterDrawsThreeAndChecksTheHandLimitAfterTheWholeAction(): void
    {
        $this->enter(1);
        $this->fx->giveHandAny(11, 6);
        $this->startOnly();

        $result = $this->resolve();

        self::assertSame(9, $this->fx->memory->count(MemoryLocation::Hand, 11));
        self::assertSame(Transition::HAND_LIMIT, $result->transition);
        self::assertSame(11, $result->activePlayer);
        self::assertSame(StateId::CONSTRUCT, $this->fx->globals->resume());
    }

    /** UT-62: ATHENA・APOLLO は 3 枚引いてから 1 枚捨てる。捨ては必須（スキップ不可）。 */
    public function testAthenaAndApolloDiscardIsMandatory(): void
    {
        foreach ([3, 8] as $pos) {
            $fx = new MoveFixture();
            $fx->place(11, $pos);
            $fx->kit->floor->start();
            $first = $fx->kit->resolver->resolve();

            self::assertSame(Transition::CHOICE, $first->transition, "floor $pos");
            self::assertSame(3, $fx->memory->count(MemoryLocation::Hand, 11));
            $args = $fx->kit->choices->args();
            self::assertSame('PICK_DISCARD', $args['public']['choiceKind']);
            self::assertFalse($args['public']['skippable']);
            try {
                $fx->kit->choices->skip(11);
                self::fail('the discard cannot be skipped');
            } catch (EffectViolation) {
                self::addToAssertionCount(1);
            }
            $hand = array_map(static fn ($c): int => $c->id, $fx->memory->inLoc(MemoryLocation::Hand, 11));
            $fx->kit->choices->answer(11, ['cards' => [$hand[1]]]);
            $done = $fx->kit->resolver->resolve();

            self::assertSame(Transition::RESUME_CONSTRUCT, $done->transition);
            self::assertSame(2, $fx->memory->count(MemoryLocation::Hand, 11));
        }
    }

    /** UT-62: ARES・ARTEMIS は場札 1 枚 → 心光 1（左から右）。HESTIA は場札 → 山札 1 枚 → 心光。 */
    public function testFieldThenLightInOrder(): void
    {
        foreach ([5 => 0, 11 => 0, 7 => 1] as $pos => $draws) {
            $fx = new MoveFixture();
            $fx->place(11, $pos);
            $field = array_map(static fn ($c): int => $c->id, $fx->memory->inLocAnyArg(MemoryLocation::Open));
            $fx->kit->floor->start();
            $first = $fx->kit->resolver->resolve();

            self::assertSame(Transition::CHOICE, $first->transition, "floor $pos");
            self::assertSame(0, $fx->players->light(11), '場札を選ぶ前に心光は増えない');
            $fx->kit->choices->answer(11, ['cards' => [$field[3]]]);
            $done = $fx->kit->resolver->resolve();

            self::assertSame(Transition::RESUME_CONSTRUCT, $done->transition);
            self::assertSame(1, $fx->players->light(11));
            self::assertSame(1 + $draws, $fx->memory->count(MemoryLocation::Hand, 11));
        }
    }

    /** UT-62: APHRODITE は「場札 2 枚 / 心光 2」の分岐。51 で選ぶ（FLOOR_A / FLOOR_B）。 */
    public function testAphroditeBranches(): void
    {
        $this->enter(9);
        $start = $this->fx->kit->floor->start();
        self::assertTrue($start['choose']);
        self::assertSame(['FLOOR_A', 'FLOOR_B'], array_column($this->fx->kit->floor->options(), 'kind'));

        $this->fx->kit->floor->choose('FLOOR_B');
        $done = $this->resolve();
        self::assertSame(Transition::RESUME_CONSTRUCT, $done->transition);
        self::assertSame(2, $this->fx->players->light(11));
        self::assertSame(0, $this->fx->memory->count(MemoryLocation::Hand, 11));

        $fx = new MoveFixture();
        $fx->place(11, 9);
        $field = array_map(static fn ($c): int => $c->id, $fx->memory->inLocAnyArg(MemoryLocation::Open));
        $fx->kit->floor->start();
        $fx->kit->floor->choose('FLOOR_A');
        $pick = $fx->kit->resolver->resolve();
        self::assertSame(Transition::CHOICE, $pick->transition);
        self::assertSame(2, $fx->kit->choices->args()['public']['data']['count']);
        $fx->kit->choices->answer(11, ['cards' => [$field[0], $field[5]]]);
        self::assertSame(Transition::RESUME_CONSTRUCT, $fx->kit->resolver->resolve()->transition);
        self::assertSame(2, $fx->memory->count(MemoryLocation::Hand, 11));
        self::assertSame(0, $fx->players->light(11));
    }

    /** UT-62: HEPHAESTUS は「自己構成（エネルギー -1）/ 山札から 2 枚」。引く分岐。 */
    public function testHephaestusDrawBranch(): void
    {
        $this->enter(12);
        $this->fx->kit->floor->start();
        $this->fx->kit->floor->choose('FLOOR_B');

        self::assertSame(Transition::RESUME_CONSTRUCT, $this->resolve()->transition);
        self::assertSame(2, $this->fx->memory->count(MemoryLocation::Hand, 11));
    }

    /** UT-62: HEPHAESTUS の自己構成の分岐は 61 へ（文脈 FLOOR、割引 1）。手札が無ければ飛ばす。 */
    public function testHephaestusComposeBranchEntersTheComposeState(): void
    {
        $this->enter(12);
        $this->fx->giveHand(11, [1, 2, 3]);
        $this->fx->kit->floor->start();
        $this->fx->kit->floor->choose('FLOOR_A');

        $result = $this->resolve();

        self::assertSame(Transition::COMPOSE, $result->transition);
        self::assertSame(11, $result->activePlayer);
        $args = $this->fx->kit->compose->args(11);
        self::assertSame('FLOOR', $args['public']['context']);
        self::assertSame(1, $args['public']['discount']);
        // 行わない（「してもよい」）と、床アクションは終わる
        $this->fx->kit->compose->cancel(11);
        self::assertSame(Transition::RESUME_CONSTRUCT, $this->resolve()->transition);

        $fx = new MoveFixture();
        $fx->place(11, 12);
        $fx->kit->floor->start();
        $fx->kit->floor->choose('FLOOR_A');
        self::assertSame(Transition::RESUME_CONSTRUCT, $fx->kit->resolver->resolve()->transition);
    }

    /** UT-62: POSEIDON の軽減は、自己構成を行わなくても以降の自己構成アクションに効く。 */
    public function testPoseidonDiscountRemainsEvenIfTheComposeIsSkipped(): void
    {
        $this->enter(4);
        $this->fx->giveHand(11, [1, 2]);
        $this->startOnly();

        $result = $this->resolve();
        self::assertSame(Transition::COMPOSE, $result->transition);
        self::assertSame(1, $this->fx->globals->turnContext()->turnComposeDiscount);
        $this->fx->kit->compose->cancel(11);
        self::assertSame(Transition::RESUME_CONSTRUCT, $this->resolve()->transition);
        self::assertSame(1, $this->fx->globals->turnContext()->turnComposeDiscount);
    }

    /** UT-62: HADES は精神分析を行わなくても心光 +1。精神分析は 62 で行わない選択ができる（Q-11）。 */
    public function testHadesGivesLightEvenWithoutAnAnalysis(): void
    {
        $this->enter(10);
        $this->fx->giveHand(11, [1, 4]); // 知恵鍵 2 つで R1A（知恵 ×2）を払える
        $this->startOnly();

        $ask = $this->resolve();
        self::assertSame(Transition::ANALYSIS, $ask->transition);
        $args = $this->fx->kit->analysis->args(11);
        self::assertSame('FLOOR', $args['public']['context']);
        self::assertTrue($args['public']['canAnalyze']);
        $this->fx->kit->analysis->cancel(11);

        $done = $this->resolve();
        self::assertSame(Transition::RESUME_CONSTRUCT, $done->transition);
        self::assertSame(1, $this->fx->players->light(11));
        self::assertSame(2, $this->fx->memory->count(MemoryLocation::Hand, 11), '手札は捨てていない');
    }

    /** コストを払えないなら、精神分析の床アクションは 62 に出ず飛ばされる（HERMES・DIONYSUS）。 */
    public function testAnalysisFloorsAreSkippedWhenNothingCanBePaid(): void
    {
        foreach ([2, 6] as $pos) {
            $fx = new MoveFixture();
            $fx->place(11, $pos);
            $fx->kit->floor->start();

            self::assertSame(Transition::RESUME_CONSTRUCT, $fx->kit->resolver->resolve()->transition, "floor $pos");
        }
    }

    public function testHermesAsksForTheAnalysisWhenItCanBePaid(): void
    {
        $this->enter(2);
        $this->fx->giveHand(11, [1, 4]);
        $this->startOnly();

        self::assertSame(Transition::ANALYSIS, $this->resolve()->transition);
    }

    /** UT-54 / UT-66: 想起の代替は選択肢に加わる。選ぶと 62 / 61 へ。取り消すと 50 から選び直せる（何も消費しない）。 */
    public function testAlternativeIsAnOptionAndCancelReturnsToTheFloorStart(): void
    {
        $fx = $this->fx;
        $this->enter(1);
        $fx->giveHand(11, [1, 4]);
        $fx->roomCard(1, 31); // M031 ALT_ANALYSIS
        $fx->kit->recall->start();
        self::assertSame(Transition::RESUME_FLOOR, $this->resolve()->transition);

        $start = $fx->kit->floor->start();
        self::assertTrue($start['choose']);
        self::assertSame(['FLOOR', 'ALT_ANALYSIS'], array_column($fx->kit->floor->options(), 'kind'));
        $fx->kit->floor->choose('ALT_ANALYSIS');

        $ask = $this->resolve();
        self::assertSame(Transition::ANALYSIS, $ask->transition);
        $fx->kit->analysis->cancel(11);

        self::assertSame(0, $fx->stack->count());
        self::assertSame(StateId::FLOOR_ACTION_START, $fx->globals->resume());
        self::assertTrue($fx->kit->floor->start()['choose'], '選び直せる');
    }

    /** 代替の割引は、そのまま 61・62 の文脈に伝わる（RC-11 の自己構成 -1）。 */
    public function testAlternativeDiscountIsPassedToTheAction(): void
    {
        $fx = $this->fx;
        $this->enter(1);
        $fx->giveHand(11, [1, 4]);
        $fx->roomCard(1, 95); // M095 ALT_COMPOSE_DISCOUNT_1
        $fx->kit->recall->start();
        $this->resolve();
        $fx->kit->floor->start();
        $fx->kit->floor->choose('ALT_COMPOSE');

        $ask = $this->resolve();

        self::assertSame(Transition::COMPOSE, $ask->transition);
        self::assertSame(1, $fx->kit->compose->args(11)['public']['discount']);
    }

    public function testUnknownOptionIsRejected(): void
    {
        $this->enter(1);
        $this->fx->kit->floor->start();

        $this->expectException(EffectViolation::class);
        $this->fx->kit->floor->choose('ALT_COMPOSE');
    }

    /** 想起で移動したあとは、移動先の部屋の床アクション（FAQ 行 8）。 */
    public function testFloorActionIsTheCurrentRoomsAfterARecallMove(): void
    {
        $fx = $this->fx;
        $this->enter(2);
        $fx->roomCard(2, 33); // MOVE_NEXT_LV1 → 3（ATHENA）
        $fx->kit->recall->start();
        $this->resolve();
        $fx->kit->choices->answer(11, ['use' => true]);
        $this->resolve();
        $field = $this->field();
        $fx->kit->choices->answer(11, ['cards' => [$field[0]]]);
        self::assertSame(Transition::RESUME_FLOOR, $this->resolve()->transition);

        self::assertSame(3, $fx->kit->floor->floorNumber());
        $fx->kit->floor->start();
        self::assertSame(Transition::CHOICE, $this->resolve()->transition);
        self::assertSame('PICK_DISCARD', $fx->kit->choices->args()['public']['choiceKind']);
    }

    /** 12 室すべてが、最後まで解決でき（選択は既定の答え、行動は取り消し）、戻り先は 60 になる。 */
    public function testAllTwelveFloorsResolve(): void
    {
        for ($pos = 1; $pos <= 12; $pos++) {
            $fx = new MoveFixture();
            $fx->place(11, $pos);
            $fx->giveHand(11, [1, 4, 2]);
            $start = $fx->kit->floor->start();
            if ($start['choose']) {
                $fx->kit->floor->choose('');
            }
            $guard = 0;
            do {
                $result = $fx->kit->resolver->resolve();
                if ($result->transition === Transition::CHOICE) {
                    $fx->kit->choices->auto($result->activePlayer);
                } elseif ($result->transition === Transition::COMPOSE) {
                    $fx->kit->compose->cancel($result->activePlayer);
                } elseif ($result->transition === Transition::ANALYSIS) {
                    $fx->kit->analysis->cancel($result->activePlayer);
                }
            } while ($result->transition !== Transition::RESUME_CONSTRUCT && ++$guard < 10);

            self::assertSame(Transition::RESUME_CONSTRUCT, $result->transition, "floor $pos");
            self::assertSame(0, $fx->stack->count(), "floor $pos");
        }
    }
}
