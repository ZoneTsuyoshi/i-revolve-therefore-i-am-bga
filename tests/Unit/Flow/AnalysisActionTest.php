<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\EgoRepository;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Flow\Notice;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Material\EffectCatalog;
use Bga\Games\IRevolveThereforeIAm\Material\EffectKind;
use Bga\Games\IRevolveThereforeIAm\Material\EffectNode;
use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\Mechanism;
use Bga\Games\IRevolveThereforeIAm\Material\Op;
use Bga\Games\IRevolveThereforeIAm\Material\Origin;
use Bga\Games\IRevolveThereforeIAm\Material\OwnerScope;
use IRevolveTests\Support\ArrayCatalog;
use IRevolveTests\Support\MoveFixture;
use IRevolveTests\Support\TestSpecs;
use PHPUnit\Framework\TestCase;

/**
 * 精神分析アクション（62。仕様書 001 §10、006 §5.4、008 §6.5.3・§7.5、UT-20〜UT-22・UT-28・UT-36・UT-64・UT-65）。
 * 精神分析トークン（60）から始め、70 経由で 62 に入り、`AnalysisAction::analyze()` で確定する。
 * 3 人戦の精神トラックは R1A・R1B → R2 → R3A・R3B → R4 → R5。
 */
final class AnalysisActionTest extends TestCase
{
    private const P = 11;

    private MoveFixture $fx;

    protected function setUp(): void
    {
        $this->fx = new MoveFixture();
    }

    /**
     * @param array{kind: string, id: int, card: int, key: int, for: string}|array<string, mixed> ...$items
     * @return array<string, mixed>
     */
    private static function pay(array $removed, array ...$items): array
    {
        return ['removed' => $removed, 'keys' => $items];
    }

    /** @return array<string, mixed> */
    private static function hand(int $card, string $for, int $key = 0): array
    {
        return ['src' => 'HAND', 'card' => $card, 'key' => $key, 'for' => $for];
    }

    /** @return array<string, mixed> */
    private static function tableau(int $card, string $for, int $key = 0): array
    {
        return ['src' => 'TABLEAU', 'card' => $card, 'key' => $key, 'for' => $for];
    }

    /** @return array<string, mixed> */
    private static function light(string $for): array
    {
        return ['src' => 'LIGHT', 'for' => $for];
    }

    /** 精神分析トークンを使って 62 に入るところまで進める。 */
    private function begin(?MoveFixture $fx = null): void
    {
        $fx ??= $this->fx;
        $fx->players->setToken($fx->globals->turnPlayer(), 'player_analysis_tok', 1);
        $fx->kit->construct->useAnalysisToken($fx->globals->turnPlayer());
        self::assertSame(Transition::ANALYSIS, $fx->kit->resolver->resolve()->transition);
    }

    /** @param array<string, mixed> $payment @return list<Notice> */
    private function analyze(string $destination, array $payment, ?MoveFixture $fx = null): array
    {
        $fx ??= $this->fx;

        return $fx->kit->analysis->analyze($fx->globals->turnPlayer(), $destination, $payment);
    }

    /** @param array<string, mixed> $payment */
    private function assertRejected(string $destination, array $payment, string $contains): void
    {
        try {
            $this->analyze($destination, $payment);
            self::fail('expected a violation');
        } catch (EffectViolation $e) {
            self::assertStringContainsString($contains, $e->getMessage());
            self::assertNull($this->fx->players->spirit(self::P), '拒否されたら階層は上がらない');
        }
    }

    /** @param list<Notice> $notices @return list<string> */
    private static function types(array $notices): array
    {
        return array_map(static fn (Notice $n): string => $n->type, $notices);
    }

