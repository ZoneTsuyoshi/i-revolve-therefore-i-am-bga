<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 獲得の出どころ（008 §6.6）。反応する効果が「何による獲得か」を判定する印。
 */
enum Origin: string
{
    /** 床アクションの要素として直接列挙された OP-01 / OP-02 / OP-03（M085〜M087 が反応する） */
    case FLOOR = 'FLOOR';

    /** 精神カード上の心光（OP-05。I02 が反応する） */
    case SPIRIT_CARD = 'SPIRIT_CARD';

    /** 周遊ボーナス */
    case TOUR = 'TOUR';

    /** 想起効果 */
    case RECALL = 'RECALL';

    /** 精神分析ボーナス */
    case BONUS = 'BONUS';

    /** カード効果 */
    case EFFECT = 'EFFECT';

    /** M085〜M087 と I02 の追加分（連鎖しない） */
    case EXTRA = 'EXTRA';
}
