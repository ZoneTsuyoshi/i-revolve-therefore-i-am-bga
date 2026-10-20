<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Data;

/**
 * memory_card の 1 行（仕様書 007 §4.1）。公開範囲の判定は持たない（PublicView が行う）。
 */
final class MemoryCard
{
    public function __construct(
        public readonly int $id,
        public readonly int $type,
        public readonly MemoryLocation $location,
        public readonly int $locationArg,
        public readonly int $position,
        public readonly bool $tapped,
        public readonly bool $faceUp,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['card_id'],
            (int) $row['card_type'],
            MemoryLocation::from((string) $row['card_loc']),
            (int) $row['card_loc_arg'],
            (int) $row['card_pos'],
            (int) $row['card_tapped'] === 1,
            (int) $row['card_face_up'] === 1,
        );
    }
}
