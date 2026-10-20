<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Data;

/**
 * memory_card テーブル（想い出カード 144 枚。仕様書 007 §4）。
 * 非公開のカードの card_id と card_type を同時に外へ出さないのは呼び出し側（PublicView）の責務。
 */
final class MemoryCardRepository
{
    private const COLUMNS = '`card_id`, `card_type`, `card_loc`, `card_loc_arg`, `card_pos`, `card_tapped`, `card_face_up`';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * セットアップで行を作る。順序（card_pos）は所在ごとに 1 から振る。
     *
     * @param list<array{id: int, type: int, loc: MemoryLocation, arg?: int, pos?: int}> $cards
     */
    public function create(array $cards): void
    {
        if ($cards === []) {
            return;
        }
        $values = [];
        foreach ($cards as $c) {
            $values[] = sprintf(
                "(%d, %d, '%s', %d, %d, 0, 0)",
                $c['id'],
                $c['type'],
                $c['loc']->value,
                $c['arg'] ?? 0,
                $c['pos'] ?? 0,
            );
        }
        $this->db->execute(
            'INSERT INTO `memory_card` (' . self::COLUMNS . ') VALUES ' . implode(', ', $values),
        );
    }

    public function find(int $cardId): ?MemoryCard
    {
        $rows = $this->db->rows('SELECT ' . self::COLUMNS . " FROM `memory_card` WHERE `card_id` = $cardId");
        return $rows === [] ? null : MemoryCard::fromRow($rows[0]);
    }

    /**
     * 所在の一覧。山札は引く順（card_pos 昇順）、捨札などは card_pos 昇順（最大が一番上）。
     *
     * @return list<MemoryCard>
     */
    public function inLoc(MemoryLocation $loc, int $arg = 0): array
    {
        $rows = $this->db->rows(
            'SELECT ' . self::COLUMNS . " FROM `memory_card` WHERE `card_loc` = '{$loc->value}' AND `card_loc_arg` = $arg"
            . ' ORDER BY `card_pos` ASC, `card_id` ASC',
        );
        return array_map(MemoryCard::fromRow(...), $rows);
    }

    /**
     * 所在を問わず、ある場所すべての引数をまたいだ一覧（例: 全員の HAND）。
     *
     * @return list<MemoryCard>
     */
    public function inLocAnyArg(MemoryLocation $loc): array
    {
        $rows = $this->db->rows(
            'SELECT ' . self::COLUMNS . " FROM `memory_card` WHERE `card_loc` = '{$loc->value}'"
            . ' ORDER BY `card_loc_arg` ASC, `card_pos` ASC, `card_id` ASC',
        );
        return array_map(MemoryCard::fromRow(...), $rows);
    }

