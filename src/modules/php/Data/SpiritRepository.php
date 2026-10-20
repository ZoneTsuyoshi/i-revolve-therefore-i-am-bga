<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Data;

/**
 * spirit_card テーブル（精神トラックの 7 枚。仕様書 007 §5.2）。プレイヤーの位置は player.player_spirit が正。
 */
final class SpiritRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param list<array{id: string, tier: int, slot: int, light: bool}> $cards
     */
    public function create(array $cards): void
    {
        $values = [];
        foreach ($cards as $c) {
            if (preg_match('/^R[1-5][A-C]?$/', $c['id']) !== 1) {
                throw new \InvalidArgumentException("invalid spirit id: {$c['id']}");
            }
            $values[] = sprintf("('%s', %d, %d, %d)", $c['id'], $c['tier'], $c['slot'], $c['light'] ? 1 : 0);
        }
        if ($values !== []) {
            $this->db->execute(
                'INSERT INTO `spirit_card` (`spirit_id`, `spirit_tier`, `spirit_slot`, `spirit_light`) VALUES '
                . implode(', ', $values),
            );
        }
    }

    /**
     * @return list<array{id: string, tier: int, slot: int, light: bool}> (tier, slot) の昇順
     */
    public function all(): array
    {
        $rows = $this->db->rows(
            'SELECT `spirit_id`, `spirit_tier`, `spirit_slot`, `spirit_light` FROM `spirit_card`'
            . ' ORDER BY `spirit_tier` ASC, `spirit_slot` ASC',
        );
        return array_map(static fn(array $r): array => [
            'id' => (string) $r['spirit_id'],
            'tier' => (int) $r['spirit_tier'],
            'slot' => (int) $r['spirit_slot'],
            'light' => (int) $r['spirit_light'] === 1,
        ], $rows);
    }

    public function tierOf(string $spiritId): int
    {
        $v = $this->db->value("SELECT `spirit_tier` FROM `spirit_card` WHERE `spirit_id` = '" . $this->db->escape($spiritId) . "'");
        if ($v === null || $v === false) {
            throw new \InvalidArgumentException("unknown spirit card: $spiritId");
        }
        return (int) $v;
    }

    public function hasLight(string $spiritId): bool
    {
        return (int) $this->db->value(
            "SELECT `spirit_light` FROM `spirit_card` WHERE `spirit_id` = '" . $this->db->escape($spiritId) . "'",
        ) === 1;
    }

    /**
     * 精神カード上の心光トークンを取る（先着。戻らない）。取れたら true。
     */
    public function takeLight(string $spiritId): bool
    {
        if (!$this->hasLight($spiritId)) {
            return false;
        }
        $this->db->execute(
            "UPDATE `spirit_card` SET `spirit_light` = 0 WHERE `spirit_id` = '" . $this->db->escape($spiritId) . "'",
        );
        return true;
    }

    /**
     * プレイヤーを精神カードへ進める。階層は 1 つずつしか上がらない（NULL = 階層 0 から第一階層へ）。
     */
    public function advance(int $playerId, string $toSpiritId): void
    {
        $current = $this->db->value("SELECT `player_spirit` FROM `player` WHERE `player_id` = $playerId");
        $currentTier = ($current === null || $current === false) ? 0 : $this->tierOf((string) $current);
        $toTier = $this->tierOf($toSpiritId);
        if ($toTier !== $currentTier + 1) {
            throw new \LogicException("cannot advance from tier $currentTier to tier $toTier");
        }
        $this->db->execute(
            "UPDATE `player` SET `player_spirit` = '" . $this->db->escape($toSpiritId) . "' WHERE `player_id` = $playerId",
        );
    }
}
