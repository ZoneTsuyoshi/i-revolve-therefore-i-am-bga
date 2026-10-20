<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Setup;

use Bga\Games\IRevolveThereforeIAm\Data\EgoRepository;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\ObjectiveRepository;
use Bga\Games\IRevolveThereforeIAm\Data\RoomRepository;
use Bga\Games\IRevolveThereforeIAm\Material\AnalysisCards;
use Bga\Games\IRevolveThereforeIAm\Material\EgoCards;
use Bga\Games\IRevolveThereforeIAm\Material\EgoKind;
use Bga\Games\IRevolveThereforeIAm\Material\FloorCards;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;
use Bga\Games\IRevolveThereforeIAm\Material\ObjectiveCards;

/**
 * セットアップの乱数と配札の計算（仕様書 001 §4、007 §13 の 1〜9）。乱数は注入するので、PHPUnit では種付きに差し替えられる。
 * 初期自我のドラフトとミープルの配置はここでは行わない（006 の 20〜25。I-04）。
 */
final class SetupPlanner
{
    /** ゲームオプション 100「回廊の配置」: 1 = ID 順（既定）、2 = ランダム（007 §5.1）。 */
    public const LAYOUT_FIXED = 1;
    public const LAYOUT_RANDOM = 2;

    /** 人数別にレベル 1 の山札から除外する枚数（001 §2）。 */
    public const EXCLUDED_LEVEL1 = [2 => 52, 3 => 26, 4 => 0];

    /** 人数別に場に並べる目標カードの枚数（001 §4 手順 8）。 */
    public const OBJECTIVE_COUNT = [2 => 3, 3 => 4, 4 => 5];

    public const OPEN_SIZE = 6;

    /** 手番順ごとの初期の想い出カード枚数（1 番手から。001 §4 の表）。 */
    public const START_HAND = [2, 3, 4, 5];

    /** 精神カードの第一・第三階層は a〜c の 3 枚から 2 枚を使う。 */
    private const BRANCHED_TIERS = [1 => ['R1A', 'R1B', 'R1C'], 3 => ['R3A', 'R3B', 'R3C']];

    /** @var \Closure(int, int): int */
    private readonly \Closure $rand;

    /**
     * @param callable(int, int): int $rand 両端を含む乱数（本番は random_int。仕様書 003 §4.4）
     */
    public function __construct(callable $rand)
    {
        $this->rand = \Closure::fromCallable($rand);
    }

    /**
     * @param list<int> $playerIds 席順（player_no の昇順 = 手番順）のプレイヤー ID
     * @param int $layoutOption ゲームオプション 100 の値
     */
    public function plan(array $playerIds, int $layoutOption = self::LAYOUT_FIXED): SetupPlan
    {
        $n = count($playerIds);
        if (!isset(self::EXCLUDED_LEVEL1[$n])) {
            throw new \InvalidArgumentException("unsupported number of players: $n");
        }
        if (count(array_unique($playerIds)) !== $n) {
            throw new \InvalidArgumentException('duplicate player ids');
        }

        $roomFloors = $this->roomFloors($layoutOption);
        [$memoryCards, $deck1, $deck2] = $this->dealMemoryCards($playerIds, $roomFloors);

        return new SetupPlan(
            memoryCards: $memoryCards,
            roomFloors: $roomFloors,
            spirits: $this->spirits(),
            egos: $this->egos($n),
            objectives: $this->objectives($n),
            players: $this->players($playerIds),
            deck1Initial: $deck1,
            deck2Initial: $deck2,
        );
    }

    /**
     * 部屋の配置（位置 → 床カード ID）。ランダムはレベル 2 の床を四隅に、レベル 1 の床を残りに、それぞれシャッフルして置く。
     *
     * @return array<int, int>
     */
    public function roomFloors(int $layoutOption): array
    {
        $level2 = [];
        $level1 = [];
        foreach (FloorCards::all() as $floor) {
            $num = self::number($floor->id);
            if ($floor->level === 2) {
                $level2[] = $num;
            } else {
                $level1[] = $num;
            }
        }
        $corners = RoomRepository::LEVEL2_POSITIONS;
        $others = array_values(array_diff(range(1, RoomRepository::ROOM_COUNT), $corners));
        if (count($level2) !== count($corners) || count($level1) !== count($others)) {
            throw new \LogicException('floor card levels do not match the corridor layout');
        }

        if ($layoutOption === self::LAYOUT_RANDOM) {
            $level2 = $this->shuffle($level2);
            $level1 = $this->shuffle($level1);
        } else {
            sort($level2);
            sort($level1);
        }

        $layout = [];
        foreach ($corners as $i => $pos) {
            $layout[$pos] = $level2[$i];
        }
        foreach ($others as $i => $pos) {
            $layout[$pos] = $level1[$i];
        }
        ksort($layout);

        return $layout;
    }

