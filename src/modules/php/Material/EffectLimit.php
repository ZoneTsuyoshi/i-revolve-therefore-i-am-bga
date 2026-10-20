<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 効果の回数制限（008 §2.1 の `limit`）。
 */
enum EffectLimit: string
{
    /** イベントのたびに */
    case PER_EVENT = 'PER_EVENT';

    /** 1 ターンに 1 回（使用済みを記録する） */
    case ONCE_PER_TURN = 'ONCE_PER_TURN';
}
