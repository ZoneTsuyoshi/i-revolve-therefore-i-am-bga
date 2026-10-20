<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 条件 1 つ（CD-NN）とそのパラメータ（008 §5.2）。
 */
final readonly class ConditionRef
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public Condition $condition,
        public array $params = [],
    ) {
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function of(Condition $condition, array $params = []): self
    {
        return new self($condition, $params);
    }
}