    /**
     * 想い出カード 144 枚の採番・除外・部屋・場札・初期手札（007 §4.3、§13 の 1〜4 と 9 の手札）。
     *
     * @param list<int> $playerIds
     * @param array<int, int> $roomFloors
     * @return array{0: list<array{id: int, type: int, loc: MemoryLocation, arg: int, pos: int}>, 1: int, 2: int}
     */
    private function dealMemoryCards(array $playerIds, array $roomFloors): array
    {
        // 種類のリストを展開してシャッフルし、先頭から card_id を振る。ID から種類を推測できない（007 §4.3）
        $types = [];
        $levelOfType = [];
        foreach (MemoryCards::all() as $def) {
            $num = self::number($def->id);
            $levelOfType[$num] = $def->level;
            for ($i = 0; $i < $def->count; $i++) {
                $types[] = $num;
            }
        }
        if (count($types) !== 144) {
            throw new \LogicException('memory cards must total 144, got ' . count($types));
        }
        $types = $this->shuffle($types);

        // レベル別に分け、山の順序（card_pos）のためにもう一度シャッフルする（ID の大小と山の順序を無相関にする）
        $byLevel = [1 => [], 2 => []];
        $typeOf = [];
        foreach ($types as $i => $type) {
            $id = $i + 1;
            $typeOf[$id] = $type;
            $byLevel[$levelOfType[$type]][] = $id;
        }
        $deck = [1 => $this->shuffle($byLevel[1]), 2 => $this->shuffle($byLevel[2])];

        /** @var array<int, array{loc: MemoryLocation, arg: int}> $placed */
        $placed = [];
        $take = static function (int $level) use (&$deck): int {
            $id = array_shift($deck[$level]);
            if ($id === null) {
                throw new \LogicException("level $level deck is empty during setup");
            }

            return $id;
        };

        // 人数別の除外（レベル 1 の山札から）
        for ($i = 0; $i < self::EXCLUDED_LEVEL1[count($playerIds)]; $i++) {
            $placed[$take(1)] = ['loc' => MemoryLocation::Out, 'arg' => 0];
        }
        // 部屋（位置のレベルの山札から裏向きで）
        foreach (array_keys($roomFloors) as $pos) {
            $placed[$take(RoomRepository::levelOfPosition($pos))] = ['loc' => MemoryLocation::Room, 'arg' => $pos];
        }
        // 場札（レベル 1 から 6 枚。枠 0〜5）
        for ($slot = 0; $slot < self::OPEN_SIZE; $slot++) {
            $placed[$take(1)] = ['loc' => MemoryLocation::Open, 'arg' => $slot];
        }
        // 初期手札（部屋・場札を置いた後のレベル 1 の山札から、手番順に 2・3・4・5 枚）
        foreach ($playerIds as $seat => $playerId) {
            for ($i = 0; $i < self::START_HAND[$seat]; $i++) {
                $placed[$take(1)] = ['loc' => MemoryLocation::Hand, 'arg' => $playerId];
            }
        }

        $cards = [];
        foreach ($placed as $id => $where) {
            $cards[$id] = ['id' => $id, 'type' => $typeOf[$id], 'loc' => $where['loc'], 'arg' => $where['arg'], 'pos' => 0];
        }
        // 残りは山札。残った順に 1 から card_pos を振る（最小が一番上）
        foreach ([1, 2] as $level) {
            foreach ($deck[$level] as $i => $id) {
                $cards[$id] = [
                    'id' => $id,
                    'type' => $typeOf[$id],
                    'loc' => MemoryLocation::deck($level),
                    'arg' => 0,
                    'pos' => $i + 1,
                ];
            }
        }
        ksort($cards);

        return [array_values($cards), count($deck[1]), count($deck[2])];
    }

