<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Setup;

use Bga\GameFramework\Db\Globals;
use Bga\Games\IRevolveThereforeIAm\Data\EgoRepository;
use Bga\Games\IRevolveThereforeIAm\Data\GamePhase;
use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryCardRepository;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\ObjectiveRepository;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\RoomRepository;
use Bga\Games\IRevolveThereforeIAm\Data\SpiritRepository;
use Bga\Games\IRevolveThereforeIAm\Setup\GameSetup;
use Bga\Games\IRevolveThereforeIAm\Setup\SetupPlanner;
use IRevolveTests\Support\SeededRandom;
use IRevolveTests\Support\SqliteDatabase;
use PHPUnit\Framework\TestCase;

/**
 * GameSetup が計画を DB（SQLite）に書いた結果と、グローバル値の初期化。
 */
final class GameSetupTest extends TestCase
{
    /**
     * @param list<int> $players
     * @return array{SqliteDatabase, GlobalValues}
     */
    private function runSetup(array $players, int $layout = SetupPlanner::LAYOUT_FIXED, int $seed = 3): array
    {
        $db = SqliteDatabase::create($players);
        $globals = new GlobalValues(new Globals());
        (new GameSetup(
            new SetupPlanner(new SeededRandom($seed)),
            new MemoryCardRepository($db),
            new RoomRepository($db),
            new SpiritRepository($db),
            new EgoRepository($db),
            new ObjectiveRepository($db),
            new PlayerValues($db),
            $globals,
        ))->run($players, $layout);

        return [$db, $globals];
    }

    public function testWritesEveryTable(): void
    {
        [$db, $globals] = $this->runSetup([11, 22, 33]);
        $cards = new MemoryCardRepository($db);

        self::assertSame(144, $cards->countAll());
        self::assertSame(26, $cards->count(MemoryLocation::Out));
        self::assertCount(12, $cards->inLocAnyArg(MemoryLocation::Room));
        self::assertSame(2, $cards->count(MemoryLocation::Hand, 11));
        self::assertSame(3, $cards->count(MemoryLocation::Hand, 22));
        self::assertSame(4, $cards->count(MemoryLocation::Hand, 33));

        self::assertCount(12, (new RoomRepository($db))->all());
        self::assertCount(7, (new SpiritRepository($db))->all());
        self::assertCount(13, array_merge(
            (new EgoRepository($db))->inLoc(EgoRepository::OUT),
            (new EgoRepository($db))->inLoc(EgoRepository::PACK),
            (new EgoRepository($db))->inLoc(EgoRepository::ENH_DECK),
        ));
        self::assertCount(7, (new ObjectiveRepository($db))->all());

        $players = new PlayerValues($db);
        self::assertSame([11, 22, 33], $players->playerIds());
        self::assertSame(1, $players->light(11));
        self::assertSame(1, $players->wildUp(11));
        self::assertSame(0, $players->analysisTokens(11));
        self::assertSame(1, $players->analysisTokens(22));
        self::assertSame(1, $players->composeTokens(33));
        self::assertSame(0, $players->persona(11));
        self::assertNull($players->spirit(11));

        self::assertSame(GamePhase::SetupEgo, $globals->phase());
        self::assertSame(0, $globals->setupStep());
        self::assertSame(0, $globals->turnPlayer());
        self::assertNull($globals->endReason());
        self::assertSame($cards->count(MemoryLocation::Deck1), $globals->deckInitial(1));
        self::assertSame($cards->count(MemoryLocation::Deck2), $globals->deckInitial(2));
        self::assertSame(120 - 26 - 8 - 6 - 9, $globals->deckInitial(1));
        self::assertSame(20, $globals->deckInitial(2));
    }

    public function testRandomLayoutIsWrittenToRoomTable(): void
    {
        [$db] = $this->runSetup([11, 22], SetupPlanner::LAYOUT_RANDOM, 5);
        $floors = (new RoomRepository($db))->all();

        self::assertCount(12, array_unique($floors));
        foreach (RoomRepository::LEVEL2_POSITIONS as $pos) {
            self::assertContains($floors[$pos], [1, 4, 7, 10]);
        }
    }

    public function testRoomCardBackLevelMatchesRoomLevel(): void
    {
        [$db] = $this->runSetup([11, 22, 33, 44]);
        $cards = new MemoryCardRepository($db);
        foreach (range(1, 12) as $pos) {
            $inRoom = $cards->inLoc(MemoryLocation::Room, $pos);
            self::assertCount(1, $inRoom);
            self::assertFalse($inRoom[0]->faceUp);
        }
    }
}
