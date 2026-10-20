<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Data;

/**
 * objective_card テーブル（目標カード 7 枚。仕様書 007 §5.4）。現在の X と得点は保存しない。
 */
final class ObjectiveRepository
{
    public const OUT = 'OUT';
    public const OPEN = 'OPEN';
    public const OWNED = 'OWNED';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param list<array{id: int, state: string, slot?: ?int}> $cards
     */
    public function create(array $cards): void
    {
        $values = [];
        foreach ($cards as $c) {
            if (!in_array($c['state'], [self::OUT, self::OPEN], true)) {
                throw new \InvalidArgumentException("invalid objective state: {$c['state']}");
            }
            $slot = $c['slot'] ?? null;
            $values[] = sprintf("(%d, '%s', %s)", $c['id'], $c['state'], $slot === null ? 'NULL' : (string) $slot);
        }
        if ($values !== []) {
            $this->db->execute('INSERT INTO `objective_card` (`obj_id`, `obj_state`, `obj_slot`) VALUES ' . implode(', ', $values));
        }
    }

    /**
     * @return list<array{id: int, state: string, slot: ?int, owner: ?int, claimTurn: ?int}> 枠の昇順
     */
    public function all(): array
    {
        $rows = $this->db->rows(
            'SELECT `obj_id`, `obj_state`, `obj_slot`, `obj_owner`, `obj_claim_turn` FROM `objective_card`'
            . ' ORDER BY `obj_slot` ASC, `obj_id` ASC',
        );
        return array_map(static fn(array $r): array => [
            'id' => (int) $r['obj_id'],
            'state' => (string) $r['obj_state'],
            'slot' => $r['obj_slot'] === null ? null : (int) $r['obj_slot'],
            'owner' => $r['obj_owner'] === null ? null : (int) $r['obj_owner'],
            'claimTurn' => $r['obj_claim_turn'] === null ? null : (int) $r['obj_claim_turn'],
        ], $rows);
    }

    /**
     * 目標を獲得する（OPEN → OWNED）。枠は空き枠として残る。1 ターン 1 枚の検査は TurnContext を持つ呼び出し側。
     */
    public function claim(int $objectiveId, int $playerId, int $turnNo): void
    {
        $state = $this->db->value("SELECT `obj_state` FROM `objective_card` WHERE `obj_id` = $objectiveId");
        if ($state !== self::OPEN) {
            throw new \LogicException("objective $objectiveId is not open");
        }
        $this->db->execute(
            "UPDATE `objective_card` SET `obj_state` = 'OWNED', `obj_owner` = $playerId, `obj_claim_turn` = $turnNo"
            . " WHERE `obj_id` = $objectiveId",
        );
    }
}
