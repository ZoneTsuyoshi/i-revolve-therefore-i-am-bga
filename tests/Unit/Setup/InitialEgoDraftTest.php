<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Setup;

use Bga\GameFramework\Db\Globals;
use Bga\Games\IRevolveThereforeIAm\Data\EgoRepository;
use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryCardRepository;
use Bga\Games\IRevolveThereforeIAm\Data\ObjectiveRepository;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\PublicView;
use Bga\Games\IRevolveThereforeIAm\Data\RoomRepository;
use Bga\Games\IRevolveThereforeIAm\Data\SpiritRepository;
use Bga\Games\IRevolveThereforeIAm\Flow\Notification;
use Bga\Games\IRevolveThereforeIAm\Setup\GameSetup;
use Bga\Games\IRevolveThereforeIAm\Setup\InitialEgoDraft;
use Bga\Games\IRevolveThereforeIAm\Setup\SetupPlanner;
use IRevolveTests\Support\SeededRandom;
use IRevolveTests\Support\SqliteDatabase;
use PHPUnit\Framework\TestCase;

/**
 * 初期自我の秘密ドラフト（006 の 20〜22）: 逆順、山の受け渡し、非公開、最後の 1 枚の除外、公開。
 */
final class InitialEgoDraftTest extends TestCase
{
    /** 手番順。ドラフトは逆（33 → 22 → 11）。 */
    private const SEATS = [11, 22, 33];

    private SqliteDatabase $db;

    private EgoRepository $egos;

    private GlobalValues $globals;

    private InitialEgoDraft $draft;

    private PublicView $view;

    protected function setUp(): void
    {
        $this->db = SqliteDatabase::create(self::SEATS);
        $this->globals = new GlobalValues(new Globals());
        $this->egos = new EgoRepository($this->db);
        $rooms = new RoomRepository($this->db);
        $spirits = new SpiritRepository($this->db);
        $objectives = new ObjectiveRepository($this->db);
        $players = new PlayerValues($this->db);
        $cards = new MemoryCardRepository($this->db);
        (new GameSetup(
            new SetupPlanner(new SeededRandom(5)),
            $cards,
            $rooms,
            $spirits,
            $this->egos,
            $objectives,
            $players,
            $this->globals,
        ))->run(self::SEATS, SetupPlanner::LAYOUT_FIXED);
        $this->draft = new InitialEgoDraft($this->egos, $this->globals, new SeededRandom(1));
        $this->view = new PublicView($cards, $rooms, $spirits, $this->egos, $objectives, $players, $this->globals);
    }

    public function testPickersGoInReverseSeatOrder(): void
    {
        $order = [];
        while (($picker = $this->draft->nextPicker(self::SEATS)) !== null) {
            $order[] = $picker;
            $this->draft->pick(self::SEATS, $picker, $this->draft->candidates()[0]);
        }

        self::assertSame([33, 22, 11], $order);
        self::assertSame(3, $this->globals->setupStep());
    }

    public function testPackHasPlayersPlusOneAndShrinksByOneEachPick(): void
    {
        self::assertCount(4, $this->draft->candidates());
        $first = $this->draft->candidates()[1];

        self::assertSame(3, $this->draft->pick(self::SEATS, 33, $first));
        self::assertNotContains($first, $this->draft->candidates());
        self::assertCount(3, $this->draft->candidates());
        self::assertSame(2, $this->draft->pick(self::SEATS, 22, $this->draft->candidates()[0]));
    }

    public function testRemainingCardsPassToTheNextPicker(): void
    {
        $before = $this->draft->candidates();
        $this->draft->pick(self::SEATS, 33, $before[2]);

        self::assertSame(array_values(array_diff($before, [$before[2]])), $this->draft->candidates());
        self::assertSame(22, $this->draft->nextPicker(self::SEATS));
    }

    public function testPickedCardMovesToPickedWithOwner(): void
    {
        $id = $this->draft->candidates()[0];
        $this->draft->pick(self::SEATS, 33, $id);

        self::assertSame([$id], array_column($this->egos->inLoc(EgoRepository::PICKED, 33), 'id'));
        self::assertSame([], $this->egos->inLoc(EgoRepository::PICKED, 22));
    }

    public function testCannotPickACardOutsideThePack(): void
    {
        $outside = $this->egos->inLoc(EgoRepository::OUT)[0]['id'];

        $this->expectException(\InvalidArgumentException::class);
        $this->draft->pick(self::SEATS, 33, $outside);
    }

    public function testCannotPickOutOfTurn(): void
    {
        $this->expectException(\LogicException::class);
        $this->draft->pick(self::SEATS, 11, $this->draft->candidates()[0]);
    }

