<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Cards;

use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use IRevolveTests\Support\CardBench;
use PHPUnit\Framework\TestCase;

/**
 * 自己構成・精神分析・床アクション・移動に絡むカード効果（008 §7.2・§7.6。UT-37〜UT-40、UT-44、UT-45、UT-48〜UT-49、UT-58、UT-24）。
 */
final class ActionCardsTest extends TestCase
{
    private const P = CardBench::P;

    private CardBench $b;

    protected function setUp(): void
    {
        $this->b = new CardBench();
    }

    /** 自己構成フェイズの自己構成を始めて 61 に入る。 */
    private function beginCompose(): void
    {
        $this->b->fx->kit->construct->startPhaseCompose(self::P);
        self::assertSame(Transition::COMPOSE, $this->b->fx->kit->resolver->resolve()->transition);
    }

    // ---- I08（MD-08） ----

    /** UT-49: I08 は手札を 2 枚まで追加で払い、自己構成したカードの下に裏向きで置く。 */
    public function testProtagonistPaysExtraCardsUnderTheComposedCard(): void
    {
        $this->b->ego('I08');
        [$target, $pay1, $pay2, $extra1, $extra2] = $this->b->fx->giveHand(self::P, [1, 2, 3, 4, 5]);
        $this->beginCompose();

        $notices = $this->b->fx->kit->compose->compose(self::P, $target, ['discard' => [$pay1, $pay2], 'extra' => [$extra1, $extra2]]);

        self::assertSame(MemoryLocation::Tableau, $this->b->fx->memory->find($target)?->location);
        foreach ([$extra1, $extra2] as $id) {
            self::assertSame(MemoryLocation::UnderCard, $this->b->fx->memory->find($id)?->location);
            self::assertSame($target, $this->b->fx->memory->find($id)?->locationArg);
        }
        $types = array_map(static fn ($n): string => $n->type, $notices);
        self::assertLessThan(array_search('cardsAttached', $types, true), array_search('cardComposed', $types, true), '自己構成の後に下へ置く');
        $attached = $notices[array_search('cardsAttached', $types, true)];
        self::assertSame($target, $attached->args['host_id']);
        self::assertSame(2, $attached->args['count']);
        self::assertSame([$extra1, $extra2], array_column($attached->args['_private'][self::P]['cards'], 'id'));
        self::assertArrayNotHasKey('cards', $attached->args, '他人には枚数だけ');
    }

    /** UT-49: 3 枚は払えない。I08 が無ければ追加の支払いは拒否される。 */
    public function testExtraPaymentIsLimitedToTwoAndNeedsTheEgo(): void
    {
        $this->b->ego('I08');
        [$target, $pay1, $pay2, $e1, $e2, $e3] = $this->b->fx->giveHand(self::P, [1, 2, 3, 4, 5, 6]);
        $this->beginCompose();
        try {
            $this->b->fx->kit->compose->compose(self::P, $target, ['discard' => [$pay1, $pay2], 'extra' => [$e1, $e2, $e3]]);
            self::fail('at most two');
        } catch (EffectViolation) {
            self::addToAssertionCount(1);
        }
        try {
            $this->b->fx->kit->compose->compose(self::P, $target, ['discard' => [$pay1, $pay2], 'extra' => [$pay1]]);
            self::fail('the same card cannot be paid twice');
        } catch (EffectViolation) {
            self::addToAssertionCount(1);
        }

        $other = new CardBench();
        [$t, $p1, $p2, $x] = $other->fx->giveHand(self::P, [1, 2, 3, 4]);
        $other->fx->kit->construct->startPhaseCompose(self::P);
        $other->fx->kit->resolver->resolve();
        $this->expectException(EffectViolation::class);
        $other->fx->kit->compose->compose(self::P, $t, ['discard' => [$p1, $p2], 'extra' => [$x]]);
    }

