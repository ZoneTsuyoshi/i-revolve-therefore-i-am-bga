<?php

declare(strict_types=1);

namespace IRevolveTests\Support;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameMutator;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Material\ActionKind;
use Bga\Games\IRevolveThereforeIAm\Material\AnalysisCards;
use Bga\Games\IRevolveThereforeIAm\Material\Corridor;
use Bga\Games\IRevolveThereforeIAm\Material\FloorBonus;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\Origin;
use Bga\Games\IRevolveThereforeIAm\Material\RecallGroup;
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

    /** このターンに階層を上げた精神分析アクションの回数（O006） */
    public int $analysisActions = 0;

    /** このターンに獲得した心光の合計（O005）。`gainLight` が足す */
    public int $lightsGained = 0;

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

    /** @var array<int, int> 使用済み（裏向き）の万能移動鍵トークン */
    public array $wildUsed = [];

    public bool $spiritLight = false;

    /** @var array<int, string|null> 精神カード（プレイヤー → カード ID。階層 0 は null） */
    public array $spirit = [];

    /** @var array<string, bool> 精神カード上の心光（カード ID → 置かれているか） */
    public array $spiritLights = [];

    /** @var list<string> 強化自我の山 */
    public array $enhancedDeck = [];

    /** @var array<int, string> 位置 → 床カード ID（無ければ `F` + 位置） */
    public array $floors = [];

    /** @var list<array{int, FloorBonus}> 与えた周遊ボーナス */
    public array $bonuses = [];

    /** @var list<array{int, int, bool}> 想起効果の移動の記録（プレイヤー、行き先、入室に数えるか） */
    public array $moves = [];

    /** @var list<array{int, string, string}> 精神階層を上げた記録（プレイヤー、行き先、via） */
    public array $advances = [];

    /** @var array{int, string}|null 勝利の記録（プレイヤー、理由） */
    public ?array $won = null;

    /** @var list<array{int, string, int, list<RecallGroup>}> 積まれた想起効果の発動 */
    public array $recallPushes = [];

    /** @var array<int, list<int>> 位置 → 床カードの下のカード */
    public array $under = [];

    /** @var list<array{string, mixed}> ボードに触る操作の記録（種類、内容） */
    public array $boardLog = [];

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

    public function floorUnderIds(int $position): array
    {
        return $this->under[$position] ?? [];
    }

    public function recallRoomPosition(): ?int
    {
        return $this->recallRoom;
    }

    public function roomsEnteredCount(): int
    {
        return $this->roomsEntered;
    }

    public function lightCount(int $player): int
    {
        return $this->lights[$player] ?? 0;
    }

    public function wildMoveKeyTokens(int $player): int
    {
        return ($this->tokens[$player][TokenKind::WILD_MOVE_KEY->value] ?? 0) + ($this->wildUsed[$player] ?? 0);
    }

    public function lightsGainedThisTurn(): int
    {
        return $this->lightsGained;
    }

    public function analysisActionsDone(): int
    {
        return $this->analysisActions;
    }

    public function spiritTier(int $player): int
    {
        $spirit = $this->spirit[$player] ?? null;

        return $spirit === null ? 0 : AnalysisCards::get($spirit)->floor;
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
        if ($player === $this->turnPlayer) {
            $this->lightsGained += $count;
        }
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

    public function floorIdAt(int $position): string
    {
        return $this->floors[$position] ?? sprintf('F%02d', $position);
    }

    public function spiritId(int $player): ?string
    {
        return $this->spirit[$player] ?? null;
    }

    public function nextSpiritIds(int $player): array
    {
        $current = $this->spiritId($player);
        $tier = $current === null ? 0 : AnalysisCards::get($current)->floor;
        $ids = [];
        foreach (AnalysisCards::all() as $id => $card) {
            if ($card->floor === $tier + 1) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function enhancedEgoIds(): array
    {
        return $this->enhancedDeck;
    }

    public function movePersona(int $player, int $to, bool $entry): void
    {
        $this->persona[$player] = $to;
        $this->moves[] = [$player, $to, $entry];
        if ($entry) {
            $this->roomsEntered++;
        }
    }

    public function grantFloorBonus(int $player, FloorBonus $bonus): void
    {
        $this->bonuses[] = [$player, $bonus];
    }

    public function advanceSpirit(int $player, string $spiritId, string $via): bool
    {
        $this->spirit[$player] = $spiritId;
        $this->advances[] = [$player, $spiritId, $via];

        return $this->spiritLights[$spiritId] ?? false;
    }

    public function obtainEnhancedEgo(int $player, string $egoId): void
    {
        $this->enhancedDeck = array_values(array_diff($this->enhancedDeck, [$egoId]));
        $this->egos[$player] = [...($this->egos[$player] ?? []), $egoId];
    }

    public function win(int $player, string $reason): void
    {
        $this->won = [$player, $reason];
    }

    public function pushRecallActivation(int $player, string $cardId, int $times, array $groups): void
    {
        $this->recallPushes[] = [$player, $cardId, $times, $groups];
    }

    public function isGameOver(): bool
    {
        return $this->won !== null;
    }

    public function warpPersona(int $player, int $to): void
    {
        $this->persona[$player] = $to;
        $this->boardLog[] = ['warp', [$player, $to]];
    }

    public function swapRoomCards(int $player, int $posA, int $posB): void
    {
        [$this->rooms[$posA], $this->rooms[$posB]] = [$this->rooms[$posB] ?? null, $this->rooms[$posA] ?? null];
        $this->boardLog[] = ['swapCards', [$posA, $posB]];
    }

    public function swapRoomFloors(int $player, int $posA, int $posB): void
    {
        [$this->floors[$posA], $this->floors[$posB]] = [$this->floorIdAt($posB), $this->floorIdAt($posA)];
        $this->boardLog[] = ['swapFloors', [$posA, $posB]];
    }

    public function swapHandAndRoom(int $player, int $handCardId, int $position): void
    {
        $roomCard = $this->rooms[$position] ?? null;
        $this->hand[$player] = array_values(array_diff($this->hand[$player] ?? [], [$handCardId]));
        if ($roomCard !== null) {
            $this->hand[$player][] = $roomCard;
        }
        $this->rooms[$position] = $handCardId;
        $this->boardLog[] = ['swapHandRoom', [$handCardId, $position]];
    }

    public function placeUnderFloor(int $player, array $cardIds): void
    {
        $pos = $this->persona[$player] ?? 1;
        foreach ($cardIds as $cardId) {
            $this->hand[$player] = array_values(array_diff($this->hand[$player] ?? [], [$cardId]));
            $this->under[$pos][] = $cardId;
            $pos = Corridor::previous($pos);
        }
    }

    public function collectUnderFloor(int $player, int $position): void
    {
        $this->hand[$player] = [...($this->hand[$player] ?? []), ...($this->under[$position] ?? [])];
        $this->under[$position] = [];
    }
}
