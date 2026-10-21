<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Effects\Ops;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectEngine;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\EngineResult;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\UnsupportedOp;
use Bga\Games\IRevolveThereforeIAm\Material\ActionKind;
use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;
use Bga\Games\IRevolveThereforeIAm\Material\EffectKind;
use Bga\Games\IRevolveThereforeIAm\Material\EffectNode;
use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\Mechanism;
use Bga\Games\IRevolveThereforeIAm\Material\Op;
use Bga\Games\IRevolveThereforeIAm\Material\Origin;
use Bga\Games\IRevolveThereforeIAm\Material\OwnerScope;
use Bga\Games\IRevolveThereforeIAm\Material\TokenKind;
use IRevolveTests\Support\ArrayCatalog;
use IRevolveTests\Support\ArrayGame;
use PHPUnit\Framework\TestCase;

/**
 * 効果エンジンの自動解決と、原子操作 OP-01〜OP-09・OP-22〜OP-24・OP-26（008 §11.2 の順 3）の検査。
 * 想起効果（§7.3）・床アクション（§7.4）・精神カード（§7.5）の定義を通して動かす。
 *
 * UT 番号は、エンジンの部分だけで確かめられるものに付ける（想起の発動者の順序・手札上限・勝利判定の実装は後続）。
 */
final class EffectEngineTest extends TestCase
{
    private ArrayGame $game;

    private EffectEngine $engine;

    protected function setUp(): void
    {
        $this->game = new ArrayGame();
        $this->engine = new EffectEngine($this->game, $this->game, ArrayCatalog::withTestSpecs());
    }

    /**
     * イベントのある操作の後で止まった（yielded）frame を、続きの節まで進める。誘発した効果の解決は呼び出し側（`FrameRunner`）の担当で、
     * ここでは積まずに続けるだけ。イベントは合わせて返す。
     */
    private static function settle(EffectEngine $engine, EffectFrame $frame, EngineResult $result): EngineResult
    {
        $events = $result->events;
        while ($result->yielded) {
            $result = $engine->run($frame);
            array_push($events, ...$result->events);
        }

        return new EngineResult($result->choice, $events, false);
    }

    /**
     * frame を作って進められるところまで進める。
     *
     * @return array{EffectFrame, EngineResult}
     */
    private function begin(string $specId, int $owner = 1): array
    {
        $frame = $this->engine->start($specId, $owner);

        return [$frame, self::settle($this->engine, $frame, $this->engine->run($frame))];
    }

    /**
     * @param list<GameEvent> $events
     * @return list<string>
     */
    private function eventNames(array $events): array
    {
        return array_map(static fn (GameEvent $e): string => $e->event->name, $events);
    }

    // ---- 想起効果 ----

    public function testRecallDrawTakesOneFromTheDeck(): void
    {
        $this->game->setDeck(['M001', 'M002']);
        [, $result] = $this->begin('RC-01');

        self::assertTrue($result->isDone());
        self::assertCount(1, $this->game->handIds(1));
        self::assertCount(1, $this->game->deck);
        self::assertSame([Origin::RECALL], $this->game->drawOrigins);
        self::assertSame(1, $this->game->victoryChecks);
    }

    public function testEmptyDeckRaisesTheExhaustedFlag(): void
    {
        [, $result] = $this->begin('RC-01');

        self::assertTrue($result->isDone());
        self::assertTrue($this->game->deckExhausted);
        self::assertSame([], $this->game->handIds(1));
    }

    /** UT-56: OPEN_DRAW_1 は場札 0 枚なら無視。1 枚なら選ばずに取る。複数なら選ぶ。 */
    public function testOpenDrawVariants(): void
    {
        [, $result] = $this->begin('RC-02');
        self::assertTrue($result->isDone());
        self::assertSame([], $this->game->handIds(1));

        [$a] = $this->game->setField(['M001']);
        [, $result] = $this->begin('RC-02');
        self::assertTrue($result->isDone());
        self::assertSame([$a], $this->game->handIds(1));
        self::assertSame([], $this->game->fieldIds());

        [$b, $c, $d] = $this->game->setField(['M002', 'M003', 'M004']);
        [$frame, $result] = $this->begin('RC-02');
        self::assertFalse($result->isDone());
        self::assertSame(ChoiceKind::PICK_FIELD_CARD, $result->choice?->kind);
        self::assertSame([$b, $c, $d], $result->choice->data['candidates']);

        $result = self::settle($this->engine, $frame, $this->engine->answer($frame, ['cards' => [$c]]));
        self::assertTrue($result->isDone());
        self::assertSame([$a, $c], $this->game->handIds(1));
        self::assertSame([$b, $d], $this->game->fieldIds());
    }

