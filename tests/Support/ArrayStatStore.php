<?php

declare(strict_types=1);

namespace IRevolveTests\Support;

use Bga\Games\IRevolveThereforeIAm\Stats\StatStore;

/**
 * `StatStore` のメモリ実装（PHPUnit 用）。プレイヤー統計は初期化時に渡した席の全員分を持つ。
 */
final class ArrayStatStore implements StatStore
{
    /** @var array<string, int> */
    public array $table = [];

    /** @var array<string, array<int, int>> */
    public array $player = [];

    /**
     * @param list<int> $seats
     */
    public function __construct(private readonly array $seats)
    {
    }

    public function initTable(string $name, int $value): void
    {
        $this->table[$name] = $value;
    }

    public function initPlayer(string $name, int $value): void
    {
        foreach ($this->seats as $id) {
            $this->player[$name][$id] = $value;
        }
    }

    public function setTable(string $name, int $value): void
    {
        $this->assertTable($name);
        $this->table[$name] = $value;
    }

    public function incTable(string $name, int $delta): void
    {
        $this->assertTable($name);
        $this->table[$name] += $delta;
    }

    public function getTable(string $name): int
    {
        $this->assertTable($name);

        return $this->table[$name];
    }

    public function setPlayer(string $name, int $value, int $playerId): void
    {
        $this->assertPlayer($name, $playerId);
        $this->player[$name][$playerId] = $value;
    }

    public function incPlayer(string $name, int $delta, int $playerId): void
    {
        $this->assertPlayer($name, $playerId);
        $this->player[$name][$playerId] += $delta;
    }

    public function getPlayer(string $name, int $playerId): int
    {
        $this->assertPlayer($name, $playerId);

        return $this->player[$name][$playerId];
    }

    private function assertTable(string $name): void
    {
        if (!isset($this->table[$name])) {
            throw new \LogicException("table stat is not initialized: $name");
        }
    }

    private function assertPlayer(string $name, int $playerId): void
    {
        if (!isset($this->player[$name][$playerId])) {
            throw new \LogicException("player stat is not initialized: $name ($playerId)");
        }
    }
}