    /**
     * 精神カード 7 枚（第一・第三階層は a〜c から 2 枚。第二・四・五階層は各 1 枚）。心光は第一〜第四階層に置く。
     *
     * @return list<array{id: string, tier: int, slot: int, light: bool}>
     */
    private function spirits(): array
    {
        $ids = [];
        foreach ([1, 2, 3, 4, 5] as $tier) {
            if (isset(self::BRANCHED_TIERS[$tier])) {
                $chosen = array_slice($this->shuffle(self::BRANCHED_TIERS[$tier]), 0, 2);
                foreach ($chosen as $slot => $id) {
                    $ids[] = ['id' => $id, 'tier' => $tier, 'slot' => $slot];
                }
            } else {
                $ids[] = ['id' => 'R' . $tier, 'tier' => $tier, 'slot' => 0];
            }
        }

        return array_map(static fn(array $c): array => $c + ['light' => AnalysisCards::get($c['id'])->hasLight], $ids);
    }

    /**
     * 自我カード 13 枚。強化自我は人数分を山（ENH_DECK）に、初期自我は人数 + 1 枚をドラフトの山（PACK）に、残りは除外。
     *
     * @return list<array{id: string, loc: string, owner: ?int, pos: int}>
     */
    private function egos(int $playerCount): array
    {
        $initial = [];
        $enhanced = [];
        foreach (EgoCards::all() as $ego) {
            if ($ego->kind === EgoKind::INITIAL) {
                $initial[] = $ego->id;
            } else {
                $enhanced[] = $ego->id;
            }
        }

        // 強化自我は人数分を山（ENH_DECK）に、初期自我は人数 + 1 枚をドラフトの山（PACK）に。残りは除外
        $groups = [
            [$this->shuffle($enhanced), $playerCount, EgoRepository::ENH_DECK],
            [$this->shuffle($initial), $playerCount + 1, EgoRepository::PACK],
        ];
        $rows = [];
        foreach ($groups as [$ids, $keep, $loc]) {
            foreach ($ids as $i => $id) {
                $rows[] = $i < $keep
                    ? ['id' => $id, 'loc' => $loc, 'owner' => null, 'pos' => $i]
                    : ['id' => $id, 'loc' => EgoRepository::OUT, 'owner' => null, 'pos' => 0];
            }
        }

        return $rows;
    }

    /**
     * 目標カード 7 枚。2・3・4 人で 3・4・5 枚を場の枠 0 から並べ、残りは使わない。
     *
     * @return list<array{id: int, state: string, slot: ?int}>
     */
    private function objectives(int $playerCount): array
    {
        $ids = [];
        foreach (ObjectiveCards::all() as $objective) {
            $ids[] = self::number($objective->id);
        }
        $rows = [];
        foreach ($this->shuffle($ids) as $i => $id) {
            $rows[] = $i < self::OBJECTIVE_COUNT[$playerCount]
                ? ['id' => $id, 'state' => ObjectiveRepository::OPEN, 'slot' => $i]
                : ['id' => $id, 'state' => ObjectiveRepository::OUT, 'slot' => null];
        }

        return $rows;
    }

    /**
     * 手番順ごとの初期トークン。全員に心光 1・万能移動鍵 1。2 番手は精神分析、3 番手は自己構成、4 番手は心光 +1 と自己構成。
     *
     * @param list<int> $playerIds
     * @return array<int, array{light: int, wild_up: int, compose: int, analysis: int}>
     */
    private function players(array $playerIds): array
    {
        $out = [];
        foreach ($playerIds as $seat => $playerId) {
            $out[$playerId] = [
                'light' => $seat === 3 ? 2 : 1,
                'wild_up' => 1,
                'compose' => $seat >= 2 ? 1 : 0,
                'analysis' => $seat === 1 ? 1 : 0,
            ];
        }

        return $out;
    }

    /**
     * Fisher-Yates（注入された乱数を使う）。
     *
     * @template T
     * @param list<T> $items
     * @return list<T>
     */
    private function shuffle(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = ($this->rand)(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }

    /**
     * 識別子の数字部分（`M012` → 12、`F03` → 3、`O004` → 4）。
     */
    private static function number(string $id): int
    {
        return (int) substr($id, 1);
    }
}
