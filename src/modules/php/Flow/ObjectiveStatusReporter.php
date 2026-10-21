<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Effects\Objective\ObjectiveWindow;

/**
 * N23 `objectiveStatus` を作る（仕様書 007 §10、T-10）。未獲得の目標の現在の X・得点・獲得できるかを、本人にだけ送る。
 * 前回の値は 1 リクエストの中のメモリにだけ持つ（{@see ScoreReporter} と同じ）。{@see self::prime()} で基準を取り、
 * {@see self::report()} が変わった人の分だけ、{@see self::all()} が全員の分を返す。
 */
final class ObjectiveStatusReporter
{
    /** @var array<int, string>|null プレイヤー ID → 前回の内容（JSON） */
    private ?array $last = null;

    /**
     * @param \Closure(): list<int> $seats 席順のプレイヤー ID
     * @param \Closure(): ObjectiveWindow $window いまの状態の窓
     */
    public function __construct(
        private readonly ObjectiveBoard $board,
        private readonly \Closure $seats,
        private readonly \Closure $window,
    ) {
    }

    public function prime(): void
    {
        $this->last = $this->snapshot();
    }

    /**
     * @return list<Notice> 前回から変わったプレイヤーの分
     */
    public function report(): array
    {
        $now = $this->snapshot();
        $notices = [];
        foreach ($now as $playerId => $json) {
            if ($this->last === null || ($this->last[$playerId] ?? null) !== $json) {
                $notices[] = Notification::objectiveStatus($playerId, $this->decode($json));
            }
        }
        $this->last = $now;

        return $notices;
    }

    /**
     * @return list<Notice> 全員の分（ターンの開始・終了時など、比較せずに送るとき）
     */
    public function all(): array
    {
        $now = $this->snapshot();
        $this->last = $now;

        return array_map(
            fn (string $json, int $playerId): Notice => Notification::objectiveStatus($playerId, $this->decode($json)),
            $now,
            array_keys($now),
        );
    }

    /**
     * @return array<int, string>
     */
    private function snapshot(): array
    {
        $window = ($this->window)();
        $out = [];
        foreach (($this->seats)() as $playerId) {
            $out[$playerId] = json_encode($this->board->statuses($playerId, $window), JSON_THROW_ON_ERROR);
        }

        return $out;
    }

    /**
     * @return list<array{obj_id: int, x: int, points: int, claimable: bool}>
     */
    private function decode(string $json): array
    {
        /** @var list<array{obj_id: int, x: int, points: int, claimable: bool}> $rows */
        $rows = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $rows;
    }
}
