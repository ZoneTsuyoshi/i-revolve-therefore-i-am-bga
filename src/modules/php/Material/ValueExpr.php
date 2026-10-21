<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 値式（008 §3.3）。操作の個数（`n`）や `REPEAT` の回数に、整数の代わりに置く。解決の時点で数える（`Effects/Condition/ValueEvaluator`）。
 *
 * - `COUNT_COMPOSED`: 自己構成済みカードの枚数（横向きも含む）
 * - `COUNT_COMPOSED_KEYS(type)`: 自己構成済みカードの印刷された鍵のうち `type` の本数（万能鍵と鍵の読み替えは数えない。R8-19）
 * - `FLOOR_DIV(x, k)`: `x` を `k` で割った商
 */
final readonly class ValueExpr
{
    public const COUNT_COMPOSED = 'COUNT_COMPOSED';

    public const COUNT_COMPOSED_KEYS = 'COUNT_COMPOSED_KEYS';

    public const FLOOR_DIV = 'FLOOR_DIV';

    private function __construct(
        public string $kind,
        public ?KeyType $key = null,
        public ?self $inner = null,
        public int $divisor = 1,
    ) {
    }

    public static function composedCount(): self
    {
        return new self(self::COUNT_COMPOSED);
    }

    public static function composedKeys(KeyType $key): self
    {
        return new self(self::COUNT_COMPOSED_KEYS, $key);
    }

    public static function floorDiv(self $inner, int $divisor): self
    {
        if ($divisor < 1) {
            throw new \InvalidArgumentException('FLOOR_DIV needs a positive divisor');
        }

        return new self(self::FLOOR_DIV, null, $inner, $divisor);
    }
}
