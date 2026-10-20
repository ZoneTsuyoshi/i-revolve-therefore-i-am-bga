<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 心光の行使（MD-04）と鍵の読み替え（MD-05）が働くアクション・フェイズ（008 §5.1）。
 */
enum ActionContext: string
{
    /** 移動フェイズ（31） */
    case MOVE = 'MOVE';

    /** 自己構成アクション（61） */
    case COMPOSE = 'COMPOSE';

    /** 精神分析アクション（62） */
    case ANALYSIS = 'ANALYSIS';
}