    public function count(MemoryLocation $loc, int $arg = 0): int
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM `memory_card` WHERE `card_loc` = '{$loc->value}' AND `card_loc_arg` = $arg",
        );
    }

    public function countAll(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM `memory_card`');
    }

    /**
     * 山の一番上の card_type（山が空なら null）。裏面のキーへの変換は Material を持つ呼び出し側が行い、
     * この値は外へ出さない（仕様書 007 §4.4 の topBack）。
     */
    public function topType(int $level): ?int
    {
        $v = $this->db->value(
            "SELECT `card_type` FROM `memory_card` WHERE `card_loc` = '" . MemoryLocation::deck($level)->value
            . "' ORDER BY `card_pos` ASC LIMIT 1",
        );
        return $v === null || $v === false ? null : (int) $v;
    }

    /**
     * 山札から引けるだけ引いて card_id を返す（足りなければ引けた分。山札切れの判定は呼び出し側）。
     * 引いたカードはまだ所在が変わらない。続けて move で移す。
     *
     * @return list<int>
     */
    public function peekDeck(int $level, int $n): array
    {
        if ($n <= 0) {
            return [];
        }
        $rows = $this->db->rows(
            "SELECT `card_id` FROM `memory_card` WHERE `card_loc` = '" . MemoryLocation::deck($level)->value
            . "' ORDER BY `card_pos` ASC LIMIT $n",
        );
        return array_map(static fn(array $r): int => (int) $r['card_id'], $rows);
    }

    /**
     * 山札から引けるだけ引いて、指定の所在へ移し、card_id を返す。
     *
     * @return list<int>
     */
    public function drawFromDeck(int $level, int $n, MemoryLocation $to, int $toArg = 0): array
    {
        $ids = $this->peekDeck($level, $n);
        foreach ($ids as $id) {
            $this->move($id, $to, $toArg);
        }
        return $ids;
    }

    /**
     * 所在を変える。card_pos は所在の規約に従って採番する。
     * TABLEAU 以外へ移すと card_tapped = 0、ROOM 以外へ移すと card_face_up = 0 に戻す（仕様書 007 §12.1）。
     * ROOM と OPEN は 1 か所 1 枚まで。
     */
    public function move(int $cardId, MemoryLocation $to, int $toArg = 0): void
    {
        if (($to === MemoryLocation::Room || $to === MemoryLocation::Open) && $this->count($to, $toArg) > 0) {
            throw new \LogicException("{$to->value}[$toArg] is already occupied");
        }
        $pos = match ($to) {
            MemoryLocation::Room, MemoryLocation::Open, MemoryLocation::Out, MemoryLocation::Hand => 0,
            default => $this->nextPos($to, $toArg),
        };
        $this->db->execute(
            "UPDATE `memory_card` SET `card_loc` = '{$to->value}', `card_loc_arg` = $toArg, `card_pos` = $pos,"
            . ' `card_tapped` = 0, `card_face_up` = 0 WHERE `card_id` = ' . $cardId,
        );
    }

    /**
     * 所在内の次の card_pos（最大 + 1。空なら 1）。
     */
    public function nextPos(MemoryLocation $loc, int $arg = 0): int
    {
        $max = $this->db->value(
            "SELECT MAX(`card_pos`) FROM `memory_card` WHERE `card_loc` = '{$loc->value}' AND `card_loc_arg` = $arg",
        );
        return ($max === null || $max === false ? 0 : (int) $max) + 1;
    }

    /**
     * 自己構成する。手札から TABLEAU へ移す（自己構成した順に card_pos を振る）。
     * 同名（同じ card_type）の自己構成済みは 1 人 1 枚まで。
     */
    public function compose(int $cardId, int $playerId): void
    {
        $card = $this->find($cardId);
        if ($card === null) {
            throw new \InvalidArgumentException("unknown card: $cardId");
        }
        $dup = (int) $this->db->value(
            "SELECT COUNT(*) FROM `memory_card` WHERE `card_loc` = 'TABLEAU' AND `card_loc_arg` = $playerId"
            . " AND `card_type` = {$card->type}",
        );
        if ($dup > 0) {
            throw new \LogicException("card type {$card->type} is already composed by $playerId");
        }
        $this->move($cardId, MemoryLocation::Tableau, $playerId);
    }

    /**
     * @param list<int> $ids
     */
    public function setTapped(array $ids, bool $tapped): void
    {
        if ($ids === []) {
            return;
        }
        $list = implode(', ', array_map('intval', $ids));
        $this->db->execute(
            'UPDATE `memory_card` SET `card_tapped` = ' . ($tapped ? 1 : 0)
            . " WHERE `card_id` IN ($list) AND `card_loc` = 'TABLEAU'",
        );
    }

    /**
     * 想起で部屋のカードを公開する / 伏せる。ROOM のカードだけ。
     */
    public function setFaceUp(int $cardId, bool $up): void
    {
        $this->db->execute(
            'UPDATE `memory_card` SET `card_face_up` = ' . ($up ? 1 : 0)
            . " WHERE `card_id` = $cardId AND `card_loc` = 'ROOM'",
        );
    }

    /**
     * ROOM の 2 枚の位置を入れ替える（M052〜M054）。片方に枚数が無い（補充途中）場合は例外。
     */
    public function swapRoomCards(int $posA, int $posB): void
    {
        $a = $this->inLoc(MemoryLocation::Room, $posA);
        $b = $this->inLoc(MemoryLocation::Room, $posB);
        if (count($a) !== 1 || count($b) !== 1) {
            throw new \LogicException("cannot swap room cards at $posA and $posB");
        }
        $this->db->execute(
            "UPDATE `memory_card` SET `card_loc_arg` = IF(`card_loc_arg` = $posA, $posB, $posA)"
            . " WHERE `card_loc` = 'ROOM' AND `card_loc_arg` IN ($posA, $posB)",
        );
    }

    /**
     * 自己構成済みカードと手札のカードを入れ替える（SWAP_SAME_COST）。手札から出るカードは、交換相手の
     * card_pos と card_tapped を引き継ぐ。出ていくカードは手札へ（横向きを戻す）。下のカードは捨札へ。
     *
     * @param \Closure(int): int $levelOf card_type からレベル（1 または 2）を返す
     */
    public function swapTableauHand(int $tableauId, int $handId, \Closure $levelOf): void
    {
        $t = $this->find($tableauId);
        $h = $this->find($handId);
        if (
            $t === null || $h === null
            || $t->location !== MemoryLocation::Tableau || $h->location !== MemoryLocation::Hand
            || $t->locationArg !== $h->locationArg
        ) {
            throw new \LogicException("cannot swap tableau $tableauId and hand $handId");
        }
        $dup = (int) $this->db->value(
            "SELECT COUNT(*) FROM `memory_card` WHERE `card_loc` = 'TABLEAU' AND `card_loc_arg` = {$t->locationArg}"
            . " AND `card_type` = {$h->type} AND `card_id` <> $tableauId",
        );
        if ($dup > 0) {
            throw new \LogicException("card type {$h->type} is already composed");
        }
        $this->releaseUnder($tableauId, $levelOf);
        $this->db->execute("UPDATE `memory_card` SET `card_loc` = 'HAND', `card_pos` = 0, `card_tapped` = 0 WHERE `card_id` = $tableauId");
        $this->db->execute(
            "UPDATE `memory_card` SET `card_loc` = 'TABLEAU', `card_pos` = {$t->position},"
            . ' `card_tapped` = ' . ($t->tapped ? 1 : 0) . " WHERE `card_id` = $handId",
        );
    }

    /**
     * 親カードの下のカード（UNDER_CARD）を、各レベルの捨札へ移す（捨てた順）。
     *
     * @param \Closure(int): int $levelOf card_type からレベル（1 または 2）を返す
     * @return list<int> 移した card_id
     */
    public function releaseUnder(int $parentId, \Closure $levelOf): array
    {
        $moved = [];
        foreach ($this->inLoc(MemoryLocation::UnderCard, $parentId) as $card) {
            $this->move($card->id, MemoryLocation::discard($levelOf($card->type)));
            $moved[] = $card->id;
        }
        return $moved;
    }
}
