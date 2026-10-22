<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Flow\Notice;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Material\Condition;
use Bga\Games\IRevolveThereforeIAm\Material\ConditionRef;
use Bga\Games\IRevolveThereforeIAm\Material\EffectCatalog;
use Bga\Games\IRevolveThereforeIAm\Material\EffectKind;
use Bga\Games\IRevolveThereforeIAm\Material\EffectNode;
use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\Mechanism;
use Bga\Games\IRevolveThereforeIAm\Material\Op;
use Bga\Games\IRevolveThereforeIAm\Material\OwnerScope;
use IRevolveTests\Support\ArrayCatalog;
use IRevolveTests\Support\MoveFixture;
use PHPUnit\Framework\TestCase;

/**
 * 自己構成アクション（61。仕様書 001 §9、006 §5.5、008 §6.5、UT-11〜UT-18・UT-27・UT-31）。
 * 自己構成フェイズの窓（60）から始め、70 経由で 61 に入り、`actCompose` 相当の `ComposeAction::compose()` で確定する。
 */
final class ComposeActionTest extends TestCase
{
    private const P = 11;

    private MoveFixture $fx;

    protected function setUp(): void
    {
        $this->fx = new MoveFixture();
    }

    /** 自己構成フェイズの自己構成を始めて、61 に入るところまで進める。 */
    private function begin(?MoveFixture $fx = null): void
    {
        $fx ??= $this->fx;
        $fx->kit->construct->startPhaseCompose(self::P);
        self::assertSame(Transition::COMPOSE, $fx->kit->resolver->resolve()->transition);
    }

    /**
     * @param array<string, mixed> $payment
     * @return list<Notice>
     */
    private function compose(int $cardId, array $payment = [], ?MoveFixture $fx = null): array
    {
        return ($fx ?? $this->fx)->kit->compose->compose(self::P, $cardId, $payment);
    }

    /**
     * @param array<string, mixed> $payment
     */
    private function assertRejected(int $cardId, array $payment, ?string $contains = null): void
    {
        try {
            $this->compose($cardId, $payment);
            self::fail('expected a violation');
        } catch (EffectViolation $e) {
            if ($contains !== null) {
                self::assertStringContainsString($contains, $e->getMessage());
            }
            self::assertSame(MemoryLocation::Hand, $this->fx->memory->find($cardId)?->location, '拒否されたら何も変わらない');
        }
    }

    /** @param list<Notice> $notices @return list<string> */
    private static function types(array $notices): array
    {
        return array_map(static fn (Notice $n): string => $n->type, $notices);
    }

    /** 001 §9-2 a: 手札を捨てる。レベル 1 は 1 つ分、レベル 2 は 2 つ分（Q-22: 過払い可）。 */
    public function testDiscardingHandCardsPaysTheirLevel(): void
    {
        [$target, $a, $b] = $this->fx->giveHand(self::P, [1, 2, 3]); // M001 のエネルギーは 2
        $this->begin();

        $notices = $this->compose($target, ['discard' => [$a, $b]]);

        self::assertSame(MemoryLocation::Tableau, $this->fx->memory->find($target)?->location);
        self::assertSame(MemoryLocation::Discard1, $this->fx->memory->find($a)?->location);
        self::assertSame(['cardsDiscarded', 'cardComposed'], self::types($notices));
        self::assertSame(0, $this->fx->memory->count(MemoryLocation::Hand, self::P));
        self::assertTrue($this->fx->globals->turnContext()->phaseComposeUsed);
        self::assertSame(1, $this->fx->globals->turnContext()->composeActionsDone);
        self::assertSame(1, $this->fx->victoryChecks);
    }

