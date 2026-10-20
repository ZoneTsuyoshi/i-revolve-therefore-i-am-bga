<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Data;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Material\ActionKind;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;

/**
 * `GameView`（効果エンジンが状態を読む口。008 §11.1）の本番の実装。DB の読み取りだけで、書き込みはしない。
 * 呼び出しのたびに DB を読む（キャッシュしない）ので、移動の適用の途中で状態が変わっても古い値を返さない。
 */
final class StoredGameView implements GameView
{
    public function __construct(
        private readonly MemoryCardRepository $memoryCards,
        private readonly EgoRepository $egos,
        private readonly PlayerValues $players,
        private readonly GlobalValues $globals,
    ) {
    }

    public function turnPlayer(): int
    {
        return $this->globals->turnPlayer();
    }

    public function playerIds(): array
    {
        return $this->players->playerIds();
    }

    public function handIds(int $player): array
    {
        return array_map(static fn(MemoryCard $c): int => $c->id, $this->memoryCards->inLoc(MemoryLocation::Hand, $player));
    }

    public function fieldIds(): array
    {
        return array_map(
            static fn(MemoryCard $c): int => $c->id,
            $this->memoryCards->inLocAnyArg(MemoryLocation::Open),
        );
    }

    public function deckSize(int $level): int
    {
        return $this->memoryCards->count(MemoryLocation::deck($level));
    }

    public function cardType(int $cardId): string
    {
        $card = $this->memoryCards->find($cardId) ?? throw new \OutOfBoundsException("Unknown card {$cardId}");

        return sprintf('M%03d', $card->type);
    }

    public function tableauIds(int $player): array
    {
        return array_map(static fn(MemoryCard $c): int => $c->id, $this->memoryCards->inLoc(MemoryLocation::Tableau, $player));
    }

    public function isTapped(int $cardId): bool
    {
        $card = $this->memoryCards->find($cardId);

        return $card !== null && $card->tapped;
    }

    public function egoIds(int $player): array
    {
        return array_map(
            static fn(array $e): string => $e['id'],
            $this->egos->inLoc(EgoRepository::OWNED, $player),
        );
    }

    public function roomCardId(int $position): ?int
    {
        $cards = $this->memoryCards->inLoc(MemoryLocation::Room, $position);

        return $cards === [] ? null : $cards[0]->id;
    }

    public function personaPosition(int $player): int
    {
        return $this->players->persona($player);
    }

    public function playersAt(int $position): array
    {
        return array_values(array_filter(
            $this->players->playerIds(),
            fn(int $id): bool => $this->players->persona($id) === $position,
        ));
    }

    public function recallRoomPosition(): ?int
    {
        $recall = $this->globals->turnContext()->recall;
        $room = $recall['room_pos'] ?? null;

        return is_int($room) ? $room : null;
    }

    public function roomsEnteredCount(): int
    {
        return $this->globals->turnContext()->roomsEnteredCount;
    }

    public function lv2EntriesCount(): int
    {
        return $this->globals->turnContext()->lv2EntriesCount;
    }

    public function composeActionsDone(): int
    {
        return $this->globals->turnContext()->composeActionsDone;
    }

    public function moveKeyKindsPaid(): array
    {
        return array_map(
            static fn(string $v): KeyType => KeyType::from($v),
            $this->globals->turnContext()->moveSummary['key_kinds_paid'],
        );
    }

    public function moveWildTokensUsed(): int
    {
        return $this->globals->turnContext()->moveSummary['wild_tokens_used'];
    }

    public function isOnceUsed(int $player, string $specId): bool
    {
        return $this->globals->turnContext()->isOnceUsed($player, $specId);
    }

    public function nextComposeDiscount(): int
    {
        return $this->globals->turnContext()->nextComposeDiscount;
    }

    public function turnComposeDiscount(): int
    {
        return $this->globals->turnContext()->turnComposeDiscount;
    }

    public function floorAlternatives(): array
    {
        $out = [];
        foreach ($this->globals->turnContext()->floorAlternatives as $alt) {
            $kind = ActionKind::tryFrom((string) ($alt['kind'] ?? ''));
            if ($kind !== null) {
                $out[] = ['kind' => $kind, 'discount' => (int) ($alt['discount'] ?? 0)];
            }
        }

        return $out;
    }
}