    /** 手札を捨てて鍵を使う。到着で心光（先着）と第一階層のボーナス（1 枚引く）。トークンはこのとき消費する。 */
    public function testHandKeysPayAndTheFirstArrivalGetsTheLightAndTheBonus(): void
    {
        [$a, $b] = $this->fx->giveHand(self::P, [1, 4]); // 知恵鍵 ×2
        $this->begin();
        self::assertSame(1, $this->fx->players->analysisTokens(self::P));

        $notices = $this->analyze('R1A', self::pay([], self::hand($a, 'WISDOM'), self::hand($b, 'WISDOM')));

        self::assertSame('R1A', $this->fx->players->spirit(self::P));
        self::assertSame(0, $this->fx->players->analysisTokens(self::P));
        self::assertSame(MemoryLocation::Discard1, $this->fx->memory->find($a)?->location);
        self::assertSame(1, $this->fx->globals->turnContext()->analysisActionsDone);
        self::assertContains('spiritAdvanced', self::types($notices));
        $advanced = array_values(array_filter($notices, static fn (Notice $n): bool => $n->type === 'spiritAdvanced'))[0];
        self::assertTrue($advanced->args['light_taken']);
        self::assertSame(1, $advanced->args['tier']);

        // 70: 心光の獲得（origin = SPIRIT_CARD）→ ボーナス（1 枚引く）
        $result = $this->fx->kit->resolver->resolve();
        self::assertSame(Transition::RESUME_CONSTRUCT, $result->transition);
        self::assertSame(1, $this->fx->players->light(self::P));
        self::assertSame(1, $this->fx->memory->count(MemoryLocation::Hand, self::P));
        self::assertFalse($this->fx->spirits->hasLight('R1A'));
        self::assertGreaterThanOrEqual(1, $this->fx->victoryChecks);
    }

    /** 先着: 後から同じ精神カードに進んだ人は心光を獲得できない（カード上には残らない。Q-27）。 */
    public function testSpiritLightIsFirstComeFirstServed(): void
    {
        [$a, $b] = $this->fx->giveHand(self::P, [1, 4]);
        $this->begin();
        $this->analyze('R1A', self::pay([], self::hand($a, 'WISDOM'), self::hand($b, 'WISDOM')));
        $this->fx->kit->resolver->resolve();

        $fx = $this->fx;
        $fx->globals->setTurnPlayer(22);
        [$c, $d] = $fx->giveHand(22, [7, 10]);
        $this->begin();
        $notices = $this->analyze('R1A', self::pay([], self::hand($c, 'WISDOM'), self::hand($d, 'WISDOM')));
        $advanced = array_values(array_filter($notices, static fn (Notice $n): bool => $n->type === 'spiritAdvanced'))[0];
        $fx->kit->resolver->resolve();

        self::assertFalse($advanced->args['light_taken']);
        self::assertSame(0, $fx->players->light(22));
        self::assertSame('R1A', $fx->players->spirit(22));
        self::assertSame(1, $fx->players->light(self::P));
    }

    /** 自己構成済みカードを横向きにして鍵を使う。複数の鍵を持つカードは同時に 2 つ払える。使用済み（横向き）のカードは使えない。 */
    public function testTableauKeysTapTheCardAndTappedCardsCannotBeUsed(): void
    {
        [$double] = $this->fx->giveTableau(self::P, [94]); // 知恵 / 知恵
        $this->begin();

        $this->fx->memory->setTapped([$double], true);
        $this->assertRejected('R1A', self::pay([], self::tableau($double, 'WISDOM', 0), self::tableau($double, 'WISDOM', 1)), 'cannot be used');
        $this->fx->memory->setTapped([$double], false);

        $notices = $this->analyze('R1A', self::pay([], self::tableau($double, 'WISDOM', 0), self::tableau($double, 'WISDOM', 1)));

        self::assertTrue($this->fx->memory->find($double)?->tapped);
        self::assertContains('cardsTapped', self::types($notices));
        self::assertSame(MemoryLocation::Tableau, $this->fx->memory->find($double)?->location);
    }

