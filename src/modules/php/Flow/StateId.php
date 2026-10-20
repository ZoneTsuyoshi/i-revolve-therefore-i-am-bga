<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

/**
 * 状態 ID（仕様書 006 §3）。transitions の行き先に使う。
 * 1 と 99 は BGA の予約、10・90・98 は Studio の雛形の ID なので使わない。本番稼働後は値を変えない。
 * BGA は States/ の中の全クラスを状態として読むので、この定数クラスは States/ に置かない。
 */
final class StateId
{
    public const SETUP_EGO_DRAFT_NEXT = 20;
    public const SETUP_EGO_PICK = 21;
    public const SETUP_EGO_REVEAL = 22;
    public const SETUP_MEEPLE_NEXT = 23;
    public const SETUP_MEEPLE_PLACE = 24;
    public const SETUP_FINISH = 25;

    public const TURN_START = 30;
    public const MOVE = 31;

    public const RECALL_START = 40;

    public const FLOOR_ACTION_START = 50;
    public const FLOOR_ACTION_CHOOSE = 51;

    public const CONSTRUCT = 60;
    public const COMPOSE = 61;
    public const ANALYSIS = 62;

    public const EFFECT_RESOLVE = 70;
    public const EFFECT_CHOICE = 71;
    public const HAND_LIMIT_DISCARD = 72;

    public const CLEANUP = 80;
    public const TURN_END = 81;
    public const NEXT_TURN = 82;

    public const GAME_END = 95;

    private function __construct()
    {
    }
}
