<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 効果の仕組み（008 §1.1、§2.1）。
 */
enum Mechanism: string
{
    /** イベントで誘発して原子操作の列を実行する */
    case TRIGGER = 'TRIGGER';

    /** 数値や選択肢を決める時点で問い合わせに答える */
    case MODIFIER = 'MODIFIER';

    /** 想起効果・床アクション・精神分析ボーナス・周遊ボーナスのような、場所やカードが定める行動 */
    case ACTION = 'ACTION';

    /** 目標カード */
    case OBJECTIVE = 'OBJECTIVE';
}
