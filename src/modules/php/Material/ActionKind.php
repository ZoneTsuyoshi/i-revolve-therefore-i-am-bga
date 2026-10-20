<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 床アクションの代わりに行える行動の種類（OP-24。008 §3.1）。
 */
enum ActionKind: string
{
    case ANALYSIS = 'ANALYSIS';
    case COMPOSE = 'COMPOSE';
}
