<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryCard;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryCardRepository;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\PublicView;
use Bga\Games\IRevolveThereforeIAm\Data\RoomRepository;
use Bga\Games\IRevolveThereforeIAm\Material\FloorCards;

/**
 * DB から移動の盤面（`MoveBoard`）を作る。読み取りだけ。
 */
final class MoveBoardReader
{
    public function __construct(
        private readonly MemoryCardRepository $memoryCards,
        private readonly RoomRepository $rooms,
        private readonly PlayerValues $players,
        private readonly GlobalValues $globals,
    ) {
    }

    public function read(int $player): MoveBoard
    {
        $locks = [];
        foreach ($this->memoryCards->inLocAnyArg(MemoryLocation::Room) as $card) {
            $locks[$card->locationArg] = PublicView::definition($card->type)->back->lock();
        }
        $others = [];
        foreach ($this->players->playerIds() as $id) {
            if ($id !== $player) {
                $others[] = $this->players->persona($id);
            }
        }
        $rooms = [];
        foreach ($this->rooms->all() as $pos => $floorNo) {
            $floor = FloorCards::get(sprintf('F%02d', $floorNo));
            $rooms[$pos] = new MoveRoom(
                $pos,
                RoomRepository::levelOfPosition($pos),
                $locks[$pos] ?? null,
                $floor->bonus,
                $floor->roomName,
                in_array($pos, $others, true),
            );
        }

        return new MoveBoard(
            $player,
            $this->players->persona($player),
            $rooms,
            $this->cards($this->memoryCards->inLoc(MemoryLocation::Hand, $player)),
            $this->cards($this->memoryCards->inLoc(MemoryLocation::Tableau, $player)),
            $this->players->light($player),
            $this->players->wildUp($player),
            $this->globals->turnContext()->lv2EntriesCount,
        );
    }

    /**
     * @param list<MemoryCard> $cards
     * @return array<int, MoveCard>
     */
    private function cards(array $cards): array
    {
        $out = [];
        foreach ($cards as $card) {
            $out[$card->id] = new MoveCard($card->id, $card->type, PublicView::definition($card->type)->keys, $card->tapped);
        }

        return $out;
    }
}
