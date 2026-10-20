<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Setup;

use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\RoomRepository;
use Bga\Games\IRevolveThereforeIAm\Flow\SeatOrder;
use Bga\Games\IRevolveThereforeIAm\Material\FloorCards;

/**
 * 人格（ミープル）の配置（001 §4 手順 14、006 の 23・24）。手番順の逆に 1 人ずつ、まだ誰もいない四隅へ置く。
 * 進み具合は setup_step（ドラフトで人数、続けて置き終えた人数が加わる）。
 */
final class PersonaPlacement
{
    public function __construct(
        private readonly PlayerValues $players,
        private readonly RoomRepository $rooms,
        private readonly GlobalValues $globals,
    ) {
    }

    /**
     * 次に置くプレイヤー（手番順の逆。全員置き終えたら null）。
     *
     * @param list<int> $seats
     */
    public function nextPlacer(array $seats): ?int
    {
        $placed = $this->globals->setupStep() - count($seats);

        return SeatOrder::reversed($seats)[max(0, $placed)] ?? null;
    }

    /**
     * まだ人格がいない四隅（位置の昇順）。
     *
     * @param list<int> $seats
     * @return list<int>
     */
    public function freeCorners(array $seats): array
    {
        $taken = array_map(fn(int $id): int => $this->players->persona($id), $seats);

        return array_values(array_diff(RoomRepository::LEVEL2_POSITIONS, $taken));
    }

    /**
     * 置く。
     *
     * @param list<int> $seats
     * @return string 部屋の名前（通知用の英語原文）
     */
    public function place(array $seats, int $playerId, int $pos): string
    {
        if ($this->nextPlacer($seats) !== $playerId) {
            throw new \LogicException("it is not player $playerId's turn to place");
        }
        if (!in_array($pos, $this->freeCorners($seats), true)) {
            throw new \InvalidArgumentException("not a free corner: $pos");
        }
        $this->players->setPersona($playerId, $pos);
        $this->globals->setSetupStep($this->globals->setupStep() + 1);

        return $this->roomName($pos);
    }

    public function roomName(int $pos): string
    {
        return FloorCards::get(sprintf('F%02d', $this->rooms->floorAt($pos)))->roomName;
    }
}
