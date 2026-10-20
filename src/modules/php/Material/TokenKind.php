<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * OP-04 `GAIN_TOKEN` が得るトークンの種類（008 §3.1）。
 */
enum TokenKind: string
{
    /** 自己構成トークン */
    case COMPOSE = 'COMPOSE';

    /** 精神分析トークン */
    case ANALYSIS = 'ANALYSIS';

    /** 万能移動鍵トークン（表向きで得る） */
    case WILD_MOVE_KEY = 'WILD_MOVE_KEY';
}
