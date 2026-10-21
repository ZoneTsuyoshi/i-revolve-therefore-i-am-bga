<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Flow\GameProgress;
use IRevolveTests\Support\EndgameRig;
use IRevolveTests\Support\MoveFixture;
use PHPUnit\Framework\TestCase;

/**
 * 勝利判定の集約（006 §9、008 T8-11）と N21 `scoreUpdated`（007 §11）。
 */
final class WinCheckerTest extends TestCase
{
    private MoveFixture $fx;

    private EndgameRig $rig;

    protected function setUp(): void
    {
        $this->fx = new MoveFixture();
        $this->rig = new EndgameRig($this->fx);
    }

    /** 断片の結集: 自己構成スコア 20 以上（暫定は印刷スコアの合計）。19 では勝たない。 */
    public function testFragmentsNeedsTwentyPoints(): void
    {
        $types = EndgameRig::typesForScore(GameProgress::WIN_SCORE);
        $this->fx->giveTableau(11, array_slice($types, 0, -1));

        self::assertFalse($this->rig->win->check(), '最後の 1 枚を除いた合計は 20 未満');
        self::assertNull($this->fx->globals->endReason());

        $this->fx->giveTableau(11, [end($types)]);
        self::assertTrue($this->rig->win->check());
        self::assertSame('FRAGMENTS', $this->fx->globals->endReason());
        self::assertSame(11, $this->fx->globals->winnerId());
    }

    public function testNobodyWinsWithoutEnoughScore(): void
    {
        $this->fx->giveTableau(11, [EndgameRig::typesForScore(1)[0]]);

        self::assertFalse($this->rig->win->check());
        self::assertNull($this->fx->globals->endReason());
        self::assertNull($this->fx->globals->winnerId());
    }

    /** 心理の極致: 第五階層にいれば勝つ。 */
    public function testApexOfPsyche(): void
    {
        $this->fx->db->execute("UPDATE `player` SET `player_spirit` = 'R5' WHERE `player_id` = 22");

        self::assertTrue($this->rig->win->check());
        self::assertSame('APEX_OF_PSYCHE', $this->fx->globals->endReason());
        self::assertSame(22, $this->fx->globals->winnerId());
    }

    /** 既に勝者が決まっている（刹那の追憶など）なら上書きしない。 */
    public function testAnExistingResultIsKept(): void
    {
        $this->fx->giveTableau(22, EndgameRig::typesForScore(20));
        $this->fx->globals->setEndReason('INSTANT_MEMORY');
        $this->fx->globals->setWinnerId(11);

        self::assertTrue($this->rig->win->check());
        self::assertSame('INSTANT_MEMORY', $this->fx->globals->endReason());
        self::assertSame(11, $this->fx->globals->winnerId());
    }

    /** 同時に満たしたとき（Q-40）: ターンプレイヤーから時計回りで最初の 1 人。 */
    public function testSimultaneousWinnersAreOrderedFromTheTurnPlayer(): void
    {
        $first = EndgameRig::typesForScore(20);
        $this->fx->giveTableau(11, $first);
        $this->fx->giveTableau(33, EndgameRig::typesForScore(20, $first));
        $this->fx->globals->setTurnPlayer(22);

        $this->rig->win->check();

        self::assertSame(33, $this->fx->globals->winnerId(), '22 → 33 → 11 の順');
    }

    /** 同時に満たしたとき（Q-40）: 効果を解決していたプレイヤーが先頭。 */
    public function testTheResolvingPlayerComesFirst(): void
    {
        $first = EndgameRig::typesForScore(20);
        $this->fx->giveTableau(11, $first);
        $this->fx->giveTableau(33, EndgameRig::typesForScore(20, $first));
        $this->fx->globals->setTurnPlayer(11);
        $this->fx->stack->pushFront([new EffectFrame(EffectFrame::KIND_END_CHECK, 33)]);

        $this->rig->win->check();

        self::assertSame(33, $this->fx->globals->winnerId());
    }

    /** N21: 基準から変わったプレイヤーだけを、合計・内訳つきで送る。 */
    public function testScoreUpdatedListsOnlyChangedPlayers(): void
    {
        self::assertNull($this->rig->reporter->report());

        $type = EndgameRig::typesForScore(1)[0];
        $this->fx->giveTableau(22, [$type]);
        $notice = $this->rig->reporter->report();

        self::assertNotNull($notice);
        self::assertSame('scoreUpdated', $notice->type);
        $score = \Bga\Games\IRevolveThereforeIAm\Data\PublicView::definition($type)->score;
        self::assertSame([22 => ['total' => $score, 'memory' => $score, 'objective' => 0]], $notice->args['scores']);
        self::assertNull($this->rig->reporter->report(), '変わらなければ送らない');
    }

    /** スコアが変わりうる操作（勝利判定の呼び出し）で、通知が溜まる。 */
    public function testCheckVictoryQueuesScoreUpdated(): void
    {
        $this->fx->giveTableau(11, [EndgameRig::typesForScore(1)[0]]);

        $this->rig->kit->mutator->checkVictory();
        $notices = $this->rig->kit->mutator->drain();

        self::assertSame(['scoreUpdated'], array_map(static fn ($n): string => $n->type, $notices));
        self::assertArrayHasKey(11, $notices[0]->args['scores']);
    }
}
