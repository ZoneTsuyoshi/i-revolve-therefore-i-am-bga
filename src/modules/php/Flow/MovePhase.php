<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Effects\Query\LightScheduleQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\MovePaymentQuery;
use Bga\Games\IRevolveThereforeIAm\Material\ActionContext;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;

/**
 * 移動フェイズ（31 `Move`）の入口。盤面の読み込み → 検証（`MovePlanner`）→ 適用（`MoveExecutor`）→ 手札上限の確認を束ねる。
 * 状態クラスは、これを呼んで通知を送り、遷移を決めるだけにする。
 */
final class MovePhase
{
    /** args に出す心光の逓増の長さ（経路の上限と同じ） */
    private const LIGHT_COST_LENGTH = MovePlanner::MAX_ROOMS * 2;

    public function __construct(
        private readonly MoveBoardReader $reader,
        private readonly MovePlanner $planner,
        private readonly MoveExecutor $executor,
        private readonly HandLimit $handLimit,
        private readonly MovePaymentQuery $payments,
        private readonly LightScheduleQuery $light,
    ) {
    }

    /**
     * 経路と支払いを検証して適用する。刹那の追憶が成立しなければ、移動フェイズ全体の終わりとして手札上限の確認を積む（Q-41）。
     *
     * @param array<string, mixed> $plan
     * @param bool $zombie true なら、手札を使えば払えるときも移動しない入力を許す（006 §10）
     * @throws MoveViolation
     */
    public function submit(int $player, string $playerName, array $plan, bool $zombie = false): MoveResult
    {
        $board = $this->reader->read($player);
        $outcome = $this->planner->plan($board, $plan, $zombie);
        $result = $this->executor->apply($board, $outcome, $playerName);
        if (!$result->ended) {
            $this->handLimit->pushCheckIfNeeded($player);
        }

        return $result;
    }

    /**
     * zombie の入力（006 §10）。
     *
     * @return array<string, mixed>
     */
    public function zombiePlan(int $player): array
    {
        return $this->planner->autoPlan($this->reader->read($player));
    }

    /**
     * 31 の args のうち、クライアントが経路を計画するための部分（公開情報のみ）と、本人の手札（`hand`）。
     * 手札は呼び出し側が `_private` に入れる。
     *
     * @return array{public: array<string, mixed>, hand: list<array<string, mixed>>}
     */
    public function planningData(int $player): array
    {
        $board = $this->reader->read($player);

        $rooms = [];
        foreach ($board->rooms as $room) {
            $rooms[] = [
                'pos' => $room->pos,
                'level' => $room->level,
                'lock' => $room->lock?->value,
                'locks' => $room->level,
                'bonus' => $room->bonus->value,
            ];
        }
        $lightCosts = [];
        for ($n = 1; $n <= self::LIGHT_COST_LENGTH; $n++) {
            $lightCosts[] = $this->light->lightsForUse($player, ActionContext::MOVE, $n);
        }
        $serves = [];
        foreach (KeyType::cases() as $held) {
            $serves[$held->value] = array_values(array_map(
                static fn(KeyType $lock): string => $lock->value,
                array_filter(
                    [KeyType::WISDOM, KeyType::WILL, KeyType::EMOTION],
                    fn(KeyType $lock): bool => $this->payments->canPayLock($player, $lock, $held),
                ),
            ));
        }

        $tableau = [];
        foreach ($board->tableau as $card) {
            $tableau[] = $this->cardView($card);
        }
        $hand = [];
        foreach ($board->hand as $card) {
            $hand[] = $this->cardView($card);
        }

        return [
            'public' => [
                'position' => $board->position,
                'rooms' => $rooms,
                'maxRooms' => MovePlanner::MAX_ROOMS,
                'canMove' => $this->planner->canMove($board),
                'resources' => [
                    'light' => $board->lights,
                    'wildUp' => $board->wildUp,
                    'lightCosts' => $lightCosts,
                    'grantedKeys' => $board->priorLv2Entries === 0 ? $this->payments->usableGrantedWildKeys($player, 2) : 0,
                ],
                'keyServes' => $serves,
                'tableau' => $tableau,
            ],
            'hand' => $hand,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cardView(MoveCard $card): array
    {
        return [
            'id' => $card->id,
            'type' => $card->type,
            'keys' => array_map(static fn(KeyType $k): string => $k->value, $card->keys),
            'tapped' => $card->tapped,
        ];
    }
}
