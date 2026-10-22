<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Cards;

use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use IRevolveTests\Support\CardBench;
use PHPUnit\Framework\TestCase;

/**
 * 想起効果 SWAP_SAME_COST（RC-07。71 の SELECT_SWAP_SAME_COST）を、SQLite の DB 上で通しで確かめる。
 * Studio の実機で「2 枚を選んで Confirm しても入れ替わらず、その 2 枚が使えなくなる」と指摘された不具合の固定。
 * 原因は N26 `cardsExchanged` の TABLEAU_HAND で `out` / `in` の向きがサーバーとクライアントで逆だったこと。
 * DB の状態は入れ替わっていたので、DB の状態と通知の向きの両方をここで固定する。
 */
final class SwapSameCostTest extends TestCase
{
    private const P = CardBench::P;

    /** M023（憂鬱）の想起が SWAP_SAME_COST。 */
    private function recallSwap(CardBench $b): void
    {
        $b->fx->place(self::P, 2);
        $b->fx->roomCard(2, 23);
        $b->fx->kit->recall->start();
    }

    public function testSwapMovesBothCardsInTheDatabaseAndTheNoticeNamesTheRightDirections(): void
    {
        $b = new CardBench();
        [$tab, $other] = $b->fx->giveTableau(self::P, [1, 5]);
        [$hand, $spare] = $b->fx->giveHand(self::P, [3, 7]);
        $b->fx->memory->setTapped([$tab], true);
        $before = $b->fx->memory->find($tab);
        self::assertNotNull($before);
        $this->recallSwap($b);

        $transition = $b->drive([['tableau' => $tab, 'hand' => $hand]]);

        self::assertNotSame(Transition::CHOICE, $transition);
        self::assertContains('SELECT_SWAP_SAME_COST', $b->choices);
        // 交換前の自己構成済みは手札へ（横向きは戻る）
        $out = $b->fx->memory->find($tab);
        self::assertSame(MemoryLocation::Hand, $out?->location);
        self::assertSame(self::P, $out?->locationArg);
        self::assertFalse($out?->tapped);
        // 手札のカードは自己構成済みへ。相手の並びと向きを引き継ぐ
        $in = $b->fx->memory->find($hand);
        self::assertSame(MemoryLocation::Tableau, $in?->location);
        self::assertSame(self::P, $in?->locationArg);
        self::assertSame($before->position, $in?->position);
        self::assertTrue($in?->tapped);
        // 場所の重複や欠落が無い
        $tableau = array_map(static fn ($c) => $c->id, $b->fx->memory->inLoc(MemoryLocation::Tableau, self::P));
        $handIds = array_map(static fn ($c) => $c->id, $b->fx->memory->inLoc(MemoryLocation::Hand, self::P));
        self::assertEqualsCanonicalizing([$hand, $other], $tableau);
        // 手札には、交換で戻ったカードと元からの 1 枚、想起で獲得した部屋のカード（交換とは無関係）
        self::assertContains($tab, $handIds);
        self::assertContains($spare, $handIds);
        self::assertCount(3, $handIds);
        self::assertSame(count($handIds), count(array_unique($handIds)));
        // N26: out は手札から出るカード（自己構成済みへ）、in は手札へ入るカード（HAND_OPEN と同じ向き）
        $exchanged = array_values(array_filter($b->notices, static fn ($n) => $n->type === 'cardsExchanged'));
        self::assertCount(1, $exchanged);
        self::assertSame('TABLEAU_HAND', $exchanged[0]->args['kind']);
        self::assertSame($hand, $exchanged[0]->args['pairs'][0]['out']['id']);
        self::assertSame($tab, $exchanged[0]->args['pairs'][0]['in']['id']);
    }

    public function testSwappedCardsCanBeComposedAndPaidWithAfterwards(): void
    {
        $b = new CardBench();
        [$tab] = $b->fx->giveTableau(self::P, [1]);
        [$hand, $target] = $b->fx->giveHand(self::P, [3, 6]);
        $this->recallSwap($b);
        $b->drive([['tableau' => $tab, 'hand' => $hand]]);

        // 交換で手札に戻ったカードは、コストとして捨てられ、自己構成もできる
        $b->fx->kit->construct->startPhaseCompose(self::P);
        self::assertSame(Transition::COMPOSE, $b->fx->kit->resolver->resolve()->transition);
        $b->fx->kit->compose->compose(self::P, $target, ['discard' => [$tab, 0 + $this->spare($b)]]);
        self::assertSame(MemoryLocation::Tableau, $b->fx->memory->find($target)?->location);
        self::assertSame(MemoryLocation::Discard1, $b->fx->memory->find($tab)?->location);
    }

    /** 払う 2 枚目の手札（コストを満たすため）を足す。 */
    private function spare(CardBench $b): int
    {
        return $b->fx->giveHand(self::P, [2])[0];
    }
}
