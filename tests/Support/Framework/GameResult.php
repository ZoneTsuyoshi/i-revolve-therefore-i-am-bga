<?php

declare(strict_types=1);

namespace Bga\GameFramework\GameResult;

/**
 * BGA フレームワークの最小のスタブ（PHPUnit 専用）。
 */
class GameResult
{
    public static function individualRanking(): self
    {
        return new self();
    }
}
