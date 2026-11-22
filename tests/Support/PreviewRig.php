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
use Bga\Games\IRevolveThereforeIAm\Data\PublicView;
use Bga\Games\IRevolveThereforeIAm\Data\RoomRepository;
use Bga\Games\IRevolveThereforeIAm\Data\SpiritRepository;
use Bga\Games\IRevolveThereforeIAm\Data\StoredGameView;
use Bga\Games\IRevolveThereforeIAm\Data\TurnContext;
use Bga\Games\IRevolveThereforeIAm\Effects\Catalog\CatalogSpecSource;
use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionEvaluator;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\ActiveSpecs;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\ComposeEnergyQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\KeyViewQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\LightScheduleQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\MovePaymentQuery;
use Bga\Games\IRevolveThereforeIAm\Flow\HandLimit;
use Bga\Games\IRevolveThereforeIAm\Flow\MoveBoardReader;
use Bga\Games\IRevolveThereforeIAm\Flow\MoveExecutor;
use Bga\Games\IRevolveThereforeIAm\Flow\MovePhase;
use Bga\Games\IRevolveThereforeIAm\Flow\MovePlanner;
use Bga\Games\IRevolveThereforeIAm\Flow\TurnKit;
use Bga\Games\IRevolveThereforeIAm\Setup\GameSetup;
use Bga\Games\IRevolveThereforeIAm\Setup\SetupPlanner;

/**
 * ローカルのプレビュー用の固定データ（scripts/preview/fixtures/）を作る土台。2〜4 人のゲームを SQLite 上に本物のセットアップで作り、
 * 本番の `Game` と同じ組み立ての `PublicView`・`MovePhase`・`TurnKit` を持つ。場面ごとの状態は、リポジトリ経由で書き換えて作る。
 */
final class PreviewRig
{
    /** BGA の標準の色（gameinfos.jsonc の player_colors）と、プレビュー用の名前 */
    private const COLORS = ['b3202a', '2a4fa0', '5f9c3a', 'd6b61e'];

    private const NAMES = ['Aoi', 'Ren', 'Sora', 'Mio'];

    /** @var list<int> */
    public readonly array $seats;

    public readonly SqliteDatabase $db;

    public readonly GlobalValues $globals;

    public readonly PlayerValues $players;

    public readonly MemoryCardRepository $memory;

    public readonly RoomRepository $rooms;

    public readonly SpiritRepository $spirits;

    public readonly EgoRepository $egos;

    public readonly ObjectiveRepository $objectives;

    public readonly StoredGameView $view;

    public readonly TurnKit $kit;

    public readonly MovePhase $move;

    public readonly PublicView $publicView;

    public function __construct(int $playerCount, int $seed)
    {
        $this->seats = array_slice([11, 22, 33, 44], 0, $playerCount);
        $this->db = SqliteDatabase::create($this->seats);
        $this->globals = new GlobalValues(new Globals());
        $this->players = new PlayerValues($this->db);
        $this->memory = new MemoryCardRepository($this->db);
        $this->rooms = new RoomRepository($this->db);
        $this->spirits = new SpiritRepository($this->db);
        $this->egos = new EgoRepository($this->db);
        $this->objectives = new ObjectiveRepository($this->db);
        (new GameSetup(
            new SetupPlanner(new SeededRandom($seed)),
            $this->memory,
            $this->rooms,
            $this->spirits,
            $this->egos,
            $this->objectives,
            $this->players,
            $this->globals,
        ))->run($this->seats, SetupPlanner::LAYOUT_FIXED);
        foreach ($this->seats as $i => $id) {
            $this->db->execute(
                "UPDATE `player` SET `player_name` = '" . self::NAMES[$i] . "', `player_color` = '" . self::COLORS[$i]
                . "' WHERE `player_id` = $id",
            );
        }

        $specs = new CatalogSpecSource();
        $this->view = new StoredGameView($this->memory, $this->egos, $this->players, $this->globals, $this->rooms, $this->spirits);
        $active = new ActiveSpecs($this->view, $specs, new ConditionEvaluator($this->view));
        $light = new LightScheduleQuery($active);
        $payments = new MovePaymentQuery($active, new KeyViewQuery($active), $light);
        $stack = new EffectStack($this->db);
        $this->kit = new TurnKit(
            $this->memory,
            $this->rooms,
            $this->spirits,
            $this->egos,
            $this->players,
            $this->globals,
            $stack,
            $this->view,
            $specs,
            fn(int $id): string => $this->nameOf($id),
            static function (): void {
            },
        );
        $this->move = new MovePhase(
            new MoveBoardReader($this->memory, $this->rooms, $this->players, $this->globals),
            new MovePlanner($payments, $light),
            new MoveExecutor($this->memory, $this->players, $this->globals),
            new HandLimit($this->memory, $this->players, $stack),
            $payments,
            $light,
        );
        $energy = new ComposeEnergyQuery($active);
        $this->publicView = new PublicView(
            $this->memory,
            $this->rooms,
            $this->spirits,
            $this->egos,
            $this->objectives,
            $this->players,
            $this->globals,
            static fn(int $playerId, int $cardId): int => $energy->heldEnergy($playerId, $cardId),
            static fn(int $playerId): int => 0,
        );
    }

