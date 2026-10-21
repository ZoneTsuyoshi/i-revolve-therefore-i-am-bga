<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\ComposeEnergyQuery;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;

/**
 * N35 `handEnergyUpdated` を作る（仕様書 007 §10、008 MD-02）。M098 を手札に持つ本人にだけ、手札の現在の自己構成エネルギーを送る。
 * 前回の値は 1 リクエストの中のメモリにだけ持つ（{@see ScoreReporter} と同じ）。{@see self::prime()} で基準を取り、
 * {@see self::report()} が、前回から変わった人のうち印刷の値と違うカードがある人（または前回は違っていた人）の分を返す。
 * 印刷の値と同じなら、クライアントは印刷の値を使うので送らない。
 */
final class HandEnergyReporter
{
    /** @var array<int, array<int, int>>|null プレイヤー ID → (card_id → 現在のエネルギー) */
    private ?array $last = null;

    /**
     * @param \Closure(): list<int> $seats 席順のプレイヤー ID
     */
    public function __construct(
        private readonly ComposeEnergyQuery $energy,
        private readonly GameView $view,
        private readonly \Closure $seats,
    ) {
    }

    public function prime(): void
    {
        $this->last = $this->snapshot();
    }

    /**
     * @return list<Notice>
     */
    public function report(): array
    {
        $now = $this->snapshot();
        $notices = [];
        foreach ($now as $playerId => $energies) {
            $before = $this->last[$playerId] ?? [];
            if ($energies === $before) {
                continue;
            }
            if ($this->deviates($energies) || $this->deviates($before)) {
                $notices[] = Notification::handEnergyUpdated($playerId, $energies);
            }
        }
        $this->last = $now;

        return $notices;
    }

    /**
     * 本人の手札の現在のエネルギー（N35 の `energies` と同じ形）。
     *
     * @return array<int, int> card_id → エネルギー
     */
    public function energiesOf(int $playerId): array
    {
        $out = [];
        foreach ($this->view->handIds($playerId) as $cardId) {
            $out[$cardId] = $this->energy->heldEnergy($playerId, $cardId);
        }

        return $out;
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function snapshot(): array
    {
        $out = [];
        foreach (($this->seats)() as $playerId) {
            $out[$playerId] = $this->energiesOf($playerId);
        }

        return $out;
    }

    /**
     * @param array<int, int> $energies
     */
    private function deviates(array $energies): bool
    {
        foreach ($energies as $cardId => $energy) {
            if (MemoryCards::get($this->view->cardType($cardId))->energy !== $energy) {
                return true;
            }
        }

        return false;
    }
}
