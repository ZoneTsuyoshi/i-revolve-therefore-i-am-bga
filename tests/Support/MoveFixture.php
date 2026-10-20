<?php

declare(strict_types=1);

namespace IRevolveTests\Support;

use Bga\GameFramework\Db\Globals;
use Bga\Games\IRevolveThereforeIAm\Data\EffectStack;
use Bga\Games\IRevolveThereforeIAm\Data\EgoRepository;
use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryCardRepository;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\ObjectiveRepository;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\RoomRepository;
use Bga\Games\IRevolveThereforeIAm\Data\SpiritRepository;
use Bga\Games\IRevolveThereforeIAm\Data\StoredGameView;
use Bga\Games\IRevolveThereforeIAm\Data\TurnContext;
use Bga\Games\IRevolveThereforeIAm\Effects\Catalog\SpecSource;
use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionEvaluator;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\ActiveSpecs;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\KeyViewQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\LightScheduleQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\MovePaymentQuery;
use Bga\Games\IRevolveThereforeIAm\Flow\HandLimit;
use Bga\Games\IRevolveThereforeIAm\Flow\MoveBoardReader;
use Bga\Games\IRevolveThereforeIAm\Flow\MoveExecutor;
use Bga\Games\IRevolveThereforeIAm\Flow\MovePhase;
use Bga\Games\IRevolveThereforeIAm\Flow\MovePlanner;
use Bga\Games\IRevolveThereforeIAm\Material\CardBack;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;
use Bga\Games\IRevolveThereforeIAm\Setup\GameSetup;
use Bga\Games\IRevolveThereforeIAm\Setup\SetupPlanner;

/**
 * 移動フェイズの結合テスト用: 3 人（11・22・33）の固定配置のゲームを SQLite 上に作り、`MovePhase` を組み立てる。
 * 手札・自己構成済みは空、人格は未配置、心光と万能移動鍵トークンは 0 から始める（テストごとに与える）。
 */
final class MoveFixture
{
    public const SEATS = [11, 22, 33];

    public readonly SqliteDatabase $db;

    public readonly GlobalValues $globals;

    public readonly PlayerValues $players;

    public readonly MemoryCardRepository $memory;

    public readonly EffectStack $stack;

    public readonly HandLimit $handLimit;

    public readonly MovePhase $phase;

    public readonly MovePlanner $planner;

    public readonly StoredGameView $view;

    public function __construct(?SpecSource $specs = null)
    {
        $specs ??= ArrayCatalog::withTestSpecs();
        $this->db = SqliteDatabase::create(self::SEATS);
        $this->globals = new GlobalValues(new Globals());
        $this->players = new PlayerValues($this->db);
        $this->memory = new MemoryCardRepository($this->db);
        $rooms = new RoomRepository($this->db);
        $egos = new EgoRepository($this->db);
        (new GameSetup(
            new SetupPlanner(new SeededRandom(7)),
            $this->memory,
            $rooms,
            new SpiritRepository($this->db),
            $egos,
            new ObjectiveRepository($this->db),
            $this->players,
            $this->globals,
        ))->run(self::SEATS, SetupPlanner::LAYOUT_FIXED);

        // 手札を空にし、トークンを 0 にする
        $this->db->execute("UPDATE `memory_card` SET `card_loc` = 'OUT', `card_loc_arg` = 0 WHERE `card_loc` = 'HAND'");
        foreach (self::SEATS as $id) {
            $this->players->setToken($id, PlayerValues::COLUMN_LIGHT, 0);
            $this->players->setToken($id, PlayerValues::COLUMN_WILD_UP, 0);
            $this->players->setToken($id, PlayerValues::COLUMN_COMPOSE, 0);
            $this->players->setToken($id, PlayerValues::COLUMN_ANALYSIS, 0);
        }
        $this->globals->setTurnPlayer(11);
        $this->globals->setTurnContext(new TurnContext());

        $this->stack = new EffectStack($this->db);
        $this->view = new StoredGameView($this->memory, $egos, $this->players, $this->globals);
        $active = new ActiveSpecs($this->view, $specs, new ConditionEvaluator($this->view));
        $light = new LightScheduleQuery($active);
        $payments = new MovePaymentQuery($active, new KeyViewQuery($active), $light);
        $this->planner = new MovePlanner($payments, $light);
        $this->handLimit = new HandLimit($this->memory, $this->players, $this->stack);
        $this->phase = new MovePhase(
            new MoveBoardReader($this->memory, $rooms, $this->players, $this->globals),
            $this->planner,
            new MoveExecutor($this->memory, $this->players, $this->globals, $specs),
            $this->handLimit,
            $payments,
            $light,
        );
    }

    public function place(int $player, int $pos): void
    {
        $this->players->setPersona($player, $pos);
        if ($player === $this->globals->turnPlayer()) {
            $ctx = $this->globals->turnContext();
            $ctx->startPos = $pos;
            $this->globals->setTurnContext($ctx);
        }
    }

    public function tokens(int $player, int $light = 0, int $wildUp = 0): void
    {
        $this->players->setToken($player, PlayerValues::COLUMN_LIGHT, $light);
        $this->players->setToken($player, PlayerValues::COLUMN_WILD_UP, $wildUp);
    }

    /**
     * 部屋の想い出カードを、指定の錠前の裏面を持つ（部屋のレベルと同じレベルの）カードに差し替える。
     */
    public function lock(int $pos, KeyType $lock): void
    {
        $level = RoomRepository::levelOfPosition($pos);
        foreach (MemoryCards::all() as $id => $def) {
            if ($def->level === $level && $def->back->lock() === $lock && $def->back === CardBack::from("Rev{$level}" . $this->letter($lock))) {
                $this->db->execute(
                    'UPDATE `memory_card` SET `card_type` = ' . (int) substr($id, 1)
                    . " WHERE `card_loc` = 'ROOM' AND `card_loc_arg` = $pos",
                );

                return;
            }
        }
        throw new \LogicException('no card with that back');
    }

    /**
     * 種類を指定して手札に加える（山札・除外・場札にある同じ種類の札から）。
     *
     * @param list<int> $types card_type（1〜99）
     * @return list<int> card_id
     */
    public function giveHand(int $player, array $types): array
    {
        return $this->give($player, $types, MemoryLocation::Hand);
    }

    /**
     * 山札（レベル 1）の上から n 枚を手札に加える。
     *
     * @return list<int> card_id
     */
    public function giveHandAny(int $player, int $n): array
    {
        return $this->memory->drawFromDeck(1, $n, MemoryLocation::Hand, $player);
    }

    /**
     * @param list<int> $types
     * @return list<int>
     */
    public function giveTableau(int $player, array $types): array
    {
        return $this->give($player, $types, MemoryLocation::Tableau);
    }

    /**
     * @param list<int> $types
     * @return list<int>
     */
    private function give(int $player, array $types, MemoryLocation $to): array
    {
        $ids = [];
        foreach ($types as $type) {
            $id = $this->db->value(
                "SELECT MIN(`card_id`) FROM `memory_card` WHERE `card_type` = $type"
                . " AND `card_loc` IN ('DECK1', 'DECK2', 'OUT', 'OPEN')",
            );
            if ($id === null) {
                throw new \LogicException("no free card of type $type");
            }
            $this->memory->move((int) $id, $to, $player);
            $ids[] = (int) $id;
        }

        return $ids;
    }

    private function letter(KeyType $lock): string
    {
        return match ($lock) {
            KeyType::WISDOM => 'B',
            KeyType::WILL => 'F',
            KeyType::EMOTION => 'H',
            KeyType::WILD => throw new \LogicException('no wild lock'),
        };
    }
}
