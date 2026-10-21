<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Stats;

/**
 * 統計の読み書きの口（仕様書 007 §14、003 §4.2）。BGA の API（`$this->bga->tableStats` / `playerStats`）を隠し、
 * PHPUnit ではメモリ実装に差し替える。名前は `stats.jsonc` のキー。
 */
interface StatStore
{
    /** テーブル統計を全て初期値にする（`setupNewGame` で一度だけ）。 */
    public function initTable(string $name, int $value): void;

    /** プレイヤー統計を全員分、初期値にする（`setupNewGame` で一度だけ）。 */
    public function initPlayer(string $name, int $value): void;

    public function setTable(string $name, int $value): void;

    public function incTable(string $name, int $delta): void;

    public function getTable(string $name): int;

    public function setPlayer(string $name, int $value, int $playerId): void;

    public function incPlayer(string $name, int $delta, int $playerId): void;

    public function getPlayer(string $name, int $playerId): int;
}
