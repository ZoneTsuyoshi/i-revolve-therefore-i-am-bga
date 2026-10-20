<?php

declare(strict_types=1);

namespace IRevolveTests\Support;

/**
 * 種付きの乱数（SetupPlanner に注入する。mt_rand のグローバルな種を使わない xorshift32）。
 */
final class SeededRandom
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = ($seed * 2654435761 + 1) & 0xFFFFFFFF;
        if ($this->state === 0) {
            $this->state = 1;
        }
    }

    /**
     * 両端を含む乱数。
     */
    public function __invoke(int $min, int $max): int
    {
        $x = $this->state;
        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= $x >> 17;
        $x ^= ($x << 5) & 0xFFFFFFFF;
        $this->state = $x;

        return $min + $x % ($max - $min + 1);
    }
}
