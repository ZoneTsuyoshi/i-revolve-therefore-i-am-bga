<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Setup;

use Bga\Games\IRevolveThereforeIAm\Data\EgoRepository;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\ObjectiveRepository;
use Bga\Games\IRevolveThereforeIAm\Data\RoomRepository;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;
use Bga\Games\IRevolveThereforeIAm\Setup\SetupPlan;
use Bga\Games\IRevolveThereforeIAm\Setup\SetupPlanner;
use IRevolveTests\Support\SeededRandom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * セットアップの計算（001 §4、007 §13）: 人数別の除外、回廊の配置、配札、初期資源、ID の採番。
 */
final class SetupPlannerTest extends TestCase
{
    private const PLAYERS = [101, 202, 303, 404];

    /**
     * @return list<int>
     */
    private static function players(int $n): array
    {
        return array_slice(self::PLAYERS, 0, $n);
    }

    private static function plan(int $n, int $layout = SetupPlanner::LAYOUT_FIXED, int $seed = 1): SetupPlan
    {
        return (new SetupPlanner(new SeededRandom($seed)))->plan(self::players($n), $layout);
    }

    /**
     * @param list<array{id: int, type: int, loc: MemoryLocation, arg: int, pos: int}> $cards
     * @return list<array{id: int, type: int, loc: MemoryLocation, arg: int, pos: int}>
     */
    private static function in(array $cards, MemoryLocation $loc, ?int $arg = null): array
    {
        return array_values(array_filter(
            $cards,
            static fn(array $c): bool => $c['loc'] === $loc && ($arg === null || $c['arg'] === $arg),
        ));
    }

    /**
     * @return array<string, array{int, int, int, int}> 人数, 除外, 配られる初期手札, レベル 1 の山札の残り
     */
    public static function playerCounts(): array
    {
        // レベル 1 は 120 枚。除外・部屋 8・場札 6・初期手札を引いた残りが山札
        return [
            '2 players' => [2, 52, 5, 120 - 52 - 8 - 6 - 5],
            '3 players' => [3, 26, 9, 120 - 26 - 8 - 6 - 9],
            '4 players' => [4, 0, 14, 120 - 0 - 8 - 6 - 14],
        ];
    }