    /** UT-56: 複数の発動者は発動順に場札を取り合う。 */
    public function testActivatorsTakeFieldCardsInOrder(): void
    {
        [$a, $b] = $this->game->setField(['M001', 'M002']);
        [$first] = $this->begin('RC-02', 1);
        $this->engine->answer($first, ['cards' => [$a]]);
        [, $second] = $this->begin('RC-02', 2); // 残りが 1 枚なので選ばずに取る

        self::assertTrue($second->isDone());
        self::assertSame([$a], $this->game->handIds(1));
        self::assertSame([$b], $this->game->handIds(2));
    }

    public function testPickFieldCardRejectsWrongAnswers(): void
    {
        [$a, $b] = $this->game->setField(['M001', 'M002', 'M003']);
        $other = $this->game->card('M004');
        [$frame] = $this->begin('RC-02');

        $wrongAnswers = [['cards' => []], ['cards' => [$a, $b]], ['cards' => [$other]], ['cards' => ['x']], ['cards' => [$a, $a]], []];
        foreach ($wrongAnswers as $answer) {
            try {
                $this->engine->answer($frame, $answer);
                self::fail('Expected a violation');
            } catch (EffectViolation) {
                self::addToAssertionCount(1);
            }
        }
        self::assertSame([], $this->game->handIds(1));
    }

    public function testRecallLightEmitsLightGained(): void
    {
        [, $result] = $this->begin('RC-03');

        self::assertSame(1, $this->game->lights[1]);
        self::assertSame([[1, 1, 'RECALL']], $this->game->lightLog);
        self::assertSame(['LIGHT_GAINED'], $this->eventNames($result->events));
        self::assertSame(['count' => 1, 'origin' => 'RECALL'], $result->events[0]->payload);
    }

    /** UT-55: NEXT_COMPOSE_DISCOUNT_1 はターンプレイヤーだけに効き、それ以外の発動は何も起こさない。 */
    public function testNextComposeDiscountOnlyForTheTurnPlayer(): void
    {
        $this->begin('RC-04', 2);
        self::assertSame(0, $this->game->nextDiscount);

        $this->begin('RC-04', 1);
        $this->begin('RC-04', 1); // M062 で 2 回 → 2 重
        self::assertSame(2, $this->game->nextDiscount);
    }

    /** UT-59: DISCARD_FOR_LIGHT は 0 枚可。捨てた枚数だけ心光（レベル 2 も 1 枚）。 */
    public function testDiscardForLight(): void
    {
        [$a, $b, $c] = $this->game->giveHand(1, ['M001', 'M085', 'M002']);
        [$frame, $result] = $this->begin('RC-05');

        self::assertSame(ChoiceKind::SELECT_DISCARD_FOR_LIGHT, $result->choice?->kind);
        $result = self::settle($this->engine, $frame, $this->engine->answer($frame, ['cards' => [$a, $b]]));

        self::assertTrue($result->isDone());
        self::assertSame(2, $this->game->lights[1]);
        self::assertSame([$c], $this->game->handIds(1));
        self::assertSame([$a, $b], $this->game->discard);
        self::assertSame(['LIGHT_GAINED'], $this->eventNames($result->events));
    }

