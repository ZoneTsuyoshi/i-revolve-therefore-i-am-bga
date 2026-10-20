<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 効果が働く人（008 §2.1）。
 */
enum OwnerScope: string
{
    /** 自己構成済みカード・自我カードの持ち主（ターンプレイヤーの手番中だけ反応する） */
    case OWNER = 'OWNER';

    /** 想起効果の発動者 */
    case ACTIVATOR = 'ACTIVATOR';

    /** 想起効果のうちターンプレイヤーだけが使えるもの。床・精神分析・周遊の行動もターンプレイヤー */
    case TURN_PLAYER = 'TURN_PLAYER';
}
