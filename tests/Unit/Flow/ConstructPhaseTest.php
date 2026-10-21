<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use IRevolveTests\Support\MoveFixture;
use PHPUnit\Framework\TestCase;

/**
 * 自己構成フェイズの窓（60。仕様書 001 §5・§11-1、006 §5.5）。
 */
final class ConstructPhaseTest extends TestCase
{
    private const P = 11;

    private MoveFixture $fx;

    protected function setUp(): void
    {
        $this->fx = new MoveFixture();
    }

    public function testArgsShowTokensAndWhetherTheyCanBeUsed(): void
    {
        $args = $this->fx->kit->construct->args(self::P);
        self::assertFalse($args['phaseComposeUsed']);
        self::assertSame([0, 0, false, false], [$args['composeTokens'], $args['analysisTokens'], $args['canComposeToken'], $args['canAnalysisToken']]);

        $this->fx->players->setToken(self::P, 'player_compose_tok', 2);
        $this->fx->players->setToken(self::P, 'player_analysis_tok', 1);
        $this->fx->giveHand(self::P, [1, 4, 2]);

        $args = $this->fx->kit->construct->args(self::P);
        self::assertSame([2, 1, true, true], [$args['composeTokens'], $args['analysisTokens'], $args['canComposeToken'], $args['canAnalysisToken']]);
    }

    /** actEndConstruct: 手札上限の確認を積んで、クリーンアップへ（Q-30）。 */
    public function testEndPushesTheHandLimitCheckAndResumesAtCleanup(): void
    {
        $this->fx->kit->construct->end(self::P);

        self::assertSame([EffectFrame::KIND_HAND_LIMIT_CHECK], array_map(static fn (EffectFrame $f): string => $f->kind, $this->fx->stack->all()));
        self::assertSame(StateId::CLEANUP, $this->fx->globals->resume());
        self::assertSame(Transition::RESUME_CLEANUP, $this->fx->kit->resolver->resolve()->transition);
    }

    /** 行動を始めるたびに、その行動の frame と手札上限の確認（単位）を積み、戻り先は 60。 */
    public function testStartingAnActionPushesTheActionAndItsHandLimitUnit(): void
    {
        $this->fx->kit->construct->startPhaseCompose(self::P);

        self::assertSame(
            [EffectFrame::KIND_COMPOSE, EffectFrame::KIND_HAND_LIMIT_CHECK],
            array_map(static fn (EffectFrame $f): string => $f->kind, $this->fx->stack->all()),
        );
        self::assertSame(StateId::CONSTRUCT, $this->fx->globals->resume());
        self::assertSame(Transition::COMPOSE, $this->fx->kit->resolver->resolve()->transition);
        // 取り消すと 60 に戻る
        $this->fx->kit->compose->cancel(self::P);
        self::assertSame(Transition::RESUME_CONSTRUCT, $this->fx->kit->resolver->resolve()->transition);
    }
}
