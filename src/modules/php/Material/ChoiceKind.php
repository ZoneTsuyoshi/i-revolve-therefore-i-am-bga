<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 汎用の選択（71 `EffectChoice`）の種類（008 §6.4。14 種）。
 */
enum ChoiceKind: string
{
    case ORDER_EFFECTS = 'ORDER_EFFECTS';
    case CONFIRM_USE = 'CONFIRM_USE';
    case PICK_FIELD_CARD = 'PICK_FIELD_CARD';
    case PICK_DISCARD = 'PICK_DISCARD';
    case SELECT_DISCARD_FOR_LIGHT = 'SELECT_DISCARD_FOR_LIGHT';
    case SELECT_SWAP_HAND_FIELD = 'SELECT_SWAP_HAND_FIELD';
    case SELECT_SWAP_SAME_COST = 'SELECT_SWAP_SAME_COST';
    case CONFIRM_MOVE = 'CONFIRM_MOVE';
    case SWAP_ROOM_CARDS = 'SWAP_ROOM_CARDS';
    case SWAP_ROOM_FLOORS = 'SWAP_ROOM_FLOORS';
    case WARP_ROOM = 'WARP_ROOM';
    case ENTREPRENEUR_SWAP = 'ENTREPRENEUR_SWAP';
    case PICK_ENHANCED_EGO = 'PICK_ENHANCED_EGO';
    case PICK_SPIRIT_BRANCH = 'PICK_SPIRIT_BRANCH';

    /** `actSkipChoice` を受けるか（008 §6.4 の「スキップ」の列）。 */
    public function skippable(): bool
    {
        return match ($this) {
            self::ORDER_EFFECTS,
            self::PICK_FIELD_CARD,
            self::PICK_DISCARD,
            self::PICK_ENHANCED_EGO,
            self::PICK_SPIRIT_BRANCH => false,
            default => true,
        };
    }
}