    /** UT-49（FAQ 行 38）: 精神分析ボーナスの無料の自己構成でも追加で払える。 */
    public function testExtraPaymentAlsoWorksInBonusComposition(): void
    {
        $this->b->ego('I08');
        [$target, $extra] = $this->b->fx->giveHand(self::P, [1, 2]);
        $this->b->fx->stack->pushFront([EffectFrame::action(EffectFrame::KIND_COMPOSE, self::P, 'BONUS_LV1', 0)]);
        self::assertSame(Transition::COMPOSE, $this->b->fx->kit->resolver->resolve()->transition);

        $this->b->fx->kit->compose->compose(self::P, $target, ['extra' => [$extra]]);

        self::assertSame(MemoryLocation::UnderCard, $this->b->fx->memory->find($extra)?->location);
    }

    // ---- I03（MD-09） ----

    /** UT-44: I03 は自己構成済みを捨札にして、エネルギーが 2 まで大きい手札のカードをエネルギーなしで自己構成する。捨てる処理が先。 */
    public function testArchitectSubstitutes(): void
    {
        $this->b->ego('I03', 'I01');
        [$old] = $this->b->fx->giveTableau(self::P, [1]);                  // M001（エネルギー 2）
        [$under] = $this->b->fx->giveHand(self::P, [2]);
        $this->b->fx->memory->move($under, MemoryLocation::UnderCard, $old);   // I08 の下のカード
        [$new, $tooBig] = $this->b->fx->giveHand(self::P, [73, 85]);          // M073（4）・M085（5）
        $this->beginCompose();

        try {
            $this->b->fx->kit->compose->architect(self::P, $old, $tooBig);
            self::fail('energy 5 is more than 2 above 2');
        } catch (EffectViolation) {
            self::addToAssertionCount(1);
        }
        $notices = $this->b->fx->kit->compose->architect(self::P, $old, $new);

        self::assertSame(MemoryLocation::Discard1, $this->b->fx->memory->find($old)?->location);
        self::assertSame(MemoryLocation::Discard1, $this->b->fx->memory->find($under)?->location, '下のカードも捨札へ');
        self::assertSame(MemoryLocation::Tableau, $this->b->fx->memory->find($new)?->location);
        $context = $this->b->fx->globals->turnContext();
        self::assertSame(1, $context->composeActionsDone, '自己構成アクション 1 回として数える');
        self::assertTrue($context->phaseComposeUsed);
        $discarded = array_values(array_filter($notices, static fn ($n): bool => $n->type === 'cardsDiscarded' && ($n->args['from_tableau'] ?? false)));
        self::assertSame([$old], array_column($discarded[0]->args['cards'], 'id'), '自己構成済みが離れる通知にカード ID がある');
        $composed = array_values(array_filter($notices, static fn ($n): bool => $n->type === 'cardComposed'))[0];
        self::assertSame('ARCHITECT', $composed->args['context']);
        // R8-18: 枚数が 1 枚目の目盛りに戻るので I01 が反応する（イベントは解決スタックに積まれている）
        self::assertNotSame([], $this->b->fx->stack->all());
    }

    /** UT-44: 無料の文脈では使えない。捨てたカードと同名の手札は自己構成できる（捨てる処理が先）。 */
    public function testArchitectIsOnlyForPayingContexts(): void
    {
        $this->b->ego('I03');
        [$old] = $this->b->fx->giveTableau(self::P, [1]);
        [$new] = $this->b->fx->giveHand(self::P, [2]);
        $this->b->fx->stack->pushFront([EffectFrame::action(EffectFrame::KIND_COMPOSE, self::P, 'BONUS_ANY', 0)]);
        $this->b->fx->kit->resolver->resolve();

        $this->expectException(EffectViolation::class);
        $this->b->fx->kit->compose->architect(self::P, $old, $new);
    }

    /** 61 の args: I03 の入れ替えの組は本人用だけ。許される組（エネルギー差・同名）だけが載る。I08 の上限、M095 の候補も載る。 */
    public function testComposeArgsExposeArchitectPairsExtraMaxAndTapCandidates(): void
    {
        $this->b->ego('I03', 'I08');
        [$old] = $this->b->fx->giveTableau(self::P, [1]);
        [$new, $tooBig] = $this->b->fx->giveHand(self::P, [73, 85]);
        $this->beginCompose();

        $args = $this->b->fx->kit->compose->args(self::P);

        self::assertSame([[$old, $new]], $args['private']['architectPairs']);
        self::assertContains($tooBig, array_column($args['private']['hand'], 'id'));
        self::assertArrayNotHasKey('architectPairs', $args['public']);
        self::assertSame(2, $args['public']['extraMax']);
        self::assertSame([], $args['public']['tapCandidates'], 'M095 を持たない');
    }

