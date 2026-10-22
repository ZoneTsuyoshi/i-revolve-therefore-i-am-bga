<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Cards;

use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectEngine;
use Bga\Games\IRevolveThereforeIAm\Effects\Value\ValueEvaluator;
use Bga\Games\IRevolveThereforeIAm\Material\ComposeContext;
use Bga\Games\IRevolveThereforeIAm\Material\EffectCatalog;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\TokenKind;
use Bga\Games\IRevolveThereforeIAm\Material\ValueExpr;
use IRevolveTests\Support\ArrayCatalog;
use IRevolveTests\Support\ArrayGame;
use IRevolveTests\Support\CardBench;
use IRevolveTests\Support\QueryKit;
use PHPUnit\Framework\TestCase;

/**
 * 修飾・値式・REPEAT・自己構成済みの交換（008 §7.2。UT-31、UT-41、UT-50）。
 */
final class ModifierCardsTest extends TestCase
{
    /** UT-31: M068 は 4 部屋以上で、最初の自己構成アクションにだけエネルギー -1。2 回目と無料の自己構成には効かない。 */
    public function testM068OnlyForTheFirstComposeAction(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M068']);
        [$m073] = $kit->game->giveHand(1, ['M073']); // エネルギー 4

        self::assertSame(4, $kit->compose->need(1, $m073, ComposeContext::PHASE), '3 部屋まで');
        $kit->game->roomsEntered = 4;
        self::assertSame(3, $kit->compose->need(1, $m073, ComposeContext::PHASE));
        $kit->game->composeActions = 1;
        self::assertSame(4, $kit->compose->need(1, $m073, ComposeContext::PHASE), '2 回目の自己構成アクションには効かない');
        self::assertSame(0, $kit->compose->need(1, $m073, ComposeContext::BONUS_ANY), '無料の自己構成');
    }

    /** UT-50: E01 は自己構成のエネルギー -1、E02 は精神分析コスト -1。 */
    public function testE01AndE02(): void
    {
        $kit = new QueryKit();
        $kit->game->giveEgos(1, ['E01', 'E02']);
        [$m001] = $kit->game->giveHand(1, ['M001']);

        self::assertSame(1, $kit->compose->need(1, $m001, ComposeContext::PHASE));
        self::assertSame(1, $kit->analysis->reduction(1));
    }

    /** 値式（008 §3.3）: 枚数、鍵の本数（万能鍵は数えない。R8-4・R8-19）、商。 */
    public function testValueExpressions(): void
    {
        $game = new ArrayGame();
        $game->giveTableau(1, ['M094', 'M001', 'M082', 'M091', 'M003', 'M006']); // 知恵 2+1+1、感情 1、意志 2、万能 2

        self::assertSame(6, ValueEvaluator::evaluate(ValueExpr::composedCount(), $game, 1));
        self::assertSame(4, ValueEvaluator::evaluate(ValueExpr::composedKeys(KeyType::WISDOM), $game, 1));
        self::assertSame(2, ValueEvaluator::evaluate(ValueExpr::composedKeys(KeyType::WILL), $game, 1));
        self::assertSame(2, ValueEvaluator::evaluate(ValueExpr::floorDiv(ValueExpr::composedCount(), 3), $game, 1));
        self::assertSame(7, ValueEvaluator::evaluate(7, $game, 1));
    }

    /** REPEAT（M090）: 値式の回数だけ子を繰り返す。0 回なら飛ばす。 */
    public function testRepeatRunsTheChildTheComputedNumberOfTimes(): void
    {
        foreach ([[2, 0], [3, 1], [9, 3]] as [$count, $expected]) {
            $game = new ArrayGame();
            $game->giveTableau(1, array_fill(0, $count, 'M001'));
            $engine = new EffectEngine($game, $game, ArrayCatalog::withTestSpecs());
            $result = $engine->run($engine->start('M090.1', 1));

            self::assertTrue($result->isDone());
            self::assertSame($expected, $game->tokens[1][TokenKind::WILD_MOVE_KEY->value] ?? 0, "{$count} 枚");
        }
    }

    /** 交換（SWAP_SAME_COST）で自己構成済みカードが場を離れるとき、通知にカード ID が載り、I08 の下のカードは捨札へ（N26・N15）。 */
    public function testSwapOutNotifiesTheLeavingCardAndReleasesTheCardsUnder(): void
    {
        $b = new CardBench();
        [$tableau] = $b->fx->giveTableau(CardBench::P, [1]);
        [$hand, $under] = $b->fx->giveHand(CardBench::P, [4, 2]);
        $b->fx->memory->move($under, MemoryLocation::UnderCard, $tableau);

        $b->fx->kit->mutator->swapComposedAndHand(CardBench::P, $tableau, $hand);
        $notices = $b->fx->kit->mutator->drain();

        $types = array_map(static fn ($n): string => $n->type, $notices);
        self::assertSame(['cardsExchanged', 'cardsDiscarded'], $types);
        self::assertSame('TABLEAU_HAND', $notices[0]->args['kind']);
        self::assertSame($hand, $notices[0]->args['pairs'][0]['out']['id']);
        self::assertSame($tableau, $notices[0]->args['pairs'][0]['in']['id']);
        self::assertSame('UNDER_RELEASED', $notices[1]->args['reason']);
        self::assertSame([$under], array_column($notices[1]->args['cards'], 'id'));
        self::assertSame(MemoryLocation::Discard1, $b->fx->memory->find($under)?->location);
        self::assertSame(MemoryLocation::Tableau, $b->fx->memory->find($hand)?->location);
    }

    /** 定義の整合: 手札に触る効果の触る資源（ボーナスの自己構成は ND を消費しない。R8-6）。 */
    public function testBonusComposeDoesNotTouchTheNextDiscount(): void
    {
        $bonus = array_map(static fn ($t): string => $t->toString(), EffectCatalog::get('R2')->touches());
        $floor = array_map(static fn ($t): string => $t->toString(), EffectCatalog::get('F04')->touches());

        self::assertNotContains('NDw', $bonus);
        self::assertContains('NDw', $floor);
    }
}
