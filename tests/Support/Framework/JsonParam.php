<?php

declare(strict_types=1);

namespace Bga\GameFramework\Actions\Types;

/**
 * BGA フレームワークの最小のスタブ（PHPUnit 専用）。
 */
#[\Attribute]
class JsonParam
{
    public function __construct(
        public ?string $name = null,
        public ?bool $associative = true,
        public ?bool $alphanum = true,
        public ?string $class = null,
    ) {
    }
}