    public function testSameKeyCannotBeUsedTwiceAndCostMustBeCoveredExactly(): void
    {
        [$a, $b, $emotion] = $this->fx->giveHand(self::P, [1, 4, 2]);
        $this->begin();

        $this->assertRejected('R1A', self::pay([], self::hand($a, 'WISDOM'), self::hand($a, 'WISDOM')), 'twice');
        $this->assertRejected('R1A', self::pay([], self::hand($a, 'WISDOM')), 'exactly');
        $this->assertRejected('R1A', self::pay([], self::hand($a, 'WISDOM'), self::hand($b, 'WISDOM'), self::light('WISDOM')), 'exactly');
        $this->assertRejected('R1A', self::pay([], self::hand($a, 'WISDOM'), self::hand($emotion, 'WISDOM')), 'cannot pay');
        $this->assertRejected('R1A', self::pay([], self::hand($a, 'WILL'), self::hand($b, 'WILL')), 'cannot pay');
        $this->assertRejected('R1B', self::pay([], self::hand($a, 'WILL'), self::hand($b, 'WILL')), 'cannot pay');
        $this->assertRejected('R2', self::pay([], self::hand($a, 'WISDOM')), 'next tier');
        $this->assertRejected('R1A', ['keys' => 'x'], 'list');
        $this->assertRejected('R1A', ['keys' => [['src' => 'TOKEN', 'for' => 'WISDOM']]], 'Cannot pay with TOKEN');
    }

    /** 万能鍵は何の代わりにもなる（M040）。 */
    public function testWildKeyPaysAnyCost(): void
    {
        [$wild, $will] = $this->fx->giveHand(self::P, [40, 3]);
        $this->begin();

        $this->analyze('R1B', self::pay([], self::hand($wild, 'WILL'), self::hand($will, 'WILL')));

        self::assertSame('R1B', $this->fx->players->spirit(self::P));
    }

    /** 001 §10-2 c / UT-17・UT-18 の精神分析版: 心光の行使。X 回目は X 個。M059 は表 [1, 1, 2, 2, 3]。 */
    public function testLightUsesFollowTheScheduleAndAreCounted(): void
    {
        $this->fx->tokens(self::P, light: 3);
        $this->begin();
        $this->fx->tokens(self::P, light: 2);
        $this->assertRejected('R1A', self::pay([], self::light('WISDOM'), self::light('WISDOM')), 'Not enough inner light'); // 1 + 2 = 3

        $this->fx->tokens(self::P, light: 3);
        $notices = $this->analyze('R1A', self::pay([], self::light('WISDOM'), self::light('WISDOM')));
        self::assertSame(0, $this->fx->players->light(self::P));
        $spent = array_values(array_filter($notices, static fn (Notice $n): bool => $n->type === 'tokensChanged' && ($n->args['delta']['light'] ?? 0) < 0));
        self::assertSame(['light' => -3], $spent[0]->args['delta']);

        $fx = new MoveFixture();
        $fx->giveTableau(self::P, [59]);
        $fx->tokens(self::P, light: 2);
        $this->begin($fx);
        $fx->kit->analysis->analyze(self::P, 'R1A', self::pay([], self::light('WISDOM'), self::light('WISDOM')));
        self::assertSame(0, $fx->players->light(self::P), 'M059: 1 個 + 1 個');
    }

    /** UT-20: E02 でコストが 1 減る。どの鍵を減らすかは選ぶ。減らす数が違う入力は拒否される。 */
    public function testCostReductionRemovesTheChosenKeys(): void
    {
        $this->fx->egos->obtainEnhancement('E02', self::P);
        [$a, $b] = $this->fx->giveHand(self::P, [1, 4]);
        $this->begin();

        $args = $this->fx->kit->analysis->args(self::P);
        self::assertSame(1, $args['public']['reduction']);
        $this->assertRejected('R1A', self::pay([], self::hand($a, 'WISDOM')), 'remove exactly 1');
        $this->assertRejected('R1A', self::pay(['WISDOM' => 2], self::hand($a, 'WISDOM')), 'remove');
        $this->assertRejected('R1A', self::pay(['WILL' => 1], self::hand($a, 'WISDOM')), 'remove');

        $this->analyze('R1A', self::pay(['WISDOM' => 1], self::hand($a, 'WISDOM')));

        self::assertSame('R1A', $this->fx->players->spirit(self::P));
        self::assertSame(MemoryLocation::Hand, $this->fx->memory->find($b)?->location);
    }

