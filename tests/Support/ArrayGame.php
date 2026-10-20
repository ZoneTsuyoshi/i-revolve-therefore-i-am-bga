<?php

declare(strict_types=1);

namespace IRevolveTests\Support;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameMutator;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Material\ActionKind;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\Origin;
use Bga\Games\IRevolveThereforeIAm\Material\TokenKind;

/**
 * `GameView` / `GameMutator` のメモリ実装（配列版。008 §12、003 §10）。カードは種類（`M001` など）から個体 ID を振って置く。
 * 山札は先頭から引く。書き込みは配列に反映するだけで、通知や勝利判定は数えるだけ。
 */
final class ArrayGame implements GameView, GameMutator
{
    public int $turnPlayer = 1;

    /** @var list<int> */
    public array $players = [1, 2];

    /** @var array<int, string> 個体 ID → 種類 */
    public array $types = [];

    /** @var array<int, list<int>> */
    public array $hand = [];

    /** @var array<int, list<int>> */
    public array $tableau = [];

    /** @var array<int, bool> */
    public array $tapped = [];

    /** @var array<int, list<string>> */
    public array $egos = [];

    /** @var list<int> */
    public array $field = [];

    /** @var list<int> */
    public array $deck = [];

    /** @var list<int> */
    public array $discard = [];

    /** @var array<int, int|null> */
    public array $rooms = [];

    /** @var array<int, int> */
    public array $persona = [];

    public ?int $recallRoom = null;

    public int $roomsEntered = 0;

    public int $lv2Entries = 0;

    public int $composeActions = 0;

    /** @var list<KeyType> */
    public array $keyKindsPaid = [];

    public int $wildTokensUsed = 0;

    /** @var array<string, true> */
    public array $onceUsed = [];

    public int $nextDiscount = 0;

    public int $turnDiscount = 0;

    /** @var list<array{kind: ActionKind, discount: int}> */
    public array $alternatives = [];

    /** @var array<int, int> */
    public array $lights = [];

    /** @var list<array{int, int, string|null}> 心光を得た記録（プレイヤー、個数、origin） */
    public array $lightLog = [];

    /** @var array<int, array<string, int>> */
    public array $tokens = [];

    public bool $spiritLight = false;

    public bool $deckExhausted = false;

    public int $victoryChecks = 0;

    /** @var list<Origin|null> 山札から引いた操作の origin */
    public array $drawOrigins = [];

    private int $nextId = 1;

    /** 種類を指定してカードを 1 枚作る（どこにも置かない）。 */
    public function card(string $type): int
    {
        $id = $this->nextId++;
        $this->types[$id] = $type;

        return $id;
    }

    /**
     * @param list<string> $types
     * @return list<int>
     */
    public function cards(array $types): array
    {
        return array_map(fn (string $type): int => $this->card($type), $types);
    }

    /**
     * @param list<string> $types
     * @return list<int>
     */
    public function giveHand(int $player, array $types): array
    {
        $ids = $this->cards($types);
        $this->hand[$player] = [...($this->hand[$player] ?? []), ...$ids];

        return $ids;
    }

    /**
     * @param list<string> $types
     * @return list<int>
     */
    public function giveTableau(int $player, array $types): array
    {
        $ids = $this->cards($types);
        $this->tableau[$player] = [...($this->tableau[$player] ?? []), ...$ids];

        return $ids;
    }

    /**
     * @param list<string> $types
     * @return list<int>
     */
    public function setField(array $types): array
    {
        $this->field = $this->cards($types);

        return $this->field;
    }

    /**
     * @param list<string> $types
     */
    public function setDeck(array $types): void
    {
        $this->deck = $this->cards($types);
    }

    /**
     * @param list<string> $egoIds
     */
    public function giveEgos(int $player, array $egoIds): void
    {
        $this->egos[$player] = $egoIds;
    }

    // ---- GameView ----

    public function turnPlayer(): int
    {
        return $this->turnPlayer;
    }

    public function playerIds(): array
    {
        return $this->players;
    }

    public function handIds(int $player): array
    {
        return $this->hand[$player] ?? [];
    }

    public function fieldIds(): array
    {
        return $this->field;
    }

    public function deckSize(int $level): int
    {
        return $level === 1 ? count($this->deck) : 0;
    }

    public function cardType(int $cardId): string
    {
        return $this->types[$cardId] ?? throw new \OutOfBoundsException("Unknown card {$cardId}");
    }

    public function tableauIds(int $player): array
    {
        return $this->tableau[$player] ?? [];
    }

