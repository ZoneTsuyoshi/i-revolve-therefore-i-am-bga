<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 床カードの周遊ボーナス（002 §7、§12）。レベル 2 の床（四隅）のみが持つ。
 */
enum FloorBonus: string
{
    /** 周遊ボーナスなし（レベル 1 の床） */
    case NONE = 'NONE';

    /** 精神分析トークン */
    case ANALYSIS_TOKEN = 'ANALYSIS_TOKEN';

    /** 心光トークン */
    case LIGHT = 'LIGHT';

    /** 自己構成トークン */
    case COMPOSE_TOKEN = 'COMPOSE_TOKEN';

    /** 万能移動鍵トークン */
    case WILD_MOVE_KEY = 'WILD_MOVE_KEY';
}