    public function testLevel2CardPaysTwoAndOverpaymentIsAllowed(): void
    {
        [$target, $lv2] = $this->fx->giveHand(self::P, [1, 85]);
        $this->begin();

        $this->compose($target, ['discard' => [$lv2]]);

        self::assertSame(MemoryLocation::Discard2, $this->fx->memory->find($lv2)?->location);
        self::assertSame(MemoryLocation::Tableau, $this->fx->memory->find($target)?->location);

        $fx = new MoveFixture();
        [$target, $lv2, $lv1] = $fx->giveHand(self::P, [1, 85, 2]);
        $this->begin($fx);
        $fx->kit->compose->compose(self::P, $target, ['discard' => [$lv2, $lv1]]); // 3 つ分で 2 つ要る。余りは失われる
        self::assertSame(0, $fx->memory->count(MemoryLocation::Hand, self::P));
    }

    /** Q-72（M077）: 手札にある間は未稼働。M077 自身を自己構成するときの自己構成エネルギー（4）は下がらない。 */
    public function testM077DoesNotLowerItsOwnComposeEnergy(): void
    {
        [$target, $a, $b, $c, $d] = $this->fx->giveHand(self::P, [77, 1, 2, 3, 4]); // M077 のエネルギーは 4
        $this->begin();

        $this->assertRejected($target, ['discard' => [$a, $b, $c]], 'Not enough energy');

        $this->compose($target, ['discard' => [$a, $b, $c, $d]]);
        self::assertSame(MemoryLocation::Tableau, $this->fx->memory->find($target)?->location);
    }

    /** M077 は自己構成済みになってから、感情鍵のカードの自己構成エネルギーを下げる。 */
    public function testM077LowersTheEnergyOfEmotionCardsOnceComposed(): void
    {
        $this->fx->giveTableau(self::P, [77]);
        [$target, $a] = $this->fx->giveHand(self::P, [2, 1]); // M002（感情鍵）のエネルギーは 2 → 1
        $this->begin();

        $this->compose($target, ['discard' => [$a]]);
        self::assertSame(MemoryLocation::Tableau, $this->fx->memory->find($target)?->location);
    }

    public function testNotEnoughEnergyIsRejected(): void
    {
        [$target, $a] = $this->fx->giveHand(self::P, [1, 2]);
        $this->begin();

        $this->assertRejected($target, ['discard' => [$a]], 'Not enough energy');
        $this->assertRejected($target, [], 'Not enough energy');
    }

    public function testBadDiscardsAreRejected(): void
    {
        [$target, $a] = $this->fx->giveHand(self::P, [1, 2]);
        $other = $this->fx->giveHand(22, [3])[0];
        $this->begin();

        $this->assertRejected($target, ['discard' => [$target, $a]], 'cannot be discarded');
        $this->assertRejected($target, ['discard' => [$a, $other]], 'cannot be discarded');
        $this->assertRejected($target, ['discard' => [$a, $a]], 'duplicates');
        $this->assertRejected($target, ['discard' => 'x'], 'list');
        $this->assertRejected($target, ['light' => -1], 'count');
        $this->assertRejected($target, ['extra' => [$a]], 'extra cards');
        $this->assertRejected($other, [], 'cannot be composed');
    }

    /** 001 §9-2 b / UT-17: 心光の行使。X 回目は X 個で 1 エネルギー分（1・3 個で 1・2 エネルギー）。 */
    public function testLightUsesAreProgressive(): void
    {
        [$target] = $this->fx->giveHand(self::P, [1]);
        $this->fx->tokens(self::P, light: 2);
        $this->begin();

        $this->assertRejected($target, ['light' => 2], 'Not enough inner light'); // 2 エネルギー分 = 1 + 2 = 3 個

        $this->fx->tokens(self::P, light: 3);
        $notices = $this->compose($target, ['light' => 2]);

        self::assertSame(0, $this->fx->players->light(self::P));
        self::assertContains('tokensChanged', self::types($notices));
        self::assertSame(['light' => -3], $notices[0]->args['delta']);
    }

