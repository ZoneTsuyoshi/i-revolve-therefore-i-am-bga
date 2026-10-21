<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\ObjectiveRepository;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\PublicView;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Effects\Objective\ObjectiveWindow;
use Bga\Games\IRevolveThereforeIAm\Flow\GameProgress;
use Bga\Games\IRevolveThereforeIAm\Flow\Notice;
use Bga\Games\IRevolveThereforeIAm\Flow\ObjectiveService;
use Bga\Games\IRevolveThereforeIAm\Flow\ObjectiveStatusReporter;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use IRevolveTests\Support\EndgameRig;
use IRevolveTests\Support\MoveFixture;
use PHPUnit\Framework\TestCase;

/**
 * 目標の獲得（フリーアクション）、スコアへの反映、O004 の窓 81、N22・N23（008 §6.7、006 §6.3。UT-68〜UT-72）。
 */
final class ObjectiveFlowTest extends TestCase
{
    private const P = 11;

    private MoveFixture $fx;

    private EndgameRig $rig;

    private ObjectiveService $service;

    private ObjectiveRepository $objectives;

    protected function setUp(): void
    {
        $this->fx = new MoveFixture();
        $this->rig = new EndgameRig($this->fx);
        $this->objectives = new ObjectiveRepository($this->fx->db);
        // 7 枚すべてを場に出す
        $this->fx->db->execute("UPDATE `objective_card` SET `obj_state` = 'OPEN', `obj_slot` = `obj_id` - 1");
        $this->service = new ObjectiveService(
            $this->rig->board,
            $this->objectives,
            $this->fx->globals,
            static fn (int $id): string => "P{$id}",
            function (): void {
                $this->rig->kit->mutator->checkVictory();
            },
        );
    }

    private function enterRooms(int $n): void
    {
        $context = $this->fx->globals->turnContext();
        $context->roomsEnteredCount = $n;
        $this->fx->globals->setTurnContext($context);
    }

