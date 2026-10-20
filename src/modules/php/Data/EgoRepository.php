<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Data;

/**
 * ego_card テーブル（初期自我 8・強化自我 5。仕様書 007 §5.3）。
 */
final class EgoRepository
{
    public const OUT = 'OUT';
    public const PACK = 'PACK';
    public const PICKED = 'PICKED';
    public const ENH_DECK = 'ENH_DECK';
    public const OWNED = 'OWNED';

    private const LOCATIONS = [self::OUT, self::PACK, self::PICKED, self::ENH_DECK, self::OWNED];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param list<array{id: string, loc: string, owner?: ?int, pos?: int}> $cards
     */
    public function create(array $cards): void
    {
        $values = [];
        foreach ($cards as $c) {
            $this->assertId($c['id']);
            $this->assertLoc($c['loc']);
            $owner = $c['owner'] ?? null;
            $values[] = sprintf("('%s', '%s', %s, %d)", $c['id'], $c['loc'], $owner === null ? 'NULL' : (string) $owner, $c['pos'] ?? 0);
        }
        if ($values !== []) {
            $this->db->execute(
                'INSERT INTO `ego_card` (`ego_id`, `ego_loc`, `ego_owner`, `ego_pos`) VALUES ' . implode(', ', $values),
            );
        }
    }

    /**
     * @return list<array{id: string, loc: string, owner: ?int, pos: int}>
     */
    public function inLoc(string $loc, ?int $owner = null): array
    {
        $this->assertLoc($loc);
        $where = "`ego_loc` = '$loc'" . ($owner === null ? '' : " AND `ego_owner` = $owner");
        $rows = $this->db->rows(
            "SELECT `ego_id`, `ego_loc`, `ego_owner`, `ego_pos` FROM `ego_card` WHERE $where ORDER BY `ego_pos` ASC, `ego_id` ASC",
        );
        return array_map(static fn(array $r): array => [
            'id' => (string) $r['ego_id'],
            'loc' => (string) $r['ego_loc'],
            'owner' => $r['ego_owner'] === null ? null : (int) $r['ego_owner'],
            'pos' => (int) $r['ego_pos'],
        ], $rows);
    }

    public function count(string $loc, ?int $owner = null): int
    {
        return count($this->inLoc($loc, $owner));
    }

    /**
     * 所在と持ち主を変える。
     */
    public function move(string $egoId, string $loc, ?int $owner = null): void
    {
        $this->assertId($egoId);
        $this->assertLoc($loc);
        $this->db->execute(
            "UPDATE `ego_card` SET `ego_loc` = '$loc', `ego_owner` = " . ($owner === null ? 'NULL' : (string) $owner)
            . " WHERE `ego_id` = '$egoId'",
        );
    }

    /**
     * 強化自我を獲得する（ENH_DECK → OWNED）。1 人 1 枚まで。
     */
    public function obtainEnhancement(string $egoId, int $playerId): void
    {
        $this->assertId($egoId);
        if (preg_match('/^E\d\d$/', $egoId) !== 1) {
            throw new \InvalidArgumentException("not an enhancement ego: $egoId");
        }
        $owned = (int) $this->db->value(
            "SELECT COUNT(*) FROM `ego_card` WHERE `ego_loc` = 'OWNED' AND `ego_owner` = $playerId AND `ego_id` LIKE 'E%'",
        );
        if ($owned > 0) {
            throw new \LogicException("player $playerId already has an enhancement ego");
        }
        $inDeck = (int) $this->db->value("SELECT COUNT(*) FROM `ego_card` WHERE `ego_id` = '$egoId' AND `ego_loc` = 'ENH_DECK'");
        if ($inDeck !== 1) {
            throw new \LogicException("enhancement ego $egoId is not in the deck");
        }
        $this->move($egoId, self::OWNED, $playerId);
    }

    private function assertId(string $id): void
    {
        if (preg_match('/^[IE]\d\d$/', $id) !== 1) {
            throw new \InvalidArgumentException("invalid ego id: $id");
        }
    }

    private function assertLoc(string $loc): void
    {
        if (!in_array($loc, self::LOCATIONS, true)) {
            throw new \InvalidArgumentException("invalid ego location: $loc");
        }
    }
}