    /** 61 の args: 無料の文脈では入れ替えられない。M095 を持たなければ extraMax は 0。 */
    public function testComposeArgsAreEmptyWithoutTheEffects(): void
    {
        $this->b->ego('I01');
        $this->b->fx->giveTableau(self::P, [1]);
        $this->b->fx->giveHand(self::P, [2]);
        $this->beginCompose();

        $args = $this->b->fx->kit->compose->args(self::P);

        self::assertSame([], $args['private']['architectPairs']);
        self::assertSame(0, $args['public']['extraMax']);
    }

    // ---- 即時効果 ----

    /** UT-40: M089 は自己構成の直後に、エネルギー 0 でレベル 1 の想い出カードを 1 枚自己構成してよい。レベル 2 は不可。 */
    public function testM089ComposesALevel1CardForFree(): void
    {
        $this->b->fx->giveTableau(self::P, [89]);
        [$lv1, $lv2] = $this->b->fx->giveHand(self::P, [1, 85]);
        $this->b->emit(Event::COMPOSED, ['card_type' => 'M089', 'ctx' => 'PHASE', 'composed_count' => 1]);

        self::assertSame(Transition::COMPOSE, $this->b->drive());
        try {
            $this->b->fx->kit->compose->compose(self::P, $lv2, []);
            self::fail('level 1 only');
        } catch (EffectViolation) {
            self::addToAssertionCount(1);
        }
        $this->b->fx->kit->compose->compose(self::P, $lv1, []);
        self::assertSame(MemoryLocation::Tableau, $this->b->fx->memory->find($lv1)?->location);
        self::assertSame(0, $this->b->fx->globals->turnContext()->composeActionsDone, '無料の自己構成は数えない（R8-7）');
        self::assertSame(Transition::RESUME_MOVE, $this->b->drive());
    }

    /** UT-40: レベル 1 の手札が無ければ、聞かずに何もしない。やめることもできる。 */
    public function testM089SkipsWhenThereIsNothingToCompose(): void
    {
        $this->b->fx->giveTableau(self::P, [89]);
        $this->b->emit(Event::COMPOSED, ['card_type' => 'M089', 'ctx' => 'PHASE', 'composed_count' => 1]);
        self::assertSame(Transition::RESUME_MOVE, $this->b->drive());

        $b = new CardBench();
        $b->fx->giveTableau(self::P, [89]);
        $b->fx->giveHand(self::P, [1]);
        $b->emit(Event::COMPOSED, ['card_type' => 'M089', 'ctx' => 'PHASE', 'composed_count' => 1]);
        self::assertSame(Transition::COMPOSE, $b->drive());
        $b->fx->kit->compose->cancel(self::P);
        self::assertSame(Transition::RESUME_MOVE, $b->drive());
    }

    /** UT-39: M088 は階層を上げ、到着した精神カードの心光とボーナスを得る。精神分析アクションの実行回数には数えない。 */
    public function testM088AdvancesTheTierWithoutCountingAnAnalysis(): void
    {
        $this->b->fx->giveTableau(self::P, [88, 79]);
        $this->b->emit(Event::COMPOSED, ['card_type' => 'M088', 'ctx' => 'PHASE', 'composed_count' => 2]);

        $this->b->drive([['spirit' => 'R1A']]);

        self::assertSame(['PICK_SPIRIT_BRANCH'], $this->b->choices);
        self::assertSame('R1A', $this->b->fx->players->spirit(self::P));
        self::assertSame(2, $this->b->light(), '精神カード上の心光 + M079（M088 の上昇にも反応する）');
        self::assertSame(1, $this->b->hand(), '第一階層のボーナスで 1 枚引く');
        self::assertSame(0, $this->b->fx->globals->turnContext()->analysisActionsDone);
    }

