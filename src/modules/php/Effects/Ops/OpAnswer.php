<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops;

/**
 * 原子操作に渡す答え。自動で解決する、選択をスキップした、または選択の内容。
 */
final readonly class OpAnswer
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        public bool $skipped,
        public array $data,
    ) {
    }

    /** 選択が要らない操作を自動で解決する。 */
    public static function auto(): self
    {
        return new self(false, []);
    }

    /** 選択をスキップした（`actSkipChoice`）。何も起こさない。 */
    public static function skipped(): self
    {
        return new self(true, []);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function of(array $data): self
    {
        return new self(false, $data);
    }
}