    public function testDiscardForLightZeroSkipAndEmptyHand(): void
    {
        $this->game->giveHand(1, ['M001']);
        [$frame] = $this->begin('RC-05');
        $result = self::settle($this->engine, $frame, $this->engine->answer($frame, ['cards' => []]));
        self::assertTrue($result->isDone());
        self::assertSame([], $this->game->lights);
        self::assertCount(1, $this->game->handIds(1));

        [$frame] = $this->begin('RC-05');
        $result = $this->engine->skip($frame);
        self::assertTrue($result->isDone());
        self::assertSame([], $this->game->lights);

        $empty = new ArrayGame();
        $engine = new EffectEngine($empty, $empty, ArrayCatalog::withTestSpecs());
        self::assertTrue($engine->run($engine->start('RC-05', 1))->isDone());
    }

    public function testDiscardForLightRejectsCardsOutsideTheHand(): void
    {
        $this->game->giveHand(1, ['M001']);
        $stranger = $this->game->card('M002');
        [$frame] = $this->begin('RC-05');

        $this->expectException(EffectViolation::class);
        self::settle($this->engine, $frame, $this->engine->answer($frame, ['cards' => [$stranger]]));
    }

    /** UT-59: SWAP_HAND_FIELD は同数、0 枚可。手札から場へ出せるのはレベル 1 だけ。 */
    public function testSwapHandAndField(): void
    {
        [$h1, $h2] = $this->game->giveHand(1, ['M001', 'M085']); // M085 はレベル 2
        [$f1, $f2] = $this->game->setField(['M002', 'M003']);
        [$frame, $result] = $this->begin('RC-06');

        self::assertSame(ChoiceKind::SELECT_SWAP_HAND_FIELD, $result->choice?->kind);
        foreach (
            [
            ['hand' => [$h1], 'field' => []],            // 同数でない
            ['hand' => [$h2], 'field' => [$f1]],         // レベル 2 は出せない
            ['hand' => [$h1], 'field' => [999]],         // 場札に無い
            ['hand' => [$h1, $h1], 'field' => [$f1, $f2]],
            ] as $answer
        ) {
            try {
                $this->engine->answer($frame, $answer);
                self::fail('Expected a violation');
            } catch (EffectViolation) {
                self::addToAssertionCount(1);
            }
        }

        $result = self::settle($this->engine, $frame, $this->engine->answer($frame, ['hand' => [$h1], 'field' => [$f2]]));
        self::assertTrue($result->isDone());
        self::assertEqualsCanonicalizing([$h2, $f2], $this->game->handIds(1));
        self::assertEqualsCanonicalizing([$f1, $h1], $this->game->fieldIds());
    }

    public function testSwapHandAndFieldZeroAndNoCandidates(): void
    {
        $this->game->giveHand(1, ['M001']);
        $this->game->setField(['M002']);
        [$frame] = $this->begin('RC-06');
        self::assertTrue($this->engine->answer($frame, ['hand' => [], 'field' => []])->isDone());
        self::assertCount(1, $this->game->handIds(1));

        // 場札が無ければ聞かない / レベル 1 の手札が無ければ聞かない
        $this->game->setField([]);
        self::assertTrue($this->begin('RC-06')[1]->isDone());
        $this->game->setField(['M002']);
        $this->game->hand[1] = [$this->game->card('M085')];
        self::assertTrue($this->begin('RC-06')[1]->isDone());
    }