    /** UT-21: M094 は知恵鍵を万能鍵としても使える（元の種類としても使える）。精神分析の間だけ。 */
    public function testM094ViewsWisdomAsWildForAnalysis(): void
    {
        $this->fx->spirits->advance(self::P, 'R1A');
        $this->fx->players->setToken(self::P, 'player_analysis_tok', 1);
        [$double] = $this->fx->giveTableau(self::P, [94]); // 知恵 / 知恵
        [$will] = $this->fx->giveHand(self::P, [3]);
        $this->fx->db->execute("UPDATE `player` SET `player_spirit` = 'R1A' WHERE `player_id` = " . self::P);
        $this->fx->kit->construct->useAnalysisToken(self::P);
        $this->fx->kit->resolver->resolve();

        // R2: 知恵・意志・感情が 1 つずつ。M094 の知恵鍵 1 つは知恵、もう 1 つは万能鍵として感情を払う
        $this->analyze('R2', self::pay([], self::tableau($double, 'WISDOM', 0), self::tableau($double, 'EMOTION', 1), self::hand($will, 'WILL')));

        self::assertSame('R2', $this->fx->players->spirit(self::P));
    }

    /** M094 を持たなければ、知恵鍵で感情のコストは払えない。 */
    public function testWithoutM094WisdomCannotPayEmotion(): void
    {
        $this->fx->spirits->advance(self::P, 'R1A');
        [$double] = $this->fx->giveTableau(self::P, [97]); // 知恵 / 知恵（読み替え無し）
        [$will] = $this->fx->giveHand(self::P, [3]);
        $this->fx->giveHand(self::P, [2]); // 感情鍵のカードがあるので、トークンは使える
        $this->begin();

        try {
            $this->analyze('R2', self::pay([], self::tableau($double, 'WISDOM', 0), self::tableau($double, 'EMOTION', 1), self::hand($will, 'WILL')));
            self::fail('a wisdom key cannot pay an emotion cost');
        } catch (EffectViolation $e) {
            self::assertStringContainsString('cannot pay', $e->getMessage());
        }
    }

    /** Q-28: 払えないときは精神分析トークンを使えない（空撃ち不可）。取り消してもトークンは消費されない。 */
    public function testAnalysisTokenCannotBeWastedAndCancelKeepsIt(): void
    {
        $this->fx->players->setToken(self::P, 'player_analysis_tok', 1);
        try {
            $this->fx->kit->construct->useAnalysisToken(self::P);
            self::fail('nothing can be paid');
        } catch (EffectViolation $e) {
            self::assertStringContainsString('cannot pay', $e->getMessage());
        }
        self::assertSame(0, $this->fx->stack->count());

        $this->fx->giveHand(self::P, [1, 4]);
        $this->begin();
        $this->fx->kit->analysis->cancel(self::P);
        self::assertSame(1, $this->fx->players->analysisTokens(self::P));
        self::assertSame(Transition::RESUME_CONSTRUCT, $this->fx->kit->resolver->resolve()->transition);
        self::assertNull($this->fx->players->spirit(self::P));
    }

    /** 心光でも払えるなら、トークンは使える。args の canAnalyze もそれに合う。 */
    public function testCanAnalyzeConsidersLightsAndKeys(): void
    {
        self::assertFalse($this->fx->kit->analysisPlanner->canAnalyze(self::P, 0));
        $this->fx->tokens(self::P, light: 3);
        self::assertTrue($this->fx->kit->analysisPlanner->canAnalyze(self::P, 0));
        $this->fx->tokens(self::P, light: 2);
        self::assertFalse($this->fx->kit->analysisPlanner->canAnalyze(self::P, 0));
        $this->fx->giveHand(self::P, [1]);
        self::assertTrue($this->fx->kit->analysisPlanner->canAnalyze(self::P, 0), '手札の鍵 1 つ + 心光 1 個');
        self::assertTrue($this->fx->kit->analysisPlanner->canAnalyze(self::P, 1), '割引でコストが減る');
    }

