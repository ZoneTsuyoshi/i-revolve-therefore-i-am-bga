<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 同時に誘発した効果の衝突判定に使う資源の記号（008 §6.3.2）。
 */
enum ResourceSymbol: string
{
    /** 手札の中身 */
    case H = 'H';

    /** レベル 1 の山札 */
    case D = 'D';

    /** 場札 */
    case O = 'O';

    /** 心光の個数 */
    case L = 'L';

    /** トークンの個数 */
    case T = 'T';

    /** 自己構成済みエリア */
    case B = 'B';

    /** 次の自己構成の軽減（next_compose_discount） */
    case ND = 'ND';

    /** ターンの軽減（turn_compose_discount） */
    case TD = 'TD';

    /** 床アクションの代替の記録（floor_alternatives） */
    case FA = 'FA';

    /** 人格の位置 */
    case P = 'P';

    /** 入室の記録（OP-15 の表にだけ現れる。008 §6.3.2 の表に無い記号） */
    case E = 'E';

    /** 床カードの下に置かれた想い出カード */
    case F = 'F';

    /** 精神階層・精神カード */
    case S = 'S';

    /** 自我カードの山 */
    case G = 'G';

    /** 部屋の想い出カード */
    case RM = 'RM';

    /** 部屋の床カード */
    case RF = 'RF';
}