    /** 心光と手札の組み合わせ。心光の行使は、手札の分を差し引いた残りに対する X 回目。 */
    public function testLightAndDiscardCombine(): void
    {
        [$target, $a] = $this->fx->giveHand(self::P, [1, 2]);
        $this->fx->tokens(self::P, light: 1);
        $this->begin();

        $this->compose($target, ['discard' => [$a], 'light' => 1]);

        self::assertSame(MemoryLocation::Tableau, $this->fx->memory->find($target)?->location);
        self::assertSame(0, $this->fx->players->light(self::P));
    }

    /** UT-17 / UT-18: M070 は表 [1, 1, 2, 2, 3]（累計 1・2・4・6・9 個）。通常は 1・3・6 個。 */
    public function testM070ReducesTheLightSchedule(): void
    {
        $this->fx->giveTableau(self::P, [70]);
        [$target] = $this->fx->giveHand(self::P, [1]);
        $this->fx->tokens(self::P, light: 2);
        $this->begin();

        $this->compose($target, ['light' => 2]); // 通常は 3 個のところ、M070 で 2 個

        self::assertSame(0, $this->fx->players->light(self::P));
    }

    /** 001 §9-2: 自己構成済みカードの鍵・万能移動鍵トークンはエネルギーの支払いに使えない（入力に無い）。 */
    public function testPaymentRejectsUnknownKeys(): void
    {
        [$target] = $this->fx->giveHand(self::P, [1]);
        $this->fx->giveTableau(self::P, [4]);
        $this->fx->players->setToken(self::P, 'player_wild_up', 5);
        $this->begin();

        $this->assertRejected($target, [], 'Not enough energy');
    }

    /** UT-12: POSEIDON・E01・次の自己構成の軽減が加算され、最後に 1 回だけ 0 へ切り上げる。 */
    public function testDiscountsAddUpAndNeverGoBelowZero(): void
    {
        $this->fx->egos->obtainEnhancement('E01', self::P);
        $ctx = $this->fx->globals->turnContext();
        $ctx->turnComposeDiscount = 1;
        $ctx->nextComposeDiscount = 1;
        $this->fx->globals->setTurnContext($ctx);
        [$target] = $this->fx->giveHand(self::P, [1]); // エネルギー 2。軽減 3 で 0
        $this->begin();

        $notices = $this->compose($target, []);

        self::assertSame(MemoryLocation::Tableau, $this->fx->memory->find($target)?->location);
        self::assertSame(0, $this->fx->globals->turnContext()->nextComposeDiscount);
        self::assertSame(1, $this->fx->globals->turnContext()->turnComposeDiscount, 'POSEIDON はターン中残る');
        self::assertContains('composeDiscountChanged', self::types($notices));
    }

    /** UT-11: M060 でレベル 2 は -1。負にならず、リソースも増えない。 */
    public function testM060ReducesLevel2OnlyAndEnergyNeverGoesNegative(): void
    {
        $this->fx->giveTableau(self::P, [60]);
        [$lv2, $a, $b, $c, $d] = $this->fx->giveHand(self::P, [85, 1, 2, 3, 4]);
        $this->begin();

        $this->assertRejected($lv2, ['discard' => [$a, $b, $c]], 'Not enough energy'); // 5 - 1 = 4 要る
        $this->compose($lv2, ['discard' => [$a, $b, $c, $d]]);
        self::assertSame(MemoryLocation::Tableau, $this->fx->memory->find($lv2)?->location);
    }