    /** UT-59: SWAP_SAME_COST は同コスト、同名制限あり。自己構成ではない扱いなので勝利判定だけを呼ぶ。 */
    public function testSwapComposedHandNeedsSameCostAndRespectsNames(): void
    {
        // 自己構成済み: M001(エネルギー 2)・M002(2)。手札: M002(2)・M003(2)・M085(5)
        [$t1, $t2] = $this->game->giveTableau(1, ['M001', 'M002']);
        [$h2, $h3, $h85] = $this->game->giveHand(1, ['M002', 'M003', 'M085']);
        [$frame, $result] = $this->begin('RC-07');

        // OPTIONAL の via が操作自身の選択なので、確認は挟まず直接 SELECT_SWAP_SAME_COST を聞く
        self::assertSame(ChoiceKind::SELECT_SWAP_SAME_COST, $result->choice?->kind);
        $pairs = $result->choice->data['pairs'];
        self::assertContains([$t1, $h3], $pairs);
        self::assertContains([$t2, $h2], $pairs);   // 同名でも、その自己構成済みカード自身と交換するなら可
        self::assertNotContains([$t1, $h2], $pairs); // M002 が別に自己構成済み
        self::assertNotContains([$t1, $h85], $pairs); // コストが違う

        foreach ([['tableau' => $t1, 'hand' => $h2], ['tableau' => $t1, 'hand' => $h85], ['tableau' => 1], []] as $answer) {
            try {
                $this->engine->answer($frame, $answer);
                self::fail('Expected a violation');
            } catch (EffectViolation) {
                self::addToAssertionCount(1);
            }
        }
        $before = $this->game->victoryChecks;
        $result = self::settle($this->engine, $frame, $this->engine->answer($frame, ['tableau' => $t1, 'hand' => $h3]));
        self::assertTrue($result->isDone());
        self::assertSame([$h3, $t2], $this->game->tableauIds(1));
        self::assertContains($t1, $this->game->handIds(1));
        self::assertSame($before + 1, $this->game->victoryChecks);
    }

    public function testSwapComposedHandSkipAndNoCandidate(): void
    {
        $this->game->giveTableau(1, ['M001']);
        $this->game->giveHand(1, ['M003']);
        [$frame] = $this->begin('RC-07');
        self::assertTrue($this->engine->skip($frame)->isDone());
        self::assertSame(['M001'], array_map(fn (int $id): string => $this->game->cardType($id), $this->game->tableauIds(1)));

        $this->game->hand[1] = [$this->game->card('M085')];
        self::assertTrue($this->begin('RC-07')[1]->isDone()); // 同コストの候補が無ければ聞かない
    }

    /** M098 の手札での値（MD-02）を、同じコストの判定に使う。 */
    public function testSwapComposedHandUsesTheCurrentHeldEnergy(): void
    {
        // 感情鍵 3 本 → M098 は 4。自己構成済みの M095(エネルギー 6)とは合わず、エネルギー 4 のカードとなら合う
        $this->game->giveTableau(1, ['M002', 'M002', 'M002']);
        [$m098] = $this->game->giveHand(1, ['M098']);
        [, $result] = $this->begin('RC-07');
        self::assertTrue($result->isDone()); // M002(エネルギー 2) と M098(4) は合わない

        $four = $this->game->giveTableau(1, ['M073']); // 知恵鍵、エネルギー 4
        [, $result] = $this->begin('RC-07');
        self::assertSame([[$four[0], $m098]], $result->choice?->data['pairs']);
    }

    /** UT-54: ALT_* は種類ごとに 1 つ記録し（割引は大きいほう）、ターンプレイヤー以外は記録しない。 */
    public function testFloorAlternativesAreRecordedOncePerKind(): void
    {
        $this->begin('RC-08', 2);
        self::assertSame([], $this->game->alternatives);

        $this->begin('RC-08');
        $this->begin('RC-08'); // M062 の 2 回目
        self::assertSame([['kind' => ActionKind::ANALYSIS, 'discount' => 0]], $this->game->alternatives);

        $this->begin('RC-10');
        $this->begin('RC-08');
        $this->begin('RC-09');
        $this->begin('RC-11');
        self::assertSame(
            [['kind' => ActionKind::ANALYSIS, 'discount' => 1], ['kind' => ActionKind::COMPOSE, 'discount' => 1]],
            $this->game->alternatives,
        );
        self::assertSame(0, $this->game->victoryChecks);
    }

    /** 想起効果の移動は、確認（CONFIRM_MOVE）を聞いてから移動に入る。移動そのもの（OP-15）は ActionOpsTest。 */
    public function testRecallMoveAsksForConfirmation(): void
    {
        [$frame, $result] = $this->begin('RC-12');
        self::assertSame(ChoiceKind::CONFIRM_MOVE, $result->choice?->kind);

        $declined = $this->engine->skip($frame);
        self::assertTrue($declined->isDone());
        self::assertSame([], $this->game->moves);
    }

    // ---- 床アクション ----

