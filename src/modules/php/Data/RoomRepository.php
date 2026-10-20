<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Data;

/**
 * room テーブル（回廊の 12 の部屋。位置 → 床カード。仕様書 007 §5.1）。
 * 部屋の想い出カードは「位置」に付く（memory_card.card_loc = ROOM）ので、床の入れ替えは room_floor の入れ替えだけで済む。
 */
final class RoomRepository
{
    public const ROOM_COUNT = 12;

    /** レベル 2 の位置（四隅）。 */
    public const LEVEL2_POSITIONS = [1, 4, 7, 10];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * 位置のレベル（位置 1・4・7・10 が 2、他は 1）。床の入れ替えはレベル 1 の位置同士でしか起きない。
     */
    public static function levelOfPosition(int $pos): int
    {
        if ($pos < 1 || $pos > self::ROOM_COUNT) {
            throw new \InvalidArgumentException("invalid room position: $pos");
        }
        return in_array($pos, self::LEVEL2_POSITIONS, true) ? 2 : 1;
    }

    /**
     * 配置を作る。
     *
     * @param array<int, int> $floorByPosition 位置（1〜12）→ 床カード ID（1〜12）
     */
    public function setup(array $floorByPosition): void
    {
        if (count($floorByPosition) !== self::ROOM_COUNT) {
            throw new \InvalidArgumentException('room layout must have 12 positions');
        }
        $values = [];
        foreach ($floorByPosition as $pos => $floor) {
            if ($pos < 1 || $pos > self::ROOM_COUNT || $floor < 1 || $floor > self::ROOM_COUNT) {
                throw new \InvalidArgumentException("invalid room layout: $pos => $floor");
            }
            $values[] = '(' . (int) $pos . ', ' . (int) $floor . ')';
        }
        $this->db->execute('INSERT INTO `room` (`room_pos`, `room_floor`) VALUES ' . implode(', ', $values));
    }

    /**
     * @return array<int, int> 位置 → 床カード ID（位置の昇順）
     */
    public function all(): array
    {
        $out = [];
        foreach ($this->db->rows('SELECT `room_pos`, `room_floor` FROM `room` ORDER BY `room_pos` ASC') as $r) {
            $out[(int) $r['room_pos']] = (int) $r['room_floor'];
        }
        return $out;
    }

    public function floorAt(int $pos): int
    {
        $v = $this->db->value("SELECT `room_floor` FROM `room` WHERE `room_pos` = $pos");
        if ($v === null || $v === false) {
            throw new \InvalidArgumentException("unknown room position: $pos");
        }
        return (int) $v;
    }

    /**
     * 2 つの位置の床カードを入れ替える（M055〜M057）。room_floor は UNIQUE なので一時値 0 を挟む。
     * 位置のレベルが同じ（床のレベルと位置のレベルを一致させる）場合だけ許す。
     */
    public function swapFloors(int $posA, int $posB): void
    {
        if ($posA === $posB || self::levelOfPosition($posA) !== self::levelOfPosition($posB)) {
            throw new \LogicException("cannot swap floors at $posA and $posB");
        }
        $a = $this->floorAt($posA);
        $b = $this->floorAt($posB);
        $this->db->execute("UPDATE `room` SET `room_floor` = 0 WHERE `room_pos` = $posA");
        $this->db->execute("UPDATE `room` SET `room_floor` = $a WHERE `room_pos` = $posB");
        $this->db->execute("UPDATE `room` SET `room_floor` = $b WHERE `room_pos` = $posA");
    }
}
