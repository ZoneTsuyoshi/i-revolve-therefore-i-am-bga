<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * イベント（008 §4.1。8 種）。値は仕様書の ID。
 */
enum Event: string
{
    case TURN_START = 'EV-01';
    case MOVE_ENDED = 'EV-02';
    case PERSONA_STOPPED = 'EV-03';
    case PERSONA_PASSED_OTHER = 'EV-04';
    case COMPOSED = 'EV-05';
    case TIER_UP = 'EV-06';
    case LIGHT_GAINED = 'EV-07';
    case FLOOR_GAIN = 'EV-08';
}