    /** UT-62 / UT-37 の素地: DEMETER は 3 枚引く。EV-08（床の要素）が起きる。 */
    public function testDemeter(): void
    {
        $this->game->setDeck(['M001', 'M002', 'M003', 'M004']);
        [, $result] = $this->begin('F01');

        self::assertTrue($result->isDone());
        self::assertCount(3, $this->game->handIds(1));
        self::assertSame(['FLOOR_GAIN'], $this->eventNames($result->events));
        self::assertSame(['element' => 'DRAW'], $result->events[0]->payload);
        self::assertSame([Origin::FLOOR], $this->game->drawOrigins);
    }

    /** UT-62: ATHENA・APOLLO は 3 枚引いてから 1 枚捨てる。捨てるのは必須（スキップ不可）。 */
    public function testAthenaAndApolloDiscardIsMandatory(): void
    {
        foreach (['F03', 'F08'] as $floor) {
            $game = new ArrayGame();
            $engine = new EffectEngine($game, $game, ArrayCatalog::withTestSpecs());
            $game->setDeck(['M001', 'M002', 'M003', 'M004']);
            $frame = $engine->start($floor, 1);
            $result = self::settle($engine, $frame, $engine->run($frame));

            self::assertSame(ChoiceKind::PICK_DISCARD, $result->choice?->kind, $floor);
            self::assertCount(3, $game->handIds(1)); // 引いたカードを含む手札から捨てる
            try {
                $engine->skip($frame);
                self::fail('Mandatory discard');
            } catch (EffectViolation) {
                self::addToAssertionCount(1);
            }
            $chosen = $game->handIds(1)[1];
            $result = self::settle($engine, $frame, $engine->answer($frame, ['cards' => [$chosen]]));
            self::assertTrue($result->isDone());
            self::assertCount(2, $game->handIds(1));
            self::assertSame([$chosen], $game->discard);
        }
    }

    public function testAthenaWithAShortHandDiscardsEverything(): void
    {
        $this->game->setDeck([]);
        $this->game->giveHand(1, ['M001']);
        [, $result] = $this->begin('F03');

        // 手札が 1 枚だけ（引けなかった）。ちょうど n 枚なので選ばずに捨てる
        self::assertTrue($result->isDone());
        self::assertSame([], $this->game->handIds(1));
    }

    /** UT-62: ARES・ARTEMIS は場札 +1 → 心光 +1。 */
    public function testAresAndArtemis(): void
    {
        foreach (['F05', 'F11'] as $floor) {
            $game = new ArrayGame();
            $engine = new EffectEngine($game, $game, ArrayCatalog::withTestSpecs());
            [$a, $b] = $game->setField(['M001', 'M002']);
            $frame = $engine->start($floor, 1);
            $result = self::settle($engine, $frame, $engine->run($frame));
            self::assertSame(ChoiceKind::PICK_FIELD_CARD, $result->choice?->kind);
            $result = self::settle($engine, $frame, $engine->answer($frame, ['cards' => [$b]]));

            self::assertTrue($result->isDone());
            self::assertSame([$b], $game->handIds(1));
            self::assertSame(1, $game->lights[1]);
            self::assertSame(
                ['FLOOR_GAIN', 'LIGHT_GAINED', 'FLOOR_GAIN'],
                $this->eventNames($result->events),
                $floor,
            );
            self::assertSame($a, $game->fieldIds()[0]);
        }
    }

    /** UT-62: HESTIA は場札 +1・山札 +1・心光 +1 の順。 */
    public function testHestia(): void
    {
        $this->game->setField(['M001']);
        $this->game->setDeck(['M002']);
        [, $result] = $this->begin('F07');

        self::assertTrue($result->isDone());
        self::assertCount(2, $this->game->handIds(1));
        self::assertSame(1, $this->game->lights[1]);
        self::assertSame(
            ['FLOOR_GAIN', 'FLOOR_GAIN', 'LIGHT_GAINED', 'FLOOR_GAIN'],
            $this->eventNames($result->events),
        );
        self::assertSame(['OPEN', 'DRAW', 'LIGHT'], array_values(array_filter(array_map(
            static fn (GameEvent $e): ?string => is_string($e->payload['element'] ?? null) ? $e->payload['element'] : null,
            $result->events,
        ))));
    }