    private function ownerOf(int $objectiveId): ?int
    {
        foreach ($this->objectives->all() as $o) {
            if ($o['id'] === $objectiveId) {
                return $o['owner'];
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function types(array $notices): array
    {
        return array_map(static fn (Notice $n): string => $n->type, $notices);
    }

    /** O001 を獲得できる: N22 と、ターンの記録（1 ターン 1 枚）、勝利判定の呼び出し、スコアへの反映。 */
    public function testClaimingAnObjective(): void
    {
        $this->enterRooms(6);
        $this->fx->players->setToken(self::P, PlayerValues::COLUMN_WILD_UP, 2);

        $notices = $this->service->claim(self::P, 1, ObjectiveWindow::none());

        self::assertSame(['objectiveClaimed'], self::types($notices));
        self::assertSame(
            ['player_id' => self::P, 'player_name' => 'P11', 'obj_id' => 1, 'objective_name' => 'Wild Move Key Goal', 'points' => 2, 'i18n' => ['objective_name']],
            $notices[0]->args,
        );
        self::assertSame(self::P, $this->ownerOf(1));
        self::assertSame(1, $this->fx->globals->turnContext()->objectiveClaimed);
        self::assertSame(['scoreUpdated'], self::types($this->rig->kit->mutator->drain()), '獲得の直後に勝利判定（N21）');
    }

    /** UT-71: 1 ターン 1 枚。ターンプレイヤーだけ。場に残っていて、条件を満たすときだけ。早い者勝ち（Q-12）。 */
    public function testClaimRules(): void
    {
        $this->enterRooms(6);

        try {
            $this->service->claim(22, 1, ObjectiveWindow::none());
            self::fail('only the turn player');
        } catch (EffectViolation) {
            self::assertNull($this->ownerOf(1));
        }
        try {
            $this->service->claim(self::P, 2, ObjectiveWindow::none()); // O002: レベル 2 が無い
            self::fail('condition not met');
        } catch (EffectViolation) {
            self::assertNull($this->ownerOf(2));
        }

        $this->service->claim(self::P, 1, ObjectiveWindow::none());
        $this->fx->giveTableau(self::P, [85, 86]);
        try {
            $this->service->claim(self::P, 2, ObjectiveWindow::none());
            self::fail('one per turn');
        } catch (EffectViolation $e) {
            self::assertStringContainsString('already', $e->getMessage());
        }
        try {
            $this->service->claim(self::P, 1, ObjectiveWindow::none());
            self::fail('already owned');
        } catch (EffectViolation) {
            self::addToAssertionCount(1);
        }
        try {
            $this->service->claim(self::P, 99, ObjectiveWindow::none());
            self::fail('unknown');
        } catch (EffectViolation) {
            self::addToAssertionCount(1);
        }
    }

    /** 早い者勝ち: 他のプレイヤーが獲得した目標は獲得できない。次のターンには別の目標を獲得できる。 */
    public function testOnlyTheFirstClaimerGetsIt(): void
    {
        $this->fx->giveTableau(self::P, [85, 86]);
        $this->fx->giveTableau(22, [85 + 2, 85 + 3]);
        $this->service->claim(self::P, 2, ObjectiveWindow::none());

        $this->fx->globals->setTurnPlayer(22);
        $this->fx->globals->resetTurnContext();
        try {
            $this->service->claim(22, 2, ObjectiveWindow::none());
            self::fail('taken');
        } catch (EffectViolation) {
            self::assertSame(self::P, $this->ownerOf(2));
        }
    }

    /** UT-71: O004 は 81 の窓だけ。手札が 6 枚あっても 51 などでは獲得できない。 */
    public function testO004OnlyInTheTurnEndWindow(): void
    {
        $this->fx->giveHandAny(self::P, 6);

        try {
            $this->service->claim(self::P, 4, ObjectiveWindow::none());
            self::fail('not at turn end');
        } catch (EffectViolation) {
            self::assertNull($this->ownerOf(4));
        }
        $this->service->claim(self::P, 4, ObjectiveWindow::turnEnd());
        self::assertSame(self::P, $this->ownerOf(4));
        self::assertNull($this->rig->board->status(self::P, 4, ObjectiveWindow::turnEnd()), '獲得した目標は場から消える');
    }

    /** UT-68: 獲得した目標の得点は X の数え直し。O005 の心光を使うと、得点が下がる。 */
    public function testAnOwnedObjectiveScoreIsRecounted(): void
    {
        $this->fx->players->setToken(self::P, PlayerValues::COLUMN_LIGHT, 4);
        $context = $this->fx->globals->turnContext();
        $context->lightsGained = 4;
        $this->fx->globals->setTurnContext($context);
        $this->service->claim(self::P, 5, ObjectiveWindow::none());
        $calc = $this->scores();

        self::assertSame(3, $calc[self::P]['objective']);
        $this->fx->players->setToken(self::P, PlayerValues::COLUMN_LIGHT, 1);
        self::assertSame(1, $this->scores()[self::P]['objective']);
        $this->fx->players->setToken(self::P, PlayerValues::COLUMN_LIGHT, 0);
        self::assertSame(0, $this->scores()[self::P]['objective'], 'X = 0 は 0 点');
        self::assertSame(self::P, $this->ownerOf(5), '獲得した目標は手放さない');
    }

    /** UT-68: O001 の X は使用済みの万能移動鍵も数える。 */
    public function testO001CountsUsedKeysInTheScore(): void
    {
        $this->enterRooms(6);
        $this->service->claim(self::P, 1, ObjectiveWindow::none());
        $this->fx->players->setToken(self::P, PlayerValues::COLUMN_WILD_UP, 1);
        $this->fx->players->setToken(self::P, PlayerValues::COLUMN_WILD_USED, 3);

        self::assertSame(3, $this->scores()[self::P]['objective']);
    }

    /** UT-72: 目標の獲得で 20 点に達したら、その場で勝利（断片の結集）。 */
    public function testClaimingCanWinTheGame(): void
    {
        $types = EndgameRig::typesForScore(GameProgress::WIN_SCORE);
        $this->fx->giveTableau(self::P, array_slice($types, 0, -1));
        $this->enterRooms(6);
        $this->fx->players->setToken(self::P, PlayerValues::COLUMN_WILD_UP, 6);
        self::assertNull($this->fx->globals->endReason());

        $this->service->claim(self::P, 1, ObjectiveWindow::none()); // +4 点

        self::assertSame('FRAGMENTS', $this->fx->globals->endReason());
        self::assertSame(self::P, $this->fx->globals->winnerId());
    }

    /** UT-72: 獲得済みの目標の X が増えて 20 点に達したら、満たした瞬間に判定する（O005 の心光の獲得など）。 */
    public function testAnIncreaseInXCanWinTheGame(): void
    {
        $this->fx->giveTableau(self::P, EndgameRig::typesForScore(16));
        $this->objectives->claim(5, self::P, 1); // O005 を獲得済み。心光 0 なので 0 点
        $base = $this->scores()[self::P]['total'];
        self::assertGreaterThanOrEqual(16, $base);
        self::assertLessThan(GameProgress::WIN_SCORE, $base);
        self::assertFalse($this->rig->win->check());

        // 足りない点数 n に対応する心光の数（O005 の得点表: 1 → 1、2 → 2、4 → 3、6 → 4）
        $lights = [1 => 1, 2 => 2, 3 => 4, 4 => 6][GameProgress::WIN_SCORE - $base];
        $this->fx->players->setToken(self::P, PlayerValues::COLUMN_LIGHT, $lights);

        self::assertTrue($this->rig->win->check());
        self::assertSame('FRAGMENTS', $this->fx->globals->endReason());
        self::assertSame(self::P, $this->fx->globals->winnerId());
    }

    /** 81: O004 を獲得できるときだけ入る。手札 6 枚以上、場に残っている、このターン未獲得。山札切れでは入らない（Q-42）。 */
    public function testCleanupEntersTheTurnEndWindowOnlyForO004(): void
    {
        self::assertSame(Transition::SKIP, $this->rig->cleanup->run(), '手札 0');

        $this->fx->giveHandAny(self::P, 5);
        self::assertSame(Transition::SKIP, $this->rig->cleanup->run(), '手札 5');

        $this->fx->giveHandAny(self::P, 1);
        self::assertSame(Transition::ASK, $this->rig->cleanup->run(), '手札 6');

        $context = $this->fx->globals->turnContext();
        $context->objectiveClaimed = 1;
        $this->fx->globals->setTurnContext($context);
        self::assertSame(Transition::SKIP, $this->rig->cleanup->run(), 'このターンに獲得済み');
    }

    public function testCleanupSkipsWhenO004IsGone(): void
    {
        $this->fx->giveHandAny(self::P, 6);
        $this->fx->db->execute("UPDATE `objective_card` SET `obj_state` = 'OUT', `obj_slot` = NULL WHERE `obj_id` = 4");
        self::assertSame(Transition::SKIP, $this->rig->cleanup->run(), 'O004 が場に無い');

        $this->fx->db->execute("UPDATE `objective_card` SET `obj_state` = 'OWNED', `obj_owner` = 22 WHERE `obj_id` = 4");
        self::assertSame(Transition::SKIP, $this->rig->cleanup->run(), 'O004 は他の人が獲得済み');
    }

    /** 山札切れが成立したら、O004 より先に全員敗北（81 には入らない。Q-42）。 */
    public function testDeckOutComesBeforeTheTurnEndWindow(): void
    {
        $this->fx->giveHandAny(self::P, 6);
        $this->fx->db->execute("UPDATE `memory_card` SET `card_loc` = 'OUT', `card_loc_arg` = 0 WHERE `card_loc` = 'DECK1'");
        // 場札を 1 枚減らす（補充できずに山札切れ）
        $this->fx->db->execute("UPDATE `memory_card` SET `card_loc` = 'OUT', `card_loc_arg` = 0 WHERE `card_loc` = 'OPEN' AND `card_loc_arg` = 0");

        self::assertSame(Transition::GAME_END, $this->rig->cleanup->run());
        self::assertSame('DECK_OUT', $this->fx->globals->endReason());
    }

    /** N23: 未獲得の目標の X・得点・獲得できるか。ターンプレイヤー以外は claimable が常に false。 */
    public function testStatusesAreTheViewersOwn(): void
    {
        $this->fx->giveTableau(self::P, [85, 86]);
        $this->fx->giveTableau(22, [87]);

        $mine = $this->rig->board->statuses(self::P, ObjectiveWindow::none());
        $theirs = $this->rig->board->statuses(22, ObjectiveWindow::none());

        self::assertCount(7, $mine);
        self::assertSame(['obj_id' => 2, 'x' => 2, 'points' => 2, 'claimable' => true], $mine[1]);
        self::assertSame(['obj_id' => 2, 'x' => 1, 'points' => 1, 'claimable' => false], $theirs[1]);

        $this->service->claim(self::P, 2, ObjectiveWindow::none());
        $after = $this->rig->board->statuses(self::P, ObjectiveWindow::none());
        self::assertCount(6, $after, '獲得した目標は場から消える');
        self::assertNotContains(2, array_column($after, 'obj_id'));
        self::assertNotContains(true, array_column($after, 'claimable'), '1 ターン 1 枚');
    }

    /** N23 の差分: 変わった人にだけ、本人宛てで送る。全員分を送り直すこともできる。 */
    public function testStatusReporterSendsToTheOwnerOnlyWhenChanged(): void
    {
        $reporter = new ObjectiveStatusReporter(
            $this->rig->board,
            static fn (): array => MoveFixture::SEATS,
            static fn (): ObjectiveWindow => ObjectiveWindow::none(),
        );
        $reporter->prime();
        self::assertSame([], $reporter->report());

        $this->fx->giveTableau(22, [85]);
        $notices = $reporter->report();

        self::assertCount(1, $notices);
        self::assertSame('objectiveStatus', $notices[0]->type);
        self::assertSame(22, $notices[0]->recipient);
        self::assertSame(['obj_id' => 2, 'x' => 1, 'points' => 1, 'claimable' => false], $notices[0]->args['statuses'][1]);
        self::assertSame([], $reporter->report());
        self::assertCount(3, $reporter->all());
        self::assertSame([11, 22, 33], array_map(static fn (Notice $n): ?int => $n->recipient, $reporter->all()));
    }

    /** getAllDatas / N31 のスコアは、勝利判定と同じ計算（獲得した目標を含む）。本人にだけ myObjectiveStatus。 */
    public function testPublicViewUsesTheSameScores(): void
    {
        $this->fx->players->setToken(self::P, PlayerValues::COLUMN_WILD_UP, 4);
        $this->enterRooms(6);
        $this->service->claim(self::P, 1, ObjectiveWindow::none());
        $view = new PublicView(
            $this->fx->memory,
            $this->fx->rooms,
            $this->fx->spirits,
            $this->fx->egos,
            $this->objectives,
            $this->fx->players,
            $this->fx->globals,
        );

        $data = $view->allDatas(self::P);
        $rows = $view->results(MoveFixture::SEATS);

        self::assertSame(3, $data['playerData'][self::P]['score']);
        self::assertSame(3, $rows[0]['objective_score']);
        self::assertSame($this->scores()[self::P]['total'], $rows[0]['total']);
        self::assertCount(6, $data['myObjectiveStatus']);
        self::assertSame(['id', 'x', 'points', 'claimable'], array_keys($data['myObjectiveStatus'][0]));
        self::assertSame([], $view->allDatas(999)['myObjectiveStatus'], '観戦者には出さない');
        self::assertSame([self::P], array_keys(array_filter(
            $view->allDatas(22)['playerData'],
            static fn (array $p): bool => $p['objectives'] !== [],
        )));
    }

    /**
     * @return array<int, array{memory: int, attached: int, objective: int, total: int}>
     */
    private function scores(): array
    {
        $calc = new \Bga\Games\IRevolveThereforeIAm\Flow\GameScoreCalculator(
            $this->fx->memory,
            $this->objectives,
            \Bga\Games\IRevolveThereforeIAm\Effects\Objective\ObjectiveEvaluator::forView($this->fx->view, \IRevolveTests\Support\ArrayCatalog::withTestSpecs()),
        );

        return $calc->scores(MoveFixture::SEATS);
    }
}
