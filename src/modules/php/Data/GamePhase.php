<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Data;

/**
 * グローバル値 phase（現在のフェイズ。仕様書 007 §7.2）。
 */
enum GamePhase: string
{
    case SetupEgo = 'SETUP_EGO';
    case SetupPersona = 'SETUP_PERSONA';
    case TurnStart = 'TURN_START';
    case Move = 'MOVE';
    case Recall = 'RECALL';
    case Floor = 'FLOOR';
    case Construct = 'CONSTRUCT';
    case Cleanup = 'CLEANUP';
    case TurnEnd = 'TURN_END';
    case End = 'END';
}
