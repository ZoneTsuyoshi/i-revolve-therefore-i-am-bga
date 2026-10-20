<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Setup;

use Bga\GameFramework\Db\Globals;
use Bga\Games\IRevolveThereforeIAm\Data\EgoRepository;
use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryCardRepository;
use Bga\Games\IRevolveThereforeIAm\Data\ObjectiveRepository;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\RoomRepository;
use Bga\Games\IRevolveThereforeIAm\Data\SpiritRepository;
use Bga\Games\IRevolveThereforeIAm\Flow\Notification;
use Bga\Games\IRevolveThereforeIAm\Setup\GameSetup;
use Bga\Games\IRevolveThereforeIAm\Setup\InitialEgoDraft;
use Bga\Games\IRevolveThereforeIAm\Setup\PersonaPlacement;
use Bga\Games\IRevolveThereforeIAm\Setup\SetupPlanner;
use IRevolveTests\Support\SeededRandom;
use IRevolveTests\Support\SqliteDatabase;
use PHPUnit\Framework\TestCase;

/**
 * 人格の配置（006 の 23・24）: 逆順、空いている四隅だけ、zombie の先頭選択。
 */
final class PersonaPlacementTest extends TestCase
{
    private const SEATS = [11, 22, 33];

    private PlayerValues $players;

    private GlobalValues $globals;

    private PersonaPlacement $placement;

    protected function setUp(): void
    {
        $db = SqliteDatabase::create(self::SEATS);
        $this->globals = new GlobalValues(new Globals());
        $this->players = new PlayerValues($db);
        $egos = new EgoRepository($db);
        $rooms = new RoomRepository($db);
        (new GameSetup(
            new SetupPlanner(new SeededRandom(4)),
            new MemoryCardRepository($db),
            $rooms,
            new SpiritRepository($db),
            $egos,
            new ObjectiveRepository($db),
            $this->players,
            $this->globals,
        ))->run(self::SEATS, SetupPlanner::LAYOUT_FIXED);
        // ドラフトを終えて配置に入った状態にする
        $draft = new InitialEgoDraft($egos, $this->globals, new SeededRandom(1));
        while (($picker = $draft->nextPicker(self::SEATS)) !== null) {
            $draft->pick(self::SEATS, $picker, $draft->candidates()[0]);
        }
        $draft->reveal(self::SEATS);
        $this->placement = new PersonaPlacement($this->players, $rooms, $this->globals);
    }

    public function testPlacersGoInReverseSeatOrder(): void
    {
        $order = [];
        while (($placer = $this->placement->nextPlacer(self::SEATS)) !== null) {
            $order[] = $placer;
            $this->placement->place(self::SEATS, $placer, $this->placement->freeCorners(self::SEATS)[0]);
        }

        self::assertSame([33, 22, 11], $order);
        self::assertSame(6, $this->globals->setupStep());
    }

    public function testAllFourCornersAreFreeAtFirstAndTakenOnesDisappear(): void
    {
        self::assertSame([1, 4, 7, 10], $this->placement->freeCorners(self::SEATS));

        $this->placement->place(self::SEATS, 33, 7);

        self::assertSame([1, 4, 10], $this->placement->freeCorners(self::SEATS));
        self::assertSame(7, $this->players->persona(33));
        self::assertSame(0, $this->players->persona(22));
    }

    public function testCannotPlaceOnTakenCorner(): void
    {
        $this->placement->place(self::SEATS, 33, 7);

        $this->expectException(\InvalidArgumentException::class);
        $this->placement->place(self::SEATS, 22, 7);
    }

    public function testCannotPlaceOutsideTheCorners(): void
    {
        foreach ([0, 2, 5, 12, 13] as $pos) {
            try {
                $this->placement->place(self::SEATS, 33, $pos);
                self::fail("position $pos must be rejected");
            } catch (\InvalidArgumentException) {
                self::assertSame(0, $this->players->persona(33));
            }
        }
    }

    public function testCannotPlaceOutOfTurn(): void
    {
        $this->expectException(\LogicException::class);
        $this->placement->place(self::SEATS, 11, 1);
    }

    public function testFirstPlacerIsTheLastSeat(): void
    {
        $db = SqliteDatabase::create(self::SEATS);
        $placement = new PersonaPlacement(new PlayerValues($db), new RoomRepository($db), new GlobalValues(new Globals()));

        // step 0（ドラフト前）でも最初の配置者は逆順の先頭で、ドラフトの完了は状態機械（20 → 22 → 23）が保証する
        self::assertSame(33, $placement->nextPlacer(self::SEATS));
    }

    public function testZombieTakesTheFirstFreeCorner(): void
    {
        $this->placement->place(self::SEATS, 33, 1);

        // zombie（24）は freeCorners の先頭を選ぶ
        $corner = $this->placement->freeCorners(self::SEATS)[0];
        $this->placement->place(self::SEATS, 22, $corner);

        self::assertSame(4, $this->players->persona(22));
        self::assertSame([7, 10], $this->placement->freeCorners(self::SEATS));
    }

    public function testRoomNameComesFromTheFloorAtThatPosition(): void
    {
        $name = $this->placement->place(self::SEATS, 33, 1);

        self::assertNotSame('', $name);
        self::assertSame($name, $this->placement->roomName(1));
        $notice = Notification::personaPlaced(33, 'Alice', 1, $name);
        self::assertSame('personaPlaced', $notice->type);
        self::assertSame(1, $notice->args['pos']);
        self::assertSame($name, $notice->args['room_name']);
        self::assertSame(['room_name'], $notice->args['i18n']);
    }

    public function testFourPlayersFillEveryCorner(): void
    {
        $seats = [1, 2, 3, 4];
        $db = SqliteDatabase::create($seats);
        $globals = new GlobalValues(new Globals());
        $players = new PlayerValues($db);
        $rooms = new RoomRepository($db);
        $egos = new EgoRepository($db);
        (new GameSetup(
            new SetupPlanner(new SeededRandom(8)),
            new MemoryCardRepository($db),
            $rooms,
            new SpiritRepository($db),
            $egos,
            new ObjectiveRepository($db),
            $players,
            $globals,
        ))->run($seats, SetupPlanner::LAYOUT_FIXED);
        $globals->setSetupStep(4);
        $placement = new PersonaPlacement($players, $rooms, $globals);

        $order = [];
        while (($placer = $placement->nextPlacer($seats)) !== null) {
            $order[] = $placer;
            $placement->place($seats, $placer, $placement->freeCorners($seats)[0]);
        }

        self::assertSame([4, 3, 2, 1], $order);
        self::assertSame([], $placement->freeCorners($seats));
        $positions = array_map(fn(int $id): int => $players->persona($id), $seats);
        sort($positions);
        self::assertSame([1, 4, 7, 10], $positions);
    }
}