    public function isTapped(int $cardId): bool
    {
        return $this->tapped[$cardId] ?? false;
    }

    public function egoIds(int $player): array
    {
        return $this->egos[$player] ?? [];
    }

    public function roomCardId(int $position): ?int
    {
        return $this->rooms[$position] ?? null;
    }

    public function personaPosition(int $player): int
    {
        return $this->persona[$player] ?? 0;
    }

    public function playersAt(int $position): array
    {
        return array_values(array_filter($this->players, fn (int $p): bool => ($this->persona[$p] ?? 0) === $position));
    }

    public function recallRoomPosition(): ?int
    {
        return $this->recallRoom;
    }

    public function roomsEnteredCount(): int
    {
        return $this->roomsEntered;
    }

    public function lv2EntriesCount(): int
    {
        return $this->lv2Entries;
    }

    public function composeActionsDone(): int
    {
        return $this->composeActions;
    }

    public function moveKeyKindsPaid(): array
    {
        return $this->keyKindsPaid;
    }

    public function moveWildTokensUsed(): int
    {
        return $this->wildTokensUsed;
    }

    public function isOnceUsed(int $player, string $specId): bool
    {
        return isset($this->onceUsed["{$player}:{$specId}"]);
    }

    public function nextComposeDiscount(): int
    {
        return $this->nextDiscount;
    }

    public function turnComposeDiscount(): int
    {
        return $this->turnDiscount;
    }

    public function floorAlternatives(): array
    {
        return $this->alternatives;
    }

    // ---- GameMutator ----

    public function drawFromDeck(int $player, int $count, ?Origin $origin): int
    {
        $this->drawOrigins[] = $origin;
        $drawn = array_splice($this->deck, 0, $count);
        $this->hand[$player] = [...($this->hand[$player] ?? []), ...$drawn];

        return count($drawn);
    }

    public function flagDeckExhausted(): void
    {
        $this->deckExhausted = true;
    }

    public function takeFromField(int $player, int $cardId): void
    {
        $this->field = array_values(array_diff($this->field, [$cardId]));
        $this->hand[$player][] = $cardId;
    }

    public function gainLight(int $player, int $count, ?Origin $origin): void
    {
        $this->lights[$player] = ($this->lights[$player] ?? 0) + $count;
        $this->lightLog[] = [$player, $count, $origin?->value];
    }

    public function gainToken(int $player, TokenKind $token, int $count): void
    {
        $this->tokens[$player][$token->value] = ($this->tokens[$player][$token->value] ?? 0) + $count;
    }

    public function takeSpiritLight(int $player): bool
    {
        $had = $this->spiritLight;
        $this->spiritLight = false;

        return $had;
    }

    public function discardFromHand(int $player, array $cardIds): void
    {
        $this->hand[$player] = array_values(array_diff($this->hand[$player] ?? [], $cardIds));
        array_push($this->discard, ...$cardIds);
    }

    public function swapHandAndField(int $player, array $handIds, array $fieldIds): void
    {
        $this->hand[$player] = [...array_diff($this->hand[$player] ?? [], $handIds), ...$fieldIds];
        $this->field = [...array_diff($this->field, $fieldIds), ...$handIds];
    }

    public function swapComposedAndHand(int $player, int $tableauCardId, int $handCardId): void
    {
        $this->tableau[$player] = array_map(
            static fn (int $id): int => $id === $tableauCardId ? $handCardId : $id,
            $this->tableau[$player] ?? [],
        );
        $this->hand[$player] = array_map(
            static fn (int $id): int => $id === $handCardId ? $tableauCardId : $id,
            $this->hand[$player] ?? [],
        );
    }

    public function setTurnComposeDiscount(int $count): void
    {
        $this->turnDiscount = $count;
    }

    public function addNextComposeDiscount(int $count): void
    {
        $this->nextDiscount += $count;
    }

    public function setFloorAlternatives(array $alternatives): void
    {
        $this->alternatives = $alternatives;
    }

    public function takeRoomCard(int $player, int $position): ?int
    {
        $cardId = $this->rooms[$position] ?? null;
        if ($cardId === null) {
            return null;
        }
        $this->rooms[$position] = null;
        $this->hand[$player][] = $cardId;

        return $cardId;
    }

    public function markOnceUsed(int $player, string $specId): void
    {
        $this->onceUsed["{$player}:{$specId}"] = true;
    }

    public function checkVictory(): void
    {
        $this->victoryChecks++;
    }
}