    /** UT-39: 第四階層からは何もしない（自己構成は成立している）。 */
    public function testM088DoesNothingFromTier4(): void
    {
        $this->b->fx->giveTableau(self::P, [88]);
        foreach (['R1A', 'R2', 'R3A', 'R4'] as $id) {
            $this->b->fx->spirits->advance(self::P, $id);
        }
        $this->b->emit(Event::COMPOSED, ['card_type' => 'M088', 'ctx' => 'PHASE', 'composed_count' => 1]);
        $this->b->drive();

        self::assertSame('R4', $this->b->fx->players->spirit(self::P));
    }

    /** UT-58（R8-5）: M071 は自己構成したカードの想起効果のうち、引く・得る・交換だけ使える。床アクションの代替・移動は使えない。 */
    public function testLullabyUsesOnlyDrawGainAndExchangeRecallEffects(): void
    {
        $this->b->fx->giveTableau(self::P, [71, 1, 52]);
        $this->b->emit(Event::COMPOSED, ['card_type' => 'M001', 'ctx' => 'PHASE', 'composed_count' => 3]);
        $this->b->drive(['use']);
        self::assertSame(1, $this->b->hand(), 'M001 の DRAW_1');

        $this->b->emit(Event::COMPOSED, ['card_type' => 'M052', 'ctx' => 'PHASE', 'composed_count' => 3]);
        $this->b->drive(['use']);
        self::assertSame([], $this->b->fx->globals->turnContext()->floorAlternatives, 'ALT_ANALYSIS は使えない');
    }

    // ---- 床アクションの追加分（M085〜M087） ----

    /** UT-37: M085 は床の「心光を得る」要素ごとに +1。ARES は場札 → 心光 + 追加。 */
    public function testM085AddsLightToFloorLightOnly(): void
    {
        $this->b->fx->giveTableau(self::P, [85]);
        $this->b->fx->place(self::P, 5);
        $field = array_map(static fn ($c): int => $c->id, $this->b->fx->memory->inLocAnyArg(MemoryLocation::Open));
        $this->b->fx->kit->floor->start();

        $this->b->drive([['cards' => [$field[0]]]]);

        self::assertSame(2, $this->b->light(), '心光 1 + M085 の追加 1');
        self::assertSame(1, $this->b->hand());
    }

    /** UT-37: 周遊ボーナスの心光（POSEIDON の足跡）や精神カード上の心光には追加が無い（origin = TOUR / EXTRA）。 */
    public function testM085IgnoresOtherLightOrigins(): void
    {
        $this->b->fx->giveTableau(self::P, [85]);
        foreach (['TOUR', 'EFFECT', 'RECALL', 'SPIRIT_CARD', 'BONUS'] as $origin) {
            self::assertFalse($this->b->emit(Event::FLOOR_GAIN, ['element' => 'NONE', 'origin' => $origin]), $origin);
        }
    }

    /** UT-38: DEMETER の 3 枚に M086 は 1 枚だけ追加（追加分で連鎖しない）。 */
    public function testM086AddsOneCardToTheFloorDraw(): void
    {
        $this->b->fx->giveTableau(self::P, [86]);
        $this->b->fx->place(self::P, 1);
        $this->b->fx->kit->floor->start();
        $this->b->drive();

        self::assertSame(4, $this->b->hand());
    }

    /** UT-38: ATHENA は追加分を含む手札から 1 枚捨てる（追加の要素が解決した直後、次の要素の前に解決する）。 */
    public function testAthenaDiscardsAfterTheExtraCard(): void
    {
        $this->b->fx->giveTableau(self::P, [86]);
        $this->b->fx->place(self::P, 3);
        $this->b->fx->kit->floor->start();

        $result = $this->b->fx->kit->resolver->resolve();

        self::assertSame(Transition::CHOICE, $result->transition);
        self::assertSame(4, $this->b->hand(), '3 枚 + 追加 1 枚を引いた後に捨てる');
    }

    // ---- I04.1（MD-10） ----