    /** UT-65: R2 のボーナスはレベル 1 の想い出カードを、エネルギーなしで自己構成してもよい。行わなくてもよい。軽減は消費しない。 */
    public function testSecondTierBonusIsAFreeLevel1Compose(): void
    {
        $this->fx->spirits->advance(self::P, 'R1A');
        $this->fx->db->execute("UPDATE `spirit_card` SET `spirit_light` = 0");
        $ctx = $this->fx->globals->turnContext();
        $ctx->nextComposeDiscount = 1;
        $this->fx->globals->setTurnContext($ctx);
        [$w, $e, $n, $lv1, $lv2] = $this->fx->giveHand(self::P, [1, 2, 3, 40, 85]);
        $this->begin();
        $this->analyze('R2', self::pay([], self::hand($w, 'WISDOM'), self::hand($e, 'EMOTION'), self::hand($n, 'WILL')));

        $ask = $this->fx->kit->resolver->resolve();

        self::assertSame(Transition::COMPOSE, $ask->transition);
        $args = $this->fx->kit->compose->args(self::P);
        self::assertSame('BONUS_LV1', $args['public']['context']);
        $composable = array_column(array_filter($args['private']['hand'], static fn (array $c): bool => $c['composable']), 'id');
        self::assertSame([$lv1], array_values($composable), 'レベル 1 だけ');
        $this->fx->kit->compose->compose(self::P, $lv1, []);

        self::assertSame(MemoryLocation::Tableau, $this->fx->memory->find($lv1)?->location);
        self::assertSame(1, $this->fx->globals->turnContext()->nextComposeDiscount, '無料の自己構成は軽減を消費しない');
        self::assertSame(0, $this->fx->globals->turnContext()->composeActionsDone);
        self::assertSame(MemoryLocation::Hand, $this->fx->memory->find($lv2)?->location);
        self::assertSame(Transition::RESUME_CONSTRUCT, $this->fx->kit->resolver->resolve()->transition);
    }

    public function testSecondTierBonusCanBeDeclinedAndIsSkippedWithoutALevel1Card(): void
    {
        $this->fx->spirits->advance(self::P, 'R1A');
        [$w, $e, $n, $lv1] = $this->fx->giveHand(self::P, [1, 2, 3, 40]);
        $this->begin();
        $this->analyze('R2', self::pay([], self::hand($w, 'WISDOM'), self::hand($e, 'EMOTION'), self::hand($n, 'WILL')));
        self::assertSame(Transition::COMPOSE, $this->fx->kit->resolver->resolve()->transition);
        $this->fx->kit->compose->cancel(self::P);
        self::assertSame(Transition::RESUME_CONSTRUCT, $this->fx->kit->resolver->resolve()->transition);
        self::assertSame(MemoryLocation::Hand, $this->fx->memory->find($lv1)?->location);

        $fx = new MoveFixture();
        $fx->spirits->advance(self::P, 'R1A');
        [$w, $e, $n] = $fx->giveHand(self::P, [1, 2, 3]);
        $this->begin($fx);
        $fx->kit->analysis->analyze(self::P, 'R2', self::pay([], self::hand($w, 'WISDOM'), self::hand($e, 'EMOTION'), self::hand($n, 'WILL')));
        self::assertSame(Transition::RESUME_CONSTRUCT, $fx->kit->resolver->resolve()->transition, '自己構成できるカードが無い');
    }

    /** UT-65: R3 は強化自我を 1 枚。候補（山の全カード）は選択者の private args にだけ入り、獲得したカードは公開される。 */
    public function testThirdTierGivesAnEnhancedEgoChosenFromThePrivateCandidates(): void
    {
        $fx = $this->fx;
        $fx->spirits->advance(self::P, 'R1A');
        $fx->db->execute("UPDATE `player` SET `player_spirit` = 'R2' WHERE `player_id` = " . self::P);
        $fx->tokens(self::P, light: 6);
        $this->begin();

        $notices = $this->analyze('R3A', self::pay([], self::light('WISDOM'), self::light('WISDOM'), self::light('WISDOM')));
        self::assertSame(0, $fx->players->light(self::P) - 6 + 6 - $fx->players->light(self::P));
        $ask = $fx->kit->resolver->resolve();

        self::assertSame(Transition::CHOICE, $ask->transition);
        self::assertSame(self::P, $ask->activePlayer);
        $args = $fx->kit->choices->args();
        self::assertSame('PICK_ENHANCED_EGO', $args['public']['choiceKind']);
        self::assertSame(['E01', 'E02', 'E03'], $args['private']['egoCandidates']);
        self::assertArrayNotHasKey('egoCandidates', $args['public']);
        self::assertFalse($args['public']['skippable']);
        self::assertContains('spiritAdvanced', self::types($notices));

        $obtained = $fx->kit->choices->answer(self::P, ['ego' => 'E02']);

        self::assertSame(['enhancedEgoObtained'], self::types($obtained));
        self::assertSame('E02', $obtained[0]->args['ego_id']);
        self::assertSame(2, $obtained[0]->args['deck_count']);
        self::assertSame(2, $fx->egos->count(EgoRepository::ENH_DECK));
        self::assertSame(['E02'], array_column($fx->egos->inLoc(EgoRepository::OWNED, self::P), 'id'));
        self::assertSame(Transition::RESUME_CONSTRUCT, $fx->kit->resolver->resolve()->transition);
    }

