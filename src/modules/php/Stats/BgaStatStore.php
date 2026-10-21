<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Stats;

use Bga\GameFramework\PlayerStats;
use Bga\GameFramework\TableStats;

/**
 * {@see StatStore} の BGA 実装（`$this->bga->tableStats` / `$this->bga->playerStats`）。
 */
final class BgaStatStore implements StatStore
{
    public function __construct(
        private readonly TableStats $table,
        private readonly PlayerStats $player,
    ) {
    }

    public function initTable(string $name, int $value): void
    {
        $this->table->init($name, $value);
    }

    public function initPlayer(string $name, int $value): void
    {
        $this->player->init($name, $value);
    }

    public function setTable(string $name, int $value): void
    {
        $this->table->set($name, $value);
    }

    public function incTable(string $name, int $delta): void
    {
        $this->table->inc($name, $delta);
    }

    public function getTable(string $name): int
    {
        return (int) $this->table->get($name);
    }

    public function setPlayer(string $name, int $value, int $playerId): void
    {
        $this->player->set($name, $value, $playerId);
    }

    public function incPlayer(string $name, int $delta, int $playerId): void
    {
        $this->player->inc($name, $delta, $playerId);
    }

    public function getPlayer(string $name, int $playerId): int
    {
        return (int) $this->player->get($name, $playerId);
    }
}
