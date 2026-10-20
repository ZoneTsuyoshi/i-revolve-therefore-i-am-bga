<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Data;

/**
 * 想い出カードの所在（memory_card.card_loc。仕様書 007 §4.2）。
 */
enum MemoryLocation: string
{
    case Deck1 = 'DECK1';
    case Deck2 = 'DECK2';
    case Discard1 = 'DISC1';
    case Discard2 = 'DISC2';
    case Out = 'OUT';
    case Open = 'OPEN';
    case Room = 'ROOM';
    case Hand = 'HAND';
    case Tableau = 'TABLEAU';
    case UnderFloor = 'UNDER_FLOOR';
    case UnderCard = 'UNDER_CARD';

    public static function deck(int $level): self
    {
        return match ($level) {
            1 => self::Deck1,
            2 => self::Deck2,
            default => throw new \InvalidArgumentException("invalid level: $level"),
        };
    }

    public static function discard(int $level): self
    {
        return match ($level) {
            1 => self::Discard1,
            2 => self::Discard2,
            default => throw new \InvalidArgumentException("invalid level: $level"),
        };
    }

    /**
     * 捨札は最後に捨てたものが一番上（card_pos が最大）。山札は card_pos が最小のものが一番上。
     */
    public function isDeck(): bool
    {
        return $this === self::Deck1 || $this === self::Deck2;
    }
}