    /** UT-45: 精神分析のコストとして払った手札は、捨札ではなく現在の部屋から反時計回りに床カードの下へ。 */
    public function testLogicianPlacesAnalysisPaymentUnderTheFloor(): void
    {
        $this->b->ego('I04');
        $this->b->fx->place(self::P, 5);
        [$a, $c] = $this->b->fx->giveHand(self::P, [1, 4]); // 知恵鍵 ×2
        $this->b->fx->players->setToken(self::P, 'player_analysis_tok', 1);
        $this->b->fx->kit->construct->useAnalysisToken(self::P);
        $this->b->fx->kit->resolver->resolve();

        $notices = $this->b->fx->kit->analysis->analyze(self::P, 'R1A', [
            'removed' => [],
            'keys' => [
                ['src' => 'HAND', 'card' => $a, 'key' => 0, 'for' => KeyType::WISDOM->value],
                ['src' => 'HAND', 'card' => $c, 'key' => 0, 'for' => KeyType::WISDOM->value],
            ],
        ]);

        self::assertSame([$a], $this->b->fx->view->floorUnderIds(5));
        self::assertSame([$c], $this->b->fx->view->floorUnderIds(4));
        $types = array_map(static fn ($n): string => $n->type, $notices);
        self::assertContains('cardsPlacedUnderFloor', $types);
        self::assertNotContains('cardsDiscarded', $types, '捨札へは行かない');
    }

    // ---- 移動の終わり ----

    /** UT-24: M061 は手札・自己構成済みの意志・知恵・感情の 3 種類で払った移動の終わりに 1 枚引く。 */
    public function testM061AfterAMoveThatUsedAllThreeKinds(): void
    {
        $fx = $this->b->fx;
        $fx->giveTableau(self::P, [61]);
        $fx->place(self::P, 5);
        $fx->lock(6, KeyType::WISDOM);
        $fx->lock(8, KeyType::WILL);
        $fx->lock(9, KeyType::EMOTION);
        [$w] = $fx->giveTableau(self::P, [1]);
        [$wl] = $fx->giveTableau(self::P, [3]);
        [$e] = $fx->giveTableau(self::P, [2]);
        $result = $fx->phase->submit(self::P, 'P11', ['rooms' => [6, 8, 9], 'payments' => [
            [['src' => 'TABLEAU', 'card' => $w, 'key' => 0]],
            [['src' => 'TABLEAU', 'card' => $wl, 'key' => 0]],
            [['src' => 'TABLEAU', 'card' => $e, 'key' => 0]],
        ]]);

        self::assertTrue($fx->kit->triggers->emit($result->events));
        $this->b->drive();
        self::assertSame(1, $this->b->hand());
    }

    /** UT-48・UT-47: I07 は途中の部屋に他の人格がいたとき、I06 は万能移動鍵トークンを使わなかった移動の終わりに引く。 */
    public function testI07AndI06AfterAMove(): void
    {
        $fx = $this->b->fx;
        $this->b->ego('I06', 'I07');
        $fx->place(self::P, 5);
        $fx->place(22, 6);
        $fx->lock(6, KeyType::WISDOM);
        $fx->lock(8, KeyType::WILL);
        [$w] = $fx->giveTableau(self::P, [1]);
        [$wl] = $fx->giveTableau(self::P, [3]);
        $result = $fx->phase->submit(self::P, 'P11', ['rooms' => [6, 8], 'payments' => [
            [['src' => 'TABLEAU', 'card' => $w, 'key' => 0]],
            [['src' => 'TABLEAU', 'card' => $wl, 'key' => 0]],
        ]]);

        $fx->kit->triggers->emit($result->events);
        $this->b->drive();
        self::assertSame(2, $this->b->hand(), 'I07（通過）と I06（トークン不使用）');
    }

    // ---- ターン開始 ----

    /** UT-75: ターン開始時の効果の解決ごとに手札上限を確認する（E03 で 9 枚になったら捨てる）。 */
    public function testTurnStartEffectIsAHandLimitUnit(): void
    {
        $this->b->ego('E03');
        $this->b->fx->giveHandAny(self::P, 8);
        $this->b->emit(Event::TURN_START);

        self::assertSame(Transition::HAND_LIMIT, $this->b->drive());
        self::assertSame(9, $this->b->hand());
    }
}