    public function nameOf(int $playerId): string
    {
        $i = array_search($playerId, $this->seats, true);

        return self::NAMES[$i === false ? 0 : $i];
    }

    /**
     * 人格を置く。ターンプレイヤーなら、ターン開始時の位置も覚える。
     */
    public function place(int $player, int $pos): void
    {
        $this->players->setPersona($player, $pos);
        if ($player === $this->globals->turnPlayer()) {
            $context = $this->globals->turnContext();
            $context->startPos = $pos;
            $this->globals->setTurnContext($context);
        }
    }

    /**
     * ターンの頭の状態にする（手番の人、ターン番号、フェイズ、空のターンの記録）。
     */
    public function startTurn(int $player, int $turnNo, \Bga\Games\IRevolveThereforeIAm\Data\GamePhase $phase): void
    {
        $this->globals->setTurnPlayer($player);
        $this->globals->setTurnNo($turnNo);
        $this->globals->setPhase($phase);
        $context = new TurnContext();
        $context->startPos = $this->players->persona($player);
        $this->globals->setTurnContext($context);
    }

    /**
     * 山札から手札へ引く。
     */
    public function draw(int $player, int $level, int $count): void
    {
        $this->memory->drawFromDeck($level, $count, MemoryLocation::Hand, $player);
    }

    /**
     * 手札を指定の枚数にする（足りなければレベル 1 の山から引き、多ければ捨札に送る）。
     */
    public function handSize(int $player, int $size): void
    {
        $hand = $this->memory->inLoc(MemoryLocation::Hand, $player);
        for ($i = count($hand); $i > $size; $i--) {
            $this->memory->move($hand[$i - 1]->id, MemoryLocation::Discard1);
        }
        if (count($hand) < $size) {
            $this->draw($player, 1, $size - count($hand));
        }
    }

    /**
     * 自己構成済みにする。同じ名前（種類）は 1 枚までなので、重なったカードは飛ばして次のカードを使う。
     *
     * @param list<int> $tappedIndexes 使用済み（横向き）にする、何枚目か（0 から）
     */
    public function compose(int $player, int $level1, int $level2, array $tappedIndexes = []): void
    {
        $ids = [];
        foreach ([1 => $level1, 2 => $level2] as $level => $count) {
            $done = 0;
            foreach ($this->memory->peekDeck($level, 80) as $cardId) {
                if ($done >= $count) {
                    break;
                }
                $this->memory->move($cardId, MemoryLocation::Hand, $player);
                try {
                    $this->memory->compose($cardId, $player);
                } catch (\LogicException) {
                    $this->memory->move($cardId, MemoryLocation::deck($level));
                    continue;
                }
                $ids[] = $cardId;
                $done++;
            }
        }
        $this->memory->setTapped(array_values(array_intersect_key($ids, array_flip($tappedIndexes))), true);
    }

    /**
     * 精神カードを第一階層から指定の階層まで進める（階層を 1 つずつ。心光のトークンは取る）。
     * 第一・第三階層は 2 枚のうち、`$branch`（0 か 1）番目を選ぶ。
     */
    public function spirit(int $player, int $toTier, int $branch = 0): void
    {
        for ($tier = 1; $tier <= $toTier; $tier++) {
            $cards = array_values(array_filter($this->spirits->all(), static fn(array $c): bool => $c['tier'] === $tier));
            $id = $cards[min($branch, count($cards) - 1)]['id'];
            $this->spirits->advance($player, $id);
            $this->spirits->takeLight($id);
        }
    }

    /**
     * 捨札へ送る（山札の上から）。
     */
    public function discard(int $level, int $count): void
    {
        foreach ($this->memory->peekDeck($level, $count) as $cardId) {
            $this->memory->move($cardId, MemoryLocation::deck($level) === MemoryLocation::Deck1
                ? MemoryLocation::Discard1
                : MemoryLocation::Discard2);
        }
    }

    /**
     * 部屋の想い出カードの種類（M 番号）を書き換える。
     */
    public function roomCard(int $pos, int $type): int
    {
        $this->db->execute("UPDATE `memory_card` SET `card_type` = $type WHERE `card_loc` = 'ROOM' AND `card_loc_arg` = $pos");

        return $this->memory->inLoc(MemoryLocation::Room, $pos)[0]->id;
    }

    /**
     * 本物の `getAllDatas` と同じ形（フレームワークが足す `players` と `playerorder` を含む）。
     *
     * @return array<string, mixed>
     */
    public function gamedatas(int $viewer): array
    {
        $data = $this->publicView->allDatas($viewer);
        $players = [];
        foreach ($this->db->rows('SELECT `player_id`, `player_no`, `player_name`, `player_color` FROM `player` ORDER BY `player_no`') as $row) {
            $id = (int) $row['player_id'];
            $players[$id] = [
                'id' => $id,
                'no' => (int) $row['player_no'],
                'name' => (string) $row['player_name'],
                'color' => (string) $row['player_color'],
                'score' => 0,
            ];
        }

        return ['players' => $players, 'playerorder' => $this->seats] + $data;
    }
}
