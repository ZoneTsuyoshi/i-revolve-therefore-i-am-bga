<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\EffectStack;
use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\SpiritRepository;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;

/**
 * 勝利判定（仕様書 006 §9、008 §6.8 の T8-11）。スコアや階層が変わりうる操作の直後に、全プレイヤーを評価する。
 *
 * - 断片の結集: 自己構成スコア 20 以上（{@see ScoreCalculator}）。
 * - 心理の極致: 精神階層が第五階層。
 * - 刹那の追憶: 入室の瞬間に `StoredGameMutator::movePersona` / `MoveExecutor` が直接書く（既に end_reason があれば何もしない）。
 *
 * 複数のプレイヤーが同時に満たしたときは、効果を解決していたプレイヤーを先頭に、ターンプレイヤーから時計回りで
 * 最初に満たした 1 人が勝つ（Q-40）。成立したら end_reason と winner_id を書く。
 */
final class WinChecker
{
    public function __construct(
        private readonly ScoreCalculator $scores,
        private readonly PlayerValues $players,
        private readonly SpiritRepository $spirits,
        private readonly GlobalValues $globals,
        private readonly EffectStack $stack,
    ) {
    }

    /**
     * @return bool 勝者が決まった（この呼び出しか、以前の呼び出しで）
     */
    public function check(): bool
    {
        if ($this->globals->endReason() !== null) {
            return true;
        }
        $seats = $this->players->playerIds();
        if ($seats === []) {
            return false;
        }
        $scores = $this->scores->scores($seats);
        foreach ($this->order($seats) as $playerId) {
            $reason = $this->reasonFor($playerId, $scores[$playerId]['total'] ?? 0);
            if ($reason !== null) {
                $this->globals->setEndReason($reason);
                $this->globals->setWinnerId($playerId);

                return true;
            }
        }

        return false;
    }

    /**
     * 評価の順（Q-40）: 解決を行っているプレイヤー、続いてターンプレイヤーから時計回り。
     *
     * @param list<int> $seats
     * @return list<int>
     */
    private function order(array $seats): array
    {
        $turn = $this->globals->turnPlayer();
        $order = in_array($turn, $seats, true) ? SeatOrder::startingFrom($seats, $turn) : $seats;
        $resolver = $this->stack->peek()?->owner;
        if ($resolver !== null && in_array($resolver, $seats, true)) {
            $order = [$resolver, ...array_values(array_filter($order, static fn (int $id): bool => $id !== $resolver))];
        }

        return $order;
    }

    private function reasonFor(int $playerId, int $total): ?string
    {
        $spirit = $this->players->spirit($playerId);
        if ($spirit !== null && $this->spirits->tierOf($spirit) >= GameProgress::TOP_TIER) {
            return 'APEX_OF_PSYCHE';
        }
        if ($total >= GameProgress::WIN_SCORE) {
            return 'FRAGMENTS';
        }

        return null;
    }
}