    #[DataProvider('playerCounts')]
    public function testCardCountsByPlayerCount(int $n, int $excluded, int $handTotal, int $deck1): void
    {
        $cards = self::plan($n)->memoryCards;

        self::assertCount(144, $cards);
        self::assertCount($excluded, self::in($cards, MemoryLocation::Out));
        self::assertCount(12, self::in($cards, MemoryLocation::Room));
        self::assertCount(6, self::in($cards, MemoryLocation::Open));
        self::assertCount($handTotal, self::in($cards, MemoryLocation::Hand));
        self::assertCount($deck1, self::in($cards, MemoryLocation::Deck1));
        // レベル 2 は 24 枚から部屋に 4 枚。残り 20 枚が補充用
        self::assertCount(20, self::in($cards, MemoryLocation::Deck2));
        self::assertSame($deck1, self::plan($n)->deck1Initial);
        self::assertSame(20, self::plan($n)->deck2Initial);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function playerNumbers(): array
    {
        return ['2 players' => [2], '3 players' => [3], '4 players' => [4]];
    }

    #[DataProvider('playerNumbers')]
    public function testExcludedCardsAreLevel1Only(int $n): void
    {
        $excluded = self::in(self::plan($n)->memoryCards, MemoryLocation::Out);
        self::assertCount(SetupPlanner::EXCLUDED_LEVEL1[$n], $excluded);
        foreach ($excluded as $card) {
            self::assertSame(1, MemoryCards::get(sprintf('M%03d', $card['type']))->level);
        }
    }

    public function testStartingHandsFollowTurnOrder(): void
    {
        $cards = self::plan(4)->memoryCards;
        foreach ([2, 3, 4, 5] as $seat => $size) {
            self::assertCount($size, self::in($cards, MemoryLocation::Hand, self::PLAYERS[$seat]), "seat $seat");
        }
    }

    public function testRoomAndOpenCardsMatchLevels(): void
    {
        $cards = self::plan(3)->memoryCards;
        $rooms = self::in($cards, MemoryLocation::Room);
        $positions = array_map(static fn(array $c): int => $c['arg'], $rooms);
        sort($positions);
        self::assertSame(range(1, 12), $positions);
        foreach ($rooms as $room) {
            $level = MemoryCards::get(sprintf('M%03d', $room['type']))->level;
            self::assertSame(RoomRepository::levelOfPosition($room['arg']), $level, "room {$room['arg']}");
        }
        $slots = array_map(static fn(array $c): int => $c['arg'], self::in($cards, MemoryLocation::Open));
        sort($slots);
        self::assertSame(range(0, 5), $slots);
        foreach (self::in($cards, MemoryLocation::Open) as $open) {
            self::assertSame(1, MemoryCards::get(sprintf('M%03d', $open['type']))->level);
        }
    }

    public function testCardTypesMatchMaterialCounts(): void
    {
        $byType = [];
        foreach (self::plan(2)->memoryCards as $card) {
            $byType[$card['type']] = ($byType[$card['type']] ?? 0) + 1;
        }
        foreach (MemoryCards::all() as $def) {
            self::assertSame($def->count, $byType[(int) substr($def->id, 1)] ?? 0, $def->id);
        }
    }

    public function testDeckPositionsAreContiguousFromOne(): void
    {
        $cards = self::plan(2)->memoryCards;
        foreach ([MemoryLocation::Deck1, MemoryLocation::Deck2] as $loc) {
            $pos = array_map(static fn(array $c): int => $c['pos'], self::in($cards, $loc));
            sort($pos);
            self::assertSame(range(1, count($pos)), $pos);
        }
    }

    public function testIdsAreNotInTypeOrder(): void
    {
        $sequences = [];
        foreach ([1, 2, 3] as $seed) {
            $cards = self::plan(4, SetupPlanner::LAYOUT_FIXED, $seed)->memoryCards;
            $ids = array_map(static fn(array $c): int => $c['id'], $cards);
            self::assertSame(range(1, 144), $ids);
            $types = array_map(static fn(array $c): int => $c['type'], $cards);
            $sorted = $types;
            sort($sorted);
            self::assertNotSame($sorted, $types, 'card_id must not follow card_type order');
            $sequences[] = $types;
        }
        // 卓ごとに対応が変わる
        self::assertNotSame($sequences[0], $sequences[1]);
        self::assertNotSame($sequences[1], $sequences[2]);
    }

    public function testDeckOrderIsUncorrelatedWithIds(): void
    {
        $deck = self::in(self::plan(4)->memoryCards, MemoryLocation::Deck1);
        usort($deck, static fn(array $a, array $b): int => $a['pos'] <=> $b['pos']);
        $ids = array_map(static fn(array $c): int => $c['id'], $deck);
        $ascending = $ids;
        sort($ascending);
        self::assertNotSame($ascending, $ids);
    }

    public function testFixedLayoutPlacesFloorsInIdOrder(): void
    {
        self::assertSame(array_combine(range(1, 12), range(1, 12)), self::plan(2)->roomFloors);
    }

    public function testRandomLayoutKeepsLevelsAndUsesEveryFloorOnce(): void
    {
        $layouts = [];
        foreach ([1, 2, 3, 4, 5] as $seed) {
            $floors = self::plan(2, SetupPlanner::LAYOUT_RANDOM, $seed)->roomFloors;
            $sorted = array_values($floors);
            sort($sorted);
            self::assertSame(range(1, 12), $sorted);
            foreach (RoomRepository::LEVEL2_POSITIONS as $pos) {
                self::assertContains($floors[$pos], [1, 4, 7, 10], "corner $pos must hold a level 2 floor");
            }
            foreach (array_diff(range(1, 12), RoomRepository::LEVEL2_POSITIONS) as $pos) {
                self::assertNotContains($floors[$pos], [1, 4, 7, 10], "position $pos must hold a level 1 floor");
            }
            $layouts[] = $floors;
        }
        self::assertGreaterThan(1, count(array_unique(array_map('serialize', $layouts))), 'the layout must vary with the seed');
        self::assertNotSame(array_combine(range(1, 12), range(1, 12)), $layouts[0]);
    }

    public function testInitialResourcesByTurnOrder(): void
    {
        $players = self::plan(4)->players;
        self::assertSame(['light' => 1, 'wild_up' => 1, 'compose' => 0, 'analysis' => 0], $players[101]);
        self::assertSame(['light' => 1, 'wild_up' => 1, 'compose' => 0, 'analysis' => 1], $players[202]);
        self::assertSame(['light' => 1, 'wild_up' => 1, 'compose' => 1, 'analysis' => 0], $players[303]);
        self::assertSame(['light' => 2, 'wild_up' => 1, 'compose' => 1, 'analysis' => 0], $players[404]);

        $two = self::plan(2)->players;
        self::assertSame(['light' => 1, 'wild_up' => 1, 'compose' => 0, 'analysis' => 0], $two[101]);
        self::assertSame(['light' => 1, 'wild_up' => 1, 'compose' => 0, 'analysis' => 1], $two[202]);
    }

    public function testSpiritCards(): void
    {
        for ($seed = 1; $seed <= 5; $seed++) {
            $spirits = self::plan(2, SetupPlanner::LAYOUT_FIXED, $seed)->spirits;
            self::assertCount(7, $spirits);
            $byTier = [];
            foreach ($spirits as $s) {
                $byTier[$s['tier']][] = $s;
            }
            self::assertCount(2, $byTier[1]);
            self::assertCount(1, $byTier[2]);
            self::assertCount(2, $byTier[3]);
            self::assertCount(1, $byTier[4]);
            self::assertCount(1, $byTier[5]);
            foreach ([1, 3] as $tier) {
                $slots = array_map(static fn(array $s): int => $s['slot'], $byTier[$tier]);
                sort($slots);
                self::assertSame([0, 1], $slots);
                $ids = array_map(static fn(array $s): string => $s['id'], $byTier[$tier]);
                self::assertCount(2, array_unique($ids));
                foreach ($ids as $id) {
                    self::assertContains($id, ["R{$tier}A", "R{$tier}B", "R{$tier}C"]);
                }
            }
            foreach ($spirits as $s) {
                self::assertSame($s['tier'] !== 5, $s['light'], $s['id']);
            }
        }
    }

    #[DataProvider('playerNumbers')]
    public function testEgoAndObjectiveCards(int $n): void
    {
        $plan = self::plan($n);

        $count = static function (array $rows, string $key, string $value): int {
            return count(array_filter($rows, static fn(array $r): bool => $r[$key] === $value));
        };
        self::assertCount(13, $plan->egos);
        self::assertSame($n, $count($plan->egos, 'loc', EgoRepository::ENH_DECK));
        self::assertSame($n + 1, $count($plan->egos, 'loc', EgoRepository::PACK));
        self::assertSame(13 - $n - ($n + 1), $count($plan->egos, 'loc', EgoRepository::OUT));
        foreach ($plan->egos as $ego) {
            if ($ego['loc'] === EgoRepository::ENH_DECK) {
                self::assertStringStartsWith('E', $ego['id']);
            }
            if ($ego['loc'] === EgoRepository::PACK) {
                self::assertStringStartsWith('I', $ego['id']);
            }
        }

        self::assertCount(7, $plan->objectives);
        $open = array_values(array_filter($plan->objectives, static fn(array $o): bool => $o['state'] === ObjectiveRepository::OPEN));
        self::assertCount($n + 1, $open);
        self::assertSame(range(0, $n), array_map(static fn(array $o): ?int => $o['slot'], $open));
    }

    public function testSameSeedGivesSamePlan(): void
    {
        self::assertEquals(self::plan(3, SetupPlanner::LAYOUT_RANDOM, 7), self::plan(3, SetupPlanner::LAYOUT_RANDOM, 7));
    }

    public function testUnsupportedPlayerCount(): void
    {
        $planner = new SetupPlanner(new SeededRandom(1));
        foreach ([[], [1], [1, 2, 3, 4, 5]] as $ids) {
            try {
                $planner->plan($ids);
                self::fail('expected an exception for ' . count($ids) . ' players');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        $planner->plan([1, 1]);
    }
}