    /** UT-62: APHRODITE は場札 2 枚か心光 2 を選ぶ（分岐は 51 で聞く）。 */
    public function testAphroditeBranches(): void
    {
        [$a, $b, $c] = $this->game->setField(['M001', 'M002', 'M003']);
        [$frame, $result] = $this->begin('F09');
        self::assertNull($result->choice?->kind);
        self::assertSame(['branches' => 2], $result->choice->data);

        try {
            self::settle($this->engine, $frame, $this->engine->answer($frame, ['branch' => 2]));
            self::fail('Unknown branch');
        } catch (EffectViolation) {
            self::addToAssertionCount(1);
        }

        $result = self::settle($this->engine, $frame, $this->engine->answer($frame, ['branch' => 1]));
        self::assertTrue($result->isDone());
        self::assertSame(2, $this->game->lights[1]);
        self::assertSame([], $this->game->handIds(1));

        [$frame] = $this->begin('F09');
        $result = self::settle($this->engine, $frame, $this->engine->answer($frame, ['branch' => 0]));
        self::assertSame(ChoiceKind::PICK_FIELD_CARD, $result->choice?->kind);
        $result = self::settle($this->engine, $frame, $this->engine->answer($frame, ['cards' => [$a, $c]]));
        self::assertTrue($result->isDone());
        self::assertSame(2, $this->game->lights[1]); // 心光は増えていない（選ばなかった分岐は飛ばす）
        self::assertSame([$a, $c], $this->game->handIds(1));
        self::assertSame([$b], $this->game->fieldIds());
    }

    public function testHephaestusDrawBranchSkipsTheComposeBranch(): void
    {
        $this->game->setDeck(['M001', 'M002', 'M003']);
        [$frame] = $this->begin('F12');
        $result = self::settle($this->engine, $frame, $this->engine->answer($frame, ['branch' => 1]));

        self::assertTrue($result->isDone());
        self::assertCount(2, $this->game->handIds(1));
    }

    /** HEPHAESTUS の自己構成の分岐、HERMES・DIONYSUS・HADES の精神分析は、61・62 に入る行動の要求（ChoiceRequest::action）で止まる。 */
    public function testFloorsThatNeedAComposeOrAnalysisWaitForTheAction(): void
    {
        [$frame] = $this->begin('F12');
        $result = self::settle($this->engine, $frame, $this->engine->answer($frame, ['branch' => 0]));
        self::assertTrue($result->choice?->isAction());
        self::assertSame(Op::COMPOSE->value, $result->choice->data['action']);
        self::assertSame(['ctx' => 'FLOOR', 'discount' => 1, 'action' => 'OP-10'], $result->choice->data);

        foreach (['F02', 'F06', 'F10'] as $floor) {
            [, $result] = $this->begin($floor);
            self::assertTrue($result->choice?->isAction(), $floor);
            self::assertSame(Op::ANALYZE->value, $result->choice->data['action']);
        }
    }

    /** UT-62: POSEIDON の軽減は、自己構成を行わなくても（自己構成の前に）以降へ効く。 */
    public function testPoseidonSetsTheTurnDiscountBeforeTheOptionalCompose(): void
    {
        [$frame, $result] = $this->begin('F04');

        self::assertSame(1, $this->game->turnDiscount);
        self::assertSame(Op::COMPOSE->value, $result->choice?->data['action']);
        // 行わない（actCancel）と、軽減だけが残る
        $result = $this->engine->cancelAction($frame);
        self::assertTrue($result->isDone());
        self::assertSame(1, $this->game->turnDiscount);
    }

    /** UT-63（周遊ボーナス 4 種）: 該当する資源を 1 つ得る。 */
    public function testTourBonuses(): void
    {
        $this->begin('F01.TOUR');
        $this->begin('F07.TOUR');
        $this->begin('F10.TOUR');
        [, $result] = $this->begin('F04.TOUR');

        self::assertSame(
            [TokenKind::ANALYSIS->value => 1, TokenKind::WILD_MOVE_KEY->value => 1, TokenKind::COMPOSE->value => 1],
            $this->game->tokens[1],
        );
        self::assertSame(1, $this->game->lights[1]);
        // 周遊ボーナスの心光は origin = TOUR で、EV-08（床アクションの要素）を起こさない（M085〜M087 は反応しない）
        self::assertSame(['LIGHT_GAINED'], $this->eventNames($result->events));
        self::assertSame('TOUR', $result->events[0]->payload['origin']);
    }