    /** 床アクション（HEPHAESTUS）の自己構成は、その 1 回だけエネルギー -1。軽減は文脈から 61 に伝わり、自己構成アクションとして数える。 */
    public function testFloorComposeUsesTheContextDiscountOnce(): void
    {
        $fx = $this->fx;
        $fx->place(self::P, 12);
        [$target, $a, $b] = $fx->giveHand(self::P, [1, 2, 3]);
        $fx->kit->floor->start();
        $fx->kit->floor->choose('FLOOR_A');
        self::assertSame(Transition::COMPOSE, $fx->kit->resolver->resolve()->transition);

        $this->compose($target, ['discard' => [$a]], $fx); // エネルギー 2 - 1 = 1

        self::assertSame(MemoryLocation::Tableau, $fx->memory->find($target)?->location);
        self::assertSame(MemoryLocation::Hand, $fx->memory->find($b)?->location);
        self::assertSame(1, $fx->globals->turnContext()->composeActionsDone);
        self::assertFalse($fx->globals->turnContext()->phaseComposeUsed, '床アクションの自己構成はフェイズの自己構成ではない');
        self::assertSame(Transition::RESUME_CONSTRUCT, $fx->kit->resolver->resolve()->transition);

        // 次の自己構成アクションに -1 は残らない
        $fx->kit->construct->startPhaseCompose(self::P);
        $fx->kit->resolver->resolve();
        self::assertSame(0, $fx->kit->compose->args(self::P)['public']['discount']);
    }

    /** UT-27: 次の自己構成の軽減は完了で消費される。取り消しでは消費されない。 */
    public function testNextDiscountIsConsumedOnlyOnCompletion(): void
    {
        $ctx = $this->fx->globals->turnContext();
        $ctx->nextComposeDiscount = 1;
        $this->fx->globals->setTurnContext($ctx);
        [$target, $a] = $this->fx->giveHand(self::P, [1, 2]);
        $this->begin();

        $this->fx->kit->compose->cancel(self::P);
        self::assertSame(1, $this->fx->globals->turnContext()->nextComposeDiscount);
        self::assertFalse($this->fx->globals->turnContext()->phaseComposeUsed, '取り消しでは使用済みにならない');
        self::assertSame(0, $this->fx->globals->turnContext()->composeActionsDone);

        $this->begin();
        $args = $this->fx->kit->compose->args(self::P);
        self::assertSame(1, $args['public']['nextDiscount']);
        $this->compose($target, ['discard' => [$a]]);
        self::assertSame(0, $this->fx->globals->turnContext()->nextComposeDiscount);
        self::assertSame(1, $this->fx->globals->turnContext()->composeActionsDone);
    }

    /** UT-15 / UT-16: M095 は感情鍵のカードを横向きにして 1 枚につき -1。万能鍵のみ・横向き済み・必要以上は不可。 */
    public function testM095TapsEmotionCards(): void
    {
        [$tapA, $tapB, $wild] = $this->fx->giveTableau(self::P, [2, 5, 40]);
        $this->fx->giveTableau(self::P, [95]);
        [$target] = $this->fx->giveHand(self::P, [1]);
        $this->begin();

        $this->assertRejected($target, ['tap' => [$wild]], 'required key');
        $this->assertRejected($target, ['tap' => [$tapA, $tapA]], 'duplicates');

        $this->compose($target, ['tap' => [$tapA, $tapB]]);
        self::assertTrue($this->fx->memory->find($tapA)?->tapped);
        self::assertTrue($this->fx->memory->find($tapB)?->tapped);

        $fx = new MoveFixture();
        $fx->giveTableau(self::P, [95]);
        [$a, $b, $c] = $fx->giveTableau(self::P, [2, 5, 8]);
        [$target] = $fx->giveHand(self::P, [1]);
        $this->begin($fx);
        try {
            $fx->kit->compose->compose(self::P, $target, ['tap' => [$a, $b, $c]]);
            self::fail('too many taps');
        } catch (EffectViolation $e) {
            self::assertStringContainsString('Too many', $e->getMessage());
        }
    }

