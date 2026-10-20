<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Engine;

use Bga\Games\IRevolveThereforeIAm\Material\Control;
use Bga\Games\IRevolveThereforeIAm\Material\Op;

/**
 * まだハンドラが無い原子操作・制御構造に当たったときの例外（実装順 4 以降。008 §11.2）。
 */
final class UnsupportedOp extends \LogicException
{
    public static function forKind(Op|Control $kind): self
    {
        return new self("Not implemented yet: {$kind->name} ({$kind->value})");
    }
}
