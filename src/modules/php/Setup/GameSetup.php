<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Setup;

use Bga\Games\IRevolveThereforeIAm\Data\EgoRepository;
use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryCardRepository;
use Bga\Games\IRevolveThereforeIAm\Data\ObjectiveRepository;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\RoomRepository;
use Bga\Games\IRevolveThereforeIAm\Data\SpiritRepository;

/**
 * `setupNewGame` の本体（仕様書 007 §13）。SetupPlanner が作った初期状態を、データ層のリポジトリ経由で DB に書く。
 * player 行は BGA が先に作っている前提（色などは Game::setupNewGame が処理済み）。
 * 初期自我のドラフトとミープルの配置はここでは行わない（006 の 20〜25。I-04）。統計の初期化は I-11。
 */
final class GameSetup
{
    public function __construct(
        private readonly SetupPlanner $planner,
        private readonly MemoryCardRepository $memoryCards,
        private readonly RoomRepository $rooms,
        private readonly SpiritRepository $spirits,
        private readonly EgoRepository $egos,
        private readonly ObjectiveRepository $objectives,
        private readonly PlayerValues $players,
        private readonly GlobalValues $globals,
    ) {
    }

    /**
     * @param list<int> $playerIds 席順（player_no の昇順 = 手番順）
     * @param int $layoutOption ゲームオプション 100「回廊の配置」の値
     */
    public function run(array $playerIds, int $layoutOption): SetupPlan
    {
        $plan = $this->planner->plan($playerIds, $layoutOption);

        $this->memoryCards->create($plan->memoryCards);
        $this->rooms->setup($plan->roomFloors);
        $this->spirits->create($plan->spirits);
        $this->egos->create($plan->egos);
        $this->objectives->create($plan->objectives);

        foreach ($plan->players as $playerId => $tokens) {
            $this->players->setToken($playerId, PlayerValues::COLUMN_LIGHT, $tokens['light']);
            $this->players->setToken($playerId, PlayerValues::COLUMN_WILD_UP, $tokens['wild_up']);
            $this->players->setToken($playerId, PlayerValues::COLUMN_COMPOSE, $tokens['compose']);
            $this->players->setToken($playerId, PlayerValues::COLUMN_ANALYSIS, $tokens['analysis']);
        }

        $this->globals->initialize();
        $this->globals->setDeckInitial(1, $plan->deck1Initial);
        $this->globals->setDeckInitial(2, $plan->deck2Initial);

        return $plan;
    }
}