    /** 61 の args: M095 で横向きにできるのは、縦向きで感情鍵を印刷された自己構成済みカード（万能鍵のみ・横向き済みは除く）。 */
    public function testArgsListTapCandidates(): void
    {
        [$tapA, $tapB, $wild] = $this->fx->giveTableau(self::P, [2, 5, 40]);
        $this->fx->giveTableau(self::P, [95]);
        $this->fx->memory->setTapped([$tapB], true);
        $this->fx->giveHand(self::P, [1]);
        $this->begin();

        $args = $this->fx->kit->compose->args(self::P);

        self::assertContains($tapA, $args['public']['tapCandidates']);
        self::assertNotContains($tapB, $args['public']['tapCandidates']);
        self::assertNotContains($wild, $args['public']['tapCandidates']);
        self::assertSame(0, $args['public']['extraMax']);
    }

    /** 001 §9-1: 同名のカードは自己構成できない。 */
    public function testSameNameCannotBeComposed(): void
    {
        $this->fx->giveTableau(self::P, [58]);
        [$duplicate, $other] = $this->fx->giveHand(self::P, [58, 1]);
        $this->begin();

        self::assertSame([$other], $this->fx->kit->composePlanner->candidates(self::P, \Bga\Games\IRevolveThereforeIAm\Material\ComposeContext::PHASE));
        $this->assertRejected($duplicate, [], 'cannot be composed');
    }

    /** UT-31 / CD-12: 自己構成フェイズの自己構成は 1 回だけ（Q-02）。 */
    public function testThePhaseComposeIsOnlyOncePerTurn(): void
    {
        [$target, $a, $b, $c] = $this->fx->giveHand(self::P, [1, 2, 3, 4]);
        $this->begin();
        $this->compose($target, ['discard' => [$a, $b]]);
        self::assertSame(Transition::RESUME_CONSTRUCT, $this->resumeAfter());

        $this->expectException(EffectViolation::class);
        $this->fx->kit->construct->startPhaseCompose(self::P);
        unset($c);
    }

    private function resumeAfter(): string
    {
        $this->fx->globals->setResume(60);

        return $this->fx->kit->resolver->resolve()->transition;
    }

    /** Q-28: 自己構成トークンは、確定したときにだけ消費する。取り消しでは消費しない。 */
    public function testComposeTokenIsConsumedOnCompletionOnly(): void
    {
        $this->fx->players->setToken(self::P, 'player_compose_tok', 1);
        [$target, $a, $b] = $this->fx->giveHand(self::P, [1, 2, 3]);

        $this->fx->kit->construct->useComposeToken(self::P);
        self::assertSame(Transition::COMPOSE, $this->fx->kit->resolver->resolve()->transition);
        $this->fx->kit->compose->cancel(self::P);
        self::assertSame(1, $this->fx->players->composeTokens(self::P));

        $this->fx->kit->construct->useComposeToken(self::P);
        $this->fx->kit->resolver->resolve();
        $notices = $this->compose($target, ['discard' => [$a, $b]]);
        self::assertSame(0, $this->fx->players->composeTokens(self::P));
        $spent = array_values(array_filter($notices, static fn (Notice $n): bool => $n->type === 'tokensChanged' && isset($n->args['delta']['compose'])));
        self::assertCount(1, $spent);
        self::assertSame(['compose' => -1], $spent[0]->args['delta']);
        // トークンによる自己構成は、フェイズの自己構成を使用済みにしない
        self::assertFalse($this->fx->globals->turnContext()->phaseComposeUsed);
        self::assertSame(1, $this->fx->globals->turnContext()->composeActionsDone);
    }

    /** トークンが無い、または自己構成できるカードが無いときは、トークンを使えない（空撃ち不可）。 */
    public function testComposeTokenCannotBeWasted(): void
    {
        try {
            $this->fx->kit->construct->useComposeToken(self::P);
            self::fail('no token');
        } catch (EffectViolation $e) {
            self::assertStringContainsString('no compose token', $e->getMessage());
        }
        $this->fx->players->setToken(self::P, 'player_compose_tok', 1);
        try {
            $this->fx->kit->construct->useComposeToken(self::P);
            self::fail('no card to compose');
        } catch (EffectViolation $e) {
            self::assertStringContainsString('no card', $e->getMessage());
        }
        self::assertSame(0, $this->fx->stack->count());
    }