    /** UT-65（一部）: R1 は 1 枚引く（origin = BONUS で M086 は反応しない）。 */
    public function testSpiritBonusDraw(): void
    {
        $this->game->setDeck(['M001']);
        [, $result] = $this->begin('R1B');

        self::assertTrue($result->isDone());
        self::assertSame([Origin::BONUS], $this->game->drawOrigins);
        self::assertSame([], $result->events); // origin が FLOOR ではないので EV-08 を起こさない
    }

    /** UT-65: R2・R4 は自己構成の行動（エネルギーなし）、R3 は強化自我（山が空なら飛ばす）、R5 は勝利。 */
    public function testSpiritBonuses(): void
    {
        [, $result] = $this->begin('R2');
        self::assertSame(['ctx' => 'BONUS_LV1', 'discount' => 0, 'action' => 'OP-10'], $result->choice?->data);
        [, $result] = $this->begin('R4');
        self::assertSame(['ctx' => 'BONUS_ANY', 'discount' => 0, 'action' => 'OP-10'], $result->choice?->data);

        [, $result] = $this->begin('R3A');
        self::assertTrue($result->isDone());

        [, $result] = $this->begin('R5');
        self::assertTrue($result->isDone());
        self::assertSame([1, 'APEX_OF_PSYCHE'], $this->game->won);
    }

    /** ボードに触る操作（OP-16〜OP-21）は後続のタスク（実装順 6）。 */
    public function testOpsWithoutAHandlerAreUnsupported(): void
    {
        $spec = new EffectSpec('T.9', 'T', Mechanism::ACTION, EffectKind::NONE, OwnerScope::TURN_PLAYER, [EffectNode::op(Op::PLACE_UNDER_FLOOR)]);
        $engine = new EffectEngine($this->game, $this->game, new ArrayCatalog([$spec]));

        $this->expectException(UnsupportedOp::class);
        $engine->run($engine->start('T.9', 1));
    }

    // ---- 個別の原子操作（テスト用の効果で動かす） ----

    /**
     * @param list<EffectNode> $ops
     */
    private function engineFor(array $ops, ?ArrayGame $game = null): EffectEngine
    {
        $game ??= $this->game;
        $spec = new EffectSpec('T.1', 'T', Mechanism::ACTION, EffectKind::NONE, OwnerScope::TURN_PLAYER, $ops);

        return new EffectEngine($game, $game, new ArrayCatalog([$spec]));
    }

    public function testGainTokenAndSpiritLight(): void
    {
        $engine = $this->engineFor([
            EffectNode::op(Op::GAIN_TOKEN, ['token' => TokenKind::COMPOSE, 'n' => 2]),
            EffectNode::op(Op::CLAIM_SPIRIT_LIGHT),
        ]);
        $this->game->spiritLight = true;
        $result = $engine->run($engine->start('T.1', 1));

        self::assertSame([TokenKind::COMPOSE->value => 2], $this->game->tokens[1]);
        self::assertSame(1, $this->game->lights[1]);
        self::assertSame([[1, 1, 'SPIRIT_CARD']], $this->game->lightLog);
        self::assertSame(['LIGHT_GAINED'], $this->eventNames($result->events));
        self::assertSame('SPIRIT_CARD', $result->events[0]->payload['origin']);
        self::assertFalse($this->game->spiritLight);

        // 心光が無ければ何も起きない（先着 1 人）
        $second = $engine->run($engine->start('T.1', 2));
        self::assertSame([], $second->events);
        self::assertArrayNotHasKey(2, $this->game->lights);
    }