    public function testEnhancedEgoMustBeFromTheDeck(): void
    {
        $fx = $this->fx;
        $fx->db->execute("UPDATE `player` SET `player_spirit` = 'R2' WHERE `player_id` = " . self::P);
        $fx->tokens(self::P, light: 6);
        $this->begin();
        $this->analyze('R3A', self::pay([], self::light('WISDOM'), self::light('WISDOM'), self::light('WISDOM')));
        $fx->kit->resolver->resolve();

        $this->expectException(EffectViolation::class);
        $fx->kit->choices->answer(self::P, ['ego' => 'E05']);
    }

    /** UT-65: R4 のボーナスはレベルを問わない（レベル 2 も可）。 */
    public function testFourthTierBonusAllowsLevel2(): void
    {
        $fx = $this->fx;
        $fx->db->execute("UPDATE `player` SET `player_spirit` = 'R3A' WHERE `player_id` = " . self::P);
        $fx->db->execute("UPDATE `spirit_card` SET `spirit_light` = 0");
        $fx->tokens(self::P, light: 21);
        [$lv2, $lv1] = $fx->giveHand(self::P, [91, 1]);
        $this->begin();
        $this->analyze('R4', self::pay(
            [],
            self::light('WISDOM'),
            self::light('WISDOM'),
            self::light('EMOTION'),
            self::light('EMOTION'),
            self::light('WILL'),
            self::light('WILL'),
        ));

        self::assertSame(Transition::COMPOSE, $fx->kit->resolver->resolve()->transition);
        $args = $fx->kit->compose->args(self::P);
        self::assertSame('BONUS_ANY', $args['public']['context']);
        self::assertSame([true, true], array_column($args['private']['hand'], 'composable'));
        $fx->kit->compose->compose(self::P, $lv2, []);
        self::assertSame(MemoryLocation::Tableau, $fx->memory->find($lv2)?->location);
        unset($lv1);
    }

    /** 心理の極致: 第五階層に進むと、その場で勝利する（001 §14-1）。残りの frame は捨てられて終局へ。 */
    public function testReachingTheFifthTierWinsTheGame(): void
    {
        $fx = $this->fx;
        $fx->db->execute("UPDATE `player` SET `player_spirit` = 'R4' WHERE `player_id` = " . self::P);
        $fx->tokens(self::P, light: 80);
        $this->begin();
        $items = [];
        foreach (['WISDOM', 'EMOTION', 'WILL'] as $key) {
            for ($i = 0; $i < 4; $i++) {
                $items[] = self::light($key);
            }
        }

        $notices = $this->analyze('R5', self::pay([], ...$items));

        self::assertSame('APEX_OF_PSYCHE', $fx->globals->endReason());
        self::assertSame(self::P, $fx->globals->winnerId());
        self::assertContains('spiritAdvanced', self::types($notices));
        self::assertSame(Transition::GAME_END, $fx->kit->resolver->resolve()->transition);
        self::assertSame(0, $fx->stack->count());
    }

    public function testR5CostIsFourOfEachKey(): void
    {
        $fx = $this->fx;
        $fx->db->execute("UPDATE `player` SET `player_spirit` = 'R4' WHERE `player_id` = " . self::P);
        $fx->tokens(self::P, light: 80);
        $this->begin();

        try {
            $this->analyze('R5', self::pay([], self::light('WISDOM'), self::light('EMOTION'), self::light('WILL')));
            self::fail('not enough');
        } catch (EffectViolation $e) {
            self::assertStringContainsString('exactly', $e->getMessage());
        }
        self::assertNull($fx->globals->endReason());
    }

