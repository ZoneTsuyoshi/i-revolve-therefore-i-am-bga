<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 制御構造（008 §3.2。4 種）。
 */
enum Control: string
{
    /** 列を左から右へ順に実行する */
    case SEQ = 'SEQ';

    /** 分岐のうち 1 つを選ぶ（「/」） */
    case CHOOSE_ONE = 'CHOOSE_ONE';

    /** 「してもよい」 */
    case OPTIONAL = 'OPTIONAL';

    /** 値式で決まる回数だけ列を繰り返す */
    case REPEAT = 'REPEAT';
}
