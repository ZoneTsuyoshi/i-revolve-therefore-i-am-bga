<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

/**
 * 遷移名（仕様書 006 §3.2 の「遷移」列）。状態クラスの transitions の宣言と、onEnteringState・act の戻り値で使う。
 *
 * 70 EffectResolve の resume は、006 §3.2 では状態 ID（int）を返すとするが、未宣言の ID へ遷移できるかは
 * Studio で未検証（006 §14 V-1）。そのため V-1 の代替案（戻り先ごとの遷移名の宣言）を最初から採用し、
 * resume の値を RESUME_* の遷移名へ変換する（resumeTransition）。
 */
final class Transition
{
    // セットアップ（20〜25）
    public const PICK = 'pick';
    public const REVEAL = 'reveal';
    public const PICKED = 'picked';
    public const DONE = 'done';
    public const PLACE = 'place';
    public const PLACED = 'placed';
    public const START = 'start';

    // 手番
    public const EFFECTS = 'effects';
    public const MOVE = 'move';
    public const MOVED = 'moved';
    public const RESOLVE = 'resolve';
    public const CHOOSE = 'choose';
    public const EXECUTE = 'execute';
    public const CHOSEN = 'chosen';
    public const ACT = 'act';
    public const END = 'end';
    public const CHOICE = 'choice';
    public const COMPOSE = 'compose';
    public const ANALYSIS = 'analysis';
    public const HAND_LIMIT = 'handLimit';
    public const ANSWERED = 'answered';
    public const DISCARDED = 'discarded';
    public const ASK = 'ask';
    public const SKIP = 'skip';
    public const NEXT = 'next';

    // 70 の戻り先（resume）と終局
    public const RESUME_MOVE = 'toResume31';
    public const RESUME_RECALL = 'toResume40';
    public const RESUME_FLOOR = 'toResume50';
    public const RESUME_CONSTRUCT = 'toResume60';
    public const RESUME_CLEANUP = 'toResume80';
    public const GAME_END = 'gameEnd';

    private function __construct()
    {
    }

    /**
     * 70 の resume（状態 ID）に対応する遷移名。
     *
     * @throws \LogicException resume が未設定または 70 の戻り先として不正
     */
    public static function resumeTransition(int $resume): string
    {
        return match ($resume) {
            StateId::MOVE => self::RESUME_MOVE,
            StateId::RECALL_START => self::RESUME_RECALL,
            StateId::FLOOR_ACTION_START => self::RESUME_FLOOR,
            StateId::CONSTRUCT => self::RESUME_CONSTRUCT,
            StateId::CLEANUP => self::RESUME_CLEANUP,
            default => throw new \LogicException("invalid resume state: $resume"),
        };
    }

    /**
     * 70 が宣言する遷移（resume の 5 種類）。
     *
     * @return array<string, int>
     */
    public static function resumeDeclarations(): array
    {
        return [
            self::RESUME_MOVE => StateId::MOVE,
            self::RESUME_RECALL => StateId::RECALL_START,
            self::RESUME_FLOOR => StateId::FLOOR_ACTION_START,
            self::RESUME_CONSTRUCT => StateId::CONSTRUCT,
            self::RESUME_CLEANUP => StateId::CLEANUP,
        ];
    }
}