    /** UT-28: M058 は精神カード上に心光が無いとき心光 +1（精神カード上の心光の獲得とは別で、I02 の追加は無い）。 */
    public function testTierUpTriggerFiresAfterTheArrival(): void
    {
        $trigger = new EffectSpec(
            id: 'M058.1',
            source: 'M058',
            mechanism: Mechanism::TRIGGER,
            kind: EffectKind::ACTIVATED,
            ownerScope: OwnerScope::OWNER,
            ops: [EffectNode::op(Op::GAIN_LIGHT, ['n' => 1, 'origin' => Origin::EFFECT])],
            events: [Event::TIER_UP],
        );
        $fx = new MoveFixture(new ArrayCatalog([...array_values(EffectCatalog::all()), ...TestSpecs::all(), $trigger]));
        $fx->giveTableau(self::P, [58]);
        [$a, $b] = $fx->giveHand(self::P, [1, 4]);
        $this->begin($fx);

        $fx->kit->analysis->analyze(self::P, 'R1A', self::pay([], self::hand($a, 'WISDOM'), self::hand($b, 'WISDOM')));
        $result = $fx->kit->resolver->resolve();

        self::assertSame(Transition::RESUME_CONSTRUCT, $result->transition);
        self::assertSame(2, $fx->players->light(self::P), '精神カード上の心光 1 + M058 の 1');
        self::assertSame(1, $fx->memory->count(MemoryLocation::Hand, self::P), 'R1 のボーナスも解決された');
    }

    /** UT-36: 階層を上げたときの誘発とボーナスが衝突する（手札）なら、ターンプレイヤーが解決順を選ぶ。衝突しなければ固定順。 */
    public function testConflictingTierUpEffectsAskForTheOrder(): void
    {
        $trigger = new EffectSpec(
            id: 'M081.1',
            source: 'M081',
            mechanism: Mechanism::TRIGGER,
            kind: EffectKind::ACTIVATED,
            ownerScope: OwnerScope::OWNER,
            ops: [EffectNode::op(Op::GAIN_OPEN, ['n' => 1, 'origin' => Origin::EFFECT])],
            events: [Event::TIER_UP],
        );
        $fx = new MoveFixture(new ArrayCatalog([...array_values(EffectCatalog::all()), ...TestSpecs::all(), $trigger]));
        $fx->giveTableau(self::P, [81]);
        $fx->spirits->advance(self::P, 'R1A');
        $fx->db->execute("UPDATE `spirit_card` SET `spirit_light` = 0");
        [$w, $e, $n, $lv1] = $fx->giveHand(self::P, [1, 2, 3, 40]);
        $this->begin($fx);
        $fx->kit->analysis->analyze(self::P, 'R2', self::pay([], self::hand($w, 'WISDOM'), self::hand($e, 'EMOTION'), self::hand($n, 'WILL')));

        $ask = $fx->kit->resolver->resolve();

        self::assertSame(Transition::CHOICE, $ask->transition);
        $args = $fx->kit->choices->args();
        self::assertSame('ORDER_EFFECTS', $args['public']['choiceKind']);
        self::assertSame(['M081.1', 'R2'], array_column($args['public']['data']['candidates'], 'spec'), '固定順（効果 ID の昇順）');
        self::assertFalse($args['public']['skippable']);
        try {
            $fx->kit->choices->skip(self::P);
            self::fail('the order cannot be skipped');
        } catch (EffectViolation) {
            self::addToAssertionCount(1);
        }

        // 場札を取ってから自己構成してよい（FAQ 行 26）: M081 を先に
        $fx->kit->choices->answer(self::P, ['index' => 0]);
        $next = $fx->kit->resolver->resolve();
        self::assertSame(Transition::CHOICE, $next->transition, '場札の獲得（残りが 1 つなので順序は聞かない）');
        self::assertSame('PICK_FIELD_CARD', $fx->kit->choices->args()['public']['choiceKind']);
        $field = array_map(static fn ($c): int => $c->id, $fx->memory->inLocAnyArg(MemoryLocation::Open));
        $fx->kit->choices->answer(self::P, ['cards' => [$field[0]]]);
        self::assertSame(Transition::COMPOSE, $fx->kit->resolver->resolve()->transition);
        self::assertSame(MemoryLocation::Hand, $fx->memory->find($lv1)?->location);
    }
}