    /** 払えないカードしか無いときも、トークンは使えない。心光や手札で払えるなら使える。 */
    public function testComposeTokenNeedsAffordableCard(): void
    {
        $this->fx->players->setToken(self::P, 'player_compose_tok', 1);
        $this->fx->giveHand(self::P, [85]); // エネルギー 5 を払う手段が無い
        $this->expectException(EffectViolation::class);
        $this->fx->kit->construct->useComposeToken(self::P);
    }

    /** args: 手札ごとの必要エネルギー（本人にだけ）と、心光の逓増。 */
    public function testArgsExposeHandEnergyOnlyPrivately(): void
    {
        $this->fx->giveHand(self::P, [1, 85]);
        $this->fx->tokens(self::P, light: 7);
        $this->begin();

        $args = $this->fx->kit->compose->args(self::P);

        self::assertSame('PHASE', $args['public']['context']);
        self::assertSame([1, 3, 6, 10, 15, 21, 28, 36], $args['public']['lightTotals']);
        self::assertSame(3, $args['public']['maxLightUses']);
        self::assertArrayNotHasKey('hand', $args['public']);
        $need = array_column($args['private']['hand'], 'need', 'type');
        ksort($need);
        self::assertSame(['M001' => 2, 'M085' => 5], $need);
    }

    /** 他のプレイヤーの行動としては操作できない。 */
    public function testOnlyTheOwnerOfTheActionCanCompose(): void
    {
        [$target] = $this->fx->giveHand(22, [1]);
        $this->begin();

        $this->expectException(EffectViolation::class);
        $this->fx->kit->compose->compose(22, $target, []);
    }

    /** EV-05: 自己構成すると、自己構成済みの TRIGGER が誘発する。自己構成したカード自身の起動効果は反応しない（R8-3）。 */
    public function testComposedEventTriggersOtherCardsButNotTheComposedOne(): void
    {
        $trigger = new EffectSpec(
            id: 'M073.1',
            source: 'M073',
            mechanism: Mechanism::TRIGGER,
            kind: EffectKind::ACTIVATED,
            ownerScope: OwnerScope::OWNER,
            ops: [EffectNode::op(Op::DRAW_DECK, ['n' => 1])],
            events: [Event::COMPOSED],
            conditions: [ConditionRef::of(Condition::COMPOSED_CARD_HAS_KEY, ['type' => KeyType::WISDOM])],
        );
        $catalog = new ArrayCatalog([...array_values(EffectCatalog::all()), $trigger]);
        $fx = new MoveFixture($catalog);
        $fx->giveTableau(self::P, [73]);
        [$wisdom, $emotion, $x, $y] = $fx->giveHand(self::P, [1, 2, 3, 4]);
        $before = $fx->memory->count(MemoryLocation::Hand, self::P);
        $this->begin($fx);

        $fx->kit->compose->compose(self::P, $wisdom, ['discard' => [$x, $y]]);
        $result = $fx->kit->resolver->resolve();

        self::assertSame(Transition::RESUME_CONSTRUCT, $result->transition);
        self::assertSame($before - 3 + 1, $fx->memory->count(MemoryLocation::Hand, self::P), '知恵鍵のカードで 1 枚引く');

        // 感情鍵のカードでは誘発しない（フェイズの自己構成は使用済みなのでトークンで）
        $fx->players->setToken(self::P, 'player_compose_tok', 1);
        $pay = $fx->giveHandAny(self::P, 2);
        $count = $fx->memory->count(MemoryLocation::Hand, self::P);
        $fx->kit->construct->useComposeToken(self::P);
        $fx->kit->resolver->resolve();
        $fx->kit->compose->compose(self::P, $emotion, ['discard' => $pay]);
        $fx->kit->resolver->resolve();
        self::assertSame($count - 3, $fx->memory->count(MemoryLocation::Hand, self::P));
    }
}