    public function testSecretPicksAreNotVisibleToOthersAndSpectators(): void
    {
        $id = $this->draft->candidates()[0];
        $this->draft->pick(self::SEATS, 33, $id);

        self::assertSame($id, $this->view->allDatas(33)['myPickedEgo']);
        self::assertNull($this->view->allDatas(22)['myPickedEgo']);
        self::assertNull($this->view->allDatas(0)['myPickedEgo']);
        foreach ([22, 0] as $viewer) {
            foreach ($this->view->allDatas($viewer)['playerData'] as $data) {
                self::assertNull($data['initialEgo']);
            }
        }
    }

    public function testPickNoticeKeepsTheCardInThePickersPrivateArgsOnly(): void
    {
        $notice = Notification::initialEgoPicked(33, 'Alice', 3, 'I05');

        self::assertSame('initialEgoPicked', $notice->type);
        self::assertSame(3, $notice->args['pack_size']);
        self::assertSame([33 => ['ego_id' => 'I05']], $notice->args['_private']);
        $public = $notice->args;
        unset($public['_private']);
        self::assertStringNotContainsString('I05', json_encode($public, JSON_THROW_ON_ERROR));
    }

    public function testRevealPublishesEveryPickAndDropsTheLastCard(): void
    {
        $picked = [];
        while (($picker = $this->draft->nextPicker(self::SEATS)) !== null) {
            $id = $this->draft->candidates()[0];
            $picked[$picker] = $id;
            $this->draft->pick(self::SEATS, $picker, $id);
        }
        $last = $this->draft->candidates();
        self::assertCount(1, $last);
        $outBefore = count($this->egos->inLoc(EgoRepository::OUT));

        $revealed = $this->draft->reveal(self::SEATS);

        self::assertSame(
            [
                ['player_id' => 11, 'ego_id' => $picked[11]],
                ['player_id' => 22, 'ego_id' => $picked[22]],
                ['player_id' => 33, 'ego_id' => $picked[33]],
            ],
            $revealed,
        );
        foreach ($picked as $playerId => $id) {
            self::assertSame([$id], array_column($this->egos->inLoc(EgoRepository::OWNED, $playerId), 'id'));
            self::assertSame($id, $this->view->allDatas(0)['playerData'][$playerId]['initialEgo']);
        }
        self::assertSame([], $this->egos->inLoc(EgoRepository::PACK));
        self::assertSame([], $this->egos->inLoc(EgoRepository::PICKED));
        self::assertSame($outBefore + 1, count($this->egos->inLoc(EgoRepository::OUT)));
        self::assertContains($last[0], array_column($this->egos->inLoc(EgoRepository::OUT), 'id'));
        foreach ($revealed as $r) {
            self::assertNotSame($last[0], $r['ego_id']);
        }
    }

    public function testRevealNoticeCarriesAllPicks(): void
    {
        $notice = Notification::initialEgosRevealed([['player_id' => 11, 'ego_id' => 'I01']]);

        self::assertSame('initialEgosRevealed', $notice->type);
        self::assertSame([['player_id' => 11, 'ego_id' => 'I01']], $notice->args['egos']);
    }

    public function testFirstCandidateIsTheSmallestIdInThePack(): void
    {
        $candidates = $this->draft->candidates();
        sort($candidates, SORT_STRING);
        self::assertSame($candidates[0], $this->draft->firstCandidate());
    }

    public function testRandomCandidateIsAlwaysInThePack(): void
    {
        for ($seed = 1; $seed <= 20; $seed++) {
            $draft = new InitialEgoDraft($this->egos, $this->globals, new SeededRandom($seed));
            self::assertContains($draft->randomCandidate(), $draft->candidates());
        }
    }

    public function testZombieStyleRandomPicksStillFinishTheDraft(): void
    {
        $draft = new InitialEgoDraft($this->egos, $this->globals, new SeededRandom(7));
        while (($picker = $draft->nextPicker(self::SEATS)) !== null) {
            $draft->pick(self::SEATS, $picker, $draft->randomCandidate());
        }
        $draft->reveal(self::SEATS);

        foreach (self::SEATS as $playerId) {
            self::assertCount(1, $this->egos->inLoc(EgoRepository::OWNED, $playerId));
        }
    }

    public function testTwoPlayerGameUsesThreeCardsAndDropsOne(): void
    {
        $seats = [5, 6];
        $db = SqliteDatabase::create($seats);
        $globals = new GlobalValues(new Globals());
        $egos = new EgoRepository($db);
        (new GameSetup(
            new SetupPlanner(new SeededRandom(2)),
            new MemoryCardRepository($db),
            new RoomRepository($db),
            new SpiritRepository($db),
            $egos,
            new ObjectiveRepository($db),
            new PlayerValues($db),
            $globals,
        ))->run($seats, SetupPlanner::LAYOUT_FIXED);
        $draft = new InitialEgoDraft($egos, $globals, new SeededRandom(1));

        self::assertCount(3, $draft->candidates());
        $order = [];
        while (($picker = $draft->nextPicker($seats)) !== null) {
            $order[] = $picker;
            $draft->pick($seats, $picker, $draft->candidates()[0]);
        }
        self::assertSame([6, 5], $order);
        self::assertCount(1, $draft->candidates());
    }
}
