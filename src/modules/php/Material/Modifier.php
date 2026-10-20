<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 修飾（008 §5.1。11 種）。値は仕様書の ID。
 */
enum Modifier: string
{
    case COMPOSE_ENERGY_DELTA = 'MD-01';
    case HELD_ENERGY_DELTA = 'MD-02';
    case ANALYSIS_COST_DELTA = 'MD-03';
    case LIGHT_SCHEDULE = 'MD-04';
    case KEY_VIEW = 'MD-05';
    case MOVE_PAYMENT_GRANT = 'MD-06';
    case TAP_FOR_ENERGY = 'MD-07';
    case EXTRA_PAY_SCORE = 'MD-08';
    case ARCHITECT_SUBSTITUTE = 'MD-09';
    case ANALYSIS_PAY_REDIRECT = 'MD-10';
    case RECALL_TIMES = 'MD-11';
}