    public function testAcquireRoomCard(): void
    {
        $room = $this->game->card('M010');
        $this->game->rooms[5] = $room;
        $this->game->recallRoom = 5;
        $engine = $this->engineFor([EffectNode::op(Op::ACQUIRE_ROOM_CARD)]);

        self::assertTrue($engine->run($engine->start('T.1', 1))->isDone());
        self::assertSame([$room], $this->game->handIds(1));
        self::assertNull($this->game->roomCardId(5));
    }

    public function testAcquireRoomCardRequiresTheTurnPlayerAndARecallPhase(): void
    {
        $engine = $this->engineFor([EffectNode::op(Op::ACQUIRE_ROOM_CARD)]);
        $this->expectException(\LogicException::class);
        $engine->run($engine->start('T.1', 1));
    }

    public function testDrawShortFlagsExhaustionAfterDrawingWhatIsLeft(): void
    {
        $this->game->setDeck(['M001']);
        $engine = $this->engineFor([EffectNode::op(Op::DRAW_DECK, ['n' => 3])]);
        $engine->run($engine->start('T.1', 1));

        self::assertCount(1, $this->game->handIds(1));
        self::assertTrue($this->game->deckExhausted);
    }

    public function testVictoryIsCheckedAfterOpsThatChangeTheScoreInputs(): void
    {
        $engine = $this->engineFor([
            EffectNode::op(Op::GAIN_LIGHT, ['n' => 1]),
            EffectNode::op(Op::ADD_NEXT_COMPOSE_DISCOUNT, ['n' => 1]),
            EffectNode::op(Op::GAIN_TOKEN, ['token' => TokenKind::WILD_MOVE_KEY, 'n' => 1]),
        ]);
        $frame = $engine->start('T.1', 1);
        self::settle($engine, $frame, $engine->run($frame));

        self::assertSame(2, $this->game->victoryChecks); // 心光とトークンだけ（008 §6.8）
    }

    // ---- frame ----

    /** UT-80（一部）: 待機中の frame は JSON を経由して復元でき、続きを解決できる。 */
    public function testFrameSurvivesSerialization(): void
    {
        [$a, $b] = $this->game->setField(['M001', 'M002']);
        $this->game->setDeck(['M003']);
        [$frame, $result] = $this->begin('F07');
        self::assertSame(ChoiceKind::PICK_FIELD_CARD, $result->choice?->kind);

        $json = json_encode($frame->toArray(), JSON_THROW_ON_ERROR);
        $restored = EffectFrame::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
        self::assertEquals($frame, $restored);

        $result = self::settle($this->engine, $restored, $this->engine->answer($restored, ['cards' => [$b]]));
        self::assertTrue($result->isDone());
        self::assertSame(1, $this->game->lights[1]);
        self::assertContains($b, $this->game->handIds(1));
        self::assertContains($a, $this->game->fieldIds());
    }

    public function testRunOnAWaitingFrameRepeatsTheSameChoice(): void
    {
        $this->game->setField(['M001', 'M002']);
        [$frame, $first] = $this->begin('RC-02');
        $again = self::settle($this->engine, $frame, $this->engine->run($frame));

        self::assertEquals($first->choice, $again->choice);
        self::assertSame([], $this->game->handIds(1)); // 二重に実行しない
    }

    public function testAnswerWithoutAPendingChoiceIsRejected(): void
    {
        $frame = $this->engine->start('RC-01', 1);

        $this->expectException(EffectViolation::class);
        self::settle($this->engine, $frame, $this->engine->answer($frame, []));
    }

    public function testChoiceRequestRemembersTheWaitingStep(): void
    {
        $this->game->giveHand(1, ['M001', 'M002']);
        $this->game->setDeck(['M003']);
        [, $result] = $this->begin('F03');

        self::assertSame(2, $result->choice?->step); // 0 = SEQ、1 = DRAW_DECK、2 = DISCARD_HAND
    }

    public function testUnknownSpecIsRejected(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        $this->engine->start('NOPE', 1);
    }

    public function testEventsExposeTheirEnumNames(): void
    {
        self::assertSame(Event::LIGHT_GAINED, (new GameEvent(Event::LIGHT_GAINED, 1))->event);
    }
}
