<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 条件（008 §5.2。13 種）。値は仕様書の ID。
 */
enum Condition: string
{
    case ROOMS_ENTERED_GE = 'CD-01';
    case ALL_KEY_KINDS_PAID = 'CD-02';
    case NO_WILD_TOKEN_USED = 'CD-03';
    case OTHER_PERSONA_IN_ROOM = 'CD-04';
    case FIRST_LV2_ENTRY_THIS_TURN = 'CD-05';
    case COMPOSED_CARD_HAS_KEY = 'CD-06';
    case COMPOSED_COUNT_IN = 'CD-07';
    case TIER_UP_VIA = 'CD-08';
    case DEST_HAD_NO_LIGHT = 'CD-09';
    case LIGHT_ORIGIN = 'CD-10';
    case FLOOR_ELEMENT = 'CD-11';
    case FIRST_COMPOSE_ACTION_THIS_TURN = 'CD-12';
    case COMPOSED_CARD_LEVEL = 'CD-13';
}
