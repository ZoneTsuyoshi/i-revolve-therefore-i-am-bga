<?php

declare(strict_types=1);

namespace Bga\GameFramework\Db;

/**
 * BGA の Globals のメモリ実装（PHPUnit 専用）。JSON で往復させて、本物と同じく配列・数値・null だけが残るようにする。
 */
class Globals
{
    /** @var array<string, string> */
    private array $values = [];

    public function get(string $name, mixed $defaultValue = null, ?string $class = null): mixed
    {
        return array_key_exists($name, $this->values) ? json_decode($this->values[$name], true) : $defaultValue;
    }

    public function set(string $name, mixed $value): void
    {
        $this->values[$name] = json_encode($value, JSON_THROW_ON_ERROR);
    }
}
