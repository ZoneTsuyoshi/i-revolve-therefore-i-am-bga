<?php

declare(strict_types=1);

namespace Bga\GameFramework\Actions\Types;

/**
 * BGA フレームワークの最小のスタブ（PHPUnit 専用）。
 */
#[\Attribute]
class IntArrayParam
{
    public function __construct(public ?string $name = null, public ?int $min = null, public ?int $max = null)
    {
    }
}
